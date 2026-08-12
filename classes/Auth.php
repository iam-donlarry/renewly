<?php
// classes/Auth.php - Authentication & User Session Manager

class Auth {
    public static function initSession(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public static function login(string $email, string $password): bool {
        self::initSession();
        $pdo = getDB();
        $stmt = $pdo->prepare("
            SELECT u.*, r.name as role_name 
            FROM users u
            JOIN roles r ON u.role_id = r.id
            WHERE u.email = ? AND u.status = 'active'
        ");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = (int)$user['id'];
            $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['role_id'] = (int)$user['role_id'];
            $_SESSION['role_name'] = $user['role_name'];
            
            // Log successful login
            if (class_exists('AuditLogger')) {
                AuditLogger::log('user_login', 'users', (int)$user['id'], null, ['email' => $email]);
            }
            return true;
        }
        return false;
    }

    public static function logout(): void {
        self::initSession();
        if (isset($_SESSION['user_id']) && class_exists('AuditLogger')) {
            AuditLogger::log('user_logout', 'users', (int)$_SESSION['user_id']);
        }
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();
    }

    public static function check(): bool {
        self::initSession();
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }

    public static function user(): ?array {
        self::initSession();
        if (!self::check()) return null;
        return [
            'id'        => $_SESSION['user_id'],
            'name'      => $_SESSION['user_name'] ?? 'User',
            'email'     => $_SESSION['user_email'] ?? '',
            'role_id'   => $_SESSION['role_id'] ?? 0,
            'role_name' => $_SESSION['role_name'] ?? 'Guest'
        ];
    }

    public static function requireLogin(): void {
        if (!self::check()) {
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                sendJSONResponse(false, 'Unauthorized. Session expired.', [], 401);
            } else {
                header('Location: ' . APP_URL . '/login');
                exit;
            }
        }
    }
}
