<?php
/**
 * ProTel Bot — Webhook Handler (Production)
 */
require_once __DIR__ . '/config/app.php';

if (empty(BOT_TOKEN)) {
    http_response_code(500);
    die("BOT_TOKEN not configured");
}

define('ADMIN_IDS', []);  // Kosong = semua user bisa akses bot

$bot = require __DIR__ . '/bot.php';
$bot->run(\SergiX44\Nutgram\RunningMode\Webhook::class);
