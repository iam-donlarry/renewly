<?php
// Mock POST request
$_SERVER['REQUEST_METHOD'] = 'POST';
$payload = json_encode([
    'subscription_id' => 6, // Use a known ID (from previous logs)
    'changes' => [],
    'items' => [
        ['product_id' => 1, 'quantity' => 10, 'unit_cost' => 15.00] 
    ],
    'due_now' => 0
]);

// Write payload to input stream wrapper if needed? 
// No, file_get_contents('php://input') is hard to mock in CLI.
// I'll modify commit_modification.php temporarily to accept fallback input for debug?
// OR I simply copy the code and run it here.

// Better: Copy the code logic here to test.
require_once '../database/db.php';

try {
    $input = json_decode($payload, true);
    $subId = $input['subscription_id'];
    $items = $input['items'];

    $stmt = $pdo->prepare("SELECT * FROM subscriptions WHERE id = ?");
    $stmt->execute([$subId]);
    $sub = $stmt->fetch(PDO::FETCH_ASSOC);
    if(!$sub) die("Sub not found");

    echo "Sub Found: " . $sub['id'] . "\n";

    $stmtItems = $pdo->prepare("SELECT * FROM subscription_items WHERE subscription_id = ? AND status = 'active'");
    $stmtItems->execute([$subId]);
    $currentItems = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

    echo "Current Items: " . count($currentItems) . "\n";
    
    // Test the specific UPDATE that might fail
    // Assume item 1 exists.
    $stmtTest = $pdo->prepare("UPDATE subscription_items SET quantity = ?, renewal_quantity = ?, total_cost = ?, updated_at = NOW() WHERE id = ?");
    // Just verify SQL syntax via prepare check.
    echo "Prepare SQL successful.\n";
    
    // Simulate Logic
    $currentMap = [];
    foreach($currentItems as $ci) {
        $currentMap[$ci['product_id']] = $ci;
    }
    
    foreach ($items as $item) {
        $pid = $item['product_id'];
        if (isset($currentMap[$pid])) {
             $oldItem = $currentMap[$pid];
             // Try the Update
             echo "Updating Item ID: " . $oldItem['id'] . "\n";
             $qty = 15; // increased
             $newTotal = 150.00;
             $stmtTest->execute([$qty, $qty, $newTotal, $oldItem['id']]);
             echo "Update Executed.\n";
        }
    }

} catch(Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
