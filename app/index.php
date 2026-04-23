<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>ProTel Broadcast Control</title>
    <!-- Telegram Mini App JS -->
    <script src="https://telegram.org/js/telegram-web-app.js"></script>
    <style>
        :root {
            --bg-color: var(--tg-theme-bg-color, #090d18);
            --text-color: var(--tg-theme-text-color, #f4f6fa);
            --hint-color: var(--tg-theme-hint-color, #708499);
            --link-color: var(--tg-theme-link-color, #cc3333); /* Brand Red */
            --button-color: var(--tg-theme-button-color, #cc3333);
            --button-text-color: var(--tg-theme-button-text-color, #ffffff);
            --secondary-bg-color: var(--tg-theme-secondary-bg-color, #111825);
            --border-color: #1a2233;
            --ok: #67c23a;
            --err: #f56c6c;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background-color: var(--bg-color);
            color: var(--text-color);
            margin: 0;
            padding: 16px;
            font-size: 14px;
        }

        * { box-sizing: border-box; }

        h1, h2, h3 { color: var(--text-color); margin-top: 0; }
        
        /* Dashboard Stats */
        .dash-header {
            background-color: var(--secondary-bg-color);
            border: 1px solid var(--border-color);
            padding: 15px;
            border-radius: 12px;
            margin-bottom: 20px;
            text-align: center;
        }

        .tabs {
            display: flex;
            background: var(--secondary-bg-color);
            border-radius: 8px;
            margin-bottom: 20px;
            padding: 4px;
        }
        .tab {
            flex: 1;
            text-align: center;
            padding: 10px;
            cursor: pointer;
            border-radius: 6px;
            color: var(--hint-color);
            font-weight: bold;
            transition: 0.2s;
        }
        .tab.active {
            background: var(--button-color);
            color: var(--button-text-color);
        }

        /* Card Elements */
        .card {
            background-color: var(--secondary-bg-color);
            border: 1px solid var(--border-color);
            padding: 15px;
            border-radius: 12px;
            margin-bottom: 12px;
        }
        
        /* Form Elements */
        label { display: block; margin-bottom: 6px; font-weight: bold; color: var(--hint-color); }
        select, textarea {
            width: 100%;
            padding: 12px;
            background: var(--bg-color);
            border: 1px solid var(--border-color);
            color: var(--text-color);
            border-radius: 8px;
            font-size: 14px;
            margin-bottom: 15px;
            outline: none;
        }
        select:focus, textarea:focus { border-color: var(--link-color); }
        
        /* Buttons */
        .btn {
            background-color: var(--button-color);
            color: var(--button-text-color);
            border: none;
            padding: 12px;
            border-radius: 8px;
            width: 100%;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: opacity 0.2s;
        }
        .btn:active { opacity: 0.8; }
        
        .btn-small { padding: 6px 12px; font-size: 12px; border-radius: 6px; width: auto; }
        .btn-pause { background-color: #e6a23c; }
        .btn-stop { background-color: #f56c6c; }
        .btn-process { background-color: #67c23a; }

        /* Status Badge */
        .badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .badge.process { background: rgba(103, 194, 58, 0.2); color: #67c23a; }
        .badge.paused { background: rgba(230, 162, 60, 0.2); color: #e6a23c; }
        .badge.failed { background: rgba(245, 108, 108, 0.2); color: #f56c6c; }
        .badge.completed { background: rgba(64, 158, 255, 0.2); color: #409eff; }
        .badge.draft { background: rgba(112, 132, 153, 0.2); color: #708499; }

        .progress-bar {
            width: 100%;
            height: 6px;
            background: var(--bg-color);
            border-radius: 3px;
            margin-top: 8px;
            overflow: hidden;
        }
        .progress-fill {
            height: 100%;
            background: var(--link-color);
            transition: width 0.3s ease;
        }
    </style>
</head>
<body>

    <div class="dash-header">
        <h2 style="margin:0;color:var(--link-color);">ProTel Control</h2>
        <div style="font-size: 12px; color: var(--hint-color); margin-top: 5px;" id="userInfo">Loading profile...</div>
    </div>

    <div class="tabs">
        <div class="tab active" onclick="switchTab('tasks')">📊 Live Tasks</div>
        <div class="tab" onclick="switchTab('create')">➕ Buat Broadcast</div>
    </div>

    <!-- Active Tasks Tab -->
    <div id="view-tasks">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
            <h3 style="margin:0;">Task Berjalan</h3>
            <button onclick="loadDashboard()" class="btn btn-small" style="background:var(--secondary-bg-color); border:1px solid var(--border-color); color:var(--text-color);">🔄 Refresh</button>
        </div>
        <div id="taskList">
            <div class="card" style="text-align:center;color:var(--hint-color);">Loading tasks...</div>
        </div>
    </div>

    <!-- Create Broadcast Tab -->
    <div id="view-create" style="display: none;">
        <h3>Set Up Broadcast Baru</h3>
        <div class="card">
            <label>Pilih Akun / Session</label>
            <select id="session_id">
                <option value="">-- Pilih Akun --</option>
            </select>
            
            <label>Pesan Broadcast</label>
            <textarea id="message" rows="5" placeholder="Tuliskan pesan promosi atau informasi Anda di sini..."></textarea>
            
            <button class="btn" onclick="submitBroadcast()" id="btnSubmit">Kirim & Mulai Broadcast</button>
        </div>
        <p style="font-size: 12px; color: var(--hint-color); text-align: center;">Task broadcast akan langsung dieksekusi secara instan.</p>
    </div>

<script>
    const tg = window.Telegram.WebApp;
    tg.expand(); // Fill screen
    tg.ready();

    // Default init data for testing (only in dev, Telegram injects securely on load)
    const initData = tg.initData || '';

    function apiCall(action, data = null) {
        return fetch(`api.php?action=${action}`, {
            method: data ? 'POST' : 'GET',
            headers: {
                'X-TG-INIT-DATA': initData,
                ...(data ? {'Content-Type': 'application/x-www-form-urlencoded'} : {})
            },
            body: data ? new URLSearchParams(data) : null
        }).then(r => r.json());
    }

    function switchTab(tab) {
        document.getElementById('view-tasks').style.display = tab === 'tasks' ? 'block' : 'none';
        document.getElementById('view-create').style.display = tab === 'create' ? 'block' : 'none';
        document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
        event.currentTarget.classList.add('active');
        if(tab === 'tasks') loadDashboard();
    }

    function loadDashboard() {
        apiCall('get_dashboard').then(res => {
            if (res.error) {
                tg.showAlert("Error: " + res.error);
                return;
            }
            // Populate User
            document.getElementById('userInfo').innerText = `${res.user.name} | Pkg: ${res.user.pkg_name} | Max: ${res.user.max_sessions} Sesi`;
            
            // Populate Dropdown
            let sel = document.getElementById('session_id');
            sel.innerHTML = '<option value="">-- Pilih Akun --</option>';
            res.sessions.forEach(s => {
                sel.innerHTML += `<option value="${s.id}">📱 ${s.phone_number}</option>`;
            });

            // Populate Tasks
            let tList = document.getElementById('taskList');
            tList.innerHTML = '';
            if (res.broadcasts.length === 0) {
                tList.innerHTML = `<div class="card" style="text-align:center;color:var(--hint-color);">Belum ada antrian task broadcast.</div>`;
            } else {
                res.broadcasts.forEach(b => {
                    let pct = b.target_count > 0 ? Math.floor((b.sent_count / b.target_count) * 100) : 0;
                    
                    let actions = '';
                    if (b.status === 'process') {
                        actions = `<button class="btn btn-small btn-pause" onclick="updateStatus(${b.id}, 'paused')">⏸ Pause</button>
                                   <button class="btn btn-small btn-stop" onclick="updateStatus(${b.id}, 'failed')">⏹ Stop</button>`;
                    } else if (b.status === 'paused' || b.status === 'draft') {
                        actions = `<button class="btn btn-small btn-process" onclick="updateStatus(${b.id}, 'process')">▶️ Lanjutkan</button>
                                   <button class="btn btn-small btn-stop" onclick="updateStatus(${b.id}, 'failed')">⏹ Hentikan</button>`;
                    }

                    tList.innerHTML += `
                    <div class="card">
                        <div style="display:flex; justify-content:space-between; margin-bottom:8px;">
                            <strong>Task #${b.id} 📱 ${b.phone_number}</strong>
                            <span class="badge ${b.status}">${b.status}</span>
                        </div>
                        <div style="font-size:12px;color:var(--hint-color); margin-bottom:10px;">
                            Pesan: ${b.message.substring(0, 30)}...<br>
                            Terkirim: <b style="color:var(--ok)">${b.sent_count} / ${b.target_count}</b>
                            ${b.failed_count > 0 ? `<br><span style="color:var(--err)">Gagal/Skip: <b>${b.failed_count}</b> kontak</span>` : ''}
                        </div>
                        <div class="progress-bar"><div class="progress-fill" style="width:${pct}%"></div></div>
                        <div style="display:flex; gap:8px; margin-top:12px; justify-content:flex-end;">
                            ${actions}
                        </div>
                    </div>`;
                });
            }
        });
    }

    function submitBroadcast() {
        let sid = document.getElementById('session_id').value;
        let msg = document.getElementById('message').value;
        if(!sid || !msg.trim()) {
            tg.showAlert("Pilih sesi akun dan isi pesannya!");
            return;
        }
        
        let btn = document.getElementById('btnSubmit');
        btn.innerText = "Memproses..."; btn.disabled = true;

        apiCall('create_broadcast', { session_id: sid, message: msg }).then(res => {
            btn.innerText = "Kirim & Mulai Broadcast"; btn.disabled = false;
            if(res.error) {
                tg.showAlert("Gagal: " + res.error);
            } else {
                document.getElementById('message').value = '';
                tg.showPopup({title: 'Berhasil', message: 'Task Broadcast sedang berjalan di server!', buttons: [{type: 'ok'}]});
                document.querySelector('.tabs .tab:first-child').click(); // switch back
                loadDashboard();
            }
        });
    }

    function updateStatus(id, newStatus) {
        tg.showConfirm(`Yakin ingin mengubah status task ke ${newStatus}?`, function(yes) {
            if(yes) {
                apiCall('update_status', { id: id, status: newStatus }).then(res => {
                    if(res.error) tg.showAlert("Gagal: "+res.error);
                    else {
                        loadDashboard();
                        if(newStatus === 'process') triggerWorker();
                    }
                });
            }
        });
    }

    let isWorking = false;
    function triggerWorker() {
        if(isWorking) return;
        isWorking = true;
        
        apiCall('execute_batch').then(res => {
            isWorking = false;
            if(res.status === 'batch_processed') {
                loadDashboard();
                triggerWorker(); // Loop immediately for the next batch!
            } else if(res.status === 'completed') {
                loadDashboard();
                tg.showPopup({title: 'Selesai', message: 'Task Broadcast telah tuntas 100%!', buttons: [{type: 'ok'}]});
            } else if(res.status === 'error') {
                tg.showAlert("Worker Error: " + res.message);
                loadDashboard();
            } else {
                // Idle or no tasks. Do nothing.
            }
        }).catch(e => {
            isWorking = false;
        });
    }

    // Auto-refresh tasks slightly less aggressively (every 5 sec)
    setInterval(() => {
        if (document.getElementById('view-tasks').style.display !== 'none') {
            loadDashboard();
            triggerWorker(); // attempt to start worker if there are pending tasks
        }
    }, 5000);

    // Initial Load
    if (initData) {
        loadDashboard();
        setTimeout(triggerWorker, 2000); // start worker loop shortly after load
    } else {
        document.getElementById('taskList').innerHTML = `<div class="card" style="text-align:center;color:red;">Error: Harus dibuka dari Telegram App</div>`;
    }
</script>
</body>
</html>
