<?php
/**
 * Runs the seven assignment scenarios on an ISOLATED scratch database/storage and prints readable evidence.
 *   php bin/security_demo.php | tee documentation/evidence/security_demo_output.txt
 * It never touches your real database or keys (uses DB "sdms_demo" and tests/_tmp/demo_storage).
 */
require __DIR__ . '/../tests/TestEnv.php';
if (PHP_SAPI !== 'cli') { exit(1); }
TestEnv::boot('sdms_demo', dirname(__DIR__) . '/tests/_tmp/demo_storage');

function h(string $t): void { echo "\n" . str_repeat('=', 78) . "\n$t\n" . str_repeat('=', 78) . "\n"; }
function line(string $k, string $v): void { echo str_pad($k, 12) . ': ' . $v . "\n"; }
function attempt(string $label, callable $fn): string {
    try { $fn(); return "$label -> ALLOWED"; }
    catch (AccessDeniedException $e) { return "$label -> DENIED ({$e->getMessage()})"; }
    catch (IntegrityException $e) { return "$label -> REJECTED ({$e->getMessage()})"; }
}
function lastAudit(int $n): void {
    $rows = array_reverse(Container::auditRepo()->page($n, 0, null, null));
    foreach ($rows as $r) {
        printf("  #%-3d %-22s user=%-3s doc=%-3s %-8s hmac=%s...\n", $r['id'], $r['action'], $r['user_id'] ?? '-', $r['document_id'] ?? '-', $r['outcome'], substr($r['record_hmac'], 0, 12));
    }
}
function reset_(): array {
    TestEnv::wipeData();
    return [TestEnv::makeUser('alice'), TestEnv::makeUser('bob'), TestEnv::makeUser('root_admin', 'admin')];
}
$SECRET = "CONFIDENTIAL: Project Falcon budget is 4.2 million. Do not share.";
$docs = Container::documents();

// ---------------------------------------------------------------- Test 1
h('TEST 1: Unauthorized document access');
[$alice, $bob] = reset_();
$id = $docs->upload($alice, TestEnv::fakeUpload($SECRET, 'falcon_budget.txt'), false);
line('Threat', 'A logged-in user reads another user\'s private document');
line('Asset', "Document #$id owned by alice");
echo attempt('bob views doc', fn() => $docs->open($bob, $id, 'view')) . "\n";
echo attempt('bob downloads doc', fn() => $docs->open($bob, $id, 'download')) . "\n";
echo attempt('alice views doc', fn() => $docs->open($alice, $id, 'view')) . "  (owner, expected)\n";
echo "Audit trail:\n"; lastAudit(4);

// ---------------------------------------------------------------- Test 2
h('TEST 2: Identifier manipulation');
[$alice, $bob] = reset_();
$id = $docs->upload($alice, TestEnv::fakeUpload($SECRET, 'falcon_budget.txt'), false);
line('Threat', 'Attacker edits the document id in the request (?id=N) to reach another user\'s file');
$out = [];
for ($i = $id; $i <= $id + 3; $i++) { $out[] = attempt("bob requests id=$i", fn() => $docs->open($bob, $i, 'view')); }
$out[] = attempt('bob requests id=999999', fn() => $docs->open($bob, 999999, 'view'));
echo implode("\n", $out) . "\n";
echo "Existing and non-existing ids give the SAME answer, so ids cannot be enumerated.\n";
echo "Audit trail:\n"; lastAudit(5);

// ---------------------------------------------------------------- Test 3
h('TEST 3: Storage exposure');
[$alice, $bob] = reset_();
$id = $docs->upload($alice, TestEnv::fakeUpload($SECRET, 'falcon_budget.txt'), false);
$doc = Container::documentsRepo()->find($id);
$file = Config::documentsDir() . '/' . $doc['storage_id'] . '.enc';
$raw = file_get_contents($file);
line('Threat', 'Attacker copies the private storage folder (and even the DB row)');
line('File on disk', basename($file) . ' (random UUID, original name not visible)');
line('Size', strlen($raw) . ' bytes (plaintext was ' . strlen($SECRET) . ')');
line('Hex dump', chunk_split(bin2hex(substr($raw, 0, 32)), 8, ' '));
line('Contains text', (str_contains($raw, 'Falcon') || str_contains($raw, 'CONFIDENTIAL')) ? 'YES (BAD)' : 'no - plaintext not present in the file');
try { CryptoService::decrypt($raw, CryptoService::newKey(), $doc['doc_nonce'], $doc['auth_tag'], 'doc|' . $doc['storage_id']); echo "decrypt with guessed key: SUCCEEDED (BAD)\n"; }
catch (IntegrityException $e) { echo "decrypt with a guessed key : FAILED  ({$e->getMessage()})\n"; }
echo "What CAN be learned: file size (approx), number of files, upload timing (file mtime), and from the DB: original filenames, owners, types, sizes.\n";
echo "What CANNOT be learned: contents. The DEK is stored only wrapped by the KEK, and the KEK lives outside the DB/storage in private_storage/keys.\n";

// ---------------------------------------------------------------- Test 4
h('TEST 4: Document modification outside the application');
[$alice, $bob] = reset_();
$id = $docs->upload($alice, TestEnv::fakeUpload($SECRET, 'falcon_budget.txt'), false);
$doc = Container::documentsRepo()->find($id);
$file = Config::documentsDir() . '/' . $doc['storage_id'] . '.enc';
$r = $docs->verify($alice, $id);
line('Before tamper', ($r['ok'] ? 'OK' : 'FAIL') . ' - ' . $r['message']);
$bytes = file_get_contents($file); $bytes[7] = chr(ord($bytes[7]) ^ 0x01); file_put_contents($file, $bytes);
line('Attack', 'flipped ONE bit at byte offset 7 of the stored encrypted file');
$r = $docs->verify($alice, $id);
line('After tamper', ($r['ok'] ? 'OK (BAD)' : 'DETECTED') . ' - ' . $r['message']);
echo attempt('alice opens tampered doc', fn() => $docs->open($alice, $id, 'view')) . "\n";
echo "Audit trail:\n"; lastAudit(5);

// ---------------------------------------------------------------- Test 5
h('TEST 5: Privilege misuse');
[$alice, $bob, $admin] = reset_();
$id = $docs->upload($alice, TestEnv::fakeUpload($SECRET, 'falcon_budget.txt'), false);
Container::sharing()->share($alice, $id, (int)$bob['id'], 'viewer', false, null);
line('Setup', 'alice shared the document with bob as VIEWER (no download)');
echo attempt('bob views', fn() => $docs->open($bob, $id, 'view')) . "  (expected: allowed)\n";
echo attempt('bob downloads', fn() => $docs->open($bob, $id, 'download')) . "\n";
echo attempt('bob updates', fn() => $docs->update($bob, $id, TestEnv::fakeUpload('hacked', 'x.txt'), false)) . "\n";
echo attempt('bob deletes', fn() => $docs->delete($bob, $id)) . "\n";
echo attempt('bob re-shares', fn() => Container::sharing()->share($bob, $id, (int)$admin['id'], 'editor', true, null)) . "\n";
echo attempt('ADMIN views alice\'s doc', fn() => $docs->open($admin, $id, 'view')) . "  (admin has no automatic content access)\n";
echo "Document still exists: " . (Container::documentsRepo()->find($id) ? 'yes' : 'NO') . "\n";
echo "Audit trail:\n"; lastAudit(6);

// ---------------------------------------------------------------- Test 6
h('TEST 6: Permission revocation');
[$alice, $bob] = reset_();
$id = $docs->upload($alice, TestEnv::fakeUpload($SECRET, 'falcon_budget.txt'), false);
Container::sharing()->share($alice, $id, (int)$bob['id'], 'viewer', true, null);
echo attempt('bob downloads while shared', function () use ($docs, $bob, $id, &$copy) { $copy = $docs->open($bob, $id, 'download')[1]; }) . "\n";
Container::sharing()->revoke($alice, $id, (int)$bob['id']);
line('Action', 'alice revoked bob\'s access');
echo attempt('bob views after revoke', fn() => $docs->open($bob, $id, 'view')) . "\n";
echo attempt('bob downloads after revoke', fn() => $docs->open($bob, $id, 'download')) . "\n";
echo "LIMITATION: bob's earlier downloaded copy still exists on his machine (" . strlen((string)$copy) . " bytes). Revocation stops FUTURE access only.\n";
echo "Audit trail:\n"; lastAudit(6);

// ---------------------------------------------------------------- Test 7
h('TEST 7: Audit tampering');
[$alice, $bob] = reset_();
$id = $docs->upload($alice, TestEnv::fakeUpload($SECRET, 'falcon_budget.txt'), false);
try { $docs->open($bob, $id, 'view'); } catch (AccessDeniedException) {}
$docs->open($alice, $id, 'view');
Container::audit()->log('logout', 'success', (int)$alice['id']);
$db = Database::main();
$snap = $db->query('SELECT * FROM audit_logs ORDER BY id')->fetchAll();
$restore = function () use ($db, $snap) {
    $db->exec('TRUNCATE TABLE audit_logs');
    foreach ($snap as $row) { Container::auditRepo()->insert($row); }
    file_put_contents(Config::auditAnchor(), end($snap)['id'] . ':' . end($snap)['record_hmac']);
};
function verdict(array $r): string { return $r['ok'] ? 'VALID (no tampering)' : 'TAMPERING DETECTED: ' . $r['errors'][0]; }
line('Baseline', verdict(Container::audit()->verify()) . ' - ' . count($snap) . ' records');

$db->exec("UPDATE audit_logs SET outcome='success' WHERE action='unauthorized_access'");
line('Attack A', "modify a record (change the denied outcome of bob's attempt to 'success')");
line('Result A', verdict(Container::audit()->verify())); $restore();

$db->exec("DELETE FROM audit_logs WHERE action='document_upload'");
line('Attack B', 'delete a record from the middle of the log');
line('Result B', verdict(Container::audit()->verify())); $restore();

$db->exec('DELETE FROM audit_logs ORDER BY id DESC LIMIT 1');
line('Attack C', 'delete the newest record (truncate the tail)');
line('Result C', verdict(Container::audit()->verify())); $restore();
line('Restored', verdict(Container::audit()->verify()));
echo "LIMITATION: an attacker who controls BOTH the database and private_storage/keys/hmac.key can recompute every HMAC and forge a valid chain.\n";
echo "\nDone. (Scratch data only: database sdms_demo and tests/_tmp/demo_storage.)\n";
