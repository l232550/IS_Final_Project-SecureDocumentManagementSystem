<?php
/**
 * AES-256-GCM helper built on PHP OpenSSL (no custom crypto).
 * - key   : 32 random bytes
 * - nonce : 12 random bytes, fresh for EVERY encryption (never reused with the same key)
 * - tag   : 16 bytes, produced by GCM; decryption fails if ciphertext, nonce, tag or AAD change
 * - AAD   : extra data that is authenticated but not encrypted (binds ciphertext to its context)
 */
class CryptoService
{
    public const CIPHER = 'aes-256-gcm';
    public const KEY_LEN = 32;
    public const NONCE_LEN = 12;
    public const TAG_LEN = 16;

    /** @return array{ciphertext:string,nonce:string,tag:string} */
    public static function encrypt(string $plaintext, string $key, string $aad): array
    {
        if (strlen($key) !== self::KEY_LEN) {
            throw new InvalidArgumentException('Key must be 32 bytes.');
        }
        $nonce = random_bytes(self::NONCE_LEN);
        $tag = '';
        $ciphertext = openssl_encrypt($plaintext, self::CIPHER, $key, OPENSSL_RAW_DATA, $nonce, $tag, $aad, self::TAG_LEN);
        if ($ciphertext === false || strlen($tag) !== self::TAG_LEN) {
            throw new RuntimeException('Encryption failed.');
        }
        return ['ciphertext' => $ciphertext, 'nonce' => $nonce, 'tag' => $tag];
    }

    /** @throws IntegrityException when authentication fails (wrong key, tampered data or wrong AAD) */
    public static function decrypt(string $ciphertext, string $key, string $nonce, string $tag, string $aad): string
    {
        if (strlen($key) !== self::KEY_LEN || strlen($nonce) !== self::NONCE_LEN || strlen($tag) !== self::TAG_LEN) {
            throw new IntegrityException('Invalid cryptographic parameters.');
        }
        $plain = openssl_decrypt($ciphertext, self::CIPHER, $key, OPENSSL_RAW_DATA, $nonce, $tag, $aad);
        if ($plain === false) {
            throw new IntegrityException('Authentication failed: data was modified or the key is wrong.');
        }
        return $plain;
    }

    public static function newKey(): string
    {
        return random_bytes(self::KEY_LEN);
    }

    public static function fingerprint(string $data): string
    {
        return hash('sha256', $data);
    }
}
