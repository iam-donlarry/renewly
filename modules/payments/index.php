<?php
// modules/payments/index.php - Filterable Global Payment Installment Ledger & Monthly Collections
$pageTitle = 'Payment Schedules';
require_once __DIR__ . '/../../components/header.php';
RBAC::require('payments.view');

$pdo = getDB();

// Auto-synchronize overdue installments where due_date has elapsed
$pdo->exec("UPDATE payment_schedules SET status = 'overdue' WHERE status = 'pending' AND due_date < CURRENT_DATE");

// Filter parameters
$clientId     = !empty($_GET['client_id']) ? (int)$_GET['client_id'] : 0;
$statusFilter = trim($_GET['status'] ?? '');
$searchQuery  = trim($_GET['search'] ?? '');

// Default Month and Year handling:
// If 'month' is not present in URL:
// - If status=overdue is explicitly requested, show all months ($monthFilter = 0) so all overdue items across time are visible
// - Otherwise, default to current month
if (!isset($_GET['month'])) {
    if ($statusFilter === 'overdue') {
        $monthFilter = 0;
    } else {
        $monthFilter = (int)date('n');
    }
} elseif ($_GET['month'] === 'all' || $_GET['month'] === '0' || $_GET['month'] === '') {
    $monthFilter = 0;
} else {
    $monthFilter = (int)$_GET['month'];
}

// Year default handling:
if (!isset($_GET['year'])) {
    if ($statusFilter === 'overdue') {
        $yearFilter = 0;
    } else {
        $yearFilter = (int)date('Y');
    }
} elseif ($_GET['year'] === 'all' || $_GET['year'] === '0' || $_GET['year'] === '') {
    $yearFilter = 0;
} else {
    $yearFilter = (int)$_GET['year'];
}

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
    if ($statusFilter === 'overdue') {
        $sql .= " AND (ps.status = 'overdue' OR (ps.status = 'pending' AND ps.due_date < CURRENT_DATE))";
    } elseif ($statusFilter === 'pending') {
        $sql .= " AND ps.status = 'pending' AND ps.due_date >= CURRENT_DATE";
    } else {
        $sql .= " AND ps.status = ?";
        $params[] = $statusFilter;
    }
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
                    <option value="all" <?= $monthFilter === 0 ? 'selected' : '' ?>>All Months</option>
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
                    <option value="all" <?= $yearFilter === 0 ? 'selected' : '' ?>>All Years</option>
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
                <?php 
                    $isFiltered = ($clientId > 0 || $statusFilter !== '' || $searchQuery !== '' || $monthFilter !== (int)date('n') || $yearFilter !== (int)date('Y'));
                ?>
                <?php if ($isFiltered): ?>
                    <a href="<?= APP_URL ?>/payments" class="btn btn-light border py-2 px-3 text-sm" title="Reset to Current Month">
                        <i data-lucide="rotate-ccw" class="w-4 h-4"></i>
                    </a>
                <?php endif; ?>
            </div>
        </form>

        <!-- Status Filter Pills -->
        <div class="d-flex align-items-center gap-2 border-top pt-3 mt-3 flex-wrap">
            <span class="text-xs font-semibold text-secondary me-2">Status:</span>
            <?php 
                $monthParam = $monthFilter > 0 ? $monthFilter : 'all';
                $yearParam  = $yearFilter > 0 ? $yearFilter : 'all';
                $baseUrl = APP_URL . "/payments?client_id={$clientId}&month={$monthParam}&year={$yearParam}&search=" . urlencode($searchQuery);
                $overdueCountPill = (int)$pdo->query("SELECT COUNT(*) FROM payment_schedules WHERE status = 'overdue' OR (status = 'pending' AND due_date < CURRENT_DATE)")->fetchColumn();
            ?>
            <a href="<?= $baseUrl ?>" class="btn btn-sm <?= empty($statusFilter) ? 'btn-primary' : 'btn-light border' ?> rounded-pill text-xs font-semibold">
                All Statuses
            </a>
            <a href="<?= $baseUrl ?>&status=pending" class="btn btn-sm <?= $statusFilter === 'pending' ? 'btn-primary' : 'btn-light border' ?> rounded-pill text-xs font-semibold">
                Pending
            </a>
            <a href="<?= APP_URL ?>/payments?client_id=<?= $clientId ?>&month=all&year=all&search=<?= urlencode($searchQuery) ?>&status=overdue" class="btn btn-sm <?= $statusFilter === 'overdue' ? 'btn-danger' : 'btn-light border' ?> rounded-pill text-xs font-semibold d-inline-flex align-items-center gap-1.5">
                Overdue
                <?php if ($overdueCountPill > 0): ?>
                    <span class="badge <?= $statusFilter === 'overdue' ? 'bg-white text-danger' : 'bg-danger text-white' ?> rounded-pill px-1.5 py-0.5" style="font-size: 0.65rem;"><?= $overdueCountPill ?></span>
                <?php endif; ?>
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
                <?php else: ?>
                    <span class="badge badge-secondary ms-2">All Months <?= $yearFilter ?: '' ?></span>
                <?php endif; ?>
            </h5>
            <span class="text-xs text-muted">Showing <?= count($payments) ?> installment row(s)</span>
        </div>

        <div class="table-responsive" style="min-height: 260px;">
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
                            <?php 
                                $isOverdue = ($p['status'] === 'overdue' || ($p['status'] === 'pending' && $p['days_until_due'] < 0));
                                $isPending = ($p['status'] === 'pending' && $p['days_until_due'] >= 0);
                                $isPaid    = ($p['status'] === 'paid');
                                $effectiveStatus = $isOverdue ? 'overdue' : $p['status'];
                                $escCompanyName = htmlspecialchars($p['company_name'], ENT_QUOTES, 'UTF-8');
                                $escRef         = htmlspecialchars($p['contract_reference'], ENT_QUOTES, 'UTF-8');
                            ?>
                            <tr class="<?= $isOverdue ? 'table-danger' : '' ?>">
                                <td class="fw-bold text-dark">
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="user-avatar-placeholder text-xs <?= $isOverdue ? 'border border-danger' : '' ?>" style="width: 32px; height: 32px;">
                                            <?= strtoupper(substr($p['company_name'], 0, 2)) ?>
                                        </div>
                                        <div>
                                            <span><?= sanitize($p['company_name']) ?></span>
                                            <?php if ($isOverdue): ?>
                                                <span class="badge bg-danger text-white ms-1" style="font-size: 0.65rem;">Action Needed</span>
                                            <?php endif; ?>
                                        </div>
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
                                <td class="font-medium <?= $isOverdue ? 'text-danger fw-bold' : 'text-muted' ?>">
                                    <?= formatDate($p['due_date']) ?>
                                    <?php if ($isOverdue): ?>
                                        <div class="text-danger fw-semibold" style="font-size: 0.72rem;">
                                            <?= abs($p['days_until_due']) ?> day(s) late
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="fw-bold text-dark"><?= formatCurrency($p['amount'], $p['currency']) ?></td>
                                <td><?= renderStatusBadge($effectiveStatus) ?></td>
                                <td>
                                    <?php if ($p['status'] === 'paid'): ?>
                                        <div class="text-xs fw-semibold text-dark"><?= formatDate($p['payment_date']) ?></div>
                                        <code class="text-muted text-xs"><?= sanitize($p['payment_reference'] ?: 'Direct Pay') ?></code>
                                    <?php else: ?>
                                        <span class="text-muted text-xs">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <div class="dropdown d-inline-block">
                                        <button class="btn btn-sm btn-light border dropdown-toggle py-1 px-2.5 text-xs font-semibold d-inline-flex align-items-center gap-1" 
                                                type="button" 
                                                data-bs-toggle="dropdown" 
                                                aria-expanded="false">
                                            <span>Actions</span>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end shadow-sm border text-xs py-1" style="min-width: 195px;">
                                            <?php if ($isOverdue): ?>
                                                <li>
                                                    <button type="button" class="dropdown-item d-flex align-items-center gap-2 py-1.5 text-danger font-medium" 
                                                            onclick="sendFollowUp(this, '<?= addslashes($escCompanyName) ?>', '<?= addslashes($escRef) ?>')">
                                                        <i data-lucide="mail-warning" class="w-4 h-4 text-danger"></i>
                                                        <span>Follow up with Mail</span>
                                                    </button>
                                                </li>
                                            <?php elseif ($isPending): ?>
                                                <li>
                                                    <button type="button" class="dropdown-item d-flex align-items-center gap-2 py-1.5 text-primary font-medium" 
                                                            onclick="sendReminder(this, '<?= addslashes($escCompanyName) ?>', '<?= addslashes($escRef) ?>')">
                                                        <i data-lucide="mail" class="w-4 h-4 text-primary"></i>
                                                        <span>Send Reminder</span>
                                                    </button>
                                                </li>
                                            <?php elseif ($isPaid): ?>
                                                <li>
                                                    <button type="button" class="dropdown-item d-flex align-items-center gap-2 py-1.5 text-secondary" 
                                                            onclick="sendReceipt(this, '<?= addslashes($escCompanyName) ?>', '<?= addslashes($escRef) ?>')">
                                                        <i data-lucide="mail-check" class="w-4 h-4 text-success"></i>
                                                        <span>Send Receipt Mail</span>
                                                    </button>
                                                </li>
                                            <?php endif; ?>

                                            <?php if (hasPermission('payments.manage') && !$isPaid): ?>
                                                <li>
                                                    <button type="button" class="dropdown-item d-flex align-items-center gap-2 py-1.5 text-success font-medium" 
                                                            onclick="markPaid(<?= (int)$p['id'] ?>)">
                                                        <i data-lucide="check-circle" class="w-4 h-4 text-success"></i>
                                                        <span>Mark as Paid</span>
                                                    </button>
                                                </li>
                                            <?php endif; ?>

                                            <li><hr class="dropdown-divider my-1"></li>

                                            <li>
                                                <a class="dropdown-item d-flex align-items-center gap-2 py-1.5 text-secondary" 
                                                   href="<?= getContractUrl($p['contract_id']) ?>">
                                                    <i data-lucide="file-text" class="w-4 h-4 text-muted"></i>
                                                    <span>View Contract</span>
                                                </a>
                                            </li>
                                        </ul>
                                    </div>
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
function sendFollowUp(button, companyName, ref) {
    showToast(`Follow up mail has been sent to ${companyName} (${ref}).`, 'success');
}

function sendReminder(button, companyName, ref) {
    showToast(`Reminder mail has been sent to ${companyName} (${ref}).`, 'success');
}

function sendReceipt(button, companyName, ref) {
    showToast(`Payment receipt mail has been sent to ${companyName} (${ref}).`, 'success');
}

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
