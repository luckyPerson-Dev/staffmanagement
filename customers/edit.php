<?php
/**
 * customers/edit.php
 * Edit customer
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
$stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ? AND deleted_at IS NULL");
$stmt->execute([$id]);
$customer = $stmt->fetch();

if (!$customer) {
    header('Location: ' . BASE_URL . '/customers/index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $whatsapp_group_link = trim($_POST['whatsapp_group_link'] ?? '');
        $ticket_penalty_percent = !empty($_POST['ticket_penalty_percent']) ? floatval($_POST['ticket_penalty_percent']) : null;
        $group_miss_penalty_percent = !empty($_POST['group_miss_penalty_percent']) ? floatval($_POST['group_miss_penalty_percent']) : null;
        $group_partial_penalty_percent = !empty($_POST['group_partial_penalty_percent']) ? floatval($_POST['group_partial_penalty_percent']) : null;
        
        if (empty($name)) {
            $error = 'Customer name is required.';
        } else {
            $stmt = $pdo->prepare("
                UPDATE customers 
                SET name = ?, 
                    whatsapp_group_link = ?, 
                    ticket_penalty_percent = ?,
                    group_miss_penalty_percent = ?,
                    group_partial_penalty_percent = ?,
                    updated_at = UTC_TIMESTAMP()
                WHERE id = ?
            ");
            $stmt->execute([
                $name, 
                $whatsapp_group_link ?: null,
                $ticket_penalty_percent,
                $group_miss_penalty_percent,
                $group_partial_penalty_percent,
                $id
            ]);
            
            log_audit(current_user()['id'], 'update', 'customer', $id, "Updated customer: $name");
            
            header('Location: ' . BASE_URL . '/customers/index.php?success=' . urlencode('Customer updated successfully'));
            exit;
        }
    }
}

$page_title = 'Edit Customer';
include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="gradient-text mb-2">Edit Customer</h1>
            <p class="text-muted mb-0">Update customer information and communication group links</p>
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
            
            <form method="POST" action="" id="editCustomerForm">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                
                <div class="mb-4">
                    <label for="name" class="form-label fw-semibold">
                        <i class="bi bi-shop me-1"></i>Customer Name <span class="text-danger">*</span>
                    </label>
                    <input type="text" 
                           class="form-control form-control-lg" 
                           id="name" 
                           name="name" 
                           required 
                           value="<?= h($customer['name']) ?>"
                           placeholder="Enter customer name">
                </div>
                
                <div class="mb-4">
                    <label for="whatsapp_group_link" class="form-label fw-semibold">
                        <i class="bi bi-link-45deg me-1"></i>Group Link
                    </label>
                    <input type="url" 
                           class="form-control form-control-lg" 
                           id="whatsapp_group_link" 
                           name="whatsapp_group_link" 
                           value="<?= h($customer['whatsapp_group_link']) ?>" 
                           placeholder="https://chat.whatsapp.com/... or https://t.me/... or https://m.me/...">
                    <small class="text-muted">
                        <i class="bi bi-info-circle me-1"></i>Paste the group invite link here (WhatsApp, Telegram, Messenger, etc.)
                    </small>
                </div>
                
                <div class="row">
                    <div class="col-12 mb-3">
                        <h5 class="border-bottom pb-2 mb-3">
                            <i class="bi bi-sliders me-2"></i>Penalty Settings
                        </h5>
                        <p class="text-muted small mb-3">
                            <i class="bi bi-info-circle me-1"></i>
                            Set custom penalties for this customer. Leave empty to use global settings.
                        </p>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-4 mb-4">
                        <label for="ticket_penalty_percent" class="form-label fw-semibold">
                            <i class="bi bi-ticket-perforated me-1"></i>Ticket Penalty (%) 
                            <span class="text-muted small">(per missed ticket)</span>
                        </label>
                        <input type="number" 
                               class="form-control form-control-lg" 
                               id="ticket_penalty_percent" 
                               name="ticket_penalty_percent" 
                               step="0.01" 
                               min="0" 
                               max="100"
                               value="<?= h($customer['ticket_penalty_percent'] ?? '') ?>"
                               placeholder="Default: <?= get_setting('ticket_penalty_percent', 5) ?>">
                        <small class="text-muted">
                            <i class="bi bi-info-circle me-1"></i>
                            Global default: <?= get_setting('ticket_penalty_percent', 5) ?>%
                        </small>
                    </div>
                    
                    <div class="col-md-4 mb-4">
                        <label for="group_miss_penalty_percent" class="form-label fw-semibold">
                            <i class="bi bi-x-circle me-1"></i>Group Miss Penalty (%) 
                            <span class="text-muted small">(per missed group)</span>
                        </label>
                        <input type="number" 
                               class="form-control form-control-lg" 
                               id="group_miss_penalty_percent" 
                               name="group_miss_penalty_percent" 
                               step="0.01" 
                               min="0" 
                               max="100"
                               value="<?= h($customer['group_miss_penalty_percent'] ?? '') ?>"
                               placeholder="Default: <?= get_setting('group_miss_percent', 10) ?>">
                        <small class="text-muted">
                            <i class="bi bi-info-circle me-1"></i>
                            Global default: <?= get_setting('group_miss_percent', 10) ?>%
                        </small>
                    </div>
                    
                    <div class="col-md-4 mb-4">
                        <label for="group_partial_penalty_percent" class="form-label fw-semibold">
                            <i class="bi bi-dash-circle me-1"></i>Group Partial Penalty (%) 
                            <span class="text-muted small">(per partial group)</span>
                        </label>
                        <input type="number" 
                               class="form-control form-control-lg" 
                               id="group_partial_penalty_percent" 
                               name="group_partial_penalty_percent" 
                               step="0.01" 
                               min="0" 
                               max="100"
                               value="<?= h($customer['group_partial_penalty_percent'] ?? '') ?>"
                               placeholder="Default: <?= get_setting('group_partial_percent', 5) ?>">
                        <small class="text-muted">
                            <i class="bi bi-info-circle me-1"></i>
                            Global default: <?= get_setting('group_partial_percent', 5) ?>%
                        </small>
                    </div>
                </div>
                
                <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                    <a href="<?= BASE_URL ?>/customers/index.php" class="btn btn-secondary btn-lg">
                        <i class="bi bi-x-circle me-1"></i>Cancel
                    </a>
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="bi bi-check-circle me-1"></i>Update Customer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.getElementById('editCustomerForm').addEventListener('submit', function(e) {
    const submitBtn = this.querySelector('button[type="submit"]');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Updating...';
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>

