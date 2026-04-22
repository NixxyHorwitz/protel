<?php
require_once __DIR__ . '/../core/auth.php';
check_auth();
require_once __DIR__ . '/../core/layout.php';

$msg = '';
$msg_type = 'info';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);

    if ($action === 'delete' && $id > 0) {
        $pdo->prepare("DELETE FROM user_sessions WHERE id = ?")->execute([$id]);
        write_log('ADMIN', "Deleted session ID $id");
        $msg = "Session deleted successfully.";
        $msg_type = 'danger';
    } elseif ($action === 'ban' && $id > 0) {
        $pdo->prepare("UPDATE user_sessions SET status = 'banned' WHERE id = ?")->execute([$id]);
        write_log('ADMIN', "Banned session ID $id");
        $msg = "User session has been banned.";
        $msg_type = 'warning';
    } elseif ($action === 'unban' && $id > 0) {
        $pdo->prepare("UPDATE user_sessions SET status = 'active' WHERE id = ?")->execute([$id]);
        write_log('ADMIN', "Unbanned session ID $id");
        $msg = "User session restored to active.";
        $msg_type = 'success';
    }
}

// Pagination + search
$search = trim($_GET['q'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

if ($search) {
    $total = $pdo->prepare("SELECT COUNT(*) FROM user_sessions WHERE telegram_id LIKE ? OR phone_number LIKE ?");
    $total->execute(["%$search%", "%$search%"]);
    $total = $total->fetchColumn();

    $stmt = $pdo->prepare("SELECT * FROM user_sessions WHERE telegram_id LIKE ? OR phone_number LIKE ? ORDER BY id DESC LIMIT $per_page OFFSET $offset");
    $stmt->execute(["%$search%", "%$search%"]);
} else {
    $total = $pdo->query("SELECT COUNT(*) FROM user_sessions")->fetchColumn();
    $stmt = $pdo->prepare("SELECT * FROM user_sessions ORDER BY id DESC LIMIT $per_page OFFSET $offset");
    $stmt->execute();
}

$sessions = $stmt->fetchAll();
$total_pages = ceil($total / $per_page);

load_header('Member Sessions');
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
        <h5 class="fw-bold mb-1">Member Sessions</h5>
        <p class="text-muted small mb-0">Total <strong><?= number_format($total) ?></strong> session(s) registered via Bot.</p>
    </div>
    <form method="GET" class="d-flex gap-2" style="min-width: 300px;">
        <input type="text" name="q" class="form-control form-control-sm" placeholder="Search Telegram ID or Phone..." value="<?= htmlspecialchars($search) ?>">
        <button type="submit" class="btn btn-sm btn-primary px-3"><i class="fa-solid fa-search"></i></button>
        <?php if ($search): ?><a href="users" class="btn btn-sm btn-light border"><i class="fa-solid fa-xmark"></i></a><?php endif; ?>
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
                        <th class="border-top-0">Telegram ID</th>
                        <th class="border-top-0">Phone Number</th>
                        <th class="border-top-0">Status</th>
                        <th class="border-top-0">Registered At</th>
                        <th class="border-top-0 text-end pe-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($sessions) > 0): ?>
                        <?php foreach ($sessions as $i => $s): ?>
                            <?php
                                $sc = match($s['status']) {
                                    'active'  => 'success',
                                    'pending' => 'warning',
                                    'banned'  => 'danger',
                                    default   => 'secondary'
                                };
                            ?>
                            <tr>
                                <td class="ps-4 text-muted small"><?= $offset + $i + 1 ?></td>
                                <td>
                                    <span class="fw-semibold font-monospace"><?= htmlspecialchars($s['telegram_id']) ?></span>
                                </td>
                                <td><?= htmlspecialchars($s['phone_number']) ?></td>
                                <td><span class="badge bg-<?= $sc ?>-subtle text-<?= $sc ?> border border-<?= $sc ?>-subtle rounded-pill px-2"><?= ucfirst($s['status']) ?></span></td>
                                <td class="text-muted small"><?= date('d M Y, H:i', strtotime($s['created_at'])) ?></td>
                                <td class="text-end pe-4">
                                    <div class="d-flex justify-content-end gap-1">
                                        <?php if ($s['status'] !== 'banned'): ?>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Ban this session?')">
                                            <input type="hidden" name="action" value="ban">
                                            <input type="hidden" name="id" value="<?= $s['id'] ?>">
                                            <button class="btn btn-sm btn-warning" title="Ban"><i class="fa-solid fa-ban"></i></button>
                                        </form>
                                        <?php else: ?>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="action" value="unban">
                                            <input type="hidden" name="id" value="<?= $s['id'] ?>">
                                            <button class="btn btn-sm btn-success" title="Restore"><i class="fa-solid fa-rotate-left"></i></button>
                                        </form>
                                        <?php endif; ?>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Delete this session permanently?')">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= $s['id'] ?>">
                                            <button class="btn btn-sm btn-danger" title="Delete"><i class="fa-solid fa-trash"></i></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="fa-solid fa-users-slash fs-3 d-block mb-2"></i>
                                <?= $search ? "No sessions matching \"" . htmlspecialchars($search) . "\"" : "No member sessions yet. Members connect via Bot." ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if ($total_pages > 1): ?>
    <div class="card-footer border-top bg-white d-flex justify-content-between align-items-center py-2 px-4">
        <span class="small text-muted">Page <?= $page ?> of <?= $total_pages ?></span>
        <nav>
            <ul class="pagination pagination-sm mb-0">
                <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                    <li class="page-item <?= $p == $page ? 'active' : '' ?>">
                        <a class="page-link" href="?page=<?= $p ?><?= $search ? '&q='.$search : '' ?>"><?= $p ?></a>
                    </li>
                <?php endfor; ?>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
</div>

<?php load_footer(); ?>
