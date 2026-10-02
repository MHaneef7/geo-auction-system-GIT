<?php
// ajax/load_items.php - Load items for infinite scroll / AJAX pagination
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

include_once '../includes/database.php';

$page        = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit       = 12;
$offset      = ($page - 1) * $limit;
$category_id = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;
$sort        = isset($_GET['sort']) ? mysqli_real_escape_string($conn, $_GET['sort']) : 'newest';

// Build WHERE
$where = "WHERE i.status = 'active' AND (i.is_deleted = 0 OR i.is_deleted IS NULL)";
$params = [];
$types = '';

if ($category_id > 0) {
    $where .= " AND i.category_id = ?";
    $params[] = $category_id;
    $types .= 'i';
}

// Build ORDER
$order_map = [
    'newest'     => 'i.created_at DESC',
    'oldest'     => 'i.created_at ASC',
    'price_asc'  => 'i.current_price ASC',
    'price_desc' => 'i.current_price DESC',
    'ending'     => 'i.end_time ASC',
];
$order = isset($order_map[$sort]) ? $order_map[$sort] : 'i.created_at DESC';

$sql = "
    SELECT i.*, c.name AS category_name,
           (SELECT COUNT(*) FROM bids b WHERE b.item_id = i.id) AS bid_count
    FROM items i
    LEFT JOIN categories c ON c.id = i.category_id
    $where
    ORDER BY $order
    LIMIT ? OFFSET ?
";

$item_params = $params;
$item_types = $types . 'ii';
$item_params[] = $limit;
$item_params[] = $offset;

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, $item_types, ...$item_params);
mysqli_stmt_execute($stmt);
$items_res = mysqli_stmt_get_result($stmt);

$items = [];
if ($items_res) {
    while ($row = mysqli_fetch_assoc($items_res)) {
        // Time remaining
        $time_remaining = '';
        if ($row['end_time']) {
            $diff = strtotime($row['end_time']) - time();
            if ($diff <= 0) {
                $time_remaining = 'Ended';
            } elseif ($diff < 3600) {
                $time_remaining = round($diff / 60) . 'm left';
            } elseif ($diff < 86400) {
                $time_remaining = round($diff / 3600) . 'h left';
            } else {
                $time_remaining = round($diff / 86400) . 'd left';
            }
        }
        $items[] = [
            'id'            => $row['id'],
            'title'         => $row['title'],
            'current_price' => 'Rs. ' . number_format((float)($row['current_price'] ?? $row['starting_price']), 2),
            'bid_count'     => (int)$row['bid_count'],
            'time_remaining'=> $time_remaining,
            'category'      => $row['category_name'] ?? '',
            'image'         => $row['image'] ?? '',
            'url'           => '../live_auction.php?id=' . $row['id'],
        ];
    }
}
if ($stmt) mysqli_stmt_close($stmt);

// Total count for has_more check
$count_sql = "SELECT COUNT(*) AS c FROM items i $where";
if (!empty($params)) {
    $c_stmt = mysqli_prepare($conn, $count_sql);
    mysqli_stmt_bind_param($c_stmt, $types, ...$params);
    mysqli_stmt_execute($c_stmt);
    $count_res = mysqli_stmt_get_result($c_stmt);
    $total = mysqli_fetch_assoc($count_res)['c'] ?? 0;
    mysqli_stmt_close($c_stmt);
} else {
    $count_res = mysqli_query($conn, $count_sql);
    $total = mysqli_fetch_assoc($count_res)['c'] ?? 0;
}

echo json_encode([
    'success'  => true,
    'items'    => $items,
    'page'     => $page,
    'has_more' => ($page * $limit) < $total,
    'total'    => $total,
]);
exit;
