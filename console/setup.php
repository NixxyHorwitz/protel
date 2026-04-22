<?php
require_once __DIR__ . '/../core/auth.php';
check_auth();
require_once __DIR__ . '/../core/layout.php';

$msg = $msg_type = '';
$settings = $pdo->query("SELECT * FROM bot_settings WHERE id = 1")->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_token'])) {
        // Validate token format (digits:alphanum 35+)
        $token   = trim($_POST['bot_token'] ?? '');
        $url     = filter_input(INPUT_POST, 'webhook_url', FILTER_VALIDATE_URL) ?: '';
        $welcome = trim($_POST['welcome_message'] ?? '');

        if (!preg_match('/^\d{6,12}:[A-Za-z0-9_-]{30,}$/', $token) && !empty($token)) {
            $msg = "Invalid bot token format."; $msg_type = 'danger';
        } elseif (!empty($url) && !str_starts_with($url, 'https://')) {
            $msg = "Webhook URL must use HTTPS."; $msg_type = 'danger';
        } else {
            $pdo->prepare("UPDATE bot_settings SET bot_token=?, webhook_url=?, welcome_message=? WHERE id=1")
                ->execute([$token, $url, $welcome]);
            write_log('SYSTEM', "Bot settings updated");
            $msg = "Settings saved successfully."; $msg_type = 'success';
            $settings = $pdo->query("SELECT * FROM bot_settings WHERE id=1")->fetch();
        }
    }

    if (isset($_POST['sync_webhook'])) {
        $token = $settings['bot_token'];
        $url   = $settings['webhook_url'];
        if (empty($token) || empty($url)) {
            $msg = "Token and Webhook URL must be set first."; $msg_type = 'warning';
        } else {
            $api = "https://api.telegram.org/bot{$token}/setWebhook";
            $ch = curl_init($api);
            curl_setopt_array($ch, [CURLOPT_POST=>1, CURLOPT_POSTFIELDS=>json_encode(['url'=>$url]),
                CURLOPT_HTTPHEADER=>['Content-Type: application/json'], CURLOPT_RETURNTRANSFER=>1, CURLOPT_TIMEOUT=>10]);
            $res = json_decode(curl_exec($ch) ?: '{}', true);
            curl_close($ch);
            if ($res['ok'] ?? false) {
                $pdo->query("UPDATE bot_settings SET status='active' WHERE id=1");
                $settings['status'] = 'active';
                write_log('WEBHOOK', "Webhook synced to $url");
                $msg = "Webhook synced successfully!"; $msg_type = 'success';
            } else {
                $msg = "Telegram Error: " . ($res['description'] ?? 'Unknown error'); $msg_type = 'danger';
                write_log('WEBHOOK_ERROR', "Sync failed: {$msg}");
            }
        }
    }
}

$webhook_info = [];
if (!empty($settings['bot_token'])) {
    $ch = curl_init("https://api.telegram.org/bot{$settings['bot_token']}/getWebhookInfo");
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>1, CURLOPT_TIMEOUT=>5]);
    $r = json_decode(curl_exec($ch) ?: '{}', true);
    curl_close($ch);
    $webhook_info = $r['result'] ?? [];
}

load_header('Bot Setup');
?>

<?php if ($msg): ?>
<div class="alert alert-<?= $msg_type ?> mb-3"><i class="fas fa-circle-info"></i> <?= h($msg) ?></div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header"><i class="fas fa-robot me-2" style="color:var(--accent)"></i>Bot Configuration</div>
            <div class="modal-body" style="padding:1rem">
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">Bot Token</label>
                        <input type="text" name="bot_token" class="form-control" style="font-family:monospace"
                            value="<?= h($settings['bot_token'] ?? '') ?>" placeholder="123456789:ABCDEF…" autocomplete="off">
                        <div style="font-size:.75rem;color:var(--text-muted);margin-top:.3rem">Get from @BotFather on Telegram</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Webhook URL</label>
                        <input type="url" name="webhook_url" class="form-control"
                            value="<?= h($settings['webhook_url'] ?? BASE_URL.'/bot_webhook.php') ?>" placeholder="https://…">
                        <div style="font-size:.75rem;color:var(--text-muted);margin-top:.3rem">Must be HTTPS</div>
                    </div>
                    <div class="mb-4">
                        <label class="form-label">Welcome Message</label>
                        <textarea name="welcome_message" class="form-control" rows="5"
                            placeholder="👋 Halo {name}, selamat datang!"><?= h($settings['welcome_message'] ?? '') ?></textarea>
                        <div style="font-size:.75rem;color:var(--text-muted);margin-top:.3rem">
                            Use <code>{name}</code> for user name. Supports Telegram HTML tags: &lt;b&gt;, &lt;i&gt;, &lt;code&gt;
                        </div>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" name="update_token" class="btn btn-primary btn-sm"><i class="fas fa-save"></i> Save</button>
                        <button type="submit" name="sync_webhook" class="btn btn-success btn-sm"><i class="fas fa-rotate"></i> Sync Webhook</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card">
            <div class="card-header"><i class="fas fa-wifi me-2" style="color:var(--accent)"></i>Webhook Status</div>
            <div class="modal-body" style="padding:1rem">
                <?php $active = ($settings['status'] ?? '') === 'active'; ?>
                <div class="d-flex align-items-center gap-2 mb-3">
                    <span style="width:8px;height:8px;border-radius:50%;background:<?= $active ? '#3fb950' : 'var(--red)' ?>;display:inline-block"></span>
                    <span style="font-size:.82rem;font-weight:600;color:<?= $active ? '#3fb950' : 'var(--red)' ?>">
                        <?= $active ? 'Active & Listening' : 'Inactive' ?>
                    </span>
                </div>
                <?php if (!empty($webhook_info)): ?>
                <div style="font-size:.78rem">
                    <div class="mb-2">
                        <div style="color:var(--text-muted);font-size:.7rem;text-transform:uppercase;font-weight:600">URL</div>
                        <code class="mono" style="font-size:.72rem;word-break:break-all"><?= h($webhook_info['url'] ?? '—') ?></code>
                    </div>
                    <div class="mb-2">
                        <div style="color:var(--text-muted);font-size:.7rem;text-transform:uppercase;font-weight:600">Pending Updates</div>
                        <span><?= (int)($webhook_info['pending_update_count'] ?? 0) ?></span>
                    </div>
                    <?php if (!empty($webhook_info['last_error_message'])): ?>
                    <div>
                        <div style="color:var(--red);font-size:.7rem;text-transform:uppercase;font-weight:600">Last Error</div>
                        <span style="color:#ff7b72;font-size:.75rem"><?= h($webhook_info['last_error_message']) ?></span>
                    </div>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                <p style="color:var(--text-muted);font-size:.8rem">No webhook info. Sync first.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php load_footer(); ?>
