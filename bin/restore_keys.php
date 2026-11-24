<?php
/** Restore keys from a backup:  php bin/restore_keys.php backups/<timestamp>/keys.enc   (refuses to overwrite existing key files) */
require __DIR__ . '/../config/bootstrap.php';
if (PHP_SAPI !== 'cli') { exit(1); }
$file = $argv[1] ?? '';
if (!is_file($file)) { fwrite(STDERR, "Usage: php bin/restore_keys.php <path to keys.enc>\n"); exit(1); }

$pass = getenv('SDMS_BACKUP_PASSPHRASE');
if ($pass === false || $pass === '') {
    echo "Backup passphrase: "; @shell_exec('stty -echo 2>/dev/null'); $pass = trim((string)fgets(STDIN)); @shell_exec('stty echo 2>/dev/null'); echo "\n";
}
$b = json_decode((string)file_get_contents($file), true);
try {
    $key = hash_pbkdf2('sha256', $pass, base64_decode($b['salt']), (int)$b['iter'], 32, true);
    $bundle = json_decode(CryptoService::decrypt(base64_decode($b['data']), $key, base64_decode($b['nonce']), base64_decode($b['tag']), 'sdms-key-backup'), true);
} catch (Throwable $e) {
    fwrite(STDERR, "Wrong passphrase or damaged backup file.\n"); exit(1);
}
if (!is_dir(Config::keysDir())) { mkdir(Config::keysDir(), 0700, true); }
foreach ($bundle as $name => $hex) {
    if (!preg_match('/^(kek_v\d+|hmac)\.key$/', $name) || !ctype_xdigit($hex) || strlen($hex) !== 64) { continue; }
    $path = Config::keysDir() . '/' . $name;
    if (file_exists($path)) { echo "skip (exists): $name\n"; continue; }
    file_put_contents($path, $hex); chmod($path, 0600); echo "restored: $name\n";
}
echo "Done. Restore the matching database.sql and documents/ from the SAME backup folder.\n";
