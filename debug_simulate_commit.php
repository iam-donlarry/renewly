<?php
// debug_simulate_commit.php
require_once 'database/db.php';

// disable auth checks or session for this CLI/test script
// define inputs
$subId = 6;
$productId = 1; // From previous debug_sub output
$newQty = 20; // Increase from 15 to 20
$unitCost = 15.00;

echo "--- BEFORE UPDATE ---\n";
print_r($pdo->query("SELECT * FROM subscription_items WHERE subscription_id=$subId AND product_id=$productId")->fetch(PDO::FETCH_ASSOC));

// payload
$payload = json_encode([
    'subscription_id' => $subId,
    'items' => [
        [
            'product_id' => $productId,
            'quantity' => $newQty,
            'unit_cost' => $unitCost
        ],
        // Include other items as unchanged? 
        // Logic loops through input items. If I omit them, logic ignores them? Or treats as remove?
        // Logic currently: "foreach ($items as $item)".
        // It does NOT delete missing items. It only updates passed items.
        // So passing just one item is fine for this test.
    ]
]);

// Prepare context for commit_modification logic
// We can't include the file directly because it expects php://input.
// Instead, let's copy the logic or use curl.
// cURL is best to test the actual endpoint.

$ch = curl_init('http://localhost/Renewly/api/commit_modification.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

$response = curl_exec($ch);
curl_close($ch);

echo "\n--- RESPONSE ---\n";
echo $response;

echo "\n--- AFTER UPDATE ---\n";
print_r($pdo->query("SELECT * FROM subscription_items WHERE subscription_id=$subId AND product_id=$productId")->fetch(PDO::FETCH_ASSOC));
