<?php
/**
 * includes/header.php
 * Common header for all pages
 */
$current_user = current_user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php
    require_once __DIR__ . '/../helpers.php';
    $website_name = get_setting('website_name', 'Staff Management');
    $website_favicon = get_setting('website_favicon', '');
    $website_logo = get_setting('website_logo', '');
    ?>
    <title><?= isset($page_title) ? h($page_title) . ' - ' . h($website_name) : h($website_name) ?></title>
    <?php if ($website_favicon && file_exists(__DIR__ . '/../' . $website_favicon)): ?>
        <link rel="icon" type="image/x-icon" href="<?= BASE_URL ?>/<?= h($website_favicon) ?>?v=<?= filemtime(__DIR__ . '/../' . $website_favicon) ?>">
    <?php endif; ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>/assets/css/design-system.css?v=2.0" rel="stylesheet">
    <link href="<?= BASE_URL ?>/assets/css/notifications.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= BASE_URL ?>/assets/js/notifications.js"></script>
</head>
<body>
    <!-- Top Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-light sticky-top top-navbar">
        <div class="container-fluid px-3 px-md-4 py-2">
            <!-- Brand -->
            <a class="navbar-brand ms-2 ms-md-3" href="<?= rtrim(BASE_URL, '/') ?>/dashboard.php">
                <?php if ($website_logo && file_exists(__DIR__ . '/../' . $website_logo)): ?>
                    <img src="<?= BASE_URL ?>/<?= h($website_logo) ?>?v=<?= filemtime(__DIR__ . '/../' . $website_logo) ?>" 
                         alt="<?= h($website_name) ?>" 
                         style="height: 32px; width: auto; margin-right: 8px; object-fit: contain;">
                <?php else: ?>
                    <i class="bi bi-people-fill me-2"></i>
                <?php endif; ?>
                <span class="d-none d-sm-inline"><?= h($website_name) ?></span>
            </a>
            
            <!-- Right Side Content -->
            <div class="ms-auto d-flex align-items-center gap-2 gap-md-3">
                <?php
                // Get notification count
                require_once __DIR__ . '/../helpers.php';
                $pdo = getPDO();
                $notificationCount = 0;
                if (isset($current_user['id'])) {
                    try {
                        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND read = 0");
                        $stmt->execute([$current_user['id']]);
                        $result = $stmt->fetch();
                        $notificationCount = $result['count'] ?? 0;
                    } catch (Exception $e) {
                        $notificationCount = 0;
                    }
                }
                
                // Get pending advances count for superadmin
                $pendingAdvancesCount = 0;
                if ($current_user['role'] === 'superadmin') {
                    try {
                        $stmt = $pdo->query("SELECT COUNT(*) as count FROM advances WHERE status = 'pending'");
                        $result = $stmt->fetch();
                        $pendingAdvancesCount = $result['count'] ?? 0;
                    } catch (Exception $e) {
                        $pendingAdvancesCount = 0;
                    }
                }
                ?>
                
                <!-- Notifications -->
                <?php if ($notificationCount > 0): ?>
                    <a class="nav-link position-relative p-2" href="<?= rtrim(BASE_URL, '/') ?>/notifications/index.php" title="Notifications">
                        <i class="bi bi-bell-fill fs-5"></i>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.65rem; padding: 2px 5px;">
                            <?= $notificationCount > 9 ? '9+' : $notificationCount ?>
                        </span>
                    </a>
                <?php endif; ?>
                
                <!-- Pending Advances (Superadmin only) -->
                <?php if ($pendingAdvancesCount > 0 && $current_user['role'] === 'superadmin'): ?>
                    <a class="nav-link position-relative p-2" href="<?= rtrim(BASE_URL, '/') ?>/advances/index.php" title="Pending Advances">
                        <i class="bi bi-wallet2 fs-5"></i>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-warning" style="font-size: 0.65rem; padding: 2px 5px;">
                            <?= $pendingAdvancesCount > 9 ? '9+' : $pendingAdvancesCount ?>
                        </span>
                    </a>
                <?php endif; ?>
                
                <!-- User Info Dropdown -->
                <div class="dropdown d-none d-md-block user-profile-dropdown">
                    <button class="btn user-profile-btn dropdown-toggle d-flex align-items-center gap-2" type="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                        <div class="user-avatar">
                            <i class="bi bi-person-circle"></i>
                        </div>
                        <div class="d-flex flex-column align-items-start text-start user-info">
                            <span class="user-name"><?= h($current_user['name']) ?></span>
                            <small class="user-role"><?= ucfirst($current_user['role']) ?></small>
                        </div>
                        <i class="bi bi-chevron-down ms-1 dropdown-arrow"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-lg user-dropdown-menu" aria-labelledby="userDropdown">
                        <li class="dropdown-header">
                            <div class="d-flex align-items-center gap-2 px-2 py-2">
                                <div class="user-avatar-large">
                                    <i class="bi bi-person-circle"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="fw-semibold"><?= h($current_user['name']) ?></div>
                                    <small class="text-muted"><?= h($current_user['email']) ?></small>
                                </div>
                            </div>
                        </li>
                        <li><hr class="dropdown-divider my-1"></li>
                        <li>
                            <a class="dropdown-item" href="<?= rtrim(BASE_URL, '/') ?>/users/edit_profile.php">
                                <i class="bi bi-person-gear me-2"></i>Edit Profile
                            </a>
                        </li>
                        <li><hr class="dropdown-divider my-1"></li>
                        <li>
                            <a class="dropdown-item text-danger" href="<?= rtrim(BASE_URL, '/') ?>/logout.php">
                                <i class="bi bi-box-arrow-right me-2"></i>Logout
                            </a>
                        </li>
                    </ul>
                </div>
                
                <!-- Mobile User Info with Dropdown -->
                <div class="dropdown d-md-none user-profile-dropdown">
                    <button class="btn user-profile-btn dropdown-toggle d-flex align-items-center gap-2" type="button" id="userDropdownMobile" data-bs-toggle="dropdown" aria-expanded="false">
                        <div class="user-avatar-small">
                            <i class="bi bi-person-circle"></i>
                        </div>
                        <span class="fw-semibold small user-name-mobile"><?= h(explode(' ', $current_user['name'])[0]) ?></span>
                        <i class="bi bi-chevron-down ms-1 dropdown-arrow"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-lg user-dropdown-menu" aria-labelledby="userDropdownMobile">
                        <li class="dropdown-header">
                            <div class="d-flex align-items-center gap-2 px-2 py-2">
                                <div class="user-avatar-large">
                                    <i class="bi bi-person-circle"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="fw-semibold"><?= h($current_user['name']) ?></div>
                                    <small class="text-muted"><?= h($current_user['email']) ?></small>
                                </div>
                            </div>
                        </li>
                        <li><hr class="dropdown-divider my-1"></li>
                        <li>
                            <a class="dropdown-item" href="<?= rtrim(BASE_URL, '/') ?>/users/edit_profile.php">
                                <i class="bi bi-person-gear me-2"></i>Edit Profile
                            </a>
                        </li>
                        <li><hr class="dropdown-divider my-1"></li>
                        <li>
                            <a class="dropdown-item text-danger" href="<?= rtrim(BASE_URL, '/') ?>/logout.php">
                                <i class="bi bi-box-arrow-right me-2"></i>Logout
                            </a>
                        </li>
                    </ul>
                </div>
                
            </div>
        </div>
    </nav>
    
    <!-- Include Sidebar -->
    <?php include __DIR__ . '/sidebar.php'; ?>

