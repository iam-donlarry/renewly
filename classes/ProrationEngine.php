<?php
// classes/ProrationEngine.php - Mid-Term Seat Additions & Reduction Queuing

class ProrationEngine {
    /**
     * Preview proration amount for adding seats
     */
    public static function previewAddition(int $contractItemId, int $newQuantity): array {
        $pdo = getDB();
        $stmt = $pdo->prepare("
            SELECT ci.*, c.start_date, c.expiry_date, c.billing_cycle, c.currency, c.exchange_rate, p.product_name
            FROM contract_items ci
            JOIN contracts c ON ci.contract_id = c.id
            JOIN products p ON ci.product_id = p.id
            WHERE ci.id = ?
        ");
        $stmt->execute([$contractItemId]);
        $item = $stmt->fetch();

        if (!$item) throw new Exception("Contract item not found.");
        if ($newQuantity <= $item['current_quantity']) {
            throw new Exception("New quantity must be greater than current quantity for an addition.");
        }

        $deltaQty = $newQuantity - (int)$item['current_quantity'];
        $unitPrice = (float)$item['unit_price'];

        // Determine next payment due date or expiry date as cycle target
        $stmtNextPay = $pdo->prepare("
            SELECT MIN(due_date) as next_due 
            FROM payment_schedules 
            WHERE contract_id = ? AND status IN ('pending', 'due') AND due_date >= CURRENT_DATE
        ");
        $stmtNextPay->execute([$item['contract_id']]);
        $nextPay = $stmtNextPay->fetch();

        $targetDate = ($nextPay && $nextPay['next_due']) ? new DateTime($nextPay['next_due']) : new DateTime($item['expiry_date']);
        $today = new DateTime('today');

        $remainingDays = max(0, $today->diff($targetDate)->days);
        $cycle = strtolower($item['billing_cycle']);
        $cycleDays = ($cycle === 'monthly') ? 30 : (($cycle === 'quarterly') ? 90 : 365);

        // Proration formula
        $fullTermDeltaCost = $deltaQty * $unitPrice;
        $proratedCharge = round(($fullTermDeltaCost / $cycleDays) * $remainingDays, 2);

        return [
            'contract_item_id' => $contractItemId,
            'product_name'     => $item['product_name'],
            'current_quantity' => (int)$item['current_quantity'],
            'new_quantity'     => $newQuantity,
            'added_seats'      => $deltaQty,
            'unit_price'       => $unitPrice,
            'remaining_days'   => $remainingDays,
            'cycle_days'       => $cycleDays,
            'prorated_charge'  => $proratedCharge,
            'currency'         => $item['currency']
        ];
    }

    /**
     * Commit immediate seat addition
     */
    public static function commitAddition(int $contractItemId, int $newQuantity, string $reason = ''): bool {
        $preview = self::previewAddition($contractItemId, $newQuantity);
        $pdo = getDB();
        $pdo->beginTransaction();

        try {
            $user = Auth::user();

            // Fetch Item & Contract
            $stmt = $pdo->prepare("SELECT * FROM contract_items WHERE id = ?");
            $stmt->execute([$contractItemId]);
            $item = $stmt->fetch();
            $contractId = (int)$item['contract_id'];

            // 1. Update line item quantity & total
            $oldQty = (int)$item['current_quantity'];
            $unitPrice = (float)$item['unit_price'];
            $newLineTotal = $newQuantity * $unitPrice;

            $stmtUpd = $pdo->prepare("
                UPDATE contract_items 
                SET current_quantity = ?, line_total = ? 
                WHERE id = ?
            ");
            $stmtUpd->execute([$newQuantity, $newLineTotal, $contractItemId]);

            // 2. Update Contract Total Value
            $stmtSum = $pdo->prepare("SELECT SUM(line_total) FROM contract_items WHERE contract_id = ?");
            $stmtSum->execute([$contractId]);
            $newContractTotal = (float)$stmtSum->fetchColumn();

            $pdo->prepare("UPDATE contracts SET total_contract_value = ? WHERE id = ?")->execute([$newContractTotal, $contractId]);

            // 3. Generate Immediate Prorated Payment Schedule Row (if charge > 0)
            if ($preview['prorated_charge'] > 0) {
                $stmtPay = $pdo->prepare("
                    INSERT INTO payment_schedules (contract_id, installment_number, due_date, amount, currency, status, notes)
                    VALUES (?, 99, CURRENT_DATE, ?, ?, 'pending', ?)
                ");
                $stmtPay->execute([
                    $contractId,
                    $preview['prorated_charge'],
                    $preview['currency'],
                    "Prorated charge for +{$preview['added_seats']} seats (" . $preview['product_name'] . ")"
                ]);
            }

            // 4. Log in subscription_adjustments
            $stmtAdj = $pdo->prepare("
                INSERT INTO subscription_adjustments (
                    contract_item_id, user_id, adjustment_type, previous_quantity, new_quantity, 
                    prorated_charge_amount, requested_date, effective_date, reason
                ) VALUES (?, ?, 'addition_immediate', ?, ?, ?, CURRENT_DATE, CURRENT_DATE, ?)
            ");
            $stmtAdj->execute([
                $contractItemId,
                $user ? $user['id'] : null,
                $oldQty,
                $newQuantity,
                $preview['prorated_charge'],
                $reason
            ]);

            // 5. Audit Log
            AuditLogger::log('seat_addition', 'contract_items', $contractItemId, ['quantity' => $oldQty], ['quantity' => $newQuantity]);

            $pdo->commit();
            return true;
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Queue seat reduction for renewal date
     */
    public static function queueReduction(int $contractItemId, int $desiredQuantity, string $reason = ''): bool {
        $pdo = getDB();
        $pdo->beginTransaction();

        try {
            $user = Auth::user();
            $stmt = $pdo->prepare("
                SELECT ci.*, c.expiry_date 
                FROM contract_items ci
                JOIN contracts c ON ci.contract_id = c.id
                WHERE ci.id = ?
            ");
            $stmt->execute([$contractItemId]);
            $item = $stmt->fetch();

            if (!$item) throw new Exception("Contract item not found.");
            if ($desiredQuantity >= $item['current_quantity']) {
                throw new Exception("Desired quantity must be less than current quantity for a reduction.");
            }

            $oldQty = (int)$item['current_quantity'];
            $expiryDate = $item['expiry_date'];

            // Update item with queued quantity and pending status
            $stmtUpd = $pdo->prepare("
                UPDATE contract_items 
                SET queued_quantity = ?, queued_effective_date = ?, status = 'pending_reduction' 
                WHERE id = ?
            ");
            $stmtUpd->execute([$desiredQuantity, $expiryDate, $contractItemId]);

            // Log adjustment record
            $stmtAdj = $pdo->prepare("
                INSERT INTO subscription_adjustments (
                    contract_item_id, user_id, adjustment_type, previous_quantity, new_quantity, 
                    prorated_charge_amount, requested_date, effective_date, reason
                ) VALUES (?, ?, 'reduction_queued', ?, ?, 0.0000, CURRENT_DATE, ?, ?)
            ");
            $stmtAdj->execute([
                $contractItemId,
                $user ? $user['id'] : null,
                $oldQty,
                $desiredQuantity,
                $expiryDate,
                $reason
            ]);

            AuditLogger::log('seat_reduction_queued', 'contract_items', $contractItemId, ['quantity' => $oldQty], ['queued_quantity' => $desiredQuantity, 'effective_date' => $expiryDate]);

            $pdo->commit();
            return true;
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}
