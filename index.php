<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ProTel Broadcast — Login</title>
    <meta name="description" content="ProTel: Platform manajemen broadcast Telegram via akun asli">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg:       #0a0e1a;
            --card:     #111827;
            --accent:   #4f7eff;
            --accent2:  #7c3aed;
            --text:     #e2e8f0;
            --muted:    #64748b;
            --border:   rgba(255,255,255,0.08);
            --success:  #10b981;
            --danger:   #ef4444;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }
        /* Animated background */
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background:
                radial-gradient(ellipse 600px 600px at 20% 30%, rgba(79,126,255,0.12) 0%, transparent 70%),
                radial-gradient(ellipse 500px 500px at 80% 70%, rgba(124,58,237,0.10) 0%, transparent 70%);
            pointer-events: none;
            z-index: 0;
        }
        .grid-bg {
            position: fixed;
            inset: 0;
            background-image:
                linear-gradient(rgba(255,255,255,0.015) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,0.015) 1px, transparent 1px);
            background-size: 40px 40px;
            z-index: 0;
        }
        .login-card {
            position: relative;
            z-index: 10;
            background: rgba(17,24,39,0.85);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 42px 40px;
            width: 100%;
            max-width: 420px;
            backdrop-filter: blur(24px);
            box-shadow: 0 25px 80px rgba(0,0,0,0.5), 0 0 0 1px rgba(79,126,255,0.1);
            animation: slideUp 0.5s ease;
        }
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .logo-wrap {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 32px;
        }
        .logo-icon {
            width: 44px;
            height: 44px;
            background: linear-gradient(135deg, var(--accent), var(--accent2));
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            box-shadow: 0 4px 20px rgba(79,126,255,0.4);
        }
        .logo-text { font-size: 22px; font-weight: 700; letter-spacing: -0.5px; }
        .logo-text span { color: var(--accent); }
        .sub-text { font-size: 13px; color: var(--muted); margin-top: 2px; }
        h2 { font-size: 20px; font-weight: 600; margin-bottom: 6px; }
        .hint { font-size: 13px; color: var(--muted); margin-bottom: 28px; }
        .form-group { margin-bottom: 18px; }
        label { display: block; font-size: 13px; font-weight: 500; color: var(--muted); margin-bottom: 8px; }
        input {
            width: 100%;
            background: rgba(255,255,255,0.04);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 12px 16px;
            color: var(--text);
            font-size: 14px;
            font-family: inherit;
            transition: border-color 0.2s, box-shadow 0.2s;
            outline: none;
        }
        input:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(79,126,255,0.15);
        }
        .btn {
            width: 100%;
            padding: 13px;
            border: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            font-family: inherit;
        }
        .btn-primary {
            background: linear-gradient(135deg, var(--accent), var(--accent2));
            color: #fff;
            box-shadow: 0 4px 20px rgba(79,126,255,0.35);
        }
        .btn-primary:hover { transform: translateY(-1px); box-shadow: 0 6px 30px rgba(79,126,255,0.5); }
        .btn-primary:active { transform: translateY(0); }
        .btn-primary:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }
        .alert {
            padding: 12px 14px;
            border-radius: 10px;
            font-size: 13px;
            margin-bottom: 18px;
            display: none;
        }
        .alert-error { background: rgba(239,68,68,0.12); border: 1px solid rgba(239,68,68,0.3); color: #fca5a5; }
        .spinner { display: inline-block; width: 16px; height: 16px; border: 2px solid rgba(255,255,255,0.3); border-top-color: #fff; border-radius: 50%; animation: spin 0.8s linear infinite; vertical-align: middle; }
        @keyframes spin { to { transform: rotate(360deg); } }
    </style>
</head>
<body>
<div class="grid-bg"></div>
<div class="login-card">
    <div class="logo-wrap">
        <div class="logo-icon">📡</div>
        <div>
            <div class="logo-text">Pro<span>Tel</span></div>
            <div class="sub-text">Telegram Broadcast System</div>
        </div>
    </div>

    <h2>Selamat datang</h2>
    <p class="hint">Login untuk mengelola akun & broadcast</p>

    <div class="alert alert-error" id="alertError"></div>

    <form id="loginForm">
        <div class="form-group">
            <label for="username">Username</label>
            <input type="text" id="username" name="username" placeholder="admin" autocomplete="username" required>
        </div>
        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" placeholder="••••••••" autocomplete="current-password" required>
        </div>
        <button type="submit" class="btn btn-primary" id="loginBtn">
            Masuk ke Dashboard
        </button>
    </form>
</div>

<script>
const API = 'api/handler.php';

document.getElementById('loginForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn   = document.getElementById('loginBtn');
    const alert = document.getElementById('alertError');
    alert.style.display = 'none';
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner"></span> Masuk...';

    const fd = new FormData(e.target);
    fd.append('action', 'login');

    try {
        const res  = await fetch(API, { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) {
            window.location.href = 'dashboard.php';
        } else {
            alert.textContent    = data.message || 'Login gagal';
            alert.style.display  = 'block';
        }
    } catch (err) {
        alert.textContent   = 'Koneksi error: ' + err.message;
        alert.style.display = 'block';
    } finally {
        btn.disabled        = false;
        btn.innerHTML       = 'Masuk ke Dashboard';
    }
});
</script>
</body>
</html>
