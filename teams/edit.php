<?php
/**
 * teams/edit.php
 * Edit team
 */

require_once __DIR__ . '/../auth_helper.php';
require_once __DIR__ . '/../helpers.php';

require_role(['superadmin', 'admin']);

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

// Get current members
$stmt = $pdo->prepare("SELECT user_id FROM team_members WHERE team_id = ?");
$stmt->execute([$id]);
$current_members = $stmt->fetchAll(PDO::FETCH_COLUMN);

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $member_ids = $_POST['member_ids'] ?? [];
        
        if (empty($name)) {
            $error = 'Team name is required.';
        } else {
            try {
                $pdo->beginTransaction();
                
                // Update team name
                $stmt = $pdo->prepare("UPDATE teams SET name = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?");
                $stmt->execute([$name, $id]);
                
                // Remove all current members
                $stmt = $pdo->prepare("DELETE FROM team_members WHERE team_id = ?");
                $stmt->execute([$id]);
                
                // Add new members
                if (!empty($member_ids)) {
                    $stmt = $pdo->prepare("INSERT INTO team_members (team_id, user_id, created_at) VALUES (?, ?, UTC_TIMESTAMP())");
                    foreach ($member_ids as $user_id) {
                        $stmt->execute([$id, intval($user_id)]);
                    }
                }
                
                $pdo->commit();
                log_audit(current_user()['id'], 'update', 'team', $id, "Updated team: $name");
                
                header('Location: ' . BASE_URL . '/teams/index.php?success=' . urlencode('Team updated successfully'));
                exit;
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = 'Failed to update team.';
            }
        }
    }
}

// Get staff users
$stmt = $pdo->query("SELECT id, name FROM users WHERE role = 'staff' AND deleted_at IS NULL ORDER BY name");
$staff = $stmt->fetchAll();

$page_title = 'Edit Team';
include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="gradient-text mb-2">Edit Team</h1>
            <p class="text-muted mb-0">Update team name and manage team members</p>
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
            
            <form method="POST" action="" id="editTeamForm">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                
                <div class="mb-4">
                    <label for="name" class="form-label fw-semibold">
                        <i class="bi bi-diagram-3 me-1"></i>Team Name <span class="text-danger">*</span>
                    </label>
                    <input type="text" 
                           class="form-control form-control-lg" 
                           id="name" 
                           name="name" 
                           required 
                           value="<?= h($team['name']) ?>"
                           placeholder="Enter team name">
                </div>
                
                <div class="mb-4">
                    <label class="form-label fw-semibold mb-3">
                        <i class="bi bi-people me-1"></i>Team Members
                    </label>
                    <div class="border rounded p-3" style="max-height: 400px; overflow-y: auto;">
                        <?php foreach ($staff as $s): ?>
                            <div class="form-check mb-2 p-2 rounded hover-bg-light" style="transition: background 0.2s;">
                                <input class="form-check-input" 
                                       type="checkbox" 
                                       name="member_ids[]" 
                                       value="<?= $s['id'] ?>" 
                                       id="member_<?= $s['id'] ?>"
                                       <?= in_array($s['id'], $current_members) ? 'checked' : '' ?>>
                                <label class="form-check-label w-100" for="member_<?= $s['id'] ?>" style="cursor: pointer;">
                                    <div class="d-flex align-items-center">
                                        <div class="rounded-circle bg-primary d-flex align-items-center justify-content-center me-2" 
                                             style="width: 32px; height: 32px;">
                                            <span class="text-white fw-bold"><?= strtoupper(substr($s['name'], 0, 1)) ?></span>
                                        </div>
                                        <span><?= h($s['name']) ?></span>
                                    </div>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <small class="text-muted">
                        <i class="bi bi-info-circle me-1"></i>Select team members from the list above
                    </small>
                </div>
                
                <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                    <a href="<?= BASE_URL ?>/teams/index.php" class="btn btn-secondary btn-lg">
                        <i class="bi bi-x-circle me-1"></i>Cancel
                    </a>
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="bi bi-check-circle me-1"></i>Update Team
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.hover-bg-light:hover {
    background-color: #f8f9fa !important;
}
</style>

<script>
document.getElementById('editTeamForm').addEventListener('submit', function(e) {
    const submitBtn = this.querySelector('button[type="submit"]');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Updating...';
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>

