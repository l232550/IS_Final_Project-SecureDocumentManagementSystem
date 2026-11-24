# Access-Control Design

**Model:** RBAC (account-wide role) combined with DAC-style document-level permissions granted by owners. ABAC-like attributes (expiry time, account active flag) are part of the check. Principle of least privilege is applied: default deny.

## Roles
| Role | Where defined | Capabilities |
|---|---|---|
| Admin | `users.role='admin'` | Manage users (role, enable/disable), view audit logs, verify audit integrity. **No** access to other users' document contents or metadata |
| Owner | `documents.owner_id` | Upload, view, download, update, delete own documents; share, change, revoke permissions; verify integrity |
| Editor | `document_permissions.can_edit=1` | View, update (re-upload same type), verify; download only if `can_download` |
| Viewer | `document_permissions.can_view=1` | View details and PDF/TXT in the browser, verify; download only if `can_download` |

Owner/Editor/Viewer are relationships rather than account roles, because one account is an owner of its own documents and possibly a viewer of others'.

## Permission matrix (enforced by `AuthorizationService::can`)
| Action | Owner | Editor | Viewer | Admin (no grant) | Other user |
|---|---|---|---|---|---|
| details | ✔ | ✔ | ✔ | ✘ | ✘ |
| view | ✔ | ✔ | ✔ | ✘ | ✘ |
| download | ✔ | only with download flag | only with download flag | ✘ | ✘ |
| edit/update | ✔ | ✔ | ✘ | ✘ | ✘ |
| verify | ✔ | ✔ | ✔ | ✘ | ✘ |
| delete | ✔ | ✘ | ✘ | ✘ | ✘ |
| share / revoke | ✔ | ✘ | ✘ | ✘ | ✘ |

Grants also stop working when `expires_at` has passed or the user account is disabled.

## Where checks happen
1. `BaseController::requireLogin()` – session must resolve to an *active* user loaded fresh from MySQL (disabling an account takes effect on the next request).
2. `DocumentService::fetchAuthorized()` – the single gate for every document operation (view, download, edit, delete, verify, details) and `ShareService` (share, revoke, list). It calls `AuthorizationService::can()`.
3. `BaseController::requireAdmin()` – for user management and audit pages.
4. CSRF token check on every state-changing POST.

The UI hides unavailable buttons for convenience only; the tests call the services and HTTP endpoints directly to prove the server refuses.

## Denial behaviour
Unauthorized, missing and revoked documents all yield the same "Access denied" (HTTP 403) so document IDs cannot be enumerated. The audit log distinguishes `unauthorized_access` (no relationship to the document), `failed_authorization` (has a relationship but not this action), and non-existent IDs (reason `no_such_document`).
