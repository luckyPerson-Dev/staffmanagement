<?php
/**
 * api/v1/router.php
 * REST API Router for Enterprise Features
 */

require_once __DIR__ . '/../../core/autoload.php';

header('Content-Type: application/json');

// CORS headers (adjust for production)
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$auth = new Auth();
$auth->requireLogin();

$requestUri = $_SERVER['REQUEST_URI'];
$path = parse_url($requestUri, PHP_URL_PATH);
$pathParts = explode('/', trim($path, '/'));

// Remove 'api/v1' from path
$apiIndex = array_search('api', $pathParts);
if ($apiIndex !== false) {
    $pathParts = array_slice($pathParts, $apiIndex + 2);
}

$resource = $pathParts[0] ?? '';
$id = $pathParts[1] ?? null;
$action = $pathParts[2] ?? null;

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($resource) {
        case 'analytics':
            require __DIR__ . '/endpoints/analytics.php';
            break;
            
        case 'ai':
            require __DIR__ . '/endpoints/ai.php';
            break;
            
        case 'documents':
            require __DIR__ . '/endpoints/documents.php';
            break;
            
        case 'notifications':
            require __DIR__ . '/endpoints/notifications.php';
            break;
            
        case 'messages':
            require __DIR__ . '/endpoints/messages.php';
            break;
            
        case 'attendance':
            require __DIR__ . '/endpoints/attendance.php';
            break;
            
        default:
            Response::error('Resource not found', null, 404);
    }
} catch (Exception $e) {
    $logger = new Logger();
    $logger->error("API Error: " . $e->getMessage());
    Response::error($e->getMessage(), null, 500);
}

