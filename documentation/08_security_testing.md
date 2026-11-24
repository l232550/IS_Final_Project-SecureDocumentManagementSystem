# Security Testing Report

All tests were run on the project's own code with controlled test accounts and scratch data (databases `sdms_test`, `sdms_demo`, and a seeded development database). No other system was tested.

## How the evidence was produced

- `phpunit` – automated tests (26 tests, 61 assertions) on an isolated scratch database/storage.
- `php bin/security_demo.php` – readable walkthrough of the seven scenarios on a scratch database.
- `bash tests/http_e2e.sh` – real HTTP requests (curl) against the running web application with seeded test accounts.
- Mutation check: temporarily making the authorization function always return true caused 8 tests to fail, and ignoring GCM tag failures caused 4 tests to fail; the original code was restored and all 26 pass. This shows the tests detect broken security code.

The raw outputs below were captured during development in a Linux container (PHP 8.3.6, MariaDB 10.11). **The team must re-run them on its own MAMP setup and add its own screenshots** (see the screenshot checklist at the end).

## Test 1: Unauthorized document access

| Item | Description |
|---|---|
| Threat | Information disclosure (STRIDE-I) |
| Targeted asset | Document owned by alice (A1) |
| Procedure | 1. Alice uploads a document. 2. Bob (no grant) requests view and download. 3. Inspect audit log. |
| Expected behaviour | Both requests denied; `unauthorized_access` recorded. |
| Actual behaviour | Both denied with the generic message; two `unauthorized_access` records written. Owner access still works. HTTP: 403. |
| Limitation discovered | A user learns nothing about whether the document exists. Sequential IDs still show approximate volume. |
| Possible improvement | Use non-sequential public IDs (UUIDs) in URLs; add alerting on repeated denials. |

**Evidence (output of `bin/security_demo.php`):**

```
Threat      : A logged-in user reads another user's private document
Asset       : Document #1 owned by alice
bob views doc -> DENIED (Access denied.)
bob downloads doc -> DENIED (Access denied.)
alice views doc -> ALLOWED  (owner, expected)
Audit trail:
  #1   document_upload        user=1   doc=1   success  hmac=b567e89b7ebc...
  #2   unauthorized_access    user=2   doc=1   denied   hmac=a7b3bd8f18b6...
  #3   unauthorized_access    user=2   doc=1   denied   hmac=121a9029deea...
  #4   document_view          user=1   doc=1   success  hmac=332439e67913...
```

## Test 2: Identifier manipulation (IDOR)

| Item | Description |
|---|---|
| Threat | Information disclosure |
| Targeted asset | Other users' documents (A1) |
| Procedure | 1. Bob requests ids around Alice's document and a non-existent id. 2. Compare responses and audit entries. |
| Expected behaviour | Every request denied; existing and non-existing ids indistinguishable. |
| Actual behaviour | All denied with identical message and HTTP 403; each attempt audited with the requested id. |
| Limitation discovered | Sequential integer IDs make enumeration attempts easy to try (though all fail). |
| Possible improvement | Opaque UUID identifiers in URLs; rate-limit denials per user. |

**Evidence (output of `bin/security_demo.php`):**

```
Threat      : Attacker edits the document id in the request (?id=N) to reach another user's file
bob requests id=1 -> DENIED (Access denied.)
bob requests id=2 -> DENIED (Access denied.)
bob requests id=3 -> DENIED (Access denied.)
bob requests id=4 -> DENIED (Access denied.)
bob requests id=999999 -> DENIED (Access denied.)
Existing and non-existing ids give the SAME answer, so ids cannot be enumerated.
Audit trail:
  #2   unauthorized_access    user=2   doc=1   denied   hmac=a7b3bd8f18b6...
  #3   unauthorized_access    user=2   doc=2   denied   hmac=2930ad2ea2b3...
  #4   unauthorized_access    user=2   doc=3   denied   hmac=a0a681b0ee5f...
  #5   unauthorized_access    user=2   doc=4   denied   hmac=b6505df6904e...
  #6   unauthorized_access    user=2   doc=999999 denied   hmac=383f9e993402...
```

## Test 3: Storage exposure

| Item | Description |
|---|---|
| Threat | Information disclosure |
| Targeted asset | Encrypted files, wrapped DEKs (A1, A3) |
| Procedure | 1. Upload a document. 2. As an attacker with the file system, read the `.enc` file. 3. Search it for the plaintext. 4. Try to decrypt with a guessed key. |
| Expected behaviour | Contents cannot be understood without the keys; only size/timestamps/count leak. |
| Actual behaviour | File is a UUID-named random-looking blob; no plaintext found; decryption with a wrong key fails authentication. |
| Limitation discovered | Sizes, timing, number of files leak; DB metadata (original names, owners) is plaintext. Same-server KEK is the weak point if the whole host is lost. |
| Possible improvement | Move KEK to a KMS/HSM; encrypt sensitive metadata; pad file sizes if size leakage matters. |

**Evidence (output of `bin/security_demo.php`):**

```
Threat      : Attacker copies the private storage folder (and even the DB row)
File on disk: 064f5b22-08dc-4e86-ae9b-e81b6f4539a1.enc (random UUID, original name not visible)
Size        : 65 bytes (plaintext was 65)
Hex dump    : a71066ba e29c5b39 5435d628 562c2d46 6c5eda3b fdb177f4 066df0d1 afe642c4 
Contains text: no - plaintext not present in the file
decrypt with a guessed key : FAILED  (Authentication failed: data was modified or the key is wrong.)
What CAN be learned: file size (approx), number of files, upload timing (file mtime), and from the DB: original filenames, owners, types, sizes.
What CANNOT be learned: contents. The DEK is stored only wrapped by the KEK, and the KEK lives outside the DB/storage in private_storage/keys.
```

## Test 4: Document modification

| Item | Description |
|---|---|
| Threat | Tampering |
| Targeted asset | Document integrity (A2) |
| Procedure | 1. Verify a document (OK). 2. Outside the app, flip one bit of the stored `.enc` file. 3. Verify again and try to view. |
| Expected behaviour | AES-GCM authentication fails; document is not released; event logged. |
| Actual behaviour | Verification reports INTEGRITY FAILURE; opening is refused; two `integrity_failure` audit records. PHPUnit additionally shows that swapping ciphertext between two documents is detected (AAD). |
| Limitation discovered | Attacker can still corrupt or delete files (availability); an attacker holding both the file and the DB row can corrupt both but still cannot produce a valid tag without the DEK. |
| Possible improvement | Keep integrity-checked backups; scheduled background verification; alerting. |

**Evidence (output of `bin/security_demo.php`):**

```
Before tamper: OK - Integrity verified: AES-GCM tag and SHA-256 fingerprint both match.
Attack      : flipped ONE bit at byte offset 7 of the stored encrypted file
After tamper: DETECTED - INTEGRITY FAILURE: the stored document was modified, is missing, or the key does not match.
alice opens tampered doc -> REJECTED (Authentication failed: data was modified or the key is wrong.)
Audit trail:
  #1   document_upload        user=1   doc=1   success  hmac=9eed93099a92...
  #2   document_verify        user=1   doc=1   success  hmac=e99462e764fc...
  #3   integrity_failure      user=1   doc=1   failure  hmac=2a5f7afaa032...
  #4   document_verify        user=1   doc=1   failure  hmac=c3373de3135d...
  #5   integrity_failure      user=1   doc=1   failure  hmac=555693b35d07...
```

## Test 5: Privilege misuse

| Item | Description |
|---|---|
| Threat | Elevation of privilege |
| Targeted asset | Documents and permissions (A1, A2, A6) |
| Procedure | 1. Alice shares with Bob as Viewer without download. 2. Bob tries download, update, delete, re-share. 3. Admin tries to open Alice's document. |
| Expected behaviour | View allowed; every other action denied; admin has no automatic content access. |
| Actual behaviour | Only view succeeded. Download, update, delete and share were denied with `failed_authorization` records; the document remained intact; admin was denied (`unauthorized_access`). |
| Limitation discovered | A viewer who may *view* can still copy visible content (screenshots); DOCX preview is unavailable so viewer of a DOCX cannot see content in the browser. |
| Possible improvement | Watermarking and view-only rendering; DLP policies. |

**Evidence (output of `bin/security_demo.php`):**

```
Setup       : alice shared the document with bob as VIEWER (no download)
bob views -> ALLOWED  (expected: allowed)
bob downloads -> DENIED (Access denied.)
bob updates -> DENIED (Access denied.)
bob deletes -> DENIED (Access denied.)
bob re-shares -> DENIED (Access denied.)
ADMIN views alice's doc -> DENIED (Access denied.)  (admin has no automatic content access)
Document still exists: yes
Audit trail:
  #3   document_view          user=2   doc=1   success  hmac=8c9b0f82c60c...
  #4   failed_authorization   user=2   doc=1   denied   hmac=3062cd71472c...
  #5   failed_authorization   user=2   doc=1   denied   hmac=bd35243020f4...
  #6   failed_authorization   user=2   doc=1   denied   hmac=2592a19dd8e7...
  #7   failed_authorization   user=2   doc=1   denied   hmac=662d1d50f2b9...
  #8   unauthorized_access    user=3   doc=1   denied   hmac=ce5da3197c9a...
```

## Test 6: Permission revocation

| Item | Description |
|---|---|
| Threat | Elevation of privilege |
| Targeted asset | Previously shared document (A1) |
| Procedure | 1. Alice shares with Bob (download allowed). 2. Bob downloads. 3. Alice revokes. 4. Bob requests view/download again. |
| Expected behaviour | Access denied immediately after revocation; earlier copies cannot be recalled. |
| Actual behaviour | Both later requests denied; revocation audited. The copy Bob downloaded earlier remains with him (limitation demonstrated). |
| Limitation discovered | Downloaded copies cannot be revoked; revocation is only as good as the server. |
| Possible improvement | View-only mode without download; watermarking; short expiry times; document-level DRM. |

**Evidence (output of `bin/security_demo.php`):**

```
bob downloads while shared -> ALLOWED
Action      : alice revoked bob's access
bob views after revoke -> DENIED (Access denied.)
bob downloads after revoke -> DENIED (Access denied.)
LIMITATION: bob's earlier downloaded copy still exists on his machine (65 bytes). Revocation stops FUTURE access only.
Audit trail:
  #1   document_upload        user=1   doc=1   success  hmac=9eed93099a92...
  #2   sharing_created        user=1   doc=1   success  hmac=241c25bb6844...
  #3   document_download      user=2   doc=1   success  hmac=a18de8f76177...
  #4   permission_revoked     user=1   doc=1   success  hmac=cfa5cf53f492...
  #5   unauthorized_access    user=2   doc=1   denied   hmac=029fa89ed014...
  #6   unauthorized_access    user=2   doc=1   denied   hmac=4d5d7cdba31e...
```

## Test 7: Audit tampering

| Item | Description |
|---|---|
| Threat | Tampering / repudiation |
| Targeted asset | Audit trail (A5) |
| Procedure | On a scratch DB: (A) modify a record's outcome; (B) delete a middle record; (C) delete the newest record; run verification after each. |
| Expected behaviour | Each alteration is detected. |
| Actual behaviour | A: HMAC mismatch. B: chain broken. C: anchor mismatch. After restoring the rows verification passes again. |
| Limitation discovered | An attacker who controls the database, the HMAC key and the anchor file can forge a consistent chain; verification is on demand. |
| Possible improvement | Ship logs to external append-only storage / SIEM; sign with an off-host key; scheduled verification with alerts. |

**Evidence (output of `bin/security_demo.php`):**

```
Baseline    : VALID (no tampering) - 4 records
Attack A    : modify a record (change the denied outcome of bob's attempt to 'success')
Result A    : TAMPERING DETECTED: Record #2: HMAC mismatch (record content was modified).
Attack B    : delete a record from the middle of the log
Result B    : TAMPERING DETECTED: Record #2: chain broken (a previous record was removed, inserted or altered).
Attack C    : delete the newest record (truncate the tail)
Result C    : TAMPERING DETECTED: Chain head does not match the stored anchor (newest records were deleted or replaced).
Restored    : VALID (no tampering)
LIMITATION: an attacker who controls BOTH the database and private_storage/keys/hmac.key can recompute every HMAC and forge a valid chain.

Done. (Scratch data only: database sdms_demo and tests/_tmp/demo_storage.)
```

## Supporting automated results

### PHPUnit (`phpunit --testdox`)

```
PHPUnit 9.6.17 by Sebastian Bergmann and contributors.

Crypto Service
 ✔ Round trip
 ✔ Nonce is fresh every time
 ✔ Flipped ciphertext bit is rejected
 ✔ Wrong key or wrong aad is rejected
 ✔ Argon 2id is used for passwords

Security Scenarios
 ✔ T 1 unauthorized document access is denied
 ✔ T 2 identifier manipulation does not leak documents
 ✔ T 3 storage exposure reveals nothing
 ✔ T 3 wrapped dek cannot be unwrapped with wrong kek
 ✔ T 4 modified ciphertext is detected
 ✔ T 4 ciphertext swap between documents is detected
 ✔ T 5 viewer cannot edit delete share or download
 ✔ Admin has no automatic access to document contents
 ✔ Editor can update but not delete
 ✔ T 6 revoked user loses access immediately
 ✔ Expired permission is denied
 ✔ T 7 untouched audit chain verifies
 ✔ T 7 modified audit record is detected
 ✔ T 7 deleted middle record breaks the chain
 ✔ T 7 deleted last records are detected by anchor
 ✔ Upload validation rejects dangerous files
 ✔ Oversize file is rejected
 ✔ Key rotation rewraps deks and documents still decrypt
 ✔ Key setup refuses to overwrite existing keys
 ✔ Audit records contain no secrets
 ✔ Login lockout after repeated failures

Time: 00:07.323, Memory: 16.00 MB

OK (26 tests, 61 assertions)
```

### HTTP end-to-end checks (`tests/http_e2e.sh`)

```
[PASS] alice views own document                             got=200 expected=200
[PASS] bob views alice's document (T1)                      got=403 expected=403
[PASS] bob guesses non-existent id (T2)                     got=403 expected=403
[PASS] anonymous request redirected to login                got=302 expected=302
[PASS] POST delete without CSRF token                       got=403 expected=403
[PASS] disallowed HTTP method (GET on POST route)           got=405 expected=405
[PASS] bob (viewer) views after share                       got=200 expected=200
[PASS] bob downloads without download flag (T5)             got=403 expected=403
[PASS] bob opens update page as viewer (T5)                 got=403 expected=403
[PASS] bob deletes as viewer (T5)                           got=403 expected=403
[PASS] bob opens share page (T5)                            got=403 expected=403
[PASS] admin opens alice's document                         got=403 expected=403
[PASS] bob views after revoke (T6)                          got=403 expected=403
[PASS] bob (editor+download) downloads                      got=200 expected=200
[PASS] bob after permission expiry                          got=403 expected=403
[PASS] normal user opens admin audit page                   got=403 expected=403
[PASS] reset token issued to server log (64 hex chars)      got=64 expected=64
[PASS] carol can sign in with the NEW password              got=1 expected=1
[PASS] reset link cannot be used a second time              got=1 expected=1
[PASS] correct password refused while locked out            got=0 expected=0
[INFO] admin audit verification: Audit chain verified: 35 records
[INFO] cookie flags: Set-Cookie: SDMSSESSID=eki0u3tje9u0a1u5aubd7q0k00; path=/; HttpOnly; SameSite=Strict
```

## Additional verified behaviours
- Password hashes are Argon2id; lockout after 5 failures (username and email count together); correct password is refused while locked.
- Password reset: token issued to server log, single use, reuse rejected, new password works (covered by tests/http_e2e.sh).
- Upload validation rejects PHP disguised as PDF, double extensions, executables, HTML, binary data in TXT and files over 10 MB.
- KEK rotation rewraps DEKs and documents still verify; setup refuses to overwrite existing keys.
- Append-only audit DB user: UPDATE/DELETE/TRUNCATE refused (tested with MariaDB 10.11).
- Security headers (CSP, nosniff, X-Frame-Options DENY, Referrer-Policy) and cookie flags (HttpOnly, SameSite=Strict) observed in HTTP responses.

## Not verified in the development container
- HTTPS/TLS 1.3 and the `Secure` cookie flag / HSTS header (only code paths exist; enable HTTPS in MAMP and check).
- Behaviour on Apache/MAMP, macOS or Windows paths, and `mysqldump` of MAMP.
- Browser rendering of the UI (Bootstrap is loaded from a CDN) and PDF inline viewing in each browser.
- Restore of a full backup on a second machine.

## Screenshot checklist (to be captured by the team on the real setup)
1. Test 1 – Bob gets *Access denied* when opening Alice's document URL.
2. Test 2 – Bob editing `id=` in the address bar; same denial page.
3. Test 3 – terminal listing of `private_storage/documents` and `xxd` of an `.enc` file.
4. Test 4 – *Verify integrity* success, then failure after the bit-flip.
5. Test 5 – Viewer's document page without Update/Delete/Share buttons and the denial when calling the URLs directly.
6. Test 6 – Share page, revocation, then Bob's denied access.
7. Test 7 – Admin *Verify audit integrity* showing valid, then TAMPERING DETECTED after the SQL edit.
8. `phpunit --testdox` and `php bin/security_demo.php` terminal output.
