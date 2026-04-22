<?php
require_once __DIR__ . '/../core/auth.php';
check_auth();
require_once __DIR__ . '/../core/layout.php';

$msg = '';
$msg_type = 'info';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create') {
        $name = trim($_POST['name'] ?? '');
        $price = (int)($_POST['price'] ?? 0);
        $max_sessions = (int)($_POST['max_sessions'] ?? 1);
        
        if ($name) {
            $pdo->prepare("INSERT INTO packages (name, price, max_sessions) VALUES (?, ?, ?)")->execute([$name, $price, $max_sessions]);
            $msg = "Package added successfully.";
            $msg_type = 'success';
        }
    } elseif ($action === 'edit') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $price = (int)($_POST['price'] ?? 0);
        $max_sessions = (int)($_POST['max_sessions'] ?? 1);
        
        if ($name && $id > 0) {
            $pdo->prepare("UPDATE packages SET name = ?, price = ?, max_sessions = ? WHERE id = ?")->execute([$name, $price, $max_sessions, $id]);
            $msg = "Package updated successfully.";
            $msg_type = 'success';
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0 && $id != 1) { // Prevent deleting default plan
            $pdo->prepare("DELETE FROM packages WHERE id = ?")->execute([$id]);
            $msg = "Package deleted successfully.";
            $msg_type = 'danger';
        }
    }
}

$stmt = $pdo->query("SELECT * FROM packages ORDER BY price ASC");
$packages = $stmt->fetchAll();

load_header('Subscription Packages');
?>

<?php if ($msg): ?>
<div class="alert alert-<?= $msg_type ?> alert-dismissible fade show py-2 small fw-medium mb-3" role="alert">
    <i class="fa-solid fa-circle-info me-1"></i> <?= htmlspecialchars($msg) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Header Row -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h5 class="fw-bold mb-1">Subscription Packages</h5>
        <p class="text-muted small mb-0">Manage packages and their limits.</p>
    </div>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalAdd">
        <i class="fas fa-plus"></i> Add Package
    </button>
</div>

<!-- Table -->
<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table mb-0 table-hover align-middle">
                <thead class="bg-light">
                    <tr>
                        <th class="border-top-0 ps-4" style="width: 50px;">ID</th>
                        <th class="border-top-0">Package Name</th>
                        <th class="border-top-0">Price (Coins)</th>
                        <th class="border-top-0">Max Sessions</th>
                        <th class="border-top-0 text-end pe-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($packages as $p): ?>
                        <tr>
                            <td class="ps-4 text-muted small"><?= $p['id'] ?></td>
                            <td class="fw-semibold"><?= htmlspecialchars($p['name']) ?></td>
                            <td><?= number_format($p['price']) ?> Coins</td>
                            <td><?= $p['max_sessions'] ?> Acc</td>
                            <td class="text-end pe-4">
                                <div class="d-flex justify-content-end gap-1">
                                    <button class="btn btn-sm btn-primary" onclick="editPkg(<?= $p['id'] ?>, '<?= htmlspecialchars(addslashes($p['name'])) ?>', <?= $p['price'] ?>, <?= $p['max_sessions'] ?>)"><i class="fa-solid fa-pen"></i></button>
                                    <?php if ($p['id'] != 1): ?>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Delete this package?')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                        <button class="btn btn-sm btn-danger"><i class="fa-solid fa-trash"></i></button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Add -->
<div class="modal fade" id="modalAdd" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="create">
                <div class="modal-header">
                    <h5 class="modal-title">Add Package</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Name</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Price (Coins)</label>
                        <input type="number" name="price" class="form-control" value="0" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Max Allowed Telegram Accounts</label>
                        <input type="number" name="max_sessions" class="form-control" value="1" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Save Package</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Edit -->
<div class="modal fade" id="modalEdit" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Package</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Name</label>
                        <input type="text" name="name" id="edit_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Price (Coins)</label>
                        <input type="number" name="price" id="edit_price" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Max Allowed Telegram Accounts</label>
                        <input type="number" name="max_sessions" id="edit_max_sessions" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Update Package</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editPkg(id, name, price, max) {
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_name').value = name;
    document.getElementById('edit_price').value = price;
    document.getElementById('edit_max_sessions').value = max;
    new bootstrap.Modal(document.getElementById('modalEdit')).show();
}
</script>

<?php load_footer(); ?>
