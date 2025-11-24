<?php
/**
 * reports/export_progress.php
 * Export daily progress to CSV
 */

require_once __DIR__ . '/../auth_helper.php';
require_once __DIR__ . '/../helpers.php';

// Staff can export their own data, admin/superadmin can export any
$current_user = current_user();
$requested_user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : null;

if ($current_user['role'] === 'staff') {
    // Staff can only export their own data
    $requested_user_id = $current_user['id'];
} else {
    require_role(['superadmin', 'admin', 'accountant']);
}

$pdo = getPDO();

// Get filter parameters
$staff_id = $requested_user_id ?? (isset($_GET['staff_id']) ? intval($_GET['staff_id']) : null);
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');
$month = isset($_GET['month']) ? intval($_GET['month']) : null;
$year = isset($_GET['year']) ? intval($_GET['year']) : null;

// If month filter is used, override date range
if ($month && $year) {
    $days_in_month = cal_days_in_month(CAL_GREGORIAN, $month, $year);
    $start_date = sprintf('%04d-%02d-01', $year, $month);
    $end_date = sprintf('%04d-%02d-%02d', $year, $month, $days_in_month);
}

// Build query
$where = ["dp.date BETWEEN ? AND ?"];
$params = [$start_date, $end_date];

if ($staff_id) {
    $where[] = "dp.user_id = ?";
    $params[] = $staff_id;
}

$where_clause = implode(' AND ', $where);

$stmt = $pdo->prepare("
    SELECT dp.*, u.name as user_name, c.name as customer_name
    FROM daily_progress dp
    JOIN users u ON dp.user_id = u.id
    LEFT JOIN customers c ON dp.customer_id = c.id
    WHERE {$where_clause}
    ORDER BY dp.date DESC, u.name
");
$stmt->execute($params);
$progress = $stmt->fetchAll();

// Ensure downloads directory exists
ensure_directory(DOWNLOADS_DIR);

// Generate filename
$filename = 'progress_' . date('Y-m-d_His') . '.csv';
$filepath = DOWNLOADS_DIR . '/' . $filename;

// Create CSV file
$fp = fopen($filepath, 'w');

// Write header
fputcsv($fp, ['Date', 'Staff Name', 'Tickets Missed', 'Group Status', 'Progress %', 'Customer', 'Missed Day', 'Overtime', 'Notes']);

// Write data
foreach ($progress as $row) {
    $is_missed = isset($row['is_missed']) && $row['is_missed'] ? 'Yes' : 'No';
    $is_overtime = isset($row['is_overtime']) && $row['is_overtime'] ? 'Yes' : 'No';
    
    // Parse group status
    $groups_status = json_decode($row['groups_status'], true) ?? [];
    $group_status_display = 'N/A';
    if (!empty($groups_status)) {
        $statuses = array_column($groups_status, 'status');
        $ok_count = count(array_filter($statuses, fn($s) => in_array($s, ['completed', 'ok'])));
        $partial_count = count(array_filter($statuses, fn($s) => $s === 'partial'));
        $miss_count = count(array_filter($statuses, fn($s) => in_array($s, ['missed', 'miss'])));
        
        $parts = [];
        if ($ok_count > 0) $parts[] = "OK: {$ok_count}";
        if ($partial_count > 0) $parts[] = "Partial: {$partial_count}";
        if ($miss_count > 0) $parts[] = "Miss: {$miss_count}";
        $group_status_display = !empty($parts) ? implode(', ', $parts) : 'N/A';
    }
    
    fputcsv($fp, [
        $row['date'],
        $row['user_name'],
        $row['tickets_missed'],
        $group_status_display,
        number_format($row['progress_percent'], 2),
        $row['customer_name'] ?? '',
        $is_missed,
        $is_overtime,
        $row['notes'] ?? ''
    ]);
}

fclose($fp);

log_audit(current_user()['id'], 'export', 'report', null, "Exported progress report: $filename");

// Download file
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="' . $filename . '"');
readfile($filepath);
exit;

