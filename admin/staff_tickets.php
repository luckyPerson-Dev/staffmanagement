<?php
/**
 * admin/staff_tickets.php
 * Manage staff ticket counts
 */

require_once __DIR__ . '/../auth_helper.php';
require_once __DIR__ . '/../helpers.php';

require_role(['superadmin', 'admin']);

$pdo = getPDO();

// Get current month/year or from GET
$current_month = isset($_GET['month']) ? intval($_GET['month']) : (int)date('n');
$current_year = isset($_GET['year']) ? intval($_GET['year']) : (int)date('Y');

// Get all staff
$stmt = $pdo->query("
    SELECT id, name, email
    FROM users
    WHERE role = 'staff' AND deleted_at IS NULL
    ORDER BY name
");
$staff = $stmt->fetchAll();

// Get staff tickets for selected month
$stmt = $pdo->prepare("
    SELECT st.user_id, st.ticket_count, u.name
    FROM staff_tickets st
    JOIN users u ON st.user_id = u.id
    WHERE st.month = ? AND st.year = ?
    ORDER BY u.name
");
$stmt->execute([$current_month, $current_year]);
$staff_tickets = $stmt->fetchAll();

// Get total tickets for the month
$stmt = $pdo->prepare("SELECT total_tickets FROM monthly_tickets WHERE month = ? AND year = ?");
$stmt->execute([$current_month, $current_year]);
$monthly_total = $stmt->fetch();
$total_tickets = $monthly_total ? (int)$monthly_total['total_tickets'] : 0;

$page_title = 'Manage Staff Tickets';
include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="mb-4">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3">
            <div>
                <h1 class="gradient-text mb-2 fs-3 fs-md-2">Manage Staff Tickets</h1>
                <p class="text-muted mb-0 small">Set ticket counts for staff members</p>
            </div>
        </div>
    </div>
    
    <!-- Month/Year Filter -->
    <div class="card shadow-lg border-0 mb-4 animate-slide-up">
        <div class="card-body p-3 p-md-4">
            <form method="GET" action="" class="row g-3">
                <div class="col-12 col-md-6 col-lg-3">
                    <label for="month" class="form-label fw-semibold small">
                        <i class="bi bi-calendar me-1"></i>Month
                    </label>
                    <select class="form-select form-select-lg" id="month" name="month" onchange="this.form.submit()">
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?= $m ?>" <?= $m == $current_month ? 'selected' : '' ?>>
                                <?= date('F', mktime(0, 0, 0, $m, 1)) ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-12 col-md-6 col-lg-3">
                    <label for="year" class="form-label fw-semibold small">
                        <i class="bi bi-calendar-year me-1"></i>Year
                    </label>
                    <input type="number" 
                           class="form-control form-control-lg" 
                           id="year" 
                           name="year" 
                           value="<?= $current_year ?>" 
                           min="2000" 
                           max="2100"
                           onchange="this.form.submit()">
                </div>
                <div class="col-12 col-lg-6 d-flex align-items-end">
                    <?php if ($total_tickets > 0): ?>
                        <div class="alert alert-info mb-0 w-100 p-3">
                            <i class="bi bi-info-circle me-2"></i>
                            <strong class="small">Total Tickets for <?= date('F Y', mktime(0, 0, 0, $current_month, 1, $current_year)) ?>:</strong> 
                            <span class="badge bg-primary fs-6 ms-2"><?= number_format($total_tickets) ?></span>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-warning mb-0 w-100 p-3">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            <span class="small">No total tickets set for this month. 
                            <a href="<?= BASE_URL ?>/admin/tickets.php" class="alert-link">Set it here</a>.</span>
                        </div>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Staff Tickets Table -->
    <div class="card shadow-lg border-0 animate-slide-up" style="animation-delay: 0.1s">
        <div class="card-body p-0">
            <!-- Desktop Table View -->
            <div class="table-responsive d-none d-md-block">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light" style="background: linear-gradient(135deg, #f8f9fa, #e9ecef);">
                        <tr>
                            <th class="ps-4 py-3 fw-semibold">Staff Name</th>
                            <th class="py-3 fw-semibold">Ticket Count</th>
                            <th class="py-3 fw-semibold">Computed Percent</th>
                            <th class="text-end pe-4 py-3 fw-semibold">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $total_staff_tickets = 0;
                        $staff_tickets_map = [];
                        foreach ($staff_tickets as $st) {
                            $staff_tickets_map[$st['user_id']] = $st;
                            $total_staff_tickets += $st['ticket_count'];
                        }
                        ?>
                        
                        <?php if (empty($staff)): ?>
                            <tr>
                                <td colspan="4" class="text-center py-5 text-muted">
                                    <i class="bi bi-inbox fs-1 d-block mb-2 text-muted"></i>
                                    <p class="mb-0">No staff members found</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($staff as $s): ?>
                                <?php 
                                $user_tickets = $staff_tickets_map[$s['id']] ?? null;
                                $ticket_count = $user_tickets ? $user_tickets['ticket_count'] : 0;
                                ?>
                                <tr class="staff-ticket-row" data-user-id="<?= $s['id'] ?>" style="transition: all 0.2s ease;">
                                    <td class="ps-4">
                                        <div class="d-flex align-items-center">
                                            <div class="rounded-circle bg-gradient-primary d-flex align-items-center justify-content-center me-3 flex-shrink-0 shadow-sm" 
                                                 style="width: 45px; height: 45px; background: linear-gradient(135deg, #007bff, #706fd3);">
                                                <span class="text-white fw-bold fs-6"><?= strtoupper(substr($s['name'], 0, 1)) ?></span>
                                            </div>
                                            <div>
                                                <strong class="fw-semibold"><?= h($s['name']) ?></strong>
                                                <div class="small text-muted"><?= h($s['email']) ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge px-3 py-2 shadow-sm ticket-count-display" style="background: linear-gradient(135deg, #007bff, #706fd3); border: none; font-size: 1rem;">
                                            <?= number_format($ticket_count) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="ticket-percent-display badge px-3 py-2 shadow-sm" 
                                              data-user-id="<?= $s['id'] ?>"
                                              data-month="<?= $current_month ?>"
                                              data-year="<?= $current_year ?>"
                                              style="background: linear-gradient(135deg, #17a2b8, #138496); border: none;">
                                            <i class="spinner-border spinner-border-sm" style="display: none; width: 1rem; height: 1rem;"></i>
                                            <span class="percent-value">-</span>%
                                        </span>
                                    </td>
                                    <td class="text-end pe-4">
                                        <button class="btn btn-sm btn-outline-primary border-0 shadow-sm edit-staff-ticket-btn" 
                                                data-user-id="<?= $s['id'] ?>"
                                                data-user-name="<?= h($s['name']) ?>"
                                                data-tickets="<?= $ticket_count ?>"
                                                title="Edit Ticket Count"
                                                style="transition: all 0.2s ease;">
                                            <i class="bi bi-pencil"></i> Edit
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            
                            <tr class="table-info">
                                <td class="ps-4"><strong>Total</strong></td>
                                <td>
                                    <strong class="badge px-3 py-2 shadow-sm" style="background: linear-gradient(135deg, #17a2b8, #138496); border: none; font-size: 1rem;">
                                        <?= number_format($total_staff_tickets) ?>
                                    </strong>
                                </td>
                                <td>-</td>
                                <td class="pe-4"></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Mobile Card View -->
            <div class="d-md-none">
                <?php if (empty($staff)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                        <p class="mb-0">No staff members found</p>
                    </div>
                <?php else: ?>
                    <div class="p-2 p-md-3">
                        <?php foreach ($staff as $s): ?>
                            <?php 
                            $user_tickets = $staff_tickets_map[$s['id']] ?? null;
                            $ticket_count = $user_tickets ? $user_tickets['ticket_count'] : 0;
                            ?>
                            <div class="card mb-3 border-0 staff-ticket-mobile-card" style="border-radius: 16px; overflow: hidden;" data-user-id="<?= $s['id'] ?>">
                                <!-- Card Header -->
                                <div class="card-header border-0 p-3" style="background: linear-gradient(135deg, rgba(0, 123, 255, 0.1), rgba(112, 111, 211, 0.1));">
                                    <div class="d-flex align-items-center">
                                        <div class="rounded-circle d-flex align-items-center justify-content-center me-3 flex-shrink-0 shadow" 
                                             style="width: 56px; height: 56px; background: linear-gradient(135deg, #007bff, #706fd3);">
                                            <span class="text-white fw-bold" style="font-size: 1.5rem;"><?= strtoupper(substr($s['name'], 0, 1)) ?></span>
                                        </div>
                                        <div class="flex-grow-1 min-w-0">
                                            <h6 class="mb-1 fw-bold" style="font-size: 1rem; color: #212529;"><?= h($s['name']) ?></h6>
                                            <small class="text-muted d-block" style="font-size: 0.8rem;"><?= h($s['email']) ?></small>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Card Body -->
                                <div class="card-body p-3">
                                    <!-- Info Cards -->
                                    <div class="row g-2 mb-3">
                                        <div class="col-6">
                                            <div class="d-flex align-items-center justify-content-between p-3 rounded-3" style="background: linear-gradient(135deg, rgba(0, 123, 255, 0.08), rgba(112, 111, 211, 0.08)); border: 1px solid rgba(0, 123, 255, 0.2);">
                                                <div class="d-flex align-items-center">
                                                    <div class="rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 36px; height: 36px; background: linear-gradient(135deg, #007bff, #706fd3);">
                                                        <i class="bi bi-ticket-perforated text-white" style="font-size: 0.9rem;"></i>
                                                    </div>
                                                    <div>
                                                        <small class="text-muted d-block mb-0" style="font-size: 0.7rem; font-weight: 500;">Ticket Count</small>
                                                        <strong class="text-primary d-block ticket-count-display" style="font-size: 0.95rem;"><?= number_format($ticket_count) ?></strong>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="d-flex align-items-center justify-content-between p-3 rounded-3" style="background: linear-gradient(135deg, rgba(23, 162, 184, 0.08), rgba(19, 132, 150, 0.08)); border: 1px solid rgba(23, 162, 184, 0.2);">
                                                <div class="d-flex align-items-center">
                                                    <div class="rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 36px; height: 36px; background: linear-gradient(135deg, #17a2b8, #138496);">
                                                        <i class="bi bi-percent text-white" style="font-size: 0.9rem;"></i>
                                                    </div>
                                                    <div>
                                                        <small class="text-muted d-block mb-0" style="font-size: 0.7rem; font-weight: 500;">Percent</small>
                                                        <span class="ticket-percent-display text-info d-block" 
                                                              data-user-id="<?= $s['id'] ?>"
                                                              data-month="<?= $current_month ?>"
                                                              data-year="<?= $current_year ?>"
                                                              style="font-size: 0.95rem; font-weight: 600;">
                                                            <i class="spinner-border spinner-border-sm" style="display: none; width: 0.8rem; height: 0.8rem;"></i>
                                                            <span class="percent-value">-</span>%
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Action Button -->
                                    <div class="d-grid">
                                        <button class="btn btn-sm btn-primary w-100 shadow-sm edit-staff-ticket-btn" 
                                                data-user-id="<?= $s['id'] ?>"
                                                data-user-name="<?= h($s['name']) ?>"
                                                data-tickets="<?= $ticket_count ?>"
                                                style="font-weight: 600;">
                                            <i class="bi bi-pencil me-1"></i>Edit
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        
                        <!-- Total Card -->
                        <div class="card mb-3 border-0" style="background: linear-gradient(135deg, rgba(23, 162, 184, 0.1), rgba(19, 132, 150, 0.1)); border-radius: 16px;">
                            <div class="card-body p-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <strong class="text-info">Total Tickets</strong>
                                    <strong class="badge px-3 py-2" style="background: linear-gradient(135deg, #17a2b8, #138496); border: none; font-size: 1rem;">
                                        <?= number_format($total_staff_tickets) ?>
                                    </strong>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
.staff-ticket-row:hover {
    background-color: rgba(0, 123, 255, 0.03) !important;
    transform: translateX(4px);
}

.staff-ticket-row:hover .btn-outline-primary {
    transform: scale(1.1);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
}

@media (max-width: 991px) {
    .staff-ticket-mobile-card {
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        border: 1px solid rgba(0, 0, 0, 0.05) !important;
    }
    
    .staff-ticket-mobile-card:hover,
    .staff-ticket-mobile-card:active {
        transform: translateY(-2px);
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15) !important;
    }
    
    .staff-ticket-mobile-card .card-header::after {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 3px;
        background: linear-gradient(90deg, #007bff, #706fd3);
    }
    
    .staff-ticket-mobile-card .btn {
        transition: all 0.2s ease;
    }
    
    .staff-ticket-mobile-card .btn:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }
}
</style>

<!-- Edit Modal -->
<div class="modal fade" id="editTicketModal" tabindex="-1" aria-labelledby="editTicketModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow-lg border-0" style="border-radius: 16px; overflow: hidden;">
            <div class="modal-header border-0 pb-3" style="background: linear-gradient(135deg, #007bff, #706fd3); padding: 2rem;">
                <div class="w-100">
                    <div class="rounded-circle bg-white d-inline-flex align-items-center justify-content-center mb-3 shadow-lg" 
                         style="width: 60px; height: 60px;">
                        <i class="bi bi-ticket-perforated" style="font-size: 28px; background: linear-gradient(135deg, #007bff, #706fd3); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;"></i>
                    </div>
                    <h5 class="modal-title text-white fw-bold mb-0" id="editTicketModalLabel">Edit Ticket Count</h5>
                    <p class="text-white-50 mb-0 mt-2 small">Update ticket count for staff member</p>
                </div>
                <button type="button" class="btn-close btn-close-white position-absolute top-0 end-0 m-3" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <form id="editTicketForm">
                    <input type="hidden" id="edit_user_id" name="user_id">
                    <input type="hidden" id="edit_month" name="month" value="<?= $current_month ?>">
                    <input type="hidden" id="edit_year" name="year" value="<?= $current_year ?>">
                    
                    <div class="mb-4">
                        <label class="form-label fw-semibold">
                            <i class="bi bi-person me-1"></i>Staff Member
                        </label>
                        <input type="text" class="form-control form-control-lg" id="edit_user_name" readonly style="background: #f8f9fa;">
                    </div>
                    
                    <div class="mb-4">
                        <label for="edit_ticket_count" class="form-label fw-semibold">
                            <i class="bi bi-ticket-perforated me-1"></i>Ticket Count <span class="text-danger">*</span>
                        </label>
                        <input type="number" 
                               class="form-control form-control-lg" 
                               id="edit_ticket_count" 
                               name="ticket_count" 
                               min="0" 
                               required
                               placeholder="Enter ticket count">
                        <small class="text-muted">
                            <i class="bi bi-info-circle me-1"></i>
                            Total tickets for this month: <strong><?= number_format($total_tickets) ?></strong>
                        </small>
                    </div>
                    
                    <div id="editFormMessage"></div>
                </form>
            </div>
            <div class="modal-footer border-0 pt-0 pb-4 px-4">
                <button type="button" class="btn btn-outline-secondary btn-lg px-4" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle me-2"></i>Cancel
                </button>
                <button type="button" class="btn btn-primary btn-lg px-4" id="saveTicketBtn"
                        style="background: linear-gradient(135deg, #007bff, #706fd3); border: none;">
                    <i class="bi bi-check-circle me-2"></i>Save
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const editModal = new bootstrap.Modal(document.getElementById('editTicketModal'));
    const editForm = document.getElementById('editTicketForm');
    const messageDiv = document.getElementById('editFormMessage');
    
    // Load ticket percentages
    function loadTicketPercent(userId, month, year, element) {
        const spinner = element.querySelector('.spinner-border');
        const valueSpan = element.querySelector('.percent-value');
        
        spinner.style.display = 'inline-block';
        valueSpan.textContent = '-';
        
        fetch(`<?= BASE_URL ?>/api/tickets/compute_user_percent.php?user_id=${userId}&month=${month}&year=${year}`)
            .then(response => response.json())
            .then(data => {
                spinner.style.display = 'none';
                if (data.success) {
                    valueSpan.textContent = data.ticket_percent.toFixed(2);
                } else {
                    valueSpan.textContent = 'Error';
                }
            })
            .catch(error => {
                spinner.style.display = 'none';
                valueSpan.textContent = 'Error';
            });
    }
    
    // Load all ticket percentages
    document.querySelectorAll('.ticket-percent-display').forEach(el => {
        const userId = parseInt(el.dataset.userId);
        const month = parseInt(el.dataset.month);
        const year = parseInt(el.dataset.year);
        loadTicketPercent(userId, month, year, el);
    });
    
    // Handle edit button clicks
    document.querySelectorAll('.edit-staff-ticket-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.getElementById('edit_user_id').value = this.dataset.userId;
            document.getElementById('edit_user_name').value = this.dataset.userName;
            document.getElementById('edit_ticket_count').value = this.dataset.tickets;
            messageDiv.innerHTML = '';
            editModal.show();
        });
    });
    
    // Handle save button
    document.getElementById('saveTicketBtn').addEventListener('click', async function() {
        const formData = {
            user_id: parseInt(document.getElementById('edit_user_id').value),
            month: parseInt(document.getElementById('edit_month').value),
            year: parseInt(document.getElementById('edit_year').value),
            ticket_count: parseInt(document.getElementById('edit_ticket_count').value)
        };
        
        try {
            const response = await fetch('<?= BASE_URL ?>/api/tickets/set_staff.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(formData)
            });
            
            const data = await response.json();
            
            if (data.success) {
                messageDiv.innerHTML = '<div class="alert alert-success"><i class="bi bi-check-circle me-2"></i>' + data.message + '</div>';
                setTimeout(() => {
                    location.reload();
                }, 1000);
            } else {
                messageDiv.innerHTML = '<div class="alert alert-danger"><i class="bi bi-exclamation-circle me-2"></i>' + data.message + '</div>';
            }
        } catch (error) {
            messageDiv.innerHTML = '<div class="alert alert-danger"><i class="bi bi-exclamation-circle me-2"></i>Error: ' + error.message + '</div>';
        }
    });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>

