<?php
// ajax/notification.php - Fetch unread notifications for logged-in user
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

include_once '../includes/database.php';
include_once '../includes/auth.php';

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'count' => 0, 'notifications' => []]);
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$action  = $_GET['action'] ?? 'fetch';

if ($action === 'mark_read') {
    // Mark all as read
    $stmt = mysqli_prepare($conn, "UPDATE notifications SET is_read = 1 WHERE user_id = ?");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'mark_one' && isset($_GET['id'])) {
    $nid = (int)$_GET['id'];
    $stmt = mysqli_prepare($conn, "UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "ii", $nid, $user_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
    echo json_encode(['success' => true]);
    exit;
}

// Fetch recent notifications
$stmt = mysqli_prepare($conn, "
    SELECT id, type, title, message, is_read, created_at
    FROM notifications
    WHERE user_id = ?
    ORDER BY id DESC
    LIMIT 15
");
$notifications = [];
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $notes_res = mysqli_stmt_get_result($stmt);
    if ($notes_res) {
        while ($row = mysqli_fetch_assoc($notes_res)) {
            $notifications[] = [
                'id'         => $row['id'],
                'type'       => $row['type'],
                'title'      => $row['title'],
                'message'    => $row['message'] ?? $row['content'] ?? '',
                'is_read'    => (int)$row['is_read'],
                'created_at' => $row['created_at'],
                'time_ago'   => time_ago($row['created_at']),
            ];
        }
    }
    mysqli_stmt_close($stmt);
}

// Count unread
$u_stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS c FROM notifications WHERE user_id = ? AND is_read = 0");
$unread = 0;
if ($u_stmt) {
    mysqli_stmt_bind_param($u_stmt, "i", $user_id);
    mysqli_stmt_execute($u_stmt);
    $u_res = mysqli_stmt_get_result($u_stmt);
    $unread = mysqli_fetch_assoc($u_res)['c'] ?? 0;
    mysqli_stmt_close($u_stmt);
}

function time_ago($datetime) {
    $diff = time() - strtotime($datetime);
    if ($diff < 60)     return 'just now';
    if ($diff < 3600)   return round($diff / 60) . ' min ago';
    if ($diff < 86400)  return round($diff / 3600) . ' hr ago';
    return round($diff / 86400) . ' days ago';
}

echo json_encode([
    'success'       => true,
    'count'         => (int)$unread,
    'notifications' => $notifications,
]);
exit;
