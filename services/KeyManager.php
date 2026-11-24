<?php
/**
 * Envelope-encryption key management.
 *   DEK (per document, random) encrypts the document.
 *   KEK (versioned, stored in private_storage/keys) wraps (encrypts) every DEK.
 * Key files hold hex text, created with exclusive-create + chmod 0600. Keys never go into MySQL or source code.
 */
class KeyManager
{
    public function __construct(private string $keyDir, private KeyVersionRepository $versions) {}

    private function kekPath(int $v): string { return $this->keyDir . "/kek_v{$v}.key"; }
    private function hmacPath(): string      { return $this->keyDir . '/hmac.key'; }

    /** First-time setup. Refuses to run if keys already exist (never regenerates a production KEK). */
    public function initialize(): void
    {
        if (!is_dir($this->keyDir) && !mkdir($this->keyDir, 0700, true)) {
            throw new RuntimeException('Cannot create key directory.');
        }
        if ($this->versions->activeVersion() !== null || is_file($this->kekPath(1)) || is_file($this->hmacPath())) {
            throw new RuntimeException('Keys already exist. Refusing to overwrite. Use bin/rotate_kek.php to rotate.');
        }
        $this->writeNewKeyFile($this->kekPath(1));
        $this->writeNewKeyFile($this->hmacPath());
        $this->versions->activate(1);
    }

    /** Create the next KEK version file and make it active. Existing documents are rewrapped separately. */
    public function createNextKek(): int
    {
        $current = $this->activeVersion();
        $next = $current + 1;
        $this->writeNewKeyFile($this->kekPath($next));
        $this->versions->activate($next);
        return $next;
    }

    private function writeNewKeyFile(string $path): void
    {
        $fh = @fopen($path, 'x');            // 'x' = fail if the file already exists
        if ($fh === false) {
            throw new RuntimeException('Key file already exists or cannot be created.');
        }
        fwrite($fh, bin2hex(CryptoService::newKey()));
        fclose($fh);
        chmod($path, 0600);
    }

    public function activeVersion(): int
    {
        $v = $this->versions->activeVersion();
        if ($v === null) {
            throw new RuntimeException('No active KEK version. Run bin/setup_keys.php first.');
        }
        return $v;
    }

    private function readKey(string $path): string
    {
        $hex = is_file($path) ? trim((string)file_get_contents($path)) : '';
        $key = ctype_xdigit($hex) ? hex2bin($hex) : false;
        if ($key === false || strlen($key) !== 32) {
            throw new RuntimeException('Key file missing or invalid: ' . basename($path));
        }
        return $key;
    }

    public function kek(int $version): string { return $this->readKey($this->kekPath($version)); }
    public function hmacKey(): string         { return $this->readKey($this->hmacPath()); }

    /** @return array{wrapped_dek:string,dek_nonce:string,dek_tag:string,key_version:int} */
    public function wrapDek(string $dek, string $storageId, ?int $version = null): array
    {
        $version ??= $this->activeVersion();
        $r = CryptoService::encrypt($dek, $this->kek($version), "dek|$storageId|$version");
        return ['wrapped_dek' => $r['ciphertext'], 'dek_nonce' => $r['nonce'], 'dek_tag' => $r['tag'], 'key_version' => $version];
    }

    /** @param array $doc a documents row */
    public function unwrapDek(array $doc): string
    {
        $v = (int)$doc['key_version'];
        return CryptoService::decrypt($doc['wrapped_dek'], $this->kek($v), $doc['dek_nonce'], $doc['dek_tag'], "dek|{$doc['storage_id']}|$v");
    }
}
