<?php
/**
 * Setup Webhook — Jalankan sekali di production
 * Akses: https://domain.com/protel/setwebhook.php
 */

require_once __DIR__ . '/config/app.php';

$webhookUrl = rtrim(APP_URL, '/') . '/webhook.php';
$apiUrl     = "https://api.telegram.org/bot" . BOT_TOKEN . "/setWebhook";

if (empty(BOT_TOKEN)) {
    die("❌ BOT_TOKEN belum diisi di config/app.php");
}

$result = file_get_contents($apiUrl . "?url=" . urlencode($webhookUrl));
$data   = json_decode($result, true);

echo "<pre>";
echo "Webhook URL : {$webhookUrl}\n";
echo "Status      : " . ($data['ok'] ? '✅ Berhasil' : '❌ Gagal') . "\n";
echo "Response    : " . $data['description'] . "\n";
echo "</pre>";
echo "<p>Setelah webhook aktif, hapus file ini dari server!</p>";
