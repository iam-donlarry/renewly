<?php
// modules/renewals/index.php - Dedicated Renewal Pipeline & Forecasting View
$pageTitle = 'Renewal Pipeline';
require_once __DIR__ . '/../../components/header.php';
RBAC::require('renewals.view');

$stageFilter = $_GET['stage'] ?? '';
$daysFilter = isset($_GET['days']) ? (int)$_GET['days'] : 60;

$filters = [];
if (!empty($stageFilter)) $filters['stage'] = $stageFilter;
if ($daysFilter > 0) $filters['days'] = $daysFilter;

$pipeline = RenewalEngine::getPipeline($filters);

$stages = [
    'upcoming'                 => 'Upcoming Scan',
    'preparation'              => 'Renewal Preparation',
    'proposal_sent'            => 'Proposal Sent',
    'awaiting_client_approval' => 'Awaiting Client Approval',
    'approved'                 => 'Approved',
    'awaiting_payment'         => 'Awaiting Payment',
    'payment_received'         => 'Payment Received',
    'renewed'                  => 'Renewed',
    'churned'                  => 'Churned / Cancelled'
];
?>

<div class="container-fluid max-w-7xl mx-auto">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 font-bold tracking-tight mb-1">Renewal Pipeline & Forecasting</h1>
            <p class="text-secondary text-sm">30–60 day advance workflow center for proactive client renewal management.</p>
        </div>
        <div class="d-flex gap-2">
            <div class="dropdown">
                <button class="btn btn-light border dropdown-toggle text-sm font-semibold" data-bs-toggle="dropdown">
                    Timeline: Next <?= $daysFilter ?> Days
                </button>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="<?= APP_URL ?>/renewals?days=30">Next 30 Days</a></li>
                    <li><a class="dropdown-item" href="<?= APP_URL ?>/renewals?days=60">Next 60 Days</a></li>
                    <li><a class="dropdown-item" href="<?= APP_URL ?>/renewals?days=90">Next 90 Days</a></li>
                </ul>
            </div>
        </div>
    </div>

    <div class="card-enterprise">
        <div class="table-responsive">
            <table class="table table-hover align-middle text-sm mb-0">
                <thead class="bg-light">
                    <tr>
                        <th>Client</th>
                        <th>Contract</th>
                        <th>Expiry Date</th>
                        <th>Current Value</th>
                        <th>Est. Renewal Value</th>
                        <th>Account Manager</th>
                        <th>Renewal Stage</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($pipeline)): ?>
                        <tr><td colspan="8" class="text-center py-4 text-muted">No renewals match the current filter.</td></tr>
                    <?php else: ?>
                        <?php foreach ($pipeline as $r): ?>
                            <tr>
                                <td class="fw-bold text-dark"><?= sanitize($r['company_name']) ?></td>
                                <td><code><?= sanitize($r['contract_reference']) ?></code></td>
                                <td>
                                    <?= formatDate($r['expiry_date']) ?>
                                    <div class="text-xs text-muted"><?= $r['days_remaining'] ?> days remaining</div>
                                </td>
                                <td><?= formatCurrency($r['current_contract_value'], $r['currency']) ?></td>
                                <td class="fw-bold text-success"><?= formatCurrency($r['estimated_renewal_value'], $r['currency']) ?></td>
                                <td><?= sanitize($r['account_manager_name'] ?? 'Unassigned') ?></td>
                                <td>
                                    <select class="form-select form-select-sm text-xs font-semibold" 
                                            onchange="updateRenewalStage(<?= $r['id'] ?>, this.value)"
                                            <?= !hasPermission('renewals.manage') ? 'disabled' : '' ?>>
                                        <?php foreach ($stages as $key => $label): ?>
                                            <option value="<?= $key ?>" <?= $r['renewal_stage'] === $key ? 'selected' : '' ?>><?= $label ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td class="text-end">
                                    <a href="<?= getContractUrl($r['contract_id']) ?>" class="btn btn-sm btn-light border py-1 px-2.5 text-xs font-medium">
                                        Open Contract
                                    </a>
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
async function updateRenewalStage(renewalId, newStage) {
    try {
        const res = await fetchAPI('<?= APP_URL ?>/ajax/renewals/update_stage.php', {
            method: 'POST',
            body: JSON.stringify({ renewal_id: renewalId, stage: newStage })
        });
        if (res.success) {
            showToast('Renewal stage updated.', 'success');
        }
    } catch (e) {}
}
</script>

<?php require_once __DIR__ . '/../../components/footer.php'; ?>
