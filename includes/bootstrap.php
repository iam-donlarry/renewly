<?php
// includes/bootstrap.php - Core System Bootstrapper
if (session_status() === PHP_SESSION_NONE) {
    ob_start();
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';

// Class Autoloader for /classes/ directory
spl_autoload_register(function ($className) {
    $file = BASE_PATH . '/classes/' . $className . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

// Require Core Classes to ensure global helpers (e.g. hasPermission) are loaded
require_once BASE_PATH . '/classes/Auth.php';
require_once BASE_PATH . '/classes/RBAC.php';
require_once BASE_PATH . '/classes/AuditLogger.php';

// Initialize Session
Auth::initSession();
