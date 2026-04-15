<?php
/**
 * ProTel — MadelineProto CLI Script: Verify OTP
 * Usage: php verify_otp.php <phone> <code>
 * Output: JSON ke stdout
 */

chdir(dirname(__DIR__));
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/app.php';

use danog\MadelineProto\API;
use danog\MadelineProto\Settings;
use danog\MadelineProto\Settings\AppInfo;

$phone = $argv[1] ?? '';
$code  = $argv[2] ?? '';

if (empty($phone) || empty($code)) {
    echo json_encode(['success' => false, 'message' => 'Phone and code required']);
    exit(1);
}

$sessionPath = rtrim(SESSION_DIR, '/\\') . DIRECTORY_SEPARATOR . 'account_' . preg_replace('/[^0-9]/', '', $phone) . '.madeline';

if (!file_exists($sessionPath)) {
    echo json_encode(['success' => false, 'message' => 'Session tidak ditemukan. Minta OTP terlebih dahulu.']);
    exit(1);
}

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

    $MadelineProto->loop(function () use ($MadelineProto, $code, &$result) {
        try {
            yield $MadelineProto->completePhoneLogin($code);

            // Cek jika butuh signup (akun baru)
            $auth = $MadelineProto->getAuthorization();
            // AUTH_KEY_UNREGISTERED = perlu signup, AUTH_KEY = sudah login
            if ($auth === \danog\MadelineProto\API::WAITING_SIGNUP) {
                yield $MadelineProto->completeSignup('ProTel User');
            }

            $me = yield $MadelineProto->getSelf();
            $result = [
                'success'    => true,
                'first_name' => $me['first_name'] ?? '',
                'last_name'  => $me['last_name']  ?? '',
                'username'   => $me['username']   ?? '',
                'tg_user_id' => $me['id']         ?? 0,
            ];
        } catch (\danog\MadelineProto\RPCErrorException $e) {
            $msg = $e->getMessage();
            if (str_contains($msg, 'SESSION_PASSWORD_NEEDED') || str_contains($msg, 'Two-step')) {
                $result = ['success' => false, 'need_2fa' => true, 'message' => 'Akun ini menggunakan verifikasi 2 langkah (2FA). Masukkan password Telegram Anda.'];
            } else {
                $result = ['success' => false, 'message' => $msg];
            }
        } catch (\Throwable $e) {
            $msg = $e->getMessage();
            if (str_contains($msg, 'SESSION_PASSWORD_NEEDED') || str_contains($msg, 'password')) {
                $result = ['success' => false, 'need_2fa' => true, 'message' => 'Akun ini menggunakan verifikasi 2 langkah (2FA). Masukkan password Telegram Anda.'];
            } else {
                $result = ['success' => false, 'message' => $msg];
            }
        }
    });
} catch (\Throwable $e) {
    $result = ['success' => false, 'message' => $e->getMessage()];
}

echo json_encode($result);
