-- OPTIONAL hardening (append-only audit at database level). Run as MySQL root AFTER database.sql.
-- 1) The audit user may only INSERT and SELECT on audit_logs (no UPDATE/DELETE).
-- 2) The normal application user gets no access to audit_logs at all.
-- Replace the passwords below with your own and put them in .env (DB_USER/DB_PASS, DB_AUDIT_USER/DB_AUDIT_PASS).
CREATE USER IF NOT EXISTS 'sdms_app'@'localhost'   IDENTIFIED BY 'CHANGE_ME_APP_PASSWORD';
CREATE USER IF NOT EXISTS 'sdms_audit'@'localhost' IDENTIFIED BY 'CHANGE_ME_AUDIT_PASSWORD';
GRANT SELECT, INSERT, UPDATE, DELETE ON sdms.users                TO 'sdms_app'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON sdms.documents            TO 'sdms_app'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON sdms.document_permissions TO 'sdms_app'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON sdms.login_attempts       TO 'sdms_app'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON sdms.password_resets      TO 'sdms_app'@'localhost';
GRANT SELECT, INSERT, UPDATE         ON sdms.key_versions         TO 'sdms_app'@'localhost';
GRANT SELECT, INSERT                 ON sdms.audit_logs           TO 'sdms_audit'@'localhost';
FLUSH PRIVILEGES;
