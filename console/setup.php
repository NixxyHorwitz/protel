<?php
require_once __DIR__ . '/../core/auth.php';
check_auth();
require_once __DIR__ . '/../core/layout.php';

$msg = '';
$stmt = $pdo->query("SELECT * FROM bot_settings WHERE id = 1");
$settings = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_token'])) {
        $token   = trim($_POST['bot_token']);
        $url     = trim($_POST['webhook_url']);
        $welcome = trim($_POST['welcome_message']);
        
        $pdo->prepare("UPDATE bot_settings SET bot_token = ?, webhook_url = ?, welcome_message = ? WHERE id = 1")
            ->execute([$token, $url, $welcome]);
        write_log('SYSTEM', "Bot settings updated manually");
        $msg = "Settings saved successfully.";
        
        $stmt = $pdo->query("SELECT * FROM bot_settings WHERE id = 1");
        $settings = $stmt->fetch();
    }
    
    if (isset($_POST['sync_webhook'])) {
        $token = $settings['bot_token'];
        $url = $settings['webhook_url'];
        
        if (empty($token) || empty($url)) {
            $msg = "Token and Webhook URL must be set before syncing.";
        } else {
            $api = "https://api.telegram.org/bot{$token}/setWebhook?url={$url}";
            $response = @file_get_contents($api);
            if ($response) {
                $res = json_decode($response, true);
                if ($res['ok']) {
                    $pdo->query("UPDATE bot_settings SET status = 'active' WHERE id = 1");
                    write_log('WEBHOOK', "Webhook synced successfully to $url");
                    $msg = "Webhook synced successfully!";
                    $settings['status'] = 'active';
                } else {
                    $err = $res['description'] ?? 'Unknown error';
                    write_log('WEBHOOK_ERROR', "Sync failed: $err");
                    $msg = "Telegram Error: $err";
                }
            } else {
                write_log('WEBHOOK_ERROR', "Failed to connect to Telegram API");
                $msg = "Failed to connect to Telegram API. Check your token.";
            }
        }
    }
}

$webhook_info = [];
if (!empty($settings['bot_token'])) {
    $api = "https://api.telegram.org/bot{$settings['bot_token']}/getWebhookInfo";
    $response = @file_get_contents($api);
    if ($response) {
        $res = json_decode($response, true);
        if (isset($res['result'])) {
            $webhook_info = $res['result'];
        }
    }
}

load_header('Bot Setup');
?>
<div class="row">
    <div class="col-md-7">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header border-bottom d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold"><i class="fa-solid fa-robot text-primary me-2"></i> Telegram Bot Configuration</h6>
            </div>
            <div class="card-body">
                <?php if($msg): ?>
                    <div class="alert alert-info py-2 small fw-medium"><?= htmlspecialchars($msg) ?></div>
                <?php endif; ?>
                
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Bot Token (from @BotFather)</label>
                        <input type="text" name="bot_token" class="form-control font-monospace" value="<?= htmlspecialchars($settings['bot_token'] ?? '') ?>" placeholder="123456789:ABCDEF...">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Webhook URL Endpoint</label>
                        <input type="url" name="webhook_url" class="form-control" value="<?= htmlspecialchars($settings['webhook_url'] ?? BASE_URL.'/bot_webhook.php') ?>">
                        <div class="form-text small">Absolute URL where Bot Webhook is processed. Ensure HTTPS.</div>
                    </div>
                    <div class="mb-4">
                        <label class="form-label small fw-bold text-muted">Welcome Message</label>
                        <textarea name="welcome_message" class="form-control" rows="5" placeholder="Contoh: 👋 Halo {name}, selamat datang!"><?= htmlspecialchars($settings['welcome_message'] ?? '') ?></textarea>
                        <div class="form-text small">Gunakan <code>{name}</code> untuk menyisipkan nama pengguna. Mendukung tag HTML Telegram: &lt;b&gt;, &lt;i&gt;, &lt;code&gt;.</div>
                    </div>
                    
                    <div class="d-flex gap-2">
                        <button type="submit" name="update_token" class="btn btn-primary"><i class="fa-solid fa-save me-1"></i> Save Settings</button>
                        <button type="submit" name="sync_webhook" class="btn btn-success"><i class="fa-solid fa-rotate me-1"></i> Sync Webhook</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="col-md-5">
        <div class="card border-0 shadow-sm">
            <div class="card-header border-bottom">
                <h6 class="mb-0 fw-bold"><i class="fa-solid fa-wifi text-primary me-2"></i> Webhook Status</h6>
            </div>
            <div class="card-body">
                <?php if ($settings['status'] === 'active'): ?>
                    <div class="d-flex align-items-center mb-3">
                        <div class="spinner-grow text-success spinner-grow-sm me-2" role="status"></div>
                        <h6 class="mb-0 fw-bold text-success">Active & Listening</h6>
                    </div>
                <?php else: ?>
                    <div class="d-flex align-items-center mb-3">
                        <div class="bg-danger rounded-circle me-2" style="width: 10px; height: 10px;"></div>
                        <h6 class="mb-0 fw-bold text-danger">Inactive</h6>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($webhook_info)): ?>
                <div class="bg-light border rounded p-3 small font-monospace" style="word-break: break-all;">
                    <div class="mb-1 text-muted fw-bold">Current URL:</div>
                    <div class="mb-3 text-primary"><?= htmlspecialchars($webhook_info['url'] ?? 'None') ?></div>
                    
                    <div class="mb-1 text-muted fw-bold">Pending Updates:</div>
                    <div class="mb-3"><?= htmlspecialchars($webhook_info['pending_update_count'] ?? 0) ?></div>
                    
                    <?php if(isset($webhook_info['last_error_message'])): ?>
                    <div class="mb-1 text-danger fw-bold">Last Error:</div>
                    <div class="text-danger"><?= htmlspecialchars($webhook_info['last_error_message']) ?></div>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                    <p class="text-muted small">No webhook info found. Sync first.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php load_footer(); ?>
