<?php
/**
 * ProTel — MadelineProto CLI Script: Request OTP
 * Usage: php request_otp.php <phone>
 * Output: JSON ke stdout
 *
 * MadelineProto v8 menggunakan Fibers — harus dijalankan via start()
 */

chdir(dirname(__DIR__));
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/app.php';

use danog\MadelineProto\API;
use danog\MadelineProto\Settings;
use danog\MadelineProto\Settings\AppInfo;

$phone = $argv[1] ?? '';
if (empty($phone)) {
    echo json_encode(['success' => false, 'message' => 'Phone required']);
    exit(1);
}

if (empty(TG_API_ID) || empty(TG_API_HASH)) {
    echo json_encode(['success' => false, 'message' => 'API ID dan API Hash belum diisi di config/app.php']);
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

    // MadelineProto v8: gunakan start() dengan event loop + callFork
    $MadelineProto->async(true);

    $MadelineProto->loop(function () use ($MadelineProto, $phone, &$result) {
        try {
            $loginResult = yield $MadelineProto->phoneLogin($phone);
            $result = [
                'success'         => true,
                'phone_code_hash' => $loginResult['phone_code_hash'] ?? '',
                'session_path'    => $MadelineProto->getSessionName(),
            ];
        } catch (\Throwable $e) {
            $result = ['success' => false, 'message' => $e->getMessage()];
        }
    });
} catch (\Throwable $e) {
    $result = ['success' => false, 'message' => $e->getMessage()];
}

echo json_encode($result);
