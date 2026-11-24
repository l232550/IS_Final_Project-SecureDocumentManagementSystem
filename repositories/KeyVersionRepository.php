<?php
class KeyVersionRepository
{
    public function __construct(private PDO $db) {}

    public function all(): array
    {
        return $this->db->query('SELECT * FROM key_versions ORDER BY version')->fetchAll();
    }

    public function activeVersion(): ?int
    {
        $v = $this->db->query("SELECT MAX(version) FROM key_versions WHERE status='active'")->fetchColumn();
        return $v === null || $v === false ? null : (int)$v;
    }

    /** Make $version the single active version; older ones become retired. */
    public function activate(int $version): void
    {
        $now = date('Y-m-d H:i:s');
        $this->db->beginTransaction();
        try {
            $this->db->prepare("UPDATE key_versions SET status='retired', retired_at=? WHERE status='active'")->execute([$now]);
            $this->db->prepare("INSERT INTO key_versions (version,status,created_at) VALUES (?,'active',?)")->execute([$version, $now]);
            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
}
