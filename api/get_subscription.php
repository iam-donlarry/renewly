<?php
// api/get_subscription.php
header('Content-Type: application/json');
require_once '../database/db.php';

if (!isset($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'ID required']);
    exit;
}

try {
    $id = $_GET['id'];

    // 1. Fetch Main Subscription Record
    $stmt = $pdo->prepare("
        SELECT s.*, o.name as org_name, v.name as vendor_name 
        FROM subscriptions s
        JOIN organizations o ON s.organization_id = o.id
        LEFT JOIN vendors v ON s.vendor_id = v.id
        WHERE s.id = ?
    ");
    $stmt->execute([$id]);
    $subscription = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$subscription) {
        http_response_code(404);
        echo json_encode(['error' => 'Subscription not found']);
        exit;
    }

    // 2. Fetch Items
    $stmtItems = $pdo->prepare("
        SELECT si.*, p.name as product_name, p.price_model
        FROM subscription_items si
        JOIN products p ON si.product_id = p.id
        WHERE si.subscription_id = ?
    ");
    $stmtItems->execute([$id]);
    $items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

    // 3. Fetch Payments
    $stmtPayments = $pdo->prepare("SELECT * FROM subscription_payments WHERE subscription_id = ? ORDER BY due_date ASC");
    $stmtPayments->execute([$id]);
    $payments = $stmtPayments->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'subscription' => $subscription,
        'items' => $items,
        'payments' => $payments
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
