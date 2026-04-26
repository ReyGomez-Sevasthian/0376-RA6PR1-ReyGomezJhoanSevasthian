<?php
/**
 * Database Configuration
 * 
 * This file contains the database connection settings.
 * Modify these constants according to your environment.
 */

// Database configuration constants
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'timetrack_pro');
define('DB_USER', 'admin');
define('DB_PASS', 'admin123');
define('DB_CHARSET', 'utf8mb4');

/**
 * Get database connection
 * 
 * @return PDO|null Database connection or null if failed
 */
function getDBConnection() {
    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        
        return new PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (PDOException $e) {
        die ("Error: " . $e->getMessage());
    }
}

/**
 * Execute a query with parameters
 * 
 * @param string $sql SQL query with placeholders
 * @param array $params Parameters to bind
 * @return array|false Query results or false on failure
 */
function executeQuery($sql, $params = []) {
    $pdo = getDBConnection();
    if (!$pdo) {
        return false;
    }
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Query failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Execute an insert/update/delete query
 * 
 * @param string $sql SQL query with placeholders
 * @param array $params Parameters to bind
 * @return int|false Number of affected rows or false on failure
 */
function executeUpdate($sql, $params = []) {
    $pdo = getDBConnection();
    if (!$pdo) {
        return false;
    }
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    } catch (PDOException $e) {
        error_log("Update failed: " . $e->getMessage());
        return false;
    }
}