<?php
// modules/clients/index.php - Client Company Directory
$pageTitle = 'Client Companies';
require_once __DIR__ . '/../../components/header.php';
RBAC::require('clients.view');

// Form submissions for Client Create/Update
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && hasPermission('clients.manage')) {
    $action = $_POST['action'] ?? '';
    if ($action === 'save_client') {
        $id = !empty($_POST['client_id']) ? (int)$_POST['client_id'] : 0;
        $data = [
            'company_name'          => trim($_POST['company_name'] ?? ''),
            'account_manager_id'    => $_POST['account_manager_id'] ?? null,
            'primary_contact_name'  => trim($_POST['primary_contact_name'] ?? ''),
            'primary_contact_email' => trim($_POST['primary_contact_email'] ?? ''),
            'primary_contact_phone' => trim($_POST['primary_contact_phone'] ?? ''),
            'address'               => trim($_POST['address'] ?? ''),
            'status'                => $_POST['status'] ?? 'active',
            'notes'                 => trim($_POST['notes'] ?? '')
        ];

        if (!empty($data['company_name'])) {
            if ($id > 0) {
                ClientManager::update($id, $data);
                $msg = 'Client updated successfully.';
            } else {
                ClientManager::create($data);
                $msg = 'Client company created successfully.';
            }
        }
    }
}

$clients = ClientManager::getAll();
$pdo = getDB();
$accountManagers = $pdo->query("SELECT id, CONCAT(first_name, ' ', last_name) as name FROM users WHERE status = 'active'")->fetchAll() ?: [];
?>

<div class="container-fluid max-w-7xl mx-auto">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 font-bold tracking-tight mb-1">Client Companies</h1>
            <p class="text-secondary text-sm">Manage client directory, primary contacts, and assigned account managers.</p>
        </div>
        <?php if (hasPermission('clients.manage')): ?>
        <button class="btn btn-primary d-flex align-items-center gap-2" data-bs-toggle="offcanvas" data-bs-target="#clientOffcanvas" onclick="resetClientForm()">
            <i data-lucide="plus" class="w-4 h-4"></i> Add Client
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
                        <th>Company Name</th>
                        <th>Account Manager</th>
                        <th>Primary Contact</th>
                        <th>Contact Email</th>
                        <th>Contracts</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($clients)): ?>
                        <tr><td colspan="7" class="text-center py-4 text-muted">No client companies found. Click "Add Client" to get started.</td></tr>
                    <?php else: ?>
                        <?php foreach ($clients as $c): ?>
                            <tr>
                                <td class="fw-bold text-dark"><?= sanitize($c['company_name']) ?></td>
                                <td><?= sanitize($c['account_manager_name'] ?? 'Unassigned') ?></td>
                                <td><?= sanitize($c['primary_contact_name'] ?: 'N/A') ?></td>
                                <td><a href="mailto:<?= sanitize($c['primary_contact_email']) ?>"><?= sanitize($c['primary_contact_email']) ?></a></td>
                                <td><span class="badge badge-secondary"><?= $c['contract_count'] ?> Contracts</span></td>
                                <td><?= renderStatusBadge($c['status']) ?></td>
                                <td class="text-end">
                                    <?php if (hasPermission('clients.manage')): ?>
                                    <button class="btn btn-sm btn-light border py-1 px-2 text-xs" 
                                            onclick='editClient(<?= json_encode($c) ?>)'>
                                        Edit
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

<!-- Offcanvas Panel for Client Form -->
<div class="offcanvas offcanvas-end" id="clientOffcanvas" style="width: 480px;">
    <div class="offcanvas-header border-bottom">
        <h5 class="offcanvas-title font-bold h6" id="offcanvasTitle">Add Client Company</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
    </div>
    <div class="offcanvas-body">
        <form method="POST" action="">
            <input type="hidden" name="action" value="save_client">
            <input type="hidden" name="client_id" id="client_id" value="">

            <div class="mb-3">
                <label class="form-label text-xs fw-bold text-secondary">Company Name *</label>
                <input type="text" name="company_name" id="company_name" class="form-control" placeholder="Acme Inc." required>
            </div>

            <div class="mb-3">
                <label class="form-label text-xs fw-bold text-secondary">Account Manager</label>
                <select name="account_manager_id" id="account_manager_id" class="form-select">
                    <option value="">-- Select Account Manager --</option>
                    <?php foreach ($accountManagers as $am): ?>
                        <option value="<?= $am['id'] ?>"><?= sanitize($am['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label text-xs fw-bold text-secondary">Primary Contact Name</label>
                <input type="text" name="primary_contact_name" id="primary_contact_name" class="form-control" placeholder="John Doe">
            </div>

            <div class="mb-3">
                <label class="form-label text-xs fw-bold text-secondary">Primary Contact Email</label>
                <input type="email" name="primary_contact_email" id="primary_contact_email" class="form-control" placeholder="john@acme.com">
            </div>

            <div class="mb-3">
                <label class="form-label text-xs fw-bold text-secondary">Primary Contact Phone</label>
                <input type="text" name="primary_contact_phone" id="primary_contact_phone" class="form-control" placeholder="+234...">
            </div>

            <div class="mb-3">
                <label class="form-label text-xs fw-bold text-secondary">Status</label>
                <select name="status" id="client_status" class="form-select">
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label text-xs fw-bold text-secondary">Notes</label>
                <textarea name="notes" id="client_notes" class="form-control" rows="3"></textarea>
            </div>

            <button type="submit" class="btn btn-primary w-100 py-2 font-semibold">Save Client</button>
        </form>
    </div>
</div>

<script>
function resetClientForm() {
    document.getElementById('offcanvasTitle').innerText = 'Add Client Company';
    document.getElementById('client_id').value = '';
    document.getElementById('company_name').value = '';
    document.getElementById('account_manager_id').value = '';
    document.getElementById('primary_contact_name').value = '';
    document.getElementById('primary_contact_email').value = '';
    document.getElementById('primary_contact_phone').value = '';
    document.getElementById('client_status').value = 'active';
    document.getElementById('client_notes').value = '';
}

function editClient(client) {
    document.getElementById('offcanvasTitle').innerText = 'Edit Client Company';
    document.getElementById('client_id').value = client.id;
    document.getElementById('company_name').value = client.company_name;
    document.getElementById('account_manager_id').value = client.account_manager_id || '';
    document.getElementById('primary_contact_name').value = client.primary_contact_name || '';
    document.getElementById('primary_contact_email').value = client.primary_contact_email || '';
    document.getElementById('primary_contact_phone').value = client.primary_contact_phone || '';
    document.getElementById('client_status').value = client.status || 'active';
    document.getElementById('client_notes').value = client.notes || '';
    
    const bsOffcanvas = new bootstrap.Offcanvas(document.getElementById('clientOffcanvas'));
    bsOffcanvas.show();
}
</script>

<?php require_once __DIR__ . '/../../components/footer.php'; ?>
