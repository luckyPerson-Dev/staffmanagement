<?php
/**
 * teams/view.php
 * View team details and progress
 */

require_once __DIR__ . '/../auth_helper.php';
require_once __DIR__ . '/../helpers.php';

require_login();

$id = intval($_GET['id'] ?? 0);
if (!$id) {
    header('Location: ' . BASE_URL . '/teams/index.php');
    exit;
}

$pdo = getPDO();
$stmt = $pdo->prepare("SELECT * FROM teams WHERE id = ? AND deleted_at IS NULL");
$stmt->execute([$id]);
$team = $stmt->fetch();

if (!$team) {
    header('Location: ' . BASE_URL . '/teams/index.php');
    exit;
}

// Get team members
$stmt = $pdo->prepare("
    SELECT u.id, u.name, u.monthly_salary
    FROM team_members tm
    JOIN users u ON tm.user_id = u.id
    WHERE tm.team_id = ? AND u.deleted_at IS NULL
    ORDER BY u.name
");
$stmt->execute([$id]);
$members = $stmt->fetchAll();

// Get current month progress for each member
$current_month = (int)date('n'); // 1-12
$current_year = (int)date('Y');

$member_progress = [];
foreach ($members as $member) {
    $stmt = $pdo->prepare("
        SELECT AVG(progress_percent) as avg_progress
        FROM daily_progress
        WHERE user_id = ? AND YEAR(date) = ? AND MONTH(date) = ?
    ");
    $stmt->execute([$member['id'], $current_year, $current_month]);
    $progress = $stmt->fetch();
    $progress_value = floatval($progress['avg_progress'] ?? 0);
    // Clamp between 0 and 100
    $member_progress[$member['id']] = max(0, min(100, $progress_value));
}

// Calculate team average
$team_avg = count($member_progress) > 0 ? array_sum($member_progress) / count($member_progress) : 0;
// Clamp team average between 0 and 100
$team_avg = max(0, min(100, $team_avg));

$page_title = 'Team Details';
include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="gradient-text mb-2"><?= h($team['name']) ?></h1>
            <p class="text-muted mb-0">Team progress and member performance overview</p>
        </div>
        <a href="<?= BASE_URL ?>/teams/index.php" class="btn btn-secondary btn-lg">
            <i class="bi bi-arrow-left me-2"></i>Back to Teams
        </a>
    </div>
    
    <div class="row">
        <div class="col-md-12">
            <div class="card shadow-lg border-0">
                <div class="card-header bg-white border-bottom">
                    <h5 class="mb-0">
                        <i class="bi bi-graph-up me-2 text-primary"></i>Team Progress - <?= date('F Y') ?>
                    </h5>
                </div>
                <div class="card-body">
                    <div class="mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="mb-0">
                                <i class="bi bi-people-fill me-2 text-primary"></i>Team Average
                            </h6>
                            <strong class="fs-5 text-<?= $team_avg >= 80 ? 'success' : ($team_avg >= 60 ? 'warning' : 'danger') ?>">
                                <?= number_format($team_avg, 2) ?>%
                            </strong>
                        </div>
                        <div class="progress" style="height: 35px; border-radius: 8px; overflow: hidden; background-color: #e9ecef;">
                            <div class="progress-bar progress-bar-striped <?= $team_avg >= 80 ? 'bg-success' : ($team_avg >= 60 ? 'bg-warning' : 'bg-danger') ?>" 
                                 role="progressbar" 
                                 aria-valuenow="<?= $team_avg ?>" 
                                 aria-valuemin="0" 
                                 aria-valuemax="100"
                                 data-progress="<?= $team_avg ?>"
                                 style="width: <?= number_format($team_avg, 2) ?>%; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 14px; min-width: 0;">
                                <span><?= number_format($team_avg, 1) ?>%</span>
                            </div>
                        </div>
                    </div>
                    
                    <hr class="my-4">
                    
                    <h6 class="mb-3">
                        <i class="bi bi-person-lines-fill me-2 text-primary"></i>Member Progress
                    </h6>
                    <?php if (empty($members)): ?>
                        <div class="text-center py-4 text-muted">
                            <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                            No team members found
                        </div>
                    <?php else: ?>
                        <?php foreach ($members as $member): ?>
                            <?php 
                            $progress = $member_progress[$member['id']] ?? 0;
                            // Ensure progress is clamped between 0-100
                            $progress = max(0, min(100, $progress));
                            $progress_color = $progress >= 80 ? 'success' : ($progress >= 60 ? 'warning' : 'danger');
                            ?>
                            <div class="mb-3 p-3 border rounded" style="background: #f8f9fa;">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <div class="d-flex align-items-center">
                                        <div class="rounded-circle bg-gradient-primary d-flex align-items-center justify-content-center me-2" 
                                             style="width: 32px; height: 32px; background: linear-gradient(135deg, #007bff, #706fd3);">
                                            <span class="text-white fw-bold small"><?= strtoupper(substr($member['name'], 0, 1)) ?></span>
                                        </div>
                                        <span class="fw-semibold"><?= h($member['name']) ?></span>
                                    </div>
                                    <strong class="text-<?= $progress_color ?>">
                                        <?= number_format($progress, 2) ?>%
                                    </strong>
                                </div>
                                <div class="progress" style="height: 24px; border-radius: 6px; overflow: hidden; background-color: #e9ecef;">
                                    <div class="progress-bar bg-<?= $progress_color ?>" 
                                         role="progressbar" 
                                         aria-valuenow="<?= $progress ?>" 
                                         aria-valuemin="0" 
                                         aria-valuemax="100"
                                         data-progress="<?= $progress ?>"
                                         style="width: <?= number_format($progress, 2) ?>%; transition: width 0.8s ease;">
                                        <?php if ($progress >= 5): ?>
                                            <span class="small fw-bold text-white"><?= number_format($progress, 1) ?>%</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    function animateProgressBars() {
        const progressBars = document.querySelectorAll('.progress-bar[data-progress]');
        
        progressBars.forEach(function(bar) {
            const targetWidth = parseFloat(bar.getAttribute('data-progress')) || 0;
            
            // Ensure minimum width for visibility (clamp between 0-100)
            const finalWidth = Math.max(0, Math.min(100, targetWidth));
            
            // Get current width (from inline style or computed)
            const currentWidth = parseFloat(bar.style.width) || 0;
            
            // Set initial width to 0 for animation
            bar.style.width = '0%';
            
            // Force reflow to ensure initial state is rendered
            void bar.offsetHeight;
            
            // Animate to target width after a short delay
            requestAnimationFrame(function() {
                setTimeout(function() {
                    bar.style.width = finalWidth + '%';
                }, 200);
            });
        });
    }
    
    // Run when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', animateProgressBars);
    } else {
        // DOM is already ready
        animateProgressBars();
    }
})();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>

