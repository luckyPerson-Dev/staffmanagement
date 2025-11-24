<?php
/**
 * teams/my_team.php
 * View staff's own team details and progress
 */

require_once __DIR__ . '/../auth_helper.php';
require_once __DIR__ . '/../helpers.php';

require_login();

$current_user = current_user();
$pdo = getPDO();

// Get the team ID for the current staff user
$stmt = $pdo->prepare("
    SELECT tm.team_id, t.name as team_name
    FROM team_members tm
    JOIN teams t ON tm.team_id = t.id
    WHERE tm.user_id = ? AND t.deleted_at IS NULL
    LIMIT 1
");
$stmt->execute([$current_user['id']]);
$team_data = $stmt->fetch();

if (!$team_data) {
    $page_title = 'My Team';
    include __DIR__ . '/../includes/header.php';
    ?>
    <div class="container-fluid py-4">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow-lg border-0">
                    <div class="card-body text-center py-5">
                        <i class="bi bi-people fs-1 text-muted d-block mb-3"></i>
                        <h4 class="mb-3">No Team Assigned</h4>
                        <p class="text-muted mb-4">You are not currently assigned to any team. Please contact your administrator.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
    include __DIR__ . '/../includes/footer.php';
    exit;
}

$team_id = $team_data['team_id'];
$team_name = $team_data['team_name'];

// Get team members
$stmt = $pdo->prepare("
    SELECT u.id, u.name, u.email, u.monthly_salary
    FROM team_members tm
    JOIN users u ON tm.user_id = u.id
    WHERE tm.team_id = ? AND u.deleted_at IS NULL
    ORDER BY 
        CASE WHEN u.id = ? THEN 0 ELSE 1 END,
        u.name
");
$stmt->execute([$team_id, $current_user['id']]);
$members = $stmt->fetchAll();

// Get current month progress for each member
$current_month = (int)date('n'); // 1-12
$current_year = (int)date('Y');

$member_progress = [];
$my_progress = 0;

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
    $progress_value = max(0, min(100, $progress_value));
    $member_progress[$member['id']] = $progress_value;
    
    // Store my progress separately
    if ($member['id'] == $current_user['id']) {
        $my_progress = $progress_value;
    }
}

// Calculate team average
$team_avg = count($member_progress) > 0 ? array_sum($member_progress) / count($member_progress) : 0;
// Clamp team average between 0 and 100
$team_avg = max(0, min(100, $team_avg));

$page_title = 'My Team';
include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="mb-4">
        <h1 class="gradient-text mb-2 fs-3 fs-md-2">My Team: <?= h($team_name) ?></h1>
        <p class="text-muted mb-0 small">Track your team's progress and performance</p>
    </div>
    
    <!-- My Progress Card -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card shadow-lg border-0">
                <div class="card-header bg-white border-bottom">
                    <h5 class="mb-0">
                        <i class="bi bi-person-check me-2 text-primary"></i>My Progress - <?= date('F Y') ?>
                    </h5>
                </div>
                <div class="card-body">
                    <div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center mb-3 gap-2">
                        <div>
                            <h6 class="text-muted mb-1 small">Your Current Progress</h6>
                            <h2 class="mb-0 text-<?= $my_progress >= 80 ? 'success' : ($my_progress >= 60 ? 'warning' : 'danger') ?> fs-3 fs-md-2">
                                <?= number_format($my_progress, 2) ?>%
                            </h2>
                        </div>
                        <div class="text-start text-sm-end">
                            <span class="badge bg-<?= $my_progress >= 80 ? 'success' : ($my_progress >= 60 ? 'warning' : 'danger') ?> px-3 py-2 fs-6">
                                <?php if ($my_progress >= 80): ?>
                                    <i class="bi bi-trophy me-1"></i>Excellent
                                <?php elseif ($my_progress >= 60): ?>
                                    <i class="bi bi-check-circle me-1"></i>Good
                                <?php else: ?>
                                    <i class="bi bi-exclamation-triangle me-1"></i>Needs Improvement
                                <?php endif; ?>
                            </span>
                        </div>
                    </div>
                    <div class="progress" style="height: 40px; border-radius: 8px; overflow: hidden; background-color: #e9ecef;">
                        <div class="progress-bar progress-bar-striped <?= $my_progress >= 80 ? 'bg-success' : ($my_progress >= 60 ? 'bg-warning' : 'bg-danger') ?>" 
                             role="progressbar" 
                             aria-valuenow="<?= $my_progress ?>" 
                             aria-valuemin="0" 
                             aria-valuemax="100"
                             data-progress="<?= $my_progress ?>"
                             style="width: <?= number_format($my_progress, 2) ?>%; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 16px; min-width: 0;">
                            <span><?= number_format($my_progress, 1) ?>%</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Team Overview -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card shadow-lg border-0">
                <div class="card-header bg-white border-bottom">
                    <h5 class="mb-0">
                        <i class="bi bi-graph-up me-2 text-primary"></i>Team Progress - <?= date('F Y') ?>
                    </h5>
                </div>
                <div class="card-body">
                    <div class="mb-4">
                        <div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center mb-2 gap-2">
                            <h6 class="mb-0 small">
                                <i class="bi bi-people-fill me-2 text-primary"></i>Team Average
                            </h6>
                            <strong class="fs-6 fs-md-5 text-<?= $team_avg >= 80 ? 'success' : ($team_avg >= 60 ? 'warning' : 'danger') ?>">
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
                        <i class="bi bi-person-lines-fill me-2 text-primary"></i>Team Members Progress
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
                            $is_me = $member['id'] == $current_user['id'];
                            ?>
                            <div class="mb-3 p-3 border rounded <?= $is_me ? 'border-primary border-2' : '' ?>" 
                                 style="background: <?= $is_me ? '#f0f7ff' : '#f8f9fa' ?>;">
                                <div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center mb-2 gap-2">
                                    <div class="d-flex align-items-center w-100 w-sm-auto">
                                        <div class="rounded-circle bg-gradient-primary d-flex align-items-center justify-content-center me-2 flex-shrink-0" 
                                             style="width: 40px; height: 40px; background: linear-gradient(135deg, #007bff, #706fd3);">
                                            <span class="text-white fw-bold"><?= strtoupper(substr($member['name'], 0, 1)) ?></span>
                                        </div>
                                        <div class="flex-grow-1 min-w-0">
                                            <strong class="d-block text-truncate"><?= h($member['name']) ?>
                                                <?php if ($is_me): ?>
                                                    <span class="badge bg-primary ms-2">You</span>
                                                <?php endif; ?>
                                            </strong>
                                            <small class="text-muted d-none d-sm-block">
                                                <i class="bi bi-envelope me-1"></i><?= h($member['email']) ?>
                                            </small>
                                        </div>
                                    </div>
                                    <strong class="text-<?= $progress_color ?> text-nowrap">
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

