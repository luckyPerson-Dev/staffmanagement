<?php
/**
 * includes/sidebar.php
 * Sidebar navigation menu
 */
$current_user = current_user();
$current_page = basename($_SERVER['PHP_SELF']);
$current_path = $_SERVER['REQUEST_URI'] ?? '';

// Helper function to check if menu item is active
function isActive($path, $exact = false) {
    global $current_path;
    
    // Get the path part from URL (remove query strings and BASE_URL)
    $current_path_clean = parse_url($current_path, PHP_URL_PATH);
    $path_clean = parse_url($path, PHP_URL_PATH);
    
    // Remove BASE_URL if present
    if (defined('BASE_URL')) {
        $base_path = parse_url(BASE_URL, PHP_URL_PATH);
        if ($base_path && strpos($current_path_clean, $base_path) === 0) {
            $current_path_clean = substr($current_path_clean, strlen($base_path));
        }
        if ($base_path && strpos($path_clean, $base_path) === 0) {
            $path_clean = substr($path_clean, strlen($base_path));
        }
    }
    
    // Normalize paths (remove leading/trailing slashes)
    $current_path_clean = trim($current_path_clean, '/');
    $path_clean = trim($path_clean, '/');
    
    if ($exact) {
        // Exact match for specific pages
        return $current_path_clean === $path_clean || 
               strpos('/' . $current_path_clean . '/', '/' . $path_clean . '/') !== false;
    } else {
        // For directory-based paths, check if it starts with the path
        $pathParts = explode('/', $path_clean);
        $currentParts = explode('/', $current_path_clean);
        
        // Check if current path starts with the menu path
        if (count($currentParts) > 0 && count($pathParts) > 0) {
            $menuDir = $pathParts[0];
            $currentDir = $currentParts[0];
            
            // Special handling for advances
            if ($menuDir === 'advances' && $currentDir === 'advances') {
                // Check if it's the exact page
                if (strpos($current_path_clean, 'advances/index.php') !== false) {
                    return $path_clean === 'advances/index.php';
                }
                if (strpos($current_path_clean, 'advances/my_advances.php') !== false) {
                    return $path_clean === 'advances/my_advances.php';
                }
                // For view.php, check if we're viewing an advance (should highlight index)
                if (strpos($current_path_clean, 'advances/view.php') !== false) {
                    return $path_clean === 'advances/index.php';
                }
            }
            
            // For other paths, check directory match
            return $menuDir === $currentDir;
        }
        return false;
    }
}
?>

<!-- Sidebar -->
<aside id="sidebar" class="sidebar">
    <nav class="sidebar-nav">
        <!-- Sidebar Toggle Button -->
        <div class="sidebar-toggle-container d-none d-lg-block">
            <button class="sidebar-toggle-btn-inline" type="button" id="sidebarToggle" aria-label="Toggle sidebar" onclick="if(typeof window.toggleSidebarNow === 'function') { console.log('Inline onclick triggered'); window.toggleSidebarNow(); } else { console.error('toggleSidebarNow not found!'); } return false;">
                <i class="bi bi-chevron-left"></i>
            </button>
        </div>
        <ul class="sidebar-menu">
            <!-- Dashboard -->
            <li class="sidebar-item">
                <a href="<?= rtrim(BASE_URL, '/') ?>/dashboard.php" class="sidebar-link <?= isActive('/dashboard.php', true) || isActive('dashboard.php', true) ? 'active' : '' ?>" data-page="dashboard" title="Dashboard">
                    <i class="bi bi-grid-3x3-gap-fill sidebar-icon"></i>
                    <span class="sidebar-text">Dashboard</span>
                </a>
            </li>
            
            <!-- Personal Section -->
            <?php if ($current_user['role'] === 'staff'): ?>
                <li class="sidebar-divider">
                    <span class="sidebar-divider-text">Personal</span>
                </li>
                <li class="sidebar-item">
                    <a href="<?= rtrim(BASE_URL, '/') ?>/teams/my_team.php" class="sidebar-link <?= isActive('/teams/my_team.php', true) || isActive('teams/my_team.php', true) ? 'active' : '' ?>" data-page="my-team" title="My Team">
                        <i class="bi bi-people-fill sidebar-icon"></i>
                        <span class="sidebar-text">My Team</span>
                    </a>
                </li>
                <li class="sidebar-item">
                    <a href="<?= rtrim(BASE_URL, '/') ?>/profit_fund/my_fund.php" class="sidebar-link <?= isActive('/profit_fund/my_fund.php', true) || isActive('profit_fund/my_fund.php', true) ? 'active' : '' ?>" data-page="my-profit-fund" title="My Profit Fund">
                        <i class="bi bi-safe2-fill sidebar-icon"></i>
                        <span class="sidebar-text">My Profit Fund</span>
                    </a>
                </li>
                <li class="sidebar-item">
                    <a href="<?= rtrim(BASE_URL, '/') ?>/advances/my_advances.php" class="sidebar-link <?= isActive('/advances/my_advances.php', true) || isActive('advances/my_advances.php', true) ? 'active' : '' ?>" data-page="advances-my" title="My Advances">
                        <i class="bi bi-wallet-fill sidebar-icon"></i>
                        <span class="sidebar-text">My Advances</span>
                    </a>
                </li>
                <li class="sidebar-item">
                    <a href="<?= rtrim(BASE_URL, '/') ?>/reports/staff_monthly_history.php?user_id=<?= $current_user['id'] ?>" class="sidebar-link <?= isActive('/reports/staff_monthly_history.php', true) || isActive('reports/staff_monthly_history.php', true) ? 'active' : '' ?>" data-page="my-progress-history" title="My Progress History">
                        <i class="bi bi-graph-up-arrow sidebar-icon"></i>
                        <span class="sidebar-text">My Progress History</span>
                    </a>
                </li>
            <?php elseif (in_array($current_user['role'], ['admin', 'accountant'])): ?>
                <li class="sidebar-divider">
                    <span class="sidebar-divider-text">Personal</span>
                </li>
                <li class="sidebar-item">
                    <a href="<?= rtrim(BASE_URL, '/') ?>/advances/my_advances.php" class="sidebar-link <?= isActive('/advances/my_advances.php', true) || isActive('advances/my_advances.php', true) ? 'active' : '' ?>" data-page="advances-my" title="My Advances">
                        <i class="bi bi-wallet-fill sidebar-icon"></i>
                        <span class="sidebar-text">My Advances</span>
                    </a>
                </li>
            <?php endif; ?>
            
            <!-- Management Section (Admin/Superadmin) -->
            <?php if (in_array($current_user['role'], ['superadmin', 'admin'])): ?>
                <li class="sidebar-divider">
                    <span class="sidebar-divider-text">Management</span>
                </li>
                <li class="sidebar-item">
                    <a href="<?= rtrim(BASE_URL, '/') ?>/users/index.php" class="sidebar-link <?= isActive('/users/index.php', true) || isActive('users/index.php', true) || isActive('users', false) ? 'active' : '' ?>" data-page="users" title="Users">
                        <i class="bi bi-person-badge-fill sidebar-icon"></i>
                        <span class="sidebar-text">Users</span>
                    </a>
                </li>
                <li class="sidebar-item">
                    <a href="<?= rtrim(BASE_URL, '/') ?>/teams/index.php" class="sidebar-link <?= isActive('/teams/index.php', true) || isActive('teams/index.php', true) || isActive('teams', false) ? 'active' : '' ?>" data-page="teams" title="Teams">
                        <i class="bi bi-diagram-3-fill sidebar-icon"></i>
                        <span class="sidebar-text">Teams</span>
                    </a>
                </li>
                <li class="sidebar-item">
                    <a href="<?= rtrim(BASE_URL, '/') ?>/customers/index.php" class="sidebar-link <?= isActive('/customers/index.php', true) || isActive('customers/index.php', true) || isActive('customers', false) ? 'active' : '' ?>" data-page="customers" title="Customers">
                        <i class="bi bi-building-fill sidebar-icon"></i>
                        <span class="sidebar-text">Customers</span>
                    </a>
                </li>
                <li class="sidebar-item">
                    <a href="<?= rtrim(BASE_URL, '/') ?>/progress/add.php" class="sidebar-link <?= isActive('/progress/add.php', true) || isActive('progress/add.php', true) || isActive('progress', false) ? 'active' : '' ?>" data-page="progress-add" title="Add Progress">
                        <i class="bi bi-plus-circle-fill sidebar-icon"></i>
                        <span class="sidebar-text">Add Progress</span>
                    </a>
                </li>
            <?php endif; ?>
            
            <!-- Financial Section (Admin/Superadmin/Accountant) -->
            <?php if (in_array($current_user['role'], ['superadmin', 'admin', 'accountant'])): ?>
                <li class="sidebar-divider">
                    <span class="sidebar-divider-text">Financial</span>
                </li>
                <li class="sidebar-item">
                    <a href="<?= rtrim(BASE_URL, '/') ?>/salary/index.php" class="sidebar-link <?= isActive('/salary/index.php', true) || isActive('salary/index.php', true) || isActive('salary', false) ? 'active' : '' ?>" data-page="salary" title="Salary & Payroll">
                        <i class="bi bi-bank sidebar-icon"></i>
                        <span class="sidebar-text">Salary & Payroll</span>
                    </a>
                </li>
            <?php endif; ?>
            
            <?php if (in_array($current_user['role'], ['superadmin', 'admin'])): ?>
                <li class="sidebar-item">
                    <a href="<?= rtrim(BASE_URL, '/') ?>/advances/index.php" class="sidebar-link <?= isActive('/advances/index.php', true) || isActive('advances/index.php', true) || (isActive('advances', false) && strpos($current_path, 'advances/view.php') === false) ? 'active' : '' ?>" data-page="advances" title="Advance Requests">
                        <i class="bi bi-wallet-fill sidebar-icon"></i>
                        <span class="sidebar-text">Advance Requests</span>
                    </a>
                </li>
                <li class="sidebar-item">
                    <a href="<?= rtrim(BASE_URL, '/') ?>/admin/advance_deduction.php" class="sidebar-link <?= isActive('/admin/advance_deduction.php', true) || isActive('admin/advance_deduction.php', true) ? 'active' : '' ?>" data-page="advance-deduction" title="Advance Auto-Deduction">
                        <i class="bi bi-currency-exchange sidebar-icon"></i>
                        <span class="sidebar-text">Advance Auto-Deduction</span>
                    </a>
                </li>
                <li class="sidebar-item">
                    <a href="<?= rtrim(BASE_URL, '/') ?>/profit_fund/withdrawals.php" class="sidebar-link <?= isActive('/profit_fund/withdrawals.php', true) || isActive('profit_fund/withdrawals.php', true) ? 'active' : '' ?>" data-page="profit-fund-withdrawals" title="Profit Fund Withdrawals">
                        <i class="bi bi-arrow-down-circle-fill sidebar-icon"></i>
                        <span class="sidebar-text">PF Withdrawals</span>
                    </a>
                </li>
            <?php endif; ?>
            
            <?php if ($current_user['role'] === 'superadmin'): ?>
                <li class="sidebar-item">
                    <a href="<?= rtrim(BASE_URL, '/') ?>/profit_fund/index.php" class="sidebar-link <?= isActive('/profit_fund/index.php', true) || isActive('profit_fund/index.php', true) || isActive('profit_fund', false) ? 'active' : '' ?>" data-page="profit-fund" title="Profit Fund">
                        <i class="bi bi-safe-fill sidebar-icon"></i>
                        <span class="sidebar-text">Profit Fund</span>
                    </a>
                </li>
            <?php endif; ?>
            
            <!-- Reports & Analytics Section (Admin/Superadmin) -->
            <?php if (in_array($current_user['role'], ['admin', 'superadmin'])): ?>
                <li class="sidebar-divider">
                    <span class="sidebar-divider-text">Reports & Analytics</span>
                </li>
                <li class="sidebar-item">
                    <a href="<?= rtrim(BASE_URL, '/') ?>/reports/daily_progress.php" class="sidebar-link <?= isActive('/reports/daily_progress.php', true) || isActive('reports/daily_progress.php', true) || isActive('reports', false) ? 'active' : '' ?>" data-page="reports-daily" title="Daily Progress Report">
                        <i class="bi bi-file-earmark-bar-graph-fill sidebar-icon"></i>
                        <span class="sidebar-text">Daily Progress Report</span>
                    </a>
                </li>
                <li class="sidebar-item">
                    <a href="<?= rtrim(BASE_URL, '/') ?>/reports/staff_monthly_history.php" class="sidebar-link <?= isActive('/reports/staff_monthly_history.php', true) || isActive('reports/staff_monthly_history.php', true) ? 'active' : '' ?>" data-page="reports-monthly" title="Staff Monthly History">
                        <i class="bi bi-calendar2-month-fill sidebar-icon"></i>
                        <span class="sidebar-text">Staff Monthly History</span>
                    </a>
                </li>
                <li class="sidebar-item">
                    <a href="<?= rtrim(BASE_URL, '/') ?>/admin/tickets.php" class="sidebar-link <?= isActive('/admin/tickets.php', true) || isActive('admin/tickets.php', true) ? 'active' : '' ?>" data-page="tickets" title="Manage Tickets">
                        <i class="bi bi-ticket-detailed-fill sidebar-icon"></i>
                        <span class="sidebar-text">Tickets</span>
                    </a>
                </li>
                <li class="sidebar-item">
                    <a href="<?= rtrim(BASE_URL, '/') ?>/admin/staff_tickets.php" class="sidebar-link <?= isActive('/admin/staff_tickets.php', true) || isActive('admin/staff_tickets.php', true) ? 'active' : '' ?>" data-page="staff-tickets" title="Staff Tickets">
                        <i class="bi bi-ticket-perforated-fill sidebar-icon"></i>
                        <span class="sidebar-text">Staff Tickets</span>
                    </a>
                </li>
            <?php endif; ?>
            
            <!-- System Section (Superadmin Only) -->
            <?php if ($current_user['role'] === 'superadmin'): ?>
                <li class="sidebar-divider">
                    <span class="sidebar-divider-text">System</span>
                </li>
                <li class="sidebar-item">
                    <a href="<?= rtrim(BASE_URL, '/') ?>/settings/index.php" class="sidebar-link <?= isActive('/settings/index.php', true) || isActive('settings/index.php', true) || (isActive('settings', false) && strpos($current_path, 'settings/website.php') === false) ? 'active' : '' ?>" data-page="settings" title="Settings">
                        <i class="bi bi-sliders sidebar-icon"></i>
                        <span class="sidebar-text">Settings</span>
                    </a>
                </li>
                <li class="sidebar-item">
                    <a href="<?= rtrim(BASE_URL, '/') ?>/settings/website.php" class="sidebar-link <?= isActive('/settings/website.php', true) || isActive('settings/website.php', true) ? 'active' : '' ?>" data-page="website-settings" title="Website Settings">
                        <i class="bi bi-globe2 sidebar-icon"></i>
                        <span class="sidebar-text">Website Settings</span>
                    </a>
                </li>
            <?php endif; ?>
        </ul>
        
        <!-- Logout Button at Bottom -->
        <div class="sidebar-footer">
            <a href="<?= rtrim(BASE_URL, '/') ?>/logout.php" class="sidebar-link sidebar-link-logout" title="Logout">
                <i class="bi bi-power sidebar-icon"></i>
                <span class="sidebar-text">Logout</span>
            </a>
        </div>
    </nav>
</aside>


