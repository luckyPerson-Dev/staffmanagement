<?php
/**
 * users/delete.php
 * Soft delete user (superadmin only)
 */

require_once __DIR__ . '/../auth_helper.php';
require_once __DIR__ . '/../helpers.php';

require_role(['superadmin', 'admin']);

$id = intval($_GET['id'] ?? 0);
if (!$id) {
    header('Location: ' . BASE_URL . '/users/index.php');
    exit;
}

// Prevent self-deletion
if ($id == current_user()['id']) {
    header('Location: ' . BASE_URL . '/users/index.php?error=' . urlencode('Cannot delete yourself'));
    exit;
}

$pdo = getPDO();
$stmt = $pdo->prepare("SELECT email FROM users WHERE id = ? AND deleted_at IS NULL");
$stmt->execute([$id]);
$user = $stmt->fetch();

if ($user) {
    // Soft delete
    $stmt = $pdo->prepare("UPDATE users SET deleted_at = UTC_TIMESTAMP() WHERE id = ?");
    $stmt->execute([$id]);
    
    log_audit(current_user()['id'], 'delete', 'user', $id, "Deleted user: {$user['email']}");
    
    header('Location: ' . BASE_URL . '/users/index.php?success=' . urlencode('User deleted successfully'));
} else {
    header('Location: ' . BASE_URL . '/users/index.php?error=' . urlencode('User not found'));
}
exit;

