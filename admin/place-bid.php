<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please log in to place a bid.']);
    exit;
}

require_once __DIR__ . "/../includes/database.php";

$user_id = intval($_SESSION['user_id']);
$item_id = isset($_POST['item_id']) ? intval($_POST['item_id']) : 0;
$bid_amount = isset($_POST['bid_amount']) ? floatval($_POST['bid_amount']) : 0;

if ($item_id <= 0 || $bid_amount <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid bid request parameters.']);
    exit;
}

mysqli_begin_transaction($conn);

try {
    // 1. Retrieve Item Details & Lock row for update
    $stmt = mysqli_prepare($conn, "SELECT * FROM `items` WHERE `id` = ? FOR UPDATE");
    mysqli_stmt_bind_param($stmt, "i", $item_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $item = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);

    if (!$item) {
        throw new Exception("Auction item not found.");
    }

    if ($item['status'] !== 'active' || strtotime($item['end_time']) <= time()) {
        throw new Exception("This auction has ended. Bidding is closed.");
    }

    if (intval($item['seller_id']) === $user_id) {
        throw new Exception("You cannot place a bid on your own auction item.");
    }

    $currentPrice = floatval($item['current_price'] > 0 ? $item['current_price'] : $item['starting_price']);
    $minIncrement = floatval($item['min_increment'] > 0 ? $item['min_increment'] : 1.00);

    $bidCheck = mysqli_query($conn, "SELECT COUNT(*) AS c FROM `bids` WHERE `item_id` = $item_id");
    $hasBids = (mysqli_fetch_assoc($bidCheck)['c'] ?? 0) > 0;
    $nextMinBid = $hasBids ? ($currentPrice + $minIncrement) : $currentPrice;

    if ($bid_amount < $nextMinBid) {
        throw new Exception("Bid must be at least Rs. " . number_format($nextMinBid, 2));
    }

    // 2. Fetch User Details
    $uStmt = mysqli_prepare($conn, "SELECT name, email FROM users WHERE id = ?");
    mysqli_stmt_bind_param($uStmt, "i", $user_id);
    mysqli_stmt_execute($uStmt);
    $uRes = mysqli_stmt_get_result($uStmt);
    $user = mysqli_fetch_assoc($uRes);
    mysqli_stmt_close($uStmt);

    $userName = $user['name'] ?? 'Bidder';
    $userEmail = $user['email'] ?? '';
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';

    // 3. Reset old winning bids
    mysqli_query($conn, "UPDATE `bids` SET `is_winning` = 0 WHERE `item_id` = $item_id");

    // 4. Insert New Bid
    $ins = mysqli_prepare($conn, "INSERT INTO `bids` (`item_id`, `user_id`, `bidder_name`, `bidder_email`, `bid_amount`, `is_winning`, `ip_address`, `user_agent`, `created_at`, `status`) VALUES (?, ?, ?, ?, ?, 1, ?, ?, NOW(), 'active')");
    mysqli_stmt_bind_param($ins, "iissdss", $item_id, $user_id, $userName, $userEmail, $bid_amount, $ip, $ua);
    mysqli_stmt_execute($ins);
    $newBidId = mysqli_insert_id($conn);
    mysqli_stmt_close($ins);

    // 5. Update Item
    $up = mysqli_prepare($conn, "UPDATE `items` SET `current_price` = ?, `bid_count` = `bid_count` + 1, `winning_bid_id` = ?, `winner_id` = ?, `updated_at` = NOW() WHERE `id` = ?");
    mysqli_stmt_bind_param($up, "diii", $bid_amount, $newBidId, $user_id, $item_id);
    mysqli_stmt_execute($up);
    mysqli_stmt_close($up);

    mysqli_commit($conn);

    echo json_encode([
        'success' => true,
        'message' => 'Bid placed successfully!',
        'new_price' => $bid_amount
    ]);
} catch (Exception $e) {
    mysqli_rollback($conn);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
exit;