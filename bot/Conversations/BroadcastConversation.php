<?php
/**
 * BroadcastConversation — Alur kirim broadcast
 *
 * Flow:
 *   start → [user kirim teks/foto] → gotMessage
 *         → [pilih grup via button] → gotGroup
 *         → [pilih akun via buttons] → (toggle, done)
 *         → [ketik delay] → gotDelay
 *         → [konfirmasi button] → execute
 */

namespace ProTel\Conversations;

use SergiX44\Nutgram\Conversations\Conversation;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\ForceReply;

class BroadcastConversation extends Conversation
{
    public ?string $messageText   = null;
    public ?array  $mediaInfo     = null;  // ['type'=>'photo','file_id'=>'...']
    public ?string $targetGroup   = null;
    public int     $targetCount   = 0;
    public array   $selectedAccs  = [];
    public int     $delaySec      = 5;

    // ── Step 1: Minta pesan ──────────────────────────────
    public function __invoke(Nutgram $bot, ...$parameters): mixed
    {
        $this->start($bot);
        return null;
    }

    public function start(Nutgram $bot): void
    {
        $accounts = getAccounts($bot->userId());
        $active   = array_filter($accounts, fn($a) => $a['status'] === 'active');

        if (empty($active)) {
            $bot->sendMessage(
                "❌ Tidak ada akun aktif\\!\n\nTambahkan akun Telegram dulu sebelum broadcast\\.",
                parse_mode: 'MarkdownV2',
                reply_markup: \Keyboards::backTo('add_account', '➕ Tambah Akun')
            );
            $this->end(); return;
        }

        // Pre-select semua akun aktif
        $this->selectedAccs = array_column(array_values($active), 'id');

        $bot->sendMessage(
            "📢 *Buat Broadcast Baru*\n\n" .
            "Ketik pesan broadcast kamu\\.\n" .
            "_Bisa pakai markdown Telegram: \\*tebal\\*, \\_miring\\_, dll_\n\n" .
            "_Atau kirim foto/video dengan caption sebagai pesan\\._\n\n" .
            "Ketik /batal untuk membatalkan\\.",
            parse_mode: 'MarkdownV2',
            reply_markup: ForceReply::make(input_field_placeholder: 'Tulis pesan...')
        );
        $this->next('gotMessage');
    }

    // ── Step 2: Terima pesan / media ─────────────────────
    public function gotMessage(Nutgram $bot): void
    {
        $msg = $bot->message();
        if (!$msg) { $this->end(); return; }

        $text = $msg->text ?? $msg->caption ?? '';
        if ($text === '/batal') { $bot->sendMessage('❌ Dibatalkan.'); $this->end(); return; }
        if (empty($text)) { $bot->sendMessage('❌ Pesan tidak boleh kosong.'); return; }

        $this->messageText = $text;

        // Cek apakah ada media
        if ($msg->photo) {
            $photo = end($msg->photo); // ambil resolusi terbesar
            $this->mediaInfo = ['type' => 'photo', 'file_id' => $photo->file_id];
        } elseif ($msg->video) {
            $this->mediaInfo = ['type' => 'video', 'file_id' => $msg->video->file_id];
        } elseif ($msg->document) {
            $this->mediaInfo = ['type' => 'document', 'file_id' => $msg->document->file_id];
        }

        // Tampilkan pilihan grup
        $groups = getContactGroups($bot->userId());
        if (empty($groups)) {
            $bot->sendMessage(
                "❌ Tidak ada kontak tersimpan\\! Tambahkan kontak dulu\\.",
                parse_mode: 'MarkdownV2',
                reply_markup: \Keyboards::backTo('ctc_add', '➕ Tambah Kontak')
            );
            $this->end(); return;
        }

        $bot->sendMessage(
            "👥 *Pilih Target Penerima:*",
            parse_mode: 'Markdown',
            reply_markup: \Keyboards::selectGroup($groups)
        );
        $this->next('gotGroup');
    }

    // ── Step 3: Terima pilihan grup (via callback) ────────
    public function gotGroup(Nutgram $bot): void
    {
        $data = $bot->callbackQuery()?->data ?? '';
        $bot->answerCallbackQuery();

        if (!str_starts_with($data, 'bc_group:')) {
            $bot->sendMessage('❌ Pilih grup dengan klik tombol di atas.');
            return;
        }

        $encoded = substr($data, strlen('bc_group:'));
        $this->targetGroup = ($encoded === '__all__') ? '' : base64_decode($encoded);

        // Hitung kontak
        $pdo = getDB();
        if ($this->targetGroup === '') {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM broadcast_contacts WHERE owner_tg_id=? AND is_active=1");
            $stmt->execute([$bot->userId()]);
            $this->targetCount = (int)$stmt->fetchColumn();
        } else {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM broadcast_contacts WHERE group_name = ? AND owner_tg_id=? AND is_active=1");
            $stmt->execute([$this->targetGroup, $bot->userId()]);
            $this->targetCount = (int)$stmt->fetchColumn();
        }

        $groupLabel = $this->targetGroup ?: 'Semua Kontak';

        // Tampilkan pilihan akun
        $accounts = array_filter(getAccounts($bot->userId()), fn($a) => $a['status'] === 'active');
        $bot->editMessageText(
            "👤 *Pilih Akun Pengirim:*\n_(centang yang ingin digunakan)_\n\nTarget: *{$groupLabel}* — `{$this->targetCount}` kontak",
            chat_id: $bot->callbackQuery()->message->chat->id,
            message_id: $bot->callbackQuery()->message->message_id,
            parse_mode: 'Markdown',
            reply_markup: \Keyboards::selectAccounts(array_values($accounts), $this->selectedAccs)
        );
        $this->next('selectAccounts');
    }

    // ── Step 4: Toggle pilihan akun ───────────────────────
    public function selectAccounts(Nutgram $bot): void
    {
        $cbq  = $bot->callbackQuery();
        $data = $cbq?->data ?? '';
        $bot->answerCallbackQuery();

        if (str_starts_with($data, 'bc_toggle_acc:')) {
            $id = (int)substr($data, strlen('bc_toggle_acc:'));
            if (in_array($id, $this->selectedAccs)) {
                $this->selectedAccs = array_values(array_diff($this->selectedAccs, [$id]));
            } else {
                $this->selectedAccs[] = $id;
            }
            // Refresh keyboard
            $accounts = array_filter(getAccounts($bot->userId()), fn($a) => $a['status'] === 'active');
            $bot->editMessageReplyMarkup(
                chat_id: $cbq->message->chat->id,
                message_id: $cbq->message->message_id,
                reply_markup: \Keyboards::selectAccounts(array_values($accounts), $this->selectedAccs)
            );
            return; // tetap di step ini
        }

        if ($data === 'bc_acc_all') {
            $accounts = array_filter(getAccounts($bot->userId()), fn($a) => $a['status'] === 'active');
            $this->selectedAccs = array_column(array_values($accounts), 'id');
            $bot->editMessageReplyMarkup(
                chat_id: $cbq->message->chat->id,
                message_id: $cbq->message->message_id,
                reply_markup: \Keyboards::selectAccounts(array_values($accounts), $this->selectedAccs)
            );
            return;
        }

        if ($data === 'bc_acc_none') {
            $this->selectedAccs = [];
            $accounts = array_filter(getAccounts($bot->userId()), fn($a) => $a['status'] === 'active');
            $bot->editMessageReplyMarkup(
                chat_id: $cbq->message->chat->id,
                message_id: $cbq->message->message_id,
                reply_markup: \Keyboards::selectAccounts(array_values($accounts), $this->selectedAccs)
            );
            return;
        }

        if ($data === 'bc_acc_done') {
            if (empty($this->selectedAccs)) {
                $bot->answerCallbackQuery(text: '⚠️ Pilih minimal 1 akun!', show_alert: true);
                return;
            }
            // Minta delay
            $bot->editMessageText(
                "⏱ *Delay antar pesan (detik):*\n\n" .
                "Disarankan minimal `3` detik untuk menghindari flood limit\\.\n" .
                "Ketik angkanya \\(contoh: `5`\\):",
                chat_id: $cbq->message->chat->id,
                message_id: $cbq->message->message_id,
                parse_mode: 'MarkdownV2',
                reply_markup: ForceReply::make(input_field_placeholder: '3')
            );
            $this->next('gotDelay');
        }

        if ($data === 'menu') { $this->end(); }
    }

    // ── Step 5: Terima delay ─────────────────────────────
    public function gotDelay(Nutgram $bot): void
    {
        $text = trim($bot->message()?->text ?? '');
        if ($text === '/batal') { $bot->sendMessage('❌ Dibatalkan.'); $this->end(); return; }

        $delay = (int)$text;
        if ($delay < 1) { $bot->sendMessage('❌ Minimal 1 detik. Coba lagi:'); return; }
        $this->delaySec = $delay;

        // Hitung estimasi
        $accCount = count($this->selectedAccs);
        $totalSec = $this->targetCount * $this->delaySec;
        $estMin   = ceil($totalSec / 60);
        $groupLabel = $this->targetGroup ?: 'Semua Kontak';
        $msgPreview = mb_strimwidth($this->messageText, 0, 80, '…');

        $bot->sendMessage(
            "📋 *Konfirmasi Broadcast*\n" .
            "━━━━━━━━━━━━━━━━━━\n" .
            "📝 *Pesan:* `{$msgPreview}`\n" .
            ($this->mediaInfo ? "📎 Media: {$this->mediaInfo['type']}\n" : "") .
            "👥 *Target:* {$groupLabel} — `{$this->targetCount}` kontak\n" .
            "👤 *Akun:* `{$accCount}` pengirim \\(round\\-robin\\)\n" .
            "⏱ *Delay:* `{$this->delaySec}` detik\n" .
            "⏳ *Estimasi:* ~`{$estMin}` menit\n" .
            "━━━━━━━━━━━━━━━━━━\n",
            parse_mode: 'MarkdownV2',
            reply_markup: \Keyboards::confirmBroadcast()
        );
        $this->next('confirmExecute');
    }

    // ── Step 6: Eksekusi broadcast ────────────────────────
    public function confirmExecute(Nutgram $bot): void
    {
        $data = $bot->callbackQuery()?->data ?? '';
        $bot->answerCallbackQuery();

        if ($data !== 'bc_confirm_yes') {
            $bot->sendMessage('❌ Broadcast dibatalkan.');
            $this->end(); return;
        }

        $bot->sendChatAction('typing');

        // Buat campaign
        $pdo      = getDB();
        $campName = 'Bot Broadcast ' . date('d/m/Y H:i');
        $pdo->prepare("INSERT INTO broadcast_campaigns (owner_tg_id, name, message_text, media_type, delay_seconds) VALUES (?, ?, ?, 'none', ?)")
            ->execute([$bot->userId(), $campName, $this->messageText, $this->delaySec]);
        $campId = (int)$pdo->lastInsertId();

        // Ambil kontak
        if ($this->targetGroup === '') {
            $stmt = $pdo->prepare("SELECT * FROM broadcast_contacts WHERE owner_tg_id=? AND is_active=1");
            $stmt->execute([$bot->userId()]);
            $contacts = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } else {
            $stmt = $pdo->prepare("SELECT * FROM broadcast_contacts WHERE group_name=? AND owner_tg_id=? AND is_active=1");
            $stmt->execute([$this->targetGroup, $bot->userId()]);
            $contacts = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        }

        // Ambil akun
        $in   = implode(',', array_map('intval', $this->selectedAccs));
        $stmt = $pdo->prepare("SELECT * FROM tg_accounts WHERE id IN ({$in}) AND owner_tg_id=? AND status='active'");
        $stmt->execute([$bot->userId()]);
        $accs = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $pdo->prepare("UPDATE broadcast_campaigns SET status='running', started_at=NOW(), total_target=? WHERE id=? AND owner_tg_id=?")
            ->execute([count($contacts), $campId, $bot->userId()]);

        // Insert log entries (round-robin)
        $logStmt  = $pdo->prepare("INSERT INTO broadcast_logs (campaign_id, account_id, contact_id, recipient, status) VALUES (?,?,?,?,'pending')");
        $accCount = count($accs);
        foreach ($contacts as $i => $c) {
            $acc       = $accs[$i % $accCount];
            $recipient = !empty($c['username']) ? '@' . ltrim($c['username'], '@') : $c['phone'];
            $logStmt->execute([$campId, $acc['id'], $c['id'], $recipient]);
        }

        // Fire background worker
        $phpBin = PHP_BINARY ?: 'php';
        $worker = realpath(__DIR__ . '/../../scripts/broadcast_worker.php');
        if (PHP_OS_FAMILY === 'Windows') {
            pclose(popen("start /B \"{$phpBin}\" \"{$worker}\" {$campId}", 'r'));
        } else {
            exec("\"{$phpBin}\" \"{$worker}\" {$campId} > /dev/null 2>&1 &");
        }

        $bot->editMessageText(
            "🚀 *Broadcast Dimulai\\!*\n\n" .
            "Campaign: `{$campName}`\n" .
            "Mengirim ke `" . count($contacts) . "` kontak via `{$accCount}` akun\\.\n\n" .
            "_Pantau progress di menu Riwayat_ 📊",
            chat_id: $bot->callbackQuery()->message->chat->id,
            message_id: $bot->callbackQuery()->message->message_id,
            parse_mode: 'MarkdownV2',
            reply_markup: \Keyboards::backTo('history', '📊 Lihat Riwayat')
        );

        $this->end();
    }
}
