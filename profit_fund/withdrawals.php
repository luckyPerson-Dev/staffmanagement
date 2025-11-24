<?php
/**
 * profit_fund/withdrawals.php
 * Admin page to manage profit fund withdrawal requests
 */

require_once __DIR__ . '/../auth_helper.php';
require_once __DIR__ . '/../helpers.php';

require_role(['admin', 'superadmin']);

$pdo = getPDO();
$message = '';
$error = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token.';
    } else {
        $action = $_POST['action'] ?? '';
        $withdrawal_id = intval($_POST['withdrawal_id'] ?? 0);
        
        if ($withdrawal_id > 0) {
            // Get withdrawal details
            $stmt = $pdo->prepare("
                SELECT pfw.*, u.name as user_name, pfb.balance as current_balance
                FROM profit_fund_withdrawals pfw
                JOIN users u ON pfw.user_id = u.id
                LEFT JOIN profit_fund_balance pfb ON pfw.user_id = pfb.user_id
                WHERE pfw.id = ?
            ");
            $stmt->execute([$withdrawal_id]);
            $withdrawal = $stmt->fetch();
            
            if (!$withdrawal) {
                $error = 'Withdrawal request not found.';
            } elseif ($withdrawal['status'] !== 'requested') {
                $error = 'This withdrawal request has already been processed.';
            } else {
                try {
                    $pdo->beginTransaction();
                    
                    if ($action === 'approve') {
                        // Check if user has sufficient balance
                        $current_balance = floatval($withdrawal['current_balance'] ?? 0);
                        $withdrawal_amount = floatval($withdrawal['amount']);
                        
                        if ($withdrawal_amount > $current_balance) {
                            throw new Exception('User does not have sufficient balance. Current balance: ৳' . number_format($current_balance, 2));
                        }
                        
                        // Update withdrawal status
                        $stmt = $pdo->prepare("
                            UPDATE profit_fund_withdrawals 
                            SET status = 'approved', approved_by = ?, updated_at = UTC_TIMESTAMP()
                            WHERE id = ?
                        ");
                        $stmt->execute([current_user()['id'], $withdrawal_id]);
                        
                        // Deduct from balance
                        $new_balance = max(0, $current_balance - $withdrawal_amount);
                        $stmt = $pdo->prepare("
                            UPDATE profit_fund_balance 
                            SET balance = ?, updated_at = UTC_TIMESTAMP()
                            WHERE user_id = ?
                        ");
                        $stmt->execute([$new_balance, $withdrawal['user_id']]);
                        
                        // If balance doesn't exist, create it (shouldn't happen, but safety check)
                        if ($stmt->rowCount() === 0) {
                            $stmt = $pdo->prepare("
                                INSERT INTO profit_fund_balance (user_id, balance, updated_at)
                                VALUES (?, ?, UTC_TIMESTAMP())
                            ");
                            $stmt->execute([$withdrawal['user_id'], $new_balance]);
                        }
                        
                        log_audit(current_user()['id'], 'approve', 'profit_fund_withdrawals', $withdrawal_id, "Approved withdrawal of ৳{$withdrawal_amount} for user {$withdrawal['user_id']}");
                        $message = 'Withdrawal request approved successfully.';
                        
                    } elseif ($action === 'reject') {
                        // Update withdrawal status
                        $stmt = $pdo->prepare("
                            UPDATE profit_fund_withdrawals 
                            SET status = 'rejected', approved_by = ?, updated_at = UTC_TIMESTAMP()
                            WHERE id = ?
                        ");
                        $stmt->execute([current_user()['id'], $withdrawal_id]);
                        
                        log_audit(current_user()['id'], 'reject', 'profit_fund_withdrawals', $withdrawal_id, "Rejected withdrawal of ৳{$withdrawal['amount']} for user {$withdrawal['user_id']}");
                        $message = 'Withdrawal request rejected.';
                        
                    } elseif ($action === 'mark_paid') {
                        // Mark as paid (only if already approved)
                        if ($withdrawal['status'] === 'approved') {
                            $stmt = $pdo->prepare("
                                UPDATE profit_fund_withdrawals 
                                SET status = 'paid', updated_at = UTC_TIMESTAMP()
                                WHERE id = ?
                            ");
                            $stmt->execute([$withdrawal_id]);
                            
                            log_audit(current_user()['id'], 'update', 'profit_fund_withdrawals', $withdrawal_id, "Marked withdrawal as paid");
                            $message = 'Withdrawal marked as paid.';
                        } else {
                            throw new Exception('Only approved withdrawals can be marked as paid.');
                        }
                    } else {
                        throw new Exception('Invalid action.');
                    }
                    
                    $pdo->commit();
                    
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $error = $e->getMessage();
                    error_log("Withdrawal action error: " . $e->getMessage());
                }
            }
        } else {
            $error = 'Invalid withdrawal ID.';
        }
    }
}

// Get all withdrawal requests
$filter_status = $_GET['status'] ?? 'all';
$where_clause = "1=1";
$params = [];

if ($filter_status !== 'all') {
    $where_clause .= " AND pfw.status = ?";
    $params[] = $filter_status;
}

$stmt = $pdo->prepare("
    SELECT 
        pfw.*,
        u.name as user_name,
        u.email as user_email,
        pfb.balance as current_balance,
        approver.name as approved_by_name
    FROM profit_fund_withdrawals pfw
    JOIN users u ON pfw.user_id = u.id
    LEFT JOIN profit_fund_balance pfb ON pfw.user_id = pfb.user_id
    LEFT JOIN users approver ON pfw.approved_by = approver.id
    WHERE {$where_clause}
    ORDER BY pfw.created_at DESC
");
$stmt->execute($params);
$withdrawals = $stmt->fetchAll();

// Calculate statistics
$stmt = $pdo->query("
    SELECT 
        COUNT(*) as total_requests,
        SUM(CASE WHEN status = 'requested' THEN 1 ELSE 0 END) as pending_count,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_count,
        SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) as paid_count,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_count,
        COALESCE(SUM(CASE WHEN status IN ('approved', 'paid') THEN amount ELSE 0 END), 0) as total_approved_amount
    FROM profit_fund_withdrawals
");
$stats = $stmt->fetch();

$page_title = 'Profit Fund Withdrawals';
include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="gradient-text mb-2">Profit Fund Withdrawals</h1>
            <p class="text-muted mb-0">Manage profit fund withdrawal requests</p>
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
    
    <!-- Statistics Cards -->
    <div class="row g-3 g-md-4 mb-4">
        <div class="col-6 col-md-3">
            <div class="stat-card animate-slide-up">
                <div class="stat-icon" style="background: linear-gradient(135deg, #007bff, #706fd3);">
                    <i class="bi bi-list-check"></i>
                </div>
                <div class="stat-label small">Total Requests</div>
                <div class="stat-value fs-5 fs-md-4"><?= $stats['total_requests'] ?? 0 ?></div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card animate-slide-up" style="animation-delay: 0.1s">
                <div class="stat-icon" style="background: linear-gradient(135deg, #ffc107, #ff9800);">
                    <i class="bi bi-hourglass-split"></i>
                </div>
                <div class="stat-label small">Pending</div>
                <div class="stat-value fs-5 fs-md-4"><?= $stats['pending_count'] ?? 0 ?></div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card animate-slide-up" style="animation-delay: 0.2s">
                <div class="stat-icon" style="background: linear-gradient(135deg, #28a745, #20c997);">
                    <i class="bi bi-check-circle"></i>
                </div>
                <div class="stat-label small">Approved/Paid</div>
                <div class="stat-value fs-5 fs-md-4"><?= ($stats['approved_count'] ?? 0) + ($stats['paid_count'] ?? 0) ?></div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card animate-slide-up" style="animation-delay: 0.3s">
                <div class="stat-icon" style="background: linear-gradient(135deg, #17a2b8, #138496);">
                    <i class="bi bi-cash-stack"></i>
                </div>
                <div class="stat-label small">Total Approved</div>
                <div class="stat-value fs-5 fs-md-4">৳<?= number_format($stats['total_approved_amount'] ?? 0, 2) ?></div>
            </div>
        </div>
    </div>
    
    <!-- Filter -->
    <div class="card shadow-lg border-0 mb-4">
        <div class="card-body">
            <form method="GET" action="" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label for="status" class="form-label fw-semibold">Filter by Status</label>
                    <select class="form-select form-select-lg" id="status" name="status">
                        <option value="all" <?= $filter_status === 'all' ? 'selected' : '' ?>>All Status</option>
                        <option value="requested" <?= $filter_status === 'requested' ? 'selected' : '' ?>>Pending</option>
                        <option value="approved" <?= $filter_status === 'approved' ? 'selected' : '' ?>>Approved</option>
                        <option value="paid" <?= $filter_status === 'paid' ? 'selected' : '' ?>>Paid</option>
                        <option value="rejected" <?= $filter_status === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary btn-lg w-100">
                        <i class="bi bi-funnel me-1"></i>Filter
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Withdrawals Table -->
    <div class="card shadow-lg border-0">
        <div class="card-body p-0">
            <?php if (empty($withdrawals)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                    No withdrawal requests found
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4">Staff</th>
                                <th>Amount</th>
                                <th>Current Balance</th>
                                <th class="d-none d-md-table-cell">Note</th>
                                <th>Status</th>
                                <th>Requested Date</th>
                                <th class="d-none d-lg-table-cell">Approved By</th>
                                <th class="text-end pe-4">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($withdrawals as $withdrawal): ?>
                                <tr>
                                    <td class="ps-4">
                                        <div class="d-flex align-items-center">
                                            <div class="rounded-circle bg-gradient-primary d-flex align-items-center justify-content-center me-3" 
                                                 style="width: 40px; height: 40px; background: linear-gradient(135deg, #007bff, #706fd3);">
                                                <span class="text-white fw-bold"><?= strtoupper(substr($withdrawal['user_name'], 0, 1)) ?></span>
                                            </div>
                                            <div>
                                                <strong class="d-block"><?= h($withdrawal['user_name']) ?></strong>
                                                <small class="text-muted"><?= h($withdrawal['user_email']) ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <strong class="text-danger">৳<?= number_format($withdrawal['amount'], 2) ?></strong>
                                    </td>
                                    <td>
                                        <span class="text-<?= floatval($withdrawal['current_balance'] ?? 0) >= floatval($withdrawal['amount']) ? 'success' : 'danger' ?>">
                                            ৳<?= number_format($withdrawal['current_balance'] ?? 0, 2) ?>
                                        </span>
                                    </td>
                                    <td class="d-none d-md-table-cell">
                                        <?= h($withdrawal['note'] ?: 'N/A') ?>
                                    </td>
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
                                        <span class="badge bg-<?= $status_color ?> px-3 py-2">
                                            <i class="bi <?= $status_icon ?> me-1"></i><?= ucfirst($withdrawal['status']) ?>
                                        </span>
                                    </td>
                                    <td><?= date('M d, Y', strtotime($withdrawal['created_at'])) ?></td>
                                    <td class="d-none d-lg-table-cell">
                                        <?= $withdrawal['approved_by_name'] ? h($withdrawal['approved_by_name']) : '-' ?>
                                    </td>
                                    <td class="text-end pe-4">
                                        <?php if ($withdrawal['status'] === 'requested'): ?>
                                            <div class="btn-group" role="group">
                                                <form method="POST" action="" class="d-inline withdrawal-form" data-action="approve" data-amount="<?= number_format($withdrawal['amount'], 2) ?>" data-user="<?= h($withdrawal['user_name']) ?>">
                                                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                                    <input type="hidden" name="action" value="approve">
                                                    <input type="hidden" name="withdrawal_id" value="<?= $withdrawal['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-success" title="Approve">
                                                        <i class="bi bi-check-circle me-1"></i>Approve
                                                    </button>
                                                </form>
                                                <form method="POST" action="" class="d-inline withdrawal-form" data-action="reject" data-user="<?= h($withdrawal['user_name']) ?>">
                                                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                                    <input type="hidden" name="action" value="reject">
                                                    <input type="hidden" name="withdrawal_id" value="<?= $withdrawal['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-danger" title="Reject">
                                                        <i class="bi bi-x-circle me-1"></i>Reject
                                                    </button>
                                                </form>
                                            </div>
                                        <?php elseif ($withdrawal['status'] === 'approved'): ?>
                                            <form method="POST" action="" class="d-inline withdrawal-form" data-action="mark_paid">
                                                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                                <input type="hidden" name="action" value="mark_paid">
                                                <input type="hidden" name="withdrawal_id" value="<?= $withdrawal['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-primary" title="Mark as Paid">
                                                    <i class="bi bi-cash-coin me-1"></i>Mark Paid
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle withdrawal form submissions with custom confirm
    document.querySelectorAll('.withdrawal-form').forEach(form => {
        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const action = this.dataset.action;
            const amount = this.dataset.amount;
            const user = this.dataset.user;
            let message = '';
            let title = '';
            let confirmText = '';
            let type = 'warning';
            
            if (action === 'approve') {
                message = `Approve withdrawal of ৳${amount} for ${user}?`;
                title = 'Approve Withdrawal';
                confirmText = 'Approve';
                type = 'warning';
            } else if (action === 'reject') {
                message = `Reject withdrawal request from ${user}?`;
                title = 'Reject Withdrawal';
                confirmText = 'Reject';
                type = 'danger';
            } else if (action === 'mark_paid') {
                message = 'Mark withdrawal as paid?';
                title = 'Mark as Paid';
                confirmText = 'Mark Paid';
                type = 'info';
            }
            
            // Ensure Notify is available
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
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>

