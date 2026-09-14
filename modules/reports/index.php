<?php
// modules/reports/index.php - Operational Reports & Analytics
$pageTitle = 'Reports & Analytics';
require_once __DIR__ . '/../../components/header.php';
RBAC::require('reports.view');

$pdo = getDB();
$currencyFilter = strtoupper(trim($_GET['currency'] ?? ''));

$contractSql = "SELECT total_contract_value as amount, currency, exchange_rate FROM contracts WHERE status = 'active'";
if (!empty($currencyFilter) && $currencyFilter !== 'ALL') {
    $contractSql .= " AND currency = " . $pdo->quote($currencyFilter);
}
$activeContracts = $pdo->query($contractSql)->fetchAll() ?: [];
$aggTotalARR = CurrencyEngine::aggregate($activeContracts, 'amount', 'currency', 'exchange_rate');

$renSql = "SELECT r.estimated_renewal_value as amount, c.currency, c.exchange_rate FROM renewals r JOIN contracts c ON r.contract_id = c.id WHERE 1=1";
if (!empty($currencyFilter) && $currencyFilter !== 'ALL') {
    $renSql .= " AND c.currency = " . $pdo->quote($currencyFilter);
}
$renewalsList = $pdo->query($renSql)->fetchAll() ?: [];
$aggEstRenewalARR = CurrencyEngine::aggregate($renewalsList, 'amount', 'currency', 'exchange_rate');

$overdueSql = "SELECT amount, currency, exchange_rate FROM payment_schedules WHERE (status = 'overdue' OR (status = 'pending' AND due_date < CURRENT_DATE))";
if (!empty($currencyFilter) && $currencyFilter !== 'ALL') {
    $overdueSql .= " AND currency = " . $pdo->quote($currencyFilter);
}
$overduePayments = $pdo->query($overdueSql)->fetchAll() ?: [];
$aggOverdueTotal = CurrencyEngine::aggregate($overduePayments, 'amount', 'currency', 'exchange_rate');

// Revenue Breakdown by Vendor (Dual-Currency Grouped)
$vendorSql = "
    SELECT v.id as vendor_id, v.vendor_name, c.currency, c.exchange_rate,
           COUNT(DISTINCT c.id) as contract_count,
           SUM(ci.line_total) as line_revenue
    FROM contract_items ci
    JOIN contracts c ON ci.contract_id = c.id
    JOIN products p ON ci.product_id = p.id
    JOIN vendors v ON p.vendor_id = v.id
    WHERE c.status = 'active'
";
if (!empty($currencyFilter) && $currencyFilter !== 'ALL') {
    $vendorSql .= " AND c.currency = " . $pdo->quote($currencyFilter);
}
$vendorSql .= " GROUP BY v.id, v.vendor_name, c.currency, c.exchange_rate";
$rawVendorBreakdown = $pdo->query($vendorSql)->fetchAll() ?: [];

$vendorBreakdown = [];
foreach ($rawVendorBreakdown as $rv) {
    $vid = $rv['vendor_id'];
    if (!isset($vendorBreakdown[$vid])) {
        $vendorBreakdown[$vid] = [
            'vendor_name' => $rv['vendor_name'],
            'contract_count' => 0,
            'items' => []
        ];
    }
    $vendorBreakdown[$vid]['contract_count'] += (int)$rv['contract_count'];
    $vendorBreakdown[$vid]['items'][] = [
        'amount'        => (float)$rv['line_revenue'],
        'currency'      => $rv['currency'],
        'exchange_rate' => (float)$rv['exchange_rate']
    ];
}
foreach ($vendorBreakdown as $vid => &$vbData) {
    $vbData['agg'] = CurrencyEngine::aggregate($vbData['items'], 'amount', 'currency', 'exchange_rate');
}
unset($vbData);
?>

<div class="container-fluid max-w-7xl mx-auto">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h1 class="h3 font-bold tracking-tight mb-1">Reports & Financial Analytics</h1>
            <p class="text-secondary text-sm">Operational summary of active revenue, vendor obligations, and renewal conversion pipeline in dual currencies.</p>
        </div>

        <!-- Currency Filter Dropdown -->
        <div class="d-flex align-items-center gap-2">
            <span class="text-xs font-semibold text-secondary">Currency:</span>
            <div class="btn-group" role="group">
                <a href="<?= APP_URL ?>/reports" class="btn btn-sm <?= empty($currencyFilter) || $currencyFilter === 'ALL' ? 'btn-primary' : 'btn-light border' ?> font-semibold text-xs">All Currencies</a>
                <a href="<?= APP_URL ?>/reports?currency=USD" class="btn btn-sm <?= $currencyFilter === 'USD' ? 'btn-primary' : 'btn-light border' ?> font-semibold text-xs">USD ($)</a>
                <a href="<?= APP_URL ?>/reports?currency=NGN" class="btn btn-sm <?= $currencyFilter === 'NGN' ? 'btn-primary' : 'btn-light border' ?> font-semibold text-xs">NGN (₦)</a>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="kpi-card">
                <span class="kpi-title">Active Contract ARR</span>
                <?= CurrencyEngine::renderDualKpiHtml($aggTotalARR, $currencyFilter, 'text-success') ?>
                <span class="text-xs text-muted mt-2"><?= count($activeContracts) ?> Active client contract(s)</span>
            </div>
        </div>

        <div class="col-md-4">
            <div class="kpi-card">
                <span class="kpi-title">Forecasted Renewal Revenue</span>
                <?= CurrencyEngine::renderDualKpiHtml($aggEstRenewalARR, $currencyFilter, 'text-primary') ?>
                <span class="text-xs text-muted mt-2">Factoring queued seat drops & catalog updates</span>
            </div>
        </div>

        <div class="col-md-4">
            <div class="kpi-card">
                <span class="kpi-title">Outstanding Collections</span>
                <?= CurrencyEngine::renderDualKpiHtml($aggOverdueTotal, $currencyFilter, 'text-danger') ?>
                <span class="text-xs text-muted mt-2"><?= count($overduePayments) ?> Overdue pending installment(s)</span>
            </div>
        </div>
    </div>

    <div class="card-enterprise">
        <div class="d-flex justify-content-between align-items-center border-bottom pb-3 mb-3">
            <h5 class="fw-bold h6 mb-0">
                Revenue Breakdown by Vendor
                <?php if (!empty($currencyFilter) && $currencyFilter !== 'ALL'): ?>
                    <span class="badge bg-dark text-white ms-2"><?= $currencyFilter ?></span>
                <?php endif; ?>
            </h5>
            <span class="text-xs text-muted"><?= count($vendorBreakdown) ?> Vendor(s) Listed</span>
        </div>
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
                                <td>
                                    <?= CurrencyEngine::renderDualKpiHtml($vb['agg'], $currencyFilter, 'text-success', true) ?>
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
