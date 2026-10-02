<?php
// ajax/search.php - Live search / autocomplete for auction items
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

include_once '../includes/database.php';

$q = isset($_GET['q']) ? mysqli_real_escape_string($conn, trim($_GET['q'])) : '';

if (strlen($q) < 2) {
    echo json_encode(['success' => true, 'results' => []]);
    exit;
}

$term = '%' . $q . '%';
$stmt = mysqli_prepare($conn, "
    SELECT i.id, i.title, i.current_price, i.starting_price, i.status,
           c.name AS category_name
    FROM items i
    LEFT JOIN categories c ON c.id = i.category_id
    WHERE (i.title LIKE ? OR i.description LIKE ?)
      AND i.status = 'active'
    ORDER BY i.created_at DESC
    LIMIT 10
");

$results = [];
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "ss", $term, $term);
    mysqli_stmt_execute($stmt);
    $results_res = mysqli_stmt_get_result($stmt);
    if ($results_res) {
        while ($row = mysqli_fetch_assoc($results_res)) {
            $price = (float)($row['current_price'] ?? $row['starting_price']);
            $results[] = [
                'id'       => $row['id'],
                'title'    => $row['title'],
                'price'    => 'Rs. ' . number_format($price, 2),
                'category' => $row['category_name'] ?? 'General',
                'url'      => 'item.php?id=' . $row['id'],
            ];
        }
    }
    mysqli_stmt_close($stmt);
}

echo json_encode(['success' => true, 'results' => $results, 'query' => $q]);
exit;
