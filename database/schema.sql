-- Database schema untuk ProTel Broadcast System
-- Jalankan sekali untuk setup awal

CREATE DATABASE IF NOT EXISTS protel_broadcast CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE protel_broadcast;

-- Tabel akun Telegram yang sudah diloginkan
CREATE TABLE IF NOT EXISTS tg_accounts (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    phone        VARCHAR(20) NOT NULL UNIQUE,
    first_name   VARCHAR(100) DEFAULT NULL,
    last_name    VARCHAR(100) DEFAULT NULL,
    username     VARCHAR(100) DEFAULT NULL,
    tg_user_id   BIGINT DEFAULT NULL,
    session_file VARCHAR(255) NOT NULL,
    status       ENUM('pending','active','disconnected','banned') DEFAULT 'pending',
    added_at     DATETIME DEFAULT CURRENT_TIMESTAMP,
    last_used    DATETIME DEFAULT NULL,
    notes        TEXT DEFAULT NULL
) ENGINE=InnoDB;

-- Tabel kontak tujuan broadcast
CREATE TABLE IF NOT EXISTS broadcast_contacts (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    phone        VARCHAR(20) DEFAULT NULL,
    username     VARCHAR(100) DEFAULT NULL,
    display_name VARCHAR(200) DEFAULT NULL,
    group_name   VARCHAR(100) DEFAULT 'Default',
    is_active    TINYINT(1) DEFAULT 1,
    added_at     DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Tabel campaign broadcast
CREATE TABLE IF NOT EXISTS broadcast_campaigns (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    name         VARCHAR(200) NOT NULL,
    message_text TEXT NOT NULL,
    media_path   VARCHAR(500) DEFAULT NULL,
    media_type   ENUM('none','photo','video','document') DEFAULT 'none',
    status       ENUM('draft','running','paused','done','failed') DEFAULT 'draft',
    delay_seconds INT DEFAULT 3,
    created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
    started_at   DATETIME DEFAULT NULL,
    finished_at  DATETIME DEFAULT NULL,
    total_target INT DEFAULT 0,
    sent_count   INT DEFAULT 0,
    failed_count INT DEFAULT 0
) ENGINE=InnoDB;

-- Tabel log per-pesan broadcast
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

-- Tabel admin app
CREATE TABLE IF NOT EXISTS admin_users (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    username     VARCHAR(50) NOT NULL UNIQUE,
    password     VARCHAR(255) NOT NULL,
    created_at   DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Default admin: username=admin, password=admin123
-- PENTING: Jalankan gen_password.php untuk generate hash baru setelah install
INSERT IGNORE INTO admin_users (username, password)
VALUES ('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');
-- Setelah import, update manual dengan: php gen_password.php untuk dapat hash yang kompatibel dengan PHP versi kamu
