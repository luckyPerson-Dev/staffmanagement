<?php
/**
 * teams/delete.php
 * Soft delete team
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
$stmt = $pdo->prepare("SELECT name FROM teams WHERE id = ? AND deleted_at IS NULL");
$stmt->execute([$id]);
$team = $stmt->fetch();

if ($team) {
    $stmt = $pdo->prepare("UPDATE teams SET deleted_at = UTC_TIMESTAMP() WHERE id = ?");
    $stmt->execute([$id]);
    
    log_audit(current_user()['id'], 'delete', 'team', $id, "Deleted team: {$team['name']}");
    
    header('Location: ' . BASE_URL . '/teams/index.php?success=' . urlencode('Team deleted successfully'));
} else {
    header('Location: ' . BASE_URL . '/teams/index.php?error=' . urlencode('Team not found'));
}
exit;

