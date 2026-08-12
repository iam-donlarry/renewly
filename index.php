<?php
// index.php - System URL Router & Bootstrapper

require_once __DIR__ . '/includes/bootstrap.php';

// Parse clean route parameter
$route = $_GET['route'] ?? 'dashboard';
$route = trim($route, '/');

// Map clean URL paths to module handler files
$routes = [
    ''                  => 'modules/dashboard/index.php',
    'dashboard'         => 'modules/dashboard/index.php',
    'login'             => 'login.php',
    'logout'            => 'logout.php',
    'clients'           => 'modules/clients/index.php',
    'vendors'           => 'modules/vendors/index.php',
    'products'          => 'modules/products/index.php',
    'contracts'         => 'modules/contracts/index.php',
    'contracts/create'  => 'modules/contracts/create.php',
    'approvals'         => 'modules/contracts/approvals.php',
    'renewals'          => 'modules/renewals/index.php',
    'payments'          => 'modules/payments/index.php',
    'reports'           => 'modules/reports/index.php',
    'activity'          => 'modules/reports/audit.php',
    'users'             => 'modules/users/index.php',
    'roles'             => 'modules/roles/index.php',
    'settings'          => 'modules/settings/index.php'
];

// Handle parameterized routes like /contracts/c113f8aee0c3b or /contracts/3
if (preg_match('#^contracts/([^/]+)$#', $route, $matches) && $matches[1] !== 'create') {
    $_GET['token'] = $matches[1];
    $_GET['id'] = decodeId($matches[1]);
    $targetFile = __DIR__ . '/modules/contracts/view.php';
} elseif (isset($routes[$route])) {
    $targetFile = __DIR__ . '/' . $routes[$route];
} else {
    // Fallback or 404
    $targetFile = __DIR__ . '/modules/dashboard/index.php';
}

if (file_exists($targetFile)) {
    require_once $targetFile;
} else {
    http_response_code(404);
    echo "<h1>404 Page Not Found</h1><p>The requested route <code>/" . sanitize($route) . "</code> does not exist.</p>";
}
