<?php
        ini_set('display_errors', 1);
        ini_set('display_startup_errors', 1);
        error_reporting(E_ALL);
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            if (isset($_GET['switch_role']) && in_array(strtolower($_GET['switch_role']), ['buyer', 'seller', 'admin'])) {
                $_SESSION['role'] = strtolower($_GET['switch_role']);
            }
            $is_authenticated = isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
            $user_id = $is_authenticated ? (int)$_SESSION['user_id'] : 0;
            $role = $is_authenticated ? strtolower(trim($_SESSION['role'] ?? 'buyer')) : 'guest';
            $header_included = false;
            if (file_exists(__DIR__ . '/../includes/header.php')) {
                include_once __DIR__ . '/../includes/header.php';
                    $header_included = true;
            } elseif (file_exists(__DIR__ . '/includes/header.php')) {
                include_once __DIR__ . '/includes/header.php';
                    $header_included = true;
            }
            if (file_exists(__DIR__ . '/../includes/database.php')) {
                include_once __DIR__ . '/../includes/database.php';
            } elseif (file_exists(__DIR__ . '/includes/database.php')) {
                include_once __DIR__ . '/includes/database.php';
            }
            $has_db = isset($conn) && $conn instanceof mysqli && !$conn->connect_error;
            $user_lat = isset($_SESSION['user_lat']) ? (float)$_SESSION['user_lat'] : 25.3960;
            $user_lng = isset($_SESSION['user_lng']) ? (float)$_SESSION['user_lng'] : 68.3578;
        function getDistanceKm ($lat1, $lon1, $lat2, $lon2) {
            if (empty($lat1) || empty($lon1) || empty($lat2) || empty($lon2)) return null;
            $earthRadius = 6371;
            $dLat = deg2rad($lat2 - $lat1);
            $dLon = deg2rad($lon2 - $lon1);
            $a = sin($dLat / 2) * sin($dLat / 2) +
                cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
                sin($dLon / 2) * sin($dLon / 2);
            $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
            return round($earthRadius * $c, 1);
        }
        function sendNotification($conn, $to_user, $type, $title, $msg, $link = '') {
            if (!$conn || $to_user <= 0) return;
            $stmt = mysqli_prepare($conn, "INSERT INTO user_notifications (`user_id`, `type`, `title`, `message`, `link`, `is_read`, `created_at`) VALUES (?, ?, ?, ?, ?, 0, NOW())");
            if (!$stmt) {
                $stmt = mysqli_prepare($conn, "INSERT INTO notifications (`user_id`, `type`, `title`, `message`, `link`, `is_read`, `created_at`) VALUES (?, ?, ?, ?, ?, 0, NOW())");
            }
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "issss", $to_user, $type, $title, $msg, $link);
                mysqli_stmt_execute($stmt);
            }
        }
        function runAutoBids($conn, $item_id, $current_highest, $exclude_uid = 0) {
            if (!$conn) return;
            $stmt = mysqli_prepare($conn, "
                SELECT ab.id, ab.user_id, ab.max_bid_amount, u.name AS bidder_name, i.min_increment, i.seller_id
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
            $insert_bid = mysqli_prepare($conn, "INSERT INTO bids (item_id, user_id, bidder_name, bid_amount, bid_status, created_at, status) VALUES (?, ?, ?, ?, 'active', NOW(), 'active')");
            $b_name = $auto['bidder_name'] . ' (AutoBid)';
            mysqli_stmt_bind_param($insert_bid, "iisd", $item_id, $auto['user_id'], $b_name, $next_bid);
            if (mysqli_stmt_execute($insert_bid)) {
                $up_item = mysqli_prepare($conn, "UPDATE items SET current_price = ?, bid_count = bid_count + 1 WHERE id = ?");
                mysqli_stmt_bind_param($up_item, "di", $next_bid, $item_id);
                mysqli_stmt_execute($up_item);
                sendNotification($conn, $auto['seller_id'], 'bid', 'Auto-Bid Received', "Auto-bid of Rs. " . number_format($next_bid, 2) . " placed on your lot.", "live_auction.php?id=$item_id");
            }
        } else {
            $deact = mysqli_prepare($conn, "UPDATE auto_bids SET is_active = 0 WHERE id = ?");
            mysqli_stmt_bind_param($deact, "i", $auto['id']);
            mysqli_stmt_execute($deact);
            sendNotification($conn, $auto['user_id'], 'outbid', 'Auto-Bid Exceeded', "Your auto-bid limit of Rs. " . number_format($max_bid, 2) . " was exceeded.", "live_auction.php?id=$item_id");
        }
    }
}

if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? $_GET['action'] ?? '';

    if ($action === 'poll_status') {
        $poll_id = (int)($_GET['item_id'] ?? 0);
        if (!$has_db || $poll_id <= 0) {
            echo json_encode(['success' => false, 'error' => 'Invalid ID']);
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

        if (!$item) {
            echo json_encode(['success' => false, 'error' => 'Item not found']);
            exit();
        }

        $now = time();
        $end_ts = strtotime($item['end_time']);
        $is_closed = ($item['status'] !== 'active') || ($now >= $end_ts);

        if ($is_closed && $item['status'] === 'active') {
            $reserve = (float)($item['reserve_price'] ?? 0);
            $h_stmt = mysqli_prepare($conn, "SELECT id, user_id, bid_amount FROM bids WHERE item_id = ? ORDER BY bid_amount DESC LIMIT 1");
            mysqli_stmt_bind_param($h_stmt, "i", $poll_id);
            mysqli_stmt_execute($h_stmt);
            $h_res = mysqli_stmt_get_result($h_stmt);
            if ($top_bid = mysqli_fetch_assoc($h_res)) {
                if ($reserve <= 0 || (float)$top_bid['bid_amount'] >= $reserve) {
                    $fin_stmt = mysqli_prepare($conn, "UPDATE items SET status = 'completed', winner_id = ?, winning_bid_id = ? WHERE id = ?");
                    mysqli_stmt_bind_param($fin_stmt, "iii", $top_bid['user_id'], $top_bid['id'], $poll_id);
                    mysqli_stmt_execute($fin_stmt);
                    sendNotification($conn, $top_bid['user_id'], 'won', 'You Won the Auction!', "Congratulations! You won lot #$poll_id with Rs. " . number_format($top_bid['bid_amount'], 2), "live_auction.php?id=$poll_id");
                } else {
                    $fin_stmt = mysqli_prepare($conn, "UPDATE items SET status = 'closed' WHERE id = ?");
                    mysqli_stmt_bind_param($fin_stmt, "i", $poll_id);
                    mysqli_stmt_execute($fin_stmt);
                }
            } else {
                $fin_stmt = mysqli_prepare($conn, "UPDATE items SET status = 'closed' WHERE id = ?");
                mysqli_stmt_bind_param($fin_stmt, "i", $poll_id);
                mysqli_stmt_execute($fin_stmt);
            }
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

        $curr_price = max((float)$item['current_price'], (float)$item['starting_price']);
        $min_inc = (float)($item['min_increment'] > 0 ? $item['min_increment'] : 100.00);
        $next_min = $curr_price + $min_inc;
        $reserve_met = ($item['reserve_price'] <= 0 || $curr_price >= (float)$item['reserve_price']);

        echo json_encode([
            'success' => true,
            'current_price' => number_format($curr_price, 2),
            'raw_price' => $curr_price,
            'min_increment' => number_format($min_inc, 2),
            'next_min_formatted' => number_format($next_min, 2),
            'raw_next_min' => $next_min,
            'bid_count' => (int)$item['bid_count'],
            'watcher_count' => (int)$item['watcher_count'],
            'end_time' => $item['end_time'],
            'is_closed' => $is_closed,
            'reserve_met' => $reserve_met,
            'history' => $history
        ]);
        exit();
    }

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
            $stmt = mysqli_prepare($conn, "SELECT current_price, starting_price, min_increment, reserve_price, status, start_time, end_time, seller_id FROM items WHERE id = ? FOR UPDATE");
            mysqli_stmt_bind_param($stmt, "i", $b_item_id);
            mysqli_stmt_execute($stmt);
            $item_data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

            if (!$item_data) throw new Exception("Lot item not found.");
            if ((int)$item_data['seller_id'] === $user_id) throw new Exception("Sellers are not permitted to bid on their own listings.");
            
            $now = time();
            $end_ts = strtotime($item_data['end_time']);
            if ($item_data['status'] !== 'active' || $now > $end_ts) {
                throw new Exception("This auction is closed or has expired.");
            }

            $last_bid_stmt = mysqli_prepare($conn, "SELECT user_id FROM bids WHERE item_id = ? ORDER BY bid_amount DESC LIMIT 1");
            mysqli_stmt_bind_param($last_bid_stmt, "i", $b_item_id);
            mysqli_stmt_execute($last_bid_stmt);
            $last_bid_res = mysqli_stmt_get_result($last_bid_stmt);
            if ($last_row = mysqli_fetch_assoc($last_bid_res)) {
                if ((int)$last_row['user_id'] === $user_id) {
                    throw new Exception("You are already the highest bidder on this item.");
                }
            }

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
            $u_name = $u_row['name'] ?? ($_SESSION['name'] ?? 'Buyer');
            $u_email = $u_row['email'] ?? ($_SESSION['email'] ?? '');
            $ip_addr = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';

            $ins_bid = mysqli_prepare($conn, "INSERT INTO bids (item_id, user_id, bidder_name, bidder_email, bid_amount, bid_status, ip_address, user_agent, created_at, status) VALUES (?, ?, ?, ?, ?, 'active', ?, ?, NOW(), 'active')");
            mysqli_stmt_bind_param($ins_bid, "iissdss", $b_item_id, $user_id, $u_name, $u_email, $b_amount, $ip_addr, $ua);
            mysqli_stmt_execute($ins_bid);

            $extended = false;
            $remaining_secs = $end_ts - $now;
            if ($remaining_secs <= 180 && $remaining_secs > 0) {
                $extend_stmt = mysqli_prepare($conn, "UPDATE items SET end_time = DATE_ADD(end_time, INTERVAL 3 MINUTE), auto_extend_count = auto_extend_count + 1 WHERE id = ?");
                mysqli_stmt_bind_param($extend_stmt, "i", $b_item_id);
                mysqli_stmt_execute($extend_stmt);
                $extended = true;
            }

            $up_item = mysqli_prepare($conn, "UPDATE items SET current_price = ?, bid_count = bid_count + 1 WHERE id = ?");
            mysqli_stmt_bind_param($up_item, "di", $b_amount, $b_item_id);
            mysqli_stmt_execute($up_item);

            mysqli_commit($conn);

            if (!empty($last_row['user_id']) && (int)$last_row['user_id'] !== $user_id) {
                sendNotification($conn, $last_row['user_id'], 'outbid', 'You Have Been Outbid!', "A higher bid of Rs. " . number_format($b_amount, 2) . " was placed on lot #$b_item_id", "live_auction.php?id=$b_item_id");
            }
            sendNotification($conn, $item_data['seller_id'], 'bid', 'New Bid Received', "New bid of Rs. " . number_format($b_amount, 2) . " placed on your listing.", "live_auction.php?id=$b_item_id");
            runAutoBids($conn, $b_item_id, $b_amount, $user_id);
            echo json_encode([
                'success' => true,
                'message' => 'Your bid of Rs. ' . number_format($b_amount, 2) . ' was placed successfully!' . ($extended ? ' (Auction extended by 3 mins)' : ''),
                'extended' => $extended
            ]);
            exit();

        } catch (Exception $e) {
            mysqli_rollback($conn);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit();
        }
    }

    if ($action === 'toggle_watchlist') {
        if (!$is_authenticated) {
            echo json_encode(['success' => false, 'error' => 'Please sign in to manage your watchlist.', 'redirect' => 'login.php']);
            exit();
        }
        $w_item_id = (int)($_POST['item_id'] ?? 0);
        if ($w_item_id <= 0 || !$has_db) {
            echo json_encode(['success' => false, 'error' => 'Invalid lot.']);
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
            $in_watchlist = false;
        } else {
            $ins = mysqli_prepare($conn, "INSERT INTO wishlist (user_id, item_id, created_at) VALUES (?, ?, NOW())");
            mysqli_stmt_bind_param($ins, "ii", $user_id, $w_item_id);
            mysqli_stmt_execute($ins);
            $in_watchlist = true;
        }

        $cnt_stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS c FROM wishlist WHERE item_id = ?");
        mysqli_stmt_bind_param($cnt_stmt, "i", $w_item_id);
        mysqli_stmt_execute($cnt_stmt);
        $w_count = mysqli_fetch_assoc(mysqli_stmt_get_result($cnt_stmt))['c'] ?? 0;

        echo json_encode(['success' => true, 'in_watchlist' => $in_watchlist, 'count' => (int)$w_count]);
        exit();
    }

    if ($action === 'set_autobid') {
        if (!$is_authenticated) {
            echo json_encode(['success' => false, 'error' => 'Please sign in to configure Auto-Bid.']);
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

        runAutoBids($conn, $ab_item_id, $base, 0);

        echo json_encode(['success' => true, 'message' => "Auto-Bid configured up to Rs. " . number_format($max_bid, 2)]);
        exit();
    }

    if ($action === 'buy_it_now') {
        if (!$is_authenticated) {
            echo json_encode(['success' => false, 'error' => 'Please sign in to purchase.']);
            exit();
        }
        $bin_id = (int)($_POST['item_id'] ?? 0);
        if ($bin_id <= 0 || !$has_db) {
            echo json_encode(['success' => false, 'error' => 'Invalid lot request.']);
            exit();
        }

        mysqli_begin_transaction($conn);
        try {
            $stmt = mysqli_prepare($conn, "SELECT title, buy_it_now_price, seller_id, status FROM items WHERE id = ? FOR UPDATE");
            mysqli_stmt_bind_param($stmt, "i", $bin_id);
            mysqli_stmt_execute($stmt);
            $item = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

            if (!$item || $item['status'] !== 'active') throw new Exception("Auction is closed.");
            if (empty($item['buy_it_now_price']) || (float)$item['buy_it_now_price'] <= 0) throw new Exception("Buy It Now not enabled for this lot.");
            if ((int)$item['seller_id'] === $user_id) throw new Exception("You cannot purchase your own item.");

            $bin_price = (float)$item['buy_it_now_price'];

            $up = mysqli_prepare($conn, "UPDATE items SET status = 'completed', winner_id = ?, current_price = ? WHERE id = ?");
            mysqli_stmt_bind_param($up, "idi", $user_id, $bin_price, $bin_id);
            mysqli_stmt_execute($up);

            $tx = mysqli_prepare($conn, "INSERT INTO transactions (item_id, buyer_id, seller_id, amount, payment_type, status, created_at) VALUES (?, ?, ?, ?, 'buy_it_now', 'completed', NOW())");
            mysqli_stmt_bind_param($tx, "iiid", $bin_id, $user_id, $item['seller_id'], $bin_price);
            mysqli_stmt_execute($tx);

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

        if (!$m_item) {
            echo json_encode(['success' => false, 'error' => 'Lot not found']);
            exit();
        }

        $m_price = max((float)$m_item['current_price'], (float)$m_item['starting_price']);
        $m_inc = (float)($m_item['min_increment'] > 0 ? $m_item['min_increment'] : 100.00);
        $m_next = $m_price + $m_inc;
        $m_reserve_met = ($m_item['reserve_price'] <= 0 || $m_price >= (float)$m_item['reserve_price']);
        $m_dist = getDistanceKm($user_lat, $user_lng, $m_item['location_lat'] ?? 0, $m_item['location_lng'] ?? 0);

        echo json_encode([
            'success' => true,
            'id' => (int)$m_item['id'],
            'auction_code' => $m_item['auction_code'] ?? ('LOT-'.$m_item['id']),
            'title' => htmlspecialchars($m_item['title']),
            'description' => htmlspecialchars($m_item['description'] ?? ''),
            'image' => htmlspecialchars($m_item['featured_image'] ?? 'https://images.unsplash.com/photo-1552519507-da3b142c6e3d?auto=format&fit=crop&w=800&q=80'),
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

$seller_message = '';
$seller_success = false;
if (($role === 'seller' || $role === 'admin') && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $post_item_id = (int)($_POST['item_id'] ?? 0);

    if (isset($_POST['accept_bid']) && $has_db) {
        $chk_stmt = mysqli_prepare($conn, "SELECT id FROM items WHERE id = ?" . ($role === 'seller' ? " AND seller_id = ?" : ""));
        if ($role === 'seller') mysqli_stmt_bind_param($chk_stmt, "ii", $post_item_id, $user_id);
        else mysqli_stmt_bind_param($chk_stmt, "i", $post_item_id);
        mysqli_stmt_execute($chk_stmt);
        if (mysqli_stmt_get_result($chk_stmt)->num_rows > 0) {
            $bid_stmt = mysqli_prepare($conn, "SELECT id, user_id, bid_amount FROM bids WHERE item_id = ? ORDER BY bid_amount DESC LIMIT 1");
            mysqli_stmt_bind_param($bid_stmt, "i", $post_item_id);
            mysqli_stmt_execute($bid_stmt);
            $bid_res = mysqli_stmt_get_result($bid_stmt);
            if ($top_bid = mysqli_fetch_assoc($bid_res)) {
                $winner_id = (int)$top_bid['user_id'];
                $winning_bid_id = (int)$top_bid['id'];
                $up_item = mysqli_prepare($conn, "UPDATE items SET status = 'completed', winner_id = ?, winning_bid_id = ? WHERE id = ?");
                mysqli_stmt_bind_param($up_item, "iii", $winner_id, $winning_bid_id, $post_item_id);
                mysqli_stmt_execute($up_item);
                
                $up_bid = mysqli_prepare($conn, "UPDATE bids SET is_winning = 1, status = 'accepted' WHERE id = ?");
                mysqli_stmt_bind_param($up_bid, "i", $winning_bid_id);
                mysqli_stmt_execute($up_bid);
                
                sendNotification($conn, $winner_id, 'auction', 'Bid Accepted!', "Congratulations! Your bid was accepted for lot #$post_item_id.", "live_auction.php?id=$post_item_id");
                $seller_success = true;
                $seller_message = "Bid accepted successfully! Item marked as completed.";
            } else {
                $seller_message = "No bids found to accept for this item.";
            }
        }
    }
    
    if (isset($_POST['reject_bid']) && $has_db) {
        $chk_stmt = mysqli_prepare($conn, "SELECT id FROM items WHERE id = ?" . ($role === 'seller' ? " AND seller_id = ?" : ""));
        if ($role === 'seller') mysqli_stmt_bind_param($chk_stmt, "ii", $post_item_id, $user_id);
        else mysqli_stmt_bind_param($chk_stmt, "i", $post_item_id);
        mysqli_stmt_execute($chk_stmt);
        if (mysqli_stmt_get_result($chk_stmt)->num_rows > 0) {
            $up_item = mysqli_prepare($conn, "UPDATE items SET status = 'closed' WHERE id = ?");
            mysqli_stmt_bind_param($up_item, "i", $post_item_id);
            mysqli_stmt_execute($up_item);
            
            $up_bids = mysqli_prepare($conn, "UPDATE bids SET status = 'rejected' WHERE item_id = ?");
            mysqli_stmt_bind_param($up_bids, "i", $post_item_id);
            mysqli_stmt_execute($up_bids);

            $seller_success = true;
            $seller_message = "Auction bid rejected and item closed.";
        }
    }
    
    if (isset($_POST['delete_item']) && $has_db) {
        $chk_stmt = mysqli_prepare($conn, "SELECT featured_image FROM items WHERE id = ?" . ($role === 'seller' ? " AND seller_id = ?" : ""));
        if ($role === 'seller') mysqli_stmt_bind_param($chk_stmt, "ii", $post_item_id, $user_id);
        else mysqli_stmt_bind_param($chk_stmt, "i", $post_item_id);
        mysqli_stmt_execute($chk_stmt);
        $res = mysqli_stmt_get_result($chk_stmt);
        if ($row = mysqli_fetch_assoc($res)) {
            if (!empty($row['featured_image']) && file_exists(__DIR__ . '/' . $row['featured_image'])) {
                @unlink(__DIR__ . '/' . $row['featured_image']);
            }
            $del_stmt = mysqli_prepare($conn, "DELETE FROM items WHERE id = ?");
            mysqli_stmt_bind_param($del_stmt, "i", $post_item_id);
            mysqli_stmt_execute($del_stmt);
            
            $del_bids = mysqli_prepare($conn, "DELETE FROM bids WHERE item_id = ?");
            mysqli_stmt_bind_param($del_bids, "i", $post_item_id);
            mysqli_stmt_execute($del_bids);
            
            $seller_success = true;
            $seller_message = "Item deleted successfully.";
        }
    }
} 
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
        $item_query = mysqli_query($conn, "SELECT * FROM `items` WHERE `status` = 'active' ORDER BY `created_at` DESC LIMIT 1");
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
        }
        $w_cnt_res = mysqli_query($conn, "SELECT COUNT(*) AS c FROM wishlist WHERE item_id = " . (int)$current_item['id']);
        if ($w_cnt_res) $watcher_count = (int)mysqli_fetch_assoc($w_cnt_res)['c'];
    }

    $res = mysqli_query($conn, "SELECT COUNT(*) AS c FROM items WHERE status = 'active'");
    if ($res) { $totalItems = (int)mysqli_fetch_assoc($res)['c']; }

    $res = mysqli_query($conn, "SELECT COUNT(*) AS c FROM bids");
    if ($res) { $totalBidsOverall = (int)mysqli_fetch_assoc($res)['c']; }

    $sql_query = "SELECT * FROM `items` WHERE `status` = 'active'";
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
} else {
    $current_item = [
        'id' => 1,
        'auction_code' => 'LOT-4092',
        'title' => '2023 Porsche 911 GT3 Touring (992) - 6-Speed Manual',
        'category_id' => 1,
        'cat_name' => 'Vehicles',
        'condition' => 'new',
        'location' => 'Sindh',
        'location_city' => 'Hyderabad',
        'location_area' => 'Qasimabad',
        'location_lat' => 25.3960,
        'location_lng' => 68.3578,
        'seller_id' => 200,
        'seller_name' => 'Apex Motors Karachi',
        'starting_price' => 17500000.00,
        'current_price' => 18450000.00,
        'min_increment' => 50000.00,
        'reserve_price' => 18000000.00,
        'buy_it_now_price' => 21000000.00,
        'bid_count' => 19,
        'status' => 'active',
        'end_time' => date('Y-m-d H:i:s', time() + 380),
        'featured_image' => 'https://images.unsplash.com/photo-1614162692292-7ac56d7f7f1e?auto=format&fit=crop&w=1200&q=80',
        'images' => 'https://images.unsplash.com/photo-1614162692292-7ac56d7f7f1e?auto=format&fit=crop&w=1200&q=80',
        'description' => "Original Paint-to-Sample Oak Green Metallic over Cohiba Brown bucket leather. 4.0-liter naturally aspirated flat-six paired with 6-speed GT sports manual transmission."
    ];
    $totalItems = 5;
    $totalBidsOverall = 42;
    $categories_res = false;
    $is_watched = false;
    $watcher_count = 7;
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

function buildFilterParamsStr($overrides = []) {
    global $show_all, $item_id, $selected_category, $search_query, $sort_by, $min_price, $max_price, $selected_condition, $selected_location, $selected_radius;
    $parts = [];
    if (!empty($overrides['id']) || $item_id > 0) {
        $parts[] = 'id=' . (!empty($overrides['id']) ? $overrides['id'] : $item_id);
    }
    if (!empty($overrides['all']) || $show_all) {
        $parts[] = 'all=1';
    }
    if (!isset($overrides['category_id']) && $selected_category > 0) {
        $parts[] = 'category_id=' . $selected_category;
    }
    if (!isset($overrides['q']) && !empty($search_query)) {
        $parts[] = 'q=' . urlencode($search_query);
    }
    if (!isset($overrides['sort']) && $sort_by !== 'latest') {
        $parts[] = 'sort=' . $sort_by;
    }
    if ($selected_radius > 0) {
        $parts[] = 'radius=' . $selected_radius;
    }
    return implode('&', $parts);
}
$filter_qs = buildFilterParamsStr();

$catalog_list = [];
if ($all_products_res) {
    mysqli_data_seek($all_products_res, 0);
    while ($p = mysqli_fetch_assoc($all_products_res)) {
        $p['dist_km'] = getDistanceKm($user_lat, $user_lng, $p['location_lat'] ?? 0, $p['location_lng'] ?? 0);
        if ($selected_radius > 0 && ($p['dist_km'] === null || $p['dist_km'] > $selected_radius)) {
            continue;
        }
        $catalog_list[] = $p;
    }
}
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

                <!-- Card View / Grid View Button Icon Added -->
                <a href="?view=<?php echo $view_mode === 'map' ? 'grid' : 'map'; ?><?php echo $item_id ? '&id='.$item_id : ''; ?>" 
                   class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-lg transition ml-1" title="Card View">
                    <i class="fa-solid <?php echo $view_mode === 'map' ? 'fa-table-cells text-emerald-600' : 'fa-table-cells text-emerald-600'; ?>"></i>
                    <span><?php echo $view_mode === 'map' ? 'Card View' : 'Card View'; ?></span>
                </a>
            </div>

            <form method="GET" action="live_auction.php" class="flex items-center gap-2 flex-1 max-w-xl justify-end">
                <?php if ($item_id > 0): ?><input type="hidden" name="id" value="<?php echo $item_id; ?>"><?php endif; ?>
                <?php if ($show_all): ?><input type="hidden" name="all" value="1"><?php endif; ?>
                <?php if ($selected_category > 0): ?><input type="hidden" name="category_id" value="<?php echo $selected_category; ?>"><?php endif; ?>

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
        $display_price = !empty($current_item['current_price']) && $current_item['current_price'] > 0 
            ? (float)$current_item['current_price'] 
            : (float)$current_item['starting_price'];
        $min_inc = (float)($current_item['min_increment'] > 0 ? $current_item['min_increment'] : 100.00);
        $next_min_bid = $display_price + $min_inc;
        $reserve_met = (empty($current_item['reserve_price']) || $display_price >= (float)$current_item['reserve_price']);
        
        $gallery_images = [];
        if (!empty($current_item['featured_image'])) $gallery_images[] = $current_item['featured_image'];
        if (!empty($current_item['images'])) {
            foreach (explode(',', $current_item['images']) as $img_path) {
                $trimmed = trim($img_path);
                if (!empty($trimmed) && !in_array($trimmed, $gallery_images)) $gallery_images[] = $trimmed;
            }
        }
        if (empty($gallery_images)) $gallery_images[] = "https://images.unsplash.com/photo-1552519507-da3b142c6e3d?auto=format&fit=crop&w=800&q=80";
        $main_img = $gallery_images[0];
        $is_owner_seller = ($role === 'seller' && (int)($current_item['seller_id'] ?? 0) === $user_id) || $role === 'admin';
        $dist_km = getDistanceKm($user_lat, $user_lng, $current_item['location_lat'] ?? 0, $current_item['location_lng'] ?? 0);
    ?>
    <section class="bg-white border border-slate-200 rounded-2xl overflow-hidden shadow-sm">
        <div class="grid grid-cols-1 lg:grid-cols-12">
            <div class="lg:col-span-5 p-5 bg-slate-50/70 border-b lg:border-b-0 lg:border-r border-slate-200 flex flex-col justify-between">
                <div class="space-y-3">
                    <div class="relative h-64 md:h-80 bg-slate-200 rounded-xl overflow-hidden shadow-inner group">
                        <img id="mainImage" src="<?php echo htmlspecialchars($main_img); ?>" alt="<?php echo htmlspecialchars($current_item['title']); ?>" class="w-full h-full object-cover transition duration-300">
                        
                        <div class="absolute top-3 left-3 flex items-center gap-2 px-3 py-1 rounded-full bg-rose-600/90 backdrop-blur-sm text-white text-[11px] font-bold uppercase tracking-wider shadow">
                            <span class="w-2 h-2 rounded-full bg-white live-dot"></span> Live Auction
                        </div>

                        <button type="button" onclick="toggleWatchlist(<?php echo $current_item['id']; ?>)" id="watchlistBtn" 
                                class="absolute top-3 right-3 w-9 h-9 rounded-full bg-white/90 hover:bg-white text-rose-500 shadow flex items-center justify-center transition cursor-pointer" title="Watchlist">
                            <i class="fa-<?php echo $is_watched ? 'solid' : 'regular'; ?> fa-heart text-sm" id="watchlistIcon"></i>
                        </button>

                        <?php if (count($gallery_images) > 1): ?>
                            <button id="prevBtn" type="button" class="absolute left-2.5 top-1/2 -translate-y-1/2 bg-black/40 hover:bg-black/70 text-white w-9 h-9 rounded-full transition flex items-center justify-center backdrop-blur-sm cursor-pointer" aria-label="Previous image">
                                <i class="fa-solid fa-chevron-left text-xs"></i>
                            </button>
                            <button id="nextBtn" type="button" class="absolute right-2.5 top-1/2 -translate-y-1/2 bg-black/40 hover:bg-black/70 text-white w-9 h-9 rounded-full transition flex items-center justify-center backdrop-blur-sm cursor-pointer" aria-label="Next image">
                                <i class="fa-solid fa-chevron-right text-xs"></i>
                            </button>
                        <?php endif; ?>

                        <div class="absolute bottom-3 left-3 right-3 flex items-center justify-between px-3 py-1.5 rounded-lg bg-slate-900/80 backdrop-blur-xs text-white text-xs">
                            <span class="flex items-center gap-1.5 font-medium truncate">
                                📍 <?php echo htmlspecialchars($current_item['location_area'] ?? ($current_item['location_city'] ?? 'Qasimabad')); ?>
                            </span>
                            <?php if ($dist_km !== null): ?>
                                <span class="font-bold text-emerald-400 shrink-0">🚗 <?php echo $dist_km; ?> km away</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if (count($gallery_images) > 1): ?>
                        <div class="thumbnail-row flex items-center gap-2.5 overflow-x-auto pb-1 pt-1">
                            <?php foreach ($gallery_images as $index => $img): ?>
                                <img src="<?php echo htmlspecialchars($img); ?>" 
                                     alt="Thumbnail <?php echo $index + 1; ?>"
                                     onclick="setMainImage('<?php echo htmlspecialchars(addslashes($img)); ?>', this)" 
                                     class="thumbnail-img w-16 h-16 rounded-lg object-cover cursor-pointer border-2 transition shrink-0 <?php echo $index === 0 ? 'active-thumb border-emerald-600' : 'border-slate-200 hover:border-slate-400'; ?>">
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="mt-4 pt-4 border-t border-slate-200 grid grid-cols-2 gap-3 text-xs">
                    <div class="bg-white p-2.5 rounded-lg border border-slate-200/80">
                        <span class="text-slate-400 block font-medium">Lot Identifier</span>
                        <p class="font-bold text-slate-800 mt-0.5">#<?php echo htmlspecialchars($current_item['auction_code'] ?? ('LOT-'.$current_item['id'])); ?></p>
                    </div>
                    <div class="bg-white p-2.5 rounded-lg border border-slate-200/80">
                        <span class="text-slate-400 block font-medium">Verified Seller</span>
                        <p class="font-bold text-slate-800 mt-0.5"><?php echo htmlspecialchars($current_item['seller_name'] ?? 'Authorized Vendor'); ?></p>
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
                            <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-slate-900 mt-2">
                                <?php echo htmlspecialchars($current_item['title']); ?>
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
                        <?php echo htmlspecialchars($current_item['description']); ?>
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
                            <?php if ($bids_history_res && mysqli_num_rows($bids_history_res) > 0): ?>
                                <?php while($bid = mysqli_fetch_assoc($bids_history_res)): 
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
                                <?php endwhile; ?>
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
                                
                                <button type="submit" name="accept_bid" onclick="return confirm('Accept the highest bid and mark this auction as completed?');" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs rounded-lg transition shadow-xs flex items-center gap-1.5 cursor-pointer">
                                    <i class="fa-solid fa-check"></i> Accept Top Bid
                                </button>
                                
                                <button type="submit" name="reject_bid" onclick="return confirm('Reject bids and close this auction?');" class="px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white font-bold text-xs rounded-lg transition shadow-xs flex items-center gap-1.5 cursor-pointer">
                                    <i class="fa-solid fa-ban"></i> Reject & Close
                                </button>
                                
                                <button type="submit" name="delete_item" onclick="return confirm('Permanently delete this lot listing?');" class="px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white font-bold text-xs rounded-lg transition shadow-xs flex items-center gap-1.5 cursor-pointer">
                                    <i class="fa-solid fa-trash"></i> Delete Lot
                                </button>
                            </form>
                        </div>
                    <?php elseif ($current_item['status'] === 'active'): ?>
                        <div class="space-y-3">
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
                    $p_price = $prod['current_price'] > 0 ? $prod['current_price'] : $prod['starting_price'];
                    $is_highlighted = ($current_item && (int)$prod['id'] === (int)$current_item['id']);
                    $card_qs = 'id=' . $prod['id'];
                    $grid_img = !empty($prod['featured_image']) ? $prod['featured_image'] : "https://images.unsplash.com/photo-1552519507-da3b142c6e3d?auto=format&fit=crop&w=400&q=80";
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
                            <span class="text-[10px] text-slate-400 mt-1 block flex items-center gap-1">
                                <i class="fa-solid fa-gavel text-[9px]"></i> <?php echo (int)($prod['bid_count'] ?? 0); ?> bids placed
                            </span>
                        </div>
                        <div class="mt-3 pt-2 border-t border-slate-200">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-[11px] text-slate-400">Current</span>
                                <span class="text-xs font-bold text-slate-900">Rs. <?php echo number_format($p_price, 0); ?></span>
                            </div>
                            <div class="flex items-center gap-1.5">
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
        </div>
    </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
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
    var currentImgIndex = 0;
    var galleryImages = <?php echo json_encode($gallery_images ?? []); ?>;
    function setMainImage(src, thumb) {
        var mainImg = document.getElementById('mainImage');
        if (mainImg) mainImg.src = src;
        var thumbnails = document.querySelectorAll('.thumbnail-row .thumbnail-img');
        thumbnails.forEach(function(img) {
            img.classList.remove('active-thumb', 'border-emerald-600');
            img.classList.add('border-slate-200');
        });
        if (thumb) {
            thumb.classList.remove('border-slate-200');
            thumb.classList.add('active-thumb', 'border-emerald-600');
        }
    }
    <?php if (isset($gallery_images) && count($gallery_images) > 1): ?>
    var prevBtn = document.getElementById('prevBtn');
    var nextBtn = document.getElementById('nextBtn');
    if (prevBtn) {
        prevBtn.addEventListener('click', function() {
            currentImgIndex = (currentImgIndex - 1 + galleryImages.length) % galleryImages.length;
            var thumbs = document.querySelectorAll('.thumbnail-row .thumbnail-img');
            setMainImage(galleryImages[currentImgIndex], thumbs[currentImgIndex]);
        });
    }
    if (nextBtn) {
        nextBtn.addEventListener('click', function() {
            currentImgIndex = (currentImgIndex + 1) % galleryImages.length;
            var thumbs = document.querySelectorAll('.thumbnail-row .thumbnail-img');
            setMainImage(galleryImages[currentImgIndex], thumbs[currentImgIndex]);
        });
    }
    <?php endif; ?>
    var endTimeMs = new Date("<?php echo $current_item['end_time'] ?? ''; ?>").getTime();
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
    var currentLotId = <?php echo (int)($current_item['id'] ?? 0); ?>;
    function pollAuctionData() {
        if (!currentLotId) return;
        fetch('live_auction.php?ajax=1&action=poll_status&item_id=' + currentLotId)
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('currentPriceDisplay').textContent = data.current_price;
                    document.getElementById('bidCountDisplay').textContent = data.bid_count;
                    document.getElementById('watcherCountDisplay').textContent = data.watcher_count;
                    document.getElementById('nextMinBidDisplay').textContent = 'Rs. ' + data.next_min_formatted;
                        var input = document.getElementById('bidAmountInput');
                            if (input) {
                                input.setAttribute('min', data.raw_next_min);
                                input.setAttribute('placeholder', 'Enter at least Rs. ' + data.next_min_formatted);
                            }
                        endTimeMs = new Date(data.end_time).getTime();

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
                    alert(data.error);
                }
            });
    }

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
                    alert(data.message || data.error);
                    if (data.success) pollAuctionData();
                });
        }
    }

    function confirmBuyItNow(itemId, price) {
        if (!confirm("Are you sure you want to purchase this lot immediately for Rs. " + Number(price).toLocaleString() + "? This will end the auction.")) return;
        var fd = new FormData();
        fd.append('action', 'buy_it_now');
        fd.append('item_id', itemId);

        fetch('live_auction.php?ajax=1', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                alert(data.message || data.error);
                if (data.success) window.location.reload();
            });
    }

    function openDetailsModal(itemId) {
        fetch('live_auction.php?ajax=1&action=get_modal_details&item_id=' + itemId)
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('modalLotCode').textContent = '#' + data.auction_code;
                    document.getElementById('modalImage').src = data.image;
                    document.getElementById('modalTitle').textContent = data.title;
                    document.getElementById('modalSeller').textContent = 'Seller: ' + data.seller_name;
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

                    document.getElementById('auctionDetailsModal').classList.add('is-open');
                    document.body.style.overflow = 'hidden';
                } else {
                    alert(data.error);
                }
            });
    }

    function closeDetailsModal() {
        document.getElementById('auctionDetailsModal').classList.remove('is-open');
        document.body.style.overflow = '';
    }

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