<?php
/**
 * attendance/clock_out.php
 * Clock out endpoint (AJAX)
 */

require_once __DIR__ . '/../auth_helper.php';
require_once __DIR__ . '/../helpers.php';

require_role(['staff']);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    response_json(['success' => false, 'message' => 'Invalid request method'], 405);
}

$user = current_user();
$date = date('Y-m-d');
$time = date('H:i:s');

$pdo = getPDO();

// Check if attendance record exists
$stmt = $pdo->prepare("SELECT id FROM attendance WHERE user_id = ? AND date = ?");
$stmt->execute([$user['id'], $date]);
$existing = $stmt->fetch();

if ($existing) {
    // Update clock out time
    $stmt = $pdo->prepare("UPDATE attendance SET clock_out = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?");
    $stmt->execute([$time, $existing['id']]);
} else {
    // Create new attendance record with clock out only
    $stmt = $pdo->prepare("
        INSERT INTO attendance (user_id, date, clock_out, status, created_at)
        VALUES (?, ?, ?, 'present', UTC_TIMESTAMP())
    ");
    $stmt->execute([$user['id'], $date, $time]);
}

log_audit($user['id'], 'clock_out', 'attendance', null, "Clocked out at $time");

response_json([
    'success' => true,
    'message' => 'Clocked out successfully',
    'time' => $time
]);

