<?php
/** Create an administrator:  php bin/create_admin.php <username> <email>   (password is prompted) */
require __DIR__ . '/../config/bootstrap.php';
if (PHP_SAPI !== 'cli') { exit(1); }

[$script, $username, $email] = $argv + [null, null, null];
if (!$username || !$email) { fwrite(STDERR, "Usage: php bin/create_admin.php <username> <email>\n"); exit(1); }

echo "Password (min 10 chars, upper+lower+digit): ";
@shell_exec('stty -echo 2>/dev/null');
$password = trim((string)fgets(STDIN));
@shell_exec('stty echo 2>/dev/null');
echo "\n";
try {
    if (!preg_match('/^[A-Za-z0-9_]{3,30}$/', $username) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new ValidationException('Invalid username or email.');
    }
    AuthService::checkPasswordStrength($password);
    if (Container::users()->usernameOrEmailExists($username, $email)) {
        throw new ValidationException('Username or email already exists.');
    }
    $id = Container::users()->create($username, $email, AuthService::hashPassword($password), 'admin');
    Container::audit()->log('admin_created', 'success', $id);
    echo "Administrator '$username' created (id $id).\n";
} catch (ValidationException $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
