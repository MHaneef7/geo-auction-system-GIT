<?php
/**
 * AJAX Infinite Scroll & Lot Loader Endpoint
 * File: ajax/load_items.php
 * Geo Auction System
 *
 * Uses OOP PHP + PDO with pagination, soft-delete filtering, and 'Rs.' currency formatting.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/database.php';

$page        = max(1, (int)($_GET['page'] ?? 1));
$limit       = 12;
$offset      = ($page - 1) * $limit;
$category_id = (int)($_GET['category_id'] ?? 0);
$sort        = trim($_GET['sort'] ?? 'newest');

try {
    $pdo = Database::getPdo();

    // Build WHERE conditions
    $whereParts = ["i.status = 'active'", "(i.is_deleted = 0 OR i.is_deleted IS NULL)"];
    $params = [];

    if ($category_id > 0) {
        $whereParts[] = "i.category_id = :category_id";
        $params[':category_id'] = $category_id;
    }

    $whereSql = implode(' AND ', $whereParts);

    // Map sorting options
    $orderMap = [
        'newest'     => 'i.id DESC',
        'oldest'     => 'i.id ASC',
        'price_asc'  => 'i.current_price ASC',
        'price_desc' => 'i.current_price DESC',
        'ending'     => 'i.end_time ASC',
    ];
    $orderSql = $orderMap[$sort] ?? 'i.id DESC';

    // 1. Fetch total count
    $cntStmt = $pdo->prepare("SELECT COUNT(*) AS c FROM items i WHERE $whereSql");
    $cntStmt->execute($params);
    $totalCount = (int)($cntStmt->fetch(PDO::FETCH_ASSOC)['c'] ?? 0);

    // 2. Fetch page records
    $sql = "
        SELECT i.*, c.name AS category_name,
               (SELECT COUNT(*) FROM bids b WHERE b.item_id = i.id) AS bid_count
        FROM items i
        LEFT JOIN categories c ON c.id = i.category_id
        WHERE $whereSql
        ORDER BY $orderSql
        LIMIT :limit OFFSET :offset
    ";

    $stmt = $pdo->prepare($sql);
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $items = [];
    $now = time();
    foreach ($rows as $row) {
        $timeRemaining = 'Ended';
        if (!empty($row['end_time'])) {
            $endTs = strtotime($row['end_time']);
            $diff = $endTs - $now;
            if ($diff <= 0) {
                $timeRemaining = 'Ended';
            } elseif ($diff < 3600) {
                $timeRemaining = max(1, (int)round($diff / 60)) . 'm left';
            } elseif ($diff < 86400) {
                $timeRemaining = (int)round($diff / 3600) . 'h left';
            } else {
                $timeRemaining = (int)round($diff / 86400) . 'd left';
            }
        }

        $price = (float)($row['current_price'] > 0 ? $row['current_price'] : ($row['starting_price'] ?? 0));
        $img = !empty($row['featured_image']) ? $row['featured_image'] : 'assets/image/placeholder.png';

        $items[] = [
            'id'             => (int)$row['id'],
            'title'          => htmlspecialchars($row['title'], ENT_QUOTES, 'UTF-8'),
            'current_price'  => 'Rs. ' . number_format($price, 2),
            'price_raw'      => $price,
            'bid_count'      => (int)($row['bid_count'] ?? 0),
            'time_remaining' => $timeRemaining,
            'category'       => htmlspecialchars($row['category_name'] ?? '', ENT_QUOTES, 'UTF-8'),
            'image'          => $img,
            'url'            => 'live_auction.php?id=' . (int)$row['id'],
        ];
    }

    echo json_encode([
        'success'   => true,
        'items'     => $items,
        'has_more'  => ($offset + count($items)) < $totalCount,
        'page'      => $page,
        'total'     => $totalCount
    ]);

} catch (\Throwable $e) {
    error_log("ajax/load_items.php Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'items' => [], 'has_more' => false, 'message' => 'Failed to load items.']);
}
exit();
