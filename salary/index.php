<?php
/**
 * salary/index.php
 * Professional Salary Management with Payroll Control Panel
 */

require_once __DIR__ . '/../auth_helper.php';
require_once __DIR__ . '/../helpers.php';

require_role(['superadmin', 'admin', 'accountant']);

// Handle inline actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    require_role(['admin', 'superadmin', 'accountant']);
    
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $_SESSION['salary_error'] = 'Invalid security token.';
        header('Location: ' . BASE_URL . '/salary/index.php');
        exit;
    }
    
    $pdo = getPDO();
    $action = $_POST['action'];
    
    // Handle add_salary action separately (doesn't require salary_id)
    if ($action === 'add_salary') {
        require_role(['admin', 'superadmin']); // Only admin and superadmin can add
        
        try {
            $user_id = intval($_POST['user_id'] ?? 0);
            $month = intval($_POST['month'] ?? 0);
            $year = intval($_POST['year'] ?? 0);
            $gross_salary = floatval($_POST['gross_salary'] ?? 0);
            $monthly_progress = floatval($_POST['monthly_progress'] ?? 0);
            $profit_fund = floatval($_POST['profit_fund'] ?? 0);
            $payable_before_advance = floatval($_POST['payable_before_advance'] ?? 0);
            $advances_deducted = floatval($_POST['advances_deducted'] ?? 0);
            $net_payable = floatval($_POST['net_payable'] ?? 0);
            $status = $_POST['status'] ?? 'pending';
            
            // Validation
            if (!$user_id || !$month || !$year || $gross_salary <= 0) {
                throw new Exception('User, month, year, and gross salary are required.');
            }
            
            if ($month < 1 || $month > 12 || $year < 2000 || $year > 2100) {
                throw new Exception('Invalid month or year.');
            }
            
            if (!in_array($status, ['pending', 'approved', 'paid'])) {
                $status = 'pending';
            }
            
            // Check if user exists
            $stmt = $pdo->prepare("SELECT id, name FROM users WHERE id = ? AND deleted_at IS NULL");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
            
            if (!$user) {
                throw new Exception('User not found or has been deleted.');
            }
            
            // Check if salary entry already exists for this user/month/year
            $stmt = $pdo->prepare("SELECT id FROM salary_history WHERE user_id = ? AND month = ? AND year = ?");
            $stmt->execute([$user_id, $month, $year]);
            if ($stmt->fetch()) {
                throw new Exception("Salary entry already exists for this user for {$month}/{$year}. Please edit the existing entry instead.");
            }
            
            $pdo->beginTransaction();
            
            // Insert new salary entry
            $stmt = $pdo->prepare("
                INSERT INTO salary_history
                (user_id, month, year, gross_salary, profit_fund, monthly_progress,
                 payable_before_advance, advances_deducted, net_payable, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())
            ");
            $stmt->execute([
                $user_id, $month, $year, $gross_salary, $profit_fund, $monthly_progress,
                $payable_before_advance, $advances_deducted, $net_payable, $status
            ]);
            $new_salary_id = $pdo->lastInsertId();
            
            // Create profit_fund record if amount > 0 (but DON'T add to balance yet)
            if ($profit_fund > 0) {
                $stmt = $pdo->prepare("
                    INSERT INTO profit_fund (user_id, month, year, amount, created_at)
                    VALUES (?, ?, ?, ?, UTC_TIMESTAMP())
                    ON DUPLICATE KEY UPDATE amount = ?
                ");
                $stmt->execute([$user_id, $month, $year, $profit_fund, $profit_fund]);
            }
            
            // ONLY add profit fund to balance if status is 'approved'
            // Profit fund should only be available for withdrawal after salary approval
            if ($status === 'approved' && $profit_fund > 0) {
                // Get current balance or create if doesn't exist
                $stmt = $pdo->prepare("
                    SELECT balance FROM profit_fund_balance WHERE user_id = ?
                ");
                $stmt->execute([$user_id]);
                $current_balance = $stmt->fetch();
                
                if ($current_balance) {
                    // Update balance (add profit fund amount)
                    $new_balance = floatval($current_balance['balance']) + $profit_fund;
                    $stmt = $pdo->prepare("
                        UPDATE profit_fund_balance 
                        SET balance = ?, updated_at = UTC_TIMESTAMP()
                        WHERE user_id = ?
                    ");
                    $stmt->execute([$new_balance, $user_id]);
                } else {
                    // Create new balance entry
                    $stmt = $pdo->prepare("
                        INSERT INTO profit_fund_balance (user_id, balance, updated_at)
                        VALUES (?, ?, UTC_TIMESTAMP())
                    ");
                    $stmt->execute([$user_id, $profit_fund]);
                }
            }
            
            $pdo->commit();
            
            log_audit(current_user()['id'], 'create', 'salary_history', $new_salary_id, "Added new salary for user {$user_id} ({$user['name']}) - Month: {$month}/{$year}, Net: ৳{$net_payable}");
            $_SESSION['salary_success'] = 'Salary added successfully.';
            
            header('Location: ' . BASE_URL . '/salary/index.php?month=' . $month . '&year=' . $year);
            exit;
            
        } catch (Exception $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $_SESSION['salary_error'] = 'Error: ' . $e->getMessage();
            header('Location: ' . BASE_URL . '/salary/index.php?month=' . ($_POST['month'] ?? date('m')) . '&year=' . ($_POST['year'] ?? date('Y')));
            exit;
        }
    }
    
    // For other actions, require salary_id
    $salary_id = intval($_POST['salary_id'] ?? 0);
    
    if (!$salary_id) {
        $_SESSION['salary_error'] = 'Invalid salary ID.';
        header('Location: ' . BASE_URL . '/salary/index.php');
        exit;
    }
    
    try {
        switch ($action) {
            case 'approve_salary':
                // Get salary data first
                $stmt = $pdo->prepare("SELECT * FROM salary_history WHERE id = ?");
                $stmt->execute([$salary_id]);
                $salary_data = $stmt->fetch();
                
                if (!$salary_data) {
                    throw new Exception('Salary record not found');
                }
                
                $pdo->beginTransaction();
                
                // Update salary status
                $stmt = $pdo->prepare("
                    UPDATE salary_history 
                    SET status = 'approved', approved_by = ?, approved_at = UTC_TIMESTAMP()
                    WHERE id = ? AND status = 'pending'
                ");
                $stmt->execute([current_user()['id'], $salary_id]);
                
                // Add profit fund to balance when salary is approved
                $profit_fund_amount = floatval($salary_data['profit_fund'] ?? 0);
                if ($profit_fund_amount > 0) {
                    // Get current balance or create if doesn't exist
                    $stmt = $pdo->prepare("
                        SELECT balance FROM profit_fund_balance WHERE user_id = ?
                    ");
                    $stmt->execute([$salary_data['user_id']]);
                    $current_balance = $stmt->fetch();
                    
                    if ($current_balance) {
                        // Update balance (add profit fund amount)
                        $new_balance = floatval($current_balance['balance']) + $profit_fund_amount;
                        $stmt = $pdo->prepare("
                            UPDATE profit_fund_balance 
                            SET balance = ?, updated_at = UTC_TIMESTAMP()
                            WHERE user_id = ?
                        ");
                        $stmt->execute([$new_balance, $salary_data['user_id']]);
                    } else {
                        // Create new balance entry
                        $stmt = $pdo->prepare("
                            INSERT INTO profit_fund_balance (user_id, balance, updated_at)
                            VALUES (?, ?, UTC_TIMESTAMP())
                        ");
                        $stmt->execute([$salary_data['user_id'], $profit_fund_amount]);
                    }
                }
                
                $pdo->commit();
                
                log_audit(current_user()['id'], 'approve', 'salary_history', $salary_id, "Approved salary for user {$salary_data['user_id']} - Profit fund added: ৳{$profit_fund_amount}");
                $_SESSION['salary_success'] = 'Salary approved successfully. Profit fund added to balance.';
                break;
                
            case 'mark_paid':
                $stmt = $pdo->prepare("
                    UPDATE salary_history 
                    SET status = 'paid', paid_at = UTC_TIMESTAMP()
                    WHERE id = ? AND status = 'approved'
                ");
                $stmt->execute([$salary_id]);
                log_audit(current_user()['id'], 'update', 'salary_history', $salary_id, "Marked salary as paid");
                $_SESSION['salary_success'] = 'Salary marked as paid.';
                break;
                
            case 'revert_salary':
                // Get salary data first
                $stmt = $pdo->prepare("SELECT * FROM salary_history WHERE id = ?");
                $stmt->execute([$salary_id]);
                $salary_data = $stmt->fetch();
                
                if (!$salary_data) {
                    throw new Exception('Salary record not found');
                }
                
                // Only revert if currently approved (not paid)
                if ($salary_data['status'] === 'approved') {
                    $pdo->beginTransaction();
                    
                    // Remove profit fund from balance when reverting approved salary
                    $profit_fund_amount = floatval($salary_data['profit_fund'] ?? 0);
                    if ($profit_fund_amount > 0) {
                        $stmt = $pdo->prepare("
                            SELECT balance FROM profit_fund_balance WHERE user_id = ?
                        ");
                        $stmt->execute([$salary_data['user_id']]);
                        $current_balance = $stmt->fetch();
                        
                        if ($current_balance) {
                            $new_balance = max(0, floatval($current_balance['balance']) - $profit_fund_amount);
                            $stmt = $pdo->prepare("
                                UPDATE profit_fund_balance 
                                SET balance = ?, updated_at = UTC_TIMESTAMP()
                                WHERE user_id = ?
                            ");
                            $stmt->execute([$new_balance, $salary_data['user_id']]);
                        }
                    }
                    
                    // Update salary status
                    $stmt = $pdo->prepare("
                        UPDATE salary_history 
                        SET status = 'pending', approved_by = NULL, approved_at = NULL, paid_at = NULL
                        WHERE id = ?
                    ");
                    $stmt->execute([$salary_id]);
                    
                    $pdo->commit();
                    
                    log_audit(current_user()['id'], 'update', 'salary_history', $salary_id, "Reverted salary to pending - Profit fund removed: ৳{$profit_fund_amount}");
                    $_SESSION['salary_success'] = 'Salary reverted to pending. Profit fund removed from balance.';
                } else {
                    // Just update status if not approved
                    $stmt = $pdo->prepare("
                        UPDATE salary_history 
                        SET status = 'pending', approved_by = NULL, approved_at = NULL, paid_at = NULL
                        WHERE id = ?
                    ");
                    $stmt->execute([$salary_id]);
                    log_audit(current_user()['id'], 'update', 'salary_history', $salary_id, "Reverted salary to pending");
                    $_SESSION['salary_success'] = 'Salary reverted to pending.';
                }
                break;
                
            case 'edit_salary':
                // Get current salary data to check status change
                $stmt = $pdo->prepare("SELECT * FROM salary_history WHERE id = ?");
                $stmt->execute([$salary_id]);
                $old_salary_data = $stmt->fetch();
                
                if (!$old_salary_data) {
                    throw new Exception('Salary record not found');
                }
                
                $old_status = $old_salary_data['status'];
                $old_profit_fund = floatval($old_salary_data['profit_fund'] ?? 0);
                
                $gross_salary = floatval($_POST['gross_salary'] ?? 0);
                $profit_fund = floatval($_POST['profit_fund'] ?? 0);
                $ticket_penalties = floatval($_POST['ticket_penalties'] ?? 0);
                $group_penalties = floatval($_POST['group_penalties'] ?? 0);
                $overtime_bonus = floatval($_POST['overtime_bonus'] ?? 0);
                $status = $_POST['status'] ?? 'pending';
                
                // Calculate net payable
                $net_payable = max(0, $gross_salary - $ticket_penalties - $group_penalties + $overtime_bonus - $profit_fund);
                
                $pdo->beginTransaction();
                
                // Update salary record
                $stmt = $pdo->prepare("
                    UPDATE salary_history 
                    SET gross_salary = ?, profit_fund = ?, net_payable = ?, status = ?, updated_at = UTC_TIMESTAMP()
                    WHERE id = ?
                ");
                $stmt->execute([$gross_salary, $profit_fund, $net_payable, $status, $salary_id]);
                
                $user_id = $old_salary_data['user_id'];
                
                // Handle profit fund balance based on status change
                // Profit fund should ONLY be in balance when status is 'approved'
                if ($old_status === 'approved' && $status !== 'approved') {
                    // Status changed FROM approved TO something else - remove profit fund from balance
                    if ($old_profit_fund > 0) {
                        $stmt = $pdo->prepare("
                            SELECT balance FROM profit_fund_balance WHERE user_id = ?
                        ");
                        $stmt->execute([$user_id]);
                        $current_balance = $stmt->fetch();
                        
                        if ($current_balance) {
                            $new_balance = max(0, floatval($current_balance['balance']) - $old_profit_fund);
                            $stmt = $pdo->prepare("
                                UPDATE profit_fund_balance 
                                SET balance = ?, updated_at = UTC_TIMESTAMP()
                                WHERE user_id = ?
                            ");
                            $stmt->execute([$new_balance, $user_id]);
                        }
                    }
                } elseif ($old_status !== 'approved' && $status === 'approved') {
                    // Status changed TO approved - add profit fund to balance
                    if ($profit_fund > 0) {
                        $stmt = $pdo->prepare("
                            SELECT balance FROM profit_fund_balance WHERE user_id = ?
                        ");
                        $stmt->execute([$user_id]);
                        $current_balance = $stmt->fetch();
                        
                        if ($current_balance) {
                            // Update balance (add profit fund amount)
                            $new_balance = floatval($current_balance['balance']) + $profit_fund;
                            $stmt = $pdo->prepare("
                                UPDATE profit_fund_balance 
                                SET balance = ?, updated_at = UTC_TIMESTAMP()
                                WHERE user_id = ?
                            ");
                            $stmt->execute([$new_balance, $user_id]);
                        } else {
                            // Create new balance entry
                            $stmt = $pdo->prepare("
                                INSERT INTO profit_fund_balance (user_id, balance, updated_at)
                                VALUES (?, ?, UTC_TIMESTAMP())
                            ");
                            $stmt->execute([$user_id, $profit_fund]);
                        }
                    }
                } elseif ($old_status === 'approved' && $status === 'approved' && $old_profit_fund != $profit_fund) {
                    // Status is still approved but profit fund amount changed - adjust balance
                    $difference = $profit_fund - $old_profit_fund;
                    if ($difference != 0) {
                        $stmt = $pdo->prepare("
                            SELECT balance FROM profit_fund_balance WHERE user_id = ?
                        ");
                        $stmt->execute([$user_id]);
                        $current_balance = $stmt->fetch();
                        
                        if ($current_balance) {
                            $new_balance = max(0, floatval($current_balance['balance']) + $difference);
                            $stmt = $pdo->prepare("
                                UPDATE profit_fund_balance 
                                SET balance = ?, updated_at = UTC_TIMESTAMP()
                                WHERE user_id = ?
                            ");
                            $stmt->execute([$new_balance, $user_id]);
                        } elseif ($profit_fund > 0) {
                            // Create new balance entry if it doesn't exist
                            $stmt = $pdo->prepare("
                                INSERT INTO profit_fund_balance (user_id, balance, updated_at)
                                VALUES (?, ?, UTC_TIMESTAMP())
                            ");
                            $stmt->execute([$user_id, $profit_fund]);
                        }
                    }
                }
                
                $pdo->commit();
                
                log_audit(current_user()['id'], 'update', 'salary_history', $salary_id, "Edited salary details" . ($status === 'approved' && $profit_fund > 0 ? " - Profit fund added to balance" : ""));
                $_SESSION['salary_success'] = 'Salary updated successfully.';
                break;
                
            case 'delete_salary':
                require_role(['admin', 'superadmin']); // Only admin and superadmin can delete
                
                // Get salary data first
                $stmt = $pdo->prepare("SELECT * FROM salary_history WHERE id = ?");
                $stmt->execute([$salary_id]);
                $salary_data = $stmt->fetch();
                
                if (!$salary_data) {
                    throw new Exception('Salary record not found');
                }
                
                $pdo->beginTransaction();
                
                $profit_fund_amount = floatval($salary_data['profit_fund'] ?? 0);
                $user_id = $salary_data['user_id'];
                $month = $salary_data['month'];
                $year = $salary_data['year'];
                
                // If salary is approved, remove profit fund from balance
                if ($salary_data['status'] === 'approved' && $profit_fund_amount > 0) {
                    $stmt = $pdo->prepare("
                        SELECT balance FROM profit_fund_balance WHERE user_id = ?
                    ");
                    $stmt->execute([$user_id]);
                    $current_balance = $stmt->fetch();
                    
                    if ($current_balance) {
                        $new_balance = max(0, floatval($current_balance['balance']) - $profit_fund_amount);
                        $stmt = $pdo->prepare("
                            UPDATE profit_fund_balance 
                            SET balance = ?, updated_at = UTC_TIMESTAMP()
                            WHERE user_id = ?
                        ");
                        $stmt->execute([$new_balance, $user_id]);
                    }
                }
                
                // Delete corresponding profit_fund record for this user/month/year
                if ($profit_fund_amount > 0) {
                    $stmt = $pdo->prepare("
                        DELETE FROM profit_fund 
                        WHERE user_id = ? AND month = ? AND year = ?
                    ");
                    $stmt->execute([$user_id, $month, $year]);
                    $profit_fund_deleted = $stmt->rowCount() > 0;
                } else {
                    $profit_fund_deleted = false;
                }
                
                // Delete the salary record
                $stmt = $pdo->prepare("DELETE FROM salary_history WHERE id = ?");
                $stmt->execute([$salary_id]);
                
                $pdo->commit();
                
                $log_message = "Deleted salary for user {$user_id} - Month: {$month}/{$year}";
                $success_message = 'Salary deleted successfully.';
                
                if ($salary_data['status'] === 'approved' && $profit_fund_amount > 0) {
                    $log_message .= " - Profit fund removed from balance: ৳{$profit_fund_amount}";
                    $success_message .= ' Profit fund has been removed from balance.';
                }
                
                if ($profit_fund_deleted) {
                    $log_message .= " - Profit fund record deleted";
                    $success_message .= ' Profit fund record has been removed.';
                }
                
                log_audit(current_user()['id'], 'delete', 'salary_history', $salary_id, $log_message);
                $_SESSION['salary_success'] = $success_message;
                break;
        }
    } catch (Exception $e) {
        $_SESSION['salary_error'] = 'Error: ' . $e->getMessage();
    }
    
    header('Location: ' . BASE_URL . '/salary/index.php?month=' . ($_GET['month'] ?? date('m')) . '&year=' . ($_GET['year'] ?? date('Y')));
    exit;
}

$pdo = getPDO();
$month = intval($_GET['month'] ?? date('m'));
$year = intval($_GET['year'] ?? date('Y'));

// Get all active staff for add salary dropdown (admin/superadmin only)
$staff_list = [];
if (in_array(get_user_role(), ['admin', 'superadmin'])) {
    $stmt = $pdo->prepare("
        SELECT id, name, email, monthly_salary 
        FROM users 
        WHERE role = 'staff' 
          AND deleted_at IS NULL 
        ORDER BY name
    ");
    $stmt->execute();
    $staff_list = $stmt->fetchAll();
}

// Get last payroll run info
$last_payroll_run = null;
try {
    $stmt = $pdo->query("
        SELECT month, year, total_staff_processed, total_salary_cost, created_at, run_by
        FROM payroll_run_log
        ORDER BY created_at DESC
        LIMIT 1
    ");
    $last_payroll_run = $stmt->fetch();
} catch (Exception $e) {
    // Table might not exist yet, ignore
}

// Get flash messages
$flash_success = $_SESSION['salary_success'] ?? $_SESSION['payroll_success'] ?? null;
$flash_error = $_SESSION['salary_error'] ?? $_SESSION['payroll_error'] ?? null;
$flash_preview = $_SESSION['payroll_preview'] ?? null;
unset($_SESSION['salary_success'], $_SESSION['salary_error'], $_SESSION['payroll_success'], $_SESSION['payroll_error'], $_SESSION['payroll_preview']);

// Check if showing deleted users
$show_deleted = isset($_GET['show_deleted']) && $_GET['show_deleted'] == '1';

// Build query with deleted user filter
$deleted_filter = $show_deleted ? '' : 'AND u.deleted_at IS NULL';

$stmt = $pdo->prepare("
    SELECT sh.*, u.name as user_name, u.id as user_id, u.deleted_at
    FROM salary_history sh
    JOIN users u ON sh.user_id = u.id
    WHERE sh.month = ? AND sh.year = ?
    {$deleted_filter}
    ORDER BY u.deleted_at IS NULL DESC, u.name
");
$stmt->execute([$month, $year]);
$salaries = $stmt->fetchAll();

$page_title = 'Salary Management';
include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="mb-4">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3">
            <div>
                <h1 class="gradient-text mb-2 fs-3 fs-md-2">Salary Management</h1>
                <p class="text-muted mb-0 small">View and manage staff salaries and payroll</p>
            </div>
        </div>
    </div>
    
    <?php if ($flash_success): ?>
        <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
            <i class="bi bi-check-circle me-2"></i><?= h($flash_success) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if ($flash_error): ?>
        <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
            <i class="bi bi-exclamation-triangle me-2"></i><?= h($flash_error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if ($flash_preview): ?>
        <div class="alert alert-info alert-dismissible fade show shadow-sm" role="alert">
            <h6 class="alert-heading"><i class="bi bi-info-circle me-2"></i>Payroll Preview</h6>
            <?= $flash_preview ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if (in_array(get_user_role(), ['admin', 'superadmin'])): ?>
        <!-- Payroll Control Panel -->
        <div class="card shadow-lg border-0 mb-4" style="border-radius: 12px; overflow: hidden;">
            <div class="card-header bg-white border-bottom py-3" style="background: linear-gradient(135deg, rgba(0, 123, 255, 0.05), rgba(112, 111, 211, 0.05));">
                <h5 class="mb-0 fw-semibold">
                    <i class="bi bi-cash-stack me-2 text-primary"></i>Payroll Control Panel
                </h5>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="<?= BASE_URL ?>/payroll/run_payroll.php" id="payrollForm">
                    <input type="hidden" name="csrf_token" value="<?= h(generate_csrf_token()) ?>">
                    
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label for="payroll_month" class="form-label fw-semibold">
                                <i class="bi bi-calendar me-1"></i>Month <span class="text-danger">*</span>
                            </label>
                            <select class="form-select form-select-lg" id="payroll_month" name="month" required>
                                <?php for ($m = 1; $m <= 12; $m++): ?>
                                    <option value="<?= $m ?>" <?= $month == $m ? 'selected' : '' ?>>
                                        <?= date('F', mktime(0, 0, 0, $m, 1)) ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-4">
                            <label for="payroll_year" class="form-label fw-semibold">
                                <i class="bi bi-calendar-year me-1"></i>Year <span class="text-danger">*</span>
                            </label>
                            <select class="form-select form-select-lg" id="payroll_year" name="year" required>
                                <?php
                                $current_year = (int)date('Y');
                                for ($y = $current_year - 3; $y <= $current_year + 1; $y++):
                                ?>
                                    <option value="<?= $y ?>" <?= $year == $y ? 'selected' : '' ?>>
                                        <?= $y ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-4 d-flex align-items-end">
                            <div class="w-100">
                                <label class="form-label fw-semibold mb-2">Action</label>
                                <div class="d-grid gap-2">
                                    <button type="submit" name="mode" value="preview" class="btn btn-outline-secondary btn-lg" id="previewPayrollBtn">
                                        <i class="bi bi-eye me-2"></i>Preview Payroll
                                    </button>
                                    <button type="submit" name="mode" value="run" class="btn btn-primary btn-lg" id="runPayrollBtn">
                                        <i class="bi bi-cash-stack me-2"></i>Run Payroll
                                    </button>
                                    <button type="submit" name="mode" value="force" class="btn btn-outline-warning btn-lg" id="forcePayrollBtn">
                                        <i class="bi bi-exclamation-triangle me-2"></i>Force Re-Run Payroll
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="alert alert-info mb-0 shadow-sm" style="border-radius: 8px;">
                        <i class="bi bi-info-circle me-2"></i>
                        <small>Running payroll will calculate salaries for the selected month.</small>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Last Payroll Run Summary -->
        <?php if ($last_payroll_run): ?>
            <div class="card shadow-sm border-0 mb-4" style="border-radius: 12px;">
                <div class="card-body">
                    <h6 class="fw-semibold mb-3">
                        <i class="bi bi-clock-history me-2 text-primary"></i>Last Payroll Run
                    </h6>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="text-muted small">Month/Year</div>
                            <div class="fw-bold"><?= date('F Y', mktime(0, 0, 0, $last_payroll_run['month'], 1, $last_payroll_run['year'])) ?></div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-muted small">Staff Processed</div>
                            <div class="fw-bold"><?= $last_payroll_run['total_staff_processed'] ?></div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-muted small">Total Salary Cost</div>
                            <div class="fw-bold">৳<?= number_format($last_payroll_run['total_salary_cost'], 2) ?></div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
    
    <!-- Export Options -->
    <div class="card shadow-lg border-0 mb-4" style="border-radius: 12px;">
        <div class="card-body p-3">
            <div class="d-flex flex-wrap gap-2 align-items-center">
                <span class="fw-semibold me-2"><i class="bi bi-download me-1"></i>Export:</span>
                <a href="<?= BASE_URL ?>/reports/export_salary.php?month=<?= $month ?>&year=<?= $year ?>&format=csv" 
                   class="btn btn-outline-success btn-sm">
                    <i class="bi bi-file-earmark-spreadsheet me-1"></i>Export Salary Summary (CSV)
                </a>
                <a href="<?= BASE_URL ?>/reports/export_salary.php?month=<?= $month ?>&year=<?= $year ?>&format=pdf" 
                   class="btn btn-outline-danger btn-sm">
                    <i class="bi bi-file-earmark-pdf me-1"></i>Export Salary Summary (PDF)
                </a>
            </div>
        </div>
    </div>
    
    <?php if (in_array(get_user_role(), ['admin', 'superadmin'])): ?>
        <!-- Add Salary Button -->
        <div class="card shadow-lg border-0 mb-4 animate-slide-up" style="border-radius: 12px; border-left: 4px solid #28a745 !important;">
            <div class="card-body p-3">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                    <div>
                        <h6 class="fw-semibold mb-1">
                            <i class="bi bi-plus-circle me-2 text-success"></i>Add New Salary Entry
                        </h6>
                        <p class="text-muted small mb-0">Manually add a salary record for any staff member</p>
                    </div>
                    <button type="button" 
                            class="btn btn-success btn-lg shadow-sm" 
                            onclick="openAddSalaryModal()">
                        <i class="bi bi-plus-circle me-2"></i>Add Salary
                    </button>
                </div>
            </div>
        </div>
    <?php endif; ?>
    
    <!-- Filter Card -->
    <div class="card shadow-lg border-0 mb-4 animate-slide-up" style="border-radius: 12px;">
        <div class="card-body p-3 p-md-4">
            <form method="GET" action="" class="row g-3">
                <div class="col-12 col-md-6 col-lg-2">
                    <label for="month" class="form-label fw-semibold small">
                        <i class="bi bi-calendar me-1"></i>Month
                    </label>
                    <select class="form-select form-select-lg" id="month" name="month">
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?= $m ?>" <?= $month == $m ? 'selected' : '' ?>>
                                <?= date('F', mktime(0,0,0,$m,1)) ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-12 col-md-6 col-lg-2">
                    <label for="year" class="form-label fw-semibold small">
                        <i class="bi bi-calendar-year me-1"></i>Year
                    </label>
                    <input type="number" 
                           class="form-control form-control-lg" 
                           id="year" 
                           name="year" 
                           value="<?= $year ?>" 
                           min="2000" 
                           max="2100">
                </div>
                <div class="col-12 col-md-6 col-lg-3">
                    <label class="form-label fw-semibold small">
                        <i class="bi bi-filter me-1"></i>Options
                    </label>
                    <div class="form-check mt-2">
                        <input class="form-check-input" type="checkbox" id="show_deleted" name="show_deleted" value="1" <?= $show_deleted ? 'checked' : '' ?>>
                        <label class="form-check-label" for="show_deleted">
                            Show Deleted Staff
                        </label>
                    </div>
                </div>
                <div class="col-12 col-md-6 col-lg-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-secondary btn-lg w-100 shadow-sm">
                        <i class="bi bi-funnel me-1"></i>Filter
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Salary Table -->
    <div class="card shadow-lg border-0 animate-slide-up" style="animation-delay: 0.1s; border-radius: 12px;">
        <div class="card-body p-0">
            <!-- Desktop Table View -->
            <div class="table-responsive d-none d-md-block">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light" style="background: linear-gradient(135deg, #f8f9fa, #e9ecef);">
                        <tr>
                            <th class="ps-4 py-3 fw-semibold">Staff</th>
                            <th class="py-3 fw-semibold">Gross Salary</th>
                            <th class="py-3 fw-semibold">Progress</th>
                            <th class="py-3 fw-semibold">Profit Fund</th>
                            <th class="py-3 fw-semibold">Advances</th>
                            <th class="py-3 fw-semibold">Net Payable</th>
                            <th class="py-3 fw-semibold">Status</th>
                            <th class="text-end pe-4 py-3 fw-semibold">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($salaries)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-5 text-muted">
                                    <i class="bi bi-inbox fs-1 d-block mb-2 text-muted"></i>
                                    <p class="mb-0">No salary records for this month</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($salaries as $salary): ?>
                                <tr class="salary-row" style="transition: all 0.2s ease; <?= !empty($salary['deleted_at']) ? 'opacity: 0.7; background-color: #f8f9fa;' : '' ?>">
                                    <td class="ps-4">
                                        <div class="d-flex align-items-center">
                                            <div class="rounded-circle bg-gradient-primary d-flex align-items-center justify-content-center me-3 flex-shrink-0 shadow-sm" 
                                                 style="width: 45px; height: 45px; background: linear-gradient(135deg, #007bff, #706fd3);">
                                                <span class="text-white fw-bold fs-6"><?= strtoupper(substr($salary['user_name'], 0, 1)) ?></span>
                                            </div>
                                            <div>
                                                <strong class="fw-semibold d-block">
                                                    <?= h($salary['user_name']) ?>
                                                    <?php if (!empty($salary['deleted_at'])): ?>
                                                        <span class="badge bg-secondary ms-2" title="Deleted User">Deleted</span>
                                                    <?php endif; ?>
                                                </strong>
                                                <div class="btn-group btn-group-sm mt-1" role="group">
                                                    <a href="<?= BASE_URL ?>/reports/staff_monthly_history.php?user_id=<?= $salary['user_id'] ?>&month=<?= $month ?>&year=<?= $year ?>" 
                                                       class="btn btn-outline-info btn-sm" title="Progress History">
                                                        <i class="bi bi-graph-up"></i>
                                                    </a>
                                                    <a href="<?= BASE_URL ?>/reports/daily_progress.php?user_id=<?= $salary['user_id'] ?>&month=<?= $month ?>&year=<?= $year ?>" 
                                                       class="btn btn-outline-secondary btn-sm" title="Daily Logs">
                                                        <i class="bi bi-calendar3"></i>
                                                    </a>
                                                    <a href="<?= BASE_URL ?>/advances/index.php?user_id=<?= $salary['user_id'] ?>" 
                                                       class="btn btn-outline-warning btn-sm" title="Advances">
                                                        <i class="bi bi-wallet2"></i>
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td><strong>৳<?= number_format($salary['gross_salary'], 2) ?></strong></td>
                                    <td>
                                        <span class="badge px-3 py-2 shadow-sm" style="background: linear-gradient(135deg, #17a2b8, #138496); border: none;">
                                            <?= number_format($salary['monthly_progress'], 1) ?>%
                                        </span>
                                    </td>
                                    <td>৳<?= number_format($salary['profit_fund'], 2) ?></td>
                                    <td>৳<?= number_format($salary['advances_deducted'], 2) ?></td>
                                    <td><strong class="text-success fs-6">৳<?= number_format($salary['net_payable'], 2) ?></strong></td>
                                    <td>
                                        <?php
                                        $status_bg = $salary['status'] === 'paid' 
                                            ? 'linear-gradient(135deg, #28a745, #20c997)' 
                                            : ($salary['status'] === 'approved' 
                                                ? 'linear-gradient(135deg, #ffc107, #ff9800)' 
                                                : 'linear-gradient(135deg, #6c757d, #495057)');
                                        ?>
                                        <span class="badge px-3 py-2 shadow-sm" style="background: <?= $status_bg ?>; border: none;">
                                            <?= ucfirst($salary['status']) ?>
                                        </span>
                                    </td>
                                    <td class="text-end pe-4">
                                        <div class="btn-group shadow-sm" role="group">
                                            <a href="<?= BASE_URL ?>/salary/view.php?id=<?= $salary['id'] ?>" 
                                               class="btn btn-sm btn-outline-primary border-0" 
                                               title="View"
                                               style="transition: all 0.2s ease;">
                                                <i class="bi bi-eye-fill"></i>
                                            </a>
                                            <?php if (in_array(get_user_role(), ['admin', 'superadmin', 'accountant'])): ?>
                                                <button type="button" 
                                                        class="btn btn-sm btn-outline-info border-0" 
                                                        title="Edit Salary"
                                                        onclick="openEditModal(<?= htmlspecialchars(json_encode($salary), ENT_QUOTES, 'UTF-8') ?>)"
                                                        style="transition: all 0.2s ease;">
                                                    <i class="bi bi-pencil-square"></i>
                                                </button>
                                                <?php if ($salary['status'] === 'pending'): ?>
                                                    <button type="button" 
                                                            class="btn btn-sm btn-outline-success border-0" 
                                                            title="Approve Salary"
                                                            onclick="approveSalary(<?= $salary['id'] ?>)"
                                                            style="transition: all 0.2s ease;">
                                                        <i class="bi bi-check-circle"></i>
                                                    </button>
                                                <?php elseif ($salary['status'] === 'approved'): ?>
                                                    <button type="button" 
                                                            class="btn btn-sm btn-outline-success border-0" 
                                                            title="Mark Paid"
                                                            onclick="markPaid(<?= $salary['id'] ?>)"
                                                            style="transition: all 0.2s ease;">
                                                        <i class="bi bi-cash-coin"></i>
                                                    </button>
                                                <?php elseif ($salary['status'] === 'paid'): ?>
                                                    <button type="button" 
                                                            class="btn btn-sm btn-outline-warning border-0" 
                                                            title="Revert to Pending"
                                                            onclick="revertSalary(<?= $salary['id'] ?>)"
                                                            style="transition: all 0.2s ease;">
                                                        <i class="bi bi-arrow-counterclockwise"></i>
                                                    </button>
                                                <?php endif; ?>
                                                <a href="<?= BASE_URL ?>/reports/staff_monthly_history.php?user_id=<?= $salary['user_id'] ?>&month=<?= $month ?>&year=<?= $year ?>" 
                                                   class="btn btn-sm btn-outline-info border-0" 
                                                   title="Progress History"
                                                   style="transition: all 0.2s ease;">
                                                    <i class="bi bi-graph-up"></i>
                                                </a>
                                                <a href="<?= BASE_URL ?>/reports/export_salary_slip.php?id=<?= $salary['id'] ?>&format=pdf" 
                                                   class="btn btn-sm btn-outline-danger border-0" 
                                                   title="Export Slip (PDF)"
                                                   style="transition: all 0.2s ease;">
                                                    <i class="bi bi-file-earmark-arrow-down"></i>
                                                </a>
                                                <?php if (in_array(get_user_role(), ['admin', 'superadmin'])): ?>
                                                    <button type="button" 
                                                            class="btn btn-sm btn-outline-danger border-0" 
                                                            title="Delete Salary"
                                                            onclick="deleteSalary(<?= $salary['id'] ?>)"
                                                            style="transition: all 0.2s ease;">
                                                        <i class="bi bi-trash-fill"></i>
                                                    </button>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Mobile Card View -->
            <div class="d-md-none">
                <?php if (empty($salaries)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                        <p class="mb-0">No salary records for this month</p>
                    </div>
                <?php else: ?>
                    <div class="p-2 p-md-3">
                        <?php foreach ($salaries as $salary): ?>
                            <div class="card mb-3 border-0 salary-mobile-card" style="border-radius: 16px; overflow: hidden;">
                                <div class="card-header border-0 p-3" style="background: linear-gradient(135deg, rgba(0, 123, 255, 0.1), rgba(112, 111, 211, 0.1));">
                                    <div class="d-flex align-items-center">
                                        <div class="rounded-circle d-flex align-items-center justify-content-center me-3 flex-shrink-0 shadow" 
                                             style="width: 56px; height: 56px; background: linear-gradient(135deg, #007bff, #706fd3);">
                                            <span class="text-white fw-bold" style="font-size: 1.5rem;"><?= strtoupper(substr($salary['user_name'], 0, 1)) ?></span>
                                        </div>
                                        <div class="flex-grow-1 min-w-0">
                                            <h6 class="mb-1 fw-bold" style="font-size: 1rem; color: #212529;"><?= h($salary['user_name']) ?></h6>
                                            <?php
                                            $status_bg_mobile = $salary['status'] === 'paid' 
                                                ? 'linear-gradient(135deg, #28a745, #20c997)' 
                                                : ($salary['status'] === 'approved' 
                                                    ? 'linear-gradient(135deg, #ffc107, #ff9800)' 
                                                    : 'linear-gradient(135deg, #6c757d, #495057)');
                                            ?>
                                            <span class="badge px-3 py-2" style="background: <?= $status_bg_mobile ?>; border: none; font-size: 0.75rem; font-weight: 600;">
                                                <?= ucfirst($salary['status']) ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="card-body p-3">
                                    <div class="row g-2 mb-3">
                                        <div class="col-6">
                                            <div class="d-flex align-items-center justify-content-between p-3 rounded-3" style="background: linear-gradient(135deg, rgba(0, 123, 255, 0.08), rgba(112, 111, 211, 0.08)); border: 1px solid rgba(0, 123, 255, 0.2);">
                                                <div class="d-flex align-items-center">
                                                    <div class="rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 36px; height: 36px; background: linear-gradient(135deg, #007bff, #706fd3);">
                                                        <i class="bi bi-currency-dollar text-white" style="font-size: 0.9rem;"></i>
                                                    </div>
                                                    <div>
                                                        <small class="text-muted d-block mb-0" style="font-size: 0.7rem; font-weight: 500;">Gross Salary</small>
                                                        <strong class="text-primary d-block" style="font-size: 0.95rem;">৳<?= number_format($salary['gross_salary'], 2) ?></strong>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="d-flex align-items-center justify-content-between p-3 rounded-3" style="background: linear-gradient(135deg, rgba(23, 162, 184, 0.08), rgba(19, 132, 150, 0.08)); border: 1px solid rgba(23, 162, 184, 0.2);">
                                                <div class="d-flex align-items-center">
                                                    <div class="rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 36px; height: 36px; background: linear-gradient(135deg, #17a2b8, #138496);">
                                                        <i class="bi bi-graph-up text-white" style="font-size: 0.9rem;"></i>
                                                    </div>
                                                    <div>
                                                        <small class="text-muted d-block mb-0" style="font-size: 0.7rem; font-weight: 500;">Progress</small>
                                                        <strong class="text-info d-block" style="font-size: 0.95rem;"><?= number_format($salary['monthly_progress'], 1) ?>%</strong>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-12">
                                            <div class="d-flex align-items-center justify-content-between p-3 rounded-3" style="background: linear-gradient(135deg, rgba(40, 167, 69, 0.15), rgba(32, 201, 151, 0.15)); border: 2px solid rgba(40, 167, 69, 0.3);">
                                                <div class="d-flex align-items-center">
                                                    <div class="rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px; background: linear-gradient(135deg, #28a745, #20c997);">
                                                        <i class="bi bi-cash-coin text-white"></i>
                                                    </div>
                                                    <div>
                                                        <small class="text-muted d-block mb-0" style="font-size: 0.7rem; font-weight: 500;">Net Payable</small>
                                                        <strong class="text-success d-block" style="font-size: 1.1rem; font-weight: 700;">৳<?= number_format($salary['net_payable'], 2) ?></strong>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="d-grid gap-2">
                                        <div class="btn-group w-100 shadow-sm" role="group">
                                            <a href="<?= BASE_URL ?>/salary/view.php?id=<?= $salary['id'] ?>" 
                                               class="btn btn-sm btn-primary flex-fill" 
                                               style="border-radius: 8px 0 0 8px; font-weight: 600;">
                                                <i class="bi bi-eye-fill me-1"></i>View
                                            </a>
                                            <?php if (in_array(get_user_role(), ['admin', 'superadmin', 'accountant'])): ?>
                                                <button type="button" 
                                                        class="btn btn-sm btn-info flex-fill" 
                                                        onclick="openEditModal(<?= htmlspecialchars(json_encode($salary), ENT_QUOTES, 'UTF-8') ?>)"
                                                        style="font-weight: 600;">
                                                    <i class="bi bi-pencil-square me-1"></i>Edit
                                                </button>
                                                <?php if ($salary['status'] === 'pending'): ?>
                                                    <button type="button" 
                                                            class="btn btn-sm btn-success flex-fill"
                                                            onclick="approveSalary(<?= $salary['id'] ?>)"
                                                            style="border-radius: 0 8px 8px 0; font-weight: 600;">
                                                        <i class="bi bi-check-circle me-1"></i>Approve
                                                    </button>
                                                <?php elseif ($salary['status'] === 'approved'): ?>
                                                    <button type="button" 
                                                            class="btn btn-sm btn-success flex-fill"
                                                            onclick="markPaid(<?= $salary['id'] ?>)"
                                                            style="border-radius: 0 8px 8px 0; font-weight: 600;">
                                                        <i class="bi bi-cash-coin me-1"></i>Mark Paid
                                                    </button>
                                                <?php elseif ($salary['status'] === 'paid'): ?>
                                                    <button type="button" 
                                                            class="btn btn-sm btn-warning flex-fill"
                                                            onclick="revertSalary(<?= $salary['id'] ?>)"
                                                            style="border-radius: 0 8px 8px 0; font-weight: 600;">
                                                        <i class="bi bi-arrow-counterclockwise me-1"></i>Revert
                                                    </button>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </div>
                                        <div class="btn-group w-100" role="group">
                                            <a href="<?= BASE_URL ?>/reports/staff_monthly_history.php?user_id=<?= $salary['user_id'] ?>&month=<?= $month ?>&year=<?= $year ?>" 
                                               class="btn btn-sm btn-outline-info flex-fill">
                                                <i class="bi bi-graph-up me-1"></i>Progress
                                            </a>
                                            <a href="<?= BASE_URL ?>/reports/daily_progress.php?user_id=<?= $salary['user_id'] ?>&month=<?= $month ?>&year=<?= $year ?>" 
                                               class="btn btn-sm btn-outline-secondary flex-fill">
                                                <i class="bi bi-calendar3 me-1"></i>Daily Logs
                                            </a>
                                            <a href="<?= BASE_URL ?>/advances/index.php?user_id=<?= $salary['user_id'] ?>" 
                                               class="btn btn-sm btn-outline-warning flex-fill">
                                                <i class="bi bi-wallet2 me-1"></i>Advances
                                            </a>
                                        </div>
                                        <div class="btn-group w-100" role="group">
                                            <a href="<?= BASE_URL ?>/reports/export_salary_slip.php?id=<?= $salary['id'] ?>&format=pdf" 
                                               class="btn btn-sm btn-outline-danger flex-fill">
                                                <i class="bi bi-file-earmark-pdf me-1"></i>PDF Slip
                                            </a>
                                            <a href="<?= BASE_URL ?>/reports/export_salary_slip.php?id=<?= $salary['id'] ?>&format=csv" 
                                               class="btn btn-sm btn-outline-success flex-fill">
                                                <i class="bi bi-file-earmark-spreadsheet me-1"></i>CSV Slip
                                            </a>
                                            <?php if (in_array(get_user_role(), ['admin', 'superadmin'])): ?>
                                                <button type="button" 
                                                        class="btn btn-sm btn-outline-danger flex-fill"
                                                        onclick="deleteSalary(<?= $salary['id'] ?>)"
                                                        style="font-weight: 600;">
                                                    <i class="bi bi-trash-fill me-1"></i>Delete
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Add Salary Modal -->
<?php if (in_array(get_user_role(), ['admin', 'superadmin'])): ?>
<div class="modal fade" id="addSalaryModal" tabindex="-1" aria-labelledby="addSalaryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content shadow-lg border-0" style="border-radius: 16px;">
            <div class="modal-header border-0 pb-3" style="background: linear-gradient(135deg, #28a745, #20c997);">
                <h5 class="modal-title text-white fw-bold" id="addSalaryModalLabel">
                    <i class="bi bi-plus-circle me-2"></i>Add New Salary Entry
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="" id="addSalaryForm">
                <input type="hidden" name="action" value="add_salary">
                <input type="hidden" name="csrf_token" value="<?= h(generate_csrf_token()) ?>">
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="add_user_id" class="form-label fw-semibold">Staff Member <span class="text-danger">*</span></label>
                            <select class="form-select form-select-lg" id="add_user_id" name="user_id" required>
                                <option value="">Select Staff Member</option>
                                <?php foreach ($staff_list as $staff): ?>
                                    <option value="<?= $staff['id'] ?>" data-salary="<?= $staff['monthly_salary'] ?>">
                                        <?= h($staff['name']) ?> (৳<?= number_format($staff['monthly_salary'], 2) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="add_month" class="form-label fw-semibold">Month <span class="text-danger">*</span></label>
                            <select class="form-select form-select-lg" id="add_month" name="month" required>
                                <?php for ($m = 1; $m <= 12; $m++): ?>
                                    <option value="<?= $m ?>" <?= $month == $m ? 'selected' : '' ?>>
                                        <?= date('F', mktime(0, 0, 0, $m, 1)) ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="add_year" class="form-label fw-semibold">Year <span class="text-danger">*</span></label>
                            <input type="number" 
                                   class="form-control form-control-lg" 
                                   id="add_year" 
                                   name="year" 
                                   value="<?= $year ?>" 
                                   min="2000" 
                                   max="2100"
                                   required>
                        </div>
                        <div class="col-md-6">
                            <label for="add_gross_salary" class="form-label fw-semibold">Gross Salary <span class="text-danger">*</span></label>
                            <input type="number" 
                                   step="0.01" 
                                   class="form-control form-control-lg" 
                                   id="add_gross_salary" 
                                   name="gross_salary" 
                                   required>
                            <small class="text-muted">Will be auto-filled from staff's monthly salary</small>
                        </div>
                        <div class="col-md-6">
                            <label for="add_monthly_progress" class="form-label fw-semibold">Monthly Progress (%)</label>
                            <input type="number" 
                                   step="0.01" 
                                   class="form-control form-control-lg" 
                                   id="add_monthly_progress" 
                                   name="monthly_progress" 
                                   value="0" 
                                   min="0" 
                                   max="1000">
                            <small class="text-muted">Total progress percentage for the month</small>
                        </div>
                        <div class="col-md-6">
                            <label for="add_profit_fund" class="form-label fw-semibold">Profit Fund</label>
                            <input type="number" 
                                   step="0.01" 
                                   class="form-control form-control-lg" 
                                   id="add_profit_fund" 
                                   name="profit_fund" 
                                   value="0">
                            <small class="text-muted">Amount to be added to profit fund</small>
                        </div>
                        <div class="col-md-6">
                            <label for="add_payable_before_advance" class="form-label fw-semibold">Payable Before Advance</label>
                            <input type="number" 
                                   step="0.01" 
                                   class="form-control form-control-lg" 
                                   id="add_payable_before_advance" 
                                   name="payable_before_advance" 
                                   value="0">
                            <small class="text-muted">Usually: (Gross Salary × Progress%) - Profit Fund</small>
                        </div>
                        <div class="col-md-6">
                            <label for="add_advances_deducted" class="form-label fw-semibold">Advances Deducted</label>
                            <input type="number" 
                                   step="0.01" 
                                   class="form-control form-control-lg" 
                                   id="add_advances_deducted" 
                                   name="advances_deducted" 
                                   value="0">
                            <small class="text-muted">Total advances to deduct from salary</small>
                        </div>
                        <div class="col-md-6">
                            <label for="add_net_payable" class="form-label fw-semibold">Net Payable <span class="text-danger">*</span></label>
                            <input type="number" 
                                   step="0.01" 
                                   class="form-control form-control-lg" 
                                   id="add_net_payable" 
                                   name="net_payable" 
                                   value="0" 
                                   required>
                            <small class="text-muted">Auto-calculated: (Payable Before Advance - Advances Deducted)</small>
                        </div>
                        <div class="col-md-12">
                            <label for="add_status" class="form-label fw-semibold">Status</label>
                            <select class="form-select form-select-lg" id="add_status" name="status">
                                <option value="pending">Pending</option>
                                <option value="approved">Approved</option>
                                <option value="paid">Paid</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0 pb-4 px-4">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success" style="background: linear-gradient(135deg, #28a745, #20c997); border: none;">
                        <i class="bi bi-plus-circle me-2"></i>Add Salary
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Edit Salary Modal -->
<div class="modal fade" id="editSalaryModal" tabindex="-1" aria-labelledby="editSalaryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content shadow-lg border-0" style="border-radius: 16px;">
            <div class="modal-header border-0 pb-3" style="background: linear-gradient(135deg, #007bff, #706fd3);">
                <h5 class="modal-title text-white fw-bold" id="editSalaryModalLabel">
                    <i class="bi bi-pencil-square me-2"></i>Edit Salary
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="" id="editSalaryForm">
                <input type="hidden" name="action" value="edit_salary">
                <input type="hidden" name="csrf_token" value="<?= h(generate_csrf_token()) ?>">
                <input type="hidden" name="salary_id" id="edit_salary_id">
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="edit_gross_salary" class="form-label fw-semibold">Gross Salary <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" class="form-control form-control-lg" id="edit_gross_salary" name="gross_salary" required>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_profit_fund" class="form-label fw-semibold">Profit Fund</label>
                            <input type="number" step="0.01" class="form-control form-control-lg" id="edit_profit_fund" name="profit_fund" value="0">
                        </div>
                        <div class="col-md-6">
                            <label for="edit_ticket_penalties" class="form-label fw-semibold">Ticket Penalties</label>
                            <input type="number" step="0.01" class="form-control form-control-lg" id="edit_ticket_penalties" name="ticket_penalties" value="0">
                        </div>
                        <div class="col-md-6">
                            <label for="edit_group_penalties" class="form-label fw-semibold">Group Penalties</label>
                            <input type="number" step="0.01" class="form-control form-control-lg" id="edit_group_penalties" name="group_penalties" value="0">
                        </div>
                        <div class="col-md-6">
                            <label for="edit_overtime_bonus" class="form-label fw-semibold">Overtime Bonus</label>
                            <input type="number" step="0.01" class="form-control form-control-lg" id="edit_overtime_bonus" name="overtime_bonus" value="0">
                        </div>
                        <div class="col-md-6">
                            <label for="edit_net_payable" class="form-label fw-semibold">Net Payable (Auto Calculated)</label>
                            <input type="number" step="0.01" class="form-control form-control-lg bg-light" id="edit_net_payable" name="net_payable" readonly>
                        </div>
                        <div class="col-md-12">
                            <label for="edit_status" class="form-label fw-semibold">Status</label>
                            <select class="form-select form-select-lg" id="edit_status" name="status">
                                <option value="pending">Pending</option>
                                <option value="approved">Approved</option>
                                <option value="paid">Paid</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0 pb-4 px-4">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" style="background: linear-gradient(135deg, #007bff, #706fd3); border: none;">
                        <i class="bi bi-check-circle me-2"></i>Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.salary-row:hover {
    background-color: rgba(0, 123, 255, 0.03) !important;
    transform: translateX(4px);
}

.salary-row:hover .btn-outline-primary,
.salary-row:hover .btn-outline-success,
.salary-row:hover .btn-outline-info,
.salary-row:hover .btn-outline-warning {
    transform: scale(1.1);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
}

@media (max-width: 991px) {
    .salary-mobile-card {
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        border: 1px solid rgba(0, 0, 0, 0.05) !important;
    }
    
    .salary-mobile-card:hover,
    .salary-mobile-card:active {
        transform: translateY(-2px);
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15) !important;
    }
}
</style>

<script>
const csrfToken = '<?= h(generate_csrf_token()) ?>';

function openAddSalaryModal() {
    // Reset form
    document.getElementById('addSalaryForm').reset();
    document.getElementById('add_month').value = <?= $month ?>;
    document.getElementById('add_year').value = <?= $year ?>;
    document.getElementById('add_status').value = 'pending';
    document.getElementById('add_monthly_progress').value = '0';
    document.getElementById('add_profit_fund').value = '0';
    document.getElementById('add_advances_deducted').value = '0';
    document.getElementById('add_payable_before_advance').value = '0';
    document.getElementById('add_net_payable').value = '0';
    
    const modal = new bootstrap.Modal(document.getElementById('addSalaryModal'));
    modal.show();
}

// Setup add salary form event listeners on page load
document.addEventListener('DOMContentLoaded', function() {
    // Auto-fill gross salary when staff is selected
    const userSelect = document.getElementById('add_user_id');
    if (userSelect) {
        userSelect.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            if (selectedOption && selectedOption.dataset.salary) {
                document.getElementById('add_gross_salary').value = selectedOption.dataset.salary;
                calculateAddPayableBeforeAdvance();
                calculateAddNetPayable();
            }
        });
    }
    
    // Auto-calculate payable before advance based on gross salary, progress, and profit fund
    ['add_gross_salary', 'add_monthly_progress', 'add_profit_fund'].forEach(id => {
        const el = document.getElementById(id);
        if (el) {
            el.addEventListener('input', calculateAddPayableBeforeAdvance);
        }
    });
    
    // Calculate net payable when inputs change
    ['add_payable_before_advance', 'add_advances_deducted'].forEach(id => {
        const el = document.getElementById(id);
        if (el) {
            el.addEventListener('input', calculateAddNetPayable);
        }
    });
});

function calculateAddPayableBeforeAdvance() {
    const grossSalary = parseFloat(document.getElementById('add_gross_salary').value) || 0;
    const monthlyProgress = parseFloat(document.getElementById('add_monthly_progress').value) || 0;
    const profitFund = parseFloat(document.getElementById('add_profit_fund').value) || 0;
    
    // Payable Before Advance = (Gross Salary × Progress%) - Profit Fund
    const payableBeforeAdvance = Math.max(0, (grossSalary * monthlyProgress / 100) - profitFund);
    document.getElementById('add_payable_before_advance').value = payableBeforeAdvance.toFixed(2);
    
    // Recalculate net payable
    calculateAddNetPayable();
}

function calculateAddNetPayable() {
    const payableBeforeAdvance = parseFloat(document.getElementById('add_payable_before_advance').value) || 0;
    const advancesDeducted = parseFloat(document.getElementById('add_advances_deducted').value) || 0;
    
    const netPayable = Math.max(0, payableBeforeAdvance - advancesDeducted);
    document.getElementById('add_net_payable').value = netPayable.toFixed(2);
}

function openEditModal(salary) {
    document.getElementById('edit_salary_id').value = salary.id;
    document.getElementById('edit_gross_salary').value = salary.gross_salary;
    document.getElementById('edit_profit_fund').value = salary.profit_fund || 0;
    document.getElementById('edit_ticket_penalties').value = 0;
    document.getElementById('edit_group_penalties').value = 0;
    document.getElementById('edit_overtime_bonus').value = 0;
    document.getElementById('edit_status').value = salary.status;
    calculateNetPayable();
    
    const modal = new bootstrap.Modal(document.getElementById('editSalaryModal'));
    modal.show();
}

function calculateNetPayable() {
    const gross = parseFloat(document.getElementById('edit_gross_salary').value) || 0;
    const profitFund = parseFloat(document.getElementById('edit_profit_fund').value) || 0;
    const ticketPenalties = parseFloat(document.getElementById('edit_ticket_penalties').value) || 0;
    const groupPenalties = parseFloat(document.getElementById('edit_group_penalties').value) || 0;
    const overtimeBonus = parseFloat(document.getElementById('edit_overtime_bonus').value) || 0;
    
    const net = Math.max(0, gross - ticketPenalties - groupPenalties + overtimeBonus - profitFund);
    document.getElementById('edit_net_payable').value = net.toFixed(2);
}

['edit_gross_salary', 'edit_profit_fund', 'edit_ticket_penalties', 'edit_group_penalties', 'edit_overtime_bonus'].forEach(id => {
    const el = document.getElementById(id);
    if (el) {
        el.addEventListener('input', calculateNetPayable);
    }
});

async function approveSalary(id) {
    try {
        // Ensure Notify is available
        if (typeof window.Notify === 'undefined') {
            console.warn('Notification system not loaded, using fallback');
            if (confirm('Are you sure you want to approve this salary?')) {
                submitSalaryAction('approve_salary', id);
            }
            return;
        }
        
        const confirmed = await window.Notify.confirm(
            'Are you sure you want to approve this salary?',
            'Approve Salary',
            'Approve',
            'Cancel',
            'warning'
        );
        if (!confirmed) return;
        
        submitSalaryAction('approve_salary', id);
    } catch (error) {
        console.error('Error in approveSalary:', error);
        if (confirm('Are you sure you want to approve this salary?')) {
            submitSalaryAction('approve_salary', id);
        }
    }
}

function submitSalaryAction(action, salaryId) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = window.location.href;
    form.innerHTML = `
        <input type="hidden" name="action" value="${action}">
        <input type="hidden" name="csrf_token" value="${csrfToken}">
        <input type="hidden" name="salary_id" value="${salaryId}">
    `;
    document.body.appendChild(form);
    form.submit();
}

async function markPaid(id) {
    try {
        // Ensure Notify is available
        if (typeof window.Notify === 'undefined') {
            console.warn('Notification system not loaded, using fallback');
            if (confirm('Are you sure you want to mark this salary as paid?')) {
                submitSalaryAction('mark_paid', id);
            }
            return;
        }
        
        const confirmed = await window.Notify.confirm(
            'Are you sure you want to mark this salary as paid?',
            'Mark as Paid',
            'Mark Paid',
            'Cancel',
            'info'
        );
        if (!confirmed) return;
        
        submitSalaryAction('mark_paid', id);
    } catch (error) {
        console.error('Error in markPaid:', error);
        if (confirm('Are you sure you want to mark this salary as paid?')) {
            submitSalaryAction('mark_paid', id);
        }
    }
}

async function revertSalary(id) {
    try {
        // Ensure Notify is available
        if (typeof window.Notify === 'undefined') {
            console.warn('Notification system not loaded, using fallback');
            if (confirm('Are you sure you want to revert this salary to pending? This will remove profit fund from balance if already approved.')) {
                submitSalaryAction('revert_salary', id);
            }
            return;
        }
        
        const confirmed = await window.Notify.confirm(
            'Are you sure you want to revert this salary to pending? This will remove profit fund from balance if already approved.',
            'Revert Salary',
            'Revert',
            'Cancel',
            'danger'
        );
        if (!confirmed) return;
        
        submitSalaryAction('revert_salary', id);
    } catch (error) {
        console.error('Error in revertSalary:', error);
        if (confirm('Are you sure you want to revert this salary to pending? This will remove profit fund from balance if already approved.')) {
            submitSalaryAction('revert_salary', id);
        }
    }
}

async function deleteSalary(id) {
    try {
        // Ensure Notify is available
        if (typeof window.Notify === 'undefined') {
            console.warn('Notification system not loaded, using fallback');
            if (confirm('Are you sure you want to delete this salary record? This action cannot be undone. If the salary was approved, the profit fund will be removed from balance.')) {
                submitSalaryAction('delete_salary', id);
            }
            return;
        }
        
        const confirmed = await window.Notify.confirm(
            'Are you sure you want to delete this salary record? This action cannot be undone. If the salary was approved, the profit fund will be removed from balance.',
            'Delete Salary',
            'Delete',
            'Cancel',
            'danger'
        );
        if (!confirmed) return;
        
        submitSalaryAction('delete_salary', id);
    } catch (error) {
        console.error('Error in deleteSalary:', error);
        if (confirm('Are you sure you want to delete this salary record? This action cannot be undone. If the salary was approved, the profit fund will be removed from balance.')) {
            submitSalaryAction('delete_salary', id);
        }
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const payrollForm = document.getElementById('payrollForm');
    if (payrollForm) {
        payrollForm.addEventListener('submit', function(e) {
            const btn = e.submitter;
            if (btn) {
                const originalHTML = btn.innerHTML;
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Processing...';
            }
        });
    }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
