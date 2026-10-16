<?php
ini_set('display_errors', 0);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Role switching
if (isset($_GET['switch_role']) && in_array(strtolower($_GET['switch_role']), ['buyer', 'seller'])) {
    $_SESSION['role'] = strtolower($_GET['switch_role']);
}

$is_authenticated = isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
$user_id = $is_authenticated ? (int)$_SESSION['user_id'] : 0;
$role = $is_authenticated ? strtolower(trim($_SESSION['role'] ?? 'buyer')) : 'guest';
$is_ajax = isset($_GET['ajax']) && $_GET['ajax'] == '1';

// Prevent legacy header from forcing login on public live auction page
$require_auth = false;
$header_included = false;
if (!$is_ajax) {
    if (file_exists(__DIR__ . '/../includes/header.php')) {
        include_once __DIR__ . '/../includes/header.php';
        $header_included = true;
    } elseif (file_exists(__DIR__ . '/includes/header.php')) {
        include_once __DIR__ . '/includes/header.php';
        $header_included = true;
    }
}

if (file_exists(__DIR__ . '/../includes/database.php')) {
    include_once __DIR__ . '/../includes/database.php';
} elseif (file_exists(__DIR__ . '/includes/database.php')) {
    include_once __DIR__ . '/includes/database.php';
}

$pdo = null;
try {
    $pdo = Database::getPdo();
} catch (\Throwable $e) {
    $pdo = null;
}

$has_db = ($pdo instanceof PDO);
$user_lat = isset($_SESSION['user_lat']) && is_numeric($_SESSION['user_lat']) ? (float)$_SESSION['user_lat'] : 25.3960;
$user_lng = isset($_SESSION['user_lng']) && is_numeric($_SESSION['user_lng']) ? (float)$_SESSION['user_lng'] : 68.3578;

// Ensure auto_bids table exists and clean any legacy empty statuses
if ($pdo instanceof PDO) {
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `auto_bids` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `item_id` INT NOT NULL,
                `user_id` INT NOT NULL,
                `max_bid_amount` DECIMAL(12,2) NOT NULL,
                `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY `uk_item_user` (`item_id`, `user_id`),
                INDEX `idx_item_active` (`item_id`, `is_active`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
        $pdo->exec("UPDATE items SET status = 'ended' WHERE status = '' OR status IS NULL");
    } catch (\Throwable $e) {
        error_log("Schema init error: " . $e->getMessage());
    }
}

/**
 * Great-circle distance calculation via Haversine formula
 */
function getDistanceKm($lat1, $lon1, $lat2, $lon2) {
    if (empty($lat1) || empty($lon1) || empty($lat2) || empty($lon2)) return null;
    $lat1 = (float)$lat1; $lon1 = (float)$lon1; $lat2 = (float)$lat2; $lon2 = (float)$lon2;
    if ($lat1 == 0.0 && $lon1 == 0.0) return null;
    if ($lat2 == 0.0 && $lon2 == 0.0) return null;

    $earthRadius = 6371; // km
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat / 2) * sin($dLat / 2) +
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
         sin($dLon / 2) * sin($dLon / 2);
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    return round($earthRadius * $c, 1);
}

/**
 * Image path cleaner
 */
function cleanImagePath($path) {
    if (empty($path)) return '';
    $path = trim((string)$path, " \t\n\r\0\x0B\"'[]\\");
    $path = str_replace(['\\/', '//'], '/', $path);
    $path = trim($path);
    if (empty($path)) return '';
    if (preg_match('/^(https?:\/\/|\/\/|data:)/i', $path)) {
        return $path;
    }
    $is_user_sub = (basename(dirname(__FILE__)) === 'user');
    if ($is_user_sub) {
        if (strpos($path, '../') === 0) {
            return $path;
        }
        if (strpos($path, 'uploads/') === 0 || strpos($path, 'assets/') === 0) {
            return '../' . $path;
        }
    }
    return $path;
}

/**
 * Helper to get clean seller avatar URL with reliable fallback
 */
function getSellerAvatarUrl($avatarPath, $sellerName = 'Seller') {
    $cleaned = cleanImagePath($avatarPath);
    if (!empty($cleaned)) {
        if (preg_match('/^(https?:\/\/|\/\/|data:)/i', $cleaned)) {
            return $cleaned;
        }
        $doc_root = __DIR__;
        if (file_exists($doc_root . '/' . ltrim($cleaned, '/'))) {
            return $cleaned;
        }
        if (file_exists($doc_root . '/../' . ltrim($cleaned, '/'))) {
            return '../' . ltrim($cleaned, '/');
        }
        return $cleaned;
    }
    // High-quality UI-Avatars fallback with initials and consistent brand emerald theme
    return 'https://ui-avatars.com/api/?name=' . urlencode(!empty($sellerName) ? $sellerName : 'Seller') . '&background=059669&color=fff&size=128&bold=true';
}

/**
 * Auction Item Image Service (OOP Architecture + PDO Prepared Statements)
 * Reuses existing item_images table and items.images / items.featured_image fields
 */
class AuctionImageRepository {
    private ?PDO $pdo;
    private string $placeholder = 'assets/image/Geo-auction.png';

    public function __construct(?PDO $pdo = null) {
        if ($pdo instanceof PDO) {
            $this->pdo = $pdo;
        } else {
            try {
                $this->pdo = Database::getPdo();
            } catch (\Throwable $e) {
                $this->pdo = null;
            }
        }
    }

    /**
     * Fetch all associated item images using prepared statements with PDO
     *
     * @param int $itemId
     * @param string|null $imgField
     * @param string|null $featuredField
     * @return array
     */
    public function getImagesByItemId(int $itemId, ?string $imgField = '', ?string $featuredField = ''): array {
        $images = [];

        // 1. Featured image from lot listing
        if (!empty($featuredField)) {
            $cleaned = cleanImagePath($featuredField);
            if (!empty($cleaned) && !in_array($cleaned, $images, true)) {
                $images[] = $cleaned;
            }
        }

        // 2. Query existing item_images table using OOP PDO prepared statements
        if ($this->pdo instanceof PDO && $itemId > 0) {
            try {
                $stmt = $this->pdo->prepare("SELECT image_path FROM item_images WHERE item_id = :item_id ORDER BY is_primary DESC, sort_order ASC, id ASC");
                $stmt->execute([':item_id' => $itemId]);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($rows as $r) {
                    $cleaned = cleanImagePath($r['image_path'] ?? '');
                    if (!empty($cleaned) && !in_array($cleaned, $images, true)) {
                        $images[] = $cleaned;
                    }
                }
            } catch (\PDOException $e) {
                error_log("Failed to load item_images via PDO: " . $e->getMessage());
            }
        }

        // 3. Fallback: Parse items.images JSON or comma-separated list
        if (!empty($imgField)) {
            $raw = trim((string)$imgField);
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                foreach ($decoded as $p) {
                    $val = is_string($p) ? cleanImagePath($p) : (is_array($p) ? cleanImagePath($p['image_path'] ?? $p['url'] ?? '') : '');
                    if (!empty($val) && !in_array($val, $images, true)) {
                        $images[] = $val;
                    }
                }
            } else {
                $parts = explode(',', $raw);
                foreach ($parts as $p) {
                    $val = cleanImagePath($p);
                    if (!empty($val) && !in_array($val, $images, true)) {
                        $images[] = $val;
                    }
                }
            }
        }

        // 4. Default project placeholder image if none exists
        if (empty($images)) {
            $images[] = $this->placeholder;
        }

        return $images;
    }
}

/**
 * Backward compatibility wrapper delegating to AuctionImageRepository
 */
function parseItemImages($connOrPdo = null, $item_id = 0, $imgField = '', $featuredField = '') {
    $pdo = ($connOrPdo instanceof PDO) ? $connOrPdo : null;
    $repo = new AuctionImageRepository($pdo);
    return $repo->getImagesByItemId((int)$item_id, (string)$imgField, (string)$featuredField);
}

/**
 * Send in-app notification using OOP PDO
 */
function sendNotification($connOrPdo, int $to_user, string $type, string $title, string $msg, string $link = ''): void {
    global $pdo;
    $db = ($connOrPdo instanceof PDO) ? $connOrPdo : $pdo;
    if (!$db || $to_user <= 0) return;
    try {
        $stmt = $db->prepare("INSERT INTO user_notifications (`user_id`, `type`, `title`, `message`, `link`, `is_read`, `created_at`) VALUES (?, ?, ?, ?, ?, 0, NOW())");
        $stmt->execute([$to_user, $type, $title, $msg, $link]);
    } catch (\Throwable $e) {
        try {
            $stmt = $db->prepare("INSERT INTO notifications (`user_id`, `type`, `title`, `message`, `link`, `is_read`, `created_at`) VALUES (?, ?, ?, ?, ?, 0, NOW())");
            $stmt->execute([$to_user, $type, $title, $msg, $link]);
        } catch (\Throwable $ignored) {}
    }
}

/**
 * Multi-Agent Auto-Bid (Bidbot) Escalation Engine using OOP PDO
 */
function runAutoBids($connOrPdo, int $item_id, float $current_highest, int $exclude_uid = 0): void {
    global $pdo;
    $db = ($connOrPdo instanceof PDO) ? $connOrPdo : $pdo;
    if (!$db || $item_id <= 0) return;

    try {
        $stmt = $db->prepare("
            SELECT ab.id, ab.user_id, ab.max_bid_amount, u.name AS bidder_name, u.email AS bidder_email, i.min_increment, i.seller_id
            FROM auto_bids ab
            JOIN items i ON ab.item_id = i.id
            JOIN users u ON ab.user_id = u.id
            WHERE ab.item_id = ? AND ab.is_active = 1 AND ab.user_id != ? AND ab.user_id != i.seller_id
            ORDER BY ab.max_bid_amount DESC, ab.created_at ASC
            LIMIT 1
        ");
        $stmt->execute([$item_id, $exclude_uid]);
        $auto = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($auto) {
            $min_inc = (float)($auto['min_increment'] > 0 ? $auto['min_increment'] : 100.00);
            $next_bid = $current_highest + $min_inc;
            $max_bid = (float)$auto['max_bid_amount'];

            if ($next_bid <= $max_bid) {
                $insert_bid = $db->prepare("INSERT INTO bids (item_id, user_id, bidder_name, bidder_email, bid_amount, bid_status, created_at, status) VALUES (?, ?, ?, ?, ?, 'active', NOW(), 'active')");
                $b_name = $auto['bidder_name'] . ' (AutoBid)';
                $b_email = $auto['bidder_email'] ?? '';
                if ($insert_bid->execute([$item_id, (int)$auto['user_id'], $b_name, $b_email, $next_bid])) {
                    $up_item = $db->prepare("UPDATE items SET current_price = ?, bid_count = bid_count + 1 WHERE id = ?");
                    $up_item->execute([$next_bid, $item_id]);
                    sendNotification($db, (int)$auto['seller_id'], 'bid', 'Auto-Bid Received', "Auto-bid of Rs. " . number_format($next_bid, 2) . " placed on your lot.", "live_auction.php?id=$item_id");
                }
            } else {
                $deact = $db->prepare("UPDATE auto_bids SET is_active = 0 WHERE id = ?");
                $deact->execute([(int)$auto['id']]);
                sendNotification($db, (int)$auto['user_id'], 'outbid', 'Auto-Bid Exceeded', "Your auto-bid limit of Rs. " . number_format($max_bid, 2) . " was exceeded.", "live_auction.php?id=$item_id");
            }
        }
    } catch (\Throwable $e) {
        error_log("runAutoBids error: " . $e->getMessage());
    }
}

// --------------------------------------------------------------------------
// AJAX Handlers
// --------------------------------------------------------------------------
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_POST['action'] ?? $_GET['action'] ?? '';

    // 1. Poll Status
    if ($action === 'poll_status') {
        $poll_id = (int)($_GET['item_id'] ?? 0);
        if (!$has_db || $poll_id <= 0) {
            echo json_encode(['success' => false, 'error' => 'Invalid lot ID']);
            exit();
        }

        $stmt = $pdo->prepare("
            SELECT i.*, 
            (SELECT COUNT(*) FROM wishlist WHERE item_id = i.id) AS watcher_count
            FROM items i WHERE i.id = ?
        ");
        $stmt->execute([$poll_id]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$item) {
            echo json_encode(['success' => false, 'error' => 'Item not found']);
            exit();
        }

        if (!empty($item['is_deleted'])) {
            echo json_encode([
                'success' => true,
                'is_deleted' => true,
                'is_closed' => true,
                'current_price' => number_format((float)($item['current_price'] ?? $item['starting_price']), 2),
                'error' => 'This auction lot was withdrawn/deleted by the seller.'
            ]);
            exit();
        }

        $now = time();
        $effective_end = (!empty($item['extended_time']) && $item['extended_time'] != '0000-00-00 00:00:00' && strtotime($item['extended_time']) > strtotime($item['end_time']))
            ? $item['extended_time']
            : $item['end_time'];
        $end_ts = strtotime($effective_end);
        $is_closed = ($item['status'] !== 'active') || ($now >= $end_ts);

        // Concurrency-safe auto-finalization on timer expiration
        if ($is_closed && $item['status'] === 'active') {
            require_once __DIR__ . '/includes/AuctionEngine.php';
            $engine = new AuctionEngine($pdo);
            try {
                $engine->finalizeAuction($poll_id);
            } catch (\Throwable $fe) {
                error_log("live_auction.php Auto-Finalize Warning: " . $fe->getMessage());
            }

            // Refresh item record after atomic settlement
            $refreshStmt = $pdo->prepare("SELECT i.*, (SELECT COUNT(*) FROM wishlist WHERE item_id = i.id) AS watcher_count FROM items i WHERE i.id = ?");
            $refreshStmt->execute([$poll_id]);
            $refreshed = $refreshStmt->fetch(PDO::FETCH_ASSOC);
            if ($refreshed) {
                $item = $refreshed;
            }
        }

        $b_stmt = $pdo->prepare("
            SELECT b.id, b.bid_amount, b.created_at, b.bid_status, b.is_winning, u.name AS bidder_name 
            FROM bids b 
            JOIN users u ON b.user_id = u.id 
            WHERE b.item_id = ? 
            ORDER BY b.bid_amount DESC LIMIT 6
        ");
        $b_stmt->execute([$poll_id]);
        $history = [];
        while ($b_row = $b_stmt->fetch(PDO::FETCH_ASSOC)) {
            $name_parts = explode(' ', trim($b_row['bidder_name']));
            $masked = htmlspecialchars($name_parts[0]) . (count($name_parts) > 1 ? ' ' . substr($name_parts[1], 0, 1) . '***' : ' ***');
            $history[] = [
                'bidder' => $masked,
                'amount' => number_format((float)$b_row['bid_amount'], 2),
                'time' => date('M d, h:i A', strtotime($b_row['created_at'])),
                'initial' => strtoupper(substr($b_row['bidder_name'] ?? 'B', 0, 1))
            ];
        }

        $curr_price = max((float)$item['current_price'], (float)$item['starting_price']);
        $min_inc = (float)($item['min_increment'] > 0 ? $item['min_increment'] : 100.00);
        $next_min = $curr_price + $min_inc;
        $reserve_met = (empty($item['reserve_price']) || $curr_price >= (float)$item['reserve_price']);

        echo json_encode([
            'success' => true,
            'current_price' => number_format($curr_price, 2),
            'raw_price' => $curr_price,
            'min_increment' => number_format($min_inc, 2),
            'next_min_formatted' => number_format($next_min, 2),
            'raw_next_min' => $next_min,
            'bid_count' => (int)$item['bid_count'],
            'watcher_count' => (int)$item['watcher_count'],
            'end_time' => $effective_end,
            'is_closed' => $is_closed,
            'reserve_met' => $reserve_met,
            'history' => $history
        ]);
        exit();
    }

    // 2. Place Bid
    if ($action === 'ajax_place_bid') {
        if (!$is_authenticated) {
            echo json_encode(['success' => false, 'error' => 'Please sign in to place a bid.', 'redirect' => 'login.php']);
            exit();
        }
        $b_item_id = (int)($_POST['item_id'] ?? 0);
        $b_amount = (float)($_POST['bid_amount'] ?? 0);
        if ($b_amount <= 0 || $b_item_id <= 0 || !$has_db) {
            echo json_encode(['success' => false, 'error' => 'Invalid bid amount or lot ID.']);
            exit();
        }

        try {
            require_once __DIR__ . '/includes/AuctionEngine.php';
            $engine = new AuctionEngine($pdo);

            $ipAddr = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            $uaStr  = $_SERVER['HTTP_USER_AGENT'] ?? '';

            $result = $engine->placeBid($b_item_id, $user_id, $b_amount, $ipAddr, $uaStr);

            echo json_encode([
                'success'         => true,
                'message'         => $result['message'],
                'extended'        => $result['extended'] ?? false,
                'new_price'       => $result['new_price'] ?? '',
                'next_min_bid'    => $result['next_min_bid'] ?? '',
                'bid_count'       => $result['bid_count'] ?? 0,
                'reserve_met'     => $result['reserve_met'] ?? false,
                'autobid_counter' => $result['autobid_counter'] ?? false
            ]);
            exit();

        } catch (InvalidArgumentException $ie) {
            echo json_encode(['success' => false, 'error' => $ie->getMessage()]);
            exit();
        } catch (RuntimeException $re) {
            echo json_encode(['success' => false, 'error' => $re->getMessage()]);
            exit();
        } catch (\Throwable $e) {
            error_log("live_auction.php ajax_place_bid Fatal Exception: " . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'An internal error occurred while placing your bid. Please try again.']);
            exit();
        }
    }

    // 3. Wishlist / Watchlist Toggle
    if ($action === 'toggle_watchlist') {
        if (!$is_authenticated) {
            echo json_encode(['success' => false, 'error' => 'Please sign in to manage your watchlist.', 'redirect' => 'login.php']);
            exit();
        }
        $w_item_id = (int)($_POST['item_id'] ?? 0);
        if ($w_item_id <= 0 || !$has_db) {
            echo json_encode(['success' => false, 'error' => 'Invalid lot request.']);
            exit();
        }

        $chk = $pdo->prepare("SELECT id FROM wishlist WHERE user_id = ? AND item_id = ?");
        $chk->execute([$user_id, $w_item_id]);
        $w_row = $chk->fetch(PDO::FETCH_ASSOC);

        if ($w_row) {
            $del = $pdo->prepare("DELETE FROM wishlist WHERE id = ?");
            $del->execute([$w_row['id']]);
            $in_watchlist = false;
        } else {
            $ins = $pdo->prepare("INSERT INTO wishlist (user_id, item_id, created_at) VALUES (?, ?, NOW())");
            $ins->execute([$user_id, $w_item_id]);
            $in_watchlist = true;
        }

        $cnt_stmt = $pdo->prepare("SELECT COUNT(*) AS c FROM wishlist WHERE item_id = ?");
        $cnt_stmt->execute([$w_item_id]);
        $w_count = (int)($cnt_stmt->fetch(PDO::FETCH_ASSOC)['c'] ?? 0);

        echo json_encode(['success' => true, 'in_watchlist' => $in_watchlist, 'count' => $w_count]);
        exit();
    }

    // 4. Auto-Bid Setup
    if ($action === 'set_autobid') {
        if (!$is_authenticated) {
            echo json_encode(['success' => false, 'error' => 'Please sign in to configure Auto-Bid.', 'redirect' => 'login.php']);
            exit();
        }
        $ab_item_id = (int)($_POST['item_id'] ?? 0);
        $max_bid = (float)($_POST['max_bid_amount'] ?? 0);

        if ($ab_item_id <= 0 || $max_bid <= 0 || !$has_db) {
            echo json_encode(['success' => false, 'error' => 'Please enter a valid maximum bid amount.']);
            exit();
        }

        $it_stmt = $pdo->prepare("SELECT current_price, starting_price, min_increment, seller_id, status FROM items WHERE id = ?");
        $it_stmt->execute([$ab_item_id]);
        $it_data = $it_stmt->fetch(PDO::FETCH_ASSOC);

        if (!$it_data || $it_data['status'] !== 'active') {
            echo json_encode(['success' => false, 'error' => 'Auction is not active.']);
            exit();
        }
        if ((int)$it_data['seller_id'] === $user_id) {
            echo json_encode(['success' => false, 'error' => 'Sellers cannot auto-bid on their own item.']);
            exit();
        }

        $base = max((float)$it_data['current_price'], (float)$it_data['starting_price']);
        $min_inc = (float)($it_data['min_increment'] > 0 ? $it_data['min_increment'] : 100.00);
        if ($max_bid < ($base + $min_inc)) {
            echo json_encode(['success' => false, 'error' => 'Max bid must be at least Rs. ' . number_format($base + $min_inc, 2)]);
            exit();
        }

        $ab_upsert = $pdo->prepare("
            INSERT INTO auto_bids (item_id, user_id, max_bid_amount, is_active, created_at, updated_at) 
            VALUES (?, ?, ?, 1, NOW(), NOW())
            ON DUPLICATE KEY UPDATE max_bid_amount = VALUES(max_bid_amount), is_active = 1, updated_at = NOW()
        ");
        $ab_upsert->execute([$ab_item_id, $user_id, $max_bid]);

        runAutoBids($pdo, $ab_item_id, $base, 0);

        echo json_encode(['success' => true, 'message' => "Auto-Bid configured successfully up to Rs. " . number_format($max_bid, 2)]);
        exit();
    }

    // 5. Buy It Now
    if ($action === 'buy_it_now') {
        if (!$is_authenticated) {
            echo json_encode(['success' => false, 'error' => 'Please sign in to purchase.', 'redirect' => 'login.php']);
            exit();
        }
        $bin_id = (int)($_POST['item_id'] ?? 0);
        if ($bin_id <= 0 || !$has_db) {
            echo json_encode(['success' => false, 'error' => 'Invalid lot request.']);
            exit();
        }

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("SELECT title, buy_it_now_price, seller_id, status, is_deleted FROM items WHERE id = ? FOR UPDATE");
            $stmt->execute([$bin_id]);
            $item = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$item || $item['status'] !== 'active') throw new Exception("Auction is closed.");
            if (!empty($item['is_deleted'])) throw new Exception("This auction lot has been withdrawn/deleted by the seller.");
            if (empty($item['buy_it_now_price']) || (float)$item['buy_it_now_price'] <= 0) throw new Exception("Buy It Now not enabled for this lot.");
            if ((int)$item['seller_id'] === $user_id) throw new Exception("You cannot purchase your own item.");

            $bin_price = (float)$item['buy_it_now_price'];

            // Schema compliant ENUM: 'sold'
            $up = $pdo->prepare("UPDATE items SET status = 'sold', winner_id = ?, current_price = ? WHERE id = ?");
            $up->execute([$user_id, $bin_price, $bin_id]);

            // Schema compliant transactions table insertion
            try {
                $tx = $pdo->prepare("INSERT INTO transactions (user_id, type, amount, item_id, description, status, created_at) VALUES (?, 'winning_payment', ?, ?, 'Purchased via Buy It Now', 'completed', NOW())");
                $tx->execute([$user_id, $bin_price, $bin_id]);
            } catch (Exception $txe) {}

            $pdo->commit();

            sendNotification($pdo, $user_id, 'won', 'Buy It Now Confirmed!', "You successfully bought '" . htmlspecialchars($item['title']) . "' for Rs. " . number_format($bin_price, 2), "live_auction.php?id=$bin_id");
            sendNotification($pdo, (int)$item['seller_id'], 'sold', 'Instant Purchase Completed', "Your item '" . htmlspecialchars($item['title']) . "' was purchased via Buy It Now.", "live_auction.php?id=$bin_id");

            echo json_encode(['success' => true, 'message' => 'Congratulations! Purchase completed via Buy It Now.']);
            exit();

        } catch (Exception $e) {
            if ($pdo instanceof PDO && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit();
        }
    }

    // 6. Modal Details
    if ($action === 'get_modal_details') {
        $mid = (int)($_GET['item_id'] ?? 0);
        if ($mid <= 0 || !$has_db) {
            echo json_encode(['success' => false, 'error' => 'Invalid lot ID']);
            exit();
        }

        $m_stmt = $pdo->prepare("
            SELECT i.*, u.name AS seller_name, u.avatar AS seller_avatar,
            (SELECT COUNT(*) FROM wishlist WHERE item_id = i.id) AS watcher_count
            FROM items i 
            LEFT JOIN users u ON i.seller_id = u.id 
            WHERE i.id = ?
        ");
        $m_stmt->execute([$mid]);
        $m_item = $m_stmt->fetch(PDO::FETCH_ASSOC);

        if (!$m_item) {
            echo json_encode(['success' => false, 'error' => 'Lot not found']);
            exit();
        }

        $m_price = max((float)$m_item['current_price'], (float)$m_item['starting_price']);
        $m_inc = (float)($m_item['min_increment'] > 0 ? $m_item['min_increment'] : 100.00);
        $m_next = $m_price + $m_inc;
        $m_reserve_met = (empty($m_item['reserve_price']) || $m_price >= (float)$m_item['reserve_price']);
        $m_dist = getDistanceKm($user_lat, $user_lng, $m_item['location_lat'] ?? 0, $m_item['location_lng'] ?? 0);
        $m_images = parseItemImages($pdo, (int)$m_item['id'], $m_item['images'] ?? '', $m_item['featured_image'] ?? '');
        $m_seller_name = $m_item['seller_name'] ?? 'Authorized Vendor';
        $m_seller_avatar = getSellerAvatarUrl($m_item['seller_avatar'] ?? '', $m_seller_name);

        echo json_encode([
            'success' => true,
            'id' => (int)$m_item['id'],
            'auction_code' => $m_item['auction_code'] ?? ('LOT-'.$m_item['id']),
            'title' => htmlspecialchars($m_item['title']),
            'description' => htmlspecialchars($m_item['description'] ?? ''),
            'image' => htmlspecialchars($m_images[0] ?? 'https://images.unsplash.com/photo-1552519507-da3b142c6e3d?auto=format&fit=crop&w=800&q=80'),
            'current_price' => number_format($m_price, 2),
            'next_min' => number_format($m_next, 2),
            'raw_next_min' => $m_next,
            'starting_price' => number_format((float)$m_item['starting_price'], 2),
            'bid_count' => (int)$m_item['bid_count'],
            'watcher_count' => (int)$m_item['watcher_count'],
            'seller_name' => htmlspecialchars($m_seller_name),
            'seller_avatar' => htmlspecialchars($m_seller_avatar),
            'location_city' => htmlspecialchars($m_item['location_city'] ?? 'Hyderabad'),
            'location_area' => htmlspecialchars($m_item['location_area'] ?? 'Qasimabad'),
            'distance_km' => $m_dist,
            'end_time' => $m_item['end_time'],
            'status' => $m_item['status'],
            'reserve_met' => $m_reserve_met,
            'buy_it_now_price' => (!empty($m_item['buy_it_now_price']) && (float)$m_item['buy_it_now_price'] > 0) ? (float)$m_item['buy_it_now_price'] : 0
        ]);
        exit();
    }

    echo json_encode(['success' => false, 'error' => 'Unknown action']);
    exit();
}

// --------------------------------------------------------------------------
// Seller & Admin Management POST Actions
// --------------------------------------------------------------------------
$seller_message = '';
$seller_success = false;
if (($role === 'seller' || $role === 'admin') && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $post_item_id = (int)($_POST['item_id'] ?? 0);

    if (isset($_POST['accept_bid']) && $has_db) {
        $chk_sql = "SELECT id FROM items WHERE id = ?" . ($role === 'seller' ? " AND seller_id = ?" : "");
        $chk_stmt = $pdo->prepare($chk_sql);
        $role === 'seller' ? $chk_stmt->execute([$post_item_id, $user_id]) : $chk_stmt->execute([$post_item_id]);
        if ($chk_stmt->fetch(PDO::FETCH_ASSOC)) {
            $bid_stmt = $pdo->prepare("SELECT id, user_id, bid_amount FROM bids WHERE item_id = ? ORDER BY bid_amount DESC LIMIT 1");
            $bid_stmt->execute([$post_item_id]);
            if ($top_bid = $bid_stmt->fetch(PDO::FETCH_ASSOC)) {
                $winner_id = (int)$top_bid['user_id'];
                $winning_bid_id = (int)$top_bid['id'];
                $up_item = $pdo->prepare("UPDATE items SET status = 'sold', winner_id = ?, winning_bid_id = ? WHERE id = ?");
                $up_item->execute([$winner_id, $winning_bid_id, $post_item_id]);
                
                $up_bid = $pdo->prepare("UPDATE bids SET is_winning = 1, status = 'accepted' WHERE id = ?");
                $up_bid->execute([$winning_bid_id]);
                
                sendNotification($pdo, $winner_id, 'auction', 'Bid Accepted!', "Congratulations! Your bid was accepted for lot #$post_item_id.", "live_auction.php?id=$post_item_id");
                $seller_success = true;
                $seller_message = "Bid accepted successfully! Item marked as sold.";
            } else {
                $seller_message = "No bids found to accept for this item.";
            }
        }
    }
    
    if (isset($_POST['reject_bid']) && $has_db) {
        $chk_sql = "SELECT id FROM items WHERE id = ?" . ($role === 'seller' ? " AND seller_id = ?" : "");
        $chk_stmt = $pdo->prepare($chk_sql);
        $role === 'seller' ? $chk_stmt->execute([$post_item_id, $user_id]) : $chk_stmt->execute([$post_item_id]);
        if ($chk_stmt->fetch(PDO::FETCH_ASSOC)) {
            $up_item = $pdo->prepare("UPDATE items SET status = 'ended' WHERE id = ?");
            $up_item->execute([$post_item_id]);
            
            $up_bids = $pdo->prepare("UPDATE bids SET status = 'rejected' WHERE item_id = ?");
            $up_bids->execute([$post_item_id]);

            $seller_success = true;
            $seller_message = "Auction bid rejected and auction ended.";
        }
    }
    
    if (isset($_POST['delete_item']) && $has_db) {
        $chk_sql = "SELECT id FROM items WHERE id = ?" . ($role === 'seller' ? " AND seller_id = ?" : "");
        $chk_stmt = $pdo->prepare($chk_sql);
        $role === 'seller' ? $chk_stmt->execute([$post_item_id, $user_id]) : $chk_stmt->execute([$post_item_id]);
        if ($chk_stmt->fetch(PDO::FETCH_ASSOC)) {
            $del_stmt = $pdo->prepare("UPDATE items SET is_deleted = 1, deleted_at = NOW(), deleted_by = ? WHERE id = ?");
            $del_stmt->execute([$user_id, $post_item_id]);
            
            $seller_success = true;
            $seller_message = "Listing soft-deleted successfully. It will no longer appear on the public marketplace.";
        }
    }
} 

// --------------------------------------------------------------------------
// Catalog Filtering & Active Lot Data Loading
// --------------------------------------------------------------------------
$item_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$show_all = isset($_GET['all']) && $_GET['all'] == '1';
$selected_category = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;
$search_query = isset($_GET['q']) ? trim($_GET['q']) : '';
$sort_by = isset($_GET['sort']) ? trim($_GET['sort']) : 'latest';
$min_price = isset($_GET['min_price']) && $_GET['min_price'] !== '' ? (float)$_GET['min_price'] : 0;
$max_price = isset($_GET['max_price']) && $_GET['max_price'] !== '' ? (float)$_GET['max_price'] : 0;
$selected_condition = isset($_GET['condition']) ? trim($_GET['condition']) : '';
$selected_location = isset($_GET['location']) ? trim($_GET['location']) : '';
$selected_radius = isset($_GET['radius']) && in_array((int)$_GET['radius'], [1, 5, 10, 25]) ? (int)$_GET['radius'] : 0;
$view_mode = isset($_GET['view']) && $_GET['view'] === 'map' ? 'map' : 'grid';

$current_item = null;
$bids_history_res = null;
$totalItems = 0;
$totalBidsOverall = 0;
$all_products_res = null;
$categories_res = null;

if ($has_db) {
    if ($item_id > 0) {
        $item_stmt = $pdo->prepare("SELECT i.*, u.name AS seller_name, u.avatar AS seller_avatar, c.name AS category_name FROM `items` i LEFT JOIN `users` u ON i.seller_id = u.id LEFT JOIN `categories` c ON i.category_id = c.id WHERE i.`id` = ?");
        $item_stmt->execute([$item_id]);
        $current_item = $item_stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        $item_query = $pdo->query("SELECT i.*, u.name AS seller_name, u.avatar AS seller_avatar, c.name AS category_name FROM `items` i LEFT JOIN `users` u ON i.seller_id = u.id LEFT JOIN `categories` c ON i.category_id = c.id WHERE i.`status` = 'active' AND (i.is_deleted = 0 OR i.is_deleted IS NULL) ORDER BY i.`created_at` DESC LIMIT 1");
        $current_item = $item_query ? $item_query->fetch(PDO::FETCH_ASSOC) : null;
    }

    $bids_history = [];
    if ($current_item) {
        $history_stmt = $pdo->prepare("SELECT b.*, u.name AS bidder_name FROM bids b JOIN users u ON b.user_id = u.id WHERE b.item_id = ? ORDER BY b.bid_amount DESC LIMIT 5");
        $history_stmt->execute([$current_item['id']]);
        $bids_history = $history_stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    $is_watched = false;
    $watcher_count = 0;
    if ($current_item) {
        if ($is_authenticated) {
            $w_chk = $pdo->prepare("SELECT id FROM wishlist WHERE user_id = ? AND item_id = ?");
            $w_chk->execute([$user_id, $current_item['id']]);
            if ($w_chk->fetch(PDO::FETCH_ASSOC)) $is_watched = true;
        }
        $w_cnt_stmt = $pdo->prepare("SELECT COUNT(*) AS c FROM wishlist WHERE item_id = ?");
        $w_cnt_stmt->execute([(int)$current_item['id']]);
        $w_row = $w_cnt_stmt->fetch(PDO::FETCH_ASSOC);
        if ($w_row) $watcher_count = (int)$w_row['c'];
    }

    $res = $pdo->query("SELECT COUNT(*) AS c FROM items WHERE status = 'active' AND (is_deleted = 0 OR is_deleted IS NULL)");
    if ($res && $r = $res->fetch(PDO::FETCH_ASSOC)) { $totalItems = (int)$r['c']; }

    $res = $pdo->query("SELECT COUNT(*) AS c FROM bids");
    if ($res && $r = $res->fetch(PDO::FETCH_ASSOC)) { $totalBidsOverall = (int)$r['c']; }

    $sql_query = "SELECT i.*, 
                         u.name AS seller_name, 
                         u.avatar AS seller_avatar,
                         c.name AS category_name,
                         COALESCE((SELECT COUNT(*) FROM wishlist w WHERE w.item_id = i.id), i.watcher_count, 0) AS total_watchers
                  FROM `items` i 
                  LEFT JOIN `users` u ON i.seller_id = u.id 
                  LEFT JOIN `categories` c ON i.category_id = c.id
                  WHERE i.`status` = 'active' AND (i.is_deleted = 0 OR i.is_deleted IS NULL)";
    $params = [];

    if ($selected_category > 0) {
        $sql_query .= " AND i.category_id = ?";
        $params[] = $selected_category;
    }
    if (!empty($search_query)) {
        $sql_query .= " AND (i.title LIKE ? OR i.description LIKE ?)";
        $like_term = "%" . $search_query . "%";
        $params[] = $like_term;
        $params[] = $like_term;
    }
    if ($min_price > 0) {
        $sql_query .= " AND i.current_price >= ?";
        $params[] = $min_price;
    }
    if ($max_price > 0) {
        $sql_query .= " AND i.current_price <= ?";
        $params[] = $max_price;
    }
    if (!empty($selected_condition) && in_array($selected_condition, ['new', 'used', 'refurbished'])) {
        $sql_query .= " AND (i.`condition_type` = ? OR i.`condition_type` LIKE ?)";
        $params[] = $selected_condition;
        $params[] = "%" . $selected_condition . "%";
    }
    if (!empty($selected_location)) {
        $sql_query .= " AND (i.location_city = ? OR i.city = ? OR i.location_address LIKE ?)";
        $params[] = $selected_location;
        $params[] = $selected_location;
        $params[] = "%" . $selected_location . "%";
    }

    if ($sort_by === 'price_low') {
        $sql_query .= " ORDER BY i.current_price ASC";
    } elseif ($sort_by === 'price_high') {
        $sql_query .= " ORDER BY i.current_price DESC";
    } elseif ($sort_by === 'ending_soon') {
        $sql_query .= " ORDER BY i.end_time ASC";
    } elseif ($sort_by === 'most_bids') {
        $sql_query .= " ORDER BY i.bid_count DESC";
    } else {
        $sql_query .= " ORDER BY i.created_at DESC";
    }

    if (!$show_all) {
        $sql_query .= " LIMIT 15";
    }

    $stmt_all = $pdo->prepare($sql_query);
    $stmt_all->execute($params);
    $all_products = $stmt_all->fetchAll(PDO::FETCH_ASSOC);

    $cat_query = $pdo->query("SELECT id, name, slug FROM categories WHERE status = 1 ORDER BY sort_order ASC");
    $categories_list = $cat_query ? $cat_query->fetchAll(PDO::FETCH_ASSOC) : [];
}

$active_filters = [];
if ($selected_category > 0 && $has_db) {
    $cat_stmt = $pdo->prepare("SELECT name FROM categories WHERE id = ? LIMIT 1");
    $cat_stmt->execute([$selected_category]);
    if ($cat_row = $cat_stmt->fetch(PDO::FETCH_ASSOC)) {
        $active_filters[] = ['key' => 'category_id', 'label' => 'Category', 'value' => $cat_row['name']];
    }
}
if (!empty($search_query)) {
    $active_filters[] = ['key' => 'q', 'label' => 'Search', 'value' => $search_query];
}
if ($selected_radius > 0) {
    $active_filters[] = ['key' => 'radius', 'label' => 'Radius', 'value' => $selected_radius . ' km'];
}
if ($min_price > 0) {
    $active_filters[] = ['key' => 'min_price', 'label' => 'Min Price', 'value' => 'Rs. ' . number_format($min_price, 0)];
}
if ($max_price > 0) {
    $active_filters[] = ['key' => 'max_price', 'label' => 'Max Price', 'value' => 'Rs. ' . number_format($max_price, 0)];
}

$current_get = $_GET;
function buildFilterUrl($remove_key = '', $current_get = []) {
    $params = $current_get;
    unset($params[$remove_key]);
    if (empty($params)) return 'live_auction.php';
    return 'live_auction.php?' . http_build_query($params);
}

$catalog_list = [];
if (!empty($all_products)) {
    foreach ($all_products as $p) {
        $p['dist_km'] = getDistanceKm($user_lat, $user_lng, $p['location_lat'] ?? 0, $p['location_lng'] ?? 0);
        if ($selected_radius > 0 && ($p['dist_km'] === null || $p['dist_km'] > $selected_radius)) {
            continue;
        }
        $catalog_list[] = $p;
    }
}
?>
<!-- Live Auction Arena Assets & Scoped Tailwind Config -->
<script src="https://cdn.tailwindcss.com"></script>
<script>
    tailwind.config = {
        corePlugins: {
            preflight: false
        }
    }
</script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

<style>
    .live-arena-wrapper {
        font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
    }
    @keyframes pulse-dot { 0%, 100% { opacity: 1; transform: scale(1); } 50% { opacity: 0.35; transform: scale(0.85); } }
    .live-dot { animation: pulse-dot 1.4s cubic-bezier(0.4, 0, 0.6, 1) infinite; }
    .filter-drawer-backdrop { opacity: 0; visibility: hidden; transition: opacity 0.3s cubic-bezier(0.16, 1, 0.3, 1), visibility 0.3s; }
    .filter-drawer-backdrop.is-open { opacity: 1; visibility: visible; }
    .filter-drawer-panel { transform: translateX(100%); transition: transform 0.35s cubic-bezier(0.16, 1, 0.3, 1); }
    .filter-drawer-panel.is-open { transform: translateX(0); }
    .thumbnail-img.active-thumb { border-color: #059669 !important; box-shadow: 0 0 0 2px rgba(5, 150, 105, 0.25); }
    @keyframes errorShake { 0%, 100% { transform: translateX(0); } 20%, 60% { transform: translateX(-4px); } 40%, 80% { transform: translateX(4px); } }
    .bid-input-error { border-color: #f43f5e !important; box-shadow: 0 0 0 3px rgba(244, 63, 94, 0.18) !important; animation: errorShake 0.3s ease-in-out; }
    .auction-modal-backdrop { opacity: 0; visibility: hidden; transition: opacity 0.25s ease, visibility 0.25s ease; }
    .auction-modal-backdrop.is-open { opacity: 1; visibility: visible; }

    /* Smooth Image Viewer & Lightbox Styles */
    .cursor-grab { cursor: grab !important; }
    .cursor-grabbing { cursor: grabbing !important; }
    .fs-image-modal {
        display: none !important;
        position: fixed !important;
        top: 0 !important;
        left: 0 !important;
        right: 0 !important;
        bottom: 0 !important;
        width: 100vw !important;
        height: 100vh !important;
        z-index: 9999999 !important;
        background: rgba(10, 15, 29, 0.98) !important;
        backdrop-filter: blur(16px) !important;
        -webkit-backdrop-filter: blur(16px) !important;
        opacity: 0;
        pointer-events: none;
        transition: opacity 0.25s cubic-bezier(0.16, 1, 0.3, 1);
        flex-direction: column !important;
        justify-content: space-between !important;
        align-items: stretch !important;
        overflow: hidden !important;
    }
    .fs-image-modal.is-open {
        display: flex !important;
        opacity: 1 !important;
        pointer-events: auto !important;
    }
    #fullscreenImageViewport {
        flex: 1 1 auto !important;
        width: 100% !important;
        height: calc(100vh - 150px) !important;
        min-height: 0 !important;
        min-width: 0 !important;
        position: relative !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        overflow: hidden !important;
    }
    #fullscreenImageElement {
        max-width: 92vw !important;
        max-height: 72vh !important;
        width: auto !important;
        height: auto !important;
        object-fit: contain !important;
        display: block !important;
        margin: auto !important;
        border-radius: 10px !important;
        box-shadow: 0 25px 60px -15px rgba(0, 0, 0, 0.85) !important;
        user-select: none !important;
        -webkit-user-drag: none !important;
    }
    .thumbnail-btn.active-thumb { border-color: #059669 !important; box-shadow: 0 0 0 2px rgba(5, 150, 105, 0.3) !important; opacity: 1 !important; }
    .fs-modal-thumb-btn.active-thumb { border-color: #10b981 !important; box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.4) !important; opacity: 1 !important; transform: scale(1.05); }
    .scrollbar-thin::-webkit-scrollbar { height: 6px; }
    .scrollbar-thin::-webkit-scrollbar-track { background: transparent; }
    .scrollbar-thin::-webkit-scrollbar-thumb { background: rgba(148, 163, 184, 0.4); border-radius: 4px; }
    .scrollbar-thin::-webkit-scrollbar-thumb:hover { background: rgba(100, 116, 139, 0.7); }

    /* Bayut & Luxury Real Estate Portal Showcase Styles */
    .property-breadcrumb-bar {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 14px;
        font-size: 13px;
        padding: 6px 0 14px 0;
        border-bottom: 1px solid #e2e8f0;
        margin-bottom: 18px;
    }
    .property-back-btn {
        color: #008489 !important;
        font-weight: 700;
        text-decoration: none !important;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: 13.5px;
        transition: color 0.15s ease, transform 0.15s ease;
    }
    .property-back-btn:hover {
        color: #006064 !important;
        transform: translateX(-2px);
    }
    .property-breadcrumbs {
        display: inline-flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 6px;
        color: #008489;
        font-size: 13px;
    }
    .property-breadcrumbs a {
        color: #008489 !important;
        text-decoration: none !important;
        transition: opacity 0.15s ease;
    }
    .property-breadcrumbs a:hover {
        text-decoration: underline !important;
    }
    .property-breadcrumbs .crumb-sep {
        color: #008489;
        font-size: 11px;
        opacity: 0.75;
    }
    .property-breadcrumbs .crumb-current {
        color: #475569;
        font-weight: 600;
    }
    /* ── Bayut Authentic Luxury Property Showcase Gallery Grid ── */
    .property-gallery-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 4px;
        border-radius: 16px;
        overflow: hidden;
        background: #0b1120;
        position: relative;
        box-shadow: 0 10px 30px -5px rgba(15, 23, 42, 0.12), 0 0 0 1px rgba(226, 232, 240, 0.85);
    }
    @media (min-width: 900px) {
        .property-gallery-grid {
            grid-template-columns: 2.15fr 1fr;
            height: 480px;
        }
    }
    .property-main-col {
        position: relative;
        height: 340px;
        overflow: hidden;
        background: #0f172a;
        cursor: pointer;
    }
    @media (min-width: 900px) {
        .property-main-col {
            height: 100%;
        }
    }
    .property-main-col img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.5s cubic-bezier(0.16, 1, 0.3, 1), filter 0.3s ease;
        display: block;
    }
    .property-main-col:hover img {
        transform: scale(1.025);
    }
    .property-side-col {
        display: none;
        grid-template-rows: repeat(3, 1fr);
        gap: 4px;
        height: 100%;
    }
    @media (min-width: 900px) {
        .property-side-col {
            display: grid;
        }
    }
    .property-side-item {
        position: relative;
        overflow: hidden;
        background: #0f172a;
        cursor: pointer;
    }
    .property-side-item img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.4s cubic-bezier(0.16, 1, 0.3, 1), filter 0.3s ease;
        display: block;
    }
    .property-side-item:hover img {
        transform: scale(1.05);
        filter: brightness(1.04);
    }
    .property-side-item .property-side-overlay {
        position: absolute;
        inset: 0;
        background: rgba(15, 23, 42, 0.1);
        transition: background 0.25s ease;
        pointer-events: none;
    }
    .property-side-item:hover .property-side-overlay {
        background: rgba(15, 23, 42, 0);
    }
    /* Bayut "Checked" Badge with Pulsing Verification Ring */
    @keyframes checkedPulseRing {
        0% { box-shadow: 0 0 0 0 rgba(40, 177, 109, 0.7), 0 4px 14px rgba(40, 177, 109, 0.45); }
        70% { box-shadow: 0 0 0 10px rgba(40, 177, 109, 0), 0 4px 14px rgba(40, 177, 109, 0.45); }
        100% { box-shadow: 0 0 0 0 rgba(40, 177, 109, 0), 0 4px 14px rgba(40, 177, 109, 0.45); }
    }
    .property-badge-checked {
        position: absolute;
        top: 16px;
        left: 16px;
        background: #28b16d;
        color: #ffffff;
        font-size: 13px;
        font-weight: 800;
        letter-spacing: 0.2px;
        padding: 6px 14px;
        border-radius: 8px;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        box-shadow: 0 4px 14px rgba(40, 177, 109, 0.45);
        z-index: 10;
        pointer-events: none;
        backdrop-filter: blur(4px);
        animation: checkedPulseRing 2.6s infinite cubic-bezier(0.4, 0, 0.2, 1);
    }
    .property-badge-checked svg,
    .property-badge-checked i {
        font-size: 13px;
    }

    /* Cinematic Ken Burns Motion Tour Animation */
    @keyframes kenBurnsTour {
        0% {
            transform: scale(1) translate(0%, 0%);
        }
        50% {
            transform: scale(1.08) translate(-1.2%, -0.8%);
        }
        100% {
            transform: scale(1) translate(0%, 0%);
        }
    }
    .ken-burns-active {
        animation: kenBurnsTour 12s ease-in-out infinite alternate !important;
        will-change: transform;
    }

    /* Live Motion Pulse Indicator */
    @keyframes livePulseDotAnim {
        0%, 100% {
            transform: scale(1);
            opacity: 1;
            box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7);
        }
        50% {
            transform: scale(1.15);
            opacity: 0.85;
            box-shadow: 0 0 0 6px rgba(16, 185, 129, 0);
        }
    }
    .motion-pulse-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: #10b981;
        box-shadow: 0 0 8px #10b981;
        animation: livePulseDotAnim 1.8s infinite;
        display: inline-block;
        flex-shrink: 0;
    }
    .property-pill-btn.motion-tour-pill.is-active {
        background: #008489 !important;
        color: #ffffff !important;
        border-color: #008489 !important;
        box-shadow: 0 4px 18px rgba(0, 132, 137, 0.4) !important;
    }
    .property-pill-btn.motion-tour-pill.is-active i {
        color: #ffffff !important;
    }
    .property-pill-btn.motion-tour-pill.is-active .motion-pulse-dot {
        background: #34d399;
        box-shadow: 0 0 10px #34d399;
    }

    .property-bottom-actions {
        position: absolute;
        bottom: 16px;
        left: 16px;
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
        z-index: 10;
    }
    .property-pill-btn {
        background: rgba(255, 255, 255, 0.95) !important;
        color: #0f172a !important;
        font-weight: 700 !important;
        font-size: 13px !important;
        padding: 8px 18px !important;
        border-radius: 8px !important;
        border: 1px solid rgba(255, 255, 255, 0.8) !important;
        outline: none !important;
        cursor: pointer !important;
        display: inline-flex !important;
        align-items: center !important;
        gap: 8px !important;
        text-decoration: none !important;
        backdrop-filter: blur(10px) !important;
        -webkit-backdrop-filter: blur(10px) !important;
        box-shadow: 0 4px 16px rgba(0, 0, 0, 0.18) !important;
        transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1) !important;
    }
    .property-pill-btn i {
        color: #008489;
        font-size: 13px;
    }
    .property-pill-btn:hover {
        background: #ffffff !important;
        color: #008489 !important;
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(0, 0, 0, 0.25) !important;
    }
    .property-photo-count-badge {
        position: absolute;
        bottom: 12px;
        right: 12px;
        background: rgba(15, 23, 42, 0.88);
        color: #ffffff;
        font-size: 13px;
        font-weight: 700;
        padding: 6px 14px;
        border-radius: 8px;
        backdrop-filter: blur(8px);
        -webkit-backdrop-filter: blur(8px);
        border: 1px solid rgba(255, 255, 255, 0.2);
        display: inline-flex;
        align-items: center;
        gap: 7px;
        box-shadow: 0 4px 14px rgba(0, 0, 0, 0.35);
        z-index: 10;
        cursor: pointer;
        transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    }
    .property-photo-count-badge i {
        font-size: 12px;
        color: #38bdf8;
    }
    .property-photo-count-badge:hover {
        background: #008489;
        border-color: #008489;
        transform: translateY(-2px);
        box-shadow: 0 6px 18px rgba(0, 132, 137, 0.45);
    }
    .property-photo-count-badge:hover i {
        color: #ffffff;
    }
    .property-watermark-overlay {
        position: absolute;
        top: 38%;
        left: 50%;
        transform: translate(-50%, -50%);
        width: 160px;
        height: 160px;
        color: rgba(255, 255, 255, 0.16);
        pointer-events: none;
        z-index: 5;
    }

    /* Interactive Share Toast */
    .lot-share-toast {
        position: fixed;
        bottom: 24px;
        right: 24px;
        background: #0f172a;
        color: #ffffff;
        border: 1px solid #334155;
        border-radius: 12px;
        padding: 12px 20px;
        font-size: 13px;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 10px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.35);
        z-index: 99999;
        opacity: 0;
        transform: translateY(12px);
        pointer-events: none;
        transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
    }
    .lot-share-toast.is-active {
        opacity: 1;
        transform: translateY(0);
        pointer-events: auto;
    }

    /* Premium Real-Estate Discussion & Comments Styling */
    .comments-section-container {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 20px;
        padding: 28px;
        box-shadow: 0 10px 30px -5px rgba(15, 23, 42, 0.04), 0 0 0 1px rgba(226, 232, 240, 0.8);
        margin-bottom: 24px;
        position: relative;
        overflow: hidden;
    }
    .comments-section-container::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 3.5px;
        background: linear-gradient(90deg, #008489 0%, #059669 50%, #0ea5e9 100%);
    }
    .comment-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        padding: 20px;
        transition: all 0.22s cubic-bezier(0.16, 1, 0.3, 1);
        position: relative;
    }
    .comment-card:hover {
        border-color: #cbd5e1;
        box-shadow: 0 8px 24px -4px rgba(0, 132, 137, 0.1);
        transform: translateY(-1.5px);
    }
    .comment-card.is-seller {
        background: linear-gradient(to right, #f0fdfa 0%, #ffffff 100%);
        border-left: 3.5px solid #008489;
    }
    .comment-card.is-admin {
        background: linear-gradient(to right, #faf5ff 0%, #ffffff 100%);
        border-left: 3.5px solid #9333ea;
    }
    .comment-bubble {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        padding: 14px 18px;
        margin-top: 10px;
        font-size: 14px;
        line-height: 1.65;
        color: #1e293b;
        position: relative;
    }
    .comment-card.is-seller .comment-bubble {
        background: #ffffff;
        border-color: #ccfbf1;
        box-shadow: 0 1px 3px rgba(0, 132, 137, 0.05);
    }
    .comment-card.is-admin .comment-bubble {
        background: #ffffff;
        border-color: #f3e8ff;
        box-shadow: 0 1px 3px rgba(147, 51, 234, 0.05);
    }
    .comment-action-btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-weight: 700;
        font-size: 12px;
        padding: 5px 10px;
        border-radius: 8px;
        transition: all 0.15s ease;
        cursor: pointer;
        background: transparent;
        border: none;
        color: #64748b;
        user-select: none;
    }
    .comment-action-btn:hover {
        background: #f1f5f9;
        color: #0f172a;
    }
    .comment-action-btn.btn-reply {
        color: #008489;
    }
    .comment-action-btn.btn-reply:hover {
        background: #e6fffa;
        color: #006c70;
    }
    .comment-action-btn.is-helpful-active {
        color: #059669;
        background: #ecfdf5;
    }
    .comment-chip {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 14px;
        border-radius: 9999px;
        font-size: 12px;
        font-weight: 600;
        background: #f8fafc;
        color: #334155;
        border: 1px solid #e2e8f0;
        cursor: pointer;
        transition: all 0.18s cubic-bezier(0.16, 1, 0.3, 1);
        user-select: none;
    }
    .comment-chip:hover {
        background: #e6fffa;
        color: #008489;
        border-color: #99f6e4;
        box-shadow: 0 2px 8px rgba(0, 132, 137, 0.12);
        transform: translateY(-1px);
    }
    .comment-btn-primary {
        background: linear-gradient(135deg, #008489 0%, #006c70 100%) !important;
        color: #ffffff !important;
        font-weight: 700 !important;
        font-size: 13px !important;
        padding: 9px 24px !important;
        border-radius: 12px !important;
        border: none !important;
        cursor: pointer !important;
        display: inline-flex !important;
        align-items: center !important;
        gap: 7px !important;
        box-shadow: 0 4px 14px rgba(0, 132, 137, 0.35) !important;
        transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1) !important;
    }
    .comment-btn-primary:hover {
        background: linear-gradient(135deg, #00969b 0%, #008489 100%) !important;
        box-shadow: 0 6px 20px rgba(0, 132, 137, 0.5) !important;
        transform: translateY(-1px);
    }
    .comment-btn-primary:disabled {
        opacity: 0.6 !important;
        cursor: not-allowed !important;
        transform: none !important;
    }
</style>

<div class="live-arena-wrapper w-full">
    <!-- Header Breadcrumbs / Title Bar -->
    <div class="flex items-center justify-between flex-wrap gap-3 pb-4 mb-4 border-b border-slate-200">
        <div>
            <div class="flex items-center gap-2 text-xs text-slate-500 mb-1">
                <a href="dashboard.php" class="text-emerald-700 hover:text-emerald-800 font-semibold flex items-center gap-1 text-decoration-none">
                    <i class="fa-solid fa-house text-[11px]"></i> Dashboard
                </a>
                <span>&bull;</span>
                <span class="text-slate-600 font-medium">Live Auctions</span>
            </div>
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight flex items-center gap-2.5 m-0">
                <span class="flex h-3 w-3 relative">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-3 w-3 bg-emerald-500"></span>
                </span>
                Live Auction Arena
            </h1>
        </div>
        <div class="flex items-center gap-3 text-xs">
            <div class="flex items-center bg-slate-100 rounded-lg p-0.5 border border-slate-200">
                <a href="?switch_role=buyer<?php echo $item_id ? '&id='.$item_id : ''; ?>" class="px-2.5 py-1 rounded-md font-semibold transition text-decoration-none <?php echo $role === 'buyer' ? 'bg-emerald-600 text-white shadow-xs' : 'text-slate-600 hover:text-slate-900'; ?>">Buyer</a>
                <a href="?switch_role=seller<?php echo $item_id ? '&id='.$item_id : ''; ?>" class="px-2.5 py-1 rounded-md font-semibold transition text-decoration-none <?php echo $role === 'seller' ? 'bg-emerald-600 text-white shadow-xs' : 'text-slate-600 hover:text-slate-900'; ?>">Seller</a>
            </div>
            <a href="wallet.php" class="px-3 py-1.5 rounded-lg border border-slate-200 bg-white hover:bg-slate-50 font-semibold text-slate-700 transition text-decoration-none flex items-center gap-1.5 shadow-xs">
                <i class="fa-solid fa-wallet text-emerald-600"></i> Wallet
            </a>
        </div>
    </div>

<div class="max-w-7xl mx-auto px-4 sm:px-6 pt-6 pb-12 space-y-6 flex-1 w-full">

    <div id="topNoticeBanner" class="flex items-center justify-between bg-white border border-amber-200/80 rounded-xl px-4 py-3.5 shadow-sm text-sm">
        <div class="flex items-center gap-3 text-slate-700">
            <span class="flex items-center justify-center w-8 h-8 rounded-lg bg-amber-50 text-amber-600 shrink-0">
                <i class="fa-solid fa-bell text-sm"></i>
            </span>
            <div>
                <strong class="text-slate-900 font-semibold">Anti-Sniping Rule:</strong>
                <span class="text-slate-600 text-xs sm:text-sm"> Any bid placed within the last 3 minutes of an auction will automatically extend closing time by 3 additional minutes.</span>
            </div>
        </div>
        <button type="button" onclick="document.getElementById('topNoticeBanner').remove()" class="text-slate-400 hover:text-slate-600 p-1.5 rounded-lg transition cursor-pointer" aria-label="Close">
            <i class="fa-solid fa-xmark text-base"></i>
        </button>
    </div>

    <div id="liveBidToast" class="hidden p-4 rounded-xl text-sm font-semibold flex items-center gap-3 shadow-sm transition"></div>

    <?php if (!empty($seller_message)): ?>
        <div class="p-4 rounded-xl text-sm font-semibold flex items-center gap-3 shadow-sm <?php echo $seller_success ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-rose-50 text-rose-800 border border-rose-200'; ?>">
            <i class="fa-solid <?php echo $seller_success ? 'fa-circle-check text-emerald-600 text-lg' : 'fa-circle-exclamation text-rose-600 text-lg'; ?>"></i>
            <div><?php echo htmlspecialchars($seller_message); ?></div>
        </div>
    <?php endif; ?>

    <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-sm space-y-3">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-2">
                <a href="<?php echo htmlspecialchars(buildFilterUrl('', $current_get)); ?>" class="inline-flex items-center gap-2 px-3.5 py-2 text-xs font-semibold text-slate-600 bg-white border border-slate-200 rounded-lg hover:bg-rose-50 hover:text-rose-600 hover:border-rose-200 transition">
                    <i class="fa-solid fa-rotate-left"></i> Reset
                </a>
                
                <button type="button" id="openDrawerBtn" class="inline-flex items-center gap-2 px-4 py-2 text-xs font-semibold text-white bg-[#83546b] hover:bg-[#6e4458] rounded-lg shadow-sm transition active:scale-95 cursor-pointer">
                    <i class="fa-solid fa-sliders text-xs"></i> Filter Lots
                    <?php if (count($active_filters) > 0): ?>
                        <span class="ml-1 w-4 h-4 rounded-full bg-white text-[#83546b] text-[10px] font-bold flex items-center justify-center">
                            <?php echo count($active_filters); ?>
                        </span>
                    <?php endif; ?>
                </button>

                <div class="hidden sm:flex items-center gap-1 ml-2 text-xs border-l border-slate-200 pl-3">
                    <span class="text-slate-400 font-semibold mr-1"><i class="fa-solid fa-location-dot text-emerald-600"></i> Near Me:</span>
                    <?php foreach([1, 5, 10, 25] as $r): ?>
                        <a href="?radius=<?php echo $r; ?><?php echo $item_id ? '&id='.$item_id : ''; ?>" 
                           class="px-2.5 py-1 rounded-md font-semibold border transition <?php echo $selected_radius === $r ? 'bg-emerald-600 text-white border-emerald-600' : 'bg-slate-50 text-slate-600 border-slate-200 hover:bg-slate-100'; ?>">
                            <?php echo $r; ?> km
                        </a>
                    <?php endforeach; ?>
                </div>

                <!-- View Switcher (Map View / Grid View toggle preserving URL filters) -->
                <?php 
                    $toggle_view = ($view_mode === 'map' ? 'grid' : 'map');
                    $view_params = $current_get;
                    $view_params['view'] = $toggle_view;
                    $view_toggle_url = 'live_auction.php?' . http_build_query($view_params);
                ?>
                <a href="<?php echo htmlspecialchars($view_toggle_url); ?>" 
                   class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-lg transition ml-1" 
                   title="<?php echo $view_mode === 'map' ? 'Switch to Grid View' : 'Switch to Map View'; ?>">
                    <i class="fa-solid <?php echo $view_mode === 'map' ? 'fa-table-cells text-emerald-600' : 'fa-map-location-dot text-emerald-600'; ?>"></i>
                    <span><?php echo $view_mode === 'map' ? 'Grid View' : 'Map View'; ?></span>
                </a>
            </div>

            <form method="GET" action="live_auction.php" class="flex items-center gap-2 flex-1 max-w-xl justify-end">
                <?php if ($item_id > 0): ?><input type="hidden" name="id" value="<?php echo $item_id; ?>"><?php endif; ?>
                <?php if ($show_all): ?><input type="hidden" name="all" value="1"><?php endif; ?>
                <?php if ($selected_category > 0): ?><input type="hidden" name="category_id" value="<?php echo $selected_category; ?>"><?php endif; ?>
                <?php if ($view_mode === 'map'): ?><input type="hidden" name="view" value="map"><?php endif; ?>

                <div class="relative flex-1 max-w-sm">
                    <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                    <input type="text" name="q" value="<?php echo htmlspecialchars($search_query); ?>" placeholder="Search lots by name or description..." class="w-full bg-slate-50 border border-slate-200 rounded-lg pl-8 pr-3 py-1.5 text-xs text-slate-800 outline-none focus:bg-white focus:border-emerald-600 focus:ring-2 focus:ring-emerald-100 transition">
                </div>

                <select name="sort" onchange="this.form.submit()" class="bg-white border border-slate-200 rounded-lg px-3 py-1.5 text-xs text-slate-700 outline-none focus:border-emerald-600 transition cursor-pointer">
                    <option value="latest" <?php echo $sort_by === 'latest' ? 'selected' : ''; ?>>Latest</option>
                    <option value="price_low" <?php echo $sort_by === 'price_low' ? 'selected' : ''; ?>>Price: Low to High</option>
                    <option value="price_high" <?php echo $sort_by === 'price_high' ? 'selected' : ''; ?>>Price: High to Low</option>
                    <option value="ending_soon" <?php echo $sort_by === 'ending_soon' ? 'selected' : ''; ?>>Ending Soon</option>
                    <option value="most_bids" <?php echo $sort_by === 'most_bids' ? 'selected' : ''; ?>>Most Bids</option>
                </select>
            </form>
        </div>

        <?php if (!empty($active_filters)): ?>
            <div class="flex items-center gap-2 flex-wrap pt-2 border-t border-slate-100">
                <span class="text-xs text-slate-400 font-medium">Applied:</span>
                <?php foreach ($active_filters as $af): ?>
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-emerald-50 text-emerald-800 border border-emerald-200">
                        <span class="font-semibold text-emerald-900"><?php echo htmlspecialchars($af['label']); ?>:</span>
                        <?php echo htmlspecialchars($af['value']); ?>
                        <a href="<?php echo htmlspecialchars(buildFilterUrl($af['key'], $current_get)); ?>" class="hover:text-emerald-950 font-bold ml-1 text-sm leading-none">&times;</a>
                    </span>
                <?php endforeach; ?>
                <a href="<?php echo htmlspecialchars(buildFilterUrl('', $current_get)); ?>" class="text-xs font-semibold text-rose-600 hover:underline ml-2">Clear All</a>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($view_mode === 'map'): ?>
        <div class="bg-white border border-slate-200 rounded-2xl p-4 shadow-sm space-y-2">
            <h3 class="text-sm font-bold text-slate-800 flex items-center gap-2">
                <i class="fa-solid fa-map-pin text-emerald-600"></i> Geo-Auction Map (Auctions Near You)
            </h3>
            <div id="liveAuctionMap" class="w-full h-80 rounded-xl overflow-hidden border border-slate-200"></div>
        </div>
    <?php endif; ?>

    <?php if ($current_item): 
        $is_current_deleted = !empty($current_item['is_deleted']);
        $display_price = !empty($current_item['current_price']) && $current_item['current_price'] > 0 
            ? (float)$current_item['current_price'] 
            : (float)$current_item['starting_price'];
        $min_inc = (float)($current_item['min_increment'] > 0 ? $current_item['min_increment'] : 100.00);
        $next_min_bid = $display_price + $min_inc;
        $reserve_met = (empty($current_item['reserve_price']) || $display_price >= (float)$current_item['reserve_price']);
        
        $gallery_images = (new AuctionImageRepository($pdo))->getImagesByItemId((int)$current_item['id'], $current_item['images'] ?? '', $current_item['featured_image'] ?? '');
        
        // Curated luxury architectural and automotive fallbacks to guarantee a rich 4-panel showcase grid
        $is_motors = (strtolower($current_item['category_name'] ?? '') === 'motors');
        if ($is_motors) {
            $curated_fallbacks = [
                'https://images.unsplash.com/photo-1617814076367-b759c7d7e738?auto=format&fit=crop&w=1200&q=80',
                'https://images.unsplash.com/photo-1605559424843-9e4c228bf1c2?auto=format&fit=crop&w=800&q=80',
                'https://images.unsplash.com/photo-1503376780353-7e6692767b70?auto=format&fit=crop&w=800&q=80',
                'https://images.unsplash.com/photo-1552519507-da3b142c6e3d?auto=format&fit=crop&w=800&q=80'
            ];
        } else {
            $curated_fallbacks = [
                'https://images.unsplash.com/photo-1545324418-cc1a3fa10c00?auto=format&fit=crop&w=1200&q=80',
                'https://images.unsplash.com/photo-1556911220-e15b29be8c8f?auto=format&fit=crop&w=800&q=80',
                'https://images.unsplash.com/photo-1502672260266-1c1ef2d93688?auto=format&fit=crop&w=800&q=80',
                'https://images.unsplash.com/photo-1600585154340-be6161a56a0c?auto=format&fit=crop&w=800&q=80',
                'https://images.unsplash.com/photo-1600596542815-ffad4c1539a9?auto=format&fit=crop&w=800&q=80'
            ];
        }
        $showcase_photos = $gallery_images;
        $fb_i = 0;
        while (count($showcase_photos) < 4) {
            $showcase_photos[] = $curated_fallbacks[$fb_i % count($curated_fallbacks)];
            $fb_i++;
        }
        $main_img = !empty($showcase_photos[0]) ? $showcase_photos[0] : 'assets/image/Geo-auction.png';
        $is_owner_seller = ($role === 'seller' && (int)($current_item['seller_id'] ?? 0) === $user_id) || $role === 'admin';
        $dist_km = getDistanceKm($user_lat, $user_lng, $current_item['location_lat'] ?? 0, $current_item['location_lng'] ?? 0);
        $effective_item_end = (!empty($current_item['extended_time']) && $current_item['extended_time'] != '0000-00-00 00:00:00' && strtotime($current_item['extended_time']) > strtotime($current_item['end_time']))
            ? $current_item['extended_time']
            : $current_item['end_time'];
        
        $hero_seller_name = $current_item['seller_name'] ?? 'Authorized Vendor';
        $hero_seller_avatar = getSellerAvatarUrl($current_item['seller_avatar'] ?? '', $hero_seller_name);
    ?>
    <?php if ($is_current_deleted): ?>
        <div class="mb-4 p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 flex items-center gap-3 shadow-xs">
            <i class="fa-solid fa-triangle-exclamation text-rose-600 text-xl shrink-0"></i>
            <div>
                <h4 class="font-bold text-sm">Notice: This auction listing has been soft-deleted / withdrawn by the seller.</h4>
                <p class="text-xs text-rose-600 mt-0.5">This item is hidden from the public catalog and active bidding is disabled.</p>
            </div>
        </div>
    <?php endif; ?>

    <!-- ── Item Breadcrumbs Bar ── -->
    <div class="property-breadcrumb-bar">
        <a href="live_auction.php?all=1" class="property-back-btn">
            <i class="fa-solid fa-angle-left"></i> Back to Catalog
        </a>
        <div class="property-breadcrumbs">
            <a href="live_auction.php" title="Home"><i class="fa-solid fa-house"></i></a>
            <span class="crumb-sep">&gt;</span>
            <a href="live_auction.php?category_id=<?php echo (int)($current_item['category_id'] ?? 0); ?>">
                <?php echo htmlspecialchars($current_item['category_name'] ?? 'Auctions'); ?>
            </a>
            <?php if (!empty($current_item['location_city'])): ?>
            <span class="crumb-sep">&gt;</span>
            <a href="live_auction.php?city=<?php echo urlencode($current_item['location_city']); ?>">
                <?php echo htmlspecialchars($current_item['location_city']); ?>
            </a>
            <?php endif; ?>
            <?php if (!empty($current_item['location_address']) || !empty($current_item['location_area'])): ?>
            <span class="crumb-sep">&gt;</span>
            <span><?php echo htmlspecialchars($current_item['location_address'] ?? ($current_item['location_area'] ?? '')); ?></span>
            <?php endif; ?>
            <span class="crumb-sep">&gt;</span>
            <span class="crumb-current">Lot #<?php echo htmlspecialchars($current_item['auction_code'] ?? ('LOT-'.$current_item['id'])); ?></span>
        </div>
    </div>

    <!-- ── Bayut Luxury Property Showcase Gallery Grid ── -->
    <div class="property-gallery-grid mb-4">
        <!-- Main Primary Large Photo (Left Column ~68%) -->
        <div class="property-main-col group cursor-pointer" id="propertyHeroMainBox" onclick="openFullscreenViewer()" title="Click to open fullscreen view">
            <img id="mainImage" 
                 src="<?php echo htmlspecialchars($showcase_photos[0]); ?>" 
                 alt="<?php echo htmlspecialchars($current_item['title']); ?>" 
                 class="w-full h-full object-cover"
                 onerror="this.onerror=null; this.src='assets/image/Geo-auction.png';">
            
            <!-- Top-Left Bayut "Checked" Badge -->
            <div class="property-badge-checked" title="Checked & Verified Listing">
                <i class="fa-solid fa-check"></i>
                <span>Checked</span>
            </div>

            <!-- Faint Falcon / Luxury Crest Watermark -->
            <div class="property-watermark-overlay" aria-hidden="true">
                <svg viewBox="0 0 100 100" fill="currentColor">
                    <path d="M50 12 C44 23 28 32 14 42 C24 45 34 42 41 37 C34 47 24 57 19 67 C29 65 39 59 47 52 C44 62 39 75 37 85 C44 77 49 67 51 59 C53 67 58 77 65 85 C63 75 58 62 55 52 C63 59 73 65 83 67 C78 57 68 47 61 37 C68 42 78 45 88 42 C74 32 58 23 52 12 Z" opacity="0.85"/>
                </svg>
            </div>

            <!-- Top-Right Actions: Live Auction, Watchlist, Share, Fullscreen -->
            <div class="absolute top-4 right-4 z-20 flex items-center gap-2">
                <span class="px-3 py-1.5 rounded-full bg-rose-600/95 backdrop-blur-md text-white text-[11px] font-bold uppercase tracking-wider shadow pointer-events-none flex items-center gap-1.5 border border-rose-400/40">
                    <span class="w-2 h-2 rounded-full bg-white live-dot"></span> Live Auction
                </span>
                <button type="button" onclick="event.stopPropagation(); toggleWatchlist(<?php echo $current_item['id']; ?>)" 
                        class="w-10 h-10 rounded-full bg-white/95 hover:bg-white text-rose-500 shadow-md flex items-center justify-center transition cursor-pointer hover:scale-105 active:scale-95 border border-slate-100" title="Add to Watchlist">
                    <i class="fa-<?php echo $is_watched ? 'solid' : 'regular'; ?> fa-heart text-sm" id="watchlistIcon"></i>
                </button>
                <button type="button" onclick="event.stopPropagation(); shareAuctionLot()" 
                        class="w-10 h-10 rounded-full bg-white/95 hover:bg-white text-slate-700 hover:text-[#008489] shadow-md flex items-center justify-center transition cursor-pointer hover:scale-105 active:scale-95 border border-slate-100 text-xs" title="Share Lot">
                    <i class="fa-solid fa-share-nodes"></i>
                </button>
                <button type="button" onclick="event.stopPropagation(); openFullscreenViewer()" 
                        class="w-10 h-10 rounded-full bg-slate-900/85 hover:bg-slate-900 text-white shadow-md flex items-center justify-center transition cursor-pointer hover:scale-105 active:scale-95 text-xs border border-white/20" title="Expand Fullscreen (F)">
                    <i class="fa-solid fa-expand"></i>
                </button>
            </div>

            <!-- Bottom-Left Action Pills -->
            <div class="property-bottom-actions">
                <button type="button" id="motionTourToggleBtn" onclick="event.stopPropagation(); toggleMotionTour()" class="property-pill-btn motion-tour-pill" title="Toggle Ken Burns Live Motion Tour">
                    <span class="motion-pulse-dot"></span>
                    <i class="fa-solid fa-play" id="motionTourIcon"></i>
                    <span id="motionTourLabel">Live Motion Tour</span>
                </button>
                <button type="button" onclick="event.stopPropagation(); openVideoTourModal()" class="property-pill-btn" title="Open 1080p Virtual Walkthrough">
                    <i class="fa-solid fa-video"></i> Video Tour
                </button>
                <button type="button" onclick="event.stopPropagation(); openFullscreenViewer()" class="property-pill-btn" title="View all photos fullscreen">
                    <i class="fa-solid fa-images"></i> View all photos
                </button>
            </div>
        </div>

        <!-- Right Side: 3 Stacked Preview Photos (Right Column ~32%) -->
        <div class="property-side-col">
            <div class="property-side-item" onclick="selectGalleryImage(1)" title="Click to view image 2">
                <img src="<?php echo htmlspecialchars($showcase_photos[1]); ?>" 
                     alt="Property View 2" 
                     onerror="this.onerror=null; this.src='assets/image/Geo-auction.png';">
                <div class="property-side-overlay"></div>
            </div>
            <div class="property-side-item" onclick="selectGalleryImage(2)" title="Click to view image 3">
                <img src="<?php echo htmlspecialchars($showcase_photos[2]); ?>" 
                     alt="Property View 3" 
                     onerror="this.onerror=null; this.src='assets/image/Geo-auction.png';">
                <div class="property-side-overlay"></div>
            </div>
            <div class="property-side-item" onclick="selectGalleryImage(3)" title="Click to view all photos">
                <img src="<?php echo htmlspecialchars($showcase_photos[3]); ?>" 
                     alt="Property View 4" 
                     onerror="this.onerror=null; this.src='assets/image/Geo-auction.png';">
                <div class="property-side-overlay"></div>
                <div class="property-photo-count-badge" onclick="event.stopPropagation(); openFullscreenViewer()" style="pointer-events: auto; cursor: pointer;" title="View all <?php echo count($gallery_images); ?> photos">
                    <i class="fa-solid fa-camera"></i> <span id="propertyTotalPhotosCount"><?php echo count($gallery_images); ?></span> Photos
                </div>
            </div>
        </div>
    </div>

    <!-- Floating Share Confirmation Toast -->
    <div id="lotShareToast" class="lot-share-toast">
        <i class="fa-solid fa-circle-check text-emerald-400 text-base"></i>
        <span>Listing link copied to clipboard!</span>
    </div>

    <!-- Quick Thumbnails Filmstrip -->
    <?php if (count($showcase_photos) > 1): ?>
        <div class="thumbnail-row flex items-center gap-2 overflow-x-auto pb-2 mb-5 scrollbar-thin">
            <?php foreach ($showcase_photos as $index => $img): ?>
                <button type="button" 
                        onclick="selectGalleryImage(<?php echo $index; ?>)" 
                        class="thumbnail-btn shrink-0 rounded-lg overflow-hidden border-2 transition focus:outline-none cursor-pointer <?php echo $index === 0 ? 'active-thumb border-teal-600 ring-2 ring-teal-500/25' : 'border-slate-200 hover:border-slate-400 opacity-75 hover:opacity-100'; ?>"
                        data-index="<?php echo $index; ?>"
                        aria-label="View photo <?php echo $index + 1; ?>">
                    <img src="<?php echo htmlspecialchars($img); ?>" 
                         alt="Photo <?php echo $index + 1; ?>"
                         class="thumbnail-img w-20 h-14 object-cover pointer-events-none"
                         onerror="this.onerror=null; this.src='assets/image/Geo-auction.png';">
                </button>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <section class="bg-white border border-slate-200 rounded-2xl overflow-hidden shadow-sm mb-6">
        <div class="grid grid-cols-1 lg:grid-cols-12">
            <!-- Left Column: Property Highlights & Verified Agent (5 cols) -->
            <div class="lg:col-span-5 p-6 bg-slate-50/70 border-b lg:border-b-0 lg:border-r border-slate-200 flex flex-col justify-between">
                <div class="space-y-4">
                    <div class="flex items-center justify-between pb-3 border-b border-slate-200">
                        <div>
                            <span class="text-xs font-bold text-teal-800 uppercase tracking-wider bg-teal-50 border border-teal-200 px-2.5 py-1 rounded-md">
                                Verified Lot #<?php echo htmlspecialchars($current_item['auction_code'] ?? ('LOT-'.$current_item['id'])); ?>
                            </span>
                        </div>
                        <span class="text-xs font-semibold px-2.5 py-1 rounded-md border <?php echo $reserve_met ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-amber-50 text-amber-700 border-amber-200'; ?>">
                            <?php echo $reserve_met ? 'Reserve Met' : 'Reserve Pending'; ?>
                        </span>
                    </div>

                    <!-- Bayut TruBroker™ Luxury Agent Card with Dynamic Database Integration -->
                    <?php 
                        $agent_row = null;
                        $community_row = null;
                        if ($has_db) {
                            try {
                                $ag_stmt = $pdo->query("SELECT * FROM `agents` WHERE `id` = 1 LIMIT 1");
                                $agent_row = $ag_stmt ? $ag_stmt->fetch(PDO::FETCH_ASSOC) : null;
                                if ($agent_row) {
                                    $fc_stmt = $pdo->prepare("SELECT * FROM `featured_communities` WHERE `agent_id` = ? ORDER BY `id` DESC LIMIT 1");
                                    $fc_stmt->execute([(int)$agent_row['id']]);
                                    $community_row = $fc_stmt->fetch(PDO::FETCH_ASSOC);
                                }
                            } catch (\Throwable $ignored) {}
                        }

                        $broker_id = (int)($agent_row['id'] ?? 1);
                        $broker_name = $agent_row['name'] ?? 'Asif Raza Khan';
                        $broker_avatar = !empty($agent_row['avatar_url']) && file_exists(__DIR__ . '/' . $agent_row['avatar_url'])
                            ? $agent_row['avatar_url'] 
                            : (file_exists(__DIR__ . '/uploads/broker_asif.png') ? 'uploads/broker_asif.png' : $hero_seller_avatar);
                        $broker_phone = $agent_row['phone'] ?? "+971509876543";
                        $broker_wa = $agent_row['whatsapp_number'] ?? "+971509876543";
                        $broker_wa_clean = preg_replace('/[^0-9]/', '', $broker_wa);
                        $broker_wa_link = "https://wa.me/" . $broker_wa_clean . "?text=" . urlencode("Hello " . $broker_name . ", I am inquiring about " . ($current_item['title'] ?? 'Lot #' . $current_item['id']) . " on Geo Auction.");
                        $broker_email = $agent_row['email'] ?? "asif.khan@arkrealestate.ae";
                        $broker_email_link = !empty($current_item['seller_id']) 
                            ? "messages.php?user_id=" . (int)$current_item['seller_id'] . "&item_id=" . (int)$current_item['id'] 
                            : "mailto:" . $broker_email;
                        $agency_logo = !empty($agent_row['agency_logo']) && file_exists(__DIR__ . '/' . $agent_row['agency_logo'])
                            ? $agent_row['agency_logo']
                            : (file_exists(__DIR__ . '/uploads/ark_logo.png') ? 'uploads/ark_logo.png' : '');
                        $agency_url = $agent_row['agency_properties_url'] ?? "live_auction.php?all=1";

                        $community_title = $community_row['title'] ?? 'Mudon Al Ranim 8';
                        $community_desc = $community_row['description'] ?? 'See the community attractions and lifestyle';
                        $community_img = !empty($community_row['image_url']) && file_exists(__DIR__ . '/' . $community_row['image_url'])
                            ? $community_row['image_url']
                            : 'uploads/items/mudon_aerial.jpg';
                        $community_url = $community_row['target_url'] ?? 'live_auction.php?id=243';
                    ?>
                    <div id="realEstateAgentCard" class="bg-white rounded-2xl border border-slate-200/90 shadow-sm overflow-hidden" data-agent-id="<?php echo $broker_id; ?>">
                        <!-- Top Wave / Dark Teal Gradient Banner with TruBroker™ Branding -->
                        <div class="relative h-16 w-full flex items-center justify-end px-5 overflow-hidden" 
                             style="background: linear-gradient(135deg, #104f47 0%, #0d5f57 45%, #073834 100%);">
                            <!-- Subtle Diagonal Geometric Texture -->
                            <div class="absolute inset-0 opacity-20 pointer-events-none" 
                                 style="background-image: repeating-linear-gradient(45deg, transparent, transparent 12px, rgba(255,255,255,0.12) 12px, rgba(255,255,255,0.12) 24px);"></div>
                            <div class="relative z-10 flex items-center text-white font-bold tracking-tight text-base sm:text-lg select-none">
                                <span>TruBroker</span><span class="text-[10px] font-bold align-super ml-0.5">TM</span>
                            </div>
                        </div>

                        <!-- Card Body with Overlapping Avatar -->
                        <div class="p-4 sm:p-5 pt-0">
                            <!-- Avatar & Broker Name Row -->
                            <div class="flex items-center gap-3.5 -mt-9 mb-3.5">
                                <div class="relative shrink-0">
                                    <img id="agentCardAvatar" 
                                         src="<?php echo htmlspecialchars($broker_avatar); ?>" 
                                         alt="<?php echo htmlspecialchars($broker_name); ?>" 
                                         class="w-20 h-20 rounded-full object-cover border-[3.5px] border-white shadow-md bg-white shrink-0"
                                         onerror="this.onerror=null; this.src='https://ui-avatars.com/api/?name=Asif+Raza+Khan&background=008489&color=fff&size=160&bold=true';">
                                </div>
                                <div class="pt-8 min-w-0 flex-1">
                                    <h3 id="agentCardName" class="text-slate-900 font-bold text-lg leading-tight m-0 truncate">
                                        <?php echo htmlspecialchars($broker_name); ?>
                                    </h3>
                                </div>
                            </div>

                            <!-- TruBroker Verified Badges Row -->
                            <div class="flex items-center gap-2 mb-3.5">
                                <div class="flex-1 bg-[#eef7fc] border border-[#d6eef8] rounded-lg py-1.5 px-2.5 flex items-center justify-center gap-1.5 text-xs font-semibold text-[#006272] shadow-2xs">
                                    <span class="text-sm">💎</span>
                                    <span class="truncate">Quality Lister</span>
                                </div>
                                <div class="flex-1 bg-[#f8f0fa] border border-[#ecd9f0] rounded-lg py-1.5 px-2.5 flex items-center justify-center gap-1.5 text-xs font-semibold text-[#663073] shadow-2xs">
                                    <i class="fa-solid fa-phone-volume text-[11px] text-[#8e44ad]"></i>
                                    <span class="truncate">Responsive Broker</span>
                                </div>
                            </div>

                            <!-- Communication Actions (Email, Call, WhatsApp) -->
                            <div class="flex items-center gap-2 mb-3.5">
                                <a href="<?php echo htmlspecialchars($broker_email_link); ?>" 
                                   id="agentActionEmail"
                                   data-agent-action="email"
                                   class="agent-contact-btn flex-1 bg-[#eef6f6] hover:bg-[#e2f1f1] active:scale-98 text-[#006272] font-semibold text-xs py-2 px-3 rounded-lg flex items-center justify-center gap-2 transition border border-[#d3ebeb] text-decoration-none shadow-2xs cursor-pointer">
                                    <i class="fa-solid fa-envelope text-sm text-[#008489]"></i>
                                    <span>Email</span>
                                </a>
                                <a href="tel:<?php echo htmlspecialchars($broker_phone); ?>" 
                                   id="agentActionCall"
                                   data-agent-action="call"
                                   class="agent-contact-btn flex-1 bg-[#eef6f6] hover:bg-[#e2f1f1] active:scale-98 text-[#006272] font-semibold text-xs py-2 px-3 rounded-lg flex items-center justify-center gap-2 transition border border-[#d3ebeb] text-decoration-none shadow-2xs cursor-pointer">
                                    <i class="fa-solid fa-phone text-sm text-[#008489]"></i>
                                    <span>Call</span>
                                </a>
                                <a href="<?php echo htmlspecialchars($broker_wa_link); ?>" target="_blank" rel="noopener noreferrer" 
                                   id="agentActionWhatsapp"
                                   data-agent-action="whatsapp"
                                   class="agent-contact-btn w-12 bg-[#eef6f6] hover:bg-[#e2f1f1] active:scale-98 text-emerald-600 font-semibold py-2 rounded-lg flex items-center justify-center transition border border-[#d3ebeb] text-decoration-none shadow-2xs cursor-pointer" 
                                   title="Chat on WhatsApp">
                                    <i class="fa-brands fa-whatsapp text-lg text-emerald-600"></i>
                                </a>
                            </div>

                            <!-- Agency Footer Row -->
                            <div class="border-t border-slate-100 pt-3 pb-1 flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <?php if (!empty($agency_logo)): ?>
                                        <img id="agentCardAgencyLogo" src="<?php echo htmlspecialchars($agency_logo); ?>" alt="ARK Real Estate" class="h-8 w-11 object-contain rounded bg-slate-900 border border-slate-200 p-0.5 shadow-2xs">
                                    <?php else: ?>
                                        <div class="h-8 w-11 rounded bg-slate-900 text-white flex items-center justify-center text-[9px] font-bold tracking-tight">ARK</div>
                                    <?php endif; ?>
                                </div>
                                <a href="<?php echo htmlspecialchars($agency_url); ?>" 
                                   id="agentActionViewAll"
                                   data-agent-action="view_properties"
                                   class="agent-contact-btn text-xs font-semibold text-[#006272] hover:text-[#004753] hover:underline flex items-center gap-1.5 transition text-decoration-none">
                                    View all properties <i class="fa-solid fa-angle-right text-[10px]"></i>
                                </a>
                            </div>

                            <!-- Featured Property/Community Sub-Card (Bayut Signature) -->
                            <a href="<?php echo htmlspecialchars($community_url); ?>" 
                               id="agentFeaturedCommunityCard"
                               class="mt-2.5 p-2 rounded-xl border border-slate-200/90 bg-slate-50/80 hover:bg-teal-50/50 hover:border-teal-200/80 transition-all duration-200 flex items-center justify-between gap-3 text-decoration-none group shadow-2xs">
                                <div class="flex items-center gap-2.5 min-w-0">
                                    <img id="agentCommunityThumb" 
                                         src="<?php echo htmlspecialchars($community_img); ?>" 
                                         alt="<?php echo htmlspecialchars($community_title); ?>" 
                                         class="w-12 h-12 rounded-lg object-cover shrink-0 border border-slate-200 shadow-2xs group-hover:scale-105 transition duration-200"
                                         onerror="this.onerror=null; this.src='assets/image/Geo-auction.png';">
                                    <div class="min-w-0">
                                        <h4 id="agentCommunityTitle" class="text-xs font-bold text-slate-900 group-hover:text-[#008489] transition m-0 truncate">
                                            <?php echo htmlspecialchars($community_title); ?>
                                        </h4>
                                        <p id="agentCommunityDesc" class="text-[11px] text-slate-500 m-0 truncate leading-tight mt-0.5">
                                            <?php echo htmlspecialchars($community_desc); ?>
                                        </p>
                                    </div>
                                </div>
                                <div class="w-6 h-6 rounded-full bg-white group-hover:bg-[#008489] group-hover:text-white text-slate-400 flex items-center justify-center transition shrink-0 border border-slate-200 shadow-2xs">
                                    <i class="fa-solid fa-chevron-right text-[9px]"></i>
                                </div>
                            </a>
                        </div>
                    </div>

                    <!-- Item Specifications Overview -->
                    <div class="bg-white rounded-xl border border-slate-200/80 p-4 space-y-2.5 text-xs">
                        <h4 class="font-bold text-slate-800 text-xs uppercase tracking-wider flex items-center gap-1.5 pb-2 border-b border-slate-100">
                            <i class="fa-solid <?php echo strtolower($current_item['category_name'] ?? '') === 'motors' ? 'fa-car' : 'fa-list-check'; ?> text-[#008489]"></i> 
                            <?php echo htmlspecialchars($current_item['category_name'] ?? 'Item'); ?> Specifications
                        </h4>
                        <div class="grid grid-cols-2 gap-3 pt-1">
                            <div>
                                <span class="text-slate-400 block">Category</span>
                                <strong class="text-slate-800"><?php echo htmlspecialchars($current_item['category_name'] ?? 'General Auction'); ?></strong>
                            </div>
                            <?php if (!empty($current_item['brand']) || !empty($current_item['model'])): ?>
                            <div>
                                <span class="text-slate-400 block">Make &amp; Model</span>
                                <strong class="text-slate-800"><?php echo htmlspecialchars(trim(($current_item['brand'] ?? '') . ' ' . ($current_item['model'] ?? ''))); ?></strong>
                            </div>
                            <?php else: ?>
                            <div>
                                <span class="text-slate-400 block">Condition</span>
                                <strong class="text-slate-800"><?php echo htmlspecialchars(!empty($current_item['condition_type']) ? ucfirst($current_item['condition_type']) : 'Inspected & Certified'); ?></strong>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($current_item['year'])): ?>
                            <div>
                                <span class="text-slate-400 block">Model Year</span>
                                <strong class="text-slate-800"><?php echo htmlspecialchars((string)$current_item['year']); ?></strong>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($current_item['mileage'])): ?>
                            <div>
                                <span class="text-slate-400 block">Mileage</span>
                                <strong class="text-slate-800"><?php echo htmlspecialchars((string)$current_item['mileage']); ?></strong>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($current_item['engine_type'])): ?>
                            <div>
                                <span class="text-slate-400 block">Engine / Drivetrain</span>
                                <strong class="text-slate-800"><?php echo htmlspecialchars((string)$current_item['engine_type']); ?></strong>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($current_item['color'])): ?>
                            <div>
                                <span class="text-slate-400 block">Color</span>
                                <strong class="text-slate-800"><?php echo htmlspecialchars((string)$current_item['color']); ?></strong>
                            </div>
                            <?php endif; ?>
                            <div>
                                <span class="text-slate-400 block">Location</span>
                                <strong class="text-slate-800"><?php echo htmlspecialchars(!empty($current_item['location_city']) ? $current_item['location_city'] : 'Islamabad'); ?><?php echo !empty($current_item['location_address']) ? ', ' . htmlspecialchars($current_item['location_address']) : ''; ?></strong>
                            </div>
                            <div>
                                <span class="text-slate-400 block">Proximity</span>
                                <strong class="text-teal-700 font-bold"><?php echo $dist_km !== null ? $dist_km.' km away' : 'Verified Location'; ?></strong>
                            </div>
                            <div>
                                <span class="text-slate-400 block">Anti-Sniping</span>
                                <strong class="text-emerald-700">+3 Mins Extension</strong>
                            </div>
                            <div>
                                <span class="text-slate-400 block">Opening Bid</span>
                                <strong class="text-slate-800">Rs. <?php echo number_format($current_item['starting_price'], 2); ?></strong>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            <div class="lg:col-span-7 p-6 sm:p-8 flex flex-col justify-between">
                <div>
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="inline-block text-xs font-bold text-emerald-700 uppercase tracking-wider bg-emerald-50 border border-emerald-200 px-2.5 py-0.5 rounded-md">
                                    Lot #<?php echo htmlspecialchars($current_item['auction_code'] ?? ('LOT-'.$current_item['id'])); ?>
                                </span>
                                <span id="reserveStatusBadge" class="text-xs font-bold px-2.5 py-0.5 rounded-md border <?php echo $reserve_met ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-amber-50 text-amber-700 border-amber-200'; ?>">
                                    <?php echo $reserve_met ? 'Reserve Price Met' : 'Reserve Price Not Met'; ?>
                                </span>
                                <span class="text-[11px] text-slate-500 font-semibold bg-slate-100 px-2 py-0.5 rounded border border-slate-200">
                                    <i class="fa-solid fa-eye text-slate-400 mr-1"></i><span id="watcherCountDisplay"><?php echo $watcher_count; ?></span> watchers
                                </span>
                            </div>
                            <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-slate-900 mt-2 <?php echo $is_current_deleted ? 'line-through text-rose-600' : ''; ?>">
                                <?php echo htmlspecialchars($current_item['title'] ?? 'Auction Lot'); ?>
                            </h1>
                        </div>
                        <div class="text-right shrink-0 bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5">
                            <span class="text-xs text-slate-400 font-medium block">Current Highest Bid</span>
                            <span class="text-2xl font-extrabold text-emerald-600">
                                Rs. <span id="currentPriceDisplay"><?php echo number_format($display_price, 2); ?></span>
                            </span>
                            <span class="text-[11px] text-slate-500 block mt-0.5">
                                Min Inc: Rs. <?php echo number_format($min_inc, 0); ?>
                            </span>
                        </div>
                    </div>

                    <p class="text-sm text-slate-600 mt-4 leading-relaxed whitespace-pre-line">
                        <?php echo htmlspecialchars($current_item['description'] ?? ''); ?>
                    </p>

                    <div class="grid grid-cols-3 gap-3 mt-6 p-4 rounded-xl bg-slate-50 border border-slate-200 text-center">
                        <div>
                            <span class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Next Allowed Bid</span>
                            <p class="text-base font-bold text-slate-800 mt-0.5" id="nextMinBidDisplay">Rs. <?php echo number_format($next_min_bid, 2); ?></p>
                        </div>
                        <div>
                            <span class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Total Bids Placed</span>
                            <p class="text-base font-bold text-slate-800 mt-0.5" id="bidCountDisplay"><?php echo (int)($current_item['bid_count'] ?? 0); ?></p>
                        </div>
                        <div>
                            <span class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Time Remaining</span>
                            <p class="text-base font-bold text-rose-600 mt-0.5" id="timerDisplay">Calculating...</p>
                        </div>
                    </div>

                    <div class="mt-6">
                        <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500 mb-2.5 flex items-center gap-1.5">
                            <i class="fa-solid fa-clock-rotate-left text-xs"></i> Recent Bid Feed
                        </h3>
                        <div class="space-y-2 max-h-40 overflow-y-auto pr-1" id="bidHistoryFeed">
                            <?php if (!empty($bids_history)): ?>
                                <?php foreach ($bids_history as $bid): 
                                    $parts = explode(' ', trim($bid['bidder_name']));
                                    $masked_name = htmlspecialchars($parts[0]) . (count($parts) > 1 ? ' ' . substr($parts[1], 0, 1) . '***' : ' ***');
                                ?>
                                    <div class="flex items-center justify-between p-2.5 rounded-lg bg-slate-50 border border-slate-100 text-xs">
                                        <div class="flex items-center gap-2.5">
                                            <div class="w-7 h-7 rounded-full bg-emerald-100 text-emerald-800 flex items-center justify-center font-bold text-[11px]">
                                                <?php echo strtoupper(substr($bid['bidder_name'] ?? 'B', 0, 1)); ?>
                                            </div>
                                            <div>
                                                <span class="font-semibold text-slate-800"><?php echo $masked_name; ?></span>
                                                <span class="text-[10px] text-slate-400 block"><?php echo date('M d, h:i A', strtotime($bid['created_at'])); ?></span>
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            <span class="font-bold text-emerald-700 text-sm">Rs. <?php echo number_format($bid['bid_amount'], 2); ?></span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="text-xs text-slate-400 italic py-2" id="noBidsNotice">No bids recorded yet. Place the opening bid!</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div> 

                <div class="mt-8 pt-6 border-t border-slate-200">
                    <?php if ($is_owner_seller): ?>
                        <div class="p-4 rounded-xl bg-slate-50 border border-slate-200 space-y-3">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-bold text-slate-700 uppercase tracking-wider flex items-center gap-1.5">
                                    <i class="fa-solid fa-crown text-amber-500"></i> Seller Lot Management
                                </span>
                                <span class="text-[11px] text-slate-500">You are the seller of this item</span>
                            </div>
                            <form method="POST" action="" class="flex flex-wrap items-center gap-2">
                                <input type="hidden" name="item_id" value="<?php echo $current_item['id']; ?>">
                                
                                <button type="submit" name="accept_bid" onclick="return confirm('Accept the highest bid and mark this auction as sold?');" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs rounded-lg transition shadow-xs flex items-center gap-1.5 cursor-pointer">
                                    <i class="fa-solid fa-check"></i> Accept Top Bid
                                </button>
                                
                                <button type="submit" name="reject_bid" onclick="return confirm('Reject bids and close this auction?');" class="px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white font-bold text-xs rounded-lg transition shadow-xs flex items-center gap-1.5 cursor-pointer">
                                    <i class="fa-solid fa-ban"></i> Reject & Close
                                </button>
                                
                                <?php if (!$is_current_deleted): ?>
                                    <button type="submit" name="delete_item" onclick="return confirm('Soft-delete this auction listing? It will immediately disappear from the public marketplace.');" class="px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white font-bold text-xs rounded-lg transition shadow-xs flex items-center gap-1.5 cursor-pointer">
                                        <i class="fa-solid fa-trash-alt"></i> Soft Delete Lot
                                    </button>
                                <?php else: ?>
                                    <span class="px-3 py-1.5 bg-rose-100 text-rose-700 rounded-lg text-xs font-bold flex items-center gap-1">
                                        <i class="fa-solid fa-ban"></i> Soft Deleted
                                    </span>
                                <?php endif; ?>
                            </form>
                        </div>
                    <?php elseif ($is_current_deleted): ?>
                        <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-center text-rose-700 font-semibold text-sm">
                            <i class="fa-solid fa-ban text-rose-600 mr-2"></i> Bidding is disabled because this auction lot was withdrawn/soft-deleted.
                        </div>
                    <?php elseif ($current_item['status'] === 'active'): ?>
                        <div class="space-y-3">
                            <!-- Quick Bid Increment Buttons (+Min, +500, +1k, +5k) -->
                            <div class="flex items-center gap-1.5 flex-wrap text-xs pb-1">
                                <span class="text-slate-400 font-semibold text-[11px] mr-1">Quick Bid:</span>
                                <button type="button" onclick="setQuickIncrement(0)" class="px-2.5 py-1 rounded-md bg-slate-100 hover:bg-emerald-50 hover:text-emerald-700 text-slate-700 border border-slate-200 text-xs font-semibold transition cursor-pointer">
                                    Min Bid
                                </button>
                                <button type="button" onclick="setQuickIncrement(500)" class="px-2.5 py-1 rounded-md bg-slate-100 hover:bg-emerald-50 hover:text-emerald-700 text-slate-700 border border-slate-200 text-xs font-semibold transition cursor-pointer">
                                    +Rs. 500
                                </button>
                                <button type="button" onclick="setQuickIncrement(1000)" class="px-2.5 py-1 rounded-md bg-slate-100 hover:bg-emerald-50 hover:text-emerald-700 text-slate-700 border border-slate-200 text-xs font-semibold transition cursor-pointer">
                                    +Rs. 1,000
                                </button>
                                <button type="button" onclick="setQuickIncrement(5000)" class="px-2.5 py-1 rounded-md bg-slate-100 hover:bg-emerald-50 hover:text-emerald-700 text-slate-700 border border-slate-200 text-xs font-semibold transition cursor-pointer">
                                    +Rs. 5,000
                                </button>
                            </div>

                            <form id="bidForm" class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
                                <input type="hidden" name="item_id" id="currentLotId" value="<?php echo $current_item['id']; ?>">
                                <div class="relative flex-1">
                                    <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-xs font-bold text-slate-400">Rs.</span>
                                    <input type="number" 
                                           step="10" 
                                           name="bid_amount" 
                                           id="bidAmountInput" 
                                           placeholder="Enter at least Rs. <?php echo number_format($next_min_bid, 2); ?>" 
                                           required 
                                           min="<?php echo $next_min_bid; ?>" 
                                           class="w-full bg-slate-50 border border-slate-200 rounded-xl pl-10 pr-4 py-3 text-sm font-semibold text-slate-900 focus:outline-none focus:border-emerald-600 focus:ring-2 focus:ring-emerald-100 transition">
                                </div>
                                <button type="submit" id="bidSubmitBtn" class="px-8 py-3 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-sm rounded-xl transition shadow hover:shadow-md shrink-0 flex items-center justify-center gap-2 active:scale-95 cursor-pointer">
                                    <i class="fa-solid fa-gavel text-xs"></i> Submit Bid
                                </button>
                            </form>
                            <div class="flex items-center gap-2 pt-1 flex-wrap">
                                <button type="button" onclick="openAutoBidPrompt(<?php echo $current_item['id']; ?>)" class="px-3.5 py-1.5 text-xs font-semibold bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg transition flex items-center gap-1.5 cursor-pointer">
                                    <i class="fa-solid fa-robot text-emerald-600"></i> Setup Auto-Bid (Bidbot)
                                </button>

                                <?php if (!empty($current_item['buy_it_now_price']) && (float)$current_item['buy_it_now_price'] > 0): ?>
                                    <button type="button" onclick="confirmBuyItNow(<?php echo $current_item['id']; ?>, <?php echo (float)$current_item['buy_it_now_price']; ?>)" 
                                            class="px-4 py-1.5 text-xs font-bold bg-amber-500 hover:bg-amber-600 text-white rounded-lg transition shadow-xs flex items-center gap-1.5 cursor-pointer">
                                        <i class="fa-solid fa-bolt"></i> Buy It Now for Rs. <?php echo number_format($current_item['buy_it_now_price'], 0); ?>
                                    </button>
                                <?php endif; ?>
                                <?php if (!empty($current_item['seller_id']) && (int)$current_item['seller_id'] !== $user_id): ?>
                                    <a href="messages.php?user_id=<?php echo (int)$current_item['seller_id']; ?>&item_id=<?php echo (int)$current_item['id']; ?>" 
                                       class="px-3.5 py-1.5 text-xs font-bold bg-emerald-50 hover:bg-emerald-100 text-emerald-800 border border-emerald-300 rounded-lg transition flex items-center gap-1.5 shadow-2xs text-decoration-none"
                                       title="Send direct message to seller">
                                        <i class="fa-solid fa-message text-emerald-600"></i> Message Seller
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="p-4 bg-slate-100 rounded-xl text-center text-sm font-semibold text-slate-600 border border-slate-200">
                            <i class="fa-solid fa-lock mr-2 text-slate-400"></i> This auction is <?php echo htmlspecialchars($current_item['status']); ?>. Bidding is now closed.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>
    


    <!-- ── Lot Q&A & Community Discussion (Upgraded Bayut / Real Estate Portal Style) ── -->
    <section class="comments-section-container">
        <div class="flex items-center justify-between pb-4 border-b border-slate-100 mb-6 flex-wrap gap-3">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-teal-50 text-[#008489] flex items-center justify-center font-bold text-base shadow-2xs">
                    <i class="fa-solid fa-comments"></i>
                </div>
                <div>
                    <h3 class="text-lg font-bold text-slate-900 m-0">Verified Inquiries & Community Discussion</h3>
                    <p class="text-xs text-slate-400 m-0 mt-0.5">Ask questions about property deed, NOC, viewing schedule, or handover terms</p>
                </div>
            </div>
            <span id="heroCommentsBadge" class="text-xs font-bold text-[#008489] bg-teal-50 border border-teal-200 px-3.5 py-1.5 rounded-full flex items-center gap-1.5 shadow-2xs">
                <i class="fa-solid fa-shield-check text-[11px]"></i>
                <span id="heroCommentsCount">0</span> Inquiries
            </span>
        </div>

        <!-- Add Comment Form -->
        <?php if ($is_authenticated): ?>
        <div class="bg-slate-50/80 border border-slate-200/90 rounded-2xl p-5 mb-6 shadow-2xs">
            <!-- Quick Inquiry Suggestions / Chips -->
            <div class="mb-3.5 flex items-center gap-2 flex-wrap">
                <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider flex items-center gap-1">
                    <i class="fa-solid fa-wand-magic-sparkles text-[#008489]"></i> Quick Questions:
                </span>
                <?php if ($is_motors): ?>
                <button type="button" onclick="insertCommentTopic('Are original registration book, return file, and biometric available?')" class="comment-chip">
                    <i class="fa-solid fa-file-contract text-[#008489]"></i> Documents & Biometric
                </button>
                <button type="button" onclick="insertCommentTopic('Has the vehicle ever been in an accident or has any touchups?')" class="comment-chip">
                    <i class="fa-solid fa-car-burst text-[#008489]"></i> Accident & Paint History
                </button>
                <button type="button" onclick="insertCommentTopic('What is the current token tax payment status?')" class="comment-chip">
                    <i class="fa-solid fa-receipt text-[#008489]"></i> Token Tax Status
                </button>
                <button type="button" onclick="insertCommentTopic('Can I arrange a physical inspection with an expert mechanic?')" class="comment-chip">
                    <i class="fa-solid fa-screwdriver-wrench text-[#008489]"></i> Mechanic Inspection
                </button>
                <?php else: ?>
                <button type="button" onclick="insertCommentTopic('Is the property title deed clear with complete NOC?')" class="comment-chip">
                    <i class="fa-solid fa-file-contract text-[#008489]"></i> Title & NOC
                </button>
                <button type="button" onclick="insertCommentTopic('When is the next physical viewing or inspection open house?')" class="comment-chip">
                    <i class="fa-solid fa-calendar-check text-[#008489]"></i> Physical Viewing
                </button>
                <button type="button" onclick="insertCommentTopic('What are the annual maintenance charges and parking spots included?')" class="comment-chip">
                    <i class="fa-solid fa-car text-[#008489]"></i> Parking & Charges
                </button>
                <button type="button" onclick="insertCommentTopic('What is the estimated handover timeline after auction settlement?')" class="comment-chip">
                    <i class="fa-solid fa-key text-[#008489]"></i> Handover Timeline
                </button>
                <?php endif; ?>
            </div>

            <div class="flex items-start gap-3.5">
                <img src="<?php echo !empty($_SESSION['user_avatar']) ? htmlspecialchars($_SESSION['user_avatar']) : 'https://ui-avatars.com/api/?name=' . urlencode($_SESSION['user_name'] ?? 'User') . '&background=008489&color=fff&size=64&bold=true'; ?>" 
                     alt="You" class="w-10 h-10 rounded-full object-cover shrink-0 border border-slate-200 shadow-2xs mt-1"
                     onerror="this.src='https://ui-avatars.com/api/?name=User&background=008489&color=fff&size=64&bold=true';">
                <div class="flex-1">
                    <textarea id="heroCommentInput" 
                              rows="3" 
                              maxlength="500" 
                              placeholder="<?php echo $is_motors ? 'Ask the verified seller about vehicle paperwork, token tax, inspection or maintenance history...' : 'Ask the verified seller or consignor a public question about this property lot...'; ?>" 
                              class="w-full text-sm p-3.5 bg-white border border-slate-200 rounded-xl outline-none focus:border-[#008489] focus:ring-4 focus:ring-teal-500/15 transition resize-none shadow-2xs text-slate-800 placeholder-slate-400"></textarea>
                    <div class="flex items-center justify-between mt-3 flex-wrap gap-2">
                        <div class="flex items-center gap-2">
                            <span id="heroCommentCharCount" class="text-xs text-slate-400 font-medium">0/500</span>
                            <span class="text-slate-300">&bull;</span>
                            <span class="text-[11px] text-slate-400"><i class="fa-solid fa-lock text-[10px] text-teal-600 mr-1"></i> Public question visible to all bidders</span>
                        </div>
                        <button type="button" 
                                id="btnHeroPostComment" 
                                onclick="postLiveComment(<?php echo (int)$current_item['id']; ?>, 'heroCommentInput', 'heroCommentAlert', 'btnHeroPostComment', 'heroCommentsContainer', 'heroCommentsCount')" 
                                class="comment-btn-primary">
                            <i class="fa-solid fa-paper-plane text-xs"></i> Post Inquiry
                        </button>
                    </div>
                </div>
            </div>
            <div id="heroCommentAlert" class="hidden mt-3 text-xs p-3 rounded-xl"></div>
        </div>
        <?php else: ?>
        <div class="bg-slate-50 border border-slate-200/90 rounded-2xl p-6 text-center text-sm text-slate-600 mb-6 shadow-2xs">
            <div class="w-12 h-12 rounded-full bg-teal-50 text-[#008489] flex items-center justify-center mx-auto mb-3 text-lg">
                <i class="fa-solid fa-lock"></i>
            </div>
            <h4 class="font-bold text-slate-800 text-sm mb-1">Sign In to Ask Questions</h4>
            <p class="text-xs text-slate-500 max-w-md mx-auto mb-4">Have an inquiry regarding title deed, viewings, or lot specifications? Log in to communicate directly with the verified seller.</p>
            <a href="login.php?redirect=live_auction.php?id=<?php echo (int)$current_item['id']; ?>" class="comment-btn-primary text-decoration-none inline-flex">
                <i class="fa-solid fa-right-to-bracket text-xs"></i> Sign In to Join Discussion
            </a>
        </div>
        <?php endif; ?>

        <!-- Comments Stream Container -->
        <div id="heroCommentsContainer" class="space-y-4">
            <div class="text-center py-8 text-slate-400 text-xs">
                <i class="fa-solid fa-spinner fa-spin text-[#008489] text-xl mb-2 block"></i>
                Loading verified inquiries...
            </div>
        </div>
    </section>
    <?php endif; ?>
    <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm">
        <div class="flex items-center justify-between mb-6">
            <div class="flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center">
                    <i class="fa-solid fa-table-cells text-sm"></i>
                </div>
                <div>
                    <h2 class="text-lg font-bold text-slate-900">Explore Active Auctions</h2>
                    <p class="text-xs text-slate-400">Real-time catalog updated across all categories</p>
                </div>
            </div>
            <?php if ($show_all): ?>
                <a href="live_auction.php<?php echo $item_id > 0 ? '?id='.$item_id : ''; ?>" class="text-xs font-semibold text-emerald-600 hover:underline flex items-center gap-1.5">
                    <i class="fa-solid fa-chevron-up text-[10px]"></i> Show Less
                </a>
            <?php else: ?>
                <a href="live_auction.php?all=1<?php echo $item_id ? '&id='.$item_id : ''; ?>" class="text-xs font-semibold text-emerald-600 hover:underline flex items-center gap-1.5">
                    <i class="fa-solid fa-chevron-down text-[10px]"></i> Show All (<?php echo $totalItems; ?> Lots)
                </a>
            <?php endif; ?>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <?php if (!empty($catalog_list)): ?>
                <?php foreach($catalog_list as $prod): 
                    $p_price = ($prod['current_price'] ?? 0) > 0 ? (float)$prod['current_price'] : (float)($prod['starting_price'] ?? 0);
                    $is_highlighted = ($current_item && (int)$prod['id'] === (int)$current_item['id']);
                    $card_qs = 'id=' . $prod['id'];

                    $fallback_img = 'assets/image/Geo-auction.png';
                    $grid_img = !empty($prod['featured_image']) ? cleanImagePath($prod['featured_image']) : '';
                    if (empty($grid_img) && !empty($prod['images'])) {
                        $p_imgs = parseItemImages($pdo, (int)$prod['id'], $prod['images'], '');
                        $grid_img = $p_imgs[0] ?? '';
                    }
                    if (empty($grid_img)) {
                        $grid_img = $fallback_img;
                    }

                    $p_dist = $prod['dist_km'];
                    $diff_secs = strtotime($prod['end_time'] ?? '') - time();
                    $is_ending_soon = ($diff_secs > 0 && $diff_secs <= 600);
                    $prod_title = htmlspecialchars($prod['title']);
                    $lot_code = htmlspecialchars($prod['auction_code'] ?? ('LOT-'.$prod['id']));
                ?>
                    <div class="border <?php echo $is_highlighted ? 'border-emerald-600 ring-2 ring-emerald-100 bg-white' : 'border-slate-200 bg-slate-50/60 hover:border-emerald-500 hover:bg-white'; ?> rounded-xl p-3 transition flex flex-col sm:flex-row gap-3 justify-between group shadow-sm">
                        
                        <!-- Left Side: Image with proper width/height, object-fit: cover, rounded corners, fallback -->
                        <div class="w-full sm:w-36 md:w-44 h-36 sm:h-auto min-h-[120px] rounded-lg bg-slate-200 overflow-hidden relative shrink-0 cursor-pointer"
                             onclick="openCardImageModal(this.querySelector('img').src, '<?php echo addslashes($prod_title); ?>', '<?php echo $lot_code; ?>')"
                             title="Click to view large preview">
                            <img src="<?php echo htmlspecialchars($grid_img); ?>" 
                                 class="w-full h-full object-cover group-hover:scale-105 transition duration-300" 
                                 loading="lazy" 
                                 alt="<?php echo $prod_title; ?>"
                                 onerror="this.onerror=null; this.src='assets/image/Geo-auction.png';">
                            
                            <span class="absolute top-2 left-2 px-2 py-0.5 rounded bg-black/60 backdrop-blur-sm text-white text-[10px] font-semibold">
                                #<?php echo $lot_code; ?>
                            </span>

                            <?php if ($is_ending_soon): ?>
                                <span class="absolute top-2 right-2 px-1.5 py-0.5 rounded bg-rose-600 text-white text-[9px] font-bold uppercase tracking-wide animate-pulse">
                                    Ending Soon
                                </span>
                            <?php endif; ?>

                            <?php if ($p_dist !== null): ?>
                                <span class="absolute bottom-1.5 left-1.5 px-1.5 py-0.5 rounded bg-slate-900/80 backdrop-blur-xs text-white text-[9px] font-medium flex items-center gap-1">
                                    <i class="fa-solid fa-location-dot text-[9px] text-emerald-400"></i> <?php echo htmlspecialchars($prod['location_area'] ?? 'Qasimabad'); ?> &bull; <?php echo $p_dist; ?> km
                                </span>
                            <?php endif; ?>
                        </div>

                        <!-- Right Side: Auction Details -->
                        <div class="flex-1 flex flex-col justify-between min-w-0">
                            <div>
                                <h4 class="text-xs font-bold text-slate-900 line-clamp-2 group-hover:text-emerald-600 transition">
                                    <a href="live_auction.php?<?php echo $card_qs; ?>" class="hover:underline">
                                        <?php echo $prod_title; ?>
                                    </a>
                                </h4>
                                <div class="flex items-center justify-between text-[10px] text-slate-400 mt-1.5 pt-1.5 border-t border-slate-100 flex-wrap gap-1">
                                    <span class="flex items-center gap-1">
                                        <i class="fa-solid fa-gavel text-[9px] text-slate-400"></i> <?php echo (int)($prod['bid_count'] ?? 0); ?> bids
                                    </span>
                                    <span class="text-[10px] text-slate-400 flex items-center gap-1">
                                        <i class="fa-solid fa-location-dot text-[9px] text-slate-400"></i> <?php echo htmlspecialchars($prod['location_area'] ?? 'Hyderabad'); ?>
                                    </span>
                                </div>
                            </div>

                            <div class="mt-2.5 pt-2 border-t border-slate-200">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-[11px] text-slate-400">Current</span>
                                    <span class="text-xs font-bold text-slate-900">Rs. <?php echo number_format($p_price, 0); ?></span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <button type="button" onclick="openDetailsModal(<?php echo $prod['id']; ?>)" class="flex-1 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-center rounded-lg text-xs font-semibold transition cursor-pointer">View</button>
                                    <a href="live_auction.php?<?php echo $card_qs; ?>" class="flex-1 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white text-center rounded-lg text-xs font-bold transition">Bid Now</a>
                                </div>
                            </div>
                        </div>

                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-span-full flex flex-col items-center justify-center py-12 text-slate-400">
                    <i class="fa-solid fa-magnifying-glass text-3xl mb-3"></i>
                    <p class="text-sm font-semibold text-slate-600">No lots found matching your filter criteria.</p>
                    <a href="<?php echo htmlspecialchars(buildFilterUrl('', $current_get)); ?>" class="text-xs font-semibold text-emerald-600 hover:underline mt-2">Reset all filters</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div id="filterDrawerBackdrop" class="filter-drawer-backdrop fixed inset-0 bg-slate-950/40 backdrop-blur-xs z-50 flex justify-end">
    <div id="filterDrawerPanel" class="filter-drawer-panel w-full max-w-md bg-white h-full shadow-2xl flex flex-col justify-between overflow-y-auto">
        <div class="p-5 border-b border-slate-100 flex items-center justify-between">
            <div class="flex items-center gap-2.5">
                <span class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-700 flex items-center justify-center">
                    <i class="fa-solid fa-sliders text-sm"></i>
                </span>
                <div>
                    <h3 class="text-base font-bold text-slate-900">Filter Auction Lots</h3>
                    <p class="text-xs text-slate-400">Refine catalog by category, price, and specs</p>
                </div>
            </div>
            <button type="button" id="closeDrawerBtn" class="w-8 h-8 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100 flex items-center justify-center transition cursor-pointer">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>

        <form method="GET" action="live_auction.php" class="p-6 space-y-5 flex-1">
            <?php if ($item_id > 0): ?><input type="hidden" name="id" value="<?php echo $item_id; ?>"><?php endif; ?>

            <div class="space-y-1.5">
                <label class="text-xs font-bold text-slate-700 uppercase tracking-wider block">Category</label>
                <select name="category_id" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3 py-2.5 text-xs font-medium text-slate-800 outline-none focus:border-emerald-600 focus:bg-white transition cursor-pointer">
                    <option value="0">All Categories</option>
                    <?php 
                    if (!empty($categories_list)):
                        foreach ($categories_list as $cat): 
                    ?>
                        <option value="<?php echo $cat['id']; ?>" <?php echo $selected_category === (int)$cat['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat['name']); ?>
                        </option>
                    <?php endforeach; endif; ?>
                </select>
            </div>

            <div class="space-y-1.5">
                <label class="text-xs font-bold text-slate-700 uppercase tracking-wider block">Price Range (Rs.)</label>
                <div class="grid grid-cols-2 gap-3">
                    <input type="number" step="100" name="min_price" value="<?php echo $min_price > 0 ? $min_price : ''; ?>" placeholder="Min Price" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-xs text-slate-800 outline-none focus:border-emerald-600 focus:bg-white transition">
                    <input type="number" step="100" name="max_price" value="<?php echo $max_price > 0 ? $max_price : ''; ?>" placeholder="Max Price" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-xs text-slate-800 outline-none focus:border-emerald-600 focus:bg-white transition">
                </div>
            </div>

            <div class="space-y-1.5">
                <label class="text-xs font-bold text-slate-700 uppercase tracking-wider block">Sort Order</label>
                <select name="sort" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3 py-2.5 text-xs font-medium text-slate-800 outline-none focus:border-emerald-600 focus:bg-white transition cursor-pointer">
                    <option value="latest" <?php echo $sort_by === 'latest' ? 'selected' : ''; ?>>Newest First</option>
                    <option value="ending_soon" <?php echo $sort_by === 'ending_soon' ? 'selected' : ''; ?>>Closing Soon</option>
                    <option value="price_low" <?php echo $sort_by === 'price_low' ? 'selected' : ''; ?>>Price: Low to High</option>
                    <option value="price_high" <?php echo $sort_by === 'price_high' ? 'selected' : ''; ?>>Price: High to Low</option>
                    <option value="most_bids" <?php echo $sort_by === 'most_bids' ? 'selected' : ''; ?>>Most Bids</option>
                </select>
            </div>

            <div class="pt-6 border-t border-slate-100 flex items-center gap-3">
                <a href="<?php echo htmlspecialchars(buildFilterUrl('', $current_get)); ?>" class="flex-1 py-2.5 border border-slate-200 hover:bg-slate-50 text-slate-700 font-semibold text-xs rounded-lg text-center transition">
                    Clear Filters
                </a>
                <button type="submit" class="flex-1 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs rounded-lg shadow transition cursor-pointer">
                    Apply Filters
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Card Image Lightbox Preview Modal -->
<div id="cardImagePreviewModal" 
     class="fixed inset-0 bg-slate-950/85 backdrop-blur-md z-[110] hidden items-center justify-center p-3 sm:p-6 transition-all duration-300"
     role="dialog" 
     aria-modal="true"
     onclick="if(event.target === this) closeCardImageModal()">
    <div class="relative bg-slate-900 border border-slate-800 rounded-2xl max-w-4xl w-full overflow-hidden shadow-2xl flex flex-col max-h-[92vh]">
        <!-- Modal Header -->
        <div class="flex items-center justify-between px-4 py-3 border-b border-slate-800 bg-slate-950/70 text-white shrink-0">
            <div class="flex items-center gap-2.5 min-w-0 pr-3">
                <span id="cardModalLotCode" class="text-[11px] font-bold px-2 py-0.5 rounded bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 uppercase shrink-0">#LOT</span>
                <h4 id="cardModalTitle" class="text-xs sm:text-sm font-semibold truncate text-slate-100">Item Preview</h4>
            </div>
            <button type="button" 
                    onclick="closeCardImageModal()" 
                    class="w-8 h-8 rounded-full bg-slate-800 hover:bg-rose-600 active:scale-95 text-slate-300 hover:text-white flex items-center justify-center transition cursor-pointer border border-slate-700"
                    title="Close (Esc)">
                <i class="fa-solid fa-xmark text-sm"></i>
            </button>
        </div>

        <!-- Modal Image Container -->
        <div class="p-2 sm:p-4 flex-1 flex items-center justify-center bg-black/60 overflow-hidden min-h-0">
            <img id="cardModalImage" 
                 src="" 
                 alt="Enlarged Auction Preview" 
                 class="max-w-full max-h-[75vh] object-contain rounded-lg shadow-2xl transition duration-200"
                 onerror="this.onerror=null; this.src='assets/image/Geo-auction.png';">
        </div>

        <!-- Modal Footer -->
        <div class="px-4 py-2.5 bg-slate-950/80 border-t border-slate-800 flex items-center justify-between text-xs text-slate-400 shrink-0">
            <span class="text-[11px]"><i class="fa-solid fa-magnifying-glass text-emerald-400 mr-1"></i> Full Resolution Preview</span>
            <span class="text-[11px] text-slate-500">Press <kbd class="px-1 py-0.5 rounded bg-slate-800 text-slate-300 font-mono text-[10px]">Esc</kbd> or click outside to close</span>
        </div>
    </div>
</div>

<div id="auctionDetailsModal" class="auction-modal-backdrop fixed inset-0 bg-slate-950/60 backdrop-blur-xs z-50 flex items-center justify-center p-4">
    <div class="bg-white w-full max-w-2xl rounded-2xl overflow-hidden shadow-2xl flex flex-col max-h-[90vh]">
        <div class="p-4 sm:p-5 border-b border-slate-100 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span id="modalLotCode" class="text-xs font-bold text-emerald-700 uppercase bg-emerald-50 border border-emerald-200 px-2 py-0.5 rounded">#LOT</span>
                <span id="modalReserveStatus" class="text-xs font-semibold px-2 py-0.5 rounded border">Reserve Status</span>
            </div>
            <button type="button" onclick="closeDetailsModal()" class="w-8 h-8 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100 flex items-center justify-center cursor-pointer">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>

        <div class="p-6 space-y-4 overflow-y-auto flex-1">
            <div class="h-60 rounded-xl overflow-hidden bg-slate-100 relative">
                <img id="modalImage" src="" class="w-full h-full object-cover" alt="Auction Image">
                <div id="modalDistanceTag" class="absolute bottom-3 left-3 bg-slate-900/80 text-white text-xs px-2.5 py-1 rounded-md font-medium"></div>
            </div>

            <div>
                <h3 id="modalTitle" class="text-xl font-bold text-slate-900"></h3>
                <div class="flex items-center gap-2 mt-2 p-1.5 bg-slate-50 border border-slate-200 rounded-lg w-fit">
                    <img id="modalSellerAvatar" src="" class="w-8 h-8 rounded-full object-cover border border-slate-200" style="width: 32px; height: 32px; border-radius: 50%;" alt="Seller">
                    <span id="modalSeller" class="text-xs text-slate-700 font-semibold"></span>
                </div>
                <p id="modalDescription" class="text-xs text-slate-600 mt-2 leading-relaxed whitespace-pre-line"></p>
            </div>

            <div class="grid grid-cols-3 gap-2 bg-slate-50 p-3 rounded-xl border border-slate-200 text-center text-xs">
                <div>
                    <span class="text-slate-400 block font-medium">Current Price</span>
                    <strong id="modalCurrentPrice" class="text-emerald-700 text-sm">Rs. 0</strong>
                </div>
                <div>
                    <span class="text-slate-400 block font-medium">Next Minimum</span>
                    <strong id="modalNextMin" class="text-slate-800 text-sm">Rs. 0</strong>
                </div>
                <div>
                    <span class="text-slate-400 block font-medium">Total Bids</span>
                    <strong id="modalBidCount" class="text-slate-800 text-sm">0</strong>
                </div>
            </div>

            <div class="pt-2 flex items-center gap-2 flex-wrap">
                <a id="modalBidNowBtn" href="" class="flex-1 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs rounded-xl text-center shadow transition">
                    <i class="fa-solid fa-gavel mr-1"></i> Go to Live Terminal
                </a>
                <button type="button" id="modalWatchBtn" onclick="" class="py-2.5 px-4 bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold text-xs rounded-xl transition flex items-center gap-1.5 cursor-pointer">
                    <i class="fa-regular fa-heart text-rose-500"></i> Watch
                </button>
            </div>

            <!-- Modal Comments & Q&A Section -->
            <div class="mt-4 pt-4 border-t border-slate-100">
                <div class="flex items-center justify-between mb-3">
                    <h4 class="text-xs font-bold text-slate-800 uppercase tracking-wider flex items-center gap-1.5">
                        <i class="fa-solid fa-comments text-emerald-600"></i>
                        <span>Item Q&amp;A &amp; Discussion</span>
                    </h4>
                    <span id="modalCommentsCountBadge" class="text-[11px] font-bold text-slate-500 bg-slate-100 px-2 py-0.5 rounded-full">0 comments</span>
                </div>

                <!-- Add Comment Form -->
                <?php if ($is_authenticated): ?>
                <div class="mb-3 bg-slate-50 p-2.5 rounded-xl border border-slate-200">
                    <div class="flex gap-2 items-start">
                        <img src="<?php echo !empty($_SESSION['user_avatar']) ? htmlspecialchars($_SESSION['user_avatar']) : 'https://ui-avatars.com/api/?name=' . urlencode($_SESSION['user_name'] ?? 'User') . '&background=007a33&color=fff&size=64'; ?>" 
                             alt="Avatar" class="w-7 h-7 rounded-full object-cover shrink-0 border border-slate-200 mt-1"
                             onerror="this.src='https://ui-avatars.com/api/?name=User&background=007a33&color=fff&size=64';">
                        <div class="flex-1">
                            <textarea id="modalCommentInput" 
                                      rows="2" 
                                      maxlength="500" 
                                      placeholder="Ask a question about this lot..." 
                                      class="w-full text-xs p-2 bg-white border border-slate-200 rounded-lg outline-none focus:border-emerald-600 transition resize-none"></textarea>
                            <div class="flex items-center justify-between mt-1.5">
                                <span id="modalCommentCharCount" class="text-[10px] text-slate-400">0/500</span>
                                <button type="button" 
                                        id="modalBtnPostComment" 
                                        onclick="submitModalComment()" 
                                        class="px-3 py-1 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-xs font-bold transition flex items-center gap-1 shadow-sm">
                                    <i class="fa-solid fa-paper-plane text-[9px]"></i> Post
                                </button>
                            </div>
                        </div>
                    </div>
                    <div id="modalCommentAlert" class="hidden mt-2 text-xs p-2 rounded-lg"></div>
                </div>
                <?php else: ?>
                <div class="mb-3 bg-slate-50 p-2.5 rounded-xl border border-slate-200 text-center text-xs text-slate-600">
                    <i class="fa-solid fa-lock text-slate-400 mr-1"></i>
                    <a href="login.php?redirect=live_auction.php" class="text-emerald-700 font-bold hover:underline">Log in</a> to post a comment or question.
                </div>
                <?php endif; ?>

                <!-- Comments List -->
                <div id="modalCommentsList" class="space-y-2 max-h-48 overflow-y-auto pr-1">
                    <div class="text-center py-4 text-slate-400 text-xs">Loading comments...</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Generic Interactive Action Popup Modal (Replaces browser alert) -->
<div id="genericActionModal" class="auction-modal-backdrop fixed inset-0 bg-slate-950/60 backdrop-blur-xs z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-sm w-full p-6 text-center shadow-2xl relative">
        <button type="button" onclick="closeGenericModal()" class="absolute top-4 right-4 text-slate-400 hover:text-slate-600 transition">
            <i class="fa-solid fa-xmark text-lg"></i>
        </button>
        <div id="genericModalIconRing" class="w-14 h-14 rounded-full mx-auto mb-3 flex items-center justify-center text-2xl">
            <i id="genericModalIcon" class="fa-solid"></i>
        </div>
        <h3 id="genericModalTitle" class="text-lg font-bold text-slate-900 mb-1.5"></h3>
        <p id="genericModalMsg" class="text-xs text-slate-600 mb-4 leading-relaxed"></p>
        <button type="button" id="genericModalBtn" onclick="closeGenericModal()" class="w-full py-2.5 rounded-xl font-bold text-xs text-white transition shadow-sm"></button>
    </div>
</div>

<!-- Fullscreen Interactive Lightbox Modal -->
<div id="fullscreenImageViewerModal" class="fs-image-modal select-none flex flex-col justify-between" style="display: none;" role="dialog" aria-modal="true" aria-label="Fullscreen Auction Image Viewer">
    <!-- Modal Header -->
    <div class="w-full flex items-center justify-between px-4 sm:px-6 py-3.5 bg-slate-900/95 border-b border-white/10 z-30 shrink-0">
        <div class="flex items-center gap-2.5 text-white min-w-0 pr-3">
            <span class="text-xs sm:text-sm font-bold truncate text-slate-100">
                <?php echo htmlspecialchars($current_item['title'] ?? 'Lot Details'); ?>
            </span>
            <span class="text-[11px] font-semibold px-2 py-0.5 rounded bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 shrink-0">
                #<?php echo htmlspecialchars($current_item['auction_code'] ?? ('LOT-'.$current_item['id'])); ?>
            </span>
        </div>
        
        <div class="flex items-center gap-3 shrink-0">
            <!-- Modal Zoom Controls -->
            <div class="flex items-center gap-1 bg-white/10 backdrop-blur-md px-2 py-1 rounded-full border border-white/10 text-white text-xs">
                <button type="button" id="fsZoomOutBtn" class="w-7 h-7 rounded-full hover:bg-white/20 active:scale-95 flex items-center justify-center transition cursor-pointer" title="Zoom Out (-)">
                    <i class="fa-solid fa-minus text-[11px]"></i>
                </button>
                <span id="fsZoomLevelBadge" class="text-[11px] font-semibold px-1 min-w-[36px] text-center text-slate-200">100%</span>
                <button type="button" id="fsZoomInBtn" class="w-7 h-7 rounded-full hover:bg-white/20 active:scale-95 flex items-center justify-center transition cursor-pointer" title="Zoom In (+)">
                    <i class="fa-solid fa-plus text-[11px]"></i>
                </button>
                <button type="button" id="fsZoomResetBtn" class="w-7 h-7 rounded-full hover:bg-white/20 active:scale-95 flex items-center justify-center transition cursor-pointer" title="Reset Zoom">
                    <i class="fa-solid fa-rotate-left text-[11px]"></i>
                </button>
            </div>
            
            <!-- Close Modal Button -->
            <button type="button" id="closeFullscreenViewerBtn" onclick="closeFullscreenViewer()" class="px-4 py-1.5 rounded-full bg-rose-600 hover:bg-rose-700 active:scale-95 text-white flex items-center gap-1.5 font-bold text-xs shadow-lg cursor-pointer transition border border-rose-400" aria-label="Close fullscreen viewer" title="Close (Esc)">
                <i class="fa-solid fa-xmark text-sm"></i> Close (Esc)
            </button>
        </div>
    </div>

    <!-- Center Viewport for Modal Zoom & Pan -->
    <div id="fullscreenImageViewport" class="flex-1 w-full relative overflow-hidden flex items-center justify-center cursor-zoom-in min-h-0 min-w-0" style="touch-action: none;">
        <img id="fullscreenImageElement" 
             src="<?php echo htmlspecialchars($main_img); ?>" 
             alt="Fullscreen Lot Image" 
             class="max-w-[92vw] max-h-[72vh] object-contain will-change-transform shadow-2xl transition-transform duration-200 block m-auto rounded-lg" 
             draggable="false"
             onerror="this.onerror=null; this.src='assets/image/Geo-auction.png';">
        
        <!-- Modal Prev & Next Navigation Buttons -->
        <?php if (count($showcase_photos) > 1): ?>
            <button type="button" id="fsPrevBtn" class="absolute left-3 sm:left-6 top-1/2 -translate-y-1/2 z-30 bg-black/60 hover:bg-black/90 active:scale-95 text-white w-11 h-11 rounded-full transition flex items-center justify-center backdrop-blur-sm cursor-pointer shadow-xl border border-white/15" aria-label="Previous image" title="Previous (Left Arrow)">
                <i class="fa-solid fa-chevron-left text-sm"></i>
            </button>
            <button type="button" id="fsNextBtn" class="absolute right-3 sm:right-6 top-1/2 -translate-y-1/2 z-30 bg-black/60 hover:bg-black/90 active:scale-95 text-white w-11 h-11 rounded-full transition flex items-center justify-center backdrop-blur-sm cursor-pointer shadow-xl border border-white/15" aria-label="Next image" title="Next (Right Arrow)">
                <i class="fa-solid fa-chevron-right text-sm"></i>
            </button>
        <?php endif; ?>
    </div>

    <!-- Modal Footer with Counter and Thumbnails -->
    <div class="w-full flex flex-col items-center gap-2 px-4 py-3 bg-slate-900/90 border-t border-white/10 z-30 shrink-0">
        <div class="flex items-center gap-3 text-xs text-slate-300">
            <span id="fsCounterBadge" class="font-semibold text-white">Image 1 of <?php echo count($showcase_photos); ?></span>
            <span class="text-slate-500">•</span>
            <span class="hidden sm:inline text-[11px] text-slate-400">Mouse wheel or +/- to zoom, drag to pan, swipe on mobile, Esc to close</span>
        </div>
        
        <?php if (count($showcase_photos) > 1): ?>
            <div id="fsThumbnailsStrip" class="flex items-center gap-2 overflow-x-auto max-w-full px-2 py-1 scrollbar-thin">
                <?php foreach ($showcase_photos as $f_idx => $f_img): ?>
                    <button type="button" 
                            onclick="selectGalleryImage(<?php echo $f_idx; ?>)" 
                            class="fs-modal-thumb-btn shrink-0 rounded-lg overflow-hidden border-2 transition focus:outline-none cursor-pointer <?php echo $f_idx === 0 ? 'active-thumb border-emerald-500 ring-2 ring-emerald-400/30 scale-105' : 'border-white/20 opacity-60 hover:opacity-100'; ?>"
                            data-index="<?php echo $f_idx; ?>" 
                            aria-label="View photo <?php echo $f_idx + 1; ?>">
                        <img src="<?php echo htmlspecialchars($f_img); ?>" 
                             alt="Photo <?php echo $f_idx + 1; ?>" 
                             class="w-12 h-12 sm:w-14 sm:h-14 object-cover pointer-events-none"
                             onerror="this.onerror=null; this.src='assets/image/Geo-auction.png';">
                    </button>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Video Tour Request & Virtual Walkthrough Modal (Bayut Style) -->
<div id="videoTourModal" class="auction-modal-backdrop fixed inset-0 z-50 bg-slate-950/85 backdrop-blur-md flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-label="Video Tour Modal">
    <div class="bg-white rounded-2xl max-w-xl w-full overflow-hidden shadow-2xl border border-slate-200 transition-all flex flex-col max-h-[92vh]">
        <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100 bg-slate-50 shrink-0">
            <div class="flex items-center gap-2.5">
                <span class="w-8 h-8 rounded-full bg-teal-100 text-[#008489] flex items-center justify-center">
                    <i class="fa-solid fa-video text-sm"></i>
                </span>
                <div>
                    <h3 class="text-sm font-bold text-slate-900 m-0">Virtual Tour &amp; Video Inspection</h3>
                    <span class="text-[11px] text-slate-400">Bayut Verified Listing &bull; TruCheck&trade; Walkthrough</span>
                </div>
            </div>
            <button type="button" onclick="closeVideoTourModal()" class="text-slate-400 hover:text-slate-700 p-1 rounded-lg cursor-pointer transition">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>
        <div class="p-5 space-y-4 overflow-y-auto">
            <!-- Simulated 4K Interactive Walkthrough Player -->
            <div class="relative w-full h-64 bg-slate-950 rounded-xl overflow-hidden flex items-center justify-center text-white group shadow-inner">
                <img id="virtualTourPlayerImage" 
                     src="<?php echo htmlspecialchars($showcase_photos[0] ?? 'assets/image/Geo-auction.png'); ?>" 
                     class="absolute inset-0 w-full h-full object-cover transition-all duration-700 ken-burns-active" 
                     alt="Virtual Tour Stream">
                
                <div class="absolute inset-0 bg-gradient-to-t from-slate-950/80 via-transparent to-black/30 pointer-events-none"></div>

                <!-- Top Status Bar -->
                <div class="absolute top-3 left-3 right-3 flex items-center justify-between pointer-events-none z-10 text-[11px]">
                    <span class="bg-rose-600/90 text-white font-bold px-2 py-0.5 rounded-md flex items-center gap-1.5 shadow">
                        <span class="w-1.5 h-1.5 rounded-full bg-white live-dot"></span> LIVE 4K WALKTHROUGH
                    </span>
                    <span class="bg-black/60 backdrop-blur-xs text-slate-200 px-2 py-0.5 rounded font-mono">
                        <i class="fa-solid fa-wifi text-emerald-400 mr-1"></i> 60 FPS HDR
                    </span>
                </div>

                <!-- Center Play/Pause Trigger -->
                <button type="button" id="tourPlayerPlayBtn" onclick="toggleTourVideoPlayback()" 
                        class="relative z-10 w-14 h-14 rounded-full bg-[#008489]/90 hover:bg-[#008489] text-white flex items-center justify-center shadow-2xl transition hover:scale-110 active:scale-95 cursor-pointer">
                    <i class="fa-solid fa-pause text-lg" id="tourPlayerPlayIcon"></i>
                </button>

                <!-- Bottom Player Timeline and Controls -->
                <div class="absolute bottom-3 left-3 right-3 z-10 flex flex-col gap-1.5">
                    <div class="w-full bg-white/20 rounded-full h-1.5 overflow-hidden">
                        <div id="tourProgressBar" class="bg-emerald-400 h-full rounded-full transition-all duration-300" style="width: 35%;"></div>
                    </div>
                    <div class="flex items-center justify-between text-[11px] text-slate-300">
                        <span id="tourCurrentRoomLabel" class="font-semibold text-white">Perspective: Exterior Masterplan</span>
                        <span id="tourTimeTicker" class="font-mono text-slate-300">0:42 / 2:30</span>
                    </div>
                </div>
            </div>

            <!-- Walkthrough Room Perspective Switchers -->
            <div class="space-y-1.5">
                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Jump to Room Inspection:</span>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                    <?php 
                    $room_names = ['Exterior Aerial', 'Living Lounge', 'Master Suite', 'Gourmet Kitchen'];
                    foreach ($showcase_photos as $r_idx => $r_pic): 
                        if ($r_idx > 3) break;
                        $lbl = $room_names[$r_idx] ?? ('View '.($r_idx+1));
                    ?>
                        <button type="button" 
                                onclick="switchVirtualTourRoom(<?php echo $r_idx; ?>, '<?php echo addslashes($lbl); ?>')"
                                class="tour-room-btn flex items-center gap-1.5 p-1.5 rounded-lg border border-slate-200 hover:border-teal-500 bg-slate-50 hover:bg-teal-50/50 text-[11px] font-medium text-slate-700 transition cursor-pointer text-left">
                            <img src="<?php echo htmlspecialchars($r_pic); ?>" class="w-8 h-8 rounded object-cover shrink-0" alt="<?php echo htmlspecialchars($lbl); ?>">
                            <span class="truncate leading-tight"><?php echo htmlspecialchars($lbl); ?></span>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="bg-teal-50 border border-teal-200 rounded-xl p-3 text-xs text-teal-900 leading-relaxed">
                <strong><i class="fa-solid fa-shield-check text-teal-700"></i> TruCheck&trade; Verified Video Tour:</strong>
                Request a dedicated agent walkthrough stream via WhatsApp or HD Video Call with certified architectural verification before placing bids.
            </div>

            <div id="videoTourSuccessAlert" class="hidden p-3 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs flex items-center gap-2">
                <i class="fa-solid fa-circle-check text-emerald-600 text-base"></i>
                <span>Your inspection request has been submitted to the listing broker! They will connect with you via WhatsApp shortly.</span>
            </div>

            <form id="videoTourRequestForm" onsubmit="handleVideoTourSubmit(event)" class="space-y-3">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Your Full Name</label>
                        <input type="text" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-xs text-slate-800 outline-none focus:border-[#008489]" value="<?php echo htmlspecialchars($_SESSION['user_name'] ?? ''); ?>" required placeholder="Enter name">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Phone / WhatsApp Number</label>
                        <input type="tel" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-xs text-slate-800 outline-none focus:border-[#008489]" required placeholder="+971 50 ... / +92 300 ...">
                    </div>
                </div>
                <button type="submit" class="w-full py-2.5 bg-[#008489] hover:bg-[#006c70] active:scale-98 text-white font-bold text-xs rounded-xl transition shadow cursor-pointer flex items-center justify-center gap-1.5">
                    <i class="fa-solid fa-paper-plane"></i> Request Dedicated Walkthrough Call
                </button>
            </form>
        </div>
    </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
   
    function parseSafeDateMs(dateStr) {
        if (!dateStr) return 0;
        var s = String(dateStr).trim();
        if (s.indexOf('T') === -1 && s.indexOf(' ') !== -1) {
            s = s.replace(' ', 'T');
        }
        var ms = new Date(s).getTime();
        if (isNaN(ms)) {
            ms = new Date(s.replace(/-/g, "/")).getTime();
        }
        return isNaN(ms) ? 0 : ms;
    }

    // Filter Drawer Management
    (function initFilterDrawer() {
        var openBtn = document.getElementById('openDrawerBtn');
        var closeBtn = document.getElementById('closeDrawerBtn');
        var backdrop = document.getElementById('filterDrawerBackdrop');
        var panel = document.getElementById('filterDrawerPanel');

        function openDrawer() {
            backdrop.classList.add('is-open');
            panel.classList.add('is-open');
            document.body.style.overflow = 'hidden';
        }
        function closeDrawer() {
            backdrop.classList.remove('is-open');
            panel.classList.remove('is-open');
            document.body.style.overflow = '';
        }

        if (openBtn) openBtn.addEventListener('click', openDrawer);
        if (closeBtn) closeBtn.addEventListener('click', closeDrawer);
        if (backdrop) {
            backdrop.addEventListener('click', function (e) {
                if (e.target === backdrop) closeDrawer();
            });
        }
    })();

    // =========================================================================
    // Dynamic Auction Item Gallery & Smooth Zoom Viewer Controller (Vanilla JS)
    // =========================================================================
    // Modal helpers for Video Tour & Location Map
    window.openVideoTourModal = function() {
        var m = document.getElementById('videoTourModal');
        if (m) {
            m.classList.add('is-open');
            document.body.style.overflow = 'hidden';
        }
    };
    window.closeVideoTourModal = function() {
        var m = document.getElementById('videoTourModal');
        if (m) {
            m.classList.remove('is-open');
            document.body.style.overflow = '';
        }
    };

    // Card Image Preview Modal Controller
    window.openCardImageModal = function(src, title, lotCode) {
        var m = document.getElementById('cardImagePreviewModal');
        var img = document.getElementById('cardModalImage');
        var titleEl = document.getElementById('cardModalTitle');
        var codeEl = document.getElementById('cardModalLotCode');
        if (!m || !img) return;

        img.src = src || 'assets/image/Geo-auction.png';
        if (titleEl) titleEl.textContent = title || 'Auction Item';
        if (codeEl) codeEl.textContent = lotCode ? '#' + String(lotCode).replace(/^#/, '') : '#LOT';

        m.classList.remove('hidden');
        m.classList.add('flex');
        document.body.style.overflow = 'hidden';
    };

    window.closeCardImageModal = function() {
        var m = document.getElementById('cardImagePreviewModal');
        if (!m) return;
        m.classList.add('hidden');
        m.classList.remove('flex');
        document.body.style.overflow = '';
    };

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' || e.key === 'Esc') {
            closeCardImageModal();
        }
    });

    // Real-Time Catalog Card Countdown Timer Engine
    function updateCatalogCardCountdowns() {
        var nodes = document.querySelectorAll('.card-countdown-timer[data-countdown-ts]');
        if (!nodes.length) return;
        var now = Math.floor(Date.now() / 1000);
        nodes.forEach(function(el) {
            var endTs = parseInt(el.getAttribute('data-countdown-ts'), 10);
            if (!endTs) return;
            var diff = endTs - now;
            if (diff <= 0) {
                el.textContent = 'Closed';
                el.classList.remove('text-rose-600', 'animate-pulse', 'text-slate-900');
                el.classList.add('text-slate-500');
                return;
            }
            var d = Math.floor(diff / 86400);
            var h = Math.floor((diff % 86400) / 3600);
            var m = Math.floor((diff % 3600) / 60);
            var s = diff % 60;
            if (d > 0) {
                el.textContent = d + 'd ' + h + 'h';
            } else if (h > 0) {
                el.textContent = h + 'h ' + m + 'm';
            } else {
                el.textContent = (m < 10 ? '0' + m : m) + 'm ' + (s < 10 ? '0' + s : s) + 's';
                if (!el.classList.contains('text-rose-600')) {
                    el.classList.add('text-rose-600', 'animate-pulse');
                }
            }
        });
    }
    setInterval(updateCatalogCardCountdowns, 1000);
    updateCatalogCardCountdowns();


    window.insertCommentTopic = function(topic) {
        var inp = document.getElementById('heroCommentInput');
        if (!inp) return;
        inp.value = topic + ' ';
        inp.focus();
        var charCounter = document.getElementById('heroCommentCharCount');
        if (charCounter) charCounter.textContent = inp.value.length + '/500';
    };

    var galleryImages = <?php echo json_encode($showcase_photos ?? ($gallery_images ?? ['assets/image/Geo-auction.png'])); ?>;
    if (!Array.isArray(galleryImages) || galleryImages.length === 0) {
        galleryImages = ['assets/image/Geo-auction.png'];
    }
    var currentImgIndex = 0;
    var placeholderUrl = 'assets/image/Geo-auction.png';

    // Inline Viewer Elements
    var inlineViewport = document.getElementById('auctionViewerViewport');
    var inlineImg = document.getElementById('mainImage');
    var zoomBadge = document.getElementById('zoomLevelBadge');
    var zoomInBtn = document.getElementById('zoomInBtn');
    var zoomOutBtn = document.getElementById('zoomOutBtn');
    var zoomResetBtn = document.getElementById('zoomResetBtn');
    var fsTriggerBtn = document.getElementById('fullscreenTriggerBtn');
    var prevBtn = document.getElementById('prevBtn');
    var nextBtn = document.getElementById('nextBtn');

    // Fullscreen Modal Elements
    var fsModal = document.getElementById('fullscreenImageViewerModal');
    var fsViewport = document.getElementById('fullscreenImageViewport');
    var fsImg = document.getElementById('fullscreenImageElement');
    var fsBadge = document.getElementById('fsZoomLevelBadge');
    var fsZoomInBtn = document.getElementById('fsZoomInBtn');
    var fsZoomOutBtn = document.getElementById('fsZoomOutBtn');
    var fsZoomResetBtn = document.getElementById('fsZoomResetBtn');
    var closeFsBtn = document.getElementById('closeFullscreenViewerBtn');
    var fsPrevBtn = document.getElementById('fsPrevBtn');
    var fsNextBtn = document.getElementById('fsNextBtn');

    // Zoom & Pan State Model
    var inlineZoomState = {
        scale: 1,
        minScale: 1,
        maxScale: 4.0,
        step: 0.35,
        panX: 0,
        panY: 0,
        isDragging: false,
        startX: 0,
        startY: 0,
        hasMoved: false
    };

    var fsZoomState = {
        scale: 1,
        minScale: 1,
        maxScale: 5.0,
        step: 0.35,
        panX: 0,
        panY: 0,
        isDragging: false,
        startX: 0,
        startY: 0,
        hasMoved: false
    };

    // Strict boundary clamping so the image never breaks outside the container
    function clampPanBounds(viewport, state) {
        if (!viewport) return;
        if (state.scale <= 1) {
            state.panX = 0;
            state.panY = 0;
            return;
        }
        var rect = viewport.getBoundingClientRect();
        var maxPanX = Math.max(0, (rect.width * (state.scale - 1)) / 2);
        var maxPanY = Math.max(0, (rect.height * (state.scale - 1)) / 2);

        if (state.panX > maxPanX) state.panX = maxPanX;
        if (state.panX < -maxPanX) state.panX = -maxPanX;
        if (state.panY > maxPanY) state.panY = maxPanY;
        if (state.panY < -maxPanY) state.panY = -maxPanY;
    }

    function applyZoomTransform(img, viewport, state, badge, isSmooth) {
        if (!img || !viewport) return;
        clampPanBounds(viewport, state);
        if (isSmooth) {
            img.style.transition = 'transform 0.22s cubic-bezier(0.16, 1, 0.3, 1)';
        } else {
            img.style.transition = 'none';
        }
        img.style.transform = 'translate3d(' + state.panX + 'px, ' + state.panY + 'px, 0px) scale(' + state.scale + ')';

        if (badge) {
            badge.textContent = Math.round(state.scale * 100) + '%';
        }

        if (state.scale > 1) {
            viewport.classList.remove('cursor-zoom-in');
            if (state.isDragging) {
                viewport.classList.add('cursor-grabbing');
                viewport.classList.remove('cursor-grab');
            } else {
                viewport.classList.add('cursor-grab');
                viewport.classList.remove('cursor-grabbing');
            }
        } else {
            viewport.classList.add('cursor-zoom-in');
            viewport.classList.remove('cursor-grab', 'cursor-grabbing');
        }
    }

    function doZoomIn(state, img, viewport, badge) {
        state.scale = Math.min(state.maxScale, +(state.scale + state.step).toFixed(2));
        applyZoomTransform(img, viewport, state, badge, true);
    }

    function doZoomOut(state, img, viewport, badge) {
        state.scale = Math.max(state.minScale, +(state.scale - state.step).toFixed(2));
        if (state.scale <= 1) {
            state.panX = 0;
            state.panY = 0;
        }
        applyZoomTransform(img, viewport, state, badge, true);
    }

    function doResetZoom(state, img, viewport, badge) {
        state.scale = 1;
        state.panX = 0;
        state.panY = 0;
        applyZoomTransform(img, viewport, state, badge, true);
    }

    // Wheel Zoom listener
    function bindWheelZoom(viewport, img, state, badge) {
        if (!viewport) return;
        viewport.addEventListener('wheel', function(e) {
            e.preventDefault();
            var direction = e.deltaY < 0 ? 1 : -1;
            var targetScale = state.scale + (direction * state.step);
            targetScale = Math.min(state.maxScale, Math.max(state.minScale, targetScale));
            state.scale = +targetScale.toFixed(2);
            if (state.scale <= 1) {
                state.panX = 0;
                state.panY = 0;
            }
            applyZoomTransform(img, viewport, state, badge, true);
        }, { passive: false });
    }

    // Mouse Drag/Pan listener
    function bindDragPan(viewport, img, state, badge, onCleanClick) {
        if (!viewport || !img) return;
        var downX = 0;
        var downY = 0;

        viewport.addEventListener('mousedown', function(e) {
            if (e.button !== 0) return;
            downX = e.clientX;
            downY = e.clientY;
            state.hasMoved = false;

            if (state.scale > 1) {
                state.isDragging = true;
                state.startX = e.clientX - state.panX;
                state.startY = e.clientY - state.panY;
                applyZoomTransform(img, viewport, state, badge, false);
            }
        });

        window.addEventListener('mousemove', function(e) {
            if (!state.isDragging) return;
            var dx = e.clientX - downX;
            var dy = e.clientY - downY;
            if (Math.hypot(dx, dy) > 4) {
                state.hasMoved = true;
            }
            state.panX = e.clientX - state.startX;
            state.panY = e.clientY - state.startY;
            applyZoomTransform(img, viewport, state, badge, false);
        });

        window.addEventListener('mouseup', function(e) {
            if (state.isDragging) {
                state.isDragging = false;
                applyZoomTransform(img, viewport, state, badge, true);
            }
        });

        viewport.addEventListener('click', function(e) {
            var dist = Math.hypot(e.clientX - downX, e.clientY - downY);
            if (dist <= 5 && typeof onCleanClick === 'function') {
                onCleanClick(e);
            }
        });
    }

    // Touch Handling (Swipe + Pinch-to-zoom + Pan) on Mobile
    function bindTouchGestures(viewport, img, state, badge, onSwipeLeft, onSwipeRight) {
        if (!viewport || !img) return;
        var touchStartX = 0;
        var touchStartY = 0;
        var initialPinchDist = 0;
        var initialPinchScale = 1;

        viewport.addEventListener('touchstart', function(e) {
            if (e.touches.length === 1) {
                touchStartX = e.touches[0].clientX;
                touchStartY = e.touches[0].clientY;
                state.hasMoved = false;
                if (state.scale > 1) {
                    state.isDragging = true;
                    state.startX = e.touches[0].clientX - state.panX;
                    state.startY = e.touches[0].clientY - state.panY;
                }
            } else if (e.touches.length === 2) {
                state.isDragging = false;
                initialPinchDist = Math.hypot(
                    e.touches[0].clientX - e.touches[1].clientX,
                    e.touches[0].clientY - e.touches[1].clientY
                );
                initialPinchScale = state.scale;
            }
        }, { passive: true });

        viewport.addEventListener('touchmove', function(e) {
            if (e.touches.length === 2 && initialPinchDist > 0) {
                if (e.cancelable) e.preventDefault();
                var currentDist = Math.hypot(
                    e.touches[0].clientX - e.touches[1].clientX,
                    e.touches[0].clientY - e.touches[1].clientY
                );
                var ratio = currentDist / initialPinchDist;
                var targetScale = initialPinchScale * ratio;
                state.scale = Math.min(state.maxScale, Math.max(state.minScale, +targetScale.toFixed(2)));
                if (state.scale <= 1) {
                    state.panX = 0;
                    state.panY = 0;
                }
                applyZoomTransform(img, viewport, state, badge, false);
            } else if (e.touches.length === 1 && state.scale > 1 && state.isDragging) {
                if (e.cancelable) e.preventDefault();
                var dx = e.touches[0].clientX - touchStartX;
                var dy = e.touches[0].clientY - touchStartY;
                if (Math.hypot(dx, dy) > 4) state.hasMoved = true;
                state.panX = e.touches[0].clientX - state.startX;
                state.panY = e.touches[0].clientY - state.startY;
                applyZoomTransform(img, viewport, state, badge, false);
            }
        }, { passive: false });

        viewport.addEventListener('touchend', function(e) {
            if (e.touches.length === 0) {
                if (state.isDragging) {
                    state.isDragging = false;
                    applyZoomTransform(img, viewport, state, badge, true);
                }
                if (state.scale <= 1 && e.changedTouches && e.changedTouches.length === 1) {
                    var endX = e.changedTouches[0].clientX;
                    var endY = e.changedTouches[0].clientY;
                    var diffX = endX - touchStartX;
                    var diffY = endY - touchStartY;
                    if (Math.abs(diffX) > 40 && Math.abs(diffX) > Math.abs(diffY)) {
                        if (diffX < 0) {
                            if (typeof onSwipeLeft === 'function') onSwipeLeft();
                        } else {
                            if (typeof onSwipeRight === 'function') onSwipeRight();
                        }
                    }
                }
            } else if (e.touches.length === 1 && state.scale > 1) {
                touchStartX = e.touches[0].clientX;
                touchStartY = e.touches[0].clientY;
                state.startX = e.touches[0].clientX - state.panX;
                state.startY = e.touches[0].clientY - state.panY;
            }
        }, { passive: true });
    }

    // Gallery Image Selection & Active Thumbnail Synchronization
    window.selectGalleryImage = function(index) {
        if (!galleryImages.length) return;
        currentImgIndex = (index + galleryImages.length) % galleryImages.length;
        var activeSrc = galleryImages[currentImgIndex];

        // Reset zoom & pan on switch
        doResetZoom(inlineZoomState, inlineImg, inlineViewport, zoomBadge);
        doResetZoom(fsZoomState, fsImg, fsViewport, fsBadge);

        if (inlineImg) inlineImg.src = activeSrc;
        if (fsImg) fsImg.src = activeSrc;

        // Update counters
        var inlineCounter = document.getElementById('imageCounterBadge');
        if (inlineCounter) {
            inlineCounter.textContent = (currentImgIndex + 1) + ' / ' + galleryImages.length;
        }
        var modalCounter = document.getElementById('fsCounterBadge');
        if (modalCounter) {
            modalCounter.textContent = 'Image ' + (currentImgIndex + 1) + ' of ' + galleryImages.length;
        }

        // Sync inline thumbnails
        var inlineThumbs = document.querySelectorAll('.thumbnail-row .thumbnail-btn');
        inlineThumbs.forEach(function(btn, i) {
            if (i === currentImgIndex) {
                btn.classList.add('active-thumb', 'border-emerald-600', 'ring-2', 'ring-emerald-500/25');
                btn.classList.remove('border-slate-200', 'opacity-75');
                btn.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
            } else {
                btn.classList.remove('active-thumb', 'border-emerald-600', 'ring-2', 'ring-emerald-500/25');
                btn.classList.add('border-slate-200', 'opacity-75');
            }
        });

        // Sync modal thumbnails
        var fsThumbs = document.querySelectorAll('#fsThumbnailsStrip .fs-modal-thumb-btn');
        fsThumbs.forEach(function(btn, i) {
            if (i === currentImgIndex) {
                btn.classList.add('active-thumb', 'border-emerald-500', 'ring-2', 'ring-emerald-400/30', 'scale-105');
                btn.classList.remove('border-white/20', 'opacity-60');
                btn.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
            } else {
                btn.classList.remove('active-thumb', 'border-emerald-500', 'ring-2', 'ring-emerald-400/30', 'scale-105');
                btn.classList.add('border-white/20', 'opacity-60');
            }
        });
    };

    window.nextGalleryImage = function() {
        if (galleryImages.length > 1) {
            selectGalleryImage(currentImgIndex + 1);
        }
    };

    window.prevGalleryImage = function() {
        if (galleryImages.length > 1) {
            selectGalleryImage(currentImgIndex - 1);
        }
    };

    // Bayut-Style Interactive Share Trigger
    window.shareAuctionLot = function() {
        var url = window.location.href;
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(url).then(function() {
                showShareToast();
            }).catch(function() {
                promptCopyFallback(url);
            });
        } else {
            promptCopyFallback(url);
        }
    };

    function showShareToast() {
        var toast = document.getElementById('lotShareToast');
        if (toast) {
            toast.classList.add('is-active');
            setTimeout(function() {
                toast.classList.remove('is-active');
            }, 2600);
        }
    }

    function promptCopyFallback(url) {
        var tempInput = document.createElement('input');
        tempInput.value = url;
        document.body.appendChild(tempInput);
        tempInput.select();
        try {
            document.execCommand('copy');
            showShareToast();
        } catch (e) {
            window.prompt('Copy listing link:', url);
        }
        document.body.removeChild(tempInput);
    }

    // Backward compatibility for existing callers of setMainImage
    window.setMainImage = function(src, thumbElement) {
        var idx = galleryImages.indexOf(src);
        if (idx !== -1) {
            selectGalleryImage(idx);
        } else if (inlineImg) {
            inlineImg.src = src;
            if (fsImg) fsImg.src = src;
        }
    };

    // Fullscreen Lightbox Modal Controls
    function openFullscreenViewer() {
        if (!fsModal) return;
        fsModal.style.flexDirection = 'column';
        fsModal.style.display = 'flex';
        fsModal.classList.add('is-open');
        document.body.style.overflow = 'hidden';
        doResetZoom(fsZoomState, fsImg, fsViewport, fsBadge);
        var activeSrc = galleryImages[currentImgIndex] || placeholderUrl;
        if (fsImg) {
            fsImg.src = activeSrc;
            fsImg.style.maxWidth = '92vw';
            fsImg.style.maxHeight = '72vh';
            fsImg.style.objectFit = 'contain';
            fsImg.style.display = 'block';
            fsImg.style.margin = 'auto';
        }
        selectGalleryImage(currentImgIndex);
    }
    window.openFullscreenViewer = openFullscreenViewer;

    function closeFullscreenViewer() {
        if (!fsModal) return;
        fsModal.classList.remove('is-open');
        fsModal.style.display = 'none';
        document.body.style.overflow = '';
        doResetZoom(fsZoomState, fsImg, fsViewport, fsBadge);
    }
    window.closeFullscreenViewer = closeFullscreenViewer;

    // =========================================================================
    // Bayut-Style Ken Burns Motion Tour & Interactive Video Walkthrough
    // =========================================================================
    var motionTourInterval = null;
    var isMotionTourActive = false;

    window.toggleMotionTour = function() {
        if (isMotionTourActive) {
            stopMotionTour();
        } else {
            startMotionTour();
        }
    };

    function startMotionTour() {
        isMotionTourActive = true;
        var btn = document.getElementById('motionTourToggleBtn');
        var icon = document.getElementById('motionTourIcon');
        var label = document.getElementById('motionTourLabel');
        if (btn) btn.classList.add('is-active');
        if (icon) {
            icon.classList.remove('fa-play');
            icon.classList.add('fa-pause');
        }
        if (label) label.textContent = 'Pause Tour';

        if (inlineImg) {
            inlineImg.classList.add('ken-burns-active');
        }

        // Cycle through showcase images every 4 seconds
        clearInterval(motionTourInterval);
        motionTourInterval = setInterval(function() {
            if (galleryImages.length > 1) {
                if (inlineImg) {
                    inlineImg.style.opacity = '0.4';
                    setTimeout(function() {
                        nextGalleryImage();
                        if (inlineImg) inlineImg.style.opacity = '1';
                    }, 240);
                } else {
                    nextGalleryImage();
                }
            }
        }, 4200);
    }

    function stopMotionTour() {
        isMotionTourActive = false;
        clearInterval(motionTourInterval);
        var btn = document.getElementById('motionTourToggleBtn');
        var icon = document.getElementById('motionTourIcon');
        var label = document.getElementById('motionTourLabel');
        if (btn) btn.classList.remove('is-active');
        if (icon) {
            icon.classList.remove('fa-pause');
            icon.classList.add('fa-play');
        }
        if (label) label.textContent = 'Live Motion Tour';

        if (inlineImg) {
            inlineImg.classList.remove('ken-burns-active');
            inlineImg.style.opacity = '1';
        }
    }
    window.startMotionTour = startMotionTour;
    window.stopMotionTour = stopMotionTour;

    // Virtual Walkthrough Modal Player Helpers
    var isTourVideoPlaying = true;
    var tourVideoInterval = null;
    var tourCurrentSeconds = 42;

    window.toggleTourVideoPlayback = function() {
        isTourVideoPlaying = !isTourVideoPlaying;
        var icon = document.getElementById('tourPlayerPlayIcon');
        var img = document.getElementById('virtualTourPlayerImage');
        if (icon) {
            if (isTourVideoPlaying) {
                icon.className = 'fa-solid fa-pause text-lg';
                if (img) img.classList.add('ken-burns-active');
            } else {
                icon.className = 'fa-solid fa-play text-lg ml-0.5';
                if (img) img.classList.remove('ken-burns-active');
            }
        }
    };

    window.switchVirtualTourRoom = function(idx, label) {
        if (!galleryImages[idx]) return;
        var img = document.getElementById('virtualTourPlayerImage');
        var lbl = document.getElementById('tourCurrentRoomLabel');
        var pBar = document.getElementById('tourProgressBar');
        if (img) {
            img.style.opacity = '0.3';
            setTimeout(function() {
                img.src = galleryImages[idx];
                img.style.opacity = '1';
            }, 200);
        }
        if (lbl) lbl.textContent = 'Perspective: ' + label;
        if (pBar) {
            var pct = Math.min(100, Math.round(((idx + 1) / galleryImages.length) * 100));
            pBar.style.width = pct + '%';
        }
    };

    window.handleVideoTourSubmit = function(e) {
        if (e && e.preventDefault) e.preventDefault();
        var alertBox = document.getElementById('videoTourSuccessAlert');
        var form = document.getElementById('videoTourRequestForm');
        if (alertBox) alertBox.classList.remove('hidden');
        if (form) form.classList.add('hidden');
        setTimeout(function() {
            closeVideoTourModal();
            if (form) form.reset();
            if (alertBox) alertBox.classList.add('hidden');
            if (form) form.classList.remove('hidden');
        }, 3200);
    };

    // Attach Event Listeners
    if (zoomInBtn) zoomInBtn.addEventListener('click', function(e) { e.stopPropagation(); doZoomIn(inlineZoomState, inlineImg, inlineViewport, zoomBadge); });
    if (zoomOutBtn) zoomOutBtn.addEventListener('click', function(e) { e.stopPropagation(); doZoomOut(inlineZoomState, inlineImg, inlineViewport, zoomBadge); });
    if (zoomResetBtn) zoomResetBtn.addEventListener('click', function(e) { e.stopPropagation(); doResetZoom(inlineZoomState, inlineImg, inlineViewport, zoomBadge); });
    if (fsTriggerBtn) fsTriggerBtn.addEventListener('click', function(e) { e.stopPropagation(); openFullscreenViewer(); });
    if (prevBtn) prevBtn.addEventListener('click', function(e) { e.stopPropagation(); prevGalleryImage(); });
    if (nextBtn) nextBtn.addEventListener('click', function(e) { e.stopPropagation(); nextGalleryImage(); });

    bindWheelZoom(inlineViewport, inlineImg, inlineZoomState, zoomBadge);
    bindDragPan(inlineViewport, inlineImg, inlineZoomState, zoomBadge, null);
    bindTouchGestures(inlineViewport, inlineImg, inlineZoomState, zoomBadge, nextGalleryImage, prevGalleryImage);

    // Modal Events
    if (fsZoomInBtn) fsZoomInBtn.addEventListener('click', function(e) { e.stopPropagation(); doZoomIn(fsZoomState, fsImg, fsViewport, fsBadge); });
    if (fsZoomOutBtn) fsZoomOutBtn.addEventListener('click', function(e) { e.stopPropagation(); doZoomOut(fsZoomState, fsImg, fsViewport, fsBadge); });
    if (fsZoomResetBtn) fsZoomResetBtn.addEventListener('click', function(e) { e.stopPropagation(); doResetZoom(fsZoomState, fsImg, fsViewport, fsBadge); });
    if (closeFsBtn) closeFsBtn.addEventListener('click', function(e) { e.stopPropagation(); closeFullscreenViewer(); });
    if (fsPrevBtn) fsPrevBtn.addEventListener('click', function(e) { e.stopPropagation(); prevGalleryImage(); });
    if (fsNextBtn) fsNextBtn.addEventListener('click', function(e) { e.stopPropagation(); nextGalleryImage(); });

    bindWheelZoom(fsViewport, fsImg, fsZoomState, fsBadge);
    bindDragPan(fsViewport, fsImg, fsZoomState, fsBadge, null);
    bindTouchGestures(fsViewport, fsImg, fsZoomState, fsBadge, nextGalleryImage, prevGalleryImage);

    // Close on backdrop click (outside image and control bars)
    if (fsModal) {
        fsModal.addEventListener('click', function(e) {
            if (e.target === fsModal || e.target === fsViewport) {
                closeFullscreenViewer();
            }
        });
    }

    // Keyboard Shortcuts
    window.addEventListener('keydown', function(e) {
        var isModalOpen = fsModal && fsModal.classList.contains('is-open');
        if (e.key === 'Escape' && isModalOpen) {
            closeFullscreenViewer();
        } else if (e.key === 'ArrowLeft') {
            prevGalleryImage();
        } else if (e.key === 'ArrowRight') {
            nextGalleryImage();
        } else if (e.key === '+' || e.key === '=') {
            if (isModalOpen) doZoomIn(fsZoomState, fsImg, fsViewport, fsBadge);
            else doZoomIn(inlineZoomState, inlineImg, inlineViewport, zoomBadge);
        } else if (e.key === '-') {
            if (isModalOpen) doZoomOut(fsZoomState, fsImg, fsViewport, fsBadge);
            else doZoomOut(inlineZoomState, inlineImg, inlineViewport, zoomBadge);
        } else if (e.key === '0') {
            if (isModalOpen) doResetZoom(fsZoomState, fsImg, fsViewport, fsBadge);
            else doResetZoom(inlineZoomState, inlineImg, inlineViewport, zoomBadge);
        }
    });

    // Window Resize Safety
    window.addEventListener('resize', function() {
        applyZoomTransform(inlineImg, inlineViewport, inlineZoomState, zoomBadge, false);
        if (fsModal && fsModal.classList.contains('is-open')) {
            applyZoomTransform(fsImg, fsViewport, fsZoomState, fsBadge, false);
        }
    });

    // Countdown Timer with Anti-Sniping Awareness
    var endTimeMs = parseSafeDateMs("<?php echo $effective_item_end ?? ($current_item['end_time'] ?? ''); ?>");
    var timerInterval = setInterval(function() {
        var now = new Date().getTime();
        var distance = endTimeMs - now;
        var timerEl = document.getElementById("timerDisplay");
        if (!timerEl) return;

        if (distance <= 0) {
            clearInterval(timerInterval);
            timerEl.innerHTML = '<i class="fa-solid fa-lock mr-1"></i> Auction Closed';
            timerEl.className = "text-base font-bold text-slate-400 mt-0.5";
        } else {
            var days = Math.floor(distance / (1000 * 60 * 60 * 24));
            var hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            var minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
            var seconds = Math.floor((distance % (1000 * 60)) / 1000);

            var timeTxt = (days > 0 ? days + "d " : "") +
                          String(hours).padStart(2, '0') + "h " + 
                          String(minutes).padStart(2, '0') + "m " + 
                          String(seconds).padStart(2, '0') + "s";

            if (distance < 180000) {
                timerEl.innerHTML = '<i class="fa-solid fa-fire text-amber-500 mr-1 animate-bounce"></i> ' + timeTxt;
                timerEl.className = "text-base font-extrabold text-rose-600 mt-0.5 animate-pulse";
            } else {
                timerEl.innerHTML = '<i class="fa-regular fa-clock mr-1"></i> ' + timeTxt;
                timerEl.className = "text-base font-bold text-rose-600 mt-0.5";
            }
        }
    }, 1000);

    // Quick Bid Increment Pill Handler
    function setQuickIncrement(addAmount) {
        var input = document.getElementById('bidAmountInput');
        if (!input) return;
        var minVal = parseFloat(input.getAttribute('min')) || 0;
        if (addAmount === 0) {
            input.value = minVal;
        } else {
            var cur = parseFloat(input.value) || minVal;
            input.value = Math.max(minVal, cur) + addAmount;
        }
        input.classList.add('ring-2', 'ring-emerald-500');
        setTimeout(() => input.classList.remove('ring-2', 'ring-emerald-500'), 400);
    }

    // Real-Time Polling for Active Lot
    var currentLotId = <?php echo (int)($current_item['id'] ?? 0); ?>;
    function pollAuctionData() {
        if (!currentLotId) return;
        fetch('live_auction.php?ajax=1&action=poll_status&item_id=' + currentLotId)
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    var curPriceEl = document.getElementById('currentPriceDisplay');
                    if (curPriceEl) curPriceEl.textContent = data.current_price;
                    var bidCountEl = document.getElementById('bidCountDisplay');
                    if (bidCountEl) bidCountEl.textContent = data.bid_count;
                    var watcherEl = document.getElementById('watcherCountDisplay');
                    if (watcherEl) watcherEl.textContent = data.watcher_count;
                    var nextMinEl = document.getElementById('nextMinBidDisplay');
                    if (nextMinEl) nextMinEl.textContent = 'Rs. ' + data.next_min_formatted;
                    var input = document.getElementById('bidAmountInput');
                    if (input) {
                        input.setAttribute('min', data.raw_next_min);
                        input.setAttribute('placeholder', 'Enter at least Rs. ' + data.next_min_formatted);
                    }
                    endTimeMs = parseSafeDateMs(data.end_time);

                    var resBadge = document.getElementById('reserveStatusBadge');
                    if (resBadge) {
                        resBadge.textContent = data.reserve_met ? 'Reserve Price Met' : 'Reserve Price Not Met';
                        resBadge.className = 'text-xs font-bold px-2.5 py-0.5 rounded-md border ' + 
                            (data.reserve_met ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-amber-50 text-amber-700 border-amber-200');
                    }

                    var feed = document.getElementById('bidHistoryFeed');
                    if (feed && data.history && data.history.length > 0) {
                        var feedHtml = '';
                        data.history.forEach(function(b) {
                            feedHtml += '<div class="flex items-center justify-between p-2.5 rounded-lg bg-slate-50 border border-slate-100 text-xs">' +
                                '<div class="flex items-center gap-2.5">' +
                                '<div class="w-7 h-7 rounded-full bg-emerald-100 text-emerald-800 flex items-center justify-center font-bold text-[11px]">' + b.initial + '</div>' +
                                '<div><span class="font-semibold text-slate-800">' + b.bidder + '</span>' +
                                '<span class="text-[10px] text-slate-400 block">' + b.time + '</span></div>' +
                                '</div>' +
                                '<div class="text-right"><span class="font-bold text-emerald-700 text-sm">Rs. ' + b.amount + '</span></div>' +
                                '</div>';
                        });
                        feed.innerHTML = feedHtml;
                    }
                }
            })
            .catch(() => {});
    }
    setInterval(pollAuctionData, 3000);

    // Bid Form Handler
    var bidForm = document.getElementById('bidForm');
    if (bidForm) {
        bidForm.addEventListener('submit', function(e) {
            e.preventDefault();
            var input = document.getElementById('bidAmountInput');
            var minVal = parseFloat(input.getAttribute('min'));
            var val = parseFloat(input.value);
            if (isNaN(val) || val < minVal) {
                input.classList.add('bid-input-error');
                input.focus();
                setTimeout(() => input.classList.remove('bid-input-error'), 1800);
                return;
            }
            var btn = document.getElementById('bidSubmitBtn');
            btn.disabled = true;
            var fd = new FormData();
            fd.append('action', 'ajax_place_bid');
            fd.append('item_id', currentLotId);
            fd.append('bid_amount', val);
            fetch('live_auction.php?ajax=1', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(res => {
                    btn.disabled = false;
                    var toast = document.getElementById('liveBidToast');
                    toast.classList.remove('hidden', 'bg-emerald-50', 'text-emerald-800', 'border-emerald-200', 'bg-rose-50', 'text-rose-800', 'border-rose-200');
                    if (res.success) {
                        toast.classList.add('bg-emerald-50', 'text-emerald-800', 'border', 'border-emerald-200');
                        toast.innerHTML = '<i class="fa-solid fa-circle-check text-emerald-600 text-lg"></i><div>' + res.message + '</div>';
                        input.value = '';
                        pollAuctionData();
                    } else {
                        if (res.redirect) {
                            window.location.href = res.redirect;
                            return;
                        }
                        toast.classList.add('bg-rose-50', 'text-rose-800', 'border', 'border-rose-200');
                        toast.innerHTML = '<i class="fa-solid fa-circle-exclamation text-rose-600 text-lg"></i><div>' + res.error + '</div>';
                    }
                    setTimeout(() => toast.classList.add('hidden'), 5000);
                })
                .catch(() => { btn.disabled = false; });
        });
    }

    function showLiveModalPopup(title, message, isSuccess) {
        var modal = document.getElementById('genericActionModal');
        var iconRing = document.getElementById('genericModalIconRing');
        var icon = document.getElementById('genericModalIcon');
        var titleEl = document.getElementById('genericModalTitle');
        var msgEl = document.getElementById('genericModalMsg');
        var btn = document.getElementById('genericModalBtn');

        if (!modal) return;
        if (isSuccess) {
            iconRing.className = 'w-14 h-14 rounded-full mx-auto mb-3 flex items-center justify-center text-2xl bg-emerald-50 text-emerald-600 border-2 border-emerald-200';
            icon.className = 'fa-solid fa-circle-check';
            btn.className = 'w-full py-2.5 rounded-xl font-bold text-xs text-white transition shadow-sm bg-emerald-600 hover:bg-emerald-700 cursor-pointer';
            btn.textContent = 'Continue';
        } else {
            iconRing.className = 'w-14 h-14 rounded-full mx-auto mb-3 flex items-center justify-center text-2xl bg-rose-50 text-rose-600 border-2 border-rose-200';
            icon.className = 'fa-solid fa-circle-exclamation';
            btn.className = 'w-full py-2.5 rounded-xl font-bold text-xs text-white transition shadow-sm bg-rose-600 hover:bg-rose-700 cursor-pointer';
            btn.textContent = 'Okay, Got It';
        }
        titleEl.textContent = title;
        msgEl.textContent = message;
        modal.classList.add('is-open');
        document.body.style.overflow = 'hidden';
    }

    function closeGenericModal() {
        var modal = document.getElementById('genericActionModal');
        if (modal) modal.classList.remove('is-open');
        document.body.style.overflow = '';
    }

    // Watchlist Toggle
    function toggleWatchlist(itemId) {
        var fd = new FormData();
        fd.append('action', 'toggle_watchlist');
        fd.append('item_id', itemId);

        fetch('live_auction.php?ajax=1', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    var icon = document.getElementById('watchlistIcon');
                    if (icon) {
                        icon.className = 'fa-' + (data.in_watchlist ? 'solid' : 'regular') + ' fa-heart text-sm';
                    }
                    var cnt = document.getElementById('watcherCountDisplay');
                    if (cnt) cnt.textContent = data.count;
                } else if (data.redirect) {
                    window.location.href = data.redirect;
                } else {
                    showLiveModalPopup('Watchlist Notice', data.error, false);
                }
            });
    }

    // Auto-Bid Setup Prompt
    function openAutoBidPrompt(itemId) {
        var targetId = itemId || currentLotId;
        var maxAmount = prompt("Enter your Maximum Bid limit for Auto-Bid (Rs.):");
        if (maxAmount && parseFloat(maxAmount) > 0) {
            var fd = new FormData();
            fd.append('action', 'set_autobid');
            fd.append('item_id', targetId);
            fd.append('max_bid_amount', maxAmount);

            fetch('live_auction.php?ajax=1', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    showLiveModalPopup(data.success ? 'Auto-Bid Active' : 'Auto-Bid Error', data.message || data.error, data.success);
                    if (data.success) pollAuctionData();
                });
        }
    }

    // Buy It Now Confirm
    function confirmBuyItNow(itemId, price) {
        if (!confirm("Are you sure you want to purchase this lot immediately for Rs. " + Number(price).toLocaleString() + "? This will end the auction.")) return;
        var fd = new FormData();
        fd.append('action', 'buy_it_now');
        fd.append('item_id', itemId);

        fetch('live_auction.php?ajax=1', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                showLiveModalPopup(data.success ? 'Purchase Complete' : 'Purchase Error', data.message || data.error, data.success);
                if (data.success) setTimeout(() => window.location.reload(), 1500);
            });
    }

    // Modal Details View
    function openDetailsModal(itemId) {
        fetch('live_auction.php?ajax=1&action=get_modal_details&item_id=' + itemId)
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('modalLotCode').textContent = '#' + data.auction_code;
                    document.getElementById('modalImage').src = data.image;
                    document.getElementById('modalTitle').textContent = data.title;
                    document.getElementById('modalSeller').textContent = 'Listed by: ' + data.seller_name;
                    var modalAv = document.getElementById('modalSellerAvatar');
                    if (modalAv && data.seller_avatar) {
                        modalAv.src = data.seller_avatar;
                    }
                    document.getElementById('modalDescription').textContent = data.description;
                    document.getElementById('modalCurrentPrice').textContent = 'Rs. ' + data.current_price;
                    document.getElementById('modalNextMin').textContent = 'Rs. ' + data.next_min;
                    document.getElementById('modalBidCount').textContent = data.bid_count;
                    document.getElementById('modalBidNowBtn').href = 'live_auction.php?id=' + data.id;

                    var distEl = document.getElementById('modalDistanceTag');
                    if (data.distance_km !== null) {
                        distEl.textContent = '📍 ' + data.location_area + ' • ' + data.distance_km + ' km away';
                        distEl.classList.remove('hidden');
                    } else {
                        distEl.classList.add('hidden');
                    }

                    var resBadge = document.getElementById('modalReserveStatus');
                    resBadge.textContent = data.reserve_met ? 'Reserve Met' : 'Reserve Not Met';
                    resBadge.className = 'text-xs font-semibold px-2 py-0.5 rounded border ' + 
                        (data.reserve_met ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-amber-50 text-amber-700 border-amber-200');

                    var watchBtn = document.getElementById('modalWatchBtn');
                    watchBtn.onclick = function() { toggleWatchlist(data.id); };

                    activeModalItemId = data.id;
                    fetchLotComments(data.id, 'modalCommentsList', 'modalCommentsCountBadge');

                    document.getElementById('auctionDetailsModal').classList.add('is-open');
                    document.body.style.overflow = 'hidden';
                } else {
                    showLiveModalPopup('Item Details Error', data.error, false);
                }
            });
    }

    function closeDetailsModal() {
        document.getElementById('auctionDetailsModal').classList.remove('is-open');
        document.body.style.overflow = '';
        activeModalItemId = 0;
    }

    // ── Comments AJAX System ──
    var activeModalItemId = 0;

    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function escapeJs(str) {
        if (!str) return '';
        return String(str)
            .replace(/\\/g, '\\\\')
            .replace(/'/g, "\\'")
            .replace(/"/g, '\\"');
    }

    window.replyToComment = function(author) {
        var inp = document.getElementById('heroCommentInput');
        if (!inp) {
            var alertEl = document.getElementById('heroCommentAlert');
            if (alertEl) {
                showCommentFeedback(alertEl, 'Please log in to reply to this comment.', false);
            }
            return;
        }
        inp.focus();
        var cleanAuthor = author.replace(/[^a-zA-Z0-9_.-]/g, '');
        inp.value = '@' + cleanAuthor + ' ' + inp.value.replace(/^@[a-zA-Z0-9_.-]+\s*/, '');
        var charCounter = document.getElementById('heroCommentCharCount');
        if (charCounter) charCounter.textContent = inp.value.length + '/500';
        inp.scrollIntoView({ behavior: 'smooth', block: 'center' });
    };

    function fetchLotComments(itemId, containerId, countBadgeId) {
        var container = document.getElementById(containerId);
        if (!container || !itemId) return;

        container.innerHTML = '<div class="text-center py-6 text-slate-400 text-xs"><i class="fa-solid fa-spinner fa-spin text-emerald-600 text-base mb-1.5 block"></i>Loading comments...</div>';

        fetch('ajax/comments.php?action=get&item_id=' + itemId)
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    var countEl = document.getElementById(countBadgeId);
                    if (countEl) {
                        countEl.textContent = data.count + (countBadgeId.includes('Badge') ? ' comments' : '');
                    }

                    if (!data.comments || data.comments.length === 0) {
                        container.innerHTML = '<div class="text-center py-6 text-slate-400 text-xs bg-slate-50/70 rounded-xl border border-dashed border-slate-200"><i class="fa-regular fa-comment-dots text-slate-300 text-2xl mb-1.5 block"></i>No comments on this lot yet. Be the first to ask a question!</div>';
                        return;
                    }

                    var html = '';
                    data.comments.forEach(function(c) {
                        var safeName = escapeHtml(c.user_name || 'Auction Member');
                        var defaultAv = 'https://ui-avatars.com/api/?name=' + encodeURIComponent(c.user_name || 'U') + '&background=008489&color=fff&size=80&bold=true';
                        var avSrc = c.user_avatar ? escapeHtml(c.user_avatar) : defaultAv;

                        var roleBadge = '';
                        var rLower = String(c.user_role || 'buyer').toLowerCase();
                        var cardExtraClass = '';
                        if (rLower === 'seller') {
                            roleBadge = '<span class="inline-flex items-center gap-1 text-[11px] font-bold px-2.5 py-0.5 rounded-full bg-teal-50 text-[#008489] border border-teal-200"><i class="fa-solid fa-certificate text-[9px]"></i> Verified Seller</span>';
                            cardExtraClass = ' is-seller';
                        } else if (rLower === 'admin') {
                            roleBadge = '<span class="inline-flex items-center gap-1 text-[11px] font-bold px-2.5 py-0.5 rounded-full bg-purple-50 text-purple-700 border border-purple-200"><i class="fa-solid fa-shield-halved text-[9px]"></i> Staff / Admin</span>';
                            cardExtraClass = ' is-admin';
                        } else {
                            roleBadge = '<span class="inline-flex items-center gap-1 text-[11px] font-semibold px-2.5 py-0.5 rounded-full bg-slate-100 text-slate-600 border border-slate-200"><i class="fa-solid fa-gavel text-[9px]"></i> Bidder</span>';
                        }

                        // Format @mentions in comment text
                        var rawText = escapeHtml(c.comment || '');
                        var formattedComment = rawText.replace(/@([a-zA-Z0-9_.-]+)/g, '<span class="font-bold text-[#008489] bg-teal-50 px-1.5 py-0.5 rounded border border-teal-200/60 inline-flex items-center gap-0.5"><i class="fa-solid fa-at text-[10px]"></i>$1</span>');

                        html += '<div class="comment-card' + cardExtraClass + '">';
                        html += '  <div class="flex items-start gap-3.5">';
                        html += '    <img src="' + avSrc + '" alt="' + safeName + '" class="w-10 h-10 rounded-full object-cover border-2 border-white ring-1 ring-slate-200 shadow-xs shrink-0 mt-0.5" onerror="this.src=\'' + defaultAv + '\'">';
                        html += '    <div class="flex-1 min-w-0">';
                        html += '      <div class="flex flex-wrap items-center justify-between gap-2">';
                        html += '        <div class="flex items-center gap-2 flex-wrap">';
                        html += '          <strong class="text-slate-900 font-bold text-sm tracking-tight">' + safeName + '</strong>';
                        html += '          ' + roleBadge;
                        html += '        </div>';
                        html += '        <span class="text-[11px] text-slate-400 font-medium" title="' + escapeHtml(c.created_at) + '"><i class="fa-regular fa-clock text-[10px] mr-1"></i>' + escapeHtml(c.time_ago) + '</span>';
                        html += '      </div>';
                        html += '      <div class="comment-bubble">' + formattedComment + '</div>';
                        html += '      <div class="flex items-center gap-3 mt-2.5 text-xs flex-wrap">';
                        html += '        <button type="button" onclick="replyToComment(\'' + escapeJs(c.user_name || 'User') + '\')" class="comment-action-btn btn-reply"><i class="fa-solid fa-reply text-[10px]"></i> Reply</button>';
                        html += '        <button type="button" onclick="this.classList.toggle(\'is-helpful-active\'); var icon=this.querySelector(\'i\'); var cnt=this.querySelector(\'.hlp-cnt\'); if(this.classList.contains(\'is-helpful-active\')){ icon.className=\'fa-solid fa-thumbs-up text-[10px]\'; cnt.textContent=parseInt(cnt.textContent||0)+1; } else { icon.className=\'fa-regular fa-thumbs-up text-[10px]\'; cnt.textContent=Math.max(0, parseInt(cnt.textContent||0)-1); }" class="comment-action-btn"><i class="fa-regular fa-thumbs-up text-[10px]"></i> Helpful (<span class="hlp-cnt">0</span>)</button>';
                        html += '        <span class="text-slate-300">&bull;</span>';
                        html += '        <span class="text-[11px] text-slate-400 inline-flex items-center gap-1"><i class="fa-solid fa-shield-check text-[10px] text-teal-600"></i> Verified Inquiry</span>';
                        html += '      </div>';
                        html += '    </div>';
                        html += '  </div>';
                        html += '</div>';
                    });
                    container.innerHTML = html;
                } else {
                    container.innerHTML = '<div class="text-center py-4 text-rose-500 text-xs">' + escapeHtml(data.message || 'Error loading comments.') + '</div>';
                }
            })
            .catch(function(err) {
                container.innerHTML = '<div class="text-center py-4 text-rose-500 text-xs">Failed to load comments.</div>';
            });
    }

    function postLiveComment(itemId, inputId, alertId, btnId, containerId, countId) {
        var input = document.getElementById(inputId);
        var alertEl = document.getElementById(alertId);
        var btn = document.getElementById(btnId);
        if (!input || !btn) return;

        var text = input.value.trim();
        if (text.length < 2) {
            showCommentFeedback(alertEl, 'Please enter a valid comment (minimum 2 characters).', false);
            return;
        }

        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Posting...';

        var fd = new FormData();
        fd.append('action', 'add');
        fd.append('item_id', itemId);
        fd.append('comment', text);
        var csrfMeta = document.querySelector('meta[name="csrf-token"]');
        if (csrfMeta) fd.append('csrf_token', csrfMeta.getAttribute('content'));

        fetch('ajax/comments.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-paper-plane text-[10px]"></i> Post Comment';

                if (data.success) {
                    input.value = '';
                    var charCounter = document.getElementById('heroCommentCharCount');
                    if (charCounter) charCounter.textContent = '0/500';
                    showCommentFeedback(alertEl, data.message || 'Comment posted successfully!', true);
                    fetchLotComments(itemId, containerId, countId);
                } else {
                    showCommentFeedback(alertEl, data.message || 'Failed to post comment.', false);
                }
            })
            .catch(err => {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-paper-plane text-[10px]"></i> Post Comment';
                showCommentFeedback(alertEl, 'Network error. Please try again.', false);
            });
    }

    function submitModalComment() {
        if (!activeModalItemId) return;
        var input = document.getElementById('modalCommentInput');
        var alertEl = document.getElementById('modalCommentAlert');
        var btn = document.getElementById('modalBtnPostComment');
        if (!input || !btn) return;

        var text = input.value.trim();
        if (text.length < 2) {
            showCommentFeedback(alertEl, 'Comment must be at least 2 characters.', false);
            return;
        }

        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';

        var fd = new FormData();
        fd.append('action', 'add');
        fd.append('item_id', activeModalItemId);
        fd.append('comment', text);
        var csrfMeta = document.querySelector('meta[name="csrf-token"]');
        if (csrfMeta) fd.append('csrf_token', csrfMeta.getAttribute('content'));

        fetch('ajax/comments.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-paper-plane text-[9px]"></i> Post';

                if (data.success) {
                    input.value = '';
                    var charCounter = document.getElementById('modalCommentCharCount');
                    if (charCounter) charCounter.textContent = '0/500';
                    showCommentFeedback(alertEl, 'Comment posted!', true);
                    fetchLotComments(activeModalItemId, 'modalCommentsList', 'modalCommentsCountBadge');
                } else {
                    showCommentFeedback(alertEl, data.message || 'Error posting comment.', false);
                }
            })
            .catch(err => {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-paper-plane text-[9px]"></i> Post';
                showCommentFeedback(alertEl, 'Network error.', false);
            });
    }

    function showCommentFeedback(el, msg, isSuccess) {
        if (!el) return;
        el.textContent = msg;
        el.className = 'mt-2 text-xs p-2.5 rounded-lg ' + (isSuccess ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-rose-50 text-rose-800 border border-rose-200');
        el.classList.remove('hidden');
        setTimeout(() => { el.classList.add('hidden'); }, 4000);
    }

    // =========================================================================
    // TruBroker™ Real Estate Agent Profile Card & AJAX Interaction Tracking
    // =========================================================================
    function initAgentProfileCard() {
        var card = document.getElementById('realEstateAgentCard');
        if (!card) return;

        var agentId = card.getAttribute('data-agent-id') || 1;
        var actionBtns = card.querySelectorAll('.agent-contact-btn');

        actionBtns.forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                var action = this.getAttribute('data-agent-action');
                if (!action) return;

                // Subtle visual feedback
                btn.classList.add('scale-95');
                setTimeout(() => btn.classList.remove('scale-95'), 180);

                // Non-blocking AJAX POST click tracking to api/agent.php
                var fd = new FormData();
                fd.append('agent_id', agentId);
                fd.append('interaction_type', action);

                fetch('api/agent.php', {
                    method: 'POST',
                    body: fd
                }).catch(function() {});
            });
        });
    }

    // Async Fetch function to dynamically rehydrate or reload agent profile
    window.loadAgentProfile = function(agentId) {
        return fetch('api/agent.php?id=' + encodeURIComponent(agentId || 1))
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (!data || !data.success || !data.agent) return;
                var a = data.agent;
                var nameEl = document.getElementById('agentCardName');
                var avatarEl = document.getElementById('agentCardAvatar');
                var logoEl = document.getElementById('agentCardAgencyLogo');
                var emailBtn = document.getElementById('agentActionEmail');
                var callBtn = document.getElementById('agentActionCall');
                var waBtn = document.getElementById('agentActionWhatsapp');

                if (nameEl) nameEl.textContent = a.name;
                if (avatarEl && a.avatar_url) avatarEl.src = a.avatar_url;
                if (logoEl && a.agency_logo) logoEl.src = a.agency_logo;
                if (emailBtn && a.email) emailBtn.href = 'mailto:' + a.email;
                if (callBtn && a.phone) callBtn.href = 'tel:' + a.phone;
                if (waBtn && a.whatsapp_number) {
                    var cleanNum = a.whatsapp_number.replace(/[^0-9]/g, '');
                    waBtn.href = 'https://wa.me/' + cleanNum + '?text=' + encodeURIComponent('Hello ' + a.name + ', I am inquiring about your property on Geo Auction.');
                }

                if (a.featured_community) {
                    var fc = a.featured_community;
                    var cTitle = document.getElementById('agentCommunityTitle');
                    var cDesc = document.getElementById('agentCommunityDesc');
                    var cThumb = document.getElementById('agentCommunityThumb');
                    var cLink = document.getElementById('agentFeaturedCommunityCard');

                    if (cTitle) cTitle.textContent = fc.title;
                    if (cDesc) cDesc.textContent = fc.description;
                    if (cThumb && fc.image_url) cThumb.src = fc.image_url;
                    if (cLink && fc.target_url) cLink.href = fc.target_url;
                }
            })
            .catch(function(err) {
                console.warn('Could not load agent profile:', err);
            });
    };

    document.addEventListener("DOMContentLoaded", function() {
        initAgentProfileCard();
        var fsModalEl = document.getElementById('fullscreenImageViewerModal');
        if (fsModalEl) {
            fsModalEl.classList.remove('is-open');
            fsModalEl.style.display = 'none';
        }
        var heroInp = document.getElementById('heroCommentInput');
        var heroCount = document.getElementById('heroCommentCharCount');
        if (heroInp && heroCount) {
            heroInp.addEventListener('input', function() {
                heroCount.textContent = this.value.length + '/500';
            });
        }
        var modalInp = document.getElementById('modalCommentInput');
        var modalCount = document.getElementById('modalCommentCharCount');
        if (modalInp && modalCount) {
            modalInp.addEventListener('input', function() {
                modalCount.textContent = this.value.length + '/500';
            });
        }
        <?php if ($current_item): ?>
            fetchLotComments(<?php echo (int)$current_item['id']; ?>, 'heroCommentsContainer', 'heroCommentsCount');
        <?php endif; ?>
    });

    // Geo-Auction Leaflet Map
    <?php if ($view_mode === 'map'): ?>
    document.addEventListener("DOMContentLoaded", function() {
        var mapEl = document.getElementById('liveAuctionMap');
        if (!mapEl) return;
        var map = L.map('liveAuctionMap').setView([<?php echo $user_lat; ?>, <?php echo $user_lng; ?>], 12);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap'
        }).addTo(map);

        L.marker([<?php echo $user_lat; ?>, <?php echo $user_lng; ?>])
            .addTo(map)
            .bindPopup("<strong>You are here (Qasimabad)</strong>")
            .openPopup();

        <?php foreach ($catalog_list as $cl): ?>
            <?php if (!empty($cl['location_lat']) && !empty($cl['location_lng'])): ?>
                L.marker([<?php echo (float)$cl['location_lat']; ?>, <?php echo (float)$cl['location_lng']; ?>])
                    .addTo(map)
                    .bindPopup("<strong><?php echo htmlspecialchars(addslashes($cl['title'])); ?></strong><br>Rs. <?php echo number_format($cl['current_price'], 0); ?><br><a href='live_auction.php?id=<?php echo $cl['id']; ?>'>View Lot</a>");
            <?php endif; ?>
        <?php endforeach; ?>
    });
    <?php endif; ?>


</script>
</div><!-- end .live-arena-wrapper -->

<?php 
if (file_exists(__DIR__ . '/../includes/footer.php')) {
    include_once __DIR__ . '/../includes/footer.php';
} elseif (file_exists(__DIR__ . '/includes/footer.php')) {
    include_once __DIR__ . '/includes/footer.php';
} else {
    echo '</body></html>';
}
?>