<?php
/**
 * Admin Place Bid Endpoint
 * File: admin/place-bid.php
 * Geo Auction System
 *
 * Uses OOP PHP + PDO with concurrency safety.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Please log in to place a bid.']);
    exit();
}

$userId    = (int)($_SESSION['user_id'] ?? ($_SESSION['id'] ?? 0));
$itemId    = isset($_POST['item_id']) ? (int)$_POST['item_id'] : 0;
$bidAmount = isset($_POST['bid_amount']) ? (float)$_POST['bid_amount'] : 0.0;

if ($itemId <= 0 || $bidAmount <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid bid request parameters.']);
    exit();
}

try {
    $pdo = Database::getPdo();
    $pdo->beginTransaction();

    // 1. Retrieve Item Details with Row Lock
    $stmt = $pdo->prepare("SELECT * FROM items WHERE id = :id FOR UPDATE");
    $stmt->execute([':id' => $itemId]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$item) {
        throw new Exception("Auction item not found.");
    }

    if ($item['status'] !== 'active' || (strtotime($item['end_time']) <= time())) {
        throw new Exception("This auction has ended. Bidding is closed.");
    }

    if ((int)$item['seller_id'] === $userId && !is_admin()) {
        throw new Exception("You cannot place a bid on your own auction item.");
    }

    $currentPrice = (float)($item['current_price'] > 0 ? $item['current_price'] : ($item['starting_price'] ?? 0));
    $minIncrement = (float)($item['min_increment'] > 0 ? $item['min_increment'] : 1.00);

    $bidCheck = $pdo->prepare("SELECT COUNT(*) AS c FROM bids WHERE item_id = :id");
    $bidCheck->execute([':id' => $itemId]);
    $hasBids = ((int)$bidCheck->fetch(PDO::FETCH_ASSOC)['c']) > 0;
    $nextMinBid = $hasBids ? ($currentPrice + $minIncrement) : $currentPrice;

    if ($bidAmount < $nextMinBid) {
        throw new Exception("Bid must be at least Rs. " . number_format($nextMinBid, 2));
    }

    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';

    // 2. Reset old winning bids
    $reset = $pdo->prepare("UPDATE bids SET is_winning = 0 WHERE item_id = :item_id");
    $reset->execute([':item_id' => $itemId]);

    // 3. Insert New Bid
    $ins = $pdo->prepare("
        INSERT INTO bids (item_id, user_id, bid_amount, is_winning, bid_status, ip_address, user_agent, created_at)
        VALUES (:item_id, :user_id, :bid_amount, 1, 'active', :ip, :ua, NOW())
    ");
    $ins->execute([
        ':item_id'    => $itemId,
        ':user_id'    => $userId,
        ':bid_amount' => $bidAmount,
        ':ip'         => $ip,
        ':ua'         => $ua
    ]);
    $newBidId = (int)$pdo->lastInsertId();

    // 4. Update Item
    $up = $pdo->prepare("
        UPDATE items 
        SET current_price = :bid, 
            bid_count = bid_count + 1, 
            winning_bid_id = :bid_id, 
            winner_id = :winner_id, 
            updated_at = NOW() 
        WHERE id = :id
    ");
    $up->execute([
        ':bid'       => $bidAmount,
        ':bid_id'    => $newBidId,
        ':winner_id' => $userId,
        ':id'        => $itemId
    ]);

    $pdo->commit();

    echo json_encode([
        'success'   => true,
        'message'   => 'Bid placed successfully!',
        'new_price' => $bidAmount
    ]);
} catch (\Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
exit();