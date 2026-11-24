# Threat Model

## Assets
| ID | Asset | Why it matters |
|--|---------|-------------|
| A1 | Document contents | Confidentiality is the core purpose |
| A2 | Document integrity | Undetected modification would be a silent compromise |
| A3 | KEK versions and HMAC key | Whoever holds them breaks A1 or forges A5 |
| A4 | User credentials and sessions | Control of an account = its access |
| A5 | Audit trail | Accountability and forensics |
| A6 | Metadata and permissions | Reveals who has what; permission integrity governs A1 |

## Trust boundaries
TB-1 user devices/Internet (untrusted) → TB-2 application server (trusted code, enforces policy) → TB-3 data tier (MySQL, encrypted files) → TB-4 key store (separate permissions, offline backup). See the diagram in Chapter 2.

## Assumptions
The application server and the OS account running PHP are not compromised; TLS is enabled in deployment; the administrator protects the key directory and the backup passphrase.

## Threats and mitigations (STRIDE)
| # | Threat | Category | Asset | Mitigation in this system | Residual risk |
|--|-------|-----|--|-------------|-------|
| T-01 | User reads another user's document | Information disclosure | A1 | Server-side `AuthorizationService` on every operation; generic denial | Bugs in policy code |
| T-02 | Changing `id` in URL (IDOR) | Information disclosure | A1 | Same check regardless of ID; missing and forbidden documents indistinguishable; denials audited | Sequential IDs reveal volume |
| T-03 | Stolen storage folder | Information disclosure | A1 | AES-256-GCM, per-document DEK, UUID names, keys elsewhere | File sizes/timestamps visible |
| T-04 | Stolen DB dump | Information disclosure | A1, A6 | DB has only wrapped DEKs; no keys | Metadata readable |
| T-05 | Ciphertext modified on disk | Tampering | A2 | GCM tag + AAD + SHA-256 fingerprint; failure refuses release and is logged | Attacker can still delete/corrupt (availability) |
| T-06 | Viewer escalates to edit/delete/share | Elevation of privilege | A1, A2 | Per-action policy; only owner may delete/share | — |
| T-07 | Revoked/expired user keeps access | Elevation of privilege | A1 | Permissions read fresh on every request | Downloaded copies |
| T-08 | Audit rows edited/deleted | Tampering, repudiation | A5 | HMAC chain, anchor file, INSERT-only DB user | Attacker holding DB + HMAC key |
| T-09 | SQL injection | Tampering, disclosure | all | PDO prepared statements everywhere, no dynamic SQL from input | — |
| T-10 | XSS | Spoofing, disclosure | A4 | Output escaping `e()`, CSP without inline scripts, HttpOnly cookie | CDN scripts trusted |
| T-11 | CSRF | Tampering | A1, A6 | Per-session token on every POST, SameSite=Strict | — |
| T-12 | Brute force / credential stuffing | Spoofing | A4 | Argon2id, per-account and per-IP lockout, generic errors, constant-work check for unknown users | Distributed attacks, no MFA |
| T-13 | Session hijack/fixation | Spoofing | A4 | HttpOnly, Secure (HTTPS), SameSite, regenerate on login, 30-min timeout | Stolen device |
| T-14 | Malicious upload (web shell, polyglot) | Tampering | A1 | Extension + finfo MIME + magic bytes, double-extension and executable checks, never executed or served raw from a web path, random names | DOCX macros opened by the user |
| T-15 | Path traversal | Tampering | A1 | User input never reaches paths; UUID regex check | — |
| T-16 | Config/secret exposure | Information disclosure | A3 | `.env` and keys outside `public/`, `.gitignore`, deny-all `.htaccess` | Misconfigured server |
| T-17 | Error leakage | Information disclosure | all | `display_errors=0`, generic error page, server-side log | Log access |
| T-18 | Key loss | Denial of service | A1, A3 | Encrypted key backup, versioned KEKs | Lost passphrase = lost keys |
| T-19 | Admin abuse | Elevation of privilege | A1 | Admin has no document access; admin actions audited | Admin can edit users/roles |
| T-20 | Reset-token theft | Spoofing | A4 | 256-bit token, stored hashed, 30-min expiry, single use | Mailbox compromise |
