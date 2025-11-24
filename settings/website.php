<?php
/**
 * settings/website.php
 * Website settings management (superadmin only)
 */

require_once __DIR__ . '/../auth_helper.php';
require_once __DIR__ . '/../helpers.php';

require_role(['superadmin']);

$pdo = getPDO();
$success = $_GET['success'] ?? '';
$error = $_GET['error'] ?? '';

// Check database connection
$db_connected = false;
$db_error = '';
try {
    $pdo->query("SELECT 1");
    $db_connected = true;
} catch (Exception $e) {
    $db_connected = false;
    $db_error = $e->getMessage();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token.';
    } elseif (isset($_POST['action']) && $_POST['action'] === 'restart_website') {
        // Handle website restart
        require_role(['superadmin']);
        
        try {
            $pdo->beginTransaction();
            
            // Get superadmin and admin user IDs to preserve
            $stmt = $pdo->prepare("SELECT id FROM users WHERE role IN ('superadmin', 'admin') AND deleted_at IS NULL");
            $stmt->execute();
            $preserved_user_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            if (empty($preserved_user_ids)) {
                throw new Exception('No superadmin or admin accounts found. Cannot restart website.');
            }
            
            // Log the restart action before clearing audit logs
            log_audit(current_user()['id'], 'system', 'website_restart', null, 'Website restart initiated - All data will be cleared except superadmin/admin accounts');
            
            // Clear all data tables (in order to respect foreign key constraints)
            // First, clear dependent data tables
            $tables_to_clear = [
                'daily_progress',
                'attendance',
                'attendance_qr_codes',
                'salary_history',
                'profit_fund',
                'profit_fund_balance',
                'profit_fund_withdrawals',
                'advances',
                'advance_auto_deductions',
                'payroll_run_log',
                'monthly_tickets',
                'staff_tickets',
                'bonuses',
                'loans',
                'loan_payments',
                'expenses',
                'notifications',
                'messages',
                'message_attachments',
                'documents',
                'support_tickets',
                'support_ticket_replies',
                'team_members',
                'team_objectives',
                'bulk_payments',
                'analytics_cache',
                'ai_insights',
                'customer_groups',
                'remember_tokens',
                'user_ip_restrictions'
            ];
            
            foreach ($tables_to_clear as $table) {
                try {
                    $pdo->exec("DELETE FROM `$table`");
                } catch (Exception $e) {
                    // Table might not exist, continue
                    error_log("Warning: Could not clear table $table: " . $e->getMessage());
                }
            }
            
            // Clear customers and teams
            $pdo->exec("DELETE FROM customers");
            $pdo->exec("DELETE FROM teams");
            
            // Delete all users except superadmin and admin
            $placeholders = implode(',', array_fill(0, count($preserved_user_ids), '?'));
            $stmt = $pdo->prepare("DELETE FROM users WHERE id NOT IN ($placeholders)");
            $stmt->execute($preserved_user_ids);
            
            // Reset user-related data for preserved users
            // Clear any team memberships for preserved users (they can be re-added)
            $placeholders = implode(',', array_fill(0, count($preserved_user_ids), '?'));
            $stmt = $pdo->prepare("DELETE FROM team_members WHERE user_id IN ($placeholders)");
            $stmt->execute($preserved_user_ids);
            
            // Reset status and other fields for preserved users
            $placeholders = implode(',', array_fill(0, count($preserved_user_ids), '?'));
            $stmt = $pdo->prepare("
                UPDATE users 
                SET status = 'active',
                    status_reason = NULL,
                    status_changed_at = NULL,
                    status_changed_by = NULL,
                    monthly_salary = 0.00,
                    deleted_at = NULL,
                    updated_at = UTC_TIMESTAMP()
                WHERE id IN ($placeholders)
            ");
            $stmt->execute($preserved_user_ids);
            
            $pdo->commit();
            
            $success = 'Website restarted successfully. All data has been cleared except superadmin and admin accounts.';
            header('Location: ' . BASE_URL . '/settings/website.php?success=' . urlencode($success));
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Failed to restart website: ' . $e->getMessage();
            error_log("Website restart error: " . $e->getMessage());
        }
    } else {
        try {
            $pdo->beginTransaction();
            
            // Update website name
            if (isset($_POST['website_name'])) {
                update_setting('website_name', trim($_POST['website_name']));
            }
            
            // Handle logo upload
            if (isset($_FILES['website_logo']) && $_FILES['website_logo']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = __DIR__ . '/../uploads/logo/';
                
                // Ensure parent directory exists
                $parent_dir = dirname($upload_dir);
                if (!is_dir($parent_dir)) {
                    @mkdir($parent_dir, 0777, true);
                }
                
                // Ensure upload directory exists with proper permissions
                if (!is_dir($upload_dir)) {
                    @mkdir($upload_dir, 0777, true);
                }
                
                // Try multiple permission levels to ensure writability
                if (!is_writable($upload_dir)) {
                    @chmod($upload_dir, 0777); // Try 777 first (most permissive)
                    if (!is_writable($upload_dir)) {
                        @chmod($upload_dir, 0775); // Fallback to 775
                    }
                    if (!is_writable($upload_dir)) {
                        throw new Exception('Upload directory is not writable. Please run: chmod 777 uploads/logo/');
                    }
                }
                
                $file = $_FILES['website_logo'];
                $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/svg+xml', 'image/webp'];
                $max_size = 2 * 1024 * 1024; // 2MB
                
                if (!in_array($file['type'], $allowed_types)) {
                    throw new Exception('Invalid file type. Allowed: JPEG, PNG, GIF, SVG, WebP');
                }
                
                if ($file['size'] > $max_size) {
                    throw new Exception('File size exceeds 2MB limit');
                }
                
                $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                $filename = 'logo.' . $extension;
                $filepath = $upload_dir . $filename;
                
                // Delete ALL old logo files (to handle extension changes)
                // This ensures old files with different extensions are removed
                $logo_files = glob($upload_dir . 'logo.*');
                if ($logo_files) {
                    foreach ($logo_files as $old_file) {
                        if (is_file($old_file)) {
                            @unlink($old_file);
                        }
                    }
                }
                
                // Check if we can write to the file path
                $test_file = $upload_dir . '.test_write';
                if (@file_put_contents($test_file, 'test') === false) {
                    @unlink($test_file);
                    throw new Exception('Cannot write to upload directory. Please set permissions: chmod -R 777 uploads/logo/');
                }
                @unlink($test_file);
                
                if (move_uploaded_file($file['tmp_name'], $filepath)) {
                    @chmod($filepath, 0666); // Make file readable/writable by all
                    update_setting('website_logo', 'uploads/logo/' . $filename);
                } else {
                    $last_error = error_get_last();
                    $error_msg = 'Failed to upload logo';
                    if ($last_error && strpos($last_error['message'], 'Permission denied') !== false) {
                        $error_msg .= '. Permission denied. Please run: chmod -R 777 uploads/logo/';
                    } elseif (!is_writable($upload_dir)) {
                        $error_msg .= '. Directory is not writable. Please run: chmod -R 777 uploads/logo/';
                    }
                    throw new Exception($error_msg);
                }
            }
            
            // Handle logo removal
            if (isset($_POST['remove_logo']) && $_POST['remove_logo'] === '1') {
                $upload_dir = __DIR__ . '/../uploads/logo/';
                // Delete ALL logo files
                $logo_files = glob($upload_dir . 'logo.*');
                if ($logo_files) {
                    foreach ($logo_files as $old_file) {
                        if (is_file($old_file)) {
                            @unlink($old_file);
                        }
                    }
                }
                update_setting('website_logo', '');
            }
            
            // Handle favicon removal
            if (isset($_POST['remove_favicon']) && $_POST['remove_favicon'] === '1') {
                $upload_dir = __DIR__ . '/../uploads/logo/';
                // Delete ALL favicon files
                $favicon_files = glob($upload_dir . 'favicon.*');
                if ($favicon_files) {
                    foreach ($favicon_files as $old_file) {
                        if (is_file($old_file)) {
                            @unlink($old_file);
                        }
                    }
                }
                update_setting('website_favicon', '');
            }
            
            // Handle favicon upload
            if (isset($_FILES['website_favicon']) && $_FILES['website_favicon']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = __DIR__ . '/../uploads/logo/';
                
                // Ensure parent directory exists
                $parent_dir = dirname($upload_dir);
                if (!is_dir($parent_dir)) {
                    @mkdir($parent_dir, 0777, true);
                }
                
                // Ensure upload directory exists with proper permissions
                if (!is_dir($upload_dir)) {
                    @mkdir($upload_dir, 0777, true);
                }
                
                // Try multiple permission levels to ensure writability
                if (!is_writable($upload_dir)) {
                    @chmod($upload_dir, 0777); // Try 777 first (most permissive)
                    if (!is_writable($upload_dir)) {
                        @chmod($upload_dir, 0775); // Fallback to 775
                    }
                    if (!is_writable($upload_dir)) {
                        throw new Exception('Upload directory is not writable. Please run: chmod 777 uploads/logo/');
                    }
                }
                
                $file = $_FILES['website_favicon'];
                $allowed_mime_types = [
                    'image/x-icon', 
                    'image/vnd.microsoft.icon', 
                    'image/ico', 
                    'image/x-ico',
                    'image/png', 
                    'image/svg+xml'
                ];
                $allowed_extensions = ['ico', 'png', 'svg'];
                $max_size = 512 * 1024; // 512KB
                
                $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                
                // Validate by both MIME type and file extension
                $valid_mime = in_array($file['type'], $allowed_mime_types);
                $valid_extension = in_array($extension, $allowed_extensions);
                
                if (!$valid_mime && !$valid_extension) {
                    throw new Exception('Invalid favicon type. Allowed: ICO, PNG, SVG. Detected type: ' . $file['type'] . ', extension: ' . $extension);
                }
                
                if ($file['size'] > $max_size) {
                    throw new Exception('Favicon size exceeds 512KB limit');
                }
                
                $filename = 'favicon.' . $extension;
                $filepath = $upload_dir . $filename;
                
                // Delete ALL old favicon files (to handle extension changes)
                // This ensures old files with different extensions are removed
                $favicon_files = glob($upload_dir . 'favicon.*');
                if ($favicon_files) {
                    foreach ($favicon_files as $old_file) {
                        if (is_file($old_file)) {
                            @unlink($old_file);
                        }
                    }
                }
                
                // Check if we can write to the file path
                $test_file = $upload_dir . '.test_write';
                if (@file_put_contents($test_file, 'test') === false) {
                    @unlink($test_file);
                    throw new Exception('Cannot write to upload directory. Please set permissions: chmod -R 777 uploads/logo/');
                }
                @unlink($test_file);
                
                if (move_uploaded_file($file['tmp_name'], $filepath)) {
                    @chmod($filepath, 0666); // Make file readable/writable by all
                    update_setting('website_favicon', 'uploads/logo/' . $filename);
                } else {
                    $last_error = error_get_last();
                    $error_msg = 'Failed to upload favicon';
                    if ($last_error && strpos($last_error['message'], 'Permission denied') !== false) {
                        $error_msg .= '. Permission denied. Please run: chmod -R 777 uploads/logo/';
                    } elseif (!is_writable($upload_dir)) {
                        $error_msg .= '. Directory is not writable. Please run: chmod -R 777 uploads/logo/';
                    }
                    throw new Exception($error_msg);
                }
            }
            
            // Update other settings
            $settings = [
                'website_email' => trim($_POST['website_email'] ?? ''),
                'website_phone' => trim($_POST['website_phone'] ?? ''),
                'website_address' => trim($_POST['website_address'] ?? ''),
                'website_timezone' => trim($_POST['website_timezone'] ?? 'UTC'),
                'website_language' => trim($_POST['website_language'] ?? 'en'),
            ];
            
            foreach ($settings as $key => $value) {
                update_setting($key, $value);
            }
            
            $pdo->commit();
            
            log_audit(current_user()['id'], 'update', 'website_settings', null, 'Updated website settings');
            
            $success = 'Website settings updated successfully.';
            header('Location: ' . BASE_URL . '/settings/website.php?success=' . urlencode($success));
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = $e->getMessage();
        }
    }
}

// Get current settings
$current_settings = [
    'website_name' => get_setting('website_name', 'Staff Management'),
    'website_logo' => get_setting('website_logo', ''),
    'website_favicon' => get_setting('website_favicon', ''),
    'website_email' => get_setting('website_email', ''),
    'website_phone' => get_setting('website_phone', ''),
    'website_address' => get_setting('website_address', ''),
    'website_timezone' => get_setting('website_timezone', 'UTC'),
    'website_language' => get_setting('website_language', 'en'),
];

$page_title = 'Website Settings';
include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="mb-4">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3">
            <div>
                <h1 class="gradient-text mb-2 fs-3 fs-md-2">Website Settings</h1>
                <p class="text-muted mb-0 small">Configure website name, logo, and basic information</p>
            </div>
        </div>
    </div>
    
    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show animate-fade-in mb-4" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i>
            <span class="small"><?= h($success) ?></span>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show animate-fade-in mb-4" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <span class="small"><?= h($error) ?></span>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <!-- Database Connection Status -->
    <div class="card shadow-lg border-0 mb-4 animate-slide-up">
        <div class="card-header bg-white border-bottom p-3 p-md-4">
            <h5 class="mb-0 fw-semibold">
                <i class="bi bi-database me-2 <?= $db_connected ? 'text-success' : 'text-danger' ?>"></i>Database Connection
            </h5>
        </div>
        <div class="card-body p-3 p-md-4">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <div class="d-flex align-items-center mb-2">
                        <span class="badge px-3 py-2 me-3 shadow-sm <?= $db_connected ? 'bg-success' : 'bg-danger' ?>" style="border: none;">
                            <?= $db_connected ? 'Connected' : 'Disconnected' ?>
                        </span>
                        <span class="text-muted small">
                            <?= $db_connected ? 'Database connection is active' : 'Database connection failed' ?>
                        </span>
                    </div>
                    <?php if (!$db_connected && $db_error): ?>
                        <div class="alert alert-danger mb-0 mt-2 small">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            <strong>Error:</strong> <?= h($db_error) ?>
                        </div>
                    <?php endif; ?>
                </div>
                <button type="button" class="btn btn-sm btn-outline-primary" onclick="checkDatabase()">
                    <i class="bi bi-arrow-clockwise me-1"></i>Refresh
                </button>
            </div>
        </div>
    </div>
    
    <form method="POST" action="" enctype="multipart/form-data" id="websiteSettingsForm">
        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
        
        <!-- Website Identity Section -->
        <div class="card shadow-lg border-0 mb-4 animate-slide-up" style="animation-delay: 0.1s">
            <div class="card-header bg-white border-bottom p-3 p-md-4">
                <h5 class="mb-0 fw-semibold">
                    <i class="bi bi-globe me-2 text-primary"></i>Website Identity
                </h5>
                <small class="text-muted d-block mt-1">Configure website name and branding</small>
            </div>
            <div class="card-body p-3 p-md-4">
                <div class="row g-3 g-md-4">
                    <div class="col-12 col-md-6">
                        <div class="form-group-card p-3 rounded-3" style="background: linear-gradient(135deg, rgba(0, 123, 255, 0.05), rgba(112, 111, 211, 0.05)); border: 1px solid rgba(0, 123, 255, 0.1);">
                            <label for="website_name" class="form-label fw-semibold mb-2">
                                <i class="bi bi-type me-2 text-primary"></i>Website Name
                                <span class="text-danger">*</span>
                            </label>
                            <input type="text" 
                                   class="form-control form-control-lg shadow-sm" 
                                   id="website_name" 
                                   name="website_name" 
                                   value="<?= h($current_settings['website_name']) ?>" 
                                   required
                                   placeholder="Staff Management"
                                   maxlength="100">
                            <small class="text-muted d-block mt-2">
                                <i class="bi bi-info-circle me-1"></i>This name appears in the header and browser title
                            </small>
                        </div>
                    </div>
                    
                    <div class="col-12 col-md-6">
                        <div class="form-group-card p-3 rounded-3" style="background: linear-gradient(135deg, rgba(40, 167, 69, 0.05), rgba(32, 201, 151, 0.05)); border: 1px solid rgba(40, 167, 69, 0.1);">
                            <label for="website_language" class="form-label fw-semibold mb-2">
                                <i class="bi bi-translate me-2 text-success"></i>Language
                            </label>
                            <select class="form-select form-select-lg shadow-sm" id="website_language" name="website_language">
                                <option value="en" <?= $current_settings['website_language'] === 'en' ? 'selected' : '' ?>>English</option>
                                <option value="bn" <?= $current_settings['website_language'] === 'bn' ? 'selected' : '' ?>>বাংলা (Bengali)</option>
                                <option value="es" <?= $current_settings['website_language'] === 'es' ? 'selected' : '' ?>>Español</option>
                                <option value="fr" <?= $current_settings['website_language'] === 'fr' ? 'selected' : '' ?>>Français</option>
                            </select>
                            <small class="text-muted d-block mt-2">
                                <i class="bi bi-info-circle me-1"></i>Default language for the website
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Logo & Favicon Section -->
        <div class="card shadow-lg border-0 mb-4 animate-slide-up" style="animation-delay: 0.2s">
            <div class="card-header bg-white border-bottom p-3 p-md-4">
                <h5 class="mb-0 fw-semibold">
                    <i class="bi bi-image me-2 text-info"></i>Logo & Favicon
                </h5>
                <small class="text-muted d-block mt-1">Upload website logo and favicon</small>
            </div>
            <div class="card-body p-3 p-md-4">
                <div class="row g-3 g-md-4">
                    <div class="col-12 col-md-6">
                        <div class="form-group-card p-3 rounded-3" style="background: linear-gradient(135deg, rgba(0, 123, 255, 0.05), rgba(112, 111, 211, 0.05)); border: 1px solid rgba(0, 123, 255, 0.1);">
                            <label for="website_logo" class="form-label fw-semibold mb-2">
                                <i class="bi bi-image me-2 text-primary"></i>Website Logo
                            </label>
                            
                            <?php if ($current_settings['website_logo'] && file_exists(__DIR__ . '/../' . $current_settings['website_logo'])): ?>
                                <div class="mb-3 position-relative d-inline-block">
                                    <img src="<?= BASE_URL ?>/<?= h($current_settings['website_logo']) ?>?v=<?= filemtime(__DIR__ . '/../' . $current_settings['website_logo']) ?>" 
                                         alt="Current Logo" 
                                         class="img-thumbnail shadow-sm" 
                                         style="max-width: 200px; max-height: 100px; object-fit: contain;"
                                         id="current_logo_preview">
                                    <button type="button" 
                                            class="btn btn-sm btn-danger position-absolute top-0 end-0 m-1" 
                                            onclick="removeLogo()"
                                            title="Remove Logo">
                                        <i class="bi bi-x-circle"></i>
                                    </button>
                                </div>
                                <input type="hidden" name="remove_logo" id="remove_logo" value="0">
                            <?php endif; ?>
                            
                            <input type="file" 
                                   class="form-control form-control-lg shadow-sm" 
                                   id="website_logo" 
                                   name="website_logo" 
                                   accept="image/jpeg,image/png,image/gif,image/svg+xml,image/webp">
                            <small class="text-muted d-block mt-2">
                                <i class="bi bi-info-circle me-1"></i>Max size: 2MB. Formats: JPEG, PNG, GIF, SVG, WebP
                            </small>
                        </div>
                    </div>
                    
                    <div class="col-12 col-md-6">
                        <div class="form-group-card p-3 rounded-3" style="background: linear-gradient(135deg, rgba(255, 193, 7, 0.05), rgba(255, 152, 0, 0.05)); border: 1px solid rgba(255, 193, 7, 0.1);">
                            <label for="website_favicon" class="form-label fw-semibold mb-2">
                                <i class="bi bi-star me-2 text-warning"></i>Favicon
                            </label>
                            
                            <?php if ($current_settings['website_favicon'] && file_exists(__DIR__ . '/../' . $current_settings['website_favicon'])): ?>
                                <div class="mb-3 position-relative d-inline-block">
                                    <img src="<?= BASE_URL ?>/<?= h($current_settings['website_favicon']) ?>?v=<?= filemtime(__DIR__ . '/../' . $current_settings['website_favicon']) ?>" 
                                         alt="Current Favicon" 
                                         class="img-thumbnail shadow-sm" 
                                         style="max-width: 64px; max-height: 64px; object-fit: contain;"
                                         id="current_favicon_preview">
                                    <button type="button" 
                                            class="btn btn-sm btn-danger position-absolute top-0 end-0 m-1" 
                                            onclick="removeFavicon()"
                                            title="Remove Favicon">
                                        <i class="bi bi-x-circle"></i>
                                    </button>
                                </div>
                                <input type="hidden" name="remove_favicon" id="remove_favicon" value="0">
                            <?php endif; ?>
                            
                            <input type="file" 
                                   class="form-control form-control-lg shadow-sm" 
                                   id="website_favicon" 
                                   name="website_favicon" 
                                   accept="image/x-icon,image/png,image/svg+xml">
                            <small class="text-muted d-block mt-2">
                                <i class="bi bi-info-circle me-1"></i>Max size: 512KB. Formats: ICO, PNG, SVG
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Contact Information Section -->
        <div class="card shadow-lg border-0 mb-4 animate-slide-up" style="animation-delay: 0.3s">
            <div class="card-header bg-white border-bottom p-3 p-md-4">
                <h5 class="mb-0 fw-semibold">
                    <i class="bi bi-envelope me-2 text-success"></i>Contact Information
                </h5>
                <small class="text-muted d-block mt-1">Website contact details</small>
            </div>
            <div class="card-body p-3 p-md-4">
                <div class="row g-3 g-md-4">
                    <div class="col-12 col-md-6">
                        <div class="form-group-card p-3 rounded-3" style="background: linear-gradient(135deg, rgba(0, 123, 255, 0.05), rgba(112, 111, 211, 0.05)); border: 1px solid rgba(0, 123, 255, 0.1);">
                            <label for="website_email" class="form-label fw-semibold mb-2">
                                <i class="bi bi-envelope me-2 text-primary"></i>Email Address
                            </label>
                            <input type="email" 
                                   class="form-control form-control-lg shadow-sm" 
                                   id="website_email" 
                                   name="website_email" 
                                   value="<?= h($current_settings['website_email']) ?>" 
                                   placeholder="contact@example.com">
                            <small class="text-muted d-block mt-2">
                                <i class="bi bi-info-circle me-1"></i>Contact email for the website
                            </small>
                        </div>
                    </div>
                    
                    <div class="col-12 col-md-6">
                        <div class="form-group-card p-3 rounded-3" style="background: linear-gradient(135deg, rgba(23, 162, 184, 0.05), rgba(19, 132, 150, 0.05)); border: 1px solid rgba(23, 162, 184, 0.1);">
                            <label for="website_phone" class="form-label fw-semibold mb-2">
                                <i class="bi bi-telephone me-2 text-info"></i>Phone Number
                            </label>
                            <input type="tel" 
                                   class="form-control form-control-lg shadow-sm" 
                                   id="website_phone" 
                                   name="website_phone" 
                                   value="<?= h($current_settings['website_phone']) ?>" 
                                   placeholder="+1 234 567 8900">
                            <small class="text-muted d-block mt-2">
                                <i class="bi bi-info-circle me-1"></i>Contact phone number
                            </small>
                        </div>
                    </div>
                    
                    <div class="col-12">
                        <div class="form-group-card p-3 rounded-3" style="background: linear-gradient(135deg, rgba(108, 117, 125, 0.05), rgba(73, 80, 87, 0.05)); border: 1px solid rgba(108, 117, 125, 0.1);">
                            <label for="website_address" class="form-label fw-semibold mb-2">
                                <i class="bi bi-geo-alt me-2 text-muted"></i>Address
                            </label>
                            <textarea class="form-control form-control-lg shadow-sm" 
                                      id="website_address" 
                                      name="website_address" 
                                      rows="3" 
                                      placeholder="Enter full address"><?= h($current_settings['website_address']) ?></textarea>
                            <small class="text-muted d-block mt-2">
                                <i class="bi bi-info-circle me-1"></i>Physical or mailing address
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Timezone Section -->
        <div class="card shadow-lg border-0 mb-4 animate-slide-up" style="animation-delay: 0.4s">
            <div class="card-header bg-white border-bottom p-3 p-md-4">
                <h5 class="mb-0 fw-semibold">
                    <i class="bi bi-clock me-2 text-warning"></i>Timezone
                </h5>
                <small class="text-muted d-block mt-1">Set the default timezone for the website</small>
            </div>
            <div class="card-body p-3 p-md-4">
                <div class="form-group-card p-3 rounded-3" style="background: linear-gradient(135deg, rgba(255, 193, 7, 0.05), rgba(255, 152, 0, 0.05)); border: 1px solid rgba(255, 193, 7, 0.1);">
                    <label for="website_timezone" class="form-label fw-semibold mb-2">
                        <i class="bi bi-globe me-2 text-warning"></i>Timezone
                    </label>
                    <select class="form-select form-select-lg shadow-sm" id="website_timezone" name="website_timezone">
                        <option value="UTC" <?= $current_settings['website_timezone'] === 'UTC' ? 'selected' : '' ?>>UTC</option>
                        <option value="America/New_York" <?= $current_settings['website_timezone'] === 'America/New_York' ? 'selected' : '' ?>>Eastern Time (ET)</option>
                        <option value="America/Chicago" <?= $current_settings['website_timezone'] === 'America/Chicago' ? 'selected' : '' ?>>Central Time (CT)</option>
                        <option value="America/Denver" <?= $current_settings['website_timezone'] === 'America/Denver' ? 'selected' : '' ?>>Mountain Time (MT)</option>
                        <option value="America/Los_Angeles" <?= $current_settings['website_timezone'] === 'America/Los_Angeles' ? 'selected' : '' ?>>Pacific Time (PT)</option>
                        <option value="Europe/London" <?= $current_settings['website_timezone'] === 'Europe/London' ? 'selected' : '' ?>>London (GMT)</option>
                        <option value="Europe/Paris" <?= $current_settings['website_timezone'] === 'Europe/Paris' ? 'selected' : '' ?>>Paris (CET)</option>
                        <option value="Asia/Dhaka" <?= $current_settings['website_timezone'] === 'Asia/Dhaka' ? 'selected' : '' ?>>Dhaka (BST)</option>
                        <option value="Asia/Kolkata" <?= $current_settings['website_timezone'] === 'Asia/Kolkata' ? 'selected' : '' ?>>Mumbai (IST)</option>
                        <option value="Asia/Tokyo" <?= $current_settings['website_timezone'] === 'Asia/Tokyo' ? 'selected' : '' ?>>Tokyo (JST)</option>
                    </select>
                    <small class="text-muted d-block mt-2">
                        <i class="bi bi-info-circle me-1"></i>All dates and times will be displayed in this timezone
                    </small>
                </div>
            </div>
        </div>
        
        <!-- Action Buttons -->
        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
            <a href="<?= BASE_URL ?>/dashboard.php" class="btn btn-secondary btn-lg w-100 w-md-auto shadow-sm">
                <i class="bi bi-x-circle me-1"></i>Cancel
            </a>
            <button type="submit" class="btn btn-primary btn-lg w-100 w-md-auto shadow-sm">
                <i class="bi bi-check-circle me-1"></i>Save Settings
            </button>
        </div>
    </form>
    
    <!-- Website Restart Section (Superadmin Only) -->
    <div class="card shadow-lg border-0 mb-4 animate-slide-up" style="animation-delay: 0.5s; border-left: 4px solid #dc3545 !important;">
        <div class="card-header bg-white border-bottom p-3 p-md-4" style="background: linear-gradient(135deg, rgba(220, 53, 69, 0.05), rgba(255, 107, 107, 0.05));">
            <h5 class="mb-0 fw-semibold text-danger">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>Danger Zone
            </h5>
            <small class="text-muted d-block mt-1">Irreversible actions</small>
        </div>
        <div class="card-body p-3 p-md-4">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                <div class="flex-grow-1">
                    <h6 class="fw-semibold mb-2">
                        <i class="bi bi-arrow-repeat me-2 text-danger"></i>Restart Website
                    </h6>
                    <p class="text-muted small mb-2">
                        This will permanently delete all data from the website except superadmin and admin accounts. 
                        All staff members, customers, teams, progress records, salaries, and other data will be cleared.
                    </p>
                    <div class="alert alert-warning small mb-0" style="background: rgba(255, 193, 7, 0.1); border: 1px solid rgba(255, 193, 7, 0.3);">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        <strong>Warning:</strong> This action cannot be undone. Make sure you have a backup if needed.
                    </div>
                </div>
                <div class="flex-shrink-0">
                    <form method="POST" action="" id="restartForm" class="d-inline">
                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                        <input type="hidden" name="action" value="restart_website">
                        <button type="button" 
                                class="btn btn-danger btn-lg shadow-sm" 
                                onclick="confirmRestart()">
                            <i class="bi bi-arrow-repeat me-2"></i>Restart Website
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.form-group-card {
    transition: all 0.3s ease;
}

.form-group-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.form-group-card input:focus,
.form-group-card select:focus,
.form-group-card textarea:focus {
    border-color: var(--color-electric-blue);
    box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.15);
}

@media (max-width: 767px) {
    .form-group-card {
        margin-bottom: 1rem;
    }
    
    .card-body {
        padding: 1rem !important;
    }
    
    .card-header {
        padding: 1rem !important;
    }
}
</style>

<script>
function checkDatabase() {
    location.reload();
}

document.getElementById('websiteSettingsForm').addEventListener('submit', function(e) {
    const submitBtn = this.querySelector('button[type="submit"]');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Saving...';
});

async function confirmRestart() {
    const message = 'Are you absolutely sure you want to restart the website?\n\n' +
                   'This will PERMANENTLY DELETE:\n' +
                   '• All staff members (except admin/superadmin)\n' +
                   '• All customers and teams\n' +
                   '• All progress records and attendance\n' +
                   '• All salary history and payroll data\n' +
                   '• All advances and profit fund data\n' +
                   '• All tickets and notifications\n' +
                   '• All other application data\n\n' +
                   'Only superadmin and admin accounts will be preserved.\n\n' +
                   'THIS ACTION CANNOT BE UNDONE!\n\n' +
                   'Type "RESTART" in the confirmation dialog to proceed.';
    
    // Use Notify system if available, otherwise use browser confirm
    if (typeof window.Notify !== 'undefined') {
        try {
            // Custom prompt for Notify system
            const confirmed = await window.Notify.confirm(
                'This will permanently delete ALL data except admin/superadmin accounts. Type "RESTART" to confirm.',
                'Restart Website - DANGER ZONE',
                'I understand, Restart Now',
                'Cancel',
                'danger'
            );
            
            if (confirmed) {
                // Double confirmation with prompt
                const confirmationText = prompt('Please type "RESTART" (all caps) to confirm:\n\n' + message);
                if (confirmationText === 'RESTART') {
                    const form = document.getElementById('restartForm');
                    const submitBtn = form.querySelector('button[type="button"]');
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Restarting...';
                    form.submit();
                } else {
                    alert('Restart cancelled. You must type "RESTART" exactly to confirm.');
                }
            }
        } catch (error) {
            console.error('Error in confirmRestart:', error);
            // Fallback to browser confirm
            if (confirm(message + '\n\nClick OK to proceed with restart.')) {
                const confirmationText = prompt('Please type "RESTART" (all caps) to confirm:');
                if (confirmationText === 'RESTART') {
                    document.getElementById('restartForm').submit();
                } else {
                    alert('Restart cancelled. You must type "RESTART" exactly to confirm.');
                }
            }
        }
    } else {
        // Fallback to browser confirm and prompt
        if (confirm(message + '\n\nClick OK to proceed with restart.')) {
            const confirmationText = prompt('Please type "RESTART" (all caps) to confirm:');
            if (confirmationText === 'RESTART') {
                const form = document.getElementById('restartForm');
                const submitBtn = form.querySelector('button[type="button"]');
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Restarting...';
                form.submit();
            } else {
                alert('Restart cancelled. You must type "RESTART" exactly to confirm.');
            }
        }
    }
}

// Preview logo before upload
document.getElementById('website_logo').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const preview = document.createElement('img');
            preview.src = e.target.result;
            preview.className = 'img-thumbnail shadow-sm mb-3';
            preview.style.maxWidth = '200px';
            preview.style.maxHeight = '100px';
            preview.style.objectFit = 'contain';
            
            const existingPreview = this.parentElement.querySelector('img');
            if (existingPreview && existingPreview.src.includes('logo')) {
                existingPreview.replaceWith(preview);
            } else {
                this.parentElement.insertBefore(preview, this);
            }
        }.bind(this);
        reader.readAsDataURL(file);
    }
});

// Preview favicon before upload
document.getElementById('website_favicon').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const preview = document.createElement('img');
            preview.src = e.target.result;
            preview.className = 'img-thumbnail shadow-sm mb-3';
            preview.style.maxWidth = '64px';
            preview.style.maxHeight = '64px';
            preview.style.objectFit = 'contain';
            
            const existingPreview = this.parentElement.querySelector('img');
            if (existingPreview && existingPreview.src.includes('favicon')) {
                existingPreview.replaceWith(preview);
            } else {
                this.parentElement.insertBefore(preview, this);
            }
        }.bind(this);
        reader.readAsDataURL(file);
    }
});

// Remove logo function
function removeLogo() {
    if (confirm('Are you sure you want to remove the website logo? This action cannot be undone.')) {
        document.getElementById('remove_logo').value = '1';
        const logoPreview = document.getElementById('current_logo_preview');
        if (logoPreview) {
            logoPreview.style.opacity = '0.5';
            logoPreview.style.filter = 'grayscale(100%)';
        }
        document.getElementById('website_logo').value = '';
    }
}

// Remove favicon function
function removeFavicon() {
    if (confirm('Are you sure you want to remove the favicon? This action cannot be undone.')) {
        document.getElementById('remove_favicon').value = '1';
        const faviconPreview = document.getElementById('current_favicon_preview');
        if (faviconPreview) {
            faviconPreview.style.opacity = '0.5';
            faviconPreview.style.filter = 'grayscale(100%)';
        }
        document.getElementById('website_favicon').value = '';
    }
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>

