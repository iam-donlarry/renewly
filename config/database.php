<?php
// config/database.php
require_once __DIR__ . '/config.php';

class Database {
    private static ?PDO $instance = null;

    public static function getConnection(): PDO {
        if (self::$instance === null) {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            
            try {
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);
            } catch (PDOException $e) {
                // Return clear clean exception error without exposing sensitive credentials
                error_log("Database Connection Error: " . $e->getMessage());
                die("Database Connection Failed. Please ensure MySQL service is running and the 'renewly' database exists.");
            }
        }
        return self::$instance;
    }
}

// Global helper for PDO connection
function getDB(): PDO {
    return Database::getConnection();
}
