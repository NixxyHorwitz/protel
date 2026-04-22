<?php
require_once __DIR__ . '/database.php';

function check_auth() {
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        header('Location: ' . BASE_URL . '/console/login');
        exit;
    }
}
