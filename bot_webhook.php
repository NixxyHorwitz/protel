<?php
require_once __DIR__ . '/core/database.php';

$input = file_get_contents('php://input');
if (!$input) {
    exit('No input');
}

$update = json_decode($input, true);
if (!$update) {
    write_log('WEBHOOK_ERROR', "Invalid JSON format received");
    exit('Invalid JSON');
}

// Log incoming request if in dev mode
if (DEV_MODE) {
    write_log('WEBHOOK_RAW', $input);
}

// Basic router for Telegram messages
if (isset($update['message'])) {
    $chat_id = $update['message']['chat']['id'];
    $text = $update['message']['text'] ?? '';
    
    // Core logic for handling user sending their phone number / OTP
    // This will integrate with MadelineProto
}

http_response_code(200);
echo "OK";
