<?php
/**
 * api/get_customer_groups.php
 * Get customer WhatsApp groups (AJAX endpoint)
 */

require_once __DIR__ . '/../auth_helper.php';
require_once __DIR__ . '/../helpers.php';

require_login();

header('Content-Type: application/json');

$customer_id = intval($_GET['customer_id'] ?? 0);

if (!$customer_id) {
    response_json(['success' => false, 'message' => 'Customer ID required'], 400);
}

$pdo = getPDO();

// Check if customer exists and get full data including whatsapp_group_link
$stmt = $pdo->prepare("SELECT id, name, whatsapp_group_link FROM customers WHERE id = ? AND deleted_at IS NULL");
$stmt->execute([$customer_id]);
$customer = $stmt->fetch();

if (!$customer) {
    response_json(['success' => false, 'message' => 'Customer not found'], 404);
}

// Get groups from customer_groups table
$groups = [];
try {
    $stmt = $pdo->prepare("
        SELECT id, name, whatsapp_group_link, status 
        FROM customer_groups 
        WHERE customer_id = ? AND deleted_at IS NULL AND status = 'active'
        ORDER BY name
    ");
    $stmt->execute([$customer_id]);
    $groups = $stmt->fetchAll();
} catch (Exception $e) {
    // Table might not exist, continue to fallback logic
    error_log("Error fetching customer groups: " . $e->getMessage());
}

// If no groups exist in customer_groups table, check if customer has a whatsapp_group_link
// and create a default group entry
if (empty($groups)) {
    // Use customer data we already fetched
    if ($customer && !empty($customer['whatsapp_group_link'])) {
        // Create a default group from the customer's whatsapp_group_link
        try {
            // Ensure table exists (create if not)
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `customer_groups` (
                    `id` INT(11) NOT NULL AUTO_INCREMENT,
                    `customer_id` INT(11) NOT NULL,
                    `name` VARCHAR(255) NOT NULL,
                    `whatsapp_group_link` VARCHAR(500) DEFAULT NULL,
                    `status` ENUM('active', 'inactive') DEFAULT 'active',
                    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                    `deleted_at` TIMESTAMP NULL DEFAULT NULL,
                    PRIMARY KEY (`id`),
                    KEY `idx_customer` (`customer_id`),
                    KEY `idx_deleted` (`deleted_at`),
                    FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
            
            $stmt = $pdo->prepare("
                INSERT INTO customer_groups (customer_id, name, whatsapp_group_link, status, created_at)
                VALUES (?, ?, ?, 'active', UTC_TIMESTAMP())
            ");
            $group_name = $customer['name'] . ' - Main Group';
            $stmt->execute([
                $customer_id,
                $group_name,
                $customer['whatsapp_group_link']
            ]);
            $group_id = $pdo->lastInsertId();
            
            // Fetch the newly created group
            $stmt = $pdo->prepare("
                SELECT id, name, whatsapp_group_link, status 
                FROM customer_groups 
                WHERE id = ?
            ");
            $stmt->execute([$group_id]);
            $new_group = $stmt->fetch();
            
            if ($new_group) {
                $groups = [$new_group];
            }
        } catch (Exception $e) {
            // If insert fails, log error but don't break the API
            error_log("Error creating default group for customer {$customer_id}: " . $e->getMessage());
            // Return empty groups array - UI will show appropriate message
        }
    }
}

// If still no groups, return empty array (not an error)
// The UI will handle this gracefully

response_json([
    'success' => true,
    'groups' => $groups
]);

