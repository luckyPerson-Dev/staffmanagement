<?php
/**
 * dashboards/staff.php
 * Staff dashboard
 */

require_once __DIR__ . '/../core/autoload.php';
require_once __DIR__ . '/../auth_helper.php';
require_once __DIR__ . '/../helpers.php';

require_role(['staff']);

$user = current_user();
$pdo = getPDO();

// Get current month progress
$current_month = (int)date('n');
$current_year = (int)date('Y');

// Get user's team_id
$stmt = $pdo->prepare("SELECT team_id FROM team_members WHERE user_id = ? LIMIT 1");
$stmt->execute([$user['id']]);
$team_result = $stmt->fetch();
$team_id = $team_result ? (int)$team_result['team_id'] : null;

// Compute metrics
$ticket_percent = compute_ticket_percent($user['id'], $current_month, $current_year);
$group_percent = $team_id ? compute_group_avg($team_id, $current_month, $current_year) : 0.0;
$per_day_percent = per_day_percent($current_month, $current_year);
$days_in_month = days_in_month($current_month, $current_year);

$stmt = $pdo->prepare("
    SELECT 
        AVG(progress_percent) as avg_progress, 
        COUNT(*) as days_count,
        SUM(progress_percent) as monthly_progress_sum,
        SUM(CASE WHEN is_overtime = 1 THEN 1 ELSE 0 END) as overtime_days,
        SUM(CASE WHEN is_missed = 1 THEN 1 ELSE 0 END) as missed_days
    FROM daily_progress
    WHERE user_id = ? AND YEAR(date) = ? AND MONTH(date) = ?
");
$stmt->execute([$user['id'], $current_year, $current_month]);
$month_progress = $stmt->fetch();

// Get latest salary history
$stmt = $pdo->prepare("
    SELECT * FROM salary_history
    WHERE user_id = ?
    ORDER BY year DESC, month DESC
    LIMIT 5
");
$stmt->execute([$user['id']]);
$salary_history = $stmt->fetchAll();

// Get pending advance requests
$stmt = $pdo->prepare("
    SELECT * FROM advances
    WHERE user_id = ? AND status = 'pending'
    ORDER BY created_at DESC
");
$stmt->execute([$user['id']]);
$pending_advances = $stmt->fetchAll();

// Get advance auto-deduction summary
$stmt = $pdo->prepare("
    SELECT * FROM advance_auto_deductions
    WHERE user_id = ? AND status = 'active'
    ORDER BY created_at DESC
    LIMIT 1
");
$stmt->execute([$user['id']]);
$auto_deduction = $stmt->fetch();

// Get completed deduction info
$stmt = $pdo->prepare("
    SELECT * FROM advance_auto_deductions
    WHERE user_id = ? AND status = 'completed'
    ORDER BY updated_at DESC
    LIMIT 1
");
$stmt->execute([$user['id']]);
$completed_deduction = $stmt->fetch();

include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="mb-4">
        <h1 class="gradient-text mb-2">My Dashboard</h1>
        <p class="text-muted mb-0">Welcome, <?= h($user['name']) ?>!</p>
    </div>
    
    <!-- Per-day percent card -->
    <div class="row mt-3">
        <div class="col-12">
            <div class="card mb-3 shadow-sm">
                <div class="card-body">
                    <div class="d-flex flex-column flex-md-row align-items-center align-items-md-start">
                        <i class="bi bi-calendar-check me-0 me-md-3 mb-2 mb-md-0 fs-4 text-primary"></i>
                        <div class="text-center text-md-start">
                            <strong class="d-block d-md-inline">Per day weight this month:</strong> 
                            <span class="fs-5 ms-0 ms-md-2 d-block d-md-inline" id="per-day-percent-display">
                                <i class="spinner-border spinner-border-sm"></i>
                            </span>
                            <span class="ms-2 d-inline-block">
                                <i class="bi bi-info-circle text-muted" 
                                   data-bs-toggle="tooltip" 
                                   data-bs-placement="top"
                                   title="Calculated as 100 / days in month (<?= $days_in_month ?> days)."></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- This Month Summary Panel -->
    <div class="row mt-3 mb-4">
        <div class="col-12">
            <div class="card shadow-lg border-0">
                <div class="card-header bg-white border-bottom">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 fw-semibold">
                            <i class="bi bi-calendar-month me-2 text-primary"></i>This Month Summary
                        </h5>
                        <a href="<?= BASE_URL ?>/reports/staff_monthly_history.php?user_id=<?= $user['id'] ?>&month=<?= $current_month ?>&year=<?= $current_year ?>" 
                           class="btn btn-primary btn-sm">
                            <i class="bi bi-clock-history me-1"></i>View Full History
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-6 col-md-3">
                            <div class="text-center p-3 rounded" style="background: linear-gradient(135deg, rgba(0, 123, 255, 0.1), rgba(112, 111, 211, 0.1));">
                                <div class="fs-4 fw-bold text-primary"><?= intval($month_progress['days_count'] ?? 0) ?></div>
                                <div class="text-muted small">Days Worked</div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="text-center p-3 rounded" style="background: linear-gradient(135deg, rgba(220, 53, 69, 0.1), rgba(255, 107, 107, 0.1));">
                                <div class="fs-4 fw-bold text-danger"><?= intval($month_progress['missed_days'] ?? 0) ?></div>
                                <div class="text-muted small">Days Missed</div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="text-center p-3 rounded" style="background: linear-gradient(135deg, rgba(40, 167, 69, 0.1), rgba(32, 201, 151, 0.1));">
                                <div class="fs-4 fw-bold text-success"><?= intval($month_progress['overtime_days'] ?? 0) ?></div>
                                <div class="text-muted small">Overtime Days</div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="text-center p-3 rounded" style="background: linear-gradient(135deg, rgba(23, 162, 184, 0.1), rgba(19, 132, 150, 0.1));">
                                <div class="fs-4 fw-bold text-info"><?= number_format(floatval($month_progress['monthly_progress_sum'] ?? 0), 2) ?>%</div>
                                <div class="text-muted small">Monthly Progress</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row mt-2">
        <div class="col-12 col-lg-6 mb-4 mb-lg-0">
            <div class="card shadow-lg border-0">
                <div class="card-header bg-white border-bottom">
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-2">
                        <h5 class="mb-0">
                            <i class="bi bi-graph-up me-2 text-primary"></i>Current Month Progress
                        </h5>
                        <span class="badge bg-light text-dark" id="live-progress-badge">
                            <i class="bi bi-circle-fill text-success me-1" style="font-size: 8px;"></i>
                            <span id="last-update-time" class="small">Live</span>
                        </span>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Live Progress Bar -->
                    <div class="mb-4">
                        <?php 
                        $initial_progress = floatval($month_progress['avg_progress'] ?? 0);
                        $initial_progress = max(0, min(100, $initial_progress));
                        $initial_color = $initial_progress >= 80 ? 'success' : ($initial_progress >= 60 ? 'warning' : 'danger');
                        $initial_status = $initial_progress >= 80 ? 'Excellent' : ($initial_progress >= 60 ? 'Good' : 'Needs Improvement');
                        $initial_icon = $initial_progress >= 80 ? 'bi-trophy' : ($initial_progress >= 60 ? 'bi-check-circle' : 'bi-exclamation-triangle');
                        ?>
                        <div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center mb-3 gap-2">
                            <div>
                                <h6 class="text-muted mb-1 small">Average Progress</h6>
                                <h2 class="mb-0 text-<?= $initial_color ?> fs-3 fs-md-2" id="avg-progress-display">
                                    <?= number_format($initial_progress, 2) ?>%
                                </h2>
                            </div>
                            <div class="text-start text-sm-end">
                                <div class="badge bg-<?= $initial_color ?> px-3 py-2 fs-6" id="progress-status-badge">
                                    <i class="bi <?= $initial_icon ?> me-1"></i>
                                    <span id="progress-status-text"><?= $initial_status ?></span>
                                </div>
                            </div>
                        </div>
                        <div class="progress" style="height: 40px; border-radius: 8px; overflow: hidden; background-color: #e9ecef; position: relative;">
                            <div class="progress-bar progress-bar-striped bg-<?= $initial_color ?>" 
                                 id="main-progress-bar"
                                 role="progressbar" 
                                 aria-valuenow="<?= $initial_progress ?>" 
                                 aria-valuemin="0" 
                                 aria-valuemax="100"
                                 data-progress="<?= $initial_progress ?>"
                                 style="width: <?= number_format($initial_progress, 2) ?>%; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 16px; min-width: <?= $initial_progress > 0 ? '2%' : '0' ?>; transition: width 0.8s ease; position: relative; z-index: 1;">
                                <span id="progress-bar-text" style="position: relative; z-index: 2;"><?= number_format($initial_progress, 1) ?>%</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Progress Stats -->
                    <?php
                    $current_day = (int)date('j');
                    $days_in_month = days_in_month($current_month, $current_year);
                    $days_remaining = $days_in_month - $current_day;
                    $per_day = per_day_percent($current_month, $current_year);
                    $expected = min(100, $current_day * $per_day);
                    $days_recorded = (int)($month_progress['days_count'] ?? 0);
                    $progress_diff = $initial_progress - $expected;
                    ?>
                    <div class="row g-2 g-md-3 mb-4">
                        <div class="col-6 col-sm-3">
                            <div class="border rounded p-2 p-md-3 text-center" style="background: #f8f9fa;">
                                <div class="text-muted small mb-1">
                                    <i class="bi bi-calendar-check me-1"></i><span class="d-none d-sm-inline">Days </span>Recorded
                                </div>
                                <div class="fw-bold fs-6 fs-md-5" id="days-recorded"><?= $days_recorded ?></div>
                            </div>
                        </div>
                        <div class="col-6 col-sm-3">
                            <div class="border rounded p-2 p-md-3 text-center" style="background: #f8f9fa;">
                                <div class="text-muted small mb-1">
                                    <i class="bi bi-calendar-x me-1"></i><span class="d-none d-sm-inline">Days </span>Remaining
                                </div>
                                <div class="fw-bold fs-6 fs-md-5" id="days-remaining"><?= $days_remaining ?></div>
                            </div>
                        </div>
                        <div class="col-6 col-sm-3">
                            <div class="border rounded p-2 p-md-3 text-center" style="background: #f8f9fa;">
                                <div class="text-muted small mb-1">
                                    <i class="bi bi-target me-1"></i>Expected
                                </div>
                                <div class="fw-bold fs-6 fs-md-5 text-info" id="expected-progress"><?= number_format($expected, 1) ?>%</div>
                            </div>
                        </div>
                        <div class="col-6 col-sm-3">
                            <div class="border rounded p-2 p-md-3 text-center" style="background: #f8f9fa;">
                                <div class="text-muted small mb-1">
                                    <i class="bi bi-arrow-up-down me-1"></i>Difference
                                </div>
                                <div class="fw-bold fs-6 fs-md-5" id="progress-difference">
                                    <?php if ($progress_diff > 0): ?>
                                        <span class="text-success">+<?= number_format($progress_diff, 1) ?>%</span>
                                    <?php elseif ($progress_diff < 0): ?>
                                        <span class="text-danger"><?= number_format($progress_diff, 1) ?>%</span>
                                    <?php else: ?>
                                        <span class="text-muted"><?= number_format($progress_diff, 1) ?>%</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Today's Progress -->
                    <div class="mb-3 p-2 p-md-3 border rounded" id="today-progress-section" style="display: none; background: #f0f7ff;">
                        <div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-2">
                            <div>
                                <small class="text-muted d-block mb-1">
                                    <i class="bi bi-calendar-day me-1"></i>Today's Progress
                                </small>
                                <strong id="today-progress-value">0%</strong>
                            </div>
                            <div class="progress w-100 w-sm-auto" style="max-width: 200px; height: 20px; background-color: #e9ecef;">
                                <div class="progress-bar bg-primary" 
                                     id="today-progress-bar"
                                     role="progressbar" 
                                     style="width: 0%; transition: width 0.5s ease;">
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Monthly Progress Breakdown -->
                    <div class="mt-4 pt-3 border-top">
                        <h6 class="mb-3 small">
                            <i class="bi bi-pie-chart me-2 text-primary"></i>Monthly Progress Breakdown
                        </h6>
                        <div class="row g-3">
                            <div class="col-6 col-sm-4 mb-2">
                                <small class="text-muted d-block mb-1">Total Monthly Progress:</small>
                                <div class="fw-bold fs-5 fs-md-4 text-primary" id="monthly-progress-sum">
                                    <?= number_format(floatval($month_progress['monthly_progress_sum'] ?? 0), 2) ?>%
                                </div>
                            </div>
                            <div class="col-6 col-sm-4 mb-2">
                                <small class="text-muted d-block mb-1">Days Recorded:</small>
                                <div class="fw-bold fs-6 fs-md-5" id="days-recorded-display"><?= $month_progress['days_count'] ?? 0 ?></div>
                            </div>
                            <div class="col-6 col-sm-4 mb-2">
                                <small class="text-muted d-block mb-1">
                                    <i class="bi bi-clock-history text-success me-1"></i>Overtime Days:
                                </small>
                                <div class="fw-bold fs-6 fs-md-5 text-success" id="overtime-days-display">
                                    <?= intval($month_progress['overtime_days'] ?? 0) ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-12 col-lg-6">
            <div class="card shadow-lg border-0">
                <div class="card-header bg-white border-bottom">
                    <h5 class="mb-0">
                        <i class="bi bi-cash-stack me-2 text-primary"></i>Salary Summary
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($salary_history)): ?>
                        <p class="text-muted">No salary history available.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Month</th>
                                        <th class="d-none d-md-table-cell">Progress</th>
                                        <th>Net Payable</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($salary_history as $salary): ?>
                                        <tr>
                                            <td>
                                                <strong><?= date('M Y', mktime(0,0,0,$salary['month'],1,$salary['year'])) ?></strong>
                                                <div class="d-md-none small text-muted">
                                                    Progress: <?= number_format($salary['monthly_progress'], 1) ?>%
                                                </div>
                                            </td>
                                            <td class="d-none d-md-table-cell"><?= number_format($salary['monthly_progress'], 1) ?>%</td>
                                            <td><strong>৳<?= number_format($salary['net_payable'], 2) ?></strong></td>
                                            <td>
                                                <span class="badge bg-<?= $salary['status'] === 'paid' ? 'success' : ($salary['status'] === 'approved' ? 'warning' : 'secondary') ?>">
                                                    <?= ucfirst($salary['status']) ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <a href="<?= BASE_URL ?>/salary/my_salary.php" class="btn btn-sm btn-outline-primary">View All</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row mt-4">
        <div class="col-12 col-lg-6 mb-4 mb-lg-0">
            <div class="card shadow-lg border-0">
                <div class="card-header bg-white border-bottom">
                    <h5 class="mb-0">
                        <i class="bi bi-wallet2 me-2 text-primary"></i>Advance Requests
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($pending_advances)): ?>
                        <div class="alert alert-info mb-3">
                            <i class="bi bi-info-circle me-2"></i>You have <?= count($pending_advances) ?> pending advance request(s).
                        </div>
                    <?php endif; ?>
                    <div class="d-flex flex-column flex-sm-row gap-2">
                        <a href="<?= BASE_URL ?>/advances/request.php" class="btn btn-primary">
                            <i class="bi bi-plus-circle me-2"></i>Request Advance
                        </a>
                        <a href="<?= BASE_URL ?>/advances/my_advances.php" class="btn btn-outline-secondary">
                            <i class="bi bi-list-ul me-2"></i>View My Advances
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-12 col-lg-6">
            <div class="card shadow-lg border-0">
                <div class="card-header bg-white border-bottom">
                    <h5 class="mb-0">
                        <i class="bi bi-cash-coin me-2 text-primary"></i>Advance Auto-Deduction Summary
                    </h5>
                </div>
                <div class="card-body">
                    <?php if ($auto_deduction): ?>
                        <div class="row g-3 mb-3">
                            <div class="col-6">
                                <div class="text-muted small mb-1">Total Advance Taken</div>
                                <div class="fw-bold fs-5 text-primary">৳<?= number_format($auto_deduction['total_advance'], 2) ?></div>
                            </div>
                            <div class="col-6">
                                <div class="text-muted small mb-1">Monthly Deduction</div>
                                <div class="fw-bold fs-5 text-info">৳<?= number_format($auto_deduction['monthly_deduction'], 2) ?></div>
                            </div>
                            <div class="col-6">
                                <div class="text-muted small mb-1">Remaining Due</div>
                                <div class="fw-bold fs-5 text-warning">৳<?= number_format($auto_deduction['remaining_due'], 2) ?></div>
                            </div>
                            <div class="col-6">
                                <div class="text-muted small mb-1">Status</div>
                                <span class="badge bg-success px-3 py-2">Active</span>
                            </div>
                        </div>
                        <div class="alert alert-info mb-0">
                            <i class="bi bi-info-circle me-2"></i>
                            <small>৳<?= number_format($auto_deduction['monthly_deduction'], 2) ?> will be deducted from your salary each month until the advance is fully cleared.</small>
                        </div>
                    <?php elseif ($completed_deduction): ?>
                        <div class="alert alert-success mb-0">
                            <i class="bi bi-check-circle me-2"></i>
                            <strong>Your advance is fully cleared. No more deductions.</strong>
                            <div class="mt-2 small">
                                <div>Total Advance: <strong>৳<?= number_format($completed_deduction['total_advance'], 2) ?></strong></div>
                                <?php if ($completed_deduction['updated_at']): ?>
                                    <div>Completed: <strong><?= date('M d, Y', strtotime($completed_deduction['updated_at'])) ?></strong></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="text-center text-muted py-3">
                            <i class="bi bi-info-circle fs-4 d-block mb-2"></i>
                            <p class="mb-0">No active advance auto-deduction schedule</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    'use strict';
    
    let updateInterval;
    let isUpdating = false;
    
    // Initialize on page load
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
    
    function init() {
        // Initialize tooltips
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
        
        // Load per-day percent
        loadPerDayPercent();
        
        // Load live progress immediately
        loadLiveProgress();
        
        // Set up auto-refresh every 30 seconds
        updateInterval = setInterval(loadLiveProgress, 30000);
        
        // Ensure progress bar is visible on load
        setTimeout(function() {
            const progressBar = document.getElementById('main-progress-bar');
            if (progressBar) {
                const currentWidth = progressBar.style.width;
                if (!currentWidth || currentWidth === '0%') {
                    const dataProgress = parseFloat(progressBar.getAttribute('data-progress')) || 0;
                    if (dataProgress > 0) {
                        progressBar.style.width = dataProgress + '%';
                    }
                }
            }
        }, 500);
        
        // Also refresh when page becomes visible
        document.addEventListener('visibilitychange', function() {
            if (!document.hidden) {
                loadLiveProgress();
            }
        });
    }
    
    function loadPerDayPercent() {
        const perDayDisplay = document.getElementById('per-day-percent-display');
        fetch('<?= BASE_URL ?>/api/compute/per_day_percent.php?month=<?= $current_month ?>&year=<?= $current_year ?>')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    perDayDisplay.innerHTML = '<strong class="text-primary">' + data.per_day_percent.toFixed(4) + '%</strong>';
                } else {
                    perDayDisplay.innerHTML = '<span class="text-danger">Error</span>';
                }
            })
            .catch(error => {
                perDayDisplay.innerHTML = '<span class="text-danger">Error</span>';
            });
    }
    
    function loadLiveProgress() {
        if (isUpdating) return;
        isUpdating = true;
        
        fetch('<?= BASE_URL ?>/api/get_live_progress.php')
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                if (data.success && data.data) {
                    updateProgressDisplay(data.data);
                    updateLastUpdateTime();
                } else {
                    console.error('Failed to load progress:', data);
                    // Don't show error to user, just log it
                }
                isUpdating = false;
            })
            .catch(error => {
                console.error('Error loading progress:', error);
                // Silently fail - progress bar will show initial value
                isUpdating = false;
            });
    }
    
    function updateProgressDisplay(data) {
        const avgProgress = parseFloat(data.avg_progress) || 0;
        const expectedProgress = parseFloat(data.expected_progress) || 0;
        const progressDiff = parseFloat(data.progress_difference) || 0;
        const progressStatus = data.progress_status || 'on-track';
        
        // Update main progress bar
        const progressBar = document.getElementById('main-progress-bar');
        if (!progressBar) {
            console.error('Progress bar element not found');
            return;
        }
        
        const progressBarText = document.getElementById('progress-bar-text');
        const avgProgressDisplay = document.getElementById('avg-progress-display');
        const progressStatusBadge = document.getElementById('progress-status-badge');
        const progressStatusText = document.getElementById('progress-status-text');
        
        // Determine color based on progress
        let barColor = 'bg-danger';
        let statusColor = 'danger';
        let statusText = 'Needs Improvement';
        let statusIcon = 'bi-exclamation-triangle';
        
        if (avgProgress >= 80) {
            barColor = 'bg-success';
            statusColor = 'success';
            statusText = 'Excellent';
            statusIcon = 'bi-trophy';
        } else if (avgProgress >= 60) {
            barColor = 'bg-warning';
            statusColor = 'warning';
            statusText = 'Good';
            statusIcon = 'bi-check-circle';
        }
        
        // Update progress bar
        progressBar.className = 'progress-bar progress-bar-striped ' + barColor;
        progressBar.setAttribute('data-progress', avgProgress);
        progressBar.setAttribute('aria-valuenow', avgProgress);
        
        // Update progress bar width - ensure it's always set
        // Get current width (remove % if present)
        let currentWidth = 0;
        if (progressBar.style.width) {
            const widthStr = progressBar.style.width.replace('%', '').trim();
            currentWidth = parseFloat(widthStr) || 0;
        }
        
        // Ensure avgProgress is a valid number
        const finalProgress = Math.max(0, Math.min(100, avgProgress));
        
        // Always update the width to ensure it displays
        // Force reflow first
        void progressBar.offsetHeight;
        
        // Set width immediately (no animation delay for reliability)
        progressBar.style.width = finalProgress + '%';
        
        // Also ensure it's visible
        progressBar.style.display = 'flex';
        progressBar.style.opacity = '1';
        
        progressBarText.textContent = avgProgress.toFixed(1) + '%';
        avgProgressDisplay.innerHTML = '<span class="text-' + statusColor + '">' + avgProgress.toFixed(2) + '%</span>';
        
        // Update status badge
        progressStatusBadge.className = 'badge bg-' + statusColor + ' px-3 py-2 fs-6';
        progressStatusText.innerHTML = '<i class="bi ' + statusIcon + ' me-1"></i>' + statusText;
        
        // Update stats
        document.getElementById('days-recorded').textContent = data.days_recorded || 0;
        document.getElementById('days-remaining').textContent = data.days_remaining || 0;
        document.getElementById('expected-progress').textContent = expectedProgress.toFixed(1) + '%';
        
        // Update difference with color
        const diffElement = document.getElementById('progress-difference');
        const diffValue = progressDiff.toFixed(1);
        if (progressDiff > 0) {
            diffElement.innerHTML = '<span class="text-success">+' + diffValue + '%</span>';
        } else if (progressDiff < 0) {
            diffElement.innerHTML = '<span class="text-danger">' + diffValue + '%</span>';
        } else {
            diffElement.innerHTML = '<span class="text-muted">' + diffValue + '%</span>';
        }
        
        // Update today's progress if available
        if (data.today_progress !== null) {
            const todaySection = document.getElementById('today-progress-section');
            const todayValue = document.getElementById('today-progress-value');
            const todayBar = document.getElementById('today-progress-bar');
            
            todaySection.style.display = 'block';
            todayValue.textContent = parseFloat(data.today_progress).toFixed(1) + '%';
            todayBar.style.width = data.today_progress + '%';
        } else {
            document.getElementById('today-progress-section').style.display = 'none';
        }
        
        // Update breakdown
        document.getElementById('ticket-percent-display').textContent = parseFloat(data.ticket_percent).toFixed(2) + '%';
        document.getElementById('group-percent-display').textContent = parseFloat(data.group_percent).toFixed(2) + '%';
        document.getElementById('base-progress-display').textContent = parseFloat(data.base_monthly_progress).toFixed(2) + '%';
    }
    
    function updateLastUpdateTime() {
        const lastUpdateElement = document.getElementById('last-update-time');
        const now = new Date();
        const timeString = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
        lastUpdateElement.textContent = 'Updated: ' + timeString;
        
        // Flash the badge to indicate update
        const badge = document.getElementById('live-progress-badge');
        badge.style.opacity = '0.5';
        setTimeout(function() {
            badge.style.opacity = '1';
        }, 200);
    }
    
    // Cleanup on page unload
    window.addEventListener('beforeunload', function() {
        if (updateInterval) {
            clearInterval(updateInterval);
        }
    });
})();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>

