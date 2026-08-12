<?php
// classes/ProductCatalog.php - Product Catalog & Pricing Models

class ProductCatalog {
    public static function getAll(): array {
        $pdo = getDB();
        $stmt = $pdo->query("
            SELECT p.*, v.vendor_name 
            FROM products p
            JOIN vendors v ON p.vendor_id = v.id
            ORDER BY v.vendor_name ASC, p.product_name ASC
        ");
        return $stmt->fetchAll() ?: [];
    }

    public static function getById(int $id): ?array {
        $pdo = getDB();
        $stmt = $pdo->prepare("
            SELECT p.*, v.vendor_name 
            FROM products p
            JOIN vendors v ON p.vendor_id = v.id
            WHERE p.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function create(array $data): int {
        $pdo = getDB();
        $stmt = $pdo->prepare("
            INSERT INTO products (vendor_id, product_name, pricing_model, default_unit_cost, currency, description, status)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            (int)$data['vendor_id'],
            $data['product_name'],
            $data['pricing_model'] ?? 'per_seat',
            (float)($data['default_unit_cost'] ?? 0.00),
            $data['currency'] ?? 'USD',
            $data['description'] ?? '',
            $data['status'] ?? 'active'
        ]);
        $id = (int)$pdo->lastInsertId();
        AuditLogger::log('create_product', 'products', $id, null, $data);
        return $id;
    }

    public static function update(int $id, array $data): bool {
        $pdo = getDB();
        $before = self::getById($id);
        $stmt = $pdo->prepare("
            UPDATE products 
            SET vendor_id = ?, product_name = ?, pricing_model = ?, default_unit_cost = ?, currency = ?, description = ?, status = ?
            WHERE id = ?
        ");
        $success = $stmt->execute([
            (int)$data['vendor_id'],
            $data['product_name'],
            $data['pricing_model'] ?? 'per_seat',
            (float)($data['default_unit_cost'] ?? 0.00),
            $data['currency'] ?? 'USD',
            $data['description'] ?? '',
            $data['status'] ?? 'active',
            $id
        ]);
        if ($success) {
            AuditLogger::log('update_product', 'products', $id, $before, $data);
        }
        return $success;
    }
}
