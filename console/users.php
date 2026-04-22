<?php
require_once __DIR__ . '/../core/auth.php';
check_auth();
require_once __DIR__ . '/../core/layout.php';

$msg = $msg_type = '';

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
        if ($id > 0) {
            $pdo->prepare("UPDATE users SET coins = ?, package_id = ? WHERE id = ?")
                ->execute([$coins ?? 0, $pkg_id ?? 1, $id]);
            write_log('ADMIN', "Updated user ID $id coins=$coins pkg=$pkg_id");
            $msg = "User updated successfully."; $msg_type = 'success';
        }
    }
}

$search    = trim(strip_tags($_GET['q'] ?? ''));
$page      = max(1, (int)($_GET['page'] ?? 1));
$per_page  = 20;
$offset    = ($page - 1) * $per_page;

if ($search) {
    $like = "%$search%";
    $total = $pdo->prepare("SELECT COUNT(*) FROM users WHERE telegram_id LIKE ? OR name LIKE ?");
    $total->execute([$like, $like]);
    $total = (int)$total->fetchColumn();
    $stmt  = $pdo->prepare("SELECT u.*, p.name AS pkg_name FROM users u LEFT JOIN packages p ON u.package_id=p.id WHERE u.telegram_id LIKE ? OR u.name LIKE ? ORDER BY u.id DESC LIMIT $per_page OFFSET $offset");
    $stmt->execute([$like, $like]);
} else {
    $total = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $stmt  = $pdo->prepare("SELECT u.*, p.name AS pkg_name FROM users u LEFT JOIN packages p ON u.package_id=p.id ORDER BY u.id DESC LIMIT $per_page OFFSET $offset");
    $stmt->execute();
}
$users      = $stmt->fetchAll();
$total_pages = max(1, (int)ceil($total / $per_page));
$packages   = $pdo->query("SELECT id, name FROM packages ORDER BY price")->fetchAll();

load_header('Bot Users');
?>

<?php if ($msg): ?>
<div class="alert alert-<?= $msg_type ?> mb-3">
    <i class="fas fa-circle-info"></i> <?= h($msg) ?>
</div>
<?php endif; ?>

<div class="page-header">
    <div>
        <h1>Bot Users</h1>
        <p><?= number_format($total) ?> total users registered via Bot</p>
    </div>
    <form method="GET" class="d-flex gap-2">
        <input type="text" name="q" class="form-control" style="width:220px" placeholder="Search ID or name…" value="<?= h($search) ?>">
        <button class="btn btn-secondary"><i class="fas fa-search"></i></button>
        <?php if ($search): ?><a href="users.php" class="btn btn-secondary"><i class="fas fa-xmark"></i></a><?php endif; ?>
    </form>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table">
            <thead><tr>
                <th>#</th>
                <th>Telegram ID</th>
                <th>Name</th>
                <th>Coins</th>
                <th>Package</th>
                <th>Joined</th>
                <th class="text-end">Actions</th>
            </tr></thead>
            <tbody>
            <?php if ($users): foreach ($users as $i => $u): ?>
            <tr>
                <td style="color:var(--text-muted)"><?= $offset + $i + 1 ?></td>
                <td><code class="mono"><?= h($u['telegram_id']) ?></code></td>
                <td><?= h($u['name'] ?? '—') ?></td>
                <td><span class="badge badge-warning"><i class="fas fa-coins me-1"></i><?= number_format((int)$u['coins']) ?></span></td>
                <td><span class="badge badge-info"><?= h($u['pkg_name'] ?? 'None') ?></span></td>
                <td style="color:var(--text-muted);font-size:.75rem"><?= date('d M Y', strtotime($u['created_at'])) ?></td>
                <td class="text-end">
                    <button class="btn btn-sm btn-secondary btn-icon"
                        onclick='openEdit(<?= (int)$u["id"] ?>,<?= (int)$u["coins"] ?>,<?= (int)($u["package_id"] ?? 1) ?>)'
                        title="Edit"><i class="fas fa-pen"></i></button>
                    <form method="POST" class="d-inline" onsubmit="return confirm('Delete this user and all their sessions?')">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                        <button class="btn btn-sm btn-danger btn-icon" title="Delete"><i class="fas fa-trash"></i></button>
                    </form>
                </td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="7" class="text-center py-4" style="color:var(--text-muted)">No users found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($total_pages > 1): ?>
    <div style="display:flex;justify-content:space-between;align-items:center;padding:.6rem 1rem;border-top:1px solid var(--border)">
        <small style="color:var(--text-muted)">Page <?= $page ?> / <?= $total_pages ?></small>
        <nav><ul class="pagination">
            <?php for ($p = 1; $p <= $total_pages; $p++): ?>
            <li class="page-item <?= $p == $page ? 'active' : '' ?>">
                <a class="page-link" href="?page=<?= $p ?><?= $search ? '&q='.urlencode($search) : '' ?>"><?= $p ?></a>
            </li>
            <?php endfor; ?>
        </ul></nav>
    </div>
    <?php endif; ?>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
<div class="modal-dialog modal-sm">
<div class="modal-content">
<form method="POST">
    <input type="hidden" name="action" value="update">
    <input type="hidden" name="id" id="e_id">
    <div class="modal-header"><h6 class="modal-title">Edit User</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <div class="mb-3">
            <label class="form-label">Coins</label>
            <input type="number" name="coins" id="e_coins" class="form-control" min="0" required>
        </div>
        <div class="mb-0">
            <label class="form-label">Package</label>
            <select name="package_id" id="e_pkg" class="form-select">
                <?php foreach ($packages as $p): ?>
                <option value="<?= (int)$p['id'] ?>"><?= h($p['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary btn-sm">Save</button>
    </div>
</form>
</div></div></div>

<script>
function openEdit(id, coins, pkg) {
    document.getElementById('e_id').value    = id;
    document.getElementById('e_coins').value = coins;
    document.getElementById('e_pkg').value   = pkg;
    new bootstrap.Modal(document.getElementById('editModal')).show();
}
</script>
<?php load_footer(); ?>
