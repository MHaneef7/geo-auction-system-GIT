<?php
/**
 * WalletService - Enterprise Double-Entry Ledger & Concurrency-Safe Service Layer
 *
 * Implements:
 * - Pessimistic row-locking (SELECT ... FOR UPDATE)
 * - Deadlock-free multi-wallet transactions (ORDER BY id ASC FOR UPDATE)
 * - Atomic bid holds, releases, and settlement transfers
 * - Idempotency key protection
 */

class WalletService {
    private mysqli $conn;

    public function __construct(?mysqli $conn = null) {
        require_once __DIR__ . '/Database.php';
        $this->conn = $conn ?? Database::getMysqli();
    }

    /**
     * Get or create wallet for user
     */
    public function getOrCreateWallet(int $userId, string $currency = 'PKR'): array {
        $stmt = mysqli_prepare($this->conn, "SELECT * FROM wallets WHERE user_id = ? AND currency = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, "is", $userId, $currency);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $wallet = mysqli_fetch_assoc($res);
        mysqli_stmt_close($stmt);

        if (!$wallet) {
            // Get user current balance from users table if available
            $u_stmt = mysqli_prepare($this->conn, "SELECT balance FROM users WHERE id = ? LIMIT 1");
            mysqli_stmt_bind_param($u_stmt, "i", $userId);
            mysqli_stmt_execute($u_stmt);
            $u_res = mysqli_stmt_get_result($u_stmt);
            $initial_bal = (float)(mysqli_fetch_assoc($u_res)['balance'] ?? 0);
            mysqli_stmt_close($u_stmt);

            $ins = mysqli_prepare($this->conn, "INSERT INTO wallets (user_id, currency, available_balance, locked_balance, status) VALUES (?, ?, ?, 0.0000, 'ACTIVE')");
            mysqli_stmt_bind_param($ins, "isd", $userId, $currency, $initial_bal);
            mysqli_stmt_execute($ins);
            $new_id = mysqli_insert_id($this->conn);
            mysqli_stmt_close($ins);

            return [
                'id' => $new_id,
                'user_id' => $userId,
                'currency' => $currency,
                'available_balance' => $initial_bal,
                'locked_balance' => 0.0000,
                'status' => 'ACTIVE'
            ];
        }

        return $wallet;
    }

    /**
     * 1. Place Bid Hold:
     * Moves $amount from Available to Locked atomically with pessimistic lock.
     */
    public function placeBidHold(int $userId, int $auctionId, float $amount, ?string $idempotencyKey = null): array {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Bid amount must be greater than zero.');
        }

        mysqli_begin_transaction($this->conn);
        try {
            // 1. Ensure wallet exists and acquire pessimistic row lock
            $this->getOrCreateWallet($userId);
            $stmt = mysqli_prepare($this->conn, "SELECT * FROM wallets WHERE user_id = ? FOR UPDATE");
            mysqli_stmt_bind_param($stmt, "i", $userId);
            mysqli_stmt_execute($stmt);
            $wallet = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
            mysqli_stmt_close($stmt);

            if (!$wallet || $wallet['status'] !== 'ACTIVE') {
                throw new RuntimeException("Wallet is not active or not found.");
            }

            $available = (float)$wallet['available_balance'];
            $locked    = (float)$wallet['locked_balance'];

            // 2. Check existing active lock for this user on this auction
            $l_stmt = mysqli_prepare($this->conn, "SELECT * FROM wallet_locks WHERE wallet_id = ? AND auction_id = ? AND status = 'ACTIVE' FOR UPDATE");
            mysqli_stmt_bind_param($l_stmt, "ii", $wallet['id'], $auctionId);
            mysqli_stmt_execute($l_stmt);
            $existing_lock = mysqli_fetch_assoc(mysqli_stmt_get_result($l_stmt));
            mysqli_stmt_close($l_stmt);

            $previous_hold = $existing_lock ? (float)$existing_lock['amount'] : 0.0;
            $incremental   = $amount - $previous_hold;

            if ($incremental > 0 && $available < $incremental) {
                throw new RuntimeException("Insufficient available balance. Required: Rs. " . number_format($incremental, 2) . ", Available: Rs. " . number_format($available, 2));
            }

            $new_available = $available - $incremental;
            $new_locked    = $locked + $incremental;

            // 3. Update wallet balances
            $up_stmt = mysqli_prepare($this->conn, "UPDATE wallets SET available_balance = ?, locked_balance = ? WHERE id = ?");
            mysqli_stmt_bind_param($up_stmt, "ddi", $new_available, $new_locked, $wallet['id']);
            mysqli_stmt_execute($up_stmt);
            mysqli_stmt_close($up_stmt);

            // Also keep users.balance synchronized
            $sync = mysqli_prepare($this->conn, "UPDATE users SET balance = ? WHERE id = ?");
            mysqli_stmt_bind_param($sync, "di", $new_available, $userId);
            mysqli_stmt_execute($sync);
            mysqli_stmt_close($sync);

            // 4. Update or Insert wallet lock
            $lock_id = $idempotencyKey ?: ('LOCK-' . bin2hex(random_bytes(8)));
            if ($existing_lock) {
                $up_lock = mysqli_prepare($this->conn, "UPDATE wallet_locks SET amount = ?, updated_at = NOW() WHERE id = ?");
                mysqli_stmt_bind_param($up_lock, "di", $amount, $existing_lock['id']);
                mysqli_stmt_execute($up_lock);
                mysqli_stmt_close($up_lock);
            } else {
                $ins_lock = mysqli_prepare($this->conn, "INSERT INTO wallet_locks (lock_id, wallet_id, auction_id, amount, status, created_at) VALUES (?, ?, ?, ?, 'ACTIVE', NOW())");
                mysqli_stmt_bind_param($ins_lock, "siid", $lock_id, $wallet['id'], $auctionId, $amount);
                mysqli_stmt_execute($ins_lock);
                mysqli_stmt_close($ins_lock);
            }

            // 5. Insert into Immutable Double-Entry Ledger
            $tx_id = 'TX-' . strtoupper(bin2hex(random_bytes(8)));
            $ins_tx = mysqli_prepare($this->conn, "
                INSERT INTO wallet_transactions 
                (transaction_id, wallet_id, type, amount, available_balance_before, available_balance_after, locked_balance_before, locked_balance_after, reference_id, reference_type, idempotency_key, metadata)
                VALUES (?, ?, 'BID_HOLD', ?, ?, ?, ?, ?, ?, 'AUCTION', ?, ?)
            ");
            $meta = json_encode(['auction_id' => $auctionId, 'total_bid' => $amount, 'incremental' => $incremental]);
            $neg_inc = -$incremental;
            $auction_ref = (string)$auctionId;
            mysqli_stmt_bind_param($ins_tx, "sidddddssss", $tx_id, $wallet['id'], $neg_inc, $available, $new_available, $locked, $new_locked, $auction_ref, $idempotencyKey, $meta);
            mysqli_stmt_execute($ins_tx);
            mysqli_stmt_close($ins_tx);

            mysqli_commit($this->conn);

            return [
                'transaction_id' => $tx_id,
                'wallet_id'      => (int)$wallet['id'],
                'locked_amount'  => $amount,
                'available'      => $new_available,
                'locked'         => $new_locked,
                'total'          => $new_available + $new_locked
            ];
        } catch (Exception $e) {
            mysqli_rollback($this->conn);
            throw $e;
        }
    }

    /**
     * 2. Release Bid Hold:
     * Releases $amount from Locked back to Available when user is outbid.
     */
    public function releaseBidHold(int $userId, int $auctionId, float $amount, ?string $idempotencyKey = null): array {
        mysqli_begin_transaction($this->conn);
        try {
            $stmt = mysqli_prepare($this->conn, "SELECT * FROM wallets WHERE user_id = ? FOR UPDATE");
            mysqli_stmt_bind_param($stmt, "i", $userId);
            mysqli_stmt_execute($stmt);
            $wallet = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
            mysqli_stmt_close($stmt);

            if (!$wallet) throw new RuntimeException("Wallet not found.");

            $available = (float)$wallet['available_balance'];
            $locked    = (float)$wallet['locked_balance'];

            // Find active lock
            $l_stmt = mysqli_prepare($this->conn, "SELECT * FROM wallet_locks WHERE wallet_id = ? AND auction_id = ? AND status = 'ACTIVE' FOR UPDATE");
            mysqli_stmt_bind_param($l_stmt, "ii", $wallet['id'], $auctionId);
            mysqli_stmt_execute($l_stmt);
            $lock = mysqli_fetch_assoc(mysqli_stmt_get_result($l_stmt));
            mysqli_stmt_close($l_stmt);

            if (!$lock) throw new RuntimeException("No active lock found for auction #$auctionId.");

            $locked_amount = (float)$lock['amount'];
            $new_available = $available + $locked_amount;
            $new_locked    = max(0.0, $locked - $locked_amount);

            // Update Wallet Balances
            $up_w = mysqli_prepare($this->conn, "UPDATE wallets SET available_balance = ?, locked_balance = ? WHERE id = ?");
            mysqli_stmt_bind_param($up_w, "ddi", $new_available, $new_locked, $wallet['id']);
            mysqli_stmt_execute($up_w);
            mysqli_stmt_close($up_w);

            // Sync users table
            $sync = mysqli_prepare($this->conn, "UPDATE users SET balance = ? WHERE id = ?");
            mysqli_stmt_bind_param($sync, "di", $new_available, $userId);
            mysqli_stmt_execute($sync);
            mysqli_stmt_close($sync);

            // Mark lock as RELEASED
            $up_l = mysqli_prepare($this->conn, "UPDATE wallet_locks SET status = 'RELEASED', updated_at = NOW() WHERE id = ?");
            mysqli_stmt_bind_param($up_l, "i", $lock['id']);
            mysqli_stmt_execute($up_l);
            mysqli_stmt_close($up_l);

            // Record in Ledger
            $tx_id = 'TX-' . strtoupper(bin2hex(random_bytes(8)));
            $ins_tx = mysqli_prepare($this->conn, "
                INSERT INTO wallet_transactions 
                (transaction_id, wallet_id, type, amount, available_balance_before, available_balance_after, locked_balance_before, locked_balance_after, reference_id, reference_type, idempotency_key, metadata)
                VALUES (?, ?, 'BID_RELEASE', ?, ?, ?, ?, ?, ?, 'AUCTION', ?, ?)
            ");
            $meta = json_encode(['auction_id' => $auctionId, 'released_amount' => $locked_amount]);
            $auction_ref = (string)$auctionId;
            mysqli_stmt_bind_param($ins_tx, "sidddddssss", $tx_id, $wallet['id'], $locked_amount, $available, $new_available, $locked, $new_locked, $auction_ref, $idempotencyKey, $meta);
            mysqli_stmt_execute($ins_tx);
            mysqli_stmt_close($ins_tx);

            mysqli_commit($this->conn);

            return [
                'transaction_id'  => $tx_id,
                'released_amount' => $locked_amount,
                'available'       => $new_available,
                'locked'          => $new_locked
            ];
        } catch (Exception $e) {
            mysqli_rollback($this->conn);
            throw $e;
        }
    }

    /**
     * Settle Bid Win:
     * Captures active lock on winning auction and marks as SETTLED.
     */
    public function settleBidWin(int $userId, int $auctionId): bool {
        mysqli_begin_transaction($this->conn);
        try {
            $stmt = mysqli_prepare($this->conn, "SELECT id FROM wallets WHERE user_id = ? FOR UPDATE");
            mysqli_stmt_bind_param($stmt, "i", $userId);
            mysqli_stmt_execute($stmt);
            $wallet = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
            mysqli_stmt_close($stmt);

            if (!$wallet) {
                mysqli_rollback($this->conn);
                return false;
            }

            $l_stmt = mysqli_prepare($this->conn, "SELECT id, amount FROM wallet_locks WHERE wallet_id = ? AND auction_id = ? AND status = 'ACTIVE' FOR UPDATE");
            mysqli_stmt_bind_param($l_stmt, "ii", $wallet['id'], $auctionId);
            mysqli_stmt_execute($l_stmt);
            $lock = mysqli_fetch_assoc(mysqli_stmt_get_result($l_stmt));
            mysqli_stmt_close($l_stmt);

            if ($lock) {
                $up_l = mysqli_prepare($this->conn, "UPDATE wallet_locks SET status = 'SETTLED', updated_at = NOW() WHERE id = ?");
                mysqli_stmt_bind_param($up_l, "i", $lock['id']);
                mysqli_stmt_execute($up_l);
                mysqli_stmt_close($up_l);
            }

            mysqli_commit($this->conn);
            return true;
        } catch (Exception $e) {
            mysqli_rollback($this->conn);
            error_log("settleBidWin error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 3. Settle Auction Payment:
     * Settles winner's locked funds, credits seller net payout, and credits platform fee.
     * Prevents deadlocks by sorting wallet IDs in strict ascending order.
     */
    public function settleAuctionPayment(int $winnerId, int $sellerId, int $auctionId, float $amount, float $platformFee, ?string $idempotencyKey = null): array {
        $net_seller_payout = $amount - $platformFee;
        if ($net_seller_payout < 0) {
            throw new InvalidArgumentException("Platform fee cannot exceed settlement amount.");
        }

        $PLATFORM_USER_ID = 1; // Admin / platform account

        mysqli_begin_transaction($this->conn);
        try {
            // Ensure wallets exist
            $this->getOrCreateWallet($winnerId);
            $this->getOrCreateWallet($sellerId);
            $this->getOrCreateWallet($PLATFORM_USER_ID);

            // Lock wallets in strict ascending order to prevent deadlocks
            $user_ids = [$winnerId, $sellerId, $PLATFORM_USER_ID];
            $in_clause = implode(',', array_map('intval', $user_ids));
            $w_res = mysqli_query($this->conn, "SELECT * FROM wallets WHERE user_id IN ($in_clause) ORDER BY id ASC FOR UPDATE");

            $wallets = [];
            while ($row = mysqli_fetch_assoc($w_res)) {
                $wallets[(int)$row['user_id']] = $row;
            }

            $winner_w   = $wallets[$winnerId] ?? null;
            $seller_w   = $wallets[$sellerId] ?? null;
            $platform_w = $wallets[$PLATFORM_USER_ID] ?? null;

            if (!$winner_w || !$seller_w || !$platform_w) {
                throw new RuntimeException("One or more participant wallets could not be located.");
            }

            // Verify active lock on winner
            $l_stmt = mysqli_prepare($this->conn, "SELECT * FROM wallet_locks WHERE wallet_id = ? AND auction_id = ? AND status = 'ACTIVE' FOR UPDATE");
            mysqli_stmt_bind_param($l_stmt, "ii", $winner_w['id'], $auctionId);
            mysqli_stmt_execute($l_stmt);
            $winner_lock = mysqli_fetch_assoc(mysqli_stmt_get_result($l_stmt));
            mysqli_stmt_close($l_stmt);

            if (!$winner_lock) {
                throw new RuntimeException("Active lock not found for winner on auction #$auctionId.");
            }

            // Deduct winner locked balance
            $w_locked = (float)$winner_w['locked_balance'];
            $new_winner_locked = max(0.0, $w_locked - $amount);
            $up_w = mysqli_prepare($this->conn, "UPDATE wallets SET locked_balance = ? WHERE id = ?");
            mysqli_stmt_bind_param($up_w, "di", $new_winner_locked, $winner_w['id']);
            mysqli_stmt_execute($up_w);
            mysqli_stmt_close($up_w);

            // Credit seller available balance
            $s_avail = (float)$seller_w['available_balance'];
            $new_seller_avail = $s_avail + $net_seller_payout;
            $up_s = mysqli_prepare($this->conn, "UPDATE wallets SET available_balance = ? WHERE id = ?");
            mysqli_stmt_bind_param($up_s, "di", $new_seller_avail, $seller_w['id']);
            mysqli_stmt_execute($up_s);
            mysqli_stmt_close($up_s);

            // Sync seller users.balance
            $sync_s = mysqli_prepare($this->conn, "UPDATE users SET balance = ? WHERE id = ?");
            mysqli_stmt_bind_param($sync_s, "di", $new_seller_avail, $sellerId);
            mysqli_stmt_execute($sync_s);
            mysqli_stmt_close($sync_s);

            // Credit platform available balance
            $p_avail = (float)$platform_w['available_balance'];
            $new_platform_avail = $p_avail + $platformFee;
            $up_p = mysqli_prepare($this->conn, "UPDATE wallets SET available_balance = ? WHERE id = ?");
            mysqli_stmt_bind_param($up_p, "di", $new_platform_avail, $platform_w['id']);
            mysqli_stmt_execute($up_p);
            mysqli_stmt_close($up_p);

            // Sync platform users.balance
            $sync_p = mysqli_prepare($this->conn, "UPDATE users SET balance = ? WHERE id = ?");
            mysqli_stmt_bind_param($sync_p, "di", $new_platform_avail, $PLATFORM_USER_ID);
            mysqli_stmt_execute($sync_p);
            mysqli_stmt_close($sync_p);

            // Mark lock as SETTLED
            $up_l = mysqli_prepare($this->conn, "UPDATE wallet_locks SET status = 'SETTLED', updated_at = NOW() WHERE id = ?");
            mysqli_stmt_bind_param($up_l, "i", $winner_lock['id']);
            mysqli_stmt_execute($up_l);
            mysqli_stmt_close($up_l);

            // Write 3 Double-Entry Ledger Transactions
            $winner_tx   = 'TX-WIN-' . strtoupper(bin2hex(random_bytes(6)));
            $seller_tx   = 'TX-SEL-' . strtoupper(bin2hex(random_bytes(6)));
            $platform_tx = 'TX-FEE-' . strtoupper(bin2hex(random_bytes(6)));

            $auction_ref = (string)$auctionId;

            // Winner Ledger
            $ins_w = mysqli_prepare($this->conn, "INSERT INTO wallet_transactions (transaction_id, wallet_id, type, amount, available_balance_before, available_balance_after, locked_balance_before, locked_balance_after, reference_id, reference_type, metadata) VALUES (?, ?, 'BID_WON', ?, ?, ?, ?, ?, ?, 'AUCTION', ?)");
            $neg_amount = -$amount;
            $w_avail = (float)$winner_w['available_balance'];
            $meta_w = json_encode(['auction_id' => $auctionId, 'role' => 'winner']);
            mysqli_stmt_bind_param($ins_w, "sidddddss", $winner_tx, $winner_w['id'], $neg_amount, $w_avail, $w_avail, $w_locked, $new_winner_locked, $auction_ref, $meta_w);
            mysqli_stmt_execute($ins_w);
            mysqli_stmt_close($ins_w);

            // Seller Ledger
            $ins_s = mysqli_prepare($this->conn, "INSERT INTO wallet_transactions (transaction_id, wallet_id, type, amount, available_balance_before, available_balance_after, locked_balance_before, locked_balance_after, reference_id, reference_type, metadata) VALUES (?, ?, 'AUCTION_PAYOUT', ?, ?, ?, ?, ?, ?, 'AUCTION', ?)");
            $s_locked = (float)$seller_w['locked_balance'];
            $meta_s = json_encode(['auction_id' => $auctionId, 'gross' => $amount, 'fee' => $platformFee]);
            mysqli_stmt_bind_param($ins_s, "sidddddss", $seller_tx, $seller_w['id'], $net_seller_payout, $s_avail, $new_seller_avail, $s_locked, $s_locked, $auction_ref, $meta_s);
            mysqli_stmt_execute($ins_s);
            mysqli_stmt_close($ins_s);

            // Platform Ledger
            $ins_p = mysqli_prepare($this->conn, "INSERT INTO wallet_transactions (transaction_id, wallet_id, type, amount, available_balance_before, available_balance_after, locked_balance_before, locked_balance_after, reference_id, reference_type, metadata) VALUES (?, ?, 'PLATFORM_FEE', ?, ?, ?, ?, ?, ?, 'AUCTION', ?)");
            $p_locked = (float)$platform_w['locked_balance'];
            $meta_p = json_encode(['auction_id' => $auctionId, 'fee' => $platformFee]);
            mysqli_stmt_bind_param($ins_p, "sidddddss", $platform_tx, $platform_w['id'], $platformFee, $p_avail, $new_platform_avail, $p_locked, $p_locked, $auction_ref, $meta_p);
            mysqli_stmt_execute($ins_p);
            mysqli_stmt_close($ins_p);

            mysqli_commit($this->conn);

            return [
                'winner_transaction_id'   => $winner_tx,
                'seller_transaction_id'   => $seller_tx,
                'platform_transaction_id' => $platform_tx,
                'settled_amount'          => $amount,
                'seller_net'              => $net_seller_payout,
                'platform_fee'            => $platformFee
            ];
        } catch (Exception $e) {
            mysqli_rollback($this->conn);
            throw $e;
        }
    }

    /**
     * 4. Deposit / Top-Up
     */
    public function deposit(int $userId, float $amount, string $paymentRef, string $gateway, ?string $idempotencyKey = null): array {
        if ($amount <= 0) throw new InvalidArgumentException("Deposit amount must be positive.");

        mysqli_begin_transaction($this->conn);
        try {
            $this->getOrCreateWallet($userId);
            $stmt = mysqli_prepare($this->conn, "SELECT * FROM wallets WHERE user_id = ? FOR UPDATE");
            mysqli_stmt_bind_param($stmt, "i", $userId);
            mysqli_stmt_execute($stmt);
            $wallet = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
            mysqli_stmt_close($stmt);

            $avail = (float)$wallet['available_balance'];
            $locked = (float)$wallet['locked_balance'];
            $new_avail = $avail + $amount;

            $up = mysqli_prepare($this->conn, "UPDATE wallets SET available_balance = ? WHERE id = ?");
            mysqli_stmt_bind_param($up, "di", $new_avail, $wallet['id']);
            mysqli_stmt_execute($up);
            mysqli_stmt_close($up);

            $sync = mysqli_prepare($this->conn, "UPDATE users SET balance = ? WHERE id = ?");
            mysqli_stmt_bind_param($sync, "di", $new_avail, $userId);
            mysqli_stmt_execute($sync);
            mysqli_stmt_close($sync);

            $tx_id = 'TX-DEP-' . strtoupper(bin2hex(random_bytes(6)));
            $ins_tx = mysqli_prepare($this->conn, "
                INSERT INTO wallet_transactions 
                (transaction_id, wallet_id, type, amount, available_balance_before, available_balance_after, locked_balance_before, locked_balance_after, reference_id, reference_type, idempotency_key, metadata)
                VALUES (?, ?, 'DEPOSIT', ?, ?, ?, ?, ?, ?, 'PAYMENT_GATEWAY', ?, ?)
            ");
            $meta = json_encode(['gateway' => $gateway, 'payment_ref' => $paymentRef]);
            mysqli_stmt_bind_param($ins_tx, "sidddddssss", $tx_id, $wallet['id'], $amount, $avail, $new_avail, $locked, $locked, $paymentRef, $idempotencyKey, $meta);
            mysqli_stmt_execute($ins_tx);
            mysqli_stmt_close($ins_tx);

            mysqli_commit($this->conn);

            return [
                'transaction_id' => $tx_id,
                'new_balance'    => $new_avail,
                'amount'         => $amount
            ];
        } catch (Exception $e) {
            mysqli_rollback($this->conn);
            throw $e;
        }
    }

    /**
     * 5. Get Wallet Balance and Active Locks
     */
    public function getBalance(int $userId): array {
        $wallet = $this->getOrCreateWallet($userId);
        $w_id = (int)$wallet['id'];

        $stmt = mysqli_prepare($this->conn, "
            SELECT l.*, i.title, i.auction_code, i.end_time 
            FROM wallet_locks l 
            JOIN items i ON l.auction_id = i.id 
            WHERE l.wallet_id = ? AND l.status = 'ACTIVE'
        ");
        mysqli_stmt_bind_param($stmt, "i", $w_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $locks = [];
        $locked_calc = 0.0;
        while ($row = mysqli_fetch_assoc($res)) {
            $locks[] = $row;
            $locked_calc += (float)$row['amount'];
        }
        mysqli_stmt_close($stmt);

        $avail = (float)$wallet['available_balance'];

        return [
            'wallet_id'         => $w_id,
            'user_id'           => $userId,
            'currency'          => $wallet['currency'],
            'available_balance' => $avail,
            'locked_balance'    => $locked_calc,
            'total_balance'     => $avail + $locked_calc,
            'active_locks'      => $locks,
            'status'            => $wallet['status']
        ];
    }
}
