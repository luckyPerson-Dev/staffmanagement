<?php
/**
 * settings/index.php
 * System settings management (superadmin only)
 */

require_once __DIR__ . '/../auth_helper.php';
require_once __DIR__ . '/../helpers.php';

require_role(['superadmin']);

$pdo = getPDO();
$success = $_GET['success'] ?? '';
$error = $_GET['error'] ?? '';

// Handle database export
if (isset($_GET['action']) && $_GET['action'] === 'export_database') {
    require_role(['superadmin']);
    
    if (!verify_csrf_token($_GET['csrf_token'] ?? '')) {
        header('Location: ' . BASE_URL . '/settings/index.php?error=' . urlencode('Invalid CSRF token.'));
        exit;
    }
    
    try {
        $pdo = getPDO();
        $db_name = DB_NAME;
        $filename = 'database_backup_' . date('Y-m-d_His') . '.sql';
        
        // Start SQL dump
        $output = "-- Database Backup\n";
        $output .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
        $output .= "-- Database: " . $db_name . "\n\n";
        $output .= "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n";
        $output .= "SET time_zone = \"+00:00\";\n";
        $output .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";
        
        // Get all tables
        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($tables as $table) {
            // Export table structure
            $output .= "-- Table structure for table `$table`\n";
            $output .= "DROP TABLE IF EXISTS `$table`;\n";
            $create_table = $pdo->query("SHOW CREATE TABLE `$table`")->fetch();
            $output .= $create_table['Create Table'] . ";\n\n";
            
            // Export table data
            $rows = $pdo->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($rows) > 0) {
                $output .= "-- Dumping data for table `$table`\n";
                $columns = array_keys($rows[0]);
                
                foreach ($rows as $row) {
                    $values = [];
                    foreach ($row as $value) {
                        if ($value === null) {
                            $values[] = 'NULL';
                        } else {
                            $values[] = $pdo->quote($value);
                        }
                    }
                    $output .= "INSERT INTO `$table` (`" . implode('`, `', $columns) . "`) VALUES (" . implode(', ', $values) . ");\n";
                }
                $output .= "\n";
            }
        }
        
        $output .= "SET FOREIGN_KEY_CHECKS = 1;\n";
        
        // Log the export
        log_audit(current_user()['id'], 'export', 'database', null, "Database exported: $filename");
        
        // Send file for download
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($output));
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        
        echo $output;
        exit;
        
    } catch (Exception $e) {
        header('Location: ' . BASE_URL . '/settings/index.php?error=' . urlencode('Failed to export database: ' . $e->getMessage()));
        exit;
    }
}

// Handle database import
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'import_database') {
    require_role(['superadmin']);
    
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token.';
    } else {
        try {
            if (!isset($_FILES['database_file']) || $_FILES['database_file']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('Please select a valid SQL file to import.');
            }
            
            $file = $_FILES['database_file'];
            
            // Validate file type
            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if ($extension !== 'sql') {
                throw new Exception('Invalid file type. Please upload a .sql file.');
            }
            
            // Check file size (max 50MB)
            if ($file['size'] > 50 * 1024 * 1024) {
                throw new Exception('File size exceeds 50MB limit.');
            }
            
            // Read SQL file
            $sql_content = file_get_contents($file['tmp_name']);
            
            if (empty($sql_content)) {
                throw new Exception('SQL file is empty or could not be read.');
            }
            
            $pdo = getPDO();
            $pdo->beginTransaction();
            
            // Disable foreign key checks
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
            $pdo->exec("SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO'");
            
            // Split SQL file into individual statements
            // Remove comments and split by semicolon
            $sql_content = preg_replace('/--.*$/m', '', $sql_content);
            $sql_content = preg_replace('/\/\*.*?\*\//s', '', $sql_content);
            
            // Split by semicolon but keep it in the statement
            $statements = array_filter(array_map('trim', explode(';', $sql_content)));
            
            $executed = 0;
            $errors = [];
            
            foreach ($statements as $statement) {
                $statement = trim($statement);
                if (empty($statement)) {
                    continue;
                }
                
                try {
                    $pdo->exec($statement);
                    $executed++;
                } catch (Exception $e) {
                    // Log error but continue
                    $errors[] = $e->getMessage();
                    error_log("SQL import error: " . $e->getMessage() . " | Statement: " . substr($statement, 0, 100));
                }
            }
            
            // Re-enable foreign key checks
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
            
            $pdo->commit();
            
            // Log the import
            log_audit(current_user()['id'], 'import', 'database', null, "Database imported from: " . $file['name'] . " | Statements executed: $executed" . (!empty($errors) ? " | Errors: " . count($errors) : ""));
            
            if (!empty($errors)) {
                $success = "Database imported successfully. $executed statements executed. " . count($errors) . " errors occurred (check logs for details).";
            } else {
                $success = "Database imported successfully. $executed statements executed.";
            }
            
            header('Location: ' . BASE_URL . '/settings/index.php?success=' . urlencode($success));
            exit;
            
        } catch (Exception $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = 'Failed to import database: ' . $e->getMessage();
            error_log("Database import error: " . $e->getMessage());
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (!isset($_POST['action']) || $_POST['action'] !== 'import_database')) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token.';
    } else {
        $settings = [
            'ticket_penalty_percent' => floatval($_POST['ticket_penalty_percent'] ?? 5),
            'group_miss_percent' => floatval($_POST['group_miss_percent'] ?? 10),
            'group_partial_percent' => floatval($_POST['group_partial_percent'] ?? 5),
            'profit_fund_percent' => floatval($_POST['profit_fund_percent'] ?? 10),
            'working_days_per_month' => intval($_POST['working_days_per_month'] ?? 26),
            'missing_day_treated_as' => floatval($_POST['missing_day_treated_as'] ?? 0),
            'attendance_penalty_enabled' => isset($_POST['attendance_penalty_enabled']) ? '1' : '0',
        ];
        
        try {
            $pdo->beginTransaction();
            foreach ($settings as $key => $value) {
                update_setting($key, $value);
            }
            $pdo->commit();
            
            log_audit(current_user()['id'], 'update', 'settings', null, 'Updated system settings');
            
            $success = 'Settings updated successfully.';
            header('Location: ' . BASE_URL . '/settings/index.php?success=' . urlencode($success));
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Failed to update settings.';
        }
    }
}

// Get current settings
$current_settings = [
    'ticket_penalty_percent' => get_setting('ticket_penalty_percent', 5),
    'group_miss_percent' => get_setting('group_miss_percent', 10),
    'group_partial_percent' => get_setting('group_partial_percent', 5),
    'profit_fund_percent' => get_setting('profit_fund_percent', 10),
    'working_days_per_month' => get_setting('working_days_per_month', 26),
    'missing_day_treated_as' => get_setting('missing_day_treated_as', 0),
    'attendance_penalty_enabled' => get_setting('attendance_penalty_enabled', 0),
];

$page_title = 'System Settings';
include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="mb-4">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3">
            <div>
                <h1 class="gradient-text mb-2 fs-3 fs-md-2">System Settings</h1>
                <p class="text-muted mb-0 small">Configure system-wide settings and penalties</p>
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
    
    <form method="POST" action="" id="settingsForm">
        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
        
        <!-- Progress Penalties Section -->
        <div class="card shadow-lg border-0 mb-4 animate-slide-up">
            <div class="card-header bg-white border-bottom p-3 p-md-4">
                <h5 class="mb-0 fw-semibold">
                    <i class="bi bi-graph-down me-2 text-primary"></i>Progress Penalties
                </h5>
                <small class="text-muted d-block mt-1">Configure penalty percentages for progress tracking</small>
            </div>
            <div class="card-body p-3 p-md-4">
                <div class="row g-3 g-md-4">
                    <div class="col-12 col-md-6 col-lg-4">
                        <div class="form-group-card p-3 rounded-3" style="background: linear-gradient(135deg, rgba(220, 53, 69, 0.05), rgba(255, 193, 7, 0.05)); border: 1px solid rgba(220, 53, 69, 0.1);">
                            <label for="ticket_penalty_percent" class="form-label fw-semibold mb-2">
                                <i class="bi bi-ticket-perforated me-2 text-danger"></i>Ticket Penalty (%)
                                <span class="text-danger">*</span>
                            </label>
                            <input type="number" 
                                   step="0.01" 
                                   class="form-control form-control-lg shadow-sm" 
                                   id="ticket_penalty_percent" 
                                   name="ticket_penalty_percent" 
                                   value="<?= h($current_settings['ticket_penalty_percent']) ?>" 
                                   required
                                   placeholder="5.00">
                            <small class="text-muted d-block mt-2">
                                <i class="bi bi-info-circle me-1"></i>Percentage deducted per missed ticket
                            </small>
                        </div>
                    </div>
                    
                    <div class="col-12 col-md-6 col-lg-4">
                        <div class="form-group-card p-3 rounded-3" style="background: linear-gradient(135deg, rgba(220, 53, 69, 0.05), rgba(255, 193, 7, 0.05)); border: 1px solid rgba(220, 53, 69, 0.1);">
                            <label for="group_miss_percent" class="form-label fw-semibold mb-2">
                                <i class="bi bi-x-circle me-2 text-danger"></i>Group Miss Penalty (%)
                                <span class="text-danger">*</span>
                            </label>
                            <input type="number" 
                                   step="0.01" 
                                   class="form-control form-control-lg shadow-sm" 
                                   id="group_miss_percent" 
                                   name="group_miss_percent" 
                                   value="<?= h($current_settings['group_miss_percent']) ?>" 
                                   required
                                   placeholder="10.00">
                            <small class="text-muted d-block mt-2">
                                <i class="bi bi-info-circle me-1"></i>Percentage deducted per missed group
                            </small>
                        </div>
                    </div>
                    
                    <div class="col-12 col-md-6 col-lg-4">
                        <div class="form-group-card p-3 rounded-3" style="background: linear-gradient(135deg, rgba(255, 193, 7, 0.05), rgba(255, 152, 0, 0.05)); border: 1px solid rgba(255, 193, 7, 0.2);">
                            <label for="group_partial_percent" class="form-label fw-semibold mb-2">
                                <i class="bi bi-dash-circle me-2 text-warning"></i>Group Partial Penalty (%)
                                <span class="text-danger">*</span>
                            </label>
                            <input type="number" 
                                   step="0.01" 
                                   class="form-control form-control-lg shadow-sm" 
                                   id="group_partial_percent" 
                                   name="group_partial_percent" 
                                   value="<?= h($current_settings['group_partial_percent']) ?>" 
                                   required
                                   placeholder="5.00">
                            <small class="text-muted d-block mt-2">
                                <i class="bi bi-info-circle me-1"></i>Percentage deducted per partially completed group
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Payroll Settings Section -->
        <div class="card shadow-lg border-0 mb-4 animate-slide-up" style="animation-delay: 0.1s">
            <div class="card-header bg-white border-bottom p-3 p-md-4">
                <h5 class="mb-0 fw-semibold">
                    <i class="bi bi-cash-stack me-2 text-success"></i>Payroll Settings
                </h5>
                <small class="text-muted d-block mt-1">Configure payroll calculation and profit fund settings</small>
            </div>
            <div class="card-body p-3 p-md-4">
                <div class="row g-3 g-md-4">
                    <div class="col-12 col-md-6 col-lg-4">
                        <div class="form-group-card p-3 rounded-3" style="background: linear-gradient(135deg, rgba(40, 167, 69, 0.05), rgba(32, 201, 151, 0.05)); border: 1px solid rgba(40, 167, 69, 0.1);">
                            <label for="profit_fund_percent" class="form-label fw-semibold mb-2">
                                <i class="bi bi-piggy-bank me-2 text-success"></i>Profit Fund (%)
                                <span class="text-danger">*</span>
                            </label>
                            <input type="number" 
                                   step="0.01" 
                                   class="form-control form-control-lg shadow-sm" 
                                   id="profit_fund_percent" 
                                   name="profit_fund_percent" 
                                   value="<?= h($current_settings['profit_fund_percent']) ?>" 
                                   required
                                   placeholder="10.00">
                            <small class="text-muted d-block mt-2">
                                <i class="bi bi-info-circle me-1"></i>Percentage of gross salary allocated to profit fund
                            </small>
                        </div>
                    </div>
                    
                    <div class="col-12 col-md-6 col-lg-4">
                        <div class="form-group-card p-3 rounded-3" style="background: linear-gradient(135deg, rgba(0, 123, 255, 0.05), rgba(112, 111, 211, 0.05)); border: 1px solid rgba(0, 123, 255, 0.1);">
                            <label for="working_days_per_month" class="form-label fw-semibold mb-2">
                                <i class="bi bi-calendar-check me-2 text-primary"></i>Working Days Per Month
                                <span class="text-danger">*</span>
                            </label>
                            <input type="number" 
                                   class="form-control form-control-lg shadow-sm" 
                                   id="working_days_per_month" 
                                   name="working_days_per_month" 
                                   value="<?= h($current_settings['working_days_per_month']) ?>" 
                                   required
                                   placeholder="26">
                            <small class="text-muted d-block mt-2">
                                <i class="bi bi-info-circle me-1"></i>Number of working days in a month
                            </small>
                        </div>
                    </div>
                    
                    <div class="col-12 col-md-6 col-lg-4">
                        <div class="form-group-card p-3 rounded-3" style="background: linear-gradient(135deg, rgba(108, 117, 125, 0.05), rgba(73, 80, 87, 0.05)); border: 1px solid rgba(108, 117, 125, 0.1);">
                            <label for="missing_day_treated_as" class="form-label fw-semibold mb-2">
                                <i class="bi bi-calendar-x me-2 text-muted"></i>Missing Day Treated As (%)
                                <span class="text-danger">*</span>
                            </label>
                            <input type="number" 
                                   step="0.01" 
                                   class="form-control form-control-lg shadow-sm" 
                                   id="missing_day_treated_as" 
                                   name="missing_day_treated_as" 
                                   value="<?= h($current_settings['missing_day_treated_as']) ?>" 
                                   required
                                   placeholder="0.00">
                            <small class="text-muted d-block mt-2">
                                <i class="bi bi-info-circle me-1"></i>Progress percentage for days with no entry (default: 0)
                            </small>
                        </div>
                    </div>
                </div>
                
                <!-- Attendance Penalty Toggle -->
                <div class="mt-4 p-3 rounded-3" style="background: linear-gradient(135deg, rgba(0, 123, 255, 0.05), rgba(112, 111, 211, 0.05)); border: 1px solid rgba(0, 123, 255, 0.1);">
                    <div class="form-check form-switch d-flex align-items-center">
                        <input class="form-check-input me-3" 
                               type="checkbox" 
                               id="attendance_penalty_enabled" 
                               name="attendance_penalty_enabled" 
                               value="1" 
                               <?= $current_settings['attendance_penalty_enabled'] ? 'checked' : '' ?>
                               style="width: 3rem; height: 1.5rem; cursor: pointer;">
                        <label class="form-check-label fw-semibold flex-grow-1" for="attendance_penalty_enabled" style="cursor: pointer;">
                            <i class="bi bi-shield-check me-2 text-primary"></i>Enable Attendance Penalties
                            <small class="text-muted d-block mt-1" style="font-weight: normal;">
                                Apply penalties for missing attendance days
                            </small>
                        </label>
                    </div>
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
    
    <!-- Database Export Section -->
    <div class="card shadow-lg border-0 mb-4 animate-slide-up" style="animation-delay: 0.5s; border-left: 4px solid #17a2b8 !important;">
        <div class="card-header bg-white border-bottom p-3 p-md-4" style="background: linear-gradient(135deg, rgba(23, 162, 184, 0.05), rgba(19, 132, 150, 0.05));">
            <h5 class="mb-0 fw-semibold text-info">
                <i class="bi bi-download me-2"></i>Export Database
            </h5>
            <small class="text-muted d-block mt-1">Download a complete backup of your database</small>
        </div>
        <div class="card-body p-3 p-md-4">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                <div class="flex-grow-1">
                    <h6 class="fw-semibold mb-2">
                        <i class="bi bi-database me-2 text-info"></i>Export Current Database
                    </h6>
                    <p class="text-muted small mb-2">
                        Download a complete SQL backup of all database tables and data. This file can be used to restore your database later.
                    </p>
                    <div class="alert alert-info small mb-0" style="background: rgba(23, 162, 184, 0.1); border: 1px solid rgba(23, 162, 184, 0.3);">
                        <i class="bi bi-info-circle me-1"></i>
                        <strong>Note:</strong> The exported file will contain all tables, data, and structure. Keep this file secure as it contains sensitive information.
                    </div>
                </div>
                <div class="flex-shrink-0">
                    <a href="<?= BASE_URL ?>/settings/index.php?action=export_database&csrf_token=<?= urlencode(generate_csrf_token()) ?>" 
                       class="btn btn-info btn-lg shadow-sm">
                        <i class="bi bi-download me-2"></i>Export Database
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Database Import Section -->
    <div class="card shadow-lg border-0 mb-4 animate-slide-up" style="animation-delay: 0.6s; border-left: 4px solid #ffc107 !important;">
        <div class="card-header bg-white border-bottom p-3 p-md-4" style="background: linear-gradient(135deg, rgba(255, 193, 7, 0.05), rgba(255, 152, 0, 0.05));">
            <h5 class="mb-0 fw-semibold text-warning">
                <i class="bi bi-upload me-2"></i>Import Database
            </h5>
            <small class="text-muted d-block mt-1">Restore database from a SQL backup file</small>
        </div>
        <div class="card-body p-3 p-md-4">
            <form method="POST" action="" enctype="multipart/form-data" id="importDatabaseForm">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                <input type="hidden" name="action" value="import_database">
                
                <div class="d-flex align-items-start justify-content-between flex-wrap gap-3">
                    <div class="flex-grow-1">
                        <h6 class="fw-semibold mb-2">
                            <i class="bi bi-upload me-2 text-warning"></i>Import Database from SQL File
                        </h6>
                        <p class="text-muted small mb-2">
                            Upload a previously exported SQL file to restore your database. This will replace all current database data with the imported data.
                        </p>
                        <div class="alert alert-warning small mb-3" style="background: rgba(255, 193, 7, 0.1); border: 1px solid rgba(255, 193, 7, 0.3);">
                            <i class="bi bi-exclamation-triangle me-1"></i>
                            <strong>Warning:</strong> This will replace all existing database data. Make sure you have a current backup before proceeding.
                        </div>
                        <div class="mb-3">
                            <label for="database_file" class="form-label fw-semibold">
                                <i class="bi bi-file-earmark-code me-2"></i>Select SQL File
                                <span class="text-danger">*</span>
                            </label>
                            <input type="file" 
                                   class="form-control form-control-lg" 
                                   id="database_file" 
                                   name="database_file" 
                                   accept=".sql"
                                   required>
                            <small class="text-muted d-block mt-2">
                                <i class="bi bi-info-circle me-1"></i>Maximum file size: 50MB. Only .sql files are allowed.
                            </small>
                        </div>
                    </div>
                    <div class="flex-shrink-0 d-flex align-items-end">
                        <button type="button" 
                                class="btn btn-warning btn-lg shadow-sm" 
                                onclick="confirmImport()">
                            <i class="bi bi-upload me-2"></i>Import Database
                        </button>
                    </div>
                </div>
            </form>
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

.form-group-card input:focus {
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
document.getElementById('settingsForm').addEventListener('submit', function(e) {
    const submitBtn = this.querySelector('button[type="submit"]');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Saving...';
});

async function confirmImport() {
    const fileInput = document.getElementById('database_file');
    const file = fileInput.files[0];
    
    if (!file) {
        alert('Please select a SQL file to import.');
        return;
    }
    
    // Validate file extension
    const fileName = file.name.toLowerCase();
    if (!fileName.endsWith('.sql')) {
        alert('Invalid file type. Please select a .sql file.');
        return;
    }
    
    // Validate file size (50MB)
    if (file.size > 50 * 1024 * 1024) {
        alert('File size exceeds 50MB limit. Please select a smaller file.');
        return;
    }
    
    const message = 'Are you absolutely sure you want to import this database?\n\n' +
                   'This will:\n' +
                   '• REPLACE all current database data\n' +
                   '• Execute all SQL statements from the file\n' +
                   '• Cannot be undone without a backup\n\n' +
                   'Make sure you have exported a backup of your current database before proceeding.\n\n' +
                   'Click OK to proceed with import.';
    
    // Use Notify system if available, otherwise use browser confirm
    if (typeof window.Notify !== 'undefined') {
        try {
            const confirmed = await window.Notify.confirm(
                'This will replace all current database data. Make sure you have a backup!',
                'Import Database - Warning',
                'I understand, Import Now',
                'Cancel',
                'warning'
            );
            
            if (confirmed) {
                // Double confirmation with prompt
                const confirmationText = prompt('Please type "IMPORT" (all caps) to confirm:\n\n' + message);
                if (confirmationText === 'IMPORT') {
                    const form = document.getElementById('importDatabaseForm');
                    const submitBtn = form.querySelector('button[type="button"]');
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Importing...';
                    form.submit();
                } else {
                    alert('Import cancelled. You must type "IMPORT" exactly to confirm.');
                }
            }
        } catch (error) {
            console.error('Error in confirmImport:', error);
            // Fallback to browser confirm
            if (confirm(message + '\n\nClick OK to proceed with import.')) {
                const confirmationText = prompt('Please type "IMPORT" (all caps) to confirm:');
                if (confirmationText === 'IMPORT') {
                    document.getElementById('importDatabaseForm').submit();
                } else {
                    alert('Import cancelled. You must type "IMPORT" exactly to confirm.');
                }
            }
        }
    } else {
        // Fallback to browser confirm and prompt
        if (confirm(message + '\n\nClick OK to proceed with import.')) {
            const confirmationText = prompt('Please type "IMPORT" (all caps) to confirm:');
            if (confirmationText === 'IMPORT') {
                const form = document.getElementById('importDatabaseForm');
                const submitBtn = form.querySelector('button[type="button"]');
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Importing...';
                form.submit();
            } else {
                alert('Import cancelled. You must type "IMPORT" exactly to confirm.');
            }
        }
    }
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>

