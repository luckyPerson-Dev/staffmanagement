<?php
/**
 * login.php
 * User login page
 */

// Error handling
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Ensure logs directory exists
$logs_dir = __DIR__ . '/logs';
if (!is_dir($logs_dir)) {
    @mkdir($logs_dir, 0755, true);
}
ini_set('error_log', $logs_dir . '/php_errors.log');

require_once __DIR__ . '/config.php';

// Try to load database connection with error handling
try {
    require_once __DIR__ . '/db_connect.php';
} catch (Exception $e) {
    error_log("Database connection file error: " . $e->getMessage());
    die("Database configuration error. Please check config.php");
}

// Try to load auth helper
try {
    require_once __DIR__ . '/auth_helper.php';
} catch (Exception $e) {
    error_log("Auth helper error: " . $e->getMessage());
    die("Authentication system error. Please check configuration.");
}

// Try to load helpers
try {
    require_once __DIR__ . '/helpers.php';
} catch (Exception $e) {
    error_log("Helpers error: " . $e->getMessage());
    // Continue without helpers if they fail
}

// Redirect if already logged in
if (function_exists('is_logged_in') && is_logged_in()) {
    header('Location: ' . BASE_URL . '/dashboard.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        
        if (empty($email) || empty($password)) {
            $error = 'Email and password are required.';
        } else {
            try {
                // Check if getPDO function exists
                if (!function_exists('getPDO')) {
                    throw new Exception('Database connection function not available');
                }
                
                $pdo = getPDO();
                
                // Verify database connection
                if (!$pdo) {
                    throw new Exception('Database connection failed');
                }
                
                $stmt = $pdo->prepare("SELECT id, name, email, password, role, status FROM users WHERE email = ? AND deleted_at IS NULL");
                $stmt->execute([$email]);
                $user = $stmt->fetch();
                
                if ($user && password_verify($password, $user['password'])) {
                    // Check user status
                    if ($user['status'] === 'banned') {
                        $error = 'Your account has been banned. Please contact administrator.';
                    } elseif ($user['status'] === 'suspended') {
                        $error = 'Your account has been suspended. Please contact administrator.';
                    } else {
                        // Login successful
                        session_regenerate_id(true);
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['user_name'] = $user['name'];
                        $_SESSION['user_role'] = $user['role'];
                        $_SESSION['last_activity'] = time();
                        
                        // Log audit if function exists
                        if (function_exists('log_audit')) {
                            try {
                                log_audit($user['id'], 'login', 'user', $user['id'], 'User logged in');
                            } catch (Exception $e) {
                                // Log audit failed, but continue with login
                                error_log("Audit log failed: " . $e->getMessage());
                            }
                        }
                        
                        header('Location: ' . BASE_URL . '/dashboard.php');
                        exit;
                    }
                } else {
                    $error = 'Invalid email or password.';
                }
            } catch (PDOException $e) {
                error_log("Database error in login: " . $e->getMessage());
                $error = 'Database connection error: ' . $e->getMessage();
                // For debugging - remove in production
                if (defined('DB_HOST') && defined('DB_NAME')) {
                    $error .= ' (Host: ' . DB_HOST . ', DB: ' . DB_NAME . ')';
                }
            } catch (Exception $e) {
                error_log("Error in login: " . $e->getMessage());
                $error = 'An error occurred: ' . $e->getMessage();
            }
        }
    } catch (Exception $e) {
        error_log("Fatal error in login: " . $e->getMessage());
        $error = 'An unexpected error occurred. Please contact administrator.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Staff Management</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>/assets/css/design-system.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>/assets/css/login.css" rel="stylesheet">
</head>
<body class="login-page">
    <div class="login-container">
        <div class="login-card">
            <div class="login-logo">
                <h1>Staff Management</h1>
                <p>Professional Staff Management System</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger animate-fade-in">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    <?= h($error) ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success animate-fade-in">
                    <i class="bi bi-check-circle-fill me-2"></i>
                    <?= h($success) ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" class="login-form">
                <div class="mb-3">
                    <label for="email" class="form-label">Email Address</label>
                    <input type="email" class="form-control" id="email" name="email" required autofocus placeholder="Enter your email">
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" class="form-control" id="password" name="password" required placeholder="Enter your password">
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-box-arrow-in-right me-2"></i>
                    Sign In
                </button>
            </form>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
</body>
</html>

