<?php
/**
 * progress/daily_progress_edit.php
 * Edit daily progress entry with missed day and overtime support
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

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $_SESSION['error'] = 'Invalid CSRF token.';
        header('Location: ' . BASE_URL . '/progress/daily_progress_edit.php?id=' . $id);
        exit;
    }
    
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("DELETE FROM daily_progress WHERE id = ?");
        $stmt->execute([$id]);
        $pdo->commit();
        
        log_audit(current_user()['id'], 'delete', 'daily_progress', $id, "Deleted progress entry for user {$entry['user_id']} on {$entry['date']}");
        
        $_SESSION['success'] = 'Progress entry deleted successfully.';
        header('Location: ' . BASE_URL . '/dashboard.php');
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = 'Failed to delete progress entry: ' . $e->getMessage();
        header('Location: ' . BASE_URL . '/progress/daily_progress_edit.php?id=' . $id);
        exit;
    }
}

// Get staff users
$stmt = $pdo->query("SELECT id, name FROM users WHERE role = 'staff' AND deleted_at IS NULL ORDER BY name");
$staff = $stmt->fetchAll();

// Get customers
$stmt = $pdo->query("SELECT id, name FROM customers WHERE deleted_at IS NULL ORDER BY name");
$customers = $stmt->fetchAll();

$groups_status = json_decode($entry['groups_status'], true) ?? [];
$is_missed = isset($entry['is_missed']) ? (int)$entry['is_missed'] : 0;
$is_overtime = isset($entry['is_overtime']) ? (int)$entry['is_overtime'] : 0;

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
    
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= h($_SESSION['error']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>
    
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
                
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="form-check form-switch form-check-lg">
                            <input class="form-check-input" 
                                   type="checkbox" 
                                   id="is_missed" 
                                   name="is_missed" 
                                   value="1"
                                   <?= $is_missed ? 'checked' : '' ?>>
                            <label class="form-check-label fw-semibold" for="is_missed">
                                <i class="bi bi-x-circle text-danger me-1"></i>Missed Day (0%)
                            </label>
                            <small class="form-text text-muted d-block mt-1">
                                Mark this day as missed. Progress will be set to 0%.
                            </small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-check form-switch form-check-lg">
                            <input class="form-check-input" 
                                   type="checkbox" 
                                   id="is_overtime" 
                                   name="is_overtime" 
                                   value="1"
                                   <?= $is_overtime ? 'checked' : '' ?>>
                            <label class="form-check-label fw-semibold" for="is_overtime">
                                <i class="bi bi-clock-history text-success me-1"></i>Overtime Worked
                            </label>
                            <small class="form-text text-muted d-block mt-1">
                                Progress will be doubled (per_day_percent × 2).
                            </small>
                        </div>
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
                            <span class="fs-4 fw-bold text-primary" id="previewPercent"><?= number_format($entry['progress_percent'], 2) ?>%</span>
                        </div>
                    </div>
                </div>
                
                <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                    <button type="button" 
                            class="btn btn-danger btn-lg" 
                            data-bs-toggle="modal" 
                            data-bs-target="#deleteModal">
                        <i class="bi bi-trash me-1"></i>Delete
                    </button>
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

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteModalLabel">Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this progress entry?</p>
                <p class="text-muted small">This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form method="POST" action="" style="display: inline;">
                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                    <input type="hidden" name="delete" value="1">
                    <button type="submit" class="btn btn-danger">Delete</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('progressForm');
    const customerSelect = document.getElementById('customer_id');
    const groupsContainer = document.getElementById('groupsContainer');
    const previewPercent = document.getElementById('previewPercent');
    const dateInput = document.getElementById('date');
    const isMissed = document.getElementById('is_missed');
    const isOvertime = document.getElementById('is_overtime');
    
    const groupsStatus = <?= json_encode($groups_status) ?>;
    
    // Load groups if customer is selected
    if (customerSelect.value) {
        loadCustomerGroups(customerSelect.value);
    }
    
    function loadCustomerGroups(customerId) {
        groupsContainer.innerHTML = '<p class="text-muted small"><i class="bi bi-hourglass-split me-1"></i>Loading groups...</p>';
        
        fetch('<?= BASE_URL ?>/api/get_customer_groups.php?customer_id=' + customerId)
            .then(r => r.json())
            .then(data => {
                if (data.success && data.groups) {
                    groupsContainer.innerHTML = '';
                    data.groups.forEach((group, index) => {
                        const existing = groupsStatus.find(g => g.group_id == group.id);
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
                            <input type="hidden" name="groups_status[${index}][customer_id]" value="${customerId}">
                        `;
                        groupsContainer.appendChild(div);
                    });
                    calculatePreview();
                } else {
                    groupsContainer.innerHTML = '<p class="text-muted small">No groups found for this customer</p>';
                }
            })
            .catch(err => {
                groupsContainer.innerHTML = '<p class="text-danger small">Error loading groups: ' + err.message + '</p>';
            });
    }
    
    customerSelect.addEventListener('change', function() {
        if (this.value) {
            loadCustomerGroups(this.value);
        } else {
            groupsContainer.innerHTML = '<p class="text-muted small"><i class="bi bi-info-circle me-1"></i>Select a customer first to load groups</p>';
        }
    });
    
    function calculatePreview() {
        const ticketsMissed = parseInt(document.getElementById('tickets_missed').value) || 0;
        
        // Build groups_status array from form
        const groupsStatus = [];
        const groupSelects = groupsContainer.querySelectorAll('select[name*="[status]"]');
        groupSelects.forEach(select => {
            const status = select.value;
            if (status) {
                // Find the hidden inputs for this group
                const parent = select.closest('.mb-2');
                if (parent) {
                    const groupIdInput = parent.querySelector('input[name*="[group_id]"]');
                    const customerIdInput = parent.querySelector('input[name*="[customer_id]"]');
                    if (groupIdInput) {
                        groupsStatus.push({
                            group_id: parseInt(groupIdInput.value),
                            customer_id: customerIdInput ? parseInt(customerIdInput.value) : null,
                            status: status
                        });
                    }
                }
            }
        });
        
        if (isMissed.checked) {
            previewPercent.textContent = '0.00';
            return;
        }
        
        const firstCustomerId = customerSelect.value ? parseInt(customerSelect.value) : null;
        
        fetch('<?= BASE_URL ?>/api/calculate_progress.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                tickets_missed: ticketsMissed,
                groups_status: groupsStatus,
                customer_id: firstCustomerId,
                is_missed: isMissed.checked,
                is_overtime: isOvertime.checked,
                date: dateInput.value
            })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                previewPercent.textContent = data.progress_percent.toFixed(2);
            }
        })
        .catch(err => {
            console.error('Preview calculation error:', err);
        });
    }
    
    dateInput.addEventListener('change', calculatePreview);
    isMissed.addEventListener('change', function() {
        if (this.checked) {
            isOvertime.checked = false;
        }
        calculatePreview();
    });
    isOvertime.addEventListener('change', function() {
        if (this.checked) {
            isMissed.checked = false;
        }
        calculatePreview();
    });
    document.getElementById('tickets_missed').addEventListener('input', calculatePreview);
    groupsContainer.addEventListener('change', function(e) {
        if (e.target.tagName === 'SELECT') calculatePreview();
    });
    
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(form);
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
        
        data.is_missed = isMissed.checked ? 1 : 0;
        data.is_overtime = isOvertime.checked ? 1 : 0;
        
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
        })
        .catch(err => {
            Notify.error('Error: ' + err.message);
        });
    });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>

