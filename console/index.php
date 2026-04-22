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

<div class="page-header">
    <h1>Dashboard</h1>
    <p>Overview of your ProTel userbot platform</p>
</div>

<div class="row g-3 mb-4">
    <?php
    $cards = [
        ['Bot Users',       $stats['users'],      'fa-users',      'blue'],
        ['Active Sessions', $stats['sessions'],   'fa-mobile-alt', 'green'],
        ['Total Contacts',  $stats['contacts'],   'fa-address-book','orange'],
        ['Broadcasts',      $stats['broadcasts'], 'fa-bullhorn',   'purple'],
    ];
    foreach ($cards as [$label, $val, $icon, $color]):
    ?>
    <div class="col-6 col-md-3">
        <div class="sc <?= $color ?>">
            <div class="si <?= $color ?>"><i class="fas <?= $icon ?>"></i></div>
            <div class="sv"><?= number_format($val) ?></div>
            <div class="sl"><?= $label ?></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="card-c">
    <div class="ch">
        <div>
            <div class="ct"><i class="fas fa-clock me-2" style="color:var(--mut);font-size:13px"></i>Recent Sessions</div>
            <div class="cs">Last 8 connected accounts</div>
        </div>
        <a href="sessions.php" class="btn btn-secondary btn-sm">View All</a>
    </div>
    <div class="cb p-0">
        <table class="tbl">
            <thead>
                <tr>
                    <th>Telegram ID</th>
                    <th>Phone</th>
                    <th>Status</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
            <?php
            $rows = $pdo->query("SELECT * FROM user_sessions ORDER BY id DESC LIMIT 8")->fetchAll();
            if ($rows): foreach ($rows as $r):
                [$bc, $bclass] = match($r['status']) {
                    'active'   => [$r['status'], 'bd-ok'],
                    'banned'   => [$r['status'], 'bd-err'],
                    'wait_otp','wait_password' => [$r['status'], 'bd-warn'],
                    default    => [$r['status'], 'bd-acc'],
                };
            ?>
            <tr>
                <td><code class="mono"><?= h($r['telegram_id']) ?></code></td>
                <td style="color:var(--sub)"><?= h($r['phone_number'] ?: '—') ?></td>
                <td><span class="bd <?= $bclass ?>"><?= ucfirst(h($r['status'])) ?></span></td>
                <td style="color:var(--mut);font-size:12px"><?= date('d M Y H:i', strtotime($r['created_at'])) ?></td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="4" style="text-align:center;padding:32px;color:var(--mut)">No sessions yet.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php load_footer(); ?>
