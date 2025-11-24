<?php
/**
 * customers/index.php
 * List customers
 */

require_once __DIR__ . '/../auth_helper.php';
require_once __DIR__ . '/../helpers.php';

require_role(['superadmin', 'admin']);

$pdo = getPDO();
$stmt = $pdo->query("
    SELECT * FROM customers 
    WHERE deleted_at IS NULL 
    ORDER BY name
");
$customers = $stmt->fetchAll();

$page_title = 'Manage Customers';
include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="mb-4">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3">
            <div>
                <h1 class="gradient-text mb-2 fs-3 fs-md-2">Manage Customers</h1>
                <p class="text-muted mb-0 small">Manage customer accounts and communication groups</p>
            </div>
            <a href="<?= BASE_URL ?>/customers/create.php" class="btn btn-primary btn-lg w-100 w-md-auto shadow-sm">
                <i class="bi bi-building-add me-2"></i>Create Customer
            </a>
        </div>
    </div>
    
    <!-- Search Card -->
    <div class="card shadow-lg border-0 mb-4 animate-slide-up" style="background: linear-gradient(135deg, rgba(0, 123, 255, 0.05) 0%, rgba(112, 111, 211, 0.05) 100%); border-left: 4px solid #007bff !important;">
        <div class="card-body p-3 p-md-4">
            <div class="row align-items-center g-3">
                <div class="col-12 col-md-8">
                    <label for="customerSearch" class="form-label fw-semibold mb-2 small" style="color: #495057;">
                        <i class="bi bi-search me-2"></i>Search Customers
                    </label>
                    <div class="position-relative">
                        <div class="input-group" style="box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08); border-radius: 12px; overflow: hidden;">
                            <span class="input-group-text bg-white border-0" style="border-right: 1px solid #e9ecef !important;">
                                <i class="bi bi-search text-primary"></i>
                            </span>
                            <input type="text" 
                                   class="form-control border-0" 
                                   id="customerSearch" 
                                   placeholder="Type to search by name or group link..."
                                   style="background: white; font-size: 14px; padding: 10px 14px;">
                            <button class="btn btn-link text-muted border-0 p-2" 
                                    type="button" 
                                    id="clearSearch" 
                                    style="display: none; background: white; text-decoration: none;"
                                    title="Clear search">
                                <i class="bi bi-x-lg"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-md-4 text-md-end">
                    <div class="d-flex flex-column align-items-start align-items-md-end">
                        <label class="form-label fw-semibold mb-2 small" style="color: #495057;">
                            Results
                        </label>
                        <div class="d-flex align-items-center">
                            <span class="badge bg-primary bg-gradient px-3 py-2" style="font-size: 13px; font-weight: 600;">
                                <i class="bi bi-people me-1"></i>
                                <span id="resultCount"><?= count($customers) ?></span> 
                                <span class="ms-1">customer<?= count($customers) != 1 ? 's' : '' ?></span>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="card shadow-lg border-0 animate-slide-up" style="animation-delay: 0.1s">
        <div class="card-body p-0">
            <!-- Desktop Table View -->
            <div class="table-responsive d-none d-md-block">
                <table class="table table-hover mb-0 align-middle" id="customersTable">
                    <thead class="table-light" style="background: linear-gradient(135deg, #f8f9fa, #e9ecef);">
                        <tr>
                            <th class="ps-4 py-3 fw-semibold">Name</th>
                            <th class="py-3 fw-semibold">Group Link</th>
                            <th class="py-3 fw-semibold">Penalties</th>
                            <th class="py-3 fw-semibold">Created</th>
                            <th class="text-end pe-4 py-3 fw-semibold">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="customersTableBody">
                        <?php if (empty($customers)): ?>
                            <tr>
                                <td colspan="5" class="text-center py-5 text-muted">
                                    <i class="bi bi-inbox fs-1 d-block mb-2 text-muted"></i>
                                    <p class="mb-0">No customers found</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($customers as $customer): ?>
                                <tr class="customer-row" 
                                    data-name="<?= strtolower(h($customer['name'])) ?>"
                                    data-whatsapp="<?= strtolower(h($customer['whatsapp_group_link'] ?? '')) ?>"
                                    style="transition: all 0.2s ease;">
                                    <td class="ps-4">
                                        <div class="d-flex align-items-center">
                                            <div class="rounded-circle bg-gradient-primary d-flex align-items-center justify-content-center me-3 flex-shrink-0 shadow-sm" 
                                                 style="width: 45px; height: 45px; background: linear-gradient(135deg, #007bff, #706fd3);">
                                                <span class="text-white fw-bold fs-6"><?= strtoupper(substr($customer['name'], 0, 1)) ?></span>
                                            </div>
                                            <strong class="fw-semibold text-truncate"><?= h($customer['name']) ?></strong>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($customer['whatsapp_group_link']): ?>
                                            <a href="<?= h($customer['whatsapp_group_link']) ?>" target="_blank" class="btn btn-sm btn-outline-info border-0 shadow-sm">
                                                <i class="bi bi-link-45deg me-1"></i>Open Group
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted small">
                                                <i class="bi bi-x-circle me-1"></i>No link
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php 
                                        $hasCustomPenalties = false;
                                        $penaltyInfo = [];
                                        
                                        if ($customer['ticket_penalty_percent'] !== null) {
                                            $hasCustomPenalties = true;
                                            $penaltyInfo[] = 'Ticket: ' . number_format($customer['ticket_penalty_percent'], 2) . '%';
                                        }
                                        if ($customer['group_miss_penalty_percent'] !== null) {
                                            $hasCustomPenalties = true;
                                            $penaltyInfo[] = 'Miss: ' . number_format($customer['group_miss_penalty_percent'], 2) . '%';
                                        }
                                        if ($customer['group_partial_penalty_percent'] !== null) {
                                            $hasCustomPenalties = true;
                                            $penaltyInfo[] = 'Partial: ' . number_format($customer['group_partial_penalty_percent'], 2) . '%';
                                        }
                                        
                                        if ($hasCustomPenalties):
                                        ?>
                                            <span class="badge px-3 py-2 shadow-sm" 
                                                  data-bs-toggle="tooltip" 
                                                  data-bs-placement="top" 
                                                  title="<?= h(implode(', ', $penaltyInfo)) ?>"
                                                  style="background: linear-gradient(135deg, #ffc107, #e0a800); border: none;">
                                                <i class="bi bi-sliders me-1"></i>Custom
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary px-3 py-2 shadow-sm" style="border: none;">
                                                <i class="bi bi-info-circle me-1"></i>Default
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="rounded-circle bg-light d-flex align-items-center justify-content-center me-2" style="width: 32px; height: 32px;">
                                                <i class="bi bi-calendar3 text-muted"></i>
                                            </div>
                                            <span class="text-muted"><?= format_date($customer['created_at']) ?></span>
                                        </div>
                                    </td>
                                    <td class="text-end pe-4">
                                        <div class="btn-group shadow-sm" role="group">
                                            <a href="<?= BASE_URL ?>/customers/edit.php?id=<?= $customer['id'] ?>" 
                                               class="btn btn-sm btn-outline-primary border-0" 
                                               title="Edit"
                                               style="transition: all 0.2s ease;">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <a href="<?= BASE_URL ?>/customers/delete.php?id=<?= $customer['id'] ?>" 
                                               class="btn btn-sm btn-outline-danger border-0 delete-link" 
                                               data-confirm="Are you sure you want to delete this customer? This action cannot be undone."
                                               data-confirm-title="Delete Customer"
                                               data-confirm-type="danger"
                                               title="Delete"
                                               style="transition: all 0.2s ease;">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Mobile Card View -->
            <div class="d-md-none">
                <?php if (empty($customers)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                        <p class="mb-0">No customers found</p>
                    </div>
                <?php else: ?>
                    <div class="p-2 p-md-3" id="customersMobileContainer">
                        <?php foreach ($customers as $customer): ?>
                            <?php 
                            $hasCustomPenalties = false;
                            $penaltyInfo = [];
                            
                            if ($customer['ticket_penalty_percent'] !== null) {
                                $hasCustomPenalties = true;
                                $penaltyInfo[] = 'Ticket: ' . number_format($customer['ticket_penalty_percent'], 2) . '%';
                            }
                            if ($customer['group_miss_penalty_percent'] !== null) {
                                $hasCustomPenalties = true;
                                $penaltyInfo[] = 'Miss: ' . number_format($customer['group_miss_penalty_percent'], 2) . '%';
                            }
                            if ($customer['group_partial_penalty_percent'] !== null) {
                                $hasCustomPenalties = true;
                                $penaltyInfo[] = 'Partial: ' . number_format($customer['group_partial_penalty_percent'], 2) . '%';
                            }
                            ?>
                            <div class="card mb-3 border-0 customer-mobile-card" 
                                 style="border-radius: 16px; overflow: hidden;"
                                 data-name="<?= strtolower(h($customer['name'])) ?>"
                                 data-whatsapp="<?= strtolower(h($customer['whatsapp_group_link'] ?? '')) ?>">
                                <!-- Card Header -->
                                <div class="card-header border-0 p-3" style="background: linear-gradient(135deg, rgba(0, 123, 255, 0.1), rgba(112, 111, 211, 0.1));">
                                    <div class="d-flex align-items-center">
                                        <div class="rounded-circle d-flex align-items-center justify-content-center me-3 flex-shrink-0 shadow" 
                                             style="width: 56px; height: 56px; background: linear-gradient(135deg, #007bff, #706fd3);">
                                            <span class="text-white fw-bold" style="font-size: 1.5rem;"><?= strtoupper(substr($customer['name'], 0, 1)) ?></span>
                                        </div>
                                        <div class="flex-grow-1 min-w-0">
                                            <h6 class="mb-1 fw-bold" style="font-size: 1rem; color: #212529;"><?= h($customer['name']) ?></h6>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Card Body -->
                                <div class="card-body p-3">
                                    <!-- Badges Row -->
                                    <div class="d-flex flex-wrap gap-2 mb-3">
                                        <?php if ($hasCustomPenalties): ?>
                                            <span class="badge px-3 py-2" 
                                                  data-bs-toggle="tooltip" 
                                                  data-bs-placement="top" 
                                                  title="<?= h(implode(', ', $penaltyInfo)) ?>"
                                                  style="background: linear-gradient(135deg, #ffc107, #e0a800); border: none; font-size: 0.75rem; font-weight: 600;">
                                                <i class="bi bi-sliders me-1"></i>Custom Penalties
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary px-3 py-2" style="border: none; font-size: 0.75rem; font-weight: 600;">
                                                <i class="bi bi-info-circle me-1"></i>Default Penalties
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- Info Cards -->
                                    <div class="row g-2 mb-3">
                                        <div class="col-12">
                                            <?php if ($customer['whatsapp_group_link']): ?>
                                                <div class="d-flex align-items-center justify-content-between p-3 rounded-3" style="background: linear-gradient(135deg, rgba(23, 162, 184, 0.08), rgba(19, 132, 150, 0.08)); border: 1px solid rgba(23, 162, 184, 0.2);">
                                                    <div class="d-flex align-items-center">
                                                        <div class="rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px; background: linear-gradient(135deg, #007bff, #706fd3);">
                                                            <i class="bi bi-link-45deg text-white"></i>
                                                        </div>
                                                        <div>
                                                            <small class="text-muted d-block mb-0" style="font-size: 0.7rem; font-weight: 500;">Group Link</small>
                                                            <a href="<?= h($customer['whatsapp_group_link']) ?>" target="_blank" class="text-info text-decoration-none d-block" style="font-size: 0.85rem; font-weight: 500;">
                                                                Open Group <i class="bi bi-box-arrow-up-right ms-1" style="font-size: 0.7rem;"></i>
                                                            </a>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php else: ?>
                                                <div class="d-flex align-items-center justify-content-between p-3 rounded-3" style="background: rgba(248, 249, 250, 0.8); border: 1px solid rgba(0, 0, 0, 0.08);">
                                                    <div class="d-flex align-items-center">
                                                        <div class="rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px; background: rgba(108, 117, 125, 0.1);">
                                                            <i class="bi bi-x-circle text-muted"></i>
                                                        </div>
                                                        <div>
                                                            <small class="text-muted d-block mb-0" style="font-size: 0.7rem; font-weight: 500;">Group Link</small>
                                                            <span class="text-muted d-block" style="font-size: 0.85rem;">No link</span>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="col-12">
                                            <div class="d-flex align-items-center justify-content-between p-3 rounded-3" style="background: rgba(248, 249, 250, 0.8); border: 1px solid rgba(0, 0, 0, 0.08);">
                                                <div class="d-flex align-items-center">
                                                    <div class="rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px; background: rgba(108, 117, 125, 0.1);">
                                                        <i class="bi bi-calendar3 text-muted"></i>
                                                    </div>
                                                    <div>
                                                        <small class="text-muted d-block mb-0" style="font-size: 0.7rem; font-weight: 500;">Created</small>
                                                        <span class="text-dark d-block" style="font-size: 0.9rem; font-weight: 500;"><?= format_date($customer['created_at']) ?></span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Action Buttons -->
                                    <div class="d-grid gap-2">
                                        <div class="btn-group w-100 shadow-sm" role="group">
                                            <a href="<?= BASE_URL ?>/customers/edit.php?id=<?= $customer['id'] ?>" 
                                               class="btn btn-sm btn-primary flex-fill" 
                                               style="border-radius: 8px 0 0 8px; font-weight: 600;">
                                                <i class="bi bi-pencil me-1"></i>Edit
                                            </a>
                                            <a href="<?= BASE_URL ?>/customers/delete.php?id=<?= $customer['id'] ?>" 
                                               class="btn btn-sm btn-danger flex-fill"
                                               class="delete-link"
                                               data-confirm="Are you sure you want to delete this customer? This action cannot be undone."
                                               data-confirm-title="Delete Customer"
                                               data-confirm-type="danger"
                                               style="border-radius: 0 8px 8px 0; font-weight: 600;">
                                                <i class="bi bi-trash me-1"></i>Delete
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div id="noResultsMessage" class="text-center py-5 text-muted d-md-none" style="display: none;">
        <i class="bi bi-search fs-1 d-block mb-2"></i>
        <p class="mb-0">No customers found matching your search</p>
    </div>
</div>

<style>
.customer-row:hover {
    background-color: rgba(0, 123, 255, 0.03) !important;
    transform: translateX(4px);
}

.customer-row:hover .btn-outline-primary,
.customer-row:hover .btn-outline-danger {
    transform: scale(1.1);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
}

@media (max-width: 991px) {
    .customer-mobile-card {
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        border: 1px solid rgba(0, 0, 0, 0.05) !important;
    }
    
    .customer-mobile-card:hover,
    .customer-mobile-card:active {
        transform: translateY(-2px);
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15) !important;
    }
    
    .customer-mobile-card .card-header::after {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 3px;
        background: linear-gradient(90deg, #007bff, #706fd3);
    }
    
    .customer-mobile-card .btn {
        transition: all 0.2s ease;
    }
    
    .customer-mobile-card .btn:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }
}
</style>

<style>
#customerSearch:focus {
    box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.15) !important;
    border-color: #007bff !important;
}

#customerSearch::placeholder {
    color: #adb5bd;
    font-style: italic;
}

#clearSearch:hover {
    color: #dc3545 !important;
    transform: scale(1.1);
    transition: all 0.2s ease;
}

.input-group:focus-within {
    box-shadow: 0 4px 12px rgba(0, 123, 255, 0.2) !important;
    transform: translateY(-1px);
    transition: all 0.3s ease;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('customerSearch');
    const clearSearchBtn = document.getElementById('clearSearch');
    const customersTableBody = document.getElementById('customersTableBody');
    const noResultsMessage = document.getElementById('noResultsMessage');
    const resultCount = document.getElementById('resultCount');
    const customerRows = document.querySelectorAll('.customer-row');
    const noResultsRow = document.querySelector('.no-results-row');
    
    // Search functionality
    searchInput.addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase().trim();
        
        if (searchTerm.length > 0) {
            clearSearchBtn.style.display = 'block';
        } else {
            clearSearchBtn.style.display = 'none';
        }
        
        filterCustomers(searchTerm);
    });
    
    // Clear search
    clearSearchBtn.addEventListener('click', function() {
        searchInput.value = '';
        this.style.display = 'none';
        searchInput.focus();
        filterCustomers('');
    });
    
    function filterCustomers(searchTerm) {
        let visibleCount = 0;
        let hasResults = false;
        const isMobile = window.innerWidth < 768;
        const mobileCards = document.querySelectorAll('.customer-mobile-card');
        
        if (customerRows.length === 0 && noResultsRow) {
            // Handle empty state
            if (searchTerm.length > 0) {
                noResultsRow.style.display = 'none';
                noResultsMessage.style.display = 'block';
            } else {
                noResultsRow.style.display = '';
                noResultsMessage.style.display = 'none';
            }
            // Update result count badge
            resultCount.parentElement.innerHTML = `
                <i class="bi bi-people me-1"></i>
                <span id="resultCount">0</span> 
                <span class="ms-1">customers</span>
            `;
            return;
        }
        
        // Filter desktop table rows
        customerRows.forEach(row => {
            const name = row.dataset.name || '';
            const whatsapp = row.dataset.whatsapp || '';
            
            if (searchTerm.length === 0 || 
                name.includes(searchTerm) || 
                whatsapp.includes(searchTerm)) {
                row.style.display = '';
                visibleCount++;
                hasResults = true;
            } else {
                row.style.display = 'none';
            }
        });
        
        // Filter mobile cards
        if (isMobile && mobileCards.length > 0) {
            visibleCount = 0;
            hasResults = false;
            mobileCards.forEach(card => {
                const name = card.dataset.name || '';
                const whatsapp = card.dataset.whatsapp || '';
                
                if (searchTerm.length === 0 || 
                    name.includes(searchTerm) || 
                    whatsapp.includes(searchTerm)) {
                    card.style.display = '';
                    visibleCount++;
                    hasResults = true;
                } else {
                    card.style.display = 'none';
                }
            });
        }
        
        // Show/hide no results message
        if (searchTerm.length > 0 && !hasResults) {
            if (!isMobile) {
                customersTableBody.style.display = 'none';
            }
            noResultsMessage.style.display = 'block';
        } else {
            if (!isMobile) {
                customersTableBody.style.display = '';
            }
            noResultsMessage.style.display = 'none';
        }
        
        // Update result count with proper singular/plural
        const countText = visibleCount === 1 ? 'customer' : 'customers';
        resultCount.parentElement.innerHTML = `
            <i class="bi bi-people me-1"></i>
            <span id="resultCount">${visibleCount}</span> 
            <span class="ms-1">${countText}</span>
        `;
        // Re-assign reference after innerHTML update
        const newResultCount = document.getElementById('resultCount');
    }
    
    // Handle Enter key to prevent form submission
    searchInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
        }
    });
    
    // Initialize Bootstrap tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>

