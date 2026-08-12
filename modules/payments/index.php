<?php
// modules/payments/index.php - Filterable Global Payment Installment Ledger & Monthly Collections
$pageTitle = 'Payment Schedules';
require_once __DIR__ . '/../../components/header.php';
RBAC::require('payments.view');

$pdo = getDB();

// Filter parameters
$clientId     = !empty($_GET['client_id']) ? (int)$_GET['client_id'] : 0;
$monthFilter  = !empty($_GET['month']) ? (int)$_GET['month'] : 0;
$yearFilter   = !empty($_GET['year']) ? (int)$_GET['year'] : 0;
$statusFilter = trim($_GET['status'] ?? '');
$searchQuery  = trim($_GET['search'] ?? '');

// Fetch all Clients for filter dropdown
$allClients = $pdo->query("SELECT id, company_name FROM clients ORDER BY company_name ASC")->fetchAll() ?: [];

// Build SQL query
$sql = "
    SELECT ps.*, c.contract_reference, cl.company_name,
           DATEDIFF(ps.due_date, CURRENT_DATE) as days_until_due
    FROM payment_schedules ps
    JOIN contracts c ON ps.contract_id = c.id
    JOIN clients cl ON c.client_id = cl.id
    WHERE 1=1
";
$params = [];

if ($clientId > 0) {
    $sql .= " AND c.client_id = ?";
    $params[] = $clientId;
}

if ($monthFilter > 0) {
    $sql .= " AND MONTH(ps.due_date) = ?";
    $params[] = $monthFilter;
}

if ($yearFilter > 0) {
    $sql .= " AND YEAR(ps.due_date) = ?";
    $params[] = $yearFilter;
}

if (!empty($statusFilter)) {
    $sql .= " AND ps.status = ?";
    $params[] = $statusFilter;
}

if (!empty($searchQuery)) {
    $sql .= " AND (cl.company_name LIKE ? OR c.contract_reference LIKE ? OR ps.payment_reference LIKE ?)";
    $term = "%{$searchQuery}%";
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
}

$sql .= " ORDER BY ps.due_date ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$payments = $stmt->fetchAll() ?: [];

// Calculate summary financial statistics for filtered dataset
$filteredTotalValue = 0.0;
$filteredPaidValue  = 0.0;
$filteredPendingVal = 0.0;
$filteredOverdueVal = 0.0;

foreach ($payments as $p) {
    $amt = (float)$p['amount'];
    $filteredTotalValue += $amt;
    if ($p['status'] === 'paid') {
        $filteredPaidValue += $amt;
    } elseif ($p['status'] === 'overdue' || ($p['status'] === 'pending' && $p['days_until_due'] < 0)) {
        $filteredOverdueVal += $amt;
    } else {
        $filteredPendingVal += $amt;
    }
}

$monthsList = [
    1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
    5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
    9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
];
$yearsList = range((int)date('Y') - 1, (int)date('Y') + 3);
?>

<div class="container-fluid max-w-7xl mx-auto">
    <!-- Page Title & Header -->
    <div class="mb-4">
        <h1 class="h3 font-bold tracking-tight mb-1">Payment Installments & Collections Ledger</h1>
        <p class="text-secondary text-sm">Filter payment schedules by client company, month, year, or payment status.</p>
    </div>

    <!-- Filter Control Bar -->
    <div class="card-enterprise mb-4">
        <form method="GET" action="<?= APP_URL ?>/payments" class="row g-3 align-items-end">
            <!-- 1. Client Select -->
            <div class="col-md-3">
                <label class="form-label text-xs fw-bold text-secondary mb-1">Client Company</label>
                <select name="client_id" class="form-select text-sm">
                    <option value="">All Client Companies</option>
                    <?php foreach ($allClients as $cli): ?>
                        <option value="<?= $cli['id'] ?>" <?= $clientId === (int)$cli['id'] ? 'selected' : '' ?>>
                            <?= sanitize($cli['company_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- 2. Month Select -->
            <div class="col-md-2">
                <label class="form-label text-xs fw-bold text-secondary mb-1">Due Month</label>
                <select name="month" class="form-select text-sm">
                    <option value="">All Months</option>
                    <?php foreach ($monthsList as $num => $mName): ?>
                        <option value="<?= $num ?>" <?= $monthFilter === $num ? 'selected' : '' ?>>
                            <?= $mName ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- 3. Year Select -->
            <div class="col-md-2">
                <label class="form-label text-xs fw-bold text-secondary mb-1">Due Year</label>
                <select name="year" class="form-select text-sm">
                    <option value="">All Years</option>
                    <?php foreach ($yearsList as $yr): ?>
                        <option value="<?= $yr ?>" <?= $yearFilter === $yr ? 'selected' : '' ?>>
                            <?= $yr ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- 4. Search Query -->
            <div class="col-md-3">
                <label class="form-label text-xs fw-bold text-secondary mb-1">Search Keywords</label>
                <input type="text" name="search" class="form-control text-sm" placeholder="Client, contract ref, payment ref..." value="<?= sanitize($searchQuery) ?>">
            </div>

            <!-- 5. Submit / Reset Buttons -->
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary w-100 font-semibold text-sm py-2">
                    <i data-lucide="filter" class="w-4 h-4 me-1"></i> Filter
                </button>
                <?php if ($clientId || $monthFilter || $yearFilter || $statusFilter || $searchQuery): ?>
                    <a href="<?= APP_URL ?>/payments" class="btn btn-light border py-2 px-3 text-sm" title="Clear Filters">
                        <i data-lucide="x" class="w-4 h-4"></i>
                    </a>
                <?php endif; ?>
            </div>
        </form>

        <!-- Status Filter Pills -->
        <div class="d-flex align-items-center gap-2 border-top pt-3 mt-3 flex-wrap">
            <span class="text-xs font-semibold text-secondary me-2">Status:</span>
            <?php 
                $baseUrl = APP_URL . "/payments?client_id={$clientId}&month={$monthFilter}&year={$yearFilter}&search=" . urlencode($searchQuery);
            ?>
            <a href="<?= $baseUrl ?>" class="btn btn-sm <?= empty($statusFilter) ? 'btn-primary' : 'btn-light border' ?> rounded-pill text-xs font-semibold">
                All Statuses
            </a>
            <a href="<?= $baseUrl ?>&status=pending" class="btn btn-sm <?= $statusFilter === 'pending' ? 'btn-primary' : 'btn-light border' ?> rounded-pill text-xs font-semibold">
                Pending
            </a>
            <a href="<?= $baseUrl ?>&status=overdue" class="btn btn-sm <?= $statusFilter === 'overdue' ? 'btn-primary' : 'btn-light border' ?> rounded-pill text-xs font-semibold">
                Overdue
            </a>
            <a href="<?= $baseUrl ?>&status=paid" class="btn btn-sm <?= $statusFilter === 'paid' ? 'btn-primary' : 'btn-light border' ?> rounded-pill text-xs font-semibold">
                Paid
            </a>
        </div>
    </div>

    <!-- Summary Metrics for Active Selection -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="kpi-card">
                <span class="kpi-title">Filtered Installments Value</span>
                <div class="kpi-value"><?= formatCurrency($filteredTotalValue, 'USD') ?></div>
                <span class="text-xs text-muted mt-1"><?= count($payments) ?> Record(s) Found</span>
            </div>
        </div>
        <div class="col-md-3">
            <div class="kpi-card">
                <span class="kpi-title">Total Collected (Paid)</span>
                <div class="kpi-value text-success"><?= formatCurrency($filteredPaidValue, 'USD') ?></div>
                <span class="text-xs text-muted mt-1">Confirmed payments</span>
            </div>
        </div>
        <div class="col-md-3">
            <div class="kpi-card">
                <span class="kpi-title">Total Pending</span>
                <div class="kpi-value text-primary"><?= formatCurrency($filteredPendingVal, 'USD') ?></div>
                <span class="text-xs text-muted mt-1">Upcoming due dates</span>
            </div>
        </div>
        <div class="col-md-3">
            <div class="kpi-card">
                <span class="kpi-title">Total Overdue</span>
                <div class="kpi-value text-danger"><?= formatCurrency($filteredOverdueVal, 'USD') ?></div>
                <span class="text-xs text-muted mt-1">Requires immediate follow-up</span>
            </div>
        </div>
    </div>

    <!-- Payment Installments Table -->
    <div class="card-enterprise">
        <div class="d-flex justify-content-between align-items-center border-bottom pb-3 mb-3">
            <h5 class="fw-bold h6 mb-0">
                Installment Schedule Results 
                <?php if ($monthFilter): ?>
                    <span class="badge badge-info ms-2"><?= $monthsList[$monthFilter] ?> <?= $yearFilter ?: '' ?></span>
                <?php endif; ?>
            </h5>
            <span class="text-xs text-muted">Showing <?= count($payments) ?> installment row(s)</span>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle text-sm mb-0">
                <thead class="bg-light">
                    <tr>
                        <th>Client Company</th>
                        <th>Contract Reference</th>
                        <th>Installment #</th>
                        <th>Due Date</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Payment Date & Ref</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($payments)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">
                                <i data-lucide="calendar-x" class="w-8 h-8 text-secondary mb-2"></i>
                                <div class="fw-semibold">No payment schedules found for the selected filters.</div>
                                <div class="text-xs">Try selecting a different client, month, or status filter.</div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($payments as $p): ?>
                            <tr>
                                <td class="fw-bold text-dark">
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="user-avatar-placeholder text-xs" style="width: 32px; height: 32px;">
                                            <?= strtoupper(substr($p['company_name'], 0, 2)) ?>
                                        </div>
                                        <span><?= sanitize($p['company_name']) ?></span>
                                    </div>
                                </td>
                                <td>
                                    <a href="<?= getContractUrl($p['contract_id']) ?>" class="text-primary font-mono fw-semibold">
                                        <?= sanitize($p['contract_reference']) ?>
                                    </a>
                                </td>
                                <td>
                                    <span class="badge badge-secondary">Installment #<?= $p['installment_number'] ?></span>
                                </td>
                                <td class="text-muted font-medium"><?= formatDate($p['due_date']) ?></td>
                                <td class="fw-bold text-dark"><?= formatCurrency($p['amount'], $p['currency']) ?></td>
                                <td><?= renderStatusBadge($p['status']) ?></td>
                                <td>
                                    <?php if ($p['status'] === 'paid'): ?>
                                        <div class="text-xs fw-semibold text-dark"><?= formatDate($p['payment_date']) ?></div>
                                        <code class="text-muted text-xs"><?= sanitize($p['payment_reference'] ?: 'Direct Pay') ?></code>
                                    <?php else: ?>
                                        <span class="text-muted text-xs">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <?php if (hasPermission('payments.manage') && $p['status'] !== 'paid'): ?>
                                    <button class="btn btn-sm btn-success py-1 px-3 text-xs font-semibold" onclick="markPaid(<?= $p['id'] ?>)">
                                        <i data-lucide="check" class="w-3.5 h-3.5 me-1"></i> Mark Paid
                                    </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
async function markPaid(paymentId) {
    const ref = await customPrompt("Enter Invoice Number for this payment schedule (serves as payment reference):", "Record Payment & Invoice Reference", "e.g. INV-2026-0084");
    if (ref === null) return;
    try {
        const res = await fetchAPI('<?= APP_URL ?>/ajax/payments/mark_paid.php', {
            method: 'POST',
            body: JSON.stringify({ payment_id: paymentId, reference: ref })
        });
        if (res.success) {
            showToast('Payment marked as Paid.', 'success');
            setTimeout(() => location.reload(), 600);
        }
    } catch (e) {}
}
</script>

<?php require_once __DIR__ . '/../../components/footer.php'; ?>
