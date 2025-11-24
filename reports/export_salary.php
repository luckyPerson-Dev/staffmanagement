<?php
/**
 * reports/export_salary.php
 * Export salary history to CSV
 */

require_once __DIR__ . '/../auth_helper.php';
require_once __DIR__ . '/../helpers.php';

require_role(['superadmin', 'admin', 'accountant']);

$format = $_GET['format'] ?? 'csv';

$pdo = getPDO();

// Get month/year from query params
$month = intval($_GET['month'] ?? date('m'));
$year = intval($_GET['year'] ?? date('Y'));

$stmt = $pdo->prepare("
    SELECT sh.*, u.name as user_name
    FROM salary_history sh
    JOIN users u ON sh.user_id = u.id
    WHERE sh.month = ? AND sh.year = ?
    ORDER BY u.name
");
$stmt->execute([$month, $year]);
$salaries = $stmt->fetchAll();

if ($format === 'pdf') {
    // PDF export would require a PDF library like TCPDF or FPDF
    // For now, redirect to salary index with a message
    $_SESSION['salary_error'] = 'PDF export requires PDF library setup. Please use CSV export for now.';
    header('Location: ' . BASE_URL . '/salary/index.php?month=' . $month . '&year=' . $year);
    exit;
}

// CSV Export
// Ensure downloads directory exists
ensure_directory(DOWNLOADS_DIR);

// Generate filename
$filename = 'salary_' . $year . '_' . str_pad($month, 2, '0', STR_PAD_LEFT) . '_' . date('His') . '.csv';
$filepath = DOWNLOADS_DIR . '/' . $filename;

// Create CSV file
$fp = fopen($filepath, 'w');

// Write header
fputcsv($fp, ['Staff Name', 'Month', 'Year', 'Gross Salary', 'Monthly Progress %', 'Profit Fund', 'Payable Before Advance', 'Advances Deducted', 'Net Payable', 'Status']);

// Write data
foreach ($salaries as $row) {
    fputcsv($fp, [
        $row['user_name'],
        $row['month'],
        $row['year'],
        number_format($row['gross_salary'], 2),
        number_format($row['monthly_progress'], 2),
        number_format($row['profit_fund'], 2),
        number_format($row['payable_before_advance'], 2),
        number_format($row['advances_deducted'], 2),
        number_format($row['net_payable'], 2),
        ucfirst($row['status'])
    ]);
}

fclose($fp);

log_audit(current_user()['id'], 'export', 'report', null, "Exported salary report: $filename");

// Download file
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="' . $filename . '"');
readfile($filepath);
exit;

