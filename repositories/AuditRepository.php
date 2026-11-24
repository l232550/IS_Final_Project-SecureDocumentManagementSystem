<?php
class AuditRepository
{
    public function __construct(private PDO $db) {}

    public function pdo(): PDO { return $this->db; }

    public function last(): ?array
    {
        return $this->db->query('SELECT * FROM audit_logs ORDER BY id DESC LIMIT 1')->fetch() ?: null;
    }

    public function insert(array $r): int
    {
        $s = $this->db->prepare('INSERT INTO audit_logs (user_id,action,document_id,outcome,ip_address,user_agent,details,created_at,prev_hash,record_hmac)
            VALUES (?,?,?,?,?,?,?,?,?,?)');
        $s->execute([$r['user_id'], $r['action'], $r['document_id'], $r['outcome'], $r['ip_address'], $r['user_agent'],
            $r['details'], $r['created_at'], $r['prev_hash'], $r['record_hmac']]);
        return (int)$this->db->lastInsertId();
    }

    /** All rows in chain order (streamed, one row at a time). */
    public function iterateAll(): Generator
    {
        $s = $this->db->query('SELECT * FROM audit_logs ORDER BY id ASC');
        while ($row = $s->fetch()) {
            yield $row;
        }
    }

    public function page(int $limit, int $offset, ?string $action, ?int $userId): array
    {
        $where = []; $p = [];
        if ($action !== null && $action !== '') { $where[] = 'action = ?'; $p[] = $action; }
        if ($userId !== null) { $where[] = 'user_id = ?'; $p[] = $userId; }
        $sql = 'SELECT * FROM audit_logs' . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY id DESC LIMIT ' . (int)$limit . ' OFFSET ' . (int)$offset;
        $s = $this->db->prepare($sql);
        $s->execute($p);
        return $s->fetchAll();
    }

    public function count(?string $action, ?int $userId): int
    {
        $where = []; $p = [];
        if ($action !== null && $action !== '') { $where[] = 'action = ?'; $p[] = $action; }
        if ($userId !== null) { $where[] = 'user_id = ?'; $p[] = $userId; }
        $s = $this->db->prepare('SELECT COUNT(*) FROM audit_logs' . ($where ? ' WHERE ' . implode(' AND ', $where) : ''));
        $s->execute($p);
        return (int)$s->fetchColumn();
    }

    public function recentForUser(int $userId, int $limit = 8): array
    {
        $s = $this->db->prepare('SELECT action,outcome,document_id,created_at FROM audit_logs WHERE user_id = ? ORDER BY id DESC LIMIT ' . (int)$limit);
        $s->execute([$userId]);
        return $s->fetchAll();
    }

    public function actions(): array
    {
        return $this->db->query('SELECT DISTINCT action FROM audit_logs ORDER BY action')->fetchAll(PDO::FETCH_COLUMN);
    }
}
