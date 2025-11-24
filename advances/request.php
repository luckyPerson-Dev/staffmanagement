<?php
/**
 * advances/request.php
 * Staff request advance
 */

require_once __DIR__ . '/../auth_helper.php';
require_once __DIR__ . '/../helpers.php';

require_role(['staff']);

$user = current_user();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token.';
    } else {
        $amount = floatval($_POST['amount'] ?? 0);
        $reason = trim($_POST['reason'] ?? '');
        
        if ($amount <= 0) {
            $error = 'Amount must be greater than 0.';
        } else {
            $pdo = getPDO();
            $stmt = $pdo->prepare("
                INSERT INTO advances (user_id, amount, reason, status, created_at)
                VALUES (?, ?, ?, 'pending', UTC_TIMESTAMP())
            ");
            $stmt->execute([$user['id'], $amount, $reason]);
            $advance_id = $pdo->lastInsertId();
            
            log_audit($user['id'], 'create', 'advance', $advance_id, "Requested advance of $amount");
            
            $success = 'Advance request submitted successfully.';
        }
    }
}

$page_title = 'Request Advance';
include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="mb-4">
        <h1 class="gradient-text mb-2 fs-3 fs-md-2">Request Advance</h1>
        <p class="text-muted mb-0 small">Submit a request for advance payment</p>
    </div>
    
    <div class="row justify-content-center">
        <div class="col-12 col-md-8 col-lg-6">
            <div class="card shadow-lg border-0">
                <div class="card-header bg-white border-bottom">
                    <h5 class="mb-0">
                        <i class="bi bi-wallet2 me-2 text-primary"></i>Advance Request Form
                    </h5>
                </div>
                <div class="card-body p-3 p-md-4">
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?= h($error) ?></div>
                    <?php endif; ?>
                    <?php if ($success): ?>
                        <div class="alert alert-success"><?= h($success) ?></div>
                    <?php endif; ?>
                    
                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                        
                        <div class="mb-3">
                            <label for="amount" class="form-label">Amount *</label>
                            <input type="number" step="0.01" class="form-control" id="amount" name="amount" required min="0.01">
                        </div>
                        
                        <div class="mb-3">
                            <label for="reason" class="form-label">Reason</label>
                            <textarea class="form-control" id="reason" name="reason" rows="3" required></textarea>
                        </div>
                        
                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <a href="<?= BASE_URL ?>/advances/my_advances.php" class="btn btn-secondary order-2 order-md-1">
                                <i class="bi bi-x-circle me-2"></i>Cancel
                            </a>
                            <button type="submit" class="btn btn-primary order-1 order-md-2">
                                <i class="bi bi-check-circle me-2"></i>Submit Request
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

