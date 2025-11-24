<?php
/**
 * api/tickets/set_staff.php
 * Set staff ticket count for a user/month/year
 * POST: user_id, month, year, ticket_count
 */

require_once __DIR__ . '/../../auth_helper.php';
require_once __DIR__ . '/../../helpers.php';

require_role(['superadmin', 'admin']);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    response_json(['success' => false, 'message' => 'Invalid request method'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
$user_id = intval($input['user_id'] ?? 0);
$month = intval($input['month'] ?? 0);
$year = intval($input['year'] ?? 0);
$ticket_count = intval($input['ticket_count'] ?? 0);

if (!$user_id || $month < 1 || $month > 12 || $year < 2000 || $year > 2100) {
    response_json(['success' => false, 'message' => 'Invalid parameters'], 400);
}

if ($ticket_count < 0) {
    response_json(['success' => false, 'message' => 'Ticket count must be non-negative'], 400);
}

$pdo = getPDO();

// Verify user exists and is staff
$stmt = $pdo->prepare("SELECT id, role FROM users WHERE id = ? AND deleted_at IS NULL");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user || $user['role'] !== 'staff') {
    response_json(['success' => false, 'message' => 'Invalid user or user is not staff'], 400);
}

try {
    // Check if entry exists
    $stmt = $pdo->prepare("SELECT id FROM staff_tickets WHERE user_id = ? AND month = ? AND year = ?");
    $stmt->execute([$user_id, $month, $year]);
    $existing = $stmt->fetch();
    
    if ($existing) {
        // Update existing
        $stmt = $pdo->prepare("UPDATE staff_tickets SET ticket_count = ? WHERE id = ?");
        $stmt->execute([$ticket_count, $existing['id']]);
        $action = 'update';
        $id = $existing['id'];
    } else {
        // Insert new
        $stmt = $pdo->prepare("INSERT INTO staff_tickets (user_id, month, year, ticket_count, created_at) VALUES (?, ?, ?, ?, UTC_TIMESTAMP())");
        $stmt->execute([$user_id, $month, $year, $ticket_count]);
        $action = 'create';
        $id = $pdo->lastInsertId();
    }
    
    log_audit(
        current_user()['id'],
        $action,
        'staff_tickets',
        $id,
        "Set ticket count for user $user_id ($month/$year): $ticket_count"
    );
    
    response_json([
        'success' => true,
        'message' => 'Staff ticket count saved successfully',
        'id' => $id,
        'user_id' => $user_id,
        'month' => $month,
        'year' => $year,
        'ticket_count' => $ticket_count
    ]);
    
} catch (Exception $e) {
    error_log("Error setting staff tickets: " . $e->getMessage());
    response_json(['success' => false, 'message' => 'Failed to save staff ticket count: ' . $e->getMessage()], 500);
}

