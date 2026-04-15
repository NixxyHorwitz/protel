<?php
/**
 * ProTel Bot — Main bot file
 *
 * Semua handler callback query & command terdaftar di sini.
 * Conversations di-include dari bot/Conversations/
 *
 * Untuk menjalankan: php polling.php
 */

declare(strict_types=1);

use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Configuration;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Psr16Cache;
use ProTel\Conversations\AddAccountConversation;
use ProTel\Conversations\BroadcastConversation;
use ProTel\Conversations\AddContactConversation;

// Bootstrap
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/bot/BotHelper.php';
require_once __DIR__ . '/bot/Keyboards.php';
require_once __DIR__ . '/bot/Conversations/AddAccountConversation.php';
require_once __DIR__ . '/bot/Conversations/BroadcastConversation.php';
require_once __DIR__ . '/bot/Conversations/AddContactConversation.php';

if (!defined('BOT_TOKEN') || empty(BOT_TOKEN)) {
    die("❌ BOT_TOKEN belum di-define sebelum memuat bot.php!\n");
}

// Persistent cache terisolasi per-bot token (agar state conversation tidak tabrakan antar bot)
$_botCacheNs = 'protel_' . substr(md5(BOT_TOKEN), 0, 8);
$cachePool   = new FilesystemAdapter($_botCacheNs, 3600, STORAGE_DIR . 'cache/');
$cache       = new Psr16Cache($cachePool);

$bot = Nutgram::factory(BOT_TOKEN, new Configuration(
    cache: $cache,
    botName: 'ProTel',
));

// ── Admin Middleware ─────────────────────────────────────────────────────────
$bot->middleware(function (Nutgram $bot, $next) {
    $adminIds = ADMIN_IDS;
    if (empty($adminIds)) {
        // Jika ADMIN_IDS kosong, izinkan semua (setup mode)
        return $next($bot);
    }
    $userId = $bot->userId();
    if (!in_array($userId, $adminIds, true)) {
        $bot->sendMessage("⛔ Kamu tidak memiliki akses ke bot ini.");
        return;
    }
    return $next($bot);
});

// ═══════════════════════════════════════════════════════════════════════════
//  COMMANDS
// ═══════════════════════════════════════════════════════════════════════════

$bot->onCommand('start', function (Nutgram $bot) {
    $name = $bot->user()?->first_name ?? 'User';
    $bot->sendMessage(
        "Halo *{$name}\\!* 👋\n\n" . mainMenuText($bot->userId()),
        parse_mode: 'MarkdownV2',
        reply_markup: Keyboards::mainMenu()
    );
});

$bot->onCommand('addaccount',  AddAccountConversation::class);
$bot->onCommand('broadcast',   BroadcastConversation::class);
$bot->onCommand('addcontact',  AddContactConversation::class);

$bot->onCommand('help', function (Nutgram $bot) {
    $bot->sendMessage(
        "📡 *ProTel Bot — Bantuan*\n\n" .
        "*/start* — Menu utama\n" .
        "*/addaccount* — Login akun Telegram baru\n" .
        "*/broadcast* — Buat broadcast baru\n" .
        "*/addcontact* — Tambah kontak manual\n" .
        "*/help* — Tampilkan bantuan ini\n\n" .
        "_Atau pakai tombol interaktif di menu utama\\._",
        parse_mode: 'MarkdownV2'
    );
});

// ═══════════════════════════════════════════════════════════════════════════
//  CALLBACK QUERY: NAVIGATION
// ═══════════════════════════════════════════════════════════════════════════

// Main Menu
$bot->onCallbackQueryData('menu', function (Nutgram $bot) {
    $bot->editMessageText(
        mainMenuText($bot->userId()),
        parse_mode: 'MarkdownV2',
        reply_markup: Keyboards::mainMenu()
    );
    $bot->answerCallbackQuery();
});

// ── ACCOUNTS ────────────────────────────────────────────────────────────────

$bot->onCallbackQueryData('accounts', function (Nutgram $bot) {
    $accounts = getAccounts($bot->userId());
    $bot->editMessageText(
        accountsText($accounts),
        parse_mode: 'Markdown',
        reply_markup: Keyboards::accounts($accounts)
    );
    $bot->answerCallbackQuery();
});

$bot->onCallbackQueryData('add_account', function (Nutgram $bot) {
    $bot->answerCallbackQuery();
    AddAccountConversation::begin($bot);
});

// Hapus akun: acc_del:{id}
$bot->onCallbackQueryData('acc_del:.*', function (Nutgram $bot) {
    $id  = (int)explode(':', $bot->callbackQuery()->data)[1];
    $pdo = getDB();

    $stmt = $pdo->prepare("SELECT phone, session_file FROM tg_accounts WHERE id = ? AND owner_tg_id = ?");
    $stmt->execute([$id, $bot->userId()]);
    $acc = $stmt->fetch();

    if ($acc) {
        $sf = $acc['session_file'] ?: (rtrim(SESSION_DIR, '/\\') . DIRECTORY_SEPARATOR . 'account_' . preg_replace('/[^0-9]/', '', $acc['phone']) . '.madeline');
        foreach (glob($sf . '*') ?: [] as $f) @unlink($f);
        $pdo->prepare("DELETE FROM tg_accounts WHERE id = ?")->execute([$id]);

        $accounts = getAccounts($bot->userId());
        $bot->editMessageText(
            "🗑 Akun `{$acc['phone']}` dihapus\\.\n\n" . accountsText($accounts),
            parse_mode: 'MarkdownV2',
            reply_markup: Keyboards::accounts($accounts)
        );
    }
    $bot->answerCallbackQuery(text: '✅ Akun dihapus');
});

// No-op (label button akun)
$bot->onCallbackQueryData('acc_noop', fn(Nutgram $bot) => $bot->answerCallbackQuery());

// ── CONTACTS ────────────────────────────────────────────────────────────────

$bot->onCallbackQueryData('contacts', function (Nutgram $bot) {
    $groups = getContactGroups($bot->userId());
    $bot->editMessageText(
        contactsText($groups),
        parse_mode: 'Markdown',
        reply_markup: Keyboards::contacts($groups)
    );
    $bot->answerCallbackQuery();
});

$bot->onCallbackQueryData('ctc_add', function (Nutgram $bot) {
    $bot->answerCallbackQuery();
    AddContactConversation::begin($bot);
});

// Import CSV — minta user kirim file
$bot->onCallbackQueryData('ctc_import', function (Nutgram $bot) {
    $bot->answerCallbackQuery();
    $bot->sendMessage(
        "📥 *Import Kontak via CSV*\n\n" .
        "Format file CSV:\n```\nphone,nama,username\n+628111,Budi Santoso,budi_id\n+628222,Sari,,\n```\n\n" .
        "Kirim file `.csv` sekarang\\.\nKetik grup tujuan sebelum mengirim file \\(balas pesan ini dengan nama grup\\)\\.",
        parse_mode: 'MarkdownV2'
    );
});

// Lihat kontak per grup: ctc_group:<base64 group>
$bot->onCallbackQueryData('ctc_group:.*', function (Nutgram $bot) {
    $encoded = substr($bot->callbackQuery()->data, strlen('ctc_group:'));
    $group   = base64_decode($encoded);
    $pdo     = getDB();
    $stmt    = $pdo->prepare("SELECT * FROM broadcast_contacts WHERE group_name = ? AND is_active=1 AND owner_tg_id=? ORDER BY added_at DESC LIMIT 20");
    $stmt->execute([$group, $bot->userId()]);
    $rows    = $stmt->fetchAll();
    $total   = $pdo->prepare("SELECT COUNT(*) FROM broadcast_contacts WHERE group_name = ? AND is_active=1 AND owner_tg_id=?");
    $total->execute([$group, $bot->userId()]);
    $count = (int)$total->fetchColumn();

    $text = "📁 *Grup: {$group}* — {$count} kontak\n━━━━━━━━━━━━━━━━━━\n";
    foreach (array_slice($rows, 0, 15) as $r) {
        $id   = $r['display_name'] ?: ($r['phone'] ?: ('@' . $r['username']));
        $text .= "• {$id}";
        if ($r['phone'] && $r['username']) $text .= " / @{$r['username']}";
        $text .= "\n";
    }
    if ($count > 15) $text .= "_... dan " . ($count - 15) . " lainnya_\n";

    $bot->editMessageText($text, parse_mode: 'Markdown', reply_markup: Keyboards::contactGroupDetail($group));
    $bot->answerCallbackQuery();
});

// Konfirmasi hapus grup: ctc_del_confirm:<base64>
$bot->onCallbackQueryData('ctc_del_confirm:.*', function (Nutgram $bot) {
    $group = base64_decode(substr($bot->callbackQuery()->data, strlen('ctc_del_confirm:')));
    $pdo   = getDB();
    $stmt  = $pdo->prepare("SELECT COUNT(*) FROM broadcast_contacts WHERE group_name = ? AND owner_tg_id = ?");
    $stmt->execute([$group, $bot->userId()]);
    $cnt   = (int)$stmt->fetchColumn();

    $bot->editMessageText(
        "⚠️ *Hapus Grup: {$group}?*\n\n`{$cnt}` kontak akan dihapus permanen\\!",
        parse_mode: 'MarkdownV2',
        reply_markup: Keyboards::confirmDeleteGroup($group)
    );
    $bot->answerCallbackQuery();
});

// Eksekusi hapus grup: ctc_del_do:<base64>
$bot->onCallbackQueryData('ctc_del_do:.*', function (Nutgram $bot) {
    $group = base64_decode(substr($bot->callbackQuery()->data, strlen('ctc_del_do:')));
    $pdo   = getDB();
    $stmt  = $pdo->prepare("DELETE FROM broadcast_contacts WHERE group_name = ? AND owner_tg_id = ?");
    $stmt->execute([$group, $bot->userId()]);
    $deleted = $stmt->rowCount();

    $groups = getContactGroups($bot->userId());
    $bot->editMessageText(
        "✅ Grup *{$group}* dihapus \\({$deleted} kontak\\)\\.\n\n" . contactsText($groups),
        parse_mode: 'MarkdownV2',
        reply_markup: Keyboards::contacts($groups)
    );
    $bot->answerCallbackQuery(text: "✅ {$deleted} kontak dihapus");
});

// ── BROADCAST ────────────────────────────────────────────────────────────────

$bot->onCallbackQueryData('broadcast_start', function (Nutgram $bot) {
    $bot->answerCallbackQuery();
    BroadcastConversation::begin($bot);
});

// Keyboard buttons yang dihandle dalam BroadcastConversation
// (bc_group:*, bc_toggle_acc:*, bc_acc_all, bc_acc_none, bc_acc_done, bc_confirm_yes)
// Semua ditangani otomatis via conversation step

// ── HISTORY ─────────────────────────────────────────────────────────────────

$bot->onCallbackQueryData('history', function (Nutgram $bot) {
    $campaigns = getCampaigns($bot->userId());
    $bot->editMessageText(
        historyText($campaigns),
        parse_mode: 'Markdown',
        reply_markup: Keyboards::history($campaigns)
    );
    $bot->answerCallbackQuery();
});

// Detail campaign: camp_detail:{id}
$bot->onCallbackQueryData('camp_detail:.*', function (Nutgram $bot) {
    $id   = (int)explode(':', $bot->callbackQuery()->data)[1];
    $pdo  = getDB();
    $stmt = $pdo->prepare("SELECT * FROM broadcast_campaigns WHERE id = ? AND owner_tg_id = ?");
    $stmt->execute([$id, $bot->userId()]);
    $c  = $stmt->fetch();
    if (!$c) { $bot->answerCallbackQuery(text: 'Campaign tidak ditemukan'); return; }

    $pct   = $c['total_target'] > 0 ? round(($c['sent_count'] / $c['total_target']) * 100) : 0;
    $bar   = progressBar((int)$pct);
    $icon  = match($c['status']) { 'running'=>'▶️','done'=>'✅','paused'=>'⏸','failed'=>'❌',default=>'📝' };

    // Ambil sample log terakhir
    $logs  = $pdo->prepare("SELECT l.recipient, l.status, l.error_msg FROM broadcast_logs l WHERE l.campaign_id=? ORDER BY l.id DESC LIMIT 5");
    $logs->execute([$id]);
    $logRows = $logs->fetchAll();

    $text  = "{$icon} *{$c['name']}*\n";
    $text .= "━━━━━━━━━━━━━━━━━━\n";
    $text .= "Status: `{$c['status']}`\n";
    $text .= "Progress: {$bar} `{$pct}%`\n";
    $text .= "✓ Terkirim: `{$c['sent_count']}` / `{$c['total_target']}`\n";
    $text .= "✗ Gagal: `{$c['failed_count']}`\n";
    $text .= "Delay: `{$c['delay_seconds']}` detik\n";
    $text .= "Dibuat: `" . date('d/m/Y H:i', strtotime($c['created_at'])) . "`\n";
    if ($c['finished_at']) $text .= "Selesai: `" . date('d/m/Y H:i', strtotime($c['finished_at'])) . "`\n";

    if (!empty($logRows)) {
        $text .= "\n*Log Terakhir:*\n";
        foreach ($logRows as $l) {
            $st = match($l['status']) { 'sent'=>'✓','failed'=>'✗','blocked'=>'🚫',default=>'⏳' };
            $text .= "{$st} `{$l['recipient']}`";
            if ($l['error_msg']) $text .= " — " . mb_strimwidth($l['error_msg'], 0, 40, '...');
            $text .= "\n";
        }
    }

    $bot->editMessageText($text, parse_mode: 'Markdown', reply_markup: Keyboards::campaignDetail($c));
    $bot->answerCallbackQuery();
});

// Pause/Resume: camp_pause:{id}
$bot->onCallbackQueryData('camp_pause:.*', function (Nutgram $bot) {
    $id  = (int)explode(':', $bot->callbackQuery()->data)[1];
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT status FROM broadcast_campaigns WHERE id = ? AND owner_tg_id = ?");
    $stmt->execute([$id, $bot->userId()]);
    $current = $stmt->fetchColumn();
    if (!$current) return;
    $new = ($current === 'running') ? 'paused' : 'running';
    $pdo->prepare("UPDATE broadcast_campaigns SET status=? WHERE id=? AND owner_tg_id=?")->execute([$new, $id, $bot->userId()]);
    $label = $new === 'paused' ? '⏸ Campaign dijeda' : '▶️ Campaign dilanjutkan';
    $bot->answerCallbackQuery(text: $label, show_alert: true);

    // Refresh detail
    $stmt2 = $pdo->prepare("SELECT * FROM broadcast_campaigns WHERE id = ?");
    $stmt2->execute([$id]);
    $c = $stmt2->fetch();
    $pct = $c['total_target'] > 0 ? round(($c['sent_count'] / $c['total_target']) * 100) : 0;
    $bar = progressBar((int)$pct);
    $icon = match($c['status']) { 'running'=>'▶️','done'=>'✅','paused'=>'⏸','failed'=>'❌',default=>'📝' };

    $text = "{$icon} *{$c['name']}*\nStatus: `{$c['status']}`\nProgress: {$bar} `{$pct}%`\n✓ `{$c['sent_count']}` ✗ `{$c['failed_count']}`";
    $bot->editMessageText($text, parse_mode: 'Markdown', reply_markup: Keyboards::campaignDetail($c));
});

// ═══════════════════════════════════════════════════════════════════════════
//  DOCUMENT HANDLER — Import CSV
// ═══════════════════════════════════════════════════════════════════════════

$bot->onDocument(function (Nutgram $bot) {
    $doc = $bot->message()->document;
    if (!$doc || !str_ends_with(strtolower($doc->file_name ?? ''), '.csv')) {
        return; // bukan CSV, ignore
    }

    $bot->sendChatAction('typing');

    // Download file dari Telegram via cURL (lebih reliable di Windows)
    $fileInfo = $bot->getFile($doc->file_id);
    $fileUrl  = "https://api.telegram.org/file/bot" . BOT_TOKEN . "/" . $fileInfo->file_path;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $fileUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $csvData = curl_exec($ch);
    curl_close($ch);

    if (!$csvData) {
        $bot->sendMessage("❌ Gagal mengunduh file.");
        return;
    }

    $group   = $bot->message()->caption ?: ('Import ' . date('d/m/Y H:i'));
    $pdo     = getDB();
    $stmt    = $pdo->prepare("INSERT IGNORE INTO broadcast_contacts (owner_tg_id, phone, username, display_name, group_name) VALUES (?,?,?,?,?)");
    $lines   = explode("\n", trim($csvData));
    $count   = 0;
    $skipped = 0;
    $first   = true;

    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line)) continue;
        $row = str_getcsv($line);
        if ($first) { $first = false; continue; } // skip header row

        $phone = preg_replace('/[^0-9+]/', '', $row[0] ?? '');
        $name  = trim($row[1] ?? '');
        $uname = ltrim(trim($row[2] ?? ''), '@');

        if ($phone || $uname) {
            $stmt->execute([$bot->userId(), $phone ?: null, $uname ?: null, $name, $group]);
            $count++;
        } else {
            $skipped++;
        }
    }

    $groups = getContactGroups($bot->userId());
    $bot->sendMessage(
        "✅ *Import Selesai\\!*\n\n" .
        "Ditambahkan: `{$count}` kontak\n" .
        "Dilewati: `{$skipped}` baris\n" .
        "Grup: *{$group}*",
        parse_mode: 'MarkdownV2',
        reply_markup: Keyboards::contacts($groups)
    );
});

// ═══════════════════════════════════════════════════════════════════════════
//  FALLBACK
// ═══════════════════════════════════════════════════════════════════════════

$bot->fallback(function (Nutgram $bot) {
    // Hanya tampilkan menu jika bukan sedang dalam conversation
    $name = $bot->user()?->first_name ?? 'User';
    $bot->sendMessage(
        "Halo *{$name}\\!* Gunakan menu di bawah atau ketik /help\\.",
        parse_mode: 'MarkdownV2',
        reply_markup: Keyboards::mainMenu()
    );
});

return $bot;
