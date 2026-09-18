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
    mysqli_query($conn, "UPDATE notifications SET is_read = 1 WHERE user_id = $user_id");
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'mark_one' && isset($_GET['id'])) {
    $nid = (int)$_GET['id'];
    mysqli_query($conn, "UPDATE notifications SET is_read = 1 WHERE id = $nid AND user_id = $user_id");
    echo json_encode(['success' => true]);
    exit;
}

// Fetch recent notifications
$notes_res = mysqli_query($conn, "
    SELECT id, type, title, message, is_read, created_at
    FROM notifications
    WHERE user_id = $user_id
    ORDER BY id DESC
    LIMIT 15
");

$notifications = [];
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

// Count unread
$unread_res = mysqli_query($conn, "SELECT COUNT(*) AS c FROM notifications WHERE user_id = $user_id AND is_read = 0");
$unread     = mysqli_fetch_assoc($unread_res)['c'] ?? 0;

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
