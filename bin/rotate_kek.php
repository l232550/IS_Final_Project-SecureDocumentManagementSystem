<?php
/**
 * Controlled KEK rotation:  php bin/rotate_kek.php
 *  1. creates the next KEK version (old key files are KEPT, they are needed for backups),
 *  2. unwraps every DEK with its old KEK and rewraps it with the new KEK (fresh nonce each time),
 *  3. never touches the encrypted document files (the DEKs do not change).
 * Safe to re-run: if interrupted it just rewraps the documents that are still on an old version.
 */
require __DIR__ . '/../config/bootstrap.php';
if (PHP_SAPI !== 'cli') { exit(1); }

$keys = Container::keys();
$docs = Container::documentsRepo();
$audit = Container::audit();
$resume = in_array('--resume', $argv, true);

try {
    $active = $keys->activeVersion();
    $pending = $docs->notOnKeyVersion($active);
    if ($pending === [] || !$resume) {
        if ($resume && $pending === []) { echo "Nothing to resume.\n"; exit(0); }
        $new = $keys->createNextKek();
        $audit->log('kek_rotated', 'success', null, null, ['from' => $active, 'to' => $new]);
        echo "Created KEK v$new (v$active kept for recovery).\n";
        $active = $new;
        $pending = $docs->notOnKeyVersion($active);
    }
    $n = 0;
    foreach ($pending as $doc) {
        $dek = $keys->unwrapDek($doc);
        $docs->updateWrap((int)$doc['id'], $keys->wrapDek($dek, $doc['storage_id'], $active));
        $n++;
    }
    $audit->log('deks_rewrapped', 'success', null, null, ['count' => $n, 'kek_version' => $active]);
    echo "Rewrapped $n document key(s) to KEK v$active.\n";
    echo "Keep old KEK files until every backup that needs them has been retired.\n";
} catch (Throwable $e) {
    error_log('rotate_kek: ' . $e->getMessage());
    $audit->log('kek_rotated', 'failure', null, null, ['error' => 'rotation_failed']);
    fwrite(STDERR, "Rotation failed (see server log). Re-run with --resume.\n");
    exit(1);
}
