<?php
/**
 * ProTel — Setup / Health Check Page
 * Akses: http://localhost/protel/setup.php
 */
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>ProTel — Setup Checker</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: Inter, sans-serif; background: #080c18; color: #e2e8f0; min-height: 100vh; padding: 40px 20px; }
        .wrap { max-width: 640px; margin: auto; }
        h1 { font-size: 26px; font-weight: 800; margin-bottom: 8px; }
        .sub { color: #64748b; margin-bottom: 32px; }
        .check-item {
            display: flex; align-items: start; gap: 14px;
            background: #111827; border: 1px solid rgba(255,255,255,0.07);
            border-radius: 12px; padding: 16px; margin-bottom: 12px;
        }
        .icon { font-size: 22px; flex-shrink: 0; margin-top: 2px; }
        .title { font-weight: 600; margin-bottom: 4px; }
        .desc { font-size: 13px; color: #64748b; }
        .desc code { background: rgba(255,255,255,0.07); padding: 2px 6px; border-radius: 5px; font-family: monospace; }
        .ok { border-left: 3px solid #10b981; }
        .warn { border-left: 3px solid #f59e0b; }
        .err { border-left: 3px solid #ef4444; }
        .badge { display: inline-block; padding: 2px 10px; border-radius: 100px; font-size: 11px; font-weight: 700; }
        .badge-ok { background: rgba(16,185,129,0.15); color: #34d399; }
        .badge-warn { background: rgba(245,158,11,0.15); color: #fcd34d; }
        .badge-err { background: rgba(239,68,68,0.15); color: #fca5a5; }
        a.btn { display: inline-block; margin-top: 24px; padding: 12px 24px; background: linear-gradient(135deg,#4f7eff,#7c3aed); color: #fff; border-radius: 10px; text-decoration: none; font-weight: 600; }
    </style>
</head>
<body>
<div class="wrap">
    <h1>📡 ProTel Setup Checker</h1>
    <p class="sub">Verifikasi semua komponen sistem sebelum mulai</p>

<?php
define('ROOT', __DIR__);
$checks = [];

// PHP version
$phpOK = version_compare(PHP_VERSION, '8.1', '>=');
$checks[] = [
    'ok'    => $phpOK,
    'title' => 'PHP Version',
    'desc'  => 'PHP ' . PHP_VERSION . ' — dibutuhkan PHP 8.1+',
];

// Extensions
$exts = ['pdo_mysql','curl','openssl','json','mbstring','sodium'];
foreach ($exts as $ext) {
    $loaded = extension_loaded($ext);
    $checks[] = [
        'ok'    => $loaded,
        'warn'  => false,
        'title' => "Extension: {$ext}",
        'desc'  => $loaded ? 'Tersedia' : "❌ Tidak tersedia — jalankan: <code>extension={$ext}</code> di php.ini",
    ];
}

// Composer autoload
$autoload = file_exists(ROOT . '/vendor/autoload.php');
$checks[] = [
    'ok'    => $autoload,
    'title' => 'MadelineProto (Composer)',
    'desc'  => $autoload
        ? 'vendor/autoload.php ditemukan ✓'
        : 'Jalankan: <code>composer require danog/madelineproto</code>',
];

// Sessions dir writeable
$sessDir = ROOT . '/sessions/';
if (!is_dir($sessDir)) @mkdir($sessDir, 0755, true);
$sessOK = is_writable($sessDir);
$checks[] = [
    'ok'    => $sessOK,
    'title' => 'Sessions Directory',
    'desc'  => $sessOK ? realpath($sessDir) : "Folder sessions/ tidak bisa ditulis",
];

// Uploads dir
$uploadDir = ROOT . '/uploads/';
if (!is_dir($uploadDir)) @mkdir($uploadDir, 0755, true);
$uplOK = is_writable($uploadDir);
$checks[] = [
    'ok'    => $uplOK,
    'title' => 'Uploads Directory',
    'desc'  => $uplOK ? realpath($uploadDir) : "Folder uploads/ tidak bisa ditulis",
];

// API config
$appConfig = ROOT . '/config/app.php';
$appOK = file_exists($appConfig);
$checks[] = [
    'ok'    => $appOK,
    'title' => 'Config App',
    'desc'  => $appOK ? 'config/app.php ditemukan' : 'File config/app.php tidak ada',
];

// Check API ID configured
$apiConfigured = false;
if ($appOK) {
    require_once $appConfig;
    $apiConfigured = defined('TG_API_ID') && !empty(TG_API_ID) && TG_API_ID !== '';
}
$checks[] = [
    'ok'    => $apiConfigured,
    'warn'  => !$apiConfigured,
    'title' => 'Telegram API ID & Hash',
    'desc'  => $apiConfigured
        ? 'API credentials sudah dikonfigurasi ✓'
        : '⚠ Belum diisi! Daftar di <a href="https://my.telegram.org/apps" target="_blank" style="color:#4f7eff">my.telegram.org/apps</a> lalu isi di <code>config/app.php</code>',
];

// DB connection
require_once ROOT . '/config/database.php';
try {
    $pdo = getDB();
    $tbl = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    $required = ['tg_accounts','broadcast_contacts','broadcast_campaigns','broadcast_logs','admin_users'];
    $missing  = array_diff($required, $tbl);
    $checks[] = [
        'ok'    => empty($missing),
        'warn'  => !empty($missing),
        'title' => 'Database',
        'desc'  => empty($missing)
            ? 'Semua tabel ditemukan: ' . implode(', ', $required)
            : 'Tabel belum dibuat: ' . implode(', ', $missing) . '. <br>Jalankan: <code>database/schema.sql</code>',
    ];
} catch (Throwable $e) {
    $checks[] = [
        'ok'    => false,
        'title' => 'Database',
        'desc'  => 'Koneksi gagal: ' . htmlspecialchars($e->getMessage()) . '<br>Pastikan MySQL Laragon sudah berjalan & konfigurasi di config/database.php benar.',
    ];
}

foreach ($checks as $c):
    $cls = $c['ok'] ? 'ok' : (($c['warn'] ?? false) ? 'warn' : 'err');
    $icon = $c['ok'] ? '✅' : (($c['warn'] ?? false) ? '⚠️' : '❌');
    $badgeCls = $c['ok'] ? 'ok' : (($c['warn'] ?? false) ? 'warn' : 'err');
    $badgeText = $c['ok'] ? 'OK' : (($c['warn'] ?? false) ? 'PERLU DIKONFIGURASI' : 'ERROR');
?>
<div class="check-item <?= $cls ?>">
    <div class="icon"><?= $icon ?></div>
    <div style="flex:1">
        <div class="title"><?= htmlspecialchars($c['title']) ?> <span class="badge badge-<?= $badgeCls ?>"><?= $badgeText ?></span></div>
        <div class="desc"><?= $c['desc'] ?></div>
    </div>
</div>
<?php endforeach; ?>

<a href="index.php" class="btn">🚀 Buka Dashboard</a>
</div>
</body>
</html>
