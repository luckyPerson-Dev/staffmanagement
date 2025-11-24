<?php
/**
 * api/get_live_progress.php
 * Get live progress data for current month (AJAX endpoint)
 */

require_once __DIR__ . '/../auth_helper.php';
require_once __DIR__ . '/../helpers.php';

require_login();

header('Content-Type: application/json');

$user = current_user();
$pdo = getPDO();

// Get current month
$current_month = (int)date('n');
$current_year = (int)date('Y');
$days_in_month = days_in_month($current_month, $current_year);
$current_day = (int)date('j');
$per_day_percent = per_day_percent($current_month, $current_year);

// Get user's team_id
$stmt = $pdo->prepare("SELECT team_id FROM team_members WHERE user_id = ? LIMIT 1");
$stmt->execute([$user['id']]);
$team_result = $stmt->fetch();
$team_id = $team_result ? (int)$team_result['team_id'] : null;

// Get current month progress
$stmt = $pdo->prepare("
    SELECT 
        AVG(progress_percent) as avg_progress, 
        COUNT(*) as days_count,
        MIN(date) as first_date,
        MAX(date) as last_date
    FROM daily_progress
    WHERE user_id = ? AND YEAR(date) = ? AND MONTH(date) = ?
");
$stmt->execute([$user['id'], $current_year, $current_month]);
$month_progress = $stmt->fetch();

$avg_progress = floatval($month_progress['avg_progress'] ?? 0);
$days_recorded = (int)($month_progress['days_count'] ?? 0);
$first_date = $month_progress['first_date'] ?? null;
$last_date = $month_progress['last_date'] ?? null;

// Calculate expected progress based on days elapsed
$expected_progress = $current_day * $per_day_percent;
$expected_progress = min(100, $expected_progress);

// Compute ticket and group percentages
$ticket_percent = compute_ticket_percent($user['id'], $current_month, $current_year);
$group_percent = $team_id ? compute_group_avg($team_id, $current_month, $current_year) : 0.0;
$base_monthly_progress = ($ticket_percent + $group_percent) / 2;

// Get today's progress if available
$today = date('Y-m-d');
$stmt = $pdo->prepare("
    SELECT progress_percent 
    FROM daily_progress 
    WHERE user_id = ? AND date = ?
");
$stmt->execute([$user['id'], $today]);
$today_progress = $stmt->fetch();
$today_progress_value = $today_progress ? floatval($today_progress['progress_percent']) : null;

// Calculate progress status
$progress_status = 'on-track';
$progress_difference = $avg_progress - $expected_progress;

if ($progress_difference < -5) {
    $progress_status = 'behind';
} elseif ($progress_difference > 5) {
    $progress_status = 'ahead';
}

response_json([
    'success' => true,
    'data' => [
        'month' => $current_month,
        'year' => $current_year,
        'month_name' => date('F Y'),
        'current_day' => $current_day,
        'days_in_month' => $days_in_month,
        'days_elapsed' => $current_day,
        'days_remaining' => $days_in_month - $current_day,
        'per_day_percent' => round($per_day_percent, 4),
        'avg_progress' => round($avg_progress, 2),
        'expected_progress' => round($expected_progress, 2),
        'progress_difference' => round($progress_difference, 2),
        'progress_status' => $progress_status,
        'days_recorded' => $days_recorded,
        'first_date' => $first_date,
        'last_date' => $last_date,
        'today_progress' => $today_progress_value,
        'ticket_percent' => round($ticket_percent, 2),
        'group_percent' => round($group_percent, 2),
        'base_monthly_progress' => round($base_monthly_progress, 2),
        'updated_at' => date('Y-m-d H:i:s')
    ]
]);

