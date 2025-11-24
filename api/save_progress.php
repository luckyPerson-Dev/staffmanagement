<?php
/**
 * api/save_progress.php
 * Save daily progress entry (AJAX endpoint)
 * 
 * NEW LOGIC:
 * - Ticket progress = per_day_percent - (ticket_miss * 1%)
 * - Group progress = per_day_percent * ratio (based on group status)
 * - Final = (ticket_progress + group_progress) / 2
 * - Missed day = 0
 * - Overtime = adds full per_day_percent extra
 */

require_once __DIR__ . '/../auth_helper.php';
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../core/compute_helpers.php';

require_role(['admin', 'superadmin']);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    response_json(['success' => false, 'message' => 'Invalid request method'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);

// Verify CSRF token
if (!verify_csrf_token($input['csrf_token'] ?? '')) {
    response_json(['success' => false, 'message' => 'Invalid CSRF token'], 403);
}

$user_id = intval($input['user_id'] ?? 0);
$date = $input['date'] ?? '';
$tickets_missed = intval($input['tickets_missed'] ?? 0);
$customer_id = !empty($input['customer_id']) ? intval($input['customer_id']) : null;
$notes = trim($input['notes'] ?? '');
$groups_status = $input['groups_status'] ?? [];
$is_missed = isset($input['is_missed']) && $input['is_missed'] ? 1 : 0;
$is_overtime = isset($input['is_overtime']) && $input['is_overtime'] ? 1 : 0;

// Validation
if (!$user_id || !$date) {
    response_json(['success' => false, 'message' => 'User ID and date are required'], 400);
}

// Validate date format
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    response_json(['success' => false, 'message' => 'Invalid date format'], 400);
}

$pdo = getPDO();

// Calculate per_day_percent based on date
$date_parts = explode('-', $date);
if (count($date_parts) !== 3) {
    response_json(['success' => false, 'message' => 'Invalid date format'], 400);
}

$year = intval($date_parts[0]);
$month = intval($date_parts[1]);
$days_in_month = cal_days_in_month(CAL_GREGORIAN, $month, $year);
$per_day_percent = 100 / $days_in_month;

// Initialize variables
$ticket_progress = 0;
$group_progress = 0;
$merged = 0;
$final_progress = 0;

// 1) Auto compute per-day percent (already done above)

// 2) If missed day is checked: Set all to 0, skip further logic, save and exit
if ($is_missed) {
    $final_progress = 0;
    $ticket_progress = 0;
    $group_progress = 0;
    $merged = 0;
} else {
    // 3) If not missed: Compute ticket and group progress normally
    
    // Calculate TICKET PROGRESS
    // ticket_progress = per_day_percent - (ticket_miss * 1%)
    $ticket_penalty_per_miss = 1.0; // Fixed at 1% per ticket miss
    $ticket_progress = $per_day_percent - ($tickets_missed * $ticket_penalty_per_miss);
    $ticket_progress = max(0, $ticket_progress); // Clamp to minimum 0
    
    // Calculate GROUP PROGRESS
    // Get group ratios from settings (convert percentages to ratios)
    $group_partial_ratio = floatval(get_setting('group_partial_ratio', 0.5)); // Default 0.5 (50%)
    $group_miss_ratio = floatval(get_setting('group_miss_ratio', 0.0)); // Default 0.0 (0%)
    
    // If ratios are stored as percentages (> 1), convert to ratio
    if ($group_partial_ratio > 1) {
        $group_partial_ratio = $group_partial_ratio / 100;
    }
    if ($group_miss_ratio > 1) {
        $group_miss_ratio = $group_miss_ratio / 100;
    }
    
    // Check for customer-specific ratios
    if ($customer_id) {
        $stmt = $pdo->prepare("
            SELECT group_partial_ratio, group_miss_ratio 
            FROM customers 
            WHERE id = ? AND deleted_at IS NULL
        ");
        $stmt->execute([$customer_id]);
        $customer = $stmt->fetch();
        
        if ($customer) {
            if ($customer['group_partial_ratio'] !== null) {
                $ratio = floatval($customer['group_partial_ratio']);
                $group_partial_ratio = ($ratio > 1) ? $ratio / 100 : $ratio;
            }
            if ($customer['group_miss_ratio'] !== null) {
                $ratio = floatval($customer['group_miss_ratio']);
                $group_miss_ratio = ($ratio > 1) ? $ratio / 100 : $ratio;
            }
        }
    }
    
    // Calculate group progress based on group statuses
    $group_progress = 0.0;
    $has_groups = false;
    
    if (!empty($groups_status)) {
        $total_groups = count($groups_status);
        $ok_groups = 0;
        $partial_groups = 0;
        $missed_groups = 0;
        
        foreach ($groups_status as $group) {
            if (!isset($group['status'])) continue;
            $has_groups = true;
            
            if ($group['status'] === 'completed' || $group['status'] === 'ok') {
                $ok_groups++;
            } elseif ($group['status'] === 'partial') {
                $partial_groups++;
            } elseif ($group['status'] === 'missed' || $group['status'] === 'miss') {
                $missed_groups++;
            }
        }
        
        if ($has_groups && $total_groups > 0) {
            // Calculate average group progress
            $ok_contribution = ($ok_groups / $total_groups) * $per_day_percent;
            $partial_contribution = ($partial_groups / $total_groups) * $per_day_percent * $group_partial_ratio;
            $miss_contribution = ($missed_groups / $total_groups) * $per_day_percent * $group_miss_ratio;
            
            $group_progress = $ok_contribution + $partial_contribution + $miss_contribution;
        }
    }
    
    // If no groups, group_progress = per_day_percent (full credit)
    if (!$has_groups) {
        $group_progress = $per_day_percent;
    }
    
    // Calculate merged progress
    $merged = ($ticket_progress + $group_progress) / 2;
    
    // 4) If overtime: final = merged + per_day_percent (double effect)
    //    else: final = merged
    if ($is_overtime) {
        $final_progress = $merged + $per_day_percent; // double effect
    } else {
        $final_progress = $merged;
    }
    
    // Clamp output
    // If $final_progress < 0 → set to 0
    if ($final_progress < 0) {
        $final_progress = 0;
    }
    
    // If $final_progress > (per_day_percent * 2) → allow but log
    $max_allowed = $per_day_percent * 2;
    if ($final_progress > $max_allowed) {
        error_log("Progress exceeds maximum allowed: user_id=$user_id, date=$date, progress=$final_progress, max=$max_allowed");
    }
    
    // Round to 2 decimal places
    $final_progress = round($final_progress, 2);
}

// Use final_progress for saving
$progress = $final_progress;

// Prepare groups_status JSON
$groups_status_json = !empty($groups_status) ? json_encode($groups_status) : null;

try {
    $pdo->beginTransaction();
    
    $entry_id = intval($input['id'] ?? 0);
    
    if ($entry_id) {
        // Update existing entry
        $stmt = $pdo->prepare("
            UPDATE daily_progress 
            SET user_id = ?, date = ?, tickets_missed = ?, groups_status = ?, 
                customer_id = ?, notes = ?, progress_percent = ?, 
                is_missed = ?, is_overtime = ?, updated_at = UTC_TIMESTAMP()
            WHERE id = ?
        ");
        $stmt->execute([
            $user_id, $date, $tickets_missed, $groups_status_json, 
            $customer_id, $notes, $progress, $is_missed, $is_overtime, $entry_id
        ]);
        
        log_audit(current_user()['id'], 'update', 'daily_progress', $entry_id, "Updated progress for user $user_id on $date");
    } else {
        // Insert new entry
        $stmt = $pdo->prepare("
            INSERT INTO daily_progress 
            (user_id, date, tickets_missed, groups_status, customer_id, notes, progress_percent, is_missed, is_overtime, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())
        ");
        $stmt->execute([
            $user_id, $date, $tickets_missed, $groups_status_json, 
            $customer_id, $notes, $progress, $is_missed, $is_overtime
        ]);
        $entry_id = $pdo->lastInsertId();
        
        log_audit(current_user()['id'], 'create', 'daily_progress', $entry_id, "Created progress for user $user_id on $date");
    }
    
    $pdo->commit();
    
    response_json([
        'success' => true,
        'message' => 'Progress saved successfully',
        'id' => $entry_id,
        'progress_percent' => $progress,
        'ticket_progress' => isset($ticket_progress) ? round($ticket_progress, 2) : 0,
        'group_progress' => isset($group_progress) ? round($group_progress, 2) : 0,
        'per_day_percent' => round($per_day_percent, 4)
    ]);
    
} catch (PDOException $e) {
    $pdo->rollBack();
    
    // Check for duplicate entry error
    if ($e->getCode() == 23000) {
        response_json(['success' => false, 'message' => 'Progress entry already exists for this user and date'], 409);
    } else {
        error_log("Progress save error: " . $e->getMessage());
        response_json(['success' => false, 'message' => 'Failed to save progress: ' . $e->getMessage()], 500);
    }
}
