<?php
/**
 * ProTel Broadcast System — MadelineProto Handler
 * Handles async Telegram MTProto operations via MadelineProto
 */

require_once __DIR__ . '/../vendor/autoload.php';

use danog\MadelineProto\API;
use danog\MadelineProto\Settings;
use danog\MadelineProto\Settings\AppInfo;

class TelegramHandler
{
    private string $sessionDir;
    private string $apiId;
    private string $apiHash;

    public function __construct(string $apiId, string $apiHash, string $sessionDir)
    {
        $this->apiId    = $apiId;
        $this->apiHash  = $apiHash;
        $this->sessionDir = rtrim($sessionDir, '/\\') . DIRECTORY_SEPARATOR;
    }

    private function getSettings(): Settings
    {
        $settings = new Settings();
        $settings->setAppInfo(
            (new AppInfo())
                ->setApiId((int)$this->apiId)
                ->setApiHash($this->apiHash)
        );
        return $settings;
    }

    /**
     * Get session file path
     */
    public function getSessionPath(string $phone): string
    {
        $safe = preg_replace('/[^0-9]/', '', $phone);
        return $this->sessionDir . "account_{$safe}.madeline";
    }

    /**
     * Step 1: Request OTP — returns phone_code_hash
     */
    public function requestOTP(string $phone): array
    {
        try {
            $sessionPath = $this->getSessionPath($phone);
            $MadelineProto = new API($sessionPath, $this->getSettings());
            $MadelineProto->start();

            // Send code
            $result = $MadelineProto->phoneLogin($phone);

            return [
                'success' => true,
                'phone_code_hash' => $result['phone_code_hash'] ?? '',
                'session_path' => $sessionPath,
            ];
        } catch (Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Step 2: Verify OTP — authenticates account
     */
    public function verifyOTP(string $phone, string $code, string $phoneCodeHash): array
    {
        try {
            $sessionPath = $this->getSessionPath($phone);
            $MadelineProto = new API($sessionPath, $this->getSettings());
            $MadelineProto->start();

            $MadelineProto->completePhoneLogin($code);

            // Get self info
            $me = $MadelineProto->getSelf();

            return [
                'success'    => true,
                'first_name' => $me['first_name'] ?? '',
                'last_name'  => $me['last_name']  ?? '',
                'username'   => $me['username']   ?? '',
                'tg_user_id' => $me['id']         ?? 0,
            ];
        } catch (Throwable $e) {
            $msg = $e->getMessage();
            // 2FA needed
            if (stripos($msg, 'password') !== false || stripos($msg, '2FA') !== false) {
                return ['success' => false, 'need_2fa' => true, 'message' => 'Akun ini menggunakan verifikasi 2 langkah (2FA). Masukkan password Telegram Anda.'];
            }
            return ['success' => false, 'message' => $msg];
        }
    }

    /**
     * Step 2b: Complete 2FA
     */
    public function complete2FA(string $phone, string $password): array
    {
        try {
            $sessionPath = $this->getSessionPath($phone);
            $MadelineProto = new API($sessionPath, $this->getSettings());
            $MadelineProto->start();

            $MadelineProto->complete2faLogin($password);
            $me = $MadelineProto->getSelf();

            return [
                'success'    => true,
                'first_name' => $me['first_name'] ?? '',
                'last_name'  => $me['last_name']  ?? '',
                'username'   => $me['username']   ?? '',
                'tg_user_id' => $me['id']         ?? 0,
            ];
        } catch (Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Send a message via a specific account
     */
    public function sendMessage(string $accountPhone, string $recipient, string $message): array
    {
        try {
            $sessionPath = $this->getSessionPath($accountPhone);
            if (!file_exists($sessionPath)) {
                return ['success' => false, 'message' => 'Session not found'];
            }

            $MadelineProto = new API($sessionPath, $this->getSettings());
            $MadelineProto->start();

            $MadelineProto->messages->sendMessage([
                'peer'    => $recipient,
                'message' => $message,
            ]);

            return ['success' => true];
        } catch (Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Send photo via a specific account
     */
    public function sendPhoto(string $accountPhone, string $recipient, string $message, string $photoPath): array
    {
        try {
            $sessionPath = $this->getSessionPath($accountPhone);
            $MadelineProto = new API($sessionPath, $this->getSettings());
            $MadelineProto->start();

            $MadelineProto->messages->sendMedia([
                'peer'    => $recipient,
                'media'   => [
                    '_'              => 'inputMediaUploadedPhoto',
                    'file'           => $MadelineProto->upload($photoPath),
                ],
                'message' => $message,
            ]);

            return ['success' => true];
        } catch (Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Check if account session is still valid
     */
    public function checkSession(string $phone): bool
    {
        try {
            $sessionPath = $this->getSessionPath($phone);
            if (!file_exists($sessionPath)) return false;
            $MadelineProto = new API($sessionPath, $this->getSettings());
            $MadelineProto->start();
            $me = $MadelineProto->getSelf();
            return isset($me['id']);
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * Logout and remove session
     */
    public function logoutAccount(string $phone): array
    {
        try {
            $sessionPath = $this->getSessionPath($phone);
            if (file_exists($sessionPath)) {
                $MadelineProto = new API($sessionPath, $this->getSettings());
                $MadelineProto->start();
                $MadelineProto->logout();
                @unlink($sessionPath);
                // Remove related files
                foreach (glob($sessionPath . '*') as $f) @unlink($f);
            }
            return ['success' => true];
        } catch (Throwable $e) {
            // Still delete local session even if remote logout fails
            @unlink($this->getSessionPath($phone));
            return ['success' => true, 'warning' => $e->getMessage()];
        }
    }
}
