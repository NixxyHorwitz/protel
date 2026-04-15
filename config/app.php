<?php
// =============================================
// TELEGRAM API CREDENTIALS
// Daftar di https://my.telegram.org/apps
// =============================================
define('TG_API_ID',   '');        // Dari my.telegram.org/apps
define('TG_API_HASH', '');        // Dari my.telegram.org/apps
define('BOT_TOKEN',   '');        // Token bot dari @BotFather

// =============================================
// APP SETTINGS
// =============================================
define('APP_NAME',    'ProTel Broadcast');
define('APP_URL',     'http://localhost/protel');
define('STORAGE_DIR', __DIR__ . '/../storage/');
define('SESSION_DIR', __DIR__ . '/../sessions/');   // tempat simpan session MadelineProto
define('ADMIN_USER',  'admin');
define('ADMIN_PASS',  '$2y$10$xU39JzjxQN8kfhPrSArvm.AHWpHKpfp/DgBvcA61JA7ydIY0E.iWq'); // password: admin123

// Default admin password = admin123
// Generate baru: echo password_hash('admin123', PASSWORD_BCRYPT);

foreach ([SESSION_DIR, STORAGE_DIR] as $_dir) {
    if (!is_dir($_dir)) mkdir($_dir, 0755, true);
}

session_start();
