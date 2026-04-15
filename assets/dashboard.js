/**
 * ProTel SAAS - Super Admin Dashboard Script
 */

// Initialize
document.addEventListener('DOMContentLoaded', () => {
    // Nav tabs
    document.querySelectorAll('.nav-item').forEach(el => {
        if (el.hasAttribute('data-tab')) {
            el.addEventListener('click', (e) => {
                e.preventDefault();
                switchTab(el.getAttribute('data-tab'));
            });
        }
    });

    // Modals
    document.querySelectorAll('.modal-close').forEach(el => {
        el.addEventListener('click', () => closeModal(el.getAttribute('data-close')));
    });

    document.getElementById('frmBot').addEventListener('submit', handleAddBot);

    // Initial load
    loadDashboard();
});

// Navigation
function switchTab(tabId) {
    document.querySelectorAll('.nav-item').forEach(el => el.classList.remove('active'));
    document.querySelector(`.nav-item[data-tab="${tabId}"]`)?.classList.add('active');

    document.querySelectorAll('.tab-pane').forEach(el => el.classList.remove('active'));
    document.getElementById('tab-' + tabId)?.classList.add('active');
    
    // Toggle sidebar on mobile
    if (window.innerWidth <= 768) {
        document.getElementById('sidebar').classList.remove('active');
    }

    if (tabId === 'dashboard') loadDashboard();
    if (tabId === 'bots') loadBots();
    if (tabId === 'users') loadUsers();
    if (tabId === 'campaigns') loadCampaigns();
}

document.getElementById('sidebarToggle').addEventListener('click', () => {
    document.getElementById('sidebar').classList.toggle('active');
});

// Toast notification
function showToast(message, type = 'success') {
    const toast = document.getElementById('toast');
    toast.textContent = message;
    toast.className = 'toast ' + type + ' show';
    setTimeout(() => { toast.classList.remove('show'); }, 3000);
}

// Modal management
function openModal(id) {
    document.getElementById(id).classList.add('open');
}
function closeModal(id) {
    document.getElementById(id).classList.remove('open');
}
function enableBtn(btnId, enabled, loadingText = 'Memproses...') {
    const btn = document.querySelector(`#${btnId} button[type=submit]`);
    if (!btn) return;
    if (!enabled) {
        btn.dataset.og = btn.innerHTML;
        btn.innerHTML = `⏳ ${loadingText}`;
        btn.disabled = true;
    } else {
        btn.innerHTML = btn.dataset.og;
        btn.disabled = false;
    }
}

// API Call Wrapper
async function apiCall(action, data = {}) {
    const formData = new FormData();
    formData.append('action', action);
    for (const key in data) {
        if (data[key] instanceof File) {
            formData.append(key, data[key]);
        } else {
            formData.append(key, typeof data[key] === 'object' ? JSON.stringify(data[key]) : data[key]);
        }
    }
    
    try {
        const res = await fetch('api/handler.php', { method: 'POST', body: formData });
        return await res.json();
    } catch (e) {
        return { success: false, message: 'Server error: ' + e.message };
    }
}

// --- TAB LOADER FUNCTIONS ---

async function loadDashboard() {
    const res = await apiCall('get_global_stats');
    if (res.success) {
        document.getElementById('statBots').textContent = res.data.bots;
        document.getElementById('statUsers').textContent = res.data.users;
        document.getElementById('statCampaigns').textContent = res.data.campaigns;
        document.getElementById('statSent').textContent = res.data.sent;
    }
}

async function loadBots() {
    document.getElementById('botsGrid').innerHTML = '<div class="loading-state">⏳ Memuat bot...</div>';
    const res = await apiCall('get_bots');
    if (res.success) {
        const bots = res.data;
        if (bots.length === 0) {
            document.getElementById('botsGrid').innerHTML = '<div style="grid-column:1/-1;text-align:center;color:var(--muted);padding:40px">Belum ada bot yang ditambahkan.</div>';
            return;
        }

        let html = '';
        bots.forEach(b => {
            const statusClass = b.status === 'active' ? 'badge-active' : 'badge-inactive';
            html += `
                <div class="bot-card ${b.status}">
                    <div class="bot-header">
                        <div class="bot-username">🤖 @${b.bot_username}</div>
                        <div class="status-badge ${statusClass}">${b.status}</div>
                    </div>
                    <div class="bot-token" title="Token API" style="-webkit-line-clamp: 1; -webkit-box-orient: vertical; overflow: hidden; display: -webkit-box; cursor: pointer" onclick="alert('${b.bot_token}')">
                        ${b.bot_token.substring(0, 15)}...${b.bot_token.slice(-5)}
                    </div>
                    <div style="font-size: 13px; color: var(--muted); margin-bottom: 15px">
                        Ditambahkan: ${b.added_at}
                    </div>
                    <div class="bot-actions">
                        ${b.status === 'active' 
                          ? `<button class="btn btn-sm" style="flex:1; background:#fee2e2; color:#991b1b; border:1px solid #fca5a5" onclick="deleteBot(${b.id})">🗑 Hapus</button>` 
                          : `<button class="btn btn-sm btn-primary" style="flex:1" onclick="activateBot(${b.id})">✅ Aktifkan</button>`
                        }
                    </div>
                </div>
            `;
        });
        document.getElementById('botsGrid').innerHTML = html;
    }
}

async function loadUsers() {
    document.getElementById('usersTable').innerHTML = '<div class="loading-state">⏳ Memuat data...</div>';
    const res = await apiCall('get_users');
    if (res.success && res.data.length > 0) {
        let rows = res.data.map((u, i) => `
            <tr>
                <td>${i + 1}</td>
                <td><code>${u.owner_tg_id}</code></td>
                <td>${u.accounts} Akun Tele</td>
                <td>${u.contacts} Kontak Tersimpan</td>
                <td>${u.campaigns} Campaign</td>
            </tr>
        `).join('');
        
        document.getElementById('usersTable').innerHTML = `
            <table class="data-table">
                <thead><tr><th width="50">No</th><th>Telegram User ID</th><th>Total Akun</th><th>Total Kontak</th><th>Total Campaign</th></tr></thead>
                <tbody>${rows}</tbody>
            </table>
        `;
    } else {
        document.getElementById('usersTable').innerHTML = '<div class="loading-state">Tidak ada pengguna aktif.</div>';
    }
}

async function loadCampaigns() {
    document.getElementById('campaignsTable').innerHTML = '<div class="loading-state">⏳ Memuat data...</div>';
    const res = await apiCall('get_global_campaigns');
    if (res.success && res.data.length > 0) {
        let rows = res.data.map((c, i) => {
            const pct = c.total > 0 ? Math.round((c.sent / c.total) * 100) : 0;
            return `
            <tr>
                <td>${c.name}</td>
                <td><code>${c.owner_tg_id}</code></td>
                <td><span class="status-badge status-${c.status}">${c.status}</span></td>
                <td>
                    <div class="progress-bar-wrap" style="height:6px; margin-bottom:4px; max-width:120px">
                        <div class="progress-bar-fill" style="width: ${pct}%"></div>
                    </div>
                    <small>${c.sent}/${c.total} (${pct}%)</small>
                </td>
                <td>${c.created_at}</td>
            </tr>
        `}).join('');
        
        document.getElementById('campaignsTable').innerHTML = `
            <table class="data-table">
                <thead><tr><th>Nama Campaign</th><th>Milik User (UID)</th><th>Status</th><th>Progress</th><th>Dibuat</th></tr></thead>
                <tbody>${rows}</tbody>
            </table>
        `;
    } else {
        document.getElementById('campaignsTable').innerHTML = '<div class="loading-state">Tidak ada campaign.</div>';
    }
}

// --- ACTIONS ---

document.getElementById('btnAddBot').addEventListener('click', () => {
    document.getElementById('frmBot').reset();
    document.getElementById('botAlert').innerHTML = '';
    openModal('modalBot');
});

async function handleAddBot(e) {
    e.preventDefault();
    const token = document.getElementById('botToken').value.trim();
    const appUrl = document.getElementById('appUrl').value.trim();
    const alertBox = document.getElementById('botAlert');
    
    alertBox.innerHTML = '';
    enableBtn('frmBot', false, 'Menghubungi Telegram API...');
    
    const res = await apiCall('add_system_bot', { token, app_url: appUrl });
    
    enableBtn('frmBot', true);
    
    if (res.success) {
        showToast('Bot berhasil ditambahkan dan webhook diset!');
        closeModal('modalBot');
        loadBots();
        loadDashboard();
    } else {
        alertBox.innerHTML = `<div style="color:var(--danger)">❌ ${res.message}</div>`;
    }
}

async function deleteBot(id) {
    if (!confirm('Hapus bot ini? Bot akan tidak dapat digunakan lagi oleh user.')) return;
    const res = await apiCall('delete_system_bot', { id });
    if (res.success) {
        showToast('Bot berhasil dihapus');
        loadBots();
        loadDashboard();
    } else {
        showToast('Gagal menghapus bot: ' + res.message, 'error');
    }
}

function logout() {
    if (confirm('Yakin ingin logout?')) {
        window.location.href = 'dashboard.php?logout=1';
    }
}
