<?php
/**
 * User Dashboard & Real-Time Bidding Analytics - Online Auction System
 * File: user/dashboard.php
 *
 * Senior Full-Stack PHP & Modern UI/UX Architecture
 *
 * ============================================================================
 * MYSQL DATABASE STRUCTURE & SCHEMA REFERENCE:
 * ============================================================================
 * 
 * -- 1. Users Table
 * CREATE TABLE IF NOT EXISTS users (
 *     id INT AUTO_INCREMENT PRIMARY KEY,
 *     name VARCHAR(100) NOT NULL,
 *     email VARCHAR(100) NOT NULL UNIQUE,
 *     role ENUM('admin', 'seller', 'buyer') NOT NULL DEFAULT 'buyer',
 *     balance DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
 *     status ENUM('active', 'inactive', 'suspended') NOT NULL DEFAULT 'active',
 *     created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
 * ) ENGINE=InnoDB;
 *
 * -- 2. Items (Auctions) Table
 * CREATE TABLE IF NOT EXISTS items (
 *     id INT AUTO_INCREMENT PRIMARY KEY,
 *     auction_code VARCHAR(20) NOT NULL UNIQUE,
 *     title VARCHAR(255) NOT NULL,
 *     category_id INT NULL,
 *     seller_id INT NOT NULL,
 *     starting_price DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
 *     current_price DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
 *     min_increment DECIMAL(12, 2) NOT NULL DEFAULT 100.00,
 *     featured_image VARCHAR(255) NULL,
 *     start_time DATETIME NOT NULL,
 *     end_time DATETIME NOT NULL,
 *     status ENUM('pending', 'active', 'ended', 'sold', 'cancelled') NOT NULL DEFAULT 'active',
 *     bid_count INT NOT NULL DEFAULT 0,
 *     winner_id INT NULL,
 *     is_deleted TINYINT(1) NOT NULL DEFAULT 0,
 *     created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 *     FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE CASCADE
 * ) ENGINE=InnoDB;
 *
 * -- 3. Bids Table
 * CREATE TABLE IF NOT EXISTS bids (
 *     id INT AUTO_INCREMENT PRIMARY KEY,
 *     item_id INT NOT NULL,
 *     user_id INT NOT NULL,
 *     bid_amount DECIMAL(12, 2) NOT NULL,
 *     bid_status ENUM('active', 'winning', 'outbid', 'won', 'lost', 'cancelled') NOT NULL DEFAULT 'active',
 *     is_winning TINYINT(1) NOT NULL DEFAULT 0,
 *     created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 *     FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE,
 *     FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
 *     INDEX idx_bids_item_amount (item_id, bid_amount DESC)
 * ) ENGINE=InnoDB;
 *
 * -- 4. Wishlist / Watchlist Table
 * CREATE TABLE IF NOT EXISTS wishlist (
 *     id INT AUTO_INCREMENT PRIMARY KEY,
 *     user_id INT NOT NULL,
 *     item_id INT NOT NULL,
 *     created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 *     FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
 *     FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE
 * ) ENGINE=InnoDB;
 *
 * -- 5. Orders Table
 * CREATE TABLE IF NOT EXISTS orders (
 *     id INT AUTO_INCREMENT PRIMARY KEY,
 *     order_number VARCHAR(50) NOT NULL UNIQUE,
 *     auction_id INT NOT NULL,
 *     buyer_id INT NOT NULL,
 *     seller_id INT NOT NULL,
 *     hammer_price DECIMAL(12, 2) NOT NULL,
 *     payment_status ENUM('PENDING', 'PAID', 'REFUNDED') NOT NULL DEFAULT 'PENDING',
 *     shipping_status ENUM('PROCESSING', 'SHIPPED', 'COMPLETED', 'DISPUTED') NOT NULL DEFAULT 'PROCESSING',
 *     created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
 * ) ENGINE=InnoDB;
 *
 * -- 6. Notifications Table
 * CREATE TABLE IF NOT EXISTS notifications (
 *     id INT AUTO_INCREMENT PRIMARY KEY,
 *     user_id INT NOT NULL,
 *     type VARCHAR(50) NOT NULL,
 *     title VARCHAR(255) NOT NULL,
 *     message TEXT NOT NULL,
 *     link VARCHAR(255) NULL,
 *     icon VARCHAR(50) NULL,
 *     is_read TINYINT(1) NOT NULL DEFAULT 0,
 *     created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
 * ) ENGINE=InnoDB;
 * ============================================================================
 */

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// -----------------------------------------------------------------------------
// 1. Database Connection & Helper Functions
// -----------------------------------------------------------------------------
require_once __DIR__ . '/../includes/database.php';

try {
    $pdo = Database::getPdo();
} catch (\Throwable $e) {
    die("Database Connection Error: " . htmlspecialchars($e->getMessage()));
}

// -----------------------------------------------------------------------------
// 2. Authentication & Role Switcher
// -----------------------------------------------------------------------------
if (!isset($_SESSION['user_id'])) {
    if (file_exists(__DIR__ . '/../login.php')) {
        header("Location: ../login.php?redirect=user/dashboard.php");
        exit();
    } elseif (file_exists(__DIR__ . '/login.php')) {
        header("Location: login.php");
        exit();
    } else {
        // Fallback for development/demo testing if session is absent
        $_SESSION['user_id'] = 1;
        $_SESSION['role'] = 'buyer';
        $_SESSION['user_name'] = 'Demo Bidder';
    }
}

// Developer / Demo role switcher support
if (isset($_GET['switch_role']) && in_array(strtolower($_GET['switch_role']), ['buyer', 'seller', 'admin'], true)) {
    $_SESSION['role'] = strtolower(trim($_GET['switch_role']));
}

$user_id   = (int)$_SESSION['user_id'];
$user_role = strtolower(trim($_SESSION['role'] ?? $_SESSION['user_role'] ?? 'buyer'));
$user_name = $_SESSION['user_name'] ?? ($_SESSION['name'] ?? 'Auctioneer');

// Generate CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Helper for item images
function get_dash_image_url(?string $path, string $root_path = '../'): string {
    if (empty($path)) {
        return 'https://images.unsplash.com/photo-1523275335684-37898b6baf30?auto=format&fit=crop&w=600&q=80';
    }
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }
    return $root_path . ltrim($path, '/');
}

// -----------------------------------------------------------------------------
// 3. BACKEND API ENDPOINT: get_bid_stats (JSON API for Dynamic Chart.js Analytics)
// -----------------------------------------------------------------------------
// This endpoint powers the Chart.js visual analytics via asynchronous AJAX calls.
if ((isset($_GET['action']) && $_GET['action'] === 'get_bid_stats') || (isset($_GET['ajax']) && $_GET['ajax'] === 'get_bid_stats')) {
    header('Content-Type: application/json; charset=utf-8');

    try {
        // A. Summary Metrics (Active Bids, Highest, Lowest, Average)
        $stmt_user_summary = $pdo->prepare("
            SELECT 
                COUNT(DISTINCT b.item_id) as total_active_bids,
                COALESCE(MAX(b.bid_amount), 0) as highest_bid,
                COALESCE(MIN(b.bid_amount), 0) as lowest_bid,
                COALESCE(AVG(b.bid_amount), 0) as average_bid,
                COUNT(b.id) as total_bids_count
            FROM bids b
            INNER JOIN items i ON b.item_id = i.id
            WHERE b.user_id = :uid 
              AND i.status = 'active' 
              AND i.end_time > NOW() 
              AND (i.is_deleted = 0 OR i.is_deleted IS NULL)
        ");
        $stmt_user_summary->execute([':uid' => $user_id]);
        $summary = $stmt_user_summary->fetch() ?: [];

        $total_active_bids = (int)($summary['total_active_bids'] ?? 0);
        $highest_bid       = (float)($summary['highest_bid'] ?? 0);
        $lowest_bid        = (float)($summary['lowest_bid'] ?? 0);
        $average_bid       = (float)($summary['average_bid'] ?? 0);
        $is_benchmark      = false;

        // If user has not placed active bids yet, calculate platform benchmarks so graphs remain informative
        if ($total_active_bids === 0) {
            $is_benchmark = true;
            $stmt_bench = $pdo->prepare("
                SELECT 
                    COUNT(DISTINCT id) as total_active_bids,
                    COALESCE(MAX(current_price), 0) as highest_bid,
                    COALESCE(MIN(current_price), 0) as lowest_bid,
                    COALESCE(AVG(current_price), 0) as average_bid
                FROM items
                WHERE status = 'active' 
                  AND end_time > NOW() 
                  AND (is_deleted = 0 OR is_deleted IS NULL)
            ");
            $stmt_bench->execute();
            $bench = $stmt_bench->fetch() ?: [];

            $total_active_bids = (int)($bench['total_active_bids'] ?? 0);
            $highest_bid       = (float)($bench['highest_bid'] ?? 0);
            $lowest_bid        = (float)($bench['lowest_bid'] ?? 0);
            $average_bid       = (float)($bench['average_bid'] ?? 0);
        }

        // B. Top 5 High-Performing / Most Active Bids (Sab se ziyada bids / Top active items)
        $stmt_top5 = $pdo->prepare("
            SELECT 
                i.id,
                i.title,
                i.auction_code,
                i.current_price,
                i.bid_count,
                COALESCE(MAX(b.bid_amount), i.current_price) as peak_bid
            FROM items i
            LEFT JOIN bids b ON i.id = b.item_id
            WHERE (i.is_deleted = 0 OR i.is_deleted IS NULL) 
              AND i.status = 'active' 
              AND i.end_time > NOW()
            GROUP BY i.id, i.title, i.auction_code, i.current_price, i.bid_count
            ORDER BY i.bid_count DESC, peak_bid DESC
            LIMIT 5
        ");
        $stmt_top5->execute();
        $top_raw = $stmt_top5->fetchAll();

        $top_labels     = [];
        $top_amounts    = [];
        $top_bid_counts = [];
        $top_items_data = [];

        foreach ($top_raw as $item) {
            $short_title = mb_strimwidth($item['title'], 0, 24, '...');
            $amt         = (float)$item['peak_bid'];
            $b_count     = (int)$item['bid_count'];

            $top_labels[]     = $short_title;
            $top_amounts[]    = $amt;
            $top_bid_counts[] = $b_count;
            $top_items_data[] = [
                'id'        => (int)$item['id'],
                'title'     => $item['title'],
                'code'      => $item['auction_code'],
                'amount'    => $amt,
                'bid_count' => $b_count,
            ];
        }

        // C. Lowest 5 / Least Active Bids (Sab se kam bids / Least active items)
        $stmt_low5 = $pdo->prepare("
            SELECT 
                i.id,
                i.title,
                i.auction_code,
                i.current_price,
                i.bid_count,
                COALESCE(MIN(b.bid_amount), i.current_price) as min_bid
            FROM items i
            LEFT JOIN bids b ON i.id = b.item_id
            WHERE (i.is_deleted = 0 OR i.is_deleted IS NULL) 
              AND i.status = 'active' 
              AND i.end_time > NOW()
            GROUP BY i.id, i.title, i.auction_code, i.current_price, i.bid_count
            ORDER BY i.bid_count ASC, min_bid ASC
            LIMIT 5
        ");
        $stmt_low5->execute();
        $low_raw = $stmt_low5->fetchAll();

        $low_labels     = [];
        $low_amounts    = [];
        $low_bid_counts = [];
        $low_items_data = [];

        foreach ($low_raw as $item) {
            $short_title = mb_strimwidth($item['title'], 0, 24, '...');
            $amt         = (float)$item['min_bid'];
            $b_count     = (int)$item['bid_count'];

            $low_labels[]     = $short_title;
            $low_amounts[]    = $amt;
            $low_bid_counts[] = $b_count;
            $low_items_data[] = [
                'id'        => (int)$item['id'],
                'title'     => $item['title'],
                'code'      => $item['auction_code'],
                'amount'    => $amt,
                'bid_count' => $b_count,
            ];
        }

        // D. Bid Status & Volume Distribution (For Doughnut Chart)
        $stmt_dist = $pdo->prepare("
            SELECT 
                COALESCE(SUM(CASE WHEN b.is_winning = 1 AND i.status = 'active' AND i.end_time > NOW() THEN 1 ELSE 0 END), 0) as winning_count,
                COALESCE(SUM(CASE WHEN b.bid_status = 'outbid' AND i.status = 'active' AND i.end_time > NOW() THEN 1 ELSE 0 END), 0) as outbid_count,
                COALESCE(SUM(CASE WHEN i.winner_id = :winner_uid THEN 1 ELSE 0 END), 0) as won_count,
                COALESCE(SUM(CASE WHEN i.status = 'active' AND i.end_time > NOW() AND TIMESTAMPDIFF(HOUR, NOW(), i.end_time) <= 24 THEN 1 ELSE 0 END), 0) as ending_soon_count
            FROM bids b
            INNER JOIN items i ON b.item_id = i.id
            WHERE b.user_id = :bid_uid AND (i.is_deleted = 0 OR i.is_deleted IS NULL)
        ");
        $stmt_dist->execute([':winner_uid' => $user_id, ':bid_uid' => $user_id]);
        $dist = $stmt_dist->fetch() ?: [];

        $winning_bids = (int)($dist['winning_count'] ?? 0);
        $outbid_bids  = (int)($dist['outbid_count'] ?? 0);
        $won_bids     = (int)($dist['won_count'] ?? 0);
        $ending_bids  = (int)($dist['ending_soon_count'] ?? 0);

        // If user has zero distribution data, provide category-level auction distribution
        if (($winning_bids + $outbid_bids + $won_bids + $ending_bids) === 0) {
            $stmt_cat_dist = $pdo->prepare("
                SELECT c.name as category_name, COUNT(i.id) as item_count
                FROM items i
                LEFT JOIN categories c ON i.category_id = c.id
                WHERE i.status = 'active' AND i.end_time > NOW() AND (i.is_deleted = 0 OR i.is_deleted IS NULL)
                GROUP BY c.name
                ORDER BY item_count DESC
                LIMIT 4
            ");
            $stmt_cat_dist->execute();
            $cat_rows = $stmt_cat_dist->fetchAll();

            $dist_labels = [];
            $dist_values = [];
            $dist_colors = ['#10b981', '#3b82f6', '#f59e0b', '#8b5cf6'];

            foreach ($cat_rows as $idx => $crow) {
                $dist_labels[] = $crow['category_name'] ?: 'General Goods';
                $dist_values[] = (int)$crow['item_count'];
            }

            if (empty($dist_labels)) {
                $dist_labels = ['Automobiles', 'Real Estate', 'Luxury Watches', 'Surplus Lots'];
                $dist_values = [4, 2, 3, 1];
            }
        } else {
            $dist_labels = ['Winning (Highest)', 'Outbid (Raise Required)', 'Auctions Won', 'Ending Soon (<24h)'];
            $dist_values = [$winning_bids, $outbid_bids, $won_bids, $ending_bids];
            $dist_colors = ['#10b981', '#ef4444', '#3b82f6', '#f59e0b'];
        }

        echo json_encode([
            'status'    => 'success',
            'timestamp' => date('Y-m-d H:i:s'),
            'summary'   => [
                'total_active_bids'      => $total_active_bids,
                'highest_bid'            => $highest_bid,
                'lowest_bid'             => $lowest_bid,
                'average_bid'            => round($average_bid, 2),
                'highest_bid_formatted'  => 'Rs. ' . number_format($highest_bid, 2),
                'lowest_bid_formatted'   => 'Rs. ' . number_format($lowest_bid, 2),
                'average_bid_formatted'  => 'Rs. ' . number_format($average_bid, 2),
                'is_benchmark'           => $is_benchmark,
            ],
            'comparison' => [
                'top_items'    => $top_items_data,
                'lowest_items' => $low_items_data,
                'top_chart'    => [
                    'labels'     => $top_labels,
                    'amounts'    => $top_amounts,
                    'bid_counts' => $top_bid_counts,
                ],
                'low_chart'    => [
                    'labels'     => $low_labels,
                    'amounts'    => $low_amounts,
                    'bid_counts' => $low_bid_counts,
                ],
            ],
            'distribution' => [
                'labels' => $dist_labels,
                'data'   => $dist_values,
                'colors' => $dist_colors,
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit();

    } catch (\Throwable $e) {
        http_response_code(500);
        echo json_encode([
            'status'  => 'error',
            'message' => 'Failed to calculate bidding analytics: ' . $e->getMessage(),
        ]);
        exit();
    }
}

// -----------------------------------------------------------------------------
// 4. POST Actions (Quick Raise Bid & 1-Click Wallet Checkout)
// -----------------------------------------------------------------------------
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action         = trim($_POST['action'] ?? '');
    $submitted_csrf = trim($_POST['csrf_token'] ?? '');

    if (!hash_equals($csrf_token, $submitted_csrf)) {
        $_SESSION['flash_error'] = 'Security verification failed: Invalid CSRF token. Please refresh and try again.';
        header("Location: dashboard.php");
        exit();
    }

    // A. Action: Quick Raise Bid
    if ($action === 'raise_bid') {
        $bid_item_id = (int)($_POST['item_id'] ?? 0);
        $bid_amount  = (float)($_POST['bid_amount'] ?? 0);

        if ($bid_item_id <= 0 || $bid_amount <= 0) {
            $_SESSION['flash_error'] = 'Invalid item or bid amount specified.';
            header("Location: dashboard.php");
            exit();
        }

        try {
            $pdo->beginTransaction();

            // Lock and inspect target item
            $stmt = $pdo->prepare("
                SELECT id, title, current_price, starting_price, min_increment, seller_id, status, start_time, end_time
                FROM items
                WHERE id = :item_id FOR UPDATE
            ");
            $stmt->execute([':item_id' => $bid_item_id]);
            $item = $stmt->fetch();

            if (!$item) {
                throw new RuntimeException("The requested auction lot was not found.");
            }

            if ($item['seller_id'] === $user_id) {
                throw new RuntimeException("You cannot place bids on your own auction listing.");
            }

            $now_ts   = time();
            $start_ts = strtotime($item['start_time']);
            $end_ts   = strtotime($item['end_time']);

            if ($item['status'] !== 'active' || $now_ts < $start_ts || $now_ts > $end_ts) {
                throw new RuntimeException("This auction has ended or is not currently open for bidding.");
            }

            $current_price = (float)($item['current_price'] > 0 ? $item['current_price'] : $item['starting_price']);
            $min_increment = (float)($item['min_increment'] > 0 ? $item['min_increment'] : 100.00);
            $min_required  = $current_price + $min_increment;

            if ($bid_amount < $min_required) {
                throw new RuntimeException("Bid must be at least Rs. " . number_format($min_required, 2));
            }

            // Identify previous leader to notify them of outbid status
            $lead_stmt = $pdo->prepare("
                SELECT user_id, bid_amount 
                FROM bids 
                WHERE item_id = :item_id 
                ORDER BY bid_amount DESC, id DESC 
                LIMIT 1
            ");
            $lead_stmt->execute([':item_id' => $bid_item_id]);
            $prev_leader = $lead_stmt->fetch();

            // Insert new winning bid
            $ins_bid = $pdo->prepare("
                INSERT INTO bids (item_id, user_id, bid_amount, bid_status, is_winning, created_at)
                VALUES (:item_id, :user_id, :bid_amount, 'winning', 1, NOW())
            ");
            $ins_bid->execute([
                ':item_id'    => $bid_item_id,
                ':user_id'    => $user_id,
                ':bid_amount' => $bid_amount,
            ]);
            $new_bid_id = (int)$pdo->lastInsertId();

            // Demote other bids for this item to 'outbid'
            $upd_bids = $pdo->prepare("
                UPDATE bids 
                SET is_winning = 0, bid_status = 'outbid' 
                WHERE item_id = :item_id AND id != :new_bid_id
            ");
            $upd_bids->execute([
                ':item_id'    => $bid_item_id,
                ':new_bid_id' => $new_bid_id,
            ]);

            // Update item price & bid count
            $upd_item = $pdo->prepare("
                UPDATE items 
                SET current_price = :new_price, bid_count = bid_count + 1, updated_at = NOW() 
                WHERE id = :item_id
            ");
            $upd_item->execute([
                ':new_price' => $bid_amount,
                ':item_id'   => $bid_item_id,
            ]);

            // Notify previous leader if different user
            if ($prev_leader && (int)$prev_leader['user_id'] !== $user_id) {
                $notif_stmt = $pdo->prepare("
                    INSERT INTO notifications (user_id, type, title, message, link, icon, created_at)
                    VALUES (:uid, 'outbid', 'You Have Been Outbid!', :msg, :link, 'fa-gavel', NOW())
                ");
                $notif_stmt->execute([
                    ':uid'  => (int)$prev_leader['user_id'],
                    ':msg'  => "Someone placed a higher bid of Rs. " . number_format($bid_amount, 2) . " on \"" . htmlspecialchars($item['title']) . "\". Raise your bid now!",
                    ':link' => "../live_auction.php?id=" . $bid_item_id,
                ]);
            }

            // Notify seller of new bid
            if ((int)$item['seller_id'] !== $user_id) {
                $seller_notif = $pdo->prepare("
                    INSERT INTO notifications (user_id, type, title, message, link, icon, created_at)
                    VALUES (:seller_uid, 'bid', 'New Bid on Your Auction!', :msg, :link, 'fa-bullhorn', NOW())
                ");
                $seller_notif->execute([
                    ':seller_uid' => (int)$item['seller_id'],
                    ':msg'        => "New bid of Rs. " . number_format($bid_amount, 2) . " received on \"" . htmlspecialchars($item['title']) . "\".",
                    ':link'       => "../live_auction.php?id=" . $bid_item_id,
                ]);
            }

            $pdo->commit();
            $_SESSION['flash_success'] = "Bid of Rs. " . number_format($bid_amount, 2) . " placed successfully! You are currently the highest bidder.";
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $_SESSION['flash_error'] = "Bid Error: " . $e->getMessage();
        }

        header("Location: dashboard.php");
        exit();
    }

    // B. Action: Settle Won Auction via Wallet
    if ($action === 'pay_won_auction') {
        $won_item_id = (int)($_POST['item_id'] ?? 0);
        if ($won_item_id <= 0) {
            $_SESSION['flash_error'] = 'Invalid auction lot selected for payment.';
            header("Location: dashboard.php");
            exit();
        }

        try {
            $pdo->beginTransaction();

            // Lock and inspect user balance
            $u_stmt = $pdo->prepare("SELECT id, balance FROM users WHERE id = :uid FOR UPDATE");
            $u_stmt->execute([':uid' => $user_id]);
            $userData = $u_stmt->fetch();
            $user_balance = (float)($userData['balance'] ?? 0);

            // Fetch won item details
            $w_stmt = $pdo->prepare("
                SELECT id, title, current_price, seller_id, winner_id, auction_code 
                FROM items 
                WHERE id = :item_id AND winner_id = :winner_id FOR UPDATE
            ");
            $w_stmt->execute([':item_id' => $won_item_id, ':winner_id' => $user_id]);
            $wonItem = $w_stmt->fetch();

            if (!$wonItem) {
                throw new RuntimeException("Auction lot not found or you are not registered as the winning bidder.");
            }

            $hammer_price = (float)$wonItem['current_price'];
            if ($user_balance < $hammer_price) {
                throw new RuntimeException("Insufficient wallet balance (Available: Rs. " . number_format($user_balance, 2) . "). Please top up your wallet first.");
            }

            // Deduct from buyer wallet
            $new_balance = $user_balance - $hammer_price;
            $pdo->prepare("UPDATE users SET balance = :new_bal, updated_at = NOW() WHERE id = :uid")
                ->execute([':new_bal' => $new_balance, ':uid' => $user_id]);

            // Check or insert order
            $chk_ord = $pdo->prepare("SELECT id FROM orders WHERE auction_id = :aid AND buyer_id = :bid LIMIT 1");
            $chk_ord->execute([':aid' => $won_item_id, ':bid' => $user_id]);
            $existing_order = $chk_ord->fetch();

            if ($existing_order) {
                $pdo->prepare("UPDATE orders SET payment_status = 'PAID', updated_at = NOW() WHERE id = :oid")
                    ->execute([':oid' => $existing_order['id']]);
            } else {
                $ord_no = 'ORD-' . strtoupper(substr(uniqid(), -8));
                $ins_ord = $pdo->prepare("
                    INSERT INTO orders (order_number, auction_id, buyer_id, seller_id, hammer_price, platform_fee, shipping_fee, total_amount, payment_status, shipping_status, created_at)
                    VALUES (:ord_no, :aid, :bid, :sid, :hammer, 0.00, 0.00, :hammer, 'PAID', 'PROCESSING', NOW())
                ");
                $ins_ord->execute([
                    ':ord_no' => $ord_no,
                    ':aid'    => $won_item_id,
                    ':bid'    => $user_id,
                    ':sid'    => $wonItem['seller_id'],
                    ':hammer' => $hammer_price,
                ]);
            }

            // Record transaction
            $ins_tx = $pdo->prepare("
                INSERT INTO transactions (user_id, type, amount, balance_before, balance_after, item_id, description, status, created_at)
                VALUES (:uid, 'bid_payment', :neg_amount, :bal_before, :bal_after, :item_id, :desc, 'completed', NOW())
            ");
            $ins_tx->execute([
                ':uid'        => $user_id,
                ':neg_amount' => -$hammer_price,
                ':bal_before' => $user_balance,
                ':bal_after'  => $new_balance,
                ':item_id'    => $won_item_id,
                ':desc'       => "Settled payment for won auction #{$wonItem['auction_code']} ({$wonItem['title']})",
            ]);

            // Add notification
            $pdo->prepare("
                INSERT INTO notifications (user_id, type, title, message, link, icon, created_at)
                VALUES (:uid, 'payment', 'Payment Settled Successfully!', :msg, 'won_auctions.php', 'fa-receipt', NOW())
            ")->execute([
                ':uid' => $user_id,
                ':msg' => "Payment of Rs. " . number_format($hammer_price, 2) . " confirmed for {$wonItem['title']}. Seller has been alerted for dispatch.",
            ]);

            $pdo->commit();
            $_SESSION['flash_success'] = "Payment of Rs. " . number_format($hammer_price, 2) . " completed successfully! Order is now in processing.";
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $_SESSION['flash_error'] = "Payment Failed: " . $e->getMessage();
        }

        header("Location: dashboard.php");
        exit();
    }
}

// -----------------------------------------------------------------------------
// 5. Fetch Key Metrics & Statistics for Top Dashboard Cards
// -----------------------------------------------------------------------------

// Metric 1: Total Active Bids
$stmt = $pdo->prepare("
    SELECT COUNT(DISTINCT b.item_id)
    FROM bids b
    INNER JOIN items i ON b.item_id = i.id
    WHERE b.user_id = :uid
      AND i.status = 'active'
      AND i.end_time > NOW()
      AND (i.is_deleted = 0 OR i.is_deleted IS NULL)
");
$stmt->execute([':uid' => $user_id]);
$metric_active_bids = (int)$stmt->fetchColumn();

// Metric 2: Auctions Won
$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM items i
    WHERE i.winner_id = :uid
");
$stmt->execute([':uid' => $user_id]);
$metric_auctions_won = (int)$stmt->fetchColumn();

// Metric 3: Total Spending / Tied-Up in Active Bids & User Wallet Balance
$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(user_top.max_bid), 0) FROM (
        SELECT b.item_id, MAX(b.bid_amount) as max_bid
        FROM bids b
        INNER JOIN items i ON b.item_id = i.id
        WHERE b.user_id = :uid 
          AND i.status = 'active' 
          AND i.end_time > NOW()
          AND (i.is_deleted = 0 OR i.is_deleted IS NULL)
        GROUP BY b.item_id
    ) as user_top
");
$stmt->execute([':uid' => $user_id]);
$metric_tied_up_amount = (float)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT balance FROM users WHERE id = :uid LIMIT 1");
$stmt->execute([':uid' => $user_id]);
$user_wallet_balance = (float)($stmt->fetchColumn() ?: 0.00);

// Metric 4: Items Listed by User (Active listings vs Sold items)
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_listed,
        COALESCE(SUM(CASE WHEN status = 'active' AND end_time > NOW() THEN 1 ELSE 0 END), 0) as active_listed,
        COALESCE(SUM(CASE WHEN status = 'sold' OR (status = 'ended' AND winner_id IS NOT NULL) THEN 1 ELSE 0 END), 0) as sold_listed
    FROM items
    WHERE seller_id = :uid AND (is_deleted = 0 OR is_deleted IS NULL)
");
$stmt->execute([':uid' => $user_id]);
$seller_metrics = $stmt->fetch() ?: ['total_listed' => 0, 'active_listed' => 0, 'sold_listed' => 0];

// Metric 5: Watchlist / Saved Items Count
$stmt = $pdo->prepare("SELECT COUNT(*) FROM wishlist WHERE user_id = :uid");
$stmt->execute([':uid' => $user_id]);
$metric_watchlist_count = (int)$stmt->fetchColumn();

// -----------------------------------------------------------------------------
// 6. Fetch Datasets for Core Sections
// -----------------------------------------------------------------------------

// Section 1: "My Active Bids"
$stmt = $pdo->prepare("
    SELECT 
        i.id,
        i.auction_code,
        i.title,
        i.featured_image,
        i.current_price,
        i.starting_price,
        i.min_increment,
        i.end_time,
        i.bid_count,
        c.name as category_name,
        ub.user_max_bid,
        ub.last_bid_time,
        (
            SELECT b2.user_id 
            FROM bids b2 
            WHERE b2.item_id = i.id 
            ORDER BY b2.bid_amount DESC, b2.id DESC 
            LIMIT 1
        ) as leader_user_id
    FROM items i
    INNER JOIN (
        SELECT item_id, MAX(bid_amount) as user_max_bid, MAX(created_at) as last_bid_time
        FROM bids
        WHERE user_id = :uid
        GROUP BY item_id
    ) ub ON i.id = ub.item_id
    LEFT JOIN categories c ON i.category_id = c.id
    WHERE i.status = 'active' 
      AND i.end_time > NOW() 
      AND (i.is_deleted = 0 OR i.is_deleted IS NULL)
    ORDER BY i.end_time ASC
");
$stmt->execute([':uid' => $user_id]);
$active_bids_list = $stmt->fetchAll();

// Section 2: "Auctions Won / Ready for Checkout"
$stmt = $pdo->prepare("
    SELECT 
        i.id,
        i.auction_code,
        i.title,
        i.featured_image,
        i.current_price as winning_amount,
        i.end_time,
        c.name as category_name,
        u.name as seller_name,
        o.id as order_id,
        o.order_number,
        o.payment_status,
        o.shipping_status,
        o.total_amount
    FROM items i
    LEFT JOIN categories c ON i.category_id = c.id
    LEFT JOIN users u ON i.seller_id = u.id
    LEFT JOIN orders o ON o.auction_id = i.id AND o.buyer_id = :buyer_uid
    WHERE i.winner_id = :winner_uid
    ORDER BY i.end_time DESC
    LIMIT 10
");
$stmt->execute([':buyer_uid' => $user_id, ':winner_uid' => $user_id]);
$won_auctions_list = $stmt->fetchAll();

// Section 3: "My Listings / Seller Section"
$stmt = $pdo->prepare("
    SELECT 
        i.id,
        i.auction_code,
        i.title,
        i.featured_image,
        i.starting_price,
        i.current_price,
        i.bid_count,
        i.start_time,
        i.end_time,
        i.status,
        c.name as category_name,
        (SELECT MAX(bid_amount) FROM bids WHERE item_id = i.id) as highest_offer,
        (SELECT COUNT(DISTINCT user_id) FROM bids WHERE item_id = i.id) as unique_bidders
    FROM items i
    LEFT JOIN categories c ON i.category_id = c.id
    WHERE i.seller_id = :uid AND (i.is_deleted = 0 OR i.is_deleted IS NULL)
    ORDER BY i.created_at DESC
    LIMIT 15
");
$stmt->execute([':uid' => $user_id]);
$my_listings_list = $stmt->fetchAll();

// Section 4: "Recent Activity & Notifications Feed"
$stmt = $pdo->prepare("
    SELECT id, type, title, message, link, icon, is_read, created_at
    FROM notifications
    WHERE user_id = :uid OR is_global = 1
    ORDER BY created_at DESC
    LIMIT 12
");
$stmt->execute([':uid' => $user_id]);
$notifications_list = $stmt->fetchAll();

// Section 5: "Explore Trending Live Auctions"
$stmt = $pdo->prepare("
    SELECT i.id, i.auction_code, i.title, i.featured_image, i.current_price, i.starting_price, i.min_increment, i.end_time, i.bid_count, c.name as category_name
    FROM items i
    LEFT JOIN categories c ON i.category_id = c.id
    WHERE i.status = 'active' 
      AND i.end_time > NOW() 
      AND (i.is_deleted = 0 OR i.is_deleted IS NULL)
    ORDER BY i.end_time ASC
    LIMIT 6
");
$stmt->execute();
$explore_auctions = $stmt->fetchAll();

// Consume flash notifications
$flash_success = $_SESSION['flash_success'] ?? '';
$flash_error   = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// Include Global Site Header
include_once __DIR__ . '/../includes/header.php';
?>

<!-- Bootstrap 5 CSS & Font Awesome 6 Icons -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<!-- Chart.js v4 for Dynamic Bidding Analytics -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>

<!-- Dashboard Custom Design System & Dynamic Aesthetics -->
<style>
:root {
    --dash-primary: #047857;
    --dash-primary-dark: #065f46;
    --dash-primary-light: #ecfdf5;
    --dash-surface: #ffffff;
    --dash-surface-muted: #f8fafc;
    --dash-border: #e2e8f0;
    --dash-text: #0f172a;
    --dash-text-muted: #64748b;
    --dash-radius: 14px;
    --dash-shadow: 0 4px 18px rgba(0, 0, 0, 0.04);
}

html.dark-theme {
    --dash-primary: #10b981;
    --dash-primary-dark: #059669;
    --dash-primary-light: rgba(16, 185, 129, 0.12);
    --dash-surface: #1e293b;
    --dash-surface-muted: #0f172a;
    --dash-border: #334155;
    --dash-text: #f1f5f9;
    --dash-text-muted: #94a3b8;
    --dash-shadow: 0 4px 20px rgba(0, 0, 0, 0.35);
}

.dashboard-wrapper {
    max-width: 1320px;
    margin: 0 auto;
    padding: 20px 16px 80px;
    color: var(--dash-text);
}

/* Banner Header */
.dash-banner {
    background: linear-gradient(135deg, #047857 0%, #064e3b 100%);
    color: #ffffff;
    border-radius: var(--dash-radius);
    padding: 28px 32px;
    margin-bottom: 28px;
    box-shadow: 0 10px 25px -5px rgba(4, 120, 87, 0.35);
    position: relative;
    overflow: hidden;
}
.dash-banner::after {
    content: '';
    position: absolute;
    right: -40px;
    bottom: -40px;
    width: 200px;
    height: 200px;
    background: radial-gradient(circle, rgba(255,255,255,0.15) 0%, rgba(255,255,255,0) 70%);
    border-radius: 50%;
    pointer-events: none;
}

/* KPI / Metric Cards */
.kpi-card {
    background: var(--dash-surface);
    border: 1px solid var(--dash-border);
    border-radius: var(--dash-radius);
    padding: 22px;
    box-shadow: var(--dash-shadow);
    transition: transform 0.25s cubic-bezier(0.16, 1, 0.3, 1), box-shadow 0.25s ease;
    height: 100%;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    position: relative;
    overflow: hidden;
}
.kpi-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 28px rgba(0, 0, 0, 0.08);
}
.kpi-icon-badge {
    width: 50px;
    height: 50px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
}
.kpi-val {
    font-size: 28px;
    font-weight: 800;
    line-height: 1.1;
    color: var(--dash-text);
}
.kpi-label {
    font-size: 13px;
    font-weight: 600;
    color: var(--dash-text-muted);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Navigation Tabs */
.dash-nav-pills {
    border-bottom: 2px solid var(--dash-border);
    margin-bottom: 24px;
    gap: 8px;
}
.dash-nav-pills .nav-link {
    color: var(--dash-text-muted);
    font-weight: 600;
    font-size: 14px;
    padding: 12px 18px;
    border-radius: 8px 8px 0 0;
    border: none;
    border-bottom: 3px solid transparent;
    transition: all 0.2s ease;
    background: transparent;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}
.dash-nav-pills .nav-link:hover {
    color: var(--dash-primary);
    background: rgba(4, 120, 87, 0.05);
}
.dash-nav-pills .nav-link.active {
    color: var(--dash-primary) !important;
    background: transparent !important;
    border-bottom: 3px solid var(--dash-primary);
}

/* Panel Containers */
.dash-panel {
    background: var(--dash-surface);
    border: 1px solid var(--dash-border);
    border-radius: var(--dash-radius);
    padding: 24px;
    box-shadow: var(--dash-shadow);
    margin-bottom: 28px;
}

/* Tables & Lists */
.dash-table {
    width: 100%;
    margin-bottom: 0;
    vertical-align: middle;
}
.dash-table th {
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--dash-text-muted);
    background: var(--dash-surface-muted);
    border-bottom: 1px solid var(--dash-border);
    padding: 14px 16px;
}
.dash-table td {
    padding: 16px;
    border-bottom: 1px solid var(--dash-border);
    color: var(--dash-text);
}
.dash-table tr:hover td {
    background: rgba(4, 120, 87, 0.02);
}

/* Thumbnail */
.item-thumb-wrapper {
    width: 60px;
    height: 60px;
    border-radius: 10px;
    overflow: hidden;
    background: #f1f5f9;
    flex-shrink: 0;
    border: 1px solid var(--dash-border);
}
.item-thumb-wrapper img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s ease;
}
.item-thumb-wrapper:hover img {
    transform: scale(1.08);
}

/* Badges */
.badge-winning {
    background-color: rgba(16, 185, 129, 0.15) !important;
    color: #059669 !important;
    border: 1px solid rgba(16, 185, 129, 0.3) !important;
    font-weight: 700;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 12px;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
.badge-outbid {
    background-color: rgba(239, 68, 68, 0.12) !important;
    color: #dc2626 !important;
    border: 1px solid rgba(239, 68, 68, 0.25) !important;
    font-weight: 700;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 12px;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
.badge-timer {
    background: rgba(245, 158, 11, 0.12);
    color: #d97706;
    border: 1px solid rgba(245, 158, 11, 0.25);
    font-weight: 700;
    padding: 5px 10px;
    border-radius: 6px;
    font-size: 12px;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-variant-numeric: tabular-nums;
}
.badge-timer.urgent {
    background: rgba(239, 68, 68, 0.15);
    color: #ef4444;
    border-color: rgba(239, 68, 68, 0.3);
    animation: pulseSlow 2s infinite ease-in-out;
}
@keyframes pulseSlow {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.75; }
}

/* Buttons */
.btn-dash-primary {
    background: linear-gradient(135deg, #047857 0%, #065f46 100%);
    color: #ffffff !important;
    font-weight: 600;
    border: none;
    border-radius: 8px;
    padding: 8px 16px;
    box-shadow: 0 4px 10px rgba(4, 120, 87, 0.25);
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.btn-dash-primary:hover {
    filter: brightness(1.08);
    transform: translateY(-1px);
}
.btn-dash-outline {
    background: transparent;
    color: var(--dash-primary) !important;
    border: 1.5px solid var(--dash-primary);
    font-weight: 600;
    border-radius: 8px;
    padding: 7px 14px;
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.btn-dash-outline:hover {
    background: var(--dash-primary);
    color: #ffffff !important;
}

/* Notification Timeline Items */
.notif-item {
    display: flex;
    gap: 16px;
    padding: 16px;
    border-radius: 10px;
    border-bottom: 1px solid var(--dash-border);
    transition: background 0.2s;
}
.notif-item:last-child {
    border-bottom: none;
}
.notif-item:hover {
    background: var(--dash-surface-muted);
}
.notif-icon {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    flex-shrink: 0;
}

/* Analytics Chart Cards & Stat Mini-Widgets */
.analytics-stat-card {
    background: var(--dash-surface-muted);
    border: 1px solid var(--dash-border);
    border-radius: 12px;
    padding: 18px 20px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    transition: transform 0.2s, box-shadow 0.2s;
}
.analytics-stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(0,0,0,0.04);
}
.analytics-chart-box {
    position: relative;
    min-height: 330px;
    width: 100%;
}
.analytics-loader-overlay {
    position: absolute;
    inset: 0;
    background: rgba(255, 255, 255, 0.7);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    z-index: 10;
    border-radius: 12px;
    backdrop-filter: blur(2px);
    transition: opacity 0.2s ease;
}
html.dark-theme .analytics-loader-overlay {
    background: rgba(15, 23, 42, 0.75);
}
</style>

<div class="dashboard-wrapper">

    <!-- Flash Alerts -->
    <?php if (!empty($flash_success)): ?>
        <div class="alert alert-success alert-dismissible fade show d-flex align-items-center mb-4 shadow-sm border-0" role="alert" style="border-radius: 10px;">
            <i class="fa-solid fa-circle-check fs-5 me-2 text-success"></i>
            <div><?= htmlspecialchars($flash_success) ?></div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (!empty($flash_error)): ?>
        <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center mb-4 shadow-sm border-0" role="alert" style="border-radius: 10px;">
            <i class="fa-solid fa-triangle-exclamation fs-5 me-2 text-danger"></i>
            <div><?= htmlspecialchars($flash_error) ?></div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- ── 1. Top Welcome Banner & User Overview ── -->
    <div class="dash-banner d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <div class="d-flex align-items-center gap-2 mb-2">
                <span class="badge bg-white text-success fw-bold px-2 py-1 text-uppercase" style="font-size: 11px; letter-spacing: 0.5px;">
                    <i class="fa-solid fa-shield-halved me-1"></i> <?= htmlspecialchars($user_role) ?> Portal
                </span>
                <span class="text-white-50 small">
                    <i class="fa-regular fa-clock me-1"></i> Live: <span id="dashLiveClock"><?= date('D, M d, Y - h:i A') ?></span>
                </span>
            </div>
            <h1 class="h3 fw-bold mb-1">Welcome back, <?= htmlspecialchars($user_name) ?>!</h1>
            <p class="mb-0 text-white-50 small">
                Monitor your active bidding battles, analyze high vs low performance metrics, and settle won auction lots.
            </p>
        </div>

        <div class="d-flex align-items-center gap-2 flex-wrap">
            <button onclick="switchDashTab('analyticsTab')" class="btn btn-light fw-bold text-success px-3 py-2 shadow-sm" style="border-radius: 8px;">
                <i class="fa-solid fa-chart-line me-1"></i> Visual Analytics
            </button>
            <?php if (in_array($user_role, ['seller', 'admin'], true)): ?>
                <a href="add_items.php" class="btn btn-outline-light fw-bold px-3 py-2" style="border-radius: 8px;">
                    <i class="fa-solid fa-plus-circle me-1"></i> List New Item
                </a>
            <?php endif; ?>
            <a href="wallet.php" class="btn btn-outline-light fw-semibold px-3 py-2" style="border-radius: 8px;">
                <i class="fa-solid fa-wallet me-1"></i> Wallet: Rs. <?= number_format($user_wallet_balance, 2) ?>
            </a>
            <!-- Quick Role Switcher for Developer Testing -->
            <div class="dropdown">
                <button class="btn btn-sm btn-outline-light dropdown-toggle px-2 py-2" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Preview role perspective">
                    <i class="fa-solid fa-masks-theater"></i> View
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow">
                    <li><h6 class="dropdown-header">Preview Dashboard As:</h6></li>
                    <li><a class="dropdown-item <?= $user_role === 'buyer' ? 'active' : '' ?>" href="dashboard.php?switch_role=buyer"><i class="fa-solid fa-user me-2"></i> Buyer Mode</a></li>
                    <li><a class="dropdown-item <?= $user_role === 'seller' ? 'active' : '' ?>" href="dashboard.php?switch_role=seller"><i class="fa-solid fa-store me-2"></i> Seller Mode</a></li>
                    <li><a class="dropdown-item <?= $user_role === 'admin' ? 'active' : '' ?>" href="dashboard.php?switch_role=admin"><i class="fa-solid fa-user-shield me-2"></i> Admin Mode</a></li>
                </ul>
            </div>
        </div>
    </div>

    <!-- ── 2. Top Metric Cards (5 Key KPIs) ── -->
    <div class="row g-3 mb-4">
        <!-- Card 1: Total Active Bids -->
        <div class="col-xl-custom col-lg-4 col-md-6 col-12" style="flex: 1 1 200px;">
            <div class="kpi-card">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <div class="kpi-label">Active Bids</div>
                        <div class="kpi-val mt-1"><?= number_format($metric_active_bids) ?></div>
                    </div>
                    <div class="kpi-icon-badge" style="background: rgba(4, 120, 87, 0.12); color: #047857;">
                        <i class="fa-solid fa-gavel"></i>
                    </div>
                </div>
                <div class="d-flex justify-content-between align-items-center pt-2 border-top" style="border-color: var(--dash-border) !important;">
                    <span class="small text-muted">Ongoing auctions</span>
                    <a href="#activeBidsTab" onclick="switchDashTab('activeBidsTab')" class="small text-success fw-bold text-decoration-none">
                        View bids →
                    </a>
                </div>
            </div>
        </div>

        <!-- Card 2: Auctions Won -->
        <div class="col-xl-custom col-lg-4 col-md-6 col-12" style="flex: 1 1 200px;">
            <div class="kpi-card">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <div class="kpi-label">Auctions Won</div>
                        <div class="kpi-val mt-1"><?= number_format($metric_auctions_won) ?></div>
                    </div>
                    <div class="kpi-icon-badge" style="background: rgba(245, 158, 11, 0.15); color: #d97706;">
                        <i class="fa-solid fa-trophy"></i>
                    </div>
                </div>
                <div class="d-flex justify-content-between align-items-center pt-2 border-top" style="border-color: var(--dash-border) !important;">
                    <span class="small text-muted">Won lots</span>
                    <a href="#wonAuctionsTab" onclick="switchDashTab('wonAuctionsTab')" class="small text-warning fw-bold text-decoration-none">
                        Checkout →
                    </a>
                </div>
            </div>
        </div>

        <!-- Card 3: Total Spending / Tied-Up in Bids -->
        <div class="col-xl-custom col-lg-4 col-md-6 col-12" style="flex: 1 1 200px;">
            <div class="kpi-card">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <div class="kpi-label">Tied-Up in Bids</div>
                        <div class="kpi-val mt-1" style="font-size: 22px;">Rs. <?= number_format($metric_tied_up_amount) ?></div>
                    </div>
                    <div class="kpi-icon-badge" style="background: rgba(59, 130, 246, 0.12); color: #2563eb;">
                        <i class="fa-solid fa-lock"></i>
                    </div>
                </div>
                <div class="d-flex justify-content-between align-items-center pt-2 border-top" style="border-color: var(--dash-border) !important;">
                    <span class="small text-muted">Wallet: Rs. <?= number_format($user_wallet_balance, 0) ?></span>
                    <a href="wallet.php" class="small text-primary fw-bold text-decoration-none">
                        Top up →
                    </a>
                </div>
            </div>
        </div>

        <!-- Card 4: Items Listed by User -->
        <div class="col-xl-custom col-lg-6 col-md-6 col-12" style="flex: 1 1 200px;">
            <div class="kpi-card">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <div class="kpi-label">My Listings</div>
                        <div class="kpi-val mt-1"><?= number_format((int)$seller_metrics['total_listed']) ?></div>
                    </div>
                    <div class="kpi-icon-badge" style="background: rgba(139, 92, 246, 0.12); color: #7c3aed;">
                        <i class="fa-solid fa-boxes-stacked"></i>
                    </div>
                </div>
                <div class="d-flex justify-content-between align-items-center pt-2 border-top" style="border-color: var(--dash-border) !important;">
                    <span class="small text-muted"><?= $seller_metrics['active_listed'] ?> Live | <?= $seller_metrics['sold_listed'] ?> Sold</span>
                    <a href="#myListingsTab" onclick="switchDashTab('myListingsTab')" class="small fw-bold text-decoration-none" style="color: #7c3aed;">
                        Manage →
                    </a>
                </div>
            </div>
        </div>

        <!-- Card 5: Watchlist Items Count -->
        <div class="col-xl-custom col-lg-6 col-md-6 col-12" style="flex: 1 1 200px;">
            <div class="kpi-card">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <div class="kpi-label">Watchlist</div>
                        <div class="kpi-val mt-1"><?= number_format($metric_watchlist_count) ?></div>
                    </div>
                    <div class="kpi-icon-badge" style="background: rgba(244, 63, 94, 0.12); color: #e11d48;">
                        <i class="fa-solid fa-heart"></i>
                    </div>
                </div>
                <div class="d-flex justify-content-between align-items-center pt-2 border-top" style="border-color: var(--dash-border) !important;">
                    <span class="small text-muted">Saved favorites</span>
                    <a href="wishlist.php" class="small text-danger fw-bold text-decoration-none">
                        Saved items →
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- ── 3. Interactive Section Tabs ── -->
    <ul class="nav dash-nav-pills" id="dashboardTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="active-bids-tab" data-bs-toggle="tab" data-bs-target="#activeBidsTab" type="button" role="tab" aria-selected="true">
                <i class="fa-solid fa-gavel"></i> My Active Bids
                <span class="badge rounded-pill bg-success-subtle text-success ms-1"><?= count($active_bids_list) ?></span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="analytics-tab" data-bs-toggle="tab" data-bs-target="#analyticsTab" type="button" role="tab" aria-selected="false">
                <i class="fa-solid fa-chart-pie text-success"></i> Bidding Analytics
                <span class="badge rounded-pill bg-primary-subtle text-primary ms-1"><i class="fa-solid fa-bolt"></i> Live</span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="won-auctions-tab" data-bs-toggle="tab" data-bs-target="#wonAuctionsTab" type="button" role="tab" aria-selected="false">
                <i class="fa-solid fa-trophy"></i> Won & Ready for Checkout
                <span class="badge rounded-pill bg-warning-subtle text-warning-emphasis ms-1"><?= count($won_auctions_list) ?></span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="my-listings-tab" data-bs-toggle="tab" data-bs-target="#myListingsTab" type="button" role="tab" aria-selected="false">
                <i class="fa-solid fa-store"></i> Seller Hub (My Listings)
                <span class="badge rounded-pill bg-primary-subtle text-primary ms-1"><?= count($my_listings_list) ?></span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="notifications-tab" data-bs-toggle="tab" data-bs-target="#notificationsTab" type="button" role="tab" aria-selected="false">
                <i class="fa-regular fa-bell"></i> Activity & Alerts
                <span class="badge rounded-pill bg-secondary-subtle text-secondary ms-1"><?= count($notifications_list) ?></span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="explore-tab" data-bs-toggle="tab" data-bs-target="#exploreTab" type="button" role="tab" aria-selected="false">
                <i class="fa-solid fa-fire text-danger"></i> Explore Live Auctions
            </button>
        </li>
    </ul>

    <!-- ── 4. Tab Content Areas ── -->
    <div class="tab-content" id="dashboardTabsContent">

        <!-- =============================================================== -->
        <!-- TAB 1: MY ACTIVE BIDS                                           -->
        <!-- =============================================================== -->
        <div class="tab-pane fade show active" id="activeBidsTab" role="tabpanel" aria-labelledby="active-bids-tab">
            <div class="dash-panel">
                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                    <div>
                        <h2 class="h5 fw-bold mb-1">My Active Bids</h2>
                        <p class="text-muted small mb-0">Auctions you have placed bids on that are currently live.</p>
                    </div>
                    <div>
                        <a href="../live_auction.php" class="btn-dash-outline btn-sm">
                            <i class="fa-solid fa-magnifying-glass"></i> Explore More Auctions
                        </a>
                    </div>
                </div>

                <?php if (!empty($active_bids_list)): ?>
                    <div class="table-responsive">
                        <table class="dash-table">
                            <thead>
                                <tr>
                                    <th>Item Details</th>
                                    <th>Current Highest</th>
                                    <th>Your Highest Bid</th>
                                    <th>Status</th>
                                    <th>Time Remaining</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($active_bids_list as $bidItem): 
                                    $is_winning = ((int)$bidItem['leader_user_id'] === $user_id);
                                    $curr_amt   = (float)$bidItem['current_price'];
                                    $user_amt   = (float)$bidItem['user_max_bid'];
                                    $min_inc    = (float)($bidItem['min_increment'] > 0 ? $bidItem['min_increment'] : 100.00);
                                    $next_min   = $curr_amt + $min_inc;
                                    $img_src    = get_dash_image_url($bidItem['featured_image']);
                                    $item_link  = "../live_auction.php?id=" . (int)$bidItem['id'];
                                ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center gap-3">
                                                <div class="item-thumb-wrapper">
                                                    <img src="<?= htmlspecialchars($img_src) ?>" alt="<?= htmlspecialchars($bidItem['title']) ?>" onerror="this.onerror=null; this.src='https://images.unsplash.com/photo-1523275335684-37898b6baf30?auto=format&fit=crop&w=150&q=80';">
                                                </div>
                                                <div>
                                                    <a href="<?= $item_link ?>" class="fw-bold text-decoration-none d-block text-dark-emphasis mb-1">
                                                        <?= htmlspecialchars($bidItem['title']) ?>
                                                    </a>
                                                    <div class="d-flex align-items-center gap-2 small text-muted">
                                                        <span class="badge bg-light text-dark border"><?= htmlspecialchars($bidItem['category_name'] ?? 'General') ?></span>
                                                        <span>Code: <span class="fw-semibold">#<?= htmlspecialchars($bidItem['auction_code']) ?></span></span>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="fw-bold text-success">Rs. <?= number_format($curr_amt, 2) ?></div>
                                            <div class="small text-muted"><?= (int)$bidItem['bid_count'] ?> bids placed</div>
                                        </td>
                                        <td>
                                            <div class="fw-bold">Rs. <?= number_format($user_amt, 2) ?></div>
                                            <div class="small text-muted"><?= date('M d, h:i A', strtotime($bidItem['last_bid_time'])) ?></div>
                                        </td>
                                        <td>
                                            <?php if ($is_winning): ?>
                                                <span class="badge-winning">
                                                    <i class="fa-solid fa-circle-check"></i> Winning
                                                </span>
                                            <?php else: ?>
                                                <span class="badge-outbid">
                                                    <i class="fa-solid fa-circle-exclamation"></i> Outbid
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge-timer" data-countdown="<?= htmlspecialchars($bidItem['end_time']) ?>">
                                                <i class="fa-regular fa-clock"></i> <span>Calculating...</span>
                                            </span>
                                        </td>
                                        <td class="text-end">
                                            <button type="button" 
                                                    class="btn btn-sm btn-dash-primary quick-raise-btn"
                                                    data-id="<?= (int)$bidItem['id'] ?>"
                                                    data-title="<?= htmlspecialchars($bidItem['title']) ?>"
                                                    data-code="<?= htmlspecialchars($bidItem['auction_code']) ?>"
                                                    data-current="<?= $curr_amt ?>"
                                                    data-min="<?= $next_min ?>"
                                                    data-thumb="<?= htmlspecialchars($img_src) ?>"
                                                    onclick="openRaiseBidModal(this)">
                                                <i class="fa-solid fa-arrow-up-right-dots"></i> Raise Bid
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <div class="mb-3 text-muted" style="font-size: 48px;">
                            <i class="fa-solid fa-gavel text-secondary-subtle"></i>
                        </div>
                        <h3 class="h6 fw-bold">No Active Bids Found</h3>
                        <p class="text-muted small mx-auto" style="max-width: 420px;">
                            You are not currently participating in any active auctions. Explore the live auction lots and start placing your bids today!
                        </p>
                        <a href="../live_auction.php" class="btn-dash-primary mt-2">
                            <i class="fa-solid fa-fire"></i> Browse Live Auctions
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- =============================================================== -->
        <!-- TAB 2: DYNAMIC BIDDING ANALYTICS (Chart.js + AJAX Visualizer)   -->
        <!-- =============================================================== -->
        <div class="tab-pane fade" id="analyticsTab" role="tabpanel" aria-labelledby="analytics-tab">
            <div class="dash-panel position-relative">
                
                <!-- Controls Bar -->
                <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
                    <div>
                        <div class="d-flex align-items-center gap-2">
                            <h2 class="h5 fw-bold mb-0">Bidding Performance Analytics</h2>
                            <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1" style="font-size: 11px;">
                                <i class="fa-solid fa-circle-dot me-1"></i> Real-time Feed
                            </span>
                        </div>
                        <p class="text-muted small mb-0 mt-1">
                            Visual comparison of high-performing vs low-performing bids, bid volume velocity, and win rate.
                        </p>
                    </div>

                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <div class="form-check form-switch me-2 mb-0 d-flex align-items-center gap-2">
                            <input class="form-check-input mt-0" type="checkbox" role="switch" id="autoRefreshAnalytics" checked>
                            <label class="form-check-label small text-muted" for="autoRefreshAnalytics">Auto-refresh (30s)</label>
                        </div>

                        <button type="button" class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center gap-2" id="btnRefreshAnalytics" onclick="loadBidAnalytics(true)">
                            <i class="fa-solid fa-rotate" id="refreshAnalyticsIcon"></i>
                            <span>Refresh</span>
                        </button>

                        <span class="badge bg-light text-muted border py-2 px-3 small" id="analyticsLastUpdatedText">
                            <i class="fa-regular fa-clock me-1"></i> Syncing...
                        </span>
                    </div>
                </div>

                <!-- Error Alert Box -->
                <div class="alert alert-danger alert-dismissible fade show d-none mb-3" id="analyticsErrorAlert" role="alert">
                    <i class="fa-solid fa-triangle-exclamation me-2"></i>
                    <span id="analyticsErrorMessage">Failed to load analytics data.</span>
                    <button type="button" class="btn btn-sm btn-outline-danger ms-3" onclick="loadBidAnalytics(true)">Retry</button>
                </div>

                <!-- 1. Summary Stat Mini-Cards -->
                <div class="row g-3 mb-4">
                    <div class="col-lg-3 col-sm-6 col-12">
                        <div class="analytics-stat-card">
                            <div>
                                <div class="text-muted small fw-bold text-uppercase">Active Bids</div>
                                <div class="h4 fw-bold mb-0 text-success mt-1" id="statActiveBids">--</div>
                                <div class="small text-muted mt-1" id="statActiveSub">Ongoing lots</div>
                            </div>
                            <div class="kpi-icon-badge" style="background: rgba(16, 185, 129, 0.12); color: #059669;">
                                <i class="fa-solid fa-gavel"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-sm-6 col-12">
                        <div class="analytics-stat-card">
                            <div>
                                <div class="text-muted small fw-bold text-uppercase">Highest Bid</div>
                                <div class="h4 fw-bold mb-0 text-dark-emphasis mt-1" id="statHighestBid">Rs. 0.00</div>
                                <div class="small text-muted mt-1">Sab se ziyada bid</div>
                            </div>
                            <div class="kpi-icon-badge" style="background: rgba(59, 130, 246, 0.12); color: #2563eb;">
                                <i class="fa-solid fa-chart-line"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-sm-6 col-12">
                        <div class="analytics-stat-card">
                            <div>
                                <div class="text-muted small fw-bold text-uppercase">Lowest Bid</div>
                                <div class="h4 fw-bold mb-0 text-dark-emphasis mt-1" id="statLowestBid">Rs. 0.00</div>
                                <div class="small text-muted mt-1">Sab se kam bid</div>
                            </div>
                            <div class="kpi-icon-badge" style="background: rgba(245, 158, 11, 0.15); color: #d97706;">
                                <i class="fa-solid fa-arrow-down-short-wide"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-sm-6 col-12">
                        <div class="analytics-stat-card">
                            <div>
                                <div class="text-muted small fw-bold text-uppercase">Average Bid</div>
                                <div class="h4 fw-bold mb-0 text-primary mt-1" id="statAverageBid">Rs. 0.00</div>
                                <div class="small text-muted mt-1">Mean portfolio offer</div>
                            </div>
                            <div class="kpi-icon-badge" style="background: rgba(139, 92, 246, 0.12); color: #7c3aed;">
                                <i class="fa-solid fa-calculator"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 2. Dual Visual Charts -->
                <div class="row g-4">
                    <!-- Chart 1: Bar Chart (Top Active vs Lowest Bids) -->
                    <div class="col-lg-8 col-12">
                        <div class="p-3 border rounded h-100" style="background: var(--dash-surface);">
                            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                                <div>
                                    <h6 class="fw-bold mb-0">
                                        <i class="fa-solid fa-chart-simple text-success me-1"></i> Top Active vs. Lowest Performing Lots
                                    </h6>
                                    <span class="small text-muted">Comparison of highest bid lots against entry-level / lowest active bids</span>
                                </div>
                                <div class="btn-group btn-group-sm" role="group">
                                    <button type="button" class="btn btn-outline-secondary active" id="btnMetricAmount" onclick="toggleChartMetric('amount')">
                                        <i class="fa-solid fa-money-bill me-1"></i> Amount (Rs.)
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary" id="btnMetricCount" onclick="toggleChartMetric('count')">
                                        <i class="fa-solid fa-hashtag me-1"></i> Total Bids
                                    </button>
                                </div>
                            </div>
                            <div class="analytics-chart-box">
                                <div class="analytics-loader-overlay d-none" id="loaderBarChart">
                                    <div class="spinner-border text-success" role="status"></div>
                                    <div class="small text-muted mt-2 fw-semibold">Updating performance graph...</div>
                                </div>
                                <canvas id="bidComparisonChart"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- Chart 2: Doughnut / Pie Chart (Bid Status & Volume Distribution) -->
                    <div class="col-lg-4 col-12">
                        <div class="p-3 border rounded h-100" style="background: var(--dash-surface);">
                            <div class="mb-3">
                                <h6 class="fw-bold mb-0">
                                    <i class="fa-solid fa-chart-pie text-primary me-1"></i> Bid Status & Outcome Distribution
                                </h6>
                                <span class="small text-muted">Winning vs Outbid vs Won vs Ending Soon</span>
                            </div>
                            <div class="analytics-chart-box d-flex align-items-center justify-content-center">
                                <div class="analytics-loader-overlay d-none" id="loaderDoughnutChart">
                                    <div class="spinner-border text-primary" role="status"></div>
                                </div>
                                <canvas id="bidDistributionChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 3. Detailed Data Table (Top 5 vs Lowest 5 Breakdown) -->
                <div class="mt-4 pt-3 border-top">
                    <div class="row g-3">
                        <div class="col-md-6 col-12">
                            <div class="p-3 border rounded" style="background: var(--dash-surface-muted);">
                                <h6 class="fw-bold text-success mb-2">
                                    <i class="fa-solid fa-fire me-1"></i> Top 5 High-Performing Lots (Highest Bids)
                                </h6>
                                <div class="table-responsive">
                                    <table class="table table-sm table-borderless mb-0" id="tableTopItems">
                                        <thead>
                                            <tr class="text-muted small border-bottom">
                                                <th>Lot Title</th>
                                                <th>Code</th>
                                                <th class="text-end">Amount</th>
                                                <th class="text-end">Bids</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr><td colspan="4" class="text-center text-muted small py-3">Loading top lots...</td></tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6 col-12">
                            <div class="p-3 border rounded" style="background: var(--dash-surface-muted);">
                                <h6 class="fw-bold text-warning mb-2">
                                    <i class="fa-solid fa-snowflake me-1"></i> Lowest 5 / Least Active Lots (Entry Bids)
                                </h6>
                                <div class="table-responsive">
                                    <table class="table table-sm table-borderless mb-0" id="tableLowItems">
                                        <thead>
                                            <tr class="text-muted small border-bottom">
                                                <th>Lot Title</th>
                                                <th>Code</th>
                                                <th class="text-end">Amount</th>
                                                <th class="text-end">Bids</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr><td colspan="4" class="text-center text-muted small py-3">Loading lowest lots...</td></tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- =============================================================== -->
        <!-- TAB 3: AUCTIONS WON & CHECKOUT                                  -->
        <!-- =============================================================== -->
        <div class="tab-pane fade" id="wonAuctionsTab" role="tabpanel" aria-labelledby="won-auctions-tab">
            <div class="dash-panel">
                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                    <div>
                        <h2 class="h5 fw-bold mb-1">Auctions Won & Ready for Checkout</h2>
                        <p class="text-muted small mb-0">Items where you placed the winning hammer bid. Settle payment or view purchase receipts.</p>
                    </div>
                    <div>
                        <a href="won_auctions.php" class="btn-dash-outline btn-sm">
                            <i class="fa-solid fa-box-check"></i> Orders & Logistics Hub
                        </a>
                    </div>
                </div>

                <?php if (!empty($won_auctions_list)): ?>
                    <div class="table-responsive">
                        <table class="dash-table">
                            <thead>
                                <tr>
                                    <th>Won Lot</th>
                                    <th>Hammer Price</th>
                                    <th>Seller</th>
                                    <th>Payment Status</th>
                                    <th>Order Info</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($won_auctions_list as $won): 
                                    $is_paid   = (strtoupper($won['payment_status'] ?? '') === 'PAID');
                                    $hammer    = (float)$won['winning_amount'];
                                    $img_src   = get_dash_image_url($won['featured_image']);
                                    $order_num = $won['order_number'] ?? ('ORD-' . strtoupper(substr(md5((string)$won['id']), 0, 8)));
                                ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center gap-3">
                                                <div class="item-thumb-wrapper">
                                                    <img src="<?= htmlspecialchars($img_src) ?>" alt="<?= htmlspecialchars($won['title']) ?>" onerror="this.onerror=null; this.src='https://images.unsplash.com/photo-1523275335684-37898b6baf30?auto=format&fit=crop&w=150&q=80';">
                                                </div>
                                                <div>
                                                    <div class="fw-bold text-dark-emphasis mb-1"><?= htmlspecialchars($won['title']) ?></div>
                                                    <div class="small text-muted">
                                                        <span>Code: #<?= htmlspecialchars($won['auction_code']) ?></span> &bull;
                                                        <span>Won: <?= date('d M Y', strtotime($won['end_time'])) ?></span>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="fw-bold text-success fs-6">Rs. <?= number_format($hammer, 2) ?></div>
                                        </td>
                                        <td>
                                            <div class="fw-semibold small"><?= htmlspecialchars($won['seller_name'] ?? 'Verified Seller') ?></div>
                                        </td>
                                        <td>
                                            <?php if ($is_paid): ?>
                                                <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
                                                    <i class="fa-solid fa-circle-check me-1"></i> Paid & Settled
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle px-2 py-1">
                                                    <i class="fa-solid fa-clock me-1"></i> Payment Required
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="small fw-semibold"><?= htmlspecialchars($order_num) ?></div>
                                            <div class="small text-muted"><?= htmlspecialchars($won['shipping_status'] ?? 'Awaiting payment') ?></div>
                                        </td>
                                        <td class="text-end">
                                            <?php if ($is_paid): ?>
                                                <button type="button" 
                                                        class="btn btn-sm btn-dash-outline"
                                                        data-id="<?= (int)$won['id'] ?>"
                                                        data-title="<?= htmlspecialchars($won['title']) ?>"
                                                        data-code="<?= htmlspecialchars($won['auction_code']) ?>"
                                                        data-hammer="<?= $hammer ?>"
                                                        data-orderno="<?= htmlspecialchars($order_num) ?>"
                                                        data-seller="<?= htmlspecialchars($won['seller_name'] ?? 'Platform Verified') ?>"
                                                        data-date="<?= date('d M Y, h:i A', strtotime($won['end_time'])) ?>"
                                                        onclick="openReceiptModal(this)">
                                                    <i class="fa-solid fa-receipt me-1"></i> View Receipt
                                                </button>
                                            <?php else: ?>
                                                <button type="button" 
                                                        class="btn btn-sm btn-dash-primary"
                                                        data-id="<?= (int)$won['id'] ?>"
                                                        data-title="<?= htmlspecialchars($won['title']) ?>"
                                                        data-hammer="<?= $hammer ?>"
                                                        onclick="openQuickPayModal(this)">
                                                    <i class="fa-solid fa-credit-card me-1"></i> Pay Now
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <div class="mb-3 text-muted" style="font-size: 48px;">
                            <i class="fa-solid fa-trophy text-secondary-subtle"></i>
                        </div>
                        <h3 class="h6 fw-bold">No Won Auctions Yet</h3>
                        <p class="text-muted small mx-auto" style="max-width: 420px;">
                            When you win an auction lot, it will appear right here with instant 1-click checkout options and shipping tracking.
                        </p>
                        <a href="../live_auction.php" class="btn-dash-primary mt-2">
                            <i class="fa-solid fa-gavel"></i> Bid on Live Auctions
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- =============================================================== -->
        <!-- TAB 4: SELLER HUB / MY LISTINGS                                 -->
        <!-- =============================================================== -->
        <div class="tab-pane fade" id="myListingsTab" role="tabpanel" aria-labelledby="my-listings-tab">
            <div class="dash-panel">
                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                    <div>
                        <h2 class="h5 fw-bold mb-1">My Listings & Offers</h2>
                        <p class="text-muted small mb-0">Track performance, monitor incoming bids, and manage reserve prices on your auction items.</p>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="add_items.php" class="btn-dash-primary btn-sm">
                            <i class="fa-solid fa-plus-circle"></i> Add New Item
                        </a>
                        <a href="my_auctions.php" class="btn-dash-outline btn-sm">
                            <i class="fa-solid fa-list-check"></i> Seller Inventory
                        </a>
                    </div>
                </div>

                <?php if (!empty($my_listings_list)): ?>
                    <div class="table-responsive">
                        <table class="dash-table">
                            <thead>
                                <tr>
                                    <th>Item Details</th>
                                    <th>Bids Received</th>
                                    <th>Highest Offer</th>
                                    <th>Auction End Date</th>
                                    <th>Status</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($my_listings_list as $listing): 
                                    $img_src    = get_dash_image_url($listing['featured_image']);
                                    $top_offer  = (float)($listing['highest_offer'] ?: $listing['current_price']);
                                    $is_live    = ($listing['status'] === 'active' && strtotime($listing['end_time']) > time());
                                ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center gap-3">
                                                <div class="item-thumb-wrapper">
                                                    <img src="<?= htmlspecialchars($img_src) ?>" alt="<?= htmlspecialchars($listing['title']) ?>" onerror="this.onerror=null; this.src='https://images.unsplash.com/photo-1523275335684-37898b6baf30?auto=format&fit=crop&w=150&q=80';">
                                                </div>
                                                <div>
                                                    <a href="../live_auction.php?id=<?= (int)$listing['id'] ?>" class="fw-bold text-dark-emphasis text-decoration-none d-block mb-1">
                                                        <?= htmlspecialchars($listing['title']) ?>
                                                    </a>
                                                    <div class="small text-muted">
                                                        <span class="badge bg-light text-dark border"><?= htmlspecialchars($listing['category_name'] ?? 'General') ?></span>
                                                        <span>Code: #<?= htmlspecialchars($listing['auction_code']) ?></span>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="fw-bold fs-6"><?= (int)$listing['bid_count'] ?> bids</div>
                                            <div class="small text-muted"><?= (int)$listing['unique_bidders'] ?> unique bidders</div>
                                        </td>
                                        <td>
                                            <div class="fw-bold text-success fs-6">Rs. <?= number_format($top_offer, 2) ?></div>
                                            <div class="small text-muted">Starting: Rs. <?= number_format((float)$listing['starting_price'], 2) ?></div>
                                        </td>
                                        <td>
                                            <div class="fw-semibold small"><?= date('d M Y, h:i A', strtotime($listing['end_time'])) ?></div>
                                            <?php if ($is_live): ?>
                                                <span class="badge-timer mt-1" data-countdown="<?= htmlspecialchars($listing['end_time']) ?>">
                                                    <i class="fa-regular fa-clock"></i> <span>Calculating...</span>
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary-subtle text-secondary mt-1">Auction Concluded</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($is_live): ?>
                                                <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
                                                    <i class="fa-solid fa-circle-dot me-1"></i> Live
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1">
                                                    <?= ucfirst(htmlspecialchars($listing['status'])) ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <div class="btn-group btn-group-sm">
                                                <a href="../live_auction.php?id=<?= (int)$listing['id'] ?>" class="btn btn-outline-secondary" title="View Public Auction">
                                                    <i class="fa-solid fa-eye"></i>
                                                </a>
                                                <a href="edit_item.php?id=<?= (int)$listing['id'] ?>" class="btn btn-outline-primary" title="Edit Item Details">
                                                    <i class="fa-solid fa-pen-to-square"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <div class="mb-3 text-muted" style="font-size: 48px;">
                            <i class="fa-solid fa-boxes-stacked text-secondary-subtle"></i>
                        </div>
                        <h3 class="h6 fw-bold">No Items Listed Yet</h3>
                        <p class="text-muted small mx-auto" style="max-width: 420px;">
                            Turn your valuable assets into cash! List your items with custom reserves, real-time live bidding, and secure payments.
                        </p>
                        <a href="add_items.php" class="btn-dash-primary mt-2">
                            <i class="fa-solid fa-plus-circle"></i> Create Your First Listing
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- =============================================================== -->
        <!-- TAB 5: RECENT ACTIVITY & NOTIFICATIONS FEED                    -->
        <!-- =============================================================== -->
        <div class="tab-pane fade" id="notificationsTab" role="tabpanel" aria-labelledby="notifications-tab">
            <div class="dash-panel">
                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                    <div>
                        <h2 class="h5 fw-bold mb-1">Recent Activity & Notification Feed</h2>
                        <p class="text-muted small mb-0">Instant alerts on outbids, closing auctions, winning confirmations, and platform updates.</p>
                    </div>
                    <div>
                        <a href="notifications.php" class="btn-dash-outline btn-sm">
                            <i class="fa-solid fa-bell"></i> Full Notification Inbox
                        </a>
                    </div>
                </div>

                <?php if (!empty($notifications_list)): ?>
                    <div class="d-flex flex-column gap-1">
                        <?php foreach ($notifications_list as $notif): 
                            $type = strtolower($notif['type'] ?? 'system');
                            $bg_color = 'rgba(59, 130, 246, 0.12)';
                            $text_color = '#2563eb';
                            $icon_class = 'fa-solid fa-bell';

                            if ($type === 'outbid') {
                                $bg_color = 'rgba(239, 68, 68, 0.12)';
                                $text_color = '#dc2626';
                                $icon_class = 'fa-solid fa-triangle-exclamation';
                            } elseif ($type === 'bid') {
                                $bg_color = 'rgba(16, 185, 129, 0.12)';
                                $text_color = '#059669';
                                $icon_class = 'fa-solid fa-gavel';
                            } elseif (in_array($type, ['won', 'auction_win'], true)) {
                                $bg_color = 'rgba(245, 158, 11, 0.15)';
                                $text_color = '#d97706';
                                $icon_class = 'fa-solid fa-trophy';
                            } elseif ($type === 'payment') {
                                $bg_color = 'rgba(16, 185, 129, 0.15)';
                                $text_color = '#059669';
                                $icon_class = 'fa-solid fa-receipt';
                            }
                        ?>
                            <div class="notif-item align-items-center">
                                <div class="notif-icon" style="background: <?= $bg_color ?>; color: <?= $text_color ?>;">
                                    <i class="<?= $icon_class ?>"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="d-flex justify-content-between align-items-center flex-wrap">
                                        <div class="fw-bold fs-6"><?= htmlspecialchars($notif['title']) ?></div>
                                        <div class="small text-muted"><?= date('d M Y, h:i A', strtotime($notif['created_at'])) ?></div>
                                    </div>
                                    <div class="small text-secondary mt-1"><?= htmlspecialchars($notif['message']) ?></div>
                                </div>
                                <?php if (!empty($notif['link'])): ?>
                                    <div>
                                        <a href="<?= htmlspecialchars($notif['link']) ?>" class="btn btn-sm btn-outline-secondary">
                                            View <i class="fa-solid fa-arrow-right ms-1"></i>
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <div class="mb-3 text-muted" style="font-size: 48px;">
                            <i class="fa-regular fa-bell text-secondary-subtle"></i>
                        </div>
                        <h3 class="h6 fw-bold">No Notifications Yet</h3>
                        <p class="text-muted small mx-auto" style="max-width: 400px;">
                            You have no unread notifications or alerts. You will receive real-time updates here whenever you are outbid or an auction is ending.
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- =============================================================== -->
        <!-- TAB 6: EXPLORE LIVE AUCTIONS                                    -->
        <!-- =============================================================== -->
        <div class="tab-pane fade" id="exploreTab" role="tabpanel" aria-labelledby="explore-tab">
            <div class="dash-panel">
                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                    <div>
                        <h2 class="h5 fw-bold mb-1">Trending Live Auctions</h2>
                        <p class="text-muted small mb-0">High-demand asset lots closing soon across verified categories.</p>
                    </div>
                    <div>
                        <a href="../live_auction.php" class="btn-dash-primary btn-sm">
                            <i class="fa-solid fa-arrow-up-right-from-square"></i> Open Full Auction Catalog
                        </a>
                    </div>
                </div>

                <?php if (!empty($explore_auctions)): ?>
                    <div class="row g-4">
                        <?php foreach ($explore_auctions as $lot): 
                            $lot_img    = get_dash_image_url($lot['featured_image']);
                            $lot_price  = (float)($lot['current_price'] > 0 ? $lot['current_price'] : $lot['starting_price']);
                            $lot_min    = $lot_price + (float)($lot['min_increment'] > 0 ? $lot['min_increment'] : 100);
                            $lot_url    = "../live_auction.php?id=" . (int)$lot['id'];
                        ?>
                            <div class="col-lg-4 col-md-6 col-12">
                                <div class="card h-100 border shadow-sm" style="border-radius: 12px; overflow: hidden; background: var(--dash-surface);">
                                    <div style="height: 190px; overflow: hidden; position: relative; background: #f1f5f9;">
                                        <img src="<?= htmlspecialchars($lot_img) ?>" alt="<?= htmlspecialchars($lot['title']) ?>" style="width: 100%; height: 100%; object-fit: cover;" onerror="this.onerror=null; this.src='https://images.unsplash.com/photo-1523275335684-37898b6baf30?auto=format&fit=crop&w=400&q=80';">
                                        <span class="position-absolute top-0 end-0 m-2 badge bg-dark bg-opacity-75 px-2 py-1">
                                            <?= htmlspecialchars($lot['category_name'] ?? 'Auction') ?>
                                        </span>
                                    </div>
                                    <div class="card-body d-flex flex-column justify-content-between p-3">
                                        <div>
                                            <a href="<?= $lot_url ?>" class="fw-bold text-dark-emphasis text-decoration-none d-block mb-2">
                                                <?= htmlspecialchars($lot['title']) ?>
                                            </a>
                                            <div class="d-flex justify-content-between align-items-center mb-3">
                                                <div>
                                                    <div class="small text-muted">Current Bid</div>
                                                    <div class="fw-bold text-success fs-5">Rs. <?= number_format($lot_price, 2) ?></div>
                                                </div>
                                                <div class="text-end">
                                                    <div class="small text-muted">Total Bids</div>
                                                    <div class="fw-semibold"><?= (int)$lot['bid_count'] ?> bids</div>
                                                </div>
                                            </div>
                                        </div>

                                        <div>
                                            <div class="d-flex justify-content-between align-items-center mb-3 pt-2 border-top">
                                                <span class="badge-timer" data-countdown="<?= htmlspecialchars($lot['end_time']) ?>">
                                                    <i class="fa-regular fa-clock"></i> <span>Calculating...</span>
                                                </span>
                                                <span class="small text-muted">#<?= htmlspecialchars($lot['auction_code']) ?></span>
                                            </div>

                                            <button type="button" 
                                                    class="btn btn-sm btn-dash-primary w-100 quick-raise-btn"
                                                    data-id="<?= (int)$lot['id'] ?>"
                                                    data-title="<?= htmlspecialchars($lot['title']) ?>"
                                                    data-code="<?= htmlspecialchars($lot['auction_code']) ?>"
                                                    data-current="<?= $lot_price ?>"
                                                    data-min="<?= $lot_min ?>"
                                                    data-thumb="<?= htmlspecialchars($lot_img) ?>"
                                                    onclick="openRaiseBidModal(this)">
                                                <i class="fa-solid fa-gavel me-1"></i> Place Bid Now
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <div class="mb-3 text-muted fs-1"><i class="fa-solid fa-gavel"></i></div>
                        <h5>No Live Auctions Available</h5>
                        <p class="text-muted small">Check back soon for newly published lots.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </div> <!-- End Tab Content -->

</div> <!-- End Dashboard Wrapper -->

<!-- ======================================================================= -->
<!-- MODAL 1: QUICK RAISE BID                                                -->
<!-- ======================================================================= -->
<div class="modal fade" id="raiseBidModal" tabindex="-1" aria-labelledby="raiseBidModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px; overflow: hidden;">
            <div class="modal-header text-white" style="background: linear-gradient(135deg, #047857 0%, #064e3b 100%);">
                <h5 class="modal-title fw-bold" id="raiseBidModalLabel">
                    <i class="fa-solid fa-gavel me-2"></i> Place / Raise Bid
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="dashboard.php" method="POST" id="raiseBidForm">
                <input type="hidden" name="action" value="raise_bid">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                <input type="hidden" name="item_id" id="modalItemId" value="0">

                <div class="modal-body p-4">
                    <!-- Lot summary -->
                    <div class="d-flex align-items-center gap-3 p-3 rounded mb-3" style="background: var(--dash-surface-muted, #f8fafc); border: 1px solid #e2e8f0;">
                        <img src="" id="modalItemThumb" alt="" style="width: 55px; height: 55px; object-fit: cover; border-radius: 8px;">
                        <div>
                            <div class="fw-bold" id="modalItemTitle">Auction Title</div>
                            <div class="small text-muted">Lot Code: <span id="modalItemCode" class="fw-semibold">#</span></div>
                        </div>
                    </div>

                    <!-- Price status -->
                    <div class="row g-2 mb-3 text-center">
                        <div class="col-6">
                            <div class="p-2 border rounded">
                                <div class="small text-muted">Current Bid</div>
                                <div class="fw-bold text-dark fs-6" id="modalCurrentPrice">Rs. 0.00</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-2 border rounded" style="background: rgba(4, 120, 87, 0.06);">
                                <div class="small text-muted">Minimum Required</div>
                                <div class="fw-bold text-success fs-6" id="modalMinRequired">Rs. 0.00</div>
                            </div>
                        </div>
                    </div>

                    <!-- Preset increment pills -->
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted text-uppercase">Quick Increment</label>
                        <div class="d-flex gap-2 flex-wrap">
                            <button type="button" class="btn btn-sm btn-outline-secondary fw-semibold" onclick="addBidIncrement(500)">+ Rs. 500</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary fw-semibold" onclick="addBidIncrement(1000)">+ Rs. 1,000</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary fw-semibold" onclick="addBidIncrement(5000)">+ Rs. 5,000</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary fw-semibold" onclick="addBidIncrement(10000)">+ Rs. 10,000</button>
                        </div>
                    </div>

                    <!-- Bid input -->
                    <div class="mb-2">
                        <label for="modalBidInput" class="form-label small fw-bold text-muted text-uppercase">Your Bid Amount (Rs.)</label>
                        <div class="input-group input-group-lg">
                            <span class="input-group-text fw-bold">Rs.</span>
                            <input type="number" step="any" class="form-control fw-bold fs-5" name="bid_amount" id="modalBidInput" required>
                        </div>
                        <div class="form-text small text-danger d-none" id="modalBidWarning">
                            Bid amount must be equal to or higher than the minimum required bid.
                        </div>
                    </div>
                </div>

                <div class="modal-footer bg-light p-3">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-dash-primary px-4" id="modalSubmitBtn">
                        <i class="fa-solid fa-check-circle me-1"></i> Confirm Bid
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ======================================================================= -->
<!-- MODAL 2: QUICK PAY (WON LOT CHECKOUT)                                   -->
<!-- ======================================================================= -->
<div class="modal fade" id="quickPayModal" tabindex="-1" aria-labelledby="quickPayModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px; overflow: hidden;">
            <div class="modal-header text-white" style="background: linear-gradient(135deg, #047857 0%, #064e3b 100%);">
                <h5 class="modal-title fw-bold" id="quickPayModalLabel">
                    <i class="fa-solid fa-credit-card me-2"></i> Instant Wallet Checkout
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="dashboard.php" method="POST">
                <input type="hidden" name="action" value="pay_won_auction">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                <input type="hidden" name="item_id" id="payItemId" value="0">

                <div class="modal-body p-4">
                    <div class="text-center mb-4">
                        <div class="small text-muted text-uppercase fw-semibold mb-1">Won Auction Lot</div>
                        <h4 class="h5 fw-bold mb-1" id="payItemTitle">Auction Lot</h4>
                        <div class="display-6 fw-bold text-success my-2" id="payHammerPrice">Rs. 0.00</div>
                        <div class="small text-muted">Final hammer settlement price</div>
                    </div>

                    <div class="p-3 rounded border mb-3" style="background: var(--dash-surface-muted, #f8fafc);">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted small">Available Wallet Balance:</span>
                            <span class="fw-bold">Rs. <?= number_format($user_wallet_balance, 2) ?></span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted small">Platform Escrow Fee:</span>
                            <span class="text-success fw-bold small">FREE (0.00)</span>
                        </div>
                    </div>

                    <?php if ($user_wallet_balance <= 0): ?>
                        <div class="alert alert-warning small mb-0">
                            <i class="fa-solid fa-triangle-exclamation me-1"></i> Your wallet balance is empty. Please top up your wallet to settle this auction.
                        </div>
                    <?php endif; ?>
                </div>

                <div class="modal-footer bg-light p-3">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <?php if ($user_wallet_balance > 0): ?>
                        <button type="submit" class="btn-dash-primary px-4">
                            <i class="fa-solid fa-lock me-1"></i> Confirm & Settle Payment
                        </button>
                    <?php else: ?>
                        <a href="wallet.php" class="btn btn-warning fw-bold px-4">
                            <i class="fa-solid fa-wallet me-1"></i> Top-up Wallet First
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ======================================================================= -->
<!-- MODAL 3: VIEW RECEIPT                                                   -->
<!-- ======================================================================= -->
<div class="modal fade" id="receiptModal" tabindex="-1" aria-labelledby="receiptModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px; overflow: hidden;">
            <div class="modal-header border-bottom">
                <h5 class="modal-title fw-bold text-dark" id="receiptModalLabel">
                    <i class="fa-solid fa-receipt text-success me-2"></i> Official Auction Invoice
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4" id="printableReceiptArea">
                <div class="text-center mb-4">
                    <div class="badge bg-success-subtle text-success border border-success-subtle px-3 py-1 mb-2">
                        <i class="fa-solid fa-circle-check me-1"></i> SETTLED & VERIFIED
                    </div>
                    <h4 class="h5 fw-bold mb-0">Geo Auction System</h4>
                    <div class="small text-muted">Order Confirmation & Escrow Receipt</div>
                </div>

                <div class="border rounded p-3 mb-3 bg-light">
                    <div class="d-flex justify-content-between small mb-2">
                        <span class="text-muted">Invoice No:</span>
                        <span class="fw-bold" id="rcptOrderNo">ORD-0000</span>
                    </div>
                    <div class="d-flex justify-content-between small mb-2">
                        <span class="text-muted">Auction Lot:</span>
                        <span class="fw-bold" id="rcptItemTitle">Lot Title</span>
                    </div>
                    <div class="d-flex justify-content-between small mb-2">
                        <span class="text-muted">Lot Code:</span>
                        <span class="fw-bold" id="rcptItemCode">#</span>
                    </div>
                    <div class="d-flex justify-content-between small mb-2">
                        <span class="text-muted">Seller:</span>
                        <span class="fw-bold" id="rcptSeller">Verified Seller</span>
                    </div>
                    <div class="d-flex justify-content-between small">
                        <span class="text-muted">Settlement Date:</span>
                        <span class="fw-bold" id="rcptDate">Date</span>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center p-3 rounded bg-success-subtle border border-success-subtle">
                    <span class="fw-bold text-success fs-6">Total Amount Settled:</span>
                    <span class="fw-bold text-success fs-5" id="rcptHammerPrice">Rs. 0.00</span>
                </div>
            </div>
            <div class="modal-footer bg-light p-3">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-outline-dark" onclick="window.print()">
                    <i class="fa-solid fa-print me-1"></i> Print Receipt
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap 5 JavaScript Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- Real-time JavaScript Countdown Timer & Modal Handler -->
<script>
document.addEventListener('DOMContentLoaded', function() {

    // 1. Dynamic Live Clock in Banner
    function updateLiveClock() {
        const el = document.getElementById('dashLiveClock');
        if (!el) return;
        const now = new Date();
        el.textContent = now.toLocaleDateString('en-US', {
            weekday: 'short', month: 'short', day: 'numeric', year: 'numeric'
        }) + ' - ' + now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    }
    setInterval(updateLiveClock, 1000);

    // 2. Real-Time Auction Countdown Timers
    function updateCountdowns() {
        const elements = document.querySelectorAll('[data-countdown]');
        const now = new Date().getTime();

        elements.forEach(function(badge) {
            const endStr = badge.getAttribute('data-countdown');
            if (!endStr) return;

            // Handle cross-browser MySQL datetime parsing
            const targetTime = new Date(endStr.replace(/-/g, '/')).getTime();
            const diff = targetTime - now;
            const textSpan = badge.querySelector('span') || badge;

            if (diff <= 0) {
                badge.className = 'badge bg-secondary-subtle text-secondary';
                textSpan.innerHTML = '<i class="fa-solid fa-flag-checkered me-1"></i> Concluded';
            } else {
                const days = Math.floor(diff / (1000 * 60 * 60 * 24));
                const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
                const seconds = Math.floor((diff % (1000 * 60)) / 1000);

                if (days > 0) {
                    textSpan.textContent = `${days}d ${hours}h ${minutes}m left`;
                } else if (hours > 0) {
                    textSpan.textContent = `${hours}h ${minutes}m ${seconds}s left`;
                    if (hours < 1) {
                        badge.classList.add('urgent');
                    }
                } else {
                    badge.classList.add('urgent');
                    textSpan.textContent = `${minutes}m ${seconds}s left`;
                }
            }
        });
    }

    updateCountdowns();
    setInterval(updateCountdowns, 1000);

    // 3. Hash URL Tab Support (e.g., #analyticsTab or #wonAuctionsTab)
    const hash = window.location.hash;
    if (hash) {
        const triggerEl = document.querySelector(`.dash-nav-pills button[data-bs-target="${hash}"]`);
        if (triggerEl) {
            bootstrap.Tab.getOrCreateInstance(triggerEl).show();
        }
    }

    // 4. Initialize Analytics automatically
    loadBidAnalytics(false);

    // Listen for tab switch to re-render Chart.js smoothly (handles canvas resize)
    const analyticsTabBtn = document.getElementById('analytics-tab');
    if (analyticsTabBtn) {
        analyticsTabBtn.addEventListener('shown.bs.tab', function() {
            if (chartComparison) chartComparison.resize();
            if (chartDistribution) chartDistribution.resize();
        });
    }
});

// Helper for switching tabs programmatically from KPI card links
function switchDashTab(tabId) {
    const triggerEl = document.querySelector(`.dash-nav-pills button[data-bs-target="#${tabId}"]`);
    if (triggerEl) {
        bootstrap.Tab.getOrCreateInstance(triggerEl).show();
        window.location.hash = tabId;
        window.scrollTo({ top: triggerEl.getBoundingClientRect().top + window.scrollY - 80, behavior: 'smooth' });
    }
}

// =============================================================================
// 5. CHART.JS & DYNAMIC ANALYTICS LOGIC (AJAX Fetch & Auto-Refresh)
// =============================================================================
let chartComparison = null;
let chartDistribution = null;
let cachedAnalyticsData = null;
let activeChartMetric = 'amount'; // 'amount' or 'count'
let autoRefreshTimer = null;

// Endpoint points directly to this file's built-in JSON API
const ANALYTICS_API_URL = 'dashboard.php?action=get_bid_stats';

async function loadBidAnalytics(isManual = false) {
    const refreshIcon = document.getElementById('refreshAnalyticsIcon');
    const lastUpdatedEl = document.getElementById('analyticsLastUpdatedText');
    const loaderBar = document.getElementById('loaderBarChart');
    const loaderDonut = document.getElementById('loaderDoughnutChart');
    const errorAlert = document.getElementById('analyticsErrorAlert');

    if (refreshIcon) refreshIcon.classList.add('fa-spin');
    if (loaderBar) loaderBar.classList.remove('d-none');
    if (loaderDonut) loaderDonut.classList.remove('d-none');
    if (errorAlert) errorAlert.classList.add('d-none');

    try {
        const response = await fetch(ANALYTICS_API_URL, {
            headers: { 'Accept': 'application/json' }
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const data = await response.json();
        if (data.status !== 'success') {
            throw new Error(data.message || 'API returned non-success response');
        }

        cachedAnalyticsData = data;

        // 1. Update Mini Summary Cards
        updateSummaryCards(data.summary);

        // 2. Render / Update Charts
        renderComparisonChart(data.comparison, activeChartMetric);
        renderDistributionChart(data.distribution);

        // 3. Render Breakdown Data Tables
        renderBreakdownTables(data.comparison);

        // 4. Update sync timestamp
        if (lastUpdatedEl) {
            const timeStr = new Date().toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
            lastUpdatedEl.innerHTML = `<i class="fa-regular fa-clock me-1"></i> Synced: ${timeStr}`;
        }

    } catch (err) {
        console.error('Analytics Fetch Error:', err);
        if (errorAlert) {
            document.getElementById('analyticsErrorMessage').textContent = 'Error loading analytics: ' + err.message;
            errorAlert.classList.remove('d-none');
        }
    } finally {
        if (refreshIcon) refreshIcon.classList.remove('fa-spin');
        if (loaderBar) loaderBar.classList.add('d-none');
        if (loaderDonut) loaderDonut.classList.add('d-none');
    }
}

// Update Top Mini Summary Cards
function updateSummaryCards(summary) {
    if (!summary) return;
    document.getElementById('statActiveBids').textContent = summary.total_active_bids.toLocaleString();
    document.getElementById('statHighestBid').textContent = summary.highest_bid_formatted;
    document.getElementById('statLowestBid').textContent = summary.lowest_bid_formatted;
    document.getElementById('statAverageBid').textContent = summary.average_bid_formatted;

    const sub = document.getElementById('statActiveSub');
    if (sub) {
        sub.textContent = summary.is_benchmark ? 'Platform active lots (Benchmark)' : 'Your active bids';
    }
}

// Chart 1: Bar Chart comparing Top Active vs Lowest Performing Bids
function renderComparisonChart(comparison, metric = 'amount') {
    const ctx = document.getElementById('bidComparisonChart');
    if (!ctx || typeof Chart === 'undefined') return;

    const topChart = comparison.top_chart;
    const lowChart = comparison.low_chart;

    const isAmount = (metric === 'amount');
    const topData  = isAmount ? topChart.amounts : topChart.bid_counts;
    const lowData  = isAmount ? lowChart.amounts : lowChart.bid_counts;

    // Use union of labels or 1 to 5 ranking
    const labels = ['Rank #1 Lot', 'Rank #2 Lot', 'Rank #3 Lot', 'Rank #4 Lot', 'Rank #5 Lot'];

    if (chartComparison) {
        chartComparison.destroy();
    }

    const isDark = document.documentElement.classList.contains('dark-theme');
    const gridColor = isDark ? 'rgba(255, 255, 255, 0.08)' : 'rgba(0, 0, 0, 0.06)';
    const textColor = isDark ? '#94a3b8' : '#64748b';

    chartComparison = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [
                {
                    label: isAmount ? 'Top Active (Highest Bid Rs.)' : 'Top Active (Bid Count)',
                    data: topData,
                    backgroundColor: 'rgba(4, 120, 87, 0.85)',
                    borderColor: '#047857',
                    borderWidth: 1.5,
                    borderRadius: 6,
                    barPercentage: 0.7,
                    categoryPercentage: 0.8
                },
                {
                    label: isAmount ? 'Lowest Bids (Lowest Offer Rs.)' : 'Lowest Bids (Bid Count)',
                    data: lowData,
                    backgroundColor: 'rgba(245, 158, 11, 0.85)',
                    borderColor: '#d97706',
                    borderWidth: 1.5,
                    borderRadius: 6,
                    barPercentage: 0.7,
                    categoryPercentage: 0.8
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                    labels: { color: textColor, font: { weight: 600, size: 12 } }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const val = context.parsed.y;
                            if (isAmount) {
                                return ` ${context.dataset.label}: Rs. ${val.toLocaleString('en-US', { minimumFractionDigits: 2 })}`;
                            }
                            return ` ${context.dataset.label}: ${val.toLocaleString()} bids`;
                        }
                    }
                }
            },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: { color: textColor, font: { weight: 600 } }
                },
                y: {
                    grid: { color: gridColor },
                    ticks: {
                        color: textColor,
                        callback: function(value) {
                            if (isAmount) {
                                if (value >= 1000000) return 'Rs. ' + (value / 1000000).toFixed(1) + 'M';
                                if (value >= 1000) return 'Rs. ' + (value / 1000).toFixed(0) + 'k';
                                return 'Rs. ' + value;
                            }
                            return value;
                        }
                    }
                }
            }
        }
    });
}

// Chart 2: Doughnut / Pie Chart (Bid Status & Volume Distribution)
function renderDistributionChart(distribution) {
    const ctx = document.getElementById('bidDistributionChart');
    if (!ctx || typeof Chart === 'undefined') return;

    if (chartDistribution) {
        chartDistribution.destroy();
    }

    const isDark = document.documentElement.classList.contains('dark-theme');
    const textColor = isDark ? '#94a3b8' : '#64748b';

    chartDistribution = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: distribution.labels,
            datasets: [{
                data: distribution.data,
                backgroundColor: distribution.colors || ['#10b981', '#ef4444', '#3b82f6', '#f59e0b'],
                borderWidth: 2,
                borderColor: isDark ? '#1e293b' : '#ffffff',
                hoverOffset: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '68%',
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        color: textColor,
                        padding: 14,
                        font: { size: 11, weight: 600 },
                        usePointStyle: true,
                        pointStyle: 'circle'
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const val = context.parsed;
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const pct = total > 0 ? ((val / total) * 100).toFixed(1) : 0;
                            return ` ${context.label}: ${val} (${pct}%)`;
                        }
                    }
                }
            }
        }
    });
}

// Toggle Metric on Comparison Bar Chart (Amount vs Count)
function toggleChartMetric(metric) {
    activeChartMetric = metric;
    document.getElementById('btnMetricAmount').classList.toggle('active', metric === 'amount');
    document.getElementById('btnMetricCount').classList.toggle('active', metric === 'count');

    if (cachedAnalyticsData && cachedAnalyticsData.comparison) {
        renderComparisonChart(cachedAnalyticsData.comparison, activeChartMetric);
    }
}

// Render Comparison Data Tables below charts
function renderBreakdownTables(comparison) {
    const topTbody = document.querySelector('#tableTopItems tbody');
    const lowTbody = document.querySelector('#tableLowItems tbody');

    if (topTbody && comparison.top_items) {
        let html = '';
        comparison.top_items.forEach((item, i) => {
            html += `
                <tr class="border-bottom">
                    <td>
                        <span class="badge bg-success-subtle text-success me-1">#${i + 1}</span>
                        <a href="../live_auction.php?id=${item.id}" class="text-dark-emphasis fw-bold text-decoration-none small">
                            ${escapeHtml(item.title.substring(0, 28))}...
                        </a>
                    </td>
                    <td><span class="badge bg-light text-dark border">#${escapeHtml(item.code)}</span></td>
                    <td class="text-end fw-bold text-success small">Rs. ${item.amount.toLocaleString()}</td>
                    <td class="text-end small"><span class="badge rounded-pill bg-light text-dark">${item.bid_count}</span></td>
                </tr>
            `;
        });
        topTbody.innerHTML = html || '<tr><td colspan="4" class="text-center text-muted small">No items recorded.</td></tr>';
    }

    if (lowTbody && comparison.lowest_items) {
        let html = '';
        comparison.lowest_items.forEach((item, i) => {
            html += `
                <tr class="border-bottom">
                    <td>
                        <span class="badge bg-warning-subtle text-warning me-1">#${i + 1}</span>
                        <a href="../live_auction.php?id=${item.id}" class="text-dark-emphasis fw-bold text-decoration-none small">
                            ${escapeHtml(item.title.substring(0, 28))}...
                        </a>
                    </td>
                    <td><span class="badge bg-light text-dark border">#${escapeHtml(item.code)}</span></td>
                    <td class="text-end fw-bold text-dark-emphasis small">Rs. ${item.amount.toLocaleString()}</td>
                    <td class="text-end small"><span class="badge rounded-pill bg-light text-dark">${item.bid_count}</span></td>
                </tr>
            `;
        });
        lowTbody.innerHTML = html || '<tr><td colspan="4" class="text-center text-muted small">No items recorded.</td></tr>';
    }
}

function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/[&<>"']/g, function(m) {
        return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[m];
    });
}

// 6. Auto-Refresh Interval Setup (every 30s)
const autoRefreshSwitch = document.getElementById('autoRefreshAnalytics');
if (autoRefreshSwitch) {
    autoRefreshSwitch.addEventListener('change', function() {
        if (this.checked) {
            startAnalyticsAutoRefresh();
        } else {
            clearInterval(autoRefreshTimer);
        }
    });
}

function startAnalyticsAutoRefresh() {
    clearInterval(autoRefreshTimer);
    autoRefreshTimer = setInterval(function() {
        // Only poll if tab is visible and switch is enabled
        if (autoRefreshSwitch && autoRefreshSwitch.checked && !document.hidden) {
            loadBidAnalytics(false);
        }
    }, 30000); // 30 seconds
}
startAnalyticsAutoRefresh();

// =============================================================================
// 7. Quick Raise Bid Modal Logic
// =============================================================================
let currentMinBid = 0;

function openRaiseBidModal(btn) {
    const itemId    = btn.getAttribute('data-id');
    const title     = btn.getAttribute('data-title');
    const code      = btn.getAttribute('data-code');
    const current   = parseFloat(btn.getAttribute('data-current') || '0');
    const minBid    = parseFloat(btn.getAttribute('data-min') || '0');
    const thumb     = btn.getAttribute('data-thumb');

    currentMinBid = minBid;

    document.getElementById('modalItemId').value = itemId;
    document.getElementById('modalItemTitle').textContent = title;
    document.getElementById('modalItemCode').textContent = '#' + code;
    document.getElementById('modalItemThumb').src = thumb;
    document.getElementById('modalCurrentPrice').textContent = 'Rs. ' + current.toLocaleString('en-US', { minimumFractionDigits: 2 });
    document.getElementById('modalMinRequired').textContent = 'Rs. ' + minBid.toLocaleString('en-US', { minimumFractionDigits: 2 });

    const input = document.getElementById('modalBidInput');
    input.value = minBid;
    input.min = minBid;

    const warning = document.getElementById('modalBidWarning');
    if (warning) warning.classList.add('d-none');

    const modal = new bootstrap.Modal(document.getElementById('raiseBidModal'));
    modal.show();
}

function addBidIncrement(amount) {
    const input = document.getElementById('modalBidInput');
    let currVal = parseFloat(input.value) || currentMinBid;
    if (currVal < currentMinBid) currVal = currentMinBid;
    input.value = (currVal + amount).toFixed(2);

    const warning = document.getElementById('modalBidWarning');
    if (warning) warning.classList.add('d-none');
}

document.getElementById('modalBidInput')?.addEventListener('input', function() {
    const warning = document.getElementById('modalBidWarning');
    const val = parseFloat(this.value) || 0;
    if (val < currentMinBid) {
        warning?.classList.remove('d-none');
    } else {
        warning?.classList.add('d-none');
    }
});

// 8. Quick Pay Modal Logic
function openQuickPayModal(btn) {
    const itemId = btn.getAttribute('data-id');
    const title  = btn.getAttribute('data-title');
    const hammer = parseFloat(btn.getAttribute('data-hammer') || '0');

    document.getElementById('payItemId').value = itemId;
    document.getElementById('payItemTitle').textContent = title;
    document.getElementById('payHammerPrice').textContent = 'Rs. ' + hammer.toLocaleString('en-US', { minimumFractionDigits: 2 });

    const modal = new bootstrap.Modal(document.getElementById('quickPayModal'));
    modal.show();
}

// 9. View Receipt Modal Logic
function openReceiptModal(btn) {
    const title   = btn.getAttribute('data-title');
    const code    = btn.getAttribute('data-code');
    const hammer  = parseFloat(btn.getAttribute('data-hammer') || '0');
    const orderNo = btn.getAttribute('data-orderno');
    const seller  = btn.getAttribute('data-seller');
    const date    = btn.getAttribute('data-date');

    document.getElementById('rcptOrderNo').textContent = orderNo;
    document.getElementById('rcptItemTitle').textContent = title;
    document.getElementById('rcptItemCode').textContent = '#' + code;
    document.getElementById('rcptSeller').textContent = seller;
    document.getElementById('rcptDate').textContent = date;
    document.getElementById('rcptHammerPrice').textContent = 'Rs. ' + hammer.toLocaleString('en-US', { minimumFractionDigits: 2 });

    const modal = new bootstrap.Modal(document.getElementById('receiptModal'));
    modal.show();
}
</script>

<?php 
// Include Global Site Footer
if (file_exists(__DIR__ . '/../includes/footer.php')) {
    include_once __DIR__ . '/../includes/footer.php';
}
?>