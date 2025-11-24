<?php
/**
 * core/Helpers.php
 * Enhanced helper functions
 */

if (!function_exists('h')) {
    function h($string) {
        return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('format_date')) {
    function format_date($date, $format = 'Y-m-d') {
        return date($format, strtotime($date));
    }
}

if (!function_exists('format_datetime')) {
    function format_datetime($datetime, $format = 'Y-m-d H:i:s') {
        return date($format, strtotime($datetime));
    }
}

if (!function_exists('format_currency')) {
    function format_currency($amount, $currency = 'BDT') {
        $symbols = [
            'BDT' => '৳',
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'INR' => '₹'
        ];
        $symbol = $symbols[$currency] ?? $currency;
        return $symbol . number_format($amount, 2);
    }
}

if (!function_exists('ensure_directory')) {
    function ensure_directory($dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }
}

if (!function_exists('get_setting')) {
    function get_setting($key, $default = null) {
        $db = Database::getInstance();
        $result = $db->fetch("SELECT value FROM settings WHERE `key` = ?", [$key]);
        return $result ? $result['value'] : $default;
    }
}

if (!function_exists('update_setting')) {
    function update_setting($key, $value) {
        $db = Database::getInstance();
        $db->query(
            "INSERT INTO settings (`key`, value, updated_at) VALUES (?, ?, NOW()) ON DUPLICATE KEY UPDATE value = ?, updated_at = NOW()",
            [$key, $value, $value]
        );
    }
}

if (!function_exists('generateQRCode')) {
    function generateQRCode($data, $size = 200) {
        // Simple QR code generation using API (for shared hosting compatibility)
        $url = "https://api.qrserver.com/v1/create-qr-code/?size={$size}x{$size}&data=" . urlencode($data);
        return $url;
    }
}

if (!function_exists('slugify')) {
    function slugify($text) {
        $text = preg_replace('~[^\pL\d]+~u', '-', $text);
        $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
        $text = preg_replace('~[^-\w]+~', '', $text);
        $text = trim($text, '-');
        $text = preg_replace('~-+~', '-', $text);
        $text = strtolower($text);
        return $text;
    }
}
