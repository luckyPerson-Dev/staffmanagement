<?php
/**
 * Custom Error Page
 * Handles 404, 500, and other errors with beautiful animations
 */

// Get error code from query or default to 404
$error_code = isset($_GET['code']) ? intval($_GET['code']) : 404;
$error_message = $_GET['message'] ?? '';

// Set appropriate error messages
$error_messages = [
    400 => ['title' => 'Bad Request', 'description' => 'The request you made is invalid.'],
    401 => ['title' => 'Unauthorized', 'description' => 'You are not authorized to access this page.'],
    403 => ['title' => 'Forbidden', 'description' => 'You do not have permission to access this resource.'],
    404 => ['title' => 'Page Not Found', 'description' => 'The page you are looking for does not exist or has been moved.'],
    500 => ['title' => 'Internal Server Error', 'description' => 'Something went wrong on our end. We are working to fix it.'],
    503 => ['title' => 'Service Unavailable', 'description' => 'The service is temporarily unavailable. Please try again later.'],
];

$error_info = $error_messages[$error_code] ?? $error_messages[404];
$page_title = $error_info['title'] . ' - ' . ($error_message ?: $error_info['description']);

// Set HTTP status code
http_response_code($error_code);

// Get base URL
require_once __DIR__ . '/config.php';

// Ensure BASE_URL is defined
if (!defined('BASE_URL')) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $script_dir = dirname($_SERVER['SCRIPT_NAME'] ?? '');
    $base_path = rtrim($script_dir, '/');
    define('BASE_URL', $protocol . $host . $base_path);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            overflow: hidden;
            position: relative;
        }

        /* Animated Background */
        .error-background {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            overflow: hidden;
            z-index: 0;
        }

        .error-background::before,
        .error-background::after {
            content: '';
            position: absolute;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.1);
            animation: float 20s infinite ease-in-out;
        }

        .error-background::before {
            width: 500px;
            height: 500px;
            top: -250px;
            left: -250px;
            animation-delay: 0s;
        }

        .error-background::after {
            width: 400px;
            height: 400px;
            bottom: -200px;
            right: -200px;
            animation-delay: 10s;
        }

        @keyframes float {
            0%, 100% {
                transform: translate(0, 0) scale(1);
                opacity: 0.3;
            }
            50% {
                transform: translate(50px, 50px) scale(1.1);
                opacity: 0.5;
            }
        }

        /* Error Container */
        .error-container {
            position: relative;
            z-index: 1;
            text-align: center;
            color: white;
            max-width: 600px;
            width: 100%;
            animation: fadeInUp 0.8s ease-out;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Error Code */
        .error-code {
            font-size: 8rem;
            font-weight: 700;
            line-height: 1;
            margin-bottom: 20px;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.9), rgba(255, 255, 255, 0.6));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            animation: pulse 2s infinite ease-in-out;
            text-shadow: 0 0 40px rgba(255, 255, 255, 0.3);
        }

        @keyframes pulse {
            0%, 100% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.05);
            }
        }

        /* Error Icon */
        .error-icon {
            font-size: 6rem;
            margin-bottom: 30px;
            animation: bounce 2s infinite ease-in-out;
            opacity: 0.9;
        }

        @keyframes bounce {
            0%, 100% {
                transform: translateY(0);
            }
            50% {
                transform: translateY(-20px);
            }
        }

        /* Error Title */
        .error-title {
            font-size: 2.5rem;
            font-weight: 600;
            margin-bottom: 16px;
            font-family: 'Poppins', sans-serif;
        }

        /* Error Description */
        .error-description {
            font-size: 1.125rem;
            opacity: 0.9;
            margin-bottom: 40px;
            line-height: 1.6;
        }

        /* Error Card */
        .error-card {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            animation: slideIn 0.6s ease-out 0.2s both;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Action Buttons */
        .error-actions {
            display: flex;
            gap: 16px;
            justify-content: center;
            flex-wrap: wrap;
            margin-top: 30px;
        }

        .error-btn {
            padding: 12px 32px;
            border-radius: 50px;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }

        .error-btn-primary {
            background: white;
            color: #667eea;
        }

        .error-btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
            color: #667eea;
        }

        .error-btn-secondary {
            background: transparent;
            color: white;
            border-color: rgba(255, 255, 255, 0.5);
        }

        .error-btn-secondary:hover {
            background: rgba(255, 255, 255, 0.2);
            border-color: white;
            color: white;
            transform: translateY(-2px);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .error-code {
                font-size: 5rem;
            }

            .error-icon {
                font-size: 4rem;
            }

            .error-title {
                font-size: 1.75rem;
            }

            .error-description {
                font-size: 1rem;
            }

            .error-card {
                padding: 30px 20px;
            }

            .error-actions {
                flex-direction: column;
            }

            .error-btn {
                width: 100%;
                justify-content: center;
            }
        }

        /* Particles Animation */
        .particles {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            overflow: hidden;
            z-index: 0;
        }

        .particle {
            position: absolute;
            background: rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            animation: particleFloat 15s infinite ease-in-out;
        }

        @keyframes particleFloat {
            0%, 100% {
                transform: translate(0, 0) rotate(0deg);
                opacity: 0;
            }
            10% {
                opacity: 1;
            }
            90% {
                opacity: 1;
            }
        }

        .particle:nth-child(1) { width: 10px; height: 10px; left: 10%; animation-delay: 0s; }
        .particle:nth-child(2) { width: 15px; height: 15px; left: 20%; animation-delay: 2s; }
        .particle:nth-child(3) { width: 8px; height: 8px; left: 30%; animation-delay: 4s; }
        .particle:nth-child(4) { width: 12px; height: 12px; left: 40%; animation-delay: 6s; }
        .particle:nth-child(5) { width: 9px; height: 9px; left: 50%; animation-delay: 8s; }
        .particle:nth-child(6) { width: 14px; height: 14px; left: 60%; animation-delay: 10s; }
        .particle:nth-child(7) { width: 11px; height: 11px; left: 70%; animation-delay: 12s; }
        .particle:nth-child(8) { width: 13px; height: 13px; left: 80%; animation-delay: 14s; }
    </style>
</head>
<body>
    <div class="error-background"></div>
    
    <div class="particles">
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
    </div>

    <div class="error-container">
        <div class="error-card">
            <div class="error-code"><?= $error_code ?></div>
            
            <?php
            $icons = [
                400 => 'bi-x-octagon',
                401 => 'bi-shield-exclamation',
                403 => 'bi-shield-lock',
                404 => 'bi-search',
                500 => 'bi-exclamation-triangle',
                503 => 'bi-cloud-slash'
            ];
            $icon = $icons[$error_code] ?? 'bi-exclamation-circle';
            ?>
            <div class="error-icon">
                <i class="bi <?= $icon ?>"></i>
            </div>
            
            <h1 class="error-title"><?= htmlspecialchars($error_info['title']) ?></h1>
            <p class="error-description">
                <?= htmlspecialchars($error_message ?: $error_info['description']) ?>
            </p>
            
            <div class="error-actions">
                <a href="<?= BASE_URL ?>/dashboard.php" class="error-btn error-btn-primary">
                    <i class="bi bi-house-door"></i>
                    Go to Dashboard
                </a>
                <a href="javascript:history.back()" class="error-btn error-btn-secondary">
                    <i class="bi bi-arrow-left"></i>
                    Go Back
                </a>
            </div>
        </div>
    </div>

    <script>
        // Add random movement to particles
        document.querySelectorAll('.particle').forEach((particle, index) => {
            const randomX = Math.random() * 100;
            const randomY = Math.random() * 100;
            const randomDuration = 10 + Math.random() * 10;
            const randomDelay = Math.random() * 5;
            
            particle.style.left = randomX + '%';
            particle.style.top = randomY + '%';
            particle.style.animationDuration = randomDuration + 's';
            particle.style.animationDelay = randomDelay + 's';
        });
    </script>
</body>
</html>

