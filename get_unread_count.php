<?php
/**
 * Unread Count Endpoint
 * File: get_unread_count.php
 * 
 * Tech Stack: PHP 8+, OOP PHP, PDO, JSON API
 * Returns unread message count and unread notification count.
 */

header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/MessageService.php';
require_once __DIR__ . '/includes/NotificationService.php';

if (!is_logged_in()) {
    http_response_code(200);
    echo json_encode([
        'success'              => true,
        'unread_messages'      => 0,
        'unread_notifications' => 0
    ]);
    exit();
}

$userId = (int)($_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
    http_response_code(200);
    echo json_encode([
        'success'              => true,
        'unread_messages'      => 0,
        'unread_notifications' => 0
    ]);
    exit();
}

try {
    $msgService   = new MessageService();
    $notifService = new NotificationService();

    $unreadMessages = $msgService->getUnreadCount($userId);
    $unreadNotifs   = $notifService->getUnreadCount($userId);

    http_response_code(200);
    echo json_encode([
        'success'              => true,
        'unread_messages'      => $unreadMessages,
        'unread_notifications' => $unreadNotifs
    ]);
    exit();

} catch (Exception $e) {
    error_log("get_unread_count.php error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success'              => false,
        'message'              => 'Server error',
        'unread_messages'      => 0,
        'unread_notifications' => 0
    ]);
    exit();
}
