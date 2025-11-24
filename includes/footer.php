    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= BASE_URL ?>/assets/js/sidebar.js"></script>
    <script src="<?= BASE_URL ?>/assets/js/animations.js"></script>
    <script src="<?= BASE_URL ?>/assets/js/confirm-helpers.js"></script>
    <script src="<?= BASE_URL ?>/assets/js/dropdown-init.js"></script>
    <style>
    /* Top Navbar Mobile Fixes */
    @media (max-width: 992px) {
        .top-navbar {
            height: 56px !important;
            min-height: 56px;
            padding: 0.5rem 0;
        }
        
        .top-navbar .container-fluid {
            padding-left: 0.75rem !important;
            padding-right: 0.75rem !important;
        }
        
        .top-navbar .navbar-brand {
            font-size: 1rem;
            margin-left: 0.5rem !important;
        }
        
        .top-navbar .navbar-brand i {
            font-size: 1.25rem;
        }
        
        .top-navbar .nav-link {
            padding: 0.5rem !important;
            font-size: 1.1rem;
        }
        
        .top-navbar .btn {
            padding: 0.4rem 0.6rem;
            font-size: 0.875rem;
        }
        
        /* Sidebar toggle button visible on desktop only */
        
        /* Ensure top navbar stays visible when sidebar is open on mobile */
        .top-navbar {
            z-index: 1051 !important;
            margin-top: 0 !important;
            margin-bottom: 0 !important;
            padding-top: 0.5rem !important;
            padding-bottom: 0.5rem !important;
        }
        
        .sidebar.active ~ * .top-navbar,
        body:has(.sidebar.active) .top-navbar {
            z-index: 1051 !important;
            visibility: visible !important;
            opacity: 1 !important;
        }
        
        /* Remove any black space/gap - ensure no gaps between elements */
        .top-navbar .container-fluid {
            padding-top: 0 !important;
            padding-bottom: 0 !important;
            margin: 0 !important;
        }
        
        .top-navbar .sidebar-toggle-btn {
            margin: 0 !important;
        }
        
        .top-navbar .navbar-brand {
            margin: 0 !important;
            margin-left: 0.5rem !important;
        }
        
        /* Sidebar styles removed - using design-system.css instead */
        /* The sidebar styling is now handled by the main CSS file for consistency */
    }
    
    /* Sidebar toggle button handled in design-system.css */
    
    /* Ensure top navbar is always visible, even when sidebar is open */
    .top-navbar {
        z-index: 1051 !important;
        margin-top: 0 !important;
    }
    
    /* Remove any gaps or black spaces */
    .top-navbar .container-fluid {
        padding-top: 0 !important;
        padding-bottom: 0 !important;
    }
    
    /* Sidebar styles removed - using design-system.css instead */
    /* The sidebar styling is now handled by the main CSS file for consistency */
    
    /* Mobile Responsive Enhancements for Staff Pages */
    @media (max-width: 768px) {
        /* Ensure tables are scrollable on mobile */
        .table-responsive {
            -webkit-overflow-scrolling: touch;
            overflow-x: auto;
        }
        
        /* Reduce padding on mobile */
        .card-body {
            padding: 1rem !important;
        }
        
        /* Make stat cards more compact on mobile */
        .stat-card {
            padding: 1rem !important;
        }
        
        .stat-value {
            font-size: 1.5rem !important;
        }
        
        /* Progress bars should be readable on mobile */
        .progress {
            min-height: 30px;
        }
        
        .progress-bar {
            font-size: 12px !important;
        }
        
        /* Stack buttons vertically on mobile */
        .btn-group {
            flex-direction: column;
            width: 100%;
        }
        
        .btn-group .btn {
            width: 100%;
            margin-bottom: 0.5rem;
        }
        
        /* Make headers more compact */
        h1.gradient-text {
            font-size: 1.75rem !important;
        }
        
        /* Ensure cards stack properly */
        .row > [class*="col-"] {
            margin-bottom: 1rem;
        }
        
        /* Better spacing for mobile */
        .container-fluid {
            padding-left: 1rem;
            padding-right: 1rem;
        }
    }
    
    @media (max-width: 576px) {
        /* Extra small devices */
        .stat-value {
            font-size: 1.25rem !important;
        }
        
        .stat-label {
            font-size: 0.75rem !important;
        }
        
        /* Compact badges */
        .badge {
            font-size: 0.7rem;
            padding: 0.25rem 0.5rem;
        }
        
        /* Smaller progress bars on very small screens */
        .progress {
            height: 25px !important;
        }
        
        /* Compact table cells */
        .table td, .table th {
            padding: 0.5rem !important;
            font-size: 0.875rem;
        }
    }
    </style>
    
    <!-- Mobile Bottom Navigation (only visible on mobile) -->
    <?php 
    // Get current user if not already set
    if (!isset($current_user) && function_exists('current_user')) {
        $current_user = current_user();
    }
    if (isset($current_user)): ?>
    <nav class="mobile-bottom-nav d-md-none" id="mobileBottomNav">
        <div class="bottom-nav-container">
            <?php
            $user_role = $current_user['role'];
            $current_path = strtolower($_SERVER['REQUEST_URI'] ?? '');
            
            // Define navigation items based on role
            $nav_items = [];
            
            // Common items for all users
            $nav_items[] = [
                'href' => BASE_URL . '/dashboard.php',
                'icon' => 'bi-speedometer2',
                'page' => 'dashboard',
                'title' => 'Dashboard'
            ];
            
            $nav_items[] = [
                'href' => BASE_URL . '/users/edit_profile.php',
                'icon' => 'bi-person-gear',
                'page' => 'edit-profile',
                'title' => 'Profile'
            ];
            
            // Staff-specific items
            if ($user_role === 'staff') {
                $nav_items[] = [
                    'href' => BASE_URL . '/teams/my_team.php',
                    'icon' => 'bi-people',
                    'page' => 'my-team',
                    'title' => 'My Team'
                ];
                
                $nav_items[] = [
                    'href' => BASE_URL . '/profit_fund/my_fund.php',
                    'icon' => 'bi-piggy-bank',
                    'page' => 'my-profit-fund',
                    'title' => 'Profit Fund'
                ];
                
                $nav_items[] = [
                    'href' => BASE_URL . '/advances/my_advances.php',
                    'icon' => 'bi-wallet2',
                    'page' => 'advances-my',
                    'title' => 'Advances'
                ];
            }
            
            // Admin/Superadmin items
            if (in_array($user_role, ['admin', 'superadmin'])) {
                $nav_items[] = [
                    'href' => BASE_URL . '/progress/add.php',
                    'icon' => 'bi-plus-circle',
                    'page' => 'progress-add',
                    'title' => 'Add Progress'
                ];
                
                $nav_items[] = [
                    'href' => BASE_URL . '/users/index.php',
                    'icon' => 'bi-people',
                    'page' => 'users',
                    'title' => 'Users'
                ];
                
                $nav_items[] = [
                    'href' => BASE_URL . '/customers/index.php',
                    'icon' => 'bi-shop',
                    'page' => 'customers',
                    'title' => 'Customers'
                ];
                
                if ($user_role === 'superadmin') {
                    $nav_items[] = [
                        'href' => BASE_URL . '/profit_fund/index.php',
                        'icon' => 'bi-piggy-bank',
                        'page' => 'profit-fund',
                        'title' => 'Profit Fund'
                    ];
                }
            }
            
            // Accountant/Superadmin - Salary
            if (in_array($user_role, ['accountant', 'superadmin'])) {
                $nav_items[] = [
                    'href' => BASE_URL . '/salary/index.php',
                    'icon' => 'bi-cash-stack',
                    'page' => 'salary',
                    'title' => 'Salary'
                ];
            }
            
            // Limit to 5 items for better mobile UX
            $nav_items = array_slice($nav_items, 0, 5);
            
            foreach ($nav_items as $item):
                // Determine if this item is active
                $is_active = false;
                $item_path = parse_url($item['href'], PHP_URL_PATH);
                $item_path_lower = strtolower($item_path);
                
                if ($item['page'] === 'dashboard' && strpos($current_path, '/dashboard.php') !== false) {
                    $is_active = true;
                } elseif ($item['page'] === 'edit-profile' && strpos($current_path, '/users/edit_profile.php') !== false) {
                    $is_active = true;
                } elseif ($item['page'] === 'my-team' && strpos($current_path, '/teams/my_team.php') !== false) {
                    $is_active = true;
                } elseif ($item['page'] === 'my-profit-fund' && strpos($current_path, '/profit_fund/my_fund.php') !== false) {
                    $is_active = true;
                } elseif ($item['page'] === 'advances-my' && strpos($current_path, '/advances/my_advances.php') !== false) {
                    $is_active = true;
                } elseif ($item['page'] === 'progress-add' && strpos($current_path, '/progress/add.php') !== false) {
                    $is_active = true;
                } elseif ($item['page'] === 'users' && strpos($current_path, '/users/index.php') !== false) {
                    $is_active = true;
                } elseif ($item['page'] === 'customers' && strpos($current_path, '/customers/') !== false) {
                    $is_active = true;
                } elseif ($item['page'] === 'profit-fund' && strpos($current_path, '/profit_fund/index.php') !== false) {
                    $is_active = true;
                } elseif ($item['page'] === 'salary' && strpos($current_path, '/salary/') !== false) {
                    $is_active = true;
                }
            ?>
            <a href="<?= h($item['href']) ?>" 
               class="bottom-nav-item <?= $is_active ? 'active' : '' ?>" 
               data-page="<?= h($item['page']) ?>"
               data-bs-toggle="tooltip"
               data-bs-placement="top"
               data-bs-title="<?= h($item['title']) ?>"
               title="<?= h($item['title']) ?>">
                <i class="bi <?= h($item['icon']) ?>"></i>
                <span class="bottom-nav-label"><?= h($item['title']) ?></span>
            </a>
            <?php endforeach; ?>
        </div>
    </nav>
    <?php endif; ?>
    
    <style>
    /* Mobile Bottom Navigation Styles */
    .mobile-bottom-nav {
        position: fixed;
        bottom: 0;
        left: 0;
        right: 0;
        background: var(--color-bg-primary);
        border-top: 1px solid var(--color-border);
        box-shadow: 0 -4px 20px rgba(0, 0, 0, 0.15);
        z-index: 1050;
        padding: 0.5rem 0 calc(0.5rem + env(safe-area-inset-bottom));
        display: none; /* Hidden by default, shown only on mobile */
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        overflow: visible;
        transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1), opacity 0.3s ease;
    }
    
    /* Hide bottom nav when sidebar is open */
    .sidebar.active ~ * .mobile-bottom-nav,
    body:has(.sidebar.active) .mobile-bottom-nav,
    .mobile-bottom-nav.sidebar-open {
        transform: translateY(100%);
        opacity: 0;
        pointer-events: none;
    }
    
    @media (max-width: 992px) {
        .mobile-bottom-nav {
            display: block;
        }
        
        /* Add padding to body to prevent content from being hidden behind bottom nav */
        body {
            padding-bottom: 65px;
        }
        
        /* Extra space for labels that appear below */
        .mobile-bottom-nav {
            padding-bottom: calc(1.2rem + env(safe-area-inset-bottom));
        }
    }
    
    .bottom-nav-container {
        display: flex;
        justify-content: space-around;
        align-items: center;
        max-width: 100%;
        margin: 0 auto;
        padding: 0 0.5rem;
        gap: 0.15rem;
    }
    
    .bottom-nav-item {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        flex: 1;
        padding: 0.4rem 0.5rem;
        color: var(--color-text-secondary);
        text-decoration: none;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        border-radius: 10px;
        min-width: 45px;
        position: relative;
        overflow: hidden;
        background: transparent;
        border: 2px solid transparent;
    }
    
    .bottom-nav-item::before {
        content: '';
        position: absolute;
        top: 0;
        left: 50%;
        transform: translateX(-50%) scaleX(0);
        width: 50px;
        height: 3px;
        background: linear-gradient(90deg, var(--color-electric-blue), #706fd3);
        border-radius: 0 0 3px 3px;
        transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    
    .bottom-nav-item i {
        font-size: 1.1rem;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        position: relative;
        z-index: 1;
    }
    
    .bottom-nav-label {
        position: absolute;
        bottom: -40px;
        left: 50%;
        transform: translateX(-50%) translateY(5px);
        background: var(--color-bg-primary);
        color: var(--color-text-primary);
        padding: 0.35rem 0.65rem;
        border-radius: 8px;
        font-size: 0.7rem;
        font-weight: 600;
        white-space: nowrap;
        opacity: 0;
        visibility: hidden;
        pointer-events: none;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        border: 1px solid var(--color-border);
        z-index: 1000;
        letter-spacing: 0.3px;
    }
    
    .bottom-nav-label::before {
        content: '';
        position: absolute;
        top: -5px;
        left: 50%;
        transform: translateX(-50%);
        width: 0;
        height: 0;
        border-left: 5px solid transparent;
        border-right: 5px solid transparent;
        border-bottom: 5px solid var(--color-bg-primary);
    }
    
    .bottom-nav-item:hover .bottom-nav-label,
    .bottom-nav-item:active .bottom-nav-label,
    .bottom-nav-item:focus .bottom-nav-label {
        opacity: 1;
        visibility: visible;
        transform: translateX(-50%) translateY(-5px);
    }
    
    /* Show label on active item */
    .bottom-nav-item.active .bottom-nav-label {
        opacity: 1;
        visibility: visible;
        transform: translateX(-50%) translateY(-5px);
        background: var(--color-electric-blue);
        color: white;
        border-color: var(--color-electric-blue);
    }
    
    .bottom-nav-item.active .bottom-nav-label::before {
        border-bottom-color: var(--color-electric-blue);
    }
    
    .bottom-nav-item:hover {
        color: var(--color-electric-blue);
        background: rgba(0, 123, 255, 0.06);
        transform: translateY(-1px);
    }
    
    .bottom-nav-item:active {
        transform: translateY(0) scale(0.95);
    }
    
    /* Active State - Enhanced */
    .bottom-nav-item.active {
        color: var(--color-electric-blue);
        background: linear-gradient(135deg, rgba(0, 123, 255, 0.08), rgba(112, 111, 211, 0.08));
        border: 1.5px solid var(--color-electric-blue);
        box-shadow: 0 2px 8px rgba(0, 123, 255, 0.15);
    }
    
    .bottom-nav-item.active::before {
        transform: translateX(-50%) scaleX(1);
    }
    
    .bottom-nav-item.active i {
        transform: scale(1.1);
        color: var(--color-electric-blue);
        filter: drop-shadow(0 2px 4px rgba(0, 123, 255, 0.3));
    }
    
    /* Ripple effect on click */
    .bottom-nav-item::after {
        content: '';
        position: absolute;
        top: 50%;
        left: 50%;
        width: 0;
        height: 0;
        border-radius: 50%;
        background: rgba(0, 123, 255, 0.3);
        transform: translate(-50%, -50%);
        transition: width 0.6s, height 0.6s;
        pointer-events: none;
    }
    
    .bottom-nav-item:active::after {
        width: 200px;
        height: 200px;
    }
    
    /* Smooth page transition animation */
    @keyframes slideInUp {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    .bottom-nav-item {
        animation: slideInUp 0.3s ease-out backwards;
    }
    
    .bottom-nav-item:nth-child(1) { animation-delay: 0.05s; }
    .bottom-nav-item:nth-child(2) { animation-delay: 0.1s; }
    .bottom-nav-item:nth-child(3) { animation-delay: 0.15s; }
    .bottom-nav-item:nth-child(4) { animation-delay: 0.2s; }
    .bottom-nav-item:nth-child(5) { animation-delay: 0.25s; }
    
    /* Pulse animation for active item */
    @keyframes pulse {
        0%, 100% {
            box-shadow: 0 4px 12px rgba(0, 123, 255, 0.2);
        }
        50% {
            box-shadow: 0 4px 20px rgba(0, 123, 255, 0.4);
        }
    }
    
    .bottom-nav-item.active {
        animation: slideInUp 0.3s ease-out, pulse 2s ease-in-out infinite;
    }
    
    /* Ensure content is not hidden behind bottom nav on mobile */
    @media (max-width: 992px) {
        .container-fluid {
            padding-bottom: 1rem;
        }
        
        /* Smooth page transitions */
        body {
            transition: opacity 0.3s ease;
        }
        
        body.page-transitioning {
            opacity: 0.7;
        }
    }
    
    /* Icon enhancements */
    .bottom-nav-item i {
        display: inline-block;
        vertical-align: middle;
    }
    
    /* Better touch targets for mobile */
    @media (max-width: 992px) {
        .bottom-nav-item {
            min-height: 44px;
            min-width: 50px;
            padding: 0.35rem 0.4rem;
        }
        
        .bottom-nav-item i {
            font-size: 1rem;
        }
        
        /* Show label on tap for mobile */
        .bottom-nav-item.tapped .bottom-nav-label {
            opacity: 1;
            visibility: visible;
            transform: translateX(-50%) translateY(-5px);
        }
        
        /* Keep label visible for active item on mobile */
        .bottom-nav-item.active .bottom-nav-label {
            opacity: 1;
            visibility: visible;
            transform: translateX(-50%) translateY(-5px);
        }
    }
    </style>
    
    <script>
    // Initialize Bootstrap dropdowns
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize all dropdowns
        var dropdownElementList = [].slice.call(document.querySelectorAll('.dropdown-toggle'));
        var dropdownList = dropdownElementList.map(function (dropdownToggleEl) {
            return new bootstrap.Dropdown(dropdownToggleEl);
        });
        
        // Mobile bottom navigation active state and animations
        const currentPath = window.location.pathname.toLowerCase();
        const bottomNavItems = document.querySelectorAll('.bottom-nav-item');
        
        // Function to update active state
        function updateActiveState() {
            bottomNavItems.forEach(item => {
                const itemPage = item.getAttribute('data-page');
                
                // Remove active class from all items first
                item.classList.remove('active');
                
                // Check if current page matches
                let isActive = false;
                
                if (itemPage === 'dashboard' && currentPath.includes('/dashboard.php')) {
                    isActive = true;
                } else if (itemPage === 'edit-profile' && currentPath.includes('/users/edit_profile.php')) {
                    isActive = true;
                } else if (itemPage === 'my-team' && currentPath.includes('/teams/my_team.php')) {
                    isActive = true;
                } else if (itemPage === 'my-profit-fund' && currentPath.includes('/profit_fund/my_fund.php')) {
                    isActive = true;
                } else if (itemPage === 'advances-my' && currentPath.includes('/advances/my_advances.php')) {
                    isActive = true;
                } else if (itemPage === 'progress-add' && currentPath.includes('/progress/add.php')) {
                    isActive = true;
                } else if (itemPage === 'users' && currentPath.includes('/users/index.php')) {
                    isActive = true;
                } else if (itemPage === 'customers' && currentPath.includes('/customers/')) {
                    isActive = true;
                } else if (itemPage === 'profit-fund' && currentPath.includes('/profit_fund/index.php')) {
                    isActive = true;
                } else if (itemPage === 'salary' && currentPath.includes('/salary/')) {
                    isActive = true;
                }
                
                if (isActive) {
                    // Add active class with animation
                    setTimeout(() => {
                        item.classList.add('active');
                    }, 50);
                }
            });
        }
        
        // Initialize Bootstrap tooltips
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl, {
                trigger: 'hover focus',
                placement: 'top',
                delay: { show: 300, hide: 100 }
            });
        });
        
        // Initial active state update
        updateActiveState();
        
        // Add smooth page transition on navigation and show label on tap
        bottomNavItems.forEach(item => {
            // Show label on tap/click for mobile
            let tapTimer;
            item.addEventListener('touchstart', function() {
                this.classList.add('tapped');
                clearTimeout(tapTimer);
                tapTimer = setTimeout(() => {
                    this.classList.remove('tapped');
                }, 2000); // Hide after 2 seconds
            });
            
            item.addEventListener('click', function(e) {
                const href = this.getAttribute('href');
                
                // Show label briefly on click
                this.classList.add('tapped');
                setTimeout(() => {
                    this.classList.remove('tapped');
                }, 1500);
                
                // Only add transition if navigating to a different page
                if (href && !this.classList.contains('active')) {
                    // Add transition class to body
                    document.body.classList.add('page-transitioning');
                    
                    // Remove transition class after navigation
                    setTimeout(() => {
                        document.body.classList.remove('page-transitioning');
                    }, 300);
                    
                    // Add click animation
                    this.style.transform = 'scale(0.9)';
                    setTimeout(() => {
                        this.style.transform = '';
                    }, 150);
                }
            });
        });
        
        // Update active state on popstate (back/forward navigation)
        window.addEventListener('popstate', function() {
            setTimeout(updateActiveState, 100);
        });
        
        // Smooth scroll to top when navigating (optional)
        bottomNavItems.forEach(item => {
            item.addEventListener('click', function() {
                if (window.scrollY > 100) {
                    window.scrollTo({
                        top: 0,
                        behavior: 'smooth'
                    });
                }
            });
        });
    });
    </script>
</body>
</html>

