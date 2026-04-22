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
    
    // Simple basic protection against brute force is usually handled via fail-to-ban, skipping for this scope but uses prepared stmt
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="../assets/css/admin.css" rel="stylesheet">
</head>
<body>
    <div class="auth-wrapper">
        <div class="card auth-card border-0 shadow-sm">
            <div class="text-center mb-4">
                <div class="d-inline-flex bg-primary text-white rounded p-3 mb-3">
                    <i class="fa-solid fa-paper-plane fs-3"></i>
                </div>
                <h4 class="fw-bold">Welcome Back</h4>
                <p class="text-muted small">Sign in to ProTel Admin Panel</p>
            </div>
            
            <?php if (isset($_GET['installed'])): ?>
                <div class="alert alert-success py-2 text-center small rounded-2">
                    <i class="fa-solid fa-check-circle me-1"></i> Setup complete! Please login with your new account.
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger py-2 text-center small rounded-2">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="mb-3">
                    <label class="form-label text-muted small fw-medium">Username</label>
                    <input type="text" name="username" class="form-control" required placeholder="admin">
                </div>
                <div class="mb-4">
                    <label class="form-label text-muted small fw-medium">Password</label>
                    <input type="password" name="password" class="form-control" required placeholder="••••••••">
                </div>
                <button type="submit" class="btn btn-primary w-100 py-2">Sign In</button>
            </form>
        </div>
    </div>
</body>
</html>
