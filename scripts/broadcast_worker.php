<?php
/**
 * Background Broadcast Worker — MadelineProto v8
 * Usage: php broadcast_worker.php <campaign_id>
 */

set_time_limit(0);
ignore_user_abort(true);

chdir(dirname(__DIR__));
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

use danog\MadelineProto\API;
use danog\MadelineProto\Settings;
use danog\MadelineProto\Settings\AppInfo;

$campaignId = (int)($argv[1] ?? 0);
if (!$campaignId) {
    error_log('[BroadcastWorker] No campaign ID provided');
    exit(1);
}

$pdo = getDB();

// Get campaign
$stmt = $pdo->prepare("SELECT * FROM broadcast_campaigns WHERE id = ?");
$stmt->execute([$campaignId]);
$campaign = $stmt->fetch();

if (!$campaign || $campaign['status'] !== 'running') {
    error_log("[BroadcastWorker] Campaign {$campaignId} not found or not running");
    exit(1);
}

$settings = new Settings();
$settings->setAppInfo(
    (new AppInfo())
        ->setApiId((int) TG_API_ID)
        ->setApiHash(TG_API_HASH)
);

// Get all pending logs grouped by account_id
$pending = $pdo->prepare("
    SELECT l.*, a.phone AS account_phone, a.session_file
    FROM broadcast_logs l
    JOIN tg_accounts a ON a.id = l.account_id
    WHERE l.campaign_id = ? AND l.status = 'pending'
    ORDER BY l.account_id, l.id ASC
");
$pending->execute([$campaignId]);
$pendingLogs = $pending->fetchAll();

if (empty($pendingLogs)) {
    $pdo->prepare("UPDATE broadcast_campaigns SET status='done', finished_at=NOW() WHERE id=?")
        ->execute([$campaignId]);
    exit(0);
}

$sentCount = 0;
$failCount = 0;
$delaySec  = max(1, (int)($campaign['delay_seconds'] ?? 3));

// Group logs by account to reuse MadelineProto session per account
$grouped = [];
foreach ($pendingLogs as $log) {
    $grouped[$log['account_phone']][] = $log;
}

foreach ($grouped as $accountPhone => $logs) {
    // Check campaign is still running
    $checkStatus = $pdo->prepare("SELECT status FROM broadcast_campaigns WHERE id = ?");
    $checkStatus->execute([$campaignId]);
    if ($checkStatus->fetchColumn() !== 'running') break;

    $sessionPath = rtrim(SESSION_DIR, '/\\') . DIRECTORY_SEPARATOR . 'account_' . preg_replace('/[^0-9]/', '', $accountPhone) . '.madeline';

    if (!file_exists($sessionPath)) {
        // Mark all logs for this account as failed
        foreach ($logs as $log) {
            $pdo->prepare("UPDATE broadcast_logs SET status='failed', error_msg='Session tidak ditemukan', sent_at=NOW() WHERE id=?")
                ->execute([$log['id']]);
            $failCount++;
        }
        continue;
    }

    try {
        $MadelineProto = new API($sessionPath, $settings);
        $MadelineProto->async(true);

        $MadelineProto->loop(function () use ($MadelineProto, $logs, $campaign, $pdo, $campaignId, $delaySec, &$sentCount, &$failCount) {
            foreach ($logs as $log) {
                // Check paused/stopped
                $checkStmt = $pdo->prepare("SELECT status FROM broadcast_campaigns WHERE id = ?");
                $checkStmt->execute([$campaignId]);
                if ($checkStmt->fetchColumn() !== 'running') return;

                try {
                    if ($campaign['media_type'] === 'none' || empty($campaign['media_path'])) {
                        yield $MadelineProto->messages->sendMessage([
                            'peer'    => $log['recipient'],
                            'message' => $campaign['message_text'],
                            'parse_mode' => 'Markdown',
                        ]);
                    } elseif ($campaign['media_type'] === 'photo') {
                        yield $MadelineProto->sendPhoto(
                            peer:    $log['recipient'],
                            file:    $campaign['media_path'],
                            caption: $campaign['message_text'],
                        );
                    } elseif ($campaign['media_type'] === 'video') {
                        yield $MadelineProto->sendVideo(
                            peer:    $log['recipient'],
                            file:    $campaign['media_path'],
                            caption: $campaign['message_text'],
                        );
                    } else {
                        yield $MadelineProto->sendDocument(
                            peer:    $log['recipient'],
                            file:    $campaign['media_path'],
                            caption: $campaign['message_text'],
                        );
                    }

                    $pdo->prepare("UPDATE broadcast_logs SET status='sent', sent_at=NOW() WHERE id=?")
                        ->execute([$log['id']]);
                    $sentCount++;

                } catch (\danog\MadelineProto\RPCErrorException $e) {
                    $errMsg = $e->getMessage();
                    $status = 'failed';

                    if (str_contains($errMsg, 'PEER_FLOOD') || str_contains($errMsg, 'FLOOD_WAIT')) {
                        $status = 'blocked';
                        $pdo->prepare("UPDATE tg_accounts SET notes=CONCAT(IFNULL(notes,''),'[FLOOD] ') WHERE phone=?")
                            ->execute([$log['account_phone']]);
                        // Extract wait time from FLOOD_WAIT_X
                        preg_match('/FLOOD_WAIT_(\d+)/', $errMsg, $m);
                        $waitSec = (int)($m[1] ?? 30);
                        yield \Amp\delay($waitSec * 1000);
                    } elseif (str_contains($errMsg, 'USER_PRIVACY_RESTRICTED')) {
                        $status = 'blocked';
                    }

                    $pdo->prepare("UPDATE broadcast_logs SET status=?, error_msg=?, sent_at=NOW() WHERE id=?")
                        ->execute([$status, substr($errMsg, 0, 490), $log['id']]);
                    $failCount++;

                } catch (\Throwable $e) {
                    $pdo->prepare("UPDATE broadcast_logs SET status='failed', error_msg=?, sent_at=NOW() WHERE id=?")
                        ->execute([substr($e->getMessage(), 0, 490), $log['id']]);
                    $failCount++;
                }

                // Update progress
                $pdo->prepare("UPDATE broadcast_campaigns SET sent_count=?, failed_count=? WHERE id=?")
                    ->execute([$sentCount, $failCount, $campaignId]);
                $pdo->prepare("UPDATE tg_accounts SET last_used=NOW() WHERE phone=?")
                    ->execute([$log['account_phone']]);

                // Delay between messages
                yield \Amp\delay($delaySec * 1000);
            }
        });

    } catch (\Throwable $e) {
        // Account-level failure
        error_log("[BroadcastWorker] Account {$accountPhone} error: " . $e->getMessage());
        foreach ($logs as $log) {
            $pdo->prepare("UPDATE broadcast_logs SET status='failed', error_msg=?, sent_at=NOW() WHERE id=?")
                ->execute([substr($e->getMessage(), 0, 490), $log['id']]);
            $failCount++;
        }
    }
}

// Mark campaign done
$pdo->prepare("UPDATE broadcast_campaigns SET status='done', finished_at=NOW(), sent_count=?, failed_count=? WHERE id=?")
    ->execute([$sentCount, $failCount, $campaignId]);

error_log("[BroadcastWorker] Campaign {$campaignId} finished. Sent={$sentCount}, Failed={$failCount}");
