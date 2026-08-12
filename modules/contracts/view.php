<?php
// modules/contracts/view.php - Comprehensive Contract Detail View & Seat Adjustment Center
$pageTitle = 'Contract Details';
require_once __DIR__ . '/../../components/header.php';
RBAC::require('contracts.view');

$token = $_GET['token'] ?? $_GET['id'] ?? '';
$contractId = decodeId($token);

if ($contractId <= 0 && !empty($token)) {
    $pdo = getDB();
    $stmtC = $pdo->prepare("SELECT id FROM contracts WHERE contract_reference = ?");
    $stmtC->execute([$token]);
    $contractId = (int)$stmtC->fetchColumn();
}

$contract = ContractEngine::getById($contractId);

if (!$contract) {
    echo '<div class="container py-5"><div class="alert alert-danger">Contract not found.</div></div>';
    require_once __DIR__ . '/../../components/footer.php';
    exit;
}

$daysRemaining = calculateDaysRemaining($contract['expiry_date']);

// Fetch Audit Logs for this Contract
$pdo = getDB();
$stmtLogs = $pdo->prepare("
    SELECT a.*, CONCAT(u.first_name, ' ', u.last_name) as user_name
    FROM audit_logs a
    LEFT JOIN users u ON a.user_id = u.id
    WHERE a.entity_type IN ('contracts', 'contract_items', 'payment_schedules') AND a.entity_id = ?
    ORDER BY a.created_at DESC
");
$stmtLogs->execute([$contractId]);
$auditLogs = $stmtLogs->fetchAll() ?: [];
?>

<div class="container-fluid max-w-7xl mx-auto">
    <!-- Header Title Bar -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <h1 class="h3 font-bold tracking-tight mb-0">Contract <?= sanitize($contract['contract_reference']) ?></h1>
                <?= renderStatusBadge($contract['status']) ?>
            </div>
            <p class="text-secondary text-sm">Agreement for <strong><?= sanitize($contract['company_name']) ?></strong> managed by <?= sanitize($contract['account_manager_name'] ?? 'Unassigned') ?>.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= APP_URL ?>/contracts" class="btn btn-outline-secondary btn-sm">
                <i data-lucide="arrow-left" class="w-4 h-4 me-1"></i> Back to Contracts
            </a>
        </div>
    </div>

    <!-- Contract Summary Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="kpi-card">
                <span class="kpi-title">Total Value</span>
                <div class="kpi-value text-success"><?= formatCurrency($contract['total_contract_value'], $contract['currency']) ?></div>
                <span class="text-xs text-muted mt-2">Billing Frequency: <?= ucfirst($contract['billing_cycle']) ?></span>
            </div>
        </div>

        <div class="col-md-3">
            <div class="kpi-card">
                <span class="kpi-title">Contract Expiry</span>
                <div class="kpi-value text-dark"><?= formatDate($contract['expiry_date']) ?></div>
                <span class="text-xs text-warning font-semibold mt-2">
                    <?= $daysRemaining < 0 ? 'Lapsed (' . abs($daysRemaining) . ' days ago)' : $daysRemaining . ' days remaining' ?>
                </span>
            </div>
        </div>

        <div class="col-md-3">
            <div class="kpi-card">
                <span class="kpi-title">Est. Renewal Value</span>
                <div class="kpi-value text-primary"><?= formatCurrency($contract['estimated_renewal_value'] ?: $contract['total_contract_value'], $contract['currency']) ?></div>
                <span class="text-xs text-muted mt-2">Stage: <?= ucfirst($contract['renewal_stage'] ?: 'Upcoming') ?></span>
            </div>
        </div>

        <div class="col-md-3">
            <div class="kpi-card">
                <span class="kpi-title">Primary Contact</span>
                <div class="fw-bold text-dark mt-2"><?= sanitize($contract['primary_contact_name'] ?: 'N/A') ?></div>
                <span class="text-xs text-muted"><?= sanitize($contract['primary_contact_email']) ?></span>
            </div>
        </div>
    </div>

    <!-- Line Items Section -->
    <div class="card-enterprise mb-4">
        <div class="d-flex justify-content-between align-items-center border-bottom pb-3 mb-3">
            <div>
                <h5 class="fw-bold h6 mb-0">Subscription Line Items</h5>
                <p class="text-xs text-muted mb-0">Products under this agreement with price snapshot integrity.</p>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle text-sm mb-0">
                <thead class="bg-light">
                    <tr>
                        <th>Vendor / Product</th>
                        <th>Agreed Unit Price</th>
                        <th>Current Seats</th>
                        <th>Queued Reduction</th>
                        <th>Line Total</th>
                        <th>Item Status</th>
                        <?php if (hasPermission('subscriptions.adjust')): ?>
                        <th class="text-end">Actions</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($contract['items'] as $item): ?>
                        <tr>
                            <td>
                                <div class="fw-bold text-dark"><?= sanitize($item['product_name']) ?></div>
                                <div class="text-xs text-muted"><?= sanitize($item['vendor_name']) ?></div>
                            </td>
                            <td class="fw-semibold"><?= formatCurrency($item['unit_price'], $contract['currency']) ?></td>
                            <td><span class="fw-bold fs-6 text-dark"><?= $item['current_quantity'] ?></span> seats</td>
                            <td>
                                <?php if (!empty($item['queued_quantity'])): ?>
                                    <span class="badge badge-warning">
                                        <i data-lucide="arrow-down" class="badge-icon"></i>
                                        Queued: <?= $item['queued_quantity'] ?> seats
                                    </span>
                                    <div class="text-xs text-muted mt-1">Effective: <?= formatDate($item['queued_effective_date']) ?></div>
                                <?php else: ?>
                                    <span class="text-muted text-xs">No pending reduction</span>
                                <?php endif; ?>
                            </td>
                            <td class="fw-bold text-success"><?= formatCurrency($item['line_total'], $contract['currency']) ?></td>
                            <td><?= renderStatusBadge($item['status']) ?></td>
                            <?php if (hasPermission('subscriptions.adjust')): ?>
                            <td class="text-end">
                                <div class="btn-group">
                                    <button class="btn btn-sm btn-outline-primary py-1 px-2 text-xs" onclick='openAdditionModal(<?= json_encode($item) ?>)'>
                                        <i data-lucide="plus" class="w-3.5 h-3.5 me-1"></i> Add Seats
                                    </button>
                                    <button class="btn btn-sm btn-outline-warning py-1 px-2 text-xs" onclick='openReductionModal(<?= json_encode($item) ?>)'>
                                        <i data-lucide="minus" class="w-3.5 h-3.5 me-1"></i> Queue Reduction
                                    </button>
                                </div>
                            </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Payment Schedule Section -->
    <div class="card-enterprise mb-4">
        <div class="d-flex justify-content-between align-items-center border-bottom pb-3 mb-3">
            <div>
                <h5 class="fw-bold h6 mb-0">Persistent Payment Schedule</h5>
                <p class="text-xs text-muted mb-0">Installment schedule and collection tracking ledger.</p>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle text-sm mb-0">
                <thead class="bg-light">
                    <tr>
                        <th>Installment #</th>
                        <th>Due Date</th>
                        <th>Amount</th>
                        <th>Payment Date</th>
                        <th>Reference</th>
                        <th>Status</th>
                        <?php if (hasPermission('payments.manage')): ?>
                        <th class="text-end">Actions</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($contract['payments'] as $p): ?>
                        <tr>
                            <td class="fw-bold">#<?= $p['installment_number'] ?></td>
                            <td class="text-muted"><?= formatDate($p['due_date']) ?></td>
                            <td class="fw-bold text-dark"><?= formatCurrency($p['amount'], $p['currency']) ?></td>
                            <td><?= formatDate($p['payment_date']) ?></td>
                            <td><code><?= sanitize($p['payment_reference'] ?: '-') ?></code></td>
                            <td><?= renderStatusBadge($p['status']) ?></td>
                            <?php if (hasPermission('payments.manage')): ?>
                            <td class="text-end">
                                <?php if ($p['status'] !== 'paid'): ?>
                                <button class="btn btn-sm btn-success py-1 px-2 text-xs font-semibold" onclick="markPaymentPaid(<?= $p['id'] ?>)">
                                    Mark Paid
                                </button>
                                <?php endif; ?>
                            </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Audit Log Section -->
    <div class="card-enterprise mb-4">
        <h5 class="fw-bold h6 border-bottom pb-3 mb-3">Contract Audit Trail</h5>
        <div class="table-responsive">
            <table class="table table-sm align-middle text-xs text-secondary mb-0">
                <thead>
                    <tr>
                        <th>Timestamp</th>
                        <th>User</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($auditLogs)): ?>
                        <tr><td colspan="3" class="text-muted py-2">No activity logged for this contract yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($auditLogs as $log): ?>
                            <tr>
                                <td><?= formatDate($log['created_at'], 'M d, Y H:i:s') ?></td>
                                <td><?= sanitize($log['user_name'] ?? 'System') ?></td>
                                <td><code class="text-dark"><?= sanitize($log['action']) ?></code></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Offcanvas Drawer: Add Seats Proration Preview (Half-Screen 50vw) -->
<div class="offcanvas offcanvas-end" id="additionOffcanvas" tabindex="-1" style="width: 50vw; min-width: 520px;">
    <div class="offcanvas-header border-bottom bg-light">
        <div>
            <h5 class="offcanvas-title font-bold h6">Add Seats (Immediate Proration)</h5>
            <span class="text-xs text-muted">Mid-term seat addition & prorated billing charge</span>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
    </div>
    <div class="offcanvas-body p-4">
        <form id="additionForm">
            <input type="hidden" id="add_item_id">
            <div class="mb-3">
                <label class="form-label text-xs font-semibold">Product Title</label>
                <input type="text" id="add_product_title" class="form-control" readonly>
            </div>
            <div class="row">
                <div class="col-6 mb-3">
                    <label class="form-label text-xs font-semibold">Current Active Seats</label>
                    <input type="text" id="add_current_qty" class="form-control" readonly>
                </div>
                <div class="col-6 mb-3">
                    <label class="form-label text-xs font-semibold">New Total Seats *</label>
                    <input type="number" id="add_new_qty" class="form-control" min="1" oninput="previewProration()" required>
                </div>
            </div>

            <!-- Directional Validation Warning Box -->
            <div class="alert alert-warning py-2.5 text-xs mb-3 d-none" id="addWarningBox">
                <i data-lucide="alert-triangle" class="w-4 h-4 me-1.5 align-middle text-warning"></i>
                <span id="addWarningMsg">New total seats must be greater than current active seats. To reduce seats at renewal, please use the <strong>Queue Reduction</strong> action instead.</span>
            </div>

            <!-- Proration Calculation Box -->
            <div class="p-3.5 bg-light rounded-3 border mb-4" id="prorationBox" style="display:none;">
                <div class="fw-bold text-dark text-xs mb-2 d-flex align-items-center gap-1.5">
                    <i data-lucide="calculator" class="w-4 h-4 text-primary"></i>
                    Proration Charge Breakdown:
                </div>
                <div class="d-flex justify-content-between text-xs text-muted mb-1">
                    <span>Seats Added:</span> <strong id="prora_added" class="text-dark">0</strong>
                </div>
                <div class="d-flex justify-content-between text-xs text-muted mb-1">
                    <span>Remaining Calendar Days:</span> <strong id="prora_days" class="text-dark">0</strong>
                </div>
                <div class="d-flex justify-content-between text-xs text-muted border-top pt-2 mt-2">
                    <span class="fw-bold text-dark">Immediate Invoice Charge:</span> 
                    <strong id="prora_charge" class="text-success h6 mb-0">$0.00</strong>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label text-xs font-semibold">Reason / Justification</label>
                <textarea id="add_reason" class="form-control" rows="3" placeholder="Client team expanded by 10 members..."></textarea>
            </div>
        </form>
    </div>
    <div class="offcanvas-footer border-top p-3 bg-light d-flex justify-content-end gap-2">
        <button type="button" class="btn btn-light border px-3" data-bs-dismiss="offcanvas">Cancel</button>
        <button type="button" class="btn btn-primary font-semibold px-4" id="btnSubmitAddition" onclick="submitAddition()">Confirm & Bill Addition</button>
    </div>
</div>

<!-- Offcanvas Drawer: Queue Seat Reduction (Half-Screen 50vw) -->
<div class="offcanvas offcanvas-end" id="reductionOffcanvas" tabindex="-1" style="width: 50vw; min-width: 520px;">
    <div class="offcanvas-header border-bottom bg-light">
        <div>
            <h5 class="offcanvas-title font-bold h6">Queue Seat Reduction (At Renewal)</h5>
            <span class="text-xs text-muted">Schedule seat count drop for upcoming renewal date</span>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
    </div>
    <div class="offcanvas-body p-4">
        <div class="alert alert-warning py-2.5 text-xs mb-4">
            <i data-lucide="info" class="w-4 h-4 me-1.5 align-middle"></i>
            Vendor terms do not refund active term drops. Desired lower quantity will take effect automatically at the upcoming renewal date (<strong><?= formatDate($contract['expiry_date']) ?></strong>).
        </div>

        <form id="reductionForm">
            <input type="hidden" id="red_item_id">
            <div class="mb-3">
                <label class="form-label text-xs font-semibold">Product Title</label>
                <input type="text" id="red_product_title" class="form-control" readonly>
            </div>
            <div class="row">
                <div class="col-6 mb-3">
                    <label class="form-label text-xs font-semibold">Current Active Seats</label>
                    <input type="text" id="red_current_qty" class="form-control" readonly>
                </div>
                <div class="col-6 mb-3">
                    <label class="form-label text-xs font-semibold">Desired Seats at Renewal *</label>
                    <input type="number" id="red_desired_qty" class="form-control" min="1" oninput="validateReduction()" required>
                </div>
            </div>

            <!-- Directional Validation Warning Box -->
            <div class="alert alert-danger py-2.5 text-xs mb-3 d-none" id="redWarningBox">
                <i data-lucide="alert-circle" class="w-4 h-4 me-1.5 align-middle text-danger"></i>
                <span id="redWarningMsg">Desired seats at renewal must be less than current active seats. To add seats immediately, please use the <strong>Add Seats</strong> action instead.</span>
            </div>

            <div class="mb-3">
                <label class="form-label text-xs font-semibold">Reason for Reduction</label>
                <textarea id="red_reason" class="form-control" rows="3" placeholder="Client downsizing department..."></textarea>
            </div>
        </form>
    </div>
    <div class="offcanvas-footer border-top p-3 bg-light d-flex justify-content-end gap-2">
        <button type="button" class="btn btn-light border px-3" data-bs-dismiss="offcanvas">Cancel</button>
        <button type="button" class="btn btn-warning font-semibold px-4" id="btnSubmitReduction" onclick="submitReduction()">Queue Reduction</button>
    </div>
</div>

<script>
let currentAdditionItem = null;

function openAdditionModal(item) {
    currentAdditionItem = item;
    document.getElementById('add_item_id').value = item.id;
    document.getElementById('add_product_title').value = item.product_name;
    document.getElementById('add_current_qty').value = item.current_quantity;
    document.getElementById('add_new_qty').value = parseInt(item.current_quantity) + 5;
    document.getElementById('prorationBox').style.display = 'none';

    previewProration();
    const bsOffcanvas = new bootstrap.Offcanvas(document.getElementById('additionOffcanvas'));
    bsOffcanvas.show();
}

async function previewProration() {
    const itemId = document.getElementById('add_item_id').value;
    const currentQty = parseInt(document.getElementById('add_current_qty').value || 0);
    const newQty = parseInt(document.getElementById('add_new_qty').value || 0);
    
    const warnBox = document.getElementById('addWarningBox');
    const submitBtn = document.getElementById('btnSubmitAddition');
    const prorationBox = document.getElementById('prorationBox');

    if (!newQty || newQty <= currentQty) {
        document.getElementById('addWarningMsg').innerHTML = `New total seats must be greater than current active seats (${currentQty}). To decrease seats at renewal, please use the <strong>Queue Reduction</strong> action instead.`;
        warnBox.classList.remove('d-none');
        prorationBox.style.display = 'none';
        submitBtn.disabled = true;
        if (window.lucide) lucide.createIcons();
        return;
    }

    warnBox.classList.add('d-none');
    submitBtn.disabled = false;

    try {
        const res = await fetchAPI('<?= APP_URL ?>/ajax/subscriptions/prorate_preview.php', {
            method: 'POST',
            body: JSON.stringify({ item_id: itemId, new_quantity: newQty })
        });

        if (res.success) {
            document.getElementById('prora_added').innerText = res.data.added_seats;
            document.getElementById('prora_days').innerText = res.data.remaining_days;
            document.getElementById('prora_charge').innerText = res.data.currency + ' ' + res.data.prorated_charge;
            prorationBox.style.display = 'block';
        }
    } catch (e) {}
}

async function submitAddition() {
    const itemId = document.getElementById('add_item_id').value;
    const newQty = parseInt(document.getElementById('add_new_qty').value);
    const reason = document.getElementById('add_reason').value;

    try {
        const res = await fetchAPI('<?= APP_URL ?>/ajax/subscriptions/add_seats.php', {
            method: 'POST',
            body: JSON.stringify({ item_id: itemId, new_quantity: newQty, reason: reason })
        });
        if (res.success) {
            showToast('Seat addition processed cleanly.', 'success');
            setTimeout(() => location.reload(), 800);
        }
    } catch (e) {}
}

function openReductionModal(item) {
    document.getElementById('red_item_id').value = item.id;
    document.getElementById('red_product_title').value = item.product_name;
    document.getElementById('red_current_qty').value = item.current_quantity;
    document.getElementById('red_desired_qty').value = Math.max(1, parseInt(item.current_quantity) - 2);

    validateReduction();
    const bsOffcanvas = new bootstrap.Offcanvas(document.getElementById('reductionOffcanvas'));
    bsOffcanvas.show();
}

function validateReduction() {
    const currentQty = parseInt(document.getElementById('red_current_qty').value || 0);
    const desiredQty = parseInt(document.getElementById('red_desired_qty').value || 0);
    
    const warnBox = document.getElementById('redWarningBox');
    const submitBtn = document.getElementById('btnSubmitReduction');

    if (!desiredQty || desiredQty >= currentQty) {
        document.getElementById('redWarningMsg').innerHTML = `Desired seats at renewal must be less than current active seats (${currentQty}). To add seats immediately, please use the <strong>Add Seats</strong> action instead.`;
        warnBox.classList.remove('d-none');
        submitBtn.disabled = true;
        if (window.lucide) lucide.createIcons();
        return;
    }

    warnBox.classList.add('d-none');
    submitBtn.disabled = false;
}

async function submitReduction() {
    const itemId = document.getElementById('red_item_id').value;
    const desiredQty = parseInt(document.getElementById('red_desired_qty').value);
    const reason = document.getElementById('red_reason').value;

    try {
        const res = await fetchAPI('<?= APP_URL ?>/ajax/subscriptions/queue_reduction.php', {
            method: 'POST',
            body: JSON.stringify({ item_id: itemId, desired_quantity: desiredQty, reason: reason })
        });
        if (res.success) {
            showToast('Seat reduction queued for renewal date.', 'warning');
            setTimeout(() => location.reload(), 800);
        }
    } catch (e) {}
}

async function markPaymentPaid(paymentId) {
    const ref = await customPrompt("Enter Invoice Number for this payment schedule (serves as payment reference):", "Record Payment & Invoice Reference", "e.g. INV-2026-0084");
    if (ref === null) return;
    try {
        const res = await fetchAPI('<?= APP_URL ?>/ajax/payments/mark_paid.php', {
            method: 'POST',
            body: JSON.stringify({ payment_id: paymentId, reference: ref })
        });
        if (res.success) {
            showToast('Payment marked as Paid.', 'success');
            setTimeout(() => location.reload(), 800);
        }
    } catch (e) {}
}
</script>

<?php require_once __DIR__ . '/../../components/footer.php'; ?>
