<?php
/**
 * Escrow & Dynamic Deposit Service
 * File: includes/EscrowService.php
 * 
 * Prevents fake bids, shill bidding, and ghosting by enforcing
 * a pre-authorized deposit hold (5% - 100%) against user wallets.
 * Automatically releases hold when outbid, settles on win, or forfeits on default.
 */

declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/WalletService.php';

class EscrowService
{
    private PDO $pdo;
    private WalletService $walletService;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getPdo();
        $this->walletService = new WalletService();
    }

    /**
     * Calculate required deposit amount based on item deposit percentage
     */
    public function calculateRequiredDeposit(float $bidAmount, float $depositPct): float
    {
        if ($depositPct <= 0.0) {
            return 0.0;
        }
        $depositPct = min(100.0, max(0.0, $depositPct));
        return round($bidAmount * ($depositPct / 100.0), 2);
    }

    /**
     * Hold required escrow deposit from user wallet before bid submission
     */
    public function holdDepositForBid(int $userId, int $itemId, float $bidAmount, float $depositPct): array
    {
        $requiredHold = $this->calculateRequiredDeposit($bidAmount, $depositPct);
        if ($requiredHold <= 0.0) {
            return [
                'required'     => false,
                'hold_amount'  => 0.0,
                'status'       => 'SKIPPED'
            ];
        }

        try {
            $lockResult = $this->walletService->placeBidHold(
                $userId,
                $itemId,
                $requiredHold,
                'BID-ESCROW-' . $userId . '-' . $itemId . '-' . time()
            );

            // Record in escrow_holds audit table
            $insHold = $this->pdo->prepare("
                INSERT INTO escrow_holds (user_id, item_id, hold_amount, status, created_at)
                VALUES (:user_id, :item_id, :hold_amount, 'held', NOW())
            ");
            $insHold->execute([
                ':user_id'     => $userId,
                ':item_id'     => $itemId,
                ':hold_amount' => $requiredHold
            ]);

            return [
                'required'       => true,
                'hold_amount'    => $requiredHold,
                'transaction_id' => $lockResult['transaction_id'] ?? null,
                'available'      => $lockResult['available'] ?? 0.0,
                'locked'         => $lockResult['locked'] ?? 0.0
            ];

        } catch (\Throwable $e) {
            throw new RuntimeException(
                "Deposit pre-authorization failed: " . $e->getMessage() . 
                " (Required deposit hold: Rs. " . number_format($requiredHold, 2) . ")"
            );
        }
    }

    /**
     * Release previous bidder's escrow hold upon being outbid
     */
    public function releaseOutbidDeposit(int $outbidUserId, int $itemId): bool
    {
        try {
            // Find active lock amount
            $stmt = $this->pdo->prepare("
                SELECT amount FROM wallet_locks 
                WHERE auction_id = :item_id 
                  AND wallet_id = (SELECT id FROM wallets WHERE user_id = :user_id LIMIT 1)
                  AND status = 'ACTIVE'
                LIMIT 1
            ");
            $stmt->execute([':item_id' => $itemId, ':user_id' => $outbidUserId]);
            $amount = $stmt->fetchColumn();

            if ($amount !== false && (float)$amount > 0) {
                $this->walletService->releaseBidHold($outbidUserId, $itemId, (float)$amount);
                
                // Update escrow_holds audit record
                $this->pdo->prepare("
                    UPDATE escrow_holds 
                    SET status = 'released', updated_at = NOW() 
                    WHERE user_id = :user_id AND item_id = :item_id AND status = 'held'
                ")->execute([':user_id' => $outbidUserId, ':item_id' => $itemId]);

                return true;
            }
            return false;
        } catch (\Throwable $e) {
            error_log("Failed to release outbid deposit for user #$outbidUserId on item #$itemId: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Settle winning bidder's escrow hold when auction is won
     */
    public function settleWinningDeposit(int $winnerId, int $itemId): bool
    {
        try {
            $this->walletService->settleBidWin($winnerId, $itemId);
            
            $this->pdo->prepare("
                UPDATE escrow_holds 
                SET status = 'captured', updated_at = NOW() 
                WHERE user_id = :user_id AND item_id = :item_id AND status = 'held'
            ")->execute([':user_id' => $winnerId, ':item_id' => $itemId]);

            return true;
        } catch (\Throwable $e) {
            error_log("Failed to settle winning deposit: " . $e->getMessage());
            return false;
        }
    }
}
