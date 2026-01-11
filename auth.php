<?php
/**
 * Authentication and Authorization Functions
 * Ethiopian Police University Library Management System
 */

require_once 'config.php';

/**
 * Start secure session
 */
function start_secure_session() {
    if (session_status() === PHP_SESSION_NONE) {
        // Configure session settings before starting
        ini_set('session.cookie_httponly', 1);
        ini_set('session.use_only_cookies', 1);
        ini_set('session.cookie_secure', 0); // Set to 1 for HTTPS
        ini_set('session.cookie_samesite', 'Strict');
        
        session_start();
        
        // Regenerate session ID periodically for security
        if (!isset($_SESSION['last_regeneration'])) {
            $_SESSION['last_regeneration'] = time();
        } elseif (time() - $_SESSION['last_regeneration'] > 300) { // 5 minutes
            session_regenerate_id(true);
            $_SESSION['last_regeneration'] = time();
        }
        
        // Check session timeout
        if (isset($_SESSION['last_activity']) && 
            (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) {
            logout_user();
            return false;
        }
        
        $_SESSION['last_activity'] = time();
    }
    
    return true;
}

/**
 * Authenticate user credentials
 * @param string $username Username
 * @param string $password Password
 * @return array|false User data on success, false on failure
 */
function authenticate_user($username, $password) {
    try {
        // Check admin/librarian login
        $sql = "SELECT admin_id as user_id, username, password_hash, full_name, role, last_login 
                FROM admins WHERE username = ? AND status = 'active'";
        $stmt = execute_query($sql, [$username]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password_hash'])) {
            // Update last login
            $update_sql = "UPDATE admins SET last_login = NOW() WHERE admin_id = ?";
            execute_query($update_sql, [$user['user_id']]);
            
            log_security_event("Successful admin/librarian login", $user['user_id']);
            return $user;
        }
        
        // Check student/staff login (using ID number as username)
        $sql = "SELECT user_id, id_number as username, full_name, role, status 
                FROM users WHERE id_number = ? AND status = 'active'";
        $stmt = execute_query($sql, [$username]);
        $user = $stmt->fetch();
        
        if ($user) {
            // For students/staff, password is their ID number (can be changed later)
            if ($password === $user['username']) {
                log_security_event("Successful student/staff login", $user['user_id']);
                return $user;
            }
        }
        
        log_security_event("Failed login attempt for username: " . $username);
        return false;
        
    } catch (Exception $e) {
        error_log("Authentication error: " . $e->getMessage());
        return false;
    }
}

/**
 * Create user session
 * @param array $user_data User data from authentication
 */
function create_session($user_data) {
    start_secure_session();
    
    $_SESSION['user_id'] = $user_data['user_id'];
    $_SESSION['username'] = $user_data['username'];
    $_SESSION['full_name'] = $user_data['full_name'];
    $_SESSION['role'] = $user_data['role'];
    $_SESSION['login_time'] = time();
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    
    log_security_event("Session created", $user_data['user_id']);
}

/**
 * Check if user is logged in
 * @return bool True if logged in, false otherwise
 */
function is_logged_in() {
    start_secure_session();
    return isset($_SESSION['user_id']) && isset($_SESSION['role']);
}

/**
 * Check if user has specific role
 * @param string $required_role Required role
 * @return bool True if user has role, false otherwise
 */
function has_role($required_role) {
    if (!is_logged_in()) {
        return false;
    }
    
    $user_role = $_SESSION['role'];
    
    // Admin has access to everything
    if ($user_role === 'admin') {
        return true;
    }
    
    // Check specific role
    return $user_role === $required_role;
}

/**
 * Get current logged in user information
 * @return array|null User data or null if not logged in
 */
function get_logged_in_user() {
    if (!is_logged_in()) {
        return null;
    }
    
    return [
        'user_id' => $_SESSION['user_id'],
        'username' => $_SESSION['username'],
        'full_name' => $_SESSION['full_name'],
        'role' => $_SESSION['role']
    ];
}

/**
 * Logout user and destroy session
 */
function logout_user() {
    start_secure_session();
    
    if (isset($_SESSION['user_id'])) {
        log_security_event("User logout", $_SESSION['user_id']);
    }
    
    // Destroy session data
    $_SESSION = array();
    
    // Delete session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    session_destroy();
}

/**
 * Generate CSRF token
 * @return string CSRF token
 */
function generate_csrf_token() {
    start_secure_session();
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 * @param string $token Token to verify
 * @return bool True if valid, false otherwise
 */
function verify_csrf_token($token) {
    start_secure_session();
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Redirect to appropriate dashboard based on user role
 */
function redirect_to_dashboard() {
    if (!is_logged_in()) {
        header('Location: index.php');
        exit();
    }
    
    $role = $_SESSION['role'];
    
    switch ($role) {
        case 'admin':
            header('Location: admin/index.php');
            break;
        case 'librarian':
            header('Location: librarian/index.php');
            break;
        case 'student':
        case 'staff':
            header('Location: student/index.php');
            break;
        default:
            logout_user();
            header('Location: index.php');
    }
    exit();
}
?>