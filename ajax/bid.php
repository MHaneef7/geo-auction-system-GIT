<?php
/**
 * AJAX Bid Placement Endpoint
 * File: ajax/bid.php
 * Geo Auction System
 *
 * Production-ready bidding controller delegating to the unified, concurrency-safe
 * AuctionEngine. Enforces strict ACID transactions, pessimistic row locks,
 * anti-sniping soft-close, proxy bidding escalation, and wallet escrow pre-authorization.
 */

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');

require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/AuctionEngine.php';

// -------------------------------------------------------------------------
// Helper: Send JSON Error and Terminate Cleanly
// -------------------------------------------------------------------------
function sendJsonError(string $message, int $httpCode = 400): void {
    http_response_code($httpCode);
    echo json_encode([
        'success' => false,
        'message' => $message,
        'error'   => $message
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

// -------------------------------------------------------------------------
// 1. HTTP Method Check
// -------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonError('Invalid request method. Only POST requests are permitted.', 405);
}

// -------------------------------------------------------------------------
// 2. Authentication Verification
// -------------------------------------------------------------------------
if (!is_logged_in()) {
    sendJsonError('Please sign in to place a bid on this lot.', 401);
}

$userId = (int)($_SESSION['user_id'] ?? ($_SESSION['id'] ?? 0));
if ($userId <= 0) {
    sendJsonError('Invalid session user identity. Please log in again.', 401);
}

// -------------------------------------------------------------------------
// 3. Input Sanitization & Validation
// -------------------------------------------------------------------------
$rawItemId    = $_POST['item_id'] ?? null;
$rawBidAmount = $_POST['bid_amount'] ?? null;

// Validate Item ID
if ($rawItemId === null || !filter_var($rawItemId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])) {
    sendJsonError('Invalid or missing auction lot identifier.');
}
$itemId = (int)$rawItemId;

// Validate Bid Amount format
if ($rawBidAmount === null || !is_numeric($rawBidAmount)) {
    sendJsonError('Bid amount must be a valid numeric number.');
}

$bidAmountFloat = (float)$rawBidAmount;
if (is_nan($bidAmountFloat) || is_infinite($bidAmountFloat) || $bidAmountFloat <= 0) {
    sendJsonError('Bid amount must be a positive number greater than zero.');
}

// Strict decimal precision check (maximum 2 decimal places)
$cleanBidStr = trim((string)$rawBidAmount);
if (!preg_match('/^\d+(\.\d{1,2})?$/', $cleanBidStr)) {
    sendJsonError('Bid amount cannot have more than 2 decimal places.');
}

$bidAmount = round($bidAmountFloat, 2);

// -------------------------------------------------------------------------
// 4. Delegate to Concurrency-Safe AuctionEngine
// -------------------------------------------------------------------------
try {
    $pdo = Database::getPdo();
    $engine = new AuctionEngine($pdo);

    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

    $result = $engine->placeBid($itemId, $userId, $bidAmount, $ipAddress, $userAgent);

    http_response_code(200);
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
    exit();

} catch (InvalidArgumentException $ie) {
    sendJsonError($ie->getMessage(), 400);
} catch (RuntimeException $re) {
    sendJsonError($re->getMessage(), 400);
} catch (\Throwable $e) {
    error_log("ajax/bid.php Fatal Exception: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    sendJsonError('An internal processing error occurred while placing your bid. Please try again.', 500);
}
