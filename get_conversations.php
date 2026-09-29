<?php
/**
 * Get Conversations Endpoint
 * File: get_conversations.php
 * 
 * Tech Stack: PHP 8+, OOP PHP, PDO, JSON API
 * Returns list of distinct conversations for the logged-in user.
 */

header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/MessageService.php';

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode([
        'success'       => false,
        'message'       => 'Authentication required.',
        'conversations' => []
    ]);
    exit();
}

$userId = (int)($_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
    http_response_code(401);
    echo json_encode([
        'success'       => false,
        'message'       => 'Invalid session.',
        'conversations' => []
    ]);
    exit();
}

try {
    $msgService = new MessageService();
    $conversations = $msgService->getConversations($userId);
    $users = $msgService->getAllUsers($userId);

    http_response_code(200);
    echo json_encode([
        'success'       => true,
        'count'         => count($conversations),
        'conversations' => $conversations,
        'users'         => $users,
        'users_count'   => count($users)
    ]);
    exit();

} catch (Exception $e) {
    error_log("get_conversations.php error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success'       => false,
        'message'       => 'Server error retrieving conversations.',
        'conversations' => []
    ]);
    exit();
}
