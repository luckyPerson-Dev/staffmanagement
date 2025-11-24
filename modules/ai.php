<?php
/**
 * modules/ai.php
 * AI-Enhanced Features Module
 * Note: This is a simulated AI module. In production, integrate with actual AI APIs.
 */

class AI {
    private $db;
    private $logger;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->logger = new Logger();
    }
    
    /**
     * Analyze staff performance patterns
     */
    public function analyzePerformance($userId, $month, $year) {
        // Get progress data
        $progress = $this->db->fetchAll("
            SELECT progress_percent, tickets_missed, date
            FROM daily_progress
            WHERE user_id = ? AND YEAR(date) = ? AND MONTH(date) = ?
            ORDER BY date
        ", [$userId, $year, $month]);
        
        if (empty($progress)) {
            return null;
        }
        
        // Calculate patterns
        $avgProgress = array_sum(array_column($progress, 'progress_percent')) / count($progress);
        $lowDays = array_filter($progress, function($p) { return $p['progress_percent'] < 60; });
        $missedTickets = array_sum(array_column($progress, 'tickets_missed'));
        
        // Generate insights
        $insights = [];
        
        if ($avgProgress < 70) {
            $insights[] = [
                'type' => 'warning',
                'message' => "Low average performance ({$avgProgress}%). Consider additional support.",
                'confidence' => 0.85
            ];
        }
        
        if (count($lowDays) > 5) {
            $insights[] = [
                'type' => 'alert',
                'message' => count($lowDays) . " days with performance below 60%. Pattern detected.",
                'confidence' => 0.90
            ];
        }
        
        if ($missedTickets > 10) {
            $insights[] = [
                'type' => 'suggestion',
                'message' => "High ticket misses ({$missedTickets}). Review workload distribution.",
                'confidence' => 0.75
            ];
        }
        
        // Store insights
        foreach ($insights as $insight) {
            $this->db->insert('ai_insights', [
                'user_id' => $userId,
                'type' => $insight['type'],
                'insight' => $insight['message'],
                'confidence' => $insight['confidence'],
                'data' => json_encode(['month' => $month, 'year' => $year])
            ]);
        }
        
        return $insights;
    }
    
    /**
     * Suggest penalty adjustments
     */
    public function suggestPenalties($userId) {
        // Analyze last 3 months
        $data = $this->db->fetchAll("
            SELECT 
                AVG(progress_percent) as avg_progress,
                AVG(tickets_missed) as avg_tickets,
                COUNT(*) as days_count
            FROM daily_progress
            WHERE user_id = ? 
            AND date >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)
        ", [$userId]);
        
        if (empty($data) || $data[0]['days_count'] < 10) {
            return null;
        }
        
        $avgProgress = $data[0]['avg_progress'];
        $avgTickets = $data[0]['avg_tickets'];
        
        $suggestions = [];
        
        // Current settings
        $ticketPenalty = get_setting('ticket_penalty_percent', 5);
        $groupMissPenalty = get_setting('group_miss_percent', 10);
        
        if ($avgProgress < 65 && $avgTickets > 2) {
            $suggestions[] = [
                'type' => 'increase',
                'setting' => 'ticket_penalty_percent',
                'current' => $ticketPenalty,
                'suggested' => min(10, $ticketPenalty + 2),
                'reason' => 'High ticket misses with low performance'
            ];
        }
        
        return $suggestions;
    }
    
    /**
     * Generate monthly summary
     */
    public function generateMonthlySummary($userId, $month, $year) {
        $progress = $this->db->fetchAll("
            SELECT * FROM daily_progress
            WHERE user_id = ? AND YEAR(date) = ? AND MONTH(date) = ?
            ORDER BY date
        ", [$userId, $year, $month]);
        
        if (empty($progress)) {
            return "No progress data available for this month.";
        }
        
        $avgProgress = array_sum(array_column($progress, 'progress_percent')) / count($progress);
        $bestDay = max(array_column($progress, 'progress_percent'));
        $worstDay = min(array_column($progress, 'progress_percent'));
        $totalTickets = array_sum(array_column($progress, 'tickets_missed'));
        
        $summary = "Monthly Performance Summary for " . date('F Y', mktime(0,0,0,$month,1,$year)) . ":\n\n";
        $summary .= "Average Progress: " . number_format($avgProgress, 2) . "%\n";
        $summary .= "Best Day: " . number_format($bestDay, 2) . "%\n";
        $summary .= "Worst Day: " . number_format($worstDay, 2) . "%\n";
        $summary .= "Total Tickets Missed: {$totalTickets}\n";
        $summary .= "Days Recorded: " . count($progress) . "\n\n";
        
        if ($avgProgress >= 80) {
            $summary .= "Excellent performance this month! Keep up the great work.";
        } elseif ($avgProgress >= 60) {
            $summary .= "Good performance. There's room for improvement in consistency.";
        } else {
            $summary .= "Performance needs attention. Consider reviewing workload and priorities.";
        }
        
        return $summary;
    }
    
    /**
     * Predict next month workload
     */
    public function predictWorkload($userId) {
        // Get last 3 months data
        $data = $this->db->fetchAll("
            SELECT 
                AVG(tickets_missed) as avg_tickets,
                AVG(progress_percent) as avg_progress
            FROM daily_progress
            WHERE user_id = ?
            AND date >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)
        ", [$userId]);
        
        if (empty($data)) {
            return null;
        }
        
        $trend = $data[0];
        $prediction = [
            'expected_tickets' => round($trend['avg_tickets'] * 26), // 26 working days
            'expected_progress' => round($trend['avg_progress'], 2),
            'confidence' => 0.70
        ];
        
        return $prediction;
    }
    
    /**
     * Generate review notes
     */
    public function generateReviewNotes($userId, $month, $year) {
        $summary = $this->generateMonthlySummary($userId, $month, $year);
        $insights = $this->analyzePerformance($userId, $month, $year);
        
        $notes = $summary . "\n\nKey Insights:\n";
        foreach ($insights as $insight) {
            $notes .= "- " . $insight['message'] . "\n";
        }
        
        return $notes;
    }
}

