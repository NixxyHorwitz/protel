<?php
/**
 * ProTel — API Handler (Admin Web Panel)
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

function respond($data) { echo json_encode(['success' => true,  'data' => $data]); exit; }
function error($msg)    { echo json_encode(['success' => false, 'message' => $msg]); exit; }

// ── Logout ─────────────────────────────────────────────────
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: ../index.php');
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// ── Login (public) ─────────────────────────────────────────
if ($action === 'login') {
    $user = $_POST['username'] ?? '';
    $pass = $_POST['password'] ?? '';
    if ($user === ADMIN_USER && password_verify($pass, ADMIN_PASS)) {
        $_SESSION['admin_logged_in'] = true;
        respond('ok');
    }
    error('Username atau password salah');
}

// ── Auth guard ──────────────────────────────────────────────
if (empty($_SESSION['admin_logged_in'])) {
    error('Unauthorized');
}

$pdo = getDB();

try {
    switch ($action) {

        // ──────────────────────────────────────────────────────
        case 'get_global_stats':
            $users = $pdo->query("
                SELECT COUNT(DISTINCT owner_tg_id) FROM (
                    SELECT owner_tg_id FROM tg_accounts
                    UNION SELECT owner_tg_id FROM broadcast_campaigns
                ) t
            ")->fetchColumn();

            $accounts  = $pdo->query("SELECT COUNT(*) FROM tg_accounts WHERE status='active'")->fetchColumn();
            $campaigns = $pdo->query("SELECT COUNT(*) FROM broadcast_campaigns")->fetchColumn();
            $sent      = $pdo->query("SELECT COALESCE(SUM(sent_count),0) FROM broadcast_campaigns")->fetchColumn();

            respond([
                'users'     => (int)$users,
                'accounts'  => (int)$accounts,
                'campaigns' => (int)$campaigns,
                'sent'      => (int)$sent,
            ]);

        // ──────────────────────────────────────────────────────
        case 'get_users':
            $stmt = $pdo->query("
                SELECT
                    t.owner_tg_id,
                    (SELECT COUNT(*) FROM tg_accounts      a WHERE a.owner_tg_id = t.owner_tg_id) AS accounts,
                    (SELECT COUNT(*) FROM broadcast_contacts c WHERE c.owner_tg_id = t.owner_tg_id) AS contacts,
                    (SELECT COUNT(*) FROM broadcast_campaigns p WHERE p.owner_tg_id = t.owner_tg_id) AS campaigns,
                    (SELECT COALESCE(SUM(sent_count),0) FROM broadcast_campaigns s WHERE s.owner_tg_id = t.owner_tg_id) AS sent
                FROM (
                    SELECT owner_tg_id FROM tg_accounts
                    UNION
                    SELECT owner_tg_id FROM broadcast_campaigns
                ) t
                ORDER BY campaigns DESC, accounts DESC
            ");
            respond($stmt->fetchAll(PDO::FETCH_ASSOC));

        // ──────────────────────────────────────────────────────
        case 'get_global_campaigns':
            $statusFilter = $_POST['status_filter'] ?? '';
            $sql = "SELECT name, owner_tg_id, status,
                           sent_count AS sent, total_target AS total,
                           failed_count AS failed, created_at
                    FROM broadcast_campaigns";
            if ($statusFilter) {
                $stmt = $pdo->prepare($sql . " WHERE status = ? ORDER BY created_at DESC LIMIT 50");
                $stmt->execute([$statusFilter]);
            } else {
                $stmt = $pdo->query($sql . " ORDER BY created_at DESC LIMIT 50");
            }
            respond($stmt->fetchAll(PDO::FETCH_ASSOC));

        // ──────────────────────────────────────────────────────
        case 'get_config':
            // Baca nilai yang sudah ada dari konstanta (sudah di-require config/app.php)
            respond([
                'api_id'    => defined('TG_API_ID')   ? TG_API_ID   : '',
                'api_hash'  => defined('TG_API_HASH')  ? TG_API_HASH : '',
                'bot_token' => defined('BOT_TOKEN')    ? BOT_TOKEN   : '',
                'app_url'   => defined('APP_URL')      ? APP_URL     : '',
            ]);

        // ──────────────────────────────────────────────────────
        case 'save_config':
            $apiId    = trim($_POST['api_id']    ?? '');
            $apiHash  = trim($_POST['api_hash']  ?? '');
            $botToken = trim($_POST['bot_token'] ?? '');
            $appUrl   = trim($_POST['app_url']   ?? '');

            if (empty($apiId) || empty($apiHash) || empty($botToken)) {
                error('API ID, API Hash, dan Bot Token wajib diisi');
            }

            $configPath = __DIR__ . '/../config/app.php';
            $content = file_get_contents($configPath);

            // Replace nilai-nilai config menggunakan regex
            $content = preg_replace("/define\('TG_API_ID',\s*'[^']*'\)/",   "define('TG_API_ID',   '{$apiId}')", $content);
            $content = preg_replace("/define\('TG_API_HASH',\s*'[^']*'\)/", "define('TG_API_HASH', '{$apiHash}')", $content);
            $content = preg_replace("/define\('BOT_TOKEN',\s*'[^']*'\)/",   "define('BOT_TOKEN',   '{$botToken}')", $content);
            $content = preg_replace("/define\('APP_URL',\s*'[^']*'\)/",     "define('APP_URL',     '{$appUrl}')", $content);

            if (file_put_contents($configPath, $content) === false) {
                error('Gagal menyimpan ke file config. Periksa permission file config/app.php');
            }

            // Set webhook otomatis jika app_url diisi
            if ($appUrl && $botToken) {
                $webhookUrl = rtrim($appUrl, '/') . '/webhook.php';
                $ch = curl_init("https://api.telegram.org/bot{$botToken}/setWebhook?url=" . urlencode($webhookUrl));
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_TIMEOUT, 8);
                curl_exec($ch);
                curl_close($ch);
            }

            respond('Konfigurasi berhasil disimpan');

        // ──────────────────────────────────────────────────────
        default:
            error("Unknown action: {$action}");
    }
} catch (Throwable $e) {
    error('DB Error: ' . $e->getMessage());
}
