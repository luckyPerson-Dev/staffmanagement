<?php
/**
 * users/create.php
 * Create new user (superadmin only)
 */

require_once __DIR__ . '/../auth_helper.php';
require_once __DIR__ . '/../helpers.php';

require_role(['superadmin', 'admin']);

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF validation
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? 'staff';
        $monthly_salary = floatval($_POST['monthly_salary'] ?? 0);
        
        // Validate role - admin/superadmin can create staff, accountant, admin, or superadmin
        // But admin cannot create superadmin (only superadmin can)
        $user_role = current_user()['role'] ?? '';
        if (!in_array($role, ['staff', 'accountant', 'admin', 'superadmin'])) {
            $error = 'Invalid role selected.';
        } elseif ($role === 'superadmin' && $user_role !== 'superadmin') {
            $error = 'Only superadmin can create superadmin users.';
        }
        // Validation
        elseif (empty($name) || empty($email) || empty($password)) {
            $error = 'Name, email, and password are required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email format.';
        } elseif (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters.';
        } else {
        $pdo = getPDO();
        
        // Check if email exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = 'Email already exists.';
        } else {
            // Create user
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("
                INSERT INTO users (name, email, password, role, monthly_salary, created_at)
                VALUES (?, ?, ?, ?, ?, UTC_TIMESTAMP())
            ");
            $stmt->execute([$name, $email, $hashed_password, $role, $monthly_salary]);
            $user_id = $pdo->lastInsertId();
            
            log_audit(current_user()['id'], 'create', 'user', $user_id, "Created user: $email");
            
            $success = 'User created successfully.';
            
            // Check if this is an AJAX request
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'message' => $success]);
                exit;
            }
            
            header('Location: ' . BASE_URL . '/users/index.php?success=' . urlencode($success));
            exit;
        }
    }
    
    // If error and AJAX request, return JSON
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest' && !empty($error)) {
        header('Content-Type: application/json');
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $error]);
        exit;
    }
}

$page_title = 'Create User';
include __DIR__ . '/../includes/header.php';
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow-lg border-0">
                <div class="card-header bg-gradient-primary text-white" style="background: linear-gradient(135deg, #007bff, #706fd3);">
                    <h4 class="mb-0">
                        <i class="bi bi-person-plus me-2"></i>Create New User
                    </h4>
                </div>
                <div class="card-body p-4">
                    <?php if ($error): ?>
                        <div class="alert alert-danger animate-fade-in">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>
                            <?= h($error) ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="" id="createUserForm">
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
                                       value="<?= h($_POST['name'] ?? '') ?>"
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
                                       value="<?= h($_POST['email'] ?? '') ?>"
                                       placeholder="Enter email address">
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label for="password" class="form-label fw-semibold">
                                <i class="bi bi-key me-1"></i>Password <span class="text-danger">*</span>
                            </label>
                            <input type="password" 
                                   class="form-control form-control-lg" 
                                   id="password" 
                                   name="password" 
                                   required 
                                   minlength="8"
                                   placeholder="Enter password">
                            <small class="text-muted">
                                <i class="bi bi-info-circle me-1"></i>Minimum 8 characters
                            </small>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-4">
                                <label for="role" class="form-label fw-semibold">
                                    <i class="bi bi-shield-check me-1"></i>Role <span class="text-danger">*</span>
                                </label>
                                <select class="form-select form-select-lg" id="role" name="role" required>
                                    <option value="staff" <?= ($_POST['role'] ?? '') === 'staff' ? 'selected' : '' ?>>Staff</option>
                                    <option value="admin" <?= ($_POST['role'] ?? '') === 'admin' ? 'selected' : '' ?>>Admin</option>
                                    <option value="accountant" <?= ($_POST['role'] ?? '') === 'accountant' ? 'selected' : '' ?>>Accountant</option>
                                    <?php if (current_user()['role'] === 'superadmin'): ?>
                                        <option value="superadmin" <?= ($_POST['role'] ?? '') === 'superadmin' ? 'selected' : '' ?>>Superadmin</option>
                                    <?php endif; ?>
                                </select>
                                <?php if (current_user()['role'] !== 'superadmin'): ?>
                                    <small class="text-muted d-block mt-1">
                                        <i class="bi bi-info-circle me-1"></i>Only superadmin can create superadmin accounts.
                                    </small>
                                <?php endif; ?>
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
                                           value="<?= h($_POST['monthly_salary'] ?? '0') ?>"
                                           placeholder="0.00">
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                            <a href="<?= BASE_URL ?>/users/index.php" class="btn btn-secondary btn-lg">
                                <i class="bi bi-x-circle me-1"></i>Cancel
                            </a>
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="bi bi-check-circle me-1"></i>Create User
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('createUserForm').addEventListener('submit', function(e) {
    const submitBtn = this.querySelector('button[type="submit"]');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Creating...';
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>

