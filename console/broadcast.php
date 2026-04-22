<?php
require_once __DIR__ . '/../core/auth.php';
check_auth();
require_once __DIR__ . '/../core/layout.php';

$msg = $msg_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id     = (int)($_POST['id'] ?? 0);

    if ($action === 'delete' && $id > 0) {
        $row = $pdo->prepare("SELECT media_path FROM broadcasts WHERE id = ?");
        $row->execute([$id]); $row = $row->fetch();
        if ($row && $row['media_path'] && file_exists(__DIR__ . '/../' . $row['media_path']))
            unlink(__DIR__ . '/../' . $row['media_path']);
        $pdo->prepare("DELETE FROM broadcasts WHERE id = ?")->execute([$id]);
        $msg = "Broadcast task deleted."; $msg_type = 'danger';
    } elseif ($action === 'create') {
        $session_id   = (int)($_POST['session_id'] ?? 0);
        $message      = trim($_POST['message'] ?? '');
        $target_count = (int)($_POST['target_count'] ?? 0);
        $media_path   = null;
        if (isset($_FILES['media']) && $_FILES['media']['error'] === 0) {
            $ext = strtolower(pathinfo($_FILES['media']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png','gif','mp4','mov'])) {
                $fname = 'uploads/' . uniqid('bc_') . '.' . $ext;
                move_uploaded_file($_FILES['media']['tmp_name'], __DIR__ . '/../' . $fname);
                $media_path = $fname;
            }
        }
        if ($session_id > 0 && !empty($message)) {
            $pdo->prepare("INSERT INTO broadcasts (session_id, message, media_path, status, target_count) VALUES (?,?,?,'draft',?)")
                ->execute([$session_id, $message, $media_path, $target_count]);
            $msg = "Broadcast task created."; $msg_type = 'success';
        } else {
            $msg = "Session and message are required."; $msg_type = 'warning';
        }
    } elseif (in_array($action, ['start','pause','stop']) && $id > 0) {
        $st = match($action) { 'start' => 'process', 'pause' => 'draft', default => 'failed' };
        $pdo->prepare("UPDATE broadcasts SET status=? WHERE id=?")->execute([$st, $id]);
        $msg = ucfirst($action) . " broadcast #$id."; $msg_type = 'success';
    }
}

$stats = [
    'total'     => $pdo->query("SELECT COUNT(*) FROM broadcasts")->fetchColumn(),
    'completed' => $pdo->query("SELECT COUNT(*) FROM broadcasts WHERE status='completed'")->fetchColumn(),
    'process'   => $pdo->query("SELECT COUNT(*) FROM broadcasts WHERE status='process'")->fetchColumn(),
    'sent'      => $pdo->query("SELECT COALESCE(SUM(sent_count),0) FROM broadcasts")->fetchColumn(),
];

$page      = max(1, (int)($_GET['page'] ?? 1));
$per_page  = 15;
$offset    = ($page - 1) * $per_page;
$filter_st = strip_tags($_GET['status'] ?? '');
$where     = $filter_st ? "WHERE b.status = " . $pdo->quote($filter_st) : "";

$total       = (int)$pdo->query("SELECT COUNT(*) FROM broadcasts b $where")->fetchColumn();
$total_pages = max(1, (int)ceil($total / $per_page));
$broadcasts  = $pdo->query("SELECT b.*, u.telegram_id, u.phone_number FROM broadcasts b LEFT JOIN user_sessions u ON b.session_id=u.id $where ORDER BY b.id DESC LIMIT $per_page OFFSET $offset")->fetchAll();
$sessions_list = $pdo->query("SELECT id, telegram_id, phone_number FROM user_sessions WHERE status='active' ORDER BY id DESC")->fetchAll();

load_header('Broadcast');
?>

<?php if ($msg): ?>
<div class="alert alert-<?= $msg_type ?> mb-3"><i class="fas fa-circle-info"></i> <?= h($msg) ?></div>
<?php endif; ?>

<div class="page-header">
    <h1>Broadcast</h1>
    <p>Create and manage bulk message broadcast tasks</p>
</div>

<!-- Stats -->
<div class="row g-3 mb-4">
    <?php foreach ([
        ['Total Tasks',    $stats['total'],     'fa-bullhorn',      'blue'],
        ['Completed',      $stats['completed'], 'fa-circle-check',  'green'],
        ['In Progress',    $stats['process'],   'fa-spinner',       'orange'],
        ['Messages Sent',  $stats['sent'],      'fa-paper-plane',   'purple'],
    ] as [$lbl, $val, $icon, $col]): ?>
    <div class="col-6 col-md-3">
        <div class="sc <?= $col ?>">
            <div class="si <?= $col ?>"><i class="fas <?= $icon ?>"></i></div>
            <div class="sv"><?= number_format($val) ?></div>
            <div class="sl"><?= $lbl ?></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="row g-3">
    <!-- Create Form -->
    <div class="col-lg-4">
        <div class="card-c">
            <div class="ch"><div class="ct"><i class="fas fa-plus-circle me-2" style="color:var(--accent)"></i>New Broadcast Task</div></div>
            <div class="cb">
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="create">
                    <div class="mb-3">
                        <label class="fl">Session (Sender)</label>
                        <select name="session_id" class="form-select" required>
                            <option value="">— Select Active Session —</option>
                            <?php foreach ($sessions_list as $sl): ?>
                            <option value="<?= (int)$sl['id'] ?>"><?= h($sl['telegram_id']) ?> (<?= h($sl['phone_number']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (empty($sessions_list)): ?>
                        <div style="font-size:12px;color:var(--warn);margin-top:5px"><i class="fas fa-triangle-exclamation me-1"></i>No active sessions.</div>
                        <?php endif; ?>
                    </div>
                    <div class="mb-3">
                        <label class="fl">Message</label>
                        <textarea name="message" class="form-control" rows="5" placeholder="Enter broadcast message…" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="fl">Media <span style="font-weight:400;text-transform:none;color:var(--mut)">(Optional)</span></label>
                        <input type="file" name="media" class="form-control" accept="image/*,video/*">
                        <div style="font-size:11px;color:var(--mut);margin-top:4px">JPG, PNG, GIF, MP4, MOV</div>
                    </div>
                    <div class="mb-4">
                        <label class="fl">Target Count <span style="font-weight:400;text-transform:none;color:var(--mut)">(0 = all)</span></label>
                        <input type="number" name="target_count" class="form-control" value="0" min="0">
                    </div>
                    <button type="submit" class="btn btn-primary w-100" <?= empty($sessions_list) ? 'disabled' : '' ?>>
                        <i class="fas fa-rocket"></i> Create Task
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Broadcast List -->
    <div class="col-lg-8">
        <div class="card-c">
            <div class="ch">
                <div class="ct"><i class="fas fa-list me-2" style="color:var(--accent)"></i>Broadcast Tasks</div>
                <div style="display:flex;gap:4px;flex-wrap:wrap">
                    <?php
                    $filters = ['' => 'All', 'draft' => 'Draft', 'process' => 'Running', 'completed' => 'Done', 'failed' => 'Failed'];
                    foreach ($filters as $fv => $fl):
                    $active = ($filter_st === $fv);
                    ?>
                    <a href="?status=<?= $fv ?>" style="
                        padding:4px 11px; border-radius:99px; font-size:12px; font-weight:600;
                        text-decoration:none; transition:all .15s;
                        background:<?= $active ? 'var(--accent)' : 'var(--hover)' ?>;
                        color:<?= $active ? '#fff' : 'var(--sub)' ?>;
                        border:1px solid <?= $active ? 'var(--accent)' : 'var(--border)' ?>;
                    "><?= $fl ?></a>
                    <?php endforeach; ?>
                </div>
            </div>
            <table class="tbl">
                <thead><tr>
                    <th style="padding-left:20px">Session</th>
                    <th>Message Preview</th>
                    <th style="text-align:center">Progress</th>
                    <th>Status</th>
                    <th style="text-align:right;padding-right:20px">Actions</th>
                </tr></thead>
                <tbody>
                <?php if ($broadcasts): foreach ($broadcasts as $b):
                    [$bdcls, $txtcls] = match($b['status']) {
                        'completed' => ['bd-ok',   'var(--ok)'],
                        'process'   => ['bd-warn',  'var(--warn)'],
                        'failed'    => ['bd-err',   'var(--err)'],
                        default     => ['bd-acc',   'var(--sub)'],
                    };
                    $pct = $b['target_count'] > 0 ? round(($b['sent_count']/$b['target_count'])*100) : 0;
                ?>
                <tr>
                    <td style="padding-left:20px">
                        <code class="mono" style="font-size:11px"><?= h($b['telegram_id'] ?? '—') ?></code>
                    </td>
                    <td style="max-width:200px">
                        <div style="font-size:13px;color:var(--sub);overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?= h($b['message']) ?>">
                            <?= h(mb_strimwidth($b['message'], 0, 55, '…')) ?>
                        </div>
                        <?php if ($b['media_path']): ?>
                        <span class="bd bd-acc" style="font-size:10px;margin-top:3px"><i class="fas fa-paperclip"></i> Media</span>
                        <?php endif; ?>
                    </td>
                    <td style="text-align:center;min-width:90px">
                        <?php if ($b['target_count'] > 0): ?>
                        <div style="background:var(--hover);border-radius:99px;height:5px;overflow:hidden;margin-bottom:4px">
                            <div style="height:100%;width:<?= $pct ?>%;background:<?= $txtcls ?>;border-radius:99px"></div>
                        </div>
                        <div style="font-size:11px;color:var(--mut)"><?= number_format($b['sent_count']) ?>/<?= number_format($b['target_count']) ?></div>
                        <?php else: ?>
                        <div style="font-size:11px;color:var(--mut)"><?= number_format($b['sent_count']) ?> sent</div>
                        <?php endif; ?>
                    </td>
                    <td><span class="bd <?= $bdcls ?>"><?= $b['status'] === 'process' ? '<i class="fas fa-spinner fa-spin me-1"></i>' : '' ?><?= ucfirst(h($b['status'])) ?></span></td>
                    <td style="text-align:right;padding-right:20px">
                        <div style="display:flex;justify-content:flex-end;gap:4px">
                            <?php if ($b['status'] === 'draft' || $b['status'] === 'failed'): ?>
                            <form method="POST" class="d-inline"><input type="hidden" name="action" value="start"><input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
                                <button class="ab green" title="Start"><i class="fas fa-play"></i></button></form>
                            <?php elseif ($b['status'] === 'process'): ?>
                            <form method="POST" class="d-inline"><input type="hidden" name="action" value="pause"><input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
                                <button class="ab warn" title="Pause"><i class="fas fa-pause"></i></button></form>
                            <form method="POST" class="d-inline"><input type="hidden" name="action" value="stop"><input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
                                <button class="ab red" title="Stop"><i class="fas fa-stop"></i></button></form>
                            <?php endif; ?>
                            <form method="POST" class="d-inline" onsubmit="return confirm('Delete this broadcast?')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
                                <button class="ab red" title="Delete"><i class="fas fa-trash"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; else: ?>
                <tr><td colspan="5" style="text-align:center;padding:40px;color:var(--mut)">
                    <i class="fas fa-bullhorn" style="font-size:28px;display:block;margin-bottom:8px;opacity:.3"></i>
                    No broadcast tasks found.
                </td></tr>
                <?php endif; ?>
                </tbody>
            </table>
            <?php if ($total_pages > 1): ?>
            <div style="display:flex;justify-content:space-between;align-items:center;padding:12px 20px;border-top:1px solid var(--border)">
                <span style="font-size:12px;color:var(--mut)">Page <?= $page ?>/<?= $total_pages ?></span>
                <div style="display:flex;gap:3px">
                    <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                    <a href="?page=<?= $p ?><?= $filter_st ? '&status='.$filter_st : '' ?>" class="pg <?= $p == $page ? 'active' : '' ?>"><?= $p ?></a>
                    <?php endfor; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php load_footer(); ?>
