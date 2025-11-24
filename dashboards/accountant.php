<?php
/**
 * dashboards/accountant.php
 * Accountant dashboard
 */

require_once __DIR__ . '/../auth_helper.php';
require_once __DIR__ . '/../helpers.php';

require_role(['accountant']);

$user = current_user();
$pdo = getPDO();

// Get pending salary approvals
$stmt = $pdo->query("
    SELECT sh.*, u.name as user_name 
    FROM salary_history sh
    JOIN users u ON sh.user_id = u.id
    WHERE sh.status = 'pending'
    ORDER BY sh.year DESC, sh.month DESC
    LIMIT 10
");
$pending_salaries = $stmt->fetchAll();

// Get approved but unpaid salaries
$stmt = $pdo->query("
    SELECT sh.*, u.name as user_name 
    FROM salary_history sh
    JOIN users u ON sh.user_id = u.id
    WHERE sh.status = 'approved'
    ORDER BY sh.year DESC, sh.month DESC
    LIMIT 10
");
$approved_salaries = $stmt->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid mt-4">
    <h2>Accountant Dashboard</h2>
    <p class="text-muted">Welcome, <?= h($user['name']) ?>!</p>
    
    <div class="row mt-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5>Pending Salary Approvals</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($pending_salaries)): ?>
                        <p class="text-muted">No pending salary approvals.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Staff</th>
                                        <th>Month</th>
                                        <th>Net Payable</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pending_salaries as $salary): ?>
                                        <tr>
                                            <td><?= h($salary['user_name']) ?></td>
                                            <td><?= date('M Y', mktime(0,0,0,$salary['month'],1,$salary['year'])) ?></td>
                                            <td><?= number_format($salary['net_payable'], 2) ?></td>
                                            <td>
                                                <a href="<?= BASE_URL ?>/salary/approve.php?id=<?= $salary['id'] ?>" class="btn btn-sm btn-outline-primary">Review</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5>Approved Salaries (Unpaid)</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($approved_salaries)): ?>
                        <p class="text-muted">No approved salaries pending payment.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Staff</th>
                                        <th>Month</th>
                                        <th>Net Payable</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($approved_salaries as $salary): ?>
                                        <tr>
                                            <td><?= h($salary['user_name']) ?></td>
                                            <td><?= date('M Y', mktime(0,0,0,$salary['month'],1,$salary['year'])) ?></td>
                                            <td><?= number_format($salary['net_payable'], 2) ?></td>
                                            <td>
                                                <a href="<?= BASE_URL ?>/salary/mark_paid.php?id=<?= $salary['id'] ?>" class="btn btn-sm btn-outline-success">Mark Paid</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row mt-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Quick Actions</h5>
                    <div class="d-flex gap-2 flex-wrap">
                        <a href="<?= BASE_URL ?>/salary/index.php" class="btn btn-primary">View All Salaries</a>
                        <a href="<?= BASE_URL ?>/reports/index.php" class="btn btn-secondary">Generate Reports</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

