<?php
use PHPUnit\Framework\TestCase;

/** The seven adversarial scenarios required by the assignment (Part 3), plus supporting checks. All run on a scratch DB. */
class SecurityScenariosTest extends TestCase
{
    private array $alice;
    private array $bob;
    private array $admin;
    private const SECRET = 'TOP-SECRET-MARKER-4f9a: merger planned for March';

    protected function setUp(): void
    {
        TestEnv::wipeData();
        $this->alice = TestEnv::makeUser('alice');
        $this->bob = TestEnv::makeUser('bob');
        $this->admin = TestEnv::makeUser('root_admin', 'admin');
    }

    private function aliceUploads(): int
    {
        return Container::documents()->upload($this->alice, TestEnv::fakeUpload(self::SECRET, 'plan.txt'), false);
    }

    private function auditActions(): array
    {
        return array_column(Container::auditRepo()->pdo()->query('SELECT action,outcome FROM audit_logs')->fetchAll(), 'outcome', 'action');
    }

    // ---- Test 1 ----
    public function testT1UnauthorizedDocumentAccessIsDenied(): void
    {
        $id = $this->aliceUploads();
        foreach (['view', 'download'] as $mode) {
            try { Container::documents()->open($this->bob, $id, $mode); $this->fail("bob could $mode"); }
            catch (AccessDeniedException) { $this->assertTrue(true); }
        }
        $this->assertSame('denied', $this->auditActions()['unauthorized_access'] ?? null);
    }

    // ---- Test 2 ----
    public function testT2IdentifierManipulationDoesNotLeakDocuments(): void
    {
        $id = $this->aliceUploads();
        $messages = [];
        for ($try = max(1, $id - 3); $try <= $id + 10; $try++) {                   // bob walks through nearby IDs
            try { Container::documents()->open($this->bob, $try, 'view'); $this->fail("bob read document $try"); }
            catch (AccessDeniedException $e) { $messages[] = $e->getMessage(); }
        }
        $this->assertCount(1, array_unique($messages), 'existing and non-existing IDs must be indistinguishable');
    }

    // ---- Test 3 ----
    public function testT3StorageExposureRevealsNothing(): void
    {
        $id = $this->aliceUploads();
        $doc = Container::documentsRepo()->find($id);
        $file = Config::documentsDir() . '/' . $doc['storage_id'] . '.enc';
        $raw = file_get_contents($file);
        $this->assertStringNotContainsString('TOP-SECRET', $raw);
        $this->assertStringNotContainsString('merger', $raw);
        $this->assertStringNotContainsString('plan.txt', basename($file));
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}\.enc$/', basename($file));
        // An attacker holding file + DB row but WITHOUT the KEK cannot recover the DEK or the text:
        $this->expectException(IntegrityException::class);
        CryptoService::decrypt($raw, CryptoService::newKey(), $doc['doc_nonce'], $doc['auth_tag'], 'doc|' . $doc['storage_id']);
    }

    public function testT3WrappedDekCannotBeUnwrappedWithWrongKek(): void
    {
        $doc = Container::documentsRepo()->find($this->aliceUploads());
        $this->expectException(IntegrityException::class);
        CryptoService::decrypt($doc['wrapped_dek'], CryptoService::newKey(), $doc['dek_nonce'], $doc['dek_tag'], "dek|{$doc['storage_id']}|{$doc['key_version']}");
    }

    // ---- Test 4 ----
    public function testT4ModifiedCiphertextIsDetected(): void
    {
        $id = $this->aliceUploads();
        $this->assertTrue(Container::documents()->verify($this->alice, $id)['ok']);          // baseline
        $doc = Container::documentsRepo()->find($id);
        $file = Config::documentsDir() . '/' . $doc['storage_id'] . '.enc';
        $bytes = file_get_contents($file);
        $bytes[5] = chr(ord($bytes[5]) ^ 0x01);                                               // attacker flips ONE bit on disk
        file_put_contents($file, $bytes);

        $this->assertFalse(Container::documents()->verify($this->alice, $id)['ok']);
        try { Container::documents()->open($this->alice, $id, 'view'); $this->fail('tampered file was released'); }
        catch (IntegrityException) { $this->assertTrue(true); }
        $this->assertArrayHasKey('integrity_failure', $this->auditActions());
    }

    public function testT4CiphertextSwapBetweenDocumentsIsDetected(): void
    {
        $a = $this->aliceUploads();
        $b = Container::documents()->upload($this->alice, TestEnv::fakeUpload('another document', 'b.txt'), false);
        $da = Container::documentsRepo()->find($a); $db = Container::documentsRepo()->find($b);
        copy(Config::documentsDir() . "/{$db['storage_id']}.enc", Config::documentsDir() . "/{$da['storage_id']}.enc");
        $this->assertFalse(Container::documents()->verify($this->alice, $a)['ok']);
    }

    // ---- Test 5 ----
    public function testT5ViewerCannotEditDeleteShareOrDownload(): void
    {
        $id = $this->aliceUploads();
        Container::sharing()->share($this->alice, $id, (int)$this->bob['id'], 'viewer', false, null);
        $this->assertSame(self::SECRET, Container::documents()->open($this->bob, $id, 'view')[1]);   // allowed
        $upload = fn() => TestEnv::fakeUpload('hacked', 'x.txt');
        $attempts = [
            'update' => fn() => Container::documents()->update($this->bob, $id, $upload(), false),
            'delete' => fn() => Container::documents()->delete($this->bob, $id),
            'share' => fn() => Container::sharing()->share($this->bob, $id, (int)$this->admin['id'], 'viewer', false, null),
            'download' => fn() => Container::documents()->open($this->bob, $id, 'download'),
        ];
        foreach ($attempts as $name => $fn) {
            try { $fn(); $this->fail("viewer performed $name"); } catch (AccessDeniedException) { $this->assertTrue(true); }
        }
        $this->assertNotNull(Container::documentsRepo()->find($id), 'document must still exist');
        $this->assertSame('denied', $this->auditActions()['failed_authorization'] ?? null);
    }

    public function testAdminHasNoAutomaticAccessToDocumentContents(): void
    {
        $id = $this->aliceUploads();
        $this->expectException(AccessDeniedException::class);
        Container::documents()->open($this->admin, $id, 'view');
    }

    public function testEditorCanUpdateButNotDelete(): void
    {
        $id = $this->aliceUploads();
        Container::sharing()->share($this->alice, $id, (int)$this->bob['id'], 'editor', false, null);
        Container::documents()->update($this->bob, $id, TestEnv::fakeUpload('edited by bob', 'v2.txt'), false);
        $this->assertSame('edited by bob', Container::documents()->open($this->alice, $id, 'view')[1]);
        $this->expectException(AccessDeniedException::class);
        Container::documents()->delete($this->bob, $id);
    }

    // ---- Test 6 ----
    public function testT6RevokedUserLosesAccessImmediately(): void
    {
        $id = $this->aliceUploads();
        Container::sharing()->share($this->alice, $id, (int)$this->bob['id'], 'viewer', true, null);
        $this->assertSame(self::SECRET, Container::documents()->open($this->bob, $id, 'download')[1]);
        Container::sharing()->revoke($this->alice, $id, (int)$this->bob['id']);
        $this->expectException(AccessDeniedException::class);
        Container::documents()->open($this->bob, $id, 'view');
    }

    public function testExpiredPermissionIsDenied(): void
    {
        $id = $this->aliceUploads();
        Container::sharing()->share($this->alice, $id, (int)$this->bob['id'], 'viewer', false, null);
        Database::main()->exec("UPDATE document_permissions SET expires_at = '2000-01-01 00:00:00'");
        $this->expectException(AccessDeniedException::class);
        Container::documents()->open($this->bob, $id, 'view');
    }

    // ---- Test 7 ----
    private function seedAudit(): void
    {
        $this->aliceUploads();
        Container::documents()->open($this->alice, 1, 'view');
        try { Container::documents()->open($this->bob, 1, 'view'); } catch (AccessDeniedException) {}
        Container::audit()->log('logout', 'success', (int)$this->alice['id']);
    }

    public function testT7UntouchedAuditChainVerifies(): void
    {
        $this->seedAudit();
        $r = Container::audit()->verify();
        $this->assertTrue($r['ok'], implode('; ', $r['errors']));
        $this->assertGreaterThan(3, $r['checked']);
    }

    public function testT7ModifiedAuditRecordIsDetected(): void
    {
        $this->seedAudit();
        Database::main()->exec("UPDATE audit_logs SET outcome = 'success' WHERE action = 'unauthorized_access'");   // hide the denial
        $r = Container::audit()->verify();
        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('HMAC mismatch', implode(' ', $r['errors']));
    }

    public function testT7DeletedMiddleRecordBreaksTheChain(): void
    {
        $this->seedAudit();
        Database::main()->exec("DELETE FROM audit_logs WHERE action = 'document_view' LIMIT 1");
        $r = Container::audit()->verify();
        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('chain broken', implode(' ', $r['errors']));
    }

    public function testT7DeletedLastRecordsAreDetectedByAnchor(): void
    {
        $this->seedAudit();
        Database::main()->exec('DELETE FROM audit_logs ORDER BY id DESC LIMIT 1');
        $r = Container::audit()->verify();
        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('anchor', implode(' ', $r['errors']));
    }

    // ---- supporting checks ----
    public function testUploadValidationRejectsDangerousFiles(): void
    {
        $cases = [
            'php disguised as pdf' => ['<?php system($_GET[1]); ?>', 'evil.pdf'],
            'double extension' => [TestEnv::minimalPdf('x'), 'shell.php.pdf'],
            'wrong extension' => ["MZ\x90\x00 binary", 'program.exe'],
            'txt with binary' => ["abc\0def", 'a.txt'],
            'html' => ['<script>alert(1)</script>', 'page.html'],
        ];
        foreach ($cases as $label => [$content, $name]) {
            try { Container::documents()->upload($this->alice, TestEnv::fakeUpload($content, $name), false); $this->fail("accepted: $label"); }
            catch (ValidationException) { $this->assertTrue(true); }
        }
        $this->assertNotNull(Container::documents()->upload($this->alice, TestEnv::fakeUpload(TestEnv::minimalPdf('ok'), 'ok.pdf'), false));
    }

    public function testOversizeFileIsRejected(): void
    {
        $this->expectException(ValidationException::class);
        Container::documents()->upload($this->alice, TestEnv::fakeUpload(str_repeat('a', Config::MAX_UPLOAD_BYTES + 1), 'big.txt'), false);
    }

    public function testKeyRotationRewrapsDeksAndDocumentsStillDecrypt(): void
    {
        $id = $this->aliceUploads();
        $before = Container::documentsRepo()->find($id);
        $keys = Container::keys();
        $new = $keys->createNextKek();
        foreach (Container::documentsRepo()->notOnKeyVersion($new) as $d) {
            Container::documentsRepo()->updateWrap((int)$d['id'], $keys->wrapDek($keys->unwrapDek($d), $d['storage_id'], $new));
        }
        $after = Container::documentsRepo()->find($id);
        $this->assertSame($new, (int)$after['key_version']);
        $this->assertNotSame($before['wrapped_dek'], $after['wrapped_dek']);
        $this->assertNotSame($before['dek_nonce'], $after['dek_nonce']);
        $this->assertSame(self::SECRET, Container::documents()->open($this->alice, $id, 'view')[1]);
    }

    public function testKeySetupRefusesToOverwriteExistingKeys(): void
    {
        $this->expectException(RuntimeException::class);
        Container::keys()->initialize();
    }

    public function testAuditRecordsContainNoSecrets(): void
    {
        $this->seedAudit();
        $dump = json_encode(Container::auditRepo()->pdo()->query('SELECT * FROM audit_logs')->fetchAll());
        $this->assertStringNotContainsString('TOP-SECRET', $dump);
    }

    public function testLoginLockoutAfterRepeatedFailures(): void
    {
        $hash = AuthService::hashPassword('RealPassword#1234');
        Container::users()->create('victim', 'victim@test.local', $hash);
        for ($i = 0; $i < Config::LOCK_THRESHOLD; $i++) {
            try { Container::auth()->login('victim', 'wrong' . $i); } catch (AuthException) {}
        }
        $this->expectException(AuthException::class);
        $this->expectExceptionMessageMatches('/Too many/');
        Container::auth()->login('victim', 'RealPassword#1234');       // even the right password is refused while locked
    }
}
