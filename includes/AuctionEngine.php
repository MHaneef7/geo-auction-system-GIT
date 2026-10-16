<?php
/**
 * Advanced Auction Mechanics & Settlement Engine
 * File: includes/AuctionEngine.php
 * 
 * Enterprise-grade auction concurrency, bidding validation, proxy auto-bidding,
 * anti-sniping soft-close protection, multi-format mechanics (English, Dutch, Vickrey),
 * and automated escrow settlement / fulfillment order creation.
 *
 * ACID Compliance & Row-Level Concurrency:
 * - Employs pessimistic locking (SELECT ... FOR UPDATE) on items, bids, and auto_bids.
 * - Prevents double-bidding, race conditions, negative balances, and ghost wins.
 */

declare(strict_types=1);

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/settings.php';
require_once __DIR__ . '/NotificationService.php';
require_once __DIR__ . '/EscrowService.php';

class AuctionEngine
{
    private PDO $pdo;
    private NotificationService $notifService;
    private EscrowService $escrowService;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getPdo();
        $this->notifService = new NotificationService($this->pdo);
        $this->escrowService = new EscrowService($this->pdo);
    }

    // =========================================================================
    // 1. CONCURRENCY-SAFE BID PLACEMENT (ENGLISH / STANDARD AUCTIONS)
    // =========================================================================

    /**
     * Atomically place a bid with pessimistic row locking, proxy auto-bid escalation,
     * anti-sniping dynamic extension, escrow hold/release, and notification dispatch.
     *
     * @param int    $itemId
     * @param int    $userId
     * @param float  $bidAmount
     * @param string $ipAddress
     * @param string $userAgent
     * @return array Result metadata
     * @throws InvalidArgumentException|RuntimeException
     */
    public function placeBid(
        int $itemId,
        int $userId,
        float $bidAmount,
        string $ipAddress = '127.0.0.1',
        string $userAgent = ''
    ): array {
        if ($itemId <= 0 || $userId <= 0) {
            throw new InvalidArgumentException("Invalid auction lot or user identity.");
        }

        // Validate numeric boundaries and decimal precision
        if (is_nan($bidAmount) || is_infinite($bidAmount) || $bidAmount <= 0) {
            throw new InvalidArgumentException("Bid amount must be a positive number greater than zero.");
        }

        $cleanBidStr = sprintf("%.2f", $bidAmount);
        $bidAmount = (float)$cleanBidStr;

        if ($bidAmount > 9999999999.99) {
            throw new InvalidArgumentException("Bid amount exceeds the platform upper limit.");
        }

        $this->pdo->beginTransaction();
        try {
            // 1. Lock Item Row (FOR UPDATE)
            $itemStmt = $this->pdo->prepare("
                SELECT id, auction_code, title, starting_price, reserve_price, 
                       current_price, min_increment, seller_id, status, 
                       auction_type, required_deposit_pct, is_sealed_bid,
                       start_time, end_time, extended_time, bid_count, approved, is_deleted
                FROM items 
                WHERE id = :id 
                FOR UPDATE
            ");
            $itemStmt->execute([':id' => $itemId]);
            $item = $itemStmt->fetch(PDO::FETCH_ASSOC);

            if (!$item) {
                throw new RuntimeException("Auction lot not found.");
            }

            // 2. Soft-delete check
            if (!empty($item['is_deleted'])) {
                throw new RuntimeException("This auction lot has been withdrawn or deleted by the seller.");
            }

            // 3. Approval status check
            if (isset($item['approved']) && (int)$item['approved'] === 0) {
                throw new RuntimeException("This auction lot is awaiting administrator approval.");
            }

            // 4. Dutch Auction routing
            if (($item['auction_type'] ?? 'english') === 'dutch') {
                throw new RuntimeException("This is a Dutch Auction lot with dropping prices. Please use instant Buy-Now.");
            }

            // 5. Anti-Shill Rule: Sellers cannot bid on their own listings
            if ((int)$item['seller_id'] === $userId) {
                throw new RuntimeException("Sellers are strictly prohibited from bidding on their own listings (Anti-Shill Bidding Rule).");
            }

            // 6. Timing Validations & Auto-Finalization on Expiry
            $now = time();
            $startTs = strtotime($item['start_time']);

            $effectiveEndTime = $item['end_time'];
            if (!empty($item['extended_time']) && $item['extended_time'] !== '0000-00-00 00:00:00') {
                if (strtotime($item['extended_time']) > strtotime($item['end_time'])) {
                    $effectiveEndTime = $item['extended_time'];
                }
            }
            $endTs = strtotime($effectiveEndTime);

            // Auction not yet started
            if ($now < $startTs) {
                throw new RuntimeException("Bidding has not yet opened for this lot. Opening time: " . date('Y-m-d H:i:s', $startTs));
            }

            // Auction expired or not active
            if ($item['status'] !== 'active' || $now >= $endTs) {
                // If still marked active but time passed, finalize on the fly
                if ($item['status'] === 'active' && $now >= $endTs) {
                    $this->finalizeAuctionLocked($itemId, $item);
                }
                throw new RuntimeException("This auction has ended. Bidding is now closed.");
            }

            // 7. Lock and inspect current highest bid
            $lastBidStmt = $this->pdo->prepare("
                SELECT id, user_id, bid_amount 
                FROM bids 
                WHERE item_id = :item_id 
                ORDER BY bid_amount DESC, id DESC 
                LIMIT 1 
                FOR UPDATE
            ");
            $lastBidStmt->execute([':item_id' => $itemId]);
            $lastBidder = $lastBidStmt->fetch(PDO::FETCH_ASSOC) ?: null;

            // Consecutive bid prevention (user cannot outbid themselves)
            if ($lastBidder && (int)$lastBidder['user_id'] === $userId) {
                throw new RuntimeException("You are already the highest bidder on this auction. There is no need to outbid yourself.");
            }

            // 8. Pricing & Minimum Increment Evaluation
            $currSymbol    = Settings::get('currency_symbol', 'Rs. ');
            $defaultInc    = (float)Settings::get('min_bid_increment', 100.0);
            $startingPrice = (float)($item['starting_price'] ?? 0.0);
            $currentPrice  = (float)($item['current_price'] ?? 0.0);
            $itemMinInc    = (float)($item['min_increment'] ?? 0.0);
            $minIncrement  = $itemMinInc > 0 ? $itemMinInc : $defaultInc;
            $bidCount      = (int)($item['bid_count'] ?? 0);

            if ($bidCount === 0 && !$lastBidder) {
                $minRequired = $startingPrice > 0 ? $startingPrice : $minIncrement;
            } else {
                $basePrice   = max($currentPrice, (float)($lastBidder['bid_amount'] ?? $startingPrice));
                $minRequired = round($basePrice + $minIncrement, 2);
            }

            // Tie-Bid Rejection
            if ($lastBidder && abs($bidAmount - (float)$lastBidder['bid_amount']) < 0.001) {
                throw new RuntimeException(
                    "A tie bid is not permitted. Your bid must exceed the current highest bid of " .
                    $currSymbol . number_format((float)$lastBidder['bid_amount'], 2) . " by at least " .
                    $currSymbol . number_format($minIncrement, 2)
                );
            }

            // Under-bid Rejection
            if ($bidAmount < $minRequired) {
                throw new RuntimeException(
                    "Bid amount is too low. The minimum acceptable bid is " .
                    $currSymbol . number_format($minRequired, 2)
                );
            }

            // 9. Escrow Pre-Authorization Hold (Anti-Ghosting Deposit)
            $depositPct = (float)($item['required_deposit_pct'] ?? 0.0);
            if ($depositPct > 0.0) {
                $this->escrowService->holdDepositForBid($userId, $itemId, $bidAmount, $depositPct);
            }

            // 10. Demote previous winning bids & release previous bidder's escrow hold
            $resetStmt = $this->pdo->prepare("
                UPDATE bids 
                SET is_winning = 0, 
                    bid_status = 'outbid' 
                WHERE item_id = :item_id AND is_winning = 1
            ");
            $resetStmt->execute([':item_id' => $itemId]);

            if ($lastBidder && (int)$lastBidder['user_id'] !== $userId) {
                $this->escrowService->releaseOutbidDeposit((int)$lastBidder['user_id'], $itemId);
            }

            // 11. Fetch bidder details
            $uStmt = $this->pdo->prepare("SELECT name, email FROM users WHERE id = :id");
            $uStmt->execute([':id' => $userId]);
            $uRow = $uStmt->fetch(PDO::FETCH_ASSOC);

            $bidderName  = $uRow['name'] ?? 'Bidder';
            $bidderEmail = $uRow['email'] ?? '';
            $uaClean     = mb_substr($userAgent, 0, 500);

            // 12. Insert the new winning bid
            $insStmt = $this->pdo->prepare("
                INSERT INTO bids (
                    item_id, user_id, bidder_name, bidder_email, bid_amount, 
                    bid_status, is_winning, is_auto_bid, ip_address, 
                    user_agent, created_at, status
                ) VALUES (
                    :item_id, :user_id, :bidder_name, :bidder_email, :bid_amount, 
                    'active', 1, 0, :ip, 
                    :ua, NOW(), 'active'
                )
            ");
            $insStmt->execute([
                ':item_id'      => $itemId,
                ':user_id'      => $userId,
                ':bidder_name'  => $bidderName,
                ':bidder_email' => $bidderEmail,
                ':bid_amount'   => $bidAmount,
                ':ip'           => $ipAddress,
                ':ua'           => $uaClean
            ]);
            $newBidId = (int)$this->pdo->lastInsertId();

            // 13. Popcorn Anti-Sniping Soft-Close (Dynamic Extension)
            $autoExtendEnabled = ((int)Settings::get('auto_extend_enabled', 1) === 1);
            $triggerSecs       = (int)Settings::get('auto_extend_trigger_seconds', 180);
            $extendMins        = (int)Settings::get('auto_extend_minutes', 3);

            $remainingSecs = $endTs - $now;
            $extended = false;

            if ($autoExtendEnabled && $remainingSecs <= $triggerSecs && $remainingSecs > 0) {
                $extStmt = $this->pdo->prepare("
                    UPDATE items 
                    SET end_time = DATE_ADD(GREATEST(end_time, NOW()), INTERVAL :mins MINUTE),
                        extended_time = DATE_ADD(GREATEST(COALESCE(extended_time, end_time), NOW()), INTERVAL :mins2 MINUTE)
                    WHERE id = :id
                ");
                $extStmt->execute([
                    ':mins'  => $extendMins,
                    ':mins2' => $extendMins,
                    ':id'    => $itemId
                ]);
                $extended = true;
            }

            // 14. Synchronize Item Pricing & Metrics
            $upItemStmt = $this->pdo->prepare("
                UPDATE items 
                SET current_price = :bid, 
                    bid_count = bid_count + 1, 
                    winning_bid_id = :bid_id, 
                    winner_id = :winner_id, 
                    updated_at = NOW() 
                WHERE id = :id
            ");
            $upItemStmt->execute([
                ':bid'       => $bidAmount,
                ':bid_id'    => $newBidId,
                ':winner_id' => $userId,
                ':id'        => $itemId
            ]);

            // 15. Competitive Multi-Agent Proxy Auto-Bid Engine
            $autoBidResult = $this->executeProxyAutoBids($itemId, $bidAmount, $userId, $minIncrement);
            $finalPrice    = $bidAmount;
            $finalWinnerId = $userId;
            $finalBidId    = $newBidId;

            if ($autoBidResult && !empty($autoBidResult['counter_bid'])) {
                $finalPrice    = (float)$autoBidResult['counter_bid'];
                $finalWinnerId = (int)$autoBidResult['winner_id'];
                $finalBidId    = (int)$autoBidResult['bid_id'];
            }

            // Commit atomic transaction
            $this->pdo->commit();

            // 16. Post-Commit Asynchronous Notifications
            $this->dispatchBidNotifications(
                $item,
                $itemId,
                $userId,
                $bidderName,
                $bidAmount,
                $lastBidder,
                $autoBidResult
            );

            // Calculate next minimum bid
            $nextMinBid = round($finalPrice + $minIncrement, 2);
            $reservePrice = (float)($item['reserve_price'] ?? 0.0);
            $reserveMet   = ($reservePrice <= 0.0 || $finalPrice >= $reservePrice);

            return [
                'success'         => true,
                'message'         => $this->buildBidSuccessMessage($finalPrice, $extended, $extendMins, $autoBidResult, $currSymbol),
                'bid_id'          => $finalBidId,
                'bid_amount'      => $finalPrice,
                'new_price'       => $currSymbol . number_format($finalPrice, 2),
                'formatted_price' => $currSymbol . number_format($finalPrice, 2),
                'next_min_bid'    => $currSymbol . number_format($nextMinBid, 2),
                'raw_next_min'    => $nextMinBid,
                'bid_count'       => $bidCount + 1 + ($autoBidResult['bids_added'] ?? 0),
                'reserve_met'     => $reserveMet,
                'extended'        => $extended,
                'autobid_counter' => !empty($autoBidResult['counter_bid']),
                'winner_id'       => $finalWinnerId
            ];

        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Executes competitive proxy auto-bidding between registered autobidders.
     * Must be called while holding transaction lock on items row.
     */
    private function executeProxyAutoBids(int $itemId, float $currentPrice, int $excludeUserId, float $minIncrement): ?array
    {
        $bidsAdded = 0;
        $counterBidPlaced = false;
        $lastAutoBid = null;

        while (true) {
            // Find highest active auto-bid that outbids currentPrice + minIncrement
            $stmt = $this->pdo->prepare("
                SELECT ab.id, ab.user_id, ab.max_bid_amount, u.name AS bidder_name, u.email AS bidder_email
                FROM auto_bids ab
                JOIN users u ON ab.user_id = u.id
                JOIN items i ON ab.item_id = i.id
                WHERE ab.item_id = :item_id 
                  AND ab.is_active = 1 
                  AND ab.user_id != :exclude_uid 
                  AND ab.user_id != i.seller_id
                ORDER BY ab.max_bid_amount DESC, ab.created_at ASC
                LIMIT 1
                FOR UPDATE
            ");
            $stmt->execute([
                ':item_id'     => $itemId,
                ':exclude_uid' => $excludeUserId
            ]);
            $auto = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$auto) {
                break;
            }

            $nextBid = round($currentPrice + $minIncrement, 2);
            $maxBid  = (float)$auto['max_bid_amount'];

            if ($nextBid <= $maxBid) {
                // Demote existing winning bids
                $this->pdo->prepare("UPDATE bids SET is_winning = 0, bid_status = 'outbid' WHERE item_id = :id AND is_winning = 1")
                    ->execute([':id' => $itemId]);

                // Insert proxy auto-bid
                $autoName  = trim($auto['bidder_name'] ?? 'Bidbot') . ' (AutoBid)';
                $autoEmail = $auto['bidder_email'] ?? '';

                $insAuto = $this->pdo->prepare("
                    INSERT INTO bids (
                        item_id, user_id, bidder_name, bidder_email, bid_amount, 
                        bid_status, is_winning, is_auto_bid, max_bid_amount, 
                        ip_address, user_agent, created_at, status
                    ) VALUES (
                        :item_id, :user_id, :name, :email, :amount, 
                        'active', 1, 1, :max_bid, 
                        '127.0.0.1', 'System/AutoBidEngine', NOW(), 'active'
                    )
                ");
                $insAuto->execute([
                    ':item_id'  => $itemId,
                    ':user_id'  => (int)$auto['user_id'],
                    ':name'     => $autoName,
                    ':email'    => $autoEmail,
                    ':amount'   => $nextBid,
                    ':max_bid'  => $maxBid
                ]);
                $autoBidId = (int)$this->pdo->lastInsertId();

                // Update item price and leader
                $this->pdo->prepare("
                    UPDATE items 
                    SET current_price = :bid, 
                        bid_count = bid_count + 1, 
                        winning_bid_id = :bid_id, 
                        winner_id = :winner_id, 
                        updated_at = NOW() 
                    WHERE id = :id
                ")->execute([
                    ':bid'       => $nextBid,
                    ':bid_id'    => $autoBidId,
                    ':winner_id' => (int)$auto['user_id'],
                    ':id'        => $itemId
                ]);

                $currentPrice     = $nextBid;
                $excludeUserId    = (int)$auto['user_id'];
                $bidsAdded++;
                $counterBidPlaced = true;
                $lastAutoBid      = [
                    'counter_bid' => $nextBid,
                    'winner_id'   => (int)$auto['user_id'],
                    'bid_id'      => $autoBidId,
                    'bids_added'  => $bidsAdded
                ];
            } else {
                // Max bid exceeded, deactivate auto-bid
                $this->pdo->prepare("UPDATE auto_bids SET is_active = 0 WHERE id = :id")
                    ->execute([':id' => (int)$auto['id']]);
            }
        }

        return $lastAutoBid;
    }

    /**
     * Dispatch post-commit notifications safely without blocking response
     */
    private function dispatchBidNotifications(
        array $item,
        int $itemId,
        int $userId,
        string $bidderName,
        float $bidAmount,
        ?array $lastBidder,
        ?array $autoBidResult
    ): void {
        try {
            $sellerId  = (int)($item['seller_id'] ?? 0);
            $itemTitle = $item['title'] ?? ('Lot #' . $itemId);

            if ($sellerId > 0 && $sellerId !== $userId) {
                $this->notifService->notifyBidPlaced($sellerId, $bidderName, $itemTitle, $bidAmount, $itemId);
            }

            if ($lastBidder && !empty($lastBidder['user_id'])) {
                $prevUid = (int)$lastBidder['user_id'];
                if ($prevUid > 0 && $prevUid !== $userId) {
                    $this->notifService->notifyOutbid($prevUid, $itemTitle, $bidAmount, $itemId);
                }
            }

            if ($autoBidResult && !empty($autoBidResult['counter_bid'])) {
                $this->notifService->notifyOutbid($userId, $itemTitle, (float)$autoBidResult['counter_bid'], $itemId);
            }
        } catch (\Throwable $e) {
            error_log("AuctionEngine Notification dispatch error: " . $e->getMessage());
        }
    }

    private function buildBidSuccessMessage(
        float $finalPrice,
        bool $extended,
        int $extendMins,
        ?array $autoBidResult,
        string $currSymbol
    ): string {
        $msg = 'Bid placed successfully!';
        if ($extended) {
            $msg .= " (Anti-Sniping: Auction extended by {$extendMins} minutes).";
        }
        if ($autoBidResult && !empty($autoBidResult['counter_bid'])) {
            $msg .= " An automated proxy counter-bid instantly responded at " . $currSymbol . number_format((float)$autoBidResult['counter_bid'], 2) . ".";
        }
        return $msg;
    }

    // =========================================================================
    // 2. CONCURRENCY-SAFE AUCTION FINALIZATION & SETTLEMENT
    // =========================================================================

    /**
     * Public entry to finalize an auction with lock acquisition
     */
    public function finalizeAuction(int $itemId): array
    {
        if ($itemId <= 0) {
            throw new InvalidArgumentException("Invalid item ID for finalization.");
        }

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("
                SELECT id, auction_code, title, starting_price, reserve_price, 
                       current_price, seller_id, status, auction_type, 
                       start_time, end_time, extended_time, winner_id, winning_bid_id
                FROM items 
                WHERE id = :id 
                FOR UPDATE
            ");
            $stmt->execute([':id' => $itemId]);
            $item = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$item) {
                throw new RuntimeException("Auction lot not found.");
            }

            $res = $this->finalizeAuctionLocked($itemId, $item);
            $this->pdo->commit();
            return $res;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Finalize an auction when item row is already locked FOR UPDATE
     */
    private function finalizeAuctionLocked(int $itemId, array $item): array
    {
        // If already resolved, return current status
        if (in_array($item['status'], ['sold', 'ended', 'cancelled'], true)) {
            return [
                'status'    => $item['status'],
                'winner_id' => $item['winner_id'] ? (int)$item['winner_id'] : null,
                'finalized' => false,
                'message'   => 'Auction is already finalized.'
            ];
        }

        // Special handling for Vickrey Auctions
        if (($item['auction_type'] ?? 'english') === 'vickrey') {
            return $this->resolveVickreyAuctionLocked($itemId, $item);
        }

        // Query highest active bid
        $bStmt = $this->pdo->prepare("
            SELECT id, user_id, bidder_name, bidder_email, bid_amount 
            FROM bids 
            WHERE item_id = :id AND bid_status IN ('active', 'winning')
            ORDER BY bid_amount DESC, id ASC 
            LIMIT 1 
            FOR UPDATE
        ");
        $bStmt->execute([':id' => $itemId]);
        $topBid = $bStmt->fetch(PDO::FETCH_ASSOC);

        $reservePrice = (float)($item['reserve_price'] ?? 0.0);
        $sellerId     = (int)$item['seller_id'];
        $itemTitle    = $item['title'] ?? ('Lot #' . $itemId);

        if ($topBid) {
            $topBidAmount = (float)$topBid['bid_amount'];
            $winnerId     = (int)$topBid['user_id'];
            $topBidId     = (int)$topBid['id'];

            if ($reservePrice <= 0.0 || $topBidAmount >= $reservePrice) {
                // RESERVE MET: Mark as SOLD
                $this->pdo->prepare("
                    UPDATE items 
                    SET status = 'sold', 
                        winner_id = :winner_id, 
                        winning_bid_id = :bid_id, 
                        current_price = :price, 
                        updated_at = NOW() 
                    WHERE id = :id
                ")->execute([
                    ':winner_id' => $winnerId,
                    ':bid_id'    => $topBidId,
                    ':price'     => $topBidAmount,
                    ':id'        => $itemId
                ]);

                // Update bids status
                $this->pdo->prepare("
                    UPDATE bids 
                    SET is_winning = IF(id = :winner_bid_id, 1, 0),
                        bid_status = IF(id = :winner_bid_id2, 'won', 'outbid')
                    WHERE item_id = :id
                ")->execute([
                    ':winner_bid_id'  => $topBidId,
                    ':winner_bid_id2' => $topBidId,
                    ':id'             => $itemId
                ]);

                // Capture winning escrow deposit
                $this->escrowService->settleWinningDeposit($winnerId, $itemId);

                // Create fulfillment Order record if not already created
                $orderNumber = $this->createOrderRecordIfNotExists($itemId, $winnerId, $sellerId, $topBidAmount);

                // Send winner & seller notifications
                try {
                    $this->notifService->notifyAuctionWon($winnerId, $itemTitle, $topBidAmount, $itemId);
                    $this->notifService->createNotification(
                        $sellerId,
                        'auction_sold',
                        'Auction Lot Sold!',
                        "Your lot '{$itemTitle}' was successfully sold for Rs. " . number_format($topBidAmount, 2) . "!",
                        "invoice.php?item_id={$itemId}",
                        'fa-solid fa-coins'
                    );
                } catch (\Throwable $ne) {
                    error_log("Finalize notification error: " . $ne->getMessage());
                }

                return [
                    'status'       => 'sold',
                    'winner_id'    => $winnerId,
                    'winning_bid'  => $topBidAmount,
                    'order_number' => $orderNumber,
                    'finalized'    => true
                ];

            } else {
                // RESERVE NOT MET: Mark as ENDED (Unsold)
                $this->pdo->prepare("
                    UPDATE items 
                    SET status = 'ended', 
                        updated_at = NOW() 
                    WHERE id = :id
                ")->execute([':id' => $itemId]);

                // Release top bidder's escrow deposit
                $this->escrowService->releaseOutbidDeposit($winnerId, $itemId);

                // Notify seller and bidder
                try {
                    $this->notifService->createNotification(
                        $sellerId,
                        'auction_ended',
                        'Auction Ended - Reserve Not Met',
                        "Your lot '{$itemTitle}' ended. The highest bid of Rs. " . number_format($topBidAmount, 2) . " did not reach your reserve price.",
                        "live_auction.php?id={$itemId}",
                        'fa-solid fa-flag-checkered'
                    );
                    $this->notifService->createNotification(
                        $winnerId,
                        'auction_ended',
                        'Auction Ended - Reserve Not Met',
                        "The auction for '{$itemTitle}' has closed without meeting the seller's confidential reserve price. Your escrow hold has been released.",
                        "live_auction.php?id={$itemId}",
                        'fa-solid fa-circle-info'
                    );
                } catch (\Throwable $ne) {
                    error_log("Reserve notification error: " . $ne->getMessage());
                }

                return [
                    'status'       => 'ended',
                    'reserve_met'  => false,
                    'winner_id'    => null,
                    'finalized'    => true
                ];
            }

        } else {
            // NO BIDS PLACED: Mark as ENDED
            $this->pdo->prepare("
                UPDATE items 
                SET status = 'ended', 
                    updated_at = NOW() 
                WHERE id = :id
            ")->execute([':id' => $itemId]);

            try {
                $this->notifService->createNotification(
                    $sellerId,
                    'auction_ended',
                    'Auction Ended Without Bids',
                    "Your lot '{$itemTitle}' closed with no bids placed.",
                    "live_auction.php?id={$itemId}",
                    'fa-solid fa-flag-checkered'
                );
            } catch (\Throwable $ne) {
                error_log("No bid notification error: " . $ne->getMessage());
            }

            return [
                'status'    => 'ended',
                'no_bids'   => true,
                'winner_id' => null,
                'finalized' => true
            ];
        }
    }

    /**
     * Automatically create an order record upon auction win if not already present
     */
    private function createOrderRecordIfNotExists(int $itemId, int $buyerId, int $sellerId, float $hammerPrice): string
    {
        $chk = $this->pdo->prepare("SELECT order_number FROM orders WHERE auction_id = :id AND buyer_id = :bid LIMIT 1");
        $chk->execute([':id' => $itemId, ':bid' => $buyerId]);
        $existing = $chk->fetchColumn();

        if ($existing) {
            return (string)$existing;
        }

        $orderNumber = 'ORD-' . strtoupper(substr(uniqid(), -8));
        $ins = $this->pdo->prepare("
            INSERT INTO orders (
                order_number, auction_id, buyer_id, seller_id, 
                hammer_price, platform_fee, shipping_fee, total_amount, 
                payment_status, shipping_status, created_at
            ) VALUES (
                :order_num, :item_id, :buyer_id, :seller_id, 
                :hammer, 0.00, 0.00, :total, 
                'PAYMENT_PENDING', 'PROCESSING', NOW()
            )
        ");
        $ins->execute([
            ':order_num' => $orderNumber,
            ':item_id'   => $itemId,
            ':buyer_id'  => $buyerId,
            ':seller_id' => $sellerId,
            ':hammer'    => $hammerPrice,
            ':total'     => $hammerPrice
        ]);

        return $orderNumber;
    }

    /**
     * Batch sweeper for expired active auctions
     */
    public function settleExpiredAuctions(int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        $stmt = $this->pdo->prepare("
            SELECT id 
            FROM items 
            WHERE status = 'active' 
              AND (
                  (extended_time IS NOT NULL AND extended_time != '0000-00-00 00:00:00' AND extended_time <= NOW())
                  OR ((extended_time IS NULL OR extended_time = '0000-00-00 00:00:00') AND end_time <= NOW())
              )
            LIMIT :lim
        ");
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $expiredIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $settled = [];
        foreach ($expiredIds as $id) {
            try {
                $settled[(int)$id] = $this->finalizeAuction((int)$id);
            } catch (\Throwable $e) {
                $settled[(int)$id] = ['error' => $e->getMessage()];
            }
        }

        return [
            'total_found' => count($expiredIds),
            'settled'     => $settled
        ];
    }

    // =========================================================================
    // 3. DUTCH AUCTION MECHANICS
    // =========================================================================

    public function getDutchCurrentPrice(array $item): array
    {
        $startPrice = (float)($item['starting_price'] ?? 0.0);
        $floorPrice = (float)($item['dutch_floor_price'] ?? ($item['reserve_price'] ?? 0.0));
        $decrement  = (float)($item['dutch_decrement_amount'] ?? 100.0);
        $interval   = (int)($item['dutch_decrement_interval'] ?? 30);

        if ($interval <= 0) $interval = 30;
        if ($decrement <= 0) $decrement = 100.0;

        $startTime = strtotime($item['start_time'] ?? 'now');
        $now       = time();

        if ($now < $startTime) {
            return [
                'current_price'    => $startPrice,
                'is_started'       => false,
                'seconds_to_start' => $startTime - $now,
                'next_drop_in'     => 0,
                'floor_price'      => $floorPrice,
                'drops_count'      => 0
            ];
        }

        $elapsedSeconds = $now - $startTime;
        $intervalsCount = (int)floor($elapsedSeconds / $interval);
        $totalDrop      = $intervalsCount * $decrement;

        $computedPrice = max($floorPrice, $startPrice - $totalDrop);
        $secondsIntoCurrentInterval = $elapsedSeconds % $interval;
        $secondsToNextDrop = $computedPrice > $floorPrice ? ($interval - $secondsIntoCurrentInterval) : 0;

        return [
            'current_price'    => round($computedPrice, 2),
            'is_started'       => true,
            'is_floor_reached' => ($computedPrice <= $floorPrice),
            'floor_price'      => $floorPrice,
            'next_drop_in'     => $secondsToNextDrop,
            'drops_count'      => $intervalsCount,
            'decrement_amount' => $decrement,
            'interval_seconds' => $interval
        ];
    }

    public function executeDutchBuyNow(int $itemId, int $userId): array
    {
        if ($itemId <= 0 || $userId <= 0) {
            throw new InvalidArgumentException("Invalid item ID or user ID.");
        }

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("
                SELECT id, title, seller_id, starting_price, reserve_price, 
                       dutch_floor_price, dutch_decrement_amount, dutch_decrement_interval, 
                       status, auction_type, start_time, end_time 
                FROM items 
                WHERE id = :id 
                FOR UPDATE
            ");
            $stmt->execute([':id' => $itemId]);
            $item = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$item) {
                throw new RuntimeException("Auction item not found.");
            }

            if (($item['auction_type'] ?? 'english') !== 'dutch') {
                throw new RuntimeException("This auction lot is not configured as a Dutch auction.");
            }

            if ($item['status'] !== 'active') {
                throw new RuntimeException("This Dutch auction is no longer active (Status: {$item['status']}).");
            }

            if ((int)$item['seller_id'] === $userId) {
                throw new RuntimeException("Sellers cannot purchase their own Dutch auction listing.");
            }

            $priceData = $this->getDutchCurrentPrice($item);
            if (!$priceData['is_started']) {
                throw new RuntimeException("This Dutch auction has not yet commenced.");
            }

            $finalPrice = (float)$priceData['current_price'];

            $uStmt = $this->pdo->prepare("SELECT id, name, email FROM users WHERE id = :id");
            $uStmt->execute([':id' => $userId]);
            $buyer = $uStmt->fetch(PDO::FETCH_ASSOC);

            if (!$buyer) {
                throw new RuntimeException("Buyer account not found.");
            }

            $insBid = $this->pdo->prepare("
                INSERT INTO bids (
                    item_id, user_id, bidder_name, bidder_email, bid_amount, 
                    bid_status, is_winning, is_auto_bid, ip_address, created_at, status
                ) VALUES (
                    :item_id, :user_id, :name, :email, :amount, 
                    'active', 1, 0, :ip, NOW(), 'active'
                )
            ");
            $insBid->execute([
                ':item_id' => $itemId,
                ':user_id' => $userId,
                ':name'    => $buyer['name'] ?? 'Buyer',
                ':email'   => $buyer['email'] ?? '',
                ':amount'  => $finalPrice,
                ':ip'      => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
            ]);
            $bidId = (int)$this->pdo->lastInsertId();

            $upItem = $this->pdo->prepare("
                UPDATE items 
                SET current_price = :price, 
                    winning_bid_id = :bid_id, 
                    winner_id = :winner_id, 
                    status = 'sold', 
                    updated_at = NOW() 
                WHERE id = :id
            ");
            $upItem->execute([
                ':price'     => $finalPrice,
                ':bid_id'    => $bidId,
                ':winner_id' => $userId,
                ':id'        => $itemId
            ]);

            // Create order record
            $orderNumber = $this->createOrderRecordIfNotExists($itemId, $userId, (int)$item['seller_id'], $finalPrice);

            $this->pdo->commit();

            try {
                $sellerId = (int)$item['seller_id'];
                $this->notifService->createNotification(
                    $sellerId,
                    'auction_sold',
                    'Dutch Auction Lot Sold!',
                    "Your Dutch auction lot '{$item['title']}' was purchased for Rs. " . number_format($finalPrice, 2) . ".",
                    "invoice.php?item_id={$itemId}",
                    'fa-solid fa-coins'
                );
                $this->notifService->createNotification(
                    $userId,
                    'auction_won',
                    'Congratulations! You Won the Dutch Auction',
                    "You successfully claimed '{$item['title']}' at the current drop price of Rs. " . number_format($finalPrice, 2) . ".",
                    "invoice.php?item_id={$itemId}",
                    'fa-solid fa-trophy'
                );
            } catch (\Throwable $e) {
                error_log("Dutch buy notification error: " . $e->getMessage());
            }

            return [
                'success'      => true,
                'final_price'  => $finalPrice,
                'item_id'      => $itemId,
                'bid_id'       => $bidId,
                'winner_id'    => $userId,
                'order_number' => $orderNumber
            ];

        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    // =========================================================================
    // 4. VICKREY AUCTION MECHANICS (SECOND-PRICE SEALED-BID)
    // =========================================================================

    public function resolveVickreyAuction(int $itemId): array
    {
        return $this->finalizeAuction($itemId);
    }

    private function resolveVickreyAuctionLocked(int $itemId, array $item): array
    {
        $bidsStmt = $this->pdo->prepare("
            SELECT id, user_id, bidder_name, bidder_email, bid_amount 
            FROM bids 
            WHERE item_id = :id AND bid_status IN ('active', 'winning')
            ORDER BY bid_amount DESC, id ASC 
            LIMIT 2
            FOR UPDATE
        ");
        $bidsStmt->execute([':id' => $itemId]);
        $topBids = $bidsStmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($topBids)) {
            $this->pdo->prepare("UPDATE items SET status = 'ended', updated_at = NOW() WHERE id = :id")->execute([':id' => $itemId]);
            return ['status' => 'ended', 'no_bids' => true, 'clearing_price' => 0.0, 'winner_id' => null];
        }

        $highestBid       = $topBids[0];
        $secondHighestBid = $topBids[1] ?? null;

        $winnerId      = (int)$highestBid['user_id'];
        $winnerMaxBid  = (float)$highestBid['bid_amount'];
        $startingPrice = (float)($item['starting_price'] ?? 0.0);

        // Vickrey Rule: Winner pays 2nd highest price, or starting price if only 1 bid
        $clearingPrice = $secondHighestBid ? (float)$secondHighestBid['bid_amount'] : $startingPrice;

        $reservePrice = (float)($item['reserve_price'] ?? 0.0);
        if ($reservePrice > 0 && $winnerMaxBid < $reservePrice) {
            $this->pdo->prepare("UPDATE items SET status = 'ended', updated_at = NOW() WHERE id = :id")->execute([':id' => $itemId]);
            $this->escrowService->releaseOutbidDeposit($winnerId, $itemId);
            return [
                'status'         => 'ended',
                'reserve_met'    => false,
                'clearing_price' => $clearingPrice,
                'winner_id'      => null
            ];
        }

        if ($reservePrice > 0 && $clearingPrice < $reservePrice && $winnerMaxBid >= $reservePrice) {
            $clearingPrice = $reservePrice;
        }

        $this->pdo->prepare("
            UPDATE items 
            SET current_price = :clearing_price,
                winning_bid_id = :bid_id,
                winner_id = :winner_id,
                status = 'sold',
                updated_at = NOW()
            WHERE id = :id
        ")->execute([
            ':clearing_price' => $clearingPrice,
            ':bid_id'         => (int)$highestBid['id'],
            ':winner_id'      => $winnerId,
            ':id'             => $itemId
        ]);

        $this->pdo->prepare("
            UPDATE bids 
            SET is_winning = IF(id = :winner_bid_id, 1, 0),
                bid_status = IF(id = :winner_bid_id2, 'won', 'outbid')
            WHERE item_id = :id
        ")->execute([
            ':winner_bid_id'  => (int)$highestBid['id'],
            ':winner_bid_id2' => (int)$highestBid['id'],
            ':id'             => $itemId
        ]);

        $this->escrowService->settleWinningDeposit($winnerId, $itemId);
        $orderNumber = $this->createOrderRecordIfNotExists($itemId, $winnerId, (int)$item['seller_id'], $clearingPrice);

        try {
            $itemTitle = $item['title'] ?? ('Lot #' . $itemId);
            $this->notifService->notifyAuctionWon($winnerId, $itemTitle, $clearingPrice, $itemId);
        } catch (\Throwable $ne) {
            error_log("Vickrey notification error: " . $ne->getMessage());
        }

        return [
            'status'            => 'sold',
            'winner_id'         => $winnerId,
            'clearing_price'    => $clearingPrice,
            'submitted_max_bid' => $winnerMaxBid,
            'second_bid'        => $secondHighestBid ? (float)$secondHighestBid['bid_amount'] : null,
            'order_number'      => $orderNumber
        ];
    }

    // =========================================================================
    // 5. POPCORN ANTI-SNIPING HELPER
    // =========================================================================

    public function checkAndApplyAntiSniping(
        int $itemId,
        int $endTimestamp,
        int $triggerSeconds = 180,
        int $extensionMinutes = 3
    ): bool {
        $now = time();
        $remainingSeconds = $endTimestamp - $now;

        if ($remainingSeconds <= $triggerSeconds && $remainingSeconds > 0) {
            $stmt = $this->pdo->prepare("
                UPDATE items 
                SET end_time = DATE_ADD(GREATEST(end_time, NOW()), INTERVAL :mins MINUTE),
                    extended_time = DATE_ADD(GREATEST(COALESCE(extended_time, end_time), NOW()), INTERVAL :mins2 MINUTE)
                WHERE id = :id
            ");
            $stmt->execute([
                ':mins'  => $extensionMinutes,
                ':mins2' => $extensionMinutes,
                ':id'    => $itemId
            ]);
            return true;
        }

        return false;
    }
}
