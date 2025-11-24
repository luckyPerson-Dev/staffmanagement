<?php
/**
 * index.php
 * Redirect to dashboard or login
 */

require_once __DIR__ . '/auth_helper.php';

if (is_logged_in()) {
    header('Location: ' . BASE_URL . '/dashboard.php');
} else {
    header('Location: ' . BASE_URL . '/login.php');
}
exit;

