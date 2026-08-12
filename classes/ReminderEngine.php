<?php
// classes/ReminderEngine.php - Daily Expiration Scanner & Idempotent Reminder Engine

class ReminderEngine {
    /**
     * Run daily scan for contract expirations and trigger idempotent notifications
     */
    public static function runDailyScan(): array {
        $pdo = getDB();
        $logs = [];
        $today = new DateTime('today');

        // Stages & intervals (days before expiry)
        $stages = [
            '30_days' => 30,
            '14_days' => 14,
            '7_days'  => 7,
            '3_days'  => 3,
            '1_day'   => 1
        ];

        foreach ($stages as $stageKey => $daysBefore) {
            $targetDate = (clone $today)->modify("+{$daysBefore} days")->format('Y-m-d');

            // Select active contracts expiring on target date
            $stmt = $pdo->prepare("
                SELECT c.id as contract_id, c.contract_reference, c.expiry_date,
                       cl.company_name, cl.primary_contact_email,
                       u.email as am_email
                FROM contracts c
                JOIN clients cl ON c.client_id = cl.id
                LEFT JOIN users u ON c.account_manager_id = u.id
                WHERE c.expiry_date = ? AND c.status IN ('active', 'expiring')
            ");
            $stmt->execute([$targetDate]);
            $contracts = $stmt->fetchAll() ?: [];

            foreach ($contracts as $contract) {
                $recipients = array_filter([
                    $contract['primary_contact_email'],
                    $contract['am_email']
                ]);

                foreach ($recipients as $email) {
                    if (empty($email)) continue;

                    // Idempotency check: verify if already sent!
                    $stmtCheck = $pdo->prepare("
                        SELECT COUNT(*) FROM reminder_logs 
                        WHERE contract_id = ? AND reminder_stage = ? AND recipient_email = ?
                    ");
                    $stmtCheck->execute([$contract['contract_id'], $stageKey, $email]);

                    if ((int)$stmtCheck->fetchColumn() === 0) {
                        // Insert log
                        $stmtIns = $pdo->prepare("
                            INSERT INTO reminder_logs (contract_id, reminder_stage, recipient_email, status)
                            VALUES (?, ?, ?, 'sent')
                        ");
                        $stmtIns->execute([$contract['contract_id'], $stageKey, $email]);

                        $logMsg = "Sent {$stageKey} reminder for contract {$contract['contract_reference']} ({$contract['company_name']}) to {$email}";
                        $logs[] = $logMsg;
                    }
                }
            }
        }

        // Auto-update status to 'expiring' for contracts within 30 days
        $pdo->exec("
            UPDATE contracts 
            SET status = 'expiring' 
            WHERE status = 'active' AND DATEDIFF(expiry_date, CURRENT_DATE) BETWEEN 0 AND 30
        ");

        // Auto-update status to 'lapsed' for contracts past expiry date
        $pdo->exec("
            UPDATE contracts 
            SET status = 'lapsed' 
            WHERE status IN ('active', 'expiring') AND expiry_date < CURRENT_DATE
        ");

        return $logs;
    }
}
