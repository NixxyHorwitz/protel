<?php
require_once __DIR__ . '/../core/auth.php';
check_auth();
require_once __DIR__ . '/../core/layout.php';

$msg = $msg_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $name     = trim(strip_tags($_POST['name'] ?? ''));
        $price    = max(0, filter_input(INPUT_POST, 'price',       FILTER_VALIDATE_INT) ?? 0);
        $max_sess = max(1, filter_input(INPUT_POST, 'max_sessions', FILTER_VALIDATE_INT) ?? 1);
        if ($name && strlen($name) <= 50) {
            $pdo->prepare("INSERT INTO packages (name, price, max_sessions) VALUES (?,?,?)")->execute([$name, $price, $max_sess]);
            $msg = "Package added."; $msg_type = 'success';
        }
    } elseif ($action === 'edit') {
        $id       = filter_input(INPUT_POST, 'id',          FILTER_VALIDATE_INT);
        $name     = trim(strip_tags($_POST['name'] ?? ''));
        $price    = max(0, filter_input(INPUT_POST, 'price',       FILTER_VALIDATE_INT) ?? 0);
        $max_sess = max(1, filter_input(INPUT_POST, 'max_sessions', FILTER_VALIDATE_INT) ?? 1);
        if ($id > 0 && $name && strlen($name) <= 50) {
            $pdo->prepare("UPDATE packages SET name=?,price=?,max_sessions=? WHERE id=?")->execute([$name, $price, $max_sess, $id]);
            $msg = "Package updated."; $msg_type = 'success';
        }
    } elseif ($action === 'delete') {
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        if ($id > 0 && $id != 1) {
            // Move users to Free Plan first
            $pdo->prepare("UPDATE users SET package_id=1 WHERE package_id=?")->execute([$id]);
            $pdo->prepare("DELETE FROM packages WHERE id=?")->execute([$id]);
            $msg = "Package deleted."; $msg_type = 'danger';
        }
    }
}

$packages = $pdo->query("SELECT p.*, (SELECT COUNT(*) FROM users WHERE package_id=p.id) AS user_count FROM packages p ORDER BY p.price")->fetchAll();

load_header('Packages');
?>

<?php if ($msg): ?>
<div class="alert alert-<?= $msg_type ?> mb-3"><i class="fas fa-circle-info"></i> <?= h($msg) ?></div>
<?php endif; ?>

<div class="page-header">
    <div><h1>Subscription Packages</h1><p>Define package tiers and session limits</p></div>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addModal">
        <i class="fas fa-plus"></i> New Package
    </button>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table">
            <thead><tr>
                <th>ID</th><th>Name</th><th>Price (Coins)</th><th>Max Sessions</th><th>Users</th><th class="text-end">Actions</th>
            </tr></thead>
            <tbody>
            <?php foreach ($packages as $p): ?>
            <tr>
                <td style="color:var(--text-muted)"><?= (int)$p['id'] ?></td>
                <td><span class="fw-600"><?= h($p['name']) ?></span><?= $p['id']==1 ? ' <span class="badge badge-secondary">Default</span>' : '' ?></td>
                <td><?= number_format((int)$p['price']) ?></td>
                <td><?= (int)$p['max_sessions'] ?></td>
                <td><?= (int)$p['user_count'] ?></td>
                <td class="text-end">
                    <button class="btn btn-sm btn-secondary btn-icon"
                        onclick='openEdit(<?= (int)$p["id"] ?>,"<?= h(addslashes($p["name"])) ?>",<?= (int)$p["price"] ?>,<?= (int)$p["max_sessions"] ?>)'
                        title="Edit"><i class="fas fa-pen"></i></button>
                    <?php if ($p['id'] != 1): ?>
                    <form method="POST" class="d-inline" onsubmit="return confirm('Delete this package? Users will be moved to Free Plan.')">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                        <button class="btn btn-sm btn-danger btn-icon" title="Delete"><i class="fas fa-trash"></i></button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add Modal -->
<div class="modal fade" id="addModal" tabindex="-1">
<div class="modal-dialog modal-sm">
<div class="modal-content">
<form method="POST">
    <input type="hidden" name="action" value="create">
    <div class="modal-header"><h6 class="modal-title">New Package</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <div class="mb-3"><label class="form-label">Name</label><input type="text" name="name" class="form-control" maxlength="50" required></div>
        <div class="mb-3"><label class="form-label">Price (Coins)</label><input type="number" name="price" class="form-control" value="0" min="0"></div>
        <div class="mb-0"><label class="form-label">Max Sessions</label><input type="number" name="max_sessions" class="form-control" value="1" min="1" max="100"></div>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-primary btn-sm">Add</button>
    </div>
</form>
</div></div></div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
<div class="modal-dialog modal-sm">
<div class="modal-content">
<form method="POST">
    <input type="hidden" name="action" value="edit">
    <input type="hidden" name="id" id="ep_id">
    <div class="modal-header"><h6 class="modal-title">Edit Package</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <div class="mb-3"><label class="form-label">Name</label><input type="text" name="name" id="ep_name" class="form-control" maxlength="50" required></div>
        <div class="mb-3"><label class="form-label">Price (Coins)</label><input type="number" name="price" id="ep_price" class="form-control" min="0"></div>
        <div class="mb-0"><label class="form-label">Max Sessions</label><input type="number" name="max_sessions" id="ep_max" class="form-control" min="1" max="100"></div>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-primary btn-sm">Save</button>
    </div>
</form>
</div></div></div>

<script>
function openEdit(id, name, price, max) {
    document.getElementById('ep_id').value    = id;
    document.getElementById('ep_name').value  = name;
    document.getElementById('ep_price').value = price;
    document.getElementById('ep_max').value   = max;
    new bootstrap.Modal(document.getElementById('editModal')).show();
}
</script>
<?php load_footer(); ?>
