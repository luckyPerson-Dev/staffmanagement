<?php
/**
 * advances/view.php
 * View and approve/reject advance request
 */

require_once __DIR__ . '/../auth_helper.php';
require_once __DIR__ . '/../helpers.php';

require_role(['admin', 'superadmin']);

$id = intval($_GET['id'] ?? 0);
if (!$id) {
    header('Location: ' . BASE_URL . '/advances/index.php');
    exit;
}

$pdo = getPDO();
$stmt = $pdo->prepare("
    SELECT a.*, u.name as user_name
    FROM advances a
    JOIN users u ON a.user_id = u.id
    WHERE a.id = ?
");
$stmt->execute([$id]);
$advance = $stmt->fetch();

if (!$advance) {
    header('Location: ' . BASE_URL . '/advances/index.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $advance['status'] === 'pending') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token.';
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'approve' || $action === 'reject') {
            $status = $action === 'approve' ? 'approved' : 'rejected';
            $stmt = $pdo->prepare("
                UPDATE advances 
                SET status = ?, approved_by = ?, approved_at = UTC_TIMESTAMP()
                WHERE id = ?
            ");
            $stmt->execute([$status, current_user()['id'], $id]);
            
            log_audit(current_user()['id'], $action, 'advance', $id, "{$action}d advance request");
            
            $success = "Advance request {$action}d successfully.";
            header('Location: ' . BASE_URL . '/advances/index.php?success=' . urlencode($success));
            exit;
        }
    }
}

$page_title = 'View Advance Request';
include __DIR__ . '/../includes/header.php';
?>

<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h4>Advance Request Details</h4>
                </div>
                <div class="card-body">
                    <dl class="row">
                        <dt class="col-sm-4">Staff Member:</dt>
                        <dd class="col-sm-8"><?= h($advance['user_name']) ?></dd>
                        
                        <dt class="col-sm-4">Amount:</dt>
                        <dd class="col-sm-8"><?= number_format($advance['amount'], 2) ?></dd>
                        
                        <dt class="col-sm-4">Reason:</dt>
                        <dd class="col-sm-8"><?= h($advance['reason']) ?></dd>
                        
                        <dt class="col-sm-4">Status:</dt>
                        <dd class="col-sm-8">
                            <span class="badge bg-<?= $advance['status'] === 'approved' ? 'success' : ($advance['status'] === 'rejected' ? 'danger' : 'warning') ?>">
                                <?= ucfirst($advance['status']) ?>
                            </span>
                        </dd>
                        
                        <dt class="col-sm-4">Created:</dt>
                        <dd class="col-sm-8"><?= format_datetime($advance['created_at']) ?></dd>
                    </dl>
                    
                    <?php if ($advance['status'] === 'pending'): ?>
                        <form method="POST" action="" class="mt-4">
                            <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                            <div class="d-grid gap-2">
                                <button type="submit" name="action" value="approve" class="btn btn-success">Approve</button>
                                <button type="submit" name="action" value="reject" class="btn btn-danger">Reject</button>
                                <a href="<?= BASE_URL ?>/advances/index.php" class="btn btn-secondary">Back</a>
                            </div>
                        </form>
                    <?php else: ?>
                        <a href="<?= BASE_URL ?>/advances/index.php" class="btn btn-secondary">Back</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

