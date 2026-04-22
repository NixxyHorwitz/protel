<?php
require_once __DIR__ . '/../core/auth.php';
check_auth();
require_once __DIR__ . '/../core/layout.php';

$msg = '';

// Handle clear action
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'clear_all') {
        $log_dir = __DIR__ . '/../logs/';
        $files = glob($log_dir . '*.log');
        foreach ($files as $f) {
            file_put_contents($f, '');
        }
        write_log('ADMIN', "System logs cleared by admin");
        $msg = "All log files cleared.";
    } elseif ($action === 'clear_file') {
        $fname = basename($_POST['filename'] ?? '');
        if ($fname && preg_match('/^[\w\-]+\.log$/', $fname)) {
            $path = __DIR__ . '/../logs/' . $fname;
            if (file_exists($path)) {
                file_put_contents($path, '');
                $msg = "File '$fname' cleared.";
            }
        }
    }
}

// Scan log files
$log_dir = __DIR__ . '/../logs/';
$log_files = [];
if (is_dir($log_dir)) {
    foreach (glob($log_dir . '*.log') as $f) {
        $log_files[] = [
            'name'  => basename($f),
            'path'  => $f,
            'size'  => filesize($f),
            'mtime' => filemtime($f),
        ];
    }
    usort($log_files, fn($a, $b) => $b['mtime'] - $a['mtime']);
}

// Active file to view
$active_file = $_GET['file'] ?? ($log_files[0]['name'] ?? '');
$active_file = basename($active_file);
$active_path = $log_dir . $active_file;

// Search within logs
$search = trim($_GET['q'] ?? '');

$lines = [];
if ($active_file && file_exists($active_path) && is_readable($active_path)) {
    $raw = file($active_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $raw = array_reverse($raw); // newest first

    if ($search) {
        $raw = array_filter($raw, fn($ln) => stripos($ln, $search) !== false);
    }

    $lines = array_values($raw);
}

// Pagination
$page     = max(1, (int)($_GET['page'] ?? 1));
$per_page = 50;
$total    = count($lines);
$total_p  = ceil($total / $per_page);
$offset   = ($page - 1) * $per_page;
$lines    = array_slice($lines, $offset, $per_page);

load_header('System Logs');

// Helper to colour-code log level
function log_badge($line) {
    $map = [
        'ERROR'         => 'danger',
        'WEBHOOK_ERROR' => 'danger',
        'FAILED'        => 'danger',
        'WARNING'       => 'warning',
        'WEBHOOK'       => 'info',
        'BROADCAST'     => 'primary',
        'ADMIN'         => 'secondary',
        'SYSTEM'        => 'dark',
    ];
    foreach ($map as $kw => $cls) {
        if (stripos($line, "[$kw]") !== false || stripos($line, " $kw ") !== false) {
            return $cls;
        }
    }
    return 'secondary';
}
?>

<?php if ($msg): ?>
<div class="alert alert-warning alert-dismissible fade show py-2 small fw-medium mb-3" role="alert">
    <i class="fa-solid fa-circle-info me-1"></i> <?= htmlspecialchars($msg) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h5 class="fw-bold mb-1">System Logs</h5>
        <p class="text-muted small mb-0">Real-time monitoring of bot and system activity.</p>
    </div>
    <form method="POST" onsubmit="return confirm('Clear ALL log files? This cannot be undone.')">
        <input type="hidden" name="action" value="clear_all">
        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fa-solid fa-trash me-1"></i>Clear All Logs</button>
    </form>
</div>

<div class="row g-3">
    <!-- File List -->
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-header border-bottom">
                <h6 class="mb-0 fw-bold"><i class="fa-solid fa-folder-open text-primary me-2"></i>Log Files</h6>
            </div>
            <div class="list-group list-group-flush">
                <?php if (count($log_files) > 0): ?>
                    <?php foreach ($log_files as $lf): ?>
                        <a href="?file=<?= urlencode($lf['name']) ?>"
                           class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-2 px-3 <?= $active_file === $lf['name'] ? 'active' : '' ?>">
                            <div>
                                <div class="fw-medium small"><?= htmlspecialchars($lf['name']) ?></div>
                                <div class="<?= $active_file === $lf['name'] ? 'text-white-50' : 'text-muted' ?> x-small" style="font-size: 0.7rem;">
                                    <?= date('d M H:i', $lf['mtime']) ?>
                                </div>
                            </div>
                            <span class="badge <?= $active_file === $lf['name'] ? 'bg-white text-primary' : 'bg-secondary-subtle text-secondary' ?> rounded-pill">
                                <?= $lf['size'] > 1024 ? round($lf['size']/1024, 1).'KB' : $lf['size'].'B' ?>
                            </span>
                        </a>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="list-group-item text-muted small py-3 text-center">No log files found.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Log Viewer -->
    <div class="col-md-9">
        <div class="card border-0 shadow-sm">
            <div class="card-header border-bottom d-flex justify-content-between align-items-center gap-2">
                <h6 class="mb-0 fw-bold font-monospace">
                    <i class="fa-solid fa-file-lines text-primary me-2"></i>
                    <?= $active_file ? htmlspecialchars($active_file) : 'No file selected' ?>
                </h6>
                <div class="d-flex gap-2 align-items-center">
                    <form method="GET" class="d-flex gap-1">
                        <input type="hidden" name="file" value="<?= htmlspecialchars($active_file) ?>">
                        <input type="text" name="q" class="form-control form-control-sm" placeholder="Search in logs..." value="<?= htmlspecialchars($search) ?>" style="width: 200px;">
                        <button type="submit" class="btn btn-sm btn-primary"><i class="fa-solid fa-search"></i></button>
                        <?php if ($search): ?><a href="?file=<?= urlencode($active_file) ?>" class="btn btn-sm btn-light border"><i class="fa-solid fa-xmark"></i></a><?php endif; ?>
                    </form>
                    <?php if ($active_file): ?>
                    <form method="POST" class="d-inline" onsubmit="return confirm('Clear this log file?')">
                        <input type="hidden" name="action" value="clear_file">
                        <input type="hidden" name="filename" value="<?= htmlspecialchars($active_file) ?>">
                        <input type="hidden" name="file_redirect" value="<?= htmlspecialchars($active_file) ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fa-solid fa-eraser me-1"></i>Clear</button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body p-0">
                <?php if ($active_file && file_exists($active_path)): ?>
                    <?php if (count($lines) > 0): ?>
                        <div style="max-height: 600px; overflow-y: auto;">
                            <table class="table table-sm mb-0 font-monospace" style="font-size: 0.78rem;">
                                <tbody>
                                    <?php foreach ($lines as $i => $line): ?>
                                        <?php
                                            $badge_class = log_badge($line);
                                            // Highlight search term
                                            $display = htmlspecialchars($line);
                                            if ($search) {
                                                $display = preg_replace(
                                                    '/(' . preg_quote(htmlspecialchars($search), '/') . ')/i',
                                                    '<mark>$1</mark>',
                                                    $display
                                                );
                                            }
                                        ?>
                                        <tr class="<?= $badge_class === 'danger' ? 'table-danger' : ($badge_class === 'warning' ? 'table-warning' : '') ?>">
                                            <td class="text-muted pe-2 border-end" style="width: 45px; user-select: none;"><?= $offset + $i + 1 ?></td>
                                            <td class="ps-3"><?= $display ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <?php if ($total_p > 1): ?>
                        <div class="border-top d-flex justify-content-between align-items-center py-2 px-3">
                            <span class="small text-muted"><?= number_format($total) ?> entries · page <?= $page ?> of <?= $total_p ?></span>
                            <nav>
                                <ul class="pagination pagination-sm mb-0">
                                    <?php for ($p = max(1, $page-2); $p <= min($total_p, $page+2); $p++): ?>
                                        <li class="page-item <?= $p == $page ? 'active' : '' ?>">
                                            <a class="page-link" href="?file=<?= urlencode($active_file) ?>&page=<?= $p ?><?= $search ? '&q='.urlencode($search) : '' ?>"><?= $p ?></a>
                                        </li>
                                    <?php endfor; ?>
                                </ul>
                            </nav>
                        </div>
                        <?php endif; ?>

                    <?php else: ?>
                        <div class="text-center py-5 text-muted">
                            <i class="fa-solid fa-file-circle-xmark fs-3 d-block mb-2 opacity-25"></i>
                            <?= $search ? "No log entries matching \"" . htmlspecialchars($search) . "\"." : "This log file is empty." ?>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="text-center py-5 text-muted">
                        <i class="fa-solid fa-folder-open fs-3 d-block mb-2 opacity-25"></i>
                        Select a log file from the left panel.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php load_footer(); ?>
