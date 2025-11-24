<?php
/**
 * api/v1/endpoints/notifications.php
 * Notifications API Endpoints
 */

$notifications = new NotificationSystem();
$user = $auth->currentUser();

switch ($method) {
    case 'GET':
        $unreadOnly = isset($_GET['unread_only']) && $_GET['unread_only'] == '1';
        $data = $notifications->getUserNotifications($user['id'], $unreadOnly);
        $unreadCount = $notifications->getUnreadCount($user['id']);
        Response::success('Notifications retrieved', [
            'notifications' => $data,
            'unread_count' => $unreadCount
        ]);
        break;
        
    case 'POST':
        if ($id && $action === 'read') {
            $notifications->markAsRead($id, $user['id']);
            Response::success('Notification marked as read');
        } elseif ($action === 'read-all') {
            $notifications->markAllAsRead($user['id']);
            Response::success('All notifications marked as read');
        } else {
            Response::error('Invalid action', null, 400);
        }
        break;
        
    default:
        Response::error('Method not allowed', null, 405);
}

