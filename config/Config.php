<?php
/** Central place for paths and constants. No secrets are stored here. */
class Config
{
    public const MAX_UPLOAD_BYTES = 10 * 1024 * 1024; // 10 MB
    public const SESSION_TIMEOUT = 1800;              // 30 minutes of inactivity
    public const LOCK_THRESHOLD = 5;                  // failed logins per identifier
    public const IP_THRESHOLD = 20;                   // failed logins per IP
    public const LOCK_WINDOW = 900;                   // 15 minutes
    public const RESET_TOKEN_TTL = 1800;              // 30 minutes

    public static function root(): string
    {
        return dirname(__DIR__);
    }

    public static function storageDir(): string
    {
        $p = Env::get('STORAGE_PATH');
        return rtrim($p !== null && $p !== '' ? $p : self::root() . '/private_storage', '/');
    }

    public static function documentsDir(): string { return self::storageDir() . '/documents'; }
    public static function keysDir(): string      { return self::storageDir() . '/keys'; }
    public static function logsDir(): string      { return self::storageDir() . '/logs'; }
    public static function auditAnchor(): string  { return self::storageDir() . '/audit_anchor.txt'; }

    public static function isProduction(): bool
    {
        return Env::get('APP_ENV', 'development') === 'production';
    }
}
