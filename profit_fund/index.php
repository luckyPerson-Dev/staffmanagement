<?php
/**
 * profit_fund/index.php
 * Profit Fund Management for Superadmin
 * Shows month-based results with filter system
 * Only shows profit fund from approved salaries
 */

require_once __DIR__ . '/../auth_helper.php';
require_once __DIR__ . '/../helpers.php';

require_role(['superadmin']);

$pdo = getPDO();
$message = '';
$error = '';

// Handle success/error messages from GET parameters
if (isset($_GET['success'])) {
    $message = $_GET['success'];
}
if (isset($_GET['error'])) {
    $error = $_GET['error'];
}

// Get filter parameters (from GET or POST)
$filter_month = isset($_REQUEST['filter_month']) ? intval($_REQUEST['filter_month']) : (isset($_GET['month']) ? intval($_GET['month']) : (int)date('m'));
$filter_year = isset($_REQUEST['filter_year']) ? intval($_REQUEST['filter_year']) : (isset($_GET['year']) ? intval($_GET['year']) : (int)date('Y'));
$filter_user_id = isset($_REQUEST['filter_user_id']) ? intval($_REQUEST['filter_user_id']) : (isset($_GET['user_id']) ? intval($_GET['user_id']) : 0);
$show_deleted = (isset($_REQUEST['show_deleted']) && $_REQUEST['show_deleted'] == '1') || (isset($_GET['show_deleted']) && $_GET['show_deleted'] == '1');

// Validate month/year
if ($filter_month < 1 || $filter_month > 12) {
    $filter_month = (int)date('m');
}
if ($filter_year < 2000 || $filter_year > 2100) {
    $filter_year = (int)date('Y');
}

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token.';
    } else {
        $action = $_POST['action'] ?? '';
        $user_id = intval($_POST['user_id'] ?? 0);
        
        if ($action === 'create' && $user_id) {
            // Initialize profit fund balance for user
            $stmt = $pdo->prepare("
                INSERT INTO profit_fund_balance (user_id, balance, updated_at)
                VALUES (?, 0, UTC_TIMESTAMP())
                ON DUPLICATE KEY UPDATE updated_at = UTC_TIMESTAMP()
            ");
            $stmt->execute([$user_id]);
            
            $user = $pdo->prepare("SELECT name FROM users WHERE id = ?");
            $user->execute([$user_id]);
            $user_data = $user->fetch();
            
            log_audit(current_user()['id'], 'create', 'profit_fund_balance', $user_id, "Initialized profit fund for user: {$user_data['name']}");
            $message = 'Profit fund initialized successfully.';
        } elseif ($action === 'close' && $user_id) {
            // Close/reset profit fund balance - Delete the record so "Activate" button shows again
            $user = $pdo->prepare("SELECT name FROM users WHERE id = ?");
            $user->execute([$user_id]);
            $user_data = $user->fetch();
            
            // Delete the profit_fund_balance record to show "Activate" button again
            $stmt = $pdo->prepare("
                DELETE FROM profit_fund_balance 
                WHERE user_id = ?
            ");
            $stmt->execute([$user_id]);
            
            log_audit(current_user()['id'], 'delete', 'profit_fund_balance', $user_id, "Closed profit fund for user: {$user_data['name']} - Record deleted to allow reactivation");
            $message = 'Profit fund closed successfully. You can activate it again using the "Activate" button.';
        } elseif ($action === 'activate' && $user_id) {
            // Activate profit fund balance (initialize if doesn't exist, or reactivate)
            $stmt = $pdo->prepare("
                INSERT INTO profit_fund_balance (user_id, balance, updated_at)
                VALUES (?, 0, UTC_TIMESTAMP())
                ON DUPLICATE KEY UPDATE updated_at = UTC_TIMESTAMP()
            ");
            $stmt->execute([$user_id]);
            
            $user = $pdo->prepare("SELECT name FROM users WHERE id = ?");
            $user->execute([$user_id]);
            $user_data = $user->fetch();
            
            log_audit(current_user()['id'], 'update', 'profit_fund_balance', $user_id, "Activated profit fund for user: {$user_data['name']}");
            $message = 'Profit fund activated successfully.';
        } elseif ($action === 'edit_balance' && $user_id) {
            // Edit profit fund balance (admin/superadmin only)
            require_role(['admin', 'superadmin']);
            
            $user = $pdo->prepare("SELECT name FROM users WHERE id = ?");
            $user->execute([$user_id]);
            $user_data = $user->fetch();
            
            if (!$user_data) {
                $error = 'User not found.';
            } else {
                $new_balance = floatval($_POST['new_balance'] ?? 0);
                $reason = trim($_POST['reason'] ?? '');
                
                if ($new_balance < 0) {
                    $error = 'Balance cannot be negative.';
                } else {
                    // Get current balance
                    $stmt = $pdo->prepare("SELECT balance FROM profit_fund_balance WHERE user_id = ?");
                    $stmt->execute([$user_id]);
                    $current_balance_record = $stmt->fetch();
                    $old_balance = $current_balance_record ? floatval($current_balance_record['balance']) : 0;
                    
                    // Update or create balance
                    if ($current_balance_record) {
                        $stmt = $pdo->prepare("
                            UPDATE profit_fund_balance 
                            SET balance = ?, updated_at = UTC_TIMESTAMP()
                            WHERE user_id = ?
                        ");
                        $stmt->execute([$new_balance, $user_id]);
                    } else {
                        $stmt = $pdo->prepare("
                            INSERT INTO profit_fund_balance (user_id, balance, updated_at)
                            VALUES (?, ?, UTC_TIMESTAMP())
                        ");
                        $stmt->execute([$user_id, $new_balance]);
                    }
                    
                    $balance_change = $new_balance - $old_balance;
                    $change_text = $balance_change > 0 ? "increased by ৳" . number_format($balance_change, 2) : ($balance_change < 0 ? "decreased by ৳" . number_format(abs($balance_change), 2) : "set to");
                    $log_message = "Edited profit fund balance for user: {$user_data['name']} - Old: ৳" . number_format($old_balance, 2) . ", New: ৳" . number_format($new_balance, 2);
                    if ($reason) {
                        $log_message .= " - Reason: " . $reason;
                    }
                    
                    log_audit(current_user()['id'], 'update', 'profit_fund_balance', $user_id, $log_message);
                    
                    // Redirect with success message
                    $base = rtrim(BASE_URL, '/');
                    $redirect_url = $base . '/profit_fund/index.php?';
                    if ($filter_month) $redirect_url .= 'month=' . $filter_month . '&';
                    if ($filter_year) $redirect_url .= 'year=' . $filter_year . '&';
                    if ($filter_user_id) $redirect_url .= 'user_id=' . $filter_user_id . '&';
                    if ($show_deleted) $redirect_url .= 'show_deleted=1&';
                    $redirect_url .= 'success=' . urlencode("Profit fund balance updated successfully for {$user_data['name']}. New balance: ৳" . number_format($new_balance, 2));
                    header('Location: ' . rtrim($redirect_url, '&'));
                    exit;
                }
            }
        } elseif ($action === 'delete' && $user_id) {
            // Delete all profit fund data for user
            $user = $pdo->prepare("SELECT name FROM users WHERE id = ?");
            $user->execute([$user_id]);
            $user_data = $user->fetch();
            
            if (!$user_data) {
                // User not found - redirect with error
                $base = rtrim(BASE_URL, '/');
                $redirect_url = $base . '/profit_fund/index.php?';
                if ($filter_month) $redirect_url .= 'month=' . $filter_month . '&';
                if ($filter_year) $redirect_url .= 'year=' . $filter_year . '&';
                if ($filter_user_id) $redirect_url .= 'user_id=' . $filter_user_id . '&';
                if ($show_deleted) $redirect_url .= 'show_deleted=1&';
                $redirect_url .= 'error=' . urlencode('User not found.');
                header('Location: ' . rtrim($redirect_url, '&'));
                exit;
            }
            
            try {
                // Temporarily disable foreign key checks to ensure deletion works
                $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
                
                $pdo->beginTransaction();
                
                $deleted_counts = [];
                
                // Delete profit fund withdrawals first
                $stmt = $pdo->prepare("DELETE FROM profit_fund_withdrawals WHERE user_id = ?");
                $stmt->execute([$user_id]);
                $deleted_counts['withdrawals'] = $stmt->rowCount();
                
                // Delete profit fund entries (monthly contributions)
                $stmt = $pdo->prepare("DELETE FROM profit_fund WHERE user_id = ?");
                $stmt->execute([$user_id]);
                $deleted_counts['contributions'] = $stmt->rowCount();
                
                // Delete profit fund balance
                $stmt = $pdo->prepare("DELETE FROM profit_fund_balance WHERE user_id = ?");
                $stmt->execute([$user_id]);
                $deleted_counts['balance'] = $stmt->rowCount();
                
                $pdo->commit();
                
                // Re-enable foreign key checks
                $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
                
                $details = "Balance: {$deleted_counts['balance']}, Contributions: {$deleted_counts['contributions']}, Withdrawals: {$deleted_counts['withdrawals']}";
                log_audit(current_user()['id'], 'delete', 'profit_fund_all', $user_id, "Deleted all profit fund data for user: {$user_data['name']} - $details");
                
                // Redirect to prevent form resubmission
                $base = rtrim(BASE_URL, '/');
                $redirect_url = $base . '/profit_fund/index.php?';
                if ($filter_month) $redirect_url .= 'month=' . $filter_month . '&';
                if ($filter_year) $redirect_url .= 'year=' . $filter_year . '&';
                if ($filter_user_id) $redirect_url .= 'user_id=' . $filter_user_id . '&';
                if ($show_deleted) $redirect_url .= 'show_deleted=1&';
                $redirect_url .= 'success=' . urlencode('All profit fund data deleted successfully for ' . h($user_data['name']) . '.');
                header('Location: ' . rtrim($redirect_url, '&'));
                exit;
            } catch (PDOException $e) {
                $pdo->rollBack();
                // Re-enable foreign key checks in case of error
                $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
                $error_msg = 'Failed to delete profit fund data: ' . $e->getMessage();
                error_log("Profit fund delete error [User ID: $user_id]: " . $e->getMessage());
                error_log("PDO Error Code: " . $e->getCode());
                error_log("PDO Error Info: " . print_r($e->errorInfo, true));
                
                // Redirect with error message
                $base = rtrim(BASE_URL, '/');
                $redirect_url = $base . '/profit_fund/index.php?';
                if ($filter_month) $redirect_url .= 'month=' . $filter_month . '&';
                if ($filter_year) $redirect_url .= 'year=' . $filter_year . '&';
                if ($filter_user_id) $redirect_url .= 'user_id=' . $filter_user_id . '&';
                if ($show_deleted) $redirect_url .= 'show_deleted=1&';
                $redirect_url .= 'error=' . urlencode($error_msg);
                header('Location: ' . rtrim($redirect_url, '&'));
                exit;
            } catch (Exception $e) {
                $pdo->rollBack();
                // Re-enable foreign key checks in case of error
                $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
                $error_msg = 'Failed to delete profit fund data: ' . $e->getMessage();
                error_log("Profit fund delete error [User ID: $user_id]: " . $e->getMessage());
                
                // Redirect with error message
                $base = rtrim(BASE_URL, '/');
                $redirect_url = $base . '/profit_fund/index.php?';
                if ($filter_month) $redirect_url .= 'month=' . $filter_month . '&';
                if ($filter_year) $redirect_url .= 'year=' . $filter_year . '&';
                if ($filter_user_id) $redirect_url .= 'user_id=' . $filter_user_id . '&';
                if ($show_deleted) $redirect_url .= 'show_deleted=1&';
                $redirect_url .= 'error=' . urlencode($error_msg);
                header('Location: ' . rtrim($redirect_url, '&'));
                exit;
            }
        }
    }
}

// $show_deleted already defined above from REQUEST

// Get all staff with their profit fund balances
$deleted_filter = $show_deleted ? '' : "AND u.deleted_at IS NULL";
$where_clause = "u.role = 'staff' AND (u.status = 'active' OR u.status IS NULL) {$deleted_filter}";
$params = [];

if ($filter_user_id > 0) {
    $where_clause .= " AND u.id = ?";
    $params[] = $filter_user_id;
}

$stmt = $pdo->prepare("
    SELECT 
        u.id,
        u.name,
        u.email,
        u.monthly_salary,
        u.deleted_at,
        COALESCE(pfb.balance, 0) as balance,
        CASE WHEN pfb.user_id IS NULL THEN 0 ELSE 1 END as has_fund,
        COALESCE((SELECT COUNT(*) FROM profit_fund pf 
                  INNER JOIN salary_history sh ON pf.user_id = sh.user_id 
                    AND pf.month = sh.month AND pf.year = sh.year
                  WHERE pf.user_id = u.id AND sh.status = 'approved'), 0) as total_contributions,
        COALESCE((SELECT SUM(pf.amount) FROM profit_fund pf 
                  INNER JOIN salary_history sh ON pf.user_id = sh.user_id 
                    AND pf.month = sh.month AND pf.year = sh.year
                  WHERE pf.user_id = u.id AND sh.status = 'approved'), 0) as total_contributed,
        COALESCE((SELECT COUNT(*) FROM profit_fund pf 
                  INNER JOIN salary_history sh ON pf.user_id = sh.user_id 
                    AND pf.month = sh.month AND pf.year = sh.year
                  WHERE pf.user_id = u.id AND sh.status = 'pending'), 0) as pending_contributions,
        COALESCE((SELECT SUM(pf.amount) FROM profit_fund pf 
                  INNER JOIN salary_history sh ON pf.user_id = sh.user_id 
                    AND pf.month = sh.month AND pf.year = sh.year
                  WHERE pf.user_id = u.id AND sh.status = 'pending'), 0) as pending_amount,
        COALESCE((SELECT COUNT(*) FROM profit_fund_withdrawals WHERE user_id = u.id AND status IN ('approved', 'paid')), 0) as total_withdrawals,
        COALESCE((SELECT SUM(amount) FROM profit_fund_withdrawals WHERE user_id = u.id AND status IN ('approved', 'paid')), 0) as total_withdrawn
    FROM users u
    LEFT JOIN profit_fund_balance pfb ON u.id = pfb.user_id
    WHERE {$where_clause}
    ORDER BY u.deleted_at IS NULL DESC, u.name
");
$stmt->execute($params);
$staff_funds = $stmt->fetchAll();

// Get month-based data for each staff member
foreach ($staff_funds as &$fund) {
    $stmt_months = $pdo->prepare("
        SELECT COUNT(DISTINCT CONCAT(pf.month, '-', pf.year)) as month_count,
               GROUP_CONCAT(DISTINCT CONCAT(pf.month, '/', pf.year) ORDER BY pf.year DESC, pf.month DESC SEPARATOR ', ') as months_list
        FROM profit_fund pf 
        INNER JOIN salary_history sh ON pf.user_id = sh.user_id 
            AND pf.month = sh.month AND pf.year = sh.year
        WHERE pf.user_id = ? AND sh.status = 'approved'
    ");
    $stmt_months->execute([$fund['id']]);
    $month_info = $stmt_months->fetch();
    $fund['months_with_data'] = intval($month_info['month_count'] ?? 0);
    $fund['months_list'] = $month_info['months_list'] ?? '';
}
unset($fund);

// Get month-specific profit fund data (only from approved salaries)
$month_deleted_filter = $show_deleted ? '' : 'AND u.deleted_at IS NULL';
$month_where = "sh.status = 'approved' AND sh.month = ? AND sh.year = ? {$month_deleted_filter}";
$month_params = [$filter_month, $filter_year];

if ($filter_user_id > 0) {
    $month_where .= " AND sh.user_id = ?";
    $month_params[] = $filter_user_id;
}

$stmt = $pdo->prepare("
    SELECT 
        u.id,
        u.name,
        u.deleted_at,
        sh.gross_salary,
        sh.monthly_progress,
        pf.amount as profit_fund_amount,
        sh.status as salary_status,
        sh.approved_at
    FROM salary_history sh
    INNER JOIN users u ON sh.user_id = u.id
    LEFT JOIN profit_fund pf ON sh.user_id = pf.user_id 
        AND sh.month = pf.month 
        AND sh.year = pf.year
    WHERE {$month_where}
    ORDER BY u.deleted_at IS NULL DESC, u.name
");
$stmt->execute($month_params);
$month_data = $stmt->fetchAll();

// Calculate month totals
$month_total_profit_fund = array_sum(array_map(function($row) {
    return floatval($row['profit_fund_amount'] ?? 0);
}, $month_data));

$month_total_salary = array_sum(array_map(function($row) {
    return floatval($row['gross_salary'] ?? 0);
}, $month_data));

// Calculate totals (all time, only approved)
$total_balance = array_sum(array_map(function($f) { return floatval($f['balance'] ?? 0); }, $staff_funds));
$total_contributed = array_sum(array_map(function($f) { return floatval($f['total_contributed'] ?? 0); }, $staff_funds));
$total_withdrawn = array_sum(array_map(function($f) { return floatval($f['total_withdrawn'] ?? 0); }, $staff_funds));

$page_title = 'Profit Fund Management';
include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="gradient-text mb-2">Profit Fund Management</h1>
            <p class="text-muted mb-0">Track and manage profit funds for all staff members</p>
        </div>
    </div>
    
    <?php if ($message): ?>
        <div class="alert alert-success alert-dismissible fade show animate-fade-in" role="alert">
            <i class="bi bi-check-circle me-2"></i><?= h($message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show animate-fade-in" role="alert">
            <i class="bi bi-exclamation-triangle me-2"></i><?= h($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <!-- Filter Section -->
    <div class="card shadow-lg border-0 mb-4">
        <div class="card-header bg-white border-bottom">
            <h5 class="mb-0 fw-semibold">
                <i class="bi bi-funnel me-2 text-primary"></i>Filter Options
            </h5>
        </div>
        <div class="card-body">
            <form method="GET" action="" class="row g-3">
                <div class="col-md-3">
                    <label for="month" class="form-label fw-semibold">Month</label>
                    <select class="form-select form-select-lg" id="month" name="month">
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?= $m ?>" <?= $filter_month == $m ? 'selected' : '' ?>>
                                <?= date('F', mktime(0, 0, 0, $m, 1)) ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="year" class="form-label fw-semibold">Year</label>
                    <input type="number" 
                           class="form-control form-control-lg" 
                           id="year" 
                           name="year" 
                           value="<?= $filter_year ?>" 
                           min="2000" 
                           max="2100">
                </div>
                <div class="col-md-4">
                    <label for="user_id" class="form-label fw-semibold">Staff Member (Optional)</label>
                    <select class="form-select form-select-lg" id="user_id" name="user_id">
                        <option value="0">All Staff</option>
                        <?php
                        $staff_filter = $show_deleted ? '' : 'AND deleted_at IS NULL';
                        $stmt = $pdo->query("SELECT id, name, deleted_at FROM users WHERE role = 'staff' {$staff_filter} ORDER BY deleted_at IS NULL DESC, name");
                        $all_staff = $stmt->fetchAll();
                        foreach ($all_staff as $staff):
                        ?>
                            <option value="<?= $staff['id'] ?>" <?= $filter_user_id == $staff['id'] ? 'selected' : '' ?>>
                                <?= h($staff['name']) ?><?= !empty($staff['deleted_at']) ? ' (Deleted)' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Options</label>
                    <div class="form-check mt-2">
                        <input class="form-check-input" type="checkbox" id="show_deleted" name="show_deleted" value="1" <?= $show_deleted ? 'checked' : '' ?>>
                        <label class="form-check-label" for="show_deleted">
                            Show Deleted Staff
                        </label>
                    </div>
                </div>
                <div class="col-md-1 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary btn-lg w-100">
                        <i class="bi bi-search me-1"></i>Filter
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Statistics Cards -->
    <div class="row g-3 g-md-4 mb-4">
        <div class="col-6 col-md-3">
            <div class="stat-card animate-slide-up">
                <div class="stat-icon" style="background: linear-gradient(135deg, #007bff, #706fd3);">
                    <i class="bi bi-wallet2"></i>
                </div>
                <div class="stat-label small">Total Balance</div>
                <div class="stat-value fs-5 fs-md-4">৳<?= number_format($total_balance, 2) ?></div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card animate-slide-up" style="animation-delay: 0.1s">
                <div class="stat-icon" style="background: linear-gradient(135deg, #28a745, #20c997);">
                    <i class="bi bi-arrow-down-circle-fill"></i>
                </div>
                <div class="stat-label small">Total Contributed</div>
                <div class="stat-value fs-5 fs-md-4">৳<?= number_format($total_contributed ?? 0, 2) ?></div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card animate-slide-up" style="animation-delay: 0.2s">
                <div class="stat-icon" style="background: linear-gradient(135deg, #dc3545, #ff6b6b);">
                    <i class="bi bi-arrow-up-circle-fill"></i>
                </div>
                <div class="stat-label small">Total Withdrawn</div>
                <div class="stat-value fs-5 fs-md-4">৳<?= number_format($total_withdrawn ?? 0, 2) ?></div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card animate-slide-up" style="animation-delay: 0.3s">
                <div class="stat-icon" style="background: linear-gradient(135deg, #17a2b8, #138496);">
                    <i class="bi bi-calendar-month"></i>
                </div>
                <div class="stat-label small"><?= date('F Y', mktime(0, 0, 0, $filter_month, 1, $filter_year)) ?> Profit Fund</div>
                <div class="stat-value fs-5 fs-md-4">৳<?= number_format($month_total_profit_fund, 2) ?></div>
            </div>
        </div>
    </div>
    
    <!-- Staff Profit Funds Table (All Time) -->
    <div class="card shadow-lg border-0">
        <div class="card-header bg-white border-bottom">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="mb-0 fw-semibold">
                        <i class="bi bi-people me-2 text-primary"></i>All Staff Profit Fund Summary
                    </h5>
                    <small class="text-muted">
                        <i class="bi bi-info-circle me-1"></i>
                        Current Balance = Available for withdrawal. Pending = Will be available after salary approval.
                    </small>
                </div>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Staff</th>
                            <th>Monthly Salary</th>
                            <th>Current Balance</th>
                            <th>Pending Profit Fund</th>
                            <th>Total Contributed</th>
                            <th>Total Withdrawn</th>
                            <th>Contributions</th>
                            <th>Status</th>
                            <th class="text-end pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($staff_funds)): ?>
                            <tr>
                                <td colspan="9" class="text-center py-5 text-muted">
                                    <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                                    No staff members found
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($staff_funds as $fund): ?>
                                <tr style="<?= !empty($fund['deleted_at']) ? 'opacity: 0.7; background-color: #f8f9fa;' : '' ?>">
                                    <td class="ps-4">
                                        <div class="d-flex align-items-center">
                                            <div class="rounded-circle bg-gradient-primary d-flex align-items-center justify-content-center me-3" 
                                                 style="width: 40px; height: 40px; background: linear-gradient(135deg, #007bff, #706fd3);">
                                                <span class="text-white fw-bold"><?= strtoupper(substr($fund['name'], 0, 1)) ?></span>
                                            </div>
                                            <div>
                                                <strong class="d-block">
                                                    <?= h($fund['name']) ?>
                                                    <?php if (!empty($fund['deleted_at'])): ?>
                                                        <span class="badge bg-secondary ms-2" title="Deleted User">Deleted</span>
                                                    <?php endif; ?>
                                                </strong>
                                                <small class="text-muted"><?= h($fund['email']) ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <i class="bi bi-cash-stack text-primary me-2"></i>
                                            <span class="fw-semibold">৳<?= number_format($fund['monthly_salary'], 2) ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <i class="bi bi-wallet2 text-<?= $fund['balance'] > 0 ? 'success' : 'muted' ?> me-2"></i>
                                            <strong class="text-<?= $fund['balance'] > 0 ? 'success' : 'muted' ?>">
                                                ৳<?= number_format($fund['balance'], 2) ?>
                                            </strong>
                                        </div>
                                        <small class="text-muted d-block mt-1">
                                            <i class="bi bi-info-circle me-1"></i>Available for withdrawal
                                        </small>
                                    </td>
                                    <td>
                                        <?php 
                                        $pending_amount = floatval($fund['pending_amount'] ?? 0);
                                        $pending_contributions = intval($fund['pending_contributions'] ?? 0);
                                        ?>
                                        <?php if ($pending_amount > 0): ?>
                                            <div class="d-flex align-items-center">
                                                <i class="bi bi-clock-history text-warning me-2"></i>
                                                <span class="text-warning fw-semibold">
                                                    ৳<?= number_format($pending_amount, 2) ?>
                                                </span>
                                            </div>
                                            <small class="text-muted d-block mt-1">
                                                <?= $pending_contributions ?> pending salary(ies)
                                            </small>
                                        <?php else: ?>
                                            <span class="text-muted">
                                                <i class="bi bi-dash-circle me-1"></i>No pending
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <i class="bi bi-arrow-down-circle text-success me-2"></i>
                                            <span class="text-success fw-semibold">
                                                ৳<?= number_format($fund['total_contributed'] ?? 0, 2) ?>
                                            </span>
                                        </div>
                                        <small class="text-muted d-block mt-1">
                                            From approved salaries
                                        </small>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <i class="bi bi-arrow-up-circle text-danger me-2"></i>
                                            <span class="text-danger fw-semibold">
                                                ৳<?= number_format($fund['total_withdrawn'] ?? 0, 2) ?>
                                            </span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2 flex-wrap">
                                            <span class="badge bg-info bg-gradient px-3 py-2">
                                                <i class="bi bi-list-check me-1"></i>
                                                <?= $fund['total_contributions'] ?? 0 ?>
                                            </span>
                                            <?php 
                                            $month_count = $fund['months_with_data'] ?? 0;
                                            $months_list = $fund['months_list'] ?? '';
                                            ?>
                                            <?php if ($month_count > 0): ?>
                                                <button type="button" 
                                                        class="btn btn-sm btn-outline-primary" 
                                                        data-bs-toggle="popover" 
                                                        data-bs-placement="left"
                                                        data-bs-trigger="click"
                                                        data-bs-html="true"
                                                        data-bs-content="<div class='text-start'><strong>Months with Profit Fund Data:</strong> <span class='badge bg-primary'><?= $month_count ?></span><br><br><strong>Month/Year List:</strong><br><small class='text-muted'><?= h($months_list) ?></small></div>"
                                                        title="Month-Based Profit Fund Data">
                                                    <i class="bi bi-calendar-month me-1"></i>
                                                    <span class="badge bg-primary"><?= $month_count ?></span>
                                                    <small class="ms-1">months</small>
                                                </button>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">No month data</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($fund['has_fund']): ?>
                                            <span class="badge bg-success bg-gradient px-3 py-2">
                                                <i class="bi bi-check-circle me-1"></i>Active
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary px-3 py-2">
                                                <i class="bi bi-x-circle me-1"></i>Not Initialized
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end pe-4">
                                        <div class="d-flex align-items-center gap-2 justify-content-end">
                                            <?php if (!$fund['has_fund']): ?>
                                                <form method="POST" action="" class="d-inline profit-fund-form" data-action="activate" data-user="<?= h($fund['name']) ?>">
                                                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                                    <input type="hidden" name="action" value="activate">
                                                    <input type="hidden" name="user_id" value="<?= $fund['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-success shadow-sm" title="Activate Profit Fund">
                                                        <i class="bi bi-check-circle me-1"></i>Activate
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <form method="POST" action="" class="d-inline profit-fund-form" data-action="close" data-user="<?= h($fund['name']) ?>">
                                                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                                    <input type="hidden" name="action" value="close">
                                                    <input type="hidden" name="user_id" value="<?= $fund['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-warning shadow-sm" title="Close/Reset Profit Fund">
                                                        <i class="bi bi-x-circle me-1"></i>Close
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            <?php if (in_array(current_user()['role'], ['admin', 'superadmin'])): ?>
                                                <button type="button" 
                                                        class="btn btn-sm btn-info shadow-sm" 
                                                        title="Edit Current Balance"
                                                        onclick="openEditBalanceModal(<?= $fund['id'] ?>, '<?= h($fund['name']) ?>', <?= number_format($fund['balance'], 2, '.', '') ?>)">
                                                    <i class="bi bi-pencil-square me-1"></i>Edit Balance
                                                </button>
                                            <?php endif; ?>
                                            <form method="POST" action="<?= rtrim(BASE_URL, '/') ?>/profit_fund/index.php" class="d-inline profit-fund-form" data-action="delete" data-user="<?= h($fund['name']) ?>">
                                                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="user_id" value="<?= $fund['id'] ?>">
                                                <?php if ($filter_month): ?><input type="hidden" name="filter_month" value="<?= $filter_month ?>"><?php endif; ?>
                                                <?php if ($filter_year): ?><input type="hidden" name="filter_year" value="<?= $filter_year ?>"><?php endif; ?>
                                                <?php if ($filter_user_id): ?><input type="hidden" name="filter_user_id" value="<?= $filter_user_id ?>"><?php endif; ?>
                                                <?php if ($show_deleted): ?><input type="hidden" name="show_deleted" value="1"><?php endif; ?>
                                                <button type="submit" class="btn btn-sm btn-danger shadow-sm" title="Delete All Profit Fund Data">
                                                    <i class="bi bi-trash me-1"></i>Delete
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Month-Based Profit Fund Details -->
    <div class="card shadow-lg border-0 mb-4">
        <div class="card-header bg-white border-bottom">
            <h5 class="mb-0 fw-semibold">
                <i class="bi bi-calendar-month me-2 text-primary"></i>
                Profit Fund Details for <?= date('F Y', mktime(0, 0, 0, $filter_month, 1, $filter_year)) ?>
                <span class="badge bg-success ms-2">Approved Salaries Only</span>
            </h5>
        </div>
        <div class="card-body p-0">
            <?php if (empty($month_data)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                    <p class="mb-0">No approved salaries found for <?= date('F Y', mktime(0, 0, 0, $filter_month, 1, $filter_year)) ?></p>
                    <small class="text-muted">Profit fund is only added when salary is approved by admin.</small>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4">Staff</th>
                                <th>Gross Salary</th>
                                <th>Monthly Progress</th>
                                <th>Profit Fund Amount</th>
                                <th>Approved Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($month_data as $row): ?>
                                <tr style="<?= !empty($row['deleted_at']) ? 'opacity: 0.7; background-color: #f8f9fa;' : '' ?>">
                                    <td class="ps-4">
                                        <div class="d-flex align-items-center">
                                            <div class="rounded-circle bg-gradient-primary d-flex align-items-center justify-content-center me-3" 
                                                 style="width: 40px; height: 40px; background: linear-gradient(135deg, #007bff, #706fd3);">
                                                <span class="text-white fw-bold"><?= strtoupper(substr($row['name'], 0, 1)) ?></span>
                                            </div>
                                            <strong>
                                                <?= h($row['name']) ?>
                                                <?php if (!empty($row['deleted_at'])): ?>
                                                    <span class="badge bg-secondary ms-2" title="Deleted User">Deleted</span>
                                                <?php endif; ?>
                                            </strong>
                                        </div>
                                    </td>
                                    <td>৳<?= number_format($row['gross_salary'], 2) ?></td>
                                    <td>
                                        <span class="badge bg-<?= $row['monthly_progress'] >= 80 ? 'success' : ($row['monthly_progress'] >= 60 ? 'warning' : 'danger') ?>">
                                            <?= number_format($row['monthly_progress'], 1) ?>%
                                        </span>
                                    </td>
                                    <td>
                                        <strong class="text-success">৳<?= number_format($row['profit_fund_amount'] ?? 0, 2) ?></strong>
                                    </td>
                                    <td>
                                        <?= $row['approved_at'] ? date('M d, Y', strtotime($row['approved_at'])) : '-' ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <tr class="table-success">
                                <td class="ps-4"><strong>Total</strong></td>
                                <td><strong>৳<?= number_format($month_total_salary, 2) ?></strong></td>
                                <td>-</td>
                                <td><strong>৳<?= number_format($month_total_profit_fund, 2) ?></strong></td>
                                <td>-</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize Bootstrap popovers for month data buttons
    const popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    popoverTriggerList.map(function (popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });
    
    // Initialize Bootstrap tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Handle profit fund form submissions with custom confirm
    document.querySelectorAll('.profit-fund-form').forEach(form => {
        form.addEventListener('submit', async function(e) {
            const action = this.dataset.action;
            const user = this.dataset.user;
            
            if (action === 'delete') {
                // Prevent default for delete action
                e.preventDefault();
                
                // Special handling for delete - requires typing confirmation
                const deleteMessage = 
                    `⚠️ WARNING: PERMANENT DELETION\n\n` +
                    `Staff Member: ${user}\n\n` +
                    `You are about to permanently delete ALL profit fund data for this staff member.\n\n` +
                    `📋 What will be deleted:\n` +
                    `   • Current profit fund balance\n` +
                    `   • All monthly profit fund contributions\n` +
                    `   • All profit fund withdrawal records\n\n` +
                    `⚠️ IMPORTANT: This action CANNOT be undone!\n\n` +
                    `━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n` +
                    `To confirm deletion, type exactly: DELETE\n` +
                    `(Type in all uppercase letters and click OK)`;
                
                const deleteConfirm = prompt(deleteMessage, '');
                
                if (deleteConfirm === null || deleteConfirm === '') {
                    // User clicked Cancel or closed the dialog or left empty
                    return false;
                }
                
                if (deleteConfirm.trim() !== 'DELETE') {
                    alert('❌ Deletion cancelled.\n\nYou must type "DELETE" exactly (in uppercase) to confirm the deletion.');
                    return false; // User typed wrong
                }
                
                // Double confirmation with clearer message
                const finalConfirm = confirm(
                    `🛑 FINAL CONFIRMATION REQUIRED\n\n` +
                    `Staff Member: ${user}\n\n` +
                    `You are about to PERMANENTLY DELETE:\n` +
                    `• All profit fund balance\n` +
                    `• All monthly contributions\n` +
                    `• All withdrawal records\n\n` +
                    `⚠️ This action CANNOT be undone!\n\n` +
                    `Click OK to proceed with deletion.\n` +
                    `Click Cancel to abort.`
                );
                
                if (!finalConfirm) {
                    return false; // User cancelled
                }
                
                // Show loading state
                const submitBtn = this.querySelector('button[type="submit"]');
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Processing...';
                }
                
                // Create a new form submission that bypasses the event listener
                const formElement = this;
                
                // Get action URL - use getAttribute to ensure we get the string value
                let originalAction = formElement.getAttribute('action');
                
                // If action is not set or invalid, construct it
                if (!originalAction || originalAction === '' || originalAction.includes('undefined') || originalAction.includes('[object')) {
                    const baseUrl = '<?= rtrim(BASE_URL, "/") ?>';
                    originalAction = baseUrl + '/profit_fund/index.php';
                }
                
                // Ensure it's a clean string
                originalAction = String(originalAction).trim();
                
                // Create hidden form
                const hiddenForm = document.createElement('form');
                hiddenForm.method = 'POST';
                hiddenForm.setAttribute('action', originalAction);
                hiddenForm.style.display = 'none';
                
                // Copy all inputs using FormData to ensure we get everything
                const formData = new FormData(formElement);
                for (let [name, value] of formData.entries()) {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = name;
                    input.value = value;
                    hiddenForm.appendChild(input);
                }
                
                // Also copy any select/textarea elements that might have been missed
                formElement.querySelectorAll('select, textarea').forEach(element => {
                    if (element.name && !formData.has(element.name)) {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = element.name;
                        input.value = element.value || '';
                        hiddenForm.appendChild(input);
                    }
                });
                
                document.body.appendChild(hiddenForm);
                
                // Submit immediately
                hiddenForm.submit();
                
                return false;
            }
            
            // For other actions, prevent default and use Notify
            e.preventDefault();
            
            let message = '';
            let title = '';
            let confirmText = '';
            let type = 'warning';
            
            if (action === 'create' || action === 'activate') {
                message = action === 'activate' 
                    ? `Activate profit fund for ${user}? This will initialize the profit fund balance.`
                    : `Initialize profit fund for ${user}?`;
                title = action === 'activate' ? 'Activate Profit Fund' : 'Initialize Profit Fund';
                confirmText = action === 'activate' ? 'Activate' : 'Initialize';
                type = 'info';
            } else if (action === 'close') {
                message = `Close/Reset profit fund for ${user}? This will delete the balance record. You can activate it again later.`;
                title = 'Close Profit Fund';
                confirmText = 'Close';
                type = 'warning';
            }
            
            // Ensure Notify is available for other actions
            if (typeof window.Notify === 'undefined') {
                console.warn('Notification system not loaded, using fallback');
                if (confirm(message)) {
                    if (!this.action) {
                        this.action = window.location.href;
                    }
                    this.submit();
                }
                return;
            }
            
            const confirmed = await window.Notify.confirm(message, title, confirmText, 'Cancel', type);
            if (confirmed) {
                // Set form action if not set
                if (!this.action) {
                    this.action = window.location.href;
                }
                this.submit();
            }
        });
    });
});

// Edit Balance Modal Functions
function openEditBalanceModal(userId, userName, currentBalance) {
    document.getElementById('edit_balance_user_id').value = userId;
    document.getElementById('edit_balance_user_name').textContent = userName;
    document.getElementById('edit_balance_current').textContent = '৳' + parseFloat(currentBalance).toFixed(2);
    document.getElementById('edit_new_balance').value = currentBalance;
    document.getElementById('edit_balance_reason').value = '';
    
    const modal = new bootstrap.Modal(document.getElementById('editBalanceModal'));
    modal.show();
}

async function submitEditBalance() {
    const form = document.getElementById('editBalanceForm');
    const formData = new FormData(form);
    
    // Validation
    const newBalance = parseFloat(formData.get('new_balance'));
    if (isNaN(newBalance) || newBalance < 0) {
        alert('Please enter a valid balance amount (must be 0 or greater).');
        return;
    }
    
    // Show loading state
    const submitBtn = document.getElementById('editBalanceSubmitBtn');
    const originalHTML = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Updating...';
    
    try {
        // Create hidden form to submit
        const hiddenForm = document.createElement('form');
        hiddenForm.method = 'POST';
        hiddenForm.action = '<?= rtrim(BASE_URL, "/") ?>/profit_fund/index.php';
        hiddenForm.style.display = 'none';
        
        // Copy all form data
        for (let [name, value] of formData.entries()) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            input.value = value;
            hiddenForm.appendChild(input);
        }
        
        // Add filter parameters to preserve current view
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('month')) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'filter_month';
            input.value = urlParams.get('month');
            hiddenForm.appendChild(input);
        }
        if (urlParams.has('year')) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'filter_year';
            input.value = urlParams.get('year');
            hiddenForm.appendChild(input);
        }
        if (urlParams.has('user_id')) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'filter_user_id';
            input.value = urlParams.get('user_id');
            hiddenForm.appendChild(input);
        }
        if (urlParams.has('show_deleted')) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'show_deleted';
            input.value = urlParams.get('show_deleted');
            hiddenForm.appendChild(input);
        }
        
        document.body.appendChild(hiddenForm);
        hiddenForm.submit();
    } catch (error) {
        console.error('Error submitting edit balance form:', error);
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalHTML;
        alert('An error occurred while updating the balance. Please try again.');
    }
}
</script>

<!-- Edit Balance Modal -->
<div class="modal fade" id="editBalanceModal" tabindex="-1" aria-labelledby="editBalanceModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow-lg border-0" style="border-radius: 16px;">
            <div class="modal-header border-0 pb-3" style="background: linear-gradient(135deg, #17a2b8, #138496);">
                <h5 class="modal-title text-white fw-bold" id="editBalanceModalLabel">
                    <i class="bi bi-pencil-square me-2"></i>Edit Profit Fund Balance
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="editBalanceForm" onsubmit="event.preventDefault(); submitEditBalance();">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                <input type="hidden" name="action" value="edit_balance">
                <input type="hidden" name="user_id" id="edit_balance_user_id">
                <div class="modal-body p-4">
                    <div class="alert alert-info mb-3">
                        <i class="bi bi-info-circle me-2"></i>
                        <strong>Staff Member:</strong> <span id="edit_balance_user_name"></span>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_balance_current" class="form-label fw-semibold">
                            <i class="bi bi-wallet2 me-1"></i>Current Balance
                        </label>
                        <input type="text" 
                               class="form-control form-control-lg bg-light" 
                               id="edit_balance_current" 
                               readonly 
                               style="font-size: 1.25rem; font-weight: bold; color: #198754;">
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_new_balance" class="form-label fw-semibold">
                            <i class="bi bi-cash-stack me-1"></i>New Balance <span class="text-danger">*</span>
                        </label>
                        <input type="number" 
                               step="0.01" 
                               min="0"
                               class="form-control form-control-lg" 
                               id="edit_new_balance" 
                               name="new_balance" 
                               required
                               placeholder="0.00">
                        <small class="text-muted">Enter the new balance amount. This will replace the current balance.</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_balance_reason" class="form-label fw-semibold">
                            <i class="bi bi-journal-text me-1"></i>Reason (Optional)
                        </label>
                        <textarea class="form-control" 
                                  id="edit_balance_reason" 
                                  name="reason" 
                                  rows="3" 
                                  placeholder="Enter reason for balance adjustment (e.g., Manual adjustment, Correction, etc.)"></textarea>
                        <small class="text-muted">This reason will be logged in the audit trail.</small>
                    </div>
                    
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <strong>Note:</strong> Changing the balance manually will override any automatic calculations. Use with caution.
                    </div>
                </div>
                <div class="modal-footer border-0 pt-3">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-1"></i>Cancel
                    </button>
                    <button type="submit" class="btn btn-info" id="editBalanceSubmitBtn">
                        <i class="bi bi-check-circle me-1"></i>Update Balance
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
