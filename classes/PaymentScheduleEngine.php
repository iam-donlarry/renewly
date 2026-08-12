<?php
// classes/PaymentScheduleEngine.php - Persistent Installment Generator & Tracker

class PaymentScheduleEngine {
    /**
     * Generate or update payment schedule installments for a contract
     */
    public static function generateSchedule(int $contractId, bool $isNew = true): void {
        $pdo = getDB();
        $stmt = $pdo->prepare("SELECT * FROM contracts WHERE id = ?");
        $stmt->execute([$contractId]);
        $contract = $stmt->fetch();
        if (!$contract) return;

        $startDate = new DateTime($contract['start_date']);
        $expiryDate = new DateTime($contract['expiry_date']);
        $cycle = strtolower($contract['billing_cycle']);
        $totalValue = (float)$contract['total_contract_value'];
        $currency = $contract['currency'];
        $exchangeRate = (float)$contract['exchange_rate'];

        // Determine number of installments based on start date, expiry date, and cycle
        $diff = $startDate->diff($expiryDate);
        $months = ($diff->y * 12) + $diff->m + ($diff->d > 15 ? 1 : 0);
        $months = max(1, $months);

        $numInstallments = 1;
        if ($cycle === 'monthly') {
            $numInstallments = $months;
        } elseif ($cycle === 'quarterly') {
            $numInstallments = max(1, (int)round($months / 3));
        } elseif ($cycle === 'yearly') {
            $numInstallments = max(1, (int)round($months / 12));
        }

        $installmentAmount = round($totalValue / $numInstallments, 4);

        if (!$isNew) {
            // Keep existing Paid or Partially Paid records! Only delete pending/due
            $pdo->prepare("
                DELETE FROM payment_schedules 
                WHERE contract_id = ? AND status IN ('pending', 'due', 'overdue')
            ")->execute([$contractId]);

            // Count existing paid installments
            $stmtPaid = $pdo->prepare("SELECT COUNT(*) FROM payment_schedules WHERE contract_id = ? AND status = 'paid'");
            $stmtPaid->execute([$contractId]);
            $paidCount = (int)$stmtPaid->fetchColumn();

            // Adjust remaining installments
            $numInstallments = max(0, $numInstallments - $paidCount);
            if ($numInstallments <= 0) return;
        }

        $stmtIns = $pdo->prepare("
            INSERT INTO payment_schedules (contract_id, installment_number, due_date, amount, currency, exchange_rate, status)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        $currentDueDate = clone $startDate;
        for ($i = 1; $i <= $numInstallments; $i++) {
            $status = ($i === 1 && $isNew) ? 'pending' : 'pending';
            $stmtIns->execute([
                $contractId,
                $i,
                $currentDueDate->format('Y-m-d'),
                $installmentAmount,
                $currency,
                $exchangeRate,
                $status
            ]);

            if ($cycle === 'monthly') {
                $currentDueDate->modify('+1 month');
            } elseif ($cycle === 'quarterly') {
                $currentDueDate->modify('+3 months');
            } elseif ($cycle === 'yearly') {
                $currentDueDate->modify('+1 year');
            }
        }
    }

    public static function markPaid(int $paymentId, ?string $reference = null, ?string $notes = null): bool {
        $pdo = getDB();
        
        if (empty($reference)) {
            $reference = 'PAY-' . date('Ymd') . '-' . str_pad((string)$paymentId, 4, '0', STR_PAD_LEFT);
        }

        $stmt = $pdo->prepare("
            UPDATE payment_schedules 
            SET status = 'paid', payment_date = CURRENT_DATE, payment_reference = ?, notes = ?
            WHERE id = ?
        ");
        $success = $stmt->execute([$reference, $notes, $paymentId]);
        if ($success) {
            AuditLogger::log('mark_payment_paid', 'payment_schedules', $paymentId, null, [
                'reference' => $reference,
                'notes'     => $notes
            ]);
        }
        return $success;
    }
}
