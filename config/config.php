<?php
// config/config.php
// System Configuration Settings & Constants

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Base Path
define('BASE_PATH', dirname(__DIR__));

// Optional .env File Loader
$envFile = BASE_PATH . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        if (strpos($line, '=') !== false) {
            list($key, $val) = explode('=', $line, 2);
            $key = trim($key);
            $val = trim(trim($val), "\"'");
            if (!array_key_exists($key, $_SERVER) && !array_key_exists($key, $_ENV)) {
                putenv("$key=$val");
                $_ENV[$key] = $val;
                $_SERVER[$key] = $val;
            }
        }
    }
}

// Environment & App Basics
define('APP_NAME', 'Renewly');

// Dynamic APP_URL Auto-Detection
if (!defined('APP_URL')) {
    $envAppUrl = getenv('APP_URL') ?: ($_ENV['APP_URL'] ?? null);
    if (!empty($envAppUrl)) {
        define('APP_URL', rtrim($envAppUrl, '/'));
    } elseif (php_sapi_name() === 'cli' || empty($_SERVER['HTTP_HOST'])) {
        define('APP_URL', 'http://localhost/Renewly');
    } else {
        // Detect protocol (HTTP vs HTTPS, with reverse proxy / Cloudflare / SSL termination support)
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https')
            || (isset($_SERVER['HTTP_X_FORWARDED_SSL']) && strtolower($_SERVER['HTTP_X_FORWARDED_SSL']) === 'on');
        $protocol = $isHttps ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'];

        // Determine URL subfolder (if any) relative to document root
        $docRoot = !empty($_SERVER['DOCUMENT_ROOT']) ? str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'])) : '';
        $appRoot = str_replace('\\', '/', realpath(BASE_PATH));

        $subPath = '';
        if ($docRoot && $appRoot && strpos($appRoot, $docRoot) === 0) {
            $subPath = substr($appRoot, strlen($docRoot));
            $subPath = ($subPath === '/' || $subPath === '\\') ? '' : rtrim($subPath, '/');
        } elseif (!empty($_SERVER['SCRIPT_NAME'])) {
            $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
            $subPath = ($scriptDir === '/' || $scriptDir === '\\' || $scriptDir === '.') ? '' : rtrim($scriptDir, '/');
        }

        define('APP_URL', $protocol . $host . $subPath);
    }
}

// Timezone
date_default_timezone_set('Africa/Lagos');

// Brand Design Tokens
define('BRAND_COLOR', '#12b1b0');
define('BRAND_HOVER', '#0f9d9c');
define('FONT_FAMILY', "'Outfit', sans-serif");

// Database Credentials (from .env or environment with localhost defaults)
define('DB_HOST', getenv('DB_HOST') ?: ($_ENV['DB_HOST'] ?? 'localhost'));
define('DB_NAME', getenv('DB_NAME') ?: ($_ENV['DB_NAME'] ?? 'renewly'));
define('DB_USER', getenv('DB_USER') ?: ($_ENV['DB_USER'] ?? 'root'));
define('DB_PASS', getenv('DB_PASS') !== false ? (string)getenv('DB_PASS') : (string)($_ENV['DB_PASS'] ?? ''));
define('DB_CHARSET', getenv('DB_CHARSET') ?: ($_ENV['DB_CHARSET'] ?? 'utf8mb4'));

