<?php
/**
 * admin/tickets.php
 * Manage monthly total tickets
 */

require_once __DIR__ . '/../auth_helper.php';
require_once __DIR__ . '/../helpers.php';

require_role(['superadmin', 'admin']);

$pdo = getPDO();

// Get current month/year or from GET
$current_month = isset($_GET['month']) ? intval($_GET['month']) : (int)date('n');
$current_year = isset($_GET['year']) ? intval($_GET['year']) : (int)date('Y');

// Get last 12 months of tickets
$stmt = $pdo->prepare("
    SELECT month, year, total_tickets, created_at
    FROM monthly_tickets
    ORDER BY year DESC, month DESC
    LIMIT 12
");
$stmt->execute();
$monthly_tickets = $stmt->fetchAll();

$page_title = 'Manage Monthly Tickets';
include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="mb-4">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3">
            <div>
                <h1 class="gradient-text mb-2 fs-3 fs-md-2">Manage Monthly Tickets</h1>
                <p class="text-muted mb-0 small">Set total tickets for each month</p>
            </div>
            <button type="button" class="btn btn-primary btn-lg w-100 w-md-auto shadow-sm" data-bs-toggle="modal" data-bs-target="#ticketModal">
                <i class="bi bi-plus-circle me-2"></i>Add/Edit Tickets
            </button>
        </div>
    </div>
    
    <div class="card shadow-lg border-0 animate-slide-up">
        <div class="card-body p-0">
            <!-- Desktop Table View -->
            <div class="table-responsive d-none d-md-block">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light" style="background: linear-gradient(135deg, #f8f9fa, #e9ecef);">
                        <tr>
                            <th class="ps-4 py-3 fw-semibold">Month/Year</th>
                            <th class="py-3 fw-semibold">Total Tickets</th>
                            <th class="py-3 fw-semibold">Created</th>
                            <th class="text-end pe-4 py-3 fw-semibold">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($monthly_tickets)): ?>
                            <tr>
                                <td colspan="4" class="text-center py-5 text-muted">
                                    <i class="bi bi-inbox fs-1 d-block mb-2 text-muted"></i>
                                    <p class="mb-0">No tickets recorded yet</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($monthly_tickets as $ticket): ?>
                                <tr class="ticket-row" style="transition: all 0.2s ease;">
                                    <td class="ps-4">
                                        <div class="d-flex align-items-center">
                                            <div class="rounded-circle bg-light d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
                                                <i class="bi bi-calendar3 text-primary"></i>
                                            </div>
                                            <strong class="fw-semibold"><?= date('F Y', mktime(0, 0, 0, $ticket['month'], 1, $ticket['year'])) ?></strong>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge px-3 py-2 shadow-sm" style="background: linear-gradient(135deg, #007bff, #706fd3); border: none; font-size: 1rem;">
                                            <?= number_format($ticket['total_tickets']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="rounded-circle bg-light d-flex align-items-center justify-content-center me-2" style="width: 32px; height: 32px;">
                                                <i class="bi bi-clock text-muted"></i>
                                            </div>
                                            <span class="text-muted"><?= format_date($ticket['created_at']) ?></span>
                                        </div>
                                    </td>
                                    <td class="text-end pe-4">
                                        <button class="btn btn-sm btn-outline-primary border-0 shadow-sm edit-ticket-btn" 
                                                data-month="<?= $ticket['month'] ?>" 
                                                data-year="<?= $ticket['year'] ?>" 
                                                data-tickets="<?= $ticket['total_tickets'] ?>"
                                                data-bs-toggle="modal" 
                                                data-bs-target="#ticketModal"
                                                style="transition: all 0.2s ease;">
                                            <i class="bi bi-pencil"></i> Edit
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Mobile Card View -->
            <div class="d-md-none">
                <?php if (empty($monthly_tickets)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                        <p class="mb-0">No tickets recorded yet</p>
                    </div>
                <?php else: ?>
                    <div class="p-2 p-md-3">
                        <?php foreach ($monthly_tickets as $ticket): ?>
                            <div class="card mb-3 border-0 ticket-mobile-card" style="border-radius: 16px; overflow: hidden;">
                                <!-- Card Header -->
                                <div class="card-header border-0 p-3" style="background: linear-gradient(135deg, rgba(0, 123, 255, 0.1), rgba(112, 111, 211, 0.1));">
                                    <div class="d-flex align-items-center">
                                        <div class="rounded-circle d-flex align-items-center justify-content-center me-3 flex-shrink-0 shadow" 
                                             style="width: 56px; height: 56px; background: linear-gradient(135deg, #007bff, #706fd3);">
                                            <i class="bi bi-calendar3 text-white" style="font-size: 1.5rem;"></i>
                                        </div>
                                        <div class="flex-grow-1 min-w-0">
                                            <h6 class="mb-1 fw-bold" style="font-size: 1rem; color: #212529;"><?= date('F Y', mktime(0, 0, 0, $ticket['month'], 1, $ticket['year'])) ?></h6>
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
                                                        <small class="text-muted d-block mb-0" style="font-size: 0.7rem; font-weight: 500;">Total Tickets</small>
                                                        <strong class="text-primary d-block" style="font-size: 0.95rem;"><?= number_format($ticket['total_tickets']) ?></strong>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="d-flex align-items-center justify-content-between p-3 rounded-3" style="background: rgba(248, 249, 250, 0.8); border: 1px solid rgba(0, 0, 0, 0.08);">
                                                <div class="d-flex align-items-center">
                                                    <div class="rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 36px; height: 36px; background: rgba(108, 117, 125, 0.1);">
                                                        <i class="bi bi-clock text-muted"></i>
                                                    </div>
                                                    <div>
                                                        <small class="text-muted d-block mb-0" style="font-size: 0.7rem; font-weight: 500;">Created</small>
                                                        <span class="text-dark d-block" style="font-size: 0.85rem; font-weight: 500;"><?= format_date($ticket['created_at']) ?></span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Action Button -->
                                    <div class="d-grid">
                                        <button class="btn btn-sm btn-primary w-100 shadow-sm edit-ticket-btn" 
                                                data-month="<?= $ticket['month'] ?>" 
                                                data-year="<?= $ticket['year'] ?>" 
                                                data-tickets="<?= $ticket['total_tickets'] ?>"
                                                data-bs-toggle="modal" 
                                                data-bs-target="#ticketModal"
                                                style="font-weight: 600;">
                                            <i class="bi bi-pencil me-1"></i>Edit
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
.ticket-row:hover {
    background-color: rgba(0, 123, 255, 0.03) !important;
    transform: translateX(4px);
}

.ticket-row:hover .btn-outline-primary {
    transform: scale(1.1);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
}

@media (max-width: 991px) {
    .ticket-mobile-card {
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        border: 1px solid rgba(0, 0, 0, 0.05) !important;
    }
    
    .ticket-mobile-card:hover,
    .ticket-mobile-card:active {
        transform: translateY(-2px);
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15) !important;
    }
    
    .ticket-mobile-card .card-header::after {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 3px;
        background: linear-gradient(90deg, #007bff, #706fd3);
    }
    
    .ticket-mobile-card .btn {
        transition: all 0.2s ease;
    }
    
    .ticket-mobile-card .btn:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }
}
</style>

<!-- Add/Edit Modal -->
<div class="modal fade" id="ticketModal" tabindex="-1" aria-labelledby="ticketModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="ticketModalLabel">
                    <i class="bi bi-ticket-perforated me-2"></i>Set Total Tickets
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="ticketsForm">
                    <div class="mb-3">
                        <label for="month" class="form-label">Month</label>
                        <select class="form-select" id="month" name="month" required>
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                                <option value="<?= $m ?>" <?= $m == $current_month ? 'selected' : '' ?>>
                                    <?= date('F', mktime(0, 0, 0, $m, 1)) ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="year" class="form-label">Year</label>
                        <input type="number" class="form-control" id="year" name="year" 
                               value="<?= $current_year ?>" min="2000" max="2100" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="total_tickets" class="form-label">Total Tickets</label>
                        <input type="number" class="form-control" id="total_tickets" name="total_tickets" 
                               min="0" required placeholder="Enter total tickets">
                    </div>
                    
                    <div id="formMessage"></div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="saveTicketBtn">
                    <i class="bi bi-save me-2"></i>Save
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('ticketsForm');
    const messageDiv = document.getElementById('formMessage');
    const ticketModal = new bootstrap.Modal(document.getElementById('ticketModal'));
    const saveBtn = document.getElementById('saveTicketBtn');
    
    // Reset form when modal is opened
    document.getElementById('ticketModal').addEventListener('show.bs.modal', function() {
        messageDiv.innerHTML = '';
        document.getElementById('total_tickets').value = '';
    });
    
    // Handle edit button clicks
    document.querySelectorAll('.edit-ticket-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.getElementById('month').value = this.dataset.month;
            document.getElementById('year').value = this.dataset.year;
            document.getElementById('total_tickets').value = this.dataset.tickets;
        });
    });
    
    // Handle save button click
    saveBtn.addEventListener('click', async function() {
        const month = parseInt(document.getElementById('month').value);
        const year = parseInt(document.getElementById('year').value);
        const totalTickets = parseInt(document.getElementById('total_tickets').value);
        
        if (!month || !year || totalTickets < 0) {
            messageDiv.innerHTML = '<div class="alert alert-danger"><i class="bi bi-exclamation-circle me-2"></i>Please fill all fields correctly.</div>';
            return;
        }
        
        const formData = {
            month: month,
            year: year,
            total_tickets: totalTickets
        };
        
        try {
            const response = await fetch('<?= BASE_URL ?>/api/tickets/set_total.php', {
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
    
    // Handle form submission (Enter key)
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        saveBtn.click();
    });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>

