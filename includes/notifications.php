<?php
function getNotifications($user_id, $pdo, $limit = 10) {
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

// Responsive notification bell
function renderNotificationBell($user_id, $pdo) {
    $unread = getUnreadCount($user_id, $pdo);
    $notifications = getNotifications($user_id, $pdo, 5);
    
    $html = '<div class="dropdown d-inline-block">
        <button class="btn btn-light btn-sm position-relative" data-bs-toggle="dropdown" style="border-radius: 50%; width: 40px; height: 40px;">
            <i class="fas fa-bell"></i>';
    
    if($unread > 0) {
        $html .= '<span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 10px;">' . $unread . '</span>';
    }
    
    $html .= '</button>
        <ul class="dropdown-menu dropdown-menu-end p-0" style="width: 280px; max-width: 90vw; max-height: 400px; overflow-y: auto;">';
    
    if(count($notifications) == 0) {
        $html .= '<li class="text-center py-3 text-muted"><i class="fas fa-bell-slash"></i> No notifications</li>';
    } else {
        foreach($notifications as $notif) {
            $html .= '<li class="border-bottom">
                <a class="dropdown-item py-2" href="#" style="white-space: normal; font-size: 13px;">
                    <strong>' . htmlspecialchars($notif['title']) . '</strong><br>
                    <small class="text-muted">' . htmlspecialchars($notif['message']) . '</small><br>
                    <small class="text-muted" style="font-size: 10px;">' . date('M j, g:i A', strtotime($notif['created_at'])) . '</small>
                </a>
            </li>';
        }
    }
    
    $html .= '</ul>
    </div>';
    
    return $html;
}
?>