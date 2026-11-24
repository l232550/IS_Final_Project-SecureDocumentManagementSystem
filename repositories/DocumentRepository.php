<?php
class DocumentRepository
{
    public function __construct(private PDO $db) {}

    public function find(int $id): ?array
    {
        $s = $this->db->prepare('SELECT d.*, u.username AS owner_name FROM documents d JOIN users u ON u.id = d.owner_id WHERE d.id = ?');
        $s->execute([$id]);
        return $s->fetch() ?: null;
    }

    public function create(array $d): int
    {
        $now = date('Y-m-d H:i:s');
        $s = $this->db->prepare('INSERT INTO documents (owner_id,original_filename,file_type,mime_type,file_size,storage_id,enc_algorithm,
            doc_nonce,auth_tag,wrapped_dek,dek_nonce,dek_tag,sha256_fingerprint,key_version,created_at,updated_at)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $s->execute([$d['owner_id'], $d['original_filename'], $d['file_type'], $d['mime_type'], $d['file_size'], $d['storage_id'],
            'AES-256-GCM', $d['doc_nonce'], $d['auth_tag'], $d['wrapped_dek'], $d['dek_nonce'], $d['dek_tag'],
            $d['sha256_fingerprint'], $d['key_version'], $now, $now]);
        return (int)$this->db->lastInsertId();
    }

    public function replaceContent(int $id, array $d): void
    {
        $s = $this->db->prepare('UPDATE documents SET original_filename=?, mime_type=?, file_size=?, storage_id=?, doc_nonce=?, auth_tag=?,
            wrapped_dek=?, dek_nonce=?, dek_tag=?, sha256_fingerprint=?, key_version=?, updated_at=? WHERE id=?');
        $s->execute([$d['original_filename'], $d['mime_type'], $d['file_size'], $d['storage_id'], $d['doc_nonce'], $d['auth_tag'],
            $d['wrapped_dek'], $d['dek_nonce'], $d['dek_tag'], $d['sha256_fingerprint'], $d['key_version'], date('Y-m-d H:i:s'), $id]);
    }

    public function updateWrap(int $id, array $w): void
    {
        $this->db->prepare('UPDATE documents SET wrapped_dek=?, dek_nonce=?, dek_tag=?, key_version=? WHERE id=?')
            ->execute([$w['wrapped_dek'], $w['dek_nonce'], $w['dek_tag'], $w['key_version'], $id]);
    }

    public function delete(int $id): void
    {
        $this->db->prepare('DELETE FROM documents WHERE id = ?')->execute([$id]);
    }

    public function listOwned(int $userId): array
    {
        $s = $this->db->prepare('SELECT id,original_filename,file_type,file_size,created_at,updated_at FROM documents WHERE owner_id = ? ORDER BY id DESC');
        $s->execute([$userId]);
        return $s->fetchAll();
    }

    /** Documents shared with the user whose permission has not expired. */
    public function listSharedWith(int $userId): array
    {
        $s = $this->db->prepare('SELECT d.id,d.original_filename,d.file_type,d.file_size,d.updated_at,u.username AS owner_name,
                p.can_view,p.can_edit,p.can_download,p.expires_at
            FROM document_permissions p JOIN documents d ON d.id = p.document_id JOIN users u ON u.id = d.owner_id
            WHERE p.user_id = ? AND (p.expires_at IS NULL OR p.expires_at > ?) ORDER BY d.id DESC');
        $s->execute([$userId, date('Y-m-d H:i:s')]);
        return $s->fetchAll();
    }

    public function countOwned(int $userId): int
    {
        $s = $this->db->prepare('SELECT COUNT(*) FROM documents WHERE owner_id = ?');
        $s->execute([$userId]);
        return (int)$s->fetchColumn();
    }

    public function notOnKeyVersion(int $version): array
    {
        $s = $this->db->prepare('SELECT * FROM documents WHERE key_version <> ?');
        $s->execute([$version]);
        return $s->fetchAll();
    }

    public function countOnKeyVersion(int $version): int
    {
        $s = $this->db->prepare('SELECT COUNT(*) FROM documents WHERE key_version = ?');
        $s->execute([$version]);
        return (int)$s->fetchColumn();
    }
}
