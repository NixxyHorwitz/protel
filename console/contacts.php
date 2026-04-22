<?php
require_once __DIR__ . '/../core/auth.php';
check_auth();
require_once __DIR__ . '/../core/layout.php';

$msg = $msg_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);

    if ($action === 'delete' && $id > 0) {
        $pdo->prepare("DELETE FROM contacts WHERE id = ?")->execute([$id]);
        $msg = "Contact deleted successfully."; $msg_type = 'danger';
    } elseif ($action === 'delete_all') {
        $session_id = (int)($_POST['session_id'] ?? 0);
        if ($session_id > 0) {
            $pdo->prepare("DELETE FROM contacts WHERE session_id = ?")->execute([$session_id]);
            $msg = "All contacts for session deleted."; $msg_type = 'warning';
        }
    } elseif ($action === 'mark_valid' && $id > 0) {
        $pdo->prepare("UPDATE contacts SET status = 'valid' WHERE id = ?")->execute([$id]);
        $msg = "Contact marked as valid."; $msg_type = 'success';
    }
}

$search    = strip_tags($_GET['q'] ?? '');
$filter_s  = (int)($_GET['session_id'] ?? 0);
$filter_st = strip_tags($_GET['status'] ?? '');
$page      = max(1, (int)($_GET['page'] ?? 1));
$per_page  = 25;
$offset    = ($page - 1) * $per_page;

$where = ['1=1']; $params = [];
if ($search) { $where[] = "(c.phone_or_username LIKE ? OR c.name LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }
if ($filter_s) { $where[] = "c.session_id = ?"; $params[] = $filter_s; }
if ($filter_st) { $where[] = "c.status = ?"; $params[] = $filter_st; }
$where_str = implode(' AND ', $where);

$cnt_stmt = $pdo->prepare("SELECT COUNT(*) FROM contacts c WHERE $where_str");
$cnt_stmt->execute($params);
$total = $cnt_stmt->fetchColumn();

$data_stmt = $pdo->prepare("SELECT c.*, u.telegram_id, u.phone_number AS session_phone FROM contacts c LEFT JOIN user_sessions u ON c.session_id = u.id WHERE $where_str ORDER BY c.id DESC LIMIT $per_page OFFSET $offset");
$data_stmt->execute($params);
$contacts = $data_stmt->fetchAll();

$total_pages = max(1, ceil($total / $per_page));
$sessions_list = $pdo->query("SELECT id, telegram_id, phone_number FROM user_sessions ORDER BY id DESC")->fetchAll();

load_header('Contacts');
?>

<?php if ($msg): ?>
<div class="alert alert-<?= $msg_type ?> mb-3"><i class="fas fa-circle-info"></i> <?= h($msg) ?></div>
<?php endif; ?>

<div class="page-header">
    <div style="display:flex;justify-content:space-between;align-items:flex-end">
        <div>
            <h1>Contacts</h1>
            <p>Total <?= number_format($total) ?> contact(s) collected</p>
        </div>
        <form method="POST" onsubmit="return confirm('Delete ALL contacts for the selected session? This cannot be undone.')">
            <input type="hidden" name="action" value="delete_all">
            <div style="display:flex;gap:6px">
                <select name="session_id" class="form-select" style="width:200px" required>
                    <option value="">— Select Session —</option>
                    <?php foreach ($sessions_list as $sl): ?>
                        <option value="<?= $sl['id'] ?>"><?= h($sl['telegram_id']) ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-danger"><i class="fas fa-trash"></i> Wipe</button>
            </div>
        </form>
    </div>
</div>

<div class="card-c mb-4">
    <div class="cb" style="padding:16px 20px">
        <form method="GET" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
            <div style="flex:1;min-width:200px"><input type="text" name="q" class="form-control" placeholder="Search name or phone…" value="<?= h($search) ?>"></div>
            <div style="width:180px">
                <select name="session_id" class="form-select">
                    <option value="">All Sessions</option>
                    <?php foreach ($sessions_list as $sl): ?>
                    <option value="<?= $sl['id'] ?>" <?= $filter_s == $sl['id'] ? 'selected' : '' ?>><?= h($sl['telegram_id']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="width:140px">
                <select name="status" class="form-select">
                    <option value="">All Status</option>
                    <option value="valid" <?= $filter_st == 'valid' ? 'selected' : '' ?>>Valid</option>
                    <option value="invalid" <?= $filter_st == 'invalid' ? 'selected' : '' ?>>Invalid</option>
                    <option value="sent" <?= $filter_st == 'sent' ? 'selected' : '' ?>>Sent</option>
                </select>
            </div>
            <div style="display:flex;gap:4px">
                <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Filter</button>
                <a href="contacts.php" class="btn btn-secondary border"><i class="fas fa-xmark"></i></a>
            </div>
        </form>
    </div>
</div>

<div class="card-c">
    <table class="tbl">
        <thead>
            <tr>
                <th style="padding-left:20px;width:40px">#</th>
                <th>Name</th>
                <th>Phone / Username</th>
                <th>Type</th>
                <th>Session ID</th>
                <th>Status</th>
                <th style="text-align:right;padding-right:20px">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($contacts): foreach ($contacts as $i => $c):
                $bdc = match($c['status']) { 'valid'=>'bd-ok', 'invalid'=>'bd-err', 'sent'=>'bd-pur', default=>'bd-acc' };
                $tdc = match($c['type']) { 'phone'=>'bd-acc', 'username'=>'bd-warn', default=>'bd-acc' };
            ?>
            <tr>
                <td style="padding-left:20px;color:var(--mut)"><?= $offset + $i + 1 ?></td>
                <td><span style="font-weight:600"><?= h($c['name'] ?: '—') ?></span></td>
                <td><code class="mono" style="font-size:12.5px"><?= h($c['phone_or_username']) ?></code></td>
                <td><span class="bd <?= $tdc ?>"><?= ucfirst($c['type']) ?></span></td>
                <td style="color:var(--mut);font-size:12px"><?= h($c['telegram_id'] ?? '—') ?></td>
                <td><span class="bd <?= $bdc ?>"><?= ucfirst($c['status']) ?></span></td>
                <td style="text-align:right;padding-right:20px">
                    <div style="display:flex;justify-content:flex-end;gap:4px">
                        <?php if ($c['status'] !== 'valid'): ?>
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="action" value="mark_valid"><input type="hidden" name="id" value="<?= $c['id'] ?>">
                            <button class="ab green" title="Mark Valid"><i class="fas fa-check"></i></button>
                        </form>
                        <?php endif; ?>
                        <form method="POST" class="d-inline" onsubmit="return confirm('Delete this contact?')">
                            <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $c['id'] ?>">
                            <button class="ab red" title="Delete"><i class="fas fa-trash"></i></button>
                        </form>
                    </div>
                </td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="7" style="text-align:center;padding:40px;color:var(--mut)"><i class="fas fa-address-book" style="font-size:28px;display:block;margin-bottom:8px;opacity:.3"></i>No contacts found</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
    <?php if ($total_pages > 1): ?>
    <div style="display:flex;justify-content:space-between;align-items:center;padding:12px 20px;border-top:1px solid var(--border)">
        <span style="font-size:12px;color:var(--mut)">Page <?= $page ?>/<?= $total_pages ?></span>
        <div style="display:flex;gap:3px">
            <?php for ($p = 1; $p <= $total_pages; $p++): ?>
            <a href="?page=<?= $p ?><?= $search ? '&q='.urlencode($search) : '' ?><?= $filter_s ? '&session_id='.$filter_s : '' ?><?= $filter_st ? '&status='.$filter_st : '' ?>" class="pg <?= $p == $page ? 'active' : '' ?>"><?= $p ?></a>
            <?php endfor; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php load_footer(); ?>
