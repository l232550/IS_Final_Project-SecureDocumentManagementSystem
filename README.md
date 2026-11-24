# Secure Document Management System

CS3002 – Information Security semester project, FAST NUCES Chiniot-Faisalabad. A PHP 8.3 / MySQL application that stores documents **encrypted** (AES-256-GCM envelope encryption with a per-document DEK wrapped by a versioned KEK), enforces **RBAC + document-level permissions** on the server, supports **revocable account-based sharing**, and keeps a **tamper-evident audit trail** (HMAC-SHA-256 hash chain).

> Group members: *Roll#01, Roll#02, Roll#03 – fill in.*  Documentation is in `documentation/`.

## Features
Registration, login, logout, Argon2id passwords, reset via single-use 30-minute tokens, session hardening, lockout · upload/view/download/update/delete/verify · sharing with View/Edit/Download and expiry, revoke · admin user management, audit log view and integrity verification · CSRF, XSS escaping, CSP and security headers, generic errors · key setup, rotation, encrypted key backup/restore.

## Requirements
PHP 8.3 (extensions `openssl`, `pdo_mysql`, `fileinfo`, `mbstring`; built with Argon2), MySQL 8 / MariaDB 10.4+, Apache (MAMP). Bootstrap 5 is loaded from a CDN (internet needed in the browser). PHPUnit 9/10 for tests (dev only; the app has no Composer dependencies).

## Setup with MAMP (step by step)
1. Copy the project folder, e.g. to `/Applications/MAMP/htdocs/SecureDocumentManagementSystem` (Windows: `C:\MAMP\htdocs\...`).
2. In MAMP → Preferences → PHP select **8.3.x**. Under *Web Server → Document Root* choose the project's **`public`** folder (so `private_storage`, `config`, etc. are not web-reachable). Start the servers.
3. Create the database (phpMyAdmin or CLI; MAMP defaults: port 8889, user `root`, password `root`):
   `mysql -h127.0.0.1 -P8889 -uroot -proot < database/database.sql`
4. `cp .env.example .env` and edit `DB_*` (and `APP_URL`, e.g. `http://localhost:8888`). `.env` is never committed.
5. Check the environment: `php bin/check_env.php` (use MAMP's PHP binary, e.g. `/Applications/MAMP/bin/php/php8.3.x/bin/php`).
6. **Generate keys once:** `php bin/setup_keys.php` – creates `private_storage/keys/kek_v1.key` and `hmac.key`. It refuses to overwrite existing keys. **Back them up immediately** (`php bin/backup.php`).
7. Create an administrator: `php bin/create_admin.php <username> <email>` (prompts for password).
8. Open `http://localhost:8888/` and register users. *Demo only:* `php bin/seed_test_data.php` creates `admin_test`, `alice`, `bob`, `carol` (password `TestPassword#2026`, public, never use in production).
9. Password-reset links are written to `private_storage/logs/mail.log` (no mail server in the dev setup).

### Optional hardening
- **Append-only audit at DB level:** run `database/audit_user.sql` as root after changing its passwords, then set `DB_USER/DB_PASS` to `sdms_app` and `DB_AUDIT_USER/DB_AUDIT_PASS` to `sdms_audit` in `.env`.
- **HTTPS:** enable SSL for the MAMP virtual host with a certificate (self-signed for development). The app then sets `Secure` cookies and HSTS automatically. Prefer TLS 1.3 / disable old protocols in the Apache SSL config. *(HTTPS was not exercised in the development container; test it on your setup.)*
- Production: set `APP_ENV=production`, keep `display_errors` off, restrict permissions of `private_storage/keys`.

## Running the tests
```
phpunit --testdox                       # 26 automated tests on a scratch DB (needs a DB user that can CREATE DATABASE)
php bin/security_demo.php               # readable evidence for the 7 assignment scenarios (scratch DB sdms_demo)
bash tests/http_e2e.sh                  # HTTP checks against the running app (seeded accounts); set BASE and MYSQL_CMD for MAMP
```
For MAMP set the same DB values in `.env`; tests use their own databases `sdms_test` and `sdms_demo` and separate throw-away keys in `tests/_tmp/`.

## Operations
| Task | Command |
|---|---|
| First-time keys | `php bin/setup_keys.php` |
| Rotate KEK and rewrap DEKs | `php bin/rotate_kek.php` (`--resume` if interrupted) |
| Backup DB + encrypted files + protected keys | `php bin/backup.php` |
| Restore keys from backup | `php bin/restore_keys.php backups/<ts>/keys.enc` |
| Environment check | `php bin/check_env.php` |

## Project structure
`public/` (only web root) · `config/` · `controllers/` · `models/` · `services/` · `repositories/` · `views/` · `private_storage/` (encrypted files, keys, logs; Git-ignored) · `database/` · `bin/` · `tests/` · `documentation/`

## Security notes and limitations
See `documentation/12_limitations.md`. Highlights: server-side (not end-to-end) encryption; KEK on the same host; 10 MB whole-file GCM; no MFA; revocation cannot recall downloaded copies; an attacker holding both DB and HMAC key can forge the audit chain.

## Acknowledgements
PHP OpenSSL, PDO, PHPUnit, Bootstrap 5, Graphviz (diagrams), NIST SP 800-38D, OWASP ASVS / Cheat Sheets. See `documentation/13_references.md`. Code was written for this project; no tutorial project was copied.
