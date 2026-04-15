<?php
/**
 * ProTel — MadelineProto CLI Script: Complete 2FA
 * Usage: php verify_2fa.php <phone> <password>
 * Output: JSON ke stdout
 */

chdir(dirname(__DIR__));
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/app.php';

use danog\MadelineProto\API;
use danog\MadelineProto\Settings;
use danog\MadelineProto\Settings\AppInfo;

$phone    = $argv[1] ?? '';
$password = $argv[2] ?? '';

if (empty($phone) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Phone and password required']);
    exit(1);
}

$sessionPath = rtrim(SESSION_DIR, '/\\') . DIRECTORY_SEPARATOR . 'account_' . preg_replace('/[^0-9]/', '', $phone) . '.madeline';

$settings = new Settings();
$settings->setAppInfo(
    (new AppInfo())
        ->setApiId((int) TG_API_ID)
        ->setApiHash(TG_API_HASH)
);

$result = ['success' => false, 'message' => 'Unknown error'];

try {
    $MadelineProto = new API($sessionPath, $settings);
    $MadelineProto->async(true);

    $MadelineProto->loop(function () use ($MadelineProto, $password, &$result) {
        try {
            yield $MadelineProto->complete2faLogin($password);
            $me = yield $MadelineProto->getSelf();
            $result = [
                'success'    => true,
                'first_name' => $me['first_name'] ?? '',
                'last_name'  => $me['last_name']  ?? '',
                'username'   => $me['username']   ?? '',
                'tg_user_id' => $me['id']         ?? 0,
            ];
        } catch (\Throwable $e) {
            $result = ['success' => false, 'message' => $e->getMessage()];
        }
    });
} catch (\Throwable $e) {
    $result = ['success' => false, 'message' => $e->getMessage()];
}

echo json_encode($result);
