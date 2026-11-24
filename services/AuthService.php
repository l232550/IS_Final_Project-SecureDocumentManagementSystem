<?php
/** Registration, login (Argon2id, rate limiting, lockout), logout and password reset. */
class AuthService
{
    private static ?string $dummyHash = null;

    public function __construct(
        private UserRepository $users,
        private LoginAttemptRepository $attempts,
        private PasswordResetRepository $resets,
        private AuditService $audit
    ) {}

    public static function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_ARGON2ID, ['memory_cost' => 65536, 'time_cost' => 3, 'threads' => 1]);
    }

    public static function checkPasswordStrength(string $p): void
    {
        if (strlen($p) < 10 || !preg_match('/[a-z]/', $p) || !preg_match('/[A-Z]/', $p) || !preg_match('/\d/', $p)) {
            throw new ValidationException('Password must be at least 10 characters and contain upper-case, lower-case and a digit.');
        }
        if (strlen($p) > 200) {
            throw new ValidationException('Password is too long.');
        }
    }

    public function register(string $username, string $email, string $password, string $confirm): int
    {
        $username = trim($username);
        $email = strtolower(trim($email));
        if (!preg_match('/^[A-Za-z0-9_]{3,30}$/', $username)) {
            throw new ValidationException('Username must be 3-30 letters, digits or underscores.');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
            throw new ValidationException('Please enter a valid email address.');
        }
        if ($password !== $confirm) {
            throw new ValidationException('Passwords do not match.');
        }
        self::checkPasswordStrength($password);
        if ($this->users->usernameOrEmailExists($username, $email)) {
            throw new ValidationException('That username or email is already registered.');
        }
        $id = $this->users->create($username, $email, self::hashPassword($password), 'user');
        $this->audit->log('user_registered', 'success', $id);
        return $id;
    }

    private function isLockedOut(string $identifier, string $ip): bool
    {
        return $this->attempts->recentFailuresFor($identifier, Config::LOCK_WINDOW) >= Config::LOCK_THRESHOLD
            || $this->attempts->recentFailuresFromIp($ip, Config::LOCK_WINDOW) >= Config::IP_THRESHOLD;
    }

    /** @return array the logged-in user row */
    public function login(string $identifier, string $password): array
    {
        $identifier = trim($identifier);
        $ip = substr($_SERVER['REMOTE_ADDR'] ?? 'cli', 0, 45);

        $user = $this->users->findByLogin($identifier);
        // Lockout key = the account (so username and email count together); unknown names use the typed text.
        $key = $user !== null ? 'uid:' . $user['id'] : strtolower($identifier);
        if ($this->isLockedOut($key, $ip)) {
            $this->audit->log('login_failed', 'denied', $user['id'] ?? null, null, ['reason' => 'rate_limited']);
            throw new AuthException('Too many failed attempts. Please try again in 15 minutes.');
        }
        self::$dummyHash ??= self::hashPassword(bin2hex(random_bytes(8)));
        $valid = password_verify($password, $user['password_hash'] ?? self::$dummyHash);   // constant work even for unknown users
        if ($user === null || !$valid || (int)$user['is_active'] !== 1) {
            $this->attempts->record($key, $ip, false);
            $this->audit->log('login_failed', 'failure', $user['id'] ?? null, null, ['reason' => 'bad_credentials']);
            throw new AuthException('Invalid credentials.');
        }
        $this->attempts->clearFor($key);
        if (password_needs_rehash($user['password_hash'], PASSWORD_ARGON2ID, ['memory_cost' => 65536, 'time_cost' => 3, 'threads' => 1])) {
            $this->users->updatePassword((int)$user['id'], self::hashPassword($password));
        }
        $this->users->touchLogin((int)$user['id']);
        if (session_status() === PHP_SESSION_ACTIVE) {
            SessionService::login((int)$user['id']);
        }
        $this->audit->log('login_success', 'success', (int)$user['id']);
        return $user;
    }

    public function logout(?array $user): void
    {
        if ($user !== null) {
            $this->audit->log('logout', 'success', (int)$user['id']);
        }
        SessionService::destroy();
    }

    /** Always behaves the same whether or not the email exists (no account enumeration). */
    public function requestReset(string $email): void
    {
        $user = $this->users->findByEmail(trim($email));
        if ($user === null || (int)$user['is_active'] !== 1) {
            $this->audit->log('password_reset_requested', 'failure', null, null, ['reason' => 'unknown_account']);
            return;
        }
        $token = bin2hex(random_bytes(32));
        $this->resets->create((int)$user['id'], hash('sha256', $token), date('Y-m-d H:i:s', time() + Config::RESET_TOKEN_TTL));
        // No mail server in the development setup: the link is written to a protected server-side log instead.
        $base = rtrim((string)Env::get('APP_URL', 'http://localhost/public'), '/');
        $line = date('c') . " RESET LINK for {$user['email']}: $base/index.php?r=reset&token=$token\n";
        file_put_contents(Config::logsDir() . '/mail.log', $line, FILE_APPEND | LOCK_EX);
        @chmod(Config::logsDir() . '/mail.log', 0600);
        $this->audit->log('password_reset_requested', 'success', (int)$user['id']);
    }

    public function tokenIsValid(string $token): bool
    {
        return preg_match('/^[0-9a-f]{64}$/', $token) === 1 && $this->resets->findValid(hash('sha256', $token)) !== null;
    }

    public function resetPassword(string $token, string $password, string $confirm): void
    {
        if ($password !== $confirm) {
            throw new ValidationException('Passwords do not match.');
        }
        self::checkPasswordStrength($password);
        $row = preg_match('/^[0-9a-f]{64}$/', $token) === 1 ? $this->resets->findValid(hash('sha256', $token)) : null;
        if ($row === null || !$this->resets->markUsed((int)$row['id'])) {      // single use, atomic
            $this->audit->log('password_reset', 'failure', null, null, ['reason' => 'invalid_token']);
            throw new AuthException('This reset link is invalid or has expired.');
        }
        $this->users->updatePassword((int)$row['user_id'], self::hashPassword($password));
        $this->attempts->clearFor('uid:' . (int)$row['user_id']);
        $this->audit->log('password_reset', 'success', (int)$row['user_id']);
    }
}
