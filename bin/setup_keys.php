<?php
/** One-time key setup:  php bin/setup_keys.php   (refuses to overwrite existing keys) */
require __DIR__ . '/../config/bootstrap.php';
if (PHP_SAPI !== 'cli') { exit(1); }

try {
    Container::keys()->initialize();
} catch (Throwable $e) {
    fwrite(STDERR, "Setup stopped: " . $e->getMessage() . "\n");
    exit(1);
}
Container::audit()->log('key_generated', 'success', null, null, ['kek_version' => 1, 'hmac_key' => 'created']);
echo "KEK v1 and HMAC key created in " . Config::keysDir() . " (permissions 600).\n";
echo "IMPORTANT: back them up now with:  php bin/backup.php   (keys are NOT in the database and cannot be recovered)\n";
