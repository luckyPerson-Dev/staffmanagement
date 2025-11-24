<?php
/**
 * users/edit_profile.php
 * Edit own profile
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
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        // Validate basic fields
        if (empty($name) || empty($email)) {
            $error = 'Name and email are required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email format.';
        } else {
            // Check if email exists for another user
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->execute([$email, $user['id']]);
            if ($stmt->fetch()) {
                $error = 'Email already exists.';
            } else {
                // Check if password change is requested
                $password_change = false;
                if (!empty($current_password) || !empty($new_password) || !empty($confirm_password)) {
                    // All password fields must be filled
                    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
                        $error = 'All password fields are required to change password.';
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
                            $password_change = true;
                        }
                    }
                }
                
                // Update profile if no errors
                if (!$error) {
                    if ($password_change) {
                        // Update with password
                        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("
                            UPDATE users 
                            SET name = ?, email = ?, password = ?, updated_at = UTC_TIMESTAMP()
                            WHERE id = ?
                        ");
                        $stmt->execute([$name, $email, $hashed_password, $user['id']]);
                        log_audit($user['id'], 'update', 'user', $user['id'], 'Updated own profile and changed password');
                        $success = 'Profile and password updated successfully.';
                    } else {
                        // Update without password
                        $stmt = $pdo->prepare("
                            UPDATE users 
                            SET name = ?, email = ?, updated_at = UTC_TIMESTAMP()
                            WHERE id = ?
                        ");
                        $stmt->execute([$name, $email, $user['id']]);
                        log_audit($user['id'], 'update', 'user', $user['id'], 'Updated own profile');
                        $success = 'Profile updated successfully.';
                    }
                    
                    header('Location: ' . BASE_URL . '/users/profile.php?success=' . urlencode($success));
                    exit;
                }
            }
        }
    }
}

// Get current profile
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user['id']]);
$profile = $stmt->fetch();

$page_title = 'Edit Profile';
include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="mb-4">
        <h1 class="gradient-text mb-2 fs-3 fs-md-2">Edit Profile</h1>
        <p class="text-muted mb-0 small">Update your profile information and personal details</p>
    </div>
    
    <div class="card shadow-lg border-0">
        <div class="card-body p-3 p-md-4">
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
            
            <form method="POST" action="" id="profileForm">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                
                <div class="row">
                    <div class="col-12 col-md-6 mb-3">
                        <label for="name" class="form-label">
                            Full Name <span class="text-danger">*</span>
                        </label>
                        <input type="text" 
                               class="form-control" 
                               id="name" 
                               name="name" 
                               required 
                               value="<?= h($profile['name']) ?>"
                               placeholder="Enter your full name">
                    </div>
                    
                    <div class="col-12 col-md-6 mb-3">
                        <label for="email" class="form-label">
                            Email Address <span class="text-danger">*</span>
                        </label>
                        <input type="email" 
                               class="form-control" 
                               id="email" 
                               name="email" 
                               required 
                               value="<?= h($profile['email']) ?>"
                               placeholder="Enter your email address">
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">
                        Role
                    </label>
                    <div class="form-control bg-light" style="display: flex; align-items: center;">
                        <span class="badge bg-<?= $profile['role'] === 'superadmin' ? 'danger' : ($profile['role'] === 'admin' ? 'primary' : 'secondary') ?>">
                            <?= ucfirst($profile['role']) ?>
                        </span>
                        <small class="text-muted ms-2">(Cannot be changed)</small>
                    </div>
                </div>
                
                <!-- Password Change Section -->
                <div class="mb-4">
                    <h6 class="fw-bold mb-3">
                        <i class="bi bi-key me-2"></i>Change Password
                    </h6>
                    <p class="text-muted small mb-3">Leave password fields empty if you don't want to change your password.</p>
                    
                    <div class="row">
                        <div class="col-12 mb-3">
                            <label for="current_password" class="form-label">
                                Current Password
                            </label>
                            <input type="password" 
                                   class="form-control" 
                                   id="current_password" 
                                   name="current_password" 
                                   placeholder="Enter current password">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="new_password" class="form-label">
                                New Password
                            </label>
                            <input type="password" 
                                   class="form-control" 
                                   id="new_password" 
                                   name="new_password" 
                                   minlength="8"
                                   placeholder="Enter new password">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="confirm_password" class="form-label">
                                Confirm New Password
                            </label>
                            <input type="password" 
                                   class="form-control" 
                                   id="confirm_password" 
                                   name="confirm_password" 
                                   minlength="8"
                                   placeholder="Confirm new password">
                        </div>
                    </div>
                    
                    <small class="text-muted">
                        <i class="bi bi-info-circle me-1"></i>
                        Minimum 8 characters required. Leave empty to keep current password.
                    </small>
                </div>
                
                <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                    <a href="<?= BASE_URL ?>/users/profile.php" class="btn btn-secondary btn-lg">
                        <i class="bi bi-x-circle me-1"></i>Cancel
                    </a>
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="bi bi-check-circle me-1"></i>Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Form submission
    const profileForm = document.getElementById('profileForm');
    if (profileForm) {
        profileForm.addEventListener('submit', function(e) {
            const currentPassword = document.getElementById('current_password').value;
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            // Validate password fields if any are filled
            if (currentPassword || newPassword || confirmPassword) {
                if (!currentPassword || !newPassword || !confirmPassword) {
                    e.preventDefault();
                    Notify.alert('Please fill all password fields to change your password, or leave them all empty.', 'Password Required', 'warning');
                    return false;
                }
                
                if (newPassword !== confirmPassword) {
                    e.preventDefault();
                    Notify.alert('New password and confirmation do not match.', 'Password Mismatch', 'error');
                    return false;
                }
                
                if (newPassword.length < 8) {
                    e.preventDefault();
                    Notify.alert('New password must be at least 8 characters long.', 'Password Too Short', 'warning');
                    return false;
                }
            }
            
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Saving...';
        });
    }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>

