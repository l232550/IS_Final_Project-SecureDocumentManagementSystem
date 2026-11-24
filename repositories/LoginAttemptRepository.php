<?php
class LoginAttemptRepository
{
    public function __construct(private PDO $db) {}

    public function record(string $identifier, string $ip, bool $success): void
    {
        $this->db->prepare('INSERT INTO login_attempts (identifier,ip_address,success,attempted_at) VALUES (?,?,?,?)')
            ->execute([mb_substr(strtolower($identifier), 0, 190), $ip, (int)$success, date('Y-m-d H:i:s')]);
    }

    public function recentFailuresFor(string $identifier, int $windowSeconds): int
    {
        $s = $this->db->prepare('SELECT COUNT(*) FROM login_attempts WHERE identifier = ? AND success = 0 AND attempted_at > ?');
        $s->execute([mb_substr(strtolower($identifier), 0, 190), date('Y-m-d H:i:s', time() - $windowSeconds)]);
        return (int)$s->fetchColumn();
    }

    public function recentFailuresFromIp(string $ip, int $windowSeconds): int
    {
        $s = $this->db->prepare('SELECT COUNT(*) FROM login_attempts WHERE ip_address = ? AND success = 0 AND attempted_at > ?');
        $s->execute([$ip, date('Y-m-d H:i:s', time() - $windowSeconds)]);
        return (int)$s->fetchColumn();
    }

    public function clearFor(string $identifier): void
    {
        $this->db->prepare('DELETE FROM login_attempts WHERE identifier = ?')->execute([mb_substr(strtolower($identifier), 0, 190)]);
    }

    public function purgeOld(): void
    {
        $this->db->prepare('DELETE FROM login_attempts WHERE attempted_at < ?')->execute([date('Y-m-d H:i:s', time() - 86400)]);
    }
}
