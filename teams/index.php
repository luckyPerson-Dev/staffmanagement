<?php
/**
 * teams/index.php
 * List teams
 */

require_once __DIR__ . '/../auth_helper.php';
require_once __DIR__ . '/../helpers.php';

require_role(['superadmin', 'admin']);

$pdo = getPDO();
$stmt = $pdo->query("
    SELECT t.*, COUNT(tm.id) as member_count
    FROM teams t
    LEFT JOIN team_members tm ON t.id = tm.team_id
    WHERE t.deleted_at IS NULL
    GROUP BY t.id
    ORDER BY t.name
");
$teams = $stmt->fetchAll();

// Get statistics
$stmt = $pdo->query("SELECT COUNT(*) as total FROM teams WHERE deleted_at IS NULL");
$total_teams = $stmt->fetch()['total'];

$stmt = $pdo->query("
    SELECT COUNT(DISTINCT tm.user_id) as total 
    FROM team_members tm 
    JOIN teams t ON tm.team_id = t.id 
    WHERE t.deleted_at IS NULL
");
$total_members = $stmt->fetch()['total'] ?? 0;

$page_title = 'Manage Teams';
include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="mb-4">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3">
            <div>
                <h1 class="gradient-text mb-2 fs-3 fs-md-2">Manage Teams</h1>
                <p class="text-muted mb-0 small">Create and manage teams for organizing staff members</p>
            </div>
            <a href="<?= BASE_URL ?>/teams/create.php" class="btn btn-primary btn-lg w-100 w-md-auto shadow-sm">
                <i class="bi bi-diagram-3 me-2"></i>Create Team
            </a>
        </div>
    </div>
    
    <!-- Statistics Cards -->
    <div class="row g-3 g-md-4 mb-4">
        <div class="col-6 col-md-3">
            <div class="stat-card animate-slide-up">
                <div class="stat-icon" style="background: linear-gradient(135deg, #007bff, #706fd3);">
                    <i class="bi bi-diagram-3"></i>
                </div>
                <div class="stat-label small">Total Teams</div>
                <div class="stat-value fs-5 fs-md-4" data-count="<?= $total_teams ?>"><?= $total_teams ?></div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card animate-slide-up" style="animation-delay: 0.1s">
                <div class="stat-icon" style="background: linear-gradient(135deg, #17a2b8, #138496);">
                    <i class="bi bi-people-fill"></i>
                </div>
                <div class="stat-label small">Total Members</div>
                <div class="stat-value fs-5 fs-md-4" data-count="<?= $total_members ?>"><?= $total_members ?></div>
            </div>
        </div>
    </div>
    
    <div class="card shadow-lg border-0 animate-slide-up" style="animation-delay: 0.2s">
        <div class="card-body p-0">
            <!-- Desktop Table View -->
            <div class="table-responsive d-none d-md-block">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light" style="background: linear-gradient(135deg, #f8f9fa, #e9ecef);">
                        <tr>
                            <th class="ps-4 py-3 fw-semibold">Name</th>
                            <th class="py-3 fw-semibold">Members</th>
                            <th class="py-3 fw-semibold">Created</th>
                            <th class="text-end pe-4 py-3 fw-semibold">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($teams)): ?>
                            <tr>
                                <td colspan="4" class="text-center py-5 text-muted">
                                    <i class="bi bi-inbox fs-1 d-block mb-2 text-muted"></i>
                                    <p class="mb-0">No teams found</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($teams as $team): ?>
                                <tr class="team-row" style="transition: all 0.2s ease;">
                                    <td class="ps-4">
                                        <div class="d-flex align-items-center">
                                            <div class="rounded-circle bg-gradient-primary d-flex align-items-center justify-content-center me-3 flex-shrink-0 shadow-sm" 
                                                 style="width: 45px; height: 45px; background: linear-gradient(135deg, #007bff, #706fd3);">
                                                <span class="text-white fw-bold fs-6"><?= strtoupper(substr($team['name'], 0, 1)) ?></span>
                                            </div>
                                            <strong class="fw-semibold"><?= h($team['name']) ?></strong>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge px-3 py-2 shadow-sm" style="background: linear-gradient(135deg, #17a2b8, #138496); border: none;">
                                            <i class="bi bi-people me-1"></i>
                                            <?= $team['member_count'] ?> member<?= $team['member_count'] != 1 ? 's' : '' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="rounded-circle bg-light d-flex align-items-center justify-content-center me-2" style="width: 32px; height: 32px;">
                                                <i class="bi bi-calendar3 text-muted"></i>
                                            </div>
                                            <span class="text-muted"><?= format_date($team['created_at']) ?></span>
                                        </div>
                                    </td>
                                    <td class="text-end pe-4">
                                        <div class="btn-group shadow-sm" role="group">
                                            <a href="<?= BASE_URL ?>/teams/view.php?id=<?= $team['id'] ?>" 
                                               class="btn btn-sm btn-outline-primary border-0" 
                                               title="View"
                                               style="transition: all 0.2s ease;">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <a href="<?= BASE_URL ?>/teams/edit.php?id=<?= $team['id'] ?>" 
                                               class="btn btn-sm btn-outline-secondary border-0" 
                                               title="Edit"
                                               style="transition: all 0.2s ease;">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <a href="<?= BASE_URL ?>/teams/delete.php?id=<?= $team['id'] ?>" 
                                               class="btn btn-sm btn-outline-danger border-0 delete-link" 
                                               data-confirm="Are you sure you want to delete this team? This action cannot be undone."
                                               data-confirm-title="Delete Team"
                                               data-confirm-type="danger"
                                               title="Delete"
                                               style="transition: all 0.2s ease;">
                                                <i class="bi bi-trash"></i>
                                            </a>
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
                <?php if (empty($teams)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                        <p class="mb-0">No teams found</p>
                    </div>
                <?php else: ?>
                    <div class="p-2 p-md-3">
                        <?php foreach ($teams as $team): ?>
                            <div class="card mb-3 border-0 team-mobile-card" style="border-radius: 16px; overflow: hidden;">
                                <!-- Card Header -->
                                <div class="card-header border-0 p-3" style="background: linear-gradient(135deg, rgba(0, 123, 255, 0.1), rgba(112, 111, 211, 0.1));">
                                    <div class="d-flex align-items-center">
                                        <div class="rounded-circle d-flex align-items-center justify-content-center me-3 flex-shrink-0 shadow" 
                                             style="width: 56px; height: 56px; background: linear-gradient(135deg, #007bff, #706fd3);">
                                            <span class="text-white fw-bold" style="font-size: 1.5rem;"><?= strtoupper(substr($team['name'], 0, 1)) ?></span>
                                        </div>
                                        <div class="flex-grow-1 min-w-0">
                                            <h6 class="mb-1 fw-bold" style="font-size: 1rem; color: #212529;"><?= h($team['name']) ?></h6>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Card Body -->
                                <div class="card-body p-3">
                                    <!-- Info Cards -->
                                    <div class="row g-2 mb-3">
                                        <div class="col-6">
                                            <div class="d-flex align-items-center justify-content-between p-3 rounded-3" style="background: linear-gradient(135deg, rgba(23, 162, 184, 0.08), rgba(19, 132, 150, 0.08)); border: 1px solid rgba(23, 162, 184, 0.2);">
                                                <div class="d-flex align-items-center">
                                                    <div class="rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 36px; height: 36px; background: linear-gradient(135deg, #17a2b8, #138496);">
                                                        <i class="bi bi-people text-white" style="font-size: 0.9rem;"></i>
                                                    </div>
                                                    <div>
                                                        <small class="text-muted d-block mb-0" style="font-size: 0.7rem; font-weight: 500;">Members</small>
                                                        <strong class="text-info d-block" style="font-size: 0.95rem;"><?= $team['member_count'] ?></strong>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="d-flex align-items-center justify-content-between p-3 rounded-3" style="background: rgba(248, 249, 250, 0.8); border: 1px solid rgba(0, 0, 0, 0.08);">
                                                <div class="d-flex align-items-center">
                                                    <div class="rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 36px; height: 36px; background: rgba(108, 117, 125, 0.1);">
                                                        <i class="bi bi-calendar3 text-muted"></i>
                                                    </div>
                                                    <div>
                                                        <small class="text-muted d-block mb-0" style="font-size: 0.7rem; font-weight: 500;">Created</small>
                                                        <span class="text-dark d-block" style="font-size: 0.85rem; font-weight: 500;"><?= format_date($team['created_at']) ?></span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Action Buttons -->
                                    <div class="d-grid gap-2">
                                        <div class="btn-group w-100 shadow-sm" role="group">
                                            <a href="<?= BASE_URL ?>/teams/view.php?id=<?= $team['id'] ?>" 
                                               class="btn btn-sm btn-primary flex-fill" 
                                               style="border-radius: 8px 0 0 8px; font-weight: 600;">
                                                <i class="bi bi-eye me-1"></i>View
                                            </a>
                                            <a href="<?= BASE_URL ?>/teams/edit.php?id=<?= $team['id'] ?>" 
                                               class="btn btn-sm btn-secondary flex-fill" 
                                               style="border-radius: 0; font-weight: 600;">
                                                <i class="bi bi-pencil me-1"></i>Edit
                                            </a>
                                            <a href="<?= BASE_URL ?>/teams/delete.php?id=<?= $team['id'] ?>" 
                                               class="btn btn-sm btn-danger flex-fill"
                                               class="delete-link"
                                               data-confirm="Are you sure you want to delete this team? This action cannot be undone."
                                               data-confirm-title="Delete Team"
                                               data-confirm-type="danger"
                                               style="border-radius: 0 8px 8px 0; font-weight: 600;">
                                                <i class="bi bi-trash me-1"></i>Delete
                                            </a>
                                        </div>
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
.team-row:hover {
    background-color: rgba(0, 123, 255, 0.03) !important;
    transform: translateX(4px);
}

.team-row:hover .btn-outline-primary,
.team-row:hover .btn-outline-secondary,
.team-row:hover .btn-outline-danger {
    transform: scale(1.1);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
}

@media (max-width: 991px) {
    .team-mobile-card {
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        border: 1px solid rgba(0, 0, 0, 0.05) !important;
    }
    
    .team-mobile-card:hover,
    .team-mobile-card:active {
        transform: translateY(-2px);
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15) !important;
    }
    
    .team-mobile-card .card-header::after {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 3px;
        background: linear-gradient(90deg, #007bff, #706fd3);
    }
    
    .team-mobile-card .btn {
        transition: all 0.2s ease;
    }
    
    .team-mobile-card .btn:hover {
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

