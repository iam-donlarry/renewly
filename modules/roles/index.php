<?php
// modules/roles/index.php - Roles & Permissions Matrix Management
$pageTitle = 'Roles & Permissions Matrix';
require_once __DIR__ . '/../../components/header.php';
RBAC::require('roles.manage');

$pdo = getDB();
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $roleId = (int)($_POST['role_id'] ?? 0);
    $selectedPermissions = $_POST['permission_ids'] ?? [];

    if ($roleId > 0 && $roleId !== 1) { // Super Admin permissions are immutable
        $pdo->beginTransaction();
        try {
            $pdo->prepare("DELETE FROM role_permissions WHERE role_id = ?")->execute([$roleId]);
            $stmtIns = $pdo->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)");
            foreach ($selectedPermissions as $pid) {
                $stmtIns->execute([$roleId, (int)$pid]);
            }
            $pdo->commit();
            $msg = 'Role permissions matrix updated successfully.';
        } catch (Exception $e) {
            $pdo->rollBack();
            $msg = 'Failed to update role permissions: ' . $e->getMessage();
        }
    }
}

// Fetch Roles & User Counts
$roles = $pdo->query("
    SELECT r.*, COUNT(u.id) as user_count 
    FROM roles r 
    LEFT JOIN users u ON r.id = u.role_id 
    GROUP BY r.id 
    ORDER BY r.id ASC
")->fetchAll() ?: [];

// Fetch Permissions grouped by Module
$permissions = $pdo->query("SELECT * FROM permissions ORDER BY module ASC, permission_key ASC")->fetchAll() ?: [];
$categorizedPermissions = [];
foreach ($permissions as $p) {
    $mod = ucfirst($p['module'] ?? 'General');
    $categorizedPermissions[$mod][] = $p;
}

// Map existing role permissions
$rolePermMap = [];
$rpRows = $pdo->query("SELECT role_id, permission_id FROM role_permissions")->fetchAll() ?: [];
foreach ($rpRows as $rp) {
    $rolePermMap[$rp['role_id']][] = (int)$rp['permission_id'];
}
?>

<div class="container-fluid max-w-7xl mx-auto">
    <div class="mb-4">
        <h1 class="h3 font-bold tracking-tight mb-1">Roles & Permissions Matrix</h1>
        <p class="text-secondary text-sm">Manage user access control roles and configure granular permission matrices.</p>
    </div>

    <?php if ($msg): ?>
        <div class="alert alert-success py-2 text-sm mb-3"><?= sanitize($msg) ?></div>
    <?php endif; ?>

    <!-- Roles Listing Table -->
    <div class="card-enterprise">
        <div class="table-responsive">
            <table class="table table-hover align-middle text-sm mb-0">
                <thead class="bg-light">
                    <tr>
                        <th>Role Name</th>
                        <th>Description</th>
                        <th>Assigned Users</th>
                        <th>Permissions Access</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($roles as $r): ?>
                        <?php 
                            $permCount = isset($rolePermMap[$r['id']]) ? count($rolePermMap[$r['id']]) : 0;
                            $isSuperAdmin = ($r['name'] === 'Super Admin');
                        ?>
                        <tr>
                            <td class="fw-bold text-dark">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="user-avatar-placeholder text-xs" style="width: 32px; height: 32px;">
                                        <?= strtoupper(substr($r['name'], 0, 2)) ?>
                                    </div>
                                    <span><?= sanitize($r['name']) ?></span>
                                </div>
                            </td>
                            <td class="text-secondary"><?= sanitize($r['description']) ?></td>
                            <td>
                                <span class="badge badge-secondary"><?= $r['user_count'] ?> User(s)</span>
                            </td>
                            <td>
                                <?php if ($isSuperAdmin): ?>
                                    <span class="badge badge-success">Full Access (*)</span>
                                <?php else: ?>
                                    <span class="badge badge-info"><?= $permCount ?> Permission(s)</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <button type="button" class="btn btn-sm btn-outline-primary font-semibold" 
                                    onclick='openRoleOffcanvas(<?= json_encode($r) ?>, <?= json_encode($rolePermMap[$r['id']] ?? []) ?>)'>
                                    <i data-lucide="shield-config" class="w-4 h-4 me-1"></i> Configure Matrix
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Half-Screen Offcanvas Drawer for Permissions Matrix -->
<div class="offcanvas offcanvas-end" id="roleOffcanvas" style="width: 50vw; min-width: 520px;">
    <div class="offcanvas-header border-bottom bg-light">
        <div>
            <h5 class="offcanvas-title font-bold h6" id="roleOffcanvasTitle">Configure Permissions Matrix</h5>
            <span class="text-xs text-muted" id="roleOffcanvasSubtitle">Role Access Control Settings</span>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
    </div>
    
    <div class="offcanvas-body p-4">
        <form method="POST" action="" id="rolePermForm">
            <input type="hidden" name="role_id" id="offcanvas_role_id" value="">

            <div id="superAdminNotice" class="alert alert-info d-none mb-3">
                <i data-lucide="shield-check" class="w-5 h-5 me-1"></i>
                Super Admin role automatically possesses all system permissions (<code>*</code>). Permissions matrix is non-restrictable for Super Admin.
            </div>

            <div id="permissionCategoriesContainer">
                <?php foreach ($categorizedPermissions as $moduleName => $permList): ?>
                    <div class="border rounded-3 p-3 mb-3 bg-white shadow-sm category-block">
                        <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-3">
                            <h6 class="fw-bold text-dark mb-0 d-flex align-items-center gap-2">
                                <i data-lucide="folder-key" class="w-4 h-4 text-primary"></i>
                                <?= sanitize($moduleName) ?> Permissions
                            </h6>
                            <button type="button" class="btn btn-xs btn-link text-primary p-0 text-xs font-semibold" onclick="toggleCategoryChecks(this)">Toggle All</button>
                        </div>

                        <div class="row g-2">
                            <?php foreach ($permList as $p): ?>
                                <div class="col-md-6">
                                    <div class="form-check p-2 border rounded-2 bg-light hover-shadow transition">
                                        <input class="form-check-input perm-checkbox me-2" type="checkbox" name="permission_ids[]" value="<?= $p['id'] ?>" id="perm_<?= $p['id'] ?>">
                                        <label class="form-check-label w-100 cursor-pointer" for="perm_<?= $p['id'] ?>">
                                            <div class="fw-bold text-dark text-xs mb-0.5"><code><?= sanitize($p['permission_key']) ?></code></div>
                                            <div class="text-muted text-xs"><?= sanitize($p['description']) ?></div>
                                        </label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="offcanvas-footer border-top pt-3 mt-4 d-flex justify-content-end gap-2">
                <button type="button" class="btn btn-light border px-4 font-semibold" data-bs-dismiss="offcanvas">Cancel</button>
                <button type="submit" class="btn btn-primary px-4 font-semibold" id="saveRolePermBtn">Save Permissions Matrix</button>
            </div>
        </form>
    </div>
</div>

<script>
function openRoleOffcanvas(role, assignedPermIds) {
    document.getElementById('roleOffcanvasTitle').innerText = role.name + ' — Permissions Matrix';
    document.getElementById('roleOffcanvasSubtitle').innerText = role.description || 'Configure role access boundaries';
    document.getElementById('offcanvas_role_id').value = role.id;

    const isSuperAdmin = (role.name === 'Super Admin');
    const notice = document.getElementById('superAdminNotice');
    const saveBtn = document.getElementById('saveRolePermBtn');
    const container = document.getElementById('permissionCategoriesContainer');

    if (isSuperAdmin) {
        notice.classList.remove('d-none');
        saveBtn.disabled = true;
        container.style.opacity = '0.5';
        container.style.pointerEvents = 'none';
    } else {
        notice.classList.add('d-none');
        saveBtn.disabled = false;
        container.style.opacity = '1';
        container.style.pointerEvents = 'auto';
    }

    // Reset all checkboxes
    document.querySelectorAll('.perm-checkbox').forEach(cb => {
        cb.checked = assignedPermIds.includes(parseInt(cb.value));
    });

    const bsOffcanvas = new bootstrap.Offcanvas(document.getElementById('roleOffcanvas'));
    bsOffcanvas.show();
}

function toggleCategoryChecks(btn) {
    const categoryBlock = btn.closest('.category-block');
    const checkboxes = categoryBlock.querySelectorAll('.perm-checkbox');
    const allChecked = Array.from(checkboxes).every(cb => cb.checked);
    checkboxes.forEach(cb => cb.checked = !allChecked);
}
</script>

<?php require_once __DIR__ . '/../../components/footer.php'; ?>
