<?php
/**
 * ============================================================================
 * File: get_live_bid.php
 * Path: /get_live_bid.php (Project Root)
 * Description: Real-Time Auction State & Bidding Polling API
 * Architecture: Senior PHP Full-Stack Standards
 * ============================================================================
 * 
 * Yeh endpoint client-side JavaScript polling (Fetch API/AJAX) ke zariye
 * har 2-3 seconds baad call hota hai.
 * 1. Current Price, Highest Bidder Name, aur Total Bids return karta hai.
 * 2. Countdown Timer (seconds_remaining) live calculate karta hai.
 * 3. Automatic Auction Finalizer: Waqt khatam hote hi status 'ended' karke
 *    winner finalize karta hai aur notifications send karta hai.
 * 4. Winner Identification: Agar auction end ho chuki ho aur logged-in user
 *    winner ho, to `can_checkout: true` aur checkout URL provide karta hai.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth_check.php';

$itemId = (int)($_GET['item_id'] ?? $_POST['item_id'] ?? 0);

if ($itemId <= 0) {
    json_response([
        'success' => false,
        'error'   => 'Missing or invalid item ID.'
    ], 400);
}

$pdo = getDB();
$currentUserId = current_user_id();

try {
    // 1. Fetch Item Record
    $itemStmt = $pdo->prepare("
        SELECT 
            id, auction_code, title, starting_price, current_price, 
            reserve_price, min_increment, seller_id, winner_id, 
            winning_bid_id, status, start_time, end_time, extended_time, 
            bid_count, featured_image
        FROM items 
        WHERE id = :id 
        LIMIT 1
    ");
    $itemStmt->execute([':id' => $itemId]);
    $item = $itemStmt->fetch();

    if (!$item) {
        json_response([
            'success' => false,
            'error'   => 'Auction item not found.'
        ], 404);
    }

    $now = time();
    $effectiveEndTime = (!empty($item['extended_time']) && $item['extended_time'] !== '0000-00-00 00:00:00' && strtotime($item['extended_time']) > strtotime($item['end_time']))
        ? $item['extended_time']
        : $item['end_time'];
    $endTimestamp = strtotime($effectiveEndTime);
    $secondsRemaining = max(0, $endTimestamp - $now);

    // 2. Automatic Auction Closing & Winner Declaration Engine
    if ($now >= $endTimestamp && $item['status'] === 'active') {
        // Find highest bidder
        $topBidStmt = $pdo->prepare("
            SELECT id, user_id, bidder_name, bidder_email, bid_amount 
            FROM bids 
            WHERE item_id = :item_id 
            ORDER BY bid_amount DESC 
            LIMIT 1
        ");
        $topBidStmt->execute([':item_id' => $itemId]);
        $topBid = $topBidStmt->fetch();

        if ($topBid) {
            $winnerId     = (int)$topBid['user_id'];
            $winningBidId = (int)$topBid['id'];
            $winningPrice = (float)$topBid['bid_amount'];

            // Mark item as ended with winner details
            $closeStmt = $pdo->prepare("
                UPDATE items 
                SET status = 'ended', winner_id = :winner_id, winning_bid_id = :bid_id, updated_at = NOW() 
                WHERE id = :id
            ");
            $closeStmt->execute([
                ':winner_id' => $winnerId,
                ':bid_id'    => $winningBidId,
                ':id'        => $itemId
            ]);

            // Update bid statuses
            $pdo->prepare("UPDATE bids SET bid_status = 'won', is_winning = 1 WHERE id = :id")->execute([':id' => $winningBidId]);
            $pdo->prepare("UPDATE bids SET bid_status = 'lost', is_winning = 0 WHERE item_id = :item_id AND id != :winning_id")->execute([
                ':item_id'    => $itemId,
                ':winning_id' => $winningBidId
            ]);

            // Create notification for Winner
            $winMsg = "Congratulations! You won the auction for '" . $item['title'] . "' with a final bid of Rs. " . number_format($winningPrice, 2) . ". Click here to proceed to checkout.";
            $pdo->prepare("
                INSERT INTO notifications (user_id, type, title, message, link, is_read, created_at)
                VALUES (:user_id, 'won', 'You Won the Auction!', :msg, :link, 0, NOW())
            ")->execute([
                ':user_id' => $winnerId,
                ':msg'     => $winMsg,
                ':link'    => "checkout.php?item_id={$itemId}"
            ]);

            // Create notification for Seller
            $sellerMsg = "Your auction for '" . $item['title'] . "' has concluded successfully! Winning bid: Rs. " . number_format($winningPrice, 2) . ".";
            $pdo->prepare("
                INSERT INTO notifications (user_id, type, title, message, link, is_read, created_at)
                VALUES (:user_id, 'auction_ended', 'Auction Concluded', :msg, :link, 0, NOW())
            ")->execute([
                ':user_id' => (int)$item['seller_id'],
                ':msg'     => $sellerMsg,
                ':link'    => "live_auction.php?id={$itemId}"
            ]);

            // Update local in-memory representation
            $item['status']         = 'ended';
            $item['winner_id']      = $winnerId;
            $item['winning_bid_id'] = $winningBidId;
        } else {
            // No bids were placed; close item without winner
            $pdo->prepare("UPDATE items SET status = 'ended', updated_at = NOW() WHERE id = :id")->execute([':id' => $itemId]);
            $item['status'] = 'ended';
        }
    }

    // 3. Fetch Current Highest Bid & Bidder Details
    $currentLeadStmt = $pdo->prepare("
        SELECT b.id, b.user_id, b.bidder_name, b.bid_amount, b.created_at, u.avatar
        FROM bids b
        LEFT JOIN users u ON u.id = b.user_id
        WHERE b.item_id = :item_id AND b.is_winning = 1
        ORDER BY b.bid_amount DESC
        LIMIT 1
    ");
    $currentLeadStmt->execute([':item_id' => $itemId]);
    $leadBid = $currentLeadStmt->fetch();

    $highestPrice = $leadBid ? (float)$leadBid['bid_amount'] : max((float)$item['current_price'], (float)$item['starting_price']);
    $minIncrement = (float)($item['min_increment'] > 0 ? $item['min_increment'] : 100.00);
    $nextMinBid   = $highestPrice + $minIncrement;

    $isWinning = false;
    $highestBidderName = 'None (No bids yet)';
    $highestBidderAvatar = 'assets/image/other.png';

    if ($leadBid) {
        $highestBidderName = $leadBid['bidder_name'];
        if (!empty($leadBid['avatar'])) {
            $highestBidderAvatar = $leadBid['avatar'];
        }
        if ($currentUserId > 0 && (int)$leadBid['user_id'] === $currentUserId) {
            $isWinning = true;
        }
    }

    // 4. Checkout Access Determination
    // Winner verification: Sirf wahi checkout kar sakta hai jo officially auction jeeta ho
    $isWinner = false;
    $canCheckout = false;
    $isAlreadySold = ($item['status'] === 'sold');

    if ($item['status'] === 'ended' && $currentUserId > 0) {
        if ((int)$item['winner_id'] === $currentUserId || ($leadBid && (int)$leadBid['user_id'] === $currentUserId)) {
            $isWinner = true;
            $canCheckout = true;
        }
    }

    // Check if order already placed
    $orderCheckStmt = $pdo->prepare("SELECT id, order_number, payment_status FROM orders WHERE auction_id = :item_id LIMIT 1");
    $orderCheckStmt->execute([':item_id' => $itemId]);
    $existingOrder = $orderCheckStmt->fetch();

    if ($existingOrder && $existingOrder['payment_status'] === 'PAID') {
        $canCheckout   = false;
        $isAlreadySold = true;
    }

    // 5. Fetch Recent 5 Bids History
    $recentBidsStmt = $pdo->prepare("
        SELECT 
            b.id, b.user_id, b.bidder_name, b.bid_amount, b.is_winning, b.created_at, u.avatar
        FROM bids b
        LEFT JOIN users u ON u.id = b.user_id
        WHERE b.item_id = :item_id
        ORDER BY b.bid_amount DESC, b.id DESC
        LIMIT 5
    ");
    $recentBidsStmt->execute([':item_id' => $itemId]);
    $rawRecentBids = $recentBidsStmt->fetchAll();

    $formattedBids = [];
    foreach ($rawRecentBids as $rb) {
        $isMyOwnBid = ($currentUserId > 0 && (int)$rb['user_id'] === $currentUserId);
        $displayName = $rb['bidder_name'];
        if (!$isMyOwnBid && strlen($displayName) > 3) {
            // Mask non-user names slightly for privacy (e.g., Ah***ed)
            $displayName = substr($displayName, 0, 2) . '***' . substr($displayName, -1);
        }
        $formattedBids[] = [
            'id'              => (int)$rb['id'],
            'bidder_name'     => $displayName,
            'bid_amount'      => (float)$rb['bid_amount'],
            'formatted_amount'=> 'Rs. ' . number_format((float)$rb['bid_amount'], 2),
            'is_winning'      => (bool)$rb['is_winning'],
            'is_me'           => $isMyOwnBid,
            'avatar'          => !empty($rb['avatar']) ? $rb['avatar'] : 'assets/image/other.png',
            'time_ago'        => date('h:i:s A', strtotime($rb['created_at']))
        ];
    }

    // 6. Return Structured Real-Time State
    json_response([
        'success'               => true,
        'item_id'               => $itemId,
        'status'                => $item['status'],
        'is_ended'              => ($item['status'] === 'ended' || $item['status'] === 'sold' || $secondsRemaining <= 0),
        'is_sold'               => $isAlreadySold,
        'seconds_remaining'     => $secondsRemaining,
        'end_time_formatted'    => date('M d, Y h:i:s A', $endTimestamp),
        'current_price'         => $highestPrice,
        'formatted_price'       => 'Rs. ' . number_format($highestPrice, 2),
        'next_min_bid'          => $nextMinBid,
        'formatted_next_min'    => 'Rs. ' . number_format($nextMinBid, 2),
        'bid_count'             => (int)$item['bid_count'],
        'highest_bidder_name'   => $highestBidderName,
        'highest_bidder_avatar' => $highestBidderAvatar,
        'is_winning'            => $isWinning,
        'is_winner'             => $isWinner,
        'can_checkout'          => $canCheckout,
        'checkout_url'          => 'checkout.php?item_id=' . $itemId,
        'recent_bids'           => $formattedBids,
        'order'                 => $existingOrder ? [
            'order_number'   => $existingOrder['order_number'],
            'payment_status' => $existingOrder['payment_status']
        ] : null
    ]);

} catch (Exception $e) {
    error_log("get_live_bid.php error: " . $e->getMessage());
    json_response([
        'success' => false,
        'error'   => 'Error retrieving live auction status.'
    ], 500);
}
