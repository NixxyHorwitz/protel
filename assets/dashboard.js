/**
 * ProTel Dashboard — JavaScript
 */

const API = 'api/handler.php';

/* ── HELPERS ── */
const $ = (sel, ctx = document) => ctx.querySelector(sel);
const $$ = (sel, ctx = document) => [...ctx.querySelectorAll(sel)];

async function apiPost(params) {
    const fd = new FormData();
    for (const [k, v] of Object.entries(params)) {
        if (Array.isArray(v)) v.forEach(i => fd.append(k + '[]', i));
        else fd.append(k, v);
    }
    const r = await fetch(API, { method: 'POST', body: fd });
    return r.json();
}

async function apiGet(params) {
    const qs = new URLSearchParams(params);
    const r  = await fetch(`${API}?${qs}`);
    return r.json();
}

/* ── TOAST ── */
function toast(msg, type = 'success', duration = 3500) {
    const el = $('#toast');
    el.textContent = msg;
    el.className   = `toast ${type} show`;
    setTimeout(() => el.classList.remove('show'), duration);
}

/* ── SPINNER ── */
function setLoading(btn, loading, text = null) {
    if (loading) {
        btn.disabled         = true;
        btn._originalText    = btn.innerHTML;
        btn.innerHTML        = '<span class="spinner"></span> ' + (text || 'Memproses...');
    } else {
        btn.disabled  = false;
        btn.innerHTML = btn._originalText || btn.innerHTML;
    }
}

/* ── MODAL ── */
function openModal(id)  { $('#' + id)?.classList.add('open'); }
function closeModal(id) { $('#' + id)?.classList.remove('open'); }

$$('[data-close]').forEach(btn => {
    btn.addEventListener('click', () => closeModal(btn.dataset.close));
});

document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        $$('.modal-overlay.open').forEach(m => m.classList.remove('open'));
    }
});

/* ── SIDEBAR ── */
$('#sidebarToggle')?.addEventListener('click', () => {
    $('#sidebar').classList.toggle('open');
});

/* ── TABS ── */
const tabTitles = {
    dashboard: 'Dashboard',
    accounts:  'Akun Telegram',
    contacts:  'Daftar Kontak',
    broadcast: 'Kirim Broadcast',
    history:   'Riwayat Campaign',
};

function switchTab(name) {
    $$('.nav-item').forEach(a => {
        a.classList.toggle('active', a.dataset.tab === name);
    });
    $$('.tab-pane').forEach(p => {
        p.classList.toggle('active', p.id === 'tab-' + name);
    });
    $('#topbarTitle').textContent = tabTitles[name] || name;
    $('#sidebar').classList.remove('open');

    // Load data for tab
    if (name === 'dashboard')  loadStats();
    if (name === 'accounts')   loadAccounts();
    if (name === 'contacts')   loadContacts();
    if (name === 'broadcast')  { loadAccountChecklist(); loadGroupOptions(); }
    if (name === 'history')    loadHistory();
}

$$('.nav-item').forEach(a => {
    a.addEventListener('click', e => {
        e.preventDefault();
        switchTab(a.dataset.tab);
    });
});

/* ── LOGOUT ── */
$('#btnLogout')?.addEventListener('click', async () => {
    await apiPost({ action: 'logout' });
    window.location.href = 'index.php';
});

/* ═══════════════════════════════════════════════
   DASHBOARD STATS
══════════════════════════════════════════════ */
async function loadStats() {
    const d = await apiGet({ action: 'get_stats' });
    if (!d.success) return;
    $('#statAccounts').textContent  = d.data.total_accounts;
    $('#statContacts').textContent  = d.data.total_contacts;
    $('#statCampaigns').textContent = d.data.total_campaigns;
    $('#statSent').textContent      = d.data.total_sent;
}

/* ═══════════════════════════════════════════════
   ACCOUNTS
══════════════════════════════════════════════ */
async function loadAccounts() {
    const wrap = $('#accountsTable');
    wrap.innerHTML = '<div class="loading-state">⏳ Memuat akun...</div>';

    const d = await apiGet({ action: 'get_accounts' });
    if (!d.success) { wrap.innerHTML = '<div class="loading-state">❌ Gagal memuat</div>'; return; }

    if (!d.data.length) {
        wrap.innerHTML = '<div class="loading-state">📭 Belum ada akun. Tambahkan akun pertama Anda!</div>';
        return;
    }

    const rows = d.data.map(acc => `
        <tr>
            <td>
                <div style="font-weight:600">${esc(acc.first_name)} ${esc(acc.last_name || '')}</div>
                <div style="font-size:11.5px;color:var(--muted)">${acc.username ? '@' + esc(acc.username) : '—'}</div>
            </td>
            <td>${esc(acc.phone)}</td>
            <td>${statusBadge(acc.status)}</td>
            <td style="font-size:12px;color:var(--muted)">${formatDate(acc.added_at)}</td>
            <td style="font-size:12px;color:var(--muted)">${acc.last_used ? formatDate(acc.last_used) : '—'}</td>
            <td>
                <button class="btn-icon danger" onclick="deleteAccount(${acc.id}, '${esc(acc.phone)}')">🗑 Hapus</button>
            </td>
        </tr>
    `).join('');

    wrap.innerHTML = `
        <table>
            <thead><tr>
                <th>Nama</th><th>Nomor HP</th><th>Status</th><th>Ditambahkan</th><th>Terakhir Dipakai</th><th>Aksi</th>
            </tr></thead>
            <tbody>${rows}</tbody>
        </table>
    `;
}

async function deleteAccount(id, phone) {
    if (!confirm(`Hapus akun ${phone}? Session Telegram akan dihapus.`)) return;
    const d = await apiPost({ action: 'delete_account', id });
    if (d.success) { toast('Akun dihapus', 'success'); loadAccounts(); }
    else toast(d.message, 'error');
}

/* ── ADD ACCOUNT FLOW ── */
$('#btnAddAccount')?.addEventListener('click', () => {
    resetAccountModal();
    openModal('modalAccount');
});

function resetAccountModal() {
    showStep('step-phone');
    $('#accPhone').value = '';
    $('#accOTP').value   = '';
    $('#acc2FA').value   = '';
    $$('#modalAccount .modal-alert').forEach(a => { a.style.display = 'none'; a.textContent = ''; });
}

function showStep(id) {
    $$('.modal-step').forEach(s => s.classList.remove('active'));
    $('#' + id)?.classList.add('active');
}

function showModalAlert(id, msg, type = 'error') {
    const el = $('#' + id);
    if (!el) return;
    el.textContent  = msg;
    el.className    = `modal-alert ${type}`;
    el.style.display = 'block';
}

let currentOTPPhone = '';

$('#btnRequestOTP')?.addEventListener('click', async () => {
    const phone = $('#accPhone').value.trim();
    if (!phone) { showModalAlert('accAlertPhone', 'Masukkan nomor HP terlebih dahulu'); return; }

    const btn = $('#btnRequestOTP');
    setLoading(btn, true, 'Mengirim kode OTP...');
    $('#accAlertPhone').style.display = 'none';

    const d = await apiPost({ action: 'request_otp', phone });
    setLoading(btn, false);

    if (d.success) {
        currentOTPPhone = phone;
        $('#otpPhone').textContent = phone;
        showStep('step-otp');
        toast('Kode OTP dikirim ke Telegram mu!', 'success');
    } else {
        showModalAlert('accAlertPhone', d.message || 'Gagal mengirim OTP');
    }
});

$('#btnBackToPhone')?.addEventListener('click', () => showStep('step-phone'));

$('#btnVerifyOTP')?.addEventListener('click', async () => {
    const code = $('#accOTP').value.trim();
    if (code.length < 5) { showModalAlert('accAlertOTP', 'Masukkan kode 5 digit'); return; }

    const btn = $('#btnVerifyOTP');
    setLoading(btn, true, 'Memverifikasi...');
    $('#accAlertOTP').style.display = 'none';

    const d = await apiPost({ action: 'verify_otp', code });
    setLoading(btn, false);

    if (d.success) {
        showSuccessStep(d);
    } else if (d.need_2fa) {
        showStep('step-2fa');
        showModalAlert('accAlert2FA', d.message, 'info');
    } else {
        showModalAlert('accAlertOTP', d.message || 'Kode salah atau sudah kadaluarsa');
    }
});

$('#btnVerify2FA')?.addEventListener('click', async () => {
    const password = $('#acc2FA').value;
    if (!password) { showModalAlert('accAlert2FA', 'Masukkan password'); return; }

    const btn = $('#btnVerify2FA');
    setLoading(btn, true, 'Memverifikasi 2FA...');
    $('#accAlert2FA').style.display = 'none';

    const d = await apiPost({ action: 'verify_2fa', password });
    setLoading(btn, false);

    if (d.success) showSuccessStep(d);
    else showModalAlert('accAlert2FA', d.message || 'Password salah');
});

function showSuccessStep(d) {
    const name = [d.first_name, d.last_name].filter(Boolean).join(' ');
    $('#successInfo').innerHTML = `
        <strong>${name || 'Akun'}</strong>${d.username ? ' (@' + d.username + ')' : ''}<br>
        berhasil ditambahkan ke sistem!
    `;
    showStep('step-success');
    toast('Akun Telegram berhasil ditambahkan! 🎉');
}

$('#btnDoneAccount')?.addEventListener('click', () => {
    closeModal('modalAccount');
    loadAccounts();
    loadStats();
});

/* ═══════════════════════════════════════════════
   CONTACTS
══════════════════════════════════════════════ */
async function loadContacts() {
    const wrap = $('#contactsTable');
    wrap.innerHTML = '<div class="loading-state">⏳ Memuat kontak...</div>';

    const d = await apiGet({ action: 'get_contacts' });
    if (!d.success || !d.data.length) {
        wrap.innerHTML = '<div class="loading-state">📭 Belum ada kontak. Import CSV atau tambahkan manual.</div>';
        return;
    }

    const rows = d.data.map(c => `
        <tr>
            <td>${esc(c.display_name || '—')}</td>
            <td>${c.phone ? esc(c.phone) : '—'}</td>
            <td>${c.username ? '@' + esc(c.username) : '—'}</td>
            <td><span class="badge" style="background:rgba(79,126,255,0.12);color:#93c5fd">${esc(c.group_name)}</span></td>
            <td style="font-size:12px;color:var(--muted)">${formatDate(c.added_at)}</td>
            <td><button class="btn-icon" onclick="deleteContact(${c.id})">🗑</button></td>
        </tr>
    `).join('');

    wrap.innerHTML = `
        <table>
            <thead><tr><th>Nama</th><th>Nomor HP</th><th>Username</th><th>Grup</th><th>Ditambahkan</th><th>Hapus</th></tr></thead>
            <tbody>${rows}</tbody>
        </table>
    `;
}

async function deleteContact(id) {
    if (!confirm('Hapus kontak ini?')) return;
    const d = await apiPost({ action: 'delete_contact', id });
    if (d.success) { toast('Kontak dihapus'); loadContacts(); }
    else toast(d.message, 'error');
}

$('#btnAddContact')?.addEventListener('click', () => openModal('modalContact'));
$('#btnImportCSV')?.addEventListener('click', () => openModal('modalCSV'));

$('#btnSaveContact')?.addEventListener('click', async () => {
    const phone   = $('#ctcPhone').value;
    const uname   = $('#ctcUsername').value;
    if (!phone && !uname) { showModalAlert('contactAlert', 'Isi nomor HP atau username'); return; }

    const btn = $('#btnSaveContact');
    setLoading(btn, true, 'Menyimpan...');

    const d = await apiPost({
        action:       'add_contact',
        phone:        phone,
        username:     uname,
        display_name: $('#ctcName').value,
        group_name:   $('#ctcGroup').value || 'Default',
    });
    setLoading(btn, false);

    if (d.success) {
        toast('Kontak berhasil disimpan!');
        closeModal('modalContact');
        loadContacts();
        $('#ctcPhone').value = '';
        $('#ctcUsername').value = '';
        $('#ctcName').value = '';
    } else {
        showModalAlert('contactAlert', d.message || 'Gagal menyimpan');
    }
});

$('#btnUploadCSV')?.addEventListener('click', async () => {
    const file = $('#csvFile').files[0];
    if (!file) { showModalAlert('csvAlert', 'Pilih file CSV terlebih dahulu'); return; }

    const btn = $('#btnUploadCSV');
    setLoading(btn, true, 'Mengupload...');

    const fd = new FormData();
    fd.append('action', 'import_contacts');
    fd.append('csv_file', file);
    fd.append('group_name', $('#csvGroup').value || 'Import ' + new Date().toLocaleDateString('id'));

    const r = await fetch(API, { method: 'POST', body: fd });
    const d = await r.json();
    setLoading(btn, false);

    if (d.success) {
        toast(`✅ ${d.imported} kontak berhasil diimport!`, 'success');
        closeModal('modalCSV');
        loadContacts();
        loadStats();
    } else {
        showModalAlert('csvAlert', d.message || 'Import gagal');
    }
});

/* ═══════════════════════════════════════════════
   BROADCAST
══════════════════════════════════════════════ */
$('#campMessage')?.addEventListener('input', function() {
    $('#charCount').textContent = this.value.length + ' karakter';
    $('#previewMsg').textContent = this.value || 'Pesan Anda akan tampil di sini...';
});

// File drop
const fileDrop  = $('#fileDrop');
const fileInput = $('#mediaInput');
if (fileDrop) {
    fileDrop.addEventListener('click', () => fileInput.click());
    fileDrop.addEventListener('dragover', e => { e.preventDefault(); fileDrop.style.borderColor = 'var(--accent)'; });
    fileDrop.addEventListener('dragleave', () => fileDrop.style.borderColor = '');
    fileDrop.addEventListener('drop', e => {
        e.preventDefault();
        fileDrop.style.borderColor = '';
        if (e.dataTransfer.files[0]) handleMediaFile(e.dataTransfer.files[0]);
    });
}
fileInput?.addEventListener('change', () => { if (fileInput.files[0]) handleMediaFile(fileInput.files[0]); });

function handleMediaFile(file) {
    const preview = $('#mediaPreview');
    preview.style.display = 'block';
    if (file.type.startsWith('image/')) {
        const url = URL.createObjectURL(file);
        preview.innerHTML = `<img src="${url}" alt="preview"><p>📎 ${esc(file.name)}</p>`;
    } else {
        preview.innerHTML = `<p>📎 ${esc(file.name)} (${(file.size/1024).toFixed(1)} KB)</p>`;
    }
}

async function loadAccountChecklist() {
    const wrap = $('#accountCheckList');
    const d    = await apiGet({ action: 'get_accounts' });
    if (!d.success || !d.data.length) {
        wrap.innerHTML = '<div class="loading-state small">😥 Belum ada akun aktif</div>';
        return;
    }
    const active = d.data.filter(a => a.status === 'active');
    wrap.innerHTML = active.map(a => `
        <label class="check-item">
            <input type="checkbox" name="account_id" value="${a.id}" checked>
            <div>
                <div class="check-item-name">${esc(a.first_name)} ${esc(a.last_name || '')}</div>
                <div class="check-item-phone">${esc(a.phone)}</div>
            </div>
        </label>
    `).join('');
}

async function loadGroupOptions() {
    const sel = $('#campGroup');
    const d   = await apiGet({ action: 'get_contacts' });
    if (!d.success) return;
    const groups = [...new Set(d.data.map(c => c.group_name).filter(Boolean))];
    const opts   = ['<option value="">Semua kontak</option>'];
    groups.forEach(g => opts.push(`<option value="${esc(g)}">${esc(g)}</option>`));
    sel.innerHTML = opts.join('');
}

$('#broadcastForm')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn = $('#btnSendBroadcast');
    setLoading(btn, true, 'Memulai broadcast...');

    const accountIds = $$('#accountCheckList input:checked').map(c => c.value);
    if (!accountIds.length) { toast('Pilih minimal 1 akun pengirim', 'error'); setLoading(btn, false); return; }

    const fd = new FormData();
    fd.append('action',      'create_campaign');
    fd.append('name',        $('#campName').value);
    fd.append('message',     $('#campMessage').value);
    fd.append('delay',       $('#campDelay').value);
    const media = $('#mediaInput').files[0];
    if (media) fd.append('media', media);

    const r1 = await fetch(API, { method: 'POST', body: fd });
    const d1 = await r1.json();

    if (!d1.success) { toast(d1.message || 'Gagal membuat campaign', 'error'); setLoading(btn, false); return; }

    const d2 = await apiPost({
        action:      'start_broadcast',
        campaign_id: d1.campaign_id,
        account_ids: accountIds,
        group_name:  $('#campGroup').value,
    });
    setLoading(btn, false);

    if (d2.success) {
        toast('🚀 ' + d2.message, 'success', 5000);
        switchTab('history');
    } else {
        toast(d2.message || 'Gagal memulai broadcast', 'error');
    }
});

/* ═══════════════════════════════════════════════
   HISTORY
══════════════════════════════════════════════ */
async function loadHistory() {
    const wrap = $('#historyTable');
    wrap.innerHTML = '<div class="loading-state">⏳ Memuat riwayat...</div>';

    const d = await apiGet({ action: 'get_campaigns' });
    if (!d.success || !d.data.length) {
        wrap.innerHTML = '<div class="loading-state">📭 Belum ada campaign. Buat broadcast pertama Anda!</div>';
        return;
    }

    const rows = d.data.map(c => {
        const pct = c.total_target > 0 ? Math.round((+c.sent_count / +c.total_target) * 100) : 0;
        const canPause = c.status === 'running';
        const canResume = c.status === 'paused';
        return `
        <tr>
            <td>
                <div style="font-weight:600">${esc(c.name)}</div>
                <div style="font-size:11px;color:var(--muted);margin-top:2px">Delay: ${c.delay_seconds}s · ${c.media_type !== 'none' ? '📎 '+c.media_type : '📝 teks'}</div>
            </td>
            <td>${campaignStatusBadge(c.status)}</td>
            <td>
                <div style="display:flex;align-items:center;gap:8px">
                    <div style="flex:1;background:rgba(255,255,255,0.06);border-radius:100px;height:5px;min-width:60px">
                        <div style="height:100%;width:${pct}%;background:linear-gradient(90deg,var(--accent),var(--accent2));border-radius:100px"></div>
                    </div>
                    <span style="font-size:12px;color:var(--muted);white-space:nowrap">${pct}% (${c.sent_count}/${c.total_target})</span>
                </div>
            </td>
            <td style="font-size:12px;color:var(--success);font-weight:600">${c.sent_count}</td>
            <td style="font-size:12px;color:var(--danger);font-weight:600">${c.failed_count}</td>
            <td style="font-size:12px;color:var(--muted)">${formatDate(c.created_at)}</td>
            <td>
                <div style="display:flex;gap:6px;flex-wrap:wrap">
                    <button class="btn-icon primary" onclick="openCampaignDetail(${c.id}, '${esc(c.name)}')">🔍</button>
                    ${canPause ? `<button class="btn-icon" title="Pause" onclick="togglePause(${c.id})">⏸</button>` : ''}
                    ${canResume ? `<button class="btn-icon primary" title="Resume" onclick="togglePause(${c.id})">▶</button>` : ''}
                </div>
            </td>
        </tr>
    `;}).join('');

    wrap.innerHTML = `
        <table>
            <thead><tr><th>Campaign</th><th>Status</th><th>Progress</th><th>✓ Kirim</th><th>✕ Gagal</th><th>Dibuat</th><th>Aksi</th></tr></thead>
            <tbody>${rows}</tbody>
        </table>
    `;
}

async function togglePause(id) {
    const d = await apiPost({ action: 'pause_campaign', id });
    if (d.success) {
        toast(d.new_status === 'paused' ? '⏸ Campaign dijeda' : '▶ Campaign dilanjutkan', 'info');
        loadHistory();
    } else {
        toast(d.message || 'Gagal', 'error');
    }
}

async function openCampaignDetail(id, name) {
    $('#detailCampTitle').textContent = '📊 ' + name;
    $('#logTableWrap').innerHTML = '<div class="loading-state">⏳ Memuat log...</div>';
    openModal('modalCampaignDetail');

    const d = await apiGet({ action: 'get_campaign_logs', campaign_id: id });
    if (!d.success) { $('#logTableWrap').innerHTML = '<div class="loading-state">❌ Gagal</div>'; return; }

    const camp = d.campaign;
    const pct  = camp.total_target > 0 ? Math.round((+camp.sent_count / +camp.total_target) * 100) : 0;
    $('#campProgressBar').style.width = pct + '%';
    $('#campProgressStats').innerHTML = `
        Total: <span>${camp.total_target}</span> &nbsp;
        Terkirim: <span style="color:var(--success)">${camp.sent_count}</span> &nbsp;
        Gagal: <span style="color:var(--danger)">${camp.failed_count}</span> &nbsp;
        Progress: <span>${pct}%</span>
    `;

    if (!d.logs.length) {
        $('#logTableWrap').innerHTML = '<div class="loading-state">📭 Belum ada log</div>';
        return;
    }

    const rows = d.logs.map(l => `
        <tr>
            <td style="font-size:12px">${esc(l.recipient)}</td>
            <td style="font-size:12px">${esc(l.account_name || l.account_phone)}</td>
            <td>${logStatusBadge(l.status)}</td>
            <td style="font-size:11px;color:var(--muted)">${l.error_msg ? esc(l.error_msg.substring(0,60)) : '—'}</td>
            <td style="font-size:11px;color:var(--muted)">${l.sent_at ? formatDate(l.sent_at) : '—'}</td>
        </tr>
    `).join('');

    $('#logTableWrap').innerHTML = `
        <table>
            <thead><tr><th>Penerima</th><th>Akun</th><th>Status</th><th>Error</th><th>Waktu</th></tr></thead>
            <tbody>${rows}</tbody>
        </table>
    `;
}

/* ═══════════════════════════════════════════════
   UTILITIES
══════════════════════════════════════════════ */
function esc(str) {
    if (!str) return '';
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function formatDate(dt) {
    if (!dt) return '—';
    return new Date(dt).toLocaleString('id-ID', { day:'2-digit', month:'short', year:'numeric', hour:'2-digit', minute:'2-digit' });
}

function statusBadge(s) {
    const map = { active: ['badge-active','✅ Aktif'], pending: ['badge-pending','⏳ Pending'], banned: ['badge-banned','🚫 Banned'], disconnected: ['badge-disc','⚠ Putus'] };
    const [cls, lbl] = map[s] || ['badge-disc', s];
    return `<span class="badge ${cls}">${lbl}</span>`;
}

function campaignStatusBadge(s) {
    const map = { draft: ['badge-draft','📝 Draft'], running: ['badge-running','▶ Berjalan'], done: ['badge-done','✅ Selesai'], failed: ['badge-failed','❌ Gagal'], paused: ['badge-pending','⏸ Dijeda'] };
    const [cls, lbl] = map[s] || ['badge-draft', s];
    return `<span class="badge ${cls}">${lbl}</span>`;
}

function logStatusBadge(s) {
    const map = { sent: ['badge-active','✓ Terkirim'], pending: ['badge-pending','⏳'], failed: ['badge-failed','✕ Gagal'], blocked: ['badge-banned','🚫 Diblokir'] };
    const [cls, lbl] = map[s] || ['badge-draft', s];
    return `<span class="badge ${cls}">${lbl}</span>`;
}

/* Auto-refresh history if running campaign */
let historyInterval = null;
function startHistoryPolling() {
    historyInterval = setInterval(() => {
        if ($('#tab-history').classList.contains('active')) loadHistory();
    }, 8000);
}

/* ── INIT ── */
loadStats();
startHistoryPolling();
