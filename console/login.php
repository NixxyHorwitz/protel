<?php
require_once __DIR__ . '/../core/database.php';

if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in']) {
    header('Location: index');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if ($username && $password) {
        $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = :username LIMIT 1");
        $stmt->execute(['username' => $username]);
        $admin = $stmt->fetch();
        
        if ($admin && password_verify($password, $admin['password'])) {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_username'] = $admin['username'];
            
            write_log('AUTH', "Admin logged in successfully: $username");
            
            header('Location: index');
            exit;
        } else {
            $error = "Invalid username or password";
            write_log('AUTH_FAIL', "Failed login attempt for: $username");
        }
    } else {
        $error = "Please fill all fields";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - ProTel Admin</title>
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
            max-width: 400px;
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
        .alert-success {
            background: rgba(34, 197, 94, 0.1);
            color: var(--ok);
            border: 1px solid rgba(34, 197, 94, 0.2);
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
    </style>
</head>
<body>
    <div class="auth-wrapper">
        <div class="auth-card">
            <div style="text-align:center; margin-bottom:30px;">
                <div style="display:inline-flex; background:var(--accent); color:#fff; border-radius:12px; padding:16px; margin-bottom:20px;">
                    <i class="fa-solid fa-paper-plane" style="font-size:24px;"></i>
                </div>
                <h4 style="margin:0 0 8px 0; font-weight:700; color:var(--text); font-size:20px;">Welcome Back</h4>
                <p style="margin:0; font-size:13px; color:var(--sub);">Sign in to ProTel Admin Panel</p>
            </div>
            
            <?php if (isset($_GET['installed'])): ?>
                <div class="alert alert-success">
                    <i class="fa-solid fa-check-circle me-1"></i> Setup complete! Please login with your new account.
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fa-solid fa-circle-exclamation me-1"></i> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div style="margin-bottom:20px;">
                    <label class="fl" style="display:block; margin-bottom:8px;">Username</label>
                    <input type="text" name="username" class="form-control" required placeholder="admin">
                </div>
                <div style="margin-bottom:30px;">
                    <label class="fl" style="display:block; margin-bottom:8px;">Password</label>
                    <input type="password" name="password" class="form-control" required placeholder="••••••••">
                </div>
                <button type="submit" class="btn-submit">Sign In <i class="fa-solid fa-arrow-right" style="margin-left:6px;font-size:12px;"></i></button>
            </form>
        </div>
    </div>
</body>
</html>
