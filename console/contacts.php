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
        $pdo->prepare("DELETE FROM contacts WHERE id = ?")->execute([$id]);
        write_log('ADMIN', "Deleted contact ID $id");
        $msg = "Contact deleted successfully.";
        $msg_type = 'danger';
    } elseif ($action === 'delete_all') {
        $session_id = (int)($_POST['session_id'] ?? 0);
        if ($session_id > 0) {
            $pdo->prepare("DELETE FROM contacts WHERE session_id = ?")->execute([$session_id]);
            write_log('ADMIN', "Deleted all contacts for session_id $session_id");
            $msg = "All contacts for session deleted.";
            $msg_type = 'warning';
        }
    } elseif ($action === 'mark_valid' && $id > 0) {
        $pdo->prepare("UPDATE contacts SET status = 'valid' WHERE id = ?")->execute([$id]);
        $msg = "Contact marked as valid.";
        $msg_type = 'success';
    }
}

// Filters
$search    = trim($_GET['q'] ?? '');
$filter_s  = (int)($_GET['session_id'] ?? 0);
$filter_st = trim($_GET['status'] ?? '');
$page      = max(1, (int)($_GET['page'] ?? 1));
$per_page  = 25;
$offset    = ($page - 1) * $per_page;

// Build WHERE
$where = ['1=1'];
$params = [];
if ($search) {
    $where[] = "(c.phone_or_username LIKE ? OR c.name LIKE ?)";
    $params[] = "%$search%"; $params[] = "%$search%";
}
if ($filter_s) { $where[] = "c.session_id = ?"; $params[] = $filter_s; }
if ($filter_st) { $where[] = "c.status = ?"; $params[] = $filter_st; }

$where_str = implode(' AND ', $where);

$cnt_stmt = $pdo->prepare("SELECT COUNT(*) FROM contacts c WHERE $where_str");
$cnt_stmt->execute($params);
$total = $cnt_stmt->fetchColumn();

$data_stmt = $pdo->prepare("SELECT c.*, u.telegram_id, u.phone_number AS session_phone FROM contacts c LEFT JOIN user_sessions u ON c.session_id = u.id WHERE $where_str ORDER BY c.id DESC LIMIT $per_page OFFSET $offset");
$data_stmt->execute($params);
$contacts = $data_stmt->fetchAll();

$total_pages = ceil($total / $per_page);

// Sessions for filter dropdown
$sessions_list = $pdo->query("SELECT id, telegram_id, phone_number FROM user_sessions ORDER BY id DESC")->fetchAll();

load_header('Contacts');
?>

<?php if ($msg): ?>
<div class="alert alert-<?= $msg_type ?> alert-dismissible fade show py-2 small fw-medium mb-3" role="alert">
    <i class="fa-solid fa-circle-info me-1"></i> <?= htmlspecialchars($msg) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Header Row -->
<div class="d-flex justify-content-between align-items-start mb-4">
    <div>
        <h5 class="fw-bold mb-1">Contacts</h5>
        <p class="text-muted small mb-0">Total <strong><?= number_format($total) ?></strong> contact(s) collected by all sessions.</p>
    </div>
</div>

<!-- Filter Bar -->
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body py-2 px-3">
        <form method="GET" class="row g-2 align-items-center">
            <div class="col-auto flex-grow-1">
                <input type="text" name="q" class="form-control form-control-sm" placeholder="Search name or phone/username..." value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="col-auto">
                <select name="session_id" class="form-select form-select-sm">
                    <option value="">All Sessions</option>
                    <?php foreach ($sessions_list as $sl): ?>
                        <option value="<?= $sl['id'] ?>" <?= $filter_s == $sl['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($sl['telegram_id']) ?> (<?= htmlspecialchars($sl['phone_number']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto">
                <select name="status" class="form-select form-select-sm">
                    <option value="">All Status</option>
                    <option value="valid"   <?= $filter_st == 'valid'   ? 'selected' : '' ?>>Valid</option>
                    <option value="invalid" <?= $filter_st == 'invalid' ? 'selected' : '' ?>>Invalid</option>
                    <option value="sent"    <?= $filter_st == 'sent'    ? 'selected' : '' ?>>Sent</option>
                </select>
            </div>
            <div class="col-auto d-flex gap-1">
                <button type="submit" class="btn btn-sm btn-primary px-3"><i class="fa-solid fa-filter me-1"></i>Filter</button>
                <a href="contacts" class="btn btn-sm btn-light border"><i class="fa-solid fa-xmark"></i></a>
            </div>
        </form>
    </div>
</div>

<!-- Table -->
<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table mb-0 table-hover align-middle">
                <thead class="bg-light">
                    <tr>
                        <th class="border-top-0 ps-4" style="width: 50px;">#</th>
                        <th class="border-top-0">Name</th>
                        <th class="border-top-0">Phone / Username</th>
                        <th class="border-top-0">Type</th>
                        <th class="border-top-0">Session (Telegram ID)</th>
                        <th class="border-top-0">Status</th>
                        <th class="border-top-0 text-end pe-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($contacts) > 0): ?>
                        <?php foreach ($contacts as $i => $c): ?>
                            <?php
                                $sc = match($c['status']) {
                                    'valid'   => 'success',
                                    'invalid' => 'danger',
                                    'sent'    => 'primary',
                                    default   => 'secondary'
                                };
                                $tc = match($c['type']) {
                                    'phone'    => 'info',
                                    'username' => 'warning',
                                    default    => 'secondary'
                                };
                            ?>
                            <tr>
                                <td class="ps-4 text-muted small"><?= $offset + $i + 1 ?></td>
                                <td><?= htmlspecialchars($c['name'] ?: '—') ?></td>
                                <td><span class="font-monospace small"><?= htmlspecialchars($c['phone_or_username']) ?></span></td>
                                <td><span class="badge bg-<?= $tc ?>-subtle text-<?= $tc ?> border border-<?= $tc ?>-subtle rounded-pill px-2"><?= ucfirst($c['type']) ?></span></td>
                                <td class="text-muted small"><?= htmlspecialchars($c['telegram_id'] ?? '—') ?></td>
                                <td><span class="badge bg-<?= $sc ?>-subtle text-<?= $sc ?> border border-<?= $sc ?>-subtle rounded-pill px-2"><?= ucfirst($c['status']) ?></span></td>
                                <td class="text-end pe-4">
                                    <div class="d-flex justify-content-end gap-1">
                                        <?php if ($c['status'] !== 'valid'): ?>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="action" value="mark_valid">
                                            <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                            <button class="btn btn-sm btn-success" title="Mark Valid"><i class="fa-solid fa-check"></i></button>
                                        </form>
                                        <?php endif; ?>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Delete this contact?')">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                            <button class="btn btn-sm btn-danger" title="Delete"><i class="fa-solid fa-trash"></i></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="fa-solid fa-address-book fs-3 d-block mb-2"></i>
                                <?= $search || $filter_s || $filter_st ? "No contacts matching your filters." : "No contacts collected yet." ?>
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
                        <a class="page-link" href="?page=<?= $p ?><?= $search ? '&q='.$search : '' ?><?= $filter_s ? '&session_id='.$filter_s : '' ?><?= $filter_st ? '&status='.$filter_st : '' ?>"><?= $p ?></a>
                    </li>
                <?php endfor; ?>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
</div>

<?php load_footer(); ?>
