<?php
/**
 * auth_helper.php
 * Authentication and authorization helper functions
 */

require_once __DIR__ . '/config.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    // Set session save path if needed
    $session_path = sys_get_temp_dir();
    if (is_writable($session_path)) {
        ini_set('session.save_path', $session_path);
    }
    session_start();
}

// Load database connection (with error handling)
try {
    require_once __DIR__ . '/db_connect.php';
} catch (Exception $e) {
    error_log("Database connection error in auth_helper: " . $e->getMessage());
    // Don't die here, let individual functions handle database errors
}

/**
 * Check if user is logged in and session is valid
 * @return bool
 */
function is_logged_in() {
    if (!isset($_SESSION['user_id'])) {
        return false;
    }
    
    // Check session timeout
    if (isset($_SESSION['last_activity']) && 
        defined('SESSION_TIMEOUT') &&
        (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) {
        session_destroy();
        return false;
    }
    
    // Check user status (banned/suspended users cannot access)
    try {
        if (!function_exists('getPDO')) {
            return false;
        }
        $pdo = getPDO();
        $stmt = $pdo->prepare("SELECT status FROM users WHERE id = ? AND deleted_at IS NULL");
        $stmt->execute([$_SESSION['user_id']]);
        $status = $stmt->fetchColumn();
        
        if ($status && in_array($status, ['banned', 'suspended'])) {
            session_destroy();
            return false;
        }
    } catch (Exception $e) {
        error_log("Error checking user status: " . $e->getMessage());
        // If database check fails, allow session to continue (graceful degradation)
        // But update last activity anyway
    }
    
    // Update last activity
    $_SESSION['last_activity'] = time();
    return true;
}

/**
 * Get current logged-in user data
 * @return array|null
 */
function current_user() {
    if (!is_logged_in()) {
        return null;
    }
    
    try {
        if (!function_exists('getPDO')) {
            return null;
        }
        $pdo = getPDO();
        $stmt = $pdo->prepare("SELECT id, name, email, role, monthly_salary, status FROM users WHERE id = ? AND deleted_at IS NULL");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        
        // Check if user is banned or suspended
        if ($user && in_array($user['status'], ['banned', 'suspended'])) {
            // Logout banned/suspended users
            session_destroy();
            return null;
        }
        
        return $user;
    } catch (Exception $e) {
        error_log("Error getting current user: " . $e->getMessage());
        return null;
    }
}

/**
 * Require user to be logged in, redirect to login if not
 */
function require_login() {
    if (!is_logged_in()) {
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }
}

/**
 * Require user to have one of the specified roles
 * @param array $allowed_roles
 */
function require_role($allowed_roles) {
    require_login();
    
    $user = current_user();
    if (!$user || !in_array($user['role'], $allowed_roles)) {
        header('Location: ' . BASE_URL . '/dashboard.php');
        exit;
    }
}

/**
 * Generate CSRF token and store in session
 * @return string
 */
function generate_csrf_token() {
    if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_TOKEN_NAME];
}

/**
 * Verify CSRF token
 * @param string $token
 * @return bool
 */
function verify_csrf_token($token) {
    return isset($_SESSION[CSRF_TOKEN_NAME]) && 
           hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
}

/**
 * Get user role
 * @return string|null
 */
function get_user_role() {
    $user = current_user();
    return $user ? $user['role'] : null;
}

