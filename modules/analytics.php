<?php
/**
 * modules/analytics.php
 * Advanced Analytics Module
 */

class Analytics {
    private $db;
    private $logger;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->logger = new Logger();
    }
    
    /**
     * Get staff productivity heatmap data
     */
    public function getProductivityHeatmap($startDate, $endDate) {
        $data = $this->db->fetchAll("
            SELECT 
                u.id,
                u.name,
                dp.date,
                dp.progress_percent
            FROM daily_progress dp
            JOIN users u ON dp.user_id = u.id
            WHERE dp.date BETWEEN ? AND ?
            AND u.role = 'staff'
            AND u.deleted_at IS NULL
            ORDER BY u.name, dp.date
        ", [$startDate, $endDate]);
        
        // Format for heatmap
        $heatmap = [];
        foreach ($data as $row) {
            $heatmap[$row['id']][$row['date']] = (float)$row['progress_percent'];
        }
        
        return $heatmap;
    }
    
    /**
     * Get monthly progress trends
     */
    public function getMonthlyTrends($userId = null, $months = 12) {
        $where = $userId ? "AND dp.user_id = ?" : "";
        $params = $userId ? [$userId] : [];
        
        $data = $this->db->fetchAll("
            SELECT 
                YEAR(dp.date) as year,
                MONTH(dp.date) as month,
                AVG(dp.progress_percent) as avg_progress,
                COUNT(*) as days_count
            FROM daily_progress dp
            WHERE dp.date >= DATE_SUB(CURDATE(), INTERVAL ? MONTH)
            {$where}
            GROUP BY YEAR(dp.date), MONTH(dp.date)
            ORDER BY year DESC, month DESC
        ", array_merge([$months], $params));
        
        return $data;
    }
    
    /**
     * Get team comparison data
     */
    public function getTeamComparison($month, $year) {
        $data = $this->db->fetchAll("
            SELECT 
                t.id as team_id,
                t.name as team_name,
                AVG(dp.progress_percent) as avg_progress,
                COUNT(DISTINCT dp.user_id) as member_count
            FROM teams t
            JOIN team_members tm ON t.id = tm.team_id
            JOIN daily_progress dp ON tm.user_id = dp.user_id
            WHERE YEAR(dp.date) = ? AND MONTH(dp.date) = ?
            AND t.deleted_at IS NULL
            GROUP BY t.id, t.name
            ORDER BY avg_progress DESC
        ", [$year, $month]);
        
        return $data;
    }
    
    /**
     * Get ticket miss patterns
     */
    public function getTicketMissPatterns($userId = null) {
        $where = $userId ? "AND dp.user_id = ?" : "";
        $params = $userId ? [$userId] : [];
        
        $data = $this->db->fetchAll("
            SELECT 
                DAYNAME(dp.date) as day_name,
                DAYOFWEEK(dp.date) as day_num,
                AVG(dp.tickets_missed) as avg_missed,
                COUNT(*) as occurrences
            FROM daily_progress dp
            WHERE dp.tickets_missed > 0
            {$where}
            GROUP BY DAYNAME(dp.date), DAYOFWEEK(dp.date)
            ORDER BY day_num
        ", $params);
        
        return $data;
    }
    
    /**
     * Get salary trends
     */
    public function getSalaryTrends($userId = null, $months = 12) {
        $where = $userId ? "AND sh.user_id = ?" : "";
        $params = $userId ? [$userId] : [];
        
        $data = $this->db->fetchAll("
            SELECT 
                sh.year,
                sh.month,
                AVG(sh.net_payable) as avg_salary,
                SUM(sh.net_payable) as total_salary,
                COUNT(*) as staff_count
            FROM salary_history sh
            WHERE sh.created_at >= DATE_SUB(CURDATE(), INTERVAL ? MONTH)
            {$where}
            GROUP BY sh.year, sh.month
            ORDER BY sh.year DESC, sh.month DESC
        ", array_merge([$months], $params));
        
        return $data;
    }
    
    /**
     * Get customer workload analytics
     */
    public function getCustomerWorkload($startDate, $endDate) {
        $data = $this->db->fetchAll("
            SELECT 
                c.id,
                c.name,
                COUNT(DISTINCT dp.id) as progress_entries,
                COUNT(DISTINCT dp.user_id) as staff_count,
                AVG(dp.progress_percent) as avg_progress
            FROM customers c
            LEFT JOIN daily_progress dp ON c.id = dp.customer_id
            WHERE (dp.date BETWEEN ? AND ? OR dp.date IS NULL)
            AND c.deleted_at IS NULL
            GROUP BY c.id, c.name
            ORDER BY progress_entries DESC
        ", [$startDate, $endDate]);
        
        return $data;
    }
    
    /**
     * Cache analytics data
     */
    public function cacheData($key, $data, $ttl = 3600) {
        $this->db->query("
            INSERT INTO analytics_cache (cache_key, cache_data, expires_at)
            VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? SECOND))
            ON DUPLICATE KEY UPDATE 
                cache_data = ?,
                expires_at = DATE_ADD(NOW(), INTERVAL ? SECOND)
        ", [$key, json_encode($data), $ttl, json_encode($data), $ttl]);
    }
    
    /**
     * Get cached data
     */
    public function getCachedData($key) {
        $result = $this->db->fetch("
            SELECT cache_data 
            FROM analytics_cache 
            WHERE cache_key = ? AND expires_at > NOW()
        ", [$key]);
        
        return $result ? json_decode($result['cache_data'], true) : null;
    }
}

