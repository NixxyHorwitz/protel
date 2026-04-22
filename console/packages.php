<?php
require_once __DIR__ . '/../core/auth.php';
check_auth();
require_once __DIR__ . '/../core/layout.php';

$msg = $msg_type = '';

// Silent Auto-Migration for duration_days if not exists
try { $pdo->exec("ALTER TABLE packages ADD COLUMN duration_days INT DEFAULT 30 AFTER price"); } catch (Exception $e) {}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $name     = trim(strip_tags($_POST['name'] ?? ''));
        $price    = max(0, filter_input(INPUT_POST, 'price', FILTER_VALIDATE_INT) ?? 0);
        $duration = max(1, filter_input(INPUT_POST, 'duration_days', FILTER_VALIDATE_INT) ?? 30);
        $max_sess = max(1, filter_input(INPUT_POST, 'max_sessions', FILTER_VALIDATE_INT) ?? 1);
        
        if (empty($name)) {
            $msg = "Package name cannot be empty."; $msg_type = 'warning';
        } else {
            $pdo->prepare("INSERT INTO packages (name, price, duration_days, max_sessions) VALUES (?,?,?,?)")->execute([$name, $price, $duration, $max_sess]);
            $msg = "Package added."; $msg_type = 'success';
        }
    } elseif ($action === 'edit') {
        $id       = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        $name     = trim(strip_tags($_POST['name'] ?? ''));
        $price    = max(0, filter_input(INPUT_POST, 'price', FILTER_VALIDATE_INT) ?? 0);
        $duration = max(1, filter_input(INPUT_POST, 'duration_days', FILTER_VALIDATE_INT) ?? 30);
        $max_sess = max(1, filter_input(INPUT_POST, 'max_sessions', FILTER_VALIDATE_INT) ?? 1);
        if ($id > 0 && $name) {
            $pdo->prepare("UPDATE packages SET name=?,price=?,duration_days=?,max_sessions=? WHERE id=?")->execute([$name, $price, $duration, $max_sess, $id]);
            $msg = "Package updated."; $msg_type = 'success';
        }
    } elseif ($action === 'delete') {
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        if ($id > 0 && $id != 1) {
            // Move users to Free Plan
            $pdo->prepare("UPDATE users SET package_id=1 WHERE package_id=?")->execute([$id]);
            $pdo->prepare("DELETE FROM packages WHERE id=?")->execute([$id]);
            $msg = "Package deleted."; $msg_type = 'danger';
        }
    }
}

$packages = $pdo->query("SELECT p.*, (SELECT COUNT(*) FROM users WHERE package_id=p.id) AS user_count FROM packages p ORDER BY p.price, p.id")->fetchAll();

load_header('Packages');
?>

<?php if ($msg): ?>
<div class="alert alert-<?= $msg_type ?> mb-3"><i class="fas fa-circle-info"></i> <?= h($msg) ?></div>
<?php endif; ?>

<div class="page-header">
    <div style="display:flex;justify-content:space-between;align-items:flex-end">
        <div>
            <h1>Subscription Packages</h1>
            <p>Define package tiers, duration, and session limits</p>
        </div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">
            <i class="fas fa-plus"></i> New Package
        </button>
    </div>
</div>

<div class="card-c">
    <table class="tbl">
        <thead>
            <tr>
                <th style="padding-left:20px;width:60px">ID</th>
                <th>Name</th>
                <th>Price (Coins)</th>
                <th>Duration (Days)</th>
                <th>Max Sessions</th>
                <th>Users</th>
                <th style="text-align:right;padding-right:20px">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($packages as $p): ?>
            <tr>
                <td style="padding-left:20px;color:var(--mut)">#<?= (int)$p['id'] ?></td>
                <td>
                    <span style="font-weight:600"><?= h($p['name']) ?></span>
                    <?php if ($p['id'] == 1): ?><span class="bd bd-acc" style="font-size:10px;margin-left:6px">Default</span><?php endif; ?>
                </td>
                <td><span class="mono" style="color:var(--warn)"><i class="fas fa-coins me-1" style="font-size:10px"></i><?= number_format((int)$p['price']) ?></span></td>
                <td><span class="bd bd-ok"><i class="fas fa-calendar-alt me-1"></i><?= (int)($p['duration_days'] ?? 30) ?> Days</span></td>
                <td><?= (int)$p['max_sessions'] ?></td>
                <td><?= (int)$p['user_count'] ?> User(s)</td>
                <td style="text-align:right;padding-right:20px">
                    <div style="display:flex;justify-content:flex-end;gap:4px">
                        <button class="ab acc" onclick='openEdit(<?= (int)$p["id"] ?>,"<?= h(addslashes($p["name"])) ?>",<?= (int)$p["price"] ?>,<?= (int)($p["duration_days"] ?? 30) ?>,<?= (int)$p["max_sessions"] ?>)' title="Edit"><i class="fas fa-pen"></i></button>
                        <?php if ($p['id'] != 1): ?>
                        <form method="POST" class="d-inline" onsubmit="return confirm('Delete this package? Users will be moved to Free Plan.')">
                            <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                            <button class="ab red" title="Delete"><i class="fas fa-trash"></i></button>
                        </form>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Add Modal -->
<div class="modal fade" id="addModal" tabindex="-1">
<div class="modal-dialog modal-sm modal-dialog-centered">
<div class="modal-content" style="background:var(--bg);border:1px solid var(--border);border-radius:12px;box-shadow:0 10px 30px rgba(0,0,0,0.5)">
<form method="POST">
    <input type="hidden" name="action" value="create">
    <div class="modal-header" style="border-bottom:1px solid var(--border)">
        <h6 class="modal-title" style="color:var(--text);font-weight:700">New Package</h6>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body">
        <div class="mb-3">
            <label class="fl">Package Name</label>
            <input type="text" name="name" class="form-control" maxlength="50" placeholder="e.g. Premium" required>
        </div>
        <div class="mb-3">
            <label class="fl">Price (Coins)</label>
            <input type="number" name="price" class="form-control" value="0" min="0">
        </div>
        <div class="mb-3">
            <label class="fl">Duration (Days)</label>
            <input type="number" name="duration_days" class="form-control" value="30" min="1" max="3650">
        </div>
        <div class="mb-0">
            <label class="fl">Max Sessions (Bots)</label>
            <input type="number" name="max_sessions" class="form-control" value="1" min="1" max="100">
        </div>
    </div>
    <div class="modal-footer" style="border-top:1px solid var(--border);background:var(--bg);">
        <button type="button" class="btn btn-secondary border text-white" style="background:var(--hover)" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary">Add Package</button>
    </div>
</form>
</div></div></div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
<div class="modal-dialog modal-sm modal-dialog-centered">
<div class="modal-content" style="background:var(--bg);border:1px solid var(--border);border-radius:12px;box-shadow:0 10px 30px rgba(0,0,0,0.5)">
<form method="POST">
    <input type="hidden" name="action" value="edit">
    <input type="hidden" name="id" id="ep_id">
    <div class="modal-header" style="border-bottom:1px solid var(--border)">
        <h6 class="modal-title" style="color:var(--text);font-weight:700">Edit Package</h6>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body">
        <div class="mb-3">
            <label class="fl">Package Name</label>
            <input type="text" name="name" id="ep_name" class="form-control" maxlength="50" required>
        </div>
        <div class="mb-3">
            <label class="fl">Price (Coins)</label>
            <input type="number" name="price" id="ep_price" class="form-control" min="0">
        </div>
        <div class="mb-3">
            <label class="fl">Duration (Days)</label>
            <input type="number" name="duration_days" id="ep_dur" class="form-control" min="1" max="3650">
        </div>
        <div class="mb-0">
            <label class="fl">Max Sessions (Bots)</label>
            <input type="number" name="max_sessions" id="ep_max" class="form-control" min="1" max="100">
        </div>
    </div>
    <div class="modal-footer" style="border-top:1px solid var(--border);background:var(--bg);">
        <button type="button" class="btn btn-secondary border text-white" style="background:var(--hover)" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Changes</button>
    </div>
</form>
</div></div></div>

<script>
function openEdit(id, name, price, dur, max) {
    document.getElementById('ep_id').value    = id;
    document.getElementById('ep_name').value  = name;
    document.getElementById('ep_price').value = price;
    document.getElementById('ep_dur').value   = dur;
    document.getElementById('ep_max').value   = max;
    new bootstrap.Modal(document.getElementById('editModal')).show();
}
</script>
<?php load_footer(); ?>
