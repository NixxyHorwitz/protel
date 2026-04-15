<?php
require_once __DIR__ . '/config/app.php';
if (empty($_SESSION['admin_logged_in'])) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ProTel — Super Admin Dashboard</title>
    <meta name="description" content="Super Admin Panel untuk Manajemen Bot Telegram">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/dashboard.css">
    <style>
        .bot-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; margin-top: 20px; }
        .bot-card { background: white; border-radius: 12px; padding: 20px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); border: 1px solid var(--border); }
        .bot-card.inactive { opacity: 0.7; }
        .bot-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 15px; }
        .bot-username { font-weight: 600; font-size: 1.1rem; color: var(--text-dark); display:flex; align-items:center; gap: 8px;}
        .status-badge { padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; text-transform: uppercase; }
        .badge-active { background: #dcfce7; color: #166534; }
        .badge-inactive { background: #fee2e2; color: #991b1b; }
        .bot-token { background: #f1f5f9; padding: 8px 12px; border-radius: 6px; font-family: monospace; font-size: 13px; color: #475569; word-break: break-all; margin-bottom: 15px; }
        .bot-actions { display: flex; gap: 10px; }
    </style>
</head>
<body>

<!-- Sidebar -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-logo">
        <div class="logo-icon">👑</div>
        <div class="logo-text">Pro<span>Admin</span></div>
    </div>
    <nav class="sidebar-nav">
        <a href="#" class="nav-item active" data-tab="dashboard">
            <span class="nav-icon">📊</span> Overview
        </a>
        <a href="#" class="nav-item" data-tab="bots">
            <span class="nav-icon">🤖</span> Kelola Bot
        </a>
        <a href="#" class="nav-item" data-tab="users">
            <span class="nav-icon">👥</span> Data Pengguna
        </a>
        <a href="#" class="nav-item" data-tab="campaigns">
            <span class="nav-icon">📢</span> Monitoring Campaign
        </a>
    </nav>
    <div class="sidebar-footer">
        <button class="btn-logout" id="btnLogout">🚪 Logout</button>
    </div>
</aside>

<!-- Main content -->
<div class="main-wrap">
    <!-- Topbar -->
    <header class="topbar">
        <button class="sidebar-toggle" id="sidebarToggle">☰</button>
        <div class="topbar-title" id="topbarTitle">Dashboard</div>
        <div class="topbar-right">
            <div class="status-dot online"></div>
            <span class="status-label">Super Admin</span>
        </div>
    </header>

    <main class="main-content">

        <!-- ── TAB: DASHBOARD ── -->
        <section class="tab-pane active" id="tab-dashboard">
            <div class="page-header">
                <h1>Global Overview</h1>
                <p class="page-sub">Statistik seluruh sistem, bot, dan pengguna</p>
            </div>
            <div class="stats-grid" id="statsGrid">
                <div class="stat-card">
                    <div class="stat-icon" style="background:linear-gradient(135deg,#4f7eff,#7c3aed)">🤖</div>
                    <div class="stat-info">
                        <div class="stat-value" id="statBots">—</div>
                        <div class="stat-label">Total Bot Aktif</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background:linear-gradient(135deg,#10b981,#059669)">👥</div>
                    <div class="stat-info">
                        <div class="stat-value" id="statUsers">—</div>
                        <div class="stat-label">Total Pengguna (Tenants)</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background:linear-gradient(135deg,#f59e0b,#d97706)">📢</div>
                    <div class="stat-info">
                        <div class="stat-value" id="statCampaigns">—</div>
                        <div class="stat-label">Total Campaign</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background:linear-gradient(135deg,#06b6d4,#0284c7)">✉️</div>
                    <div class="stat-info">
                        <div class="stat-value" id="statSent">—</div>
                        <div class="stat-label">Total Pesan Terkirim</div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ── TAB: BOTS ── -->
        <section class="tab-pane" id="tab-bots">
            <div class="page-header">
                <div>
                    <h1>Kelola Bot Telegram</h1>
                    <p class="page-sub">Tambahkan token bot yang akan dipakai oleh pengguna</p>
                </div>
                <button class="btn btn-primary" id="btnAddBot">+ Tambah Bot</button>
            </div>

            <div id="botsGrid" class="bot-grid">
                <div class="loading-state">⏳ Memuat bot...</div>
            </div>
        </section>

        <!-- ── TAB: USERS ── -->
        <section class="tab-pane" id="tab-users">
            <div class="page-header">
                <h1>Data Pengguna (Tenants)</h1>
                <p class="page-sub">Daftar pengguna Telegram yang berinteraksi dengan bot sistem</p>
            </div>
            <div class="table-card" id="usersTable">
                <div class="loading-state">⏳ Memuat data...</div>
            </div>
        </section>

        <!-- ── TAB: CAMPAIGNS ── -->
        <section class="tab-pane" id="tab-campaigns">
            <div class="page-header">
                <h1>Monitoring Campaign</h1>
                <p class="page-sub">Pantau aktivitas broadcast dari seluruh pengguna</p>
            </div>
            <div class="table-card" id="campaignsTable">
                <div class="loading-state">⏳ Memuat data...</div>
            </div>
        </section>

    </main>
</div>

<!-- ═══ MODAL: ADD BOT ═══ -->
<div class="modal-overlay" id="modalBot">
    <div class="modal-card">
        <div class="modal-header">
            <div class="modal-title">➕ Tambah Bot Telegram</div>
            <button class="modal-close" data-close="modalBot">✕</button>
        </div>
        <form id="frmBot">
            <div class="form-group">
                <label>Script Webhook URL</label>
                <div style="font-size: 13px; color: var(--muted); margin-bottom: 5px;">Pastikan ini adalah URL valid (HTTPS) server Anda (contoh: https://domainanda.com/protel)</div>
                <input type="url" id="appUrl" placeholder="https://domain.com/protel" required value="<?= APP_URL ?>">
            </div>
            <div class="form-group">
                <label>Telegram Bot Token (dari @BotFather)</label>
                <input type="text" id="botToken" placeholder="1234567890:ABCdefghIJKLmnopQRSTuvwxyz" required>
            </div>
            <button type="submit" class="btn btn-primary btn-full">Simpan & Set Webhook</button>
            <div class="modal-alert" id="botAlert"></div>
        </form>
    </div>
</div>

<!-- Toast -->
<div class="toast" id="toast"></div>

<script src="assets/dashboard.js"></script>
</body>
</html>
