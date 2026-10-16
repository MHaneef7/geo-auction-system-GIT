<?php
/**
 * Second Chance Offer Engine
 * File: includes/SecondChanceService.php
 * 
 * If a winning bidder defaults or fails to complete payment within 24 hours,
 * this engine automatically creates and dispatches private purchase offers
 * to the 2nd and 3rd highest distinct bidders at their submitted bid price.
 */

declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/NotificationService.php';

class SecondChanceService
{
    private PDO $pdo;
    private NotificationService $notifService;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getPdo();
        $this->notifService = new NotificationService($this->pdo);
    }

    /**
     * Generate second chance offers for an item where the winner failed to settle
     */
    public function generateOffersForDefaultedItem(int $itemId, int $validityHours = 24): array
    {
        $stmt = $this->pdo->prepare("
            SELECT id, title, seller_id, winner_id, winning_bid_id, current_price, status 
            FROM items 
            WHERE id = :id 
            LIMIT 1
        ");
        $stmt->execute([':id' => $itemId]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$item) {
            throw new RuntimeException("Auction item not found.");
        }

        $winnerId = (int)($item['winner_id'] ?? 0);

        // Fetch 2nd and 3rd distinct highest bidders excluding original winner & seller
        $bidsStmt = $this->pdo->prepare("
            SELECT b.id AS bid_id, b.user_id, b.bid_amount, u.name, u.email 
            FROM bids b
            JOIN users u ON b.user_id = u.id
            WHERE b.item_id = :id 
              AND b.user_id != :winner_id 
              AND b.user_id != :seller_id
              AND b.bid_status IN ('active', 'outbid')
            GROUP BY b.user_id 
            ORDER BY MAX(b.bid_amount) DESC 
            LIMIT 2
        ");
        $bidsStmt->execute([
            ':id'        => $itemId,
            ':winner_id' => $winnerId,
            ':seller_id' => (int)$item['seller_id']
        ]);
        $runnersUp = $bidsStmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($runnersUp)) {
            return [
                'success' => false,
                'message' => 'No eligible runner-up bidders found for this item lot.'
            ];
        }

        $generatedOffers = [];
        $rank = 2;

        foreach ($runnersUp as $runner) {
            $userId       = (int)$runner['user_id'];
            $offeredPrice = (float)$runner['bid_amount'];
            $token        = bin2hex(random_bytes(32));
            $expiresAt    = date('Y-m-d H:i:s', time() + ($validityHours * 3600));

            // Check if active offer already exists
            $check = $this->pdo->prepare("
                SELECT id FROM second_chance_offers 
                WHERE item_id = :item_id AND user_id = :user_id AND status = 'pending'
            ");
            $check->execute([':item_id' => $itemId, ':user_id' => $userId]);
            if ($check->fetch()) {
                continue; // Skip duplicate pending offer
            }

            $ins = $this->pdo->prepare("
                INSERT INTO second_chance_offers (
                    item_id, user_id, offered_price, bid_id, rank, status, token, expires_at, created_at
                ) VALUES (
                    :item_id, :user_id, :price, :bid_id, :rank, 'pending', :token, :expires_at, NOW()
                )
            ");
            $ins->execute([
                ':item_id'    => $itemId,
                ':user_id'    => $userId,
                ':price'      => $offeredPrice,
                ':bid_id'     => (int)$runner['bid_id'],
                ':rank'       => $rank,
                ':token'      => $token,
                ':expires_at' => $expiresAt
            ]);

            $offerId = (int)$this->pdo->lastInsertId();

            // Send instant real-time notification
            try {
                $this->notifService->createNotification(
                    $userId,
                    "Second Chance Offer on Lot #{$itemId}!",
                    "You have received an exclusive 2nd Chance offer to buy '{$item['title']}' at your bid of Rs. " . number_format($offeredPrice, 2) . ". Valid for {$validityHours} hours.",
                    'second_chance',
                    $itemId
                );
            } catch (\Throwable $e) {
                error_log("Failed to dispatch second chance notification: " . $e->getMessage());
            }

            $generatedOffers[] = [
                'offer_id'      => $offerId,
                'user_id'       => $userId,
                'user_name'     => $runner['name'],
                'offered_price' => $offeredPrice,
                'rank'          => $rank,
                'expires_at'    => $expiresAt,
                'token'         => $token
            ];

            $rank++;
        }

        return [
            'success' => true,
            'item_id' => $itemId,
            'offers'  => $generatedOffers
        ];
    }

    /**
     * Accept a Second Chance Offer
     */
    public function acceptOffer(string $token, int $userId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT o.*, i.title, i.seller_id 
            FROM second_chance_offers o
            JOIN items i ON o.item_id = i.id
            WHERE o.token = :token 
              AND o.user_id = :user_id 
              AND o.status = 'pending'
            LIMIT 1
        ");
        $stmt->execute([':token' => $token, ':user_id' => $userId]);
        $offer = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$offer) {
            throw new RuntimeException("Invalid, expired, or non-existent Second Chance Offer.");
        }

        if (strtotime($offer['expires_at']) < time()) {
            $this->pdo->prepare("UPDATE second_chance_offers SET status = 'expired' WHERE id = :id")
                ->execute([':id' => (int)$offer['id']]);
            throw new RuntimeException("This Second Chance Offer has expired.");
        }

        $this->pdo->beginTransaction();
        try {
            // Mark offer as accepted
            $this->pdo->prepare("
                UPDATE second_chance_offers 
                SET status = 'accepted', responded_at = NOW() 
                WHERE id = :id
            ")->execute([':id' => (int)$offer['id']]);

            // Invalidate other pending offers for this lot
            $this->pdo->prepare("
                UPDATE second_chance_offers 
                SET status = 'expired', responded_at = NOW() 
                WHERE item_id = :item_id AND id != :id AND status = 'pending'
            ")->execute([':item_id' => (int)$offer['item_id'], ':id' => (int)$offer['id']]);

            // Re-assign item winner to the runner-up
            $this->pdo->prepare("
                UPDATE items 
                SET winner_id = :winner_id, 
                    current_price = :price, 
                    status = 'sold', 
                    updated_at = NOW() 
                WHERE id = :item_id
            ")->execute([
                ':winner_id' => $userId,
                ':price'     => (float)$offer['offered_price'],
                ':item_id'   => (int)$offer['item_id']
            ]);

            $this->pdo->commit();

            // Notify seller
            try {
                $this->notifService->createNotification(
                    (int)$offer['seller_id'],
                    "Second Chance Offer Accepted!",
                    "A runner-up bidder accepted the Second Chance offer for '{$offer['title']}' at Rs. " . number_format((float)$offer['offered_price'], 2) . ".",
                    'auction_won',
                    (int)$offer['item_id']
                );
            } catch (\Throwable $e) {}

            return [
                'success'       => true,
                'item_id'       => (int)$offer['item_id'],
                'offered_price' => (float)$offer['offered_price'],
                'message'       => 'Second Chance Offer successfully accepted! You are now the confirmed winner.'
            ];

        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }
}
