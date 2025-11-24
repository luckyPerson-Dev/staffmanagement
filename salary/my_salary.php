<?php
/**
 * salary/my_salary.php
 * Staff view own salary history
 */

require_once __DIR__ . '/../auth_helper.php';
require_once __DIR__ . '/../helpers.php';

require_role(['staff']);

$user = current_user();
$pdo = getPDO();

$stmt = $pdo->prepare("
    SELECT * FROM salary_history
    WHERE user_id = ?
    ORDER BY year DESC, month DESC
");
$stmt->execute([$user['id']]);
$salaries = $stmt->fetchAll();

$page_title = 'My Salary History';
include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="mb-4">
        <h1 class="gradient-text mb-2 fs-3 fs-md-2">My Salary History</h1>
        <p class="text-muted mb-0 small">View your complete salary records</p>
    </div>
    
    <div class="card shadow-lg border-0">
        <div class="card-header bg-white border-bottom">
            <h5 class="mb-0">
                <i class="bi bi-cash-stack me-2 text-primary"></i>Salary Records
            </h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Month</th>
                            <th class="d-none d-md-table-cell">Gross Salary</th>
                            <th class="d-none d-lg-table-cell">Progress</th>
                            <th>Net Payable</th>
                            <th>Status</th>
                            <th class="text-end pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($salaries)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">
                                    <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                                    No salary records found
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($salaries as $salary): ?>
                                <tr>
                                    <td class="ps-4">
                                        <strong><?= date('F Y', mktime(0,0,0,$salary['month'],1,$salary['year'])) ?></strong>
                                        <div class="d-md-none small text-muted mt-1">
                                            Gross: ৳<?= number_format($salary['gross_salary'], 2) ?><br>
                                            Progress: <?= number_format($salary['monthly_progress'], 1) ?>%
                                        </div>
                                    </td>
                                    <td class="d-none d-md-table-cell">৳<?= number_format($salary['gross_salary'], 2) ?></td>
                                    <td class="d-none d-lg-table-cell">
                                        <span class="badge bg-<?= $salary['monthly_progress'] >= 80 ? 'success' : ($salary['monthly_progress'] >= 60 ? 'warning' : 'danger') ?>">
                                            <?= number_format($salary['monthly_progress'], 1) ?>%
                                        </span>
                                    </td>
                                    <td><strong class="text-success">৳<?= number_format($salary['net_payable'], 2) ?></strong></td>
                                    <td>
                                        <span class="badge bg-<?= $salary['status'] === 'paid' ? 'success' : ($salary['status'] === 'approved' ? 'warning' : 'secondary') ?> px-2 px-md-3 py-2">
                                            <?= ucfirst($salary['status']) ?>
                                        </span>
                                    </td>
                                    <td class="text-end pe-4">
                                        <a href="<?= BASE_URL ?>/salary/view.php?id=<?= $salary['id'] ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-eye me-1 d-sm-none"></i>
                                            <span class="d-none d-sm-inline">View</span>
                                            <span class="d-sm-none">Details</span>
                                        </a>
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

<?php include __DIR__ . '/../includes/footer.php'; ?>

