<?php
/**
 * progress/edit.php
 * Edit daily progress entry
 */

require_once __DIR__ . '/../auth_helper.php';
require_once __DIR__ . '/../helpers.php';

require_role(['admin', 'superadmin']);

$id = intval($_GET['id'] ?? 0);
if (!$id) {
    header('Location: ' . BASE_URL . '/dashboard.php');
    exit;
}

$pdo = getPDO();
$stmt = $pdo->prepare("SELECT * FROM daily_progress WHERE id = ?");
$stmt->execute([$id]);
$entry = $stmt->fetch();

if (!$entry) {
    header('Location: ' . BASE_URL . '/dashboard.php');
    exit;
}

// Get staff users
$stmt = $pdo->query("SELECT id, name FROM users WHERE role = 'staff' AND deleted_at IS NULL ORDER BY name");
$staff = $stmt->fetchAll();

// Get customers
$stmt = $pdo->query("SELECT id, name FROM customers WHERE deleted_at IS NULL ORDER BY name");
$customers = $stmt->fetchAll();

$groups_status = json_decode($entry['groups_status'], true) ?? [];

$page_title = 'Edit Daily Progress';
include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="gradient-text mb-2">Edit Daily Progress</h1>
            <p class="text-muted mb-0">Update daily progress entry for staff members</p>
        </div>
    </div>
    
    <div class="card shadow-lg border-0">
        <div class="card-body p-4">
            <form id="progressForm">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                <input type="hidden" name="id" value="<?= $entry['id'] ?>">
                
                <div class="row">
                    <div class="col-md-6 mb-4">
                        <label for="user_id" class="form-label fw-semibold">
                            <i class="bi bi-person me-1"></i>Staff Member <span class="text-danger">*</span>
                        </label>
                        <select class="form-select form-select-lg" id="user_id" name="user_id" required>
                            <option value="">Select Staff</option>
                            <?php foreach ($staff as $s): ?>
                                <option value="<?= $s['id'] ?>" <?= $entry['user_id'] == $s['id'] ? 'selected' : '' ?>><?= h($s['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-6 mb-4">
                        <label for="date" class="form-label fw-semibold">
                            <i class="bi bi-calendar me-1"></i>Date <span class="text-danger">*</span>
                        </label>
                        <input type="date" 
                               class="form-control form-control-lg" 
                               id="date" 
                               name="date" 
                               required 
                               value="<?= $entry['date'] ?>">
                    </div>
                </div>
                
                <div class="mb-4">
                    <label for="tickets_missed" class="form-label fw-semibold">
                        <i class="bi bi-ticket-perforated me-1"></i>Tickets Missed
                    </label>
                    <input type="number" 
                           class="form-control form-control-lg" 
                           id="tickets_missed" 
                           name="tickets_missed" 
                           min="0" 
                           value="<?= $entry['tickets_missed'] ?>"
                           placeholder="0">
                </div>
                
                <div class="mb-4">
                    <label for="customer_id" class="form-label fw-semibold">
                        <i class="bi bi-shop me-1"></i>Customer
                    </label>
                    <select class="form-select form-select-lg" id="customer_id" name="customer_id">
                        <option value="">Select Customer</option>
                        <?php foreach ($customers as $customer): ?>
                            <option value="<?= $customer['id'] ?>" <?= $entry['customer_id'] == $customer['id'] ? 'selected' : '' ?>><?= h($customer['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="mb-4">
                    <label class="form-label fw-semibold">
                        <i class="bi bi-chat-dots me-1"></i>Groups Status
                    </label>
                    <div id="groupsContainer" class="border rounded p-3 bg-light">
                        <p class="text-muted small mb-0">
                            <i class="bi bi-info-circle me-1"></i>Select a customer first to load groups
                        </p>
                    </div>
                </div>
                
                <div class="mb-4">
                    <label for="notes" class="form-label fw-semibold">
                        <i class="bi bi-sticky me-1"></i>Notes
                    </label>
                    <textarea class="form-control form-control-lg" 
                              id="notes" 
                              name="notes" 
                              rows="3"
                              placeholder="Enter any additional notes..."><?= h($entry['notes']) ?></textarea>
                </div>
                
                <div class="alert alert-info animate-fade-in" id="previewAlert">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-graph-up me-2 fs-5"></i>
                        <div>
                            <strong>Current Progress:</strong> 
                            <span class="fs-4 fw-bold text-primary"><?= number_format($entry['progress_percent'], 2) ?>%</span>
                        </div>
                    </div>
                </div>
                
                <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                    <a href="<?= BASE_URL ?>/dashboard.php" class="btn btn-secondary btn-lg">
                        <i class="bi bi-x-circle me-1"></i>Cancel
                    </a>
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="bi bi-check-circle me-1"></i>Update Progress
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Similar script as add.php but for editing
document.addEventListener('DOMContentLoaded', function() {
    const customerSelect = document.getElementById('customer_id');
    const groupsContainer = document.getElementById('groupsContainer');
    
    if (customerSelect.value) {
        // Load groups for selected customer
        fetch('<?= BASE_URL ?>/api/get_customer_groups.php?customer_id=' + customerSelect.value)
            .then(r => r.json())
            .then(data => {
                if (data.success && data.groups) {
                    groupsContainer.innerHTML = '';
                    data.groups.forEach((group, index) => {
                        const existing = <?= json_encode($groups_status) ?>.find(g => g.group_id == group.id);
                        const div = document.createElement('div');
                        div.className = 'mb-2';
                        div.innerHTML = `
                            <label class="form-label small">${group.name}</label>
                            <select class="form-select form-select-sm" name="groups_status[${index}][status]">
                                <option value="">Select Status</option>
                                <option value="completed" ${existing && existing.status === 'completed' ? 'selected' : ''}>Completed</option>
                                <option value="partial" ${existing && existing.status === 'partial' ? 'selected' : ''}>Partial</option>
                                <option value="missed" ${existing && existing.status === 'missed' ? 'selected' : ''}>Missed</option>
                            </select>
                            <input type="hidden" name="groups_status[${index}][group_id]" value="${group.id}">
                        `;
                        groupsContainer.appendChild(div);
                    });
                }
            });
    }
    
    customerSelect.addEventListener('change', function() {
        // Same logic as add.php
        if (this.value) {
            fetch('<?= BASE_URL ?>/api/get_customer_groups.php?customer_id=' + this.value)
                .then(r => r.json())
                .then(data => {
                    if (data.success && data.groups) {
                        groupsContainer.innerHTML = '';
                        data.groups.forEach((group, index) => {
                            const div = document.createElement('div');
                            div.className = 'mb-2';
                            div.innerHTML = `
                                <label class="form-label small">${group.name}</label>
                                <select class="form-select form-select-sm" name="groups_status[${index}][status]">
                                    <option value="">Select Status</option>
                                    <option value="completed">Completed</option>
                                    <option value="partial">Partial</option>
                                    <option value="missed">Missed</option>
                                </select>
                                <input type="hidden" name="groups_status[${index}][group_id]" value="${group.id}">
                            `;
                            groupsContainer.appendChild(div);
                        });
                    }
                });
        }
    });
    
    document.getElementById('progressForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        const data = {};
        formData.forEach((value, key) => {
            if (key.startsWith('groups_status')) {
                const match = key.match(/groups_status\[(\d+)\]\[(.+)\]/);
                if (match) {
                    const index = match[1];
                    const field = match[2];
                    if (!data.groups_status) data.groups_status = {};
                    if (!data.groups_status[index]) data.groups_status[index] = {};
                    data.groups_status[index][field] = value;
                }
            } else {
                data[key] = value;
            }
        });
        
        if (data.groups_status) {
            data.groups_status = Object.values(data.groups_status).filter(g => g.group_id && g.status);
        }
        
        fetch('<?= BASE_URL ?>/api/save_progress.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(data)
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                Notify.success('Progress updated successfully!');
                window.location.href = '<?= BASE_URL ?>/dashboard.php';
            } else {
                Notify.error('Error: ' + (data.message || 'Failed to update progress'));
            }
        });
    });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>

