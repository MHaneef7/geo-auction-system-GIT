<?php
/**
 * Fetch Chat History Endpoint
 * File: fetch_messages.php
 * 
 * Tech Stack: PHP 8+, OOP PHP, PDO, JSON API
 * Fetches message history between the authenticated user and a specific recipient.
 */

header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/MessageService.php';

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required.']);
    exit();
}

$currentUserId = (int)($_SESSION['user_id'] ?? ($_SESSION['admin_id'] ?? 0));
if ($currentUserId <= 0) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Invalid session identifier.']);
    exit();
}

// Accept user_id from GET or POST
$otherUserId = (int)($_GET['user_id'] ?? ($_POST['user_id'] ?? 0));
$itemId = isset($_GET['item_id']) ? (int)$_GET['item_id'] : (isset($_POST['item_id']) ? (int)$_POST['item_id'] : null);
$autoMarkRead = isset($_GET['mark_read']) ? (bool)$_GET['mark_read'] : true;

if ($otherUserId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'A valid user ID is required.']);
    exit();
}

try {
    $msgService = new MessageService();

    // Verify other user exists
    $otherUser = $msgService->getUser($otherUserId);
    if (!$otherUser) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'User not found.']);
        exit();
    }

    // Auto mark messages as read if requested
    if ($autoMarkRead) {
        $msgService->markMessagesAsRead($currentUserId, $otherUserId);
    }

    // Fetch messages between current user and other user
    $messages = $msgService->getChatHistory($currentUserId, $otherUserId);

    // Fetch item context if specified or attached to conversation
    $relatedItem = null;
    $targetItemId = $itemId;

    if (!$targetItemId && !empty($messages)) {
        // Find most recent item reference in the conversation
        for ($i = count($messages) - 1; $i >= 0; $i--) {
            if (!empty($messages[$i]['item_id'])) {
                $targetItemId = $messages[$i]['item_id'];
                break;
            }
        }
    }

    if ($targetItemId) {
        $itemRow = $msgService->getItem($targetItemId);
        if ($itemRow) {
            $relatedItem = [
                'id'            => (int)$itemRow['id'],
                'title'         => htmlspecialchars($itemRow['title'], ENT_QUOTES, 'UTF-8'),
                'auction_code'  => htmlspecialchars($itemRow['auction_code'], ENT_QUOTES, 'UTF-8'),
                'current_price' => number_format((float)($itemRow['current_price'] ?: $itemRow['starting_price']), 2),
                'status'        => $itemRow['status'],
                'image'         => $itemRow['featured_image'] ? htmlspecialchars($itemRow['featured_image'], ENT_QUOTES, 'UTF-8') : '',
            ];
        }
    }

    // Resolve avatar
    $avatar = trim($otherUser['avatar'] ?? '');
    if (!empty($avatar)) {
        if (!preg_match('/^(https?:\/\/|\/\/|data:)/i', $avatar)) {
            $avatar = ltrim($avatar, '/\\');
        }
    } else {
        $avatar = 'https://ui-avatars.com/api/?name=' . urlencode($otherUser['name'] ?: 'User') . '&background=047857&color=fff&size=96&bold=true';
    }

    http_response_code(200);
    echo json_encode([
        'success'      => true,
        'current_user' => $currentUserId,
        'other_user'   => [
            'id'       => (int)$otherUser['id'],
            'name'     => htmlspecialchars($otherUser['name'], ENT_QUOTES, 'UTF-8'),
            'username' => !empty($otherUser['username']) ? htmlspecialchars($otherUser['username'], ENT_QUOTES, 'UTF-8') : '',
            'avatar'   => $avatar,
            'role'     => $otherUser['role'] ?? 'buyer',
            'status'   => $otherUser['status'] ?? 'active',
        ],
        'related_item' => $relatedItem,
        'messages'     => $messages,
        'count'        => count($messages)
    ]);
    exit();

} catch (Exception $e) {
    error_log("fetch_messages.php error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error while retrieving chat history.']);
    exit();
}
