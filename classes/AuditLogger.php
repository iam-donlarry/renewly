<?php
// classes/AuditLogger.php - Audit Logging with Structured State Diffs

class AuditLogger {
    public static function log(
        string $action, 
        string $entityType, 
        int $entityId, 
        ?array $beforeState = null, 
        ?array $afterState = null
    ): void {
        try {
            $pdo = getDB();
            $user = Auth::user();
            $userId = $user ? $user['id'] : null;
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

            $stmt = $pdo->prepare("
                INSERT INTO audit_logs (user_id, action, entity_type, entity_id, before_state, after_state, ip_address)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $userId,
                $action,
                $entityType,
                $entityId,
                $beforeState ? json_encode($beforeState) : null,
                $afterState ? json_encode($afterState) : null,
                $ipAddress
            ]);
        } catch (Exception $e) {
            error_log("Audit Logger Failure: " . $e->getMessage());
        }
    }

    public static function getRecent(int $limit = 50): array {
        $pdo = getDB();
        $stmt = $pdo->prepare("
            SELECT a.*, u.first_name, u.last_name, u.email
            FROM audit_logs a
            LEFT JOIN users u ON a.user_id = u.id
            ORDER BY a.created_at DESC
            LIMIT ?
        ");
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }
}
