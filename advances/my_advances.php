<?php
/**
 * advances/my_advances.php
 * View own advance requests
 */

require_once __DIR__ . '/../auth_helper.php';
require_once __DIR__ . '/../helpers.php';

require_login();

$user = current_user();
$pdo = getPDO();

// Get advance requests
$stmt = $pdo->prepare("
    SELECT a.*, u.name as approved_by_name
    FROM advances a
    LEFT JOIN users u ON a.approved_by = u.id
    WHERE a.user_id = ?
    ORDER BY a.created_at DESC
");
$stmt->execute([$user['id']]);
$advances = $stmt->fetchAll();

// Get deduction history from salary_history
$stmt = $pdo->prepare("
    SELECT 
        sh.id,
        sh.month,
        sh.year,
        sh.advances_deducted,
        sh.net_payable,
        sh.status as salary_status,
        sh.approved_at,
        approver.name as approved_by_name
    FROM salary_history sh
    LEFT JOIN users approver ON sh.approved_by = approver.id
    WHERE sh.user_id = ? AND sh.advances_deducted > 0
    ORDER BY sh.year DESC, sh.month DESC, sh.approved_at DESC
");
$stmt->execute([$user['id']]);
$deduction_history = $stmt->fetchAll();

$page_title = 'My Advance Requests';
include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="mb-4">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3">
            <div>
                <h1 class="gradient-text mb-2 fs-3 fs-md-2">My Advance Requests</h1>
                <p class="text-muted mb-0 small">View and manage your advance payment requests</p>
            </div>
            <?php if ($user['role'] === 'staff'): ?>
                <a href="<?= BASE_URL ?>/advances/request.php" class="btn btn-primary btn-lg w-100 w-md-auto">
                    <i class="bi bi-wallet2 me-2"></i>Request Advance
                </a>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="card shadow-lg border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                                <thead class="table-light">
                        <tr>
                            <th class="ps-4">Amount</th>
                            <th class="d-none d-md-table-cell">Reason</th>
                            <th>Status</th>
                            <th class="d-none d-lg-table-cell">Approved By</th>
                            <th class="d-none d-sm-table-cell">Created</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($advances)): ?>
                            <tr>
                                <td colspan="5" class="text-center py-5 text-muted">
                                    <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                                    No advance requests found
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($advances as $advance): ?>
                                <tr>
                                    <td class="ps-4">
                                        <strong class="text-success">৳<?= number_format($advance['amount'], 2) ?></strong>
                                        <div class="d-md-none small text-muted mt-1">
                                            Reason: <?= h($advance['reason']) ?><br>
                                            Created: <?= date('M d, Y', strtotime($advance['created_at'])) ?>
                                        </div>
                                    </td>
                                    <td class="d-none d-md-table-cell">
                                        <span class="text-truncate d-inline-block" style="max-width: 300px;" title="<?= h($advance['reason']) ?>">
                                            <?= h($advance['reason']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= $advance['status'] === 'approved' ? 'success' : ($advance['status'] === 'rejected' ? 'danger' : 'warning') ?> px-2 px-md-3 py-2">
                                            <?= ucfirst($advance['status']) ?>
                                        </span>
                                    </td>
                                    <td class="d-none d-lg-table-cell"><?= $advance['approved_by_name'] ? h($advance['approved_by_name']) : '<span class="text-muted">-</span>' ?></td>
                                    <td class="d-none d-sm-table-cell"><?= format_datetime($advance['created_at']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Deduction History Section -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card shadow-lg border-0">
                <div class="card-header bg-white border-bottom">
                    <h5 class="mb-0 fw-semibold">
                        <i class="bi bi-clock-history me-2 text-primary"></i>My Deduction History
                    </h5>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($deduction_history)): ?>
                        <div class="text-center py-5 text-muted">
                            <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                            <p class="mb-0">No deduction history found</p>
                            <small class="text-muted">Your advance deductions will appear here once they are applied during payroll processing</small>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="ps-4">Month/Year</th>
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
                                                <span class="badge bg-info bg-gradient">
                                                    <?= date('F Y', mktime(0, 0, 0, $history['month'], 1, $history['year'])) ?>
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
                                        <td class="ps-4"><strong>Total Deducted:</strong></td>
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

<?php include __DIR__ . '/../includes/footer.php'; ?>

