<?php
// modules/products/index.php - Product Catalog & Pricing Models
$pageTitle = 'Products & Pricing';
require_once __DIR__ . '/../../components/header.php';
RBAC::require('vendors.view');

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && hasPermission('vendors.manage')) {
    $action = $_POST['action'] ?? '';
    if ($action === 'save_product') {
        $id = !empty($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
        $data = [
            'vendor_id'         => (int)$_POST['vendor_id'],
            'product_name'      => trim($_POST['product_name'] ?? ''),
            'pricing_model'     => $_POST['pricing_model'] ?? 'per_seat',
            'default_unit_cost' => (float)($_POST['default_unit_cost'] ?? 0.00),
            'currency'          => $_POST['currency'] ?? 'USD',
            'description'       => trim($_POST['description'] ?? ''),
            'status'            => $_POST['status'] ?? 'active'
        ];

        if (!empty($data['product_name']) && $data['vendor_id'] > 0) {
            if ($id > 0) {
                ProductCatalog::update($id, $data);
                $msg = 'Product updated successfully.';
            } else {
                ProductCatalog::create($data);
                $msg = 'Product created successfully.';
            }
        }
    }
}

$products = ProductCatalog::getAll();
$vendors = VendorManager::getAll();
?>

<div class="container-fluid max-w-7xl mx-auto">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 font-bold tracking-tight mb-1">Products & Pricing Catalog</h1>
            <p class="text-secondary text-sm">Configure vendor software products, seat/flat pricing models, and baseline prices.</p>
        </div>
        <?php if (hasPermission('vendors.manage')): ?>
        <button class="btn btn-primary d-flex align-items-center gap-2" data-bs-toggle="offcanvas" data-bs-target="#productOffcanvas" onclick="resetProductForm()">
            <i data-lucide="plus" class="w-4 h-4"></i> Add Product
        </button>
        <?php endif; ?>
    </div>

    <?php if ($msg): ?>
        <div class="alert alert-success py-2 text-sm mb-3"><?= sanitize($msg) ?></div>
    <?php endif; ?>

    <div class="card-enterprise">
        <div class="table-responsive">
            <table class="table table-hover align-middle text-sm mb-0">
                <thead class="bg-light">
                    <tr>
                        <th>Product Title</th>
                        <th>Vendor</th>
                        <th>Pricing Model</th>
                        <th>Default Cost</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($products)): ?>
                        <tr><td colspan="6" class="text-center py-4 text-muted">No products found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($products as $p): ?>
                            <tr>
                                <td class="fw-bold text-dark"><?= sanitize($p['product_name']) ?></td>
                                <td><?= sanitize($p['vendor_name']) ?></td>
                                <td>
                                    <span class="badge badge-secondary">
                                        <?= $p['pricing_model'] === 'per_seat' ? 'Per Seat / User' : 'Flat Rate' ?>
                                    </span>
                                </td>
                                <td class="fw-semibold text-success"><?= formatCurrency($p['default_unit_cost'], $p['currency']) ?></td>
                                <td><?= renderStatusBadge($p['status']) ?></td>
                                <td class="text-end">
                                    <?php if (hasPermission('vendors.manage')): ?>
                                    <button class="btn btn-sm btn-light border py-1 px-2 text-xs" onclick='editProduct(<?= json_encode($p) ?>)'>Edit</button>
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

<!-- Offcanvas Panel for Product Form -->
<div class="offcanvas offcanvas-end" id="productOffcanvas" style="width: 480px;">
    <div class="offcanvas-header border-bottom">
        <h5 class="offcanvas-title font-bold h6" id="productOffcanvasTitle">Add Product</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
    </div>
    <div class="offcanvas-body">
        <form method="POST" action="">
            <input type="hidden" name="action" value="save_product">
            <input type="hidden" name="product_id" id="product_id" value="">

            <div class="mb-3">
                <label class="form-label text-xs fw-bold text-secondary">Vendor *</label>
                <select name="vendor_id" id="prod_vendor_id" class="form-select" required>
                    <option value="">-- Select Vendor --</option>
                    <?php foreach ($vendors as $v): ?>
                        <option value="<?= $v['id'] ?>"><?= sanitize($v['vendor_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label text-xs fw-bold text-secondary">Product Name *</label>
                <input type="text" name="product_name" id="product_name" class="form-control" placeholder="Microsoft 365 Business Premium" required>
            </div>

            <div class="mb-3">
                <label class="form-label text-xs fw-bold text-secondary">Pricing Model *</label>
                <select name="pricing_model" id="pricing_model" class="form-select">
                    <option value="per_seat">Per Seat / Per User</option>
                    <option value="flat_rate">Flat Rate</option>
                </select>
            </div>

            <div class="row">
                <div class="col-8 mb-3">
                    <label class="form-label text-xs fw-bold text-secondary">Default Unit Cost *</label>
                    <input type="number" step="0.0001" name="default_unit_cost" id="default_unit_cost" class="form-control" placeholder="22.00" required>
                </div>
                <div class="col-4 mb-3">
                    <label class="form-label text-xs fw-bold text-secondary">Currency</label>
                    <select name="currency" id="prod_currency" class="form-select">
                        <option value="USD">USD ($)</option>
                        <option value="NGN">NGN (₦)</option>
                        <option value="EUR">EUR (€)</option>
                        <option value="GBP">GBP (£)</option>
                    </select>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label text-xs fw-bold text-secondary">Status</label>
                <select name="status" id="prod_status" class="form-select">
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>

            <button type="submit" class="btn btn-primary w-100 py-2 font-semibold">Save Product</button>
        </form>
    </div>
</div>

<script>
function resetProductForm() {
    document.getElementById('productOffcanvasTitle').innerText = 'Add Product';
    document.getElementById('product_id').value = '';
    document.getElementById('prod_vendor_id').value = '';
    document.getElementById('product_name').value = '';
    document.getElementById('pricing_model').value = 'per_seat';
    document.getElementById('default_unit_cost').value = '';
    document.getElementById('prod_currency').value = 'USD';
    document.getElementById('prod_status').value = 'active';
}

function editProduct(p) {
    document.getElementById('productOffcanvasTitle').innerText = 'Edit Product';
    document.getElementById('product_id').value = p.id;
    document.getElementById('prod_vendor_id').value = p.vendor_id;
    document.getElementById('product_name').value = p.product_name;
    document.getElementById('pricing_model').value = p.pricing_model || 'per_seat';
    document.getElementById('default_unit_cost').value = p.default_unit_cost;
    document.getElementById('prod_currency').value = p.currency || 'USD';
    document.getElementById('prod_status').value = p.status || 'active';
    
    const bsOffcanvas = new bootstrap.Offcanvas(document.getElementById('productOffcanvas'));
    bsOffcanvas.show();
}
</script>

<?php require_once __DIR__ . '/../../components/footer.php'; ?>
