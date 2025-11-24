<?php
/**
 * customers/delete.php
 * Soft delete customer
 */

require_once __DIR__ . '/../auth_helper.php';
require_once __DIR__ . '/../helpers.php';

require_role(['superadmin', 'admin']);

$id = intval($_GET['id'] ?? 0);
if (!$id) {
    header('Location: ' . BASE_URL . '/customers/index.php');
    exit;
}

$pdo = getPDO();
$stmt = $pdo->prepare("SELECT name FROM customers WHERE id = ? AND deleted_at IS NULL");
$stmt->execute([$id]);
$customer = $stmt->fetch();

if ($customer) {
    $stmt = $pdo->prepare("UPDATE customers SET deleted_at = UTC_TIMESTAMP() WHERE id = ?");
    $stmt->execute([$id]);
    
    log_audit(current_user()['id'], 'delete', 'customer', $id, "Deleted customer: {$customer['name']}");
    
    header('Location: ' . BASE_URL . '/customers/index.php?success=' . urlencode('Customer deleted successfully'));
} else {
    header('Location: ' . BASE_URL . '/customers/index.php?error=' . urlencode('Customer not found'));
}
exit;

