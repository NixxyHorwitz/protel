<?php
require_once __DIR__ . '/config.php';

if (!file_exists(__DIR__ . '/env.php')) {
    if (basename($_SERVER['PHP_SELF']) !== 'install.php') {
        header('Location: ' . BASE_URL . '/console/install');
        exit;
    }
    return;
}

$env = require __DIR__ . '/env.php';

$dsn = "mysql:host={$env['DB_HOST']};dbname={$env['DB_NAME']};charset=utf8mb4";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
     $pdo = new PDO($dsn, $env['DB_USER'], $env['DB_PASS'], $options);
     
     // Silent Auto-Migrations for Bot Logic Fixes
     try { $pdo->exec("ALTER TABLE user_sessions DROP INDEX telegram_id"); } catch (\Exception $e) {}
     try { $pdo->exec("ALTER TABLE user_sessions MODIFY COLUMN status ENUM('pending', 'wait_otp', 'wait_password', 'active', 'expired', 'banned') DEFAULT 'pending'"); } catch (\Exception $e) {}
} catch (\PDOException $e) {
     write_log('DB_ERROR', $e->getMessage());
     if (basename($_SERVER['PHP_SELF']) !== 'install.php') {
         die("Database connection failed. Please check core/env.php.");
     }
}
