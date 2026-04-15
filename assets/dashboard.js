/**
 * ProTel Admin — Dashboard JS (Simplified)
 */

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.nav-item[data-tab]').forEach(el => {
        el.addEventListener('click', e => {
            e.preventDefault();
            switchTab(el.getAttribute('data-tab'));
        });
    });

    document.getElementById('sidebarToggle').addEventListener('click', () => {
        document.getElementById('sidebar').classList.toggle('active');
    });

    document.getElementById('btnLogout').addEventListener('click', () => {
        if (confirm('Yakin ingin logout?')) {
            window.location.href = 'dashboard.php?logout=1';
        }
    });

    // Form konfigurasi bot
    document.getElementById('frmConfig').addEventListener('submit', saveConfig);

    loadDashboard();
});

// ── Navigation ────────────────────────────────────────────────
function switchTab(tabId) {
    const titles = {
        dashboard: 'Overview',
        users:     'Data Pengguna',
        campaigns: 'Semua Campaign',
        settings:  'Konfigurasi Bot',
    };

    document.querySelectorAll('.nav-item').forEach(el => el.classList.remove('active'));
    document.querySelector(`.nav-item[data-tab="${tabId}"]`)?.classList.add('active');
    document.querySelectorAll('.tab-pane').forEach(el => el.classList.remove('active'));
    document.getElementById('tab-' + tabId)?.classList.add('active');
    document.getElementById('topbarTitle').textContent = titles[tabId] || tabId;

    if (window.innerWidth <= 768) document.getElementById('sidebar').classList.remove('active');

    if (tabId === 'dashboard') loadDashboard();
    if (tabId === 'users')     loadUsers();
    if (tabId === 'campaigns') loadCampaigns();
    if (tabId === 'settings')  loadSettings();
}

// ── API Wrapper ───────────────────────────────────────────────
async function api(action, data = {}) {
    const fd = new FormData();
    fd.append('action', action);
    Object.entries(data).forEach(([k, v]) => fd.append(k, v));
    try {
        const res = await fetch('api/handler.php', { method: 'POST', body: fd });
        return await res.json();
    } catch (e) {
        return { success: false, message: e.message };
    }
}

// ── Toast ─────────────────────────────────────────────────────
function toast(msg, type = 'success') {
    const el = document.getElementById('toast');
    el.textContent = msg;
    el.className = `toast ${type} show`;
    setTimeout(() => el.classList.remove('show'), 3500);
}

function progressBar(pct) {
    pct = Math.min(100, Math.max(0, pct));
    return `<div class="progress-bar-wrap" style="height:6px;max-width:140px;margin-bottom:4px">
                <div class="progress-bar-fill" style="width:${pct}%"></div>
            </div><small>${pct}%</small>`;
}

function statusBadge(status) {
    const map = {
        running: 'badge-running', done: 'badge-done',
        paused: 'badge-pending', failed: 'badge-failed', draft: 'badge-draft',
    };
    return `<span class="badge ${map[status] || 'badge-draft'}">${status}</span>`;
}

// ═══════════════════════════════════════════════════════════
//  OVERVIEW
// ═══════════════════════════════════════════════════════════

async function loadDashboard() {
    const res = await api('get_global_stats');
    if (res.success) {
        document.getElementById('statUsers').textContent     = res.data.users;
        document.getElementById('statAccounts').textContent  = res.data.accounts;
        document.getElementById('statCampaigns').textContent = res.data.campaigns;
        document.getElementById('statSent').textContent      = res.data.sent;
    }

    // Running campaigns only
    const rc = await api('get_global_campaigns', { status_filter: 'running' });
    const wrap = document.getElementById('runningCampaigns');
    if (rc.success && rc.data.length > 0) {
        wrap.innerHTML = `<table class="data-table">
            <thead><tr><th>Nama Campaign</th><th>User UID</th><th>Progress</th><th>Terkirim</th></tr></thead>
            <tbody>${rc.data.map(c => {
                const pct = c.total > 0 ? Math.round((c.sent / c.total) * 100) : 0;
                return `<tr>
                    <td><strong>${c.name}</strong></td>
                    <td><code>${c.owner_tg_id}</code></td>
                    <td>${progressBar(pct)}</td>
                    <td>${c.sent} / ${c.total}</td>
                </tr>`;
            }).join('')}</tbody>
        </table>`;
    } else {
        wrap.innerHTML = '<div class="loading-state">✅ Tidak ada campaign yang sedang berjalan.</div>';
    }
}

// ═══════════════════════════════════════════════════════════
//  USERS
// ═══════════════════════════════════════════════════════════

async function loadUsers() {
    document.getElementById('usersTable').innerHTML = '<div class="loading-state">⏳ Memuat...</div>';
    const res = await api('get_users');
    if (!res.success || !res.data.length) {
        document.getElementById('usersTable').innerHTML = '<div class="loading-state">Belum ada pengguna yang terdaftar.</div>';
        return;
    }

    document.getElementById('usersTable').innerHTML = `
        <table class="data-table">
            <thead><tr>
                <th>No</th><th>Telegram User ID</th>
                <th>Akun Terdaftar</th><th>Total Kontak</th>
                <th>Campaign</th><th>Pesan Terkirim</th>
            </tr></thead>
            <tbody>${res.data.map((u, i) => `<tr>
                <td>${i + 1}</td>
                <td><code>${u.owner_tg_id}</code></td>
                <td>${u.accounts}</td>
                <td>${u.contacts}</td>
                <td>${u.campaigns}</td>
                <td>${u.sent ?? 0}</td>
            </tr>`).join('')}</tbody>
        </table>`;
}

// ═══════════════════════════════════════════════════════════
//  CAMPAIGNS
// ═══════════════════════════════════════════════════════════

async function loadCampaigns() {
    document.getElementById('campaignsTable').innerHTML = '<div class="loading-state">⏳ Memuat...</div>';
    const res = await api('get_global_campaigns');
    if (!res.success || !res.data.length) {
        document.getElementById('campaignsTable').innerHTML = '<div class="loading-state">Belum ada campaign.</div>';
        return;
    }

    document.getElementById('campaignsTable').innerHTML = `
        <table class="data-table">
            <thead><tr>
                <th>Nama Campaign</th><th>User UID</th><th>Status</th>
                <th>Progress</th><th>✓ Terkirim</th><th>✗ Gagal</th><th>Dibuat</th>
            </tr></thead>
            <tbody>${res.data.map(c => {
                const pct = c.total > 0 ? Math.round((c.sent / c.total) * 100) : 0;
                return `<tr>
                    <td><strong>${c.name}</strong></td>
                    <td><code>${c.owner_tg_id}</code></td>
                    <td>${statusBadge(c.status)}</td>
                    <td>${progressBar(pct)}</td>
                    <td>${c.sent}</td>
                    <td>${c.failed ?? 0}</td>
                    <td>${c.created_at}</td>
                </tr>`;
            }).join('')}</tbody>
        </table>`;
}

// ═══════════════════════════════════════════════════════════
//  SETTINGS (Konfigurasi Bot)
// ═══════════════════════════════════════════════════════════

async function loadSettings() {
    const res = await api('get_config');
    if (res.success) {
        document.getElementById('cfgApiId').value   = res.data.api_id   || '';
        document.getElementById('cfgApiHash').value = res.data.api_hash || '';
        document.getElementById('cfgBotToken').value= res.data.bot_token|| '';
        document.getElementById('cfgAppUrl').value  = res.data.app_url  || '';
    }
}

async function saveConfig(e) {
    e.preventDefault();
    const btn = e.target.querySelector('button[type=submit]');
    btn.disabled = true;
    btn.innerHTML = '⏳ Menyimpan...';

    const res = await api('save_config', {
        api_id:    document.getElementById('cfgApiId').value.trim(),
        api_hash:  document.getElementById('cfgApiHash').value.trim(),
        bot_token: document.getElementById('cfgBotToken').value.trim(),
        app_url:   document.getElementById('cfgAppUrl').value.trim(),
    });

    btn.disabled = false;
    btn.innerHTML = '💾 Simpan Konfigurasi';

    if (res.success) {
        toast('✅ Konfigurasi berhasil disimpan!');
    } else {
        toast('❌ Gagal: ' + res.message, 'error');
    }
}
