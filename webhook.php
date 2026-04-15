<?php
/**
 * ProTel Bot — Webhook Handler (Production)
 * Telegram memanggil URL ini setiap kali ada pesan masuk.
 */

// PENTING: define sebelum require config agar session_start() diskip
define('BOT_WEBHOOK_MODE', true);

require_once __DIR__ . '/config/app.php';

// Debug Logger: Bukti bahwa web server meneruskan ke script PHP
$rawInput = file_get_contents('php://input');
if ($rawInput) {
    file_put_contents(__DIR__ . '/webhook.log', "[" . date('Y-m-d H:i:s') . "] IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'Unknown') . " => " . $rawInput . "\n", FILE_APPEND);
}

// Tangkap semua error ke log — jangan sampai Telegram dapat response non-200
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

if (empty(BOT_TOKEN)) {
    http_response_code(200); // Tetap 200 agar Telegram tidak retry terus
    exit;
}

define('ADMIN_IDS', []);

try {
    $bot = require __DIR__ . '/bot.php';
    $bot->run(\SergiX44\Nutgram\RunningMode\Webhook::class);
} catch (Throwable $e) {
    // Catat error ke log server, tapi tetap return 200 ke Telegram
    error_log('[ProTel Webhook] FATAL: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(200);
}
