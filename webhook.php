<?php
/**
 * ProTel Bot — Webhook Handler
 *
 * Set webhook ke URL ini di production.
 * Gunakan setwebhook.php untuk setup otomatis.
 */

$bot = require __DIR__ . '/bot.php';
$bot->run(\SergiX44\Nutgram\RunningMode\Webhook::class);
