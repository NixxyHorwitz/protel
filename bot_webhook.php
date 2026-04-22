<?php
require_once __DIR__ . '/core/database.php';
// Include Composer autoload for MadelineProto
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
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
            sendMessage($current_chat_id, "❌ <b>Sistem Timeout / Crash!</b>\nProses memakan waktu terlalu lama. Hal ini sering terjadi karena enkripsi lambat. Silakan hubungi admin.");
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

function processUpdateUser($from_id, $first_name, $username) {
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

    $stmt = processUpdateUser($from_id, $cb['from']['first_name']??'', '');
    $stmt->execute([$from_id]);
    $user = $stmt->fetch();

    $args = explode(':', $data);
    $cmd = $args[0];

    switch ($cmd) {
        case 'dashboard':
            answerCallback($cb_id);
            $msg = "⚡️ <b>ProTel Dashboard</b>\n\nPilih menu operasi di bawah ini:";
            editMessage($chat_id, $msg_id, $msg, menuDashboard());
            // Reset any pending session for safety
            $pdo->prepare("DELETE FROM user_sessions WHERE telegram_id = ? AND status = 'pending'")->execute([$from_id]);
            break;

        case 'accounts':
            answerCallback($cb_id);
            $sess_stmt = $pdo->prepare("SELECT * FROM user_sessions WHERE telegram_id = ? AND status != 'pending'");
            $sess_stmt->execute([$from_id]);
            $sessions = $sess_stmt->fetchAll();

            $msg = "📱 <b>Akun Terhubung:</b> <code>" . count($sessions) . " / " . $user['max_sessions'] . "</code>\n\n";
            $kb = [];
            foreach ($sessions as $i => $s) {
                $icon = match($s['status']) { 'active'=>'✅', 'wait_otp'=>'🔑','wait_password'=>'🔒', 'banned'=>'🚫', default=>'❓' };
                $msg .= ($i+1) . ". {$icon} <code>" . htmlspecialchars($s['phone_number']) . "</code> - " . ucfirst($s['status']) . "\n";
                // Add button to manage this account
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
            $pdo->prepare("DELETE FROM user_sessions WHERE id = ? AND telegram_id = ?")->execute([$acc_id, $from_id]);
            answerCallback($cb_id, "Akun berhasil dihapus!", true);
            // Auto back to accounts
            $cb['data'] = 'accounts'; goto redispatch;
            break;

        case 'add_acc':
            answerCallback($cb_id);
            $msg = "📱 <b>Menambah Nomor Baru</b>\n\nKirimkan nomor HP Telegram kamu dengan format internasional (Contoh: <code>+628123xxxx</code>).";
            $kb = [[['text' => '🔙 Batal', 'callback_data' => 'dashboard']]];
            
            // Delete old pending
            $pdo->prepare("DELETE FROM user_sessions WHERE telegram_id = ? AND status = 'pending'")->execute([$from_id]);
            // Insert new pending
            $pdo->prepare("INSERT INTO user_sessions (telegram_id, phone_number, status) VALUES (?, '', 'pending')")->execute([$from_id]);
            
            editMessage($chat_id, $msg_id, $msg, ['inline_keyboard' => $kb]);
            break;

        case 'resend_otp':
            $acc_id = $args[1] ?? 0;
            $s = $pdo->prepare("SELECT * FROM user_sessions WHERE id = ? AND telegram_id = ?");
            $s->execute([$acc_id, $from_id]);
            $acc = $s->fetch();
            if (!$acc || $acc['status'] !== 'wait_otp') {
                answerCallback($cb_id, "Sesi tidak valid.", true); break;
            }
            answerCallback($cb_id, "Meminta OTP ulang...");
            try {
                $API = getMadeline($from_id, $acc['phone_number']);
                $API->phoneLogin($acc['phone_number']);
                editMessage($chat_id, $msg_id, "📩 <b>OTP Dikirim Ulang!</b>\n\nCek aplikasi Telegrammu.\n\nKetik langsung kode OTP nya (bebas saja formatnya).", [
                    'inline_keyboard' => [[['text' => '🔙 Batal Login', 'callback_data' => "delete_acc:{$acc['id']}"]]]
                ]);
            } catch (\Exception $e) {
                editMessage($chat_id, $msg_id, "❌ <b>Gagal Resend OTP</b>\n\n<code>{$e->getMessage()}</code>", [
                    'inline_keyboard' => [[['text' => '🔙 Kembali', 'callback_data' => 'accounts']]]
                ]);
            }
            break;

        case 'broadcast_menu':
            answerCallback($cb_id);
            $sess_stmt = $pdo->prepare("SELECT b.*, s.phone_number FROM broadcasts b JOIN user_sessions s ON b.session_id = s.id WHERE s.telegram_id = ? ORDER BY b.id DESC LIMIT 5");
            $sess_stmt->execute([$from_id]);
            $bcasts = $sess_stmt->fetchAll();

            $msg = "📢 <b>Broadcast Terakhir</b>\n\n";
            $kb = [[['text' => '➕ Buat Broadcast Baru', 'callback_data' => 'new_broadcast']]];
            
            if (empty($bcasts)) {
                $msg .= "<i>Belum ada task broadcast.</i>";
            } else {
                foreach($bcasts as $b) {
                    $pct = $b['target_count'] > 0 ? floor(($b['sent_count']/$b['target_count'])*100) : 0;
                    $msg .= "ID: {$b['id']} | <b>{$b['status']}</b> | Sent: {$b['sent_count']}\n";
                    if ($b['status'] === 'draft' || $b['status'] === 'paused') {
                        $kb[] = [['text' => "▶️ Start BC #{$b['id']}", 'callback_data' => "bc_start:{$b['id']}"]];
                    } elseif ($b['status'] === 'process') {
                        $kb[] = [
                            ['text' => "⏸ Pause BC #{$b['id']}", 'callback_data' => "bc_pause:{$b['id']}"],
                            ['text' => "⏹ Stop BC #{$b['id']}", 'callback_data' => "bc_stop:{$b['id']}"]
                        ];
                    }
                }
            }
            $kb[] = [['text' => '🔙 Kembali', 'callback_data' => 'dashboard']];
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

    $stmt = processUpdateUser($from_id, $msg['from']['first_name']??'', '');
    $stmt->execute([$from_id]);
    $user = $stmt->fetch();

    if ($text === '/start' || $text === '/menu' || $text === '/dashboard') {
        $name = $msg['from']['first_name'] ?? 'Pengguna';
        $w = $settings['welcome_message'] ?? "Halo {name}!";
        $txt = str_replace('{name}', htmlspecialchars($name), $w);
        sendMessage($chat_id, $txt, menuDashboard());
        http_response_code(200); exit;
    }
    
    // Check pending session states (only the most recently modified pending/wait_otp/wait_password row)
    $s_stmt = $pdo->prepare("SELECT * FROM user_sessions WHERE telegram_id = ? AND status IN ('pending', 'wait_otp', 'wait_password') ORDER BY updated_at DESC LIMIT 1");
    $s_stmt->execute([$from_id]);
    $session = $s_stmt->fetch();

    // Import contact trigger via document upload
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
                    $stmt = $pdo->prepare("INSERT IGNORE INTO contacts (session_id, phone_number, name) VALUES (?, ?, ?)");
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

    if (!$session) {
        // Just delete message or ignore to reduce spam
        sendMessage($chat_id, "Pesan tidak dikenali atau anda tidak sedang dalam antrian perintah. Klik /menu");
        http_response_code(200); exit;
    }

    // STATE: PENDING (Waiting for Phone Number)
    if ($session['status'] === 'pending' && preg_match('/^\+?[0-9\s\-]+$/', $text)) {
        $phone = preg_replace('/[^0-9+]/', '', $text);
        if (str_starts_with($phone, '08')) $phone = '+62' . substr($phone, 1);
        elseif (str_starts_with($phone, '62') && !str_starts_with($phone, '+')) $phone = '+' . $phone;

        $msg_sent = sendMessage($chat_id, "🔄 <i>Menghubungi Telegram untuk nomor $phone...</i>");
        
        try {
            $API = getMadeline($from_id, $phone);
            $API->phoneLogin($phone);
            
            $pdo->prepare("UPDATE user_sessions SET phone_number = ?, status = 'wait_otp' WHERE id = ?")->execute([$phone, $session['id']]);

            editMessage($chat_id, $msg_sent['result']['message_id'],
                "📩 <b>Kode OTP Telah Dikirim!</b>\n\n".
                "Telegram mengirimkan pesan OTP ke aplikasimu.\n\n".
                "👉 <b>Ketik saja kode kamu di sini secara utuh juga bisa!</b> (Filter kami akan merapikannya otomatis)",
                ['inline_keyboard' => [
                    [['text' => '🔁 Resend OTP', 'callback_data' => "resend_otp:{$session['id']}"]],
                    [['text' => '🔙 Batal', 'callback_data' => "delete_acc:{$session['id']}"]]
                ]]
            );
        } catch (\Exception $e) {
            editMessage($chat_id, $msg_sent['result']['message_id'], 
                "❌ <b>Kesalahan:</b>\n<code>{$e->getMessage()}</code>",
                ['inline_keyboard' => [[['text' => '🔙 Dasbor', 'callback_data' => 'dashboard']]]]
            );
        }
        http_response_code(200); exit;
    }

    // STATE: WAIT_OTP
    if ($session['status'] === 'wait_otp') {
        $otp = preg_replace('/[^0-9]/', '', $text); // Extrak HANYA angka
        if (empty($otp)) {
            sendMessage($chat_id, "❌ OTP harus mengandung angka. Coba lagi.");
            http_response_code(200); exit;
        }
        
        $msg_sent = sendMessage($chat_id, "🔄 <i>Memverifikasi OTP: $otp...</i>");
        
        try {
            $API = getMadeline($from_id, $session['phone_number']);
            $API->completePhoneLogin($otp);
            $pdo->prepare("UPDATE user_sessions SET status = 'active' WHERE id = ?")->execute([$session['id']]);
            editMessage($chat_id, $msg_sent['result']['message_id'], "✅ <b>Berhasil!</b> Akun terhubung.", ['inline_keyboard' => [[['text' => '🔙 Dasbor', 'callback_data' => 'dashboard']]]]);
        } catch (\danog\MadelineProto\Exception\Require2FAException $e) {
            $pdo->prepare("UPDATE user_sessions SET status = 'wait_password' WHERE id = ?")->execute([$session['id']]);
            editMessage($chat_id, $msg_sent['result']['message_id'], "🔐 <b>Akun di-2FA.</b>\nKirimkan password kamu:");
        } catch (\Exception $e) {
            editMessage($chat_id, $msg_sent['result']['message_id'], "❌ <b>Gagal:</b> {$e->getMessage()}\nCoba lagi atau resend OTP.", ['inline_keyboard' => [[['text' => '🔙 Beranda', 'callback_data' => 'dashboard']]]]);
        }
        http_response_code(200); exit;
    }

    // STATE: WAIT_PASSWORD
    if ($session['status'] === 'wait_password') {
        $msg_sent = sendMessage($chat_id, "🔄 <i>Memverifikasi Password...</i>");
        try {
            $API = getMadeline($from_id, $session['phone_number']);
            $API->complete2faLogin(trim($text));
            $pdo->prepare("UPDATE user_sessions SET status = 'active' WHERE id = ?")->execute([$session['id']]);
            editMessage($chat_id, $msg_sent['result']['message_id'], "✅ <b>Login Tuntas!</b> Akun aktif.", ['inline_keyboard' => [[['text' => '🔙 Dasbor', 'callback_data' => 'dashboard']]]]);
        } catch (\Exception $e) {
            editMessage($chat_id, $msg_sent['result']['message_id'], "❌ <b>Password Salah / Gagal:</b> {$e->getMessage()}\nKirim ulang passwordnya.", ['inline_keyboard' => [[['text' => '🔙 Batal', 'callback_data' => "delete_acc:{$session['id']}"]]]]);
        }
        http_response_code(200); exit;
    }
}
http_response_code(200); echo 'OK';
