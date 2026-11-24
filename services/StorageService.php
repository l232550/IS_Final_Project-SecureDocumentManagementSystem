<?php
/** Encrypted files on the private file system. Physical names are random UUIDs; user input never reaches a path. */
class StorageService
{
    public function __construct(private string $dir) {}

    public static function newId(): string
    {
        $b = random_bytes(16);
        $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);   // UUID version 4
        $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);
        $h = bin2hex($b);
        return sprintf('%s-%s-%s-%s-%s', substr($h, 0, 8), substr($h, 8, 4), substr($h, 12, 4), substr($h, 16, 4), substr($h, 20));
    }

    private function path(string $id): string
    {
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $id)) {
            throw new RuntimeException('Invalid storage identifier.');   // blocks path traversal
        }
        return $this->dir . '/' . $id . '.enc';
    }

    public function write(string $id, string $encrypted): void
    {
        if (!is_dir($this->dir) && !mkdir($this->dir, 0700, true)) {
            throw new RuntimeException('Cannot create storage directory.');
        }
        $path = $this->path($id);
        $tmp = $path . '.tmp';
        if (file_put_contents($tmp, $encrypted, LOCK_EX) === false) {
            throw new RuntimeException('Cannot write encrypted file.');
        }
        chmod($tmp, 0600);
        rename($tmp, $path);                // atomic replace
    }

    public function read(string $id): string
    {
        $path = $this->path($id);
        $data = is_file($path) ? file_get_contents($path) : false;
        if ($data === false) {
            throw new IntegrityException('Stored encrypted file is missing.');
        }
        return $data;
    }

    public function delete(string $id): void
    {
        $path = $this->path($id);
        if (is_file($path)) {
            unlink($path);
        }
    }

    public function exists(string $id): bool { return is_file($this->path($id)); }
}
