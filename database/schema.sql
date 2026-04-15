-- =========================================================
--  ProTel Broadcast System — Database Schema (SaaS Edition)
--  Jalankan sekali untuk setup awal
-- =========================================================

CREATE DATABASE IF NOT EXISTS protel_broadcast CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE protel_broadcast;

-- ─────────────────────────────────────────────────────────
-- 1. Daftar Bot Telegram yang dikelola Super Admin
-- ─────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS system_bots (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    bot_token    VARCHAR(255) NOT NULL UNIQUE,
    bot_username VARCHAR(100) NOT NULL,
    status       ENUM('active','inactive') DEFAULT 'active',
    added_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ─────────────────────────────────────────────────────────
-- 2. Akun Telegram user (untuk broadcast — per owner)
-- ─────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS tg_accounts (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    owner_tg_id  BIGINT NOT NULL DEFAULT 0,  -- Telegram User ID pemilik
    phone        VARCHAR(20) NOT NULL,
    first_name   VARCHAR(100) DEFAULT NULL,
    last_name    VARCHAR(100) DEFAULT NULL,
    username     VARCHAR(100) DEFAULT NULL,
    tg_user_id   BIGINT DEFAULT NULL,
    session_file VARCHAR(255) NOT NULL,
    status       ENUM('pending','active','disconnected','banned') DEFAULT 'pending',
    added_at     DATETIME DEFAULT CURRENT_TIMESTAMP,
    last_used    DATETIME DEFAULT NULL,
    notes        TEXT DEFAULT NULL,
    UNIQUE KEY uk_owner_phone (owner_tg_id, phone),
    INDEX idx_owner (owner_tg_id)
) ENGINE=InnoDB;

-- ─────────────────────────────────────────────────────────
-- 3. Daftar kontak tujuan broadcast (per owner)
-- ─────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS broadcast_contacts (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    owner_tg_id  BIGINT NOT NULL DEFAULT 0,
    phone        VARCHAR(20) DEFAULT NULL,
    username     VARCHAR(100) DEFAULT NULL,
    display_name VARCHAR(200) DEFAULT NULL,
    group_name   VARCHAR(100) DEFAULT 'Default',
    is_active    TINYINT(1) DEFAULT 1,
    added_at     DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_owner_group (owner_tg_id, group_name)
) ENGINE=InnoDB;

-- ─────────────────────────────────────────────────────────
-- 4. Campaign broadcast (per owner)
-- ─────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS broadcast_campaigns (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    owner_tg_id   BIGINT NOT NULL DEFAULT 0,
    name          VARCHAR(200) NOT NULL,
    message_text  TEXT NOT NULL,
    media_path    VARCHAR(500) DEFAULT NULL,
    media_type    ENUM('none','photo','video','document') DEFAULT 'none',
    status        ENUM('draft','running','paused','done','failed') DEFAULT 'draft',
    delay_seconds INT DEFAULT 3,
    created_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
    started_at    DATETIME DEFAULT NULL,
    finished_at   DATETIME DEFAULT NULL,
    total_target  INT DEFAULT 0,
    sent_count    INT DEFAULT 0,
    failed_count  INT DEFAULT 0,
    INDEX idx_owner  (owner_tg_id),
    INDEX idx_status (status)
) ENGINE=InnoDB;

-- ─────────────────────────────────────────────────────────
-- 5. Log per-pesan broadcast
-- ─────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS broadcast_logs (
    id           BIGINT AUTO_INCREMENT PRIMARY KEY,
    campaign_id  INT NOT NULL,
    account_id   INT NOT NULL,
    contact_id   INT DEFAULT NULL,
    recipient    VARCHAR(200) NOT NULL,
    status       ENUM('pending','sent','failed','blocked') DEFAULT 'pending',
    error_msg    VARCHAR(500) DEFAULT NULL,
    sent_at      DATETIME DEFAULT NULL,
    INDEX idx_campaign (campaign_id),
    INDEX idx_account  (account_id),
    INDEX idx_status   (status)
) ENGINE=InnoDB;

-- ─────────────────────────────────────────────────────────
-- 6. Admin login panel web
-- ─────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS admin_users (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    username     VARCHAR(50) NOT NULL UNIQUE,
    password     VARCHAR(255) NOT NULL,
    created_at   DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Default admin: username=admin, password=admin123
INSERT IGNORE INTO admin_users (username, password)
VALUES ('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');
-- Setelah import, jalankan: php gen_password.php untuk regenerasi hash
