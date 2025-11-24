<?php
/**
 * core/Response.php
 * Standardized API response helper
 */

class Response {
    public static function json($data, $statusCode = 200) {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data, JSON_PRETTY_PRINT);
        exit;
    }
    
    public static function success($message, $data = null, $statusCode = 200) {
        self::json([
            'success' => true,
            'message' => $message,
            'data' => $data
        ], $statusCode);
    }
    
    public static function error($message, $errors = null, $statusCode = 400) {
        self::json([
            'success' => false,
            'message' => $message,
            'errors' => $errors
        ], $statusCode);
    }
    
    public static function paginated($data, $pagination, $message = 'Success') {
        self::json([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'pagination' => $pagination->getInfo()
        ]);
    }
}

