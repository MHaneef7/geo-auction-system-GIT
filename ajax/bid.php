<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

include_once __DIR__ . '/../includes/database.php';
include_once  __DIR__ . '/../includes/auth.php';

// Must be logged in
if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'You must be logged in to place a bid.']);
    exit;
}

$item_id  = isset($_POST['item_id']) ? (int)$_POST['item_id'] : 0;
$bid_amount = isset($_POST['bid_amount']) ? (float)$_POST['bid_amount'] : 0;
$user_id  = (int)$_SESSION['user_id'];

if (!$item_id || $bid_amount <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid bid data.']);
    exit;
}

// Get item info
$item_res = mysqli_query($conn, "SELECT * FROM items WHERE id = $item_id AND status = 'active'");
if (!$item_res || mysqli_num_rows($item_res) === 0) {
    echo json_encode(['success' => false, 'message' => 'Auction not found or not active.']);
    exit;
}
$item = mysqli_fetch_assoc($item_res);

// Check end time
if ($item['end_time'] && strtotime($item['end_time']) < time()) {
    echo json_encode(['success' => false, 'message' => 'This auction has ended.']);
    exit;
}

// Cannot bid on your own item
if ($item['seller_id'] == $user_id) {
    echo json_encode(['success' => false, 'message' => 'You cannot bid on your own item.']);
    exit;
}

$current_price = (float)($item['current_price'] ?? $item['starting_price']);
$min_increment = (float)($item['min_increment'] ?? 1);
$min_bid       = $current_price + $min_increment;

if ($bid_amount < $min_bid) {
    echo json_encode(['success' => false, 'message' => 'Bid must be at least $' . number_format($min_bid, 2)]);
    exit;
}

// Get bidder name
$bidder_name = mysqli_real_escape_string($conn, $_SESSION['user_name'] ?? 'User');
$bid_esc     = mysqli_real_escape_string($conn, $bid_amount);

// Reset previous winning bids for this item
mysqli_query($conn, "UPDATE bids SET is_winning = 0 WHERE item_id = $item_id");

// Insert new bid
$insert = mysqli_query($conn, "INSERT INTO bids (item_id, user_id, bidder_name, amount, is_winning, created_at)
    VALUES ($item_id, $user_id, '$bidder_name', '$bid_esc', 1, NOW())");

if (!$insert) {
    echo json_encode(['success' => false, 'message' => 'Failed to place bid. Please try again.']);
    exit;
}

// Update item current price
mysqli_query($conn, "UPDATE items SET current_price = '$bid_esc' WHERE id = $item_id");

// Notify the seller
$seller_id = (int)$item['seller_id'];
$item_title = mysqli_real_escape_string($conn, $item['title']);
$msg_esc    = "A new bid of \$$bid_esc has been placed on your item: $item_title";
$title_esc  = "New Bid on Your Item";
mysqli_query($conn, "INSERT INTO notifications (user_id, type, title, message, is_read, created_at)
    VALUES ($seller_id, 'bid', '$title_esc', '$msg_esc', 0, NOW())");

// Notify previous highest bidder (if any)
$prev_res = mysqli_query($conn, "SELECT user_id FROM bids WHERE item_id = $item_id AND user_id != $user_id ORDER BY amount DESC LIMIT 1");
if ($prev_res && $prev_row = mysqli_fetch_assoc($prev_res)) {
    $prev_uid = (int)$prev_row['user_id'];
    $outbid_msg = "You have been outbid on: $item_title. New bid: \$$bid_esc";
    mysqli_query($conn, "INSERT INTO notifications (user_id, type, title, message, is_read, created_at)
        VALUES ($prev_uid, 'outbid', 'You Were Outbid', '$outbid_msg', 0, NOW())");
}

// Get updated bid count
$count_res = mysqli_query($conn, "SELECT COUNT(*) AS c FROM bids WHERE item_id = $item_id");
$bid_count = mysqli_fetch_assoc($count_res)['c'] ?? 0;

echo json_encode([
    'success'    => true,
    'message'    => 'Bid placed successfully!',
    'new_price'  => '$' . number_format($bid_amount, 2),
    'bid_count'  => $bid_count,
    'bid_amount' => $bid_amount,
]);
exit;
