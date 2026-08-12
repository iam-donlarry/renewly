<?php
// modules/settings/index.php - System Configuration Settings
$pageTitle = 'System Settings';
require_once __DIR__ . '/../../components/header.php';
RBAC::require('settings.manage');

$pdo = getDB();
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $updatableKeys = ['global_exchange_rate', 'default_currency', 'default_grace_period'];
    foreach ($updatableKeys as $key) {
        if (isset($_POST[$key])) {
            $val = trim($_POST[$key]);
            $stmt = $pdo->prepare("
                INSERT INTO app_settings (setting_key, setting_value) 
                VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
            ");
            $stmt->execute([$key, $val]);
        }
    }
    $msg = 'System settings updated successfully.';
}

// Fetch current settings
$settingsRows = $pdo->query("SELECT * FROM app_settings")->fetchAll() ?: [];
$settings = [];
foreach ($settingsRows as $sr) {
    $settings[$sr['setting_key']] = $sr['setting_value'];
}
?>

<div class="container-fluid max-w-4xl mx-auto">
    <div class="mb-4">
        <h1 class="h3 font-bold tracking-tight mb-1">System Preferences & Settings</h1>
        <p class="text-secondary text-sm">Configure baseline exchange rates, default currency, and grace periods.</p>
    </div>

    <?php if ($msg): ?>
        <div class="alert alert-success py-2 text-sm mb-3"><?= sanitize($msg) ?></div>
    <?php endif; ?>

    <div class="card-enterprise">
        <form method="POST" action="">
            <div class="mb-4">
                <label class="form-label font-semibold text-dark">Global Exchange Rate Baseline (USD to NGN)</label>
                <div class="input-group" style="max-width: 320px;">
                    <span class="input-group-text">1 USD =</span>
                    <input type="number" step="0.0001" name="global_exchange_rate" class="form-control" value="<?= sanitize($settings['global_exchange_rate'] ?? '1550.00') ?>" required>
                    <span class="input-group-text">NGN</span>
                </div>
                <div class="form-text text-xs">Used as baseline default when converting USD contracts to local NGN reporting.</div>
            </div>

            <div class="mb-4">
                <label class="form-label font-semibold text-dark">Default Grace Period (Days)</label>
                <div class="input-group" style="max-width: 240px;">
                    <input type="number" name="default_grace_period" class="form-control" value="<?= sanitize($settings['default_grace_period'] ?? '7') ?>" required>
                    <span class="input-group-text">Days</span>
                </div>
                <div class="form-text text-xs">Days allowed after expiry before contract is automatically marked as Lapsed.</div>
            </div>

            <button type="submit" class="btn btn-primary px-4 font-semibold">Save Settings</button>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../../components/footer.php'; ?>
