<?php
/**
 * core/autoload.php
 * Simple autoloader for enterprise Staff Management System
 * Compatible with shared hosting, pure PHP
 */

// Define base paths
if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__));
}
if (!defined('CORE_PATH')) {
    define('CORE_PATH', ROOT_PATH . '/core');
}
if (!defined('MODULES_PATH')) {
    define('MODULES_PATH', ROOT_PATH . '/modules');
}
if (!defined('API_PATH')) {
    define('API_PATH', ROOT_PATH . '/api');
}

// Load configuration first
require_once ROOT_PATH . '/config.php';

// Load core components
require_once CORE_PATH . '/Database.php';
require_once CORE_PATH . '/compute_helpers.php';
require_once CORE_PATH . '/Auth.php';
require_once CORE_PATH . '/Logger.php';
require_once CORE_PATH . '/CSRF.php';
require_once CORE_PATH . '/Validator.php';
require_once CORE_PATH . '/Pagination.php';
require_once CORE_PATH . '/Response.php';
require_once CORE_PATH . '/Security.php';
require_once CORE_PATH . '/Helpers.php';

// Load modules
$modules = ['Staff', 'Team', 'Payroll', 'Progress', 'Advance', 'Customer', 'Attendance', 'Analytics', 'Document', 'Notification', 'Message', 'Support'];
foreach ($modules as $module) {
    $file = MODULES_PATH . '/' . strtolower($module) . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
}
