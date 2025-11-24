<?php
/**
 * advances/index.php
 * List all advance requests (admin)
 */

require_once __DIR__ . '/../auth_helper.php';
require_once __DIR__ . '/../helpers.php';

require_role(['admin', 'superadmin']);

$pdo = getPDO();
$stmt = $pdo->query("
    SELECT a.*, u.name as user_name, approver.name as approved_by_name
    FROM advances a
    JOIN users u ON a.user_id = u.id
    LEFT JOIN users approver ON a.approved_by = approver.id
    ORDER BY a.created_at DESC
");
$advances = $stmt->fetchAll();

// Calculate total paid advances (approved)
$stmt = $pdo->query("
    SELECT COALESCE(SUM(amount), 0) as total_paid
    FROM advances
    WHERE status = 'approved'
");
$totalPaidResult = $stmt->fetch();
$totalPaid = $totalPaidResult['total_paid'] ?? 0;

// Get pending count
$pendingCount = 0;
foreach ($advances as $adv) {
    if ($adv['status'] === 'pending') $pendingCount++;
}

$page_title = 'Manage Advances';
include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="mb-4">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3">
            <div>
                <h1 class="gradient-text mb-2 fs-3 fs-md-2">Manage Advance Requests</h1>
                <p class="text-muted mb-0 small">Review and manage staff advance payment requests</p>
            </div>
        </div>
    </div>
    
    <!-- Statistics Cards -->
    <div class="row g-3 g-md-4 mb-4">
        <div class="col-6 col-md-3">
            <div class="stat-card animate-slide-up">
                <div class="stat-icon" style="background: linear-gradient(135deg, #28a745, #20c997);">
                    <i class="bi bi-cash-coin"></i>
                </div>
                <div class="stat-label small">Total Paid</div>
                <div class="stat-value fs-5 fs-md-4">৳<?= number_format($totalPaid, 0) ?></div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card animate-slide-up" style="animation-delay: 0.1s">
                <div class="stat-icon" style="background: linear-gradient(135deg, #ffc107, #ff9800);">
                    <i class="bi bi-clock-history"></i>
                </div>
                <div class="stat-label small">Pending Requests</div>
                <div class="stat-value fs-5 fs-md-4" data-count="<?= $pendingCount ?>"><?= $pendingCount ?></div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card animate-slide-up" style="animation-delay: 0.2s">
                <div class="stat-icon" style="background: linear-gradient(135deg, #007bff, #706fd3);">
                    <i class="bi bi-wallet2"></i>
                </div>
                <div class="stat-label small">Total Requests</div>
                <div class="stat-value fs-5 fs-md-4" data-count="<?= count($advances) ?>"><?= count($advances) ?></div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card animate-slide-up" style="animation-delay: 0.3s">
                <div class="stat-icon" style="background: linear-gradient(135deg, #17a2b8, #138496);">
                    <i class="bi bi-check-circle-fill"></i>
                </div>
                <div class="stat-label small">Approved</div>
                <div class="stat-value fs-5 fs-md-4" data-count="<?= count(array_filter($advances, fn($a) => $a['status'] === 'approved')) ?>"><?= count(array_filter($advances, fn($a) => $a['status'] === 'approved')) ?></div>
            </div>
        </div>
    </div>
    
    <?php if ($pendingCount > 0): ?>
        <div class="alert alert-warning animate-fade-in mb-4">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <strong><?= $pendingCount ?></strong> pending advance request(s) require your attention.
        </div>
    <?php endif; ?>
    
    <div class="card shadow-lg border-0 animate-slide-up" style="animation-delay: 0.4s">
        <div class="card-body p-0">
            <!-- Desktop Table View -->
            <div class="table-responsive d-none d-md-block">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light" style="background: linear-gradient(135deg, #f8f9fa, #e9ecef);">
                        <tr>
                            <th class="ps-4 py-3 fw-semibold">Staff</th>
                            <th class="py-3 fw-semibold">Amount</th>
                            <th class="py-3 fw-semibold">Reason</th>
                            <th class="py-3 fw-semibold">Status</th>
                            <th class="py-3 fw-semibold">Approved By</th>
                            <th class="py-3 fw-semibold">Created</th>
                            <th class="text-end pe-4 py-3 fw-semibold">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($advances)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">
                                    <i class="bi bi-inbox fs-1 d-block mb-2 text-muted"></i>
                                    <p class="mb-0">No advance requests found</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($advances as $advance): ?>
                                <tr class="advance-row <?= $advance['status'] === 'pending' ? 'table-warning' : '' ?>" style="transition: all 0.2s ease;">
                                    <td class="ps-4">
                                        <div class="d-flex align-items-center">
                                            <div class="rounded-circle bg-gradient-primary d-flex align-items-center justify-content-center me-3 flex-shrink-0 shadow-sm" 
                                                 style="width: 45px; height: 45px; background: linear-gradient(135deg, #007bff, #706fd3);">
                                                <span class="text-white fw-bold fs-6"><?= strtoupper(substr($advance['user_name'], 0, 1)) ?></span>
                                            </div>
                                            <strong class="fw-semibold"><?= h($advance['user_name']) ?></strong>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="rounded-circle bg-light d-flex align-items-center justify-content-center me-2" style="width: 32px; height: 32px;">
                                                <i class="bi bi-cash-stack text-success"></i>
                                            </div>
                                            <strong class="text-success fw-semibold">৳<?= number_format($advance['amount'], 2) ?></strong>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="text-truncate d-inline-block" style="max-width: 200px;" title="<?= h($advance['reason']) ?>">
                                            <?= h($advance['reason']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php
                                        $statusConfig = [
                                            'approved' => ['gradient' => 'linear-gradient(135deg, #28a745, #218838)', 'icon' => 'check-circle-fill'],
                                            'rejected' => ['gradient' => 'linear-gradient(135deg, #dc3545, #c82333)', 'icon' => 'x-circle-fill'],
                                            'pending' => ['gradient' => 'linear-gradient(135deg, #ffc107, #e0a800)', 'icon' => 'clock-history']
                                        ];
                                        $statusInfo = $statusConfig[$advance['status']] ?? $statusConfig['pending'];
                                        ?>
                                        <span class="badge px-3 py-2 shadow-sm" style="background: <?= $statusInfo['gradient'] ?>; border: none;">
                                            <i class="bi bi-<?= $statusInfo['icon'] ?> me-1"></i>
                                            <?= ucfirst($advance['status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($advance['approved_by_name']): ?>
                                            <div class="d-flex align-items-center">
                                                <div class="rounded-circle bg-light d-flex align-items-center justify-content-center me-2" style="width: 32px; height: 32px;">
                                                    <i class="bi bi-person-check text-primary"></i>
                                                </div>
                                                <span class="text-muted"><?= h($advance['approved_by_name']) ?></span>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="rounded-circle bg-light d-flex align-items-center justify-content-center me-2" style="width: 32px; height: 32px;">
                                                <i class="bi bi-calendar3 text-muted"></i>
                                            </div>
                                            <span class="text-muted"><?= format_datetime($advance['created_at']) ?></span>
                                        </div>
                                    </td>
                                    <td class="text-end pe-4">
                                        <div class="btn-group shadow-sm" role="group">
                                            <?php if ($advance['status'] === 'pending'): ?>
                                                <a href="<?= BASE_URL ?>/advances/view.php?id=<?= $advance['id'] ?>" 
                                                   class="btn btn-sm btn-outline-warning border-0" 
                                                   title="Review"
                                                   style="transition: all 0.2s ease;">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                            <?php else: ?>
                                                <a href="<?= BASE_URL ?>/advances/view.php?id=<?= $advance['id'] ?>" 
                                                   class="btn btn-sm btn-outline-secondary border-0" 
                                                   title="View"
                                                   style="transition: all 0.2s ease;">
                                                    <i class="bi bi-eye"></i>
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
                <?php if (empty($advances)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                        <p class="mb-0">No advance requests found</p>
                    </div>
                <?php else: ?>
                    <div class="p-2 p-md-3">
                        <?php foreach ($advances as $advance): ?>
                            <?php
                            $statusConfig = [
                                'approved' => ['gradient' => 'linear-gradient(135deg, #28a745, #218838)', 'icon' => 'check-circle-fill'],
                                'rejected' => ['gradient' => 'linear-gradient(135deg, #dc3545, #c82333)', 'icon' => 'x-circle-fill'],
                                'pending' => ['gradient' => 'linear-gradient(135deg, #ffc107, #e0a800)', 'icon' => 'clock-history']
                            ];
                            $statusInfo = $statusConfig[$advance['status']] ?? $statusConfig['pending'];
                            ?>
                            <div class="card mb-3 border-0 advance-mobile-card" style="border-radius: 16px; overflow: hidden;">
                                <!-- Card Header -->
                                <div class="card-header border-0 p-3" style="background: linear-gradient(135deg, rgba(0, 123, 255, 0.1), rgba(112, 111, 211, 0.1));">
                                    <div class="d-flex align-items-center">
                                        <div class="rounded-circle d-flex align-items-center justify-content-center me-3 flex-shrink-0 shadow" 
                                             style="width: 56px; height: 56px; background: linear-gradient(135deg, #007bff, #706fd3);">
                                            <span class="text-white fw-bold" style="font-size: 1.5rem;"><?= strtoupper(substr($advance['user_name'], 0, 1)) ?></span>
                                        </div>
                                        <div class="flex-grow-1 min-w-0">
                                            <h6 class="mb-1 fw-bold" style="font-size: 1rem; color: #212529;"><?= h($advance['user_name']) ?></h6>
                                            <span class="badge px-3 py-2" style="background: <?= $statusInfo['gradient'] ?>; border: none; font-size: 0.75rem; font-weight: 600;">
                                                <i class="bi bi-<?= $statusInfo['icon'] ?> me-1"></i>
                                                <?= ucfirst($advance['status']) ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Card Body -->
                                <div class="card-body p-3">
                                    <!-- Info Cards -->
                                    <div class="row g-2 mb-3">
                                        <div class="col-12">
                                            <div class="d-flex align-items-center justify-content-between p-3 rounded-3" style="background: linear-gradient(135deg, rgba(40, 167, 69, 0.08), rgba(32, 201, 151, 0.08)); border: 1px solid rgba(40, 167, 69, 0.2);">
                                                <div class="d-flex align-items-center">
                                                    <div class="rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px; background: linear-gradient(135deg, #28a745, #20c997);">
                                                        <i class="bi bi-cash-stack text-white"></i>
                                                    </div>
                                                    <div>
                                                        <small class="text-muted d-block mb-0" style="font-size: 0.7rem; font-weight: 500;">Amount</small>
                                                        <strong class="text-success d-block" style="font-size: 1rem;">৳<?= number_format($advance['amount'], 2) ?></strong>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-12">
                                            <div class="d-flex align-items-center justify-content-between p-3 rounded-3" style="background: rgba(248, 249, 250, 0.8); border: 1px solid rgba(0, 0, 0, 0.08);">
                                                <div class="d-flex align-items-center">
                                                    <div class="rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px; background: rgba(108, 117, 125, 0.1);">
                                                        <i class="bi bi-chat-left-text text-muted"></i>
                                                    </div>
                                                    <div class="flex-grow-1 min-w-0">
                                                        <small class="text-muted d-block mb-0" style="font-size: 0.7rem; font-weight: 500;">Reason</small>
                                                        <span class="text-dark d-block text-truncate" style="font-size: 0.85rem; font-weight: 500;" title="<?= h($advance['reason']) ?>"><?= h($advance['reason']) ?></span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <?php if ($advance['approved_by_name']): ?>
                                        <div class="col-12">
                                            <div class="d-flex align-items-center justify-content-between p-3 rounded-3" style="background: rgba(248, 249, 250, 0.8); border: 1px solid rgba(0, 0, 0, 0.08);">
                                                <div class="d-flex align-items-center">
                                                    <div class="rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px; background: rgba(0, 123, 255, 0.1);">
                                                        <i class="bi bi-person-check text-primary"></i>
                                                    </div>
                                                    <div>
                                                        <small class="text-muted d-block mb-0" style="font-size: 0.7rem; font-weight: 500;">Approved By</small>
                                                        <span class="text-dark d-block" style="font-size: 0.9rem; font-weight: 500;"><?= h($advance['approved_by_name']) ?></span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                        <div class="col-12">
                                            <div class="d-flex align-items-center justify-content-between p-3 rounded-3" style="background: rgba(248, 249, 250, 0.8); border: 1px solid rgba(0, 0, 0, 0.08);">
                                                <div class="d-flex align-items-center">
                                                    <div class="rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px; background: rgba(108, 117, 125, 0.1);">
                                                        <i class="bi bi-calendar3 text-muted"></i>
                                                    </div>
                                                    <div>
                                                        <small class="text-muted d-block mb-0" style="font-size: 0.7rem; font-weight: 500;">Created</small>
                                                        <span class="text-dark d-block" style="font-size: 0.9rem; font-weight: 500;"><?= format_datetime($advance['created_at']) ?></span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Action Buttons -->
                                    <div class="d-grid gap-2">
                                        <?php if ($advance['status'] === 'pending'): ?>
                                            <a href="<?= BASE_URL ?>/advances/view.php?id=<?= $advance['id'] ?>" 
                                               class="btn btn-warning btn-sm w-100 shadow-sm" 
                                               style="border-radius: 8px; font-weight: 600;">
                                                <i class="bi bi-eye me-2"></i>Review Request
                                            </a>
                                        <?php else: ?>
                                            <a href="<?= BASE_URL ?>/advances/view.php?id=<?= $advance['id'] ?>" 
                                               class="btn btn-outline-secondary btn-sm w-100 shadow-sm" 
                                               style="border-radius: 8px; font-weight: 600;">
                                                <i class="bi bi-eye me-2"></i>View Details
                                            </a>
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
.advance-row:hover {
    background-color: rgba(0, 123, 255, 0.03) !important;
    transform: translateX(4px);
}

.advance-row:hover .btn-outline-warning,
.advance-row:hover .btn-outline-secondary {
    transform: scale(1.1);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
}

@media (max-width: 991px) {
    .advance-mobile-card {
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        border: 1px solid rgba(0, 0, 0, 0.05) !important;
    }
    
    .advance-mobile-card:hover,
    .advance-mobile-card:active {
        transform: translateY(-2px);
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15) !important;
    }
    
    .advance-mobile-card .card-header::after {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 3px;
        background: linear-gradient(90deg, #007bff, #706fd3);
    }
    
    .advance-mobile-card .btn {
        transition: all 0.2s ease;
    }
    
    .advance-mobile-card .btn:hover {
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

<?php include __DIR__ . '/../includes/footer.php'; ?>

