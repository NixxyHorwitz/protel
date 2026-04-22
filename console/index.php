<?php
require_once __DIR__ . '/../core/auth.php';
check_auth();
require_once __DIR__ . '/../core/layout.php';

$stats = [
    'users'      => (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
    'sessions'   => (int)$pdo->query("SELECT COUNT(*) FROM user_sessions WHERE status='active'")->fetchColumn(),
    'contacts'   => (int)$pdo->query("SELECT COUNT(*) FROM contacts")->fetchColumn(),
    'broadcasts' => (int)$pdo->query("SELECT COUNT(*) FROM broadcasts")->fetchColumn(),
];

load_header('Dashboard');
?>

<div class="row g-3 mb-4">
    <?php
    $cards = [
        ['Bot Users',        $stats['users'],      'fa-users',         'var(--accent)',   'var(--blue-dim)'],
        ['Active Sessions',  $stats['sessions'],   'fa-mobile-alt',    '#3fb950',         'var(--green-dim)'],
        ['Total Contacts',   $stats['contacts'],   'fa-address-book',  '#e3b341',         'var(--yellow-dim)'],
        ['Broadcasts',       $stats['broadcasts'], 'fa-bullhorn',      '#ff7b72',         'var(--red-dim)'],
    ];
    foreach ($cards as [$label, $val, $icon, $color, $bg]):
    ?>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:<?= $bg ?>; color:<?= $color ?>">
                <i class="fas <?= $icon ?>"></i>
            </div>
            <div>
                <div class="stat-val"><?= number_format($val) ?></div>
                <div class="stat-label"><?= $label ?></div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between">
        <span><i class="fas fa-clock me-2 text-muted" style="font-size:.75rem"></i>Recent Sessions</span>
        <a href="sessions.php" class="btn btn-sm btn-secondary">View All</a>
    </div>
    <div class="table-responsive">
        <table class="table">
            <thead><tr>
                <th>Telegram ID</th>
                <th>Phone</th>
                <th>Status</th>
                <th>Date</th>
            </tr></thead>
            <tbody>
            <?php
            $rows = $pdo->query("SELECT * FROM user_sessions ORDER BY id DESC LIMIT 8")->fetchAll();
            if ($rows): foreach ($rows as $r):
                $badge = match($r['status']) {
                    'active'   => 'badge-success',
                    'pending','wait_otp','wait_password' => 'badge-warning',
                    'banned'   => 'badge-danger',
                    default    => 'badge-secondary'
                };
            ?>
            <tr>
                <td><code class="mono"><?= h($r['telegram_id']) ?></code></td>
                <td><?= h($r['phone_number'] ?: '—') ?></td>
                <td><span class="badge <?= $badge ?>"><?= ucfirst(h($r['status'])) ?></span></td>
                <td style="color:var(--text-muted);font-size:.75rem"><?= date('d M H:i', strtotime($r['created_at'])) ?></td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="4" class="text-center py-4" style="color:var(--text-muted)">No sessions yet.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php load_footer(); ?>
