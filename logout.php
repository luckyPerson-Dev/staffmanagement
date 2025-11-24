<?php
/**
 * logout.php
 * User logout handler
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/helpers.php';

if (is_logged_in()) {
    log_audit($_SESSION['user_id'], 'logout', 'user', $_SESSION['user_id'], 'User logged out');
}

session_destroy();
header('Location: ' . BASE_URL . '/login.php');
exit;

