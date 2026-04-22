<?php
function h(?string $s): string {
    return htmlspecialchars((string)($s ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function load_header(string $title): void {
    $page = basename($_SERVER['PHP_SELF']);
    $nav = [
        ['index.php',    'fa-chart-line',     'Dashboard'],
        ['setup.php',    'fa-robot',          'Bot Setup'],
        ['users.php',    'fa-users',          'Bot Users'],
        ['packages.php', 'fa-box',            'Packages'],
        ['sessions.php', 'fa-mobile-alt',     'Sessions'],
        ['contacts.php', 'fa-address-book',   'Contacts'],
        ['broadcast.php','fa-bullhorn',        'Broadcast'],
        ['logs.php',     'fa-clipboard-list', 'Logs'],
    ];
    ?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($title) ?> · ProTel</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'><rect width='32' height='32' rx='8' fill='%233b82f6'/><text y='23' x='5' font-size='20'>✈</text></svg>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>/assets/css/admin.css" rel="stylesheet">
</head>
<body>

<!-- Sidebar overlay (mobile) -->
<div class="sb-overlay" id="sbOverlay"></div>

<!-- Sidebar -->
<aside class="sidebar" id="sidebar">
    <a class="sb-brand" href="index.php">
        <div class="sb-logo"><i class="fa-solid fa-paper-plane"></i></div>
        <div class="sb-name">Pro<span>Tel</span></div>
    </a>
    <div class="sb-scroll">
        <span class="sb-label">Main Menu</span>
        <?php foreach ($nav as [$file, $icon, $label]):
            if ($file === 'broadcast.php'): ?>
        <span class="sb-label" style="margin-top:8px">Tools</span>
        <?php endif; ?>
        <a class="sb-link <?= $page === $file ? 'active' : '' ?>" href="<?= $file ?>">
            <i class="fas <?= $icon ?>"></i> <?= $label ?>
        </a>
        <?php endforeach; ?>
    </div>
    <div class="sb-user">
        <div class="sb-ava"><i class="fas fa-user"></i></div>
        <div>
            <div class="sb-uname">Administrator</div>
            <div class="sb-urole">Super Admin</div>
        </div>
        <a href="logout.php" class="sb-logout" title="Logout"><i class="fas fa-right-from-bracket"></i></a>
    </div>
</aside>

<!-- Topbar -->
<div class="topbar">
    <button class="sb-toggle" id="sbToggle"><i class="fas fa-bars"></i></button>
    <h1 class="tb-title"><?= h($title) ?></h1>
</div>

<!-- Main -->
<div class="main-content">
    <div class="content-wrap">
<?php
}

function load_footer(): void { ?>
    </div><!-- /content-wrap -->
</div><!-- /main-content -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function(){
    var toggle  = document.getElementById('sbToggle');
    var sidebar = document.getElementById('sidebar');
    var overlay = document.getElementById('sbOverlay');
    function close() { sidebar.classList.remove('open'); overlay.classList.remove('show'); }
    if (toggle)  toggle.addEventListener('click', function(){ sidebar.classList.toggle('open'); overlay.classList.toggle('show'); });
    if (overlay) overlay.addEventListener('click', close);
})();
</script>
</body></html>
<?php
}
?>
