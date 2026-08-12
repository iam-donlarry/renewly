<?php
// cron/daily_runner.php - Daily Cron Job for Automated Idempotent Reminders & Expirations
require_once __DIR__ . '/../includes/bootstrap.php';

echo "[" . date('Y-m-d H:i:s') . "] Starting Renewly Daily Reminder Scan...\n";

try {
    $logs = ReminderEngine::runDailyScan();
    echo "Completed daily scan cleanly.\n";
    foreach ($logs as $log) {
        echo " - {$log}\n";
    }
} catch (Exception $e) {
    echo "CRON ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
