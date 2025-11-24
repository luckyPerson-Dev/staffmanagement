<?php
/**
 * Comprehensive Project Audit Script
 * Checks all modules, files, syntax, database, and features
 */

// Prevent direct access in production
if (php_sapi_name() !== 'cli' && !isset($_GET['run']) && !isset($_GET['token'])) {
    die('Access denied. Run via CLI or with ?run=1&token=YOUR_SECRET_TOKEN');
}

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

// Load config
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../core/autoload.php';
require_once __DIR__ . '/../helpers.php';

$audit_report = [
    'timestamp' => date('Y-m-d H:i:s'),
    'checks_performed' => [],
    'files_inspected' => [],
    'issues_found' => [],
    'fixes_applied' => [],
    'sql_migrations_executed' => [],
    'tests_passed' => [],
    'tests_failed' => [],
    'manual_actions_recommended' => []
];

// Helper function to add check result
function add_check($name, $status, $details = '') {
    global $audit_report;
    $audit_report['checks_performed'][] = [
        'name' => $name,
        'status' => $status, // 'pass', 'fail', 'warning'
        'details' => $details,
        'timestamp' => date('Y-m-d H:i:s')
    ];
}

// Helper function to add issue
function add_issue($severity, $description, $file = null, $line = null) {
    global $audit_report;
    $audit_report['issues_found'][] = [
        'severity' => $severity, // 'critical', 'high', 'medium', 'low'
        'description' => $description,
        'file' => $file,
        'line' => $line,
        'timestamp' => date('Y-m-d H:i:s')
    ];
}

// Helper function to add fix
function add_fix($file, $description) {
    global $audit_report;
    $audit_report['fixes_applied'][] = [
        'file' => $file,
        'description' => $description,
        'timestamp' => date('Y-m-d H:i:s')
    ];
}

echo "=== Staff Management System - Comprehensive Audit ===\n\n";

// 1. Check PHP Syntax
echo "1. Checking PHP syntax...\n";
$php_files = [];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__ . '/..'));
foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'php' && 
        strpos($file->getPathname(), '/vendor/') === false &&
        strpos($file->getPathname(), '/node_modules/') === false) {
        $php_files[] = $file->getPathname();
    }
}

$syntax_errors = [];
foreach ($php_files as $file) {
    $output = [];
    $return_var = 0;
    exec("php -l " . escapeshellarg($file) . " 2>&1", $output, $return_var);
    if ($return_var !== 0) {
        $syntax_errors[] = [
            'file' => $file,
            'error' => implode("\n", $output)
        ];
    }
    $audit_report['files_inspected'][] = $file;
}

if (empty($syntax_errors)) {
    add_check('PHP Syntax Check', 'pass', count($php_files) . ' files checked');
    echo "   ✓ All PHP files have valid syntax (" . count($php_files) . " files)\n";
} else {
    add_check('PHP Syntax Check', 'fail', count($syntax_errors) . ' errors found');
    foreach ($syntax_errors as $error) {
        add_issue('critical', 'Syntax error: ' . $error['error'], $error['file']);
        echo "   ✗ Syntax error in: " . $error['file'] . "\n";
    }
}

// 2. Check Database Connection
echo "\n2. Testing database connection...\n";
try {
    $pdo = getPDO();
    $stmt = $pdo->query("SELECT 1");
    $result = $stmt->fetch();
    if ($result) {
        add_check('Database Connection', 'pass', 'Successfully connected to database');
        echo "   ✓ Database connection successful\n";
    } else {
        throw new Exception('Database query failed');
    }
} catch (Exception $e) {
    add_check('Database Connection', 'fail', $e->getMessage());
    add_issue('critical', 'Database connection failed: ' . $e->getMessage());
    echo "   ✗ Database connection failed: " . $e->getMessage() . "\n";
}

// 3. Check for duplicate getPDO() declarations
echo "\n3. Checking for duplicate function declarations...\n";
$getpdo_count = 0;
foreach ($php_files as $file) {
    $content = file_get_contents($file);
    if (preg_match('/function\s+getPDO\s*\(/', $content)) {
        $getpdo_count++;
        if ($getpdo_count > 1 && $file !== __DIR__ . '/../core/Database.php') {
            add_issue('high', 'Duplicate getPDO() function found', $file);
            echo "   ⚠ Duplicate getPDO() in: " . $file . "\n";
        }
    }
}

if ($getpdo_count <= 1) {
    add_check('Duplicate Functions Check', 'pass', 'No duplicate getPDO() found');
} else {
    add_check('Duplicate Functions Check', 'warning', 'Multiple getPDO() declarations found');
}

// 4. Check for duplicate constant definitions
echo "\n4. Checking for duplicate constant definitions...\n";
$constants_checked = ['ROOT_PATH', 'BASE_URL', 'DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS'];
$constant_issues = [];
foreach ($constants_checked as $const) {
    $count = 0;
    foreach ($php_files as $file) {
        $content = file_get_contents($file);
        if (preg_match('/define\s*\(\s*[\'"]' . preg_quote($const, '/') . '[\'"]/', $content)) {
            $count++;
            if ($count > 1 && !preg_match('/if\s*\(\s*!\s*defined\s*\(/', $content)) {
                $constant_issues[] = ['constant' => $const, 'file' => $file];
            }
        }
    }
}

if (empty($constant_issues)) {
    add_check('Duplicate Constants Check', 'pass', 'All constants properly guarded');
    echo "   ✓ No unprotected duplicate constants found\n";
} else {
    add_check('Duplicate Constants Check', 'warning', count($constant_issues) . ' unprotected constants found');
    foreach ($constant_issues as $issue) {
        add_issue('medium', 'Unprotected constant definition: ' . $issue['constant'], $issue['file']);
        echo "   ⚠ Unprotected constant in: " . $issue['file'] . "\n";
    }
}

// 5. Check required modules/files exist
echo "\n5. Checking required modules and files...\n";
$required_files = [
    'auth_helper.php',
    'config.php',
    'db_connect.php',
    'helpers.php',
    'core/Database.php',
    'core/CSRF.php',
    'core/Security.php',
    'core/compute_helpers.php',
    'core/autoload.php',
    'progress/add.php',
    'progress/daily_progress_edit.php',
    'progress/edit.php',
    'salary/index.php',
    'payroll/run_payroll.php',
    'admin/tickets.php',
    'admin/staff_tickets.php',
    'reports/daily_progress.php',
    'reports/staff_monthly_history.php',
    'advances/index.php',
    'advances/request.php',
    'profit_fund/index.php',
    'attendance/clock_in.php',
    'attendance/clock_out.php',
    'api/calculate_progress.php',
    'api/save_progress.php',
    'api/compute/per_day_percent.php',
    'includes/header.php',
    'includes/sidebar.php',
    'includes/footer.php'
];

$missing_files = [];
foreach ($required_files as $file) {
    $full_path = __DIR__ . '/../' . $file;
    if (!file_exists($full_path)) {
        $missing_files[] = $file;
        add_issue('high', 'Required file missing: ' . $file);
        echo "   ✗ Missing: " . $file . "\n";
    }
}

if (empty($missing_files)) {
    add_check('Required Files Check', 'pass', count($required_files) . ' files found');
    echo "   ✓ All required files exist\n";
} else {
    add_check('Required Files Check', 'fail', count($missing_files) . ' files missing');
}

// 6. Check database tables
echo "\n6. Checking database tables...\n";
try {
    $pdo = getPDO();
    $required_tables = [
        'users', 'daily_progress', 'salary_history', 'monthly_tickets', 
        'staff_tickets', 'advances', 'profit_fund', 'profit_fund_balance',
        'payroll_run_log', 'audit_logs', 'settings', 'customers', 'teams',
        'team_members', 'customer_groups', 'notifications'
    ];
    
    $missing_tables = [];
    foreach ($required_tables as $table) {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() === 0) {
            $missing_tables[] = $table;
            add_issue('critical', 'Required table missing: ' . $table);
            echo "   ✗ Missing table: " . $table . "\n";
        }
    }
    
    if (empty($missing_tables)) {
        add_check('Database Tables Check', 'pass', count($required_tables) . ' tables found');
        echo "   ✓ All required tables exist\n";
    } else {
        add_check('Database Tables Check', 'fail', count($missing_tables) . ' tables missing');
    }
} catch (Exception $e) {
    add_check('Database Tables Check', 'fail', $e->getMessage());
    echo "   ✗ Error checking tables: " . $e->getMessage() . "\n";
}

// 7. Check CSRF implementation
echo "\n7. Checking CSRF protection...\n";
$csrf_files = ['core/CSRF.php', 'core/Security.php', 'auth_helper.php'];
$csrf_found = false;
foreach ($csrf_files as $file) {
    $full_path = __DIR__ . '/../' . $file;
    if (file_exists($full_path)) {
        $content = file_get_contents($full_path);
        if (preg_match('/generate.*csrf|verify.*csrf/i', $content)) {
            $csrf_found = true;
            break;
        }
    }
}

if ($csrf_found) {
    add_check('CSRF Protection', 'pass', 'CSRF functions found');
    echo "   ✓ CSRF protection functions exist\n";
} else {
    add_check('CSRF Protection', 'fail', 'CSRF functions not found');
    add_issue('high', 'CSRF protection functions missing');
    echo "   ✗ CSRF protection functions not found\n";
}

// 8. Check exports directory
echo "\n8. Checking exports directory...\n";
$exports_dir = __DIR__ . '/../exports';
if (!is_dir($exports_dir)) {
    @mkdir($exports_dir, 0755, true);
    add_fix('exports/', 'Created exports directory');
    echo "   ✓ Created exports directory\n";
} else {
    echo "   ✓ Exports directory exists\n";
}

// Check .htaccess in exports
$htaccess_path = $exports_dir . '/.htaccess';
if (!file_exists($htaccess_path)) {
    file_put_contents($htaccess_path, "Deny from all\n");
    add_fix('exports/.htaccess', 'Created .htaccess to protect exports');
    echo "   ✓ Created .htaccess in exports\n";
}

// 9. Check logs directory
echo "\n9. Checking logs directory...\n";
$logs_dir = __DIR__ . '/../logs';
if (!is_dir($logs_dir)) {
    @mkdir($logs_dir, 0755, true);
    add_fix('logs/', 'Created logs directory');
    echo "   ✓ Created logs directory\n";
}

// 10. Test per_day_percent function
echo "\n10. Testing per_day_percent function...\n";
try {
    if (function_exists('per_day_percent')) {
        $test_cases = [
            [2, 2024, 29], // February 2024 (leap year)
            [2, 2023, 28], // February 2023 (non-leap)
            [4, 2024, 30], // April
            [1, 2024, 31], // January
        ];
        
        $all_passed = true;
        foreach ($test_cases as $test) {
            list($month, $year, $expected_days) = $test;
            $days = days_in_month($month, $year);
            $percent = per_day_percent($month, $year);
            $expected_percent = round(100 / $expected_days, 4);
            
            if ($days !== $expected_days || abs($percent - $expected_percent) > 0.0001) {
                $all_passed = false;
                add_issue('medium', "per_day_percent test failed for $month/$year");
                echo "   ✗ Test failed for month $month/$year\n";
            }
        }
        
        if ($all_passed) {
            add_check('per_day_percent Function', 'pass', 'All test cases passed');
            echo "   ✓ per_day_percent function works correctly\n";
        } else {
            add_check('per_day_percent Function', 'fail', 'Some test cases failed');
        }
    } else {
        add_check('per_day_percent Function', 'fail', 'Function not found');
        add_issue('high', 'per_day_percent function not available');
        echo "   ✗ per_day_percent function not found\n";
    }
} catch (Exception $e) {
    add_check('per_day_percent Function', 'fail', $e->getMessage());
    echo "   ✗ Error testing per_day_percent: " . $e->getMessage() . "\n";
}

// Summary
echo "\n=== Audit Summary ===\n";
$total_checks = count($audit_report['checks_performed']);
$passed = count(array_filter($audit_report['checks_performed'], function($c) { return $c['status'] === 'pass'; }));
$failed = count(array_filter($audit_report['checks_performed'], function($c) { return $c['status'] === 'fail'; }));
$warnings = count(array_filter($audit_report['checks_performed'], function($c) { return $c['status'] === 'warning'; }));

echo "Total Checks: $total_checks\n";
echo "Passed: $passed\n";
echo "Failed: $failed\n";
echo "Warnings: $warnings\n";
echo "Issues Found: " . count($audit_report['issues_found']) . "\n";
echo "Fixes Applied: " . count($audit_report['fixes_applied']) . "\n";

// Save audit report
$report_file = __DIR__ . '/audit_report_' . date('Y-m-d_His') . '.json';
file_put_contents($report_file, json_encode($audit_report, JSON_PRETTY_PRINT));
echo "\nAudit report saved to: " . $report_file . "\n";

if (php_sapi_name() !== 'cli') {
    header('Content-Type: application/json');
    echo json_encode($audit_report, JSON_PRETTY_PRINT);
}

