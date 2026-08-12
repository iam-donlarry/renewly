<?php
// classes/VendorManager.php - Vendor Catalog Management

class VendorManager {
    public static function getAll(): array {
        $pdo = getDB();
        $stmt = $pdo->query("
            SELECT v.*, (SELECT COUNT(*) FROM products WHERE vendor_id = v.id) as product_count
            FROM vendors v
            ORDER BY v.vendor_name ASC
        ");
        return $stmt->fetchAll() ?: [];
    }

    public static function getById(int $id): ?array {
        $pdo = getDB();
        $stmt = $pdo->prepare("SELECT * FROM vendors WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function create(array $data): int {
        $pdo = getDB();
        $stmt = $pdo->prepare("
            INSERT INTO vendors (vendor_name, website, support_email, status, notes)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $data['vendor_name'],
            $data['website'] ?? '',
            $data['support_email'] ?? '',
            $data['status'] ?? 'active',
            $data['notes'] ?? ''
        ]);
        $id = (int)$pdo->lastInsertId();
        AuditLogger::log('create_vendor', 'vendors', $id, null, $data);
        return $id;
    }

    public static function update(int $id, array $data): bool {
        $pdo = getDB();
        $before = self::getById($id);
        $stmt = $pdo->prepare("
            UPDATE vendors SET vendor_name = ?, website = ?, support_email = ?, status = ?, notes = ? WHERE id = ?
        ");
        $success = $stmt->execute([
            $data['vendor_name'],
            $data['website'] ?? '',
            $data['support_email'] ?? '',
            $data['status'] ?? 'active',
            $data['notes'] ?? '',
            $id
        ]);
        if ($success) {
            AuditLogger::log('update_vendor', 'vendors', $id, $before, $data);
        }
        return $success;
    }
}
