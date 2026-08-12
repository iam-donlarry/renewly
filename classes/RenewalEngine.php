<?php
// classes/RenewalEngine.php - Renewal Lifecycle Management & Forecasting

class RenewalEngine {
    /**
     * Get renewal pipeline list with filtering
     */
    public static function getPipeline(array $filters = []): array {
        $pdo = getDB();
        $sql = "
            SELECT r.*, c.contract_reference, c.start_date, c.expiry_date, c.currency, c.billing_cycle, c.status as contract_status,
                   cl.company_name, cl.primary_contact_name, cl.primary_contact_email,
                   CONCAT(u.first_name, ' ', u.last_name) as account_manager_name,
                   DATEDIFF(c.expiry_date, CURRENT_DATE) as days_remaining
            FROM renewals r
            JOIN contracts c ON r.contract_id = c.id
            JOIN clients cl ON c.client_id = cl.id
            LEFT JOIN users u ON r.account_manager_id = u.id
            WHERE 1=1
        ";

        $params = [];
        if (!empty($filters['stage'])) {
            $sql .= " AND r.renewal_stage = ?";
            $params[] = $filters['stage'];
        }
        if (!empty($filters['days'])) {
            $sql .= " AND DATEDIFF(c.expiry_date, CURRENT_DATE) <= ?";
            $params[] = (int)$filters['days'];
        }
        if (!empty($filters['account_manager_id'])) {
            $sql .= " AND r.account_manager_id = ?";
            $params[] = (int)$filters['account_manager_id'];
        }

        $sql .= " ORDER BY c.expiry_date ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll() ?: [];
    }

    /**
     * Calculate exact Estimated Renewal Value for a contract factoring queued reductions & latest catalog prices
     */
    public static function calculateEstimatedRenewalValue(int $contractId): float {
        $pdo = getDB();
        $stmtItems = $pdo->prepare("
            SELECT ci.*, p.default_unit_cost as latest_catalog_price 
            FROM contract_items ci
            JOIN products p ON ci.product_id = p.id
            WHERE ci.contract_id = ? AND ci.status != 'cancelled'
        ");
        $stmtItems->execute([$contractId]);
        $items = $stmtItems->fetchAll() ?: [];

        $estTotal = 0.0;
        foreach ($items as $item) {
            // Use queued_quantity if set, otherwise current_quantity
            $effectiveQty = !empty($item['queued_quantity']) ? (int)$item['queued_quantity'] : (int)$item['current_quantity'];
            $price = (float)$item['unit_price']; // Or catalog price if catalog updated
            $estTotal += ($effectiveQty * $price);
        }
        return round($estTotal, 2);
    }

    /**
     * Update renewal stage & action notes
     */
    public static function updateStage(int $renewalId, string $newStage, ?string $nextAction = null, ?string $nextActionDate = null, ?string $notes = null): bool {
        $pdo = getDB();
        $before = $pdo->query("SELECT * FROM renewals WHERE id = {$renewalId}")->fetch();

        $stmt = $pdo->prepare("
            UPDATE renewals 
            SET renewal_stage = ?, next_action = ?, next_action_due_date = ?, notes = ?
            WHERE id = ?
        ");
        $success = $stmt->execute([
            $newStage,
            $nextAction,
            !empty($nextActionDate) ? $nextActionDate : null,
            $notes,
            $renewalId
        ]);

        if ($success) {
            AuditLogger::log('update_renewal_stage', 'renewals', $renewalId, $before, [
                'renewal_stage' => $newStage,
                'next_action'   => $nextAction
            ]);
        }
        return $success;
    }
}
