<?php
/**
 * dashboard.php
 * Role-based dashboard
 */

// Error handling
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

$logs_dir = __DIR__ . '/logs';
if (!is_dir($logs_dir)) {
    @mkdir($logs_dir, 0755, true);
}
ini_set('error_log', $logs_dir . '/php_errors.log');

require_once __DIR__ . '/config.php';

try {
    require_once __DIR__ . '/auth_helper.php';
    require_once __DIR__ . '/helpers.php';
} catch (Exception $e) {
    error_log("Error loading helpers: " . $e->getMessage());
    header('Location: ' . BASE_URL . '/login.php?error=system_error');
    exit;
}

require_login();

$user = current_user();
if (!$user) {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

$role = $user['role'];

// Redirect to role-specific dashboard
switch ($role) {
    case 'superadmin':
        include __DIR__ . '/dashboards/superadmin.php';
        break;
    case 'admin':
        include __DIR__ . '/dashboards/admin.php';
        break;
    case 'accountant':
        include __DIR__ . '/dashboards/accountant.php';
        break;
    case 'staff':
    default:
        include __DIR__ . '/dashboards/staff.php';
        break;
}

