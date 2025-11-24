<?php
/**
 * users/profile.php
 * View user profile
 */

require_once __DIR__ . '/../auth_helper.php';
require_once __DIR__ . '/../helpers.php';

require_login();

$user = current_user();
$pdo = getPDO();

// Get full user details
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user['id']]);
$profile = $stmt->fetch();

// Get statistics
$stmt = $pdo->prepare("SELECT COUNT(*) FROM daily_progress WHERE user_id = ?");
$stmt->execute([$user['id']]);
$totalProgressDays = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT AVG(progress_percent) FROM daily_progress WHERE user_id = ?");
$stmt->execute([$user['id']]);
$avgProgress = $stmt->fetchColumn() ?: 0;

$stmt = $pdo->prepare("SELECT COUNT(*) FROM advances WHERE user_id = ?");
$stmt->execute([$user['id']]);
$totalAdvances = $stmt->fetchColumn();

$stats = [
    'total_progress_days' => $totalProgressDays,
    'avg_progress' => $avgProgress,
    'total_advances' => $totalAdvances,
];

$page_title = 'My Profile';
include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="mb-4">
        <h1 class="gradient-text mb-2 fs-3 fs-md-2">My Profile</h1>
        <p class="text-muted mb-0 small">View and manage your profile information</p>
    </div>
    
    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show animate-fade-in mb-4" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i>
            <span class="small"><?= h($_GET['success']) ?></span>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <div class="row g-4">
        <div class="col-12 col-md-4">
            <!-- Profile Card -->
            <div class="card shadow-lg border-0 mb-4 mb-md-0">
                <div class="card-body text-center p-3 p-md-4">
                    <div class="mb-3">
                        <div class="rounded-circle bg-gradient-primary d-inline-flex align-items-center justify-content-center shadow-lg" 
                             style="width: 100px; height: 100px; background: linear-gradient(135deg, #007bff, #706fd3);">
                            <span class="text-white fw-bold" style="font-size: 2.5rem;"><?= strtoupper(substr($profile['name'], 0, 1)) ?></span>
                        </div>
                    </div>
                    <h4 class="mb-1 fs-5 fs-md-4"><?= h($profile['name']) ?></h4>
                    <p class="text-muted mb-2 small">
                        <i class="bi bi-envelope me-1"></i><?= h($profile['email']) ?>
                    </p>
                    <span class="badge bg-<?= $profile['role'] === 'superadmin' ? 'danger' : ($profile['role'] === 'admin' ? 'primary' : 'secondary') ?> mb-3 px-3 py-2">
                        <i class="bi bi-<?= $profile['role'] === 'superadmin' ? 'shield-fill' : ($profile['role'] === 'admin' ? 'person-badge' : 'person') ?> me-1"></i>
                        <?= ucfirst($profile['role']) ?>
                    </span>
                    <?php if ($profile['status'] && $profile['status'] !== 'active'): ?>
                        <div class="alert alert-<?= $profile['status'] === 'banned' ? 'danger' : 'warning' ?> mb-0 mt-2 small">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            Account <?= ucfirst($profile['status']) ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Statistics Card -->
            <div class="card shadow-lg border-0">
                <div class="card-header bg-white border-bottom">
                    <h6 class="mb-0 small">
                        <i class="bi bi-bar-chart me-2 text-primary"></i>Statistics
                    </h6>
                </div>
                <div class="card-body p-3 p-md-4">
                    <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                        <span class="text-muted small">
                            <i class="bi bi-calendar-check me-1"></i>Progress Days
                        </span>
                        <strong class="fs-6"><?= $stats['total_progress_days'] ?></strong>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                        <span class="text-muted small">
                            <i class="bi bi-graph-up me-1"></i>Avg Progress
                        </span>
                        <strong class="fs-6 text-primary"><?= number_format($stats['avg_progress'], 1) ?>%</strong>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-muted small">
                            <i class="bi bi-wallet2 me-1"></i>Total Advances
                        </span>
                        <strong class="fs-6"><?= $stats['total_advances'] ?></strong>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-12 col-md-8">
            <div class="card shadow-lg border-0">
                <div class="card-header bg-white border-bottom">
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-2">
                        <h5 class="mb-0 small">
                            <i class="bi bi-person-circle me-2 text-primary"></i>Profile Information
                        </h5>
                        <a href="<?= BASE_URL ?>/users/edit_profile.php" class="btn btn-sm btn-primary">
                            <i class="bi bi-pencil me-1"></i>Edit Profile
                        </a>
                    </div>
                </div>
                <div class="card-body p-3 p-md-4">
                    <dl class="row mb-0 g-3">
                        <dt class="col-12 col-sm-4 fw-semibold">
                            <i class="bi bi-person me-1 text-muted"></i>Full Name
                        </dt>
                        <dd class="col-12 col-sm-8 mb-0"><?= h($profile['name']) ?></dd>
                        
                        <dt class="col-12 col-sm-4 fw-semibold">
                            <i class="bi bi-envelope me-1 text-muted"></i>Email Address
                        </dt>
                        <dd class="col-12 col-sm-8 mb-0">
                            <a href="mailto:<?= h($profile['email']) ?>" class="text-decoration-none">
                                <?= h($profile['email']) ?>
                            </a>
                        </dd>
                        
                        <dt class="col-12 col-sm-4 fw-semibold">
                            <i class="bi bi-shield-check me-1 text-muted"></i>Role
                        </dt>
                        <dd class="col-12 col-sm-8 mb-0">
                            <span class="badge bg-<?= $profile['role'] === 'superadmin' ? 'danger' : ($profile['role'] === 'admin' ? 'primary' : 'secondary') ?> px-3 py-2">
                                <i class="bi bi-<?= $profile['role'] === 'superadmin' ? 'shield-fill' : ($profile['role'] === 'admin' ? 'person-badge' : 'person') ?> me-1"></i>
                                <?= ucfirst($profile['role']) ?>
                            </span>
                        </dd>
                        
                        <dt class="col-12 col-sm-4 fw-semibold">
                            <i class="bi bi-cash-stack me-1 text-muted"></i>Monthly Salary
                        </dt>
                        <dd class="col-12 col-sm-8 mb-0">
                            <strong class="text-success">৳<?= number_format($profile['monthly_salary'], 2) ?></strong>
                        </dd>
                        
                        <dt class="col-12 col-sm-4 fw-semibold">
                            <i class="bi bi-info-circle me-1 text-muted"></i>Account Status
                        </dt>
                        <dd class="col-12 col-sm-8 mb-0">
                            <span class="badge bg-<?= $profile['status'] === 'active' ? 'success' : ($profile['status'] === 'banned' ? 'danger' : 'warning') ?> px-3 py-2">
                                <i class="bi bi-<?= $profile['status'] === 'active' ? 'check-circle' : ($profile['status'] === 'banned' ? 'x-circle' : 'pause-circle') ?> me-1"></i>
                                <?= ucfirst($profile['status'] ?? 'active') ?>
                            </span>
                        </dd>
                        
                        <dt class="col-12 col-sm-4 fw-semibold">
                            <i class="bi bi-calendar-event me-1 text-muted"></i>Member Since
                        </dt>
                        <dd class="col-12 col-sm-8 mb-0">
                            <i class="bi bi-clock me-1 text-muted"></i><?= format_date($profile['created_at']) ?>
                        </dd>
                        
                        <?php if ($profile['status_reason']): ?>
                            <dt class="col-12 col-sm-4 fw-semibold">
                                <i class="bi bi-file-text me-1 text-muted"></i>Status Reason
                            </dt>
                            <dd class="col-12 col-sm-8 mb-0"><?= h($profile['status_reason']) ?></dd>
                        <?php endif; ?>
                    </dl>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

