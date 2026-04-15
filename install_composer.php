<?php
/**
 * ProTel — Composer Installer via Web
 * Buka sekali: https://domain.com/protel/install_composer.php?key=protel123
 * ⚠️ HAPUS FILE INI SETELAH SELESAI!
 */
if (($_GET['key'] ?? '') !== 'protel123') { http_response_code(403); die('Forbidden'); }

set_time_limit(300);
echo "<pre style='font-family:monospace;padding:20px;font-size:13px'>";
echo "📦 ProTel — Composer Install\n";
echo str_repeat('─', 50) . "\n\n";
flush();

$dir = __DIR__;

// Step 1: Download composer.phar
echo "1. Mendownload composer.phar...\n"; flush();
if (!file_exists($dir . '/composer.phar')) {
    $ch = curl_init('https://getcomposer.org/composer.phar');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 120);
    $data = curl_exec($ch);
    $err  = curl_error($ch);
    curl_close($ch);
    if (!$data) { echo "   ❌ Gagal download: $err\n"; exit; }
    file_put_contents($dir . '/composer.phar', $data);
    echo "   ✓ composer.phar berhasil didownload\n";
} else {
    echo "   ✓ composer.phar sudah ada\n";
}
flush();

// Step 2: Jalankan composer install
echo "\n2. Menjalankan composer install...\n";
echo "   (Ini bisa memakan waktu 2-5 menit)\n\n";
flush();

$cmd = 'cd ' . escapeshellarg($dir) . ' && php composer.phar install --no-dev --optimize-autoloader 2>&1';
$output = shell_exec($cmd);

if ($output === null) {
    echo "❌ shell_exec tidak tersedia di server ini.\n";
    echo "Gunakan metode lain (SSH/FTP upload).\n";
} else {
    echo htmlspecialchars($output) . "\n";
    if (is_dir($dir . '/vendor')) {
        echo "\n✅ Berhasil! Folder vendor/ sudah ada.\n";
    } else {
        echo "\n❌ vendor/ tidak terbuat. Coba upload manual via FTP.\n";
    }
}

// Cleanup
@unlink($dir . '/composer.phar');

echo "\n" . str_repeat('─', 50) . "\n";
echo "⚠️  HAPUS file install_composer.php sekarang!\n";
echo "</pre>";

// Self-delete setelah 10 detik
echo "<script>setTimeout(() => { fetch('install_composer.php?key=protel123&delete=1'); }, 10000);</script>";
if (isset($_GET['delete'])) { @unlink(__FILE__); }
