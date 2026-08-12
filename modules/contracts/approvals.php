<?php
// modules/contracts/approvals.php - Contract Approval Queue & Review Center
require_once __DIR__ . '/../../includes/bootstrap.php';
RBAC::require('contracts.approve');

$pdo = getDB();
$msg = '';
$msgType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $contractId = (int)($_POST['contract_id'] ?? 0);
    $action     = $_POST['action'] ?? '';
    $reason     = trim($_POST['rejection_reason'] ?? '');

    if ($contractId > 0 && in_array($action, ['approve', 'reject', 'update_and_approve'])) {
        $pdo->beginTransaction();
        try {
            if ($action === 'reject') {
                $stmt = $pdo->prepare("UPDATE contracts SET approval_status = 'rejected', status = 'rejected', notes = CONCAT(IFNULL(notes, ''), '\n[Rejection Reason]: ', ?), updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt->execute([$reason ?: 'No specific reason provided.', $contractId]);

                AuditLogger::log('contract_rejected', 'contracts', $contractId, ['reason' => $reason]);
                $msg = "Contract #{$contractId} has been rejected.";
                $msgType = 'warning';
            } else {
                // If update_and_approve, update line items first
                if ($action === 'update_and_approve' && !empty($_POST['item_ids'])) {
                    $itemIds = $_POST['item_ids'];
                    $quantities = $_POST['quantities'] ?? [];
                    $unitPrices = $_POST['unit_prices'] ?? [];

                    $totalContractValue = 0.0;
                    for ($i = 0; $i < count($itemIds); $i++) {
                        $iid   = (int)$itemIds[$i];
                        $qty   = (int)($quantities[$i] ?? 1);
                        $price = (float)($unitPrices[$i] ?? 0.00);
                        $lineTotal = $qty * $price;
                        $totalContractValue += $lineTotal;

                        $stmtUpd = $pdo->prepare("UPDATE contract_items SET current_quantity = ?, unit_price = ?, line_total = ? WHERE id = ? AND contract_id = ?");
                        $stmtUpd->execute([$qty, $price, $lineTotal, $iid, $contractId]);
                    }

                    // Update master contract total
                    $stmtTotal = $pdo->prepare("UPDATE contracts SET total_contract_value = ? WHERE id = ?");
                    $stmtTotal->execute([$totalContractValue, $contractId]);
                }

                // Mark contract as approved and active
                $stmt = $pdo->prepare("UPDATE contracts SET approval_status = 'approved', status = 'active', updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt->execute([$contractId]);

                // Clear any legacy/unapproved schedules and generate fresh payment installment schedule
                $pdo->prepare("DELETE FROM payment_schedules WHERE contract_id = ? AND status IN ('pending', 'due', 'overdue')")->execute([$contractId]);
                PaymentScheduleEngine::generateSchedule($contractId, true);

                AuditLogger::log('contract_approved', 'contracts', $contractId, ['approval_status' => 'approved']);
                $msg = "Contract #{$contractId} approved and activated successfully.";
                $msgType = 'success';
            }

            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            $msg = 'Failed to process approval action: ' . $e->getMessage();
            $msgType = 'danger';
        }
    }
}

// Fetch Pending Approvals with detailed item payloads
$pending = $pdo->query("
    SELECT c.*, cl.company_name, cl.primary_contact_name, cl.primary_contact_email,
           CONCAT(u.first_name, ' ', u.last_name) as account_manager_name
    FROM contracts c
    JOIN clients cl ON c.client_id = cl.id
    LEFT JOIN users u ON c.account_manager_id = u.id
    WHERE c.approval_status = 'pending'
    ORDER BY c.created_at ASC
")->fetchAll() ?: [];

// Attach items to pending contracts for offcanvas inspection
foreach ($pending as &$req) {
    $stmtItems = $pdo->prepare("
        SELECT ci.*, p.product_name, v.vendor_name
        FROM contract_items ci
        JOIN products p ON ci.product_id = p.id
        JOIN vendors v ON p.vendor_id = v.id
        WHERE ci.contract_id = ?
    ");
    $stmtItems->execute([$req['id']]);
    $req['items'] = $stmtItems->fetchAll() ?: [];
}
unset($req);

$pageTitle = 'Contract Approval Queue';
require_once __DIR__ . '/../../components/header.php';
?>

<div class="container-fluid max-w-7xl mx-auto">
    <div class="mb-4">
        <h1 class="h3 font-bold tracking-tight mb-1">Contract Approval Queue</h1>
        <p class="text-secondary text-sm">Review, adjust, approve, or reject pending client subscription agreements.</p>
    </div>

    <?php if ($msg): ?>
        <div class="alert alert-<?= $msgType ?> py-2.5 text-sm mb-4 d-flex align-items-center justify-content-between">
            <div>
                <i data-lucide="<?= $msgType === 'success' ? 'check-circle' : 'info' ?>" class="w-4 h-4 me-1.5 align-middle"></i>
                <?= sanitize($msg) ?>
            </div>
            <button type="button" class="btn-close text-xs" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (empty($pending)): ?>
        <div class="card-enterprise p-5 text-center">
            <div class="mb-3">
                <div class="badge-success rounded-circle d-inline-flex p-3">
                    <i data-lucide="check-circle" class="w-8 h-8"></i>
                </div>
            </div>
            <h5 class="fw-bold h6">All caught up!</h5>
            <p class="text-secondary text-sm mb-0">There are no pending contract approval requests at this time.</p>
        </div>
    <?php else: ?>
        <div class="d-flex flex-column gap-3.5">
            <?php foreach ($pending as $req): ?>
                <div class="card-enterprise p-4 shadow-sm hover-shadow transition">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                        
                        <!-- Contract Details Column -->
                        <div class="d-flex gap-3 align-items-center">
                            <div class="user-avatar-placeholder text-xs" style="width: 44px; height: 44px; font-size: 1rem;">
                                <?= strtoupper(substr($req['company_name'], 0, 2)) ?>
                            </div>
                            <div>
                                <div class="d-flex align-items-center gap-2 mb-1">
                                    <a href="<?= getContractUrl($req) ?>" class="fw-bold text-dark h6 mb-0 text-decoration-none hover-teal">
                                        <?= sanitize($req['contract_reference']) ?>
                                    </a>
                                    <span class="badge badge-warning text-xs">Pending Approval</span>
                                    <span class="badge badge-secondary text-xs"><?= ucfirst($req['billing_cycle']) ?></span>
                                </div>

                                <div class="d-flex gap-3 text-xs text-secondary flex-wrap">
                                    <span>Client: <strong class="text-dark"><?= sanitize($req['company_name']) ?></strong></span>
                                    <span>Manager: <strong class="text-dark"><?= sanitize($req['account_manager_name'] ?? 'Unassigned') ?></strong></span>
                                    <span>Start: <strong class="text-dark"><?= formatDate($req['start_date']) ?></strong></span>
                                    <span>Expiry: <strong class="text-dark"><?= formatDate($req['expiry_date']) ?></strong></span>
                                </div>
                            </div>
                        </div>

                        <!-- Unclustered Action Toolbar -->
                        <div class="d-flex align-items-center gap-2.5">
                            <!-- Contract Total Badge -->
                            <div class="text-end me-2 d-none d-md-block">
                                <div class="text-xs text-muted">Total Value</div>
                                <div class="fw-bold text-success h6 mb-0"><?= formatCurrency((float)$req['total_contract_value'], $req['currency']) ?></div>
                            </div>

                            <!-- 1. Review & Adjust Offcanvas Trigger -->
                            <button type="button" class="btn btn-sm btn-light border font-semibold px-3 py-2 d-flex align-items-center gap-1.5"
                                onclick='openApprovalReview(<?= json_encode($req) ?>)'>
                                <i data-lucide="eye" class="w-4 h-4 text-secondary"></i> Review & Edit
                            </button>

                            <!-- 2. Direct Reject Button -->
                            <button type="button" class="btn btn-sm btn-outline-danger font-semibold px-3 py-2 d-flex align-items-center gap-1.5"
                                onclick='openRejectModal(<?= json_encode($req) ?>)'>
                                <i data-lucide="x" class="w-4 h-4"></i> Reject
                            </button>

                            <!-- 3. Direct Approve Button -->
                            <form method="POST" action="" class="m-0">
                                <input type="hidden" name="contract_id" value="<?= $req['id'] ?>">
                                <input type="hidden" name="action" value="approve">
                                <button type="submit" class="btn btn-sm btn-success font-semibold px-3.5 py-2 d-flex align-items-center gap-1.5 shadow-sm">
                                    <i data-lucide="check" class="w-4 h-4"></i> Approve
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Half-Screen Offcanvas Drawer for Contract Inspection & Admin Edits -->
<div class="offcanvas offcanvas-end" id="approvalReviewOffcanvas" tabindex="-1" style="width: 50vw; min-width: 520px;">
    <div class="offcanvas-header border-bottom bg-light">
        <div>
            <h5 class="offcanvas-title font-bold h6" id="reviewOffcanvasTitle">Review Contract Details</h5>
            <span class="text-xs text-muted" id="reviewOffcanvasSubtitle">Inspect commercial terms & adjust pricing snapshot</span>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
    </div>

    <div class="offcanvas-body p-4">
        <form method="POST" action="" id="approvalReviewForm">
            <input type="hidden" name="contract_id" id="offcanvas_contract_id" value="">
            <input type="hidden" name="action" value="update_and_approve">

            <!-- Primary Metadata Card -->
            <div class="p-3 bg-light rounded-3 border mb-4">
                <div class="row g-2 text-xs">
                    <div class="col-6">
                        <span class="text-muted">Client Company:</span>
                        <div class="fw-bold text-dark" id="offcanvas_client_name">-</div>
                    </div>
                    <div class="col-6">
                        <span class="text-muted">Account Manager:</span>
                        <div class="fw-bold text-dark" id="offcanvas_am_name">-</div>
                    </div>
                    <div class="col-6">
                        <span class="text-muted">Start Date:</span>
                        <div class="fw-bold text-dark" id="offcanvas_start_date">-</div>
                    </div>
                    <div class="col-6">
                        <span class="text-muted">Expiry Date:</span>
                        <div class="fw-bold text-dark" id="offcanvas_expiry_date">-</div>
                    </div>
                </div>
            </div>

            <!-- Line Items Table (Editable by Admin) -->
            <h6 class="fw-bold text-dark text-xs text-uppercase tracking-wider mb-2.5 d-flex justify-content-between align-items-center">
                <span>Subscribed Product Line Items</span>
                <span class="text-muted font-normal text-xs">(Editable before approval)</span>
            </h6>

            <div class="table-responsive border rounded-3 mb-4">
                <table class="table table-sm align-middle text-xs mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th>Product Title</th>
                            <th style="width: 25%;">Seats / Qty</th>
                            <th style="width: 30%;">Agreed Unit Price</th>
                            <th class="text-end">Line Total</th>
                        </tr>
                    </thead>
                    <tbody id="offcanvas_items_tbody">
                        <!-- Dynamic items populated by JS -->
                    </tbody>
                </table>
            </div>

            <!-- Admin Commercial Summary Box -->
            <div class="p-3.5 rounded-3 border bg-light mb-4">
                <div class="d-flex justify-content-between text-xs text-muted mb-1">
                    <span>Billing Frequency:</span>
                    <strong id="offcanvas_billing_cycle" class="text-dark">-</strong>
                </div>
                <div class="d-flex justify-content-between text-sm fw-bold text-dark border-top pt-2 mt-2">
                    <span>Total Calculated Value:</span>
                    <strong id="offcanvas_total_value" class="text-primary h6 mb-0">$0.00</strong>
                </div>
            </div>

            <div class="offcanvas-footer border-top pt-3 mt-4 d-flex justify-content-between align-items-center">
                <button type="button" class="btn btn-outline-danger btn-sm font-semibold" onclick="triggerRejectFromOffcanvas()">
                    <i data-lucide="x" class="w-4 h-4 me-1"></i> Reject
                </button>

                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-light border btn-sm px-3" data-bs-dismiss="offcanvas">Cancel</button>
                    <button type="submit" class="btn btn-success btn-sm font-semibold px-4 shadow-sm">
                        <i data-lucide="check" class="w-4 h-4 me-1"></i> Save & Approve Contract
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Rejection Reason Dialog -->
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="">
                <input type="hidden" name="contract_id" id="reject_contract_id" value="">
                <input type="hidden" name="action" value="reject">

                <div class="modal-header border-bottom">
                    <h5 class="modal-title font-bold h6">Reject Contract Agreement</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-xs text-muted mb-3">Please specify a reason for rejecting this contract agreement. The account manager will be notified in the audit log.</p>
                    <div class="mb-3">
                        <label class="form-label text-xs font-semibold">Rejection Justification / Reason *</label>
                        <textarea name="rejection_reason" class="form-control" rows="3" placeholder="e.g. Discount exceeds approved 15% threshold..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer border-top bg-light">
                    <button type="button" class="btn btn-light border btn-sm px-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger btn-sm font-semibold px-4">Reject Agreement</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let currentContractForReview = null;

function openApprovalReview(contract) {
    currentContractForReview = contract;
    document.getElementById('offcanvas_contract_id').value = contract.id;
    document.getElementById('reviewOffcanvasTitle').innerText = contract.contract_reference + ' — Details Review';
    document.getElementById('reviewOffcanvasSubtitle').innerText = 'Agreement for ' + contract.company_name;

    document.getElementById('offcanvas_client_name').innerText = contract.company_name;
    document.getElementById('offcanvas_am_name').innerText = contract.account_manager_name || 'Unassigned';
    document.getElementById('offcanvas_start_date').innerText = contract.start_date;
    document.getElementById('offcanvas_expiry_date').innerText = contract.expiry_date;
    document.getElementById('offcanvas_billing_cycle').innerText = (contract.billing_cycle || '').toUpperCase();

    // Render Editable Items
    const tbody = document.getElementById('offcanvas_items_tbody');
    tbody.innerHTML = '';

    const curr = contract.currency || 'USD';
    const currSymbol = (curr === 'NGN') ? '₦' : (curr === 'EUR') ? '€' : (curr === 'GBP') ? '£' : '$';

    (contract.items || []).forEach(item => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>
                <input type="hidden" name="item_ids[]" value="${item.id}">
                <div class="fw-semibold text-dark">${item.product_name}</div>
                <div class="text-muted" style="font-size: 0.7rem;">${item.vendor_name}</div>
            </td>
            <td>
                <input type="number" min="1" name="quantities[]" class="form-control form-control-sm item-qty-input" value="${item.current_quantity}" oninput="recalcOffcanvasTotal('${currSymbol}')">
            </td>
            <td>
                <input type="number" step="0.01" name="unit_prices[]" class="form-control form-control-sm item-price-input" value="${parseFloat(item.unit_price).toFixed(2)}" oninput="recalcOffcanvasTotal('${currSymbol}')">
            </td>
            <td class="text-end fw-bold text-dark item-line-total">
                ${currSymbol}${(item.current_quantity * item.unit_price).toFixed(2)}
            </td>
        `;
        tbody.appendChild(tr);
    });

    recalcOffcanvasTotal(currSymbol);

    const bsOffcanvas = new bootstrap.Offcanvas(document.getElementById('approvalReviewOffcanvas'));
    bsOffcanvas.show();
}

function recalcOffcanvasTotal(currSymbol = '$') {
    let grandTotal = 0;
    const rows = document.querySelectorAll('#offcanvas_items_tbody tr');
    rows.forEach(tr => {
        const qty = parseInt(tr.querySelector('.item-qty-input').value) || 0;
        const price = parseFloat(tr.querySelector('.item-price-input').value) || 0;
        const lineTotal = qty * price;
        grandTotal += lineTotal;
        tr.querySelector('.item-line-total').innerText = currSymbol + lineTotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    });

    document.getElementById('offcanvas_total_value').innerText = currSymbol + grandTotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function openRejectModal(contract) {
    document.getElementById('reject_contract_id').value = contract.id;
    const modal = new bootstrap.Modal(document.getElementById('rejectModal'));
    modal.show();
}

function triggerRejectFromOffcanvas() {
    if (currentContractForReview) {
        const offcanvasEl = document.getElementById('approvalReviewOffcanvas');
        const bsOffcanvas = bootstrap.Offcanvas.getInstance(offcanvasEl);
        if (bsOffcanvas) bsOffcanvas.hide();

        setTimeout(() => {
            openRejectModal(currentContractForReview);
        }, 300);
    }
}
</script>

<?php require_once __DIR__ . '/../../components/footer.php'; ?>
