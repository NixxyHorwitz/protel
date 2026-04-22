<?php
function load_header($title) {
    echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . $title . ' - ProTel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="' . BASE_URL . '/assets/css/admin.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
    <div class="layout-wrapper">
        <aside class="layout-sidebar p-3">
            <div class="d-flex align-items-center mb-4 px-2">
                <div class="bg-primary text-white rounded p-2 me-2 d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                    <i class="fa-solid fa-paper-plane"></i>
                </div>
                <h5 class="mb-0 fw-bold text-white">ProTel</h5>
            </div>
            <div class="text-xs fw-bold text-muted text-uppercase px-3 mb-2" style="font-size: 0.7rem;">Menu</div>
            <nav>
                <a href="index" class="sidebar-link ' . (basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : '') . '"><i class="fa-solid fa-chart-line"></i> Dashboard</a>
                <a href="setup" class="sidebar-link ' . (basename($_SERVER['PHP_SELF']) == 'setup.php' ? 'active' : '') . '"><i class="fa-solid fa-robot"></i> Bot Setup</a>
                <a class="sidebar-link ' . (basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active' : '') . '" href="users.php">
                    <i class="fas fa-users"></i> Bot Users (Clients)
                </a>
                <a class="sidebar-link ' . (basename($_SERVER['PHP_SELF']) == 'packages.php' ? 'active' : '') . '" href="packages.php">
                    <i class="fas fa-box"></i> Subscription Packages
                </a>
                <a class="sidebar-link ' . (basename($_SERVER['PHP_SELF']) == 'sessions.php' ? 'active' : '') . '" href="sessions.php">
                    <i class="fas fa-mobile-alt"></i> Connected Accounts (Sessions)
                </a>
                <a class="sidebar-link ' . (basename($_SERVER['PHP_SELF']) == 'contacts.php' ? 'active' : '') . '" href="contacts.php">
                    <i class="fas fa-address-book"></i> Contacts
                </a>
                <a class="sidebar-link ' . (basename($_SERVER['PHP_SELF']) == 'broadcast.php' ? 'active' : '') . '" href="broadcast.php">
                    <i class="fa-solid fa-bullhorn"></i> Broadcast Task
                </a>
                <a href="logs" class="sidebar-link ' . (basename($_SERVER['PHP_SELF']) == 'logs.php' ? 'active' : '') . '"><i class="fa-solid fa-clipboard-list"></i> System Logs</a>
                <a href="logout" class="sidebar-link text-danger mt-4"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
            </nav>
        </aside>
        <div class="layout-content">
            <header class="topbar justify-content-between">
                <div class="fw-medium text-muted">
                    <i class="fa-solid fa-bars d-md-none me-3 cursor-pointer"></i>
                    ' . $title . '
                </div>
                <div class="d-flex align-items-center fw-medium">
                    <span class="bg-light rounded-circle p-2 d-flex align-items-center justify-content-center me-2 text-secondary" style="width: 35px; height: 35px;">
                        <i class="fa-solid fa-user"></i>
                    </span>
                    Admin
                </div>
            </header>
            <main class="layout-main">';
}

function load_footer() {
    echo '    </main>
        </div>
    </div>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        $(document).ready(function() {
            $(".fa-bars").click(function() {
                $(".layout-sidebar").toggle();
            });
        });
    </script>
</body>
</html>';
}
?>
