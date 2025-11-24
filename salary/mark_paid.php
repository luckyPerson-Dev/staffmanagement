<?php
/**
 * salary/mark_paid.php
 * Mark salary as paid (accountant)
 */

require_once __DIR__ . '/../auth_helper.php';
require_once __DIR__ . '/../helpers.php';

require_role(['accountant']);

$id = intval($_GET['id'] ?? 0);
if (!$id) {
    header('Location: ' . BASE_URL . '/salary/index.php');
    exit;
}

$pdo = getPDO();
$stmt = $pdo->prepare("SELECT * FROM salary_history WHERE id = ?");
$stmt->execute([$id]);
$salary = $stmt->fetch();

if (!$salary) {
    header('Location: ' . BASE_URL . '/salary/index.php');
    exit;
}

if ($salary['status'] !== 'approved') {
    header('Location: ' . BASE_URL . '/salary/view.php?id=' . $id);
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token.';
    } else {
        $payment_method = trim($_POST['payment_method'] ?? '');
        $transaction_ref = trim($_POST['transaction_ref'] ?? '');
        
        if (empty($payment_method)) {
            $error = 'Payment method is required.';
        } else {
            $stmt = $pdo->prepare("
                UPDATE salary_history 
                SET status = 'paid', payment_method = ?, transaction_ref = ?, paid_at = UTC_TIMESTAMP()
                WHERE id = ?
            ");
            $stmt->execute([$payment_method, $transaction_ref, $id]);
            
            log_audit(current_user()['id'], 'update', 'salary_history', $id, "Marked salary as paid: $payment_method");
            
            header('Location: ' . BASE_URL . '/salary/view.php?id=' . $id . '&success=' . urlencode('Salary marked as paid'));
            exit;
        }
    }
}

$page_title = 'Mark Salary as Paid';
include __DIR__ . '/../includes/header.php';
?>

<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h4>Mark Salary as Paid</h4>
                </div>
                <div class="card-body">
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?= h($error) ?></div>
                    <?php endif; ?>
                    
                    <dl class="row mb-4">
                        <dt class="col-sm-4">Net Payable:</dt>
                        <dd class="col-sm-8"><strong><?= number_format($salary['net_payable'], 2) ?></strong></dd>
                    </dl>
                    
                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                        
                        <div class="mb-3">
                            <label for="payment_method" class="form-label">Payment Method *</label>
                            <select class="form-select" id="payment_method" name="payment_method" required>
                                <option value="">Select Method</option>
                                <option value="Bank Transfer">Bank Transfer</option>
                                <option value="Cash">Cash</option>
                                <option value="Cheque">Cheque</option>
                                <option value="Mobile Payment">Mobile Payment</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="transaction_ref" class="form-label">Transaction Reference</label>
                            <input type="text" class="form-control" id="transaction_ref" name="transaction_ref" 
                                   value="<?= h($_POST['transaction_ref'] ?? '') ?>">
                            <small class="text-muted">Optional: Transaction ID, cheque number, etc.</small>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-success">Mark as Paid</button>
                            <a href="<?= BASE_URL ?>/salary/view.php?id=<?= $id ?>" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

