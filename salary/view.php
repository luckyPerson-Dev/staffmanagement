<?php
/**
 * salary/view.php
 * View salary details
 */

require_once __DIR__ . '/../auth_helper.php';
require_once __DIR__ . '/../helpers.php';

require_login();

$id = intval($_GET['id'] ?? 0);
if (!$id) {
    header('Location: ' . BASE_URL . '/salary/index.php');
    exit;
}

$pdo = getPDO();
$user = current_user();

// Staff can only view their own salaries
if ($user['role'] === 'staff') {
    $stmt = $pdo->prepare("
        SELECT sh.*, u.name as user_name
        FROM salary_history sh
        JOIN users u ON sh.user_id = u.id
        WHERE sh.id = ? AND sh.user_id = ?
    ");
    $stmt->execute([$id, $user['id']]);
} else {
    $stmt = $pdo->prepare("
        SELECT sh.*, u.name as user_name
        FROM salary_history sh
        JOIN users u ON sh.user_id = u.id
        WHERE sh.id = ?
    ");
    $stmt->execute([$id]);
}

$salary = $stmt->fetch();

if (!$salary) {
    header('Location: ' . BASE_URL . '/salary/index.php');
    exit;
}

$page_title = 'Salary Details';
include __DIR__ . '/../includes/header.php';
?>

<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h4>Salary Details</h4>
                </div>
                <div class="card-body">
                    <dl class="row">
                        <dt class="col-sm-4">Staff Member:</dt>
                        <dd class="col-sm-8"><?= h($salary['user_name']) ?></dd>
                        
                        <dt class="col-sm-4">Period:</dt>
                        <dd class="col-sm-8"><?= date('F Y', mktime(0,0,0,$salary['month'],1,$salary['year'])) ?></dd>
                        
                        <dt class="col-sm-4">Gross Salary:</dt>
                        <dd class="col-sm-8"><?= number_format($salary['gross_salary'], 2) ?></dd>
                        
                        <dt class="col-sm-4">Monthly Progress:</dt>
                        <dd class="col-sm-8"><?= number_format($salary['monthly_progress'], 2) ?>%</dd>
                        
                        <dt class="col-sm-4">Profit Fund:</dt>
                        <dd class="col-sm-8"><?= number_format($salary['profit_fund'], 2) ?></dd>
                        
                        <dt class="col-sm-4">Payable (Before Advances):</dt>
                        <dd class="col-sm-8"><?= number_format($salary['payable_before_advance'], 2) ?></dd>
                        
                        <dt class="col-sm-4">Advances Deducted:</dt>
                        <dd class="col-sm-8">
                            ৳<?= number_format($salary['advances_deducted'], 2) ?>
                            <?php
                            // Check if this was from auto-deduction
                            $stmt = $pdo->prepare("
                                SELECT * FROM advance_auto_deductions
                                WHERE user_id = ? AND status IN ('active', 'completed')
                                ORDER BY created_at DESC
                                LIMIT 1
                            ");
                            $stmt->execute([$salary['user_id']]);
                            $auto_ded = $stmt->fetch();
                            if ($auto_ded && $salary['advances_deducted'] > 0):
                            ?>
                                <br><small class="text-muted">
                                    <i class="bi bi-info-circle me-1"></i>
                                    Auto-deduction: ৳<?= number_format($auto_ded['monthly_deduction'], 2) ?>/month
                                    <?php if ($auto_ded['status'] === 'active'): ?>
                                        (Remaining: ৳<?= number_format($auto_ded['remaining_due'], 2) ?>)
                                    <?php endif; ?>
                                </small>
                            <?php endif; ?>
                        </dd>
                        
                        <dt class="col-sm-4">Net Payable:</dt>
                        <dd class="col-sm-8"><strong><?= number_format($salary['net_payable'], 2) ?></strong></dd>
                        
                        <dt class="col-sm-4">Status:</dt>
                        <dd class="col-sm-8">
                            <span class="badge bg-<?= $salary['status'] === 'paid' ? 'success' : ($salary['status'] === 'approved' ? 'warning' : 'secondary') ?>">
                                <?= ucfirst($salary['status']) ?>
                            </span>
                        </dd>
                        
                        <?php if ($salary['payment_method']): ?>
                            <dt class="col-sm-4">Payment Method:</dt>
                            <dd class="col-sm-8"><?= h($salary['payment_method']) ?></dd>
                        <?php endif; ?>
                        
                        <?php if ($salary['transaction_ref']): ?>
                            <dt class="col-sm-4">Transaction Reference:</dt>
                            <dd class="col-sm-8"><?= h($salary['transaction_ref']) ?></dd>
                        <?php endif; ?>
                    </dl>
                    
                    <div class="mt-4">
                        <a href="<?= BASE_URL ?>/salary/index.php" class="btn btn-secondary">Back</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

