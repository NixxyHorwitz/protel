<?php
require_once __DIR__ . '/core/database.php';
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

function write_worker_log($msg) {
    write_log('WORKER', $msg);
    echo "[" . date('Y-m-d H:i:s') . "] " . $msg . "\n";
}

write_worker_log("🟢 Starting ProTel Background Worker Daemon...");

while (true) {
    // 1. Fetch one broadcast task that is ready to process
    $b_stmt = $pdo->prepare("SELECT b.*, s.phone_number, s.telegram_id FROM broadcasts b JOIN user_sessions s ON b.session_id = s.id WHERE b.status = 'process' ORDER BY b.id ASC LIMIT 1");
    $b_stmt->execute();
    $task = $b_stmt->fetch();

    if (!$task) {
        // No active task, sleep to avoid CPU hugging
        sleep(3);
        continue;
    }

    $bid = $task['id'];
    $session_id = $task['session_id'];
    $owner_id = $task['telegram_id'];
    $phone_number = $task['phone_number'];
    
    write_worker_log("🚀 Processing Task #{$bid} (Session: {$phone_number})");

    // Initialize MadelineProto for this session
    $settings = new \danog\MadelineProto\Settings();
    $settings->getLogger()->setLevel(\danog\MadelineProto\Logger::FATAL_ERROR);
    $settings->getPeer()->setCacheAllPeersOnStartup(false);
    $settings->getAppInfo()->setApiId(2040)->setApiHash('b18441a1ff607e10a989891a5462e627');

    $safe_phone = preg_replace('/[^0-9]/', '', $phone_number);
    $sessionPath = __DIR__ . "/sessions/session_{$owner_id}_{$safe_phone}.madeline";

    if (!file_exists($sessionPath)) {
        write_worker_log("❌ Error Task #{$bid}: Session file missing for {$phone_number}. Marking as failed.");
        $pdo->prepare("UPDATE broadcasts SET status = 'failed' WHERE id = ?")->execute([$bid]);
        continue;
    }

    try {
        $API = new \danog\MadelineProto\API($sessionPath, $settings);
    } catch (\Exception $e) {
        write_worker_log("❌ Error Task #{$bid}: MadelineProto Error: " . $e->getMessage());
        $pdo->prepare("UPDATE broadcasts SET status = 'failed' WHERE id = ?")->execute([$bid]);
        continue;
    }

    // Process Contacts in batches of 50
    $c_stmt = $pdo->prepare("SELECT id, phone_or_username, type FROM contacts WHERE session_id = ? AND status = 'valid' LIMIT 50");
    $c_stmt->execute([$session_id]);
    $contacts = $c_stmt->fetchAll();

    if (empty($contacts)) {
        write_worker_log("✅ Task #{$bid} completed! No more targets.");
        $pdo->prepare("UPDATE broadcasts SET status = 'completed' WHERE id = ?")->execute([$bid]);
        
        // Auto-Reset contacts to valid so the user can reuse them for their NEXT broadcast without re-importing
        $pdo->prepare("UPDATE contacts SET status = 'valid' WHERE session_id = ? AND status = 'sent'")->execute([$session_id]);
        continue;
    }

    foreach ($contacts as $contact) {
        // Fast-check if task was paused/stopped by user via Dashboard
        $ck = $pdo->prepare("SELECT status FROM broadcasts WHERE id = ?"); $ck->execute([$bid]);
        if ($ck->fetchColumn() !== 'process') {
             write_worker_log("⏸ Task #{$bid} paused/stopped by user.");
             break 2; // Break back to main while loop so we don't process this batch further
        }

        $target = $contact['phone_or_username'];
        if ($contact['type'] === 'phone' && !str_starts_with($target, '+')) {
            // Assume missing + for local numbers, but ideally user formatted it correctly
            if (is_numeric($target)) $target = '+' . $target; 
        }

        try {
            if (!empty($task['media_path']) && file_exists(__DIR__ . '/uploads/' . $task['media_path'])) {
                // Send Media
                $API->messages->sendMedia([
                    'peer' => $target,
                    'media' => [
                        '_' => 'inputMediaUploadedDocument',
                        'file' => __DIR__ . '/uploads/' . $task['media_path']
                    ],
                    'message' => $task['message'],
                    'parse_mode' => 'HTML'
                ]);
            } else {
                // Send standard text message
                $API->messages->sendMessage([
                    'peer' => $target, 
                    'message' => $task['message'],
                    'parse_mode' => 'HTML'
                ]);
            }
            
            // Success
            $pdo->prepare("UPDATE contacts SET status = 'sent' WHERE id = ?")->execute([$contact['id']]);
            $pdo->prepare("UPDATE broadcasts SET sent_count = sent_count + 1 WHERE id = ?")->execute([$bid]);
            write_worker_log("✅ Sent to {$target}");

        } catch (\Exception $e) {
            write_worker_log("⚠️ Failed to send to {$target}: " . $e->getMessage());
            // Mark invalid so it skips it next cycle
            $pdo->prepare("UPDATE contacts SET status = 'invalid' WHERE id = ?")->execute([$contact['id']]);
        }
        
        // FLOOD WAIT PROTECTION 
        // Normal Telegram accounts have strict rate limits for messaging non-mutual contacts.
        sleep(random_int(2, 5)); // Random delay of 2-5 seconds
    }
}
