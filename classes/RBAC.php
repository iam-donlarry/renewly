<?php
// classes/RBAC.php - Role-Based Access Control Gatekeeper

class RBAC {
    private static ?array $userPermissions = null;

    /**
     * Fetch user permissions array for logged-in user
     */
    public static function getUserPermissions(): array {
        if (self::$userPermissions !== null) {
            return self::$userPermissions;
        }

        $user = Auth::user();
        if (!$user) {
            self::$userPermissions = [];
            return self::$userPermissions;
        }

        // Super Admin gets all permissions
        if ($user['role_name'] === 'Super Admin') {
            self::$userPermissions = ['*'];
            return self::$userPermissions;
        }

        $pdo = getDB();
        $stmt = $pdo->prepare("
            SELECT p.permission_key 
            FROM role_permissions rp
            JOIN permissions p ON rp.permission_id = p.id
            WHERE rp.role_id = ?
        ");
        $stmt->execute([$user['role_id']]);
        self::$userPermissions = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
        return self::$userPermissions;
    }

    /**
     * Check if currently authenticated user has specific permission key
     */
    public static function has(string $permissionKey): bool {
        $permissions = self::getUserPermissions();
        if (in_array('*', $permissions, true)) {
            return true;
        }
        return in_array($permissionKey, $permissions, true);
    }

    /**
     * Enforce permission check or throw/abort 403 response
     */
    public static function require(string $permissionKey): void {
        Auth::requireLogin();
        if (!self::has($permissionKey)) {
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                sendJSONResponse(false, "Forbidden. You lack permission: {$permissionKey}", [], 403);
            } else {
                http_response_code(403);
                die("<h1>403 Forbidden</h1><p>You do not have permission (<code>{$permissionKey}</code>) to access this page or perform this action.</p><a href='" . APP_URL . "/dashboard'>Return to Dashboard</a>");
            }
        }
    }
}

/**
 * Global helper function for RBAC checks in templates & views
 */
function hasPermission(string $permissionKey): bool {
    return RBAC::has($permissionKey);
}
