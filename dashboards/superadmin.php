<?php
/**
 * dashboards/superadmin.php
 * Superadmin dashboard
 */

require_once __DIR__ . '/../auth_helper.php';
require_once __DIR__ . '/../helpers.php';

require_role(['superadmin']);

$user = current_user();
$pdo = getPDO();

// Get selected month/year or default to current month
$selected_month = isset($_GET['month']) ? intval($_GET['month']) : (int)date('n');
$selected_year = isset($_GET['year']) ? intval($_GET['year']) : (int)date('Y');

// Get system stats
$stats = [
    'total_users' => $pdo->query("SELECT COUNT(*) FROM users WHERE deleted_at IS NULL")->fetchColumn(),
    'total_staff' => $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'staff' AND deleted_at IS NULL")->fetchColumn(),
    'total_customers' => $pdo->query("SELECT COUNT(*) FROM customers WHERE deleted_at IS NULL")->fetchColumn(),
    'total_teams' => $pdo->query("SELECT COUNT(*) FROM teams WHERE deleted_at IS NULL")->fetchColumn(),
    'pending_advances' => $pdo->query("SELECT COUNT(*) FROM advances WHERE status = 'pending'")->fetchColumn(),
];

// Get total salary cost (sum of all staff monthly salaries from users table)
$stmt = $pdo->query("
    SELECT SUM(monthly_salary) as total 
    FROM users 
    WHERE role = 'staff' 
      AND (status = 'active' OR status IS NULL)
      AND deleted_at IS NULL
");
$salary_result = $stmt->fetch();
$total_salary_cost = $salary_result ? floatval($salary_result['total']) : 0;

// Get total profit fund (sum of all balances from profit_fund_balance table)
$stmt = $pdo->query("SELECT SUM(balance) as total FROM profit_fund_balance");
$profit_result = $stmt->fetch();
$total_profit_fund = $profit_result ? floatval($profit_result['total']) : 0;

// Get salary cost trend (last 12 months)
$stmt = $pdo->prepare("
    SELECT month, year, SUM(net_payable) as total_cost
    FROM salary_history
    WHERE (year * 12 + month) >= (YEAR(CURDATE()) * 12 + MONTH(CURDATE()) - 11)
    GROUP BY year, month
    ORDER BY year, month
");
$stmt->execute();
$salary_trend = $stmt->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="gradient-text mb-2">Superadmin Dashboard</h1>
            <p class="text-muted mb-0">Welcome back, <strong><?= h($user['name']) ?></strong>! Here's your system overview.</p>
        </div>
    </div>
    
    <!-- Statistics Cards -->
    <div class="row g-3 g-md-4 mb-4">
        <div class="col-6 col-md-3">
            <div class="stat-card animate-slide-up">
                <div class="stat-icon" style="background: linear-gradient(135deg, #007bff, #706fd3);">
                    <i class="bi bi-people-fill"></i>
                </div>
                <div class="stat-label small">Total Users</div>
                <div class="stat-value fs-5 fs-md-4" data-count="<?= $stats['total_users'] ?>"><?= $stats['total_users'] ?></div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card animate-slide-up" style="animation-delay: 0.1s">
                <div class="stat-icon" style="background: linear-gradient(135deg, #28a745, #20c997);">
                    <i class="bi bi-person-badge"></i>
                </div>
                <div class="stat-label small">Staff Members</div>
                <div class="stat-value fs-5 fs-md-4" data-count="<?= $stats['total_staff'] ?>"><?= $stats['total_staff'] ?></div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card animate-slide-up" style="animation-delay: 0.2s">
                <div class="stat-icon" style="background: linear-gradient(135deg, #17a2b8, #138496);">
                    <i class="bi bi-building"></i>
                </div>
                <div class="stat-label small">Customers</div>
                <div class="stat-value fs-5 fs-md-4" data-count="<?= $stats['total_customers'] ?>"><?= $stats['total_customers'] ?></div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <a href="<?= BASE_URL ?>/advances/index.php" class="text-decoration-none stat-card-link">
                <div class="stat-card animate-slide-up" style="animation-delay: 0.3s;">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #ffc107, #ff9800);">
                        <i class="bi bi-wallet2"></i>
                    </div>
                    <div class="stat-label small">Pending Advances</div>
                    <div class="stat-value fs-5 fs-md-4" data-count="<?= $stats['pending_advances'] ?>"><?= $stats['pending_advances'] ?></div>
                </div>
            </a>
        </div>
    </div>
    
    <!-- Financial Stats -->
    <div class="row g-3 g-md-4 mb-4">
        <div class="col-6 col-md-4">
            <div class="stat-card animate-slide-up" style="animation-delay: 0.4s">
                <div class="stat-icon" style="background: linear-gradient(135deg, #28a745, #20c997);">
                    <i class="bi bi-cash-stack"></i>
                </div>
                <div class="stat-label small">Total Salary Cost</div>
                <div class="stat-value fs-5 fs-md-4">৳<?= number_format($total_salary_cost, 2) ?></div>
            </div>
        </div>
        
        <div class="col-6 col-md-4">
            <div class="stat-card animate-slide-up" style="animation-delay: 0.5s">
                <div class="stat-icon" style="background: linear-gradient(135deg, #17a2b8, #138496);">
                    <i class="bi bi-piggy-bank"></i>
                </div>
                <div class="stat-label small">Total Profit Fund</div>
                <div class="stat-value fs-5 fs-md-4">৳<?= number_format($total_profit_fund, 2) ?></div>
            </div>
        </div>
    </div>
    
    <!-- Salary Cost Trend Chart -->
    <div class="row g-4 mb-4">
        <div class="col-12">
            <div class="stat-card animate-slide-up" style="animation-delay: 0.6s">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <div class="stat-label mb-1">Salary Cost Trend</div>
                        <h5 class="mb-0" style="background: linear-gradient(135deg, var(--color-electric-blue), var(--color-teal)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;">
                            Last 12 Months
                        </h5>
                    </div>
                    <div class="stat-icon" style="background: linear-gradient(135deg, #007bff, #0056b3); width: 60px; height: 60px;">
                        <i class="bi bi-graph-up"></i>
                    </div>
                </div>
                <div style="position: relative; height: 400px;">
                    <canvas id="salaryTrendChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Salary Cost Trend Chart
    const salaryTrendData = <?= json_encode($salary_trend) ?>;
    
    // Prepare chart data
    const labels = [];
    const data = [];
    
    // Create a map for all last 12 months
    const monthMap = {};
    salaryTrendData.forEach(item => {
        const key = `${item['year']}-${String(item['month']).padStart(2, '0')}`;
        monthMap[key] = parseFloat(item['total_cost']) || 0;
    });
    
    // Generate labels and data for last 12 months
    const today = new Date();
    for (let i = 11; i >= 0; i--) {
        const date = new Date(today.getFullYear(), today.getMonth() - i, 1);
        const month = date.getMonth() + 1;
        const year = date.getFullYear();
        const key = `${year}-${String(month).padStart(2, '0')}`;
        
        labels.push(date.toLocaleDateString('en-US', { month: 'short', year: 'numeric' }));
        data.push(monthMap[key] || 0);
    }
    
    const ctx = document.getElementById('salaryTrendChart').getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Total Salary Cost',
                data: data,
                borderColor: 'rgb(75, 192, 192)',
                backgroundColor: 'rgba(75, 192, 192, 0.2)',
                tension: 0.1,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: true,
                    position: 'top'
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return 'Total: ' + parseFloat(context.parsed.y).toLocaleString('en-US', {
                                minimumFractionDigits: 2,
                                maximumFractionDigits: 2
                            });
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return parseFloat(value).toLocaleString('en-US', {
                                minimumFractionDigits: 0,
                                maximumFractionDigits: 0
                            });
                        }
                    }
                }
            }
        }
    });
});
</script>

<style>
.stat-card-link {
    display: block;
    color: inherit;
}
.stat-card-link:hover {
    color: inherit;
    text-decoration: none;
}
.stat-card-link .stat-card:hover {
    transform: translateY(-4px);
    box-shadow: var(--shadow-xl);
}
</style>

<?php include __DIR__ . '/../includes/footer.php'; ?>

