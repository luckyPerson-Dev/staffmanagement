<?php
/**
 * reports/index.php
 * Reports dashboard
 */

require_once __DIR__ . '/../auth_helper.php';
require_once __DIR__ . '/../helpers.php';

require_role(['superadmin', 'admin', 'accountant']);

$page_title = 'Reports';
include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid mt-4">
    <h2>Reports</h2>
    
    <div class="row mt-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Progress Reports</h5>
                    <p class="card-text">Export daily progress data as CSV</p>
                    <a href="<?= BASE_URL ?>/reports/export_progress.php" class="btn btn-primary">Export Progress CSV</a>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Salary Reports</h5>
                    <p class="card-text">Export salary history as CSV</p>
                    <a href="<?= BASE_URL ?>/reports/export_salary.php" class="btn btn-primary">Export Salary CSV</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

