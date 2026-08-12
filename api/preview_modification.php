<?php
require_once '../database/db.php';


// Disable error display to prevent HTML injection into JSON
ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json');

// Buffer output to catch unexpected warnings
ob_start();

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) throw new Exception('Invalid JSON input');

    $subId = $input['subscription_id'];
    $newItems = $input['items']; // Array of {product_id, quantity, unit_cost}

    // 1. Fetch Current Subscription
    $stmt = $pdo->prepare("SELECT * FROM subscriptions WHERE id = ?");
    $stmt->execute([$subId]);
    $sub = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$sub) throw new Exception('Subscription not found');

    $expiryDate = new DateTime($sub['expiry_date']);
    $today = new DateTime();
    
    // If expired, no changes allowed (or treated as new sub)
    if ($today > $expiryDate) {
        throw new Exception('Cannot modify expired subscription');
    }

    // 2. Determine Proration Limit (Target Date) and Cycle Days
    // This logic replaces the old simple prorate-to-end logic
    $stmtPay = $pdo->prepare("SELECT MIN(due_date) as next_due FROM subscription_payments WHERE subscription_id = ? AND status = 'pending' AND due_date >= CURRENT_DATE");
    $stmtPay->execute([$subId]);
    $nextPayment = $stmtPay->fetch(PDO::FETCH_ASSOC);

    if ($nextPayment && $nextPayment['next_due']) {
        // Monthly/Quarterly Case: Prorate until next bill
        $targetDate = new DateTime($nextPayment['next_due']);
        $cycle = $sub['billing_cycle'];
        $cycleDays = ($cycle === 'monthly') ? 30 : (($cycle === 'quarterly') ? 90 : 365);
    } else {
        // Paid Upfront / End of Term
        $targetDate = new DateTime($sub['expiry_date']);
        $cycleDays = 365; // Fallback or calculated from Start->Expiry
        
        $startDate = new DateTime($sub['start_date']);
        $termDays = $startDate->diff($targetDate)->days;
        $cycleDays = max(1, $termDays);
    }
    
    // Calculate Days Remaining in this billing period
    $remainingDays = $today->diff($targetDate)->days;
    $remainingDays = max(0, $remainingDays);
    
    // Normalize Cycle Days 
    $cycleDays = max(1, $cycleDays);

    // 3. Fetch Existing Active Items
    $stmtItems = $pdo->prepare("SELECT * FROM subscription_items WHERE subscription_id = ? AND status = 'active'");
    $stmtItems->execute([$subId]);
    $currentItems = $stmtItems->fetchAll(PDO::FETCH_ASSOC);
    
    $changes = [];
    $dueNow = 0;

    // Map existing items
    $currentMap = [];
    foreach($currentItems as $ci) {
        $currentMap[$ci['product_id']] = $ci; 
    }

    foreach ($newItems as $ni) {
        $pid = $ni['product_id'];
        $qty = $ni['quantity'];
        $cost = $ni['unit_cost']; // Full Term Unit Cost
        
        if (!isset($currentMap[$pid])) {
            // NEW PRODUCT
            // Cost = (UnitCost * Qty).
            $termCost = $cost * $qty;
            $proRata = ($termCost / $cycleDays) * $remainingDays;
            
            $dueNow += $proRata;
            $changes[] = [
                'type' => 'add',
                'product_id' => $pid,
                'name' => $ni['name'] ?? 'Unknown Product',
                'amount' => $proRata,
                'desc' => "Pro-rated add-on ({$remainingDays} days remaining in cycle)"
            ];
        } else {
            // EXISTING
            $oldQty = $currentMap[$pid]['quantity'];
            if ($qty > $oldQty) {
                // INCREASE
                $qtyDiff = $qty - $oldQty;
                $termCost = $cost * $qtyDiff;
                $proRata = ($termCost / $cycleDays) * $remainingDays;
                
                $dueNow += $proRata;
                 $changes[] = [
                    'type' => 'increase',
                    'product_id' => $pid,
                    'name' => $ni['name'] ?? 'Product',
                    'amount' => $proRata,
                    'desc' => "Added {$qtyDiff} qty ({$remainingDays} days remaining in cycle)"
                ];
            } elseif ($qty < $oldQty) {
                // DOWNGRADE
                $changes[] = [
                    'type' => 'decrease',
                    'product_id' => $pid,
                    'msg' => 'Quantity reduction will take effect at renewal.'
                ];
            }
        }
    }

    ob_clean(); // Discard any warnings/notices
    echo json_encode([
        'success' => true,
        'due_now' => round($dueNow, 2),
        'currency' => $sub['currency'],
        'changes' => $changes,
        'remaining_days' => $remainingDays
    ]);

} catch (Exception $e) {
    ob_clean();
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
