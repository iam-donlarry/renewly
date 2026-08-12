<?php
// modules/contracts/index.php - Contract Agreements Master View
$pageTitle = 'All Contracts';
require_once __DIR__ . '/../../components/header.php';
RBAC::require('contracts.view');

$statusFilter = $_GET['status'] ?? '';
$pdo = getDB();

$sql = "
    SELECT c.*, cl.company_name, CONCAT(u.first_name, ' ', u.last_name) as account_manager_name,
           (SELECT COUNT(*) FROM contract_items WHERE contract_id = c.id) as item_count,
           DATEDIFF(c.expiry_date, CURRENT_DATE) as days_remaining
    FROM contracts c
    JOIN clients cl ON c.client_id = cl.id
    LEFT JOIN users u ON c.account_manager_id = u.id
    WHERE 1=1
";
$params = [];
if (!empty($statusFilter)) {
    $sql .= " AND c.status = ?";
    $params[] = $statusFilter;
}

$sql .= " ORDER BY c.expiry_date ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$contracts = $stmt->fetchAll() ?: [];
?>

<div class="container-fluid max-w-7xl mx-auto">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 font-bold tracking-tight mb-1">Contract Agreements</h1>
            <p class="text-secondary text-sm">Master directory of client subscription contracts, line items, and payment status.</p>
        </div>
        <div class="d-flex gap-2">
            <div class="dropdown">
                <button class="btn btn-light border dropdown-toggle text-sm font-semibold" data-bs-toggle="dropdown">
                    Filter: <?= !empty($statusFilter) ? ucfirst($statusFilter) : 'All Statuses' ?>
                </button>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="<?= APP_URL ?>/contracts">All Statuses</a></li>
                    <li><a class="dropdown-item" href="<?= APP_URL ?>/contracts?status=active">Active</a></li>
                    <li><a class="dropdown-item" href="<?= APP_URL ?>/contracts?status=expiring">Expiring Soon</a></li>
                    <li><a class="dropdown-item" href="<?= APP_URL ?>/contracts?status=renewed">Renewed</a></li>
                    <li><a class="dropdown-item" href="<?= APP_URL ?>/contracts?status=lapsed">Lapsed</a></li>
                </ul>
            </div>
            <?php if (hasPermission('contracts.create')): ?>
            <a href="<?= APP_URL ?>/contracts/create" class="btn btn-primary d-flex align-items-center gap-2">
                <i data-lucide="plus" class="w-4 h-4"></i> New Contract
            </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="card-enterprise">
        <div class="table-responsive">
            <table class="table table-hover align-middle text-sm mb-0">
                <thead class="bg-light">
                    <tr>
                        <th>Contract Ref</th>
                        <th>Client</th>
                        <th>Account Mgr</th>
                        <th>Total Value</th>
                        <th>Billing Cycle</th>
                        <th>Expiry Date</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($contracts)): ?>
                        <tr><td colspan="8" class="text-center py-4 text-muted">No contracts found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($contracts as $c): ?>
                            <tr>
                                <td>
                                    <a href="<?= getContractUrl($c) ?>" class="fw-bold text-primary font-mono text-decoration-none">
                                        <?= sanitize($c['contract_reference']) ?>
                                    </a>
                                </td>
                                <td class="fw-semibold text-dark"><?= sanitize($c['company_name']) ?></td>
                                <td><?= sanitize($c['account_manager_name'] ?? 'Unassigned') ?></td>
                                <td class="fw-bold text-success"><?= formatCurrency($c['total_contract_value'], $c['currency']) ?></td>
                                <td><span class="badge badge-secondary"><?= ucfirst($c['billing_cycle']) ?></span></td>
                                <td>
                                    <?= formatDate($c['expiry_date']) ?>
                                    <div class="text-xs text-muted">
                                        <?= $c['days_remaining'] < 0 ? 'Lapsed (' . abs($c['days_remaining']) . 'd ago)' : '(' . $c['days_remaining'] . ' days left)' ?>
                                    </div>
                                </td>
                                <td><?= renderStatusBadge($c['status']) ?></td>
                                <td class="text-end">
                                    <a href="<?= getContractUrl($c) ?>" class="btn btn-sm btn-light border py-1 px-2.5 text-xs font-medium">
                                        View Details
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../components/footer.php'; ?>
