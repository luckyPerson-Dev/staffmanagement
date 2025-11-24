<?php
/**
 * notifications/index.php
 * Notification Center
 */

require_once __DIR__ . '/../auth_helper.php';
require_once __DIR__ . '/../helpers.php';

require_login();

$user = current_user();
$pdo = getPDO();

// Get notifications
$stmt = $pdo->prepare("
    SELECT * FROM notifications
    WHERE user_id = ?
    ORDER BY created_at DESC
    LIMIT 50
");
$stmt->execute([$user['id']]);
$notifications = $stmt->fetchAll();

// Get unread count
$stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND read = 0");
$stmt->execute([$user['id']]);
$unreadCount = $stmt->fetchColumn();

$page_title = 'Notifications';
include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="gradient-text mb-2">Notifications</h1>
            <p class="text-muted mb-0">Stay updated with your latest notifications</p>
        </div>
        <?php if ($unreadCount > 0): ?>
            <button class="btn btn-primary btn-lg" onclick="markAllAsRead()">
                <i class="bi bi-check-all me-2"></i>Mark All as Read
            </button>
        <?php endif; ?>
    </div>
    
    <?php if (empty($notifications)): ?>
        <div class="card shadow-lg border-0">
            <div class="card-body text-center py-5">
                <i class="bi bi-bell-slash fs-1 text-muted d-block mb-3"></i>
                <h5 class="text-muted">No notifications</h5>
                <p class="text-muted">You're all caught up!</p>
            </div>
        </div>
    <?php else: ?>
        <div class="card shadow-lg border-0">
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    <?php foreach ($notifications as $notification): ?>
                        <div class="list-group-item list-group-item-action <?= !$notification['read'] ? 'bg-light' : '' ?>" 
                             style="cursor: pointer;"
                             onclick="markAsRead(<?= $notification['id'] ?>)">
                            <div class="d-flex align-items-start">
                                <div class="me-3">
                                    <?php
                                    $iconClass = 'bi-info-circle';
                                    $iconColor = 'text-info';
                                    if ($notification['type'] === 'success') {
                                        $iconClass = 'bi-check-circle-fill';
                                        $iconColor = 'text-success';
                                    } elseif ($notification['type'] === 'warning') {
                                        $iconClass = 'bi-exclamation-triangle-fill';
                                        $iconColor = 'text-warning';
                                    } elseif ($notification['type'] === 'danger') {
                                        $iconClass = 'bi-x-circle-fill';
                                        $iconColor = 'text-danger';
                                    }
                                    ?>
                                    <i class="bi <?= $iconClass ?> <?= $iconColor ?> fs-4"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="d-flex justify-content-between align-items-start mb-1">
                                        <h6 class="mb-0 <?= !$notification['read'] ? 'fw-bold' : '' ?>">
                                            <?= h($notification['title']) ?>
                                        </h6>
                                        <?php if (!$notification['read']): ?>
                                            <span class="badge bg-primary">New</span>
                                        <?php endif; ?>
                                    </div>
                                    <p class="mb-1 text-muted"><?= h($notification['message']) ?></p>
                                    <small class="text-muted">
                                        <i class="bi bi-clock me-1"></i>
                                        <?= format_datetime($notification['created_at']) ?>
                                    </small>
                                </div>
                            </div>
                            <?php if ($notification['link']): ?>
                                <div class="mt-2">
                                    <a href="<?= BASE_URL . $notification['link'] ?>" class="btn btn-sm btn-outline-primary">
                                        View Details <i class="bi bi-arrow-right ms-1"></i>
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
function markAsRead(id) {
    fetch('<?= BASE_URL ?>/api/v1/notifications/' + id + '/read', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({})
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            location.reload();
        }
    });
}

function markAllAsRead() {
    fetch('<?= BASE_URL ?>/api/v1/notifications/read-all', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({})
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            location.reload();
        }
    });
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>

