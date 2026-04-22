<?php
require_once __DIR__ . '/../core/auth.php';
check_auth();
require_once __DIR__ . '/../core/layout.php';

$msg = '';
$msg_type = 'info';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
            $msg = "User deleted successfully.";
            $msg_type = 'danger';
        }
    } elseif ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $coins = (int)($_POST['coins'] ?? 0);
        $package_id = (int)($_POST['package_id'] ?? 1);
        if ($id > 0) {
            $pdo->prepare("UPDATE users SET coins = ?, package_id = ? WHERE id = ?")->execute([$coins, $package_id, $id]);
            $msg = "User updated successfully.";
            $msg_type = 'success';
        }
    }
}

// Pagination + search
$search = trim($_GET['q'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

if ($search) {
    $total = $pdo->prepare("SELECT COUNT(*) FROM users WHERE telegram_id LIKE ? OR name LIKE ?");
    $total->execute(["%$search%", "%$search%"]);
    $total = $total->fetchColumn();

    $stmt = $pdo->prepare("SELECT u.*, p.name as pkg_name FROM users u LEFT JOIN packages p ON u.package_id = p.id WHERE u.telegram_id LIKE ? OR u.name LIKE ? ORDER BY u.id DESC LIMIT $per_page OFFSET $offset");
    $stmt->execute(["%$search%", "%$search%"]);
} else {
    $total = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $stmt = $pdo->prepare("SELECT u.*, p.name as pkg_name FROM users u LEFT JOIN packages p ON u.package_id = p.id ORDER BY u.id DESC LIMIT $per_page OFFSET $offset");
    $stmt->execute();
}

$users = $stmt->fetchAll();
$total_pages = ceil($total / $per_page);
$packages = $pdo->query("SELECT id, name FROM packages")->fetchAll(PDO::FETCH_KEY_PAIR);

load_header('Bot Clients');
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
        <h5 class="fw-bold mb-1">Bot Users (Clients)</h5>
        <p class="text-muted small mb-0">Total <strong><?= number_format($total) ?></strong> bot users registered.</p>
    </div>
    <form method="GET" class="d-flex gap-2" style="min-width: 300px;">
        <input type="text" name="q" class="form-control form-control-sm" placeholder="Search Telegram ID or Name..." value="<?= htmlspecialchars($search) ?>">
        <button type="submit" class="btn btn-sm btn-primary px-3"><i class="fa-solid fa-search"></i></button>
        <?php if ($search): ?><a href="users.php" class="btn btn-sm btn-light border"><i class="fa-solid fa-xmark"></i></a><?php endif; ?>
    </form>
</div>

<!-- Table -->
<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table mb-0 table-hover align-middle">
                <thead class="bg-light">
                    <tr>
                        <th class="border-top-0 ps-4" style="width: 50px;">#</th>
                        <th class="border-top-0">Telegram ID & Name</th>
                        <th class="border-top-0">Coins</th>
                        <th class="border-top-0">Active Package</th>
                        <th class="border-top-0">Registered</th>
                        <th class="border-top-0 text-end pe-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($users) > 0): ?>
                        <?php foreach ($users as $i => $u): ?>
                            <tr>
                                <td class="ps-4 text-muted small"><?= $offset + $i + 1 ?></td>
                                <td>
                                    <div class="fw-bold text-dark"><?= htmlspecialchars($u['name']) ?></div>
                                    <div class="text-muted small font-monospace"><?= htmlspecialchars($u['telegram_id']) ?></div>
                                </td>
                                <td>
                                    <span class="badge bg-warning text-dark"><i class="fas fa-coins me-1"></i> <?= number_format($u['coins']) ?></span>
                                </td>
                                <td><span class="badge bg-success-subtle text-success border border-success-subtle px-2"><?= htmlspecialchars($u['pkg_name'] ?: 'None') ?></span></td>
                                <td class="text-muted small"><?= date('d M Y, H:i', strtotime($u['created_at'])) ?></td>
                                <td class="text-end pe-4">
                                    <div class="d-flex justify-content-end gap-1">
                                        <button class="btn btn-sm btn-primary" onclick="editUser(<?= $u['id'] ?>, <?= $u['coins'] ?>, <?= $u['package_id'] ?: 1 ?>)" title="Manage"><i class="fa-solid fa-pen"></i></button>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Delete this bot user?')">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                            <button class="btn btn-sm btn-danger" title="Delete"><i class="fa-solid fa-trash"></i></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="6" class="text-center py-5 text-muted">No bot users found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Edit -->
<div class="modal fade" id="modalEdit" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-header">
                    <h5 class="modal-title">Manage User Data</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Coins Balance</label>
                        <input type="number" name="coins" id="edit_coins" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Subscription Package</label>
                        <select name="package_id" id="edit_package_id" class="form-select">
                            <?php foreach($packages as $pid => $pname): ?>
                            <option value="<?= $pid ?>"><?= htmlspecialchars($pname) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editUser(id, coins, package_id) {
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_coins').value = coins;
    document.getElementById('edit_package_id').value = package_id;
    new bootstrap.Modal(document.getElementById('modalEdit')).show();
}
</script>

<?php load_footer(); ?>
