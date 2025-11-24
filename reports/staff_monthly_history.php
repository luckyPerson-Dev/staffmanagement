<?php
/**
 * reports/staff_monthly_history.php
 * Staff monthly performance summary with charts and detailed breakdown
 */

require_once __DIR__ . '/../auth_helper.php';
require_once __DIR__ . '/../helpers.php';

// Staff can only view their own history, admin/superadmin can view any
$current_user = current_user();
$viewing_user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : null;

if ($current_user['role'] === 'staff') {
    // Staff can only view their own history
    $viewing_user_id = $current_user['id'];
} elseif (!in_array($current_user['role'], ['superadmin', 'admin', 'accountant'])) {
    require_role(['superadmin', 'admin', 'accountant']);
}

$pdo = getPDO();

// Get filter parameters
$selected_user_id = $viewing_user_id ?? (isset($_GET['user_id']) ? intval($_GET['user_id']) : null);
$month = isset($_GET['month']) ? intval($_GET['month']) : date('n');
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

// If no user selected and user is admin, default to first staff member
if (!$selected_user_id && in_array($current_user['role'], ['superadmin', 'admin', 'accountant'])) {
    $stmt = $pdo->query("SELECT id FROM users WHERE role = 'staff' AND deleted_at IS NULL ORDER BY name LIMIT 1");
    $first_staff = $stmt->fetch();
    $selected_user_id = $first_staff ? intval($first_staff['id']) : null;
}

// Validate month/year
if ($month < 1 || $month > 12) $month = date('n');
if ($year < 2000 || $year > 2100) $year = date('Y');

$days_in_month = cal_days_in_month(CAL_GREGORIAN, $month, $year);
$start_date = sprintf('%04d-%02d-01', $year, $month);
$end_date = sprintf('%04d-%02d-%02d', $year, $month, $days_in_month);

// Get staff list for filter (admin only)
$staff_list = [];
if (in_array($current_user['role'], ['superadmin', 'admin', 'accountant'])) {
    $stmt = $pdo->query("SELECT id, name FROM users WHERE role = 'staff' AND deleted_at IS NULL ORDER BY name");
    $staff_list = $stmt->fetchAll();
}

// Get user info
$user_info = null;
if ($selected_user_id) {
    $stmt = $pdo->prepare("SELECT id, name, email FROM users WHERE id = ? AND deleted_at IS NULL");
    $stmt->execute([$selected_user_id]);
    $user_info = $stmt->fetch();
}

// Get all daily progress entries for the month
$daily_entries = [];
$monthly_stats = [
    'total_working_days' => 0,
    'missed_days' => 0,
    'overtime_days' => 0,
    'total_monthly_progress' => 0.0,
    'ticket_total_missed' => 0,
    'group_ok' => 0,
    'group_partial' => 0,
    'group_miss' => 0,
    'notes_summary' => []
];

if ($selected_user_id && $user_info) {
    $stmt = $pdo->prepare("
        SELECT * 
        FROM daily_progress 
        WHERE user_id = ? AND date >= ? AND date <= ?
        ORDER BY date ASC
    ");
    $stmt->execute([$selected_user_id, $start_date, $end_date]);
    $daily_entries = $stmt->fetchAll();
    
    // Calculate statistics
    foreach ($daily_entries as $entry) {
        $monthly_stats['total_working_days']++;
        
        if (isset($entry['is_missed']) && $entry['is_missed']) {
            $monthly_stats['missed_days']++;
        }
        
        if (isset($entry['is_overtime']) && $entry['is_overtime']) {
            $monthly_stats['overtime_days']++;
        }
        
        $monthly_stats['total_monthly_progress'] += floatval($entry['progress_percent']);
        $monthly_stats['ticket_total_missed'] += intval($entry['tickets_missed']);
        
        // Parse group status
        $groups_status = json_decode($entry['groups_status'], true) ?? [];
        foreach ($groups_status as $group) {
            $status = $group['status'] ?? '';
            if (in_array($status, ['completed', 'ok'])) {
                $monthly_stats['group_ok']++;
            } elseif ($status === 'partial') {
                $monthly_stats['group_partial']++;
            } elseif (in_array($status, ['missed', 'miss'])) {
                $monthly_stats['group_miss']++;
            }
        }
        
        // Collect notes
        if (!empty($entry['notes'])) {
            $monthly_stats['notes_summary'][] = [
                'date' => $entry['date'],
                'note' => $entry['notes']
            ];
        }
    }
}

// Prepare chart data
$chart_labels = [];
$chart_progress = [];
$chart_colors = [];

for ($day = 1; $day <= $days_in_month; $day++) {
    $date_str = sprintf('%04d-%02d-%02d', $year, $month, $day);
    $chart_labels[] = date('M d', strtotime($date_str));
    
    // Find entry for this day
    $entry = null;
    foreach ($daily_entries as $e) {
        if ($e['date'] === $date_str) {
            $entry = $e;
            break;
        }
    }
    
    if ($entry) {
        $progress = floatval($entry['progress_percent']);
        $chart_progress[] = $progress;
        
        // Color based on progress
        if (isset($entry['is_missed']) && $entry['is_missed']) {
            $chart_colors[] = 'rgba(220, 53, 69, 0.8)'; // Red for missed
        } elseif (isset($entry['is_overtime']) && $entry['is_overtime']) {
            $chart_colors[] = 'rgba(40, 167, 69, 0.8)'; // Green for overtime
        } elseif ($progress >= 80) {
            $chart_colors[] = 'rgba(40, 167, 69, 0.8)'; // Green
        } elseif ($progress >= 50) {
            $chart_colors[] = 'rgba(255, 193, 7, 0.8)'; // Yellow
        } else {
            $chart_colors[] = 'rgba(220, 53, 69, 0.8)'; // Red
        }
    } else {
        $chart_progress[] = null; // No entry
        $chart_colors[] = 'rgba(200, 200, 200, 0.3)'; // Gray for no entry
    }
}

$page_title = 'Staff Monthly History';
include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="gradient-text mb-2">Staff Monthly History</h1>
            <p class="text-muted mb-0">Detailed monthly performance summary and progress breakdown</p>
        </div>
        <div>
            <?php if ($selected_user_id && !empty($daily_entries)): ?>
                <a href="<?= BASE_URL ?>/reports/export_progress.php?user_id=<?= $selected_user_id ?>&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>" 
                   class="btn btn-success btn-lg me-2">
                    <i class="bi bi-file-earmark-spreadsheet me-1"></i>Export CSV
                </a>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Filters -->
    <div class="card shadow-lg border-0 mb-4">
        <div class="card-body p-4">
            <form method="GET" action="" class="row g-3">
                <?php if (in_array($current_user['role'], ['superadmin', 'admin', 'accountant'])): ?>
                    <div class="col-md-4">
                        <label for="user_id" class="form-label fw-semibold">
                            <i class="bi bi-person me-1"></i>Staff Member <span class="text-danger">*</span>
                        </label>
                        <select class="form-select form-select-lg" id="user_id" name="user_id" required>
                            <option value="">Select Staff</option>
                            <?php foreach ($staff_list as $s): ?>
                                <option value="<?= $s['id'] ?>" <?= $selected_user_id == $s['id'] ? 'selected' : '' ?>>
                                    <?= h($s['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php else: ?>
                    <input type="hidden" name="user_id" value="<?= $selected_user_id ?>">
                <?php endif; ?>
                
                <div class="col-md-4">
                    <label for="month" class="form-label fw-semibold">
                        <i class="bi bi-calendar me-1"></i>Month
                    </label>
                    <select class="form-select form-select-lg" id="month" name="month">
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?= $m ?>" <?= $month == $m ? 'selected' : '' ?>>
                                <?= date('F', mktime(0, 0, 0, $m, 1)) ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                
                <div class="col-md-4">
                    <label for="year" class="form-label fw-semibold">
                        <i class="bi bi-calendar-year me-1"></i>Year
                    </label>
                    <input type="number" 
                           class="form-control form-control-lg" 
                           id="year" 
                           name="year" 
                           value="<?= $year ?>" 
                           min="2000" 
                           max="2100">
                </div>
                
                <div class="col-12">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="bi bi-funnel me-1"></i>Load History
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <?php if ($selected_user_id && $user_info): ?>
        <!-- Summary Cards -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card shadow-sm border-0">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="rounded-circle bg-primary bg-opacity-10 d-flex align-items-center justify-content-center me-3" style="width: 50px; height: 50px;">
                                <i class="bi bi-calendar-check text-primary fs-4"></i>
                            </div>
                            <div>
                                <div class="text-muted small">Working Days</div>
                                <div class="fw-bold fs-5"><?= $monthly_stats['total_working_days'] ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card shadow-sm border-0">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="rounded-circle bg-danger bg-opacity-10 d-flex align-items-center justify-content-center me-3" style="width: 50px; height: 50px;">
                                <i class="bi bi-x-circle text-danger fs-4"></i>
                            </div>
                            <div>
                                <div class="text-muted small">Missed Days</div>
                                <div class="fw-bold fs-5"><?= $monthly_stats['missed_days'] ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card shadow-sm border-0">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="rounded-circle bg-success bg-opacity-10 d-flex align-items-center justify-content-center me-3" style="width: 50px; height: 50px;">
                                <i class="bi bi-clock-history text-success fs-4"></i>
                            </div>
                            <div>
                                <div class="text-muted small">Overtime Days</div>
                                <div class="fw-bold fs-5"><?= $monthly_stats['overtime_days'] ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card shadow-sm border-0">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="rounded-circle bg-info bg-opacity-10 d-flex align-items-center justify-content-center me-3" style="width: 50px; height: 50px;">
                                <i class="bi bi-graph-up text-info fs-4"></i>
                            </div>
                            <div>
                                <div class="text-muted small">Monthly Progress</div>
                                <div class="fw-bold fs-5"><?= number_format($monthly_stats['total_monthly_progress'], 2) ?>%</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Additional Stats -->
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card shadow-sm border-0">
                    <div class="card-body">
                        <h6 class="fw-semibold mb-3">Ticket Summary</h6>
                        <div class="text-center">
                            <div class="fs-3 fw-bold text-primary"><?= $monthly_stats['ticket_total_missed'] ?></div>
                            <div class="text-muted small">Total Tickets Missed</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-8">
                <div class="card shadow-sm border-0">
                    <div class="card-body">
                        <h6 class="fw-semibold mb-3">Group Progress Summary</h6>
                        <div class="row text-center">
                            <div class="col-4">
                                <div class="fs-4 fw-bold text-success"><?= $monthly_stats['group_ok'] ?></div>
                                <div class="text-muted small">OK/Completed</div>
                            </div>
                            <div class="col-4">
                                <div class="fs-4 fw-bold text-warning"><?= $monthly_stats['group_partial'] ?></div>
                                <div class="text-muted small">Partial</div>
                            </div>
                            <div class="col-4">
                                <div class="fs-4 fw-bold text-danger"><?= $monthly_stats['group_miss'] ?></div>
                                <div class="text-muted small">Missed</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Chart -->
        <?php if (!empty($daily_entries)): ?>
            <div class="card shadow-lg border-0 mb-4">
                <div class="card-body">
                    <h5 class="fw-semibold mb-4">
                        <i class="bi bi-bar-chart me-2 text-primary"></i>Daily Progress Chart
                    </h5>
                    <canvas id="progressChart" height="80"></canvas>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Daily Breakdown Table -->
        <div class="card shadow-lg border-0">
            <div class="card-header bg-white border-bottom">
                <h5 class="mb-0 fw-semibold">
                    <i class="bi bi-calendar3 me-2"></i>Daily Breakdown - <?= date('F Y', mktime(0, 0, 0, $month, 1, $year)) ?>
                </h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4 py-3 fw-semibold">Day</th>
                                <th class="py-3 fw-semibold">Date</th>
                                <th class="py-3 fw-semibold">Ticket Missed</th>
                                <th class="py-3 fw-semibold">Group Status</th>
                                <th class="py-3 fw-semibold">Final Daily Progress</th>
                                <th class="py-3 fw-semibold">Status</th>
                                <th class="py-3 fw-semibold">Notes</th>
                                <th class="text-end pe-4 py-3 fw-semibold">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($daily_entries)): ?>
                                <tr>
                                    <td colspan="8" class="text-center py-5 text-muted">
                                        <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                                        <p class="mb-0">No progress entries found for this month</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($daily_entries as $entry): ?>
                                    <?php
                                    $groups_status = json_decode($entry['groups_status'], true) ?? [];
                                    $group_status_display = 'N/A';
                                    if (!empty($groups_status)) {
                                        $statuses = array_column($groups_status, 'status');
                                        $ok_count = count(array_filter($statuses, fn($s) => in_array($s, ['completed', 'ok'])));
                                        $partial_count = count(array_filter($statuses, fn($s) => $s === 'partial'));
                                        $miss_count = count(array_filter($statuses, fn($s) => in_array($s, ['missed', 'miss'])));
                                        
                                        $parts = [];
                                        if ($ok_count > 0) $parts[] = "OK: {$ok_count}";
                                        if ($partial_count > 0) $parts[] = "Partial: {$partial_count}";
                                        if ($miss_count > 0) $parts[] = "Miss: {$miss_count}";
                                        $group_status_display = !empty($parts) ? implode(', ', $parts) : 'N/A';
                                    }
                                    ?>
                                    <tr>
                                        <td class="ps-4"><?= date('j', strtotime($entry['date'])) ?></td>
                                        <td><?= date('M d, Y', strtotime($entry['date'])) ?></td>
                                        <td><?= $entry['tickets_missed'] ?></td>
                                        <td><small class="text-muted"><?= h($group_status_display) ?></small></td>
                                        <td>
                                            <span class="badge px-3 py-2 <?= $entry['progress_percent'] >= 80 ? 'bg-success' : ($entry['progress_percent'] >= 50 ? 'bg-warning' : 'bg-danger') ?>">
                                                <?= number_format($entry['progress_percent'], 2) ?>%
                                            </span>
                                        </td>
                                        <td>
                                            <?php if (isset($entry['is_missed']) && $entry['is_missed']): ?>
                                                <span class="badge bg-danger">Missed</span>
                                            <?php elseif (isset($entry['is_overtime']) && $entry['is_overtime']): ?>
                                                <span class="badge bg-success">Overtime</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Normal</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <small class="text-muted"><?= h(substr($entry['notes'] ?? '', 0, 30)) ?><?= strlen($entry['notes'] ?? '') > 30 ? '...' : '' ?></small>
                                        </td>
                                        <td class="text-end pe-4">
                                            <a href="<?= BASE_URL ?>/progress/daily_progress_edit.php?id=<?= $entry['id'] ?>" 
                                               class="btn btn-sm btn-outline-primary">
                                                <i class="bi bi-pencil"></i> Edit
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
        
        <!-- Notes Summary -->
        <?php if (!empty($monthly_stats['notes_summary'])): ?>
            <div class="card shadow-lg border-0 mt-4">
                <div class="card-header bg-white border-bottom">
                    <h5 class="mb-0 fw-semibold">
                        <i class="bi bi-sticky me-2"></i>Notes Summary
                    </h5>
                </div>
                <div class="card-body">
                    <div class="list-group">
                        <?php foreach ($monthly_stats['notes_summary'] as $note_item): ?>
                            <div class="list-group-item">
                                <div class="d-flex w-100 justify-content-between">
                                    <h6 class="mb-1"><?= date('M d, Y', strtotime($note_item['date'])) ?></h6>
                                </div>
                                <p class="mb-0"><?= h($note_item['note']) ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
    <?php else: ?>
        <div class="card shadow-lg border-0">
            <div class="card-body text-center py-5">
                <i class="bi bi-info-circle fs-1 text-muted d-block mb-3"></i>
                <p class="text-muted">Please select a staff member and month to view their monthly history.</p>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
<?php if (!empty($daily_entries)): ?>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('progressChart');
    if (!ctx) return;
    
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?= json_encode($chart_labels) ?>,
            datasets: [{
                label: 'Daily Progress %',
                data: <?= json_encode($chart_progress) ?>,
                backgroundColor: <?= json_encode($chart_colors) ?>,
                borderColor: <?= json_encode(array_map(fn($c) => str_replace('0.8', '1', $c), $chart_colors)) ?>,
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            scales: {
                y: {
                    beginAtZero: true,
                    max: 100,
                    title: {
                        display: true,
                        text: 'Progress %'
                    }
                },
                x: {
                    title: {
                        display: true,
                        text: 'Date'
                    },
                    ticks: {
                        maxRotation: 45,
                        minRotation: 45
                    }
                }
            },
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            if (context.parsed.y === null) {
                                return 'No entry';
                            }
                            return 'Progress: ' + context.parsed.y.toFixed(2) + '%';
                        }
                    }
                }
            }
        }
    });
});
<?php endif; ?>
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>

