<?php
session_start();
// Error Settings
define('DEV_MODE', true);
if (DEV_MODE) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(0);
}
define('BASE_URL', 'http://localhost/protel');

// Function for proper logging
function write_log($type, $msg, $file = 'system.log') {
    $logFile = __DIR__ . '/../logs/' . $file;
    $date = date('Y-m-d H:i:s');
    $content = "[$date][$type] $msg\n";
    file_put_contents($logFile, $content, FILE_APPEND);
}

// Exception wrapper
set_exception_handler(function($e) {
    write_log('ERROR', $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    if (DEV_MODE) { echo "<b>Error:</b> " . $e->getMessage(); }
});
