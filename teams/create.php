<?php
/**
 * teams/create.php
 * Create team
 */

require_once __DIR__ . '/../auth_helper.php';
require_once __DIR__ . '/../helpers.php';

require_role(['superadmin', 'admin']);

$pdo = getPDO();
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
                
                $stmt = $pdo->prepare("INSERT INTO teams (name, created_at) VALUES (?, UTC_TIMESTAMP())");
                $stmt->execute([$name]);
                $team_id = $pdo->lastInsertId();
                
                // Add members
                if (!empty($member_ids)) {
                    $stmt = $pdo->prepare("INSERT INTO team_members (team_id, user_id, created_at) VALUES (?, ?, UTC_TIMESTAMP())");
                    foreach ($member_ids as $user_id) {
                        $stmt->execute([$team_id, intval($user_id)]);
                    }
                }
                
                $pdo->commit();
                log_audit(current_user()['id'], 'create', 'team', $team_id, "Created team: $name");
                
                header('Location: ' . BASE_URL . '/teams/index.php?success=' . urlencode('Team created successfully'));
                exit;
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = 'Failed to create team.';
            }
        }
    }
}

// Get staff users
$stmt = $pdo->query("SELECT id, name FROM users WHERE role = 'staff' AND deleted_at IS NULL ORDER BY name");
$staff = $stmt->fetchAll();

$page_title = 'Create Team';
include __DIR__ . '/../includes/header.php';
?>

<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h4>Create Team</h4>
                </div>
                <div class="card-body">
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?= h($error) ?></div>
                    <?php endif; ?>
                    
                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                        
                        <div class="mb-3">
                            <label for="name" class="form-label">Team Name *</label>
                            <input type="text" class="form-control" id="name" name="name" required value="<?= h($_POST['name'] ?? '') ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Team Members</label>
                            <?php foreach ($staff as $s): ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="member_ids[]" 
                                           value="<?= $s['id'] ?>" id="member_<?= $s['id'] ?>">
                                    <label class="form-check-label" for="member_<?= $s['id'] ?>">
                                        <?= h($s['name']) ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">Create Team</button>
                            <a href="<?= BASE_URL ?>/teams/index.php" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

