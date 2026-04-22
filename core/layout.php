<?php
function h(?string $s): string {
    return htmlspecialchars((string)($s ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function load_header(string $title): void {
    $page = basename($_SERVER['PHP_SELF']);
    $nav = [
        ['dashboard' ,'index.php',    'fa-chart-line',    'Dashboard'],
        ['setup',      'setup.php',    'fa-robot',         'Bot Setup'],
        ['users',      'users.php',    'fa-users',         'Bot Users'],
        ['packages',   'packages.php', 'fa-box',           'Packages'],
        ['sessions',   'sessions.php', 'fa-mobile-alt',    'Sessions'],
        ['contacts',   'contacts.php', 'fa-address-book',  'Contacts'],
        ['broadcast',  'broadcast.php','fa-bullhorn',       'Broadcast'],
        ['logs',       'logs.php',     'fa-clipboard-list','Logs'],
    ];
    ?><!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($title) ?> · ProTel</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'><rect width='32' height='32' rx='8' fill='%233b82f6'/><text y='22' x='5' font-size='20'>✈</text></svg>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>/assets/css/admin.css" rel="stylesheet">
</head>
<body>
<div class="sidebar-overlay" id="sidebarOverlay"></div>
<div class="layout-wrapper">
    <aside class="layout-sidebar" id="sidebar">
        <a class="sidebar-brand" href="index.php">
            <div class="brand-icon"><i class="fa-solid fa-paper-plane"></i></div>
            <span class="brand-name">ProTel</span>
        </a>
        <nav class="sidebar-nav">
            <span class="nav-label">Navigation</span>
            <?php foreach ($nav as [$_slug, $file, $icon, $label]): ?>
                <?php if ($file === 'broadcast.php'): ?>
                    <span class="nav-label" style="margin-top:0.5rem;">Tools</span>
                <?php endif; ?>
                <a class="sidebar-link <?= $page === $file ? 'active' : '' ?>" href="<?= $file ?>">
                    <i class="fas <?= $icon ?>"></i> <?= $label ?>
                </a>
            <?php endforeach; ?>
            <div style="flex:1"></div>
            <a class="sidebar-link danger" href="logout.php" style="margin-top:1rem;">
                <i class="fas fa-right-from-bracket"></i> Logout
            </a>
        </nav>
    </aside>

    <div class="layout-content">
        <header class="topbar">
            <div class="topbar-title">
                <i class="fas fa-bars" id="sidebar-toggle"></i>
                <?= h($title) ?>
            </div>
            <div class="topbar-right">
                <span class="admin-badge"><i class="fas fa-circle"></i> Admin</span>
            </div>
        </header>
        <main class="layout-main">
<?php
}

function load_footer(): void {
    echo '</main></div></div>';
    ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    (function(){
        var toggle = document.getElementById('sidebar-toggle');
        var sidebar = document.getElementById('sidebar');
        var overlay = document.getElementById('sidebarOverlay');
        function close() { sidebar.classList.remove('open'); overlay.classList.remove('open'); }
        if(toggle) toggle.addEventListener('click', function(){
            sidebar.classList.toggle('open');
            overlay.classList.toggle('open');
        });
        if(overlay) overlay.addEventListener('click', close);
    })();
    </script>
    <?php
    echo '</body></html>';
}
?>
