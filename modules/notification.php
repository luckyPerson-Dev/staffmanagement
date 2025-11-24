<?php
/**
 * modules/notification.php
 * Notification System Module
 */

class NotificationSystem {
    private $db;
    private $logger;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->logger = new Logger();
    }
    
    /**
     * Create notification
     */
    public function create($userId, $type, $title, $message, $link = null) {
        return $this->db->insert('notifications', [
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'link' => $link
        ]);
    }
    
    /**
     * Get user notifications
     */
    public function getUserNotifications($userId, $unreadOnly = false) {
        $where = $unreadOnly ? "AND read = 0" : "";
        return $this->db->fetchAll("
            SELECT * FROM notifications
            WHERE user_id = ? {$where}
            ORDER BY created_at DESC
            LIMIT 50
        ", [$userId]);
    }
    
    /**
     * Mark as read
     */
    public function markAsRead($notificationId, $userId) {
        $this->db->query("
            UPDATE notifications 
            SET read = 1 
            WHERE id = ? AND user_id = ?
        ", [$notificationId, $userId]);
    }
    
    /**
     * Mark all as read
     */
    public function markAllAsRead($userId) {
        $this->db->query("
            UPDATE notifications 
            SET read = 1 
            WHERE user_id = ? AND read = 0
        ", [$userId]);
    }
    
    /**
     * Get unread count
     */
    public function getUnreadCount($userId) {
        $result = $this->db->fetch("
            SELECT COUNT(*) as count 
            FROM notifications 
            WHERE user_id = ? AND read = 0
        ", [$userId]);
        return $result['count'] ?? 0;
    }
    
    /**
     * Send email notification (if configured)
     */
    public function sendEmail($userId, $subject, $message) {
        $user = $this->db->fetch("SELECT email, name FROM users WHERE id = ?", [$userId]);
        if (!$user) return false;
        
        // Simple mail function (configure SMTP in production)
        $headers = "From: " . SMTP_FROM_NAME . " <" . SMTP_FROM_EMAIL . ">\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        
        return mail($user['email'], $subject, $message, $headers);
    }
}

