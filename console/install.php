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
            CREATE TABLE IF NOT EXISTS user_sessions (id INT AUTO_INCREMENT PRIMARY KEY, telegram_id VARCHAR(50) NOT NULL UNIQUE, phone_number VARCHAR(20) NOT NULL, madeline_session LONGTEXT NULL, status ENUM('pending', 'active', 'expired', 'banned') DEFAULT 'pending', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP);
            CREATE TABLE IF NOT EXISTS contacts (id INT AUTO_INCREMENT PRIMARY KEY, session_id INT NOT NULL, name VARCHAR(100) NULL, phone_or_username VARCHAR(100) NOT NULL, type ENUM('phone', 'username', 'id') NOT NULL, status ENUM('valid', 'invalid', 'sent') DEFAULT 'valid', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP);
            CREATE TABLE IF NOT EXISTS broadcasts (id INT AUTO_INCREMENT PRIMARY KEY, session_id INT NOT NULL, message TEXT NOT NULL, media_path VARCHAR(255) NULL, status ENUM('draft', 'process', 'completed', 'failed') DEFAULT 'draft', target_count INT DEFAULT 0, sent_count INT DEFAULT 0, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP);
            ";
            $pdo->exec($schema);
            
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="../assets/css/admin.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="auth-wrapper">
        <div class="card auth-card border-0 shadow-sm">
            <div class="text-center mb-4">
                <div class="d-inline-flex bg-primary text-white rounded p-3 mb-3">
                    <i class="fa-solid fa-cogs fs-3"></i>
                </div>
                <h4 class="fw-bold">ProTel Setup</h4>
                <p class="text-muted small">Step <?= $step ?> of 2: <?= $step == 1 ? 'Configure Database' : 'Admin Account Setup' ?></p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger py-2 text-center small rounded-2">
                    <i class="fa-solid fa-circle-exclamation me-1"></i> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="install?step=<?= $step ?>">
                <?php if ($step == 1): ?>
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-medium">Database Host</label>
                        <input type="text" name="db_host" class="form-control" value="127.0.0.1" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-medium">Database Name</label>
                        <input type="text" name="db_name" class="form-control" value="protel" required>
                        <div class="form-text small">It will be created if it doesn't exist.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-medium">Database Username</label>
                        <input type="text" name="db_user" class="form-control" value="root" required>
                    </div>
                    <div class="mb-4">
                        <label class="form-label text-muted small fw-medium">Database Password</label>
                        <input type="password" name="db_pass" class="form-control" placeholder="(Leave blank if no password)">
                    </div>
                    <button type="submit" class="btn btn-primary w-100 py-2">Connect & Create Schema <i class="fa-solid fa-arrow-right ms-1"></i></button>
                    
                <?php else: ?>
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-medium">Choose Username</label>
                        <input type="text" name="admin_user" class="form-control" required placeholder="Ex: admin">
                    </div>
                    <div class="mb-4">
                        <label class="form-label text-muted small fw-medium">Choose Password</label>
                        <input type="password" name="admin_pass" class="form-control" required placeholder="Min. 5 characters">
                    </div>
                    <button type="submit" class="btn btn-success w-100 py-2">Complete Setup <i class="fa-solid fa-check ms-1"></i></button>
                    <div class="mt-3 text-center">
                        <a href="install?step=1" class="text-muted small text-decoration-none"><i class="fa-solid fa-arrow-left me-1"></i> Back to Database Step</a>
                    </div>
                <?php endif; ?>
            </form>
        </div>
    </div>
</body>
</html>
