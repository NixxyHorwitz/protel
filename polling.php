<?php
/**
 * ProTel Bot — Long Polling (Development)
 * Jalankan: php polling.php
 */
require_once __DIR__ . '/config/app.php';

if (empty(BOT_TOKEN)) {
    die("❌ BOT_TOKEN belum diisi di config/app.php!\n");
}

define('ADMIN_IDS', []);  // Kosong = semua user bisa akses bot

$bot = require __DIR__ . '/bot.php';
echo "🚀 ProTel Bot berjalan (long polling)...\n";
echo "   Ctrl+C untuk berhenti.\n\n";
$bot->run();
