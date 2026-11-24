# Backup and Recovery

**Principle:** the database, the encrypted files, and the KEK versions they need are *one logical backup* and must be restored together. A database without the matching keys or files cannot be decrypted; keys without ciphertext are useless; ciphertext restored from a different point in time than the database fails verification (tag/fingerprint mismatch) or is missing.

## Backup
```
php bin/backup.php            # prompts for a passphrase (or set SDMS_BACKUP_PASSPHRASE)
```
Creates `backups/<timestamp>/` containing `database.sql` (mysqldump), `documents/` (encrypted files only), and `keys.enc` (all KEK versions + HMAC key, AES-256-GCM under a PBKDF2-derived key). The passphrase is never stored. Store `keys.enc` and the passphrase **separately** from the database dump and file backup (different media/location), otherwise one theft yields everything.

## Key version management
Each document records `key_version`. After rotation all rows point to the newest KEK, but **older KEK files must be kept** until every backup that references them is retired, because an old backup's `database.sql` still wraps DEKs with the old version. `key_versions` shows active/retired status.

## Recovery procedure
1. Install the application, create an empty database, import `database/database.sql` schema only if not restoring a dump.
2. `mysql sdms < backups/<ts>/database.sql`
3. Copy `backups/<ts>/documents/*.enc` into `private_storage/documents/`.
4. `php bin/restore_keys.php backups/<ts>/keys.enc` (asks for passphrase; refuses to overwrite existing key files).
5. Log in as admin → *Audit logs → Verify audit integrity*; open a document and press *Verify integrity*.
6. Record the recovery (`key_backup`/`kek` events are audited automatically for backups).

## Compromise scenarios
- **Lost KEK, no backup:** affected documents are permanently unreadable.
- **KEK suspected stolen:** run `php bin/rotate_kek.php`, restrict old key files, consider re-encrypting documents (not automated).
- **Lost backup passphrase:** key backup is unrecoverable; take a new backup immediately while keys still exist.

## What was verified
Backup, restore into an empty key directory (byte-identical keys), wrong-passphrase rejection, and absence of plaintext in the backup folder were run in the development container. Full database/file restore on a second machine was **not** exercised and should be rehearsed by the team before the demo.
