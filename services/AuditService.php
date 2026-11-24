<?php
/**
 * Tamper-evident audit log.
 *   record_hmac = HMAC-SHA-256( audit_key, canonical(record fields) )   and the fields include prev_hash,
 *   so every record is chained to the one before it (hash chain).
 * Append-only by design: this class only INSERTs. With the optional DB user in database/audit_user.sql the
 * database itself also refuses UPDATE/DELETE from the application.
 * An "anchor" file (outside the DB) remembers the newest record so that deleting the LAST rows is detected too.
 * Never pass passwords, keys or document contents in $details.
 */
class AuditService
{
    public const GENESIS = '0000000000000000000000000000000000000000000000000000000000000000';

    public function __construct(private AuditRepository $repo, private string $hmacKey, private string $anchorFile) {}

    public function log(string $action, string $outcome, ?int $userId = null, ?int $documentId = null, array|string $details = []): void
    {
        $detailsStr = is_array($details) ? (string)json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : $details;
        $rec = [
            'user_id' => $userId,
            'action' => $action,
            'document_id' => $documentId,
            'outcome' => $outcome,
            'ip_address' => substr($_SERVER['REMOTE_ADDR'] ?? 'cli', 0, 45),
            'user_agent' => mb_substr(preg_replace('/[\x00-\x1f]/', '', $_SERVER['HTTP_USER_AGENT'] ?? 'cli') ?? '', 0, 200),
            'details' => mb_substr($detailsStr, 0, 500),
            'created_at' => date('Y-m-d H:i:s'),
        ];

        $pdo = $this->repo->pdo();
        $got = $pdo->query("SELECT GET_LOCK('sdms_audit_chain', 5)")->fetchColumn();   // serialise chain appends
        if ((int)$got !== 1) {
            throw new RuntimeException('Could not obtain audit chain lock.');
        }
        try {
            $last = $this->repo->last();
            $rec['prev_hash'] = $last['record_hmac'] ?? self::GENESIS;
            $rec['record_hmac'] = $this->compute($rec);
            $id = $this->repo->insert($rec);
            @file_put_contents($this->anchorFile, $id . ':' . $rec['record_hmac'], LOCK_EX);
            @chmod($this->anchorFile, 0600);
        } finally {
            $pdo->query("SELECT RELEASE_LOCK('sdms_audit_chain')");
        }
    }

    private function compute(array $r): string
    {
        $canonical = json_encode([
            $r['prev_hash'],
            $r['user_id'] === null ? null : (int)$r['user_id'],
            $r['action'],
            $r['document_id'] === null ? null : (int)$r['document_id'],
            $r['outcome'], $r['ip_address'], $r['user_agent'], $r['details'], $r['created_at'],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return hash_hmac('sha256', (string)$canonical, $this->hmacKey);
    }

    /** @return array{ok:bool,checked:int,errors:string[]} */
    public function verify(): array
    {
        $errors = [];
        $prev = self::GENESIS;
        $checked = 0;
        $lastId = null;
        $lastHmac = null;
        foreach ($this->repo->iterateAll() as $row) {
            $checked++;
            if (!hash_equals($prev, (string)$row['prev_hash'])) {
                $errors[] = "Record #{$row['id']}: chain broken (a previous record was removed, inserted or altered).";
            }
            if (!hash_equals($this->compute($row), (string)$row['record_hmac'])) {
                $errors[] = "Record #{$row['id']}: HMAC mismatch (record content was modified).";
            }
            $prev = (string)$row['record_hmac'];
            $lastId = (int)$row['id'];
            $lastHmac = (string)$row['record_hmac'];
        }
        if (is_file($this->anchorFile)) {
            $anchor = trim((string)file_get_contents($this->anchorFile));
            if ($anchor !== '' && $anchor !== $lastId . ':' . $lastHmac) {
                $errors[] = 'Chain head does not match the stored anchor (newest records were deleted or replaced).';
            }
        }
        return ['ok' => $errors === [], 'checked' => $checked, 'errors' => $errors];
    }
}
