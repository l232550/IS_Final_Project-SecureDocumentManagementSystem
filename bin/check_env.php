<?php
/** Pre-flight check:  php bin/check_env.php  - tells you what is missing before you start the app. */
require __DIR__ . '/../config/bootstrap.php';
if (PHP_SAPI !== 'cli') { exit(1); }
$bad = 0;
function check(string $label, bool $ok, string $hint = ''): void {
    global $bad; if (!$ok) { $bad++; }
    echo ($ok ? '[ OK ] ' : '[FAIL] ') . $label . ($ok || $hint === '' ? '' : "  -> $hint") . "\n";
}
check('PHP >= 8.3 (found ' . PHP_VERSION . ')', version_compare(PHP_VERSION, '8.3.0', '>='), 'select PHP 8.3.x in MAMP');
foreach (['openssl', 'pdo_mysql', 'fileinfo', 'mbstring'] as $e) { check("extension $e", extension_loaded($e), 'enable it in php.ini'); }
check('Argon2id available', defined('PASSWORD_ARGON2ID'), 'PHP must be built with Argon2 support');
check('AES-256-GCM available', in_array('aes-256-gcm', openssl_get_cipher_methods(), true));
$cli = php_ini_loaded_file();
echo "[INFO] php.ini for CLI: " . ($cli ?: 'none') . " (the web server may use a different php.ini; make sure upload_max_filesize >= 10M and post_max_size >= 11M there)\n";
check('.env present', is_file(Config::root() . '/.env'), 'copy .env.example to .env and edit it');
try { Database::main()->query('SELECT 1'); check('database connection', true); $tables = Database::main()->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN); check('schema imported (' . count($tables) . ' tables)', count($tables) >= 7, 'import database/database.sql'); }
catch (Throwable $e) { check('database connection', false, 'check DB_* values in .env (MAMP: port 8889, root/root)'); }
foreach (['documents' => Config::documentsDir(), 'logs' => Config::logsDir()] as $n => $d) { check("storage/$n writable", is_dir($d) && is_writable($d), "create and make writable: $d"); }
check('key files present', is_file(Config::keysDir() . '/kek_v1.key') && is_file(Config::keysDir() . '/hmac.key'), 'run: php bin/setup_keys.php');
$pub = realpath(Config::root() . '/public');
check('private_storage is outside public/', strpos((string)realpath(Config::storageDir()), (string)$pub) !== 0);
echo $bad === 0 ? "\nAll checks passed.\n" : "\n$bad check(s) failed.\n";
exit($bad === 0 ? 0 : 1);
