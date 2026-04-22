<?php
function h(?string $s): string {
    return htmlspecialchars((string)($s ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function load_header(string $title): void {
    $page    = basename($_SERVER['PHP_SELF']);
    $depth   = (strpos($_SERVER['PHP_SELF'], '/console/') !== false) ? '../' : '';
    $cssPath = $depth . 'assets/css/admin.css';
    $nav = [
        ['index.php',     'fa-chart-line',     'Dashboard',  'main'],
        ['setup.php',     'fa-robot',          'Bot Setup',  'main'],
        ['users.php',     'fa-users',          'Bot Users',  'main'],
        ['packages.php',  'fa-box',            'Packages',   'main'],
        ['sessions.php',  'fa-mobile-alt',     'Sessions',   'main'],
        ['contacts.php',  'fa-address-book',   'Contacts',   'tools'],
        ['broadcast.php', 'fa-bullhorn',       'Broadcast',  'tools'],
        ['logs.php',      'fa-clipboard-list', 'Logs',       'tools'],
    ];
    ?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= h($title) ?> · ProTel Admin</title>
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'><rect width='32' height='32' rx='8' fill='%233b82f6'/><text y='23' x='5' font-size='20'>✈</text></svg>">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
<link href="<?= $cssPath ?>" rel="stylesheet">
</head>
<body>

<div id="sbOverlay" class="sb-overlay"></div>

<aside id="sidebar" class="sidebar">
    <a class="sb-brand" href="index.php">
        <div class="sb-logo"><i class="fas fa-paper-plane"></i></div>
        <div class="sb-name">Pro<span>Tel</span></div>
    </a>

    <nav class="sb-scroll">
        <?php
        $currentGroup = '';
        foreach ($nav as [$file, $icon, $label, $group]):
            if ($group !== $currentGroup):
                $currentGroup = $group;
                echo '<div class="sb-label">' . ($group === 'tools' ? 'Tools' : 'Navigation') . '</div>';
            endif;
        ?>
        <a class="sb-link <?= $page === $file ? 'active' : '' ?>" href="<?= $file ?>">
            <i class="fas <?= $icon ?>"></i>
            <span><?= $label ?></span>
        </a>
        <?php endforeach; ?>
    </nav>

    <div class="sb-user">
        <div class="sb-ava"><i class="fas fa-user"></i></div>
        <div>
            <div class="sb-uname">Administrator</div>
            <div class="sb-urole">Super Admin</div>
        </div>
        <a href="logout.php" class="sb-logout" title="Logout"><i class="fas fa-right-from-bracket"></i></a>
    </div>
</aside>

<div class="main-wrap">
    <header class="topbar">
        <button class="sb-toggle" id="sbToggle" type="button"><i class="fas fa-bars"></i></button>
        <div class="tb-title"><?= h($title) ?></div>
        <div class="tb-right">
            <span class="tb-admin-pill"><i class="fas fa-circle-dot"></i> Admin</span>
        </div>
    </header>

    <main class="page-body">
<?php
}

function load_footer(): void {
    echo '</main></div><!-- /main-wrap -->';
    ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function(){
    var toggle  = document.getElementById('sbToggle');
    var sidebar = document.getElementById('sidebar');
    var overlay = document.getElementById('sbOverlay');
    if (!toggle) return;
    toggle.addEventListener('click', function(){
        sidebar.classList.toggle('open');
        overlay.classList.toggle('show');
    });
    overlay.addEventListener('click', function(){
        sidebar.classList.remove('open');
        overlay.classList.remove('show');
    });
})();
</script>
</body></html>
<?php
}
?>
