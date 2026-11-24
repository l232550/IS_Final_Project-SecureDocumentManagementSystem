<?php
/**
 * RBAC + document-level permissions. This is the ONE place that decides access; every document operation calls it.
 *   users.role      : 'admin' | 'user'       (account-wide RBAC)
 *   per document    : owner (documents.owner_id), editor / viewer (document_permissions)
 * Admins manage users and audit logs but get NO access to document contents or metadata (least privilege).
 * Actions: details, view, download, edit, verify, delete, share
 */
class AuthorizationService
{
    public const ACTIONS = ['details', 'view', 'download', 'edit', 'verify', 'delete', 'share'];

    public function __construct(private PermissionRepository $perms) {}

    public function isAdmin(array $user): bool
    {
        return ($user['role'] ?? '') === 'admin' && (int)($user['is_active'] ?? 0) === 1;
    }

    /** The user's relationship to the document: owner | editor | viewer | none */
    public function roleOn(array $user, array $doc): string
    {
        if ((int)$user['is_active'] !== 1) return 'none';
        if ((int)$doc['owner_id'] === (int)$user['id']) return 'owner';
        $p = $this->activePermission($user, $doc);
        if ($p === null) return 'none';
        return (int)$p['can_edit'] === 1 ? 'editor' : 'viewer';
    }

    public function activePermission(array $user, array $doc): ?array
    {
        $p = $this->perms->find((int)$doc['id'], (int)$user['id']);       // always read fresh from the DB
        if ($p === null) return null;
        if ($p['expires_at'] !== null && strtotime((string)$p['expires_at']) <= time()) return null;
        return $p;
    }

    public function can(array $user, array $doc, string $action): bool
    {
        if (!in_array($action, self::ACTIONS, true) || (int)$user['is_active'] !== 1) {
            return false;
        }
        if ((int)$doc['owner_id'] === (int)$user['id']) {
            return true;                                   // owner: everything on own documents
        }
        $p = $this->activePermission($user, $doc);
        if ($p === null) {
            return false;                                  // includes admins: no automatic content access
        }
        $edit = (int)$p['can_edit'] === 1;
        $view = (int)$p['can_view'] === 1 || $edit;        // edit implies view
        return match ($action) {
            'details', 'view', 'verify' => $view,
            'download' => (int)$p['can_download'] === 1,
            'edit' => $edit,
            default => false,                              // delete, share: owner only
        };
    }
}
