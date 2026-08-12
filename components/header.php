<?php
// components/header.php - Top Navigation Bar with Redesigned Header Actions
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
$currentUser = Auth::user();

// Calculate Notifications Count
$pdo = getDB();
$headerPendingApprovals = 0;
$headerExpiringCount = 0;

if (hasPermission('contracts.approve')) {
    $headerPendingApprovals = (int)$pdo->query("SELECT COUNT(*) FROM contracts WHERE approval_status = 'pending'")->fetchColumn();
}
if (hasPermission('renewals.view')) {
    $headerExpiringCount = (int)$pdo->query("SELECT COUNT(*) FROM contracts WHERE DATEDIFF(expiry_date, CURRENT_DATE) BETWEEN 0 AND 30 AND status IN ('active', 'expiring')")->fetchColumn();
}
$headerNotificationCount = $headerPendingApprovals + $headerExpiringCount;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? sanitize($pageTitle) . ' | ' : '' ?><?= APP_NAME ?></title>
    <!-- Outfit Google Font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <!-- Application CSS Design System -->
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css">
</head>
<body>
<div class="app-wrapper">
    <!-- Top Header Navigation -->
    <header class="app-header">
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-sm btn-light border d-lg-none" id="sidebarToggle">
                <i data-lucide="menu" class="w-5 h-5"></i>
            </button>
            <a href="<?= APP_URL ?>/dashboard" class="brand-logo">
                <img src="<?= APP_URL ?>/images/logo.png" alt="Renewly Logo" onerror="this.onerror=null; this.src='<?= APP_URL ?>/assets/images/logo.png';">
                <span>Renewly</span>
            </a>
        </div>

        <!-- Redesigned Header Actions -->
        <div class="d-flex align-items-center gap-2.5">
            <!-- 1. Notifications Bell Dropdown -->
            <div class="dropdown">
                <a href="#" class="notification-bell" data-bs-toggle="dropdown" aria-expanded="false" title="Notifications">
                    <i data-lucide="bell" class="w-5 h-5 text-secondary"></i>
                    <?php if ($headerNotificationCount > 0): ?>
                        <span class="notification-badge"><?= $headerNotificationCount ?></span>
                    <?php endif; ?>
                </a>
                <div class="dropdown-menu dropdown-menu-end shadow-lg border-0 rounded-3 p-0 mt-2" style="width: 320px;">
                    <div class="p-3 border-bottom d-flex justify-content-between align-items-center bg-light">
                        <h6 class="fw-bold mb-0 text-sm">Notifications</h6>
                        <span class="badge badge-info"><?= $headerNotificationCount ?> Pending</span>
                    </div>
                    <div class="p-2" style="max-height: 280px; overflow-y: auto;">
                        <?php if ($headerPendingApprovals > 0): ?>
                        <a href="<?= APP_URL ?>/approvals" class="dropdown-item p-2 rounded-2 d-flex align-items-start gap-2 mb-1">
                            <div class="badge-warning rounded-circle p-1.5 mt-0.5">
                                <i data-lucide="file-text" class="w-4 h-4"></i>
                            </div>
                            <div>
                                <div class="fw-bold text-dark text-xs"><?= $headerPendingApprovals ?> Contract(s) Pending Approval</div>
                                <div class="text-xs text-muted">Review pending client contract agreements</div>
                            </div>
                        </a>
                        <?php endif; ?>

                        <?php if ($headerExpiringCount > 0): ?>
                        <a href="<?= APP_URL ?>/renewals" class="dropdown-item p-2 rounded-2 d-flex align-items-start gap-2">
                            <div class="badge-danger rounded-circle p-1.5 mt-0.5">
                                <i data-lucide="clock" class="w-4 h-4"></i>
                            </div>
                            <div>
                                <div class="fw-bold text-dark text-xs"><?= $headerExpiringCount ?> Renewal(s) Expiring Soon</div>
                                <div class="text-xs text-muted">Contracts reaching 30-day expiry threshold</div>
                            </div>
                        </a>
                        <?php endif; ?>

                        <?php if ($headerNotificationCount === 0): ?>
                        <div class="text-center py-4 text-muted text-xs">
                            <i data-lucide="check-circle" class="w-6 h-6 text-success mb-1"></i>
                            <div>All systems clear! No pending alerts.</div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- 2. Redesigned User Menu Pill Dropdown -->
            <div class="dropdown">
                <a href="#" class="user-menu" data-bs-toggle="dropdown" aria-expanded="false">
                    <div class="user-avatar-placeholder font-bold text-xs">
                        <?= strtoupper(substr($currentUser['name'] ?? 'U', 0, 2)) ?>
                    </div>
                    <div class="user-info d-none d-md-flex">
                        <span class="user-name"><?= sanitize($currentUser['name']) ?></span>
                        <span class="user-role"><?= sanitize($currentUser['role_name']) ?></span>
                    </div>
                    <i data-lucide="chevron-down" class="w-4 h-4 text-muted ms-1 d-none d-md-inline"></i>
                </a>
                <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 rounded-3 p-2 mt-2" style="min-width: 220px;">
                    <li class="px-3 py-2 border-bottom mb-1 bg-light rounded-2">
                        <div class="fw-bold text-dark text-sm"><?= sanitize($currentUser['name']) ?></div>
                        <div class="text-xs text-muted"><?= sanitize($currentUser['email']) ?></div>
                        <span class="badge badge-primary mt-1 text-xs"><?= sanitize($currentUser['role_name']) ?></span>
                    </li>
                    <?php if (hasPermission('reports.view')): ?>
                    <li>
                        <a class="dropdown-item rounded-2 py-2 text-sm d-flex align-items-center gap-2" href="<?= APP_URL ?>/activity">
                            <i data-lucide="activity" class="w-4 h-4 text-secondary"></i> Audit Activity Logs
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php if (hasPermission('settings.manage')): ?>
                    <li>
                        <a class="dropdown-item rounded-2 py-2 text-sm d-flex align-items-center gap-2" href="<?= APP_URL ?>/settings">
                            <i data-lucide="settings" class="w-4 h-4 text-secondary"></i> System Settings
                        </a>
                    </li>
                    <?php endif; ?>
                    <li><hr class="dropdown-divider my-1"></li>
                    <li>
                        <a class="dropdown-item text-danger rounded-2 py-2 text-sm d-flex align-items-center gap-2" href="<?= APP_URL ?>/logout">
                            <i data-lucide="log-out" class="w-4 h-4"></i> Sign Out
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </header>

    <!-- Include Sidebar Navigation -->
    <?php require_once __DIR__ . '/sidebar.php'; ?>

    <!-- Main Content Area -->
    <main class="main-content">
