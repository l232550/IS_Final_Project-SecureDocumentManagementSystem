# Design Decision Records

## DDR-01: Document Storage and Protection

**1. Problem.** Uploaded documents are confidential. Where and in what form should they live so that a stolen disk, a leaked backup, a SQL injection, or a misconfigured web server does not expose them, and so that users can only reach them through the application?

**2. Alternatives considered.**
- *Plaintext files in a private folder:* simple, but any read access to the disk or backup exposes everything.
- *Files under the web root with obscure names:* "security by obscurity"; a directory listing, guessable URL or log entry leaks them. Rejected outright.
- *Encrypted BLOBs inside MySQL:* one backup unit and transactional consistency, but large blobs bloat the database and backups, slow queries, and a SQL injection or DB dump yields ciphertext and wrapped keys together.
- *Third-party object storage (S3 etc.):* adds an external trust boundary and dependency not needed for the prototype.
- *Client-side encryption before upload:* stronger confidentiality from the server but blocks server-enforced sharing and instant revocation and requires key distribution (see DDR-02).

**3. Selected approach.** Encrypt on the server after validation and before permanent storage; write only ciphertext to `private_storage/documents/<random UUID>.enc`, outside the web root; keep metadata, wrapped DEKs, nonces, tags and fingerprints in MySQL; serve documents only through authenticated PHP controllers.

**4. Justification.** Separating file bytes, metadata and keys means the compromise of one component is insufficient (Test 3 demonstrates this). UUID names prevent path traversal and name leakage; the storage ID regex makes it impossible for user input to reach a path. Streaming from memory avoids decrypted temp files altogether.

**5. Security concepts.** Data at rest protection, defence in depth, separation of storage, least exposure, encryption boundary (server), metadata exposure, secure file upload validation (extension, finfo MIME, magic bytes, executable rejection, size cap).

**6. Limitations.** Filenames, sizes, owners are plaintext in MySQL; file sizes and times visible on disk; the server sees plaintext during a request; deletion is not forensic erasure; no versioning.

**7. Risks and trade-offs.** Two things must be backed up consistently (DB + files); whole-file encryption limits upload size (10 MB) and costs memory; a compromised web server process can reach both files and keys.

## DDR-02: Cryptographic Protection and Key Management

**1. Problem.** How to guarantee confidentiality and detect modification of stored documents, and how to manage keys so that rotation and recovery are possible without storing keys next to the data?

**2. Alternatives considered.**
- *AES-CBC/CTR + separate HMAC:* works but two primitives must be composed correctly (encrypt-then-MAC), more room for error.
- *AES-256-GCM (AEAD):* one vetted primitive giving confidentiality and integrity. **Chosen.**
- *ChaCha20-Poly1305 / libsodium secretstream:* excellent and supports streaming, but the project mandates PHP OpenSSL and AES-GCM is hardware accelerated.
- *Single master key for all files:* simple, but compromise or nonce misuse affects everything; rotation requires re-encrypting all files.
- *Per-user asymmetric keys (RSA/ECIES) with signatures:* enables end-to-end encryption and non-repudiation but complicates sharing/revocation and password-loss recovery; unnecessary for this threat model.
- *Hash only (SHA-256) for integrity:* detects accidents, not an attacker who can recompute the hash.

**3. Selected approach.** Envelope encryption: random 256-bit DEK per document encrypts the file with AES-256-GCM (96-bit random nonce, 128-bit tag, AAD = storage UUID); a versioned 256-bit KEK wraps the DEK with AES-256-GCM using a separate nonce; SHA-256 fingerprint stored as a second check; keys generated with `random_bytes`; KEKs and HMAC key in `private_storage/keys` (mode 600), never in MySQL or source; scripted setup, rotation with DEK rewrapping, and passphrase-protected backup.

**4. Justification.** Per-document DEKs limit the blast radius and keep nonce counts tiny per key; the KEK layer makes rotation a cheap metadata operation (verified by tests); AAD stops ciphertext/DEK swapping; AEAD removes the need to combine primitives.

**5. Security concepts.** Symmetric vs asymmetric cryptography, authenticated encryption vs hashing, key hierarchy, nonce uniqueness, key lifecycle (generate, use, rotate, back up, recover, destroy), separation of keys from data, CSPRNG, server-side vs client-side crypto.

**6. Limitations.** Server is trusted with plaintext; KEK is a file on the same host (production should use KMS/HSM); rotation does not re-encrypt documents; the key directory is a single point of failure without the backup; random 96-bit nonces are safe only within NIST invocation limits.

**7. Risks and trade-offs.** Operational burden of key backup; lost keys mean lost data; whole-file GCM needs the entire document in memory (10 MB cap); no end-to-end confidentiality from the server operator.

## DDR-03: Authentication and Access Control

**1. Problem.** How to prove who a user is, and how to decide, for every document operation, what that user may do, while keeping administrators from having automatic access to confidential content?

**2. Alternatives considered.**
- *Passwords hashed with SHA-256/bcrypt/PBKDF2/scrypt/Argon2id:* fast unsalted hashes are unacceptable; bcrypt and PBKDF2 are acceptable but CPU-only; Argon2id is memory-hard and resists GPU/ASIC cracking, and is built into PHP. **Chosen.**
- *JWT instead of server sessions:* stateless but hard to revoke and easy to misuse; server sessions with immediate invalidation fit better.
- *Pure DAC (owner grants only):* flexible, but no administrative role.
- *Pure RBAC:* no per-document sharing.
- *ABAC with a policy engine:* powerful but heavy for a prototype.
- *Admin-reads-everything:* convenient for support but violates least privilege and creates a high-value target.

**3. Selected approach.** Argon2id passwords; PHP sessions (HttpOnly, SameSite=Strict, Secure on HTTPS, ID regenerated at login, 30-minute inactivity timeout); per-account and per-IP lockout; roles `admin`/`user` plus document-level owner/editor/viewer from `document_permissions` (with download flag and expiry); one `AuthorizationService` called by the single gate `fetchAuthorized`; admins manage users and audit but get no document access.

**4. Justification.** Central default-deny policy avoids scattered checks; fresh DB reads give immediate effect of disabling users or changing permissions; uniform denials avoid ID enumeration; separation of duties keeps administration and confidentiality apart.

**5. Security concepts.** Authentication factors, password hashing/salting/work factor, session management and fixation, brute-force defence, RBAC, DAC, ABAC, least privilege, separation of duties, complete mediation, IDOR.

**6. Limitations.** No MFA; lockout can be abused to lock a victim out temporarily (denial of service); IP limits are weak behind NAT/proxies; password policy is length/complexity based; reset links are logged to a file instead of e-mailed in development.

**7. Risks and trade-offs.** Usability vs security in lockout thresholds (5 per account, 20 per IP per 15 minutes); per-request DB lookups for user and permissions cost a little performance for correctness.

## DDR-04: Document Sharing and Permission Revocation

**1. Problem.** How can an owner share a confidential document with specific people, limit what they can do, and take access back reliably?

**2. Alternatives considered.**
- *Public/secret share links:* easy, but links leak (history, logs, forwarding), are not tied to an identity, and are hard to revoke or audit. Rejected for this version.
- *Per-recipient re-encryption (encrypt the DEK for each recipient's public key):* strong, but revocation needs re-encryption and key rotation, and requires asymmetric key management and password-based key recovery.
- *Sending a copy to the recipient:* not revocable at all.
- *Account-based ACL checked by the server:* **chosen.**

**3. Selected approach.** The owner picks a registered active user, a level (Viewer or Editor), an optional download flag, and an optional expiry. The grant is a row in `document_permissions`. Every request re-reads and re-checks it. Revoking deletes the row, so the next request fails. Sharing, modification and revocation are audited.

**4. Justification.** Because the key hierarchy is server-side and policy is evaluated per request, revocation is immediate and no key re-distribution is needed. Binding access to authenticated accounts gives accountability.

**5. Security concepts.** Access-control lists, complete mediation, revocation, expiry (time-bound access), least privilege (download is a separate right), link security, accountability.

**6. Limitations.** Anyone who already downloaded a copy keeps it; the server can still read the document; users can see the list of usernames when choosing recipients; no group sharing.

**7. Risks and trade-offs.** Simple and revocable but not end-to-end private; permission rows are an attack target (mitigated by DB privileges and audit).

## DDR-05: Audit Logging and Protection of Audit Records

**1. Problem.** Security-relevant activity must be recorded so that misuse can be investigated, and the records must resist silent modification or deletion, including by someone with database access.

**2. Alternatives considered.**
- *Plain table or text file logging:* trivially editable.
- *Per-row SHA-256 hash without a key:* an attacker can recompute hashes.
- *Hash chain without a key:* detects edits only if the attacker cannot rebuild the whole chain; they can.
- *HMAC per row + chain:* **chosen.**
- *Digital signatures (asymmetric):* attacker with the server cannot forge without the private key, but the signing key must live on the same server, so the gain is limited; adds complexity.
- *External WORM/SIEM storage:* the best practice for production, out of scope for the prototype.

**3. Selected approach.** Every record stores `prev_hash` and `record_hmac = HMAC-SHA-256(audit key, prev_hash + canonical fields)`. Genesis value is 64 zeros. Appends are serialized with a MySQL advisory lock. An anchor file outside MySQL stores the newest `id:hmac`, so truncating the tail is detected. The application only INSERTs; an optional MySQL user holds INSERT/SELECT only. Admins can run verification from the UI; failures are logged as `audit_verification_failure`. No passwords, keys or document contents are recorded.

**4. Justification.** Editing, deleting a middle record, or truncating the end each produce a distinct detectable error (Test 7: HMAC mismatch, chain broken, anchor mismatch). A separate HMAC key means database access alone is not enough to forge.

**5. Security concepts.** Accountability, non-repudiation (limited), integrity vs authenticity, HMAC, hash chains, append-only logs, separation of privilege, log minimization (privacy).

**6. Limitations.** An attacker who controls both the database and the HMAC key (and anchor file) can recompute a perfectly valid chain; detection requires that at least one of these remains uncompromised. Verification runs on demand rather than continuously. Records show *that* something happened, not intent.

**7. Risks and trade-offs.** Serialized inserts limit write throughput; the log grows without bound (no archival); logging every denial can be noisy but is useful for spotting ID enumeration.
