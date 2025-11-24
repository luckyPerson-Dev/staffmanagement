<?php
/**
 * crons/send_reminders.php
 * Send daily reminders (progress entry, attendance, etc.)
 * 
 * Setup cron: 0 9 * * * /usr/bin/php /path/to/crons/send_reminders.php
 */

require_once __DIR__ . '/../core/autoload.php';

$logger = new Logger();
$notifications = new NotificationSystem();
$db = Database::getInstance();

try {
    // Get all active staff
    $staff = $db->fetchAll("SELECT id, name, email FROM users WHERE role = 'staff' AND deleted_at IS NULL");
    
    foreach ($staff as $member) {
        // Check if progress entered today
        $today = date('Y-m-d');
        $progress = $db->fetch("SELECT id FROM daily_progress WHERE user_id = ? AND date = ?", [$member['id'], $today]);
        
        if (!$progress) {
            // Send reminder
            $notifications->create(
                $member['id'],
                'reminder',
                'Daily Progress Reminder',
                'Please remember to enter your daily progress for today.',
                BASE_URL . '/progress/add.php'
            );
            
            // Send email if configured
            if (SMTP_USER) {
                $notifications->sendEmail(
                    $member['id'],
                    'Daily Progress Reminder',
                    "Hello {$member['name']},<br><br>Please remember to enter your daily progress for today."
                );
            }
        }
    }
    
    $logger->info("Reminders sent successfully");
    
} catch (Exception $e) {
    $logger->error("Reminder sending failed: " . $e->getMessage());
    exit(1);
}

