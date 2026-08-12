<?php
// modules/users/index.php - User Accounts Management
$pageTitle = 'User Accounts';
require_once __DIR__ . '/../../components/header.php';
RBAC::require('users.manage');

$msg = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'save_user') {
        $id = !empty($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $roleId = (int)($_POST['role_id'] ?? 0);
        $status = $_POST['status'] ?? 'active';

        $pdo = getDB();
        if ($id > 0) {
            if (!empty($password)) {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, password_hash = ?, role_id = ?, status = ? WHERE id = ?");
                $stmt->execute([$firstName, $lastName, $email, $hash, $roleId, $status, $id]);
            } else {
                $stmt = $pdo->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, role_id = ?, status = ? WHERE id = ?");
                $stmt->execute([$firstName, $lastName, $email, $roleId, $status, $id]);
            }
            $msg = 'User updated successfully.';
        } else {
            if (empty($password)) {
                $error = 'Password is required for new user.';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (first_name, last_name, email, password_hash, role_id, status) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$firstName, $lastName, $email, $hash, $roleId, $status]);
                $msg = 'User created successfully.';
            }
        }
    }
}

$pdo = getDB();
$users = $pdo->query("
    SELECT u.*, r.name as role_name 
    FROM users u 
    JOIN roles r ON u.role_id = r.id 
    ORDER BY u.created_at DESC
")->fetchAll() ?: [];

$roles = $pdo->query("SELECT * FROM roles ORDER BY id ASC")->fetchAll() ?: [];
?>

<div class="container-fluid max-w-7xl mx-auto">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 font-bold tracking-tight mb-1">System User Accounts</h1>
            <p class="text-secondary text-sm">Manage internal staff user accounts, emails, and assigned roles.</p>
        </div>
        <button class="btn btn-primary d-flex align-items-center gap-2" data-bs-toggle="offcanvas" data-bs-target="#userOffcanvas" onclick="resetUserForm()">
            <i data-lucide="user-plus" class="w-4 h-4"></i> Add User
        </button>
    </div>

    <?php if ($msg): ?>
        <div class="alert alert-success py-2 text-sm mb-3"><?= sanitize($msg) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger py-2 text-sm mb-3"><?= sanitize($error) ?></div>
    <?php endif; ?>

    <div class="card-enterprise">
        <div class="table-responsive">
            <table class="table table-hover align-middle text-sm mb-0">
                <thead class="bg-light">
                    <tr>
                        <th>User Name</th>
                        <th>Email Address</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Created Date</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                        <tr>
                            <td class="fw-bold text-dark"><?= sanitize($u['first_name'] . ' ' . $u['last_name']) ?></td>
                            <td><?= sanitize($u['email']) ?></td>
                            <td><span class="badge badge-info"><?= sanitize($u['role_name']) ?></span></td>
                            <td><?= renderStatusBadge($u['status']) ?></td>
                            <td><?= formatDate($u['created_at']) ?></td>
                            <td class="text-end">
                                <button class="btn btn-sm btn-light border py-1 px-2 text-xs" onclick='editUser(<?= json_encode($u) ?>)'>Edit</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Offcanvas Panel for User Form -->
<div class="offcanvas offcanvas-end" id="userOffcanvas" style="width: 480px;">
    <div class="offcanvas-header border-bottom">
        <h5 class="offcanvas-title font-bold h6" id="userOffcanvasTitle">Add User Account</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
    </div>
    <div class="offcanvas-body">
        <form method="POST" action="">
            <input type="hidden" name="action" value="save_user">
            <input type="hidden" name="user_id" id="user_id" value="">

            <div class="row">
                <div class="col-6 mb-3">
                    <label class="form-label text-xs fw-bold text-secondary">First Name *</label>
                    <input type="text" name="first_name" id="first_name" class="form-control" required>
                </div>
                <div class="col-6 mb-3">
                    <label class="form-label text-xs fw-bold text-secondary">Last Name *</label>
                    <input type="text" name="last_name" id="last_name" class="form-control" required>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label text-xs fw-bold text-secondary">Email Address *</label>
                <input type="email" name="email" id="user_email" class="form-control" required>
            </div>

            <div class="mb-3">
                <label class="form-label text-xs fw-bold text-secondary">Password <span class="text-muted fw-normal">(Leave blank if unchanged)</span></label>
                <input type="password" name="password" id="user_password" class="form-control">
            </div>

            <div class="mb-3">
                <label class="form-label text-xs fw-bold text-secondary">Assigned Role *</label>
                <select name="role_id" id="user_role_id" class="form-select" required>
                    <?php foreach ($roles as $r): ?>
                        <option value="<?= $r['id'] ?>"><?= sanitize($r['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label text-xs fw-bold text-secondary">Status</label>
                <select name="status" id="user_status" class="form-select">
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>

            <button type="submit" class="btn btn-primary w-100 py-2 font-semibold">Save User</button>
        </form>
    </div>
</div>

<script>
function resetUserForm() {
    document.getElementById('userOffcanvasTitle').innerText = 'Add User Account';
    document.getElementById('user_id').value = '';
    document.getElementById('first_name').value = '';
    document.getElementById('last_name').value = '';
    document.getElementById('user_email').value = '';
    document.getElementById('user_password').value = '';
    document.getElementById('user_status').value = 'active';
}

function editUser(u) {
    document.getElementById('userOffcanvasTitle').innerText = 'Edit User Account';
    document.getElementById('user_id').value = u.id;
    document.getElementById('first_name').value = u.first_name;
    document.getElementById('last_name').value = u.last_name;
    document.getElementById('user_email').value = u.email;
    document.getElementById('user_password').value = '';
    document.getElementById('user_role_id').value = u.role_id;
    document.getElementById('user_status').value = u.status || 'active';
    
    const bsOffcanvas = new bootstrap.Offcanvas(document.getElementById('userOffcanvas'));
    bsOffcanvas.show();
}
</script>

<?php require_once __DIR__ . '/../../components/footer.php'; ?>
