<?php
session_start();
require_once __DIR__ . "/../includes/database.php";

// Set response header to JSON
header('Content-Type: application/json');

// 1. Check if user is logged in (Adjust session variable based on your auth system)
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please log in to place a bid.']);
    exit;
}

$user_id = intval($_SESSION['user_id']);
$item_id = isset($_POST['item_id']) ? intval($_POST['item_id']) : 0;
$bid_amount = isset($_POST['bid_amount']) ? floatval($_POST['bid_amount']) : 0;

if ($item_id <= 0 || $bid_amount <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid bid request parameters.']);
    exit;
}

try {
    // Start Transaction to maintain data integrity
    $conn->beginTransaction();

    // 2. Retrieve Item Details & Lock row for update
    $stmt = $conn->prepare("SELECT * FROM `items` WHERE `id` = ? FOR UPDATE");
    $stmt->execute([$item_id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$item) {
        $conn->rollBack();
        echo json_encode(['success' => false, 'message' => 'Auction item not found.']);
        exit;
    }

    // 3. Validate Auction Status and Expiration Time
    if ($item['status'] !== 'active' || strtotime($item['end_time']) <= time()) {
        $conn->rollBack();
        echo json_encode(['success' => false, 'message' => 'This auction has ended. Bidding is closed.']);
        exit;
    }

    // 4. Prevent user from bidding on their own item (optional check)
    if (intval($item['seller_id']) === $user_id) {
        $conn->rollBack();
        echo json_encode(['success' => false, 'message' => 'You cannot place a bid on your own auction item.']);
        exit;
    }

    // 5. Calculate Current Price and Minimum Next Bid Required
    $currentPrice = floatval($item['current_price'] > 0 ? $item['current_price'] : $item['starting_price']);
    $minIncrement = floatval($item['min_increment'] > 0 ? $item['min_increment'] : 1000);
    
    // Check if there are existing bids to evaluate minimum threshold
    $bidCheckStmt = $conn->prepare("SELECT COUNT(*) FROM `bids` WHERE `item_id` = ?");
    $bidCheckStmt->execute([$item_id]);
    $hasBids = $bidCheckStmt->fetchColumn() > 0;

    $nextMinBid = $hasBids ? ($currentPrice + $minIncrement) : $currentPrice;

    if ($bid_amount < $nextMinBid) {
        $conn->rollBack();
        echo json_encode([
            'success' => false, 
            'message' => 'Bid must be at least Rs. ' . number_format($nextMinBid)
        ]);
        exit;
    }

    // 6. Retrieve User Details for Denormalized Bid Logging (Name/Email)
    $userStmt = $conn->prepare("SELECT `name`, `email` FROM `users` WHERE `id` = ?");
    $userStmt->execute([$user_id]);
    $user = $userStmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        $conn->rollBack();
        echo json_encode(['success' => false, 'message' => 'User account not found.']);
        exit;
    }

    // 7. Update previous winning bids status to false
    $resetWinStmt = $conn->prepare("UPDATE `bids` SET `is_winning` = 0, `is_winning` = 0 WHERE `item_id` = ?");
    // Depending on your schema field conventions, mark old winning status false
    $resetWinStmt = $conn->prepare("UPDATE `bids` SET `is_winning` = 0 WHERE `item_id` = ?");
    $resetWinStmt->execute([$item_id]);

    // 8. Insert the New Bid Record
    $insertBid = $conn->prepare("
        INSERT INTO `bids` (
            `item_id`, `user_id`, `bidder_name`, `bidder_email`, 
            `bid_amount`, `is_winning`, `ip_address`, `user_agent`, `created_at`, `status`
        ) VALUES (?, ?, ?, ?, ?, 1, ?, ?, NOW(), 'active')
    ");
    
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

    $insertBid->execute([
        $item_id, 
        $user_id, 
        $user['name'], 
        $user['email'], 
        $bid_amount, 
        $ip_address, 
        $user_agent
    ]);
    
    $newBidId = $conn->lastInsertId();

    // 9. Update the Items Table (New current price, bid count, winning bid ID)
    $updateItem = $conn->prepare("
        UPDATE `items` 
        SET `current_price` = ?, 
            `bid_count` = `bid_count` + 1, 
            `winning_bid_id` = ?, 
            `winner_id` = ?,
            `updated_at` = NOW()
        WHERE `id` = ?
    ");
    $updateItem->execute([$bid_amount, $newBidId, $user_id, $item_id]);

    // Commit Transaction
    $conn->commit();

    echo json_encode([
        'success' => true, 
        'message' => 'Bid placed successfully!',
        'new_price' => $bid_amount
    ]);

} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo json_encode([
        'success' => false, 
        'message' => 'An error occurred while processing your bid. Please try again.'
    ]);
}