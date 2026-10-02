<?php
/**
 * AJAX Message Dispatcher Endpoint
 * File: send_message.php
 * Geo Auction System
 *
 * Handles sending messages, database transaction, and notification triggering.
 */

header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/MessageService.php';

// Check request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed. POST is required.']);
    exit();
}

// Check authentication
if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required. Please sign in.']);
    exit();
}

$senderId = (int)($_SESSION['user_id'] ?? ($_SESSION['admin_id'] ?? 0));
if ($senderId <= 0) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Invalid user session.']);
    exit();
}

// Parse input (supports both standard POST FormData and JSON payload)
$rawInput = file_get_contents('php://input');
$jsonData = json_decode($rawInput, true);

$receiverId  = (int)($_POST['receiver_id'] ?? ($jsonData['receiver_id'] ?? 0));
$messageBody = trim($_POST['message_body'] ?? ($_POST['message'] ?? ($jsonData['message_body'] ?? ($jsonData['message'] ?? ''))));
$itemId      = isset($_POST['item_id']) ? (int)$_POST['item_id'] : (isset($jsonData['item_id']) ? (int)$jsonData['item_id'] : null);
$subject     = trim($_POST['subject'] ?? ($jsonData['subject'] ?? ''));

if ($receiverId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'A valid recipient user ID is required.']);
    exit();
}
if ($senderId === $receiverId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'You cannot send a message to yourself.']);
    exit();
}

if ($messageBody === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Message content cannot be empty.']);
    exit();
}

if (mb_strlen($messageBody) > 5000) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Message exceeds maximum permitted limit (5,000 characters).']);
    exit();
}

try {
    $msgService = new MessageService();
    $result = $msgService->sendMessage(
        $senderId,
        $receiverId,
        $messageBody,
        $itemId > 0 ? $itemId : null,
        $subject !== '' ? $subject : null
    );

    http_response_code(200);
    echo json_encode([
        'success'    => true,
        'message'    => 'Message sent successfully',
        'message_id' => $result['message_id'],
        'created_at' => $result['created_at'],
        'time_fmt'   => $result['time_fmt']
    ]);
    exit();

} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit();

} catch (RuntimeException $e) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit();

} catch (Exception $e) {
    error_log("send_message.php error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to send message due to a server error.']);
    exit();
}
