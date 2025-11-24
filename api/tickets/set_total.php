<?php
/**
 * api/tickets/set_total.php
 * Set total tickets for a month/year
 * POST: month, year, total_tickets
 */

require_once __DIR__ . '/../../auth_helper.php';
require_once __DIR__ . '/../../helpers.php';

require_role(['superadmin', 'admin']);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    response_json(['success' => false, 'message' => 'Invalid request method'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
$month = intval($input['month'] ?? 0);
$year = intval($input['year'] ?? 0);
$total_tickets = intval($input['total_tickets'] ?? 0);

if ($month < 1 || $month > 12 || $year < 2000 || $year > 2100) {
    response_json(['success' => false, 'message' => 'Invalid month or year'], 400);
}

if ($total_tickets < 0) {
    response_json(['success' => false, 'message' => 'Total tickets must be non-negative'], 400);
}

$pdo = getPDO();

try {
    // Check if entry exists
    $stmt = $pdo->prepare("SELECT id FROM monthly_tickets WHERE month = ? AND year = ?");
    $stmt->execute([$month, $year]);
    $existing = $stmt->fetch();
    
    if ($existing) {
        // Update existing
        $stmt = $pdo->prepare("UPDATE monthly_tickets SET total_tickets = ? WHERE id = ?");
        $stmt->execute([$total_tickets, $existing['id']]);
        $action = 'update';
        $id = $existing['id'];
    } else {
        // Insert new
        $stmt = $pdo->prepare("INSERT INTO monthly_tickets (month, year, total_tickets, created_at) VALUES (?, ?, ?, UTC_TIMESTAMP())");
        $stmt->execute([$month, $year, $total_tickets]);
        $action = 'create';
        $id = $pdo->lastInsertId();
    }
    
    log_audit(
        current_user()['id'],
        $action,
        'monthly_tickets',
        $id,
        "Set total tickets for $month/$year: $total_tickets"
    );
    
    response_json([
        'success' => true,
        'message' => 'Total tickets saved successfully',
        'id' => $id,
        'month' => $month,
        'year' => $year,
        'total_tickets' => $total_tickets
    ]);
    
} catch (Exception $e) {
    error_log("Error setting total tickets: " . $e->getMessage());
    response_json(['success' => false, 'message' => 'Failed to save total tickets: ' . $e->getMessage()], 500);
}

