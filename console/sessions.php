<?php
require_once __DIR__ . '/../core/auth.php';
check_auth();
require_once __DIR__ . '/../core/layout.php';

$msg = $msg_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id     = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

    if ($action === 'delete' && $id > 0) {
        $pdo->prepare("DELETE FROM user_sessions WHERE id = ?")->execute([$id]);
        write_log('ADMIN', "Deleted session ID $id");
        $msg = "Session removed."; $msg_type = 'danger';
    } elseif ($action === 'ban' && $id > 0) {
        $pdo->prepare("UPDATE user_sessions SET status='banned' WHERE id = ?")->execute([$id]);
        write_log('ADMIN', "Banned session ID $id");
        $msg = "Session banned."; $msg_type = 'warning';
    } elseif ($action === 'unban' && $id > 0) {
        $pdo->prepare("UPDATE user_sessions SET status='active' WHERE id = ?")->execute([$id]);
        write_log('ADMIN', "Unbanned session ID $id");
        $msg = "Session restored."; $msg_type = 'success';
    }
}

$search    = trim(strip_tags($_GET['q'] ?? ''));
$page      = max(1, (int)($_GET['page'] ?? 1));
$per_page  = 20;
$offset    = ($page - 1) * $per_page;

if ($search) {
    $like  = "%$search%";
    $total = (int)$pdo->prepare("SELECT COUNT(*) FROM user_sessions WHERE telegram_id LIKE ? OR phone_number LIKE ?")->execute([$like,$like]) ? $pdo->query("SELECT COUNT(*) FROM user_sessions WHERE telegram_id LIKE '$like' OR phone_number LIKE '$like'")->fetchColumn() : 0;
    $stmt  = $pdo->prepare("SELECT * FROM user_sessions WHERE telegram_id LIKE ? OR phone_number LIKE ? ORDER BY id DESC LIMIT $per_page OFFSET $offset");
    $stmt->execute([$like, $like]);
} else {
    $total = (int)$pdo->query("SELECT COUNT(*) FROM user_sessions")->fetchColumn();
    $stmt  = $pdo->prepare("SELECT * FROM user_sessions ORDER BY id DESC LIMIT $per_page OFFSET $offset");
    $stmt->execute();
}
$sessions     = $stmt->fetchAll();
$total_pages  = max(1, (int)ceil($total / $per_page));

load_header('Sessions');
?>

<?php if ($msg): ?>
<div class="alert alert-<?= $msg_type ?> mb-3"><i class="fas fa-circle-info"></i> <?= h($msg) ?></div>
<?php endif; ?>

<div class="page-header">
    <div>
        <h1>Connected Accounts (Sessions)</h1>
        <p><?= number_format($total) ?> session(s) in database</p>
    </div>
    <form method="GET" class="d-flex gap-2">
        <input type="text" name="q" class="form-control" style="width:200px" placeholder="Search…" value="<?= h($search) ?>">
        <button class="btn btn-secondary"><i class="fas fa-search"></i></button>
        <?php if ($search): ?><a href="sessions.php" class="btn btn-secondary"><i class="fas fa-xmark"></i></a><?php endif; ?>
    </form>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table">
            <thead><tr>
                <th>#</th><th>Telegram ID</th><th>Phone</th><th>Status</th><th>Created</th><th class="text-end">Actions</th>
            </tr></thead>
            <tbody>
            <?php if ($sessions): foreach ($sessions as $i => $s):
                $badge = match($s['status']) {
                    'active'        => 'badge-success',
                    'banned'        => 'badge-danger',
                    'wait_otp','wait_password' => 'badge-warning',
                    default         => 'badge-secondary'
                };
            ?>
            <tr>
                <td style="color:var(--text-muted)"><?= $offset + $i + 1 ?></td>
                <td><code class="mono"><?= h($s['telegram_id']) ?></code></td>
                <td><?= h($s['phone_number'] ?: '—') ?></td>
                <td><span class="badge <?= $badge ?>"><?= ucfirst(h($s['status'])) ?></span></td>
                <td style="color:var(--text-muted);font-size:.75rem"><?= date('d M Y H:i', strtotime($s['created_at'])) ?></td>
                <td class="text-end">
                    <?php if ($s['status'] !== 'banned'): ?>
                    <form method="POST" class="d-inline">
                        <input type="hidden" name="action" value="ban">
                        <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                        <button class="btn btn-sm btn-warning btn-icon" title="Ban"><i class="fas fa-ban"></i></button>
                    </form>
                    <?php else: ?>
                    <form method="POST" class="d-inline">
                        <input type="hidden" name="action" value="unban">
                        <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                        <button class="btn btn-sm btn-success btn-icon" title="Unban"><i class="fas fa-rotate-left"></i></button>
                    </form>
                    <?php endif; ?>
                    <form method="POST" class="d-inline" onsubmit="return confirm('Delete session permanently?')">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                        <button class="btn btn-sm btn-danger btn-icon" title="Delete"><i class="fas fa-trash"></i></button>
                    </form>
                </td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="6" class="text-center py-4" style="color:var(--text-muted)">No sessions found.</td></tr>
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

<?php load_footer(); ?>
