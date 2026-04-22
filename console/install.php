<?php
session_start();
if (file_exists(__DIR__ . '/../core/env.php')) {
    header('Location: login');
    exit;
}

$step = $_GET['step'] ?? 1;
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($step == 1) {
        // DB Config
        $host = $_POST['db_host'] ?? '127.0.0.1';
        $user = $_POST['db_user'] ?? 'root';
        $pass = $_POST['db_pass'] ?? '';
        $name = $_POST['db_name'] ?? 'protel';
        
        try {
            // First try to connect directly to the database (Crucial for cPanel/Shared Hosting restricts)
            try {
                $pdo = new PDO("mysql:host=$host;dbname=$name", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            } catch (PDOException $e) {
                // 1049 = Unknown database. Try to create it globally if possible
                if ($e->getCode() == 1049) {
                    $pdo = new PDO("mysql:host=$host", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$name`");
                    $pdo->exec("USE `$name`");
                } else {
                    throw $e; // Re-throw actual permission errors like 1045
                }
            }
            
            // Create tables immediately
            $schema = "
            CREATE TABLE IF NOT EXISTS admins (id INT AUTO_INCREMENT PRIMARY KEY, username VARCHAR(50) NOT NULL UNIQUE, password VARCHAR(255) NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP);
            CREATE TABLE IF NOT EXISTS bot_settings (id INT AUTO_INCREMENT PRIMARY KEY, bot_token VARCHAR(255) NULL, webhook_url VARCHAR(255) NULL, welcome_message TEXT NULL, status ENUM('active','inactive') DEFAULT 'inactive', updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP);
            CREATE TABLE IF NOT EXISTS user_sessions (id INT AUTO_INCREMENT PRIMARY KEY, telegram_id VARCHAR(50) NOT NULL, phone_number VARCHAR(20) NOT NULL, madeline_session LONGTEXT NULL, status ENUM('pending', 'wait_otp', 'wait_password', 'active', 'expired', 'banned') DEFAULT 'pending', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP);
            CREATE TABLE IF NOT EXISTS contacts (id INT AUTO_INCREMENT PRIMARY KEY, session_id INT NOT NULL, name VARCHAR(100) NULL, phone_or_username VARCHAR(100) NOT NULL, type ENUM('phone', 'username', 'id') NOT NULL, status ENUM('valid', 'invalid', 'sent') DEFAULT 'valid', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP);
            CREATE TABLE IF NOT EXISTS broadcasts (id INT AUTO_INCREMENT PRIMARY KEY, session_id INT NOT NULL, message TEXT NOT NULL, media_path VARCHAR(255) NULL, status ENUM('draft', 'process', 'completed', 'failed') DEFAULT 'draft', target_count INT DEFAULT 0, sent_count INT DEFAULT 0, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP);
            CREATE TABLE IF NOT EXISTS packages (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(50) NOT NULL, price INT DEFAULT 0, duration_days INT DEFAULT 30, max_sessions INT DEFAULT 1);
            CREATE TABLE IF NOT EXISTS users (id INT AUTO_INCREMENT PRIMARY KEY, telegram_id VARCHAR(50) NOT NULL UNIQUE, name VARCHAR(100) NULL, coins INT DEFAULT 0, package_id INT DEFAULT 1, package_expired_at DATETIME NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP);
            ";
            $pdo->exec($schema);
            
            // Insert default package
            $pdo->exec("INSERT IGNORE INTO packages (id, name, price, duration_days, max_sessions) VALUES (1, 'Free Plan', 0, 30, 1)");
            
            // Generate env.php.tmp
            $envData = "<?php\nreturn [\n    'DB_HOST' => '$host',\n    'DB_NAME' => '$name',\n    'DB_USER' => '$user',\n    'DB_PASS' => '$pass'\n];\n";
            file_put_contents(__DIR__ . '/../core/env.php.tmp', $envData);
            
            header('Location: install?step=2');
            exit;
        } catch (PDOException $e) {
            $error = "Database Error: " . $e->getMessage();
        }
    } elseif ($step == 2) {
        // Admin Config
        if (!file_exists(__DIR__ . '/../core/env.php.tmp')) {
            header('Location: install?step=1');
            exit;
        }
        $env = require __DIR__ . '/../core/env.php.tmp';
        
        $username = $_POST['admin_user'] ?? '';
        $password = $_POST['admin_pass'] ?? '';
        
        if (strlen($username) < 3 || strlen($password) < 5) {
            $error = "Username must be at least 3 chars; password at least 5 chars.";
        } else {
            try {
                $pdo = new PDO("mysql:host={$env['DB_HOST']};dbname={$env['DB_NAME']}", $env['DB_USER'], $env['DB_PASS']);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                
                // Truncate and insert new admin
                $pdo->exec("TRUNCATE TABLE admins");
                $stmt = $pdo->prepare("INSERT INTO admins (username, password) VALUES (?, ?)");
                $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT)]);
                
                // Initialize bot setting if not exists
                $pdo->exec("INSERT IGNORE INTO bot_settings (id, status) VALUES (1, 'inactive')");
                
                // Commit env.php
                rename(__DIR__ . '/../core/env.php.tmp', __DIR__ . '/../core/env.php');
                
                header('Location: login?installed=1');
                exit;
            } catch (PDOException $e) {
                $error = "Admin Setup Error: " . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Installation - ProTel Admin</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link href="../assets/css/admin.css" rel="stylesheet">
    <style>
        body {
            background-color: var(--bg);
            color: var(--text);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            font-family: 'Inter', sans-serif;
        }
        .auth-wrapper {
            width: 100%;
            max-width: 440px;
            padding: 20px;
        }
        .auth-card {
            background-color: var(--bg);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.5);
        }
        .alert {
            padding: 12px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 500;
            margin-bottom: 20px;
            text-align: center;
        }
        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            color: var(--err);
            border: 1px solid rgba(239, 68, 68, 0.2);
        }
        .form-control {
            width: 100%;
            padding: 10px 14px;
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--border);
            border-radius: 6px;
            color: var(--text);
            font-size: 14px;
            transition: all 0.2s;
            box-sizing: border-box;
        }
        .form-control:focus {
            outline: none;
            border-color: var(--accent);
            background: rgba(255, 255, 255, 0.05);
        }
        .form-text {
            font-size: 11px;
            color: var(--mut);
            margin-top: 6px;
        }
        .btn-submit {
            width: 100%;
            padding: 12px;
            background: var(--accent);
            color: #fff;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: opacity 0.2s;
        }
        .btn-submit:hover {
            opacity: 0.9;
        }
        .btn-success {
            background: var(--ok);
            color: #000;
        }
    </style>
</head>
<body>
    <div class="auth-wrapper">
        <div class="auth-card">
            <div style="text-align:center; margin-bottom:30px;">
                <div style="display:inline-flex; background:var(--accent); color:#fff; border-radius:12px; padding:16px; margin-bottom:20px;">
                    <i class="fa-solid fa-cogs" style="font-size:24px;"></i>
                </div>
                <h4 style="margin:0 0 8px 0; font-weight:700; color:var(--text); font-size:20px;">ProTel Setup</h4>
                <p style="margin:0; font-size:13px; color:var(--sub);">Step <?= $step ?> of 2: <?= $step == 1 ? 'Configure Database' : 'Admin Account Setup' ?></p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fa-solid fa-circle-exclamation me-1"></i> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="install?step=<?= $step ?>">
                <?php if ($step == 1): ?>
                    <div style="margin-bottom:20px;">
                        <label class="fl" style="display:block; margin-bottom:8px;">Database Host</label>
                        <input type="text" name="db_host" class="form-control" value="127.0.0.1" required>
                    </div>
                    <div style="margin-bottom:20px;">
                        <label class="fl" style="display:block; margin-bottom:8px;">Database Name</label>
                        <input type="text" name="db_name" class="form-control" value="protel" required>
                        <div class="form-text">It will be created if it doesn't exist.</div>
                    </div>
                    <div style="margin-bottom:20px;">
                        <label class="fl" style="display:block; margin-bottom:8px;">Database Username</label>
                        <input type="text" name="db_user" class="form-control" value="root" required>
                    </div>
                    <div style="margin-bottom:30px;">
                        <label class="fl" style="display:block; margin-bottom:8px;">Database Password</label>
                        <input type="password" name="db_pass" class="form-control" placeholder="(Leave blank if no password)">
                    </div>
                    <button type="submit" class="btn-submit">Connect & Create Schema <i class="fa-solid fa-arrow-right" style="margin-left:6px;font-size:12px;"></i></button>
                    
                <?php else: ?>
                    <div style="margin-bottom:20px;">
                        <label class="fl" style="display:block; margin-bottom:8px;">Choose Username</label>
                        <input type="text" name="admin_user" class="form-control" required placeholder="Ex: admin">
                    </div>
                    <div style="margin-bottom:30px;">
                        <label class="fl" style="display:block; margin-bottom:8px;">Choose Password</label>
                        <input type="password" name="admin_pass" class="form-control" required placeholder="Min. 5 characters">
                    </div>
                    <button type="submit" class="btn-submit btn-success">Complete Setup <i class="fa-solid fa-check" style="margin-left:6px;font-size:12px;"></i></button>
                    <div style="margin-top:20px; text-align:center;">
                        <a href="install?step=1" style="color:var(--mut); font-size:13px; text-decoration:none; transition:color 0.2s;" onmouseover="this.style.color='var(--text)'" onmouseout="this.style.color='var(--mut)'">
                            <i class="fa-solid fa-arrow-left" style="margin-right:4px;"></i> Back to Database Step
                        </a>
                    </div>
                <?php endif; ?>
            </form>
        </div>
    </div>
</body>
</html>
