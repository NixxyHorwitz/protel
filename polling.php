<?php
/**
 * ProTel Bot — Long Polling Runner (Untuk Testing Local)
 *
 * Cara pakai:
 * php polling.php "TOKEN_BOT_KAMU"
 */

require_once __DIR__ . '/config/app.php';

$token = $argv[1] ?? '';
if (empty($token)) {
    die("❌ Error! Cara menjalankan:\nphp polling.php \"TOKEN_BOT_DARI_BOTFATHER\"\n");
}

define('BOT_TOKEN', $token);
define('ADMIN_IDS', []);

$bot = require __DIR__ . '/bot.php';
echo "🚀 ProTel Bot berjalan (long polling)...\n";
echo "   Tekan Ctrl+C untuk berhenti.\n\n";
$bot->run();
