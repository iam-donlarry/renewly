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

    file_put_contents('debug_commit.log', "--- Request at " . date('Y-m-d H:i:s') . " ---\n", FILE_APPEND);
    
    $rawInput = file_get_contents('php://input');
    file_put_contents('debug_commit.log', "Raw Input: $rawInput\n", FILE_APPEND);

    $input = json_decode($rawInput, true);
    if (!$input) {
        file_put_contents('debug_commit.log', "Error: Invalid JSON input\n", FILE_APPEND);
        throw new Exception('Invalid JSON input');
    }

    $subId = $input['subscription_id'];
    
    // Fetch Subscription Details
    $stmtSub = $pdo->prepare("SELECT * FROM subscriptions WHERE id = ?");
    $stmtSub->execute([$subId]);
    $sub = $stmtSub->fetch(PDO::FETCH_ASSOC);
    if (!$sub) throw new Exception('Subscription not found');

    // Start Transaction
    $pdo->beginTransaction();

    // $changes unused, using $items directly from input
    // 2. Process Changes
    // Identify Proration End Date (Target)
    $stmtPay = $pdo->prepare("SELECT MIN(due_date) as next_due FROM subscription_payments WHERE subscription_id = ? AND status = 'pending' AND due_date >= CURRENT_DATE");
    $stmtPay->execute([$subId]);
    $nextPayment = $stmtPay->fetch(PDO::FETCH_ASSOC);

    if ($nextPayment && $nextPayment['next_due']) {
        // Monthly/Quarterly
        $targetDate = new DateTime($nextPayment['next_due']);
        $cycle = $sub['billing_cycle'];
        $cycleDays = ($cycle === 'monthly') ? 30 : (($cycle === 'quarterly') ? 90 : 365);
    } else {
        // Paid Upfront / End of Term
        $targetDate = new DateTime($sub['expiry_date']);
        $cycleDays = 365;
        $startDate = new DateTime($sub['start_date']);
        $termDays = $startDate->diff($targetDate)->days;
        $cycleDays = max(1, $termDays);
    }
    
    $today = new DateTime();
    $remainingDays = $today->diff($targetDate)->days;
    $remainingDays = max(0, $remainingDays);
    $cycleDays = max(1, $cycleDays);

    $stmtItems = $pdo->prepare("SELECT * FROM subscription_items WHERE subscription_id = ? AND status = 'active'");
    $stmtItems->execute([$subId]);
    $currentItems = $stmtItems->fetchAll(PDO::FETCH_ASSOC);
    
    // Create Map
    $currentMap = [];
    foreach($currentItems as $ci) {
        $currentMap[$ci['product_id']] = $ci;
    }

    $finalDueNow = 0;

    foreach ($input['items'] as $item) {
        $pid = $item['product_id'];
        $qty = (int)$item['quantity'];
        $cost = (float)$item['unit_cost'];
        
        file_put_contents('debug_commit.log', "Processing Item: PID=$pid, Qty=$qty, Cost=$cost\n", FILE_APPEND);
        
        if (!isset($currentMap[$pid])) {
            file_put_contents('debug_commit.log', "Item $pid not found in current map. Inserting.\n", FILE_APPEND);
            // INSERT NEW (Co-terming)
            $totalItemCost = $qty * $cost;
            $stmtIns = $pdo->prepare("INSERT INTO subscription_items (subscription_id, product_id, quantity, renewal_quantity, unit_cost, total_cost, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
            $stmtIns->execute([$subId, $pid, $qty, $qty, $cost, $totalItemCost]);
            
            // Proration
            $termCost = $qty * $cost; // Cycle Cost
            $proRata = ($termCost / $cycleDays) * $remainingDays;
            $finalDueNow += $proRata;
            
        } else {
            // UPDATE EXISTING
            $oldItem = $currentMap[$pid];
            $oldQty = (int)$oldItem['quantity'];
            $oldCost = (float)$oldItem['total_cost']; // Use total_cost from DB as truth for value
             // Or calculate from unit_cost?
             // Best to compare TOTAL VALUE change.
             
            $newTotalCost = $qty * $cost;
            $valueDiff = $newTotalCost - $oldCost;

            if ($qty >= $oldQty) {
                file_put_contents('debug_commit.log', "Updating Item $pid: NewQty=$qty >= OldQty=$oldQty. ValueDiff=$valueDiff. Executing Update.\n", FILE_APPEND);
                // INCREASE or PRICE CHANGE or NO CHANGE
                // Immediate update of everything
                $stmtUpd = $pdo->prepare("UPDATE subscription_items SET quantity = ?, renewal_quantity = ?, unit_cost = ?, total_cost = ?, updated_at = NOW() WHERE id = ?");
                $stmtUpd->execute([$qty, $qty, $cost, $newTotalCost, $oldItem['id']]);
                
                // Proration: If Value Increased, charge difference
                if ($valueDiff > 0) {
                     $proRata = ($valueDiff / $cycleDays) * $remainingDays;
                     $finalDueNow += $proRata;
                }
                // If Value decreased (but qty same/higher? e.g. price drop), no refund.

            } elseif ($qty < $oldQty) {
                // DECREASE (Queued Downgrade)
                $stmtDown = $pdo->prepare("UPDATE subscription_items SET renewal_quantity = ?, updated_at = NOW() WHERE id = ?");
                $stmtDown->execute([$qty, $oldItem['id']]);
            }
        }
    }

    // 3. Create Immediate Invoice (Prorated)
    if ($finalDueNow > 0) {
        $stmtPay = $pdo->prepare("INSERT INTO subscription_payments (subscription_id, due_date, amount, currency, status) VALUES (?, NOW(), ?, ?, 'pending')");
        $stmtPay->execute([$subId, $finalDueNow, $sub['currency']]);
    }
    
    // 4. Update FUTURE Payments (Recurring Cycle)
    // Recalculate the Grand Total per Cycle based on Active items
    $stmtSum = $pdo->prepare("SELECT SUM(total_cost) FROM subscription_items WHERE subscription_id = ? AND status = 'active'");
    $stmtSum->execute([$subId]);
    // Recalculate the Grand Total per Cycle based on Active items
    $stmtSum = $pdo->prepare("SELECT SUM(total_cost) FROM subscription_items WHERE subscription_id = ? AND status = 'active'");
    $stmtSum->execute([$subId]);
    $totalContractValue = (float)$stmtSum->fetchColumn(); 
    
    // Determine number of cycles in the full term to divide the total value correctly
    $startDate = new DateTime($sub['start_date']);
    $endDate = new DateTime($sub['expiry_date']);
    $termDays = max(1, $startDate->diff($endDate)->days);
    
    // Cycle Days based on billing_cycle
    $billingCycle = strtolower($sub['billing_cycle']);
    $cycleDuration = 365; // default
    if ($billingCycle === 'monthly') $cycleDuration = 30;
    elseif ($billingCycle === 'quarterly') $cycleDuration = 90;
    
    $numCycles = max(1, round($termDays / $cycleDuration));
    
    $newPerCycleTotal = $totalContractValue / $numCycles;
    
    // Update pending payments due in FUTURE (After today)
    $stmtFutureUpdate = $pdo->prepare("UPDATE subscription_payments SET amount = ? WHERE subscription_id = ? AND status = 'pending' AND due_date > NOW()");
    $stmtFutureUpdate->execute([$newPerCycleTotal, $subId]);

    // Update Subscription Record Cost (Annual Run Rate usually, or Term Total?)
    // If Monthly, cost usually stores Total Term Value? Or Monthly Value?
    // Let's assume cost stores the "Value of the Contract".
    // If I increase monthly pay, I increase contract value.
    // Ideally we re-sum all payments? 
    // Simplify: Set subscriptions.cost = (newPerCycleTotal * RemainingCycles) + Paid?
    // For MVP, let's update subscriptions.cost = newPerCycleTotal * CyclesInYear (if we track annualized).
    // Or just leave it? "cost" is used for KPI.
    // Best: Update 'cost' to reflect the CURRENT Annual/Term Total.
    
    // Let's just update based on Sum of Items (which is the current "Per Period" total mostly?)
    // If unit_cost is monthly, then total_cost is monthly total.
    $pdo->prepare("UPDATE subscriptions SET cost = ? WHERE id = ?")->execute([$newPerCycleTotal, $subId]);

    // 5. Log Activity
    $stmtLog = $pdo->prepare("INSERT INTO activity_logs (action, entity_type, entity_id, details) VALUES (?, ?, ?, ?)");
    $stmtLog->execute(['modify_subscription', 'subscription', $subId, 'Modified items. Prorated Invoice: ' . round($finalDueNow,2) . '. New Recurring: ' . $newPerCycleTotal]);

    $pdo->commit();
    ob_clean();
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    file_put_contents('debug_commit.log', "EXCEPTION: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n", FILE_APPEND);
    if ($pdo->inTransaction()) $pdo->rollBack();
    ob_clean();
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
