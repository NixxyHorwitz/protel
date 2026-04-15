<?php
/**
 * ProTel Bot — SaaS Webhook Handler
 *
 * Menerima request dari Telegram untuk semua bot yang didaftarkan.
 */

// Load app config (untuk SESSION_DIR, STORAGE_DIR, dll) tapi BUKAN untuk BOT_TOKEN
require_once __DIR__ . '/config/app.php';

$token = $_GET['token'] ?? '';
if (empty($token)) {
    http_response_code(400);
    die("Token missing");
}

// Define token dinamis berdasarkan bot mana yang sedang menerima chat
define('BOT_TOKEN', $token);

// Admin IDS dikosongkan agar pengguna manapun bisa menggunakan bot (SaaS)
define('ADMIN_IDS', []);

$bot = require __DIR__ . '/bot.php';
$bot->run(\SergiX44\Nutgram\RunningMode\Webhook::class);
