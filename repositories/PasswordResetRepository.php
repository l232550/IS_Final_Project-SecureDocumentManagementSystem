<?php
class PasswordResetRepository
{
    public function __construct(private PDO $db) {}

    public function create(int $userId, string $tokenHash, string $expiresAt): void
    {
        $this->db->prepare('UPDATE password_resets SET used_at = ? WHERE user_id = ? AND used_at IS NULL')->execute([date('Y-m-d H:i:s'), $userId]);
        $this->db->prepare('INSERT INTO password_resets (user_id,token_hash,expires_at,created_at) VALUES (?,?,?,?)')
            ->execute([$userId, $tokenHash, $expiresAt, date('Y-m-d H:i:s')]);
    }

    public function findValid(string $tokenHash): ?array
    {
        $s = $this->db->prepare('SELECT * FROM password_resets WHERE token_hash = ? AND used_at IS NULL AND expires_at > ?');
        $s->execute([$tokenHash, date('Y-m-d H:i:s')]);
        return $s->fetch() ?: null;
    }

    /** Atomically marks the token used; returns false if someone else used it first (single-use). */
    public function markUsed(int $id): bool
    {
        $s = $this->db->prepare('UPDATE password_resets SET used_at = ? WHERE id = ? AND used_at IS NULL');
        $s->execute([date('Y-m-d H:i:s'), $id]);
        return $s->rowCount() === 1;
    }
}
