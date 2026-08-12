<?php
// classes/ContractEngine.php - Contract Creation, Price Snapshotting & Lifecycle

class ContractEngine {
    public static function generateReference(): string {
        $prefix = 'CTR-' . date('Y') . '-';
        $pdo = getDB();
        $stmt = $pdo->query("SELECT COUNT(*) FROM contracts");
        $count = (int)$stmt->fetchColumn() + 1;
        return $prefix . str_pad((string)$count, 5, '0', STR_PAD_LEFT);
    }

    public static function getById(int $id): ?array {
        $pdo = getDB();
        $stmt = $pdo->prepare("
            SELECT c.*, 
                   cl.company_name, cl.primary_contact_name, cl.primary_contact_email, cl.primary_contact_phone,
                   CONCAT(u.first_name, ' ', u.last_name) as account_manager_name,
                   r.renewal_stage, r.estimated_renewal_value
            FROM contracts c
            JOIN clients cl ON c.client_id = cl.id
            LEFT JOIN users u ON c.account_manager_id = u.id
            LEFT JOIN renewals r ON r.contract_id = c.id
            WHERE c.id = ?
        ");
        $stmt->execute([$id]);
        $contract = $stmt->fetch();
        if (!$contract) return null;

        // Fetch Line Items
        $stmtItems = $pdo->prepare("
            SELECT ci.*, p.product_name, v.vendor_name 
            FROM contract_items ci
            JOIN products p ON ci.product_id = p.id
            JOIN vendors v ON p.vendor_id = v.id
            WHERE ci.contract_id = ?
            ORDER BY ci.id ASC
        ");
        $stmtItems->execute([$id]);
        $contract['items'] = $stmtItems->fetchAll() ?: [];

        // Fetch Payment Schedule
        $stmtPayments = $pdo->prepare("
            SELECT * FROM payment_schedules 
            WHERE contract_id = ? 
            ORDER BY due_date ASC, installment_number ASC
        ");
        $stmtPayments->execute([$id]);
        $contract['payments'] = $stmtPayments->fetchAll() ?: [];

        return $contract;
    }

    public static function createContract(array $data, array $items): int {
        $pdo = getDB();
        $pdo->beginTransaction();

        try {
            $user = Auth::user();
            $reference = !empty($data['contract_reference']) ? $data['contract_reference'] : self::generateReference();

            // Calculate total contract value from line items
            $totalValue = 0.0;
            foreach ($items as $item) {
                $qty = (int)$item['quantity'];
                $unitPrice = (float)$item['unit_price'];
                $totalValue += ($qty * $unitPrice);
            }

            // 1. Insert Master Contract Record
            $stmt = $pdo->prepare("
                INSERT INTO contracts (
                    contract_reference, client_id, account_manager_id, start_date, expiry_date, 
                    currency, exchange_rate, billing_cycle, total_contract_value, status, approval_status, 
                    auto_renew, notes, created_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $isApprover = hasPermission('contracts.approve');
            $defaultApproval = $isApprover ? 'approved' : 'pending';
            $defaultStatus   = $isApprover ? 'active' : 'draft';

            $stmt->execute([
                $reference,
                (int)$data['client_id'],
                (int)$data['account_manager_id'],
                $data['start_date'],
                $data['expiry_date'],
                $data['currency'] ?? 'USD',
                (float)($data['exchange_rate'] ?? 1.0000),
                $data['billing_cycle'] ?? 'monthly',
                $totalValue,
                $data['status'] ?? $defaultStatus,
                $data['approval_status'] ?? $defaultApproval,
                !empty($data['auto_renew']) ? 1 : 0,
                $data['notes'] ?? '',
                $user ? $user['id'] : null
            ]);

            $contractId = (int)$pdo->lastInsertId();

            // 2. Insert Line Items with PRICE SNAPSHOTTING
            $stmtItem = $pdo->prepare("
                INSERT INTO contract_items (contract_id, product_id, pricing_model, current_quantity, unit_price, line_total, status)
                VALUES (?, ?, ?, ?, ?, ?, 'active')
            ");

            foreach ($items as $item) {
                $productId = (int)$item['product_id'];
                $qty = (int)$item['quantity'];
                $unitPrice = (float)$item['unit_price'];
                $lineTotal = $qty * $unitPrice;
                $model = $item['pricing_model'] ?? 'per_seat';

                $stmtItem->execute([
                    $contractId,
                    $productId,
                    $model,
                    $qty,
                    $unitPrice,
                    $lineTotal
                ]);
            }

            // 3. Generate Payment Installment Schedule ONLY if contract is approved / active
            $approvalStatus = $data['approval_status'] ?? $defaultApproval;
            if ($approvalStatus === 'approved') {
                PaymentScheduleEngine::generateSchedule($contractId, true);
            }

            // 4. Initialize Renewal Record
            $stmtRen = $pdo->prepare("
                INSERT INTO renewals (contract_id, account_manager_id, renewal_stage, current_contract_value, estimated_renewal_value, target_renewal_date, next_action)
                VALUES (?, ?, 'upcoming', ?, ?, ?, 'Initial contract review')
            ");
            $stmtRen->execute([
                $contractId,
                (int)$data['account_manager_id'],
                $totalValue,
                $totalValue,
                $data['expiry_date']
            ]);

            // 5. Audit Log
            AuditLogger::log('create_contract', 'contracts', $contractId, null, [
                'reference' => $reference,
                'total_value' => $totalValue
            ]);

            $pdo->commit();
            return $contractId;

        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}
