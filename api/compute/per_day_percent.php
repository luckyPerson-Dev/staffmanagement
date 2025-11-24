<?php
/**
 * api/compute/per_day_percent.php
 * Get per-day percentage for a month/year
 * GET: month, year (optional, defaults to current month)
 */

require_once __DIR__ . '/../../core/autoload.php';
require_once __DIR__ . '/../../auth_helper.php';
require_once __DIR__ . '/../../helpers.php';

require_login();

header('Content-Type: application/json');

$month = isset($_GET['month']) ? intval($_GET['month']) : (int)date('n');
$year = isset($_GET['year']) ? intval($_GET['year']) : (int)date('Y');

if ($month < 1 || $month > 12 || $year < 2000 || $year > 2100) {
    response_json(['success' => false, 'message' => 'Invalid month or year'], 400);
}

try {
    $per_day_percent = per_day_percent($month, $year);
    $days_in_month = days_in_month($month, $year);
    
    response_json([
        'success' => true,
        'month' => $month,
        'year' => $year,
        'per_day_percent' => $per_day_percent,
        'days_in_month' => $days_in_month
    ]);
    
} catch (Exception $e) {
    error_log("Error computing per-day percent: " . $e->getMessage());
    response_json(['success' => false, 'message' => 'Failed to compute per-day percent: ' . $e->getMessage()], 500);
}

