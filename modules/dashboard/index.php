<?php
// modules/dashboard/index.php - Operational & Renewal Forecasting Dashboard
$pageTitle = 'Dashboard Overview';
require_once __DIR__ . '/../../components/header.php';

$pdo = getDB();

// 1. KPI Counts
$activeContracts = (int)$pdo->query("SELECT COUNT(*) FROM contracts WHERE status = 'active'")->fetchColumn();
$expiringContracts = (int)$pdo->query("SELECT COUNT(*) FROM contracts WHERE DATEDIFF(expiry_date, CURRENT_DATE) BETWEEN 0 AND 30 AND status IN ('active', 'expiring')")->fetchColumn();
$activeClients = (int)$pdo->query("SELECT COUNT(*) FROM clients WHERE status = 'active'")->fetchColumn();
$overduePayments = (int)$pdo->query("SELECT COUNT(*) FROM payment_schedules WHERE status = 'overdue' OR (status = 'pending' AND due_date < CURRENT_DATE)")->fetchColumn();

// 2. Revenue Totals
$revenueSum = (float)$pdo->query("SELECT SUM(total_contract_value) FROM contracts WHERE status = 'active'")->fetchColumn();

// 3. Renewal Pipeline Overview (Next 60 Days with Forecasting Breakdown)
$renewalPipeline = RenewalEngine::getPipeline(['days' => 60]);

// 4. Recent Payment Obligations
$upcomingPayments = $pdo->query("
    SELECT ps.*, c.contract_reference, cl.company_name
    FROM payment_schedules ps
    JOIN contracts c ON ps.contract_id = c.id
    JOIN clients cl ON c.client_id = cl.id
    WHERE ps.status IN ('pending', 'due', 'overdue')
    ORDER BY ps.due_date ASC
    LIMIT 5
")->fetchAll() ?: [];
?>

<div class="container-fluid max-w-7xl mx-auto">
    <!-- Welcome Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 font-bold tracking-tight mb-1">Operational & Renewal Dashboard</h1>
            <p class="text-secondary text-sm">Advance visibility for client subscription renewals and upcoming obligations.</p>
        </div>
        <div class="d-flex gap-2">
            <?php if (hasPermission('contracts.create')): ?>
            <a href="<?= APP_URL ?>/contracts/create" class="btn btn-primary d-flex align-items-center gap-2">
                <i data-lucide="plus" class="w-4 h-4"></i> New Contract
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Top Connected KPI Bar Card with Watermark Fading Icons -->
    <div class="kpi-bar-container mb-4">
        <div class="row g-0">
            <div class="col-md-3">
                <div class="kpi-bar-item">
                    <div class="kpi-bar-title">Active ARR / Value</div>
                    <div class="kpi-bar-value text-dark"><?= formatCurrency($revenueSum) ?></div>
                    <div class="kpi-bar-subtext">Active annual contract value</div>
                    <i data-lucide="dollar-sign" class="kpi-fading-icon"></i>
                </div>
            </div>

            <div class="col-md-3">
                <div class="kpi-bar-item">
                    <div class="kpi-bar-title">Active Client Companies</div>
                    <div class="kpi-bar-value text-dark"><?= $activeClients ?></div>
                    <div class="kpi-bar-subtext">Total registered client accounts</div>
                    <i data-lucide="building-2" class="kpi-fading-icon"></i>
                </div>
            </div>

            <div class="col-md-3">
                <div class="kpi-bar-item">
                    <div class="kpi-bar-title">Expiring (Next 30 Days)</div>
                    <div class="kpi-bar-value text-warning"><?= $expiringContracts ?></div>
                    <div class="kpi-bar-subtext">Requires early renewal outreach</div>
                    <i data-lucide="calendar" class="kpi-fading-icon"></i>
                </div>
            </div>

            <div class="col-md-3">
                <div class="kpi-bar-item">
                    <div class="kpi-bar-title">Overdue Payment Schedule</div>
                    <div class="kpi-bar-value text-danger"><?= $overduePayments ?></div>
                    <div class="kpi-bar-subtext">Pending installment collections</div>
                    <i data-lucide="alert-circle" class="kpi-fading-icon"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Renewal Forecasting Section (Feature Core) -->
    <div class="card-enterprise mb-4">
        <div class="d-flex justify-content-between align-items-center border-bottom pb-3 mb-3">
            <div>
                <h5 class="fw-bold h6 mb-1 d-flex align-items-center gap-2">
                    <i data-lucide="rotate-cw" class="w-5 h-5 text-primary"></i>
                    Renewal Forecast Pipeline (Next 60 Days)
                </h5>
                <p class="text-muted text-xs mb-0">Detailed action-oriented foresight factoring pending seat reductions and estimated renewal values.</p>
            </div>
            <a href="<?= APP_URL ?>/renewals" class="btn btn-sm btn-outline-primary fw-semibold">View Full Pipeline</a>
        </div>

        <?php if (empty($renewalPipeline)): ?>
            <div class="text-center py-4 text-muted">
                <i data-lucide="check-circle" class="w-8 h-8 text-success mb-2"></i>
                <p class="mb-0">No upcoming renewals in the next 60 days. All contracts are clear!</p>
            </div>
        <?php else: ?>
            <div class="row g-3">
                <?php foreach ($renewalPipeline as $item): ?>
                    <?php 
                        $contractDetails = ContractEngine::getById((int)$item['contract_id']);
                        $itemCount = count($contractDetails['items'] ?? []);
                        $hasPendingReduction = false;
                        $summaryText = "";
                        foreach ($contractDetails['items'] ?? [] as $lineItem) {
                            if (!empty($lineItem['queued_quantity'])) {
                                $hasPendingReduction = true;
                                $summaryText .= "{$lineItem['product_name']}: {$lineItem['current_quantity']} → {$lineItem['queued_quantity']} seats (Queued reduction); ";
                            } else {
                                $summaryText .= "{$lineItem['product_name']}: {$lineItem['current_quantity']} seats; ";
                            }
                        }
                    ?>
                    <div class="col-md-6">
                        <div class="border rounded-3 p-3 bg-white shadow-sm h-100">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <h6 class="fw-bold mb-0 text-dark"><?= sanitize($item['company_name']) ?></h6>
                                    <span class="text-xs text-muted">Contract: <code><?= sanitize($item['contract_reference']) ?></code></span>
                                </div>
                                <span class="badge badge-warning">
                                    <i data-lucide="clock" class="badge-icon"></i>
                                    Expires in <?= $item['days_remaining'] ?> days
                                </span>
                            </div>

                            <div class="my-2 p-2 bg-light rounded text-xs">
                                <div class="fw-semibold text-secondary mb-1">Seats Breakdown:</div>
                                <div class="text-muted"><?= sanitize(rtrim($summaryText, '; ')) ?></div>
                            </div>

                            <div class="d-flex justify-content-between align-items-center text-xs mt-3 pt-2 border-top">
                                <div>
                                    <span class="text-muted">Est. Renewal Value:</span>
                                    <strong class="text-success ms-1"><?= formatCurrency($item['estimated_renewal_value'], $item['currency']) ?></strong>
                                </div>
                                <div>
                                    <span class="text-muted">Account Manager:</span>
                                    <strong class="ms-1"><?= sanitize($item['account_manager_name'] ?? 'Unassigned') ?></strong>
                                </div>
                            </div>

                            <div class="d-flex justify-content-between align-items-center text-xs mt-2">
                                <div class="text-primary font-medium">
                                    <i data-lucide="arrow-right-circle" class="w-3.5 h-3.5 me-1"></i>
                                    Next Action: <?= sanitize($item['next_action'] ?? 'Review contract') ?>
                                </div>
                                <a href="<?= getContractUrl($item['contract_id']) ?>" class="btn btn-sm btn-light border text-xs py-1">View Details</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Bottom Row: Payment Obligations & Quick Actions -->
    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card-enterprise">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold h6 mb-0">Upcoming Payment Obligations</h5>
                    <a href="<?= APP_URL ?>/payments" class="text-xs text-primary font-medium">View All Payments</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle text-sm mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th>Client</th>
                                <th>Contract</th>
                                <th>Due Date</th>
                                <th>Amount</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($upcomingPayments)): ?>
                                <tr><td colspan="5" class="text-center text-muted py-3">No pending payments due.</td></tr>
                            <?php else: ?>
                                <?php foreach ($upcomingPayments as $p): ?>
                                    <tr>
                                        <td class="fw-semibold text-dark"><?= sanitize($p['company_name']) ?></td>
                                        <td><code><?= sanitize($p['contract_reference']) ?></code></td>
                                        <td class="text-muted"><?= formatDate($p['due_date']) ?></td>
                                        <td class="fw-bold"><?= formatCurrency($p['amount'], $p['currency']) ?></td>
                                        <td><?= renderStatusBadge($p['status']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card-enterprise">
                <h5 class="fw-bold h6 mb-3">Quick Operations</h5>
                <div class="d-flex flex-column gap-2">
                    <?php if (hasPermission('contracts.create')): ?>
                    <a href="<?= APP_URL ?>/contracts/create" class="btn btn-light border text-start d-flex align-items-center gap-3 p-3">
                        <i data-lucide="file-plus" class="w-5 h-5 text-primary"></i>
                        <div>
                            <div class="fw-bold text-dark text-sm">Create New Contract</div>
                            <div class="text-xs text-muted">Add multi-item license agreement for client</div>
                        </div>
                    </a>
                    <?php endif; ?>

                    <?php if (hasPermission('clients.manage')): ?>
                    <a href="<?= APP_URL ?>/clients" class="btn btn-light border text-start d-flex align-items-center gap-3 p-3">
                        <i data-lucide="user-plus" class="w-5 h-5 text-primary"></i>
                        <div>
                            <div class="fw-bold text-dark text-sm">Add Client Company</div>
                            <div class="text-xs text-muted">Register a new client company account</div>
                        </div>
                    </a>
                    <?php endif; ?>

                    <?php if (hasPermission('renewals.view')): ?>
                    <a href="<?= APP_URL ?>/renewals" class="btn btn-light border text-start d-flex align-items-center gap-3 p-3">
                        <i data-lucide="rotate-cw" class="w-5 h-5 text-primary"></i>
                        <div>
                            <div class="fw-bold text-dark text-sm">Manage Renewals</div>
                            <div class="text-xs text-muted">Review 30–60 day advance renewal pipeline</div>
                        </div>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../components/footer.php'; ?>
