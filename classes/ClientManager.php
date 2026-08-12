<?php
// classes/ClientManager.php - Client Company Directory Management

class ClientManager {
    public static function getAll(): array {
        $pdo = getDB();
        $stmt = $pdo->query("
            SELECT c.*, 
                   CONCAT(u.first_name, ' ', u.last_name) as account_manager_name,
                   (SELECT COUNT(*) FROM contracts WHERE client_id = c.id) as contract_count
            FROM clients c
            LEFT JOIN users u ON c.account_manager_id = u.id
            ORDER BY c.company_name ASC
        ");
        return $stmt->fetchAll() ?: [];
    }

    public static function getById(int $id): ?array {
        $pdo = getDB();
        $stmt = $pdo->prepare("
            SELECT c.*, CONCAT(u.first_name, ' ', u.last_name) as account_manager_name
            FROM clients c
            LEFT JOIN users u ON c.account_manager_id = u.id
            WHERE c.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function create(array $data): int {
        $pdo = getDB();
        $stmt = $pdo->prepare("
            INSERT INTO clients (company_name, account_manager_id, primary_contact_name, primary_contact_email, primary_contact_phone, address, status, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $data['company_name'],
            !empty($data['account_manager_id']) ? (int)$data['account_manager_id'] : null,
            $data['primary_contact_name'] ?? '',
            $data['primary_contact_email'] ?? '',
            $data['primary_contact_phone'] ?? '',
            $data['address'] ?? '',
            $data['status'] ?? 'active',
            $data['notes'] ?? ''
        ]);
        $clientId = (int)$pdo->lastInsertId();
        AuditLogger::log('create_client', 'clients', $clientId, null, $data);
        return $clientId;
    }

    public static function update(int $id, array $data): bool {
        $pdo = getDB();
        $before = self::getById($id);
        $stmt = $pdo->prepare("
            UPDATE clients 
            SET company_name = ?, account_manager_id = ?, primary_contact_name = ?, primary_contact_email = ?, primary_contact_phone = ?, address = ?, status = ?, notes = ?
            WHERE id = ?
        ");
        $success = $stmt->execute([
            $data['company_name'],
            !empty($data['account_manager_id']) ? (int)$data['account_manager_id'] : null,
            $data['primary_contact_name'] ?? '',
            $data['primary_contact_email'] ?? '',
            $data['primary_contact_phone'] ?? '',
            $data['address'] ?? '',
            $data['status'] ?? 'active',
            $data['notes'] ?? '',
            $id
        ]);
        if ($success) {
            AuditLogger::log('update_client', 'clients', $id, $before, $data);
        }
        return $success;
    }
}
