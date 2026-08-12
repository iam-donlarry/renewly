<?php
// modules/vendors/index.php - Vendor Catalog Directory
$pageTitle = 'Vendors Catalog';
require_once __DIR__ . '/../../components/header.php';
RBAC::require('vendors.view');

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && hasPermission('vendors.manage')) {
    $action = $_POST['action'] ?? '';
    if ($action === 'save_vendor') {
        $id = !empty($_POST['vendor_id']) ? (int)$_POST['vendor_id'] : 0;
        $data = [
            'vendor_name'   => trim($_POST['vendor_name'] ?? ''),
            'website'       => trim($_POST['website'] ?? ''),
            'support_email' => trim($_POST['support_email'] ?? ''),
            'status'        => $_POST['status'] ?? 'active',
            'notes'         => trim($_POST['notes'] ?? '')
        ];

        if (!empty($data['vendor_name'])) {
            if ($id > 0) {
                VendorManager::update($id, $data);
                $msg = 'Vendor updated successfully.';
            } else {
                VendorManager::create($data);
                $msg = 'Vendor added successfully.';
            }
        }
    }
}

$vendors = VendorManager::getAll();
?>

<div class="container-fluid max-w-7xl mx-auto">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 font-bold tracking-tight mb-1">Vendor Directory</h1>
            <p class="text-secondary text-sm">Manage software and cloud vendor catalog (Microsoft, Adobe, AWS, Zoom, etc.).</p>
        </div>
        <?php if (hasPermission('vendors.manage')): ?>
        <button class="btn btn-primary d-flex align-items-center gap-2" data-bs-toggle="offcanvas" data-bs-target="#vendorOffcanvas" onclick="resetVendorForm()">
            <i data-lucide="plus" class="w-4 h-4"></i> Add Vendor
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
                        <th>Vendor Name</th>
                        <th>Website</th>
                        <th>Support Email</th>
                        <th>Products Count</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($vendors)): ?>
                        <tr><td colspan="6" class="text-center py-4 text-muted">No vendors found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($vendors as $v): ?>
                            <tr>
                                <td class="fw-bold text-dark"><?= sanitize($v['vendor_name']) ?></td>
                                <td><a href="<?= sanitize($v['website']) ?>" target="_blank" class="text-primary"><?= sanitize($v['website'] ?: 'N/A') ?></a></td>
                                <td><?= sanitize($v['support_email'] ?: 'N/A') ?></td>
                                <td><span class="badge badge-info"><?= $v['product_count'] ?> Products</span></td>
                                <td><?= renderStatusBadge($v['status']) ?></td>
                                <td class="text-end">
                                    <?php if (hasPermission('vendors.manage')): ?>
                                    <button class="btn btn-sm btn-light border py-1 px-2 text-xs" onclick='editVendor(<?= json_encode($v) ?>)'>Edit</button>
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

<!-- Offcanvas Panel for Vendor Form -->
<div class="offcanvas offcanvas-end" id="vendorOffcanvas" style="width: 480px;">
    <div class="offcanvas-header border-bottom">
        <h5 class="offcanvas-title font-bold h6" id="vendorOffcanvasTitle">Add Vendor</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
    </div>
    <div class="offcanvas-body">
        <form method="POST" action="">
            <input type="hidden" name="action" value="save_vendor">
            <input type="hidden" name="vendor_id" id="vendor_id" value="">

            <div class="mb-3">
                <label class="form-label text-xs fw-bold text-secondary">Vendor Name *</label>
                <input type="text" name="vendor_name" id="vendor_name" class="form-control" placeholder="Microsoft" required>
            </div>

            <div class="mb-3">
                <label class="form-label text-xs fw-bold text-secondary">Website URL</label>
                <input type="url" name="website" id="website" class="form-control" placeholder="https://microsoft.com">
            </div>

            <div class="mb-3">
                <label class="form-label text-xs fw-bold text-secondary">Support Email</label>
                <input type="email" name="support_email" id="support_email" class="form-control" placeholder="support@microsoft.com">
            </div>

            <div class="mb-3">
                <label class="form-label text-xs fw-bold text-secondary">Status</label>
                <select name="status" id="vendor_status" class="form-select">
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>

            <button type="submit" class="btn btn-primary w-100 py-2 font-semibold">Save Vendor</button>
        </form>
    </div>
</div>

<script>
function resetVendorForm() {
    document.getElementById('vendorOffcanvasTitle').innerText = 'Add Vendor';
    document.getElementById('vendor_id').value = '';
    document.getElementById('vendor_name').value = '';
    document.getElementById('website').value = '';
    document.getElementById('support_email').value = '';
    document.getElementById('vendor_status').value = 'active';
}

function editVendor(v) {
    document.getElementById('vendorOffcanvasTitle').innerText = 'Edit Vendor';
    document.getElementById('vendor_id').value = v.id;
    document.getElementById('vendor_name').value = v.vendor_name;
    document.getElementById('website').value = v.website || '';
    document.getElementById('support_email').value = v.support_email || '';
    document.getElementById('vendor_status').value = v.status || 'active';
    
    const bsOffcanvas = new bootstrap.Offcanvas(document.getElementById('vendorOffcanvas'));
    bsOffcanvas.show();
}
</script>

<?php require_once __DIR__ . '/../../components/footer.php'; ?>
