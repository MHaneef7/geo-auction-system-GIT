<?php
/**
 * ============================================================================
 * File: place_bid.php
 * Path: /place_bid.php (Project Root)
 * Description: Real-Time AJAX Bidding Engine Controller
 * Architecture: Senior PHP Full-Stack Standards (PDO Transactions, Pessimistic Locking)
 * ============================================================================
 * 
 * Yeh controller live bidding system ka core engine hai.
 * 1. Pessimistic Locking (SELECT ... FOR UPDATE) se race conditions khatam karta hai.
 * 2. Strict server-side verification: Minimum increments, seller check, self-outbid check, auction expiry.
 * 3. Anti-sniping logic: Last 3 minutes me aane wali bid par auction time extend karta hai.
 * 4. Auto-bidding support: Proxy counter-bids automatically evaluate karta hai.
 * 5. Outbid notifications aur seller alerts real-time insert karta hai.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth_check.php';

// Only allow POST requests for placing bids
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response([
        'success' => false,
        'error'   => 'Method Not Allowed. POST is required.'
    ], 405);
}

// 1. Authentication Check (Must be logged in)
$user = require_auth();
$userId    = (int)$user['id'];
$userName  = trim($user['name'] ?? 'Bidder');
$userEmail = trim($user['email'] ?? '');

// 2. Parse & Sanitize Input
$itemId    = (int)($_POST['item_id'] ?? 0);
$bidAmount = (float)($_POST['bid_amount'] ?? 0);
$csrfToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;

// Validate basic input types
if ($itemId <= 0 || $bidAmount <= 0) {
    json_response([
        'success' => false,
        'error'   => 'Invalid auction lot or bid amount submitted.'
    ], 400);
}

// CSRF Protection (if token provided in header or form)
if ($csrfToken !== null && !verify_csrf_token($csrfToken)) {
    json_response([
        'success' => false,
        'error'   => 'Security validation failed (Invalid CSRF Token). Please refresh the page.'
    ], 403);
}

$pdo = getDB();

try {
    // 3. Begin Transaction with Strict Isolation
    $pdo->beginTransaction();

    // 4. Fetch Item with Row-Level Exclusive Lock (FOR UPDATE)
    $itemQuery = "
        SELECT 
            id, auction_code, title, starting_price, current_price, 
            reserve_price, min_increment, seller_id, status, 
            start_time, end_time, extended_time, is_deleted
        FROM items 
        WHERE id = :item_id 
        FOR UPDATE
    ";
    $itemStmt = $pdo->prepare($itemQuery);
    $itemStmt->execute([':item_id' => $itemId]);
    $item = $itemStmt->fetch();

    if (!$item) {
        throw new Exception("Auction item not found in catalog.");
    }

    if (!empty($item['is_deleted'])) {
        throw new Exception("This auction lot has been withdrawn by the seller.");
    }

    // 5. Verification: Seller cannot bid on own item
    if ((int)$item['seller_id'] === $userId) {
        throw new Exception("Sellers are strictly prohibited from bidding on their own listings.");
    }

    // 6. Verification: Auction Status & Expiry Check
    $now = time();
    $effectiveEndTime = (!empty($item['extended_time']) && $item['extended_time'] !== '0000-00-00 00:00:00' && strtotime($item['extended_time']) > strtotime($item['end_time']))
        ? $item['extended_time']
        : $item['end_time'];
    $endTimestamp = strtotime($effectiveEndTime);

    if ($item['status'] !== 'active' || $now >= $endTimestamp) {
        // Automatically mark as ended if time passed
        if ($now >= $endTimestamp && $item['status'] === 'active') {
            $closeStmt = $pdo->prepare("UPDATE items SET status = 'ended' WHERE id = :id");
            $closeStmt->execute([':id' => $itemId]);
        }
        throw new Exception("This auction has ended. Bidding is now closed for this lot.");
    }

    // 7. Verification: Check if user is already the highest bidder
    $leadBidStmt = $pdo->prepare("
        SELECT user_id, bid_amount 
        FROM bids 
        WHERE item_id = :item_id AND is_winning = 1 
        ORDER BY bid_amount DESC 
        LIMIT 1
    ");
    $leadBidStmt->execute([':item_id' => $itemId]);
    $leadBid = $leadBidStmt->fetch();

    if ($leadBid && (int)$leadBid['user_id'] === $userId) {
        throw new Exception("You are already the highest bidder on this item. No need to outbid yourself!");
    }

    // 8. Verification: Minimum Bid Amount Calculation
    $basePrice     = max((float)$item['current_price'], (float)$item['starting_price']);
    $minIncrement  = (float)($item['min_increment'] > 0 ? $item['min_increment'] : 100.00);
    $minimumAllowed = $basePrice + $minIncrement;

    if ($bidAmount < $minimumAllowed) {
        throw new Exception("Your bid must be at least Rs. " . number_format($minimumAllowed, 2));
    }

    // 9. Reset Previous Winning Flags for this Item
    $resetWinningStmt = $pdo->prepare("
        UPDATE bids 
        SET is_winning = 0, bid_status = 'outbid' 
        WHERE item_id = :item_id AND is_winning = 1
    ");
    $resetWinningStmt->execute([':item_id' => $itemId]);

    // 10. Insert New Bid
    $clientIp  = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);

    $insertBidStmt = $pdo->prepare("
        INSERT INTO bids (
            item_id, user_id, bidder_name, bidder_email, 
            bid_amount, bid_status, is_winning, is_auto_bid, 
            ip_address, user_agent, created_at, status
        ) VALUES (
            :item_id, :user_id, :bidder_name, :bidder_email, 
            :bid_amount, 'winning', 1, 0, 
            :ip_address, :user_agent, NOW(), 'active'
        )
    ");
    $insertBidStmt->execute([
        ':item_id'      => $itemId,
        ':user_id'      => $userId,
        ':bidder_name'  => $userName,
        ':bidder_email' => $userEmail,
        ':bid_amount'   => $bidAmount,
        ':ip_address'   => $clientIp,
        ':user_agent'   => $userAgent
    ]);
    $newBidId = (int)$pdo->lastInsertId();

    // 11. Anti-Sniping Protection Logic
    // Agar auction khatam hone me 3 minutes (180 secs) se kam waqt reh gaya ho,
    // to auction time me mazeed 3 minutes ka izafa kar dein.
    $secondsRemaining = $endTimestamp - $now;
    $timeExtended = false;
    $newEndDateTimeStr = $effectiveEndTime;

    if ($secondsRemaining <= 180 && $secondsRemaining > 0) {
        $extendStmt = $pdo->prepare("
            UPDATE items 
            SET 
                end_time = DATE_ADD(GREATEST(end_time, NOW()), INTERVAL 3 MINUTE),
                extended_time = DATE_ADD(GREATEST(end_time, NOW()), INTERVAL 3 MINUTE)
            WHERE id = :id
        ");
        $extendStmt->execute([':id' => $itemId]);
        $timeExtended = true;

        // Fetch new end time
        $timeFetchStmt = $pdo->prepare("SELECT end_time FROM items WHERE id = :id");
        $timeFetchStmt->execute([':id' => $itemId]);
        $newEndDateTimeStr = $timeFetchStmt->fetchColumn();
    }

    // 12. Update Item Current Price & Bid Count
    $updateItemStmt = $pdo->prepare("
        UPDATE items 
        SET 
            current_price  = :current_price,
            bid_count      = bid_count + 1,
            winner_id      = :winner_id,
            winning_bid_id = :winning_bid_id,
            updated_at     = NOW()
        WHERE id = :id
    ");
    $updateItemStmt->execute([
        ':current_price'  => $bidAmount,
        ':winner_id'      => $userId,
        ':winning_bid_id' => $newBidId,
        ':id'             => $itemId
    ]);

    // 13. Real-Time In-App Notifications
    // A. Notify Seller
    $sellerId = (int)$item['seller_id'];
    $itemTitle = $item['title'];
    $sellerNotifStmt = $pdo->prepare("
        INSERT INTO notifications (user_id, type, title, message, link, is_read, created_at)
        VALUES (:user_id, 'bid', 'New Bid Placed', :msg, :link, 0, NOW())
    ");
    $sellerNotifStmt->execute([
        ':user_id' => $sellerId,
        ':msg'     => "A new leading bid of Rs. " . number_format($bidAmount, 2) . " was placed on '{$itemTitle}'.",
        ':link'    => "live_auction.php?id={$itemId}"
    ]);

    // B. Notify Previous Outbid User (agar koi pehla bidder tha)
    if ($leadBid && (int)$leadBid['user_id'] !== $userId) {
        $outbidUserId = (int)$leadBid['user_id'];
        $outbidNotifStmt = $pdo->prepare("
            INSERT INTO notifications (user_id, type, title, message, link, is_read, created_at)
            VALUES (:user_id, 'outbid', 'You Have Been Outbid!', :msg, :link, 0, NOW())
        ");
        $outbidNotifStmt->execute([
            ':user_id' => $outbidUserId,
            ':msg'     => "Another bidder placed Rs. " . number_format($bidAmount, 2) . " on '{$itemTitle}'. Place a higher bid to win!",
            ':link'    => "live_auction.php?id={$itemId}"
        ]);
    }

    // 14. Evaluate Auto-Bidding (Proxy Bids)
    // Check if another user configured a higher max auto-bid
    $autoBidStmt = $pdo->prepare("
        SELECT id, user_id, max_bid_amount 
        FROM auto_bids 
        WHERE item_id = :item_id AND user_id != :current_user_id AND is_active = 1 
        ORDER BY max_bid_amount DESC 
        LIMIT 1
    ");
    $autoBidStmt->execute([
        ':item_id'         => $itemId,
        ':current_user_id' => $userId
    ]);
    $autoBidder = $autoBidStmt->fetch();

    $autoBidTriggered = false;
    $finalPrice = $bidAmount;

    if ($autoBidder && (float)$autoBidder['max_bid_amount'] >= ($bidAmount + $minIncrement)) {
        $abUserId = (int)$autoBidder['user_id'];
        $counterBidAmount = min((float)$autoBidder['max_bid_amount'], $bidAmount + $minIncrement);

        // Fetch auto-bidder user details
        $abUserStmt = $pdo->prepare("SELECT name, email FROM users WHERE id = :id");
        $abUserStmt->execute([':id' => $abUserId]);
        $abUser = $abUserStmt->fetch();

        if ($abUser) {
            // Outbid current manual bidder with automatic proxy bid
            $pdo->prepare("UPDATE bids SET is_winning = 0, bid_status = 'outbid' WHERE id = :id")->execute([':id' => $newBidId]);

            $insAutoBid = $pdo->prepare("
                INSERT INTO bids (
                    item_id, user_id, bidder_name, bidder_email, 
                    bid_amount, bid_status, is_winning, is_auto_bid, 
                    max_bid_amount, ip_address, user_agent, created_at, status
                ) VALUES (
                    :item_id, :user_id, :bidder_name, :bidder_email, 
                    :bid_amount, 'winning', 1, 1, 
                    :max_bid, 'system-autobid', 'AutoBidBot', NOW(), 'active'
                )
            ");
            $insAutoBid->execute([
                ':item_id'      => $itemId,
                ':user_id'      => $abUserId,
                ':bidder_name'  => $abUser['name'] . ' (Auto)',
                ':bidder_email' => $abUser['email'],
                ':bid_amount'   => $counterBidAmount,
                ':max_bid'      => $autoBidder['max_bid_amount']
            ]);
            $autoBidId = (int)$pdo->lastInsertId();

            // Update item price to auto-bid price
            $pdo->prepare("
                UPDATE items 
                SET current_price = :price, bid_count = bid_count + 1, winner_id = :winner_id, winning_bid_id = :bid_id 
                WHERE id = :id
            ")->execute([
                ':price'     => $counterBidAmount,
                ':winner_id' => $abUserId,
                ':bid_id'    => $autoBidId,
                ':id'        => $itemId
            ]);

            // Notify original bidder that they were immediately outbid by auto-bid
            $pdo->prepare("
                INSERT INTO notifications (user_id, type, title, message, link, is_read, created_at)
                VALUES (:user_id, 'outbid', 'Instant Counter-Bid Placed', :msg, :link, 0, NOW())
            ")->execute([
                ':user_id' => $userId,
                ':msg'     => "An automatic proxy bid of Rs. " . number_format($counterBidAmount, 2) . " outbid your offer on '{$itemTitle}'.",
                ':link'    => "live_auction.php?id={$itemId}"
            ]);

            $autoBidTriggered = true;
            $finalPrice = $counterBidAmount;
        }
    }

    // 15. Commit Transaction
    $pdo->commit();

    // Fetch updated total bid count
    $totalBidsStmt = $pdo->prepare("SELECT COUNT(*) FROM bids WHERE item_id = :item_id");
    $totalBidsStmt->execute([':item_id' => $itemId]);
    $totalBids = (int)$totalBidsStmt->fetchColumn();

    // 16. Return High-Quality JSON Response
    json_response([
        'success'           => true,
        'message'           => $autoBidTriggered 
                                ? "Your bid of Rs. " . number_format($bidAmount, 2) . " was placed, but an automated proxy bid outbid you!" 
                                : "Congratulations! Your bid of Rs. " . number_format($bidAmount, 2) . " has been placed successfully.",
        'bid_id'            => $newBidId,
        'item_id'           => $itemId,
        'bid_amount'        => $bidAmount,
        'current_price'     => $finalPrice,
        'formatted_price'   => 'Rs. ' . number_format($finalPrice, 2),
        'bid_count'         => $totalBids,
        'time_extended'     => $timeExtended,
        'new_end_time'      => $newEndDateTimeStr,
        'auto_bid_counter'  => $autoBidTriggered,
        'is_winning'        => !$autoBidTriggered
    ], 200);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    json_response([
        'success' => false,
        'error'   => $e->getMessage()
    ], 400);
}
