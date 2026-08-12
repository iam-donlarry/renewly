<?php
// cron/daily_check.php
require_once __DIR__ . '/../database/db.php';

echo "Starting Daily Subscription Check...\n";

try {
    $today = date('Y-m-d');

    // 1. Mark 'lapsed'
    $sqlExpire = "
        UPDATE subscriptions 
        SET status = 'lapsed', updated_at = NOW()
        WHERE status IN ('active', 'expiring')
          AND DATE_ADD(expiry_date, INTERVAL grace_period_days DAY) < :today
    ";
    $stmtExpire = $pdo->prepare($sqlExpire);
    $stmtExpire->execute([':today' => $today]);
    echo "Marked " . $stmtExpire->rowCount() . " subscriptions as LAPSED.\n";

    // 2. Reminders (Cycle-Aware)
    // Define intervals (days before expiry) for each cycle type
    $reminder_config = [
        'monthly'   => [7, 3, 1],        // Compact cycle: Warn at 1 week, 3 days, 1 day
        'quarterly' => [14, 7, 3, 1],    // Standard cycle: Warn at 2 weeks, etc.
        'yearly'    => [30, 14, 7, 1]    // Long cycle: Warn at 1 month, etc.
    ];

    foreach ($reminder_config as $cycle => $intervals) {
        foreach ($intervals as $days) {
            $targetDate = date('Y-m-d', strtotime("+$days days"));
            
            // Allow for singular/plural comparison or explicit string matching
            // Using prepared statements for security
            $stmtRemind = $pdo->prepare("
                SELECT s.id, v.name as vendor_name, p.name as product_name, o.contact_email 
                FROM subscriptions s
                JOIN vendors v ON s.vendor_id = v.id
                JOIN organizations o ON s.organization_id = o.id
                LEFT JOIN products p ON s.product_id = p.id
                WHERE s.expiry_date = :target_date 
                  AND s.status = 'active'
                  AND s.billing_cycle = :cycle
            ");
            
            $stmtRemind->execute([
                ':target_date' => $targetDate,
                ':cycle' => $cycle
            ]);
            
            $subs = $stmtRemind->fetchAll();

            foreach ($subs as $sub) {
                // Log the reminder (In a real app, this sends an email)
                $prodName = $sub['product_name'] ?? $sub['vendor_name'];
                $pdo->prepare("INSERT INTO reminders_log (subscription_id, reminder_type, recipient, status) VALUES (?, ?, ?, 'sent')")
                    ->execute([$sub['id'], "{$days}_days_before", $sub['contact_email']]);
                
                echo "Logged {$days}-day reminder for {$prodName} (Cycle: {$cycle})\n";
            }
        }
    }
    
    // 3. Mark 'expiring'
    $pdo->query("UPDATE subscriptions SET status = 'expiring' WHERE status = 'active' AND expiry_date <= DATE_ADD(NOW(), INTERVAL 30 DAY)");

    echo "Done.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
