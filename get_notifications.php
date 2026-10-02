<?php
/**
 * Get Notifications Endpoint
 * File: get_notifications.php
 * 
 * Tech Stack: PHP 8+, OOP PHP, PDO, JSON API
 * Returns unread count and latest 10 notifications for the logged-in user.
 */

header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/NotificationService.php';

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode([
        'success'       => false,
        'message'       => 'Authentication required.',
        'unread_count'  => 0,
        'notifications' => []
    ]);
    exit();
}

$userId = (int)($_SESSION['user_id'] ?? ($_SESSION['admin_id'] ?? 0));
if ($userId <= 0) {
    http_response_code(401);
    echo json_encode([
        'success'       => false,
        'message'       => 'Invalid user session.',
        'unread_count'  => 0,
        'notifications' => []
    ]);
    exit();
}

try {
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
    $notifService = new NotificationService();

    $unreadCount   = $notifService->getUnreadCount($userId);
    $notifications = $notifService->getRecentNotifications($userId, $limit);

    http_response_code(200);
    echo json_encode([
        'success'       => true,
        'unread_count'  => $unreadCount,
        'notifications' => $notifications
    ]);
    exit();

} catch (Exception $e) {
    error_log("get_notifications.php error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success'       => false,
        'message'       => 'Server error retrieving notifications.',
        'unread_count'  => 0,
        'notifications' => []
    ]);
    exit();
}
