<?php
/** PDO connections. Always prepared statements, exceptions on error. */
class Database
{
    private static ?PDO $main = null;
    private static ?PDO $audit = null;

    public static function main(): PDO
    {
        return self::$main ??= self::connect(Env::get('DB_USER', 'root'), Env::get('DB_PASS', ''));
    }

    /** Separate low-privilege connection for audit_logs when DB_AUDIT_USER is set. */
    public static function audit(): PDO
    {
        $user = Env::get('DB_AUDIT_USER', '');
        if ($user === '' || $user === null) {
            return self::main();
        }
        return self::$audit ??= self::connect($user, Env::get('DB_AUDIT_PASS', ''));
    }

    public static function connect(?string $user, ?string $pass, ?string $dbname = null): PDO
    {
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            Env::get('DB_HOST', '127.0.0.1'), Env::get('DB_PORT', '3306'), $dbname ?? Env::get('DB_NAME', 'sdms'));
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $pdo->exec("SET time_zone = '" . date('P') . "'");
        return $pdo;
    }

    public static function reset(): void
    {
        self::$main = null;
        self::$audit = null;
    }
}
