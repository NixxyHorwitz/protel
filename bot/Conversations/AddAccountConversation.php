<?php
/**
 * AddAccountConversation — Alur login akun Telegram via OTP
 *
 * Flow:
 *   start → [user ketik nomor HP] → enterPhone
 *        → [CLI request_otp] → [user ketik OTP] → enterOTP
 *        → success ATAU need_2fa → [user ketik password] → enter2FA → done
 */

namespace ProTel\Conversations;

use SergiX44\Nutgram\Conversations\Conversation;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\ForceReply;

class AddAccountConversation extends Conversation
{
    public ?string $phone = null;

    // ── Step 1: Minta nomor HP ────────────────────────────
    public function __invoke(Nutgram $bot): void
    {
        $this->start($bot);
    }

    public function start(Nutgram $bot): void
    {
        $bot->sendMessage(
            "📱 *Login Akun Telegram*\n\n" .
            "Masukkan nomor HP yang terdaftar di Telegram\n" .
            "Format internasional, contoh: `+628123456789`",
            parse_mode: 'Markdown',
            reply_markup: ForceReply::make(input_field_placeholder: '+628...')
        );
        $this->next('enterPhone');
    }

    // ── Step 2: Terima nomor, request OTP ────────────────
    public function enterPhone(Nutgram $bot): void
    {
        $input = trim($bot->message()?->text ?? '');

        if ($input === '/batal' || $input === 'batal') {
            $bot->sendMessage('❌ Dibatalkan.'); $this->end(); return;
        }

        $phone = preg_replace('/[^0-9+]/', '', $input);
        if (strlen($phone) < 8) {
            $bot->sendMessage("❌ Nomor tidak valid. Coba lagi atau ketik /batal:");
            return; // stay on same step
        }

        $this->phone = $phone;
        $bot->sendChatAction('typing');

        $msg = $bot->sendMessage("⏳ Mengirim kode ke *{$phone}*\\.\\.\\.", parse_mode: 'MarkdownV2');

        $result = runScript(__DIR__ . '/../../scripts/request_otp.php', [$phone]);

        if ($result['success'] ?? false) {
            $bot->editMessageText(
                "✅ Kode OTP dikirim ke Telegram *{$phone}*\!\n\n" .
                "Masukkan 5 digit kode yang kamu terima:",
                chat_id: $msg->chat->id,
                message_id: $msg->message_id,
                parse_mode: 'MarkdownV2',
                reply_markup: ForceReply::make(input_field_placeholder: '12345')
            );
            $this->next('enterOTP');
        } else {
            $err = $result['message'] ?? 'Unknown error';
            $bot->editMessageText(
                "❌ Gagal mengirim OTP:\n`{$err}`\n\nCoba lagi dengan /addaccount",
                chat_id: $msg->chat->id,
                message_id: $msg->message_id,
                parse_mode: 'Markdown'
            );
            $this->end();
        }
    }

    // ── Step 3: Verifikasi OTP ────────────────────────────
    public function enterOTP(Nutgram $bot): void
    {
        $code = trim($bot->message()?->text ?? '');

        if ($code === '/batal') { $bot->sendMessage('❌ Dibatalkan.'); $this->end(); return; }
        if (!preg_match('/^\d{5,6}$/', $code)) {
            $bot->sendMessage("❌ Kode harus 5-6 digit angka. Coba lagi:");
            return;
        }

        $bot->sendChatAction('typing');
        $result = runScript(__DIR__ . '/../../scripts/verify_otp.php', [$this->phone, $code]);

        if ($result['success'] ?? false) {
            saveAccountToDB($bot->userId(), $this->phone, $result);
            $name = trim(($result['first_name'] ?? '') . ' ' . ($result['last_name'] ?? ''));
            $bot->sendMessage(
                "🎉 *Berhasil\\!*\n\n" .
                "Akun *{$name}*" . ($result['username'] ? " \\(@{$result['username']}\\)" : '') . "\n" .
                "Nomor: `{$this->phone}`\n" .
                "Sudah ditambahkan dan siap digunakan untuk broadcast\\!",
                parse_mode: 'MarkdownV2',
                reply_markup: \Keyboards::backTo('accounts', '📱 Ke Daftar Akun')
            );
            $this->end();

        } elseif ($result['need_2fa'] ?? false) {
            $bot->sendMessage(
                "🔐 *Verifikasi 2 Langkah*\n\n" .
                "Akun ini menggunakan password 2FA\\.\n" .
                "Masukkan password Telegram kamu:",
                parse_mode: 'MarkdownV2',
                reply_markup: ForceReply::make(input_field_placeholder: 'Password...')
            );
            $this->next('enter2FA');

        } else {
            $err = $result['message'] ?? 'Kode salah';
            $bot->sendMessage("❌ *Gagal:* `{$err}`\n\nCoba lagi dengan /addaccount", parse_mode: 'Markdown');
            $this->end();
        }
    }

    // ── Step 3b: Verifikasi 2FA password ─────────────────
    public function enter2FA(Nutgram $bot): void
    {
        $password = $bot->message()?->text ?? '';

        if (!$password) { $bot->sendMessage('❌ Password tidak boleh kosong.'); return; }

        $bot->sendChatAction('typing');
        $result = runScript(__DIR__ . '/../../scripts/verify_2fa.php', [$this->phone, $password]);

        if ($result['success'] ?? false) {
            saveAccountToDB($bot->userId(), $this->phone, $result);
            $name = trim(($result['first_name'] ?? '') . ' ' . ($result['last_name'] ?? ''));
            $bot->sendMessage(
                "🎉 *Berhasil\\!* Akun *{$name}* ditambahkan ke sistem\\!",
                parse_mode: 'MarkdownV2',
                reply_markup: \Keyboards::backTo('accounts', '📱 Ke Daftar Akun')
            );
        } else {
            $err = $result['message'] ?? 'Password salah';
            $bot->sendMessage("❌ `{$err}`\n\nCoba lagi dengan /addaccount", parse_mode: 'Markdown');
        }
        $this->end();
    }
}
