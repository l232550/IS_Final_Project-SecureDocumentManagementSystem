<?php
/**
 * Backup:  php bin/backup.php [--passphrase-env=SDMS_BACKUP_PASSPHRASE]
 * Creates backups/<timestamp>/ with
 *   database.sql          (mysqldump of metadata, wrapped DEKs, audit log)
 *   documents/            (copy of the ENCRYPTED files; still ciphertext)
 *   keys.enc              (all KEK versions + HMAC key, encrypted with AES-256-GCM under a key derived from YOUR passphrase)
 * The three parts belong together: restore them together (see documentation/backup_recovery.md).
 */
require __DIR__ . '/../config/bootstrap.php';
if (PHP_SAPI !== 'cli') { exit(1); }

$pass = getenv('SDMS_BACKUP_PASSPHRASE');
if ($pass === false || $pass === '') {
    echo "Backup passphrase for the key file (not stored anywhere): ";
    @shell_exec('stty -echo 2>/dev/null'); $pass = trim((string)fgets(STDIN)); @shell_exec('stty echo 2>/dev/null'); echo "\n";
}
if (strlen($pass) < 12) { fwrite(STDERR, "Passphrase must be at least 12 characters.\n"); exit(1); }

$dir = Config::root() . '/backups/' . date('Ymd_His');
mkdir($dir . '/documents', 0700, true);

// 1) database
$dump = Env::get('MYSQLDUMP_PATH', 'mysqldump');
$cmd = sprintf('%s --host=%s --port=%s --user=%s --single-transaction --routines %s > %s',
    escapeshellarg($dump), escapeshellarg((string)Env::get('DB_HOST')), escapeshellarg((string)Env::get('DB_PORT')),
    escapeshellarg((string)Env::get('DB_USER')), escapeshellarg((string)Env::get('DB_NAME')), escapeshellarg($dir . '/database.sql'));
putenv('MYSQL_PWD=' . Env::get('DB_PASS', ''));          // password passed via environment, not on the command line
exec($cmd, $o, $rc);
putenv('MYSQL_PWD');
if ($rc !== 0) { fwrite(STDERR, "mysqldump failed. Check MYSQLDUMP_PATH in .env.\n"); exit(1); }
chmod($dir . '/database.sql', 0600);

// 2) encrypted documents
$n = 0;
foreach (glob(Config::documentsDir() . '/*.enc') ?: [] as $f) { copy($f, $dir . '/documents/' . basename($f)); $n++; }

// 3) keys, protected by a passphrase-derived key (PBKDF2-HMAC-SHA256, 600k iterations) + AES-256-GCM
$bundle = [];
foreach (glob(Config::keysDir() . '/*.key') ?: [] as $f) { $bundle[basename($f)] = trim((string)file_get_contents($f)); }
if (!isset($bundle['hmac.key'])) { fwrite(STDERR, "No keys found to back up.\n"); exit(1); }
$salt = random_bytes(16);
$iter = 600000;
$key = hash_pbkdf2('sha256', $pass, $salt, $iter, 32, true);
$enc = CryptoService::encrypt(json_encode($bundle), $key, 'sdms-key-backup');
file_put_contents($dir . '/keys.enc', json_encode([
    'v' => 1, 'kdf' => 'PBKDF2-HMAC-SHA256', 'iter' => $iter, 'salt' => base64_encode($salt),
    'nonce' => base64_encode($enc['nonce']), 'tag' => base64_encode($enc['tag']), 'data' => base64_encode($enc['ciphertext']),
]));
chmod($dir . '/keys.enc', 0600);

Container::audit()->log('key_backup_created', 'success', null, null, ['files' => $n, 'kek_versions' => count($bundle) - 1]);
echo "Backup written to $dir\n  database.sql, $n encrypted document(s), keys.enc (" . (count($bundle) - 1) . " KEK version(s) + HMAC key)\n";
echo "Store keys.enc and the passphrase in DIFFERENT places from the database dump.\n";
