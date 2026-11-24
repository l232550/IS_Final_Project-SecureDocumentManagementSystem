-- Secure Document Management System - database schema (MySQL 8 / MariaDB 10.4+)
-- Contains NO secrets. KEK and HMAC keys are never stored here.
CREATE DATABASE IF NOT EXISTS sdms CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE sdms;

CREATE TABLE IF NOT EXISTS users (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  username      VARCHAR(30)  NOT NULL,
  email         VARCHAR(190) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,              -- Argon2id hash
  role          ENUM('admin','user') NOT NULL DEFAULT 'user',
  is_active     TINYINT(1)   NOT NULL DEFAULT 1,
  last_login_at DATETIME NULL,
  created_at    DATETIME NOT NULL,
  updated_at    DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_username (username),
  UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS key_versions (
  version     INT UNSIGNED NOT NULL,
  status      ENUM('active','retired') NOT NULL,
  created_at  DATETIME NOT NULL,
  retired_at  DATETIME NULL,
  PRIMARY KEY (version)
) ENGINE=InnoDB;                                    -- metadata only, never key material

CREATE TABLE IF NOT EXISTS documents (
  id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  owner_id           INT UNSIGNED NOT NULL,
  original_filename  VARCHAR(255) NOT NULL,
  file_type          ENUM('pdf','docx','txt') NOT NULL,
  mime_type          VARCHAR(120) NOT NULL,
  file_size          INT UNSIGNED NOT NULL,          -- plaintext size in bytes
  storage_id         CHAR(36) NOT NULL,              -- random UUID = physical file name
  enc_algorithm      VARCHAR(20) NOT NULL DEFAULT 'AES-256-GCM',
  doc_nonce          VARBINARY(12) NOT NULL,         -- 96-bit GCM nonce for the document
  auth_tag           VARBINARY(16) NOT NULL,         -- 128-bit GCM tag for the document
  wrapped_dek        VARBINARY(32) NOT NULL,         -- DEK encrypted with the KEK
  dek_nonce          VARBINARY(12) NOT NULL,         -- separate nonce for DEK wrapping
  dek_tag            VARBINARY(16) NOT NULL,         -- GCM tag for DEK wrapping
  sha256_fingerprint CHAR(64) NOT NULL,              -- SHA-256 of the plaintext
  key_version        INT UNSIGNED NOT NULL,          -- KEK version that wrapped the DEK
  created_at         DATETIME NOT NULL,
  updated_at         DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_documents_storage (storage_id),
  KEY idx_documents_owner (owner_id),
  KEY idx_documents_keyver (key_version),
  CONSTRAINT fk_documents_owner FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_documents_keyver FOREIGN KEY (key_version) REFERENCES key_versions(version)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS document_permissions (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  document_id  INT UNSIGNED NOT NULL,
  user_id      INT UNSIGNED NOT NULL,                -- recipient
  can_view     TINYINT(1) NOT NULL DEFAULT 1,
  can_edit     TINYINT(1) NOT NULL DEFAULT 0,
  can_download TINYINT(1) NOT NULL DEFAULT 0,
  expires_at   DATETIME NULL,
  granted_by   INT UNSIGNED NOT NULL,
  created_at   DATETIME NOT NULL,
  updated_at   DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_perm_doc_user (document_id, user_id),
  KEY idx_perm_user (user_id),
  CONSTRAINT fk_perm_doc  FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE,
  CONSTRAINT fk_perm_user FOREIGN KEY (user_id)     REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_perm_by   FOREIGN KEY (granted_by)  REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- No foreign keys on purpose: audit rows must survive deletion of users/documents.
CREATE TABLE IF NOT EXISTS audit_logs (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id     INT UNSIGNED NULL,
  action      VARCHAR(50)  NOT NULL,
  document_id INT UNSIGNED NULL,
  outcome     ENUM('success','failure','denied') NOT NULL,
  ip_address  VARCHAR(45)  NOT NULL,
  user_agent  VARCHAR(255) NOT NULL,
  details     VARCHAR(500) NOT NULL,
  created_at  DATETIME NOT NULL,
  prev_hash   CHAR(64) NOT NULL,                     -- record_hmac of previous row
  record_hmac CHAR(64) NOT NULL,                     -- HMAC-SHA-256 over this row + prev_hash
  PRIMARY KEY (id),
  KEY idx_audit_user (user_id),
  KEY idx_audit_action (action),
  KEY idx_audit_doc (document_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS login_attempts (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  identifier   VARCHAR(190) NOT NULL,
  ip_address   VARCHAR(45)  NOT NULL,
  success      TINYINT(1)   NOT NULL,
  attempted_at DATETIME     NOT NULL,
  PRIMARY KEY (id),
  KEY idx_la_ident (identifier, attempted_at),
  KEY idx_la_ip (ip_address, attempted_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS password_resets (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id    INT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL,                      -- SHA-256 of the token (token itself never stored)
  expires_at DATETIME NOT NULL,
  used_at    DATETIME NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_pr_token (token_hash),
  KEY idx_pr_user (user_id),
  CONSTRAINT fk_pr_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;
