<?php
/**
 * users/change_password.php
 * Change password for current user
 */

require_once __DIR__ . '/../auth_helper.php';
require_once __DIR__ . '/../helpers.php';

require_login();

$user = current_user();
$pdo = getPDO();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token.';
    } else {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $error = 'All fields are required.';
        } elseif ($new_password !== $confirm_password) {
            $error = 'New password and confirmation do not match.';
        } elseif (strlen($new_password) < 8) {
            $error = 'New password must be at least 8 characters long.';
        } else {
            // Verify current password
            $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$user['id']]);
            $db_user = $stmt->fetch();
            
            if (!$db_user || !password_verify($current_password, $db_user['password'])) {
                $error = 'Current password is incorrect.';
            } else {
                // Update password
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?");
                $stmt->execute([$hashed_password, $user['id']]);
                
                log_audit($user['id'], 'update', 'user', $user['id'], 'Password changed');
                
                $success = 'Password changed successfully.';
            }
        }
    }
}

$page_title = 'Change Password';
include __DIR__ . '/../includes/header.php';
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow-lg border-0">
                <div class="card-header bg-gradient-primary text-white" style="background: linear-gradient(135deg, #007bff, #706fd3);">
                    <h4 class="mb-0">
                        <i class="bi bi-key me-2"></i>Change Password
                    </h4>
                </div>
                <div class="card-body p-4">
                    <?php if ($error): ?>
                        <div class="alert alert-danger animate-fade-in">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>
                            <?= h($error) ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($success): ?>
                        <div class="alert alert-success animate-fade-in">
                            <i class="bi bi-check-circle-fill me-2"></i>
                            <?= h($success) ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="" id="changePasswordForm">
                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                        
                        <div class="mb-4">
                            <label for="current_password" class="form-label fw-semibold">
                                <i class="bi bi-lock me-1"></i>Current Password <span class="text-danger">*</span>
                            </label>
                            <input type="password" 
                                   class="form-control form-control-lg" 
                                   id="current_password" 
                                   name="current_password" 
                                   required
                                   placeholder="Enter your current password">
                        </div>
                        
                        <div class="mb-4">
                            <label for="new_password" class="form-label fw-semibold">
                                <i class="bi bi-key me-1"></i>New Password <span class="text-danger">*</span>
                            </label>
                            <input type="password" 
                                   class="form-control form-control-lg" 
                                   id="new_password" 
                                   name="new_password" 
                                   required 
                                   minlength="8"
                                   placeholder="Enter new password">
                            <small class="text-muted">
                                <i class="bi bi-info-circle me-1"></i>Minimum 8 characters
                            </small>
                        </div>
                        
                        <div class="mb-4">
                            <label for="confirm_password" class="form-label fw-semibold">
                                <i class="bi bi-key-fill me-1"></i>Confirm New Password <span class="text-danger">*</span>
                            </label>
                            <input type="password" 
                                   class="form-control form-control-lg" 
                                   id="confirm_password" 
                                   name="confirm_password" 
                                   required 
                                   minlength="8"
                                   placeholder="Confirm new password">
                        </div>
                        
                        <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                            <a href="<?= BASE_URL ?>/dashboard.php" class="btn btn-secondary btn-lg">
                                <i class="bi bi-x-circle me-1"></i>Cancel
                            </a>
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="bi bi-check-circle me-1"></i>Change Password
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('changePasswordForm').addEventListener('submit', function(e) {
    const newPassword = document.getElementById('new_password').value;
    const confirmPassword = document.getElementById('confirm_password').value;
    
    if (newPassword !== confirmPassword) {
        e.preventDefault();
        Notify.alert('New password and confirmation do not match!', 'Password Mismatch', 'error');
        return false;
    }
    
    const submitBtn = this.querySelector('button[type="submit"]');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Changing...';
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
