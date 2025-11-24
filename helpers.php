<?php
/**
 * helpers.php
 * General helper functions
 */

require_once __DIR__ . '/db_connect.php';

/**
 * Send JSON response
 * @param mixed $data
 * @param int $status_code
 */
function response_json($data, $status_code = 200) {
    http_response_code($status_code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/**
 * Log audit trail
 * @param int $user_id
 * @param string $action
 * @param string $resource
 * @param int|null $resource_id
 * @param string|null $details
 */
function log_audit($user_id, $action, $resource, $resource_id = null, $details = null) {
    $pdo = getPDO();
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    
    $stmt = $pdo->prepare("
        INSERT INTO audit_logs (user_id, action, resource, resource_id, details, ip_address, created_at)
        VALUES (?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())
    ");
    $stmt->execute([$user_id, $action, $resource, $resource_id, $details, $ip]);
}

/**
 * Sanitize output for HTML
 * @param string $string
 * @return string
 */
if (!function_exists('h')) {
    function h($string) {
        return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
    }
}

/**
 * Format date for display
 * @param string $date
 * @return string
 */
if (!function_exists('format_date')) {
    function format_date($date) {
        return date('Y-m-d', strtotime($date));
    }
}

/**
 * Format datetime for display
 * @param string $datetime
 * @return string
 */
if (!function_exists('format_datetime')) {
    function format_datetime($datetime) {
        return date('Y-m-d H:i:s', strtotime($datetime));
    }
}

/**
 * Ensure directory exists
 * @param string $dir
 */
if (!function_exists('ensure_directory')) {
    function ensure_directory($dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        // Ensure directory is writable
        if (is_dir($dir) && !is_writable($dir)) {
            @chmod($dir, 0775);
        }
    }
}

/**
 * Get setting value
 * @param string $key
 * @param mixed $default
 * @param int|null $month Optional month for auto calculation
 * @param int|null $year Optional year for auto calculation
 * @return mixed
 */
if (!function_exists('get_setting')) {
    function get_setting($key, $default = null, $month = null, $year = null) {
        $pdo = getPDO();
        $stmt = $pdo->prepare("SELECT value FROM settings WHERE `key` = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetch();
        $value = $result ? $result['value'] : $default;
        
        // Special handling for daily_penalty_base
        if ($key === 'daily_penalty_base' && $value === 'auto') {
            // Ensure compute_helpers.php is loaded
            if (!function_exists('per_day_percent')) {
                $compute_helpers = __DIR__ . '/core/compute_helpers.php';
                if (file_exists($compute_helpers)) {
                    require_once $compute_helpers;
                }
            }
            
            if (function_exists('per_day_percent')) {
                if ($month !== null && $year !== null) {
                    return per_day_percent($month, $year);
                }
                // If month/year not provided, use current month/year
                return per_day_percent((int)date('n'), (int)date('Y'));
            }
        }
        
        return $value;
    }
}

/**
 * Update setting value
 * @param string $key
 * @param mixed $value
 */
if (!function_exists('update_setting')) {
    function update_setting($key, $value) {
        $pdo = getPDO();
        $stmt = $pdo->prepare("
            INSERT INTO settings (`key`, value, updated_at) 
            VALUES (?, ?, UTC_TIMESTAMP())
            ON DUPLICATE KEY UPDATE value = ?, updated_at = UTC_TIMESTAMP()
        ");
        $stmt->execute([$key, $value, $value]);
    }
}

/**
 * Get active advance auto-deduction for a user
 * @param int $user_id
 * @return array|null
 */
if (!function_exists('get_active_advance_deduction')) {
    function get_active_advance_deduction($user_id) {
        $pdo = getPDO();
        $stmt = $pdo->prepare("
            SELECT * FROM advance_auto_deductions
            WHERE user_id = ? AND status = 'active'
            ORDER BY created_at DESC
            LIMIT 1
        ");
        $stmt->execute([$user_id]);
        return $stmt->fetch();
    }
}

/**
 * Get total approved advances for a user
 * @param int $user_id
 * @return float
 */
if (!function_exists('get_total_approved_advances')) {
    function get_total_approved_advances($user_id) {
        $pdo = getPDO();
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(amount), 0) as total
            FROM advances
            WHERE user_id = ? AND status = 'approved'
        ");
        $stmt->execute([$user_id]);
        $result = $stmt->fetch();
        return floatval($result['total'] ?? 0);
    }
}

/**
 * Calculate remaining advance due for a user
 * @param int $user_id
 * @param int|null $exclude_month Exclude this month from calculation
 * @param int|null $exclude_year Exclude this year from calculation
 * @return float
 */
if (!function_exists('calculate_remaining_advance_due')) {
    function calculate_remaining_advance_due($user_id, $exclude_month = null, $exclude_year = null) {
        $pdo = getPDO();
        
        // Get total approved advances
        $total_approved = get_total_approved_advances($user_id);
        
        // Get total already deducted
        $where_clause = "user_id = ?";
        $params = [$user_id];
        
        if ($exclude_month !== null && $exclude_year !== null) {
            $where_clause .= " AND (month != ? OR year != ?)";
            $params[] = $exclude_month;
            $params[] = $exclude_year;
        }
        
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(advances_deducted), 0) as total_deducted
            FROM salary_history
            WHERE {$where_clause}
        ");
        $stmt->execute($params);
        $result = $stmt->fetch();
        $total_deducted = floatval($result['total_deducted'] ?? 0);
        
        return max(0, $total_approved - $total_deducted);
    }
}

