<?php
/**
 * Database Connection Module
 * Maritime Exam Portal
 *
 * Provides PDO database connection with error handling
 */

// Prevent direct access
if (!defined('API_ACCESS')) {
    http_response_code(403);
    die('Direct access not permitted');
}

// Database configuration
// IMPORTANT: Update these values with your actual cPanel database credentials
define('DB_HOST', 'localhost');           // Usually 'localhost' on cPanel
define('DB_NAME', 'maritime_exam_portal'); // Your database name (may have prefix like 'cpanel_maritime')
define('DB_USER', 'root');                 // Your database username
define('DB_PASS', '');                     // Your database password
define('DB_CHARSET', 'utf8mb4');

/**
 * Get PDO database connection
 * @return PDO Database connection object
 * @throws Exception if connection fails
 */
function getDbConnection() {
    static $pdo = null;

    // Reuse existing connection
    if ($pdo !== null) {
        return $pdo;
    }

    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET
        ];

        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);

        return $pdo;

    } catch (PDOException $e) {
        // Log error securely (don't expose to client)
        error_log('Database Connection Error: ' . $e->getMessage());

        // Return generic error to client
        throw new Exception('Database connection failed. Please try again later.');
    }
}

/**
 * Execute a SELECT query and return all results
 * @param string $sql SQL query with placeholders
 * @param array $params Parameters for prepared statement
 * @return array Query results
 */
function dbQuery($sql, $params = []) {
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log('Database Query Error: ' . $e->getMessage() . ' | SQL: ' . $sql);
        throw new Exception('Database query failed');
    }
}

/**
 * Execute a SELECT query and return single row
 * @param string $sql SQL query with placeholders
 * @param array $params Parameters for prepared statement
 * @return array|null Single row or null if not found
 */
function dbQuerySingle($sql, $params = []) {
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch();
        return $result ?: null;
    } catch (PDOException $e) {
        error_log('Database Query Error: ' . $e->getMessage() . ' | SQL: ' . $sql);
        throw new Exception('Database query failed');
    }
}

/**
 * Execute an INSERT/UPDATE/DELETE query
 * @param string $sql SQL query with placeholders
 * @param array $params Parameters for prepared statement
 * @return int Number of affected rows
 */
function dbExecute($sql, $params = []) {
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    } catch (PDOException $e) {
        error_log('Database Execute Error: ' . $e->getMessage() . ' | SQL: ' . $sql);
        throw new Exception('Database operation failed');
    }
}

/**
 * Execute INSERT and return last inserted ID
 * @param string $sql SQL INSERT query with placeholders
 * @param array $params Parameters for prepared statement
 * @return int Last inserted ID
 */
function dbInsert($sql, $params = []) {
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $pdo->lastInsertId();
    } catch (PDOException $e) {
        error_log('Database Insert Error: ' . $e->getMessage() . ' | SQL: ' . $sql);
        throw new Exception('Database insert failed');
    }
}

/**
 * Begin database transaction
 */
function dbBeginTransaction() {
    $pdo = getDbConnection();
    $pdo->beginTransaction();
}

/**
 * Commit database transaction
 */
function dbCommit() {
    $pdo = getDbConnection();
    $pdo->commit();
}

/**
 * Rollback database transaction
 */
function dbRollback() {
    $pdo = getDbConnection();
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
}

/**
 * Test database connection
 * @return bool True if connection successful
 */
function testDbConnection() {
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->query('SELECT 1');
        return $stmt !== false;
    } catch (Exception $e) {
        return false;
    }
}

?>
