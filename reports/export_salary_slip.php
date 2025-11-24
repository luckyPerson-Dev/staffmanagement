<?php
/**
 * reports/export_salary_slip.php
 * Export individual salary slip (PDF/CSV)
 */

require_once __DIR__ . '/../auth_helper.php';
require_once __DIR__ . '/../helpers.php';

require_role(['superadmin', 'admin', 'accountant']);

$id = intval($_GET['id'] ?? 0);
$format = $_GET['format'] ?? 'pdf';

if (!$id) {
    header('Location: ' . BASE_URL . '/salary/index.php');
    exit;
}

$pdo = getPDO();
$stmt = $pdo->prepare("
    SELECT sh.*, u.name as user_name, u.email
    FROM salary_history sh
    JOIN users u ON sh.user_id = u.id
    WHERE sh.id = ?
");
$stmt->execute([$id]);
$salary = $stmt->fetch();

if (!$salary) {
    header('Location: ' . BASE_URL . '/salary/index.php');
    exit;
}

if ($format === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="salary_slip_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $salary['user_name']) . '_' . $salary['month'] . '_' . $salary['year'] . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Salary Slip']);
    fputcsv($output, ['Staff Name', $salary['user_name']]);
    fputcsv($output, ['Month/Year', date('F Y', mktime(0, 0, 0, $salary['month'], 1, $salary['year']))]);
    fputcsv($output, ['Gross Salary', '৳' . number_format($salary['gross_salary'], 2)]);
    fputcsv($output, ['Monthly Progress', number_format($salary['monthly_progress'], 2) . '%']);
    fputcsv($output, ['Profit Fund', '৳' . number_format($salary['profit_fund'], 2)]);
    fputcsv($output, ['Advances Deducted', '৳' . number_format($salary['advances_deducted'], 2)]);
    fputcsv($output, ['Net Payable', '৳' . number_format($salary['net_payable'], 2)]);
    fputcsv($output, ['Status', ucfirst($salary['status'])]);
    fclose($output);
    exit;
} else {
    // PDF Export - Generate printable HTML page with full page structure
    $month_name = date('F', mktime(0, 0, 0, intval($salary['month']), 1));
    $year = intval($salary['year']);
    $company_name = 'Staff Management System';
    try {
        $company_name = get_setting('website_name', 'Staff Management System');
    } catch (Exception $e) {
        // Use default if get_setting fails
    }
    
    $page_title = 'Salary Slip - ' . h($salary['user_name']);
    include __DIR__ . '/../includes/header.php';
    ?>
    
<div class="container-fluid py-4">
    <div class="mb-4 no-print">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3">
            <div>
                <h1 class="gradient-text mb-2 fs-3 fs-md-2">Salary Slip</h1>
                <p class="text-muted mb-0 small"><?= h($salary['user_name']) ?> - <?= $month_name ?> <?= $year ?></p>
            </div>
            <div class="d-flex gap-2">
                <a href="<?= BASE_URL ?>/salary/index.php?month=<?= $salary['month'] ?>&year=<?= $salary['year'] ?>" 
                   class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i>Back to Salary
                </a>
                <button class="btn btn-primary" onclick="window.print()">
                    <i class="bi bi-printer me-1"></i>Print / Save as PDF
                </button>
            </div>
        </div>
    </div>
    
    <!-- Generate HTML for PDF -->
    <div class="card shadow-lg border-0" style="border-radius: 12px;">
        <div class="card-body p-0">
            <style>
        @media print {
            @page {
                size: A4;
                margin: 15mm;
            }
            
            /* Hide all navigation and UI elements */
            .navbar,
            .sidebar,
            .mobile-bottom-nav,
            .top-navbar,
            .container-fluid > .mb-4:first-child,
            .btn,
            .no-print {
                display: none !important;
                visibility: hidden !important;
            }
            
            /* Show only the salary slip content */
            body {
                background: white !important;
                padding: 0 !important;
            }
            
            .container-fluid {
                padding: 0 !important;
                margin: 0 !important;
            }
            
            .card {
                box-shadow: none !important;
                border: none !important;
            }
            
            .salary-slip {
                margin: 0 !important;
                padding: 20px !important;
                box-shadow: none !important;
            }
        }
        
        .salary-slip {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            font-family: 'Arial', 'Helvetica', sans-serif;
            font-size: 12pt;
            line-height: 1.6;
            color: #333;
        }
        
        .header {
            text-align: center;
            border-bottom: 3px solid #007bff;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        
        .header h1 {
            color: #007bff;
            font-size: 28pt;
            margin-bottom: 10px;
        }
        
        .header h2 {
            color: #666;
            font-size: 18pt;
            font-weight: normal;
        }
        
        .info-section {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }
        
        .info-box {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            border-left: 4px solid #007bff;
        }
        
        .info-box h3 {
            color: #007bff;
            font-size: 14pt;
            margin-bottom: 10px;
            border-bottom: 1px solid #dee2e6;
            padding-bottom: 5px;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px dotted #dee2e6;
        }
        
        .info-row:last-child {
            border-bottom: none;
        }
        
        .info-label {
            font-weight: 600;
            color: #555;
        }
        
        .info-value {
            color: #333;
            text-align: right;
        }
        
        .salary-breakdown {
            margin-top: 30px;
            border: 2px solid #007bff;
            border-radius: 8px;
            overflow: hidden;
        }
        
        .salary-breakdown h3 {
            background: linear-gradient(135deg, #007bff, #706fd3);
            color: white;
            padding: 15px 20px;
            margin: 0;
            font-size: 16pt;
        }
        
        .breakdown-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .breakdown-table tr {
            border-bottom: 1px solid #dee2e6;
        }
        
        .breakdown-table tr:last-child {
            border-bottom: none;
        }
        
        .breakdown-table td {
            padding: 15px 20px;
            font-size: 12pt;
        }
        
        .breakdown-table td:first-child {
            font-weight: 600;
            color: #555;
            width: 60%;
        }
        
        .breakdown-table td:last-child {
            text-align: right;
            font-weight: 600;
            color: #333;
        }
        
        .total-row {
            background: #f8f9fa;
            font-size: 14pt;
        }
        
        .total-row td {
            padding: 20px;
            font-weight: 700;
            color: #007bff;
        }
        
        .status-badge {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 11pt;
        }
        
        .status-pending {
            background: #6c757d;
            color: white;
        }
        
        .status-approved {
            background: #ffc107;
            color: #000;
        }
        
        .status-paid {
            background: #28a745;
            color: white;
        }
        
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 2px solid #dee2e6;
            text-align: center;
            color: #666;
            font-size: 10pt;
        }
        
            </style>
            
            <div class="salary-slip p-4">
        <div class="header">
            <h1><?= h($company_name) ?></h1>
            <h2>Salary Slip</h2>
        </div>
        
        <div class="info-section">
            <div class="info-box">
                <h3>Employee Information</h3>
                <div class="info-row">
                    <span class="info-label">Name:</span>
                    <span class="info-value"><?= h($salary['user_name']) ?></span>
                </div>
                <?php if (!empty($salary['email'])): ?>
                <div class="info-row">
                    <span class="info-label">Email:</span>
                    <span class="info-value"><?= h($salary['email']) ?></span>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="info-box">
                <h3>Pay Period</h3>
                <div class="info-row">
                    <span class="info-label">Month:</span>
                    <span class="info-value"><?= $month_name ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Year:</span>
                    <span class="info-value"><?= $year ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Status:</span>
                    <span class="info-value">
                        <span class="status-badge status-<?= $salary['status'] ?>">
                            <?= ucfirst($salary['status']) ?>
                        </span>
                    </span>
                </div>
            </div>
        </div>
        
        <div class="salary-breakdown">
            <h3>Salary Breakdown</h3>
            <table class="breakdown-table">
                <tr>
                    <td>Gross Salary</td>
                    <td>৳<?= number_format($salary['gross_salary'], 2) ?></td>
                </tr>
                <tr>
                    <td>Monthly Progress</td>
                    <td><?= number_format($salary['monthly_progress'], 2) ?>%</td>
                </tr>
                <tr>
                    <td>Profit Fund Deduction</td>
                    <td>- ৳<?= number_format($salary['profit_fund'], 2) ?></td>
                </tr>
                <?php if ($salary['advances_deducted'] > 0): ?>
                <tr>
                    <td>Advances Deducted</td>
                    <td>- ৳<?= number_format($salary['advances_deducted'], 2) ?></td>
                </tr>
                <?php endif; ?>
                <tr class="total-row">
                    <td>Net Payable</td>
                    <td>৳<?= number_format($salary['net_payable'], 2) ?></td>
                </tr>
            </table>
        </div>
        
        <div class="footer">
            <p><strong>Generated on:</strong> <?= date('F d, Y \a\t h:i A') ?></p>
            <p>This is a computer-generated document. No signature required.</p>
        </div>
    </div>
    
            </div>
        </div>
    </div>
</div>

<script>
    // Auto-trigger print dialog on load (optional - comment out if not desired)
    // window.onload = function() {
    //     window.print();
    // };
</script>

<?php
    include __DIR__ . '/../includes/footer.php';
    exit;
}
