<?php
/**
 * core/compute_helpers.php
 * Computation helper functions for profit fund, tickets, and dynamic per-day calculations
 */

/**
 * Get number of days in a month
 * @param int $month Month (1-12)
 * @param int $year Year (e.g., 2024)
 * @return int Number of days in the month
 */
function days_in_month(int $month, int $year): int {
    return cal_days_in_month(CAL_GREGORIAN, $month, $year);
}

/**
 * Calculate per-day percentage based on month length
 * @param int $month Month (1-12)
 * @param int $year Year (e.g., 2024)
 * @return float Per-day percentage (100 / days in month), rounded to 4 decimals
 */
function per_day_percent(int $month, int $year): float {
    $days = days_in_month($month, $year);
    return round(100 / $days, 4);
}

/**
 * Compute ticket percentage for a user in a given month/year
 * @param int $user_id User ID
 * @param int $month Month (1-12)
 * @param int $year Year (e.g., 2024)
 * @return float Ticket percentage (0-100), rounded to 2 decimals
 */
function compute_ticket_percent(int $user_id, int $month, int $year): float {
    $pdo = getPDO();
    
    // Get total tickets for the month
    $stmt = $pdo->prepare("SELECT total_tickets FROM monthly_tickets WHERE month = ? AND year = ?");
    $stmt->execute([$month, $year]);
    $monthly = $stmt->fetch();
    
    if (!$monthly || $monthly['total_tickets'] == 0) {
        return 0.0;
    }
    
    $total_tickets = (int)$monthly['total_tickets'];
    
    // Get staff ticket count
    $stmt = $pdo->prepare("SELECT ticket_count FROM staff_tickets WHERE user_id = ? AND month = ? AND year = ?");
    $stmt->execute([$user_id, $month, $year]);
    $staff = $stmt->fetch();
    
    $staff_ticket_count = $staff ? (int)$staff['ticket_count'] : 0;
    
    // Calculate percentage
    $percent = ($staff_ticket_count / $total_tickets) * 100;
    
    return round($percent, 2);
}

/**
 * Compute average ticket percentage for multiple users
 * @param array $user_ids Array of user IDs
 * @param int $month Month (1-12)
 * @param int $year Year (e.g., 2024)
 * @return float Average ticket percentage, rounded to 2 decimals
 */
function compute_team_ticket_avg(array $user_ids, int $month, int $year): float {
    if (empty($user_ids)) {
        return 0.0;
    }
    
    $sum = 0;
    $count = 0;
    
    foreach ($user_ids as $user_id) {
        $percent = compute_ticket_percent((int)$user_id, $month, $year);
        $sum += $percent;
        $count++;
    }
    
    if ($count == 0) {
        return 0.0;
    }
    
    return round($sum / $count, 2);
}

/**
 * Compute average monthly group progress for a team
 * @param int $team_id Team ID
 * @param int $month Month (1-12)
 * @param int $year Year (e.g., 2024)
 * @return float Average progress percentage, rounded to 2 decimals
 */
function compute_group_avg(int $team_id, int $month, int $year): float {
    $pdo = getPDO();
    
    // Get all users in the team
    $stmt = $pdo->prepare("
        SELECT tm.user_id 
        FROM team_members tm
        INNER JOIN users u ON tm.user_id = u.id
        WHERE tm.team_id = ? AND u.deleted_at IS NULL
    ");
    $stmt->execute([$team_id]);
    $members = $stmt->fetchAll();
    
    if (empty($members)) {
        return 0.0;
    }
    
    // Get date range for the month
    $days_in_month = days_in_month($month, $year);
    $start_date = sprintf('%04d-%02d-01', $year, $month);
    $end_date = sprintf('%04d-%02d-%02d', $year, $month, $days_in_month);
    
    $user_ids = array_column($members, 'user_id');
    $placeholders = implode(',', array_fill(0, count($user_ids), '?'));
    
    // Calculate average progress for all team members in the month
    $stmt = $pdo->prepare("
        SELECT AVG(progress_percent) as avg_progress
        FROM daily_progress
        WHERE user_id IN ($placeholders)
          AND date >= ?
          AND date <= ?
    ");
    
    $params = array_merge($user_ids, [$start_date, $end_date]);
    $stmt->execute($params);
    $result = $stmt->fetch();
    
    if (!$result || $result['avg_progress'] === null) {
        return 0.0;
    }
    
    return round((float)$result['avg_progress'], 2);
}

