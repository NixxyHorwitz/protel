<?php
require_once __DIR__ . '/core/database.php';
// Include Composer autoload for MadelineProto
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

// ─── Temp State Helper ────────────────────────────────────────────────────────
function getTempState(int|string $from_id): ?array {
    $f = __DIR__ . "/sessions/state_{$from_id}.json";
    return file_exists($f) ? json_decode(file_get_contents($f), true) : null;
}

function setTempState(int|string $from_id, ?string $status, string $phone = '', int $msg_id = 0): void {
    if (!is_dir(__DIR__ . '/sessions')) mkdir(__DIR__ . '/sessions', 0777, true);
    $f = __DIR__ . "/sessions/state_{$from_id}.json";
    if ($status === null) {
        if (file_exists($f)) @unlink($f);
    } else {
        file_put_contents($f, json_encode(['status' => $status, 'phone_number' => $phone, 'msg_id' => $msg_id]));
    }
}

// ─── Telegram API Helper ──────────────────────────────────────────────────────
function tg(string $method, array $params): ?array {
    global $pdo;
    $settings = $pdo->query("SELECT bot_token FROM bot_settings WHERE id = 1")->fetch();
    if (!$settings || empty($settings['bot_token'])) return null;

    $url  = "https://api.telegram.org/bot{$settings['bot_token']}/$method";
    $ch   = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($params),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
    ]);
    $res = curl_exec($ch);
    curl_close($ch);
    return $res ? json_decode($res, true) : null;
}

function sendMessage(int|string $chat_id, string $text, array $reply_markup = []): array|null {
    $params = ['chat_id' => $chat_id, 'text' => $text, 'parse_mode' => 'HTML'];
    if (!empty($reply_markup)) $params['reply_markup'] = $reply_markup;
    return tg('sendMessage', $params);
}

function editMessage(int|string $chat_id, int $message_id, string $text, array $reply_markup = []): void {
    $params = ['chat_id' => $chat_id, 'message_id' => $message_id, 'text' => $text, 'parse_mode' => 'HTML'];
    if (!empty($reply_markup)) $params['reply_markup'] = $reply_markup;
    tg('editMessageText', $params);
}

function deleteMessage(int|string $chat_id, int $message_id): void {
    tg('deleteMessage', ['chat_id' => $chat_id, 'message_id' => $message_id]);
}

function answerCallback(string $callback_id, string $text = '', bool $alert = false): void {
    tg('answerCallbackQuery', ['callback_query_id' => $callback_id, 'text' => $text, 'show_alert' => $alert]);
}

// ─── MadelineProto Helper ─────────────────────────────────────────────────────
function getMadeline(int|string $telegram_id, string $phone_number) {
    if (!is_dir(__DIR__ . '/sessions')) mkdir(__DIR__ . '/sessions', 0777, true);
    $settings = new \danog\MadelineProto\Settings();
    $settings->getLogger()->setLevel(\danog\MadelineProto\Logger::FATAL_ERROR);
    $settings->getPeer()->setCacheAllPeersOnStartup(false);
    $settings->getAppInfo()->setApiId(2040)->setApiHash('b18441a1ff607e10a989891a5462e627');
    
    // Uniq session per phone number so multi users can exist
    $safe_phone = preg_replace('/[^0-9]/', '', $phone_number);
    $sessionPath = __DIR__ . "/sessions/session_{$telegram_id}_{$safe_phone}.madeline";
    return new \danog\MadelineProto\API($sessionPath, $settings);
}

// ─── Error Catcher ────────────────────────────────────────────────────────────
$current_chat_id = null;
register_shutdown_function(function() use (&$current_chat_id) {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE])) {
        write_log('FATAL_ERROR', "{$error['message']} in {$error['file']}:{$error['line']}");
        if ($current_chat_id) {
            sendMessage($current_chat_id, "❌ <b>Sistem Timeout / Crash!</b>\nProses memakan waktu terlalu lama. Hal ini sering terjadi karena koneksi Telegram. Coba ulangi kembali.");
        }
    }
});

// ─── Input Payload ────────────────────────────────────────────────────────────
$input = file_get_contents('php://input');
if (!$input) { http_response_code(200); exit('OK'); }
$update = json_decode($input, true);
if (!$update) { http_response_code(200); exit('OK'); }

$settings = $pdo->query("SELECT * FROM bot_settings WHERE id = 1")->fetch();

// ─── Helpers menus ────────────────────────────────────────────────────────────
function menuDashboard(): array {
    return [
        'inline_keyboard' => [
            [['text' => '📱 My Accounts', 'callback_data' => 'accounts'], ['text' => '📢 Broadcast Menu', 'callback_data' => 'broadcast_menu']],
            [['text' => '👥 Import Contacts', 'callback_data' => 'contacts_menu'], ['text' => '💳 Package & Coins', 'callback_data' => 'package_info']],
            [['text' => '📞 Contact Admin', 'callback_data' => 'admin_contact']]
        ]
    ];
}

function processUpdateUser($from_id, $first_name) {
    global $pdo;
    $stmt = $pdo->prepare("INSERT IGNORE INTO users (telegram_id, name) VALUES (?, ?)");
    $stmt->execute([$from_id, $first_name]);
    return $pdo->prepare("SELECT u.*, p.name as pkg_name, p.max_sessions FROM users u LEFT JOIN packages p ON u.package_id = p.id WHERE u.telegram_id = ?");
}

// ─── Callback Queries ─────────────────────────────────────────────────────────
if (isset($update['callback_query'])) {
    $cb      = $update['callback_query'];
    $cb_id   = $cb['id'];
    $data    = $cb['data'];
    $chat_id = $cb['message']['chat']['id'];
    $msg_id  = $cb['message']['message_id'];
    $from_id = $cb['from']['id'];
    global $current_chat_id; $current_chat_id = $chat_id;

    $stmt = processUpdateUser($from_id, $cb['from']['first_name']??'');
    $stmt->execute([$from_id]);
    $user = $stmt->fetch();

    $args = explode(':', $data);
    $cmd = $args[0];

    switch ($cmd) {
        case 'dashboard':
            answerCallback($cb_id);
            setTempState($from_id, null); // Clear temp state
            $msg = "⚡️ <b>ProTel Dashboard</b>\n\nPilih menu operasi di bawah ini:";
            editMessage($chat_id, $msg_id, $msg, menuDashboard());
            // Cleanup any ghost pending sessions from mysql if any were left over from old logic
            $pdo->prepare("DELETE FROM user_sessions WHERE telegram_id = ? AND status IN ('pending', 'wait_otp', 'wait_password')")->execute([$from_id]);
            break;

        case 'accounts':
            answerCallback($cb_id);
            $sess_stmt = $pdo->prepare("SELECT * FROM user_sessions WHERE telegram_id = ? AND status = 'active'");
            $sess_stmt->execute([$from_id]);
            $sessions = $sess_stmt->fetchAll();

            $msg = "📱 <b>Akun Terhubung:</b> <code>" . count($sessions) . " / " . $user['max_sessions'] . "</code>\n\n";
            $kb = [];
            foreach ($sessions as $i => $s) {
                $icon = match($s['status']) { 'active'=>'✅', 'banned'=>'🚫', default=>'❓' };
                $msg .= ($i+1) . ". {$icon} <code>" . htmlspecialchars($s['phone_number']) . "</code> - " . ucfirst($s['status']) . "\n";
                $kb[] = [['text' => "⚙️ Kelola " . $s['phone_number'], 'callback_data' => "manage_acc:{$s['id']}"]];
            }
            if (count($sessions) < $user['max_sessions']) {
                $kb[] = [['text' => '➕ Tambah Akun / Nomor Baru', 'callback_data' => 'add_acc']];
            } else {
                $msg .= "\n⚠️ <i>Limit akun tercapai sesuai paket Anda. Upgrade untuk menambah slot.</i>";
            }
            $kb[] = [['text' => '🔙 Kembali', 'callback_data' => 'dashboard']];
            editMessage($chat_id, $msg_id, $msg, ['inline_keyboard' => $kb]);
            break;

        case 'manage_acc':
            $acc_id = $args[1] ?? 0;
            $s = $pdo->prepare("SELECT * FROM user_sessions WHERE id = ? AND telegram_id = ?");
            $s->execute([$acc_id, $from_id]);
            $acc = $s->fetch();
            if (!$acc) { answerCallback($cb_id, "Akun tidak ditemukan!", true); break; }
            answerCallback($cb_id);
            $msg = "⚙️ <b>Kelola Akun</b>\n\nNomor: <code>{$acc['phone_number']}</code>\nStatus: <b>{$acc['status']}</b>";
            $kb = [
                [['text' => '🗑 Hapus / Logout Akun', 'callback_data' => "delete_acc:{$acc['id']}"]],
                [['text' => '🔙 Kembali', 'callback_data' => 'accounts']]
            ];
            editMessage($chat_id, $msg_id, $msg, ['inline_keyboard' => $kb]);
            break;
            
        case 'delete_acc':
            $acc_id = $args[1] ?? 0;
            $s = $pdo->prepare("SELECT phone_number FROM user_sessions WHERE id = ? AND telegram_id = ?");
            $s->execute([$acc_id, $from_id]);
            $acc_phone = $s->fetchColumn();

            if ($acc_phone) {
                // Remove madeline session file cleanly
                $safe_phone = preg_replace('/[^0-9]/', '', $acc_phone);
                $sessionPath = __DIR__ . "/sessions/session_{$from_id}_{$safe_phone}.madeline";
                @unlink($sessionPath);
                $pdo->prepare("DELETE FROM user_sessions WHERE id = ?")->execute([$acc_id]);
                answerCallback($cb_id, "Akun berhasil dihapus dan dilogout!", true);
            }
            $cb['data'] = 'accounts'; goto redispatch;
            break;

        case 'cancel_add':
            answerCallback($cb_id, "Penambahan akun dibatalkan", true);
            setTempState($from_id, null);
            $cb['data'] = 'accounts'; goto redispatch;
            break;

        case 'add_acc':
            // Check limitations first
            $sess_stmt = $pdo->prepare("SELECT COUNT(*) FROM user_sessions WHERE telegram_id = ? AND status = 'active'");
            $sess_stmt->execute([$from_id]);
            if ($sess_stmt->fetchColumn() >= $user['max_sessions']) {
                answerCallback($cb_id, "Limit akun paket Anda penuh!", true);
                break;
            }
            answerCallback($cb_id);
            $msg = "📱 <b>Menambah Nomor Baru</b>\n\nKirimkan nomor HP Telegram kamu dengan format internasional (Contoh: <code>+628123xxxx</code>).";
            $kb = [[['text' => '🔙 Batal', 'callback_data' => 'cancel_add']]];
            
            // Set temporary state to JSON! We don't save to database yet!
            setTempState($from_id, 'pending', '');
            
            editMessage($chat_id, $msg_id, $msg, ['inline_keyboard' => $kb]);
            break;

        case 'resend_otp':
            $state = getTempState($from_id);
            if (!$state || $state['status'] !== 'wait_otp') {
                answerCallback($cb_id, "Sesi tidak valid / Sedang diproses.", true); break;
            }
            answerCallback($cb_id, "Meminta OTP ulang...");
            
            // Advance state to block duplicate webhooks from calling phoneLogin again!
            setTempState($from_id, 'processing_resend', $state['phone_number'], $msg_id);
            
            try {
                $API = getMadeline($from_id, $state['phone_number']);
                $API->phoneLogin($state['phone_number']);
                // Revert state to purely wait_otp so user can type the code
                setTempState($from_id, 'wait_otp', $state['phone_number'], $msg_id);
                editMessage($chat_id, $msg_id, "📩 <b>OTP Dikirim Ulang!</b>\n\nCek aplikasi Telegrammu.\n\nKetik langsung kode OTP nya (bebas saja formatnya).", [
                    'inline_keyboard' => [[['text' => '🔙 Batal Login', 'callback_data' => "cancel_add"]]]
                ]);
            } catch (\Exception $e) {
                setTempState($from_id, 'wait_otp', $state['phone_number'], $msg_id); // Re-open
                editMessage($chat_id, $msg_id, "❌ <b>Gagal Resend OTP</b>\n\n<code>{$e->getMessage()}</code>", [
                    'inline_keyboard' => [[['text' => '🔙 Batal', 'callback_data' => 'cancel_add']]]
                ]);
            }
            break;

        case 'broadcast_menu':
            answerCallback($cb_id);
            setTempState($from_id, null);
            
            $msg = "📢 <b>Broadcast Command Center</b>\n\nUntuk memulai kampanye pesan masal dan mengontrol task Anda secara Real-Time, silakan buka Mini-App kami.";
            
            // Build the URL to the MiniApp dynamically (since the bot could run anywhere)
            $host = str_replace(['http://', 'https://'], '', BASE_URL);
            $app_url = BASE_URL . "/app/index.php";

            // WebApp Inline Button
            $kb = [
                [['text' => '🚀 Buka Mini App Broadcast', 'web_app' => ['url' => $app_url]]],
                [['text' => '🔙 Kembali', 'callback_data' => 'dashboard']]
            ];
            editMessage($chat_id, $msg_id, $msg, ['inline_keyboard' => $kb]);
            break;
            
        case 'bc_start':
        case 'bc_pause':
        case 'bc_stop':
            answerCallback($cb_id, "Perintah dikirim!", true);
            $bid = $args[1] ?? 0;
            $st = ($cmd == 'bc_start') ? 'process' : (($cmd == 'bc_pause') ? 'paused' : 'failed'); // Stop marks as failed/cancelled
            $pdo->prepare("UPDATE broadcasts SET status = ? WHERE id = ?")->execute([$st, $bid]);
            $cb['data'] = 'broadcast_menu'; goto redispatch;
            break;

        case 'contacts_menu':
            answerCallback($cb_id, "Fitur Import Kontak", false);
            setTempState($from_id, null);
            // Count total contacts
            $c = $pdo->prepare("SELECT COUNT(*) FROM contacts WHERE session_id IN (SELECT id FROM user_sessions WHERE telegram_id = ?)");
            $c->execute([$from_id]);
            $total = $c->fetchColumn();
            
            $msg = "👥 <b>Manajemen Kontak</b>\n\nTotal kontakmu: <b>{$total}</b>\n\nUntuk mengimport, kirim file <code>.txt</code> atau <code>.csv</code> berisi list ID atau nomor HP / username satu per baris.";
            $kb = [[['text' => '🗑 Hapus Semua Kontak', 'callback_data' => 'contacts_clear']], [['text' => '🔙 Kembali', 'callback_data' => 'dashboard']]];
            editMessage($chat_id, $msg_id, $msg, ['inline_keyboard' => $kb]);
            break;
            
        case 'contacts_clear':
            $pdo->prepare("DELETE FROM contacts WHERE session_id IN (SELECT id FROM user_sessions WHERE telegram_id = ?)")->execute([$from_id]);
            answerCallback($cb_id, "Semua kontak dibersihkan!", true);
            $cb['data'] = 'contacts_menu'; goto redispatch;
            break;

        case 'package_info':
            answerCallback($cb_id);
            $msg = "💳 <b>Informasi Akun</b>\n\n";
            $msg .= "👤 User: {$user['name']}\n";
            $msg .= "🪙 Coins: <b>{$user['coins']}</b>\n";
            $msg .= "📦 Paket: <b>{$user['pkg_name']}</b> (Max {$user['max_sessions']} akun)\n\n";
            $msg .= "<i>Ingin topup coin atau upgrade paket? Hubungi admin.</i>";
            editMessage($chat_id, $msg_id, $msg, ['inline_keyboard' => [[['text' => '🔙 Kembali', 'callback_data' => 'dashboard']]]]);
            break;
            
        case 'admin_contact':
            answerCallback($cb_id);
            editMessage($chat_id, $msg_id, "📞 Hubungi admin di @admin.", ['inline_keyboard' => [[['text' => '🔙 Kembali', 'callback_data' => 'dashboard']]]]);
            break;
    }

redispatch:
    http_response_code(200);
    exit('OK');
}

// ─── Handle Text Messages ─────────────────────────────────────────────────────
if (isset($update['message'])) {
    $msg     = $update['message'];
    $chat_id = $msg['chat']['id'];
    $from_id = $msg['from']['id'];
    $text    = trim($msg['text'] ?? '');
    global $current_chat_id; $current_chat_id = $chat_id;

    $stmt = processUpdateUser($from_id, $msg['from']['first_name']??'');
    $stmt->execute([$from_id]);
    $user = $stmt->fetch();

    if ($text === '/start' || $text === '/menu' || $text === '/dashboard') {
        setTempState($from_id, null);
        $name = $msg['from']['first_name'] ?? 'Pengguna';
        $w = $settings['welcome_message'] ?? "Halo {name}!";
        $txt = str_replace('{name}', htmlspecialchars($name), $w);
        sendMessage($chat_id, $txt, menuDashboard());
        http_response_code(200); exit;
    }
    
    // Check Temporary JSON State!!
    $state = getTempState($from_id);

    // Import contact via Contact Share (Attachment)
    if (isset($msg['contact'])) {
        // Hapus balon kontak besar yang dikirim user agar chat tidak nyampah
        deleteMessage($chat_id, $msg['message_id']);

        $phone = preg_replace('/[^0-9+]/', '', $msg['contact']['phone_number']);
        $name = trim(($msg['contact']['first_name'] ?? '') . ' ' . ($msg['contact']['last_name'] ?? ''));
        if (empty($name)) $name = 'Contact';

        if ($phone) {
            $s_stmt = $pdo->prepare("SELECT id FROM user_sessions WHERE telegram_id = ? AND status = 'active' LIMIT 1");
            $s_stmt->execute([$from_id]);
            $act_s = $s_stmt->fetchColumn();
            
            if ($act_s) {
                try {
                    $pdo->prepare("INSERT IGNORE INTO contacts (session_id, phone_or_username, type, name) VALUES (?, ?, 'phone', ?)")->execute([$act_s, $phone, $name]);
                    
                    // Grouping Notifikasi: edit message lama jika diimpor dalam waktu berdekatan
                    $sfile = __DIR__ . "/sessions/import_{$from_id}.json";
                    $istate = file_exists($sfile) ? json_decode(file_get_contents($sfile), true) : ['msg_id' => 0, 'time' => 0];
                    $now = time();
                    
                    // Hitung jumlah kontak yang baru saja masuk dalam 1 menit terakhir
                    $cst = $pdo->prepare("SELECT COUNT(*) FROM contacts WHERE session_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 1 MINUTE)");
                    $cst->execute([$act_s]);
                    $recent_count = $cst->fetchColumn();

                    $msg_text = "✅ <b>$recent_count Kontak Tersimpan!</b>\nBerhasil menambahkan rentetan kontak tersebut ke dalam daftarmu.";
                    $kb = ['inline_keyboard' => [[['text' => '🔙 Menu Kontak', 'callback_data' => 'contacts_menu']]]];

                    if ($now - $istate['time'] < 30 && $istate['msg_id'] > 0) {
                        // Coba update pesan sebelumnya (bisa catch error jika teks persis sama)
                        try { editMessage($chat_id, $istate['msg_id'], $msg_text, $kb); } catch(\Exception $e) {}
                        $istate['time'] = $now;
                        file_put_contents($sfile, json_encode($istate));
                    } else {
                        // Kirim pesan balon notifikasi baru
                        $res = sendMessage($chat_id, $msg_text, $kb);
                        file_put_contents($sfile, json_encode(['msg_id' => $res['result']['message_id'] ?? 0, 'time' => $now]));
                    }
                } catch (\Exception $e) {
                    sendMessage($chat_id, "❌ <b>Gagal Menyimpan!</b>\n". $e->getMessage());
                }
            } else {
                sendMessage($chat_id, "❌ <b>Gagal!</b> Kamu harus punya minimal 1 sesi akun aktif untuk menyimpan kontak.", ['inline_keyboard' => [[['text' => '🔙 Beranda', 'callback_data' => 'dashboard']]]]);
            }
        }
        http_response_code(200); exit;
    }

    // Import contact trigger via document upload `.txt`/`.csv`
    if (isset($msg['document']) && in_array($msg['document']['mime_type'], ['text/plain', 'text/csv'])) {
        $file_id = $msg['document']['file_id'];
        $file_info = tg('getFile', ['file_id' => $file_id]);
        if ($file_info && isset($file_info['result']['file_path'])) {
            $settings = $pdo->query("SELECT bot_token FROM bot_settings WHERE id = 1")->fetch();
            $path = $file_info['result']['file_path'];
            $file_url = "https://api.telegram.org/file/bot{$settings['bot_token']}/$path";
            $content = @file_get_contents($file_url);
            
            if ($content) {
                // Find first active session for this user to assign contacts to
                $s_stmt = $pdo->prepare("SELECT id FROM user_sessions WHERE telegram_id = ? AND status = 'active' LIMIT 1");
                $s_stmt->execute([$from_id]);
                $act_s = $s_stmt->fetchColumn();
                
                if ($act_s) {
                    $lines = explode("\n", str_replace("\r", "", $content));
                    $inserted = 0;
                    $stmt = $pdo->prepare("INSERT IGNORE INTO contacts (session_id, phone_or_username, type, name) VALUES (?, ?, 'phone', ?)");
                    foreach ($lines as $ln) {
                        $ln = trim($ln); if (!$ln) continue;
                        $parts = explode(',', $ln);
                        $p = preg_replace('/[^0-9+]/', '', $parts[0]);
                        $n = $parts[1] ?? 'Contact';
                        if ($p) { $stmt->execute([$act_s, $p, $n]); $inserted++; }
                    }
                    sendMessage($chat_id, "✅ <b>Berhasil!</b> $inserted kontak berhasil diimport ke salah satu akun aktifmu.", ['inline_keyboard' => [[['text' => '🔙 Menu Kontak', 'callback_data' => 'contacts_menu']]]]);
                } else {
                    sendMessage($chat_id, "❌ <b>Gagal!</b> Kamu harus punya minimal 1 sesi aktif (Active) untuk menyimpan kontak.", ['inline_keyboard' => [[['text' => '🔙 Beranda', 'callback_data' => 'dashboard']]]]);
                }
            }
        }
        http_response_code(200); exit;
    }

    if (!$state) {
        // Not in any queue
        sendMessage($chat_id, "Pesan tidak dikenali atau anda tidak sedang dalam antrian perintah. Klik /menu");
        http_response_code(200); exit;
    }

    // STATE: PENDING (Waiting for Phone Number)
    if ($state['status'] === 'pending' && preg_match('/^\+?[0-9\s\-]+$/', $text)) {
        // Delete user's message anti-spam
        deleteMessage($chat_id, $msg['message_id']);
        
        $phone = preg_replace('/[^0-9+]/', '', $text);
        if (str_starts_with($phone, '08')) $phone = '+62' . substr($phone, 1);
        elseif (str_starts_with($phone, '62') && !str_starts_with($phone, '+')) $phone = '+' . $phone;

        $msg_sent = sendMessage($chat_id, "🔄 <i>Menghubungi Telegram untuk nomor $phone...</i>");
        $bot_msg_id = $msg_sent['result']['message_id'] ?? 0;
        
        // Anti-Duplicate Webhook: Advance state IMMEDIATELY before slow network call
        setTempState($from_id, 'wait_otp', $phone, $bot_msg_id);
        
        try {
            $API = getMadeline($from_id, $phone);
            $API->phoneLogin($phone);

            editMessage($chat_id, $bot_msg_id,
                "📩 <b>Kode OTP Telah Dikirim!</b>\n\n".
                "Telegram mengirimkan pesan OTP ke aplikasimu.\n\n".
                "👉 <b>Ketik saja kode kamu di sini secara utuh juga bisa!</b> (Filter kami akan merapikannya otomatis)",
                ['inline_keyboard' => [
                    [['text' => '🔁 Resend OTP', 'callback_data' => "resend_otp"]],
                    [['text' => '🔙 Batal', 'callback_data' => "cancel_add"]]
                ]]
            );
        } catch (\Exception $e) {
            // Revert state if failed
            setTempState($from_id, 'pending', '', $bot_msg_id);
            editMessage($chat_id, $bot_msg_id, 
                "❌ <b>Kesalahan:</b>\n<code>{$e->getMessage()}</code>\nCoba kirimkan kembali format nomor yang benar.",
                ['inline_keyboard' => [[['text' => '🔙 Batal', 'callback_data' => 'cancel_add']]]]
            );
        }
        http_response_code(200); exit;
    }

    // STATE: WAIT_OTP
    if ($state['status'] === 'wait_otp') {
        // Hapus karakter spasi/strip/titik barangkali user mengetik "1 2 3 4 5" atau "1-2-3-4-5"
        $clean_text = str_replace([' ', '-', '.', "\n", "\r"], '', $text);
        
        // Coba cari 5 angka berurutan (untuk antisipasi jika user Copas SELURUH teks SMS/Telegram)
        if (preg_match('/[0-9]{5}/', $clean_text, $m)) {
            $otp = $m[0];
        } else {
            $otp = preg_replace('/[^0-9]/', '', $text); // Fallback: ambil semua sisa angka
        }
        
        // Delete user's input line anti-spam
        deleteMessage($chat_id, $msg['message_id']);
        
        $bot_msg_id = $state['msg_id'] ?? 0;
        
        if (empty($otp)) {
            if ($bot_msg_id) editMessage($chat_id, $bot_msg_id, "❌ OTP tidak terdeteksi. Silakan ketik angka OTP saja.", ['inline_keyboard' => [[['text' => '🔙 Batal', 'callback_data' => 'cancel_add']]]]);
            http_response_code(200); exit;
        }
        
        // Lock state to processing
        setTempState($from_id, 'processing_otp', $state['phone_number'], $bot_msg_id);
        
        if ($bot_msg_id) editMessage($chat_id, $bot_msg_id, "🔄 <i>Memverifikasi OTP: $otp...</i>");
        else { $res = sendMessage($chat_id, "🔄 <i>Memverifikasi OTP: $otp...</i>"); $bot_msg_id = $res['result']['message_id']??0; }
        
        try {
            $API = getMadeline($from_id, $state['phone_number']);
            $API->completePhoneLogin($otp);
            // OTP SUCCESS! Insert into database definitively as ACTIVE!
            $pdo->prepare("INSERT INTO user_sessions (telegram_id, phone_number, status) VALUES (?, ?, 'active')")->execute([$from_id, $state['phone_number']]);
            setTempState($from_id, null); // remove from temp state
            
            editMessage($chat_id, $bot_msg_id, "✅ <b>Berhasil!</b> Akun terhubung.", ['inline_keyboard' => [[['text' => '🔙 Dasbor', 'callback_data' => 'dashboard']]]]);
        } catch (\danog\MadelineProto\Exception\Require2FAException $e) {
            // Needs 2FA, advance state
            setTempState($from_id, 'wait_password', $state['phone_number'], $bot_msg_id);
            editMessage($chat_id, $bot_msg_id, "🔐 <b>Akun di-2FA.</b>\nKirimkan password kamu:", ['inline_keyboard' => [[['text' => '🔙 Batal', 'callback_data' => 'cancel_add']]]]);
        } catch (\Exception $e) {
            setTempState($from_id, 'wait_otp', $state['phone_number'], $bot_msg_id); // Revert lock
            editMessage($chat_id, $bot_msg_id, "❌ <b>Gagal:</b> {$e->getMessage()}\nKirim OTP dengan benar. Jika ingin menyudahi klik Batal.", ['inline_keyboard' => [[['text' => '🔙 Batal', 'callback_data' => 'cancel_add']]]]);
        }
        http_response_code(200); exit;
    }

    // STATE: WAIT_PASSWORD
    if ($state['status'] === 'wait_password') {
        // Delete user's message anti-spam
        deleteMessage($chat_id, $msg['message_id']);
        
        $bot_msg_id = $state['msg_id'] ?? 0;
        // Lock state to processing
        setTempState($from_id, 'processing_pwd', $state['phone_number'], $bot_msg_id);
        
        if ($bot_msg_id) editMessage($chat_id, $bot_msg_id, "🔄 <i>Memverifikasi Password...</i>");
        
        try {
            $API = getMadeline($from_id, $state['phone_number']);
            $API->complete2faLogin(trim($text));
            // PASS SUCCESS! Insert fully
            $pdo->prepare("INSERT INTO user_sessions (telegram_id, phone_number, status) VALUES (?, ?, 'active')")->execute([$from_id, $state['phone_number']]);
            setTempState($from_id, null);
            
            editMessage($chat_id, $bot_msg_id, "✅ <b>Login Tuntas!</b> Akun aktif.", ['inline_keyboard' => [[['text' => '🔙 Dasbor', 'callback_data' => 'dashboard']]]]);
        } catch (\Exception $e) {
            setTempState($from_id, 'wait_password', $state['phone_number'], $bot_msg_id); // Revert lock
            editMessage($chat_id, $bot_msg_id, "❌ <b>Password Salah / Gagal:</b> {$e->getMessage()}\nKirim ulang passwordnya.", ['inline_keyboard' => [[['text' => '🔙 Batal', 'callback_data' => "cancel_add"]]]]);
        }
        http_response_code(200); exit;
    }
}
http_response_code(200); echo 'OK';
