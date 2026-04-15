<?php
/**
 * ProTel — Debug Endpoint
 * Buka: http://yoursite.com/protel/debug.php?key=protel123
 * Hapus file ini setelah debug selesai!
 */

define('BOT_WEBHOOK_MODE', true);

if (($_GET['key'] ?? '') !== 'protel123') {
    http_response_code(403); die('Forbidden');
}

echo "<pre style='font-family:monospace;font-size:13px;padding:20px'>";
echo "<h2>ProTel Debug</h2>";

// PHP info
echo "<b>PHP Version:</b> " . PHP_VERSION . "\n";
echo "<b>PHP SAPI:</b> " . PHP_SAPI . "\n\n";

// Extensions
$needed = ['curl', 'pdo', 'pdo_mysql', 'mbstring', 'openssl', 'json', 'fileinfo'];
echo "<b>PHP Extensions:</b>\n";
foreach ($needed as $ext) {
    $ok = extension_loaded($ext);
    echo "  " . ($ok ? "✓" : "✗ MISSING") . " {$ext}\n";
}

// Config
echo "\n<b>Config Test:</b>\n";
try {
    require_once __DIR__ . '/config/app.php';
    echo "  ✓ config/app.php loaded\n";
    echo "  BOT_TOKEN: " . (empty(BOT_TOKEN) ? "✗ KOSONG — belum diisi!" : '✓ ' . substr(BOT_TOKEN, 0, 10) . '...') . "\n";
    echo "  TG_API_ID: " . (empty(TG_API_ID) ? "✗ KOSONG" : '✓ ' . TG_API_ID) . "\n";
    echo "  APP_URL: " . APP_URL . "\n";
    echo "  SESSION_DIR: " . SESSION_DIR . (is_writable(SESSION_DIR) ? " ✓ writable" : " ✗ NOT writable") . "\n";
    echo "  STORAGE_DIR: " . STORAGE_DIR . (is_writable(STORAGE_DIR) ? " ✓ writable" : " ✗ NOT writable") . "\n";
} catch (Throwable $e) {
    echo "  ✗ ERROR: " . $e->getMessage() . "\n";
}

// DB
echo "\n<b>Database Test:</b>\n";
try {
    require_once __DIR__ . '/config/database.php';
    $pdo = getDB();
    $pdo->query("SELECT 1");
    echo "  ✓ Koneksi database berhasil\n";
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "  Tables: " . implode(', ', $tables) . "\n";
} catch (Throwable $e) {
    echo "  ✗ DB ERROR: " . $e->getMessage() . "\n";
}

// Autoload
echo "\n<b>Vendor Autoload:</b>\n";
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    try {
        require_once __DIR__ . '/vendor/autoload.php';
        echo "  ✓ vendor/autoload.php loaded\n";
        echo "  ✓ Nutgram: " . (class_exists('SergiX44\Nutgram\Nutgram') ? 'ditemukan' : '✗ tidak ditemukan — coba composer install') . "\n";
    } catch (Throwable $e) {
        echo "  ✗ ERROR: " . $e->getMessage() . "\n";
    }
} else {
    echo "  ✗ vendor/autoload.php TIDAK ADA — jalankan: composer install\n";
}

// Bot load test
echo "\n<b>Bot Load Test:</b>\n";
try {
    define('ADMIN_IDS', []);
    $bot = require __DIR__ . '/bot.php';
    echo "  ✓ bot.php berhasil dimuat\n";
} catch (Throwable $e) {
    echo "  ✗ ERROR: " . $e->getMessage() . "\n";
    echo "  File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "\n  Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n<b style='color:red'>⚠ Hapus file debug.php setelah selesai debugging!</b>\n";
echo "</pre>";
