<?php
// api/fetch_expiring.php
header('Content-Type: application/json');

require_once '../database/db.php';

try {
    // 1. Calculate KPI Stats
    // 1. Calculate KPI Stats (Revenue by Currency)
    // 1. Calculate KPI Stats (Revenue by Currency & Cycle)
    $stmtRevenue = $pdo->query("
        SELECT 
            currency,
            billing_cycle,
            SUM(cost) as total_value
        FROM subscriptions 
        WHERE status = 'active'
        GROUP BY currency, billing_cycle
        ORDER BY currency, FIELD(billing_cycle, 'monthly', 'quarterly', 'yearly')
    ");
    // Fetch all rows to process in PHP
    $raw_revenue = $stmtRevenue->fetchAll(PDO::FETCH_ASSOC);
    
    // Re-structure: ['USD' => ['monthly' => 500, 'yearly' => 1000], 'NGN' => ...]
    $revenue_breakdown = [];
    foreach ($raw_revenue as $row) {
        $revenue_breakdown[$row['currency']][$row['billing_cycle']] = $row['total_value'];
    }

    // 1b. Other Stats
    $stmtStats = $pdo->query("
        SELECT 
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_count,
            SUM(CASE WHEN expiry_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as high_alert_count
        FROM subscriptions 
    ");
    $stats = $stmtStats->fetch();

    // 1b. Calculate Active Companies
    $stmtOrgs = $pdo->query("SELECT COUNT(*) as count FROM organizations");
    $orgStats = $stmtOrgs->fetch();

    // 2. Fetch Subscriptions (Expiring Soon or Recent)
    // Removed s.plan_name as it likely doesn't exist in the current schema
    $stmtSubs = $pdo->query("
        SELECT 
            s.id, 
            COALESCE(p.name, 'Direct Subscription') as plan_name,
            v.name as vendor_name, 
            o.name as org_name,
            s.expiry_date, 
            s.status
        FROM subscriptions s
        JOIN vendors v ON s.vendor_id = v.id
        JOIN organizations o ON s.organization_id = o.id
        LEFT JOIN products p ON s.product_id = p.id
        WHERE s.status IN ('active', 'expiring')
        ORDER BY s.expiry_date ASC
        LIMIT 10
    ");
    $rows = $stmtSubs->fetchAll();

    echo json_encode([
        'stats' => [
            'revenue_breakdown' => $revenue_breakdown, // Pass raw map: {'USD': 123, 'NGN': 456}
            'total_spend' => 'Deprecated', // Keep for safety or remove
            'active_count' => (int)($stats['active_count'] ?? 0),
            'high_alert_count' => (int)($stats['high_alert_count'] ?? 0),
            'active_companies' => (int)($orgStats['count'] ?? 0) 
        ],
        'subscriptions' => $rows
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>
