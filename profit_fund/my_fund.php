<?php
/**
 * profit_fund/my_fund.php
 * Staff view of their own profit fund
 */

require_once __DIR__ . '/../auth_helper.php';
require_once __DIR__ . '/../helpers.php';

require_role(['staff']);

$user = current_user();
$pdo = getPDO();
$error = '';
$success = '';

// Get current month and year
$current_month = (int)date('m');
$current_year = (int)date('Y');

// Calculate balance from approved salaries only (exclude current month if not approved)
// This ensures users can't see current month's profit fund until salary is approved
$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(pf.amount), 0) as calculated_balance
    FROM profit_fund pf
    INNER JOIN salary_history sh ON pf.user_id = sh.user_id 
        AND pf.month = sh.month 
        AND pf.year = sh.year
    WHERE pf.user_id = ? 
        AND sh.status = 'approved'
");
$stmt->execute([$user['id']]);
$calculated_balance = floatval($stmt->fetch()['calculated_balance'] ?? 0);

// Get total withdrawn (approved/paid withdrawals)
$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(amount), 0) as total_withdrawn
    FROM profit_fund_withdrawals
    WHERE user_id = ? AND status IN ('approved', 'paid')
");
$stmt->execute([$user['id']]);
$total_withdrawn = floatval($stmt->fetch()['total_withdrawn'] ?? 0);

// Current balance = total contributed (approved) - total withdrawn
$current_balance = max(0, $calculated_balance - $total_withdrawn);

// Handle withdrawal request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'request_withdrawal') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $amount = floatval($_POST['amount'] ?? 0);
        $note = trim($_POST['note'] ?? '');
        
        // Validate amount
        if ($amount <= 0) {
            $error = 'Amount must be greater than 0.';
        } elseif ($amount > $current_balance) {
            $error = 'Withdrawal amount cannot exceed your current balance (৳' . number_format($current_balance, 2) . ').';
        } else {
            try {
                // Create withdrawal request
                $stmt = $pdo->prepare("
                    INSERT INTO profit_fund_withdrawals (user_id, amount, note, status, created_at)
                    VALUES (?, ?, ?, 'requested', UTC_TIMESTAMP())
                ");
                $stmt->execute([$user['id'], $amount, $note]);
                $withdrawal_id = $pdo->lastInsertId();
                
                log_audit($user['id'], 'create', 'profit_fund_withdrawals', $withdrawal_id, "Requested profit fund withdrawal of ৳{$amount}");
                
                $success = 'Withdrawal request submitted successfully. It will be reviewed by admin.';
                
                // Refresh page to show updated data
                header('Location: ' . BASE_URL . '/profit_fund/my_fund.php?success=' . urlencode($success));
                exit;
            } catch (Exception $e) {
                $error = 'Error submitting withdrawal request: ' . $e->getMessage();
                error_log("Withdrawal request error: " . $e->getMessage());
            }
        }
    }
}

// Get flash messages
if (isset($_GET['success'])) {
    $success = $_GET['success'];
}
if (isset($_GET['error'])) {
    $error = $_GET['error'];
}

// Get last updated time from profit_fund_balance table (if exists)
$stmt = $pdo->prepare("
    SELECT updated_at
    FROM profit_fund_balance
    WHERE user_id = ?
");
$stmt->execute([$user['id']]);
$balance_data = $stmt->fetch();
$last_updated = $balance_data ? $balance_data['updated_at'] : null;

// Get total contributed (only from approved salaries)
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_contributions,
        COALESCE(SUM(pf.amount), 0) as total_contributed
    FROM profit_fund pf
    INNER JOIN salary_history sh ON pf.user_id = sh.user_id 
        AND pf.month = sh.month 
        AND pf.year = sh.year
    WHERE pf.user_id = ? AND sh.status = 'approved'
");
$stmt->execute([$user['id']]);
$contributed_data = $stmt->fetch();
$total_contributions = (int)($contributed_data['total_contributions'] ?? 0);
$total_contributed = floatval($contributed_data['total_contributed'] ?? 0);

// Get total withdrawn (sum of approved/paid withdrawals)
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_withdrawals,
        COALESCE(SUM(amount), 0) as total_withdrawn
    FROM profit_fund_withdrawals
    WHERE user_id = ? AND status IN ('approved', 'paid')
");
$stmt->execute([$user['id']]);
$withdrawn_data = $stmt->fetch();
$total_withdrawals = (int)($withdrawn_data['total_withdrawals'] ?? 0);
$total_withdrawn = floatval($withdrawn_data['total_withdrawn'] ?? 0);

// Get pending withdrawals count
$stmt = $pdo->prepare("
    SELECT COUNT(*) as count
    FROM profit_fund_withdrawals
    WHERE user_id = ? AND status = 'requested'
");
$stmt->execute([$user['id']]);
$pending_count = (int)($stmt->fetch()['count'] ?? 0);

// Get recent contributions (last 12 months, only from approved salaries)
$stmt = $pdo->prepare("
    SELECT 
        pf.id,
        pf.month,
        pf.year,
        pf.amount,
        pf.created_at,
        sh.approved_at,
        sh.status as salary_status
    FROM profit_fund pf
    INNER JOIN salary_history sh ON pf.user_id = sh.user_id 
        AND pf.month = sh.month 
        AND pf.year = sh.year
    WHERE pf.user_id = ? 
        AND sh.status = 'approved'
    ORDER BY pf.year DESC, pf.month DESC
    LIMIT 12
");
$stmt->execute([$user['id']]);
$recent_contributions = $stmt->fetchAll();

// Get salary history for these months to show gross salary and progress (already joined, just get from sh)
foreach ($recent_contributions as &$contrib) {
    $stmt = $pdo->prepare("
        SELECT gross_salary, monthly_progress, status
        FROM salary_history
        WHERE user_id = ? AND month = ? AND year = ? AND status = 'approved'
        LIMIT 1
    ");
    $stmt->execute([$user['id'], $contrib['month'], $contrib['year']]);
    $salary_data = $stmt->fetch();
    $contrib['gross_salary'] = $salary_data ? floatval($salary_data['gross_salary']) : 0;
    $contrib['monthly_progress'] = $salary_data ? floatval($salary_data['monthly_progress']) : 0;
}
unset($contrib);

// Get withdrawal history
$stmt = $pdo->prepare("
    SELECT 
        id,
        amount,
        note as reason,
        status,
        created_at as requested_at,
        approved_by
    FROM profit_fund_withdrawals
    WHERE user_id = ?
    ORDER BY created_at DESC
    LIMIT 20
");
$stmt->execute([$user['id']]);
$withdrawals = $stmt->fetchAll();

// Add payment info if available (check if columns exist)
foreach ($withdrawals as &$withdrawal) {
    // These fields might not exist in the table, so we'll handle gracefully
    $withdrawal['paid_at'] = null;
    $withdrawal['payment_method'] = null;
    $withdrawal['transaction_ref'] = null;
}
unset($withdrawal);


$page_title = 'My Profit Fund';
include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="gradient-text mb-2 fs-3 fs-md-2">My Profit Fund</h1>
            <p class="text-muted mb-0 small">Track your profit fund balance and history</p>
        </div>
        <?php if ($current_balance > 0): ?>
            <button type="button" class="btn btn-primary btn-lg" data-bs-toggle="modal" data-bs-target="#withdrawalModal">
                <i class="bi bi-cash-coin me-2"></i>Request Withdrawal
            </button>
        <?php endif; ?>
    </div>
    
    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show animate-fade-in" role="alert">
            <i class="bi bi-check-circle me-2"></i><?= h($success) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show animate-fade-in" role="alert">
            <i class="bi bi-exclamation-triangle me-2"></i><?= h($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <?php
    // Check if current month salary is pending
    $stmt = $pdo->prepare("
        SELECT id, status, profit_fund
        FROM salary_history
        WHERE user_id = ? AND month = ? AND year = ?
        LIMIT 1
    ");
    $stmt->execute([$user['id'], $current_month, $current_year]);
    $current_month_salary = $stmt->fetch();
    
    if ($current_month_salary && $current_month_salary['status'] !== 'approved'):
        $pending_profit_fund = floatval($current_month_salary['profit_fund'] ?? 0);
    ?>
        <div class="alert alert-info alert-dismissible fade show animate-fade-in" role="alert">
            <i class="bi bi-info-circle me-2"></i>
            <strong>Note:</strong> Your <?= date('F Y', mktime(0, 0, 0, $current_month, 1, $current_year)) ?> profit fund (৳<?= number_format($pending_profit_fund, 2) ?>) will be added to your balance once your salary is approved by admin.
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <!-- Summary Cards -->
    <div class="row g-3 g-md-4 mb-4">
        <div class="col-6 col-md-3">
            <div class="stat-card animate-slide-up">
                <div class="stat-icon" style="background: linear-gradient(135deg, #007bff, #706fd3);">
                    <i class="bi bi-wallet2"></i>
                </div>
                <div class="stat-label small">Current Balance</div>
                <div class="stat-value fs-5 fs-md-4">৳<?= number_format($current_balance, 2) ?></div>
                <?php if ($last_updated): ?>
                    <small class="text-muted d-block mt-2">
                        <i class="bi bi-clock me-1"></i>Updated: <?= date('M d, Y', strtotime($last_updated)) ?>
                    </small>
                <?php endif; ?>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card animate-slide-up" style="animation-delay: 0.1s">
                <div class="stat-icon" style="background: linear-gradient(135deg, #28a745, #1dd1a1);">
                    <i class="bi bi-arrow-down-circle"></i>
                </div>
                <div class="stat-label small">Total Contributed</div>
                <div class="stat-value fs-5 fs-md-4">৳<?= number_format($total_contributed, 2) ?></div>
                <small class="text-muted d-block mt-2 small">
                    <i class="bi bi-calendar-check me-1"></i><?= $total_contributions ?> contribution(s)
                </small>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card animate-slide-up" style="animation-delay: 0.2s">
                <div class="stat-icon" style="background: linear-gradient(135deg, #dc3545, #ff6b6b);">
                    <i class="bi bi-arrow-up-circle"></i>
                </div>
                <div class="stat-label small">Total Withdrawn</div>
                <div class="stat-value fs-5 fs-md-4">৳<?= number_format($total_withdrawn, 2) ?></div>
                <small class="text-muted d-block mt-2 small">
                    <i class="bi bi-receipt me-1"></i><?= $total_withdrawals ?> withdrawal(s)
                </small>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card animate-slide-up" style="animation-delay: 0.3s">
                <div class="stat-icon" style="background: linear-gradient(135deg, #ffc107, #ff9800);">
                    <i class="bi bi-hourglass-split"></i>
                </div>
                <div class="stat-label small">Pending Requests</div>
                <div class="stat-value fs-5 fs-md-4"><?= $pending_count ?></div>
                <small class="text-muted d-block mt-2">
                    <i class="bi bi-info-circle me-1"></i>Awaiting approval
                </small>
            </div>
        </div>
    </div>
    
    <!-- Recent Contributions -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card shadow-lg border-0">
                <div class="card-header bg-white border-bottom">
                    <h5 class="mb-0">
                        <i class="bi bi-arrow-down-circle me-2 text-success"></i>Recent Contributions
                        <span class="badge bg-success ms-2">Approved Salaries Only</span>
                    </h5>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($recent_contributions)): ?>
                        <div class="text-center py-5 text-muted">
                            <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                            No contributions yet
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="ps-4">Month</th>
                                        <th class="d-none d-md-table-cell">Gross Salary</th>
                                        <th class="d-none d-lg-table-cell">Monthly Progress</th>
                                        <th>Contribution</th>
                                        <th class="d-none d-sm-table-cell">Approved Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_contributions as $contribution): ?>
                                        <tr>
                                            <td class="ps-4">
                                                <strong><?= date('F Y', mktime(0, 0, 0, $contribution['month'], 1, $contribution['year'])) ?></strong>
                                                <div class="d-md-none small text-muted mt-1">
                                                    Gross: ৳<?= number_format($contribution['gross_salary'] ?? 0, 2) ?><br>
                                                    Progress: <span class="badge bg-<?= ($contribution['monthly_progress'] ?? 0) >= 80 ? 'success' : (($contribution['monthly_progress'] ?? 0) >= 60 ? 'warning' : 'danger') ?>"><?= number_format($contribution['monthly_progress'] ?? 0, 1) ?>%</span>
                                                </div>
                                            </td>
                                            <td class="d-none d-md-table-cell">৳<?= number_format($contribution['gross_salary'] ?? 0, 2) ?></td>
                                            <td class="d-none d-lg-table-cell">
                                                <span class="badge bg-<?= ($contribution['monthly_progress'] ?? 0) >= 80 ? 'success' : (($contribution['monthly_progress'] ?? 0) >= 60 ? 'warning' : 'danger') ?>">
                                                    <?= number_format($contribution['monthly_progress'] ?? 0, 1) ?>%
                                                </span>
                                            </td>
                                            <td>
                                                <strong class="text-success">+৳<?= number_format($contribution['amount'], 2) ?></strong>
                                            </td>
                                            <td class="d-none d-sm-table-cell">
                                                <?= $contribution['approved_at'] ? date('M d, Y', strtotime($contribution['approved_at'])) : date('M d, Y', strtotime($contribution['created_at'])) ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Withdrawal History -->
    <div class="row">
        <div class="col-md-12">
            <div class="card shadow-lg border-0">
                <div class="card-header bg-white border-bottom">
                    <h5 class="mb-0">
                        <i class="bi bi-arrow-up-circle me-2 text-danger"></i>Withdrawal History
                    </h5>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($withdrawals)): ?>
                        <div class="text-center py-5 text-muted">
                            <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                            No withdrawals yet
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="ps-4">Date</th>
                                        <th>Amount</th>
                                        <th class="d-none d-md-table-cell">Note</th>
                                        <th>Status</th>
                                        <th class="d-none d-lg-table-cell">Payment Method</th>
                                        <th class="d-none d-lg-table-cell">Transaction Ref</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($withdrawals as $withdrawal): ?>
                                        <tr>
                                            <td class="ps-4">
                                                <strong><?= date('M d, Y', strtotime($withdrawal['requested_at'])) ?></strong>
                                                <div class="d-md-none small text-muted mt-1">
                                                    Note: <?= h($withdrawal['reason'] ?? 'N/A') ?>
                                                </div>
                                            </td>
                                            <td>
                                                <strong class="text-danger">-৳<?= number_format($withdrawal['amount'], 2) ?></strong>
                                            </td>
                                            <td class="d-none d-md-table-cell"><?= h($withdrawal['reason'] ?? 'N/A') ?></td>
                                            <td>
                                                <?php
                                                $status_colors = [
                                                    'requested' => 'warning',
                                                    'approved' => 'info',
                                                    'paid' => 'success',
                                                    'rejected' => 'danger'
                                                ];
                                                $status_color = $status_colors[$withdrawal['status']] ?? 'secondary';
                                                $status_icons = [
                                                    'requested' => 'bi-hourglass-split',
                                                    'approved' => 'bi-check-circle',
                                                    'paid' => 'bi-check-circle-fill',
                                                    'rejected' => 'bi-x-circle'
                                                ];
                                                $status_icon = $status_icons[$withdrawal['status']] ?? 'bi-circle';
                                                ?>
                                                <span class="badge bg-<?= $status_color ?> px-2 px-md-3 py-2">
                                                    <i class="bi <?= $status_icon ?> me-1"></i>
                                                    <span class="d-none d-sm-inline"><?= ucfirst($withdrawal['status']) ?></span>
                                                    <span class="d-sm-none"><?= ucfirst(substr($withdrawal['status'], 0, 1)) ?></span>
                                                </span>
                                            </td>
                                            <td class="d-none d-lg-table-cell text-muted">-</td>
                                            <td class="d-none d-lg-table-cell text-muted">-</td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Withdrawal Request Modal -->
<?php if ($current_balance > 0): ?>
<div class="modal fade" id="withdrawalModal" tabindex="-1" aria-labelledby="withdrawalModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-gradient-primary text-white" style="background: linear-gradient(135deg, #007bff, #706fd3);">
                <h5 class="modal-title" id="withdrawalModalLabel">
                    <i class="bi bi-cash-coin me-2"></i>Request Profit Fund Withdrawal
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="" id="withdrawalForm">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                    <input type="hidden" name="action" value="request_withdrawal">
                    
                    <div class="alert alert-info mb-3">
                        <i class="bi bi-info-circle me-2"></i>
                        <strong>Available Balance:</strong> ৳<?= number_format($current_balance, 2) ?>
                    </div>
                    
                    <div class="mb-3">
                        <label for="amount" class="form-label fw-semibold">
                            <i class="bi bi-currency-dollar me-1"></i>Withdrawal Amount <span class="text-danger">*</span>
                        </label>
                        <div class="input-group input-group-lg">
                            <span class="input-group-text">৳</span>
                            <input type="number" 
                                   class="form-control" 
                                   id="amount" 
                                   name="amount" 
                                   step="0.01" 
                                   min="0.01" 
                                   max="<?= $current_balance ?>" 
                                   required
                                   placeholder="Enter amount">
                        </div>
                        <small class="text-muted">
                            Maximum: ৳<?= number_format($current_balance, 2) ?>
                        </small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="note" class="form-label fw-semibold">
                            <i class="bi bi-file-text me-1"></i>Reason/Note (Optional)
                        </label>
                        <textarea class="form-control" 
                                  id="note" 
                                  name="note" 
                                  rows="3" 
                                  placeholder="Enter reason for withdrawal (optional)"></textarea>
                    </div>
                    
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <small>Your withdrawal request will be reviewed by admin. You will be notified once it's approved or rejected.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-1"></i>Cancel
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle me-1"></i>Submit Request
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.getElementById('withdrawalForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const amount = parseFloat(document.getElementById('amount').value);
    const maxAmount = <?= $current_balance ?>;
    
    // Ensure Notify is available
    if (typeof window.Notify === 'undefined') {
        console.warn('Notification system not loaded, using fallback');
        if (amount <= 0) {
            alert('Amount must be greater than 0.');
            return false;
        }
        if (amount > maxAmount) {
            alert('Amount cannot exceed your available balance (৳' + maxAmount.toFixed(2) + ').');
            return false;
        }
        if (confirm('Submit withdrawal request of ৳' + amount.toFixed(2) + '?')) {
            if (!this.action) {
                this.action = window.location.href;
            }
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Submitting...';
            this.submit();
        }
        return false;
    }
    
    if (amount <= 0) {
        await window.Notify.alert('Amount must be greater than 0.', 'Invalid Amount', 'error');
        return false;
    }
    
    if (amount > maxAmount) {
        await window.Notify.alert('Amount cannot exceed your available balance (৳' + maxAmount.toFixed(2) + ').', 'Insufficient Balance', 'error');
        return false;
    }
    
    const confirmed = await window.Notify.confirm(
        'Submit withdrawal request of ৳' + amount.toFixed(2) + '?',
        'Request Withdrawal',
        'Submit Request',
        'Cancel',
        'info'
    );
    
    if (confirmed) {
        // Set form action if not set
        if (!this.action) {
            this.action = window.location.href;
        }
        const submitBtn = this.querySelector('button[type="submit"]');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Submitting...';
        this.submit();
    }
});
</script>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>

