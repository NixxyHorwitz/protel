<?php
// =============================================
// TELEGRAM API CREDENTIALS
// Daftar di https://my.telegram.org/apps
// =============================================
define('TG_API_ID',   '');        // isi dengan API ID kamu
define('TG_API_HASH', '');        // isi dengan API Hash kamu

// =============================================
// APP SETTINGS
// =============================================
define('APP_NAME',    'ProTel Broadcast');
define('APP_URL',     'http://localhost/protel');
define('SESSION_DIR', __DIR__ . '/../sessions/');   // tempat simpan session MadelineProto
define('ADMIN_USER',  'admin');
define('ADMIN_PASS',  '$2y$10$xU39JzjxQN8kfhPrSArvm.AHWpHKpfp/DgBvcA61JA7ydIY0E.iWq'); // password: admin123

// Default admin password = admin123
// Generate baru: echo password_hash('admin123', PASSWORD_BCRYPT);

if (!is_dir(SESSION_DIR)) {
    mkdir(SESSION_DIR, 0755, true);
}

session_start();
