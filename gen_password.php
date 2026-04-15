<?php
/**
 * Admin password generator
 * Akses sekali untuk generate password hash baru
 * Hapus file ini setelah digunakan!
 */
if ($_POST['pass'] ?? false) {
    $hash = password_hash($_POST['pass'], PASSWORD_BCRYPT);
    echo "<pre>Hash: $hash</pre>";
    echo "<p>Update di database_users atau config/app.php</p>";
}
?>
<!DOCTYPE html>
<html>
<head><title>Generate Password</title>
<style>body{font-family:sans-serif;padding:40px;background:#111;color:#eee}input,button{padding:10px;border-radius:8px;border:1px solid #333;background:#222;color:#eee;font-size:14px}</style>
</head>
<body>
<h2>Generate Admin Password Hash</h2>
<form method="post">
    <input type="password" name="pass" placeholder="Password baru" required>
    <button type="submit">Generate</button>
</form>
<p style="color:#f59e0b">⚠ Hapus file ini setelah digunakan!</p>
</body>
</html>
