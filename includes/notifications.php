<?php
function getNotifications($user_id, $pdo, $limit = 10) {
    // Remove the ? parameter and use integer directly
    $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT " . (int)$limit);
    $stmt->execute([$user_id]);
    return $stmt->fetchAll();
}

function markAsRead($notification_id, $pdo) {
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ?");
    $stmt->execute([$notification_id]);
}

function getUnreadCount($user_id, $pdo) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    return $stmt->fetchColumn();
}

// Add notification bell to all pages
function renderNotificationBell($user_id, $pdo) {
    $unread = getUnreadCount($user_id, $pdo);
    $notifications = getNotifications($user_id, $pdo, 5);
    
    $html = '<div class="dropdown d-inline-block me-3">
        <button class="btn btn-light position-relative" data-bs-toggle="dropdown">
            <i class="fas fa-bell"></i>';
    
    if($unread > 0) {
        $html .= '<span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">' . $unread . '</span>';
    }
    
    $html .= '</button>
        <ul class="dropdown-menu dropdown-menu-end" style="width: 320px;">';
    
    if(count($notifications) == 0) {
        $html .= '<li><a class="dropdown-item text-center" href="#">No new notifications</a></li>';
    } else {
        foreach($notifications as $notif) {
            $html .= '<li>
                <a class="dropdown-item" href="#" style="white-space: normal;">
                    <strong>' . htmlspecialchars($notif['title']) . '</strong><br>
                    <small>' . htmlspecialchars($notif['message']) . '</small><br>
                    <small class="text-muted">' . date('M j, g:i A', strtotime($notif['created_at'])) . '</small>
                </a>
            </li>
            <li><hr class="dropdown-divider"></li>';
        }
    }
    
    $html .= '</ul>
    </div>';
    
    return $html;
}
?>