<?php
/**
 * admin/advance_deduction.php
 * Admin panel for managing advance auto-deductions
 */

require_once __DIR__ . '/../auth_helper.php';
require_once __DIR__ . '/../helpers.php';

require_role(['admin', 'superadmin']);

$pdo = getPDO();
$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token.';
    } else {
        $action = $_POST['action'] ?? '';
        $user_id = intval($_POST['user_id'] ?? 0);
        
        if (!$user_id) {
            $error = 'Please select a staff member.';
        } else {
            try {
                if ($action === 'create' || $action === 'update') {
                    $monthly_deduction = floatval($_POST['monthly_deduction'] ?? 0);
                    $total_advance = floatval($_POST['total_advance'] ?? 0);
                    $remaining_due = floatval($_POST['remaining_due'] ?? 0);
                    
                    // Validation
                    if ($monthly_deduction <= 0) {
                        $error = 'Monthly deduction must be greater than 0.';
                    } elseif ($total_advance <= 0) {
                        $error = 'Total advance must be greater than 0.';
                    } elseif ($remaining_due < 0) {
                        $error = 'Remaining due cannot be negative.';
                    } elseif ($remaining_due > $total_advance) {
                        $error = 'Remaining due cannot exceed total advance.';
                    } elseif ($monthly_deduction > $remaining_due) {
                        $error = 'Monthly deduction cannot exceed remaining due.';
                    } else {
                        // Auto-calculate remaining_due if not provided
                        if ($remaining_due == 0 && $total_advance > 0) {
                            // Get existing deduction record if any
                            $stmt = $pdo->prepare("
                                SELECT remaining_due FROM advance_auto_deductions 
                                WHERE user_id = ? AND status = 'active'
                            ");
                            $stmt->execute([$user_id]);
                            $existing = $stmt->fetch();
                            
                            if ($existing) {
                                // Add to existing remaining_due
                                $remaining_due = floatval($existing['remaining_due']) + $total_advance;
                            } else {
                                $remaining_due = $total_advance;
                            }
                        }
                        
                        // Check if active record exists
                        $stmt = $pdo->prepare("
                            SELECT id FROM advance_auto_deductions 
                            WHERE user_id = ? AND status = 'active'
                        ");
                        $stmt->execute([$user_id]);
                        $existing_record = $stmt->fetch();
                        
                        if ($existing_record) {
                            // Update existing record
                            $stmt = $pdo->prepare("
                                UPDATE advance_auto_deductions 
                                SET monthly_deduction = ?, 
                                    total_advance = ?,
                                    remaining_due = ?,
                                    status = CASE 
                                        WHEN ? <= 0 THEN 'completed'
                                        ELSE 'active'
                                    END,
                                    updated_at = UTC_TIMESTAMP()
                                WHERE id = ?
                            ");
                            $stmt->execute([
                                $monthly_deduction,
                                $total_advance,
                                $remaining_due,
                                $remaining_due,
                                $existing_record['id']
                            ]);
                            
                            log_audit(
                                current_user()['id'], 
                                'update', 
                                'advance_auto_deductions', 
                                $existing_record['id'], 
                                "Updated auto-deduction for user $user_id"
                            );
                            $success = 'Auto-deduction schedule updated successfully.';
                        } else {
                            // Create new record
                            $status = ($remaining_due <= 0) ? 'completed' : 'active';
                            $stmt = $pdo->prepare("
                                INSERT INTO advance_auto_deductions 
                                (user_id, monthly_deduction, total_advance, remaining_due, status, created_at)
                                VALUES (?, ?, ?, ?, ?, UTC_TIMESTAMP())
                            ");
                            $stmt->execute([
                                $user_id,
                                $monthly_deduction,
                                $total_advance,
                                $remaining_due,
                                $status
                            ]);
                            
                            $deduction_id = $pdo->lastInsertId();
                            log_audit(
                                current_user()['id'], 
                                'create', 
                                'advance_auto_deductions', 
                                $deduction_id, 
                                "Created auto-deduction for user $user_id"
                            );
                            $success = 'Auto-deduction schedule created successfully.';
                        }
                    }
                } elseif ($action === 'delete') {
                    $deduction_id = intval($_POST['deduction_id'] ?? 0);
                    if ($deduction_id) {
                        $stmt = $pdo->prepare("DELETE FROM advance_auto_deductions WHERE id = ?");
                        $stmt->execute([$deduction_id]);
                        log_audit(
                            current_user()['id'], 
                            'delete', 
                            'advance_auto_deductions', 
                            $deduction_id, 
                            "Deleted auto-deduction record"
                        );
                        $success = 'Auto-deduction record deleted successfully.';
                    }
                }
            } catch (Exception $e) {
                $error = 'Error: ' . $e->getMessage();
                error_log("Advance deduction error: " . $e->getMessage());
            }
        }
    }
}

// Get staff list
$stmt = $pdo->query("
    SELECT id, name FROM users 
    WHERE role = 'staff' AND deleted_at IS NULL 
    ORDER BY name
");
$staff_list = $stmt->fetchAll();

// Get selected user's data
$selected_user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
$deduction_data = null;
$user_advances = [];

if ($selected_user_id) {
    // Get deduction record
    $stmt = $pdo->prepare("
        SELECT * FROM advance_auto_deductions 
        WHERE user_id = ? AND status = 'active'
        ORDER BY created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$selected_user_id]);
    $deduction_data = $stmt->fetch();
    
    // Get all approved advances for this user
    $stmt = $pdo->prepare("
        SELECT * FROM advances 
        WHERE user_id = ? AND status = 'approved'
        ORDER BY created_at DESC
    ");
    $stmt->execute([$selected_user_id]);
    $user_advances = $stmt->fetchAll();
}

// Get all deduction records for listing
$stmt = $pdo->query("
    SELECT aad.*, u.name as user_name
    FROM advance_auto_deductions aad
    JOIN users u ON aad.user_id = u.id
    WHERE u.deleted_at IS NULL
    ORDER BY aad.created_at DESC
");
$all_deductions = $stmt->fetchAll();

// Get deduction history from salary_history
$history_filter_user = isset($_GET['history_user_id']) ? intval($_GET['history_user_id']) : 0;
$history_where = "sh.advances_deducted > 0";
$history_params = [];

if ($history_filter_user > 0) {
    $history_where .= " AND sh.user_id = ?";
    $history_params[] = $history_filter_user;
}

$stmt = $pdo->prepare("
    SELECT 
        sh.id,
        sh.user_id,
        sh.month,
        sh.year,
        sh.advances_deducted,
        sh.net_payable,
        sh.status as salary_status,
        sh.approved_at,
        sh.approved_by,
        u.name as user_name,
        approver.name as approved_by_name
    FROM salary_history sh
    JOIN users u ON sh.user_id = u.id
    LEFT JOIN users approver ON sh.approved_by = approver.id
    WHERE {$history_where} AND u.deleted_at IS NULL
    ORDER BY sh.year DESC, sh.month DESC, sh.approved_at DESC
    LIMIT 100
");
$stmt->execute($history_params);
$deduction_history = $stmt->fetchAll();

$page_title = 'Advance Auto-Deduction Management';
include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="mb-4">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3">
            <div>
                <h1 class="gradient-text mb-2 fs-3 fs-md-2">Advance Auto-Deduction Management</h1>
                <p class="text-muted mb-0 small">Configure automatic monthly deductions for staff advances</p>
            </div>
        </div>
    </div>
    
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show animate-fade-in mb-4" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <span class="small"><?= h($error) ?></span>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show animate-fade-in mb-4" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i>
            <span class="small"><?= h($success) ?></span>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <div class="row">
        <!-- Configuration Form -->
        <div class="col-lg-8 mb-4">
            <div class="card shadow-lg border-0 animate-slide-up">
                <div class="card-header bg-white border-bottom p-3 p-md-4">
                    <h5 class="mb-0 fw-semibold">
                        <i class="bi bi-gear me-2 text-primary"></i>Configure Auto-Deduction
                    </h5>
                    <small class="text-muted d-block mt-1">Set up automatic monthly deductions for staff advances</small>
                </div>
                <div class="card-body p-3 p-md-4">
                    <form method="GET" action="" class="mb-4">
                        <div class="form-group-card p-3 rounded-3 mb-4" style="background: linear-gradient(135deg, rgba(0, 123, 255, 0.05), rgba(112, 111, 211, 0.05)); border: 1px solid rgba(0, 123, 255, 0.1);">
                            <div class="row g-3">
                                <div class="col-md-8">
                                    <label for="user_id" class="form-label fw-semibold mb-2">
                                        <i class="bi bi-person me-2 text-primary"></i>Select Staff <span class="text-danger">*</span>
                                    </label>
                                    <select class="form-select form-select-lg shadow-sm" id="user_id" name="user_id" required onchange="this.form.submit()">
                                        <option value="">-- Select Staff Member --</option>
                                        <?php foreach ($staff_list as $staff): ?>
                                            <option value="<?= $staff['id'] ?>" <?= $selected_user_id == $staff['id'] ? 'selected' : '' ?>>
                                                <?= h($staff['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4 d-flex align-items-end">
                                    <button type="submit" class="btn btn-primary btn-lg w-100 shadow-sm">
                                        <i class="bi bi-search me-1"></i>Load
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                    
                    <?php if ($selected_user_id): ?>
                        <!-- Show approved advances -->
                        <?php if (!empty($user_advances)): ?>
                            <div class="alert alert-info mb-4 shadow-sm" style="background: linear-gradient(135deg, rgba(23, 162, 184, 0.1), rgba(19, 132, 150, 0.1)); border: 1px solid rgba(23, 162, 184, 0.3);">
                                <h6 class="alert-heading fw-semibold mb-3">
                                    <i class="bi bi-info-circle me-2"></i>Approved Advances for <?= h($staff_list[array_search($selected_user_id, array_column($staff_list, 'id'))]['name'] ?? 'Staff') ?>
                                </h6>
                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered mb-0">
                                        <thead>
                                            <tr>
                                                <th>Amount</th>
                                                <th>Reason</th>
                                                <th>Approved Date</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            $total_approved = 0;
                                            foreach ($user_advances as $adv): 
                                                $total_approved += floatval($adv['amount']);
                                            ?>
                                                <tr>
                                                    <td><strong>৳<?= number_format($adv['amount'], 2) ?></strong></td>
                                                    <td><?= h($adv['reason']) ?></td>
                                                    <td><?= $adv['approved_at'] ? date('M d, Y', strtotime($adv['approved_at'])) : '-' ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                            <tr class="table-success">
                                                <td colspan="2"><strong>Total Approved Advances:</strong></td>
                                                <td><strong>৳<?= number_format($total_approved, 2) ?></strong></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-warning mb-4 shadow-sm" style="background: linear-gradient(135deg, rgba(255, 193, 7, 0.1), rgba(255, 152, 0, 0.1)); border: 1px solid rgba(255, 193, 7, 0.3);">
                                <i class="bi bi-exclamation-triangle me-2"></i>No approved advances found for this staff member.
                            </div>
                        <?php endif; ?>
                        
                        <!-- Deduction Form -->
                        <form method="POST" action="" id="deductionForm">
                            <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                            <input type="hidden" name="action" value="<?= $deduction_data ? 'update' : 'create' ?>">
                            <input type="hidden" name="user_id" value="<?= $selected_user_id ?>">
                            
                            <div class="row g-3 g-md-4">
                                <div class="col-md-6">
                                    <div class="form-group-card p-3 rounded-3" style="background: linear-gradient(135deg, rgba(40, 167, 69, 0.05), rgba(32, 201, 151, 0.05)); border: 1px solid rgba(40, 167, 69, 0.1);">
                                        <label for="total_advance" class="form-label fw-semibold mb-2">
                                            <i class="bi bi-cash-stack me-2 text-success"></i>Total Advance <span class="text-danger">*</span>
                                        </label>
                                        <input type="number" 
                                               step="0.01" 
                                               class="form-control form-control-lg shadow-sm" 
                                               id="total_advance" 
                                               name="total_advance" 
                                               value="<?= $deduction_data ? number_format($deduction_data['total_advance'], 2, '.', '') : number_format($total_approved ?? 0, 2, '.', '') ?>" 
                                               required
                                               onchange="updateRemainingDue()">
                                        <small class="text-muted d-block mt-2">
                                            <i class="bi bi-info-circle me-1"></i>Total advance amount to be deducted
                                        </small>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="form-group-card p-3 rounded-3" style="background: linear-gradient(135deg, rgba(0, 123, 255, 0.05), rgba(112, 111, 211, 0.05)); border: 1px solid rgba(0, 123, 255, 0.1);">
                                        <label for="monthly_deduction" class="form-label fw-semibold mb-2">
                                            <i class="bi bi-calendar-month me-2 text-primary"></i>Monthly Deduction <span class="text-danger">*</span>
                                        </label>
                                        <input type="number" 
                                               step="0.01" 
                                               class="form-control form-control-lg shadow-sm" 
                                               id="monthly_deduction" 
                                               name="monthly_deduction" 
                                               value="<?= $deduction_data ? number_format($deduction_data['monthly_deduction'], 2, '.', '') : '' ?>" 
                                               required
                                               onchange="updateRemainingDue()">
                                        <small class="text-muted d-block mt-2">
                                            <i class="bi bi-info-circle me-1"></i>Amount to deduct per month
                                        </small>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="form-group-card p-3 rounded-3" style="background: linear-gradient(135deg, rgba(255, 193, 7, 0.05), rgba(255, 152, 0, 0.05)); border: 1px solid rgba(255, 193, 7, 0.1);">
                                        <label for="remaining_due" class="form-label fw-semibold mb-2">
                                            <i class="bi bi-calculator me-2 text-warning"></i>Remaining Due <span class="text-danger">*</span>
                                        </label>
                                        <input type="number" 
                                               step="0.01" 
                                               class="form-control form-control-lg shadow-sm bg-light" 
                                               id="remaining_due" 
                                               name="remaining_due" 
                                               value="<?= $deduction_data ? number_format($deduction_data['remaining_due'], 2, '.', '') : number_format($total_approved ?? 0, 2, '.', '') ?>" 
                                               required
                                               readonly>
                                        <small class="text-muted d-block mt-2">
                                            <i class="bi bi-info-circle me-1"></i>Auto-calculated remaining amount
                                        </small>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="form-group-card p-3 rounded-3" style="background: linear-gradient(135deg, rgba(108, 117, 125, 0.05), rgba(73, 80, 87, 0.05)); border: 1px solid rgba(108, 117, 125, 0.1);">
                                        <label class="form-label fw-semibold mb-2">
                                            <i class="bi bi-info-circle me-2 text-muted"></i>Status
                                        </label>
                                        <div class="form-control form-control-lg bg-light shadow-sm">
                                            <?php if ($deduction_data): ?>
                                                <span class="badge bg-<?= $deduction_data['status'] === 'active' ? 'success' : 'secondary' ?> px-3 py-2">
                                                    <?= ucfirst($deduction_data['status']) ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-success px-3 py-2">Active</span>
                                            <?php endif; ?>
                                        </div>
                                        <small class="text-muted d-block mt-2">
                                            <i class="bi bi-info-circle me-1"></i>Current deduction status
                                        </small>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mt-4 d-grid gap-2 d-md-flex justify-content-md-start">
                                <button type="submit" class="btn btn-primary btn-lg shadow-sm">
                                    <i class="bi bi-check-circle me-2"></i>
                                    <?= $deduction_data ? 'Update' : 'Create' ?> Deduction Schedule
                                </button>
                                <?php if ($deduction_data): ?>
                                    <button type="button" 
                                            class="btn btn-danger btn-lg shadow-sm" 
                                            onclick="deleteDeduction(<?= $deduction_data['id'] ?>)">
                                        <i class="bi bi-trash me-2"></i>Delete
                                    </button>
                                <?php endif; ?>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- All Deductions List -->
        <div class="col-lg-4">
            <div class="card shadow-lg border-0 animate-slide-up" style="animation-delay: 0.1s">
                <div class="card-header bg-white border-bottom p-3 p-md-4">
                    <h5 class="mb-0 fw-semibold">
                        <i class="bi bi-list-ul me-2 text-primary"></i>All Deduction Schedules
                    </h5>
                    <small class="text-muted d-block mt-1">Active and completed deduction schedules</small>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive" style="max-height: 600px; overflow-y: auto;">
                        <table class="table table-hover mb-0">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th>Staff</th>
                                    <th>Monthly</th>
                                    <th>Remaining</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($all_deductions)): ?>
                                    <tr>
                                        <td colspan="4" class="text-center text-muted py-4">
                                            No deduction schedules found
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($all_deductions as $ded): ?>
                                        <tr style="cursor: pointer;" onclick="window.location='?user_id=<?= $ded['user_id'] ?>'">
                                            <td>
                                                <strong><?= h($ded['user_name']) ?></strong>
                                            </td>
                                            <td>৳<?= number_format($ded['monthly_deduction'], 2) ?></td>
                                            <td>৳<?= number_format($ded['remaining_due'], 2) ?></td>
                                            <td>
                                                <span class="badge bg-<?= $ded['status'] === 'active' ? 'success' : 'secondary' ?>">
                                                    <?= ucfirst($ded['status']) ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Deduction History Section -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card shadow-lg border-0 animate-slide-up" style="animation-delay: 0.2s">
                <div class="card-header bg-white border-bottom p-3 p-md-4">
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3">
                        <div>
                            <h5 class="mb-0 fw-semibold">
                                <i class="bi bi-clock-history me-2 text-primary"></i>Deduction History
                            </h5>
                            <small class="text-muted d-block mt-1">History of advance deductions applied during payroll</small>
                        </div>
                        <form method="GET" action="" class="d-flex gap-2">
                            <input type="hidden" name="user_id" value="<?= $selected_user_id ?>">
                            <select name="history_user_id" class="form-select form-select-sm" onchange="this.form.submit()">
                                <option value="0">All Staff</option>
                                <?php foreach ($staff_list as $staff): ?>
                                    <option value="<?= $staff['id'] ?>" <?= $history_filter_user == $staff['id'] ? 'selected' : '' ?>>
                                        <?= h($staff['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                    </div>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($deduction_history)): ?>
                        <div class="text-center py-5 text-muted">
                            <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                            <p class="mb-0">No deduction history found</p>
                            <small class="text-muted d-block mt-2">Deductions will appear here once they are applied during payroll processing</small>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="ps-4">Staff</th>
                                        <th>Month/Year</th>
                                        <th>Amount Deducted</th>
                                        <th>Net Payable</th>
                                        <th>Status</th>
                                        <th>Approved By</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $total_deducted = 0;
                                    foreach ($deduction_history as $history): 
                                        $total_deducted += floatval($history['advances_deducted']);
                                    ?>
                                        <tr>
                                            <td class="ps-4">
                                                <div class="d-flex align-items-center">
                                                    <div class="rounded-circle bg-gradient-primary d-flex align-items-center justify-content-center me-2" 
                                                         style="width: 32px; height: 32px; background: linear-gradient(135deg, #007bff, #706fd3);">
                                                        <span class="text-white fw-bold small"><?= strtoupper(substr($history['user_name'], 0, 1)) ?></span>
                                                    </div>
                                                    <strong><?= h($history['user_name']) ?></strong>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-info bg-gradient">
                                                    <?= date('M Y', mktime(0, 0, 0, $history['month'], 1, $history['year'])) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <strong class="text-danger">-৳<?= number_format($history['advances_deducted'], 2) ?></strong>
                                            </td>
                                            <td>
                                                <strong class="text-success">৳<?= number_format($history['net_payable'], 2) ?></strong>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?= $history['salary_status'] === 'paid' ? 'success' : ($history['salary_status'] === 'approved' ? 'primary' : 'warning') ?>">
                                                    <?= ucfirst($history['salary_status']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?= $history['approved_by_name'] ? h($history['approved_by_name']) : '<span class="text-muted">-</span>' ?>
                                            </td>
                                            <td>
                                                <?= $history['approved_at'] ? date('M d, Y', strtotime($history['approved_at'])) : '<span class="text-muted">-</span>' ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <tr class="table-success">
                                        <td colspan="2" class="ps-4"><strong>Total Deducted:</strong></td>
                                        <td><strong class="text-danger">-৳<?= number_format($total_deducted, 2) ?></strong></td>
                                        <td colspan="4"></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function updateRemainingDue() {
    const totalAdvance = parseFloat(document.getElementById('total_advance').value) || 0;
    const monthlyDeduction = parseFloat(document.getElementById('monthly_deduction').value) || 0;
    
    // Remaining due starts as total advance (will be updated by payroll)
    document.getElementById('remaining_due').value = totalAdvance.toFixed(2);
}

async function deleteDeduction(id) {
    const confirmed = await Notify.confirm(
        'Are you sure you want to delete this deduction schedule?',
        'Delete Deduction Schedule',
        'Delete',
        'Cancel',
        'danger'
    );
    if (!confirmed) {
        return;
    }
    
    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = `
        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="deduction_id" value="${id}">
    `;
    document.body.appendChild(form);
    form.submit();
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    updateRemainingDue();
});
</script>

<style>
.form-group-card {
    transition: all 0.3s ease;
}

.form-group-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.form-group-card input:focus,
.form-group-card select:focus {
    border-color: var(--color-electric-blue);
    box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.15);
}

@media (max-width: 767px) {
    .form-group-card {
        margin-bottom: 1rem;
    }
    
    .card-body {
        padding: 1rem !important;
    }
    
    .card-header {
        padding: 1rem !important;
    }
}
</style>

<?php include __DIR__ . '/../includes/footer.php'; ?>

