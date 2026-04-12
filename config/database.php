<?php
/**
 * Heal2Rise Book - Database Configuration
 * Secure database connection with PDO
 */

define('DB_HOST', 'localhost');
define('DB_NAME', 'heal2rise_db');
define('DB_USER', 'root');
define('DB_PASS', '');

// Base URL - Change this if your project is in a different folder
define('BASE_URL', '/heal2risebookproject');

class Database {
    private static $instance = null;
    private $connection;

    private function __construct() {
        try {
            $this->connection = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch (PDOException $e) {
            die("Database connection failed: " . $e->getMessage());
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->connection;
    }

    // Prevent cloning
    private function __clone() {}
}

// Helper function to get database connection
function getDB() {
    return Database::getInstance()->getConnection();
}
?>
