<?php
require_once __DIR__ . '/config/app.php';
if (isset($_GET['logout'])) { session_destroy(); header('Location: index.php'); exit; }
if (empty($_SESSION['admin_logged_in'])) { header('Location: index.php'); exit; }
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ProTel — Admin Panel</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/dashboard.css?v=4">
</head>
<body>

<aside class="sidebar" id="sidebar">
    <div class="sidebar-logo">
        <div class="logo-icon">📡</div>
        <div class="logo-text">Pro<span>Tel</span></div>
    </div>
    <nav class="sidebar-nav">
        <a href="#" class="nav-item active" data-tab="dashboard"><span class="nav-icon">📊</span> Overview</a>
        <a href="#" class="nav-item" data-tab="users"><span class="nav-icon">👥</span> Pengguna</a>
        <a href="#" class="nav-item" data-tab="campaigns"><span class="nav-icon">📢</span> Campaign</a>
        <a href="#" class="nav-item" data-tab="settings"><span class="nav-icon">⚙️</span> Konfigurasi Bot</a>
    </nav>
    <div class="sidebar-footer">
        <button class="btn-logout" id="btnLogout">🚪 Logout</button>
    </div>
</aside>

<div class="main-wrap">
    <header class="topbar">
        <button class="sidebar-toggle" id="sidebarToggle">☰</button>
        <div class="topbar-title" id="topbarTitle">Overview</div>
        <div class="topbar-right">
            <div class="status-dot online"></div>
            <span class="status-label">Admin</span>
        </div>
    </header>

    <main class="main-content">

        <!-- ── TAB: OVERVIEW ── -->
        <section class="tab-pane active" id="tab-dashboard">
            <div class="page-header">
                <div><h1>Overview Global</h1><p class="page-sub">Statistik sistem broadcast ProTel</p></div>
                <button class="btn btn-secondary" onclick="loadDashboard()">🔄 Refresh</button>
            </div>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon" style="background:linear-gradient(135deg,#4f7eff,#7c3aed)">👥</div>
                    <div class="stat-info"><div class="stat-value" id="statUsers">—</div><div class="stat-label">Total Pengguna Bot</div></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background:linear-gradient(135deg,#10b981,#059669)">📱</div>
                    <div class="stat-info"><div class="stat-value" id="statAccounts">—</div><div class="stat-label">Akun Telegram Tercatat</div></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background:linear-gradient(135deg,#f59e0b,#d97706)">📢</div>
                    <div class="stat-info"><div class="stat-value" id="statCampaigns">—</div><div class="stat-label">Total Campaign</div></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background:linear-gradient(135deg,#06b6d4,#0284c7)">✉️</div>
                    <div class="stat-info"><div class="stat-value" id="statSent">—</div><div class="stat-label">Total Pesan Terkirim</div></div>
                </div>
            </div>
            <div class="section-title" style="margin-top:24px">Campaign Sedang Berjalan</div>
            <div class="table-card" id="runningCampaigns"><div class="loading-state">⏳ Memuat...</div></div>
        </section>

        <!-- ── TAB: USERS ── -->
        <section class="tab-pane" id="tab-users">
            <div class="page-header">
                <div><h1>Data Pengguna Bot</h1><p class="page-sub">Semua user yang berinteraksi dengan bot</p></div>
                <button class="btn btn-secondary" onclick="loadUsers()">🔄 Refresh</button>
            </div>
            <div class="table-card" id="usersTable"><div class="loading-state">⏳ Memuat...</div></div>
        </section>

        <!-- ── TAB: CAMPAIGNS ── -->
        <section class="tab-pane" id="tab-campaigns">
            <div class="page-header">
                <div><h1>Monitoring Semua Campaign</h1><p class="page-sub">Pantau seluruh broadcast dari semua pengguna</p></div>
                <button class="btn btn-secondary" onclick="loadCampaigns()">🔄 Refresh</button>
            </div>
            <div class="table-card" id="campaignsTable"><div class="loading-state">⏳ Memuat...</div></div>
        </section>

        <!-- ── TAB: SETTINGS ── -->
        <section class="tab-pane" id="tab-settings">
            <div class="page-header">
                <div>
                    <h1>Konfigurasi Bot</h1>
                    <p class="page-sub">Isi sekali, simpan, dan bot langsung aktif</p>
                </div>
            </div>
            <div style="max-width: 600px">
                <div class="card" style="background:var(--card);border:1px solid var(--border);border-radius:var(--radius);padding:28px">
                    <p style="font-size:13px;color:var(--muted);margin-bottom:24px;line-height:1.6">
                        ⚠️ <strong>Catatan:</strong> Konfigurasi disimpan ke <code>config/app.php</code>.<br>
                        Untuk mendapatkan <strong>API ID & Hash</strong>, daftar di <a href="https://my.telegram.org/apps" target="_blank" style="color:var(--accent)">my.telegram.org/apps</a>.<br>
                        Untuk mendapatkan <strong>Bot Token</strong>, chat ke <a href="https://t.me/BotFather" target="_blank" style="color:var(--accent)">@BotFather</a> → /newbot.
                    </p>
                    <form id="frmConfig">
                        <div class="form-group">
                            <label>Telegram API ID</label>
                            <input type="text" id="cfgApiId" placeholder="12345678" autocomplete="off">
                        </div>
                        <div class="form-group">
                            <label>Telegram API Hash</label>
                            <input type="text" id="cfgApiHash" placeholder="0123456789abcdef0123456789abcdef" autocomplete="off">
                        </div>
                        <div class="form-group">
                            <label>Bot Token (dari @BotFather)</label>
                            <input type="text" id="cfgBotToken" placeholder="1234567890:ABCdefGHIjklMNOpqrsTUVwxyz" autocomplete="off">
                            <small style="color:var(--muted);font-size:12px;margin-top:4px;display:block">
                                Bot ini hanya digunakan sebagai antarmuka pengguna. Broadcast dikirim via akun Telegram asli.
                            </small>
                        </div>
                        <div class="form-group">
                            <label>App URL (URL server kamu)</label>
                            <input type="url" id="cfgAppUrl" placeholder="https://domain.com/protel">
                            <small style="color:var(--muted);font-size:12px;margin-top:4px;display:block">
                                Digunakan untuk setup Webhook secara otomatis saat disimpan.
                            </small>
                        </div>
                        <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;margin-top:8px">
                            💾 Simpan Konfigurasi
                        </button>
                    </form>
                    <div id="configAlert" style="margin-top:14px;font-size:13px"></div>
                </div>
            </div>
        </section>

    </main>
</div>

<div class="toast" id="toast"></div>
<script src="assets/dashboard.js?v=4"></script>
</body>
</html>
