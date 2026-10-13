<?php
/**
 * Database Configuration & PDO Connection Factory
 * Reads database credentials from environment variables and returns a reusable singleton PDO instance.
 */

require_once __DIR__ . '/env.php';

// Define backward-compatible constants if not already defined
if (!defined('DB_HOST')) define('DB_HOST', env('DB_HOST', 'localhost'));
if (!defined('DB_USER')) define('DB_USER', env('DB_USER', 'root'));
if (!defined('DB_PASS')) define('DB_PASS', env('DB_PASS', ''));
if (!defined('DB_NAME')) define('DB_NAME', env('DB_NAME', 'staffroom_db'));

if (!class_exists('DatabaseConnection')) {
    class DatabaseConnection
    {
        private static $pdo = null;

        /**
         * Get or create reusable singleton PDO instance
         *
         * @return PDO
         * @throws PDOException
         */
        public static function get()
        {
            if (self::$pdo === null) {
                $host = env('DB_HOST', 'localhost');
                $dbname = env('DB_NAME', 'staffroom_db');
                $user = env('DB_USER', 'root');
                $pass = env('DB_PASS', '');
                $charset = 'utf8mb4';

                $dsn = "mysql:host={$host};dbname={$dbname};charset={$charset}";

                $options = [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Throw exceptions on errors
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,       // Return objects by default
                    PDO::ATTR_EMULATE_PREPARES   => false,                // Use native prepared statements
                    PDO::ATTR_PERSISTENT         => true                  // Persistent connection reuse
                ];

                try {
                    self::$pdo = new PDO($dsn, $user, $pass, $options);
                } catch (PDOException $e) {
                    error_log("Database Connection Error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
                    if (class_exists('Response')) {
                        Response::error('Internal Server Error: Database connection failed.', 500);
                    } else {
                        http_response_code(500);
                        header('Content-Type: application/json; charset=utf-8');
                        echo json_encode([
                            'success' => false,
                            'status' => false,
                            'message' => 'Internal Server Error: Database connection failed.'
                        ]);
                        exit;
                    }
                }
            }

            return self::$pdo;
        }

        /**
         * Reset / close the connection if needed
         */
        public static function disconnect()
        {
            self::$pdo = null;
        }
    }
}

return DatabaseConnection::get();
?>
