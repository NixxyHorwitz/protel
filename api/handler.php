<?php
/**
 * ProTel — API Handler
 * Semua AJAX request diarahkan ke sini
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');

// ── Auth guard ──────────────────────────────────────────
function requireLogin(): void {
    if (empty($_SESSION['admin_logged_in'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthenticated']);
        exit;
    }
}

// ── Run PHP CLI script dan return parsed JSON ──────────
function runScript(string $scriptPath, array $args = []): array {
    // Cari php.exe dari PATH atau XAMPP/Laragon
    $phpBin = PHP_BINARY ?: 'php';

    $escapedScript = escapeshellarg($scriptPath);
    $escapedArgs   = implode(' ', array_map('escapeshellarg', $args));
    $cmd = "\"{$phpBin}\" {$escapedScript} {$escapedArgs} 2>&1";

    $output = shell_exec($cmd);

    if ($output === null) {
        return ['success' => false, 'message' => 'shell_exec gagal — periksa disable_functions di php.ini'];
    }

    // Ambil baris terakhir yang berisi JSON (ignore warning output dari MadelineProto)
    $lines = array_filter(array_map('trim', explode("\n", $output)));
    $jsonLine = '';
    foreach (array_reverse($lines) as $line) {
        if (str_starts_with($line, '{') && str_ends_with($line, '}')) {
            $jsonLine = $line;
            break;
        }
    }

    if (empty($jsonLine)) {
        return ['success' => false, 'message' => 'Script output tidak valid: ' . substr($output, 0, 400)];
    }

    $result = json_decode($jsonLine, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return ['success' => false, 'message' => 'JSON parse error: ' . $jsonLine];
    }

    return $result;
}

// ── Session path helper ────────────────────────────────
function sessionFileForPhone(string $phone): string {
    $safe = preg_replace('/[^0-9]/', '', $phone);
    return rtrim(SESSION_DIR, '/\\') . DIRECTORY_SEPARATOR . "account_{$safe}.madeline";
}

// ── Save account to DB ─────────────────────────────────
function saveAccountToDB(string $phone, array $result): void {
    $pdo  = getDB();
    $stmt = $pdo->prepare("
        INSERT INTO tg_accounts (phone, first_name, last_name, username, tg_user_id, session_file, status)
        VALUES (?, ?, ?, ?, ?, ?, 'active')
        ON DUPLICATE KEY UPDATE
            first_name = VALUES(first_name),
            last_name  = VALUES(last_name),
            username   = VALUES(username),
            tg_user_id = VALUES(tg_user_id),
            status     = 'active'
    ");
    $stmt->execute([
        $phone,
        $result['first_name'] ?? '',
        $result['last_name']  ?? '',
        $result['username']   ?? '',
        $result['tg_user_id'] ?? 0,
        sessionFileForPhone($phone),
    ]);
}

// ═══════════════════════════════════════════════════════
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// ── AUTH ────────────────────────────────────────────────
if ($action === 'login') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $pdo  = getDB();
    $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE username = ? LIMIT 1");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_username']  = $username;
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Username atau password salah!']);
    }
    exit;
}

if ($action === 'logout') {
    session_destroy();
    echo json_encode(['success' => true]);
    exit;
}

// ═══════════════════════════════════════════════════════
//  ACCOUNT MANAGEMENT
// ═══════════════════════════════════════════════════════

// Step 1: Request OTP
if ($action === 'request_otp') {
    requireLogin();

    $phone = preg_replace('/[^0-9+]/', '', $_POST['phone'] ?? '');
    if (strlen($phone) < 8) {
        echo json_encode(['success' => false, 'message' => 'Nomor tidak valid (min 8 digit)']);
        exit;
    }

    if (empty(TG_API_ID) || empty(TG_API_HASH)) {
        echo json_encode(['success' => false, 'message' => '⚠ API ID & Hash belum diisi di config/app.php. Daftar di https://my.telegram.org/apps']);
        exit;
    }

    $result = runScript(__DIR__ . '/../scripts/request_otp.php', [$phone]);

    if ($result['success'] ?? false) {
        $_SESSION['otp_phone']           = $phone;
        $_SESSION['otp_phone_code_hash'] = $result['phone_code_hash'] ?? '';
    }

    echo json_encode($result);
    exit;
}

// Step 2: Verify OTP
if ($action === 'verify_otp') {
    requireLogin();

    $phone = $_SESSION['otp_phone'] ?? '';
    $code  = trim($_POST['code'] ?? '');

    if (empty($phone)) {
        echo json_encode(['success' => false, 'message' => 'Session OTP tidak ditemukan. Minta kode baru.']);
        exit;
    }
    if (strlen($code) < 5) {
        echo json_encode(['success' => false, 'message' => 'Kode OTP harus 5 digit']);
        exit;
    }

    $result = runScript(__DIR__ . '/../scripts/verify_otp.php', [$phone, $code]);

    if ($result['success'] ?? false) {
        saveAccountToDB($phone, $result);
        unset($_SESSION['otp_phone'], $_SESSION['otp_phone_code_hash']);
    }

    echo json_encode($result);
    exit;
}

// Step 2b: 2FA
if ($action === 'verify_2fa') {
    requireLogin();

    $phone    = $_SESSION['otp_phone'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($phone) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'Data tidak lengkap']);
        exit;
    }

    $result = runScript(__DIR__ . '/../scripts/verify_2fa.php', [$phone, $password]);

    if ($result['success'] ?? false) {
        saveAccountToDB($phone, $result);
        unset($_SESSION['otp_phone'], $_SESSION['otp_phone_code_hash']);
    }

    echo json_encode($result);
    exit;
}

// List accounts
if ($action === 'get_accounts') {
    requireLogin();
    $pdo  = getDB();
    $rows = $pdo->query("
        SELECT id, phone, first_name, last_name, username, tg_user_id, status, added_at, last_used, notes
        FROM tg_accounts
        ORDER BY added_at DESC
    ")->fetchAll();
    echo json_encode(['success' => true, 'data' => $rows]);
    exit;
}

// Delete account
if ($action === 'delete_account') {
    requireLogin();
    $id  = (int)($_POST['id'] ?? 0);
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT phone, session_file FROM tg_accounts WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if ($row) {
        $sessFile = $row['session_file'] ?: sessionFileForPhone($row['phone']);
        if ($sessFile && file_exists($sessFile)) @unlink($sessFile);
        foreach (glob($sessFile . '*') ?: [] as $f) @unlink($f);
        $pdo->prepare("DELETE FROM tg_accounts WHERE id = ?")->execute([$id]);
    }
    echo json_encode(['success' => true]);
    exit;
}

// ═══════════════════════════════════════════════════════
//  CONTACTS
// ═══════════════════════════════════════════════════════

if ($action === 'get_contacts') {
    requireLogin();
    $group = trim($_GET['group'] ?? '');
    $pdo   = getDB();
    if ($group !== '') {
        $stmt = $pdo->prepare("SELECT * FROM broadcast_contacts WHERE group_name = ? ORDER BY added_at DESC");
        $stmt->execute([$group]);
    } else {
        $stmt = $pdo->query("SELECT * FROM broadcast_contacts ORDER BY added_at DESC");
    }
    echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
    exit;
}

if ($action === 'add_contact') {
    requireLogin();
    $phone       = preg_replace('/[^0-9+]/', '', $_POST['phone'] ?? '');
    $username    = ltrim(trim($_POST['username'] ?? ''), '@');
    $displayName = trim($_POST['display_name'] ?? '');
    $group       = trim($_POST['group_name'] ?? 'Default') ?: 'Default';

    if (empty($phone) && empty($username)) {
        echo json_encode(['success' => false, 'message' => 'Phone atau username harus diisi']);
        exit;
    }

    $pdo  = getDB();
    $stmt = $pdo->prepare("INSERT INTO broadcast_contacts (phone, username, display_name, group_name) VALUES (?, ?, ?, ?)");
    $stmt->execute([$phone ?: null, $username ?: null, $displayName, $group]);
    echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
    exit;
}

if ($action === 'delete_contact') {
    requireLogin();
    $id = (int)($_POST['id'] ?? 0);
    getDB()->prepare("DELETE FROM broadcast_contacts WHERE id = ?")->execute([$id]);
    echo json_encode(['success' => true]);
    exit;
}

// Import contacts CSV
if ($action === 'import_contacts') {
    requireLogin();
    if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'File tidak ada atau upload error']);
        exit;
    }
    $tmpFile = $_FILES['csv_file']['tmp_name'];
    $group   = trim($_POST['group_name'] ?? '') ?: 'Import ' . date('d/m/Y');
    $pdo     = getDB();
    $stmt    = $pdo->prepare("INSERT IGNORE INTO broadcast_contacts (phone, username, display_name, group_name) VALUES (?, ?, ?, ?)");
    $count   = 0;
    if (($handle = fopen($tmpFile, 'r')) !== false) {
        $firstRow = true;
        while (($row = fgetcsv($handle, 2000, ',')) !== false) {
            if ($firstRow) { $firstRow = false; continue; }
            $phone = preg_replace('/[^0-9+]/', '', $row[0] ?? '');
            $name  = trim($row[1] ?? '');
            $uname = ltrim(trim($row[2] ?? ''), '@');
            if ($phone || $uname) {
                $stmt->execute([$phone ?: null, $uname ?: null, $name, $group]);
                $count++;
            }
        }
        fclose($handle);
    }
    echo json_encode(['success' => true, 'imported' => $count]);
    exit;
}

// ═══════════════════════════════════════════════════════
//  CAMPAIGNS / BROADCAST
// ═══════════════════════════════════════════════════════

if ($action === 'create_campaign') {
    requireLogin();
    $name    = trim($_POST['name'] ?? '') ?: 'Campaign ' . date('d/m/Y H:i');
    $message = trim($_POST['message'] ?? '');
    $delay   = max(1, (int)($_POST['delay'] ?? 3));

    if (empty($message)) {
        echo json_encode(['success' => false, 'message' => 'Pesan tidak boleh kosong']);
        exit;
    }

    $mediaPath = null;
    $mediaType = 'none';
    if (isset($_FILES['media']) && $_FILES['media']['error'] === UPLOAD_ERR_OK) {
        $ext     = strtolower(pathinfo($_FILES['media']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png','gif','mp4','mov','avi','pdf','zip','doc','docx'];
        if (!in_array($ext, $allowed)) {
            echo json_encode(['success' => false, 'message' => "Tipe file .{$ext} tidak diizinkan"]);
            exit;
        }
        $uploadDir = realpath(__DIR__ . '/../') . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR;
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        $filename = uniqid('media_') . '.' . $ext;
        move_uploaded_file($_FILES['media']['tmp_name'], $uploadDir . $filename);
        $mediaPath = $uploadDir . $filename;
        $mime      = mime_content_type($mediaPath);
        $mediaType = str_contains($mime, 'image') ? 'photo' : (str_contains($mime, 'video') ? 'video' : 'document');
    }

    $pdo  = getDB();
    $stmt = $pdo->prepare("INSERT INTO broadcast_campaigns (name, message_text, media_path, media_type, delay_seconds) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$name, $message, $mediaPath, $mediaType, $delay]);
    echo json_encode(['success' => true, 'campaign_id' => $pdo->lastInsertId()]);
    exit;
}

if ($action === 'get_campaigns') {
    requireLogin();
    $rows = getDB()->query("SELECT * FROM broadcast_campaigns ORDER BY created_at DESC LIMIT 50")->fetchAll();
    echo json_encode(['success' => true, 'data' => $rows]);
    exit;
}

// Start broadcast
if ($action === 'start_broadcast') {
    requireLogin();
    $campaignId = (int)($_POST['campaign_id'] ?? 0);
    $accountIds = (array)($_POST['account_ids'] ?? []);
    $groupName  = trim($_POST['group_name'] ?? '');

    $pdo = getDB();

    // Get campaign
    $stmt = $pdo->prepare("SELECT * FROM broadcast_campaigns WHERE id = ?");
    $stmt->execute([$campaignId]);
    $campaign = $stmt->fetch();
    if (!$campaign) {
        echo json_encode(['success' => false, 'message' => 'Campaign tidak ditemukan']);
        exit;
    }

    // Get accounts
    if (!empty($accountIds)) {
        $in   = implode(',', array_map('intval', $accountIds));
        $accs = $pdo->query("SELECT * FROM tg_accounts WHERE id IN ($in) AND status = 'active'")->fetchAll();
    } else {
        $accs = $pdo->query("SELECT * FROM tg_accounts WHERE status = 'active'")->fetchAll();
    }

    if (empty($accs)) {
        echo json_encode(['success' => false, 'message' => 'Tidak ada akun aktif yang dipilih']);
        exit;
    }

    // Get contacts
    if (!empty($groupName)) {
        $stmt = $pdo->prepare("SELECT * FROM broadcast_contacts WHERE group_name = ? AND is_active = 1");
        $stmt->execute([$groupName]);
    } else {
        $stmt = $pdo->query("SELECT * FROM broadcast_contacts WHERE is_active = 1");
    }
    $contacts = $stmt->fetchAll();

    if (empty($contacts)) {
        echo json_encode(['success' => false, 'message' => 'Tidak ada kontak tujuan' . ($groupName ? " dalam grup '{$groupName}'" : '')]);
        exit;
    }

    // Mark running
    $pdo->prepare("UPDATE broadcast_campaigns SET status='running', started_at=NOW(), total_target=?, sent_count=0, failed_count=0 WHERE id=?")
        ->execute([count($contacts), $campaignId]);

    // Delete old logs for this campaign (jika re-run)
    $pdo->prepare("DELETE FROM broadcast_logs WHERE campaign_id = ? AND status = 'pending'")->execute([$campaignId]);

    // Insert pending log entries dengan round-robin account distribution
    $logStmt  = $pdo->prepare("
        INSERT INTO broadcast_logs (campaign_id, account_id, contact_id, recipient, status)
        VALUES (?, ?, ?, ?, 'pending')
    ");
    $accCount = count($accs);
    foreach ($contacts as $i => $contact) {
        $acc       = $accs[$i % $accCount];
        $recipient = !empty($contact['username'])
            ? '@' . ltrim($contact['username'], '@')
            : ($contact['phone'] ?? '');
        if (empty($recipient)) continue;
        $logStmt->execute([$campaignId, $acc['id'], $contact['id'], $recipient]);
    }

    // Fire background worker (non-blocking)
    $phpBin       = PHP_BINARY ?: 'php';
    $workerScript = realpath(__DIR__ . '/../scripts/broadcast_worker.php');
    if (PHP_OS_FAMILY === 'Windows') {
        $cmd = "start /B \"{$phpBin}\" \"{$workerScript}\" {$campaignId}";
        pclose(popen($cmd, 'r'));
    } else {
        exec("\"{$phpBin}\" \"{$workerScript}\" {$campaignId} > /dev/null 2>&1 &");
    }

    echo json_encode(['success' => true, 'message' => "Broadcast dimulai! Mengirim ke " . count($contacts) . " kontak via " . count($accs) . " akun."]);
    exit;
}

// Get campaign logs
if ($action === 'get_campaign_logs') {
    requireLogin();
    $cid  = (int)($_GET['campaign_id'] ?? 0);
    $pdo  = getDB();

    $stmt = $pdo->prepare("SELECT * FROM broadcast_campaigns WHERE id = ?");
    $stmt->execute([$cid]);
    $camp = $stmt->fetch();

    $logStmt = $pdo->prepare("
        SELECT l.*, a.phone AS account_phone, a.first_name AS account_name
        FROM broadcast_logs l
        LEFT JOIN tg_accounts a ON a.id = l.account_id
        WHERE l.campaign_id = ?
        ORDER BY l.id DESC
        LIMIT 500
    ");
    $logStmt->execute([$cid]);

    echo json_encode(['success' => true, 'campaign' => $camp, 'logs' => $logStmt->fetchAll()]);
    exit;
}

// Dashboard stats
if ($action === 'get_stats') {
    requireLogin();
    $pdo   = getDB();
    $stats = [
        'total_accounts'  => (int)$pdo->query("SELECT COUNT(*) FROM tg_accounts WHERE status='active'")->fetchColumn(),
        'total_contacts'  => (int)$pdo->query("SELECT COUNT(*) FROM broadcast_contacts WHERE is_active=1")->fetchColumn(),
        'total_campaigns' => (int)$pdo->query("SELECT COUNT(*) FROM broadcast_campaigns")->fetchColumn(),
        'total_sent'      => (int)$pdo->query("SELECT COALESCE(SUM(sent_count),0) FROM broadcast_campaigns")->fetchColumn(),
    ];
    echo json_encode(['success' => true, 'data' => $stats]);
    exit;
}

// Pause/Resume campaign
if ($action === 'pause_campaign') {
    requireLogin();
    $cid = (int)($_POST['id'] ?? 0);
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT status FROM broadcast_campaigns WHERE id = ?");
    $stmt->execute([$cid]);
    $current = $stmt->fetchColumn();
    $newStatus = ($current === 'running') ? 'paused' : 'running';
    $pdo->prepare("UPDATE broadcast_campaigns SET status = ? WHERE id = ?")->execute([$newStatus, $cid]);
    echo json_encode(['success' => true, 'new_status' => $newStatus]);
    exit;
}

// Unknown action
http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Unknown action: ' . htmlspecialchars($action)]);
