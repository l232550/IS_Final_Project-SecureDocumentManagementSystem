# Introduction and Requirements Traceability

## Project summary
The Secure Document Management System lets registered users upload PDF, DOCX and TXT documents, keeps them encrypted at rest, shares them with other registered users under fine-grained and revocable permissions, and records security-relevant activity in a tamper-evident audit log. The emphasis, following the assignment, is on defensible design decisions rather than feature volume.

## Technology
PHP 8.3 (core PHP, PDO prepared statements, OpenSSL), MySQL, Apache, Bootstrap 5, PHPUnit, MAMP for development, Git/GitHub for version control. No frameworks.

## Design decisions beyond the brief
| # | Decision | Reason |
|--|--------------|-----------|
| A | `users.role` stores only `admin` or `user`; Owner/Editor/Viewer are per-document relationships | The same person is owner of some documents and viewer of others |
| B | `document_permissions` has `can_view`, `can_edit`, `can_download`, `expires_at`; edit implies view; download is separate | Matches the View/Edit/Download sharing requirement |
| C | PDF and TXT can be viewed in the browser (decrypted in memory); DOCX is download-only | Browsers cannot render DOCX; avoids a risky converter |
| D | Extra tables `login_attempts`, `password_resets`, `key_versions` | Needed for rate limiting, reset tokens and KEK versioning (no key material) |
| E | Audit appends are serialized with a MySQL advisory lock; optional INSERT/SELECT-only DB user; off-DB anchor file | Prevents forks of the chain, detects tail truncation, adds DB-level append-only |
| F | AAD (storage UUID, key version) added to AES-GCM | Prevents swapping ciphertexts or wrapped DEKs between records; AES-256-GCM and the DEK/KEK design are unchanged |
| G | Password-reset links are written to a protected server log in development | MAMP has no mail server; tokens are stored hashed, single use, 30 minutes |
| H | Keys are files in `private_storage/keys` created once by a script that refuses to overwrite | Meets "never regenerate a production KEK" |
| I | Setup checks for Argon2id and AES-256-GCM (`bin/check_env.php`) | Fails early with a clear message |
| J | PHPUnit is the only dev dependency | Keeps the application dependency-free |

## Requirements traceability
| Requirement | Implemented in |
|--------------|-------------|
| Registration, login, logout, Argon2id | `AuthController`, `AuthService`, `UserRepository` |
| Sessions: regenerate, 30-min timeout, HttpOnly/Secure/SameSite | `SessionService`, `public/index.php` |
| Rate limiting and lockout | `AuthService`, `LoginAttemptRepository` |
| Password reset (single-use, timed) | `AuthService`, `PasswordResetRepository` |
| Upload validation (ext, finfo MIME, 10 MB, executables) | `UploadValidator` |
| Envelope encryption, nonces, tags | `CryptoService`, `KeyManager`, `DocumentService` |
| Private encrypted storage, UUID names | `StorageService`, `private_storage/documents` |
| Document operations (upload, view, download, update, delete, details, verify) | `DocumentController`, `DocumentService` |
| RBAC + document-level authorization | `AuthorizationService` (called from `fetchAuthorized`) |
| Sharing, expiry, revocation | `ShareController`, `ShareService`, `PermissionRepository` |
| Audit HMAC + hash chain + verification | `AuditService`, `AuditRepository`, `AdminController` |
| Admin user management and audit UI | `AdminController`, `views/admin` |
| CSRF, XSS, headers, error handling | `Csrf`, `e()`, `public/index.php`, `bootstrap.php` |
| Key setup, rotation, backup, recovery | `bin/setup_keys.php`, `rotate_kek.php`, `backup.php`, `restore_keys.php` |
| Seven security tests | `tests/Security/*`, `bin/security_demo.php`, `tests/http_e2e.sh` |
| Five DDRs, threat model, diagrams, DB/crypto/access design | `documentation/` (this report) |
