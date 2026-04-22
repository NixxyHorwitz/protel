<?php
require_once __DIR__ . '/../core/auth.php';
session_destroy();
header('Location: login.php');
exit;
