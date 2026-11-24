<?php
/** Secure session handling: HttpOnly + SameSite + Secure(on HTTPS) cookie, strict mode, 30-minute inactivity timeout. */
class SessionService
{
    public static function isHttps(): bool
    {
        return !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    }

    /** @return bool true if the session had expired (and was reset) */
    public static function start(): bool
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return false;
        }
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');
        session_name('SDMSSESSID');
        session_set_cookie_params([
            'lifetime' => 0, 'path' => '/', 'secure' => self::isHttps(), 'httponly' => true, 'samesite' => 'Strict',
        ]);
        session_start();

        $expired = false;
        if (isset($_SESSION['user_id'], $_SESSION['last_activity']) && time() - (int)$_SESSION['last_activity'] > Config::SESSION_TIMEOUT) {
            self::destroy();
            session_start();
            $expired = true;
        }
        if (isset($_SESSION['user_id'])) {
            $_SESSION['last_activity'] = time();
        }
        return $expired;
    }

    public static function login(int $userId): void
    {
        session_regenerate_id(true);        // prevents session fixation
        $_SESSION = ['user_id' => $userId, 'last_activity' => time(), 'csrf' => bin2hex(random_bytes(32))];
    }

    public static function destroy(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', ['expires' => time() - 42000, 'path' => $p['path'], 'secure' => $p['secure'], 'httponly' => true, 'samesite' => 'Strict']);
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    public static function flash(string $type, string $msg): void
    {
        $_SESSION['flash'][] = ['type' => $type, 'msg' => $msg];
    }

    public static function takeFlash(): array
    {
        $f = $_SESSION['flash'] ?? [];
        unset($_SESSION['flash']);
        return $f;
    }
}
