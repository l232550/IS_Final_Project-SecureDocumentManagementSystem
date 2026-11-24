<?php
/** Tiny service locator: builds each service once so controllers and scripts stay simple. */
class Container
{
    private static array $items = [];

    private static function once(string $k, callable $f): mixed { return self::$items[$k] ??= $f(); }

    public static function reset(): void { self::$items = []; Database::reset(); }

    public static function keys(): KeyManager
    {
        return self::once('keys', fn() => new KeyManager(Config::keysDir(), new KeyVersionRepository(Database::main())));
    }

    public static function storage(): StorageService
    {
        return self::once('storage', fn() => new StorageService(Config::documentsDir()));
    }

    public static function audit(): AuditService
    {
        return self::once('audit', fn() => new AuditService(self::auditRepo(), self::keys()->hmacKey(), Config::auditAnchor()));
    }

    public static function auditRepo(): AuditRepository
    {
        return self::once('auditRepo', fn() => new AuditRepository(Database::audit()));
    }

    public static function users(): UserRepository { return self::once('users', fn() => new UserRepository(Database::main())); }
    public static function documentsRepo(): DocumentRepository { return self::once('docsRepo', fn() => new DocumentRepository(Database::main())); }
    public static function permissions(): PermissionRepository { return self::once('perms', fn() => new PermissionRepository(Database::main())); }
    public static function attempts(): LoginAttemptRepository { return self::once('attempts', fn() => new LoginAttemptRepository(Database::main())); }
    public static function resets(): PasswordResetRepository { return self::once('resets', fn() => new PasswordResetRepository(Database::main())); }

    public static function authz(): AuthorizationService
    {
        return self::once('authz', fn() => new AuthorizationService(self::permissions()));
    }

    public static function documents(): DocumentService
    {
        return self::once('docs', fn() => new DocumentService(self::documentsRepo(), self::authz(), self::keys(), self::storage(), self::audit()));
    }

    public static function sharing(): ShareService
    {
        return self::once('share', fn() => new ShareService(self::documents(), self::permissions(), self::users(), self::audit()));
    }

    public static function auth(): AuthService
    {
        return self::once('auth', fn() => new AuthService(self::users(), self::attempts(), self::resets(), self::audit()));
    }
}
