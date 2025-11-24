<?php
/**
 * progress/add.php
 * Add daily progress entry
 */

require_once __DIR__ . '/../auth_helper.php';
require_once __DIR__ . '/../helpers.php';

require_role(['admin', 'superadmin']);

$pdo = getPDO();

// Get staff users
$stmt = $pdo->query("SELECT id, name FROM users WHERE role = 'staff' AND deleted_at IS NULL ORDER BY name");
$staff = $stmt->fetchAll();

// Get customers
$stmt = $pdo->query("SELECT id, name FROM customers WHERE deleted_at IS NULL ORDER BY name");
$customers = $stmt->fetchAll();

$page_title = 'Add Daily Progress';
include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="gradient-text mb-2">Add Daily Progress</h1>
            <p class="text-muted mb-0">Record daily progress entries for staff members</p>
        </div>
    </div>
    
    <div class="card shadow-lg border-0">
        <div class="card-body p-4">
            <form id="progressForm">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                
                <div class="row">
                    <div class="col-md-6 mb-4">
                        <label for="user_id" class="form-label fw-semibold">
                            <i class="bi bi-person me-1"></i>Staff Member <span class="text-danger">*</span>
                        </label>
                        <select class="form-select form-select-lg" id="user_id" name="user_id" required>
                            <option value="">Select Staff</option>
                            <?php foreach ($staff as $s): ?>
                                <option value="<?= $s['id'] ?>"><?= h($s['name']) ?></option>
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
                               value="<?= date('Y-m-d') ?>">
                    </div>
                </div>
                
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="form-check form-switch form-check-lg">
                            <input class="form-check-input" 
                                   type="checkbox" 
                                   id="is_missed" 
                                   name="is_missed" 
                                   value="1">
                            <label class="form-check-label fw-semibold" for="is_missed">
                                Missed Day (0%)
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
                                   value="1">
                            <label class="form-check-label fw-semibold" for="is_overtime">
                                Overtime Worked
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
                           value="0"
                           placeholder="0">
                </div>
                
                <div class="mb-4">
                    <label class="form-label fw-semibold">
                        <i class="bi bi-building me-1"></i>Customers (Select Multiple)
                    </label>
                    <div class="position-relative">
                        <input type="text" 
                               class="form-control form-control-lg" 
                               id="customerSearch" 
                               placeholder="Search customers...">
                        <div class="position-absolute w-100 bg-white border rounded mt-1 shadow-lg" 
                             id="customerDropdown" 
                             style="display: none; max-height: 300px; overflow-y: auto; z-index: 1000;">
                            <?php foreach ($customers as $customer): ?>
                                <div class="customer-option p-3 border-bottom" 
                                     data-id="<?= $customer['id'] ?>" 
                                     data-name="<?= h($customer['name']) ?>"
                                     style="cursor: pointer; transition: background-color 0.2s;">
                                    <div class="form-check mb-0">
                                        <input class="form-check-input customer-checkbox" 
                                               type="checkbox" 
                                               value="<?= $customer['id'] ?>" 
                                               id="customer_<?= $customer['id'] ?>"
                                               style="pointer-events: none;">
                                        <label class="form-check-label w-100 mb-0" 
                                               for="customer_<?= $customer['id'] ?>" 
                                               style="cursor: pointer; user-select: none; padding-left: 8px;">
                                            <strong><?= h($customer['name']) ?></strong>
                                        </label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div id="selectedCustomers" class="mt-2"></div>
                    <input type="hidden" id="customer_ids" name="customer_ids" value="">
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
                              placeholder="Enter any additional notes..."></textarea>
                </div>
                
                <div class="alert alert-info animate-fade-in" id="previewAlert" style="display:none;">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-graph-up me-2 fs-5"></i>
                        <div>
                            <strong>Progress Preview:</strong> 
                            <span class="fs-4 fw-bold text-primary" id="previewPercent">0</span>%
                        </div>
                    </div>
                </div>
                
                <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                    <a href="<?= BASE_URL ?>/dashboard.php" class="btn btn-secondary btn-lg">
                        <i class="bi bi-x-circle me-1"></i>Cancel
                    </a>
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="bi bi-check-circle me-1"></i>Save Progress
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.customer-option {
    transition: all 0.2s ease;
}
.customer-option:hover {
    background-color: #f0f7ff !important;
    transform: translateX(4px);
}
.customer-option:active {
    background-color: #e0efff !important;
}
.customer-option:last-child {
    border-bottom: none !important;
}
.customer-option .form-check {
    margin: 0;
}
.customer-option .form-check-label {
    padding-left: 8px;
}
#customerDropdown {
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15) !important;
}
#selectedCustomers .badge {
    display: inline-flex;
    align-items: center;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('progressForm');
    const customerSearch = document.getElementById('customerSearch');
    const customerDropdown = document.getElementById('customerDropdown');
    const selectedCustomersDiv = document.getElementById('selectedCustomers');
    const customerIdsInput = document.getElementById('customer_ids');
    const groupsContainer = document.getElementById('groupsContainer');
    const previewAlert = document.getElementById('previewAlert');
    const previewPercent = document.getElementById('previewPercent');
    
    let selectedCustomers = [];
    let allCustomers = <?= json_encode($customers) ?>;
    
    // Customer search functionality
    customerSearch.addEventListener('focus', function() {
        customerDropdown.style.display = 'block';
        filterCustomers();
    });
    
    customerSearch.addEventListener('input', function() {
        filterCustomers();
    });
    
    // Close dropdown when clicking outside
    document.addEventListener('click', function(e) {
        if (!customerSearch.contains(e.target) && !customerDropdown.contains(e.target)) {
            customerDropdown.style.display = 'none';
        }
    });
    
    function filterCustomers() {
        const searchTerm = customerSearch.value.toLowerCase();
        const options = customerDropdown.querySelectorAll('.customer-option');
        
        options.forEach(option => {
            const name = option.dataset.name.toLowerCase();
            if (name.includes(searchTerm)) {
                option.style.display = 'block';
            } else {
                option.style.display = 'none';
            }
        });
    }
    
    // Handle customer selection - make entire row clickable
    customerDropdown.addEventListener('click', function(e) {
        const option = e.target.closest('.customer-option');
        if (option) {
            // Prevent event from bubbling if clicking on the row itself
            e.stopPropagation();
            
            const checkbox = option.querySelector('.customer-checkbox');
            if (checkbox) {
                // Toggle checkbox state
                checkbox.checked = !checkbox.checked;
                toggleCustomer(checkbox);
            }
        }
    });
    
    function toggleCustomer(checkbox) {
        const customerId = parseInt(checkbox.value);
        const customerName = checkbox.closest('.customer-option').dataset.name;
        
        if (checkbox.checked) {
            if (!selectedCustomers.find(c => c.id === customerId)) {
                selectedCustomers.push({id: customerId, name: customerName});
            }
        } else {
            selectedCustomers = selectedCustomers.filter(c => c.id !== customerId);
        }
        
        updateSelectedCustomers();
        loadCustomerGroups();
    }
    
    function updateSelectedCustomers() {
        customerIdsInput.value = selectedCustomers.map(c => c.id).join(',');
        
        if (selectedCustomers.length === 0) {
            selectedCustomersDiv.innerHTML = '';
            return;
        }
        
        let html = '<div class="d-flex flex-wrap gap-2 mt-2">';
        selectedCustomers.forEach(customer => {
            html += `
                <span class="badge bg-primary fs-6 p-2">
                    ${customer.name}
                    <button type="button" 
                            class="btn-close btn-close-white ms-2" 
                            style="font-size: 0.7rem;"
                            data-customer-id="${customer.id}"
                            onclick="removeCustomer(${customer.id})"></button>
                </span>
            `;
        });
        html += '</div>';
        selectedCustomersDiv.innerHTML = html;
    }
    
    // Remove customer function (global for onclick)
    window.removeCustomer = function(customerId) {
        selectedCustomers = selectedCustomers.filter(c => c.id !== customerId);
        const checkbox = document.querySelector(`.customer-checkbox[value="${customerId}"]`);
        if (checkbox) {
            checkbox.checked = false;
        }
        updateSelectedCustomers();
        loadCustomerGroups();
    };
    
    // Load groups for all selected customers
    function loadCustomerGroups() {
        if (selectedCustomers.length === 0) {
            groupsContainer.innerHTML = '<p class="text-muted small"><i class="bi bi-info-circle me-1"></i>Select customers first to load groups</p>';
            return;
        }
        
        groupsContainer.innerHTML = '<p class="text-muted small"><i class="bi bi-hourglass-split me-1"></i>Loading groups...</p>';
        
        // Load groups for all selected customers - map with customer data
        const promises = selectedCustomers.map(customer => 
            fetch('<?= BASE_URL ?>/api/get_customer_groups.php?customer_id=' + customer.id)
                .then(r => {
                    if (!r.ok) {
                        throw new Error('HTTP error! status: ' + r.status);
                    }
                    return r.json();
                })
                .then(data => {
                    // Groups loaded successfully
                    return { customer: customer, data: data };
                })
                .catch(err => {
                    console.error('Error fetching groups for customer', customer.id, ':', err);
                    return { customer: customer, data: { success: false, error: err.message } };
                })
        );
        
        Promise.all(promises).then(results => {
            // All customer groups loaded
            groupsContainer.innerHTML = '';
            let groupIndex = 0;
            let hasGroups = false;
            
            results.forEach((result) => {
                const customer = result.customer;
                const data = result.data;
                
                if (!customer) {
                    return; // Skip if customer is undefined
                }
                
                if (data.success && data.groups && Array.isArray(data.groups) && data.groups.length > 0) {
                    hasGroups = true;
                    
                    // Add customer header
                    const headerDiv = document.createElement('div');
                    headerDiv.className = 'mb-2 mt-3';
                    headerDiv.innerHTML = `<strong class="text-primary">${customer.name}</strong>`;
                    groupsContainer.appendChild(headerDiv);
                    
                    // Add groups for this customer
                    data.groups.forEach((group) => {
                        if (!group || !group.id) {
                            console.warn('Invalid group data:', group);
                            return; // Skip invalid groups
                        }
                        
                        const div = document.createElement('div');
                        div.className = 'mb-2';
                        div.innerHTML = `
                            <label class="form-label small">${group.name || 'Unnamed Group'}</label>
                            <select class="form-select form-select-sm" name="groups_status[${groupIndex}][status]" data-group-id="${group.id}">
                                <option value="">Select Status</option>
                                <option value="completed">Completed</option>
                                <option value="partial">Partial</option>
                                <option value="missed">Missed</option>
                            </select>
                            <input type="hidden" name="groups_status[${groupIndex}][group_id]" value="${group.id}">
                            <input type="hidden" name="groups_status[${groupIndex}][customer_id]" value="${customer.id}">
                        `;
                        groupsContainer.appendChild(div);
                        groupIndex++;
                    });
                } else {
                    // No groups found for customer
                }
            });
            
            if (!hasGroups || groupIndex === 0) {
                groupsContainer.innerHTML = '<p class="text-muted small"><i class="bi bi-info-circle me-1"></i>No groups found for selected customers. Please add a group link to the customer in the <a href="<?= BASE_URL ?>/customers/index.php" target="_blank">Customers</a> page, then refresh this page.</p>';
            }
            
            // Recalculate preview after loading groups
            calculatePreview();
        }).catch(err => {
            console.error('Error in Promise.all:', err);
            groupsContainer.innerHTML = '<p class="text-danger small">Error loading groups: ' + err.message + '</p>';
        });
    }
    
    // Calculate preview
    function calculatePreview() {
        const dateInput = document.getElementById('date');
        const isMissed = document.getElementById('is_missed').checked;
        const isOvertime = document.getElementById('is_overtime').checked;
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
        
        // If missed day is checked, progress is 0
        if (isMissed) {
            previewPercent.textContent = '0.00';
            previewAlert.style.display = 'block';
            return;
        }
        
        // Get first selected customer ID for penalty calculation
        const firstCustomerId = selectedCustomers.length > 0 ? selectedCustomers[0].id : null;
        
        fetch('<?= BASE_URL ?>/api/calculate_progress.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                tickets_missed: ticketsMissed,
                groups_status: groupsStatus,
                customer_id: firstCustomerId,
                is_missed: isMissed,
                is_overtime: isOvertime,
                date: dateInput.value
            })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                previewPercent.textContent = data.progress_percent.toFixed(2);
                previewAlert.style.display = 'block';
            }
        })
        .catch(err => {
            console.error('Preview calculation error:', err);
        });
    }
    
    // Add event listeners
    document.getElementById('date').addEventListener('change', calculatePreview);
    document.getElementById('is_missed').addEventListener('change', function() {
        if (this.checked) {
            document.getElementById('is_overtime').checked = false;
        }
        calculatePreview();
    });
    document.getElementById('is_overtime').addEventListener('change', function() {
        if (this.checked) {
            document.getElementById('is_missed').checked = false;
        }
        calculatePreview();
    });
    document.getElementById('tickets_missed').addEventListener('input', calculatePreview);
    groupsContainer.addEventListener('change', function(e) {
        if (e.target.tagName === 'SELECT') calculatePreview();
    });
    
    // Submit form
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
        
        // Convert groups_status to array
        if (data.groups_status) {
            data.groups_status = Object.values(data.groups_status).filter(g => g.group_id && g.status);
        }
        
        // Set primary customer_id (first selected customer)
        if (data.customer_ids) {
            const customerIds = data.customer_ids.split(',').filter(id => id);
            data.customer_id = customerIds.length > 0 ? parseInt(customerIds[0]) : null;
        }
        
        // Add missed day and overtime flags
        data.is_missed = document.getElementById('is_missed').checked ? 1 : 0;
        data.is_overtime = document.getElementById('is_overtime').checked ? 1 : 0;
        
        fetch('<?= BASE_URL ?>/api/save_progress.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(data)
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                Notify.success('Progress saved successfully!');
                form.reset();
                window.location.href = '<?= BASE_URL ?>/dashboard.php';
            } else {
                Notify.error('Error: ' + (data.message || 'Failed to save progress'));
            }
        })
        .catch(err => {
            Notify.error('Error: ' + err.message);
        });
    });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>

