<?php
/**
 * Database Configuration and Connection
 * Ethiopian Police University Library Management System
 */

// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'epu_library');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Application configuration
define('APP_NAME', 'Ethiopian Police University Library Management System');
define('APP_VERSION', '1.0.0');
define('SESSION_TIMEOUT', 3600); // 1 hour in seconds
define('MAX_LOGIN_ATTEMPTS', 5);
define('BORROWING_PERIOD_DAYS', 14);
define('FINE_PER_DAY', 2.00); // ETB per day for overdue books
define('MAX_BOOKS_PER_USER', 3);

// Security settings - must be set before session_start()
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_secure', 0); // Set to 1 for HTTPS
    ini_set('session.cookie_samesite', 'Strict');
}

/**
 * Database connection using PDO
 * @return PDO Database connection object
 * @throws PDOException on connection failure
 */
function get_db_connection() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET
            ];
            
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            die("Database connection failed. Please try again later.");
        }
    }
    
    return $pdo;
}

/**
 * Execute a prepared statement safely
 * @param string $sql SQL query with placeholders
 * @param array $params Parameters for the query
 * @return PDOStatement Executed statement
 */
function execute_query($sql, $params = []) {
    try {
        $pdo = get_db_connection();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    } catch (PDOException $e) {
        error_log("Query execution failed: " . $e->getMessage());
        throw new Exception("Database operation failed");
    }
}

/**
 * Sanitize input data
 * @param string $data Input data to sanitize
 * @return string Sanitized data
 */
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

/**
 * Log security events
 * @param string $event Event description
 * @param string $user_id User ID (optional)
 * @param string $ip_address IP address (optional)
 */
function log_security_event($event, $user_id = null, $ip_address = null) {
    $ip_address = $ip_address ?: $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[{$timestamp}] Security Event: {$event} | User: {$user_id} | IP: {$ip_address}" . PHP_EOL;
    
    error_log($log_entry, 3, 'logs/security.log');
}

// Create logs directory if it doesn't exist
if (!file_exists('logs')) {
    mkdir('logs', 0755, true);
}
?>