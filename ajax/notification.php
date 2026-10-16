<?php
/**
 * AJAX Notification Polling & Actions Endpoint
 * File: ajax/notification.php
 * Geo Auction System
 *
 * Uses OOP PHP + PDO with NotificationService.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/NotificationService.php';

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'count' => 0, 'unread' => 0, 'notifications' => []]);
    exit();
}

$userId = (int)($_SESSION['user_id'] ?? ($_SESSION['id'] ?? 0));
$action = $_GET['action'] ?? ($_POST['action'] ?? 'fetch');

try {
    $notifService = new NotificationService();

    if ($action === 'mark_read') {
        $notifService->markAllAsRead($userId);
        echo json_encode(['success' => true, 'unread' => 0]);
        exit();
    }

    if ($action === 'mark_one' && (isset($_GET['id']) || isset($_POST['id']))) {
        $nid = (int)($_GET['id'] ?? ($_POST['id'] ?? 0));
        if ($nid > 0) {
            $notifService->markAsRead($nid, $userId);
        }
        $unread = $notifService->getUnreadCount($userId);
        echo json_encode(['success' => true, 'unread' => $unread]);
        exit();
    }

    $notifications = $notifService->getRecentNotifications($userId, 15);
    $unreadCount   = $notifService->getUnreadCount($userId);

    echo json_encode([
        'success'       => true,
        'count'         => $unreadCount,
        'unread'        => $unreadCount,
        'notifications' => $notifications
    ]);
} catch (\Throwable $e) {
    error_log("ajax/notification.php error: " . $e->getMessage());
    echo json_encode(['success' => false, 'count' => 0, 'unread' => 0, 'notifications' => []]);
}
exit();
