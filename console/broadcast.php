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
        // Delete related media if exists
        $row = $pdo->prepare("SELECT media_path FROM broadcasts WHERE id = ?");
        $row->execute([$id]);
        $row = $row->fetch();
        if ($row && $row['media_path'] && file_exists(__DIR__ . '/../' . $row['media_path'])) {
            unlink(__DIR__ . '/../' . $row['media_path']);
        }
        $pdo->prepare("DELETE FROM broadcasts WHERE id = ?")->execute([$id]);
        write_log('ADMIN', "Deleted broadcast ID $id");
        $msg = "Broadcast task deleted.";
        $msg_type = 'danger';
    } elseif ($action === 'create') {
        $session_id  = (int)($_POST['session_id'] ?? 0);
        $message     = trim($_POST['message'] ?? '');
        $target_count = (int)($_POST['target_count'] ?? 0);
        $media_path   = null;

        if (isset($_FILES['media']) && $_FILES['media']['error'] === 0) {
            $ext = strtolower(pathinfo($_FILES['media']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg','jpeg','png','gif','mp4','mov'];
            if (in_array($ext, $allowed)) {
                $fname = 'uploads/' . uniqid('bc_') . '.' . $ext;
                move_uploaded_file($_FILES['media']['tmp_name'], __DIR__ . '/../' . $fname);
                $media_path = $fname;
            }
        }

        if ($session_id > 0 && !empty($message)) {
            $pdo->prepare("INSERT INTO broadcasts (session_id, message, media_path, status, target_count) VALUES (?, ?, ?, 'draft', ?)")
                ->execute([$session_id, $message, $media_path, $target_count]);
            write_log('BROADCAST', "New broadcast task created for session $session_id");
            $msg = "Broadcast task created successfully.";
            $msg_type = 'success';
        } else {
            $msg = "Session and message are required.";
            $msg_type = 'warning';
        }
    }
}

// Stats
$stats = [
    'total'     => $pdo->query("SELECT COUNT(*) FROM broadcasts")->fetchColumn(),
    'draft'     => $pdo->query("SELECT COUNT(*) FROM broadcasts WHERE status='draft'")->fetchColumn(),
    'process'   => $pdo->query("SELECT COUNT(*) FROM broadcasts WHERE status='process'")->fetchColumn(),
    'completed' => $pdo->query("SELECT COUNT(*) FROM broadcasts WHERE status='completed'")->fetchColumn(),
    'failed'    => $pdo->query("SELECT COUNT(*) FROM broadcasts WHERE status='failed'")->fetchColumn(),
    'sent'      => $pdo->query("SELECT COALESCE(SUM(sent_count),0) FROM broadcasts")->fetchColumn(),
];

$page     = max(1, (int)($_GET['page'] ?? 1));
$per_page = 15;
$offset   = ($page - 1) * $per_page;

$filter_st = trim($_GET['status'] ?? '');
$where_str = $filter_st ? "WHERE b.status = '$filter_st'" : "";

$total       = $pdo->query("SELECT COUNT(*) FROM broadcasts b $where_str")->fetchColumn();
$total_pages = ceil($total / $per_page);

$broadcasts = $pdo->query("SELECT b.*, u.telegram_id FROM broadcasts b LEFT JOIN user_sessions u ON b.session_id = u.id $where_str ORDER BY b.id DESC LIMIT $per_page OFFSET $offset")->fetchAll();
$sessions_list = $pdo->query("SELECT id, telegram_id, phone_number FROM user_sessions WHERE status='active' ORDER BY id DESC")->fetchAll();

load_header('Broadcast Task');
?>

<?php if ($msg): ?>
<div class="alert alert-<?= $msg_type ?> alert-dismissible fade show py-2 small fw-medium mb-3" role="alert">
    <i class="fa-solid fa-circle-info me-1"></i> <?= htmlspecialchars($msg) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Stat Cards -->
<div class="row g-3 mb-4">
    <?php
    $cards = [
        ['label'=>'Total Tasks',   'val'=>$stats['total'],     'color'=>'primary', 'icon'=>'fa-bullhorn'],
        ['label'=>'Completed',     'val'=>$stats['completed'], 'color'=>'success', 'icon'=>'fa-circle-check'],
        ['label'=>'In Progress',   'val'=>$stats['process'],   'color'=>'warning', 'icon'=>'fa-spinner'],
        ['label'=>'Messages Sent', 'val'=>$stats['sent'],      'color'=>'info',    'icon'=>'fa-paper-plane'],
    ];
    foreach ($cards as $card):
    ?>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body d-flex align-items-center">
                <div class="bg-<?= $card['color'] ?> bg-opacity-10 text-<?= $card['color'] ?> rounded p-3 me-3">
                    <i class="fa-solid <?= $card['icon'] ?> fs-5"></i>
                </div>
                <div>
                    <h6 class="text-muted mb-1 small fw-bold"><?= $card['label'] ?></h6>
                    <h4 class="mb-0 fw-bold"><?= number_format($card['val']) ?></h4>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="row g-3">
    <!-- Create New Broadcast -->
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header border-bottom">
                <h6 class="mb-0 fw-bold"><i class="fa-solid fa-plus-circle text-primary me-2"></i>New Broadcast Task</h6>
            </div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="create">
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold">Session (Sender)</label>
                        <select name="session_id" class="form-select form-select-sm" required>
                            <option value="">— Select Active Session —</option>
                            <?php foreach ($sessions_list as $sl): ?>
                                <option value="<?= $sl['id'] ?>"><?= htmlspecialchars($sl['telegram_id']) ?> (<?= htmlspecialchars($sl['phone_number']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (empty($sessions_list)): ?>
                            <div class="form-text text-warning"><i class="fa-solid fa-triangle-exclamation me-1"></i>No active sessions available.</div>
                        <?php endif; ?>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold">Message</label>
                        <textarea name="message" class="form-control form-control-sm" rows="5" placeholder="Enter broadcast message..." required></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold">Media Attachment <span class="text-muted fw-normal">(Optional)</span></label>
                        <input type="file" name="media" class="form-control form-control-sm" accept="image/*,video/*">
                        <div class="form-text">Accepted: JPG, PNG, GIF, MP4, MOV</div>
                    </div>
                    <div class="mb-4">
                        <label class="form-label text-muted small fw-bold">Target Count <span class="text-muted fw-normal">(0 = all contacts)</span></label>
                        <input type="number" name="target_count" class="form-control form-control-sm" value="0" min="0">
                    </div>
                    <button type="submit" class="btn btn-primary w-100" <?= empty($sessions_list) ? 'disabled' : '' ?>>
                        <i class="fa-solid fa-rocket me-1"></i> Create Task
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Broadcast List -->
    <div class="col-md-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header border-bottom d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold"><i class="fa-solid fa-list text-primary me-2"></i>Broadcast Tasks</h6>
                <div class="d-flex gap-1">
                    <?php
                    $filters = ['' => 'All', 'draft' => 'Draft', 'process' => 'Running', 'completed' => 'Done', 'failed' => 'Failed'];
                    foreach ($filters as $fv => $fl):
                    ?>
                        <a href="?status=<?= $fv ?>" class="btn btn-sm <?= $filter_st === $fv ? 'btn-primary' : 'btn-light border' ?>"><?= $fl ?></a>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table mb-0 table-hover align-middle">
                        <thead class="bg-light">
                            <tr>
                                <th class="border-top-0 ps-4">Session</th>
                                <th class="border-top-0">Message Preview</th>
                                <th class="border-top-0 text-center">Progress</th>
                                <th class="border-top-0">Status</th>
                                <th class="border-top-0 text-end pe-4">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($broadcasts) > 0): ?>
                                <?php foreach ($broadcasts as $b): ?>
                                    <?php
                                        $sc = match($b['status']) {
                                            'draft'     => 'secondary',
                                            'process'   => 'warning',
                                            'completed' => 'success',
                                            'failed'    => 'danger',
                                            default     => 'secondary'
                                        };
                                        $pct = $b['target_count'] > 0 ? round(($b['sent_count']/$b['target_count'])*100) : 0;
                                    ?>
                                    <tr>
                                        <td class="ps-4">
                                            <span class="fw-semibold small font-monospace"><?= htmlspecialchars($b['telegram_id'] ?? '—') ?></span>
                                        </td>
                                        <td style="max-width: 200px;">
                                            <div class="text-truncate small" title="<?= htmlspecialchars($b['message']) ?>">
                                                <?= htmlspecialchars(mb_strimwidth($b['message'], 0, 60, '...')) ?>
                                            </div>
                                            <?php if ($b['media_path']): ?>
                                                <span class="badge bg-info-subtle text-info border border-info-subtle rounded-pill px-2 mt-1"><i class="fa-solid fa-paperclip me-1"></i>Media</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center" style="min-width: 100px;">
                                            <?php if ($b['target_count'] > 0): ?>
                                                <div class="progress" style="height: 6px;">
                                                    <div class="progress-bar bg-<?= $sc ?>" style="width: <?= $pct ?>%"></div>
                                                </div>
                                                <small class="text-muted"><?= number_format($b['sent_count']) ?> / <?= number_format($b['target_count']) ?></small>
                                            <?php else: ?>
                                                <small class="text-muted"><?= number_format($b['sent_count']) ?> sent</small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?= $sc ?>-subtle text-<?= $sc ?> border border-<?= $sc ?>-subtle rounded-pill px-2">
                                                <?= $b['status'] === 'process' ? '<i class="fa-solid fa-spinner fa-spin me-1"></i>' : '' ?>
                                                <?= ucfirst($b['status']) ?>
                                            </span>
                                        </td>
                                        <td class="text-end pe-4">
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Delete this broadcast task?')">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?= $b['id'] ?>">
                                                <button class="btn btn-sm btn-danger" title="Delete"><i class="fa-solid fa-trash"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center py-5 text-muted">
                                        <i class="fa-solid fa-bullhorn fs-3 d-block mb-2 opacity-25"></i>
                                        No broadcast tasks found.
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
                                <a class="page-link" href="?page=<?= $p ?><?= $filter_st ? '&status='.$filter_st : '' ?>"><?= $p ?></a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php load_footer(); ?>
