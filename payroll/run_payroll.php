<?php
/**
 * payroll/run_payroll.php
 * Calculate and generate payroll for a given month/year
 * Supports POST requests with preview/run modes and CSRF validation
 * Also supports CLI execution and GET requests (legacy)
 */

require_once __DIR__ . '/../core/autoload.php';
require_once __DIR__ . '/../auth_helper.php';
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../modules/notification.php';

// Handle POST requests (new workflow)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_role(['admin', 'superadmin']);
    
    // CSRF validation
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf_token)) {
        $_SESSION['payroll_error'] = 'Invalid security token. Please try again.';
        header('Location: ' . BASE_URL . '/salary/index.php');
        exit;
    }
    
    // Get inputs
    $month = isset($_POST['month']) ? intval($_POST['month']) : 0;
    $year = isset($_POST['year']) ? intval($_POST['year']) : 0;
    $mode = $_POST['mode'] ?? 'preview';
    
    // Validate inputs
    if ($month < 1 || $month > 12 || $year < 2000 || $year > 2100) {
        $_SESSION['payroll_error'] = 'Invalid month or year selected.';
        header('Location: ' . BASE_URL . '/salary/index.php');
        exit;
    }
    
    // Handle force mode
    $force = ($mode === 'force');
    if ($force) {
        $mode = 'run'; // Force mode is essentially run mode with override
    }
    
    if (!in_array($mode, ['preview', 'run'])) {
        $_SESSION['payroll_error'] = 'Invalid mode selected.';
        header('Location: ' . BASE_URL . '/salary/index.php');
        exit;
    }
    
    $pdo = getPDO();
    
    // Check idempotency for run mode (unless force)
    if ($mode === 'run' && !$force) {
        $stmt = $pdo->prepare("
            SELECT id FROM payroll_run_log 
            WHERE month = ? AND year = ?
            LIMIT 1
        ");
        $stmt->execute([$month, $year]);
        if ($stmt->fetch()) {
            $_SESSION['payroll_error'] = "Payroll already processed for this month. Use Force Rerun to override.";
            header('Location: ' . BASE_URL . '/salary/index.php?month=' . $month . '&year=' . $year);
            exit;
        }
    }
    
    // If force mode, archive/replace existing salary_history entries
    if ($mode === 'run' && $force) {
        $pdo->beginTransaction();
        try {
            // Delete existing salary_history entries for this month/year
            // This effectively replaces them with new entries
            $stmt = $pdo->prepare("
                DELETE FROM salary_history 
                WHERE month = ? AND year = ?
            ");
            $stmt->execute([$month, $year]);
            
            // Also delete existing profit_fund entries for this month/year
            $stmt = $pdo->prepare("
                DELETE FROM profit_fund 
                WHERE month = ? AND year = ?
            ");
            $stmt->execute([$month, $year]);
            
            // Delete existing payroll_run_log entry if exists
            $stmt = $pdo->prepare("
                DELETE FROM payroll_run_log 
                WHERE month = ? AND year = ?
            ");
            $stmt->execute([$month, $year]);
            
            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['payroll_error'] = 'Error clearing existing payroll data: ' . $e->getMessage();
            header('Location: ' . BASE_URL . '/salary/index.php?month=' . $month . '&year=' . $year);
            exit;
        }
    }
    
    // Calculate per-day percent for the month
    $per_day_percent = per_day_percent($month, $year);
    $days_in_month = days_in_month($month, $year);
    $start_date = sprintf('%04d-%02d-01', $year, $month);
    $end_date = sprintf('%04d-%02d-%02d', $year, $month, $days_in_month);
    
    // Get settings
    $profit_fund_percent = floatval(get_setting('profit_fund_percent', 5));
    $global_support_penalty_percent = floatval(get_setting('global_support_penalty_percent', 0));
    
    // Get all active staff (exclude deleted users)
    $stmt = $pdo->prepare("
        SELECT u.id, u.monthly_salary, 
               (SELECT tm.team_id FROM team_members tm WHERE tm.user_id = u.id LIMIT 1) as team_id
        FROM users u
        WHERE u.role = 'staff' 
          AND (u.status = 'active' OR u.status IS NULL)
          AND u.deleted_at IS NULL
        ORDER BY u.id
    ");
    $stmt->execute();
    $staff = $stmt->fetchAll();
    
    $processed = 0;
    $errors = [];
    $total_salary_cost = 0;
    $total_profit_fund_added = 0;
    $preview_data = [];
    
    // Start transaction only for run mode
    if ($mode === 'run') {
        $pdo->beginTransaction();
    }
    
    try {
        foreach ($staff as $user) {
            try {
                $user_id = (int)$user['id'];
                $gross_salary = floatval($user['monthly_salary']);
                $team_id = $user['team_id'] ? (int)$user['team_id'] : null;
                
                // Calculate monthly progress (sum of daily progress)
                $stmt = $pdo->prepare("
                    SELECT SUM(progress_percent) as monthly_progress_sum
                    FROM daily_progress 
                    WHERE user_id = ? AND date >= ? AND date <= ?
                ");
                $stmt->execute([$user_id, $start_date, $end_date]);
                $progress_result = $stmt->fetch();
                $final_monthly_progress = floatval($progress_result['monthly_progress_sum'] ?? 0);
                $final_monthly_progress = max(0, round($final_monthly_progress, 2));
                
                // Calculate salary breakdown
                $profit_fund_amount = round($gross_salary * ($profit_fund_percent / 100), 2);
                $payable_before_advance = round(($gross_salary * ($final_monthly_progress / 100)) - $profit_fund_amount, 2);
                
                // ADVANCE AUTO-DEDUCTION LOGIC
                // Check if user has active auto-deduction schedule
                $stmt = $pdo->prepare("
                    SELECT id, monthly_deduction, remaining_due, total_advance
                    FROM advance_auto_deductions
                    WHERE user_id = ? AND status = 'active'
                    ORDER BY created_at DESC
                    LIMIT 1
                ");
                $stmt->execute([$user_id]);
                $auto_deduction = $stmt->fetch();
                
                $advances_to_deduct = 0;
                
                if ($auto_deduction) {
                    // Use auto-deduction schedule
                    $monthly_deduction = floatval($auto_deduction['monthly_deduction']);
                    $remaining_due = floatval($auto_deduction['remaining_due']);
                    
                    // Deduct min(monthly_deduction, remaining_due)
                    $advances_to_deduct = min($monthly_deduction, $remaining_due);
                    $advances_to_deduct = max(0, round($advances_to_deduct, 2));
                    
                    // Update remaining_due (only in run mode)
                    if ($mode === 'run') {
                        $new_remaining_due = max(0, round($remaining_due - $advances_to_deduct, 2));
                        $new_status = ($new_remaining_due <= 0) ? 'completed' : 'active';
                        
                        $stmt = $pdo->prepare("
                            UPDATE advance_auto_deductions
                            SET remaining_due = ?,
                                status = ?,
                                updated_at = UTC_TIMESTAMP()
                            WHERE id = ?
                        ");
                        $stmt->execute([$new_remaining_due, $new_status, $auto_deduction['id']]);
                    }
                } else {
                    // Fallback to old logic: manual advance deduction
                    // Get approved advances
                    $stmt = $pdo->prepare("
                        SELECT SUM(amount) as total_advances
                        FROM advances
                        WHERE user_id = ? AND status = 'approved'
                    ");
                    $stmt->execute([$user_id]);
                    $advances_result = $stmt->fetch();
                    $total_approved_advances = floatval($advances_result['total_advances'] ?? 0);
                    
                    // Get total already deducted
                    $stmt = $pdo->prepare("
                        SELECT SUM(advances_deducted) as total_deducted
                        FROM salary_history
                        WHERE user_id = ? AND (month != ? OR year != ?)
                    ");
                    $stmt->execute([$user_id, $month, $year]);
                    $deducted_result = $stmt->fetch();
                    $total_already_deducted = floatval($deducted_result['total_deducted'] ?? 0);
                    
                    $advances_to_deduct = max(0, $total_approved_advances - $total_already_deducted);
                }
                
                $net_payable = max(0, round($payable_before_advance - $advances_to_deduct, 2));
                
                // Store preview data
                $preview_data[] = [
                    'user_id' => $user_id,
                    'gross_salary' => $gross_salary,
                    'monthly_progress' => $final_monthly_progress,
                    'profit_fund' => $profit_fund_amount,
                    'advances_deducted' => $advances_to_deduct,
                    'net_payable' => $net_payable
                ];
                
                // Only persist if run mode
                if ($mode === 'run') {
                    // Since we deleted existing entries in force mode, always insert new
                    $created_by = current_user()['id'] ?? 1;
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO salary_history
                        (user_id, month, year, gross_salary, profit_fund, monthly_progress,
                         payable_before_advance, advances_deducted, net_payable, status, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', UTC_TIMESTAMP())
                    ");
                    $stmt->execute([
                        $user_id, $month, $year, $gross_salary, $profit_fund_amount, $final_monthly_progress,
                        $payable_before_advance, $advances_to_deduct, $net_payable
                    ]);
                    $salary_id = $pdo->lastInsertId();
                    
                    // Create/Update profit_fund record (but DON'T add to balance until salary is approved)
                    $stmt = $pdo->prepare("
                        INSERT INTO profit_fund (user_id, month, year, amount, created_at)
                        VALUES (?, ?, ?, ?, UTC_TIMESTAMP())
                        ON DUPLICATE KEY UPDATE amount = ?
                    ");
                    $stmt->execute([$user_id, $month, $year, $profit_fund_amount, $profit_fund_amount]);
                    
                    log_audit(
                        $created_by, 
                        'create', 
                        'salary_history', 
                        $salary_id, 
                        "Generated payroll for user $user_id - Month: $month/$year, Progress: $final_monthly_progress%, Net: $net_payable" . ($force ? ' [FORCE RERUN]' : '')
                    );
                }
                
                $processed++;
                $total_salary_cost += $net_payable;
                $total_profit_fund_added += $profit_fund_amount;
                
            } catch (Exception $e) {
                $errors[] = [
                    'user_id' => $user_id ?? null,
                    'error' => $e->getMessage()
                ];
                error_log("Payroll error for user {$user_id}: " . $e->getMessage());
            }
        }
        
        if ($mode === 'run') {
            $pdo->commit();
            
            // Create payroll_run_log entry with is_force flag
            $run_by = current_user()['id'] ?? 1;
            try {
                // Try to insert with is_force column if it exists
                $stmt = $pdo->prepare("
                    INSERT INTO payroll_run_log (month, year, run_by, total_staff_processed, total_salary_cost, is_force, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())
                ");
                $stmt->execute([$month, $year, $run_by, $processed, round($total_salary_cost, 2), $force ? 1 : 0]);
            } catch (Exception $e) {
                // If is_force column doesn't exist, insert without it
                $stmt = $pdo->prepare("
                    INSERT INTO payroll_run_log (month, year, run_by, total_staff_processed, total_salary_cost, created_at)
                    VALUES (?, ?, ?, ?, ?, UTC_TIMESTAMP())
                ");
                $stmt->execute([$month, $year, $run_by, $processed, round($total_salary_cost, 2)]);
            }
            
            // Send notifications
            try {
                $notificationSystem = new NotificationSystem();
                $stmt = $pdo->prepare("
                    SELECT id FROM users 
                    WHERE role IN ('superadmin', 'admin') 
                    AND deleted_at IS NULL
                ");
                $stmt->execute();
                $admins = $stmt->fetchAll(PDO::FETCH_COLUMN);
                
                $month_name = date('F', mktime(0, 0, 0, $month, 1));
                $notification_title = "Payroll Processed Successfully";
                $notification_message = sprintf(
                    "Payroll for %s %d has been processed successfully.%s\n\n" .
                    "Staff Processed: %d\n" .
                    "Total Salary Cost: %s",
                    $month_name,
                    $year,
                    $force ? " [FORCE RERUN]" : "",
                    $processed,
                    number_format(round($total_salary_cost, 2), 2)
                );
                
                $notification_link = BASE_URL . '/salary/index.php?month=' . $month . '&year=' . $year;
                
                foreach ($admins as $admin_id) {
                    $notificationSystem->create(
                        $admin_id,
                        'success',
                        $notification_title,
                        $notification_message,
                        $notification_link
                    );
                }
            } catch (Exception $e) {
                error_log("Failed to create payroll notification: " . $e->getMessage());
            }
            
            $_SESSION['payroll_success'] = sprintf(
                "Payroll processed successfully for %s %d. %d staff processed. Total cost: ৳%s%s",
                date('F', mktime(0, 0, 0, $month, 1)),
                $year,
                $processed,
                number_format(round($total_salary_cost, 2), 2),
                $force ? ' [FORCE RERUN]' : ''
            );
        } else {
            // Preview mode - return summary
            $preview_html = '<div class="table-responsive"><table class="table table-sm"><thead><tr><th>Staff ID</th><th>Gross Salary</th><th>Progress %</th><th>Net Payable</th></tr></thead><tbody>';
            foreach ($preview_data as $row) {
                $preview_html .= sprintf(
                    '<tr><td>%d</td><td>৳%s</td><td>%s%%</td><td>৳%s</td></tr>',
                    $row['user_id'],
                    number_format($row['gross_salary'], 2),
                    number_format($row['monthly_progress'], 2),
                    number_format($row['net_payable'], 2)
                );
            }
            $preview_html .= '</tbody></table></div>';
            $preview_html .= sprintf(
                '<p class="mb-0"><strong>Total Staff:</strong> %d | <strong>Total Estimated Payable:</strong> ৳%s</p>',
                $processed,
                number_format(round($total_salary_cost, 2), 2)
            );
            
            $_SESSION['payroll_preview'] = $preview_html;
        }
        
        header('Location: ' . BASE_URL . '/salary/index.php?month=' . $month . '&year=' . $year);
        exit;
        
    } catch (Exception $e) {
        if ($mode === 'run') {
            $pdo->rollBack();
        }
        error_log("Payroll error: " . $e->getMessage());
        $_SESSION['payroll_error'] = 'Payroll processing failed: ' . $e->getMessage();
        header('Location: ' . BASE_URL . '/salary/index.php?month=' . $month . '&year=' . $year);
        exit;
    }
}

// Legacy GET/CLI support (existing code)
// Allow CLI execution
if (php_sapi_name() === 'cli') {
    // Parse CLI arguments
    $force = in_array('--force', $argv ?? []);
    $month = null;
    $year = null;
    
    // Find month and year in arguments (skip --force)
    foreach ($argv ?? [] as $arg) {
        if ($arg === '--force') continue;
        if (is_numeric($arg)) {
            if ($month === null) {
                $month = intval($arg);
            } elseif ($year === null) {
                $year = intval($arg);
            }
        }
    }
    
    // Default to previous month if not provided
    if ($month === null || $year === null) {
        $prevMonth = date('n', strtotime('first day of last month'));
        $prevYear = date('Y', strtotime('first day of last month'));
        $month = $month ?? $prevMonth;
        $year = $year ?? $prevYear;
    }
    
    $_SESSION = []; // Mock session for CLI
    $_SESSION['user_id'] = 1; // Assume superadmin for CLI
} else {
    require_role(['superadmin']);
    
    // Default to previous month if not provided
    $month = isset($_GET['month']) ? intval($_GET['month']) : null;
    $year = isset($_GET['year']) ? intval($_GET['year']) : null;
    
    if ($month === null || $year === null) {
        $prevMonth = date('n', strtotime('first day of last month'));
        $prevYear = date('Y', strtotime('first day of last month'));
        $month = $month ?? $prevMonth;
        $year = $year ?? $prevYear;
    }
    
    $force = isset($_GET['force']) && $_GET['force'] == '1';
}

// Validate month/year
if ($month < 1 || $month > 12 || $year < 2000 || $year > 2100) {
    if (php_sapi_name() === 'cli') {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid month or year',
            'month' => $month,
            'year' => $year
        ], JSON_PRETTY_PRINT) . "\n";
        exit(1);
    } else {
        response_json(['success' => false, 'message' => 'Invalid month or year'], 400);
    }
}

$pdo = getPDO();

// Check if payroll already exists for this month/year (for information only, legacy mode)
if (!$force) {
    $stmt = $pdo->prepare("
        SELECT id FROM payroll_run_log 
        WHERE month = ? AND year = ?
        LIMIT 1
    ");
    $stmt->execute([$month, $year]);
    if ($stmt->fetch()) {
        if (php_sapi_name() === 'cli') {
            echo json_encode([
                'success' => false,
                'message' => "Payroll already processed for {$month}/{$year}. Use --force to override."
            ], JSON_PRETTY_PRINT) . "\n";
            exit(1);
        } else {
            response_json([
                'success' => false,
                'message' => "Payroll already processed for {$month}/{$year}. Use Force Rerun to override."
            ], 400);
        }
    }
}

// If force mode in legacy, clear existing data
if ($force) {
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("DELETE FROM salary_history WHERE month = ? AND year = ?");
        $stmt->execute([$month, $year]);
        
        $stmt = $pdo->prepare("DELETE FROM profit_fund WHERE month = ? AND year = ?");
        $stmt->execute([$month, $year]);
        
        $stmt = $pdo->prepare("DELETE FROM payroll_run_log WHERE month = ? AND year = ?");
        $stmt->execute([$month, $year]);
        
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        if (php_sapi_name() === 'cli') {
            echo json_encode(['success' => false, 'message' => 'Error clearing existing data: ' . $e->getMessage()], JSON_PRETTY_PRINT) . "\n";
            exit(1);
        } else {
            response_json(['success' => false, 'message' => 'Error clearing existing data: ' . $e->getMessage()], 400);
        }
    }
}

// Get settings
$profit_fund_percent = floatval(get_setting('profit_fund_percent', 5));
$global_support_penalty_percent = floatval(get_setting('global_support_penalty_percent', 0));

// Calculate per-day percent for the month
$per_day_percent = per_day_percent($month, $year);
$days_in_month = days_in_month($month, $year);

// Date range for the month
$start_date = sprintf('%04d-%02d-01', $year, $month);
$end_date = sprintf('%04d-%02d-%02d', $year, $month, $days_in_month);

try {
    $pdo->beginTransaction();
    
    // Get all active staff (exclude deleted users)
    $stmt = $pdo->prepare("
        SELECT u.id, u.monthly_salary, 
               (SELECT tm.team_id FROM team_members tm WHERE tm.user_id = u.id LIMIT 1) as team_id
        FROM users u
        WHERE u.role = 'staff' 
          AND (u.status = 'active' OR u.status IS NULL)
          AND u.deleted_at IS NULL
        ORDER BY u.id
    ");
    $stmt->execute();
    $staff = $stmt->fetchAll();
    
    $processed = 0;
    $errors = [];
    $total_salary_cost = 0;
    $total_profit_fund_added = 0;
    
    foreach ($staff as $user) {
        try {
            $user_id = (int)$user['id'];
            $gross_salary = floatval($user['monthly_salary']);
            $team_id = $user['team_id'] ? (int)$user['team_id'] : null;
            
            // Calculate monthly progress (sum of daily progress)
            $stmt = $pdo->prepare("
                SELECT SUM(progress_percent) as monthly_progress_sum
                FROM daily_progress 
                WHERE user_id = ? AND date >= ? AND date <= ?
            ");
            $stmt->execute([$user_id, $start_date, $end_date]);
            $progress_result = $stmt->fetch();
            $final_monthly_progress = floatval($progress_result['monthly_progress_sum'] ?? 0);
            $final_monthly_progress = max(0, round($final_monthly_progress, 2));
            
            // Calculate salary breakdown
            $profit_fund_amount = round($gross_salary * ($profit_fund_percent / 100), 2);
            $payable_before_advance = round(($gross_salary * ($final_monthly_progress / 100)) - $profit_fund_amount, 2);
            
            // ADVANCE AUTO-DEDUCTION LOGIC
            $stmt = $pdo->prepare("
                SELECT id, monthly_deduction, remaining_due, total_advance
                FROM advance_auto_deductions
                WHERE user_id = ? AND status = 'active'
                ORDER BY created_at DESC
                LIMIT 1
            ");
            $stmt->execute([$user_id]);
            $auto_deduction = $stmt->fetch();
            
            $advances_to_deduct = 0;
            
            if ($auto_deduction) {
                $monthly_deduction = floatval($auto_deduction['monthly_deduction']);
                $remaining_due = floatval($auto_deduction['remaining_due']);
                $advances_to_deduct = min($monthly_deduction, $remaining_due);
                $advances_to_deduct = max(0, round($advances_to_deduct, 2));
                
                $new_remaining_due = max(0, round($remaining_due - $advances_to_deduct, 2));
                $new_status = ($new_remaining_due <= 0) ? 'completed' : 'active';
                
                $stmt = $pdo->prepare("
                    UPDATE advance_auto_deductions
                    SET remaining_due = ?,
                        status = ?,
                        updated_at = UTC_TIMESTAMP()
                    WHERE id = ?
                ");
                $stmt->execute([$new_remaining_due, $new_status, $auto_deduction['id']]);
            } else {
                $stmt = $pdo->prepare("
                    SELECT SUM(amount) as total_advances
                    FROM advances
                    WHERE user_id = ? AND status = 'approved'
                ");
                $stmt->execute([$user_id]);
                $advances_result = $stmt->fetch();
                $total_approved_advances = floatval($advances_result['total_advances'] ?? 0);
                
                $stmt = $pdo->prepare("
                    SELECT SUM(advances_deducted) as total_deducted
                    FROM salary_history
                    WHERE user_id = ? AND (month != ? OR year != ?)
                ");
                $stmt->execute([$user_id, $month, $year]);
                $deducted_result = $stmt->fetch();
                $total_already_deducted = floatval($deducted_result['total_deducted'] ?? 0);
                
                $advances_to_deduct = max(0, $total_approved_advances - $total_already_deducted);
            }
            
            $net_payable = max(0, round($payable_before_advance - $advances_to_deduct, 2));
            
            // Insert salary_history
            $created_by = current_user()['id'] ?? 1;
            
            $stmt = $pdo->prepare("
                INSERT INTO salary_history
                (user_id, month, year, gross_salary, profit_fund, monthly_progress,
                 payable_before_advance, advances_deducted, net_payable, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', UTC_TIMESTAMP())
            ");
            $stmt->execute([
                $user_id, $month, $year, $gross_salary, $profit_fund_amount, $final_monthly_progress,
                $payable_before_advance, $advances_to_deduct, $net_payable
            ]);
            $salary_id = $pdo->lastInsertId();
            
            // Insert/Update profit_fund
            $stmt = $pdo->prepare("
                INSERT INTO profit_fund (user_id, month, year, amount, created_at)
                VALUES (?, ?, ?, ?, UTC_TIMESTAMP())
                ON DUPLICATE KEY UPDATE amount = ?
            ");
            $stmt->execute([$user_id, $month, $year, $profit_fund_amount, $profit_fund_amount]);
            
            log_audit(
                $created_by, 
                'create', 
                'salary_history', 
                $salary_id, 
                "Generated payroll for user $user_id - Month: $month/$year, Progress: $final_monthly_progress%, Net: $net_payable" . ($force ? ' [FORCE RERUN]' : '')
            );
            
            $processed++;
            $total_salary_cost += $net_payable;
            $total_profit_fund_added += $profit_fund_amount;
            
        } catch (Exception $e) {
            $errors[] = [
                'user_id' => $user_id ?? null,
                'error' => $e->getMessage()
            ];
            error_log("Payroll error for user {$user_id}: " . $e->getMessage());
        }
    }
    
    $pdo->commit();
    
    // Create payroll_run_log entry
    try {
        $run_by = current_user()['id'] ?? 1;
        $stmt = $pdo->prepare("
            INSERT INTO payroll_run_log (month, year, run_by, total_staff_processed, total_salary_cost, is_force, created_at)
            VALUES (?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())
        ");
        $stmt->execute([$month, $year, $run_by, $processed, round($total_salary_cost, 2), $force ? 1 : 0]);
    } catch (Exception $e) {
        // If is_force column doesn't exist, insert without it
        try {
            $run_by = current_user()['id'] ?? 1;
            $stmt = $pdo->prepare("
                INSERT INTO payroll_run_log (month, year, run_by, total_staff_processed, total_salary_cost, created_at)
                VALUES (?, ?, ?, ?, ?, UTC_TIMESTAMP())
            ");
            $stmt->execute([$month, $year, $run_by, $processed, round($total_salary_cost, 2)]);
        } catch (Exception $e2) {
            // Ignore if table doesn't exist
        }
    }
    
    // Create success notification
    try {
        $notificationSystem = new NotificationSystem();
        $stmt = $pdo->prepare("
            SELECT id FROM users 
            WHERE role IN ('superadmin', 'admin') 
            AND deleted_at IS NULL
        ");
        $stmt->execute();
        $admins = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $month_name = date('F', mktime(0, 0, 0, $month, 1));
        $notification_title = "Payroll Processed Successfully";
        $notification_message = sprintf(
            "Payroll for %s %d has been processed successfully.%s\n\n" .
            "Staff Processed: %d\n" .
            "Total Salary Cost: %s\n" .
            "Profit Fund Added: %s\n" .
            "Days in Month: %d\n" .
            "Per Day Percent: %.2f%%",
            $month_name,
            $year,
            $force ? " [FORCE RERUN]" : "",
            $processed,
            number_format(round($total_salary_cost, 2), 2),
            number_format(round($total_profit_fund_added, 2), 2),
            $days_in_month,
            $per_day_percent
        );
        
        $notification_link = BASE_URL . '/salary/index.php?month=' . $month . '&year=' . $year;
        
        foreach ($admins as $admin_id) {
            $notificationSystem->create(
                $admin_id,
                'success',
                $notification_title,
                $notification_message,
                $notification_link
            );
        }
    } catch (Exception $e) {
        error_log("Failed to create payroll notification: " . $e->getMessage());
    }
    
    // Prepare summary
    $summary = [
        'success' => true,
        'message' => "Payroll processed successfully for $month/$year" . ($force ? " [FORCE RERUN]" : ""),
        'month' => $month,
        'year' => $year,
        'total_staff_processed' => $processed,
        'total_salary_cost' => round($total_salary_cost, 2),
        'total_profit_fund_added' => round($total_profit_fund_added, 2),
        'per_day_percent' => $per_day_percent,
        'days_in_month' => $days_in_month,
        'force_rerun' => $force
    ];
    
    if (!empty($errors)) {
        $summary['errors'] = $errors;
        $summary['error_count'] = count($errors);
    }
    
    if (php_sapi_name() === 'cli') {
        echo json_encode($summary, JSON_PRETTY_PRINT) . "\n";
        exit(0);
    } else {
        // For browser requests, show a nice HTML page
        $page_title = 'Payroll Processed';
        include __DIR__ . '/../includes/header.php';
        ?>
        <div class="container-fluid py-4">
            <div class="row justify-content-center">
                <div class="col-lg-10">
                    <div class="card shadow-lg border-0 animate-fade-in" style="border-radius: 16px; overflow: hidden;">
                        <div class="card-body p-5">
                            <!-- Success Header -->
                            <div class="text-center mb-5">
                                <div class="rounded-circle bg-success bg-gradient d-inline-flex align-items-center justify-content-center mb-4 shadow-lg" 
                                     style="width: 100px; height: 100px;">
                                    <i class="bi bi-check-circle-fill text-white" style="font-size: 50px;"></i>
                                </div>
                                <h1 class="gradient-text mb-2 fw-bold">Payroll Processed Successfully!</h1>
                                <p class="text-muted fs-5">Payroll for <strong><?= date('F Y', mktime(0, 0, 0, $month, 1, $year)) ?></strong> has been processed.</p>
                                <?php if ($force): ?>
                                    <span class="badge bg-warning text-dark fs-6 mt-2">Force Rerun</span>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Stats Cards -->
                            <div class="row g-4 mb-5">
                                <div class="col-md-4">
                                    <div class="stat-card animate-slide-up">
                                        <div class="stat-icon">
                                            <i class="bi bi-people-fill"></i>
                                        </div>
                                        <div class="stat-label">Staff Processed</div>
                                        <div class="stat-value" data-count="<?= $processed ?>"><?= $processed ?></div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="stat-card animate-slide-up" style="animation-delay: 0.1s">
                                        <div class="stat-icon" style="background: linear-gradient(135deg, #28a745, #1dd1a1);">
                                            <i class="bi bi-cash-stack"></i>
                                        </div>
                                        <div class="stat-label">Total Salary Cost</div>
                                        <div class="stat-value">৳<?= number_format(round($total_salary_cost, 2), 2) ?></div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="stat-card animate-slide-up" style="animation-delay: 0.2s">
                                        <div class="stat-icon" style="background: linear-gradient(135deg, #17a2b8, #706fd3);">
                                            <i class="bi bi-piggy-bank"></i>
                                        </div>
                                        <div class="stat-label">Profit Fund Added</div>
                                        <div class="stat-value">৳<?= number_format(round($total_profit_fund_added, 2), 2) ?></div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Processing Details -->
                            <div class="card shadow-sm border-0 mb-4" style="background: #f8f9fa;">
                                <div class="card-body p-4">
                                    <h5 class="fw-semibold mb-4">
                                        <i class="bi bi-info-circle text-primary me-2"></i>Processing Details
                                    </h5>
                                    <div class="row g-4">
                                        <div class="col-md-3">
                                            <div class="text-muted small mb-1">Month</div>
                                            <div class="fw-semibold"><?= date('F', mktime(0, 0, 0, $month, 1)) ?> <?= $year ?></div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="text-muted small mb-1">Days in Month</div>
                                            <div class="fw-semibold"><?= $days_in_month ?></div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="text-muted small mb-1">Per Day Percent</div>
                                            <div class="fw-semibold"><?= number_format($per_day_percent, 2) ?>%</div>
                                        </div>
                                        <?php if ($force): ?>
                                        <div class="col-md-3">
                                            <div class="text-muted small mb-1">Run Type</div>
                                            <div class="fw-semibold">
                                                <span class="badge bg-warning text-dark fs-6">Force Rerun</span>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                        <?php if (!empty($errors)): ?>
                                        <div class="col-12">
                                            <div class="text-muted small mb-1">Errors</div>
                                            <div class="fw-semibold">
                                                <span class="badge bg-danger fs-6"><?= count($errors) ?> error(s) occurred</span>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <?php if (!empty($errors)): ?>
                            <div class="alert alert-warning border-0 shadow-sm mb-4">
                                <h6 class="alert-heading fw-semibold">
                                    <i class="bi bi-exclamation-triangle me-2"></i>Processing Warnings
                                </h6>
                                <p class="mb-0">Some errors occurred during processing. Please check the logs for details.</p>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Action Buttons -->
                            <div class="d-grid gap-2 d-md-flex justify-content-md-center mb-4">
                                <a href="<?= BASE_URL ?>/salary/index.php?month=<?= $month ?>&year=<?= $year ?>" 
                                   class="btn btn-primary btn-lg px-5"
                                   style="background: linear-gradient(135deg, #007bff, #706fd3); border: none;">
                                    <i class="bi bi-eye me-2"></i>View Salary Details
                                </a>
                                <a href="<?= BASE_URL ?>/salary/index.php" 
                                   class="btn btn-outline-secondary btn-lg px-5">
                                    <i class="bi bi-arrow-left me-2"></i>Back to Salary Management
                                </a>
                            </div>
                            
                            <!-- Notification Info -->
                            <div class="text-center">
                                <small class="text-muted">
                                    <i class="bi bi-bell me-1"></i>
                                    Notifications have been sent to all administrators.
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
        include __DIR__ . '/../includes/footer.php';
        exit;
    }
    
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Payroll error: " . $e->getMessage());
    
    // Create failure notification
    try {
        $notificationSystem = new NotificationSystem();
        $stmt = $pdo->prepare("
            SELECT id FROM users 
            WHERE role IN ('superadmin', 'admin') 
            AND deleted_at IS NULL
        ");
        $stmt->execute();
        $admins = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $month_name = date('F', mktime(0, 0, 0, $month, 1));
        $notification_title = "Payroll Processing Failed";
        $notification_message = sprintf(
            "Payroll processing for %s %d has failed.\n\n" .
            "Error: %s\n\n" .
            "Please check the logs and try again.",
            $month_name,
            $year,
            $e->getMessage()
        );
        
        $notification_link = BASE_URL . '/payroll/run_payroll.php?month=' . $month . '&year=' . $year;
        
        foreach ($admins as $admin_id) {
            $notificationSystem->create(
                $admin_id,
                'error',
                $notification_title,
                $notification_message,
                $notification_link
            );
        }
    } catch (Exception $notif_error) {
        error_log("Failed to create payroll failure notification: " . $notif_error->getMessage());
    }
    
    $error_response = [
        'success' => false,
        'message' => 'Failed to process payroll: ' . $e->getMessage(),
        'month' => $month,
        'year' => $year
    ];
    
    if (php_sapi_name() === 'cli') {
        echo json_encode($error_response, JSON_PRETTY_PRINT) . "\n";
        exit(1);
    } else {
        // For browser requests, show a nice error page
        $page_title = 'Payroll Processing Failed';
        include __DIR__ . '/../includes/header.php';
        ?>
        <div class="container-fluid py-4">
            <div class="row justify-content-center">
                <div class="col-lg-10">
                    <div class="card shadow-lg border-0 animate-fade-in" style="border-radius: 16px; overflow: hidden;">
                        <div class="card-body p-5">
                            <!-- Error Header -->
                            <div class="text-center mb-5">
                                <div class="rounded-circle bg-danger bg-gradient d-inline-flex align-items-center justify-content-center mb-4 shadow-lg" 
                                     style="width: 100px; height: 100px;">
                                    <i class="bi bi-x-circle-fill text-white" style="font-size: 50px;"></i>
                                </div>
                                <h1 class="text-danger mb-2 fw-bold">Payroll Processing Failed</h1>
                                <p class="text-muted fs-5">An error occurred while processing payroll for <strong><?= date('F Y', mktime(0, 0, 0, $month, 1, $year)) ?></strong>.</p>
                            </div>
                            
                            <!-- Error Details -->
                            <div class="alert alert-danger border-0 shadow-sm mb-4">
                                <h6 class="alert-heading fw-semibold mb-3">
                                    <i class="bi bi-exclamation-triangle me-2"></i>Error Details
                                </h6>
                                <p class="mb-0"><?= h($e->getMessage()) ?></p>
                            </div>
                            
                            <!-- What to do next -->
                            <div class="card shadow-sm border-0 mb-4" style="background: #f8f9fa;">
                                <div class="card-body p-4">
                                    <h5 class="fw-semibold mb-3">
                                        <i class="bi bi-info-circle text-primary me-2"></i>What to do next?
                                    </h5>
                                    <ul class="mb-0" style="line-height: 2;">
                                        <li>Check the error message above for details</li>
                                        <li>Review the server logs for more information</li>
                                        <li>Verify all required data is present (staff salaries, progress entries, etc.)</li>
                                        <li>Try running payroll again after fixing any issues</li>
                                    </ul>
                                </div>
                            </div>
                            
                            <!-- Action Buttons -->
                            <div class="d-grid gap-2 d-md-flex justify-content-md-center mb-4">
                                <a href="<?= BASE_URL ?>/payroll/run_payroll.php?month=<?= $month ?>&year=<?= $year ?>" 
                                   class="btn btn-primary btn-lg px-5"
                                   style="background: linear-gradient(135deg, #007bff, #706fd3); border: none;">
                                    <i class="bi bi-arrow-clockwise me-2"></i>Try Again
                                </a>
                                <a href="<?= BASE_URL ?>/salary/index.php" 
                                   class="btn btn-outline-secondary btn-lg px-5">
                                    <i class="bi bi-arrow-left me-2"></i>Back to Salary Management
                                </a>
                            </div>
                            
                            <!-- Notification Info -->
                            <div class="text-center">
                                <small class="text-muted">
                                    <i class="bi bi-bell me-1"></i>
                                    Failure notifications have been sent to all administrators.
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
        include __DIR__ . '/../includes/footer.php';
        exit;
    }
}
