<?php
/**
 * Keyboards — Semua inline keyboard builder untuk ProTel Bot
 */

use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

class Keyboards
{
    // ── MAIN MENU ────────────────────────────────────────
    public static function mainMenu(): InlineKeyboardMarkup
    {
        return InlineKeyboardMarkup::make()
            ->addRow(
                InlineKeyboardButton::make('📱 Akun Telegram',   callback_data: 'accounts'),
                InlineKeyboardButton::make('📋 Kontak',          callback_data: 'contacts'),
            )
            ->addRow(
                InlineKeyboardButton::make('📢 Broadcast',       callback_data: 'broadcast_start'),
                InlineKeyboardButton::make('📊 Riwayat',         callback_data: 'history'),
            )
            ->addRow(
                InlineKeyboardButton::make('🔄 Refresh Stats',   callback_data: 'menu'),
            );
    }

    // ── ACCOUNTS ─────────────────────────────────────────
    public static function accounts(array $accounts): InlineKeyboardMarkup
    {
        $kb = InlineKeyboardMarkup::make();

        foreach ($accounts as $acc) {
            $name  = trim(($acc['first_name'] ?? '') . ' ' . ($acc['last_name'] ?? ''));
            $st    = $acc['status'] === 'active' ? '✅' : '⚠️';
            $kb->addRow(
                InlineKeyboardButton::make("{$st} {$name} ({$acc['phone']})", callback_data: 'acc_noop'),
                InlineKeyboardButton::make('🗑',                               callback_data: "acc_del:{$acc['id']}"),
            );
        }

        $kb->addRow(InlineKeyboardButton::make('➕ Login Akun Baru', callback_data: 'add_account'))
           ->addRow(InlineKeyboardButton::make('🏠 Menu Utama',      callback_data: 'menu'));

        return $kb;
    }

    // ── CONTACTS ─────────────────────────────────────────
    public static function contacts(array $groups): InlineKeyboardMarkup
    {
        $kb = InlineKeyboardMarkup::make();

        foreach ($groups as $g) {
            $kb->addRow(
                InlineKeyboardButton::make("📁 {$g['group_name']} ({$g['cnt']})", callback_data: "ctc_group:" . base64_encode($g['group_name'])),
                InlineKeyboardButton::make('🗑 Hapus Grup',                         callback_data: "ctc_del_confirm:" . base64_encode($g['group_name'])),
            );
        }

        $kb->addRow(
            InlineKeyboardButton::make('➕ Tambah Kontak', callback_data: 'ctc_add'),
            InlineKeyboardButton::make('📥 Import CSV',   callback_data: 'ctc_import'),
        )->addRow(InlineKeyboardButton::make('🏠 Menu Utama', callback_data: 'menu'));

        return $kb;
    }

    // ── CONTACT GROUP DETAIL ──────────────────────────────
    public static function contactGroupDetail(string $group): InlineKeyboardMarkup
    {
        return InlineKeyboardMarkup::make()
            ->addRow(InlineKeyboardButton::make('🗑 Hapus Grup Ini', callback_data: 'ctc_del_confirm:' . base64_encode($group)))
            ->addRow(InlineKeyboardButton::make('← Kembali ke Kontak',  callback_data: 'contacts'));
    }

    // ── CONFIRM DELETE GROUP ─────────────────────────────
    public static function confirmDeleteGroup(string $group): InlineKeyboardMarkup
    {
        return InlineKeyboardMarkup::make()
            ->addRow(
                InlineKeyboardButton::make('✅ Ya, Hapus!', callback_data: 'ctc_del_do:' . base64_encode($group)),
                InlineKeyboardButton::make('❌ Batal',      callback_data: 'contacts'),
            );
    }

    // ── BROADCAST: SELECT GROUP ───────────────────────────
    public static function selectGroup(array $groups): InlineKeyboardMarkup
    {
        $kb = InlineKeyboardMarkup::make();
        $total = array_sum(array_column($groups, 'cnt'));

        foreach ($groups as $g) {
            $kb->addRow(InlineKeyboardButton::make(
                "📁 {$g['group_name']} — {$g['cnt']} kontak",
                callback_data: 'bc_group:' . base64_encode($g['group_name'])
            ));
        }

        $kb->addRow(InlineKeyboardButton::make("🌐 Semua Kontak ({$total})", callback_data: 'bc_group:__all__'))
           ->addRow(InlineKeyboardButton::make('❌ Batal', callback_data: 'menu'));

        return $kb;
    }

    // ── BROADCAST: SELECT ACCOUNTS ───────────────────────
    public static function selectAccounts(array $accounts, array $selected = []): InlineKeyboardMarkup
    {
        $kb = InlineKeyboardMarkup::make();
        foreach ($accounts as $acc) {
            $isSelected = in_array($acc['id'], $selected);
            $check  = $isSelected ? '✅' : '☑️';
            $name   = trim(($acc['first_name'] ?? '') . ' ' . ($acc['last_name'] ?? ''));
            $kb->addRow(InlineKeyboardButton::make(
                "{$check} {$name} ({$acc['phone']})",
                callback_data: 'bc_toggle_acc:' . $acc['id']
            ));
        }

        $kb->addRow(
            InlineKeyboardButton::make('✅ Semua',    callback_data: 'bc_acc_all'),
            InlineKeyboardButton::make('☑️ None',     callback_data: 'bc_acc_none'),
        )->addRow(InlineKeyboardButton::make('▶️ Lanjut →', callback_data: 'bc_acc_done'))
         ->addRow(InlineKeyboardButton::make('❌ Batal',    callback_data: 'menu'));

        return $kb;
    }

    // ── BROADCAST: CONFIRM ────────────────────────────────
    public static function confirmBroadcast(): InlineKeyboardMarkup
    {
        return InlineKeyboardMarkup::make()
            ->addRow(
                InlineKeyboardButton::make('🚀 Kirim Sekarang!', callback_data: 'bc_confirm_yes'),
                InlineKeyboardButton::make('❌ Batal',            callback_data: 'menu'),
            );
    }

    // ── HISTORY ───────────────────────────────────────────
    public static function history(array $campaigns): InlineKeyboardMarkup
    {
        $kb = InlineKeyboardMarkup::make();

        foreach ($campaigns as $c) {
            $icon = match($c['status']) {
                'running' => '▶️', 'done' => '✅', 'paused' => '⏸', 'failed' => '❌', default => '📝'
            };
            $name = mb_strimwidth($c['name'], 0, 25, '...');
            $kb->addRow(
                InlineKeyboardButton::make("{$icon} {$name}", callback_data: "camp_detail:{$c['id']}"),
            );
        }

        $kb->addRow(
            InlineKeyboardButton::make('🔄 Refresh',   callback_data: 'history'),
            InlineKeyboardButton::make('🏠 Menu',      callback_data: 'menu'),
        );

        return $kb;
    }

    // ── CAMPAIGN DETAIL ───────────────────────────────────
    public static function campaignDetail(array $campaign): InlineKeyboardMarkup
    {
        $kb = InlineKeyboardMarkup::make();

        if ($campaign['status'] === 'running') {
            $kb->addRow(InlineKeyboardButton::make('⏸ Pause', callback_data: "camp_pause:{$campaign['id']}"));
        }
        if ($campaign['status'] === 'paused') {
            $kb->addRow(InlineKeyboardButton::make('▶️ Resume', callback_data: "camp_pause:{$campaign['id']}"));
        }

        $kb->addRow(InlineKeyboardButton::make('← Kembali',  callback_data: 'history'));

        return $kb;
    }

    // ── BACK BUTTON ───────────────────────────────────────
    public static function backTo(string $callbackData, string $label = '← Kembali'): InlineKeyboardMarkup
    {
        return InlineKeyboardMarkup::make()
            ->addRow(InlineKeyboardButton::make($label, callback_data: $callbackData));
    }

    // ── CANCEL ────────────────────────────────────────────
    public static function cancel(string $callbackData = 'menu'): InlineKeyboardMarkup
    {
        return InlineKeyboardMarkup::make()
            ->addRow(InlineKeyboardButton::make('❌ Batalkan', callback_data: $callbackData));
    }
}
