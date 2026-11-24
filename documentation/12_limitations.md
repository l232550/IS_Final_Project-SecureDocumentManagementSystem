# Limitations and Future Work

| Area | Limitation | Possible improvement |
|---|---|---|
| Confidentiality | Server-side encryption: the server (and its operator) can read plaintext during a request | End-to-end encryption with per-user key pairs |
| Keys | KEK/HMAC key are files on the app host | KMS/HSM, separate key service, split duties |
| Rotation | Rotation rewraps DEKs only; no automated document re-encryption | Add re-encrypt job when a DEK exposure is suspected |
| Large files | Whole-file AES-GCM, 10 MB cap, memory-bound | Chunked AEAD format designed with a cryptographer, or libsodium secretstream |
| Metadata | Filenames, sizes, owners, sharing graph stored unencrypted | Encrypt sensitive metadata columns |
| Authentication | No MFA; lockout can be abused to lock a victim out temporarily; IP throttling weak behind NAT/proxy; reset links logged not e-mailed | TOTP/WebAuthn, CAPTCHA/backoff, SMTP delivery |
| Sharing | Downloaded copies cannot be revoked; username list visible to owners when sharing; no groups | View-only mode, watermarking, user search by exact e-mail |
| Preview | DOCX cannot be previewed in the browser | Server-side conversion in a sandbox |
| Audit | Forgeable by an attacker with DB + HMAC key + anchor; verified on demand; no archival | External WORM/SIEM, off-host signing, scheduled verification |
| Frontend | Bootstrap loaded from CDN without Subresource Integrity | Self-host assets or add SRI hashes |
| Deletion | File deletion is not forensic erasure | Encrypted volumes, DEK destruction as the deletion guarantee |
| Availability | Bulk upload/DoS protections limited to size cap and login lockout | Request throttling at the web server |
| Testing | HTTPS, MAMP specifics and cross-browser behaviour not verified here | Verify on target environment |
