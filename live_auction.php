<?php
ini_set('display_errors', 0);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_GET['switch_role']) && in_array(strtolower($_GET['switch_role']), ['buyer', 'seller', 'admin'])) {
    $switched = strtolower($_GET['switch_role']);
    $_SESSION['role'] = $switched;
    if ($switched === 'admin') {
        $_SESSION['user_id'] = 1;
        $_SESSION['user_name'] = 'Admin User';
        $_SESSION['email'] = 'admin@gmail.com';
    } elseif ($switched === 'seller') {
        $_SESSION['user_id'] = 2;
        $_SESSION['user_name'] = 'Ghulam Fatima';
        $_SESSION['email'] = 'ghulalmfatima@gmail.com';
    } else {
        $_SESSION['user_id'] = 3;
        $_SESSION['user_name'] = 'aakash';
        $_SESSION['email'] = 'aakash@gmail.com';
    }
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

$has_db = isset($conn) && $conn instanceof mysqli && !$conn->connect_error;
$user_lat = isset($_SESSION['user_lat']) && is_numeric($_SESSION['user_lat']) ? (float)$_SESSION['user_lat'] : 25.3960;
$user_lng = isset($_SESSION['user_lng']) && is_numeric($_SESSION['user_lng']) ? (float)$_SESSION['user_lng'] : 68.3578;

if ($has_db) {
    @$conn->query("
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
    @$conn->query("UPDATE items SET status = 'ended' WHERE status = '' OR status IS NULL");
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
    if (preg_match('/^(https?:\/\/|\/\/|data:)/i', $path)) {
        return $path;
    }
    if (preg_match('/^https?:\/[^\/]/i', $path)) {
        return preg_replace('/^(https?):\/([^\/])/i', '$1://$2', $path);
    }
    $path = str_replace(['\\/', '//'], '/', $path);
    $path = trim($path);
    if (empty($path)) return '';
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
 * Robust Item Image Parser (Checks item_images table, featured_image, items.images JSON/CSV)
 */
function parseItemImages($conn, $item_id = 0, $imgField = '', $featuredField = '') {
    $images = [];
    if (!empty($featuredField)) {
        $cleaned = cleanImagePath($featuredField);
        if (!empty($cleaned) && !in_array($cleaned, $images)) $images[] = $cleaned;
    }
    if ($conn instanceof mysqli && $item_id > 0) {
        try {
            $stmt = mysqli_prepare($conn, "SELECT image_path FROM item_images WHERE item_id = ? ORDER BY is_primary DESC, sort_order ASC, id ASC");
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "i", $item_id);
                mysqli_stmt_execute($stmt);
                $res = mysqli_stmt_get_result($stmt);
                while ($r = mysqli_fetch_assoc($res)) {
                    $cleaned = cleanImagePath($r['image_path'] ?? '');
                    if (!empty($cleaned) && !in_array($cleaned, $images)) $images[] = $cleaned;
                }
                mysqli_stmt_close($stmt);
            }
        } catch (Exception $e) {}
    }
    if (!empty($imgField)) {
        $raw = trim((string)$imgField);
        $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                foreach ($decoded as $p) {
                    $val = is_string($p) ? cleanImagePath($p) : (is_array($p) ? cleanImagePath($p['image_path'] ?? $p['url'] ?? '') : '');
                    if (!empty($val) && !in_array($val, $images)) $images[] = $val;
                }
            } else {
                $parts = explode(',', $raw);
                foreach ($parts as $p) {
                    $val = cleanImagePath($p);
                    if (!empty($val) && !in_array($val, $images)) $images[] = $val;
                }
            }
    }
    if (empty($images)) {
        $images[] = 'https://images.unsplash.com/photo-1552519507-da3b142c6e3d?auto=format&fit=crop&w=800&q=80';
    }
    return $images;
}
function sendNotification($conn, $to_user, $type, $title, $msg, $link = '') {
    if (!$conn || $to_user <= 0) return;
    try {
        $stmt = mysqli_prepare($conn, "INSERT INTO user_notifications (`user_id`, `type`, `title`, `message`, `link`, `is_read`, `created_at`) VALUES (?, ?, ?, ?, ?, 0, NOW())");
        if (!$stmt) {
            $stmt = mysqli_prepare($conn, "INSERT INTO notifications (`user_id`, `type`, `title`, `message`, `link`, `is_read`, `created_at`) VALUES (?, ?, ?, ?, ?, 0, NOW())");
        }
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "issss", $to_user, $type, $title, $msg, $link);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
    } catch (Exception $e) {
        error_log("Failed to send notification: " . $e->getMessage());
    }
}

/**
 * Multi-Agent Auto-Bid (Bidbot) Escalation Engine
 */
function runAutoBids($conn, $item_id, $current_highest, $exclude_uid = 0) {
    if (!$conn || $item_id <= 0) return;

    $stmt = mysqli_prepare($conn, "
        SELECT ab.id, ab.user_id, ab.max_bid_amount, u.name AS bidder_name, u.email AS bidder_email, i.min_increment, i.seller_id
        FROM auto_bids ab
        JOIN items i ON ab.item_id = i.id
        JOIN users u ON ab.user_id = u.id
        WHERE ab.item_id = ? AND ab.is_active = 1 AND ab.user_id != ? AND ab.user_id != i.seller_id
        ORDER BY ab.max_bid_amount DESC, ab.created_at ASC
        LIMIT 1
    ");
    if (!$stmt) return;
    mysqli_stmt_bind_param($stmt, "ii", $item_id, $exclude_uid);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);

    if ($auto = mysqli_fetch_assoc($res)) {
        $min_inc = (float)($auto['min_increment'] > 0 ? $auto['min_increment'] : 100.00);
        $next_bid = $current_highest + $min_inc;
        $max_bid = (float)$auto['max_bid_amount'];

        if ($next_bid <= $max_bid) {
            $insert_bid = mysqli_prepare($conn, "INSERT INTO bids (item_id, user_id, bidder_name, bidder_email, bid_amount, bid_status, created_at, status) VALUES (?, ?, ?, ?, ?, 'active', NOW(), 'active')");
            $b_name = $auto['bidder_name'] . ' (AutoBid)';
            $b_email = $auto['bidder_email'] ?? '';
            mysqli_stmt_bind_param($insert_bid, "iissd", $item_id, $auto['user_id'], $b_name, $b_email, $next_bid);
            if (mysqli_stmt_execute($insert_bid)) {
                $up_item = mysqli_prepare($conn, "UPDATE items SET current_price = ?, bid_count = bid_count + 1 WHERE id = ?");
                mysqli_stmt_bind_param($up_item, "di", $next_bid, $item_id);
                mysqli_stmt_execute($up_item);
                sendNotification($conn, $auto['seller_id'], 'bid', 'Auto-Bid Received', "Auto-bid of Rs. " . number_format($next_bid, 2) . " placed on your lot.", "live_auction.php?id=$item_id");
            }
            mysqli_stmt_close($insert_bid);
        } else {
            $deact = mysqli_prepare($conn, "UPDATE auto_bids SET is_active = 0 WHERE id = ?");
            mysqli_stmt_bind_param($deact, "i", $auto['id']);
            mysqli_stmt_execute($deact);
            mysqli_stmt_close($deact);
            sendNotification($conn, $auto['user_id'], 'outbid', 'Auto-Bid Exceeded', "Your auto-bid limit of Rs. " . number_format($max_bid, 2) . " was exceeded.", "live_auction.php?id=$item_id");
        }
    }
    mysqli_stmt_close($stmt);
}

// --------------------------------------------------------------------------
// AJAX Handlers
// --------------------------------------------------------------------------
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_POST['action'] ?? $_GET['action'] ?? '';

    // Item Comments AJAX Actions Routing
    if (in_array($action, ['add_comment', 'post_comment', 'fetch_comments', 'get_comments', 'get_card_comments'])) {
        require __DIR__ . '/ajax_comment.php';
        exit();
    }

    // 1. Poll Status
    if ($action === 'poll_status') {
        $poll_id = (int)($_GET['item_id'] ?? 0);
        if (!$has_db || $poll_id <= 0) {
            echo json_encode(['success' => false, 'error' => 'Invalid lot ID']);
            exit();
        }

        $stmt = mysqli_prepare($conn, "
            SELECT i.*, 
            (SELECT COUNT(*) FROM wishlist WHERE item_id = i.id) AS watcher_count
            FROM items i WHERE i.id = ?
        ");
        mysqli_stmt_bind_param($stmt, "i", $poll_id);
        mysqli_stmt_execute($stmt);
        $item = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

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

        // Auto finalize on timer expiration
        if ($is_closed && $item['status'] === 'active') {
            $reserve = (float)($item['reserve_price'] ?? 0);
            $h_stmt = mysqli_prepare($conn, "SELECT id, user_id, bid_amount FROM bids WHERE item_id = ? ORDER BY bid_amount DESC LIMIT 1");
            mysqli_stmt_bind_param($h_stmt, "i", $poll_id);
            mysqli_stmt_execute($h_stmt);
            $h_res = mysqli_stmt_get_result($h_stmt);
            if ($top_bid = mysqli_fetch_assoc($h_res)) {
                if ($reserve <= 0 || (float)$top_bid['bid_amount'] >= $reserve) {
                    $fin_stmt = mysqli_prepare($conn, "UPDATE items SET status = 'sold', winner_id = ?, winning_bid_id = ? WHERE id = ?");
                    mysqli_stmt_bind_param($fin_stmt, "iii", $top_bid['user_id'], $top_bid['id'], $poll_id);
                    mysqli_stmt_execute($fin_stmt);
                    mysqli_stmt_close($fin_stmt);
                    sendNotification($conn, $top_bid['user_id'], 'won', 'You Won the Auction!', "Congratulations! You won lot #$poll_id with Rs. " . number_format($top_bid['bid_amount'], 2), "live_auction.php?id=$poll_id");
                } else {
                    $fin_stmt = mysqli_prepare($conn, "UPDATE items SET status = 'ended' WHERE id = ?");
                    mysqli_stmt_bind_param($fin_stmt, "i", $poll_id);
                    mysqli_stmt_execute($fin_stmt);
                    mysqli_stmt_close($fin_stmt);
                }
            } else {
                $fin_stmt = mysqli_prepare($conn, "UPDATE items SET status = 'ended' WHERE id = ?");
                mysqli_stmt_bind_param($fin_stmt, "i", $poll_id);
                mysqli_stmt_execute($fin_stmt);
                mysqli_stmt_close($fin_stmt);
            }
            mysqli_stmt_close($h_stmt);
        }

        $b_stmt = mysqli_prepare($conn, "
            SELECT b.id, b.bid_amount, b.created_at, b.bid_status, b.is_winning, u.name AS bidder_name 
            FROM bids b 
            JOIN users u ON b.user_id = u.id 
            WHERE b.item_id = ? 
            ORDER BY b.bid_amount DESC LIMIT 6
        ");
        mysqli_stmt_bind_param($b_stmt, "i", $poll_id);
        mysqli_stmt_execute($b_stmt);
        $b_res = mysqli_stmt_get_result($b_stmt);
        $history = [];
        while ($b_row = mysqli_fetch_assoc($b_res)) {
            $name_parts = explode(' ', trim($b_row['bidder_name']));
            $masked = htmlspecialchars($name_parts[0]) . (count($name_parts) > 1 ? ' ' . substr($name_parts[1], 0, 1) . '***' : ' ***');
            $history[] = [
                'bidder' => $masked,
                'amount' => number_format((float)$b_row['bid_amount'], 2),
                'time' => date('M d, h:i A', strtotime($b_row['created_at'])),
                'initial' => strtoupper(substr($b_row['bidder_name'] ?? 'B', 0, 1))
            ];
        }
        mysqli_stmt_close($b_stmt);

        $curr_price = max((float)$item['current_price'], (float)$item['starting_price']);
        $min_inc = (float)($item['min_increment'] > 0 ? $item['min_increment'] : 100.00);
        $next_min = $curr_price + $min_inc;
        $reserve_met = (empty($item['reserve_price']) || $curr_price >= (float)$item['reserve_price']);

        $cm_cnt_stmt = mysqli_prepare($conn, "SELECT COUNT(id) AS total FROM item_comments WHERE item_id = ?");
        $poll_comments = 0;
        if ($cm_cnt_stmt) {
            mysqli_stmt_bind_param($cm_cnt_stmt, "i", $poll_id);
            mysqli_stmt_execute($cm_cnt_stmt);
            $poll_comments = (int)mysqli_fetch_assoc(mysqli_stmt_get_result($cm_cnt_stmt))['total'];
            mysqli_stmt_close($cm_cnt_stmt);
        }

        echo json_encode([
            'success' => true,
            'current_price' => number_format($curr_price, 2),
            'raw_price' => $curr_price,
            'min_increment' => number_format($min_inc, 2),
            'next_min_formatted' => number_format($next_min, 2),
            'raw_next_min' => $next_min,
            'bid_count' => (int)$item['bid_count'],
            'watcher_count' => (int)$item['watcher_count'],
            'total_comments' => $poll_comments,
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

        mysqli_begin_transaction($conn);
        try {
            $stmt = mysqli_prepare($conn, "SELECT current_price, starting_price, min_increment, reserve_price, status, start_time, end_time, extended_time, seller_id, is_deleted FROM items WHERE id = ? FOR UPDATE");
            mysqli_stmt_bind_param($stmt, "i", $b_item_id);
            mysqli_stmt_execute($stmt);
            $item_data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
            mysqli_stmt_close($stmt);

            if (!$item_data) throw new Exception("Lot item not found.");
            if (!empty($item_data['is_deleted'])) throw new Exception("This auction lot has been withdrawn/deleted by the seller.");
            if ((int)$item_data['seller_id'] === $user_id) throw new Exception("Sellers are not permitted to bid on their own listings.");
            
            $now = time();
            $effective_end = (!empty($item_data['extended_time']) && $item_data['extended_time'] != '0000-00-00 00:00:00' && strtotime($item_data['extended_time']) > strtotime($item_data['end_time']))
                ? $item_data['extended_time']
                : $item_data['end_time'];
            $end_ts = strtotime($effective_end);

            if ($item_data['status'] !== 'active' || $now > $end_ts) {
                throw new Exception("This auction is closed or has expired.");
            }

            $last_bid_stmt = mysqli_prepare($conn, "SELECT user_id FROM bids WHERE item_id = ? ORDER BY bid_amount DESC LIMIT 1");
            mysqli_stmt_bind_param($last_bid_stmt, "i", $b_item_id);
            mysqli_stmt_execute($last_bid_stmt);
            $last_bid_res = mysqli_stmt_get_result($last_bid_stmt);
            if ($last_row = mysqli_fetch_assoc($last_bid_res)) {
                if ((int)$last_row['user_id'] === $user_id) {
                    throw new Exception("You are already the highest bidder on this lot.");
                }
            }
            mysqli_stmt_close($last_bid_stmt);

            $base_price = max((float)$item_data['current_price'], (float)$item_data['starting_price']);
            $min_inc = (float)($item_data['min_increment'] > 0 ? $item_data['min_increment'] : 100.00);
            $min_required = $base_price + $min_inc;

            if ($b_amount < $min_required) {
                throw new Exception("Bid must be at least Rs. " . number_format($min_required, 2));
            }

            $u_stmt = mysqli_prepare($conn, "SELECT name, email FROM users WHERE id = ?");
            mysqli_stmt_bind_param($u_stmt, "i", $user_id);
            mysqli_stmt_execute($u_stmt);
            $u_row = mysqli_fetch_assoc(mysqli_stmt_get_result($u_stmt));
            mysqli_stmt_close($u_stmt);

            $u_name = $u_row['name'] ?? ($_SESSION['name'] ?? 'Buyer');
            $u_email = $u_row['email'] ?? ($_SESSION['email'] ?? '');
            $ip_addr = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';

            $ins_bid = mysqli_prepare($conn, "INSERT INTO bids (item_id, user_id, bidder_name, bidder_email, bid_amount, bid_status, ip_address, user_agent, created_at, status) VALUES (?, ?, ?, ?, ?, 'active', ?, ?, NOW(), 'active')");
            mysqli_stmt_bind_param($ins_bid, "iissdss", $b_item_id, $user_id, $u_name, $u_email, $b_amount, $ip_addr, $ua);
            mysqli_stmt_execute($ins_bid);
            mysqli_stmt_close($ins_bid);

            // Anti-sniping: extend end_time and extended_time together
            $extended = false;
            $remaining_secs = $end_ts - $now;
            if ($remaining_secs <= 180 && $remaining_secs > 0) {
                $extend_stmt = mysqli_prepare($conn, "UPDATE items SET end_time = DATE_ADD(GREATEST(end_time, NOW()), INTERVAL 3 MINUTE), extended_time = NOW() WHERE id = ?");
                if ($extend_stmt) {
                    mysqli_stmt_bind_param($extend_stmt, "i", $b_item_id);
                    mysqli_stmt_execute($extend_stmt);
                    mysqli_stmt_close($extend_stmt);
                }
                $extended = true;
            }

            $up_item = mysqli_prepare($conn, "UPDATE items SET current_price = ?, bid_count = bid_count + 1 WHERE id = ?");
            mysqli_stmt_bind_param($up_item, "di", $b_amount, $b_item_id);
            mysqli_stmt_execute($up_item);
            mysqli_stmt_close($up_item);

            mysqli_commit($conn);

            if (!empty($last_row['user_id']) && (int)$last_row['user_id'] !== $user_id) {
                sendNotification($conn, $last_row['user_id'], 'outbid', 'You Have Been Outbid!', "A higher bid of Rs. " . number_format($b_amount, 2) . " was placed on lot #$b_item_id", "live_auction.php?id=$b_item_id");
            }
            sendNotification($conn, $item_data['seller_id'], 'bid', 'New Bid Received', "New bid of Rs. " . number_format($b_amount, 2) . " placed on your listing.", "live_auction.php?id=$b_item_id");
            runAutoBids($conn, $b_item_id, $b_amount, $user_id);

            echo json_encode([
                'success' => true,
                'message' => 'Your bid of Rs. ' . number_format($b_amount, 2) . ' was placed successfully!' . ($extended ? ' (Anti-Sniping: Extended by 3 mins)' : ''),
                'extended' => $extended
            ]);
            exit();

        } catch (Exception $e) {
            mysqli_rollback($conn);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
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

        $chk = mysqli_prepare($conn, "SELECT id FROM wishlist WHERE user_id = ? AND item_id = ?");
        mysqli_stmt_bind_param($chk, "ii", $user_id, $w_item_id);
        mysqli_stmt_execute($chk);
        $w_res = mysqli_stmt_get_result($chk);

        if ($w_row = mysqli_fetch_assoc($w_res)) {
            $del = mysqli_prepare($conn, "DELETE FROM wishlist WHERE id = ?");
            mysqli_stmt_bind_param($del, "i", $w_row['id']);
            mysqli_stmt_execute($del);
            mysqli_stmt_close($del);
            $in_watchlist = false;
        } else {
            $ins = mysqli_prepare($conn, "INSERT INTO wishlist (user_id, item_id, created_at) VALUES (?, ?, NOW())");
            mysqli_stmt_bind_param($ins, "ii", $user_id, $w_item_id);
            mysqli_stmt_execute($ins);
            mysqli_stmt_close($ins);
            $in_watchlist = true;
        }
        mysqli_stmt_close($chk);

        $cnt_stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS c FROM wishlist WHERE item_id = ?");
        mysqli_stmt_bind_param($cnt_stmt, "i", $w_item_id);
        mysqli_stmt_execute($cnt_stmt);
        $w_count = mysqli_fetch_assoc(mysqli_stmt_get_result($cnt_stmt))['c'] ?? 0;
        mysqli_stmt_close($cnt_stmt);

        echo json_encode(['success' => true, 'in_watchlist' => $in_watchlist, 'count' => (int)$w_count]);
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

        $it_stmt = mysqli_prepare($conn, "SELECT current_price, starting_price, min_increment, seller_id, status FROM items WHERE id = ?");
        mysqli_stmt_bind_param($it_stmt, "i", $ab_item_id);
        mysqli_stmt_execute($it_stmt);
        $it_data = mysqli_fetch_assoc(mysqli_stmt_get_result($it_stmt));
        mysqli_stmt_close($it_stmt);

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

        $ab_upsert = mysqli_prepare($conn, "
            INSERT INTO auto_bids (item_id, user_id, max_bid_amount, is_active, created_at, updated_at) 
            VALUES (?, ?, ?, 1, NOW(), NOW())
            ON DUPLICATE KEY UPDATE max_bid_amount = VALUES(max_bid_amount), is_active = 1, updated_at = NOW()
        ");
        mysqli_stmt_bind_param($ab_upsert, "iid", $ab_item_id, $user_id, $max_bid);
        mysqli_stmt_execute($ab_upsert);
        mysqli_stmt_close($ab_upsert);

        runAutoBids($conn, $ab_item_id, $base, 0);

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

        mysqli_begin_transaction($conn);
        try {
            $stmt = mysqli_prepare($conn, "SELECT title, buy_it_now_price, seller_id, status, is_deleted FROM items WHERE id = ? FOR UPDATE");
            mysqli_stmt_bind_param($stmt, "i", $bin_id);
            mysqli_stmt_execute($stmt);
            $item = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
            mysqli_stmt_close($stmt);

            if (!$item || $item['status'] !== 'active') throw new Exception("Auction is closed.");
            if (!empty($item['is_deleted'])) throw new Exception("This auction lot has been withdrawn/deleted by the seller.");
            if (empty($item['buy_it_now_price']) || (float)$item['buy_it_now_price'] <= 0) throw new Exception("Buy It Now not enabled for this lot.");
            if ((int)$item['seller_id'] === $user_id) throw new Exception("You cannot purchase your own item.");

            $bin_price = (float)$item['buy_it_now_price'];

            // Schema compliant ENUM: 'sold'
            $up = mysqli_prepare($conn, "UPDATE items SET status = 'sold', winner_id = ?, current_price = ? WHERE id = ?");
            mysqli_stmt_bind_param($up, "idi", $user_id, $bin_price, $bin_id);
            mysqli_stmt_execute($up);
            mysqli_stmt_close($up);

            // Schema compliant transactions table insertion
            try {
                $tx = mysqli_prepare($conn, "INSERT INTO transactions (user_id, type, amount, item_id, description, status, created_at) VALUES (?, 'winning_payment', ?, ?, 'Purchased via Buy It Now', 'completed', NOW())");
                if ($tx) {
                    mysqli_stmt_bind_param($tx, "idis", $user_id, $bin_price, $bin_id, $bin_price);
                    mysqli_stmt_execute($tx);
                    mysqli_stmt_close($tx);
                }
            } catch (Exception $txe) {}

            mysqli_commit($conn);

            sendNotification($conn, $user_id, 'won', 'Buy It Now Confirmed!', "You successfully bought '" . htmlspecialchars($item['title']) . "' for Rs. " . number_format($bin_price, 2), "live_auction.php?id=$bin_id");
            sendNotification($conn, $item['seller_id'], 'sold', 'Instant Purchase Completed', "Your item '" . htmlspecialchars($item['title']) . "' was purchased via Buy It Now.", "live_auction.php?id=$bin_id");

            echo json_encode(['success' => true, 'message' => 'Congratulations! Purchase completed via Buy It Now.']);
            exit();

        } catch (Exception $e) {
            mysqli_rollback($conn);
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

        $m_stmt = mysqli_prepare($conn, "
            SELECT i.*, u.name AS seller_name,
            (SELECT COUNT(*) FROM wishlist WHERE item_id = i.id) AS watcher_count
            FROM items i 
            LEFT JOIN users u ON i.seller_id = u.id 
            WHERE i.id = ?
        ");
        mysqli_stmt_bind_param($m_stmt, "i", $mid);
        mysqli_stmt_execute($m_stmt);
        $m_item = mysqli_fetch_assoc(mysqli_stmt_get_result($m_stmt));
        mysqli_stmt_close($m_stmt);

        if (!$m_item) {
            echo json_encode(['success' => false, 'error' => 'Lot not found']);
            exit();
        }

        $m_price = max((float)$m_item['current_price'], (float)$m_item['starting_price']);
        $m_inc = (float)($m_item['min_increment'] > 0 ? $m_item['min_increment'] : 100.00);
        $m_next = $m_price + $m_inc;
        $m_reserve_met = (empty($m_item['reserve_price']) || $m_price >= (float)$m_item['reserve_price']);
        $m_dist = getDistanceKm($user_lat, $user_lng, $m_item['location_lat'] ?? 0, $m_item['location_lng'] ?? 0);
        $m_images = parseItemImages($conn, (int)$m_item['id'], $m_item['images'] ?? '', $m_item['featured_image'] ?? '');

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
            'seller_name' => htmlspecialchars($m_item['seller_name'] ?? 'Authorized Vendor'),
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

if ($is_authenticated && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $post_item_id = (int)($_POST['item_id'] ?? 0);

    if ($post_item_id > 0 && (isset($_POST['accept_bid']) || isset($_POST['reject_bid']) || isset($_POST['delete_item']))) {
        // Fetch item to verify ownership and existing details
        $chk_stmt = mysqli_prepare($conn, "SELECT id, seller_id, title, current_price, status, featured_image, images FROM items WHERE id = ?");
        mysqli_stmt_bind_param($chk_stmt, "i", $post_item_id);
        mysqli_stmt_execute($chk_stmt);
        $res = mysqli_stmt_get_result($chk_stmt);
        $item_to_manage = mysqli_fetch_assoc($res);
        mysqli_stmt_close($chk_stmt);

        if (!$item_to_manage) {
            $seller_message = "Auction lot #$post_item_id not found.";
            $seller_success = false;
        } elseif ($role !== 'admin' && (int)$item_to_manage['seller_id'] !== $user_id) {
            $seller_message = "You are not authorized to manage lot #$post_item_id. Only the owner or an administrator can manage this lot.";
            $seller_success = false;
        } else {
            // User is authorized (owner or admin)
            if (isset($_POST['accept_bid']) && $has_db) {
                // Fetch highest bid
                $bid_stmt = mysqli_prepare($conn, "
                    SELECT b.id, b.user_id, b.bid_amount, u.name, u.email 
                    FROM bids b 
                    JOIN users u ON b.user_id = u.id 
                    WHERE b.item_id = ? 
                    ORDER BY b.bid_amount DESC, b.id ASC 
                    LIMIT 1
                ");
                mysqli_stmt_bind_param($bid_stmt, "i", $post_item_id);
                mysqli_stmt_execute($bid_stmt);
                $bid_res = mysqli_stmt_get_result($bid_stmt);
                if ($top_bid = mysqli_fetch_assoc($bid_res)) {
                    $winner_id = (int)$top_bid['user_id'];
                    $winning_bid_id = (int)$top_bid['id'];
                    $top_amount = (float)$top_bid['bid_amount'];

                    // Mark item as sold
                    $up_item = mysqli_prepare($conn, "UPDATE items SET status = 'sold', winner_id = ?, winning_bid_id = ?, current_price = ? WHERE id = ?");
                    mysqli_stmt_bind_param($up_item, "iidi", $winner_id, $winning_bid_id, $top_amount, $post_item_id);
                    mysqli_stmt_execute($up_item);
                    mysqli_stmt_close($up_item);

                    // Mark winning bid as won/accepted
                    $up_bid = mysqli_prepare($conn, "UPDATE bids SET is_winning = 1, bid_status = 'won', status = 'accepted' WHERE id = ?");
                    mysqli_stmt_bind_param($up_bid, "i", $winning_bid_id);
                    mysqli_stmt_execute($up_bid);
                    mysqli_stmt_close($up_bid);

                    // Mark other bids as lost/outbid
                    $up_other = mysqli_prepare($conn, "UPDATE bids SET is_winning = 0, bid_status = 'lost', status = 'outbid' WHERE item_id = ? AND id != ?");
                    mysqli_stmt_bind_param($up_other, "ii", $post_item_id, $winning_bid_id);
                    mysqli_stmt_execute($up_other);
                    mysqli_stmt_close($up_other);

                    // Send notification to winner and seller
                    $formatted_amount = number_format($top_amount, 2);
                    sendNotification($conn, $winner_id, 'auction', 'Bid Accepted & Won!', "Congratulations! Your top bid of Rs. $formatted_amount on lot #$post_item_id ('" . addslashes($item_to_manage['title']) . "') has been accepted.", "live_auction.php?id=$post_item_id");
                    sendNotification($conn, (int)$item_to_manage['seller_id'], 'auction', 'Top Bid Accepted', "Top bid of Rs. $formatted_amount for lot #$post_item_id was accepted and lot marked as Sold.", "live_auction.php?id=$post_item_id");

                    $seller_success = true;
                    $seller_message = "Top bid of Rs. $formatted_amount accepted successfully! The lot has been awarded and marked as Sold.";
                    $item_id = $post_item_id;
                } else {
                    $seller_success = false;
                    $seller_message = "No bids have been placed on this item yet. Cannot accept top bid.";
                }
                mysqli_stmt_close($bid_stmt);
            }

            if (isset($_POST['reject_bid']) && $has_db) {
                // Mark item as ended
                $up_item = mysqli_prepare($conn, "UPDATE items SET status = 'ended' WHERE id = ?");
                mysqli_stmt_bind_param($up_item, "i", $post_item_id);
                mysqli_stmt_execute($up_item);
                mysqli_stmt_close($up_item);

                // Reject all bids
                $up_bids = mysqli_prepare($conn, "UPDATE bids SET is_winning = 0, bid_status = 'cancelled', status = 'rejected' WHERE item_id = ?");
                mysqli_stmt_bind_param($up_bids, "i", $post_item_id);
                mysqli_stmt_execute($up_bids);
                mysqli_stmt_close($up_bids);

                sendNotification($conn, (int)$item_to_manage['seller_id'], 'auction', 'Auction Closed', "Lot #$post_item_id ('" . addslashes($item_to_manage['title']) . "') was closed and bids were rejected.", "live_auction.php?id=$post_item_id");

                $seller_success = true;
                $seller_message = "Auction lot #$post_item_id has been closed and pending bids have been rejected.";
                $item_id = $post_item_id;
            }

            if (isset($_POST['delete_item']) && $has_db) {
                // Soft-delete: marks lot as cancelled & is_deleted = 1 with timestamp and user audit
                $del_stmt = mysqli_prepare($conn, "UPDATE items SET is_deleted = 1, status = 'cancelled', deleted_at = NOW(), deleted_by = ? WHERE id = ?");
                mysqli_stmt_bind_param($del_stmt, "ii", $user_id, $post_item_id);
                mysqli_stmt_execute($del_stmt);
                mysqli_stmt_close($del_stmt);

                // Cancel all bids for this item
                $del_bids = mysqli_prepare($conn, "UPDATE bids SET bid_status = 'cancelled', status = 'cancelled' WHERE item_id = ?");
                mysqli_stmt_bind_param($del_bids, "i", $post_item_id);
                mysqli_stmt_execute($del_bids);
                mysqli_stmt_close($del_bids);

                // Redirect cleanly
                header("Location: live_auction.php?deleted=1&lot_id=" . $post_item_id);
                exit();
            }
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
        $item_stmt = mysqli_prepare($conn, "SELECT * FROM `items` WHERE `id` = ?");
        mysqli_stmt_bind_param($item_stmt, "i", $item_id);
        mysqli_stmt_execute($item_stmt);
        $item_query = mysqli_stmt_get_result($item_stmt);
    } else {
        $item_query = mysqli_query($conn, "SELECT * FROM `items` WHERE `status` = 'active' AND (is_deleted = 0 OR is_deleted IS NULL) ORDER BY `created_at` DESC LIMIT 1");
    }

    if ($item_query) {
        $current_item = mysqli_fetch_assoc($item_query);
        if ($current_item && !empty($current_item['seller_id'])) {
            $seller_id_fetched = (int)$current_item['seller_id'];
            $seller_stmt = mysqli_prepare($conn, "SELECT name FROM users WHERE id = ?");
            mysqli_stmt_bind_param($seller_stmt, "i", $seller_id_fetched);
            mysqli_stmt_execute($seller_stmt);
            $seller_q = mysqli_stmt_get_result($seller_stmt);
            if ($seller_q && $s_row = mysqli_fetch_assoc($seller_q)) {
                $current_item['seller_name'] = $s_row['name'];
            }
            mysqli_stmt_close($seller_stmt);
        }
    }
    if ($current_item) {
        $history_stmt = mysqli_prepare($conn, "SELECT b.*, u.name AS bidder_name FROM bids b JOIN users u ON b.user_id = u.id WHERE b.item_id = ? ORDER BY b.bid_amount DESC LIMIT 5");
        mysqli_stmt_bind_param($history_stmt, "i", $current_item['id']);
        mysqli_stmt_execute($history_stmt);
        $bids_history_res = mysqli_stmt_get_result($history_stmt);
    }

    $is_watched = false;
    $watcher_count = 0;
    if ($current_item) {
        if ($is_authenticated) {
            $w_chk = mysqli_prepare($conn, "SELECT id FROM wishlist WHERE user_id = ? AND item_id = ?");
            mysqli_stmt_bind_param($w_chk, "ii", $user_id, $current_item['id']);
            mysqli_stmt_execute($w_chk);
            if (mysqli_stmt_get_result($w_chk)->num_rows > 0) $is_watched = true;
            mysqli_stmt_close($w_chk);
        }
        $w_cnt_stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS c FROM wishlist WHERE item_id = ?");
        if ($w_cnt_stmt) {
            $cid = (int)$current_item['id'];
            mysqli_stmt_bind_param($w_cnt_stmt, "i", $cid);
            mysqli_stmt_execute($w_cnt_stmt);
            $w_cnt_res = mysqli_stmt_get_result($w_cnt_stmt);
            if ($w_cnt_res) $watcher_count = (int)mysqli_fetch_assoc($w_cnt_res)['c'];
            mysqli_stmt_close($w_cnt_stmt);
        }
    }

    $res = mysqli_query($conn, "SELECT COUNT(*) AS c FROM items WHERE status = 'active' AND (is_deleted = 0 OR is_deleted IS NULL)");
    if ($res) { $totalItems = (int)mysqli_fetch_assoc($res)['c']; }

    $res = mysqli_query($conn, "SELECT COUNT(*) AS c FROM bids");
    if ($res) { $totalBidsOverall = (int)mysqli_fetch_assoc($res)['c']; }

    $sql_query = "SELECT * FROM `items` WHERE `status` = 'active' AND (is_deleted = 0 OR is_deleted IS NULL)";
    $params = [];
    $types = "";

    if ($selected_category > 0) {
        $sql_query .= " AND category_id = ?";
        $params[] = $selected_category;
        $types .= "i";
    }
    if (!empty($search_query)) {
        $sql_query .= " AND (title LIKE ? OR description LIKE ?)";
        $like_term = "%" . $search_query . "%";
        $params[] = $like_term;
        $params[] = $like_term;
        $types .= "ss";
    }
    if ($min_price > 0) {
        $sql_query .= " AND current_price >= ?";
        $params[] = $min_price;
        $types .= "d";
    }
    if ($max_price > 0) {
        $sql_query .= " AND current_price <= ?";
        $params[] = $max_price;
        $types .= "d";
    }
    if (!empty($selected_condition) && in_array($selected_condition, ['new', 'used', 'refurbished'])) {
        $sql_query .= " AND `condition` = ?";
        $params[] = $selected_condition;
        $types .= "s";
    }
    if (!empty($selected_location)) {
        $sql_query .= " AND location = ?";
        $params[] = $selected_location;
        $types .= "s";
    }

    if ($sort_by === 'price_low') {
        $sql_query .= " ORDER BY current_price ASC";
    } elseif ($sort_by === 'price_high') {
        $sql_query .= " ORDER BY current_price DESC";
    } elseif ($sort_by === 'ending_soon') {
        $sql_query .= " ORDER BY end_time ASC";
    } elseif ($sort_by === 'most_bids') {
        $sql_query .= " ORDER BY bid_count DESC";
    } else {
        $sql_query .= " ORDER BY created_at DESC";
    }

    if (!$show_all) {
        $sql_query .= " LIMIT 15";
    }

    $stmt_all = mysqli_prepare($conn, $sql_query);
    if (!empty($params)) {
        mysqli_stmt_bind_param($stmt_all, $types, ...$params);
    }
    mysqli_stmt_execute($stmt_all);
    $all_products_res = mysqli_stmt_get_result($stmt_all);

    $categories_res = mysqli_query($conn, "SELECT id, name, slug FROM categories WHERE status = 1 ORDER BY sort_order ASC");
}

$active_filters = [];
if ($selected_category > 0 && $has_db) {
    $cat_stmt = mysqli_prepare($conn, "SELECT name FROM categories WHERE id = ? LIMIT 1");
    mysqli_stmt_bind_param($cat_stmt, "i", $selected_category);
    mysqli_stmt_execute($cat_stmt);
    $cat_name_res = mysqli_stmt_get_result($cat_stmt);
    if ($cat_name_res && $cat_row = mysqli_fetch_assoc($cat_name_res)) {
        $active_filters[] = ['key' => 'category_id', 'label' => 'Category', 'value' => $cat_row['name']];
    }
    mysqli_stmt_close($cat_stmt);
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

// Fetch comment counts grouped by item_id
$item_comment_counts = [];
if ($has_db) {
    $cc_res = mysqli_query($conn, "SELECT item_id, COUNT(id) AS total_comments FROM item_comments GROUP BY item_id");
    if ($cc_res) {
        while ($cc_row = mysqli_fetch_assoc($cc_res)) {
            $item_comment_counts[(string)$cc_row['item_id']] = (int)$cc_row['total_comments'];
        }
    }
}

$catalog_list = [];
if ($all_products_res) {
    mysqli_data_seek($all_products_res, 0);
    while ($p = mysqli_fetch_assoc($all_products_res)) {
        $p['dist_km'] = getDistanceKm($user_lat, $user_lng, $p['location_lat'] ?? 0, $p['location_lng'] ?? 0);
        if ($selected_radius > 0 && ($p['dist_km'] === null || $p['dist_km'] > $selected_radius)) {
            continue;
        }
        $p['comment_count'] = $item_comment_counts[(string)$p['id']] ?? 0;
        $catalog_list[] = $p;
    }
}

// Current item comments for live stream
$current_item_comment_count = $current_item ? ($item_comment_counts[(string)$current_item['id']] ?? 0) : 0;
$current_item_comments = [];
if ($has_db && $current_item && !empty($current_item['id'])) {
    $c_stmt = mysqli_prepare($conn, "
        SELECT c.*, u.name AS user_name, u.username, u.avatar, u.role AS user_role
        FROM item_comments c
        LEFT JOIN users u ON c.user_id = u.id
        WHERE c.item_id = ?
        ORDER BY c.created_at ASC, c.id ASC
        LIMIT 100
    ");
    if ($c_stmt) {
        $c_iid = (string)$current_item['id'];
        mysqli_stmt_bind_param($c_stmt, "s", $c_iid);
        mysqli_stmt_execute($c_stmt);
        $c_res = mysqli_stmt_get_result($c_stmt);
        while ($c_row = mysqli_fetch_assoc($c_res)) {
            $current_item_comments[] = $c_row;
        }
        mysqli_stmt_close($c_stmt);
    }
}

if (!function_exists('formatCommentTime')) {
    function formatCommentTime($datetime) {
        if (empty($datetime)) return 'Just now';
        $time = is_numeric($datetime) ? (int)$datetime : strtotime($datetime);
        if (!$time) return 'Just now';
        $diff = time() - $time;
        if ($diff < 5) return 'Just now';
        if ($diff < 60) return $diff . 's ago';
        if ($diff < 3600) return floor($diff / 60) . 'm ago';
        if ($diff < 86400) return floor($diff / 3600) . 'h ago';
        if ($diff < 604800) return floor($diff / 86400) . 'd ago';
        return date('M d, Y', $time);
    }
}

// Current logged-in user details for nested comment composer
$current_user_avatar = '';
$current_user_name = $_SESSION['name'] ?? 'Bidder';
if ($is_authenticated && $has_db) {
    if (!empty($_SESSION['avatar'])) {
        $current_user_avatar = cleanImagePath($_SESSION['avatar']);
    } else {
        $u_stmt = mysqli_prepare($conn, "SELECT name, avatar FROM users WHERE id = ?");
        if ($u_stmt) {
            mysqli_stmt_bind_param($u_stmt, "i", $user_id);
            mysqli_stmt_execute($u_stmt);
            $u_row = mysqli_fetch_assoc(mysqli_stmt_get_result($u_stmt));
            if ($u_row) {
                if (!empty($u_row['name'])) $current_user_name = $u_row['name'];
                if (!empty($u_row['avatar'])) $current_user_avatar = cleanImagePath($u_row['avatar']);
            }
            mysqli_stmt_close($u_stmt);
        }
    }
}
$current_user_initial = strtoupper(substr($current_user_name, 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live Auction Arena - Geo Auction System</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: #f8fafc; color: #1e293b; overflow-x: hidden; }
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
        .scrollbar-thin::-webkit-scrollbar { width: 5px; height: 5px; }
        .scrollbar-thin::-webkit-scrollbar-track { background: #f1f5f9; border-radius: 9999px; }
        .scrollbar-thin::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 9999px; }
        .scrollbar-thin::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
        @keyframes commentEntryFade { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: translateY(0); } }
        .animate-comment-entry { animation: commentEntryFade 0.25s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
    </style>
</head>
<body class="bg-slate-50 min-h-screen flex flex-col">

<?php if (!$header_included): ?>
    <header class="bg-slate-900 text-white border-b border-slate-800 sticky top-0 z-40">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 h-14 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <a href="dashboard.php" class="flex items-center gap-2 text-white font-bold text-sm tracking-tight">
                    <span class="w-8 h-8 rounded-lg bg-emerald-600 flex items-center justify-center text-white font-black text-xs">GA</span>
                    <span>Geo Auction System</span>
                </a>
                <span class="text-[11px] text-slate-400 bg-slate-800 px-2 py-0.5 rounded">Live Arena</span>
            </div>
            <div class="flex items-center gap-3 text-xs">
                <div class="flex items-center bg-slate-800 rounded-lg p-0.5 border border-slate-700">
                    <a href="?switch_role=buyer<?php echo $item_id ? '&id='.$item_id : ''; ?>" class="px-2.5 py-1 rounded-md font-semibold transition <?php echo $role === 'buyer' ? 'bg-emerald-600 text-white' : 'text-slate-400 hover:text-white'; ?>">Buyer</a>
                    <a href="?switch_role=seller<?php echo $item_id ? '&id='.$item_id : ''; ?>" class="px-2.5 py-1 rounded-md font-semibold transition <?php echo $role === 'seller' ? 'bg-emerald-600 text-white' : 'text-slate-400 hover:text-white'; ?>">Seller</a>
                    <a href="?switch_role=admin<?php echo $item_id ? '&id='.$item_id : ''; ?>" class="px-2.5 py-1 rounded-md font-semibold transition <?php echo $role === 'admin' ? 'bg-emerald-600 text-white' : 'text-slate-400 hover:text-white'; ?>">Admin</a>
                </div>
                <a href="dashboard.php" class="text-slate-300 hover:text-white font-medium">Dashboard</a>
                <a href="wallet.php" class="text-slate-300 hover:text-white font-medium">Wallet</a>
            </div>
        </div>
    </header>
<?php endif; ?>

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
        
        $gallery_images = parseItemImages($conn, (int)$current_item['id'], $current_item['images'] ?? '', $current_item['featured_image'] ?? '');
        $main_img = $gallery_images[0];
        $is_owner_seller = ($user_id > 0 && (int)($current_item['seller_id'] ?? 0) === $user_id) || $role === 'admin';
        $dist_km = getDistanceKm($user_lat, $user_lng, $current_item['location_lat'] ?? 0, $current_item['location_lng'] ?? 0);
        $effective_item_end = (!empty($current_item['extended_time']) && $current_item['extended_time'] != '0000-00-00 00:00:00' && strtotime($current_item['extended_time']) > strtotime($current_item['end_time']))
            ? $current_item['extended_time']
            : $current_item['end_time'];
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
    <section class="bg-white border border-slate-200 rounded-3xl overflow-hidden shadow-sm p-4 sm:p-6 mb-6 space-y-6">
        <!-- 1. BAYUT-INSPIRED PROPERTY IMAGE GALLERY & SLIDER -->
        <?php include __DIR__ . '/includes/components/property_gallery.php'; ?>

        <!-- 2. PROPERTY DETAILS & LIVE BIDDING CONSOLE (2-COLUMN ARCHITECTURE) -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 sm:gap-8 pt-2 border-t border-slate-100">
            
            <!-- LEFT COLUMN: PROPERTY INFORMATION, SPECS & BID FEED (7 COLS) -->
            <div class="lg:col-span-7 space-y-6">
                <div>
                    <div class="flex items-center gap-2 flex-wrap mb-2">
                        <span class="inline-block text-xs font-extrabold text-emerald-800 uppercase tracking-wider bg-emerald-50 border border-emerald-200 px-3 py-1 rounded-lg">
                            Lot #<?php echo htmlspecialchars($current_item['auction_code'] ?? ('LOT-'.$current_item['id'])); ?>
                        </span>
                        <span id="reserveStatusBadge" class="text-xs font-bold px-3 py-1 rounded-lg border <?php echo $reserve_met ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-amber-50 text-amber-700 border-amber-200'; ?>">
                            <?php echo $reserve_met ? 'Reserve Price Met' : 'Reserve Price Not Met'; ?>
                        </span>
                        <span class="text-xs text-slate-500 font-semibold bg-slate-100 px-2.5 py-1 rounded-lg border border-slate-200 flex items-center gap-1.5">
                            <i class="fa-solid fa-eye text-slate-400"></i>
                            <span id="watcherCountDisplay"><?php echo $watcher_count; ?></span> watchers
                        </span>
                    </div>

                    <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-slate-900 mt-1 <?php echo $is_current_deleted ? 'line-through text-rose-600' : ''; ?>">
                        <?php echo htmlspecialchars($current_item['title']); ?>
                    </h1>

                    <p class="text-xs text-slate-500 mt-2 flex items-center gap-2 flex-wrap">
                        <span class="inline-flex items-center gap-1 text-slate-700 font-medium">
                            <i class="fa-solid fa-location-dot text-emerald-600"></i>
                            <?php echo htmlspecialchars($current_item['location_area'] ?? ($current_item['location_city'] ?? 'Hyderabad')); ?>
                        </span>
                        <span class="text-slate-300">&bull;</span>
                        <span>Seller: <strong class="text-slate-800"><?php echo htmlspecialchars($current_item['seller_name'] ?? 'Authorized Vendor'); ?></strong></span>
                    </p>
                </div>

                <!-- Description Card -->
                <div class="bg-slate-50/70 border border-slate-200/80 rounded-2xl p-5">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500 mb-2 flex items-center gap-1.5">
                        <i class="fa-solid fa-align-left text-xs"></i> Property Overview & Auction Details
                    </h3>
                    <p class="text-sm text-slate-700 leading-relaxed whitespace-pre-line">
                        <?php echo htmlspecialchars($current_item['description']); ?>
                    </p>
                </div>

                <!-- Property Specifications Badges -->
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
                    <div class="bg-white p-3 rounded-xl border border-slate-200 shadow-2xs">
                        <span class="text-slate-400 block font-medium">Lot Identifier</span>
                        <p class="font-extrabold text-slate-900 mt-0.5">#<?php echo htmlspecialchars($current_item['auction_code'] ?? ('LOT-'.$current_item['id'])); ?></p>
                    </div>
                    <div class="bg-white p-3 rounded-xl border border-slate-200 shadow-2xs">
                        <span class="text-slate-400 block font-medium">Opening Bid</span>
                        <p class="font-extrabold text-slate-900 mt-0.5">Rs. <?php echo number_format((float)$current_item['starting_price'], 0); ?></p>
                    </div>
                    <div class="bg-white p-3 rounded-xl border border-slate-200 shadow-2xs">
                        <span class="text-slate-400 block font-medium">Min Increment</span>
                        <p class="font-extrabold text-emerald-700 mt-0.5">Rs. <?php echo number_format($min_inc, 0); ?></p>
                    </div>
                    <div class="bg-white p-3 rounded-xl border border-slate-200 shadow-2xs">
                        <span class="text-slate-400 block font-medium">Verified Seller</span>
                        <p class="font-extrabold text-slate-900 mt-0.5 truncate"><?php echo htmlspecialchars($current_item['seller_name'] ?? 'Authorized Vendor'); ?></p>
                    </div>
                </div>

                <!-- Live Bid Activity Feed -->
                <div>
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500 mb-2.5 flex items-center justify-between">
                        <span class="flex items-center gap-1.5">
                            <i class="fa-solid fa-clock-rotate-left text-xs"></i> Live Bid Activity Feed
                        </span>
                        <span class="text-[11px] text-slate-400 font-normal">Real-time updates</span>
                    </h3>
                    <div class="space-y-2 max-h-52 overflow-y-auto pr-1" id="bidHistoryFeed">
                        <?php if ($bids_history_res && mysqli_num_rows($bids_history_res) > 0): ?>
                            <?php while($bid = mysqli_fetch_assoc($bids_history_res)): 
                                $parts = explode(' ', trim($bid['bidder_name']));
                                $masked_name = htmlspecialchars($parts[0]) . (count($parts) > 1 ? ' ' . substr($parts[1], 0, 1) . '***' : ' ***');
                            ?>
                                <div class="flex items-center justify-between p-3 rounded-xl bg-slate-50 border border-slate-200/80 text-xs hover:bg-white transition">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-full bg-emerald-100 text-emerald-800 flex items-center justify-center font-bold text-xs shadow-2xs">
                                            <?php echo strtoupper(substr($bid['bidder_name'] ?? 'B', 0, 1)); ?>
                                        </div>
                                        <div>
                                            <span class="font-bold text-slate-900"><?php echo $masked_name; ?></span>
                                            <span class="text-[10px] text-slate-400 block"><?php echo date('M d, h:i A', strtotime($bid['created_at'])); ?></span>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <span class="font-extrabold text-emerald-700 text-sm">Rs. <?php echo number_format($bid['bid_amount'], 2); ?></span>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <p class="text-xs text-slate-400 italic py-3 text-center bg-slate-50 rounded-xl border border-dashed border-slate-200" id="noBidsNotice">
                                No bids recorded yet. Be the first to place an opening bid!
                            </p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Lot Discussion & Comments UI Component -->
                <?php
                if (file_exists(__DIR__ . '/includes/components/item_comments.php')) {
                    include __DIR__ . '/includes/components/item_comments.php';
                }
                ?>

            </div>

            <!-- RIGHT COLUMN: LIVE BIDDING TERMINAL & PRICING CONSOLE (5 COLS) -->
            <div class="lg:col-span-5 space-y-4">
                
                <!-- Main Price & Countdown Card -->
                <div class="bg-gradient-to-br from-slate-900 to-slate-950 text-white rounded-3xl p-6 shadow-xl border border-slate-800 space-y-5">
                    
                    <div class="flex items-start justify-between gap-3 border-b border-slate-800 pb-4">
                        <div>
                            <span class="text-xs text-slate-400 font-semibold uppercase tracking-wider block">Current Highest Bid</span>
                            <div class="text-3xl font-black text-emerald-400 tracking-tight mt-0.5">
                                Rs. <span id="currentPriceDisplay"><?php echo number_format($display_price, 2); ?></span>
                            </div>
                        </div>
                        <div class="text-right">
                            <span class="text-[11px] text-slate-400 font-medium block">Min Increment</span>
                            <span class="text-xs font-bold text-slate-200 block mt-0.5">Rs. <?php echo number_format($min_inc, 0); ?></span>
                        </div>
                    </div>

                    <!-- 3-Stats Grid -->
                    <div class="grid grid-cols-3 gap-2 text-center bg-slate-800/60 rounded-2xl p-3 border border-slate-700/50">
                        <div>
                            <span class="text-[10px] font-semibold text-slate-400 uppercase tracking-wider block">Next Allowed</span>
                            <strong class="text-xs font-bold text-slate-100 mt-0.5 block truncate" id="nextMinBidDisplay">Rs. <?php echo number_format($next_min_bid, 2); ?></strong>
                        </div>
                        <div class="border-x border-slate-700/60">
                            <span class="text-[10px] font-semibold text-slate-400 uppercase tracking-wider block">Bids Placed</span>
                            <strong class="text-sm font-black text-slate-100 mt-0.5 block" id="bidCountDisplay"><?php echo (int)($current_item['bid_count'] ?? 0); ?></strong>
                        </div>
                        <div>
                            <span class="text-[10px] font-semibold text-slate-400 uppercase tracking-wider block">Closing In</span>
                            <strong class="text-xs font-bold text-rose-400 mt-0.5 block" id="timerDisplay">Calculating...</strong>
                        </div>
                    </div>

                    <!-- Bidding Form / Status Area -->
                    <?php if ($is_owner_seller): ?>
                        <div class="p-5 rounded-2xl bg-slate-800/90 border border-slate-700/80 shadow-md space-y-4">
                            <div class="flex flex-wrap items-center justify-between gap-2 pb-3 border-b border-slate-700/60">
                                <div>
                                    <span class="text-xs font-bold text-amber-400 uppercase tracking-wider flex items-center gap-1.5">
                                        <i class="fa-solid fa-crown text-amber-400"></i> Seller Lot Management
                                    </span>
                                    <p class="text-[11px] text-slate-400 mt-0.5">Control panel for lot #<?php echo $current_item['id']; ?></p>
                                </div>
                                <div class="flex items-center gap-2">
                                    <?php if ($is_current_deleted): ?>
                                        <span class="px-3 py-1 rounded-full text-[11px] font-bold bg-rose-950/80 text-rose-300 border border-rose-800 flex items-center gap-1.5">
                                            <i class="fa-solid fa-ban text-rose-400"></i> Soft Deleted
                                        </span>
                                    <?php elseif ($current_item['status'] === 'sold'): ?>
                                        <span class="px-3 py-1 rounded-full text-[11px] font-bold bg-emerald-950/80 text-emerald-300 border border-emerald-800 flex items-center gap-1.5">
                                            <i class="fa-solid fa-check-circle text-emerald-400"></i> Sold & Closed
                                        </span>
                                    <?php elseif ($current_item['status'] === 'ended'): ?>
                                        <span class="px-3 py-1 rounded-full text-[11px] font-bold bg-slate-700 text-slate-300 border border-slate-600 flex items-center gap-1.5">
                                            <i class="fa-solid fa-lock text-slate-400"></i> Closed / Ended
                                        </span>
                                    <?php else: ?>
                                        <span class="px-3 py-1 rounded-full text-[11px] font-bold bg-amber-950/80 text-amber-300 border border-amber-800 flex items-center gap-1.5">
                                            <span class="w-2 h-2 rounded-full bg-amber-400 animate-pulse"></span> Active Live
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <?php if (!empty($seller_message)): ?>
                                <div class="p-3.5 rounded-xl text-xs font-semibold flex items-center gap-2.5 <?php echo $seller_success ? 'bg-emerald-950/60 text-emerald-300 border border-emerald-800' : 'bg-rose-950/60 text-rose-300 border border-rose-800'; ?>">
                                    <i class="fa-solid <?php echo $seller_success ? 'fa-circle-check text-emerald-400 text-base' : 'fa-circle-exclamation text-rose-400 text-base'; ?> shrink-0"></i>
                                    <div><?php echo htmlspecialchars($seller_message); ?></div>
                                </div>
                            <?php endif; ?>

                            <form method="POST" action="?id=<?php echo $current_item['id']; ?>" class="space-y-3">
                                <input type="hidden" name="item_id" value="<?php echo $current_item['id']; ?>">
                                
                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5 w-full">
                                    <!-- Accept Top Bid Button -->
                                    <button type="submit" name="accept_bid" 
                                        <?php echo ($current_item['status'] === 'sold' || $is_current_deleted) ? 'disabled' : ''; ?>
                                        onclick="return confirm('Accept the highest bid and mark this lot as sold?');" 
                                        class="w-full h-11 px-3 py-2.5 rounded-xl font-bold text-xs tracking-wider uppercase transition-all duration-200 flex items-center justify-center gap-2 shadow-xs cursor-pointer <?php echo ($current_item['status'] === 'sold' || $is_current_deleted) ? 'bg-slate-700/60 text-slate-500 border border-slate-700 cursor-not-allowed' : 'bg-emerald-600 hover:bg-emerald-700 active:scale-[0.98] text-white border border-emerald-500 shadow-emerald-900/30'; ?>">
                                        <i class="fa-solid fa-check-circle text-sm"></i>
                                        <span>Accept Top Bid</span>
                                    </button>
                                    
                                    <!-- Reject & Close Button -->
                                    <button type="submit" name="reject_bid" 
                                        <?php echo ($current_item['status'] === 'ended' || $current_item['status'] === 'sold' || $is_current_deleted) ? 'disabled' : ''; ?>
                                        onclick="return confirm('Reject bids and close this auction lot?');" 
                                        class="w-full h-11 px-3 py-2.5 rounded-xl font-bold text-xs tracking-wider uppercase transition-all duration-200 flex items-center justify-center gap-2 shadow-xs cursor-pointer <?php echo ($current_item['status'] === 'ended' || $current_item['status'] === 'sold' || $is_current_deleted) ? 'bg-slate-700/60 text-slate-500 border border-slate-700 cursor-not-allowed' : 'bg-amber-600 hover:bg-amber-700 active:scale-[0.98] text-white border border-amber-500 shadow-amber-900/30'; ?>">
                                        <i class="fa-solid fa-ban text-sm"></i>
                                        <span>Reject & Close</span>
                                    </button>
                                    
                                    <!-- Delete Lot Button -->
                                    <button type="submit" name="delete_item" 
                                        <?php echo $is_current_deleted ? 'disabled' : ''; ?>
                                        onclick="return confirm('Are you sure you want to soft-delete this lot? It will immediately disappear from the public live auction.');" 
                                        class="w-full h-11 px-3 py-2.5 rounded-xl font-bold text-xs tracking-wider uppercase transition-all duration-200 flex items-center justify-center gap-2 shadow-xs cursor-pointer <?php echo $is_current_deleted ? 'bg-slate-700/60 text-slate-500 border border-slate-700 cursor-not-allowed' : 'bg-rose-600 hover:bg-rose-700 active:scale-[0.98] text-white border border-rose-500 shadow-rose-900/30'; ?>">
                                        <i class="fa-solid fa-trash-can text-sm"></i>
                                        <span>Delete Lot</span>
                                    </button>
                                </div>
                            </form>
                        </div>
                    <?php elseif ($is_current_deleted): ?>
                        <div class="p-4 rounded-2xl bg-rose-950/60 border border-rose-800 text-center text-rose-300 font-semibold text-xs">
                            <i class="fa-solid fa-ban text-rose-400 mr-1.5"></i> Bidding is disabled because this auction lot was withdrawn.
                        </div>
                    <?php elseif ($current_item['status'] === 'active'): ?>
                        <div class="space-y-3">
                            <!-- Quick Bid Pills -->
                            <div class="flex items-center gap-1.5 flex-wrap text-xs">
                                <span class="text-slate-400 font-semibold text-[11px] mr-1">Quick Bid:</span>
                                <button type="button" onclick="setQuickIncrement(0)" class="px-2.5 py-1 rounded-lg bg-slate-800 hover:bg-emerald-600 text-slate-200 text-xs font-semibold transition cursor-pointer border border-slate-700">
                                    Min
                                </button>
                                <button type="button" onclick="setQuickIncrement(500)" class="px-2.5 py-1 rounded-lg bg-slate-800 hover:bg-emerald-600 text-slate-200 text-xs font-semibold transition cursor-pointer border border-slate-700">
                                    +Rs. 500
                                </button>
                                <button type="button" onclick="setQuickIncrement(1000)" class="px-2.5 py-1 rounded-lg bg-slate-800 hover:bg-emerald-600 text-slate-200 text-xs font-semibold transition cursor-pointer border border-slate-700">
                                    +Rs. 1,000
                                </button>
                                <button type="button" onclick="setQuickIncrement(5000)" class="px-2.5 py-1 rounded-lg bg-slate-800 hover:bg-emerald-600 text-slate-200 text-xs font-semibold transition cursor-pointer border border-slate-700">
                                    +Rs. 5,000
                                </button>
                            </div>

                            <!-- Bid Form -->
                            <form id="bidForm" class="flex flex-col gap-2.5">
                                <input type="hidden" name="item_id" id="currentLotId" value="<?php echo $current_item['id']; ?>">
                                <div class="relative">
                                    <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-xs font-bold text-slate-400">Rs.</span>
                                    <input type="number" 
                                           step="10" 
                                           name="bid_amount" 
                                           id="bidAmountInput" 
                                           placeholder="Enter at least Rs. <?php echo number_format($next_min_bid, 2); ?>" 
                                           required 
                                           min="<?php echo $next_min_bid; ?>" 
                                           class="w-full bg-slate-800 border border-slate-700 text-white rounded-xl pl-10 pr-4 py-3 text-sm font-semibold focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 transition">
                                </div>
                                <button type="submit" id="bidSubmitBtn" class="w-full py-3.5 bg-emerald-600 hover:bg-emerald-500 text-white font-extrabold text-sm rounded-xl transition shadow-lg flex items-center justify-center gap-2 active:scale-95 cursor-pointer">
                                    <i class="fa-solid fa-gavel text-xs"></i> Place Live Bid Now
                                </button>
                            </form>

                            <!-- Secondary Actions: Auto-Bid, Buy It Now, Message Seller -->
                            <div class="flex items-center gap-2 pt-1 flex-wrap">
                                <button type="button" onclick="openAutoBidPrompt(<?php echo $current_item['id']; ?>)" class="flex-1 min-w-[140px] py-2 px-3 text-xs font-bold bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-xl transition flex items-center justify-center gap-1.5 border border-slate-700 cursor-pointer">
                                    <i class="fa-solid fa-robot text-emerald-400"></i> Setup Auto-Bid
                                </button>

                                <?php if (!empty($current_item['buy_it_now_price']) && (float)$current_item['buy_it_now_price'] > 0): ?>
                                    <button type="button" onclick="confirmBuyItNow(<?php echo $current_item['id']; ?>, <?php echo (float)$current_item['buy_it_now_price']; ?>)" 
                                            class="flex-1 min-w-[140px] py-2 px-3 text-xs font-bold bg-amber-500 hover:bg-amber-600 text-white rounded-xl transition shadow-xs flex items-center justify-center gap-1.5 cursor-pointer">
                                        <i class="fa-solid fa-bolt"></i> Buy Now: Rs. <?php echo number_format($current_item['buy_it_now_price'], 0); ?>
                                    </button>
                                <?php endif; ?>

                                <?php if (!empty($current_item['seller_id']) && (int)$current_item['seller_id'] !== $user_id): ?>
                                    <a href="messages.php?user_id=<?php echo (int)$current_item['seller_id']; ?>&item_id=<?php echo (int)$current_item['id']; ?>" 
                                       class="w-full py-2 px-3 text-xs font-bold bg-emerald-950/80 hover:bg-emerald-900 text-emerald-300 border border-emerald-800 rounded-xl transition flex items-center justify-center gap-1.5 shadow-2xs text-decoration-none">
                                        <i class="fa-solid fa-message text-emerald-400"></i> Message Seller Directly
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="p-4 bg-slate-800/80 rounded-2xl text-center text-xs font-semibold text-slate-400 border border-slate-700">
                            <i class="fa-solid fa-lock mr-1.5 text-slate-500"></i> This auction is <?php echo htmlspecialchars($current_item['status']); ?>. Bidding is closed.
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Safe Transaction & Security Guarantee Card -->
                <div class="bg-slate-50 border border-slate-200 rounded-2xl p-4 text-xs text-slate-600 space-y-2">
                    <div class="flex items-center gap-2 font-bold text-slate-800">
                        <i class="fa-solid fa-shield-halved text-emerald-600 text-sm"></i>
                        <span>Buyer Protection & Escrow Guarantee</span>
                    </div>
                    <p class="text-[11px] leading-relaxed text-slate-500">
                        All property bids are legally binding and protected under Geo Auction System escrow terms with verified land department title verification.
                    </p>
                </div>
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

        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4">
            <?php if (!empty($catalog_list)): ?>
                <?php foreach($catalog_list as $prod): 
                    $p_price = ($prod['current_price'] ?? 0) > 0 ? (float)$prod['current_price'] : (float)($prod['starting_price'] ?? 0);
                    $is_highlighted = ($current_item && (int)$prod['id'] === (int)$current_item['id']);
                    $card_qs = 'id=' . $prod['id'];
                    $grid_img = !empty($prod['featured_image']) ? cleanImagePath($prod['featured_image']) : '';
                    if (empty($grid_img) && !empty($prod['images'])) {
                        $p_imgs = parseItemImages(null, 0, $prod['images'], '');
                        $grid_img = $p_imgs[0] ?? '';
                    }
                    if (empty($grid_img)) {
                        $grid_img = "https://images.unsplash.com/photo-1552519507-da3b142c6e3d?auto=format&fit=crop&w=400&q=80";
                    }
                    $p_dist = $prod['dist_km'];
                    $diff_secs = strtotime($prod['end_time'] ?? '') - time();
                    $is_ending_soon = ($diff_secs > 0 && $diff_secs <= 600);
                ?>
                    <div class="border <?php echo $is_highlighted ? 'border-emerald-600 ring-2 ring-emerald-100 bg-white' : 'border-slate-200 bg-slate-50/60 hover:border-emerald-500 hover:bg-white'; ?> rounded-xl p-3 transition flex flex-col justify-between group shadow-sm">
                        <div>
                            <div class="h-32 rounded-lg bg-slate-200 overflow-hidden mb-2.5 relative">
                                <img src="<?php echo htmlspecialchars($grid_img); ?>" class="w-full h-full object-cover group-hover:scale-105 transition duration-300" loading="lazy" alt="<?php echo htmlspecialchars($prod['title']); ?>">
                                <span class="absolute top-2 left-2 px-2 py-0.5 rounded bg-black/60 backdrop-blur-sm text-white text-[10px] font-semibold">
                                    #<?php echo htmlspecialchars($prod['auction_code'] ?? ('LOT-'.$prod['id'])); ?>
                                </span>
                                <?php if ($is_ending_soon): ?>
                                    <span class="absolute top-2 right-2 px-1.5 py-0.5 rounded bg-rose-600 text-white text-[9px] font-bold uppercase tracking-wide animate-pulse">
                                        Ending Soon
                                    </span>
                                <?php endif; ?>
                                <?php if ($p_dist !== null): ?>
                                    <span class="absolute bottom-1.5 left-1.5 px-1.5 py-0.5 rounded bg-slate-900/80 backdrop-blur-xs text-white text-[9px] font-medium">
                                        📍 <?php echo htmlspecialchars($prod['location_area'] ?? 'Qasimabad'); ?> &bull; <?php echo $p_dist; ?> km
                                    </span>
                                <?php endif; ?>
                            </div>
                            <h4 class="text-xs font-bold text-slate-900 line-clamp-1 group-hover:text-emerald-600 transition">
                                <?php echo htmlspecialchars($prod['title']); ?>
                            </h4>
                            <div class="flex items-center justify-between text-[10px] text-slate-400 mt-1.5 pt-1.5 border-t border-slate-100">
                                <span class="flex items-center gap-1">
                                    <i class="fa-solid fa-gavel text-[9px] text-slate-400"></i> <?php echo (int)($prod['bid_count'] ?? 0); ?> bids
                                </span>
                                <span class="text-[10px] text-slate-400">
                                    📍 <?php echo htmlspecialchars($prod['location_area'] ?? 'Hyderabad'); ?>
                                </span>
                            </div>
                        </div>
                        <div class="mt-3 pt-2 border-t border-slate-200">
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
                    if ($categories_res && mysqli_num_rows($categories_res) > 0):
                        mysqli_data_seek($categories_res, 0);
                        while ($cat = mysqli_fetch_assoc($categories_res)): 
                    ?>
                        <option value="<?php echo $cat['id']; ?>" <?php echo $selected_category === (int)$cat['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat['name']); ?>
                        </option>
                    <?php endwhile; endif; ?>
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

<!-- Card Discussion Offcanvas Drawer -->
<div id="cardCommentDrawerBackdrop" class="filter-drawer-backdrop fixed inset-0 bg-slate-950/50 backdrop-blur-xs z-50 flex justify-end">
    <div id="cardCommentDrawerPanel" class="filter-drawer-panel w-full max-w-md bg-white h-full shadow-2xl flex flex-col justify-between">
        <!-- Header -->
        <div class="p-4 sm:p-5 border-b border-slate-100 flex items-center justify-between bg-slate-50/70">
            <div class="flex items-center gap-2.5">
                <span class="w-9 h-9 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center font-bold">
                    <i class="fa-regular fa-comments text-base"></i>
                </span>
                <div class="max-w-[260px]">
                    <span id="cardDrawerLotBadge" class="text-[10px] font-extrabold uppercase tracking-wider text-emerald-700 bg-emerald-100/70 px-2 py-0.5 rounded">#LOT</span>
                    <h3 id="cardDrawerLotTitle" class="text-sm font-bold text-slate-900 truncate mt-0.5">Lot Discussion</h3>
                </div>
            </div>
            <button type="button" onclick="closeCardCommentsDrawer()" class="w-8 h-8 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100 flex items-center justify-center transition cursor-pointer">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>

        <!-- Comments Feed -->
        <div class="p-4 sm:p-5 space-y-3 overflow-y-auto flex-1 bg-slate-50/30" id="cardDrawerCommentsList">
            <div class="text-center py-12 text-slate-400">
                <i class="fa-solid fa-spinner fa-spin text-xl text-emerald-600 mb-2"></i>
                <p class="text-xs font-semibold">Loading discussion...</p>
            </div>
        </div>

        <!-- Quick Reply Form -->
        <div class="p-4 border-t border-slate-200 bg-white">
            <?php if ($is_authenticated): ?>
                <form id="cardDrawerCommentForm" class="space-y-2">
                    <input type="hidden" name="action" value="post_comment">
                    <input type="hidden" name="item_id" id="cardDrawerItemId" value="">
                    <div class="relative">
                        <textarea id="cardDrawerCommentInput" 
                                  name="comment" 
                                  rows="2" 
                                  maxlength="500" 
                                  placeholder="Write a quick comment or question..." 
                                  required
                                  class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs text-slate-800 focus:outline-none focus:border-emerald-600 focus:bg-white focus:ring-2 focus:ring-emerald-100 transition resize-none"></textarea>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-[10px] text-slate-400">
                            <span id="cardDrawerCharCount">0</span> / 500
                        </span>
                        <button type="submit" 
                                id="cardDrawerSubmitBtn" 
                                class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs rounded-xl shadow-xs transition active:scale-95 cursor-pointer flex items-center gap-1.5">
                            <i class="fa-solid fa-paper-plane text-xs"></i> Send
                        </button>
                    </div>
                </form>
            <?php else: ?>
                <div class="p-3 bg-slate-50 rounded-xl border border-slate-200 text-xs text-center text-slate-600">
                    <p class="mb-2">Sign in to participate in this lot's discussion.</p>
                    <a href="login.php" class="inline-block px-4 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs rounded-lg transition">Sign In</a>
                </div>
            <?php endif; ?>
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
                <p id="modalSeller" class="text-xs text-slate-500 mt-1"></p>
                <p id="modalDescription" class="text-xs text-slate-600 mt-2 leading-relaxed whitespace-pre-line"></p>
            </div>

            <!-- Existing Comments Feed in View Card Modal -->
            <div id="modalCommentsContainer" class="space-y-2 pt-2 border-t border-slate-100">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-slate-800 flex items-center gap-1.5">
                        <i class="fa-regular fa-comments text-emerald-600"></i>
                        <span>Comments (<span id="modalCommentsCount">0</span>)</span>
                    </span>
                </div>
                <div id="modalCommentsList" class="space-y-2 max-h-44 overflow-y-auto pr-1 scrollbar-thin text-xs">
                    <div class="text-center py-4 text-slate-400 text-xs italic">No comments yet. Be the first to ask the seller a question!</div>
                </div>
            </div>

            <!-- Comment Input Field matching user screenshot -->
            <form id="modalCommentForm" onsubmit="handleModalCommentSubmit(event)" class="flex items-center gap-2 pt-1">
                <input type="hidden" id="modalCommentItemId" value="">
                <input type="text" 
                       id="modalCommentInput" 
                       placeholder="Write a comment..." 
                       required 
                       maxlength="500" 
                       class="flex-1 bg-white border border-slate-300 rounded-lg px-3.5 py-2 text-xs text-slate-800 placeholder-slate-400 focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-500 transition">
                <button type="submit" 
                        id="modalCommentSubmitBtn" 
                        class="px-4 py-2 bg-[#60877f] hover:bg-[#4f7069] active:bg-[#3d5953] text-white text-xs font-bold rounded-lg shadow-xs transition active:scale-95 cursor-pointer shrink-0 disabled:opacity-50">
                    Post Comment
                </button>
            </form>

            <div class="pt-2 flex items-center gap-2 flex-wrap">
                <a id="modalBidNowBtn" href="" class="flex-1 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs rounded-xl text-center shadow transition flex items-center justify-center gap-1.5">
                    <i class="fa-solid fa-plane-departure text-xs"></i> Go to Live Terminal
                </a>
                <button type="button" id="modalWatchBtn" onclick="" class="py-2.5 px-4 bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold text-xs rounded-xl transition flex items-center gap-1.5 cursor-pointer">
                    <i class="fa-regular fa-heart text-rose-500"></i> Watch
                </button>
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

    // Image Gallery Carousel & Bayut Bridge
    var currentImgIndex = 0;
    var galleryImages = <?php echo json_encode($gallery_images ?? []); ?>;
    function setMainImage(src, thumb) {
        if (typeof selectBayutImage === 'function' && window.bayutGalleryData) {
            for (var i = 0; i < window.bayutGalleryData.length; i++) {
                if (window.bayutGalleryData[i].url === src) {
                    selectBayutImage(i);
                    return;
                }
            }
        }
        var mainImg = document.getElementById('bayutHeroImg') || document.getElementById('mainImage');
        if (mainImg) mainImg.src = src;
    }
    var prevBtn = document.getElementById('prevBtn');
    var nextBtn = document.getElementById('nextBtn');
    if (prevBtn) {
        prevBtn.addEventListener('click', function(e) {
            if (typeof stepGalleryHero === 'function') stepGalleryHero(-1, e);
        });
    }
    if (nextBtn) {
        nextBtn.addEventListener('click', function(e) {
            if (typeof stepGalleryHero === 'function') stepGalleryHero(1, e);
        });
    }

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
                    if (data.total_comments !== undefined) {
                        var cmtCountEl = document.getElementById('activeLotCommentCount');
                        if (cmtCountEl) cmtCountEl.textContent = data.total_comments;
                    }
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

    // ------------------------------------------------------------------------
    // Nested Comments & Live Discussion State Engine
    // ------------------------------------------------------------------------
    var initialCommentsCount = <?php echo count($current_item_comments); ?>;
    var initialLastCommentId = <?php 
        $max_cid = 0;
        if (!empty($current_item_comments)) {
            foreach ($current_item_comments as $c) {
                if ((int)$c['id'] > $max_cid) $max_cid = (int)$c['id'];
            }
        }
        echo $max_cid;
    ?>;

    function formatClientTimeAgo(dateStr) {
        if (!dateStr) return 'Just now';
        var d = new Date(dateStr.replace(/-/g, "/"));
        var secs = Math.floor((new Date() - d) / 1000);
        if (isNaN(secs) || secs < 5) return 'Just now';
        if (secs < 60) return secs + 's ago';
        if (secs < 3600) return Math.floor(secs / 60) + 'm ago';
        if (secs < 86400) return Math.floor(secs / 3600) + 'h ago';
        if (secs < 604800) return Math.floor(secs / 86400) + 'd ago';
        return d.toLocaleDateString();
    }

    function renderCommentItemHtml(c) {
        var avatarHtml = c.avatar 
            ? '<img src="' + c.avatar + '" class="w-7 h-7 rounded-full object-cover border border-slate-200 shadow-2xs" alt="Avatar">'
            : '<div class="w-7 h-7 rounded-full ' + (c.is_seller ? 'bg-emerald-600 text-white' : 'bg-slate-200 text-slate-700') + ' flex items-center justify-center font-bold text-xs shadow-2xs">' + (c.initial || 'U') + '</div>';

        var roleBadge = '';
        if (c.is_seller) {
            roleBadge = '<span class="ml-1 text-[9px] bg-emerald-600 text-white font-extrabold px-1.5 py-0.5 rounded uppercase tracking-wider">Seller</span>';
        } else if (c.user_role === 'admin') {
            roleBadge = '<span class="ml-1 text-[9px] bg-purple-600 text-white font-extrabold px-1.5 py-0.5 rounded uppercase tracking-wider">Admin</span>';
        }

        var bgClass = c.is_seller ? 'bg-emerald-50/70 border border-emerald-200/80' : 'bg-white border border-slate-200/80';

        return '<div class="p-3 rounded-xl ' + bgClass + ' text-xs space-y-1.5 transition hover:border-slate-300 shadow-2xs animate-comment-entry" data-comment-id="' + c.id + '">' +
            '<div class="flex items-center justify-between">' +
                '<div class="flex items-center gap-2">' +
                    avatarHtml +
                    '<div>' +
                        '<span class="font-bold text-slate-900">' + c.user_name + '</span>' +
                        roleBadge +
                    '</div>' +
                '</div>' +
                '<span class="text-[10px] text-slate-400 font-medium">' + (c.time_ago || formatClientTimeAgo(c.created_at)) + '</span>' +
            '</div>' +
            '<p class="text-slate-700 text-xs leading-relaxed whitespace-pre-wrap break-words pl-9">' + c.comment + '</p>' +
        '</div>';
    }

    window.commentStreamState = {
        isCollapsed: false,
        isStreamExpanded: false,
        lastCommentId: initialLastCommentId,
        totalCount: initialCommentsCount,
        isSubmitting: false,

        toggleCollapse: function() {
            var body = document.getElementById('nestedCommentsBody');
            var btnText = document.getElementById('toggleCommentsBtnText');
            var btnIcon = document.getElementById('toggleCommentsBtnIcon');
            var btn = document.getElementById('toggleCommentsCollapseBtn');
            if (!body) return;

            this.isCollapsed = !this.isCollapsed;
            if (this.isCollapsed) {
                body.style.display = 'none';
                if (btnText) btnText.textContent = 'Expand (' + this.totalCount + ')';
                if (btnIcon) btnIcon.className = 'fa-solid fa-chevron-down text-[10px] transition-transform duration-200';
                if (btn) btn.setAttribute('aria-expanded', 'false');
            } else {
                body.style.display = 'block';
                if (btnText) btnText.textContent = 'Collapse';
                if (btnIcon) btnIcon.className = 'fa-solid fa-chevron-up text-[10px] transition-transform duration-200';
                if (btn) btn.setAttribute('aria-expanded', 'true');
            }
        },

        toggleExpandStream: function() {
            var stream = document.getElementById('itemCommentsStream');
            var textSpan = document.getElementById('expandCommentsStreamText');
            var icon = document.getElementById('expandCommentsStreamIcon');
            if (!stream) return;

            this.isStreamExpanded = !this.isStreamExpanded;
            if (this.isStreamExpanded) {
                stream.classList.remove('max-h-72', 'sm:max-h-80');
                stream.classList.add('max-h-[32rem]');
                if (textSpan) textSpan.textContent = 'Compact discussion view';
                if (icon) icon.className = 'fa-solid fa-angles-up text-[10px]';
            } else {
                stream.classList.remove('max-h-[32rem]');
                stream.classList.add('max-h-72', 'sm:max-h-80');
                if (textSpan) textSpan.textContent = 'Show expanded scroll view (' + this.totalCount + ' comments)';
                if (icon) icon.className = 'fa-solid fa-angles-down text-[10px]';
            }
        },

        clearInput: function() {
            var textarea = document.getElementById('activeLotCommentTextarea');
            var counter = document.getElementById('activeLotCharCounter');
            var clearBtn = document.getElementById('activeLotCommentClearBtn');
            if (textarea) textarea.value = '';
            if (counter) {
                counter.textContent = '0';
                counter.className = 'font-bold text-slate-600';
            }
            if (clearBtn) clearBtn.classList.add('hidden');
        },

        updateCharCount: function(val) {
            var counter = document.getElementById('activeLotCharCounter');
            var clearBtn = document.getElementById('activeLotCommentClearBtn');
            var len = val ? val.length : 0;
            if (counter) {
                counter.textContent = len;
                if (len >= 480) {
                    counter.className = 'font-bold text-rose-600';
                } else if (len >= 400) {
                    counter.className = 'font-bold text-amber-600';
                } else {
                    counter.className = 'font-bold text-slate-600';
                }
            }
            if (clearBtn) {
                if (len > 0) clearBtn.classList.remove('hidden');
                else clearBtn.classList.add('hidden');
            }
        },

        syncCountBadges: function(count) {
            this.totalCount = count;
            var activeBadge = document.getElementById('activeLotCommentCount');
            if (activeBadge) activeBadge.textContent = count;
            
            var cardBadge = document.getElementById('cardCommentCount_' + currentLotId);
            if (cardBadge) cardBadge.textContent = count;

            var btnText = document.getElementById('toggleCommentsBtnText');
            if (btnText && this.isCollapsed) {
                btnText.textContent = 'Expand (' + count + ')';
            }

            var expandBar = document.getElementById('expandStreamControlBar');
            if (expandBar) {
                if (count > 3) expandBar.style.display = 'block';
                else expandBar.style.display = 'none';
            }
        }
    };

    var activeLotCommentForm = document.getElementById('activeLotCommentForm');
    var activeLotTextarea = document.getElementById('activeLotCommentTextarea');
    var activeLotSubmitBtn = document.getElementById('activeLotCommentSubmitBtn');

    if (activeLotTextarea) {
        activeLotTextarea.addEventListener('input', function() {
            commentStreamState.updateCharCount(this.value);
        });

        activeLotTextarea.addEventListener('keydown', function(e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
                e.preventDefault();
                if (activeLotCommentForm) {
                    activeLotCommentForm.requestSubmit();
                }
            }
        });
    }

    if (activeLotCommentForm) {
        activeLotCommentForm.addEventListener('submit', function(e) {
            e.preventDefault();
            var text = activeLotTextarea ? activeLotTextarea.value.trim() : '';
            if (!text) return;

            commentStreamState.isSubmitting = true;
            if (activeLotSubmitBtn) {
                activeLotSubmitBtn.disabled = true;
                activeLotSubmitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin text-xs"></i> <span>Posting...</span>';
            }

            var fd = new FormData(activeLotCommentForm);

            fetch('ajax_comment.php', { method: 'POST', body: fd })
                .then(function(r) { return r.json(); })
                .then(function(res) {
                    commentStreamState.isSubmitting = false;
                    if (activeLotSubmitBtn) {
                        activeLotSubmitBtn.disabled = false;
                        activeLotSubmitBtn.innerHTML = '<i class="fa-solid fa-paper-plane text-[11px]"></i> <span>Post Comment</span>';
                    }

                    if (res.status === 'success' && res.comment) {
                        var stream = document.getElementById('itemCommentsStream');
                        var emptyPrompt = document.getElementById('noCommentsPrompt');
                        if (emptyPrompt) emptyPrompt.remove();

                        if (stream) {
                            var temp = document.createElement('div');
                            temp.innerHTML = renderCommentItemHtml(res.comment);
                            var newElem = temp.firstElementChild;
                            if (newElem) {
                                stream.appendChild(newElem);
                                stream.scrollTop = stream.scrollHeight;
                            }
                        }

                        if (res.comment.id > commentStreamState.lastCommentId) {
                            commentStreamState.lastCommentId = res.comment.id;
                        }

                        if (res.total_comments !== undefined) {
                            commentStreamState.syncCountBadges(res.total_comments);
                        }

                        commentStreamState.clearInput();
                    } else {
                        if (res.redirect) {
                            window.location.href = res.redirect;
                            return;
                        }
                        if (typeof showLiveModalPopup === 'function') {
                            showLiveModalPopup('Comment Error', res.message || 'Failed to post comment', false);
                        } else {
                            alert(res.message || 'Failed to post comment');
                        }
                    }
                })
                .catch(function() {
                    commentStreamState.isSubmitting = false;
                    if (activeLotSubmitBtn) {
                        activeLotSubmitBtn.disabled = false;
                        activeLotSubmitBtn.innerHTML = '<i class="fa-solid fa-paper-plane text-[11px]"></i> <span>Post Comment</span>';
                    }
                });
        });
    }

    // Background polling for new comments
    function pollActiveLotComments() {
        if (!currentLotId) return;
        fetch('ajax_comment.php?action=fetch_comments&item_id=' + currentLotId + '&last_id=' + commentStreamState.lastCommentId)
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.status === 'success') {
                    if (data.total_comments !== undefined) {
                        commentStreamState.syncCountBadges(data.total_comments);
                    }

                    if (data.comments && data.comments.length > 0) {
                        var stream = document.getElementById('itemCommentsStream');
                        var emptyPrompt = document.getElementById('noCommentsPrompt');
                        if (emptyPrompt) emptyPrompt.remove();

                        data.comments.forEach(function(c) {
                            if (c.id > commentStreamState.lastCommentId) {
                                commentStreamState.lastCommentId = c.id;
                                if (stream) {
                                    var temp = document.createElement('div');
                                    temp.innerHTML = renderCommentItemHtml(c);
                                    var newElem = temp.firstElementChild;
                                    if (newElem) {
                                        stream.appendChild(newElem);
                                        stream.scrollTop = stream.scrollHeight;
                                    }
                                }
                            }
                        });
                    }
                }
            })
            .catch(function() {});
    }
    setInterval(pollActiveLotComments, 6000);

    // ------------------------------------------------------------------------
    // Card Discussion Offcanvas Drawer Management
    // ------------------------------------------------------------------------
    var cardDrawerBackdrop = document.getElementById('cardCommentDrawerBackdrop');
    var cardDrawerPanel = document.getElementById('cardCommentDrawerPanel');
    var cardDrawerCommentsList = document.getElementById('cardDrawerCommentsList');
    var cardDrawerItemIdInput = document.getElementById('cardDrawerItemId');
    var cardDrawerLotTitle = document.getElementById('cardDrawerLotTitle');
    var cardDrawerLotBadge = document.getElementById('cardDrawerLotBadge');
    var cardDrawerForm = document.getElementById('cardDrawerCommentForm');
    var cardDrawerInput = document.getElementById('cardDrawerCommentInput');
    var cardDrawerCharCount = document.getElementById('cardDrawerCharCount');
    var cardDrawerSubmitBtn = document.getElementById('cardDrawerSubmitBtn');

    window.openCardCommentsDrawer = function(itemId, title, e) {
        if (e) {
            e.preventDefault();
            e.stopPropagation();
        }
        if (!cardDrawerBackdrop || !cardDrawerPanel) return;

        if (cardDrawerLotTitle) cardDrawerLotTitle.textContent = title || ('Lot #' + itemId);
        if (cardDrawerLotBadge) cardDrawerLotBadge.textContent = '#' + itemId;
        if (cardDrawerItemIdInput) cardDrawerItemIdInput.value = itemId;

        cardDrawerBackdrop.classList.add('is-open');
        cardDrawerPanel.classList.add('is-open');
        document.body.style.overflow = 'hidden';

        if (cardDrawerCommentsList) {
            cardDrawerCommentsList.innerHTML = '<div class="text-center py-12 text-slate-400">' +
                '<i class="fa-solid fa-spinner fa-spin text-xl text-emerald-600 mb-2"></i>' +
                '<p class="text-xs font-semibold">Loading discussion...</p>' +
            '</div>';
        }

        fetch('ajax_comment.php?action=get_card_comments&item_id=' + itemId)
            .then(function(r) { return r.json(); })
            .then(function(res) {
                if (res.status === 'success' && cardDrawerCommentsList) {
                    if (res.title && cardDrawerLotTitle) cardDrawerLotTitle.textContent = res.title;
                    if (res.comments && res.comments.length > 0) {
                        var html = '';
                        res.comments.forEach(function(c) {
                            var av = c.avatar 
                                ? '<img src="' + c.avatar + '" class="w-6 h-6 rounded-full object-cover border border-slate-200" alt="Avatar">'
                                : '<div class="w-6 h-6 rounded-full ' + (c.is_seller ? 'bg-emerald-600 text-white' : 'bg-slate-200 text-slate-700') + ' flex items-center justify-center font-bold text-[10px]">' + (c.initial || 'U') + '</div>';

                            html += '<div class="p-2.5 rounded-xl ' + (c.is_seller ? 'bg-emerald-50/70 border border-emerald-200/80' : 'bg-white border border-slate-200/80') + ' text-xs space-y-1 shadow-2xs">' +
                                '<div class="flex items-center justify-between">' +
                                    '<div class="flex items-center gap-1.5">' +
                                        av +
                                        '<strong class="text-slate-800 text-[11px]">' + c.user_name + '</strong>' +
                                        (c.is_seller ? '<span class="text-[9px] bg-emerald-600 text-white font-extrabold px-1 rounded uppercase">Seller</span>' : '') +
                                    '</div>' +
                                    '<span class="text-[10px] text-slate-400">' + c.time_ago + '</span>' +
                                '</div>' +
                                '<p class="text-slate-600 text-xs pl-7 leading-relaxed">' + c.comment + '</p>' +
                            '</div>';
                        });
                        cardDrawerCommentsList.innerHTML = html;
                    } else {
                        cardDrawerCommentsList.innerHTML = '<div class="text-center py-10 text-slate-400">' +
                            '<i class="fa-regular fa-comment-dots text-2xl text-slate-300 mb-1.5"></i>' +
                            '<p class="text-xs font-semibold text-slate-600">No comments yet on this lot.</p>' +
                            '<p class="text-[11px] text-slate-400 mt-0.5">Post the first comment below!</p>' +
                        '</div>';
                    }
                }
            })
            .catch(function() {
                if (cardDrawerCommentsList) {
                    cardDrawerCommentsList.innerHTML = '<div class="text-center py-8 text-rose-500 text-xs">Failed to load comments.</div>';
                }
            });
    };

    window.closeCardCommentsDrawer = function() {
        if (!cardDrawerBackdrop || !cardDrawerPanel) return;
        cardDrawerBackdrop.classList.remove('is-open');
        cardDrawerPanel.classList.remove('is-open');
        document.body.style.overflow = '';
    };

    if (cardDrawerBackdrop) {
        cardDrawerBackdrop.addEventListener('click', function(e) {
            if (e.target === cardDrawerBackdrop) closeCardCommentsDrawer();
        });
    }

    if (cardDrawerInput && cardDrawerCharCount) {
        cardDrawerInput.addEventListener('input', function() {
            cardDrawerCharCount.textContent = this.value.length;
        });
    }

    if (cardDrawerForm) {
        cardDrawerForm.addEventListener('submit', function(e) {
            e.preventDefault();
            var text = cardDrawerInput ? cardDrawerInput.value.trim() : '';
            var targetItemId = cardDrawerItemIdInput ? cardDrawerItemIdInput.value : 0;
            if (!text || !targetItemId) return;

            cardDrawerSubmitBtn.disabled = true;
            cardDrawerSubmitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin text-xs"></i>';

            var fd = new FormData(cardDrawerForm);

            fetch('ajax_comment.php', { method: 'POST', body: fd })
                .then(function(r) { return r.json(); })
                .then(function(res) {
                    cardDrawerSubmitBtn.disabled = false;
                    cardDrawerSubmitBtn.innerHTML = '<i class="fa-solid fa-paper-plane text-xs"></i> Send';

                    if (res.status === 'success' && res.comment) {
                        var cardBadge = document.getElementById('cardCommentCount_' + targetItemId);
                        if (cardBadge && res.total_comments !== undefined) {
                            cardBadge.textContent = res.total_comments;
                        }

                        if (parseInt(targetItemId) === currentLotId) {
                            var stream = document.getElementById('itemCommentsStream');
                            var emptyPrompt = document.getElementById('noCommentsPrompt');
                            if (emptyPrompt) emptyPrompt.remove();
                            if (stream) {
                                var temp = document.createElement('div');
                                temp.innerHTML = renderCommentItemHtml(res.comment);
                                stream.appendChild(temp.firstChild);
                                stream.scrollTop = stream.scrollHeight;
                            }
                            var countBadge = document.getElementById('activeLotCommentCount');
                            if (countBadge && res.total_comments !== undefined) {
                                countBadge.textContent = res.total_comments;
                            }
                            if (res.comment.id > lastCommentId) {
                                lastCommentId = res.comment.id;
                            }
                        }

                        cardDrawerInput.value = '';
                        if (cardDrawerCharCount) cardDrawerCharCount.textContent = '0';
                        openCardCommentsDrawer(targetItemId, cardDrawerLotTitle ? cardDrawerLotTitle.textContent : '');
                    } else {
                        if (res.redirect) {
                            window.location.href = res.redirect;
                            return;
                        }
                        if (typeof showLiveModalPopup === 'function') {
                            showLiveModalPopup('Comment Error', res.message || 'Failed to post comment', false);
                        } else {
                            alert(res.message || 'Failed to post comment');
                        }
                    }
                })
                .catch(function() {
                    cardDrawerSubmitBtn.disabled = false;
                    cardDrawerSubmitBtn.innerHTML = '<i class="fa-solid fa-paper-plane text-xs"></i> Send';
                });
        });
    }

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
    // Modal Details View (View Cards)
    function openDetailsModal(itemId) {
        var itemIdInput = document.getElementById('modalCommentItemId');
        if (itemIdInput) itemIdInput.value = itemId;

        var commentInput = document.getElementById('modalCommentInput');
        if (commentInput) commentInput.value = '';

        var commentsList = document.getElementById('modalCommentsList');
        if (commentsList) {
            commentsList.innerHTML = '<div class="text-center py-4 text-slate-400 text-xs"><i class="fa-solid fa-spinner fa-spin text-emerald-600 mr-1.5"></i> Loading comments...</div>';
        }

        var commentsCount = document.getElementById('modalCommentsCount');
        if (commentsCount) commentsCount.textContent = '0';

        fetch('live_auction.php?ajax=1&action=get_modal_details&item_id=' + itemId)
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    var lotCodeEl = document.getElementById('modalLotCode');
                    if (lotCodeEl) lotCodeEl.textContent = '#' + data.auction_code;
                    var imgEl = document.getElementById('modalImage');
                    if (imgEl) imgEl.src = data.image;
                    var titleEl = document.getElementById('modalTitle');
                    if (titleEl) titleEl.textContent = data.title;
                    var sellerEl = document.getElementById('modalSeller');
                    if (sellerEl) sellerEl.textContent = 'Seller: ' + data.seller_name;

                    var descEl = document.getElementById('modalDescription');
                    if (descEl) descEl.textContent = data.description;
                    var bidNowBtn = document.getElementById('modalBidNowBtn');
                    if (bidNowBtn) bidNowBtn.href = 'live_auction.php?id=' + data.id;
                    var distEl = document.getElementById('modalDistanceTag');
                    if (distEl) {
                        if (data.distance_km !== null) {
                            distEl.textContent = '📍 ' + data.location_area + ' • ' + data.distance_km + ' km away';
                            distEl.classList.remove('hidden');
                        } else {
                            distEl.classList.add('hidden');
                        }
                    }
                    var resBadge = document.getElementById('modalReserveStatus');
                    if (resBadge) {
                        resBadge.textContent = data.reserve_met ? 'Reserve Met' : 'Reserve Not Met';
                        resBadge.className = 'text-xs font-semibold px-2 py-0.5 rounded border ' + 
                            (data.reserve_met ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-amber-50 text-amber-700 border-amber-200');
                    }

                    var watchBtn = document.getElementById('modalWatchBtn');
                    if (watchBtn) {
                        watchBtn.onclick = function() { toggleWatchlist(data.id); };
                    }

                    document.getElementById('auctionDetailsModal').classList.add('is-open');
                    document.body.style.overflow = 'hidden';

                    loadModalComments(itemId);
                } else {
                    showLiveModalPopup('Item Details Error', data.error, false);
                }
            });
    }

    function loadModalComments(itemId) {
        var list = document.getElementById('modalCommentsList');
        var countBadge = document.getElementById('modalCommentsCount');
        if (!list) return;

        fetch('ajax_comment.php?action=fetch_comments&item_id=' + itemId)
            .then(r => r.json())
            .then(res => {
                if (res.status === 'success') {
                    if (countBadge) countBadge.textContent = res.total_comments || 0;
                    if (res.comments && res.comments.length > 0) {
                        var html = '';
                        res.comments.forEach(function(c) {
                            var av = c.avatar 
                                ? '<img src="' + c.avatar + '" class="w-6 h-6 rounded-full object-cover border border-slate-200 shadow-2xs" alt="Avatar">'
                                : '<div class="w-6 h-6 rounded-full ' + (c.is_seller ? 'bg-emerald-600 text-white' : 'bg-slate-200 text-slate-700') + ' flex items-center justify-center font-bold text-[10px] shadow-2xs">' + (c.initial || 'U') + '</div>';

                            html += '<div class="p-2.5 rounded-xl ' + (c.is_seller ? 'bg-emerald-50/70 border border-emerald-200/80' : 'bg-slate-50 border border-slate-200/70') + ' text-xs space-y-1 shadow-2xs">' +
                                '<div class="flex items-center justify-between">' +
                                    '<div class="flex items-center gap-1.5">' +
                                        av +
                                        '<strong class="text-slate-800 text-[11px]">' + c.user_name + '</strong>' +
                                        (c.is_seller ? '<span class="text-[9px] bg-emerald-600 text-white font-extrabold px-1 rounded uppercase">Seller</span>' : '') +
                                    '</div>' +
                                    '<span class="text-[10px] text-slate-400">' + c.time_ago + '</span>' +
                                '</div>' +
                                '<p class="text-slate-600 text-xs pl-7 leading-relaxed whitespace-pre-wrap break-words">' + c.comment + '</p>' +
                            '</div>';
                        });
                        list.innerHTML = html;
                        list.scrollTop = list.scrollHeight;
                    } else {
                        list.innerHTML = '<div class="text-center py-4 text-slate-400 text-xs italic">No comments yet. Be the first to ask the seller a question!</div>';
                    }
                }
            })
            .catch(() => {
                if (list) list.innerHTML = '<div class="text-center py-3 text-rose-500 text-xs">Failed to load comments.</div>';
            });
    }

    function handleModalCommentSubmit(e) {
        e.preventDefault();
        var input = document.getElementById('modalCommentInput');
        var itemIdInput = document.getElementById('modalCommentItemId');
        var submitBtn = document.getElementById('modalCommentSubmitBtn');
        var text = input ? input.value.trim() : '';
        var itemId = itemIdInput ? itemIdInput.value : 0;
        if (!text || !itemId) return;

        <?php if (!$is_authenticated): ?>
            window.location.href = 'login.php';
            return;
        <?php endif; ?>

        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin text-xs"></i>';
        }

        var fd = new FormData();
        fd.append('action', 'post_comment');
        fd.append('item_id', itemId);
        fd.append('comment', text);
        fetch('ajax_comment.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(res => {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Post Comment';
                }

                if (res.status === 'success' && res.comment) {
                    input.value = '';
                    var list = document.getElementById('modalCommentsList');
                    var countBadge = document.getElementById('modalCommentsCount');

                    if (list && list.querySelector('.italic')) {
                        list.innerHTML = '';
                    }

                    if (countBadge && res.total_comments !== undefined) {
                        countBadge.textContent = res.total_comments;
                    }

                    if (list) {
                        var c = res.comment;
                        var av = c.avatar 
                            ? '<img src="' + c.avatar + '" class="w-6 h-6 rounded-full object-cover border border-slate-200 shadow-2xs" alt="Avatar">'
                            : '<div class="w-6 h-6 rounded-full ' + (c.is_seller ? 'bg-emerald-600 text-white' : 'bg-slate-200 text-slate-700') + ' flex items-center justify-center font-bold text-[10px] shadow-2xs">' + (c.initial || 'U') + '</div>';

                        var newElem = document.createElement('div');
                        newElem.className = 'p-2.5 rounded-xl ' + (c.is_seller ? 'bg-emerald-50/70 border border-emerald-200/80' : 'bg-slate-50 border border-slate-200/70') + ' text-xs space-y-1 shadow-2xs animate-comment-entry';
                        newElem.innerHTML = '<div class="flex items-center justify-between">' +
                                '<div class="flex items-center gap-1.5">' +
                                    av +
                                    '<strong class="text-slate-800 text-[11px]">' + c.user_name + '</strong>' +
                                    (c.is_seller ? '<span class="text-[9px] bg-emerald-600 text-white font-extrabold px-1 rounded uppercase">Seller</span>' : '') +
                                '</div>' +
                                '<span class="text-[10px] text-slate-400">' + c.time_ago + '</span>' +
                            '</div>' +
                            '<p class="text-slate-600 text-xs pl-7 leading-relaxed whitespace-pre-wrap break-words">' + c.comment + '</p>';
                        list.appendChild(newElem);
                        list.scrollTop = list.scrollHeight;
                    }
                } else {
                    if (res.redirect) {
                        window.location.href = res.redirect;
                        return;
                    }
                    alert(res.message || 'Failed to post comment');
                }
            })
            .catch(() => {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Post Comment';
                }
            });
    }

    function closeDetailsModal() {
        document.getElementById('auctionDetailsModal').classList.remove('is-open');
        document.body.style.overflow = '';
    }

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

<?php 
if (file_exists(__DIR__ . '/../includes/footer.php')) {
    include_once __DIR__ . '/../includes/footer.php';
} elseif (file_exists(__DIR__ . '/includes/footer.php')) {
    include_once __DIR__ . '/includes/footer.php';
}
?>
</body>
</html>