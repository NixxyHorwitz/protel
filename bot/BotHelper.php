<?php
/**
 * BotHelper — shared utility functions untuk ProTel Bot
 */

use danog\MadelineProto\API;

/**
 * Jalankan PHP CLI script (MadelineProto) dan return JSON result
 */
function runScript(string $scriptPath, array $args = []): array
{
    $phpBin = PHP_BINARY ?: 'php';
    $escaped = implode(' ', array_map('escapeshellarg', $args));
    $output  = shell_exec("\"{$phpBin}\" " . escapeshellarg($scriptPath) . " {$escaped} 2>&1");

    if ($output === null) {
        return ['success' => false, 'message' => 'shell_exec disabled di php.ini'];
    }

    // Extract baris JSON terakhir (ignore warning MadelineProto di atas)
    foreach (array_reverse(array_filter(array_map('trim', explode("\n", $output)))) as $line) {
        if (str_starts_with($line, '{') && str_ends_with($line, '}')) {
            $r = json_decode($line, true);
            if (json_last_error() === JSON_ERROR_NONE) return $r;
        }
    }

    return ['success' => false, 'message' => 'Output tidak valid: ' . substr(strip_tags($output), 0, 300)];
}

/**
 * Simpan akun ke database setelah login berhasil
 */
function saveAccountToDB(int $ownerId, string $phone, array $data): bool
{
    try {
        $pdo   = getDB();
        $safe  = preg_replace('/[^0-9]/', '', $phone);
        $sFile = rtrim(SESSION_DIR, '/\\') . DIRECTORY_SEPARATOR . "account_{$safe}.madeline";
        $pdo->prepare("
            INSERT INTO tg_accounts (owner_tg_id, phone, first_name, last_name, username, tg_user_id, session_file, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'active')
            ON DUPLICATE KEY UPDATE
                first_name=VALUES(first_name), last_name=VALUES(last_name),
                username=VALUES(username), tg_user_id=VALUES(tg_user_id), status='active'
        ")->execute([
            $ownerId,
            $phone,
            $data['first_name'] ?? '',
            $data['last_name']  ?? '',
            $data['username']   ?? '',
            $data['tg_user_id'] ?? 0,
            $sFile,
        ]);
        return true;
    } catch (\Throwable $e) {
        return false;
    }
}

// ── DB helpers ────────────────────────────────────────

function getAccounts(int $ownerId): array
{
    $stmt = getDB()->prepare("SELECT * FROM tg_accounts WHERE owner_tg_id = ? ORDER BY status='active' DESC, added_at DESC");
    $stmt->execute([$ownerId]);
    return $stmt->fetchAll(\PDO::FETCH_ASSOC);
}

function getContactGroups(int $ownerId): array
{
    $stmt = getDB()->prepare("SELECT group_name, COUNT(*) AS cnt FROM broadcast_contacts WHERE owner_tg_id = ? AND is_active=1 GROUP BY group_name ORDER BY group_name");
    $stmt->execute([$ownerId]);
    return $stmt->fetchAll(\PDO::FETCH_ASSOC);
}

function getCampaigns(int $ownerId, int $limit = 15): array
{
    $stmt = getDB()->prepare("SELECT * FROM broadcast_campaigns WHERE owner_tg_id = ? ORDER BY created_at DESC LIMIT " . (int)$limit);
    $stmt->execute([$ownerId]);
    return $stmt->fetchAll(\PDO::FETCH_ASSOC);
}

function getStats(int $ownerId): array
{
    $pdo = getDB();
    $s1 = $pdo->prepare("SELECT COUNT(*) FROM tg_accounts WHERE owner_tg_id = ? AND status='active'"); $s1->execute([$ownerId]);
    $s2 = $pdo->prepare("SELECT COUNT(*) FROM broadcast_contacts WHERE owner_tg_id = ? AND is_active=1"); $s2->execute([$ownerId]);
    $s3 = $pdo->prepare("SELECT COUNT(*) FROM broadcast_campaigns WHERE owner_tg_id = ?"); $s3->execute([$ownerId]);
    $s4 = $pdo->prepare("SELECT COALESCE(SUM(sent_count),0) FROM broadcast_campaigns WHERE owner_tg_id = ?"); $s4->execute([$ownerId]);

    return [
        'accounts'  => (int)$s1->fetchColumn(),
        'contacts'  => (int)$s2->fetchColumn(),
        'campaigns' => (int)$s3->fetchColumn(),
        'sent'      => (int)$s4->fetchColumn(),
    ];
}

// ── Text builders (Markdown) ─────────────────────────

function mainMenuText(int $ownerId): string
{
    $s = getStats($ownerId);
    return
        "📡 *ProTel Broadcast System*\n" .
        "━━━━━━━━━━━━━━━━━━\n" .
        "👤 Akun Aktif  : `{$s['accounts']}`\n" .
        "📋 Total Kontak : `{$s['contacts']}`\n" .
        "📢 Campaign    : `{$s['campaigns']}`\n" .
        "✉️ Terkirim    : `{$s['sent']}`\n" .
        "━━━━━━━━━━━━━━━━━━\n" .
        "_Pilih menu di bawah:_";
}

function accountsText(array $accounts): string
{
    $total   = count($accounts);
    $active  = count(array_filter($accounts, fn($a) => $a['status'] === 'active'));
    $text    = "📱 *Akun Telegram*\nAktif: `{$active}` dari `{$total}` akun\n━━━━━━━━━━━━━━━━━━\n";

    if (empty($accounts)) {
        $text .= "_Belum ada akun. Tambahkan akun pertama kamu!_";
    } else {
        foreach ($accounts as $i => $a) {
            $no    = $i + 1;
            $name  = trim(($a['first_name'] ?? '') . ' ' . ($a['last_name'] ?? ''));
            $uname = $a['username'] ? " (@{$a['username']})" : '';
            $st    = match($a['status']) {
                'active'       => '✅',
                'banned'       => '🚫',
                'disconnected' => '⚠️',
                default        => '❓'
            };
            $last = $a['last_used'] ? date('d/m H:i', strtotime($a['last_used'])) : '—';
            $text .= "{$st} *{$no}.* {$name}{$uname}\n`{$a['phone']}` · Terpakai: {$last}\n\n";
        }
    }
    return $text;
}

function contactsText(array $groups): string
{
    $total = array_sum(array_column($groups, 'cnt'));
    $text  = "📋 *Daftar Kontak*\nTotal: `{$total}` kontak dalam " . count($groups) . " grup\n━━━━━━━━━━━━━━━━━━\n";
    foreach ($groups as $g) {
        $text .= "📁 *{$g['group_name']}* — `{$g['cnt']}` kontak\n";
    }
    if (empty($groups)) $text .= "_Belum ada kontak._";
    return $text;
}

function historyText(array $campaigns): string
{
    $text = "📊 *Riwayat Campaign*\n━━━━━━━━━━━━━━━━━━\n";
    if (empty($campaigns)) {
        return $text . "_Belum ada campaign._";
    }
    foreach ($campaigns as $c) {
        $pct  = $c['total_target'] > 0 ? round(($c['sent_count'] / $c['total_target']) * 100) : 0;
        $bar  = progressBar($pct);
        $icon = match($c['status']) {
            'running' => '▶️', 'done' => '✅', 'paused' => '⏸', 'failed' => '❌', default => '📝'
        };
        $text .= "{$icon} *" . mb_strimwidth($c['name'], 0, 30, '...') . "*\n";
        $text .= "{$bar} `{$pct}%` ({$c['sent_count']}/{$c['total_target']})\n";
        $text .= "✓`{$c['sent_count']}` ✗`{$c['failed_count']}` · " . date('d/m H:i', strtotime($c['created_at'])) . "\n\n";
    }
    return $text;
}

function progressBar(int $pct): string
{
    $filled = (int)round($pct / 10);
    return '█' . str_repeat('▓', $filled) . str_repeat('░', 10 - $filled) . '▒';
}
