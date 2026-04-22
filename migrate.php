<?php
$env = require_once __DIR__ . '/core/env.php';
$pdo = new PDO("mysql:host={$env['DB_HOST']};dbname={$env['DB_NAME']};charset=utf8mb4", $env['DB_USER'], $env['DB_PASS'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS packages (
        id INT AUTO_INCREMENT PRIMARY KEY, 
        name VARCHAR(50), 
        price INT DEFAULT 0, 
        max_sessions INT DEFAULT 1, 
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );");
    $pdo->exec("INSERT IGNORE INTO packages (id, name, price, max_sessions) VALUES (1, 'Free Plan', 0, 1);");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY, 
        telegram_id VARCHAR(50) NOT NULL UNIQUE, 
        name VARCHAR(100) NULL, 
        coins INT DEFAULT 0, 
        package_id INT DEFAULT 1, 
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );");
    
    $pdo->exec("INSERT IGNORE INTO users (telegram_id, package_id) SELECT DISTINCT telegram_id, 1 FROM user_sessions;");
    
    // Drop the unique constraint on user_sessions.telegram_id
    // First, check if index exists
    $stmt = $pdo->query("SHOW INDEX FROM user_sessions WHERE Key_name = 'telegram_id'");
    if ($stmt->rowCount() > 0) {
        $pdo->exec("ALTER TABLE user_sessions DROP INDEX telegram_id");
        echo "Dropped unique constraint.\n";
    }
    
    echo "Database migrations applied successfully.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
