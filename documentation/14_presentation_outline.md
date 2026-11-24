# Presentation Outline and Demo Script (about 12-15 minutes)

## Slides
1. **Title and team** – project, roll numbers, course.
2. **Problem and goals** – confidential documents, sharing, accountability; "build a defensible system".
3. **Architecture** – layered diagram, public/ vs private_storage, three components (files, DB, keys).
4. **Threat model** – assets, trust boundaries, top threats (T-01 … T-08).
5. **Cryptographic design** – DEK/KEK envelope, AES-256-GCM, nonces, AAD, SHA-256; why GCM vs hash.
6. **Key management** – setup once, rotation + rewrap, encrypted backup, recovery.
7. **Access control** – roles, permission matrix, single gate `fetchAuthorized`, admin has no content access.
8. **Sharing and revocation** – account-based, expiry, why no public links.
9. **Audit design** – HMAC chain, anchor, append-only DB user, limitation.
10. **Live demo** (see below).
11. **Security test results** – seven scenarios, 26 PHPUnit tests, mutation check.
12. **Limitations and future work** – E2E encryption, KMS, MFA, external log store.
13. **Q&A** – be ready for: why AES-GCM, why per-document DEK, what if the KEK leaks, why admin cannot read documents, what revocation cannot do, how nonce reuse is avoided, what an attacker with DB access learns.

## Live demo script
1. Show `private_storage/documents` listing and `xxd` of one `.enc` file (Test 3).
2. Log in as **alice**: upload a TXT/PDF; open *Details* (see algorithm, KEK version, fingerprint); press *Verify integrity*.
3. Log in as **bob** (second browser/private window): paste Alice's document URL → *Access denied*; change the `id=` value (Tests 1-2).
4. Alice shares with Bob as *Viewer, no download*; Bob views but sees no Update/Delete/Share/Download; call `?r=doc/download&id=N` manually → denied (Test 5).
5. Alice revokes; Bob reloads → denied (Test 6). Mention downloaded copies.
6. In a terminal flip one byte of the `.enc` file; Alice presses *Verify integrity* → failure; restore the file from backup (Test 4).
7. Log in as admin: *Audit logs* → *Verify audit integrity* (valid). In MySQL: `UPDATE audit_logs SET outcome='success' WHERE id=…;` → verify again → **TAMPERING DETECTED** (Test 7). *Do this on the demo database only and take a backup first, because the chain stays broken afterwards.*
8. Show `phpunit --testdox` and `php bin/security_demo.php` output.
