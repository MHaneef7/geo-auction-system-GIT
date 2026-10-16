<?php
/**
 * Live Search Autocomplete Endpoint
 * File: ajax/search.php
 * Geo Auction System
 *
 * Uses OOP PHP + PDO with parameter binding.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/database.php';

$q = trim($_GET['q'] ?? '');

if (mb_strlen($q) < 2) {
    echo json_encode(['success' => true, 'results' => [], 'query' => $q]);
    exit();
}

try {
    $pdo = Database::getPdo();
    $stmt = $pdo->prepare("
        SELECT i.id, i.title, i.current_price, i.starting_price, i.status, i.featured_image,
               c.name AS category_name
        FROM items i
        LEFT JOIN categories c ON c.id = i.category_id
        WHERE (i.title LIKE :q1 OR i.description LIKE :q2)
          AND i.status = 'active'
          AND (i.is_deleted = 0 OR i.is_deleted IS NULL)
        ORDER BY i.id DESC
        LIMIT 10
    ");
    $param = '%' . $q . '%';
    $stmt->execute([':q1' => $param, ':q2' => $param]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $results = [];
    foreach ($rows as $row) {
        $price = (float)($row['current_price'] > 0 ? $row['current_price'] : ($row['starting_price'] ?? 0));
        $results[] = [
            'id'       => (int)$row['id'],
            'title'    => htmlspecialchars($row['title'], ENT_QUOTES, 'UTF-8'),
            'price'    => 'Rs. ' . number_format($price, 2),
            'category' => htmlspecialchars($row['category_name'] ?? '', ENT_QUOTES, 'UTF-8'),
            'image'    => $row['featured_image'] ?? 'assets/image/placeholder.png',
            'url'      => 'live_auction.php?id=' . (int)$row['id'],
        ];
    }

    echo json_encode(['success' => true, 'results' => $results, 'query' => $q]);
} catch (\Throwable $e) {
    error_log("ajax/search.php error: " . $e->getMessage());
    echo json_encode(['success' => false, 'results' => [], 'message' => 'Search error']);
}
exit();
