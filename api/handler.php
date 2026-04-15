<?php
/**
 * ProTel SAAS - API Handler
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

if (empty($_SESSION['admin_logged_in'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$pdo = getDB();

function respond($data, $success = true) {
    echo json_encode(['success' => $success, 'data' => $data]);
    exit;
}
function error($msg) {
    echo json_encode(['success' => false, 'message' => $msg]);
    exit;
}

try {
    switch ($action) {

        case 'get_global_stats':
            $bots = $pdo->query("SELECT COUNT(*) FROM system_bots")->fetchColumn();
            $usersAcc = $pdo->query("SELECT COUNT(DISTINCT owner_tg_id) FROM tg_accounts")->fetchColumn();
            $usersCam = $pdo->query("SELECT COUNT(DISTINCT owner_tg_id) FROM broadcast_campaigns")->fetchColumn();
            $users = max($usersAcc, $usersCam); // roughly estimate distinct tenants
            $campaigns = $pdo->query("SELECT COUNT(*) FROM broadcast_campaigns")->fetchColumn();
            $sent = $pdo->query("SELECT COALESCE(SUM(sent_count),0) FROM broadcast_campaigns")->fetchColumn();
            respond([
                'bots' => (int)$bots,
                'users' => (int)$users,
                'campaigns' => (int)$campaigns,
                'sent' => (int)$sent
            ]);
            break;

        case 'get_bots':
            $bots = $pdo->query("SELECT * FROM system_bots ORDER BY added_at DESC")->fetchAll(PDO::FETCH_ASSOC);
            respond($bots);
            break;

        case 'get_users':
            // Group by owner_tg_id from accounts, contacts, and campaigns
            $stmt = $pdo->query("
                SELECT owner_tg_id,
                       (SELECT COUNT(*) FROM tg_accounts WHERE tg_accounts.owner_tg_id = main.owner_tg_id) as accounts,
                       (SELECT COUNT(*) FROM broadcast_contacts WHERE broadcast_contacts.owner_tg_id = main.owner_tg_id) as contacts,
                       (SELECT COUNT(*) FROM broadcast_campaigns WHERE broadcast_campaigns.owner_tg_id = main.owner_tg_id) as campaigns
                FROM (
                    SELECT owner_tg_id FROM tg_accounts
                    UNION
                    SELECT owner_tg_id FROM broadcast_contacts
                    UNION
                    SELECT owner_tg_id FROM broadcast_campaigns
                ) AS main
                ORDER BY campaigns DESC, accounts DESC
            ");
            respond($stmt->fetchAll(PDO::FETCH_ASSOC));
            break;

        case 'get_global_campaigns':
            $stmt = $pdo->query("
                SELECT name, owner_tg_id, status, sent_count as sent, total_target as total, created_at 
                FROM broadcast_campaigns 
                ORDER BY created_at DESC LIMIT 50
            ");
            respond($stmt->fetchAll(PDO::FETCH_ASSOC));
            break;

        case 'add_system_bot':
            $token = trim($_POST['token'] ?? '');
            $appUrl = trim($_POST['app_url'] ?? '');
            
            if (empty($token) || empty($appUrl)) error("Token dan URL wajib diisi");
            
            // 1. Validasi Token ke Telegram API
            $getMeUrl = "https://api.telegram.org/bot{$token}/getMe";
            $meData = @file_get_contents($getMeUrl);
            if (!$meData) error("Token tidak valid / Ditolak oleh Telegram");
            
            $me = json_decode($meData, true);
            if (!$me || empty($me['ok'])) error("Token tidak valid");
            
            $username = $me['result']['username'];
            
            // 2. Set Webhook
            $webhookUrl = rtrim($appUrl, '/') . '/webhook.php?token=' . urlencode($token);
            $sethookUrl = "https://api.telegram.org/bot{$token}/setWebhook?url=" . urlencode($webhookUrl);
            $hookData = @file_get_contents($sethookUrl);
            
            if (!$hookData) error("Gagal set webhook (Network Error)");
            $hookRes = json_decode($hookData, true);
            if (!$hookRes || empty($hookRes['ok'])) error("Gagal set webhook: " . ($hookRes['description'] ?? ''));

            // 3. Simpan ke Database
            $stmt = $pdo->prepare("INSERT INTO system_bots (bot_token, bot_username, status) VALUES (?,?, 'active') 
                                   ON DUPLICATE KEY UPDATE bot_username=VALUES(bot_username), status='active'");
            $stmt->execute([$token, $username]);

            respond("Bot berhasil ditambahkan dan berjalan!");
            break;

        case 'delete_system_bot':
            $id = (int)($_POST['id'] ?? 0);
            $stmt = $pdo->prepare("DELETE FROM system_bots WHERE id = ?");
            $stmt->execute([$id]);
            respond("Bot diputus");
            break;

        default:
            error("Unknown action: $action");
    }
} catch (Throwable $e) {
    error("DB Error: " . $e->getMessage());
}
