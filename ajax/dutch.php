<?php
/**
 * Dutch Auction AJAX API Endpoint
 * File: ajax/dutch.php
 * 
 * Provides:
 * 1. Real-time dynamic price calculation & countdown (action=get_price)
 * 2. Instant Buy-Now transaction at current drop price (action=buy)
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

require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/AuctionEngine.php';

$pdo = Database::getPdo();
$engine = new AuctionEngine($pdo);

$action = $_POST['action'] ?? $_GET['action'] ?? 'get_price';
$itemId = (int)($_POST['item_id'] ?? $_GET['item_id'] ?? 0);

if ($itemId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Valid auction item ID is required.']);
    exit();
}

try {
    if ($action === 'get_price') {
        $stmt = $pdo->prepare("
            SELECT id, title, starting_price, reserve_price, dutch_floor_price, 
                   dutch_decrement_amount, dutch_decrement_interval, status, 
                   auction_type, start_time, end_time 
            FROM items 
            WHERE id = :id LIMIT 1
        ");
        $stmt->execute([':id' => $itemId]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$item) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Auction item not found.']);
            exit();
        }

        if (($item['auction_type'] ?? 'english') !== 'dutch') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'This lot is not a Dutch auction.']);
            exit();
        }

        $priceData = $engine->getDutchCurrentPrice($item);

        http_response_code(200);
        echo json_encode([
            'success'   => true,
            'item_id'   => $itemId,
            'status'    => $item['status'],
            'price'     => $priceData['current_price'],
            'price_fmt' => 'Rs. ' . number_format($priceData['current_price'], 2),
            'data'      => $priceData
        ]);
        exit();

    } elseif ($action === 'buy') {
        if (!is_logged_in()) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Please sign in to buy this Dutch auction lot.']);
            exit();
        }

        $userId = (int)($_SESSION['user_id'] ?? $_SESSION['id'] ?? 0);
        if ($userId <= 0) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Invalid session user ID.']);
            exit();
        }

        // Verify CSRF
        if (!validate_csrf_token()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'CSRF verification failed. Please reload.']);
            exit();
        }

        $result = $engine->executeDutchBuyNow($itemId, $userId);

        http_response_code(200);
        echo json_encode([
            'success'     => true,
            'message'     => 'Congratulations! You successfully bought this Dutch Auction lot at Rs. ' . number_format($result['final_price'], 2) . '!',
            'final_price' => $result['final_price'],
            'item_id'     => $itemId,
            'winner_id'   => $userId
        ]);
        exit();

    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Unknown action requested.']);
        exit();
    }
} catch (RuntimeException $e) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit();
} catch (\Throwable $e) {
    error_log("ajax/dutch.php Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error processing Dutch auction request.']);
    exit();
}
