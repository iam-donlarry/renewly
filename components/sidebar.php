<?php
// components/sidebar.php - Sidebar Navigation (Expense App Pattern)
$currentRoute = $route ?? 'dashboard';

// Calculate Dynamic Pending Action Badges
$pdo = getDB();
$pendingApprovals = 0;
$expiringRenewals = 0;
$overduePayments = 0;

if (hasPermission('contracts.approve')) {
    $pendingApprovals = (int)$pdo->query("SELECT COUNT(*) FROM contracts WHERE approval_status = 'pending'")->fetchColumn();
}
if (hasPermission('renewals.view')) {
    $expiringRenewals = (int)$pdo->query("SELECT COUNT(*) FROM contracts WHERE DATEDIFF(expiry_date, CURRENT_DATE) BETWEEN 0 AND 30 AND status IN ('active', 'expiring')")->fetchColumn();
}
if (hasPermission('payments.view')) {
    $overduePayments = (int)$pdo->query("SELECT COUNT(*) FROM payment_schedules WHERE status = 'overdue' OR (status = 'pending' AND due_date < CURRENT_DATE)")->fetchColumn();
}
?>
<aside class="sidebar">
    <nav class="sidebar-nav">
        <!-- Main Section -->
        <div class="sidebar-section-header">Main</div>
        <a href="<?= APP_URL ?>/dashboard" class="nav-link-item <?= $currentRoute === 'dashboard' ? 'active' : '' ?>">
            <i data-lucide="layout-dashboard" class="w-5 h-5"></i>
            <span>Dashboard</span>
        </a>

        <!-- Management Section -->
        <div class="sidebar-section-header">Management</div>
        
        <?php if (hasPermission('clients.view')): ?>
        <a href="<?= APP_URL ?>/clients" class="nav-link-item <?= $currentRoute === 'clients' ? 'active' : '' ?>">
            <i data-lucide="building-2" class="w-5 h-5"></i>
            <span>Clients</span>
        </a>
        <?php endif; ?>

        <?php if (hasPermission('vendors.view')): ?>
        <?php $catalog_active = in_array($currentRoute, ['vendors', 'products']); ?>
        <details class="nav-group" <?= $catalog_active ? 'open' : '' ?>>
            <summary class="nav-group-summary">
                <i data-lucide="store" class="w-5 h-5"></i>
                <span>Vendor Catalog</span>
                <i data-lucide="chevron-right" class="caret-icon"></i>
            </summary>
            <div class="sub-menu">
                <a href="<?= APP_URL ?>/vendors" class="sub-nav-link <?= $currentRoute === 'vendors' ? 'active' : '' ?>">
                    <span>Vendors Directory</span>
                </a>
                <a href="<?= APP_URL ?>/products" class="sub-nav-link <?= $currentRoute === 'products' ? 'active' : '' ?>">
                    <span>Products & Pricing</span>
                </a>
            </div>
        </details>
        <?php endif; ?>

        <!-- Operations Section -->
        <div class="sidebar-section-header">Operations</div>

        <?php if (hasPermission('contracts.view')): ?>
        <?php $contracts_active = in_array($currentRoute, ['contracts', 'contracts/create', 'contracts/view', 'subscriptions']); ?>
        <details class="nav-group" <?= $contracts_active ? 'open' : '' ?>>
            <summary class="nav-group-summary">
                <i data-lucide="file-text" class="w-5 h-5"></i>
                <span>Contracts</span>
                <?php if ($pendingApprovals > 0): ?>
                    <span class="nav-badge ms-auto"><?= $pendingApprovals ?></span>
                <?php endif; ?>
                <i data-lucide="chevron-right" class="caret-icon"></i>
            </summary>
            <div class="sub-menu">
                <a href="<?= APP_URL ?>/contracts" class="sub-nav-link <?= $currentRoute === 'contracts' ? 'active' : '' ?>">
                    <span>All Contracts</span>
                </a>
                <?php if (hasPermission('contracts.create')): ?>
                <a href="<?= APP_URL ?>/contracts/create" class="sub-nav-link <?= $currentRoute === 'contracts/create' ? 'active' : '' ?>">
                    <span>New Contract</span>
                </a>
                <?php endif; ?>
                <?php if (hasPermission('contracts.approve')): ?>
                <a href="<?= APP_URL ?>/approvals" class="sub-nav-link <?= $currentRoute === 'approvals' ? 'active' : '' ?>">
                    <span>Approval Queue</span>
                    <?php if ($pendingApprovals > 0): ?>
                        <span class="nav-badge ms-auto"><?= $pendingApprovals ?></span>
                    <?php endif; ?>
                </a>
                <?php endif; ?>
            </div>
        </details>
        <?php endif; ?>

        <?php if (hasPermission('renewals.view')): ?>
        <a href="<?= APP_URL ?>/renewals" class="nav-link-item <?= $currentRoute === 'renewals' ? 'active' : '' ?>">
            <i data-lucide="rotate-cw" class="w-5 h-5"></i>
            <span>Renewal Pipeline</span>
            <?php if ($expiringRenewals > 0): ?>
                <span class="nav-badge ms-auto"><?= $expiringRenewals ?></span>
            <?php endif; ?>
        </a>
        <?php endif; ?>

        <?php if (hasPermission('payments.view')): ?>
        <a href="<?= APP_URL ?>/payments" class="nav-link-item <?= $currentRoute === 'payments' ? 'active' : '' ?>">
            <i data-lucide="credit-card" class="w-5 h-5"></i>
            <span>Payment Schedules</span>
            <?php if ($overduePayments > 0): ?>
                <span class="nav-badge ms-auto"><?= $overduePayments ?></span>
            <?php endif; ?>
        </a>
        <?php endif; ?>

        <!-- Analytics & System Section -->
        <div class="sidebar-section-header">System</div>

        <?php if (hasPermission('reports.view')): ?>
        <a href="<?= APP_URL ?>/reports" class="nav-link-item <?= $currentRoute === 'reports' ? 'active' : '' ?>">
            <i data-lucide="bar-chart-3" class="w-5 h-5"></i>
            <span>Reports & Analytics</span>
        </a>
        <a href="<?= APP_URL ?>/activity" class="nav-link-item <?= $currentRoute === 'activity' ? 'active' : '' ?>">
            <i data-lucide="activity" class="w-5 h-5"></i>
            <span>Activity Audit Trail</span>
        </a>
        <?php endif; ?>

        <?php if (hasPermission('users.manage') || hasPermission('roles.manage')): ?>
        <?php $users_active = in_array($currentRoute, ['users', 'roles']); ?>
        <details class="nav-group" <?= $users_active ? 'open' : '' ?>>
            <summary class="nav-group-summary">
                <i data-lucide="users" class="w-5 h-5"></i>
                <span>User Management</span>
                <i data-lucide="chevron-right" class="caret-icon"></i>
            </summary>
            <div class="sub-menu">
                <?php if (hasPermission('users.manage')): ?>
                <a href="<?= APP_URL ?>/users" class="sub-nav-link <?= $currentRoute === 'users' ? 'active' : '' ?>">
                    <span>Users List</span>
                </a>
                <?php endif; ?>
                <?php if (hasPermission('roles.manage')): ?>
                <a href="<?= APP_URL ?>/roles" class="sub-nav-link <?= $currentRoute === 'roles' ? 'active' : '' ?>">
                    <span>Roles & Permissions</span>
                </a>
                <?php endif; ?>
            </div>
        </details>
        <?php endif; ?>

        <?php if (hasPermission('settings.manage')): ?>
        <a href="<?= APP_URL ?>/settings" class="nav-link-item <?= $currentRoute === 'settings' ? 'active' : '' ?>">
            <i data-lucide="settings" class="w-5 h-5"></i>
            <span>System Settings</span>
        </a>
        <?php endif; ?>
    </nav>
</aside>
