<?php
use PHPUnit\Framework\TestCase;

class CryptoServiceTest extends TestCase
{
    public function testRoundTrip(): void
    {
        $key = CryptoService::newKey();
        $r = CryptoService::encrypt('hello world', $key, 'ctx');
        $this->assertSame(12, strlen($r['nonce']));
        $this->assertSame(16, strlen($r['tag']));
        $this->assertSame('hello world', CryptoService::decrypt($r['ciphertext'], $key, $r['nonce'], $r['tag'], 'ctx'));
    }

    public function testNonceIsFreshEveryTime(): void
    {
        $key = CryptoService::newKey();
        $nonces = [];
        for ($i = 0; $i < 200; $i++) { $nonces[] = CryptoService::encrypt('x', $key, 'a')['nonce']; }
        $this->assertCount(200, array_unique($nonces));
    }

    public function testFlippedCiphertextBitIsRejected(): void
    {
        $key = CryptoService::newKey();
        $r = CryptoService::encrypt('sensitive', $key, 'a');
        $r['ciphertext'][0] = chr(ord($r['ciphertext'][0]) ^ 1);
        $this->expectException(IntegrityException::class);
        CryptoService::decrypt($r['ciphertext'], $key, $r['nonce'], $r['tag'], 'a');
    }

    public function testWrongKeyOrWrongAadIsRejected(): void
    {
        $key = CryptoService::newKey();
        $r = CryptoService::encrypt('sensitive', $key, 'doc|1');
        try {
            CryptoService::decrypt($r['ciphertext'], CryptoService::newKey(), $r['nonce'], $r['tag'], 'doc|1');
            $this->fail('wrong key accepted');
        } catch (IntegrityException) { $this->assertTrue(true); }
        $this->expectException(IntegrityException::class);
        CryptoService::decrypt($r['ciphertext'], $key, $r['nonce'], $r['tag'], 'doc|2');   // AAD mismatch
    }

    public function testArgon2idIsUsedForPasswords(): void
    {
        $h = AuthService::hashPassword('CorrectHorse#2026');
        $this->assertStringStartsWith('$argon2id$', $h);
        $this->assertTrue(password_verify('CorrectHorse#2026', $h));
        $this->assertFalse(password_verify('wrong', $h));
    }
}
