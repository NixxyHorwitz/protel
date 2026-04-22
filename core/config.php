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
if (!function_exists('base_url')) {
  function base_url(string $path = ''): string
  {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
      $scheme = 'https';
    }
    
    // Support auto-detect subfolder without breaking Laragon's Virtual Host (e.g. protel.test)
    $basePath = dirname($_SERVER['SCRIPT_NAME']);
    $basePath = str_replace(['/console', '/core', '\\'], ['', '', '/'], $basePath);
    if ($basePath === '/') $basePath = '';
    
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $root   = rtrim($scheme . '://' . $host . $basePath . '/', '/') . '/';
    return $root . ltrim($path, '/');
  }
}

// Assign to BASE_URL to preserve compatibility with the rest of the application
define('BASE_URL', rtrim(base_url(), '/'));

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
