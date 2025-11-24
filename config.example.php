<?php
/**
 * config.example.php
 * Example configuration file - Copy this to config.php and update with your values
 * 
 * IMPORTANT: 
 * 1. Copy this file to config.php
 * 2. Update all database credentials
 * 3. Update BASE_URL to your domain
 */

// Database Configuration
// UPDATE THESE VALUES WITH YOUR HOSTING DATABASE CREDENTIALS
define('DB_HOST', 'localhost'); // Usually 'localhost' or '127.0.0.1'. Check your hosting cPanel for exact value.
define('DB_NAME', 'your_database_name'); // Your database name from cPanel (usually username_dbname)
define('DB_USER', 'your_database_user'); // Your database username from cPanel (usually username_dbuser)
define('DB_PASS', 'your_database_password'); // Your database password
define('DB_CHARSET', 'utf8mb4');

// Application Configuration
// UPDATE BASE_URL TO YOUR ACTUAL DOMAIN
define('BASE_URL', 'https://yourdomain.com/staff2'); // Change to your actual domain
define('SESSION_TIMEOUT', 1800); // 30 minutes in seconds
define('TIMEZONE', 'UTC'); // Change to your timezone if needed

// File Upload Directories
define('DOWNLOADS_DIR', __DIR__ . '/downloads');
define('STORAGE_DIR', __DIR__ . '/storage');
define('UPLOADS_DIR', __DIR__ . '/uploads');
define('EXPORTS_DIR', __DIR__ . '/exports');
define('LOGS_DIR', __DIR__ . '/logs');

// Security
define('CSRF_TOKEN_NAME', 'csrf_token');
define('LOG_LEVEL', 1);

// Enterprise Features
define('ENABLE_2FA', true);
define('ENABLE_ANALYTICS', true);
define('ENABLE_AI_FEATURES', true);
define('ENABLE_QR_ATTENDANCE', true);
define('DEFAULT_CURRENCY', 'BDT');
define('MAX_UPLOAD_SIZE', 10485760); // 10MB

// Email Configuration
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', '');
define('SMTP_PASS', '');
define('SMTP_FROM_EMAIL', 'noreply@yourdomain.com');
define('SMTP_FROM_NAME', 'Staff Management System');

// AI/ML Configuration
define('AI_API_KEY', '');
define('AI_ENABLED', false);

// Set timezone
date_default_timezone_set(TIMEZONE);

// Error reporting (disabled for production)
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/php_errors.log');

// Ensure directories exist
define('ROOT_PATH', __DIR__);

