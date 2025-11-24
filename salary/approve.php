<?php
/**
 * salary/approve.php
 * Approve salary (accountant)
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

if ($salary['status'] !== 'pending') {
    header('Location: ' . BASE_URL . '/salary/view.php?id=' . $id);
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token.';
    } else {
        try {
            $pdo->beginTransaction();
            
            // Update salary status
            $stmt = $pdo->prepare("
                UPDATE salary_history 
                SET status = 'approved', approved_by = ?, approved_at = UTC_TIMESTAMP()
                WHERE id = ?
            ");
            $stmt->execute([current_user()['id'], $id]);
            
            // Add profit fund to balance when salary is approved
            $profit_fund_amount = floatval($salary['profit_fund'] ?? 0);
            if ($profit_fund_amount > 0) {
                // Get current balance or create if doesn't exist
                $stmt = $pdo->prepare("
                    SELECT balance FROM profit_fund_balance WHERE user_id = ?
                ");
                $stmt->execute([$salary['user_id']]);
                $current_balance = $stmt->fetch();
                
                if ($current_balance) {
                    // Update balance (add profit fund amount)
                    $new_balance = floatval($current_balance['balance']) + $profit_fund_amount;
                    $stmt = $pdo->prepare("
                        UPDATE profit_fund_balance 
                        SET balance = ?, updated_at = UTC_TIMESTAMP()
                        WHERE user_id = ?
                    ");
                    $stmt->execute([$new_balance, $salary['user_id']]);
                } else {
                    // Create new balance entry
                    $stmt = $pdo->prepare("
                        INSERT INTO profit_fund_balance (user_id, balance, updated_at)
                        VALUES (?, ?, UTC_TIMESTAMP())
                    ");
                    $stmt->execute([$salary['user_id'], $profit_fund_amount]);
                }
            }
            
            $pdo->commit();
            
            log_audit(current_user()['id'], 'approve', 'salary_history', $id, "Approved salary for user {$salary['user_id']} - Profit fund added: ৳{$profit_fund_amount}");
            
            header('Location: ' . BASE_URL . '/salary/view.php?id=' . $id . '&success=' . urlencode('Salary approved successfully. Profit fund added to balance.'));
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Error approving salary: ' . $e->getMessage();
            error_log("Salary approval error: " . $e->getMessage());
        }
    }
}

$page_title = 'Approve Salary';
include __DIR__ . '/../includes/header.php';
?>

<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h4>Approve Salary</h4>
                </div>
                <div class="card-body">
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?= h($error) ?></div>
                    <?php endif; ?>
                    
                    <p>Are you sure you want to approve this salary?</p>
                    <dl class="row">
                        <dt class="col-sm-4">Net Payable:</dt>
                        <dd class="col-sm-8"><strong><?= number_format($salary['net_payable'], 2) ?></strong></dd>
                    </dl>
                    
                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-success">Approve Salary</button>
                            <a href="<?= BASE_URL ?>/salary/view.php?id=<?= $id ?>" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

