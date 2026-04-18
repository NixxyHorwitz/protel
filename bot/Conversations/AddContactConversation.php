<?php
/**
 * AddContactConversation — Alur tambah kontak manual
 */

namespace ProTel\Conversations;

use SergiX44\Nutgram\Conversations\Conversation;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\ForceReply;

class AddContactConversation extends Conversation
{
    public ?string $phone    = null;
    public ?string $username = null;
    public ?string $name     = null;
    public ?string $group    = 'Default';

    public function start(Nutgram $bot): void
    {
        $bot->sendMessage(
            "📋 *Tambah Kontak Baru*\n\n" .
            "Masukkan nomor HP kontak \\(format: `+628...`\\)\n" .
            "Atau ketik `-` jika ingin pakai username saja\\.",
            parse_mode: 'MarkdownV2',
            reply_markup: ForceReply::make(input_field_placeholder: '+628...')
        );
        $this->next('enterPhone');
    }

    public function enterPhone(Nutgram $bot): void
    {
        $text = trim($bot->message()?->text ?? '');
        if ($text === '/batal') { $bot->sendMessage('❌ Dibatalkan.'); $this->end(); return; }

        if ($text !== '-') {
            $this->phone = preg_replace('/[^0-9+]/', '', $text);
        }

        $bot->sendMessage(
            "Username Telegram \\(tanpa @\\)\\.\nKetik `-` jika tidak ada:",
            parse_mode: 'MarkdownV2',
            reply_markup: ForceReply::make(input_field_placeholder: 'username...')
        );
        $this->next('enterUsername');
    }

    public function enterUsername(Nutgram $bot): void
    {
        $text = trim($bot->message()?->text ?? '');
        if ($text !== '-') {
            $this->username = ltrim($text, '@');
        }

        if (!$this->phone && !$this->username) {
            $bot->sendMessage('❌ Harus ada nomor HP atau username. Coba lagi dengan /addcontact');
            $this->end(); return;
        }

        $bot->sendMessage(
            "Nama tampilan kontak:\n\\(Ketik `-` untuk lewati\\)",
            parse_mode: 'MarkdownV2',
            reply_markup: ForceReply::make(input_field_placeholder: 'Nama...')
        );
        $this->next('enterName');
    }

    public function enterName(Nutgram $bot): void
    {
        $text = trim($bot->message()?->text ?? '');
        if ($text !== '-') $this->name = $text;

        $bot->sendMessage(
            "Nama grup:\n\\(ketik nama grup atau `-` untuk Default\\)",
            parse_mode: 'MarkdownV2',
            reply_markup: ForceReply::make(input_field_placeholder: 'Default')
        );
        $this->next('enterGroup');
    }

    public function enterGroup(Nutgram $bot): void
    {
        $text = trim($bot->message()?->text ?? '');
        $this->group = ($text && $text !== '-') ? $text : 'Default';

        try {
            $pdo  = getDB();
            $stmt = $pdo->prepare("INSERT INTO broadcast_contacts (owner_tg_id, phone, username, display_name, group_name) VALUES (?,?,?,?,?)");
            $stmt->execute([$bot->userId(), $this->phone ?: null, $this->username ?: null, $this->name, $this->group]);

            $info = implode(', ', array_filter([$this->phone, $this->username ? '@'.$this->username : null, $this->name]));
            $bot->sendMessage(
                "✅ Kontak ditambahkan\\!\n`{$info}` → Grup: *{$this->group}*",
                parse_mode: 'MarkdownV2',
                reply_markup: \Keyboards::backTo('contacts', '📋 Ke Daftar Kontak')
            );
        } catch (\Throwable $e) {
            $bot->sendMessage("❌ Gagal menyimpan: `" . $e->getMessage() . "`", parse_mode: 'Markdown');
        }

        $this->end();
    }
}
