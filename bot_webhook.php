<?php
require_once __DIR__ . '/core/database.php';

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
    write_log('WEBHOOK_RAW', $input);
}

// ─── Fetch Bot Settings (token + welcome message) ─────────────────────────────
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

    // Cek status user
    $session = $pdo->prepare("SELECT * FROM user_sessions WHERE telegram_id = ?");
    $session->execute([$from_id]);
    $session = $session->fetch();

    switch ($cb_data) {

        case 'menu_connect':
            if ($session && $session['status'] === 'active') {
                answerCallback($cb_id, '✅ Nomor kamu sudah terhubung!', true);
                break;
            }
            if ($session && $session['status'] === 'banned') {
                answerCallback($cb_id, '🚫 Akun kamu telah dibanned.', true);
                break;
            }
            answerCallback($cb_id);
            sendMessage($chat_id,
                "📱 <b>Hubungkan Nomor Telegram</b>\n\n".
                "Kirim nomor HP kamu dengan format internasional:\n".
                "<code>+628xxxxxxxxxx</code>\n\n".
                "⚠️ Pastikan nomor tersebut terdaftar di Telegram.",
                ['inline_keyboard' => [[['text' => '🔙 Kembali', 'callback_data' => 'menu_back']]]]
            );
            // Set user state to awaiting phone
            $pdo->prepare("INSERT INTO user_sessions (telegram_id, phone_number, status) VALUES (?, '', 'pending') ON DUPLICATE KEY UPDATE status = IF(status='banned','banned','pending')")->execute([$from_id]);
            break;

        case 'menu_status':
            answerCallback($cb_id);
            if (!$session) {
                $status_text = "❌ <b>Belum terdaftar.</b>\n\nKamu belum menghubungkan nomor apapun.";
            } else {
                $icon = match($session['status']) {
                    'active'  => '✅',
                    'pending' => '⏳',
                    'banned'  => '🚫',
                    default   => '❓',
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
                "❓ <b>Bantuan</b>\n\n".
                "Bot ini digunakan untuk menghubungkan nomor Telegram kamu ke sistem ProTel.\n\n".
                "<b>Cara penggunaan:</b>\n".
                "1. Klik <b>Connect My Number</b>\n".
                "2. Kirim nomor HP format internasional\n".
                "3. Masukkan kode OTP yang dikirim Telegram\n".
                "4. Selesai! Status kamu akan menjadi <b>Active</b>\n\n".
                "Hubungi admin jika ada masalah.",
                ['inline_keyboard' => [[['text' => '🔙 Kembali', 'callback_data' => 'menu_back']]]]
            );
            break;

        case 'menu_contact':
            answerCallback($cb_id);
            sendMessage($chat_id,
                "📞 <b>Kontak Admin</b>\n\n".
                "Untuk bantuan lebih lanjut, hubungi:\n".
                "➡️ @admin\n\n".
                "_Jam layanan: 09.00 – 21.00 WIB_",
                ['inline_keyboard' => [[['text' => '🔙 Kembali', 'callback_data' => 'menu_back']]]]
            );
            break;

        case 'menu_back':
            answerCallback($cb_id);
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

    // Fetch current session
    $session = $pdo->prepare("SELECT * FROM user_sessions WHERE telegram_id = ?");
    $session->execute([$from_id]);
    $session = $session->fetch();

    // ── /start ──
    if ($text === '/start') {
        write_log('BOT', "User $from_id sent /start");
        $name = $msg['from']['first_name'] ?? 'Pengguna';

        // Personalize welcome: replace {name} placeholder if set
        $personalized = str_replace('{name}', htmlspecialchars($name), $welcome_msg);

        sendMessage($chat_id, $personalized, mainMenuKeyboard());
        http_response_code(200);
        exit('OK');
    }

    // ── /menu ──
    if ($text === '/menu') {
        sendMessage($chat_id, $welcome_msg, mainMenuKeyboard());
        http_response_code(200);
        exit('OK');
    }

    // ── Phone Number Input (user in pending state) ──
    if ($session && $session['status'] === 'pending' && preg_match('/^\+?[0-9]{7,15}$/', str_replace([' ', '-'], '', $text))) {
        $phone = preg_replace('/[^0-9+]/', '', $text);
        // Normalize to +62 format if starts with 08
        if (str_starts_with($phone, '08')) {
            $phone = '+62' . substr($phone, 1);
        } elseif (str_starts_with($phone, '62') && !str_starts_with($phone, '+')) {
            $phone = '+' . $phone;
        }

        $pdo->prepare("UPDATE user_sessions SET phone_number = ?, status = 'pending' WHERE telegram_id = ?")
            ->execute([$phone, $from_id]);

        write_log('BOT', "User $from_id submitted phone: $phone");

        sendMessage($chat_id,
            "⏳ <b>Nomor Diterima!</b>\n\n".
            "Nomor: <code>$phone</code>\n\n".
            "Permintaanmu sedang diproses oleh admin. ".
            "Kamu akan mendapat notifikasi saat akun aktif.",
            ['inline_keyboard' => [[['text' => '📊 Cek Status', 'callback_data' => 'menu_status']]]]
        );
        http_response_code(200);
        exit('OK');
    }

    // ── Banned user ──
    if ($session && $session['status'] === 'banned') {
        sendMessage($chat_id, "🚫 <b>Akses Ditolak.</b>\n\nAkun Telegram kamu telah dibanned dari sistem.");
        http_response_code(200);
        exit('OK');
    }

    // ── Default: show menu ──
    sendMessage($chat_id, "Ketik /start atau pilih menu di bawah.", mainMenuKeyboard());
}

http_response_code(200);
echo 'OK';
