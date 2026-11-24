<?php
/** Account-based sharing. Only the owner can share, change or revoke; permissions live in MySQL and are checked on every request. */
class ShareService
{
    public function __construct(
        private DocumentService $documents,
        private PermissionRepository $perms,
        private UserRepository $users,
        private AuditService $audit
    ) {}

    /** @param string $level 'viewer' or 'editor' */
    public function share(array $owner, int $docId, int $recipientId, string $level, bool $download, ?string $expires): bool
    {
        $doc = $this->documents->fetchAuthorized($owner, $docId, 'share');
        $recipient = $this->users->findById($recipientId);
        if ($recipient === null || (int)$recipient['is_active'] !== 1) {
            throw new ValidationException('Please select a registered recipient.');
        }
        if ((int)$recipient['id'] === (int)$owner['id']) {
            throw new ValidationException('You already own this document.');
        }
        if (!in_array($level, ['viewer', 'editor'], true)) {
            throw new ValidationException('Invalid permission level.');
        }
        $expiresAt = null;
        if ($expires !== null && trim($expires) !== '') {
            $dt = DateTime::createFromFormat('Y-m-d\TH:i', trim($expires));
            if ($dt === false || $dt->getTimestamp() <= time()) {
                throw new ValidationException('The expiry must be a valid date in the future.');
            }
            $expiresAt = $dt->format('Y-m-d H:i:s');
        }
        $edit = $level === 'editor';
        $isNew = $this->perms->upsert((int)$doc['id'], (int)$recipient['id'], true, $edit, $download, $expiresAt, (int)$owner['id']);
        $this->audit->log($isNew ? 'sharing_created' : 'permission_modified', 'success', (int)$owner['id'], (int)$doc['id'],
            ['recipient_id' => (int)$recipient['id'], 'level' => $level, 'download' => $download, 'expires' => $expiresAt]);
        return $isNew;
    }

    public function revoke(array $owner, int $docId, int $recipientId): void
    {
        $this->documents->fetchAuthorized($owner, $docId, 'share');
        if (!$this->perms->revoke($docId, $recipientId)) {
            throw new ValidationException('That user does not have access to this document.');
        }
        $this->audit->log('permission_revoked', 'success', (int)$owner['id'], $docId, ['recipient_id' => $recipientId]);
    }

    public function listFor(array $owner, int $docId): array
    {
        $this->documents->fetchAuthorized($owner, $docId, 'share');
        return $this->perms->listForDocument($docId);
    }
}
