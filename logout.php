<?php
// logout.php - Destroys session and redirects to login
require_once __DIR__ . '/includes/bootstrap.php';
Auth::logout();
header('Location: ' . APP_URL . '/login');
exit;
