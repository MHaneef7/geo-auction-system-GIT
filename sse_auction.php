<?php
/**
 * sse_auction.php - Dedicated Real-Time Server-Sent Events (SSE) Stream
 * Provides push updates for live auction price, bids, timers, and watchers.
 */
ini_set('display_errors', 0);
error_reporting(0);

// Close session immediately to avoid blocking concurrent HTTP requests from the same client session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$auth_user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
session_write_close();

header('Content-Type: text/event-stream; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Connection: keep-alive');
header('X-Accel-Buffering: no');

// Clean and flush any existing output buffers
while (ob_get_level() > 0) {
    ob_end_clean();
}

if (file_exists(__DIR__ . '/includes/database.php')) {
    include_once __DIR__ . '/includes/database.php';
} elseif (file_exists(__DIR__ . '/../includes/database.php')) {
    include_once __DIR__ . '/../includes/database.php';
}

$has_db = isset($conn) && $conn instanceof mysqli && !$conn->connect_error;
$item_id = (int)($_GET['item_id'] ?? 0);

if (!$has_db || $item_id <= 0) {
    echo "event: error\n";
    echo "data: " . json_encode(['error' => 'Invalid lot request or database unavailable']) . "\n\n";
    flush();
    exit();
}

$start_time = time();
$max_run_seconds = 25; // Clean recycling interval for HTTP connections
$last_state_hash = '';

function sendSseMessage($event, $data) {
    echo "event: {$event}\n";
    echo "data: " . json_encode($data) . "\n\n";
    if (ob_get_level() > 0) ob_flush();
    flush();
}

while (!connection_aborted() && (time() - $start_time < $max_run_seconds)) {
    // 1. Fetch live lot details
    $stmt = mysqli_prepare($conn, "
        SELECT i.*, 
        (SELECT COUNT(*) FROM wishlist WHERE item_id = i.id) AS watcher_count
        FROM items i WHERE i.id = ?
    ");
    if (!$stmt) break;
    mysqli_stmt_bind_param($stmt, "i", $item_id);
    mysqli_stmt_execute($stmt);
    $item = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if (!$item) {
        sendSseMessage('auction_update', ['success' => false, 'error' => 'Lot item not found']);
        break;
    }

    $now = time();
    $effective_end = (!empty($item['extended_time']) && $item['extended_time'] != '0000-00-00 00:00:00' && strtotime($item['extended_time']) > strtotime($item['end_time']))
        ? $item['extended_time']
        : $item['end_time'];
    $end_ts = strtotime($effective_end);
    $is_closed = ($item['status'] !== 'active') || ($now >= $end_ts);

    // 2. Fetch top 6 active bids
    $b_stmt = mysqli_prepare($conn, "
        SELECT b.id, b.user_id, b.bid_amount, b.created_at, b.bid_status, b.is_winning, b.status, u.name AS bidder_name 
        FROM bids b 
        JOIN users u ON b.user_id = u.id 
        WHERE b.item_id = ? AND b.bid_status NOT IN ('cancelled') AND b.status NOT IN ('retracted', 'voided')
        ORDER BY b.bid_amount DESC, b.id DESC LIMIT 6
    ");
    mysqli_stmt_bind_param($b_stmt, "i", $item_id);
    mysqli_stmt_execute($b_stmt);
    $b_res = mysqli_stmt_get_result($b_stmt);
    
    $history = [];
    $latest_bid_id = 0;
    $latest_bid_user_id = 0;
    $latest_bid_time = 0;
    $latest_bid_created_at = '';

    while ($b_row = mysqli_fetch_assoc($b_res)) {
        if ($latest_bid_id === 0) {
            $latest_bid_id = (int)$b_row['id'];
            $latest_bid_user_id = (int)$b_row['user_id'];
            $latest_bid_time = strtotime($b_row['created_at']);
            $latest_bid_created_at = $b_row['created_at'];
        }
        $name_parts = explode(' ', trim($b_row['bidder_name']));
        $masked = htmlspecialchars($name_parts[0]) . (count($name_parts) > 1 ? ' ' . substr($name_parts[1], 0, 1) . '***' : ' ***');
        $history[] = [
            'id' => (int)$b_row['id'],
            'user_id' => (int)$b_row['user_id'],
            'bidder' => $masked,
            'raw_name' => htmlspecialchars($b_row['bidder_name']),
            'amount' => number_format((float)$b_row['bid_amount'], 2),
            'raw_amount' => (float)$b_row['bid_amount'],
            'time' => date('M d, h:i A', strtotime($b_row['created_at'])),
            'created_at' => $b_row['created_at'],
            'initial' => strtoupper(substr($b_row['bidder_name'] ?? 'B', 0, 1))
        ];
    }
    mysqli_stmt_close($b_stmt);

    $curr_price = max((float)$item['current_price'], (float)$item['starting_price']);
    $min_inc = (float)($item['min_increment'] > 0 ? $item['min_increment'] : 100.00);
    $next_min = $curr_price + $min_inc;
    $reserve_met = (empty($item['reserve_price']) || $curr_price >= (float)$item['reserve_price']);

    // 3. Comments count
    $cm_cnt_stmt = mysqli_prepare($conn, "SELECT COUNT(id) AS total FROM item_comments WHERE item_id = ?");
    $total_comments = 0;
    if ($cm_cnt_stmt) {
        $cm_iid = (string)$item_id;
        mysqli_stmt_bind_param($cm_cnt_stmt, "s", $cm_iid);
        mysqli_stmt_execute($cm_cnt_stmt);
        $total_comments = (int)mysqli_fetch_assoc(mysqli_stmt_get_result($cm_cnt_stmt))['total'];
        mysqli_stmt_close($cm_cnt_stmt);
    }

    // 4. Generate State Fingerprint
    $current_state_hash = md5($curr_price . '|' . $item['bid_count'] . '|' . $item['watcher_count'] . '|' . $total_comments . '|' . $latest_bid_id . '|' . $effective_end . '|' . $item['status'] . '|' . ($item['is_deleted'] ?? 0));

    // Send payload only when data has changed or initial load
    if ($current_state_hash !== $last_state_hash) {
        $last_state_hash = $current_state_hash;

        $payload = [
            'success' => true,
            'server_time' => $now, // Authoritative server epoch timestamp for drift correction
            'item_id' => $item_id,
            'current_price' => number_format($curr_price, 2),
            'raw_price' => $curr_price,
            'min_increment' => number_format($min_inc, 2),
            'next_min_formatted' => number_format($next_min, 2),
            'raw_next_min' => $next_min,
            'bid_count' => (int)$item['bid_count'],
            'watcher_count' => (int)$item['watcher_count'],
            'total_comments' => $total_comments,
            'end_time' => $effective_end,
            'is_closed' => $is_closed,
            'is_deleted' => !empty($item['is_deleted']),
            'reserve_met' => $reserve_met,
            'history' => $history,
            'latest_bid_id' => $latest_bid_id,
            'latest_bid_user_id' => $latest_bid_user_id,
            'latest_bid_time' => $latest_bid_time,
            'latest_bid_created_at' => $latest_bid_created_at
        ];
        sendSseMessage('auction_update', $payload);
    } else {
        // Keep-alive heartbeat comment
        echo ": heartbeat {$now}\n\n";
        if (ob_get_level() > 0) ob_flush();
        flush();
    }

    sleep(1);
}
