<?php
/**
 * reports/daily_progress.php
 * Daily progress report with comprehensive filters, pagination, and CSV export
 */

require_once __DIR__ . '/../auth_helper.php';
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../core/Pagination.php';

require_role(['superadmin', 'admin', 'accountant']);

$pdo = getPDO();

// Get filter parameters
$staff_id = isset($_GET['staff_id']) ? intval($_GET['staff_id']) : null;
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';
$month = isset($_GET['month']) ? intval($_GET['month']) : null;
$year = isset($_GET['year']) ? intval($_GET['year']) : null;
$group_status_filter = $_GET['group_status'] ?? ''; // ok, partial, miss
$is_missed_filter = isset($_GET['is_missed']) ? $_GET['is_missed'] : ''; // yes, no
$is_overtime_filter = isset($_GET['is_overtime']) ? $_GET['is_overtime'] : ''; // yes, no
$sort_by = $_GET['sort_by'] ?? 'date'; // date, staff
$sort_order = $_GET['sort_order'] ?? 'DESC'; // ASC, DESC

// If month filter is used, override date range
if ($month && $year) {
    $days_in_month = cal_days_in_month(CAL_GREGORIAN, $month, $year);
    $start_date = sprintf('%04d-%02d-01', $year, $month);
    $end_date = sprintf('%04d-%02d-%02d', $year, $month, $days_in_month);
} elseif (empty($start_date) || empty($end_date)) {
    // Default to current month if no dates specified
    $start_date = date('Y-m-01');
    $end_date = date('Y-m-t');
    $month = date('n');
    $year = date('Y');
}

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$items_per_page = 50;

// Build WHERE clause
$where = [];
$params = [];

// Date range
if ($start_date && $end_date) {
    $where[] = "dp.date BETWEEN ? AND ?";
    $params[] = $start_date;
    $params[] = $end_date;
}

// Staff filter
if ($staff_id) {
    $where[] = "dp.user_id = ?";
    $params[] = $staff_id;
}

// Group status filter (check JSON groups_status field)
if ($group_status_filter && in_array($group_status_filter, ['ok', 'partial', 'miss'])) {
    // For JSON field, we need to check if any group has this status
    if ($group_status_filter === 'ok') {
        $where[] = "(dp.groups_status IS NULL OR JSON_SEARCH(dp.groups_status, 'one', 'completed') IS NOT NULL OR JSON_SEARCH(dp.groups_status, 'one', 'ok') IS NOT NULL)";
    } elseif ($group_status_filter === 'partial') {
        $where[] = "JSON_SEARCH(dp.groups_status, 'one', 'partial') IS NOT NULL";
    } elseif ($group_status_filter === 'miss') {
        $where[] = "JSON_SEARCH(dp.groups_status, 'one', 'missed') IS NOT NULL OR JSON_SEARCH(dp.groups_status, 'one', 'miss') IS NOT NULL";
    }
}

// Missed day filter
if ($is_missed_filter === 'yes') {
    $where[] = "dp.is_missed = 1";
} elseif ($is_missed_filter === 'no') {
    $where[] = "(dp.is_missed = 0 OR dp.is_missed IS NULL)";
}

// Overtime filter
if ($is_overtime_filter === 'yes') {
    $where[] = "dp.is_overtime = 1";
} elseif ($is_overtime_filter === 'no') {
    $where[] = "(dp.is_overtime = 0 OR dp.is_overtime IS NULL)";
}

$where_clause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// Get total count for pagination
$count_sql = "SELECT COUNT(*) as total FROM daily_progress dp JOIN users u ON dp.user_id = u.id {$where_clause}";
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_items = $count_stmt->fetch()['total'];

// Pagination
$pagination = new Pagination($total_items, $items_per_page, $page, $_SERVER['REQUEST_URI']);
$offset = $pagination->getOffset();
$limit = $pagination->getLimit();

// Sort order
$order_by = 'dp.date DESC';
if ($sort_by === 'staff') {
    $order_by = 'u.name ' . ($sort_order === 'ASC' ? 'ASC' : 'DESC') . ', dp.date DESC';
} else {
    $order_by = 'dp.date ' . ($sort_order === 'ASC' ? 'ASC' : 'DESC') . ', u.name ASC';
}

// Main query
$sql = "
    SELECT dp.*, u.name as user_name, c.name as customer_name
    FROM daily_progress dp
    JOIN users u ON dp.user_id = u.id
    LEFT JOIN customers c ON dp.customer_id = c.id
    {$where_clause}
    ORDER BY {$order_by}
    LIMIT {$limit} OFFSET {$offset}
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$progress = $stmt->fetchAll();

// Get staff list for filter
$stmt = $pdo->query("SELECT id, name FROM users WHERE role = 'staff' AND deleted_at IS NULL ORDER BY name");
$staff_list = $stmt->fetchAll();

// Calculate statistics
$total_entries = $total_items;
$missed_days = 0;
$overtime_days = 0;
$avg_progress = 0;

// Get stats from all matching records (not just current page)
$stats_sql = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN dp.is_missed = 1 THEN 1 ELSE 0 END) as missed_count,
    SUM(CASE WHEN dp.is_overtime = 1 THEN 1 ELSE 0 END) as overtime_count,
    AVG(dp.progress_percent) as avg_progress
    FROM daily_progress dp
    JOIN users u ON dp.user_id = u.id
    {$where_clause}";
$stats_stmt = $pdo->prepare($stats_sql);
$stats_stmt->execute($params);
$stats = $stats_stmt->fetch();

$missed_days = intval($stats['missed_count'] ?? 0);
$overtime_days = intval($stats['overtime_count'] ?? 0);
$avg_progress = floatval($stats['avg_progress'] ?? 0);

$page_title = 'Daily Progress Report';
include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="gradient-text mb-2">Daily Progress Report</h1>
            <p class="text-muted mb-0">View and export daily progress entries with advanced filters</p>
        </div>
        <a href="<?= BASE_URL ?>/reports/export_progress.php?<?= http_build_query($_GET) ?>" 
           class="btn btn-success btn-lg">
            <i class="bi bi-file-earmark-spreadsheet me-1"></i>Export CSV
        </a>
    </div>
    
    <!-- Statistics Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="rounded-circle bg-primary bg-opacity-10 d-flex align-items-center justify-content-center me-3" style="width: 50px; height: 50px;">
                            <i class="bi bi-list-check text-primary fs-4"></i>
                        </div>
                        <div>
                            <div class="text-muted small">Total Entries</div>
                            <div class="fw-bold fs-5"><?= number_format($total_entries) ?></div>
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
                            <div class="fw-bold fs-5"><?= $missed_days ?></div>
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
                            <div class="fw-bold fs-5"><?= $overtime_days ?></div>
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
                            <div class="text-muted small">Avg Progress</div>
                            <div class="fw-bold fs-5"><?= number_format($avg_progress, 1) ?>%</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Filters -->
    <div class="card shadow-lg border-0 mb-4">
        <div class="card-body p-4">
            <form method="GET" action="" class="row g-3">
                <div class="col-md-3">
                    <label for="staff_id" class="form-label fw-semibold">
                        <i class="bi bi-person me-1"></i>Staff Member
                    </label>
                    <select class="form-select form-select-lg" id="staff_id" name="staff_id">
                        <option value="">All Staff</option>
                        <?php foreach ($staff_list as $s): ?>
                            <option value="<?= $s['id'] ?>" <?= $staff_id == $s['id'] ? 'selected' : '' ?>>
                                <?= h($s['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label for="month" class="form-label fw-semibold">
                        <i class="bi bi-calendar me-1"></i>Month
                    </label>
                    <select class="form-select form-select-lg" id="month" name="month">
                        <option value="">All Months</option>
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?= $m ?>" <?= $month == $m ? 'selected' : '' ?>>
                                <?= date('F', mktime(0, 0, 0, $m, 1)) ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label for="year" class="form-label fw-semibold">
                        <i class="bi bi-calendar-year me-1"></i>Year
                    </label>
                    <input type="number" 
                           class="form-control form-control-lg" 
                           id="year" 
                           name="year" 
                           value="<?= $year ?>" 
                           min="2000" 
                           max="2100"
                           placeholder="All Years">
                </div>
                
                <div class="col-md-3">
                    <label for="group_status" class="form-label fw-semibold">
                        <i class="bi bi-diagram-3 me-1"></i>Group Status
                    </label>
                    <select class="form-select form-select-lg" id="group_status" name="group_status">
                        <option value="">All Statuses</option>
                        <option value="ok" <?= $group_status_filter === 'ok' ? 'selected' : '' ?>>OK/Completed</option>
                        <option value="partial" <?= $group_status_filter === 'partial' ? 'selected' : '' ?>>Partial</option>
                        <option value="miss" <?= $group_status_filter === 'miss' ? 'selected' : '' ?>>Missed</option>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label for="is_missed" class="form-label fw-semibold">
                        <i class="bi bi-x-circle me-1"></i>Missed Day
                    </label>
                    <select class="form-select form-select-lg" id="is_missed" name="is_missed">
                        <option value="">All</option>
                        <option value="yes" <?= $is_missed_filter === 'yes' ? 'selected' : '' ?>>Yes</option>
                        <option value="no" <?= $is_missed_filter === 'no' ? 'selected' : '' ?>>No</option>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label for="is_overtime" class="form-label fw-semibold">
                        <i class="bi bi-clock-history me-1"></i>Overtime
                    </label>
                    <select class="form-select form-select-lg" id="is_overtime" name="is_overtime">
                        <option value="">All</option>
                        <option value="yes" <?= $is_overtime_filter === 'yes' ? 'selected' : '' ?>>Yes</option>
                        <option value="no" <?= $is_overtime_filter === 'no' ? 'selected' : '' ?>>No</option>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label for="start_date" class="form-label fw-semibold">
                        <i class="bi bi-calendar-range me-1"></i>Start Date
                    </label>
                    <input type="date" 
                           class="form-control form-control-lg" 
                           id="start_date" 
                           name="start_date" 
                           value="<?= $start_date ?>">
                </div>
                
                <div class="col-md-3">
                    <label for="end_date" class="form-label fw-semibold">
                        <i class="bi bi-calendar-range me-1"></i>End Date
                    </label>
                    <input type="date" 
                           class="form-control form-control-lg" 
                           id="end_date" 
                           name="end_date" 
                           value="<?= $end_date ?>">
                </div>
                
                <div class="col-md-6">
                    <label for="sort_by" class="form-label fw-semibold">
                        <i class="bi bi-sort-alpha-down me-1"></i>Sort By
                    </label>
                    <div class="input-group">
                        <select class="form-select form-select-lg" id="sort_by" name="sort_by">
                            <option value="date" <?= $sort_by === 'date' ? 'selected' : '' ?>>Date</option>
                            <option value="staff" <?= $sort_by === 'staff' ? 'selected' : '' ?>>Staff</option>
                        </select>
                        <select class="form-select form-select-lg" name="sort_order">
                            <option value="DESC" <?= $sort_order === 'DESC' ? 'selected' : '' ?>>Descending</option>
                            <option value="ASC" <?= $sort_order === 'ASC' ? 'selected' : '' ?>>Ascending</option>
                        </select>
                    </div>
                </div>
                
                <div class="col-md-6 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary btn-lg w-100 me-2">
                        <i class="bi bi-funnel me-1"></i>Filter
                    </button>
                    <a href="<?= BASE_URL ?>/reports/daily_progress.php" class="btn btn-outline-secondary btn-lg">
                        <i class="bi bi-arrow-clockwise me-1"></i>Reset
                    </a>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Results Table -->
    <div class="card shadow-lg border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4 py-3 fw-semibold">Date</th>
                            <th class="py-3 fw-semibold">Staff</th>
                            <th class="py-3 fw-semibold">Tickets Missed</th>
                            <th class="py-3 fw-semibold">Group Status</th>
                            <th class="py-3 fw-semibold">Daily Progress %</th>
                            <th class="py-3 fw-semibold">Overtime?</th>
                            <th class="py-3 fw-semibold">Missed Day?</th>
                            <th class="py-3 fw-semibold">Notes</th>
                            <th class="text-end pe-4 py-3 fw-semibold">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($progress)): ?>
                            <tr>
                                <td colspan="9" class="text-center py-5 text-muted">
                                    <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                                    <p class="mb-0">No progress entries found for the selected filters</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($progress as $entry): ?>
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
                                    <td class="ps-4"><?= date('M d, Y', strtotime($entry['date'])) ?></td>
                                    <td><strong><?= h($entry['user_name']) ?></strong></td>
                                    <td><?= $entry['tickets_missed'] ?></td>
                                    <td>
                                        <small class="text-muted"><?= h($group_status_display) ?></small>
                                    </td>
                                    <td>
                                        <span class="badge px-3 py-2 <?= $entry['progress_percent'] >= 80 ? 'bg-success' : ($entry['progress_percent'] >= 50 ? 'bg-warning' : 'bg-danger') ?>">
                                            <?= number_format($entry['progress_percent'], 2) ?>%
                                        </span>
                                    </td>
                                    <td>
                                        <?php if (isset($entry['is_overtime']) && $entry['is_overtime']): ?>
                                            <span class="badge bg-success">Yes</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">No</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (isset($entry['is_missed']) && $entry['is_missed']): ?>
                                            <span class="badge bg-danger">Yes</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">No</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <small class="text-muted"><?= h(substr($entry['notes'] ?? '', 0, 50)) ?><?= strlen($entry['notes'] ?? '') > 50 ? '...' : '' ?></small>
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
            
            <!-- Pagination -->
            <?php if ($pagination->getTotalPages() > 1): ?>
                <div class="card-footer bg-white border-top">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="text-muted small">
                            <?= $pagination->getInfo()['showing'] ?>
                        </div>
                        <nav>
                            <ul class="pagination mb-0">
                                <?php if ($pagination->hasPrev()): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">Previous</a>
                                    </li>
                                <?php else: ?>
                                    <li class="page-item disabled">
                                        <span class="page-link">Previous</span>
                                    </li>
                                <?php endif; ?>
                                
                                <?php
                                $total_pages = $pagination->getTotalPages();
                                $start = max(1, $page - 2);
                                $end = min($total_pages, $page + 2);
                                
                                if ($start > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => 1])) ?>">1</a>
                                    </li>
                                    <?php if ($start > 2): ?>
                                        <li class="page-item disabled"><span class="page-link">...</span></li>
                                    <?php endif; ?>
                                <?php endif; ?>
                                
                                <?php for ($i = $start; $i <= $end; $i++): ?>
                                    <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>
                                    </li>
                                <?php endfor; ?>
                                
                                <?php if ($end < $total_pages): ?>
                                    <?php if ($end < $total_pages - 1): ?>
                                        <li class="page-item disabled"><span class="page-link">...</span></li>
                                    <?php endif; ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $total_pages])) ?>"><?= $total_pages ?></a>
                                    </li>
                                <?php endif; ?>
                                
                                <?php if ($pagination->hasNext()): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">Next</a>
                                    </li>
                                <?php else: ?>
                                    <li class="page-item disabled">
                                        <span class="page-link">Next</span>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
