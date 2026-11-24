<?php
class PermissionRepository
{
    public function __construct(private PDO $db) {}

    public function find(int $docId, int $userId): ?array
    {
        $s = $this->db->prepare('SELECT * FROM document_permissions WHERE document_id = ? AND user_id = ?');
        $s->execute([$docId, $userId]);
        return $s->fetch() ?: null;
    }

    public function listForDocument(int $docId): array
    {
        $s = $this->db->prepare('SELECT p.*, u.username FROM document_permissions p JOIN users u ON u.id = p.user_id WHERE p.document_id = ? ORDER BY u.username');
        $s->execute([$docId]);
        return $s->fetchAll();
    }

    /** @return bool true if a new row was created, false if an existing one was updated */
    public function upsert(int $docId, int $userId, bool $view, bool $edit, bool $download, ?string $expires, int $grantedBy): bool
    {
        $now = date('Y-m-d H:i:s');
        $isNew = $this->find($docId, $userId) === null;
        $s = $this->db->prepare('INSERT INTO document_permissions (document_id,user_id,can_view,can_edit,can_download,expires_at,granted_by,created_at,updated_at)
            VALUES (?,?,?,?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE can_view=VALUES(can_view), can_edit=VALUES(can_edit), can_download=VALUES(can_download),
              expires_at=VALUES(expires_at), granted_by=VALUES(granted_by), updated_at=VALUES(updated_at)');
        $s->execute([$docId, $userId, (int)$view, (int)$edit, (int)$download, $expires, $grantedBy, $now, $now]);
        return $isNew;
    }

    /** Revocation = deleting the row, so the very next request is denied. */
    public function revoke(int $docId, int $userId): bool
    {
        $s = $this->db->prepare('DELETE FROM document_permissions WHERE document_id = ? AND user_id = ?');
        $s->execute([$docId, $userId]);
        return $s->rowCount() > 0;
    }
}
