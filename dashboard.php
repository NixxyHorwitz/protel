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
    <title>ProTel — Dashboard Broadcast</title>
    <meta name="description" content="Kelola akun Telegram dan kirim broadcast massal">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/dashboard.css">
</head>
<body>

<!-- Sidebar -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-logo">
        <div class="logo-icon">📡</div>
        <div class="logo-text">Pro<span>Tel</span></div>
    </div>
    <nav class="sidebar-nav">
        <a href="#" class="nav-item active" data-tab="dashboard">
            <span class="nav-icon">🏠</span> Dashboard
        </a>
        <a href="#" class="nav-item" data-tab="accounts">
            <span class="nav-icon">👤</span> Akun Telegram
        </a>
        <a href="#" class="nav-item" data-tab="contacts">
            <span class="nav-icon">📋</span> Daftar Kontak
        </a>
        <a href="#" class="nav-item" data-tab="broadcast">
            <span class="nav-icon">📢</span> Broadcast
        </a>
        <a href="#" class="nav-item" data-tab="history">
            <span class="nav-icon">📊</span> Riwayat
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
            <span class="status-label">Online</span>
        </div>
    </header>

    <main class="main-content">

        <!-- ── TAB: DASHBOARD ── -->
        <section class="tab-pane active" id="tab-dashboard">
            <div class="page-header">
                <h1>Overview</h1>
                <p class="page-sub">Statistik sistem broadcast Telegram Anda</p>
            </div>
            <div class="stats-grid" id="statsGrid">
                <div class="stat-card">
                    <div class="stat-icon" style="background:linear-gradient(135deg,#4f7eff,#7c3aed)">👤</div>
                    <div class="stat-info">
                        <div class="stat-value" id="statAccounts">—</div>
                        <div class="stat-label">Akun Aktif</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background:linear-gradient(135deg,#10b981,#059669)">📋</div>
                    <div class="stat-info">
                        <div class="stat-value" id="statContacts">—</div>
                        <div class="stat-label">Total Kontak</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background:linear-gradient(135deg,#f59e0b,#d97706)">📢</div>
                    <div class="stat-info">
                        <div class="stat-value" id="statCampaigns">—</div>
                        <div class="stat-label">Campaign</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background:linear-gradient(135deg,#06b6d4,#0284c7)">✉️</div>
                    <div class="stat-info">
                        <div class="stat-value" id="statSent">—</div>
                        <div class="stat-label">Pesan Terkirim</div>
                    </div>
                </div>
            </div>

            <!-- Quick actions -->
            <div class="section-title">Aksi Cepat</div>
            <div class="quick-actions">
                <div class="action-card" onclick="switchTab('accounts')">
                    <div class="ac-icon">➕</div>
                    <div class="ac-label">Tambah Akun</div>
                    <div class="ac-hint">Login akun Telegram baru</div>
                </div>
                <div class="action-card" onclick="switchTab('contacts')">
                    <div class="ac-icon">📥</div>
                    <div class="ac-label">Import Kontak</div>
                    <div class="ac-hint">Upload CSV kontak tujuan</div>
                </div>
                <div class="action-card" onclick="switchTab('broadcast')">
                    <div class="ac-icon">🚀</div>
                    <div class="ac-label">Kirim Broadcast</div>
                    <div class="ac-hint">Buat campaign baru</div>
                </div>
                <div class="action-card" onclick="switchTab('history')">
                    <div class="ac-icon">📊</div>
                    <div class="ac-label">Lihat Riwayat</div>
                    <div class="ac-hint">Monitor campaign berjalan</div>
                </div>
            </div>
        </section>

        <!-- ── TAB: ACCOUNTS ── -->
        <section class="tab-pane" id="tab-accounts">
            <div class="page-header">
                <div>
                    <h1>Akun Telegram</h1>
                    <p class="page-sub">Kelola akun userbot untuk broadcast</p>
                </div>
                <button class="btn btn-primary" id="btnAddAccount">+ Tambah Akun</button>
            </div>

            <div class="table-card" id="accountsTable">
                <div class="loading-state">⏳ Memuat akun...</div>
            </div>
        </section>

        <!-- ── TAB: CONTACTS ── -->
        <section class="tab-pane" id="tab-contacts">
            <div class="page-header">
                <div>
                    <h1>Daftar Kontak</h1>
                    <p class="page-sub">Kelola kontak tujuan broadcast</p>
                </div>
                <div class="btn-group">
                    <button class="btn btn-secondary" id="btnImportCSV">⬆ Import CSV</button>
                    <button class="btn btn-primary" id="btnAddContact">+ Tambah Kontak</button>
                </div>
            </div>

            <div class="table-card" id="contactsTable">
                <div class="loading-state">⏳ Memuat kontak...</div>
            </div>
        </section>

        <!-- ── TAB: BROADCAST ── -->
        <section class="tab-pane" id="tab-broadcast">
            <div class="page-header">
                <h1>Kirim Broadcast</h1>
                <p class="page-sub">Buat dan kirim pesan massal ke semua kontak</p>
            </div>

            <div class="broadcast-layout">
                <!-- Form -->
                <div class="broadcast-form card">
                    <div class="card-title">✍️ Tulis Pesan</div>
                    <form id="broadcastForm">
                        <div class="form-group">
                            <label>Nama Campaign</label>
                            <input type="text" id="campName" placeholder="Promo Agustus 2024..." required>
                        </div>
                        <div class="form-group">
                            <label>Pesan</label>
                            <textarea id="campMessage" rows="6" placeholder="Tulis pesan broadcast di sini...&#10;&#10;Gunakan emoji untuk membuat pesan lebih menarik! 🎉" required></textarea>
                            <div class="char-count" id="charCount">0 karakter</div>
                        </div>
                        <div class="form-group">
                            <label>Media (Opsional)</label>
                            <div class="file-drop" id="fileDrop">
                                <div class="file-drop-icon">📎</div>
                                <div>Drag & drop atau <label for="mediaInput" class="file-label">pilih file</label></div>
                                <div class="file-hint">Foto, video, atau dokumen</div>
                                <input type="file" id="mediaInput" accept="image/*,video/*,.pdf,.zip" hidden>
                            </div>
                            <div id="mediaPreview" class="media-preview" style="display:none"></div>
                        </div>
                        <div class="form-row">
                            <div class="form-group" style="flex:1">
                                <label>Delay antar pesan (detik)</label>
                                <input type="number" id="campDelay" value="3" min="1" max="60">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Grup Kontak Tujuan</label>
                            <select id="campGroup">
                                <option value="">Semua kontak</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary btn-full" id="btnSendBroadcast">
                            🚀 Kirim Broadcast
                        </button>
                    </form>
                </div>

                <!-- Preview -->
                <div class="broadcast-preview card">
                    <div class="card-title">📱 Preview Pesan</div>
                    <div class="tg-preview">
                        <div class="tg-bubble">
                            <div class="tg-msg" id="previewMsg">Pesan Anda akan tampil di sini...</div>
                            <div class="tg-time">✓✓ 09:41</div>
                        </div>
                    </div>
                    <div class="form-group" style="margin-top:16px">
                        <label>Akun Pengirim (centang semua yang aktif)</label>
                        <div id="accountCheckList" class="check-list">
                            <div class="loading-state small">⏳ Memuat akun...</div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ── TAB: HISTORY ── -->
        <section class="tab-pane" id="tab-history">
            <div class="page-header">
                <h1>Riwayat Campaign</h1>
                <p class="page-sub">Monitor dan lihat detail broadcast</p>
            </div>
            <div class="table-card" id="historyTable">
                <div class="loading-state">⏳ Memuat riwayat...</div>
            </div>
        </section>

    </main>
</div>

<!-- ═══ MODAL: ADD ACCOUNT ═══ -->
<div class="modal-overlay" id="modalAccount">
    <div class="modal-card">
        <div class="modal-header">
            <div class="modal-title">➕ Tambah Akun Telegram</div>
            <button class="modal-close" data-close="modalAccount">✕</button>
        </div>

        <!-- Step 1: Phone -->
        <div id="step-phone" class="modal-step active">
            <p class="modal-hint">Masukkan nomor telepon yang terdaftar di Telegram. Sistem akan mengirim kode OTP.</p>
            <div class="form-group">
                <label>Nomor HP (format internasional)</label>
                <input type="tel" id="accPhone" placeholder="+62812345678901" autocomplete="tel">
            </div>
            <button class="btn btn-primary btn-full" id="btnRequestOTP">
                📲 Kirim Kode OTP
            </button>
            <div class="modal-alert" id="accAlertPhone"></div>
        </div>

        <!-- Step 2: OTP -->
        <div id="step-otp" class="modal-step">
            <p class="modal-hint">Kode telah dikirim ke Telegram <span id="otpPhone" class="highlight"></span>. Masukkan kode 5 digit:</p>
            <div class="form-group">
                <label>Kode OTP</label>
                <input type="text" id="accOTP" placeholder="12345" maxlength="5" pattern="[0-9]{5}" inputmode="numeric">
            </div>
            <button class="btn btn-primary btn-full" id="btnVerifyOTP">
                ✅ Verifikasi OTP
            </button>
            <button class="btn btn-ghost btn-full" id="btnBackToPhone">← Ganti Nomor</button>
            <div class="modal-alert" id="accAlertOTP"></div>
        </div>

        <!-- Step 2b: 2FA -->
        <div id="step-2fa" class="modal-step">
            <p class="modal-hint">Akun ini menggunakan verifikasi 2 langkah. Masukkan password Telegram Anda:</p>
            <div class="form-group">
                <label>Password 2FA</label>
                <input type="password" id="acc2FA" placeholder="Password Telegram">
            </div>
            <button class="btn btn-primary btn-full" id="btnVerify2FA">
                🔓 Verifikasi Password
            </button>
            <div class="modal-alert" id="accAlert2FA"></div>
        </div>

        <!-- Step 3: Success -->
        <div id="step-success" class="modal-step success-step">
            <div class="success-icon">🎉</div>
            <div class="success-title">Akun Berhasil Ditambahkan!</div>
            <div class="success-info" id="successInfo"></div>
            <button class="btn btn-primary btn-full" id="btnDoneAccount">Selesai</button>
        </div>
    </div>
</div>

<!-- ═══ MODAL: ADD CONTACT ═══ -->
<div class="modal-overlay" id="modalContact">
    <div class="modal-card">
        <div class="modal-header">
            <div class="modal-title">➕ Tambah Kontak</div>
            <button class="modal-close" data-close="modalContact">✕</button>
        </div>
        <div class="form-group">
            <label>Nomor HP</label>
            <input type="tel" id="ctcPhone" placeholder="+6281234567890">
        </div>
        <div class="form-group">
            <label>Username Telegram</label>
            <input type="text" id="ctcUsername" placeholder="@username (tanpa @)">
        </div>
        <div class="form-group">
            <label>Nama</label>
            <input type="text" id="ctcName" placeholder="Budi Santoso">
        </div>
        <div class="form-group">
            <label>Grup</label>
            <input type="text" id="ctcGroup" placeholder="Default" value="Default">
        </div>
        <button class="btn btn-primary btn-full" id="btnSaveContact">Simpan Kontak</button>
        <div class="modal-alert" id="contactAlert"></div>
    </div>
</div>

<!-- ═══ MODAL: IMPORT CSV ═══ -->
<div class="modal-overlay" id="modalCSV">
    <div class="modal-card">
        <div class="modal-header">
            <div class="modal-title">⬆ Import CSV</div>
            <button class="modal-close" data-close="modalCSV">✕</button>
        </div>
        <p class="modal-hint">Format CSV: <code>phone, nama, username</code> (baris pertama = header)</p>
        <div class="form-group">
            <label>Nama Grup</label>
            <input type="text" id="csvGroup" placeholder="Import Batch 1">
        </div>
        <div class="form-group">
            <label>File CSV</label>
            <input type="file" id="csvFile" accept=".csv" class="file-input-full">
        </div>
        <button class="btn btn-primary btn-full" id="btnUploadCSV">⬆ Upload & Import</button>
        <div class="modal-alert" id="csvAlert"></div>
    </div>
</div>

<!-- ═══ MODAL: CAMPAIGN DETAIL ═══ -->
<div class="modal-overlay" id="modalCampaignDetail">
    <div class="modal-card modal-wide">
        <div class="modal-header">
            <div class="modal-title" id="detailCampTitle">Detail Campaign</div>
            <button class="modal-close" data-close="modalCampaignDetail">✕</button>
        </div>
        <div class="campaign-progress" id="campProgressWrap">
            <div class="progress-bar-wrap">
                <div class="progress-bar-fill" id="campProgressBar"></div>
            </div>
            <div class="progress-stats" id="campProgressStats"></div>
        </div>
        <div class="log-table-wrap" id="logTableWrap">
            <div class="loading-state">⏳ Memuat log...</div>
        </div>
    </div>
</div>

<!-- Toast -->
<div class="toast" id="toast"></div>

<script src="assets/dashboard.js"></script>
</body>
</html>
