<?php
/**
 * users/edit.php
 * Edit user
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
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND deleted_at IS NULL");
$stmt->execute([$id]);
$user = $stmt->fetch();

if (!$user) {
    header('Location: ' . BASE_URL . '/users/index.php');
    exit;
}

// Only superadmin can edit superadmin users
if ($user['role'] === 'superadmin' && get_user_role() !== 'superadmin') {
    header('Location: ' . BASE_URL . '/users/index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $role = $_POST['role'] ?? 'staff';
        $monthly_salary = floatval($_POST['monthly_salary'] ?? 0);
        $password = $_POST['password'] ?? '';
        
        if (empty($name) || empty($email)) {
            $error = 'Name and email are required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email format.';
        } else {
            // Check if email exists for another user
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->execute([$email, $id]);
            if ($stmt->fetch()) {
                $error = 'Email already exists.';
            } else {
                // Update user
                if (!empty($password)) {
                    if (strlen($password) < 8) {
                        $error = 'Password must be at least 8 characters.';
                    } else {
                        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("
                            UPDATE users 
                            SET name = ?, email = ?, password = ?, role = ?, monthly_salary = ?, updated_at = UTC_TIMESTAMP()
                            WHERE id = ?
                        ");
                        $stmt->execute([$name, $email, $hashed_password, $role, $monthly_salary, $id]);
                    }
                } else {
                    $stmt = $pdo->prepare("
                        UPDATE users 
                        SET name = ?, email = ?, role = ?, monthly_salary = ?, updated_at = UTC_TIMESTAMP()
                        WHERE id = ?
                    ");
                    $stmt->execute([$name, $email, $role, $monthly_salary, $id]);
                }
                
                if (!$error) {
                    log_audit(current_user()['id'], 'update', 'user', $id, "Updated user: $email");
                    header('Location: ' . BASE_URL . '/users/index.php?success=User updated successfully');
                    exit;
                }
            }
        }
    }
}

$page_title = 'Edit User';
include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="gradient-text mb-2">Edit User</h1>
            <p class="text-muted mb-0">Update user information, role, and salary details</p>
        </div>
    </div>
    
    <div class="card shadow-lg border-0">
        <div class="card-body p-4">
            <?php if ($error): ?>
                <div class="alert alert-danger animate-fade-in">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    <?= h($error) ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" id="editUserForm">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                
                <div class="row">
                    <div class="col-md-6 mb-4">
                        <label for="name" class="form-label fw-semibold">
                            <i class="bi bi-person me-1"></i>Full Name <span class="text-danger">*</span>
                        </label>
                        <input type="text" 
                               class="form-control form-control-lg" 
                               id="name" 
                               name="name" 
                               required 
                               value="<?= h($user['name']) ?>"
                               placeholder="Enter full name">
                    </div>
                    
                    <div class="col-md-6 mb-4">
                        <label for="email" class="form-label fw-semibold">
                            <i class="bi bi-envelope me-1"></i>Email Address <span class="text-danger">*</span>
                        </label>
                        <input type="email" 
                               class="form-control form-control-lg" 
                               id="email" 
                               name="email" 
                               required 
                               value="<?= h($user['email']) ?>"
                               placeholder="Enter email address">
                    </div>
                </div>
                
                <div class="mb-4">
                    <label for="password" class="form-label fw-semibold">
                        <i class="bi bi-key me-1"></i>New Password
                    </label>
                    <input type="password" 
                           class="form-control form-control-lg" 
                           id="password" 
                           name="password" 
                           minlength="8"
                           placeholder="Leave blank to keep current password">
                    <small class="text-muted">
                        <i class="bi bi-info-circle me-1"></i>Minimum 8 characters. Leave blank to keep current password.
                    </small>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-4">
                        <label for="role" class="form-label fw-semibold">
                            <i class="bi bi-shield-check me-1"></i>Role <span class="text-danger">*</span>
                        </label>
                        <select class="form-select form-select-lg" 
                                id="role" 
                                name="role" 
                                required 
                                <?= (get_user_role() !== 'superadmin' && $user['role'] === 'superadmin') ? 'disabled' : '' ?>>
                            <option value="staff" <?= $user['role'] === 'staff' ? 'selected' : '' ?>>Staff</option>
                            <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                            <option value="accountant" <?= $user['role'] === 'accountant' ? 'selected' : '' ?>>Accountant</option>
                            <?php if (get_user_role() === 'superadmin'): ?>
                                <option value="superadmin" <?= $user['role'] === 'superadmin' ? 'selected' : '' ?>>Superadmin</option>
                            <?php endif; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-6 mb-4">
                        <label for="monthly_salary" class="form-label fw-semibold">
                            <i class="bi bi-cash-stack me-1"></i>Monthly Salary
                        </label>
                        <div class="input-group input-group-lg">
                            <span class="input-group-text">৳</span>
                            <input type="number" 
                                   step="0.01" 
                                   class="form-control" 
                                   id="monthly_salary" 
                                   name="monthly_salary" 
                                   value="<?= h($user['monthly_salary']) ?>"
                                   placeholder="0.00">
                        </div>
                    </div>
                </div>
                
                <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                    <a href="<?= BASE_URL ?>/users/index.php" class="btn btn-secondary btn-lg">
                        <i class="bi bi-x-circle me-1"></i>Cancel
                    </a>
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="bi bi-check-circle me-1"></i>Update User
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.getElementById('editUserForm').addEventListener('submit', function(e) {
    const submitBtn = this.querySelector('button[type="submit"]');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Updating...';
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>

