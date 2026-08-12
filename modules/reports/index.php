<?php
// modules/reports/index.php - Operational Reports & Analytics
$pageTitle = 'Reports & Analytics';
require_once __DIR__ . '/../../components/header.php';
RBAC::require('reports.view');

$pdo = getDB();
$totalARR = (float)$pdo->query("SELECT SUM(total_contract_value) FROM contracts WHERE status = 'active'")->fetchColumn();
$estRenewalARR = (float)$pdo->query("SELECT SUM(estimated_renewal_value) FROM renewals")->fetchColumn();
$overdueTotal = (float)$pdo->query("SELECT SUM(amount) FROM payment_schedules WHERE status = 'overdue' OR (status = 'pending' AND due_date < CURRENT_DATE)")->fetchColumn();

// Revenue Breakdown by Vendor
$vendorBreakdown = $pdo->query("
    SELECT v.vendor_name, COUNT(DISTINCT c.id) as contract_count, SUM(ci.line_total) as vendor_revenue
    FROM contract_items ci
    JOIN contracts c ON ci.contract_id = c.id
    JOIN products p ON ci.product_id = p.id
    JOIN vendors v ON p.vendor_id = v.id
    WHERE c.status = 'active'
    GROUP BY v.id
    ORDER BY vendor_revenue DESC
")->fetchAll() ?: [];
?>

<div class="container-fluid max-w-7xl mx-auto">
    <div class="mb-4">
        <h1 class="h3 font-bold tracking-tight mb-1">Reports & Financial Analytics</h1>
        <p class="text-secondary text-sm">Operational summary of active revenue, vendor obligations, and renewal conversion pipeline.</p>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="kpi-card">
                <span class="kpi-title">Active Contract ARR</span>
                <div class="kpi-value text-success"><?= formatCurrency($totalARR) ?></div>
                <span class="text-xs text-muted mt-2">Sum of active client contracts</span>
            </div>
        </div>

        <div class="col-md-4">
            <div class="kpi-card">
                <span class="kpi-title">Forecasted Renewal Revenue</span>
                <div class="kpi-value text-primary"><?= formatCurrency($estRenewalARR) ?></div>
                <span class="text-xs text-muted mt-2">Factoring queued seat drops & catalog updates</span>
            </div>
        </div>

        <div class="col-md-4">
            <div class="kpi-card">
                <span class="kpi-title">Outstanding Collections</span>
                <div class="kpi-value text-danger"><?= formatCurrency($overdueTotal) ?></div>
                <span class="text-xs text-muted mt-2">Overdue pending payment installments</span>
            </div>
        </div>
    </div>

    <div class="card-enterprise">
        <h5 class="fw-bold h6 border-bottom pb-3 mb-3">Revenue Breakdown by Vendor</h5>
        <div class="table-responsive">
            <table class="table table-hover align-middle text-sm mb-0">
                <thead class="bg-light">
                    <tr>
                        <th>Vendor Name</th>
                        <th>Active Contracts Count</th>
                        <th>Annual Revenue Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($vendorBreakdown)): ?>
                        <tr><td colspan="3" class="text-center py-3 text-muted">No vendor breakdown data available.</td></tr>
                    <?php else: ?>
                        <?php foreach ($vendorBreakdown as $vb): ?>
                            <tr>
                                <td class="fw-bold text-dark"><?= sanitize($vb['vendor_name']) ?></td>
                                <td><span class="badge badge-info"><?= $vb['contract_count'] ?> Contracts</span></td>
                                <td class="fw-bold text-success"><?= formatCurrency($vb['vendor_revenue']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../components/footer.php'; ?>
