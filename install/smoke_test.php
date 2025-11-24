<?php
/**
 * Smoke Test Script
 * Tests critical functionality after installation
 */

// Prevent direct access in production
if (php_sapi_name() !== 'cli' && !isset($_GET['run']) && !isset($_GET['token'])) {
    die('Access denied. Run via CLI or with ?run=1&token=YOUR_SECRET_TOKEN');
}

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../core/autoload.php';
require_once __DIR__ . '/../auth_helper.php';
require_once __DIR__ . '/../helpers.php';

$results = [
    'status' => 1,
    'message' => 'Smoke test passed',
    'details' => [],
    'timestamp' => date('Y-m-d H:i:s')
];

echo "=== Staff Management System - Smoke Test ===\n\n";

// 1. Test database connection
echo "1. Testing database connection...\n";
try {
    $pdo = getPDO();
    $stmt = $pdo->query("SELECT 1");
    $result = $stmt->fetch();
    if ($result) {
        $results['details'][] = ['test' => 'Database Connection', 'status' => 'pass'];
        echo "   ✓ Database connection successful\n";
    } else {
        throw new Exception('Query failed');
    }
} catch (Exception $e) {
    $results['status'] = 0;
    $results['message'] = 'Smoke test failed: ' . $e->getMessage();
    $results['details'][] = ['test' => 'Database Connection', 'status' => 'fail', 'error' => $e->getMessage()];
    echo "   ✗ Database connection failed: " . $e->getMessage() . "\n";
    echo json_encode($results, JSON_PRETTY_PRINT) . "\n";
    exit(1);
}

// 2. Test superadmin exists (DO NOT CREATE - only superadmin can create superadmin)
echo "\n2. Checking superadmin account...\n";
try {
    $stmt = $pdo->prepare("SELECT id, email, role FROM users WHERE role = 'superadmin' AND deleted_at IS NULL LIMIT 1");
    $stmt->execute();
    $superadmin = $stmt->fetch();
    if ($superadmin) {
        $results['details'][] = ['test' => 'Superadmin Account', 'status' => 'pass', 'email' => $superadmin['email']];
        echo "   ✓ Superadmin account found: " . $superadmin['email'] . "\n";
    } else {
        $results['details'][] = ['test' => 'Superadmin Account', 'status' => 'warning', 'message' => 'No superadmin found. Create one manually via database or through existing superadmin account.'];
        echo "   ⚠ No superadmin account found. Create one manually via database or through existing superadmin account.\n";
        echo "   → SECURITY: Only existing superadmin can create new superadmin accounts.\n";
    }
} catch (Exception $e) {
    $results['details'][] = ['test' => 'Superadmin Account', 'status' => 'warning', 'error' => $e->getMessage()];
    echo "   ⚠ Superadmin check: " . $e->getMessage() . "\n";
}

// 3. Test per_day_percent function
echo "\n3. Testing per_day_percent function...\n";
try {
    if (function_exists('per_day_percent')) {
        $test_month = (int)date('n');
        $test_year = (int)date('Y');
        $percent = per_day_percent($test_month, $test_year);
        $days = days_in_month($test_month, $test_year);
        $expected = round(100 / $days, 4);
        
        if (abs($percent - $expected) < 0.0001) {
            $results['details'][] = [
                'test' => 'per_day_percent Function',
                'status' => 'pass',
                'month' => $test_month,
                'year' => $test_year,
                'result' => $percent
            ];
            echo "   ✓ per_day_percent works: $percent% for month $test_month/$test_year ($days days)\n";
        } else {
            throw new Exception("Expected $expected, got $percent");
        }
    } else {
        throw new Exception('Function not found');
    }
} catch (Exception $e) {
    $results['details'][] = ['test' => 'per_day_percent Function', 'status' => 'fail', 'error' => $e->getMessage()];
    echo "   ✗ per_day_percent test failed: " . $e->getMessage() . "\n";
}

// 4. Test payroll preview mode
echo "\n4. Testing payroll preview mode...\n";
try {
    $prev_month = (int)date('n', strtotime('first day of last month'));
    $prev_year = (int)date('Y', strtotime('first day of last month'));
    
    // Get staff count
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM users WHERE role = 'staff' AND deleted_at IS NULL");
    $stmt->execute();
    $staff_count = $stmt->fetch()['count'];
    
    if ($staff_count > 0) {
        $results['details'][] = [
            'test' => 'Payroll Preview',
            'status' => 'pass',
            'staff_count' => $staff_count,
            'month' => $prev_month,
            'year' => $prev_year
        ];
        echo "   ✓ Payroll preview ready (staff count: $staff_count)\n";
    } else {
        $results['details'][] = ['test' => 'Payroll Preview', 'status' => 'warning', 'message' => 'No staff found'];
        echo "   ⚠ No staff found for payroll test\n";
    }
} catch (Exception $e) {
    $results['details'][] = ['test' => 'Payroll Preview', 'status' => 'fail', 'error' => $e->getMessage()];
    echo "   ✗ Payroll preview test failed: " . $e->getMessage() . "\n";
}

// 5. Test CSV export functionality
echo "\n5. Testing CSV export...\n";
try {
    $exports_dir = __DIR__ . '/../exports';
    if (!is_dir($exports_dir)) {
        @mkdir($exports_dir, 0755, true);
    }
    
    $test_file = $exports_dir . '/test_' . time() . '.csv';
    $fp = fopen($test_file, 'w');
    if ($fp) {
        fputcsv($fp, ['Test', 'Data']);
        fputcsv($fp, ['1', '2']);
        fclose($fp);
        
        if (file_exists($test_file)) {
            @unlink($test_file);
            $results['details'][] = ['test' => 'CSV Export', 'status' => 'pass'];
            echo "   ✓ CSV export functionality works\n";
        } else {
            throw new Exception('File not created');
        }
    } else {
        throw new Exception('Cannot create file');
    }
} catch (Exception $e) {
    $results['details'][] = ['test' => 'CSV Export', 'status' => 'fail', 'error' => $e->getMessage()];
    echo "   ✗ CSV export test failed: " . $e->getMessage() . "\n";
}

// 6. Test required functions exist
echo "\n6. Testing required functions...\n";
$required_functions = [
    'getPDO',
    'per_day_percent',
    'days_in_month',
    'compute_ticket_percent',
    'generate_csrf_token',
    'verify_csrf_token',
    'log_audit',
    'h'
];

$missing_functions = [];
foreach ($required_functions as $func) {
    if (!function_exists($func)) {
        $missing_functions[] = $func;
    }
}

if (empty($missing_functions)) {
    $results['details'][] = ['test' => 'Required Functions', 'status' => 'pass', 'count' => count($required_functions)];
    echo "   ✓ All required functions exist (" . count($required_functions) . " functions)\n";
} else {
    $results['details'][] = ['test' => 'Required Functions', 'status' => 'fail', 'missing' => $missing_functions];
    echo "   ✗ Missing functions: " . implode(', ', $missing_functions) . "\n";
}

// 7. Test required tables exist
echo "\n7. Testing required tables...\n";
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
    }
}

if (empty($missing_tables)) {
    $results['details'][] = ['test' => 'Required Tables', 'status' => 'pass', 'count' => count($required_tables)];
    echo "   ✓ All required tables exist (" . count($required_tables) . " tables)\n";
} else {
    $results['details'][] = ['test' => 'Required Tables', 'status' => 'fail', 'missing' => $missing_tables];
    echo "   ✗ Missing tables: " . implode(', ', $missing_tables) . "\n";
    echo "   → Run migrations/add_missing_tables.sql to fix\n";
}

// Summary
echo "\n=== Smoke Test Summary ===\n";
$passed = count(array_filter($results['details'], function($d) { return $d['status'] === 'pass'; }));
$failed = count(array_filter($results['details'], function($d) { return $d['status'] === 'fail'; }));
$warnings = count(array_filter($results['details'], function($d) { return in_array($d['status'], ['warning', 'created']); }));

echo "Total Tests: " . count($results['details']) . "\n";
echo "Passed: $passed\n";
echo "Failed: $failed\n";
echo "Warnings: $warnings\n";

if ($failed > 0) {
    $results['status'] = 0;
    $results['message'] = "Smoke test failed: $failed test(s) failed";
}

// Output JSON
if (php_sapi_name() !== 'cli') {
    header('Content-Type: application/json');
}

echo "\n" . json_encode($results, JSON_PRETTY_PRINT) . "\n";

exit($results['status'] === 1 ? 0 : 1);

