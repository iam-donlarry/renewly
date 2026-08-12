<?php
// config/config.php
// System Configuration Settings & Constants

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Environment & App Basics
define('APP_NAME', 'Renewly');
define('APP_URL', 'http://localhost/Renewly');
define('BASE_PATH', dirname(__DIR__));

// Timezone
date_default_timezone_set('Africa/Lagos');

// Brand Design Tokens
define('BRAND_COLOR', '#12b1b0');
define('BRAND_HOVER', '#0f9d9c');
define('FONT_FAMILY', "'Outfit', sans-serif");

// Database Credentials
define('DB_HOST', 'localhost');
define('DB_NAME', 'renewly');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');
