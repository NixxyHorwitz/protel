<?php
require_once __DIR__ . '/../core/auth.php';
check_auth();
require_once __DIR__ . '/../core/layout.php';

$msg = $msg_type = '';

// Silent Auto-Migration for user package expiration
try { $pdo->exec("ALTER TABLE users ADD COLUMN package_expired_at DATETIME NULL AFTER package_id"); } catch (Exception $e) {}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        if ($id > 0) {
            $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM user_sessions WHERE telegram_id = (SELECT telegram_id FROM users WHERE id = ? LIMIT 1)")->execute([$id]);
            write_log('ADMIN', "Deleted user ID $id");
            $msg = "User deleted."; $msg_type = 'danger';
        }
    } elseif ($action === 'update') {
        $id       = filter_input(INPUT_POST, 'id',         FILTER_VALIDATE_INT);
        $coins    = filter_input(INPUT_POST, 'coins',      FILTER_VALIDATE_INT);
        $pkg_id   = filter_input(INPUT_POST, 'package_id', FILTER_VALIDATE_INT);
        $exp_date = trim($_POST['package_expired_at'] ?? '');

        if ($id > 0) {
            $exp_val = empty($exp_date) ? null : date('Y-m-d H:i:s', strtotime($exp_date));
            $pdo->prepare("UPDATE users SET coins = ?, package_id = ?, package_expired_at = ? WHERE id = ?")
                ->execute([$coins ?? 0, $pkg_id ?? 1, $exp_val, $id]);
            write_log('ADMIN', "Updated user ID $id: coins=$coins, pkg=$pkg_id, exp=" . ($exp_val ?: 'Indefinite'));
            $msg = "User active package detailed updated."; $msg_type = 'success';
        }
    }
}

$search    = strip_tags($_GET['q'] ?? '');
$page      = max(1, (int)($_GET['page'] ?? 1));
$per_page  = 20;
$offset    = ($page - 1) * $per_page;

if ($search) {
    $like = "%$search%";
    $total = $pdo->prepare("SELECT COUNT(*) FROM users WHERE telegram_id LIKE ? OR name LIKE ?");
    $total->execute([$like, $like]);
    $total = (int)$total->fetchColumn();
    $stmt  = $pdo->prepare("SELECT u.*, p.name AS pkg_name, p.duration_days FROM users u LEFT JOIN packages p ON u.package_id=p.id WHERE u.telegram_id LIKE ? OR u.name LIKE ? ORDER BY u.id DESC LIMIT $per_page OFFSET $offset");
    $stmt->execute([$like, $like]);
} else {
    $total = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $stmt  = $pdo->prepare("SELECT u.*, p.name AS pkg_name, p.duration_days FROM users u LEFT JOIN packages p ON u.package_id=p.id ORDER BY u.id DESC LIMIT $per_page OFFSET $offset");
    $stmt->execute();
}
$users      = $stmt->fetchAll();
$total_pages = max(1, (int)ceil($total / $per_page));
$packages   = $pdo->query("SELECT id, name FROM packages ORDER BY price")->fetchAll();

load_header('Bot Users');
?>

<?php if ($msg): ?>
<div class="alert alert-<?= $msg_type ?> mb-3"><i class="fas fa-circle-info"></i> <?= h($msg) ?></div>
<?php endif; ?>

<div class="page-header">
    <div style="display:flex;justify-content:space-between;align-items:flex-end">
        <div>
            <h1>Manage Bot Users</h1>
            <p>Total <?= number_format($total) ?> users registered via Telegram Bot</p>
        </div>
    </div>
</div>

<div class="card-c mb-4">
    <div class="cb" style="padding:16px 20px">
        <form method="GET" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
            <div style="flex:1;min-width:200px">
                <input type="text" name="q" class="form-control" placeholder="Search ID or Name…" value="<?= h($search) ?>">
            </div>
            <div style="display:flex;gap:4px">
                <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Search</button>
                <?php if ($search): ?><a href="users.php" class="btn btn-secondary border"><i class="fas fa-xmark"></i></a><?php endif; ?>
            </div>
        </form>
    </div>
</div>

<div class="card-c">
    <table class="tbl">
        <thead>
            <tr>
                <th style="padding-left:20px;width:50px">#</th>
                <th>User Identity</th>
                <th>Balance (Coins)</th>
                <th>Active Package</th>
                <th>Package Validity</th>
                <th>Joined Date</th>
                <th style="text-align:right;padding-right:20px">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($users): foreach ($users as $i => $u): 
                // Check string or null safely
                $expired_raw = $u['package_expired_at'] ?? null;
                $is_expired = false;
                $exp_label = "Indefinite / Free";
                
                if ($expired_raw) {
                    $ts_exp = strtotime($expired_raw);
                    if ($ts_exp > time()) {
                        $diff = $ts_exp - time();
                        $diff_days = floor($diff / (60*60*24));
                        $exp_label = date('d M Y', $ts_exp) . " <span style='color:var(--ok)'>(" . $diff_days . "d left)</span>";
                    } else {
                        $is_expired = true;
                        $exp_label = date('d M Y', $ts_exp) . " <span style='color:var(--err)'>(EXPIRED)</span>";
                    }
                }
            ?>
            <tr>
                <td style="padding-left:20px;color:var(--mut)"><?= $offset + $i + 1 ?></td>
                <td>
                    <div style="font-weight:600"><?= h($u['name'] ?? 'Unknown User') ?></div>
                    <code class="mono" style="font-size:11px;color:var(--mut)"><?= h($u['telegram_id']) ?></code>
                </td>
                <td><span class="mono" style="color:var(--warn);font-weight:600"><i class="fas fa-coins me-1" style="font-size:10px"></i><?= number_format((int)$u['coins']) ?></span></td>
                <td>
                    <span class="bd bd-acc"><?= h($u['pkg_name'] ?? 'Free Default') ?></span>
                </td>
                <td style="font-size:12px;color:var(--sub)">
                    <?= $exp_label ?>
                </td>
                <td style="color:var(--mut);font-size:12px">
                    <?= date('d M Y, H:i', strtotime($u['created_at'])) ?>
                </td>
                <td style="text-align:right;padding-right:20px">
                    <div style="display:flex;justify-content:flex-end;gap:4px">
                        <?php 
                            $raw_date = $expired_raw ? date('Y-m-d\TH:i', strtotime($expired_raw)) : '';
                        ?>
                        <button class="ab acc" onclick='openEdit(<?= (int)$u["id"] ?>,<?= (int)$u["coins"] ?>,<?= (int)($u["package_id"] ?? 1) ?>,"<?= $raw_date ?>")' title="Manage User/Package"><i class="fas fa-sliders-h"></i></button>
                        <form method="POST" class="d-inline" onsubmit="return confirm('Delete this user completely, including all sessions?')">
                            <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                            <button class="ab red" title="Delete"><i class="fas fa-trash"></i></button>
                        </form>
                    </div>
                </td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="7" style="text-align:center;padding:40px;color:var(--mut)"><i class="fas fa-users" style="font-size:28px;opacity:.3;display:block;margin-bottom:8px"></i>No users found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
    
    <?php if ($total_pages > 1): ?>
    <div style="display:flex;justify-content:space-between;align-items:center;padding:12px 20px;border-top:1px solid var(--border)">
        <span style="font-size:12px;color:var(--mut)">Page <?= $page ?>/<?= $total_pages ?></span>
        <div style="display:flex;gap:3px">
            <?php for ($p = 1; $p <= $total_pages; $p++): ?>
            <a href="?page=<?= $p ?><?= $search ? '&q='.urlencode($search) : '' ?>" class="pg <?= $p == $page ? 'active' : '' ?>"><?= $p ?></a>
            <?php endfor; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Edit User/Package Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
<div class="modal-dialog modal-dialog-centered">
<div class="modal-content" style="background:var(--bg);border:1px solid var(--border);border-radius:12px;box-shadow:0 10px 30px rgba(0,0,0,0.5)">
<form method="POST">
    <input type="hidden" name="action" value="update">
    <input type="hidden" name="id" id="e_id">
    <div class="modal-header" style="border-bottom:1px solid var(--border)">
        <h6 class="modal-title" style="color:var(--text);font-weight:700">Manage User Package</h6>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body">
        <div class="row g-3">
            <div class="col-md-6">
                <div class="mb-3">
                    <label class="fl">Coins Balance</label>
                    <input type="number" name="coins" id="e_coins" class="form-control" min="0" required>
                </div>
            </div>
            <div class="col-md-6">
                <div class="mb-3">
                    <label class="fl">Active Package</label>
                    <select name="package_id" id="e_pkg" class="form-select">
                        <?php foreach ($packages as $p): ?>
                        <option value="<?= (int)$p['id'] ?>"><?= h($p['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
        <hr style="border-color:var(--border);margin: 10px 0 20px 0;">
        <div class="mb-2">
            <label class="fl">Package Expiration Date/Time</label>
            <input type="datetime-local" name="package_expired_at" id="e_exp" class="form-control">
            <div style="font-size:11px;color:var(--mut);margin-top:4px"><i class="fas fa-info-circle me-1"></i>Leave blank for indefinite/free lifetime packages. To revoke a package, set the date to yesterday.</div>
        </div>
    </div>
    <div class="modal-footer" style="border-top:1px solid var(--border);background:var(--bg);">
        <button type="button" class="btn btn-secondary border text-white" style="background:var(--hover)" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary">Save User Data</button>
    </div>
</form>
</div></div></div>

<script>
function openEdit(id, coins, pkg, exp) {
    document.getElementById('e_id').value    = id;
    document.getElementById('e_coins').value = coins;
    document.getElementById('e_pkg').value   = pkg;
    document.getElementById('e_exp').value   = exp;
    new bootstrap.Modal(document.getElementById('editModal')).show();
}
</script>
<?php load_footer(); ?>
