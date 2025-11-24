<?php
/**
 * users/manage_status.php
 * Superadmin: Ban, unban, suspend staff accounts
 */

require_once __DIR__ . '/../auth_helper.php';
require_once __DIR__ . '/../helpers.php';

require_role(['superadmin', 'admin']);

$id = intval($_GET['id'] ?? 0);
if (!$id) {
    header('Location: ' . BASE_URL . '/users/index.php');
    exit;
}

$pdo = getPDO();
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$id]);
$targetUser = $stmt->fetch();

if (!$targetUser) {
    header('Location: ' . BASE_URL . '/users/index.php?error=' . urlencode('User not found'));
    exit;
}

// Prevent self-modification
if ($id == current_user()['id']) {
    header('Location: ' . BASE_URL . '/users/index.php?error=' . urlencode('Cannot modify your own status'));
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token.';
    } else {
        $action = $_POST['action'] ?? '';
        $reason = trim($_POST['reason'] ?? '');
        
        $statusMap = [
            'ban' => 'banned',
            'unban' => 'active',
            'suspend' => 'suspended',
            'activate' => 'active'
        ];
        
        if (!isset($statusMap[$action])) {
            $error = 'Invalid action.';
        } else {
            $newStatus = $statusMap[$action];
            
            // Get before snapshot
            $before = $targetUser;
            
            // Update status
            $stmt = $pdo->prepare("
                UPDATE users 
                SET status = ?, 
                    status_reason = ?,
                    status_changed_at = UTC_TIMESTAMP(),
                    status_changed_by = ?,
                    updated_at = UTC_TIMESTAMP()
                WHERE id = ?
            ");
            $stmt->execute([$newStatus, $reason ?: null, current_user()['id'], $id]);
            
            // Get after snapshot
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$id]);
            $after = $stmt->fetch();
            
            log_audit(
                current_user()['id'], 
                $action, 
                'user', 
                $id, 
                "Changed user status to {$newStatus}",
                $before,
                $after
            );
            
            $success = "User status changed to " . ucfirst($newStatus) . " successfully.";
            header('Location: ' . BASE_URL . '/users/index.php?success=' . urlencode($success));
            exit;
        }
    }
}

$page_title = 'Manage User Status';
include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="gradient-text mb-2">Manage User Status</h1>
                    <p class="text-muted mb-0">Ban, unban, suspend, or activate user accounts</p>
                </div>
            </div>
            
            <div class="card shadow-lg border-0">
                <div class="card-header bg-gradient-primary text-white" style="background: linear-gradient(135deg, #007bff, #706fd3);">
                    <h4 class="mb-0">
                        <i class="bi bi-shield-exclamation me-2"></i>User Status Management
                    </h4>
                </div>
                <div class="card-body p-4">
                    <div class="alert alert-info mb-4">
                        <div class="d-flex align-items-center mb-3">
                            <div class="rounded-circle bg-gradient-primary d-flex align-items-center justify-content-center me-3" 
                                 style="width: 50px; height: 50px; background: linear-gradient(135deg, #007bff, #706fd3);">
                                <span class="text-white fw-bold fs-5"><?= strtoupper(substr($targetUser['name'], 0, 1)) ?></span>
                            </div>
                            <div>
                                <strong class="fs-5"><?= h($targetUser['name']) ?></strong><br>
                                <small class="text-muted"><?= h($targetUser['email']) ?></small>
                            </div>
                        </div>
                        <div class="mt-2">
                            <strong>Current Status:</strong> 
                            <span class="badge bg-<?= $targetUser['status'] === 'active' ? 'success' : ($targetUser['status'] === 'banned' ? 'danger' : 'warning') ?> fs-6">
                                <?= ucfirst($targetUser['status'] ?? 'active') ?>
                            </span>
                        </div>
                    </div>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger animate-fade-in">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>
                            <?= h($error) ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="" id="statusForm">
                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                        
                        <div class="mb-4">
                            <label class="form-label fw-semibold mb-3">
                                <i class="bi bi-gear me-1"></i>Select Action
                            </label>
                            <div class="row g-3">
                                <?php if ($targetUser['status'] !== 'banned'): ?>
                                    <div class="col-md-6">
                                        <button type="submit" 
                                                name="action" 
                                                value="ban" 
                                                class="btn btn-danger w-100 btn-lg ban-user-btn"
                                                data-confirm="Are you sure you want to BAN this user? This will prevent them from logging in."
                                                data-confirm-title="Ban User"
                                                data-confirm-type="danger">
                                            <i class="bi bi-ban me-2"></i>Ban User
                                        </button>
                                    </div>
                                <?php else: ?>
                                    <div class="col-md-6">
                                        <button type="submit" 
                                                name="action" 
                                                value="unban" 
                                                class="btn btn-success w-100 btn-lg">
                                            <i class="bi bi-check-circle me-2"></i>Unban User
                                        </button>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($targetUser['status'] !== 'suspended'): ?>
                                    <div class="col-md-6">
                                        <button type="submit" 
                                                name="action" 
                                                value="suspend" 
                                                class="btn btn-warning w-100 btn-lg">
                                            <i class="bi bi-pause-circle me-2"></i>Suspend User
                                        </button>
                                    </div>
                                <?php else: ?>
                                    <div class="col-md-6">
                                        <button type="submit" 
                                                name="action" 
                                                value="activate" 
                                                class="btn btn-success w-100 btn-lg">
                                            <i class="bi bi-play-circle me-2"></i>Activate User
                                        </button>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label for="reason" class="form-label fw-semibold">
                                <i class="bi bi-chat-left-text me-1"></i>Reason (Optional)
                            </label>
                            <textarea class="form-control form-control-lg" 
                                      id="reason" 
                                      name="reason" 
                                      rows="4" 
                                      placeholder="Enter reason for status change..."><?= h($_POST['reason'] ?? '') ?></textarea>
                            <small class="text-muted">
                                <i class="bi bi-info-circle me-1"></i>This reason will be visible to the user and logged in audit trail.
                            </small>
                        </div>
                        
                        <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                            <a href="<?= BASE_URL ?>/users/index.php" class="btn btn-secondary btn-lg">
                                <i class="bi bi-x-circle me-1"></i>Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

