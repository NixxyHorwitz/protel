<?php
require_once __DIR__ . '/../core/database.php';

header('Content-Type: application/json');

// Validate Telegram Web App Data
function validateTelegramWebAppData($initData, $botToken) {
    parse_str($initData, $data);
    if (!isset($data['hash'])) return false;
    $hash = $data['hash'];
    unset($data['hash']);
    ksort($data);
    $data_check_string = [];
    foreach ($data as $k => $v) { $data_check_string[] = "$k=$v"; }
    $data_check_string = implode("\n", $data_check_string);
    $secret_key = hash_hmac('sha256', $botToken, "WebAppData", true);
    $calculated_hash = bin2hex(hash_hmac('sha256', $data_check_string, $secret_key, true));
    if (hash_equals($calculated_hash, $hash)) {
        return json_decode($data['user'], true);
    }
    return false;
}

$settings = $pdo->query("SELECT bot_token FROM bot_settings WHERE id = 1")->fetch();
$bot_token = $settings['bot_token'] ?? '';

$initData = $_SERVER['HTTP_X_TG_INIT_DATA'] ?? '';
if (empty($initData)) {
    echo json_encode(['error' => 'Missing authentication data']); exit;
}

$tgUser = validateTelegramWebAppData($initData, $bot_token);
if (!$tgUser) {
    echo json_encode(['error' => 'Invalid authentication data']); exit;
}

$telegram_id = $tgUser['id'];
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'get_dashboard':
        // Fetch User Info
        $s = $pdo->prepare("SELECT u.*, p.name as pkg_name, p.max_sessions FROM users u LEFT JOIN packages p ON u.package_id = p.id WHERE u.telegram_id = ?");
        $s->execute([$telegram_id]);
        $user = $s->fetch() ?: ['name' => $tgUser['first_name'], 'pkg_name' => 'Free', 'max_sessions' => 1];

        // Fetch user's registered sessions
        $s2 = $pdo->prepare("SELECT id, phone_number, status FROM user_sessions WHERE telegram_id = ? AND status = 'active'");
        $s2->execute([$telegram_id]);
        $sessions = $s2->fetchAll();

        // Fetch User's Broadcasts
        $s3 = $pdo->prepare("SELECT b.*, s.phone_number FROM broadcasts b JOIN user_sessions s ON b.session_id = s.id WHERE s.telegram_id = ? ORDER BY b.id DESC LIMIT 20");
        $s3->execute([$telegram_id]);
        $broadcasts = $s3->fetchAll();

        echo json_encode([
            'status' => 'ok',
            'user' => $user,
            'sessions' => $sessions,
            'broadcasts' => $broadcasts
        ]);
        break;

    case 'create_broadcast':
        $session_id = $_POST['session_id'] ?? 0;
        $message = $_POST['message'] ?? '';
        
        // Verify ownership of the session
        $s = $pdo->prepare("SELECT id FROM user_sessions WHERE id = ? AND telegram_id = ?");
        $s->execute([$session_id, $telegram_id]);
        if (!$s->fetch()) {
            echo json_encode(['error' => 'Sesi tidak valid / bukan milik Anda']); exit;
        }

        if (empty(trim($message))) {
            echo json_encode(['error' => 'Pesan broadcast tidak boleh kosong']); exit;
        }

        // Hitung target contact
        $c = $pdo->prepare("SELECT COUNT(*) FROM contacts WHERE session_id = ?");
        $c->execute([$session_id]);
        $target_count = $c->fetchColumn();

        if ($target_count == 0) {
            echo json_encode(['error' => 'Agun ini belum memiliki kontak untuk dibroadcast']); exit;
        }

        $stmt = $pdo->prepare("INSERT INTO broadcasts (session_id, message, status, target_count, sent_count) VALUES (?, ?, 'process', ?, 0)");
        $stmt->execute([$session_id, trim($message), $target_count]);
        
        echo json_encode(['status' => 'ok']);
        break;

    case 'update_status':
        $id = $_POST['id'] ?? 0;
        $new_status = $_POST['status'] ?? ''; // process, paused, failed (stop)
        
        // Verify ownership via join
        $s = $pdo->prepare("SELECT b.id FROM broadcasts b JOIN user_sessions s ON b.session_id = s.id WHERE b.id = ? AND s.telegram_id = ?");
        $s->execute([$id, $telegram_id]);
        if (!$s->fetch()) {
            echo json_encode(['error' => 'Unauthorized']); exit;
        }

        if (!in_array($new_status, ['process', 'paused', 'failed'])) {
            echo json_encode(['error' => 'Invalid status']); exit;
        }

        $pdo->prepare("UPDATE broadcasts SET status = ? WHERE id = ?")->execute([$new_status, $id]);
        echo json_encode(['status' => 'ok']);
        break;

    case 'execute_batch':
        // Ini adalah Mini-App Worker (Frontend Triggered Worker)
        // Mengeksekusi hingga 3 kontak dalam 1 siklus AJAX untuk menghindari timeout
        set_time_limit(60);

        $b_stmt = $pdo->prepare("SELECT b.*, s.phone_number, s.telegram_id FROM broadcasts b JOIN user_sessions s ON b.session_id = s.id WHERE b.status = 'process' AND s.telegram_id = ? ORDER BY b.id ASC LIMIT 1");
        $b_stmt->execute([$telegram_id]);
        $task = $b_stmt->fetch();

        if (!$task) {
            echo json_encode(['status' => 'idle', 'message' => 'No active tasks']); exit;
        }

        $session_id = $task['session_id'];
        $bid = $task['id'];

        $c_stmt = $pdo->prepare("SELECT id, phone_or_username, type FROM contacts WHERE session_id = ? AND status = 'valid' LIMIT 3");
        $c_stmt->execute([$session_id]);
        $contacts = $c_stmt->fetchAll();

        if (empty($contacts)) {
            $pdo->prepare("UPDATE broadcasts SET status = 'completed' WHERE id = ?")->execute([$bid]);
            $pdo->prepare("UPDATE contacts SET status = 'valid' WHERE session_id = ? AND status = 'sent'")->execute([$session_id]);
            echo json_encode(['status' => 'completed', 'task_id' => $bid]); exit;
        }

        // Init MadelineProto
        if (file_exists(__DIR__ . '/../vendor/autoload.php')) require_once __DIR__ . '/../vendor/autoload.php';
        
        $settings = new \danog\MadelineProto\Settings();
        $settings->getLogger()->setLevel(\danog\MadelineProto\Logger::FATAL_ERROR);
        $settings->getPeer()->setCacheAllPeersOnStartup(false);
        $settings->getAppInfo()->setApiId(2040)->setApiHash('b18441a1ff607e10a989891a5462e627');

        $safe_phone = preg_replace('/[^0-9]/', '', $task['phone_number']);
        $sessionPath = __DIR__ . "/../sessions/session_{$telegram_id}_{$safe_phone}.madeline";

        if (!file_exists($sessionPath)) {
            $pdo->prepare("UPDATE broadcasts SET status = 'failed' WHERE id = ?")->execute([$bid]);
            echo json_encode(['status' => 'error', 'message' => 'Sesi tidak ditemukan']); exit;
        }

        try {
            $API = new \danog\MadelineProto\API($sessionPath, $settings);
        } catch (\Exception $e) {
            $pdo->prepare("UPDATE broadcasts SET status = 'failed' WHERE id = ?")->execute([$bid]);
            echo json_encode(['status' => 'error', 'message' => 'Gagal meload API']); exit;
        }

        $sent_this_batch = 0;
        foreach ($contacts as $contact) {
            // Check if user paused mid-batch
            $ck = $pdo->prepare("SELECT status FROM broadcasts WHERE id = ?"); $ck->execute([$bid]);
            if ($ck->fetchColumn() !== 'process') {
                 break;
            }

            $target = $contact['phone_or_username'];
            if ($contact['type'] === 'phone' && is_numeric($target) && !str_starts_with($target, '+')) {
                $target = '+' . $target; 
            }

            try {
                $API->messages->sendMessage([
                    'peer' => $target, 
                    'message' => $task['message'],
                    'parse_mode' => 'HTML'
                ]);
                $pdo->prepare("UPDATE contacts SET status = 'sent' WHERE id = ?")->execute([$contact['id']]);
                $pdo->prepare("UPDATE broadcasts SET sent_count = sent_count + 1 WHERE id = ?")->execute([$bid]);
                $sent_this_batch++;
            } catch (\Exception $e) {
                // Invalid num / blocked
                $pdo->prepare("UPDATE contacts SET status = 'invalid' WHERE id = ?")->execute([$contact['id']]);
            }
            sleep(1); // Jeda aman
        }

        echo json_encode(['status' => 'batch_processed', 'sent' => $sent_this_batch, 'task_id' => $bid]);
        break;

    default:
        echo json_encode(['error' => 'Unknown action']);
}
