<?php
/**
 * ProTel Bot — Long Polling Runner
 *
 * Gunakan untuk development lokal:
 *   php polling.php
 *
 * Untuk production, set webhook ke:
 *   https://domain.com/protel/webhook.php
 */

$bot = require __DIR__ . '/bot.php';
echo "🚀 ProTel Bot berjalan (long polling)...\n";
echo "   Tekan Ctrl+C untuk berhenti.\n\n";
$bot->run();
