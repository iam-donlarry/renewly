<?php
// modules/reports/audit.php - System Audit Activity Logs
$pageTitle = 'Audit Activity Logs';
require_once __DIR__ . '/../../components/header.php';
RBAC::require('reports.view');

$pdo = getDB();
$logs = $pdo->query("
    SELECT a.*, u.first_name, u.last_name, u.email 
    FROM audit_logs a 
    LEFT JOIN users u ON a.user_id = u.id 
    ORDER BY a.created_at DESC 
    LIMIT 100
")->fetchAll() ?: [];
?>

<div class="container-fluid max-w-7xl mx-auto">
    <div class="mb-4">
        <h1 class="h3 font-bold tracking-tight mb-1">System Audit Activity Logs</h1>
        <p class="text-secondary text-sm">Audit trail of system transactions, contract edits, and user operations with state diffs.</p>
    </div>

    <div class="card-enterprise">
        <div class="table-responsive">
            <table class="table table-hover align-middle text-sm mb-0">
                <thead class="bg-light">
                    <tr>
                        <th>Timestamp</th>
                        <th>User</th>
                        <th>Action</th>
                        <th>Target Entity</th>
                        <th>State Diffs & Metadata</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr><td colspan="5" class="text-center py-4 text-muted">No audit activity logged yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($logs as $l): ?>
                            <tr>
                                <td class="text-secondary text-xs" style="white-space: nowrap;">
                                    <?= formatDate($l['created_at'], 'M d, Y H:i:s') ?>
                                </td>
                                <td class="fw-bold text-dark">
                                    <?= sanitize(($l['first_name'] ? $l['first_name'] . ' ' . $l['last_name'] : 'System Automated')) ?>
                                </td>
                                <td>
                                    <span class="badge badge-info"><?= sanitize($l['action']) ?></span>
                                </td>
                                <td>
                                    <code><?= sanitize($l['entity_type']) ?>#<?= $l['entity_id'] ?></code>
                                </td>
                                <td class="text-xs text-secondary font-monospace" style="max-width: 380px; overflow-x: auto;">
                                    <?php if ($l['state_after']): ?>
                                        <?= sanitize($l['state_after']) ?>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
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

<?php require_once __DIR__ . '/../../components/footer.php'; ?>
