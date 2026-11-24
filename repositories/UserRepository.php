<?php
class UserRepository
{
    public function __construct(private PDO $db) {}

    public function findById(int $id): ?array
    {
        $s = $this->db->prepare('SELECT * FROM users WHERE id = ?');
        $s->execute([$id]);
        return $s->fetch() ?: null;
    }

    public function findByEmail(string $email): ?array
    {
        $s = $this->db->prepare('SELECT * FROM users WHERE email = ?');
        $s->execute([strtolower($email)]);
        return $s->fetch() ?: null;
    }

    /** Login identifier can be email or username. */
    public function findByLogin(string $login): ?array
    {
        $s = $this->db->prepare('SELECT * FROM users WHERE email = ? OR username = ? LIMIT 1');
        $s->execute([strtolower($login), $login]);
        return $s->fetch() ?: null;
    }

    public function usernameOrEmailExists(string $username, string $email): bool
    {
        $s = $this->db->prepare('SELECT COUNT(*) FROM users WHERE username = ? OR email = ?');
        $s->execute([$username, strtolower($email)]);
        return (int)$s->fetchColumn() > 0;
    }

    public function create(string $username, string $email, string $hash, string $role = 'user'): int
    {
        $now = date('Y-m-d H:i:s');
        $s = $this->db->prepare('INSERT INTO users (username,email,password_hash,role,created_at,updated_at) VALUES (?,?,?,?,?,?)');
        $s->execute([$username, strtolower($email), $hash, $role, $now, $now]);
        return (int)$this->db->lastInsertId();
    }

    public function all(): array
    {
        return $this->db->query('SELECT id,username,email,role,is_active,last_login_at,created_at FROM users ORDER BY id')->fetchAll();
    }

    /** Users a document can be shared with (active, not the owner). */
    public function recipients(int $excludeId): array
    {
        $s = $this->db->prepare('SELECT id,username FROM users WHERE is_active = 1 AND id <> ? ORDER BY username');
        $s->execute([$excludeId]);
        return $s->fetchAll();
    }

    public function updateRole(int $id, string $role): void
    {
        $this->db->prepare('UPDATE users SET role = ?, updated_at = ? WHERE id = ?')->execute([$role, date('Y-m-d H:i:s'), $id]);
    }

    public function setActive(int $id, bool $active): void
    {
        $this->db->prepare('UPDATE users SET is_active = ?, updated_at = ? WHERE id = ?')->execute([(int)$active, date('Y-m-d H:i:s'), $id]);
    }

    public function updatePassword(int $id, string $hash): void
    {
        $this->db->prepare('UPDATE users SET password_hash = ?, updated_at = ? WHERE id = ?')->execute([$hash, date('Y-m-d H:i:s'), $id]);
    }

    public function touchLogin(int $id): void
    {
        $this->db->prepare('UPDATE users SET last_login_at = ? WHERE id = ?')->execute([date('Y-m-d H:i:s'), $id]);
    }
}
