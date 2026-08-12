<?php
// modules/contracts/create.php - Contract Creation Wizard with Invoice Summary Ledger
require_once __DIR__ . '/../../includes/bootstrap.php';
RBAC::require('contracts.create');

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $client_id = (int)($_POST['client_id'] ?? 0);
        $account_manager_id = (int)($_POST['account_manager_id'] ?? 0);
        $start_date = $_POST['start_date'] ?? '';
        $expiry_date = $_POST['expiry_date'] ?? '';
        $billing_cycle = $_POST['billing_cycle'] ?? 'monthly';
        $currency = $_POST['currency'] ?? 'USD';
        $exchange_rate = (float)($_POST['exchange_rate'] ?? 1.0000);
        $notes = trim($_POST['notes'] ?? '');

        // Extract products array
        $product_ids = $_POST['product_ids'] ?? [];
        $quantities = $_POST['quantities'] ?? [];
        $unit_prices = $_POST['unit_prices'] ?? [];

        if (empty($client_id) || empty($start_date) || empty($expiry_date) || empty($product_ids)) {
            throw new Exception("Please select a client, dates, and at least one contract product line item.");
        }

        $items = [];
        for ($i = 0; $i < count($product_ids); $i++) {
            $pid = (int)$product_ids[$i];
            $qty = (int)($quantities[$i] ?? 1);
            $price = (float)($unit_prices[$i] ?? 0.00);

            if ($pid > 0 && $qty > 0) {
                $productDetails = ProductCatalog::getById($pid);
                $items[] = [
                    'product_id'    => $pid,
                    'pricing_model' => $productDetails['pricing_model'] ?? 'per_seat',
                    'quantity'      => $qty,
                    'unit_price'    => $price
                ];
            }
        }

        if (empty($items)) {
            throw new Exception("Contract must contain at least one valid product line item.");
        }

        $isApprover = hasPermission('contracts.approve');
        $approvalStatus = $isApprover ? 'approved' : 'pending';
        $contractStatus = $isApprover ? 'active' : 'draft';

        $contractData = [
            'client_id'          => $client_id,
            'account_manager_id' => $account_manager_id,
            'start_date'         => $start_date,
            'expiry_date'        => $expiry_date,
            'billing_cycle'      => $billing_cycle,
            'currency'           => $currency,
            'exchange_rate'      => $exchange_rate,
            'notes'              => $notes,
            'status'             => $contractStatus,
            'approval_status'    => $approvalStatus
        ];

        $newContractId = ContractEngine::createContract($contractData, $items);
        header('Location: ' . getContractUrl($newContractId));
        exit;

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

$pageTitle = 'Create New Contract';
require_once __DIR__ . '/../../components/header.php';

$clients = ClientManager::getAll();
$products = ProductCatalog::getAll();
$pdo = getDB();
$accountManagers = $pdo->query("SELECT id, CONCAT(first_name, ' ', last_name) as name FROM users WHERE status = 'active'")->fetchAll() ?: [];
?>

<div class="container-fluid max-w-5xl mx-auto">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 font-bold tracking-tight mb-1">Create New Subscription Contract</h1>
            <p class="text-secondary text-sm">Assemble client software contract line items, billing frequency, and agreed pricing.</p>
        </div>
        <a href="<?= APP_URL ?>/contracts" class="btn btn-outline-secondary btn-sm">Cancel</a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger py-2 text-sm mb-3"><?= sanitize($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="" id="contractForm">
        <!-- 1. Primary Parameters -->
        <div class="card-enterprise mb-4">
            <h5 class="fw-bold h6 border-bottom pb-3 mb-3">1. Primary Contract Parameters</h5>

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label text-xs fw-bold text-secondary">Client Company *</label>
                    <select name="client_id" class="form-select" required>
                        <option value="">-- Select Client --</option>
                        <?php foreach ($clients as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= sanitize($c['company_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label text-xs fw-bold text-secondary">Account Manager *</label>
                    <select name="account_manager_id" class="form-select" required>
                        <option value="">-- Select Account Manager --</option>
                        <?php foreach ($accountManagers as $am): ?>
                            <option value="<?= $am['id'] ?>"><?= sanitize($am['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label text-xs fw-bold text-secondary">Contract Start Date *</label>
                    <input type="date" name="start_date" id="start_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                </div>

                <div class="col-md-6">
                    <label class="form-label text-xs fw-bold text-secondary">Contract Expiry Date *</label>
                    <input type="date" name="expiry_date" id="expiry_date" class="form-control" value="<?= date('Y-m-d', strtotime('+1 year')) ?>" required>
                </div>

                <div class="col-md-4">
                    <label class="form-label text-xs fw-bold text-secondary">Billing Cycle *</label>
                    <select name="billing_cycle" id="billing_cycle" class="form-select" onchange="recalculateContractSummary()">
                        <option value="monthly">Monthly (12 Installments)</option>
                        <option value="quarterly">Quarterly (4 Installments)</option>
                        <option value="yearly" selected>Yearly / Annual (1 Installment)</option>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label text-xs fw-bold text-secondary">Contract Currency</label>
                    <select name="currency" id="currency_select" class="form-select" onchange="recalculateContractSummary()">
                        <option value="USD" selected>USD ($)</option>
                        <option value="NGN">NGN (₦)</option>
                        <option value="EUR">EUR (€)</option>
                        <option value="GBP">GBP (£)</option>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label text-xs fw-bold text-secondary">Exchange Rate Baseline</label>
                    <input type="number" step="0.0001" name="exchange_rate" id="exchange_rate" class="form-control" value="1550.00" oninput="recalculateContractSummary()">
                </div>
            </div>
        </div>

        <!-- 2. Products Table & Embedded Invoice Summary Ledger -->
        <div class="card-enterprise mb-4">
            <div class="d-flex justify-content-between align-items-center border-bottom pb-3 mb-3">
                <h5 class="fw-bold h6 mb-0">2. Contract Products & Subscriptions</h5>
                <button type="button" class="btn btn-sm btn-outline-primary font-semibold" onclick="addContractRow()">
                    <i data-lucide="plus" class="w-4 h-4 me-1"></i> Add Product Line
                </button>
            </div>

            <div class="table-responsive">
                <table class="table align-middle text-sm mb-0" id="itemsTable">
                    <thead class="bg-light">
                        <tr>
                            <th style="width: 40%;">Product Title</th>
                            <th style="width: 18%;">Quantity / Seats</th>
                            <th style="width: 22%;">Agreed Unit Price</th>
                            <th style="width: 15%;" class="text-end">Line Subtotal</th>
                            <th style="width: 5%;" class="text-end"></th>
                        </tr>
                    </thead>
                    <tbody id="itemsTableBody">
                        <!-- Dynamic Rows appended here -->
                    </tbody>
                </table>
            </div>

            <!-- Invoice Total Calculation Ledger -->
            <div class="border-top pt-4 mt-4">
                <div class="row justify-content-end">
                    <div class="col-md-7 col-lg-6">
                        <div class="p-4 rounded-3 border shadow-sm" style="background: #f8fafc; border-color: #e2e8f0;">
                            <div class="fw-bold text-dark text-xs mb-3.5 text-uppercase tracking-wider border-bottom pb-2.5 d-flex align-items-center justify-content-between">
                                <span class="font-bold">Commercial Invoice Summary</span>
                                <div class="badge-primary rounded-circle p-1.5 d-flex align-items-center justify-content-center">
                                    <i data-lucide="receipt" class="w-4 h-4 text-white"></i>
                                </div>
                            </div>

                            <div class="d-flex justify-content-between text-xs text-muted mb-2.5 px-1">
                                <span>Subscribed Items:</span>
                                <strong id="calcTotalProducts" class="text-dark font-medium">0 Product Line(s)</strong>
                            </div>

                            <div class="d-flex justify-content-between text-xs text-muted mb-2.5 px-1">
                                <span>Subscribed Seats:</span>
                                <strong id="calcTotalSeats" class="text-dark font-medium">0 Seats</strong>
                            </div>

                            <div class="d-flex justify-content-between text-xs text-muted mb-3 pb-2.5 border-bottom px-1">
                                <span>Billing Frequency:</span>
                                <strong id="summaryCycleBadge" class="text-dark font-medium">Yearly / Annual</strong>
                            </div>

                            <div class="d-flex justify-content-between align-items-center text-sm fw-bold text-dark mb-3 py-2 px-3 rounded-2" style="background: #ffffff; border: 1px dashed #cbd5e1;">
                                <span class="text-dark">Total Contract Value:</span>
                                <span id="calcTotalValue" class="h5 font-bold text-primary mb-0">$0.00</span>
                            </div>

                            <div class="d-flex justify-content-between text-xs text-muted mb-3 px-1">
                                <span>Per-Installment Collection:</span>
                                <strong id="calcInstallmentValue" class="text-dark font-semibold">$0.00</strong>
                            </div>

                            <div class="d-flex justify-content-between text-xs text-muted pt-2.5 border-top px-1">
                                <span>Local Equivalent (NGN):</span>
                                <strong id="calcNgnEquivalent" class="text-success font-mono font-bold">₦0.00</strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 3. Notes & Submit Buttons -->
        <div class="card-enterprise mb-4">
            <label class="form-label text-xs fw-bold text-secondary">Special Terms / Notes</label>
            <textarea name="notes" class="form-control" rows="3" placeholder="Enter any agreed commercial terms, discount notes, or billing instructions..."></textarea>
        </div>

        <div class="d-flex justify-content-end gap-2">
            <a href="<?= APP_URL ?>/contracts" class="btn btn-light border px-4 font-medium">Cancel</a>
            <button type="submit" class="btn btn-primary px-5 font-semibold">Save & Activate Contract</button>
        </div>
    </form>
</div>

<script>
const productsCatalog = <?= json_encode($products) ?>;

function addContractRow() {
    const tbody = document.getElementById('itemsTableBody');
    const tr = document.createElement('tr');

    let options = '<option value="">-- Select Product --</option>';
    productsCatalog.forEach(p => {
        options += `<option value="${p.id}" data-cost="${p.default_unit_cost}">${p.vendor_name} - ${p.product_name} ($${parseFloat(p.default_unit_cost).toFixed(2)})</option>`;
    });

    tr.innerHTML = `
        <td>
            <select name="product_ids[]" class="form-select form-select-sm prod-select" onchange="onProductSelect(this)" required>
                ${options}
            </select>
        </td>
        <td>
            <input type="number" min="1" name="quantities[]" class="form-control form-control-sm qty-input" value="1" oninput="recalculateContractSummary()" required>
        </td>
        <td>
            <input type="number" step="0.01" name="unit_prices[]" class="form-control form-control-sm unit-price-input" value="0.00" oninput="recalculateContractSummary()" required>
        </td>
        <td class="text-end fw-bold text-dark line-subtotal">$0.00</td>
        <td class="text-end">
            <button type="button" class="btn btn-sm btn-outline-danger py-0 px-2 rounded-circle" onclick="removeContractRow(this)">&times;</button>
        </td>
    `;
    tbody.appendChild(tr);
    if (window.lucide) lucide.createIcons();
    recalculateContractSummary();
}

function removeContractRow(btn) {
    btn.closest('tr').remove();
    recalculateContractSummary();
}

function onProductSelect(selectEl) {
    const selectedOption = selectEl.options[selectEl.selectedIndex];
    const defaultCost = selectedOption.getAttribute('data-cost');
    const tr = selectEl.closest('tr');
    const priceInput = tr.querySelector('.unit-price-input');
    if (defaultCost && priceInput) {
        priceInput.value = parseFloat(defaultCost).toFixed(2);
    }
    recalculateContractSummary();
}

function recalculateContractSummary() {
    let grandTotal = 0;
    let totalSeats = 0;
    let productCount = 0;

    const rows = document.querySelectorAll('#itemsTableBody tr');
    const curr = document.getElementById('currency_select').value || 'USD';
    const currSymbol = (curr === 'NGN') ? '₦' : (curr === 'EUR') ? '€' : (curr === 'GBP') ? '£' : '$';

    rows.forEach(tr => {
        const prodSelect = tr.querySelector('.prod-select');
        const qtyInput   = tr.querySelector('.qty-input');
        const priceInput = tr.querySelector('.unit-price-input');
        const subtotalTd = tr.querySelector('.line-subtotal');

        if (prodSelect && prodSelect.value) {
            productCount++;
            const qty = parseInt(qtyInput.value) || 0;
            const price = parseFloat(priceInput.value) || 0;
            const lineTotal = qty * price;

            totalSeats += qty;
            grandTotal += lineTotal;

            if (subtotalTd) {
                subtotalTd.innerText = currSymbol + lineTotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }
        } else if (subtotalTd) {
            subtotalTd.innerText = currSymbol + '0.00';
        }
    });

    // Billing Cycle Calculation
    const cycle = document.getElementById('billing_cycle').value || 'yearly';
    let installmentsCount = 1;
    let cycleLabel = 'Yearly / Annual';

    if (cycle === 'monthly') {
        installmentsCount = 12;
        cycleLabel = 'Monthly (12 Payments)';
    } else if (cycle === 'quarterly') {
        installmentsCount = 4;
        cycleLabel = 'Quarterly (4 Payments)';
    }

    const perInstallment = (installmentsCount > 0) ? (grandTotal / installmentsCount) : grandTotal;

    // Exchange Rate Conversion (Local Equivalent)
    const rate = parseFloat(document.getElementById('exchange_rate').value) || 1550.0;
    const ngnTotal = grandTotal * rate;

    // Update Summary UI Elements
    document.getElementById('summaryCycleBadge').innerText = cycleLabel;
    document.getElementById('calcTotalValue').innerText = currSymbol + grandTotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    document.getElementById('calcInstallmentValue').innerText = currSymbol + perInstallment.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ((installmentsCount > 1) ? ` / ${cycle.slice(0, -2)}` : '');
    
    document.getElementById('calcTotalSeats').innerText = totalSeats + ' Seat(s)';
    document.getElementById('calcTotalProducts').innerText = productCount + ' Product Line(s)';

    document.getElementById('calcNgnEquivalent').innerText = '₦' + ngnTotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

// Initialize on load
document.addEventListener('DOMContentLoaded', () => {
    addContractRow();
});
</script>

<?php require_once __DIR__ . '/../../components/footer.php'; ?>
