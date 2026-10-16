<?php
/**
 * Mark As Read Endpoint
 * File: mark_as_read.php
 * 
 * Tech Stack: PHP 8+, OOP PHP, PDO, JSON API
 * Supports marking a single notification, all notifications, or a message conversation as read.
 */

header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/NotificationService.php';
require_once __DIR__ . '/includes/MessageService.php';

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required.']);
    exit();
}

$currentUserId = (int)($_SESSION['user_id'] ?? 0);
if ($currentUserId <= 0) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Invalid session.']);
    exit();
}

// Parse inputs from POST, JSON, or GET
$rawInput = file_get_contents('php://input');
$jsonData = json_decode($rawInput, true);

$notificationId       = isset($_POST['notification_id']) ? (int)$_POST['notification_id'] : (isset($jsonData['notification_id']) ? (int)$jsonData['notification_id'] : (isset($_GET['notification_id']) ? (int)$_GET['notification_id'] : 0));
$markAllNotifications = isset($_POST['mark_all_notifications']) || isset($jsonData['mark_all_notifications']) || isset($_GET['mark_all_notifications']) || ((isset($_POST['action']) && $_POST['action'] === 'mark_all_notifications') || (isset($_GET['action']) && $_GET['action'] === 'mark_all_notifications'));
$messageUserId        = isset($_POST['message_user_id']) ? (int)$_POST['message_user_id'] : (isset($jsonData['message_user_id']) ? (int)$jsonData['message_user_id'] : (isset($_GET['message_user_id']) ? (int)$_GET['message_user_id'] : 0));

try {
    $notifService = new NotificationService();
    $msgService   = new MessageService();

    // 1. Mark All Notifications as Read
    if ($markAllNotifications) {
        $notifService->markAllAsRead($currentUserId);
        $newUnreadCount = $notifService->getUnreadCount($currentUserId);

        http_response_code(200);
        echo json_encode([
            'success'      => true,
            'message'      => 'All notifications marked as read',
            'unread_count' => $newUnreadCount
        ]);
        exit();
    }

    // 2. Mark Single Notification as Read
    if ($notificationId > 0) {
        $notifService->markAsRead($notificationId, $currentUserId);
        $newUnreadCount = $notifService->getUnreadCount($currentUserId);

        http_response_code(200);
        echo json_encode([
            'success'         => true,
            'notification_id' => $notificationId,
            'unread_count'    => $newUnreadCount
        ]);
        exit();
    }

    // 3. Mark Messages from other user as Read
    if ($messageUserId > 0) {
        $updatedRows = $msgService->markMessagesAsRead($currentUserId, $messageUserId);
        $newUnreadMsgs = $msgService->getUnreadCount($currentUserId);

        http_response_code(200);
        echo json_encode([
            'success'         => true,
            'updated_count'   => $updatedRows,
            'unread_messages' => $newUnreadMsgs
        ]);
        exit();
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No recognized read action specified.']);
    exit();

} catch (Exception $e) {
    error_log("mark_as_read.php error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error marking item as read.']);
    exit();
}
