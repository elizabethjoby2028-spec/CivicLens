<?php
/**
 * Database Connection Helper (PDO)
 */

require_once __DIR__ . '/../config.php';

function get_db() {
    static $pdo = null;

    if ($pdo === null) {
        $config = require __DIR__ . '/../config/database.php';
        
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $config['host'],
            $config['port'],
            $config['database'],
            $config['charset']
        );

        try {
            $pdo = new PDO($dsn, $config['username'], $config['password'], $config['options']);
        } catch (PDOException $e) {
            // In development, provide helpful guidance; in production, keep error generic.
            if (APP_DEBUG) {
                die("<h3>Database Connection Error</h3><p>Could not connect to MySQL: " . htmlspecialchars($e->getMessage()) . "</p><p>Please ensure XAMPP MySQL is started and database 'civiclens_db' exists.</p>");
            } else {
                error_log("Database connection error: " . $e->getMessage());
                die("A database connection error occurred. Please contact the administrator.");
            }
        }
    }

    return $pdo;
}

/**
 * Check if the database connection can be established
 */
function test_db_connection() {
    try {
        $db = get_db();
        return ($db !== null);
    } catch (Exception $e) {
        return false;
    }
}
