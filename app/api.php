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

    default:
        echo json_encode(['error' => 'Unknown action']);
}
