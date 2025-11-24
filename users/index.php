<?php
/**
 * users/index.php
 * List all users
 */

require_once __DIR__ . '/../auth_helper.php';
require_once __DIR__ . '/../helpers.php';

require_role(['superadmin', 'admin']);

$pdo = getPDO();
$stmt = $pdo->query("
    SELECT id, name, email, role, monthly_salary, status, created_at 
    FROM users 
    WHERE deleted_at IS NULL 
    ORDER BY role, name
");
$users = $stmt->fetchAll();

// Get statistics
$stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE deleted_at IS NULL");
$total_users = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE (status = 'active' OR status IS NULL) AND deleted_at IS NULL");
$active_users = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role = 'staff' AND deleted_at IS NULL");
$staff_count = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT SUM(monthly_salary) as total FROM users WHERE role = 'staff' AND (status = 'active' OR status IS NULL) AND deleted_at IS NULL");
$total_salary = $stmt->fetch()['total'] ?? 0;

$page_title = 'Manage Users';

// Get current user info BEFORE including header
$current_user = current_user();
$current_role = $current_user ? $current_user['role'] : null;

include __DIR__ . '/../includes/header.php';

// Get success message from URL
$success_message = $_GET['success'] ?? null;
?>

<div class="container-fluid py-4">
    <?php if ($success_message): ?>
        <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
            <i class="bi bi-check-circle me-2"></i><?= h($success_message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <div class="mb-4">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3">
            <div>
                <h1 class="gradient-text mb-2 fs-3 fs-md-2">Manage Users</h1>
                <p class="text-muted mb-0 small">Manage staff accounts, roles, and permissions</p>
            </div>
            <?php if (in_array($current_role, ['superadmin', 'admin'])): ?>
                <button type="button" 
                        class="btn btn-primary btn-lg w-100 w-md-auto shadow-sm" 
                        data-bs-toggle="modal" 
                        data-bs-target="#createUserModal" 
                        id="createUserBtn">
                    <i class="bi bi-person-plus me-2"></i>Create New User
                </button>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Statistics Cards -->
    <div class="row g-3 g-md-4 mb-4">
        <div class="col-6 col-md-3">
            <div class="stat-card animate-slide-up">
                <div class="stat-icon" style="background: linear-gradient(135deg, #007bff, #706fd3);">
                    <i class="bi bi-people-fill"></i>
                </div>
                <div class="stat-label small">Total Users</div>
                <div class="stat-value fs-5 fs-md-4" data-count="<?= $total_users ?>"><?= $total_users ?></div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card animate-slide-up" style="animation-delay: 0.1s">
                <div class="stat-icon" style="background: linear-gradient(135deg, #28a745, #20c997);">
                    <i class="bi bi-check-circle-fill"></i>
                </div>
                <div class="stat-label small">Active Users</div>
                <div class="stat-value fs-5 fs-md-4" data-count="<?= $active_users ?>"><?= $active_users ?></div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card animate-slide-up" style="animation-delay: 0.2s">
                <div class="stat-icon" style="background: linear-gradient(135deg, #17a2b8, #138496);">
                    <i class="bi bi-person-badge"></i>
                </div>
                <div class="stat-label small">Staff Members</div>
                <div class="stat-value fs-5 fs-md-4" data-count="<?= $staff_count ?>"><?= $staff_count ?></div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card animate-slide-up" style="animation-delay: 0.3s">
                <div class="stat-icon" style="background: linear-gradient(135deg, #ffc107, #ff9800);">
                    <i class="bi bi-cash-stack"></i>
                </div>
                <div class="stat-label small">Total Salary</div>
                <div class="stat-value fs-5 fs-md-4">৳<?= number_format($total_salary, 0) ?></div>
            </div>
        </div>
    </div>
    
    <div class="card shadow-lg border-0 animate-slide-up" style="animation-delay: 0.4s">
        <div class="card-body p-0">
            <!-- Desktop Table View -->
            <div class="table-responsive d-none d-md-block">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light" style="background: linear-gradient(135deg, #f8f9fa, #e9ecef);">
                        <tr>
                            <th class="ps-4 py-3 fw-semibold">User</th>
                            <th class="py-3 fw-semibold">Role</th>
                            <th class="py-3 fw-semibold">Status</th>
                            <th class="py-3 fw-semibold">Monthly Salary</th>
                            <th class="py-3 fw-semibold">Created</th>
                            <th class="text-end pe-4 py-3 fw-semibold">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($users)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">
                                    <i class="bi bi-inbox fs-1 d-block mb-2 text-muted"></i>
                                    <p class="mb-0">No users found</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($users as $index => $user): ?>
                                <tr class="user-row" style="transition: all 0.2s ease;">
                                    <td class="ps-4">
                                        <div class="d-flex align-items-center">
                                            <div class="rounded-circle bg-gradient-primary d-flex align-items-center justify-content-center me-3 flex-shrink-0 shadow-sm" 
                                                 style="width: 45px; height: 45px; background: linear-gradient(135deg, #007bff, #706fd3);">
                                                <span class="text-white fw-bold fs-6"><?= strtoupper(substr($user['name'], 0, 1)) ?></span>
                                            </div>
                                            <div class="min-w-0">
                                                <strong class="d-block text-truncate fw-semibold"><?= h($user['name']) ?></strong>
                                                <small class="text-muted d-flex align-items-center">
                                                    <i class="bi bi-envelope me-1"></i>
                                                    <span class="text-truncate"><?= h($user['email']) ?></span>
                                                </small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php
                                        $roleColors = [
                                            'superadmin' => ['bg' => 'danger', 'icon' => 'shield-fill', 'gradient' => 'linear-gradient(135deg, #dc3545, #c82333)'],
                                            'admin' => ['bg' => 'primary', 'icon' => 'person-badge', 'gradient' => 'linear-gradient(135deg, #007bff, #0056b3)'],
                                            'accountant' => ['bg' => 'info', 'icon' => 'calculator', 'gradient' => 'linear-gradient(135deg, #17a2b8, #138496)'],
                                            'staff' => ['bg' => 'secondary', 'icon' => 'person', 'gradient' => 'linear-gradient(135deg, #6c757d, #5a6268)']
                                        ];
                                        $roleConfig = $roleColors[$user['role']] ?? $roleColors['staff'];
                                        ?>
                                        <span class="badge px-3 py-2 shadow-sm" style="background: <?= $roleConfig['gradient'] ?>; border: none;">
                                            <i class="bi bi-<?= $roleConfig['icon'] ?> me-1"></i>
                                            <?= ucfirst($user['role']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php 
                                        $status = $user['status'] ?? 'active';
                                        $statusConfig = [
                                            'active' => ['bg' => 'success', 'icon' => 'check-circle-fill', 'gradient' => 'linear-gradient(135deg, #28a745, #218838)'],
                                            'banned' => ['bg' => 'danger', 'icon' => 'x-circle-fill', 'gradient' => 'linear-gradient(135deg, #dc3545, #c82333)'],
                                            'suspended' => ['bg' => 'warning', 'icon' => 'pause-circle-fill', 'gradient' => 'linear-gradient(135deg, #ffc107, #e0a800)']
                                        ];
                                        $statusInfo = $statusConfig[$status] ?? $statusConfig['active'];
                                        ?>
                                        <span class="badge px-3 py-2 shadow-sm" style="background: <?= $statusInfo['gradient'] ?>; border: none;">
                                            <i class="bi bi-<?= $statusInfo['icon'] ?> me-1"></i>
                                            <?= ucfirst($status) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="rounded-circle bg-light d-flex align-items-center justify-content-center me-2" style="width: 32px; height: 32px;">
                                                <i class="bi bi-cash-stack text-primary"></i>
                                            </div>
                                            <span class="fw-semibold text-success">৳<?= number_format($user['monthly_salary'], 2) ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="rounded-circle bg-light d-flex align-items-center justify-content-center me-2" style="width: 32px; height: 32px;">
                                                <i class="bi bi-calendar3 text-muted"></i>
                                            </div>
                                            <span class="text-muted"><?= format_date($user['created_at']) ?></span>
                                        </div>
                                    </td>
                                    <td class="text-end pe-4">
                                        <div class="btn-group shadow-sm" role="group">
                                            <a href="<?= BASE_URL ?>/users/edit.php?id=<?= $user['id'] ?>" 
                                               class="btn btn-sm btn-outline-primary border-0" 
                                               title="Edit User"
                                               style="transition: all 0.2s ease; text-decoration: none;">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <?php 
                                            $current_user_obj = current_user();
                                            $current_role_check = $current_user_obj ? $current_user_obj['role'] : null;
                                            if (in_array($current_role_check, ['superadmin', 'admin']) && $user['id'] != $current_user_obj['id']): 
                                            ?>
                                                <a href="<?= BASE_URL ?>/users/manage_status.php?id=<?= $user['id'] ?>" 
                                                   class="btn btn-sm btn-outline-<?= $status === 'banned' ? 'success' : ($status === 'suspended' ? 'success' : 'warning') ?> border-0" 
                                                   title="Manage Status"
                                                   style="transition: all 0.2s ease; text-decoration: none;">
                                                    <i class="bi bi-<?= $status === 'banned' ? 'check-circle' : ($status === 'suspended' ? 'play-circle' : 'shield-exclamation') ?>"></i>
                                                </a>
                                                <a href="<?= BASE_URL ?>/users/delete.php?id=<?= $user['id'] ?>" 
                                                   class="btn btn-sm btn-outline-danger border-0 delete-link" 
                                                   data-confirm="Are you sure you want to delete this user? This action cannot be undone."
                                                   data-confirm-title="Delete User"
                                                   data-confirm-type="danger"
                                                   title="Delete User"
                                                   style="transition: all 0.2s ease; text-decoration: none;">
                                                    <i class="bi bi-trash"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Mobile Card View -->
            <div class="d-md-none">
                <?php if (empty($users)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                        <p class="mb-0">No users found</p>
                    </div>
                <?php else: ?>
                    <div class="p-2 p-md-3">
                        <?php foreach ($users as $user): ?>
                            <?php
                            $roleColors = [
                                'superadmin' => ['bg' => 'danger', 'icon' => 'shield-fill', 'gradient' => 'linear-gradient(135deg, #dc3545, #c82333)'],
                                'admin' => ['bg' => 'primary', 'icon' => 'person-badge', 'gradient' => 'linear-gradient(135deg, #007bff, #0056b3)'],
                                'accountant' => ['bg' => 'info', 'icon' => 'calculator', 'gradient' => 'linear-gradient(135deg, #17a2b8, #138496)'],
                                'staff' => ['bg' => 'secondary', 'icon' => 'person', 'gradient' => 'linear-gradient(135deg, #6c757d, #5a6268)']
                            ];
                            $roleConfig = $roleColors[$user['role']] ?? $roleColors['staff'];
                            $status = $user['status'] ?? 'active';
                            $statusConfig = [
                                'active' => ['bg' => 'success', 'icon' => 'check-circle-fill', 'gradient' => 'linear-gradient(135deg, #28a745, #218838)'],
                                'banned' => ['bg' => 'danger', 'icon' => 'x-circle-fill', 'gradient' => 'linear-gradient(135deg, #dc3545, #c82333)'],
                                'suspended' => ['bg' => 'warning', 'icon' => 'pause-circle-fill', 'gradient' => 'linear-gradient(135deg, #ffc107, #e0a800)']
                            ];
                            $statusInfo = $statusConfig[$status] ?? $statusConfig['active'];
                            ?>
                            <div class="card mb-3 border-0 user-mobile-card" style="border-radius: 16px; overflow: hidden;">
                                <!-- Card Header with Gradient -->
                                <div class="card-header border-0 p-3" style="background: linear-gradient(135deg, rgba(0, 123, 255, 0.1), rgba(112, 111, 211, 0.1));">
                                    <div class="d-flex align-items-center">
                                        <div class="rounded-circle d-flex align-items-center justify-content-center me-3 flex-shrink-0 shadow" 
                                             style="width: 56px; height: 56px; background: linear-gradient(135deg, #007bff, #706fd3);">
                                            <span class="text-white fw-bold" style="font-size: 1.5rem;"><?= strtoupper(substr($user['name'], 0, 1)) ?></span>
                                        </div>
                                        <div class="flex-grow-1 min-w-0">
                                            <h6 class="mb-1 fw-bold" style="font-size: 1rem; color: #212529;"><?= h($user['name']) ?></h6>
                                            <small class="text-muted d-flex align-items-center" style="font-size: 0.8rem;">
                                                <i class="bi bi-envelope me-1"></i>
                                                <span class="text-truncate"><?= h($user['email']) ?></span>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Card Body -->
                                <div class="card-body p-3">
                                    <!-- Badges Row -->
                                    <div class="d-flex flex-wrap gap-2 mb-3">
                                        <span class="badge px-3 py-2" style="background: <?= $roleConfig['gradient'] ?>; border: none; font-size: 0.75rem; font-weight: 600;">
                                            <i class="bi bi-<?= $roleConfig['icon'] ?> me-1"></i>
                                            <?= ucfirst($user['role']) ?>
                                        </span>
                                        <span class="badge px-3 py-2" style="background: <?= $statusInfo['gradient'] ?>; border: none; font-size: 0.75rem; font-weight: 600;">
                                            <i class="bi bi-<?= $statusInfo['icon'] ?> me-1"></i>
                                            <?= ucfirst($status) ?>
                                        </span>
                                    </div>
                                    
                                    <!-- Info Cards -->
                                    <div class="row g-2 mb-3">
                                        <div class="col-12">
                                            <div class="d-flex align-items-center justify-content-between p-3 rounded-3" style="background: linear-gradient(135deg, rgba(40, 167, 69, 0.08), rgba(32, 201, 151, 0.08)); border: 1px solid rgba(40, 167, 69, 0.2);">
                                                <div class="d-flex align-items-center">
                                                    <div class="rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px; background: linear-gradient(135deg, #28a745, #20c997);">
                                                        <i class="bi bi-cash-stack text-white"></i>
                                                    </div>
                                                    <div>
                                                        <small class="text-muted d-block mb-0" style="font-size: 0.7rem; font-weight: 500;">Monthly Salary</small>
                                                        <strong class="text-success d-block" style="font-size: 1rem;">৳<?= number_format($user['monthly_salary'], 2) ?></strong>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-12">
                                            <div class="d-flex align-items-center justify-content-between p-3 rounded-3" style="background: rgba(248, 249, 250, 0.8); border: 1px solid rgba(0, 0, 0, 0.08);">
                                                <div class="d-flex align-items-center">
                                                    <div class="rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px; background: rgba(108, 117, 125, 0.1);">
                                                        <i class="bi bi-calendar3 text-muted"></i>
                                                    </div>
                                                    <div>
                                                        <small class="text-muted d-block mb-0" style="font-size: 0.7rem; font-weight: 500;">Member Since</small>
                                                        <span class="text-dark d-block" style="font-size: 0.9rem; font-weight: 500;"><?= format_date($user['created_at']) ?></span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Action Buttons -->
                                    <div class="d-grid gap-2">
                                        <a href="<?= BASE_URL ?>/users/edit.php?id=<?= $user['id'] ?>" 
                                           class="btn btn-primary btn-sm w-100 shadow-sm" 
                                           style="border-radius: 8px; font-weight: 600; text-decoration: none;">
                                            <i class="bi bi-pencil me-2"></i>Edit User
                                        </a>
                                        <?php 
                                        $current_user_obj = current_user();
                                        $current_role_check = $current_user_obj ? $current_user_obj['role'] : null;
                                        if (in_array($current_role_check, ['superadmin', 'admin']) && $user['id'] != $current_user_obj['id']): 
                                        ?>
                                            <div class="btn-group w-100 shadow-sm" role="group">
                                                <a href="<?= BASE_URL ?>/users/manage_status.php?id=<?= $user['id'] ?>" 
                                                   class="btn btn-sm btn-<?= $status === 'banned' ? 'success' : ($status === 'suspended' ? 'success' : 'warning') ?>" 
                                                   style="border-radius: 8px 0 0 8px; font-weight: 600; text-decoration: none;">
                                                    <i class="bi bi-<?= $status === 'banned' ? 'check-circle' : ($status === 'suspended' ? 'play-circle' : 'shield-exclamation') ?> me-1"></i>
                                                    Status
                                                </a>
                                                <a href="<?= BASE_URL ?>/users/delete.php?id=<?= $user['id'] ?>" 
                                                   class="btn btn-sm btn-danger delete-link"
                                                   data-confirm="Are you sure you want to delete this user? This action cannot be undone."
                                                   data-confirm-title="Delete User"
                                                   data-confirm-type="danger"
                                                   style="border-radius: 0 8px 8px 0; font-weight: 600; text-decoration: none;">
                                                    <i class="bi bi-trash me-1"></i>
                                                    Delete
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
.user-row:hover {
    background-color: rgba(0, 123, 255, 0.03) !important;
    transform: translateX(4px);
}

.user-row:hover .btn-outline-primary,
.user-row:hover .btn-outline-warning,
.user-row:hover .btn-outline-success,
.user-row:hover .btn-outline-danger {
    transform: scale(1.1);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
}

@media (max-width: 991px) {
    .user-mobile-card {
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        border: 1px solid rgba(0, 0, 0, 0.05) !important;
    }
    
    .user-mobile-card:hover,
    .user-mobile-card:active {
        transform: translateY(-2px);
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15) !important;
    }
    
    .user-mobile-card .card-header {
        position: relative;
        overflow: hidden;
    }
    
    .user-mobile-card .card-header::after {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 3px;
        background: linear-gradient(90deg, #007bff, #706fd3);
    }
    
    .user-mobile-card .btn {
        transition: all 0.2s ease;
    }
    
    .user-mobile-card .btn:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Animate counters
    const counters = document.querySelectorAll('[data-count]');
    counters.forEach(counter => {
        const target = parseInt(counter.getAttribute('data-count'));
        let current = 0;
        const increment = target / 30;
        const timer = setInterval(() => {
            current += increment;
            if (current >= target) {
                counter.textContent = target;
                clearInterval(timer);
            } else {
                counter.textContent = Math.floor(current);
            }
        }, 30);
    });
});
</script>

<!-- Create User Modal -->
<?php if (in_array($current_role, ['superadmin', 'admin'])): ?>
<div class="modal fade" id="createUserModal" tabindex="-1" aria-labelledby="createUserModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="createUserModalLabel">
                    <i class="bi bi-person-plus me-2"></i>Create New User
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            
            <form method="POST" action="<?= BASE_URL ?>/users/create.php" id="createUserForm">
                <input type="hidden" name="csrf_token" value="<?= h(generate_csrf_token()) ?>">
                <div class="modal-body">
                    <div id="createUserError" class="alert alert-danger d-none" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        <span id="createUserErrorText"></span>
                    </div>
                    
                    <div class="mb-3">
                        <label for="create_name" class="form-label">
                            Full Name <span class="text-danger">*</span>
                        </label>
                        <input type="text" 
                               class="form-control" 
                               id="create_name" 
                               name="name" 
                               required 
                               placeholder="Enter full name">
                    </div>
                    
                    <div class="mb-3">
                        <label for="create_email" class="form-label">
                            Email Address <span class="text-danger">*</span>
                        </label>
                        <input type="email" 
                               class="form-control" 
                               id="create_email" 
                               name="email" 
                               required 
                               placeholder="Enter email address">
                    </div>
                    
                    <div class="mb-3">
                        <label for="create_password" class="form-label">
                            Password <span class="text-danger">*</span>
                        </label>
                        <input type="password" 
                               class="form-control" 
                               id="create_password" 
                               name="password" 
                               required 
                               minlength="8"
                               placeholder="Enter password">
                        <small class="text-muted">Minimum 8 characters</small>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="create_role" class="form-label">
                                Role <span class="text-danger">*</span>
                            </label>
                            <select class="form-select" id="create_role" name="role" required>
                                <option value="">-- Select Role --</option>
                                <option value="staff">Staff</option>
                                <option value="accountant">Accountant</option>
                                <option value="admin">Admin</option>
                                <?php if ($current_role === 'superadmin'): ?>
                                    <option value="superadmin">Superadmin</option>
                                <?php endif; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="create_monthly_salary" class="form-label">
                                Monthly Salary
                            </label>
                            <div class="input-group">
                                <span class="input-group-text">৳</span>
                                <input type="number" 
                                       step="0.01" 
                                       class="form-control" 
                                       id="create_monthly_salary" 
                                       name="monthly_salary" 
                                       value="0"
                                       min="0"
                                       placeholder="0.00">
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="createUserSubmitBtn">
                        <i class="bi bi-check-circle me-2"></i>Create User
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const createUserForm = document.getElementById('createUserForm');
    const createUserModalEl = document.getElementById('createUserModal');
    
    // Only initialize if modal exists
    if (!createUserForm || !createUserModalEl) {
        // Create user modal not available
        return;
    }
    
    const createUserModal = new bootstrap.Modal(createUserModalEl);
    const errorAlert = document.getElementById('createUserError');
    const errorText = document.getElementById('createUserErrorText');
    const submitBtn = document.getElementById('createUserSubmitBtn');
    
    // Handle form submission via AJAX
    createUserForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        // Hide previous errors
        errorAlert.classList.add('d-none');
        
        // Disable submit button
        const originalHTML = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Creating...';
        
        // Get form data
        const formData = new FormData(createUserForm);
        
        // Submit via fetch
        fetch(createUserForm.action, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: formData
        })
        .then(response => {
            const contentType = response.headers.get('content-type');
            if (contentType && contentType.includes('application/json')) {
                return response.json();
            } else if (response.redirected) {
                // Success - redirect to users page
                window.location.href = response.url;
                return;
            } else {
                return response.text();
            }
        })
        .then(data => {
            if (!data) return;
            
            if (typeof data === 'object') {
                // JSON response
                if (data.success) {
                    // Success - close modal and reload page
                    createUserModal.hide();
                    window.location.reload();
                } else {
                    // Show error
                    errorText.textContent = data.message || 'An error occurred';
                    errorAlert.classList.remove('d-none');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalHTML;
                }
            } else {
                // HTML response - check for errors
                const parser = new DOMParser();
                const doc = parser.parseFromString(data, 'text/html');
                const errorDiv = doc.querySelector('.alert-danger');
                
                if (errorDiv) {
                    // Show error
                    errorText.textContent = errorDiv.textContent.trim();
                    errorAlert.classList.remove('d-none');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalHTML;
                } else {
                    // Success - reload page
                    createUserModal.hide();
                    window.location.reload();
                }
            }
        })
        .catch(error => {
            errorText.textContent = 'An error occurred: ' + error.message;
            errorAlert.classList.remove('d-none');
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalHTML;
        });
    });
    
    // Reset form when modal is closed
    if (createUserModalEl) {
        createUserModalEl.addEventListener('hidden.bs.modal', function() {
            if (createUserForm) createUserForm.reset();
            if (errorAlert) errorAlert.classList.add('d-none');
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="bi bi-check-circle me-2"></i>Create User';
            }
        });
    }
});
</script>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>

