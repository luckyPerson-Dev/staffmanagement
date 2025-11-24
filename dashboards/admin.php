<?php
/**
 * dashboards/admin.php
 * Admin dashboard
 */

require_once __DIR__ . '/../auth_helper.php';
require_once __DIR__ . '/../helpers.php';

require_role(['admin']);

$user = current_user();
$pdo = getPDO();

// Get today's date
$today = date('Y-m-d');

// Get today's progress entries
$stmt = $pdo->prepare("
    SELECT dp.*, u.name as user_name 
    FROM daily_progress dp
    JOIN users u ON dp.user_id = u.id
    WHERE dp.date = ? AND u.deleted_at IS NULL
    ORDER BY dp.created_at DESC
");
$stmt->execute([$today]);
$today_progress = $stmt->fetchAll();

// Get monthly statistics
$current_month = (int)date('n');
$current_year = (int)date('Y');
$days_in_month = cal_days_in_month(CAL_GREGORIAN, $current_month, $current_year);
$start_date = sprintf('%04d-%02d-01', $current_year, $current_month);
$end_date = sprintf('%04d-%02d-%02d', $current_year, $current_month, $days_in_month);

$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_entries,
        SUM(CASE WHEN is_missed = 1 THEN 1 ELSE 0 END) as missed_days,
        SUM(CASE WHEN is_overtime = 1 THEN 1 ELSE 0 END) as overtime_days,
        AVG(progress_percent) as avg_progress
    FROM daily_progress
    WHERE date >= ? AND date <= ?
");
$stmt->execute([$start_date, $end_date]);
$month_stats = $stmt->fetch();

// Get pending advances
$stmt = $pdo->query("
    SELECT a.*, u.name as user_name 
    FROM advances a
    JOIN users u ON a.user_id = u.id
    WHERE a.status = 'pending'
    ORDER BY a.created_at DESC
    LIMIT 10
");
$pending_advances = $stmt->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid mt-4">
    <h2>Admin Dashboard</h2>
    <p class="text-muted">Welcome, <?= h($user['name']) ?>!</p>
    
    <!-- Monthly Statistics -->
    <div class="row mt-4">
        <div class="col-md-3">
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="rounded-circle bg-primary bg-opacity-10 d-flex align-items-center justify-content-center me-3" style="width: 50px; height: 50px;">
                            <i class="bi bi-list-check text-primary"></i>
                        </div>
                        <div>
                            <div class="text-muted small">Total Entries</div>
                            <div class="fw-bold fs-5"><?= $month_stats['total_entries'] ?? 0 ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="rounded-circle bg-danger bg-opacity-10 d-flex align-items-center justify-content-center me-3" style="width: 50px; height: 50px;">
                            <i class="bi bi-x-circle text-danger"></i>
                        </div>
                        <div>
                            <div class="text-muted small">Missed Days</div>
                            <div class="fw-bold fs-5"><?= $month_stats['missed_days'] ?? 0 ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="rounded-circle bg-success bg-opacity-10 d-flex align-items-center justify-content-center me-3" style="width: 50px; height: 50px;">
                            <i class="bi bi-clock-history text-success"></i>
                        </div>
                        <div>
                            <div class="text-muted small">Overtime Days</div>
                            <div class="fw-bold fs-5"><?= $month_stats['overtime_days'] ?? 0 ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="rounded-circle bg-info bg-opacity-10 d-flex align-items-center justify-content-center me-3" style="width: 50px; height: 50px;">
                            <i class="bi bi-graph-up text-info"></i>
                        </div>
                        <div>
                            <div class="text-muted small">Avg Progress</div>
                            <div class="fw-bold fs-5"><?= number_format($month_stats['avg_progress'] ?? 0, 1) ?>%</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row mt-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5>Today's Progress Entries (<?= $today ?>)</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($today_progress)): ?>
                        <p class="text-muted">No progress entries for today.</p>
                        <a href="<?= BASE_URL ?>/progress/add.php" class="btn btn-primary">Add Progress Entry</a>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Staff</th>
                                        <th>Progress</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($today_progress as $entry): ?>
                                        <tr>
                                            <td><?= h($entry['user_name']) ?></td>
                                            <td>
                                                <?= number_format($entry['progress_percent'], 2) ?>%
                                                <?php if (isset($entry['is_missed']) && $entry['is_missed']): ?>
                                                    <span class="badge bg-danger ms-1">Missed</span>
                                                <?php endif; ?>
                                                <?php if (isset($entry['is_overtime']) && $entry['is_overtime']): ?>
                                                    <span class="badge bg-success ms-1">Overtime</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <a href="<?= BASE_URL ?>/progress/daily_progress_edit.php?id=<?= $entry['id'] ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <a href="<?= BASE_URL ?>/progress/add.php" class="btn btn-primary mt-2">Add New Entry</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5>Pending Advance Requests</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($pending_advances)): ?>
                        <p class="text-muted">No pending advance requests.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Staff</th>
                                        <th>Amount</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pending_advances as $advance): ?>
                                        <tr>
                                            <td><?= h($advance['user_name']) ?></td>
                                            <td><?= number_format($advance['amount'], 2) ?></td>
                                            <td>
                                                <a href="<?= BASE_URL ?>/advances/view.php?id=<?= $advance['id'] ?>" class="btn btn-sm btn-outline-primary">Review</a>
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
                        <a href="<?= BASE_URL ?>/progress/add.php" class="btn btn-primary">Add Progress Entry</a>
                        <a href="<?= BASE_URL ?>/advances/index.php" class="btn btn-secondary">View All Advances</a>
                        <a href="<?= BASE_URL ?>/users/index.php" class="btn btn-info">Manage Staff</a>
                        <a href="<?= BASE_URL ?>/teams/index.php" class="btn btn-success">Manage Teams</a>
                        <a href="<?= BASE_URL ?>/customers/index.php" class="btn btn-warning">Manage Customers</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

