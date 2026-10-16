<?php
/**
 * Send Message Endpoint (Real-Time Messaging API)
 * File: send_message.php
 * 
 * Tech Stack: PHP 8+, OOP PHP, PDO, JSON API
 * Features: CSRF & Same-Origin Defense, Anti-Spam Rate Limiting,
 * Input Sanitization, and Transactional Message Dispatching.
 */

declare(strict_types=1);

if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
}


if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/MessageService.php';

// 1. Verify HTTP Request Method
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method Not Allowed. POST is required.'
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

// 2. Authentication Check
if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Authentication required. Please sign in.'
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

$senderId = (int)($_SESSION['user_id'] ?? $_SESSION['id'] ?? 0);
if ($senderId <= 0) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid or expired user session.'
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

// 3. Parse Input (Supports JSON payloads and standard multipart/urlencoded FormData)
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
$jsonData = [];

if (stripos($contentType, 'application/json') !== false) {
    $rawInput = file_get_contents('php://input');
    if (!empty($rawInput)) {
        $jsonData = json_decode($rawInput, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Malformed JSON payload: ' . json_last_error_msg()
            ], JSON_UNESCAPED_UNICODE);
            exit();
        }
    }
}

// Extract parameters
$receiverId  = (int)($_POST['receiver_id'] ?? ($jsonData['receiver_id'] ?? 0));
$rawBody     = (string)($_POST['message_body'] ?? ($_POST['message'] ?? ($jsonData['message_body'] ?? ($jsonData['message'] ?? ''))));
$itemId      = isset($_POST['item_id']) ? (int)$_POST['item_id'] : (isset($jsonData['item_id']) ? (int)$jsonData['item_id'] : null);
$rawSubject  = (string)($_POST['subject'] ?? ($jsonData['subject'] ?? ''));
$csrfToken   = (string)($_POST['csrf_token'] ?? ($jsonData['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')));

// 4. CSRF and Same-Origin Protection
$isCsrfValid = false;
if (!empty($csrfToken) && validate_csrf_token($csrfToken)) {
    $isCsrfValid = true;
} else {
    // Defense-in-depth: Verify same-origin referrer and AJAX signature
    $serverHost = $_SERVER['HTTP_HOST'] ?? '';
    $origin     = $_SERVER['HTTP_ORIGIN'] ?? '';
    $referer    = $_SERVER['HTTP_REFERER'] ?? '';
    $xRequested = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '');

    $originMatches = false;
    if (!empty($origin)) {
        $originHost = parse_url($origin, PHP_URL_HOST);
        $originMatches = ($originHost !== null && strcasecmp($originHost, explode(':', $serverHost)[0]) === 0);
    } elseif (!empty($referer)) {
        $refHost = parse_url($referer, PHP_URL_HOST);
        $originMatches = ($refHost !== null && strcasecmp($refHost, explode(':', $serverHost)[0]) === 0);
    }

    if ($originMatches && $xRequested === 'xmlhttprequest') {
        $isCsrfValid = true;
    }
}

if (!$isCsrfValid) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Security token mismatch. Please refresh your browser window.'
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

// 5. Anti-Spam Rate Limiting (25 messages / 60 seconds)
$rateLimit = check_rate_limit('msg_send_' . $senderId, 25, 60);
if (!$rateLimit['allowed']) {
    http_response_code(429);
    echo json_encode([
        'success' => false,
        'message' => 'You are sending messages too quickly. Please pause for a few seconds.'
    ], JSON_UNESCAPED_UNICODE);
    exit();
}
record_rate_limit_attempt('msg_send_' . $senderId, 60);

// 6. Input Sanitization & Integrity Validation
// Strip null bytes and normalize whitespace
$cleanBody = str_replace(chr(0), '', trim($rawBody));
$cleanBody = strip_tags($cleanBody);

$cleanSubject = str_replace(chr(0), '', trim($rawSubject));
$cleanSubject = strip_tags($cleanSubject);

if ($receiverId <= 0) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'A valid recipient member ID is required.'
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

if ($senderId === $receiverId) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'You cannot send a message to your own account.'
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

if ($cleanBody === '') {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Message content cannot be blank.'
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

if (mb_strlen($cleanBody) > 5000) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Message exceeds maximum allowable limit of 5,000 characters.'
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

// 7. Dispatch Message via Transactional Message Service
try {
    $msgService = new MessageService();
    $result = $msgService->sendMessage(
        $senderId,
        $receiverId,
        $cleanBody,
        ($itemId !== null && $itemId > 0) ? $itemId : null,
        $cleanSubject !== '' ? $cleanSubject : null
    );

    http_response_code(200);
    echo json_encode([
        'success'    => true,
        'message'    => 'Message sent successfully',
        'message_id' => $result['message_id'],
        'created_at' => $result['created_at'],
        'time_fmt'   => $result['time_fmt'],
        'data'       => [
            'message_id'  => $result['message_id'],
            'sender_id'   => $senderId,
            'receiver_id' => $receiverId,
            'item_id'     => $itemId,
            'created_at'  => $result['created_at'],
            'time_fmt'    => $result['time_fmt']
        ]
    ], JSON_UNESCAPED_UNICODE);
    exit();

} catch (InvalidArgumentException $e) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit();

} catch (RuntimeException $e) {
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit();

} catch (\Throwable $e) {
    error_log("send_message.php critical error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Unable to dispatch message due to an internal server error.'
    ], JSON_UNESCAPED_UNICODE);
    exit();
}
