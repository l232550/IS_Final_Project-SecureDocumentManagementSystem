<?php
/**
 * Builds an ISOLATED scratch environment (separate database + separate key/document storage) so tests and demos
 * never touch real data. Needs a DB user that may CREATE DATABASE (MAMP's root does). Uses fake, throw-away keys.
 */
class TestEnv
{
    public static function boot(string $dbName, string $storageDir): void
    {
        putenv("DB_NAME=$dbName");
        putenv("STORAGE_PATH=$storageDir");
        require_once dirname(__DIR__) . '/config/bootstrap.php';
        Container::reset();

        $admin = Database::connect(Env::get('DB_USER'), Env::get('DB_PASS'), 'information_schema');
        $admin->exec("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo = Database::main();
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $t) {
            $pdo->exec("DROP TABLE `$t`");
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        $sql = (string)file_get_contents(dirname(__DIR__) . '/database/database.sql');
        $sql = preg_replace('/--.*$/m', '', $sql);
        foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
            if (stripos($stmt, 'CREATE DATABASE') === 0 || stripos($stmt, 'USE ') === 0) { continue; }
            $pdo->exec($stmt);
        }
        self::rrmdir($storageDir);
        mkdir($storageDir, 0700, true);
        Container::keys()->initialize();
    }

    public static function wipeData(): void
    {
        $pdo = Database::main();
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach (['audit_logs', 'document_permissions', 'documents', 'password_resets', 'login_attempts', 'users'] as $t) {
            $pdo->exec("TRUNCATE TABLE `$t`");
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        foreach (glob(Config::documentsDir() . '/*') ?: [] as $f) { unlink($f); }
        @unlink(Config::auditAnchor());
    }

    public static function makeUser(string $name, string $role = 'user'): array
    {
        $id = Container::users()->create($name, "$name@test.local", password_hash('x', PASSWORD_DEFAULT), $role);
        return Container::users()->findById($id);
    }

    /** Builds a $_FILES-style array from in-memory content (the temp file is removed by the caller or by PHP). */
    public static function fakeUpload(string $content, string $name): array
    {
        $tmp = tempnam(sys_get_temp_dir(), 'sdms');
        file_put_contents($tmp, $content);
        return ['name' => $name, 'tmp_name' => $tmp, 'error' => UPLOAD_ERR_OK, 'size' => strlen($content), 'type' => ''];
    }

    public static function minimalPdf(string $text): string
    {
        return "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n"
            . "3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 200 200]>>endobj\n% $text\ntrailer<</Root 1 0 R>>\n%%EOF\n";
    }

    public static function rrmdir(string $d): void
    {
        if (!is_dir($d)) { return; }
        foreach (scandir($d) as $f) {
            if ($f === '.' || $f === '..') { continue; }
            is_dir("$d/$f") ? self::rrmdir("$d/$f") : unlink("$d/$f");
        }
        rmdir($d);
    }
}
