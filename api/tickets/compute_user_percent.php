<?php
/**
 * api/tickets/compute_user_percent.php
 * Compute ticket percentage for a user/month/year
 * GET: user_id, month, year
 */

require_once __DIR__ . '/../../core/autoload.php';
require_once __DIR__ . '/../../auth_helper.php';
require_once __DIR__ . '/../../helpers.php';

require_login();

header('Content-Type: application/json');

$user_id = intval($_GET['user_id'] ?? 0);
$month = intval($_GET['month'] ?? 0);
$year = intval($_GET['year'] ?? 0);

if (!$user_id || $month < 1 || $month > 12 || $year < 2000 || $year > 2100) {
    response_json(['success' => false, 'message' => 'Invalid parameters'], 400);
}

// Check permission: user can only view their own, or admin/superadmin can view any
$current_user = current_user();
if ($user_id != $current_user['id'] && !in_array($current_user['role'], ['admin', 'superadmin'])) {
    response_json(['success' => false, 'message' => 'Unauthorized'], 403);
}

try {
    $percent = compute_ticket_percent($user_id, $month, $year);
    
    response_json([
        'success' => true,
        'user_id' => $user_id,
        'month' => $month,
        'year' => $year,
        'ticket_percent' => $percent
    ]);
    
} catch (Exception $e) {
    error_log("Error computing ticket percent: " . $e->getMessage());
    response_json(['success' => false, 'message' => 'Failed to compute ticket percent: ' . $e->getMessage()], 500);
}

