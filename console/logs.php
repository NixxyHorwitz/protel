<?php
require_once __DIR__ . '/../core/auth.php';
check_auth();
require_once __DIR__ . '/../core/layout.php';

$msg = $msg_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'clear_all') {
        foreach (glob(__DIR__ . '/../logs/*.log') as $f) file_put_contents($f, '');
        write_log('ADMIN', "System logs cleared by admin");
        $msg = "All log files cleared."; $msg_type = 'warning';
    } elseif ($action === 'clear_file') {
        $fname = basename($_POST['filename'] ?? '');
        if ($fname && preg_match('/^[\w\-]+\.log$/', $fname)) {
            $path = __DIR__ . '/../logs/' . $fname;
            if (file_exists($path)) { file_put_contents($path, ''); $msg = "File '$fname' cleared."; $msg_type = 'success'; }
        }
    }
}

$log_dir = __DIR__ . '/../logs/';
$log_files = [];
if (is_dir($log_dir)) {
    foreach (glob($log_dir . '*.log') as $f) {
        $log_files[] = ['name' => basename($f), 'path' => $f, 'size' => filesize($f), 'mtime' => filemtime($f)];
    }
    usort($log_files, fn($a, $b) => $b['mtime'] - $a['mtime']);
}

$active_file = basename($_GET['file'] ?? ($log_files[0]['name'] ?? ''));
$active_path = $log_dir . $active_file;
$search = strip_tags($_GET['q'] ?? '');

$lines = [];
if ($active_file && file_exists($active_path)) {
    $raw = file($active_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $raw = array_reverse($raw);
    if ($search) $raw = array_filter($raw, fn($ln) => stripos($ln, $search) !== false);
    $lines = array_values($raw);
}

$page     = max(1, (int)($_GET['page'] ?? 1));
$per_page = 50;
$total    = count($lines);
$total_p  = max(1, ceil($total / $per_page));
$offset   = ($page - 1) * $per_page;
$lines    = array_slice($lines, $offset, $per_page);

load_header('System Logs');

function log_badge($line) {
    if (stripos($line, '[ERROR]') !== false || stripos($line, '[WEBHOOK_ERROR]') !== false || stripos($line, 'FATAL') !== false) return 'bd-err';
    if (stripos($line, '[WARNING]') !== false) return 'bd-warn';
    if (stripos($line, '[BROADCAST]') !== false) return 'bd-pur';
    if (stripos($line, '[WEBHOOK]') !== false) return 'bd-acc';
    return 'bd-acc'; // Default
}
?>

<?php if ($msg): ?>
<div class="alert alert-<?= $msg_type ?> mb-3"><i class="fas fa-circle-info"></i> <?= h($msg) ?></div>
<?php endif; ?>

<div class="page-header">
    <div style="display:flex;justify-content:space-between;align-items:flex-end">
        <div>
            <h1>System Logs</h1>
            <p>Real-time monitoring of bot and system activity</p>
        </div>
        <form method="POST" onsubmit="return confirm('Clear ALL log files? This cannot be undone.')">
            <input type="hidden" name="action" value="clear_all">
            <button type="submit" class="btn btn-danger"><i class="fas fa-trash"></i> Clear All</button>
        </form>
    </div>
</div>

<div class="row g-3">
    <!-- File List -->
    <div class="col-md-3">
        <div class="card-c">
            <div class="ch" style="padding:16px 20px;border-bottom:1px solid var(--border)">
                <div class="ct"><i class="fas fa-folder-open me-2" style="color:var(--accent)"></i>Log Files</div>
            </div>
            <div style="display:flex;flex-direction:column;max-height:600px;overflow-y:auto">
                <?php if ($log_files): foreach ($log_files as $lf): $isActive = ($active_file === $lf['name']); ?>
                <a href="?file=<?= urlencode($lf['name']) ?>" style="
                    display:flex;justify-content:space-between;align-items:center;
                    padding:12px 16px; border-bottom:1px solid var(--border); text-decoration:none;
                    background:<?= $isActive ? 'var(--hover)' : 'transparent' ?>;
                    border-left:<?= $isActive ? '3px solid var(--accent)' : '3px solid transparent' ?>;
                ">
                    <div>
                        <div style="font-size:13px;font-weight:600;color:<?= $isActive ? 'var(--text)' : 'var(--sub)' ?>"><?= h($lf['name']) ?></div>
                        <div style="font-size:11px;color:var(--mut);margin-top:2px"><?= date('d M H:i', $lf['mtime']) ?></div>
                    </div>
                    <span class="bd <?= $isActive ? 'bd-acc' : '' ?>" style="<?= !$isActive ? 'background:var(--hover);color:var(--mut)' : '' ?>">
                        <?= $lf['size'] > 1024 ? round($lf['size']/1024,1).'KB' : $lf['size'].'B' ?>
                    </span>
                </a>
                <?php endforeach; else: ?>
                <div style="padding:20px;text-align:center;color:var(--mut);font-size:13px">No log files found.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Log Viewer -->
    <div class="col-md-9">
        <div class="card-c">
            <div class="ch" style="padding:16px 20px;border-bottom:1px solid var(--border);flex-wrap:wrap;gap:10px">
                <div class="ct">
                    <i class="fas fa-file-lines me-2" style="color:var(--accent)"></i>
                    <span class="mono" style="font-size:14px;background:none;padding:0"><?= h($active_file ?: 'No file') ?></span>
                </div>
                <div style="display:flex;gap:6px;align-items:center">
                    <form method="GET" style="display:flex;gap:4px">
                        <input type="hidden" name="file" value="<?= h($active_file) ?>">
                        <input type="text" name="q" class="form-control" placeholder="Search logs…" value="<?= h($search) ?>" style="width:200px">
                        <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search"></i></button>
                        <?php if ($search): ?><a href="?file=<?= urlencode($active_file) ?>" class="btn btn-secondary border btn-sm"><i class="fas fa-xmark"></i></a><?php endif; ?>
                    </form>
                    <?php if ($active_file): ?>
                    <form method="POST" onsubmit="return confirm('Clear this file?')">
                        <input type="hidden" name="action" value="clear_file"><input type="hidden" name="filename" value="<?= h($active_file) ?>">
                        <button type="submit" class="btn btn-warning btn-sm" style="color:#000"><i class="fas fa-eraser"></i></button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
            
            <div style="max-height:600px;overflow-y:auto;background:var(--bg)">
                <?php if ($active_file && file_exists($active_path)): ?>
                    <?php if ($lines): ?>
                    <table class="tbl" style="margin:0">
                        <tbody>
                            <?php foreach ($lines as $i => $line): 
                                $badge_class = log_badge($line);
                                $display = h($line);
                                if ($search) $display = preg_replace('/('.preg_quote(h($search), '/').')/i', '<mark style="background:var(--warn);color:#000;border-radius:3px;padding:0 2px">$1</mark>', $display);
                            ?>
                            <tr style="<?= str_contains($badge_class, 'err') ? 'background:rgba(239,68,68,.05)' : '' ?>">
                                <td style="width:40px;color:var(--mut);text-align:right;border-right:1px solid var(--border);padding:8px;font-size:11px;user-select:none"><?= $offset + $i + 1 ?></td>
                                <td style="padding:8px 12px;font-family:'JetBrains Mono',monospace;font-size:11.5px;color:var(--text);word-break:break-all"><?= $display ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <div style="padding:60px 20px;text-align:center;color:var(--mut)">
                        <i class="fas fa-file-circle-xmark" style="font-size:32px;opacity:.3;margin-bottom:10px;display:block"></i>
                        <?= $search ? "No matches" : "Empty log file" ?>
                    </div>
                    <?php endif; ?>
                <?php else: ?>
                <div style="padding:60px 20px;text-align:center;color:var(--mut)">
                    <i class="fas fa-folder-open" style="font-size:32px;opacity:.3;margin-bottom:10px;display:block"></i>
                    Select a log file from the left panel.
                </div>
                <?php endif; ?>
            </div>

            <?php if ($total_p > 1): ?>
            <div style="display:flex;justify-content:space-between;align-items:center;padding:12px 20px;border-top:1px solid var(--border)">
                <span style="font-size:12px;color:var(--mut)"><?= number_format($total) ?> entries · page <?= $page ?>/<?= $total_p ?></span>
                <div style="display:flex;gap:3px">
                    <?php for ($p = max(1, $page-2); $p <= min($total_p, $page+2); $p++): ?>
                    <a href="?file=<?= urlencode($active_file) ?>&page=<?= $p ?><?= $search ? '&q='.urlencode($search) : '' ?>" class="pg <?= $p == $page ? 'active' : '' ?>"><?= $p ?></a>
                    <?php endfor; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php load_footer(); ?>
