# Part 1 – Security Research and Design

This chapter answers every question in Part 1 of the assignment. Full justification of the five major decisions is in the Design Decision Records (Chapter 9).

## 1. Data Storage and Confidentiality

**Q1.1 How are documents stored?** Each upload is validated, encrypted, and written as a single file `private_storage/documents/<random-uuid>.enc`. The folder is outside the Apache web root (`public/`), so no URL maps to it. MySQL stores only metadata.

**Q1.2 Original form, protected form, or both?** Protected form only. The plaintext is never written to disk by the application (PHP's own upload temp file is removed by PHP at the end of the request). Alternatives rejected: plaintext on a private disk (a stolen disk or backup exposes everything); database BLOBs (large encrypted blobs slow the database and inflate backups, and a SQL injection or DB dump would expose ciphertext and wrapped keys together).

**Q1.3 When does protection happen?** On the server, after upload validation (size, extension, finfo MIME, magic bytes) and before the first byte is written to permanent storage. Decryption happens only in memory, per authorized request.

**Q1.4 What goes in the database and what is stored separately?** The database holds metadata, permissions, wrapped DEKs, nonces, tags, fingerprints and the audit chain. Separate from it: encrypted files (private file system) and the KEK/HMAC keys (`private_storage/keys`). Keeping the three apart means one compromised component is not enough to read a document.

**Q1.5 If an attacker gets the document storage, what do they learn?** The number of files, their approximate sizes, and file timestamps. They do not learn contents, original names or types (names are random UUIDs; the name lives in MySQL). Without the KEK they cannot unwrap any DEK, and every document has a different DEK. Demonstrated in Test 3. If the attacker also obtains the database, they learn metadata (original filenames, owners, sizes, sharing relationships) but still not contents; metadata is *not* encrypted (documented limitation). If they also obtain the key directory, they can decrypt everything.

## 2. Cryptographic Protection

**Q2.1 Only authorized users can read.** Confidentiality comes from AES-256-GCM encryption at rest. Authorization comes from the server: the key hierarchy is server-side, so a user can only obtain plaintext through a PHP request that passes `AuthorizationService`. Encryption alone does not decide *who* may read; access control does.

**Q2.2 Detecting modification.** Two layers: (a) the AES-GCM authentication tag (128-bit) fails on decryption if any ciphertext bit, nonce, tag or AAD changed; (b) a SHA-256 fingerprint of the plaintext, stored in MySQL, is compared after decryption. AAD binds each ciphertext to its own storage ID so files cannot be swapped between documents (Test 4).

**Q2.3 Managing cryptographic material.** Envelope encryption: a random 256-bit DEK per document (`random_bytes(32)`), wrapped by a versioned 256-bit KEK with AES-256-GCM using a separate nonce. KEK files are created once by `bin/setup_keys.php` (exclusive-create, chmod 600, refuses to overwrite). `bin/rotate_kek.php` creates a new KEK version and rewraps all DEKs without re-encrypting documents. `bin/backup.php` stores a passphrase-protected key backup (PBKDF2-HMAC-SHA256 + AES-256-GCM).

**Q2.4 Compromised or lost credentials/keys.**
- *User password compromised:* the attacker gets that user's access only. Mitigation: Argon2id hashing, lockout, single-use reset tokens, admin can disable the account, owner can revoke shares, audit trail shows what the account did.
- *KEK lost:* documents wrapped by that version are unrecoverable. Mitigation: passphrase-protected key backup, old KEK versions retained, and the documented recovery procedure.
- *KEK stolen:* with the encrypted files an attacker can decrypt. Response: restore from clean backup, rotate (new KEK, rewrap), and re-encrypt documents if the old DEKs may be exposed (rotation alone changes only the wrapping layer; a full re-encryption script would be the next step – a limitation).
- *HMAC key stolen:* audit chain can be forged (see DDR-05).

**Q2.5 Client vs server.** All cryptography runs on the server. Reasons: the server must enforce sharing and revocation (client-side keys per recipient would make instant revocation hard), PHP OpenSSL is a trusted implementation, and JavaScript crypto would add an attack surface with no benefit for this threat model. Trade-off: the server is trusted with plaintext during a request; this is not an end-to-end-encrypted design. Asymmetric cryptography and digital signatures were considered (per-user key pairs, signed documents) and rejected as unnecessary complexity for the prototype; they are the natural extension for end-to-end sharing.

## 3. Authentication and Authorization

**Q3.1 Identity.** Registration and login with Argon2id password hashing (`password_hash`/`password_verify`), PHP sessions with HttpOnly + SameSite=Strict (+ Secure on HTTPS) cookies, session ID regeneration at login, 30-minute inactivity timeout, rate limiting and temporary lockout.

**Q3.2 Distinguishing roles.** `users.role` is `admin` or `user` (account-wide RBAC). *Owner*, *Editor* and *Viewer* are document-level relationships derived from `documents.owner_id` and `document_permissions`, because the same person is an owner of their own documents and a viewer of someone else's.

**Q3.3 Preventing cross-user access.** Every document operation calls `DocumentService::fetchAuthorized()`, which loads the document and asks `AuthorizationService::can()`. There is no other path to a document.

**Q3.4 Checking permissions on every operation.** The ten document operations (upload, view, download, update, delete, share, manage/revoke permissions, verify, details) are enforced in the service layer, not in views. Hidden buttons are only convenience. Permissions are read fresh from the database on each request, so changes take effect immediately.

**Q3.5 Revocation.** Yes. The owner revokes from the Share page; the row is deleted and the next request is denied (Test 6). Expiry is checked on every request as well.

## 4. Secure Document Sharing

**Q4.1 How sharing works.** The owner selects a registered, active user, chooses Viewer or Editor, optionally allows download, and optionally sets an expiry. The grant is a row in `document_permissions`.

**Q4.2 Enforcement.** Same `AuthorizationService`, server-side, on every request.

**Q4.3 After revocation.** The user receives the same generic "Access denied" as any unauthorized user and an audit record is written. Already-downloaded copies cannot be recalled (documented limitation).

**Q4.4 Risk of links/shared URLs.** A public or token link can be forwarded, cached, logged in proxies/history, and cannot be tied to an identity or instantly revoked without extra state. The system therefore has **no public links**: document URLs (`?r=doc/view&id=N`) work only for authenticated users who pass authorization, so a leaked URL is useless to others.

**Q4.5 Downloads and temporary copies.** Decryption happens in memory and is streamed to the browser with `Cache-Control: no-store`, `nosniff` and a sanitized filename; no decrypted temporary file is ever created, so there is nothing to clean up. The upload temp file is deleted by PHP at the end of the request. Download is a separate permission.

## 5. Auditing and Accountability

**Q5.1 Activities recorded.** Login success/failure, logout, upload, view, download, update, delete, share creation, permission change, revocation, unauthorized access, failed authorization, integrity failures, key events (generation, rotation, rewrap, backup), audit verification result, password reset events, role/status changes, CSRF failures.

**Q5.2 Protecting audit records.** HMAC-SHA-256 over each record including the previous record's HMAC (hash chain), an off-database anchor for the newest record, an application that only ever INSERTs, and an optional MySQL user that can only INSERT/SELECT on `audit_logs` (verified: UPDATE/DELETE/TRUNCATE are refused).

**Q5.3 What admins can investigate.** User list, roles, activation status, and the full audit log with filter and integrity verification.

**Q5.4 Should admins read every document?** No. Least privilege and separation of duties: administration (users, audit) is separate from document confidentiality. Admins are denied document contents and metadata unless explicitly shared like any other user (tested).

**Q5.5 Legitimate vs suspicious activity.** Records show user, IP, user agent, time, action, outcome and document ID. Patterns to look for: bursts of `unauthorized_access` across sequential document IDs from one user (ID enumeration), repeated `login_failed` followed by `login_success`, `integrity_failure` events, access right after a revocation, and downloads outside normal behaviour.
