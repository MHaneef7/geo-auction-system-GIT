<?php
/**
 * ============================================================================
 * AJAX COMMENT & DISCUSSION ENDPOINT
 * File: ajax_comment.php
 * Geo Auction System
 * ============================================================================
 * 
 * Handles:
 * 1. POST: add_comment / post_comment (Save user comment on an auction lot)
 * 2. GET/POST: fetch_comments (Fetch live comment stream for an auction item)
 * 3. GET/POST: get_card_comments (Fetch latest 3-5 comments for offcanvas drawer)
 */

ini_set('display_errors', 0);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

// Include Database connection
if (file_exists(__DIR__ . '/includes/database.php')) {
    require_once __DIR__ . '/includes/database.php';
} elseif (file_exists(__DIR__ . '/../includes/database.php')) {
    require_once __DIR__ . '/../includes/database.php';
}

if (!isset($conn) || !($conn instanceof mysqli) || $conn->connect_error) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Database connection unavailable.'
    ]);
    exit();
}

$is_authenticated = isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
$user_id = $is_authenticated ? (int)$_SESSION['user_id'] : 0;
$action = $_POST['action'] ?? $_GET['action'] ?? '';

/**
 * Helper: Relative Human-Readable Time Calculation
 */
function getCommentRelativeTime($datetime) {
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

// ----------------------------------------------------------------------------
// 1. ADD / POST COMMENT
// ----------------------------------------------------------------------------
if ($action === 'add_comment' || $action === 'post_comment') {
    if (!$is_authenticated || $user_id <= 0) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Please sign in to participate in the auction discussion.',
            'redirect' => 'login.php'
        ]);
        exit();
    }

    $raw_item_id = trim((string)($_POST['item_id'] ?? ''));
    $item_id = (int)$raw_item_id;
    $raw_comment = trim((string)($_POST['comment'] ?? ''));

    if ($item_id <= 0) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid auction lot requested.'
        ]);
        exit();
    }

    if (empty($raw_comment)) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Comment cannot be empty.'
        ]);
        exit();
    }

    if (mb_strlen($raw_comment, 'UTF-8') > 500) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Comment is too long (maximum 500 characters).'
        ]);
        exit();
    }

    // Sanitize to prevent XSS attacks while keeping readability
    $sanitized_comment = htmlspecialchars($raw_comment, ENT_QUOTES, 'UTF-8');

    // Verify lot exists and is not deleted
    $chk_item = mysqli_prepare($conn, "SELECT id, title, seller_id, status, is_deleted FROM items WHERE id = ?");
    if (!$chk_item) {
        echo json_encode(['status' => 'error', 'message' => 'Failed to prepare lot verification query.']);
        exit();
    }
    mysqli_stmt_bind_param($chk_item, "i", $item_id);
    mysqli_stmt_execute($chk_item);
    $item_res = mysqli_stmt_get_result($chk_item);
    $item_row = mysqli_fetch_assoc($item_res);
    mysqli_stmt_close($chk_item);

    if (!$item_row || !empty($item_row['is_deleted'])) {
        echo json_encode([
            'status' => 'error',
            'message' => 'This auction lot does not exist or has been withdrawn.'
        ]);
        exit();
    }

    // Insert using prepared statement
    $stmt = mysqli_prepare($conn, "INSERT INTO item_comments (item_id, user_id, comment, created_at) VALUES (?, ?, ?, NOW())");
    if (!$stmt) {
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $conn->error]);
        exit();
    }

    mysqli_stmt_bind_param($stmt, "iis", $item_id, $user_id, $sanitized_comment);
    if (!mysqli_stmt_execute($stmt)) {
        echo json_encode(['status' => 'error', 'message' => 'Failed to save comment: ' . $stmt->error]);
        mysqli_stmt_close($stmt);
        exit();
    }
    $new_comment_id = mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);

    // Fetch commenter user details
    $u_stmt = mysqli_prepare($conn, "SELECT id, name, username, avatar, role FROM users WHERE id = ?");
    mysqli_stmt_bind_param($u_stmt, "i", $user_id);
    mysqli_stmt_execute($u_stmt);
    $user_data = mysqli_fetch_assoc(mysqli_stmt_get_result($u_stmt));
    mysqli_stmt_close($u_stmt);

    $display_name = !empty($user_data['name']) ? $user_data['name'] : (!empty($user_data['username']) ? $user_data['username'] : 'Verified Bidder');
    $initial = strtoupper(substr($display_name, 0, 1));
    $avatar = !empty($user_data['avatar']) ? $user_data['avatar'] : '';
    $user_role = strtolower(trim($user_data['role'] ?? 'buyer'));
    $is_seller_reply = ((int)$item_row['seller_id'] === $user_id);

    // Get updated total comment count
    $cnt_stmt = mysqli_prepare($conn, "SELECT COUNT(id) AS total FROM item_comments WHERE item_id = ?");
    mysqli_stmt_bind_param($cnt_stmt, "i", $item_id);
    mysqli_stmt_execute($cnt_stmt);
    $total_comments = (int)mysqli_fetch_assoc(mysqli_stmt_get_result($cnt_stmt))['total'];
    mysqli_stmt_close($cnt_stmt);

    // Notify seller if another user commented
    if (!$is_seller_reply && !empty($item_row['seller_id'])) {
        $seller_id = (int)$item_row['seller_id'];
        $n_title = "New Question on #" . $item_id;
        $n_msg = htmlspecialchars($display_name) . " commented on '" . htmlspecialchars($item_row['title']) . "'";
        $n_link = "live_auction.php?id=" . $item_id;
        try {
            $notif = mysqli_prepare($conn, "INSERT INTO user_notifications (user_id, type, title, message, link, is_read, created_at) VALUES (?, 'comment', ?, ?, ?, 0, NOW())");
            if (!$notif) {
                $notif = mysqli_prepare($conn, "INSERT INTO notifications (user_id, type, title, message, link, is_read, created_at) VALUES (?, 'comment', ?, ?, ?, 0, NOW())");
            }
            if ($notif) {
                mysqli_stmt_bind_param($notif, "isss", $seller_id, $n_title, $n_msg, $n_link);
                mysqli_stmt_execute($notif);
                mysqli_stmt_close($notif);
            }
        } catch (Exception $ne) {}
    }

    echo json_encode([
        'status' => 'success',
        'message' => 'Comment posted successfully.',
        'total_comments' => $total_comments,
        'comment' => [
            'id' => (int)$new_comment_id,
            'item_id' => $item_id,
            'user_id' => $user_id,
            'user_name' => htmlspecialchars($display_name),
            'username' => htmlspecialchars($user_data['username'] ?? ''),
            'initial' => $initial,
            'avatar' => htmlspecialchars($avatar),
            'user_role' => $user_role,
            'is_seller' => $is_seller_reply,
            'comment' => $sanitized_comment,
            'created_at' => date('Y-m-d H:i:s'),
            'time_ago' => 'Just now'
        ]
    ]);
    exit();
}

// ----------------------------------------------------------------------------
// 2. FETCH COMMENTS STREAM FOR AN ITEM (Live stream & Polling)
// ----------------------------------------------------------------------------
if ($action === 'fetch_comments' || $action === 'get_comments') {
    $item_id = (int)($_GET['item_id'] ?? $_POST['item_id'] ?? 0);
    $last_id = (int)($_GET['last_id'] ?? $_POST['last_id'] ?? 0);

    if ($item_id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid lot ID']);
        exit();
    }

    $seller_id = 0;
    $s_chk = mysqli_prepare($conn, "SELECT seller_id FROM items WHERE id = ?");
    if ($s_chk) {
        mysqli_stmt_bind_param($s_chk, "i", $item_id);
        mysqli_stmt_execute($s_chk);
        $s_row = mysqli_fetch_assoc(mysqli_stmt_get_result($s_chk));
        if ($s_row) $seller_id = (int)$s_row['seller_id'];
        mysqli_stmt_close($s_chk);
    }

    $sql = "
        SELECT c.*, u.name AS user_name, u.username, u.avatar, u.role AS user_role
        FROM item_comments c
        LEFT JOIN users u ON c.user_id = u.id
        WHERE c.item_id = ?
    ";
    if ($last_id > 0) {
        $sql .= " AND c.id > ?";
    }
    $sql .= " ORDER BY c.created_at ASC, c.id ASC LIMIT 100";

    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        echo json_encode(['status' => 'error', 'message' => 'Failed to load comments']);
        exit();
    }

    if ($last_id > 0) {
        mysqli_stmt_bind_param($stmt, "ii", $item_id, $last_id);
    } else {
        mysqli_stmt_bind_param($stmt, "i", $item_id);
    }

    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $comments = [];

    while ($r = mysqli_fetch_assoc($res)) {
        $display_name = !empty($r['user_name']) ? $r['user_name'] : (!empty($r['username']) ? $r['username'] : 'Bidder #' . $r['user_id']);
        $initial = strtoupper(substr($display_name, 0, 1));
        $is_seller = ($seller_id > 0 && (int)$r['user_id'] === $seller_id);

        $comments[] = [
            'id' => (int)$r['id'],
            'item_id' => (int)$r['item_id'],
            'user_id' => (int)$r['user_id'],
            'user_name' => htmlspecialchars($display_name),
            'username' => htmlspecialchars($r['username'] ?? ''),
            'initial' => $initial,
            'avatar' => htmlspecialchars($r['avatar'] ?? ''),
            'user_role' => strtolower(trim($r['user_role'] ?? 'buyer')),
            'is_seller' => $is_seller,
            'comment' => htmlspecialchars($r['comment']),
            'created_at' => $r['created_at'],
            'time_ago' => getCommentRelativeTime($r['created_at'])
        ];
    }
    mysqli_stmt_close($stmt);

    // Total count for badge sync
    $cnt_stmt = mysqli_prepare($conn, "SELECT COUNT(id) AS total FROM item_comments WHERE item_id = ?");
    mysqli_stmt_bind_param($cnt_stmt, "i", $item_id);
    mysqli_stmt_execute($cnt_stmt);
    $total_count = (int)mysqli_fetch_assoc(mysqli_stmt_get_result($cnt_stmt))['total'];
    mysqli_stmt_close($cnt_stmt);

    echo json_encode([
        'status' => 'success',
        'item_id' => $item_id,
        'total_comments' => $total_count,
        'count' => count($comments),
        'comments' => $comments
    ]);
    exit();
}

// ----------------------------------------------------------------------------
// 3. GET CARD DISCUSSION PREVIEW (Latest 2-3 Comments for Offcanvas Drawer)
// ----------------------------------------------------------------------------
if ($action === 'get_card_comments') {
    $item_id = (int)($_GET['item_id'] ?? 0);
    if ($item_id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid lot ID']);
        exit();
    }

    // Fetch lot title & seller_id
    $lot_title = 'Auction Lot #' . $item_id;
    $seller_id = 0;
    $item_info_stmt = mysqli_prepare($conn, "SELECT title, seller_id FROM items WHERE id = ?");
    if ($item_info_stmt) {
        mysqli_stmt_bind_param($item_info_stmt, "i", $item_id);
        mysqli_stmt_execute($item_info_stmt);
        $ii_res = mysqli_stmt_get_result($item_info_stmt);
        if ($ii_row = mysqli_fetch_assoc($ii_res)) {
            if (!empty($ii_row['title'])) $lot_title = $ii_row['title'];
            if (!empty($ii_row['seller_id'])) $seller_id = (int)$ii_row['seller_id'];
        }
        mysqli_stmt_close($item_info_stmt);
    }

    $stmt = mysqli_prepare($conn, "
        SELECT c.*, u.name AS user_name, u.username, u.avatar, u.role AS user_role
        FROM item_comments c
        LEFT JOIN users u ON c.user_id = u.id
        WHERE c.item_id = ?
        ORDER BY c.created_at DESC
        LIMIT 5
    ");
    if (!$stmt) {
        echo json_encode(['status' => 'error', 'message' => 'Query error']);
        exit();
    }
    mysqli_stmt_bind_param($stmt, "i", $item_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);

    $comments = [];

    while ($r = mysqli_fetch_assoc($res)) {
        $display_name = !empty($r['user_name']) ? $r['user_name'] : (!empty($r['username']) ? $r['username'] : 'Bidder');
        
        $comments[] = [
            'id' => (int)$r['id'],
            'user_name' => htmlspecialchars($display_name),
            'initial' => strtoupper(substr($display_name, 0, 1)),
            'avatar' => htmlspecialchars($r['avatar'] ?? ''),
            'is_seller' => ($seller_id > 0 && (int)$r['user_id'] === $seller_id),
            'comment' => htmlspecialchars($r['comment']),
            'time_ago' => getCommentRelativeTime($r['created_at'])
        ];
    }
    mysqli_stmt_close($stmt);

    // Reverse so latest is at bottom
    $comments = array_reverse($comments);

    $cnt_stmt = mysqli_prepare($conn, "SELECT COUNT(id) AS total FROM item_comments WHERE item_id = ?");
    mysqli_stmt_bind_param($cnt_stmt, "i", $item_id);
    mysqli_stmt_execute($cnt_stmt);
    $total_count = (int)mysqli_fetch_assoc(mysqli_stmt_get_result($cnt_stmt))['total'];
    mysqli_stmt_close($cnt_stmt);

    echo json_encode([
        'status' => 'success',
        'item_id' => $item_id,
        'title' => htmlspecialchars($lot_title),
        'total_comments' => $total_count,
        'comments' => $comments,
        'is_authenticated' => $is_authenticated
    ]);
    exit();
}

echo json_encode(['status' => 'error', 'message' => 'Unknown action requested.']);
exit();
