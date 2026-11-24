# Database and Storage Design

## What is stored where
| Data | Location | Why |
|---|---|---|
| Account data, Argon2id hashes, roles | MySQL `users` | Relational, small, needs uniqueness constraints |
| Document metadata, wrapped DEK, nonces, tags, fingerprint, key version | MySQL `documents` | Needed to authorize, list, verify and decrypt; useless without the KEK |
| Sharing grants | MySQL `document_permissions` | Must be queried and revoked transactionally |
| Audit chain | MySQL `audit_logs` (+ anchor file) | Queryable for admins; integrity via HMAC |
| Rate-limit and reset-token state | MySQL `login_attempts`, `password_resets` | Needs time-window queries; tokens stored only as SHA-256 |
| Key version metadata | MySQL `key_versions` | Which KEK is active; **no key material** |
| Encrypted document bodies | `private_storage/documents/<uuid>.enc` | Large binary data is better on the file system; outside web root |
| KEKs and HMAC key | `private_storage/keys/*.key` (chmod 600) | Must not be in the DB, repo or web root |
| Mail-substitute log, PHP error log | `private_storage/logs/` | Server-side only |

## Tables
`users` (PK id; unique username, email) · `key_versions` (PK version) · `documents` (PK id; FK owner_id→users RESTRICT, FK key_version→key_versions; unique storage_id; indexes on owner and key_version) · `document_permissions` (PK id; unique (document_id,user_id); FKs cascade on document/user delete) · `audit_logs` (PK id; **no foreign keys on purpose**, so records survive deleting users or documents) · `login_attempts` (indexes on identifier+time and ip+time) · `password_resets` (unique token_hash; FK user cascade). The full DDL is `database/database.sql`; all tables are InnoDB with `NOT NULL` where applicable and `DATETIME` timestamps written by PHP in one timezone.

## `documents` columns required by the specification
Document ID (`id`), Original Filename, Owner ID, File Type, File Size, Upload Date (`created_at`), Encrypted Storage Identifier (`storage_id`), Encryption Algorithm, Document Nonce (`doc_nonce`, 12 B), Authentication Tag (`auth_tag`, 16 B), Wrapped DEK (32 B), DEK Wrapping Nonce (`dek_nonce`), DEK Wrapping Authentication Tag (`dek_tag`), SHA-256 Fingerprint, Key Version.

## Design note: AAD
The specification lists "Document ID" as the context; because the numeric ID does not exist before the row is inserted, the *storage UUID* (known before encryption) is used as AAD instead. It serves the same purpose, binding the ciphertext to one document.

## What an attacker gets
| Compromise | Obtains | Does not obtain |
|---|---|---|
| Storage folder only | Ciphertext, sizes, timestamps | Names, contents, keys |
| Database only | Metadata, wrapped DEKs, hashes, audit | Contents (needs KEK) |
| Storage + DB | Same as DB plus ciphertext | Contents (needs KEK) |
| Key directory only | KEKs and HMAC key | Ciphertext and wrapped DEKs |
| Everything | Contents and ability to forge audit | — |

## Append-only audit at DB level (optional, verified)
`database/audit_user.sql` creates `sdms_app` (no access to `audit_logs`) and `sdms_audit` (INSERT and SELECT only). Tested: UPDATE, DELETE and TRUNCATE by the audit user are refused, and the app user cannot read `audit_logs`.
