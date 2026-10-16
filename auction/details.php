<?php
// auction/details.php - Auction item details page (redirect wrapper to root item.php)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include_once '../includes/database.php';
include_once '../includes/auth.php';

$id    = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$error = null;
$item  = null;

if ($id > 0) {
    try {
        $stmt = $pdo->prepare("
            SELECT i.*, u.name AS seller_name, u.email AS seller_email, c.name AS category_name
            FROM items i
            LEFT JOIN users u ON u.id = i.seller_id
            LEFT JOIN categories c ON c.id = i.category_id
            WHERE i.id = :id
        ");
        $stmt->execute([':id' => $id]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$item) {
            $error = 'Item not found.';
        }
    } catch (\Throwable $e) {
        $error = 'Database error fetching item.';
    }
} else {
    $error = 'Invalid item ID.';
}

// If item exists, redirect to the main item page
if ($item) {
    header("Location: ../item.php?id=$id");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Item Not Found - Geo Auction</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; font-family: 'Segoe UI', sans-serif; }
    </style>
</head>
<body class="bg-light">
    <div class="container my-5 text-center">
        <div class="py-5">
            <div class="fs-1 text-danger mb-3"><i class="fas fa-exclamation-triangle"></i></div>
            <h2 class="fw-bold mb-2">Item Not Found</h2>
            <p class="text-muted mb-4"><?php echo htmlspecialchars($error ?? 'The requested auction item could not be found.'); ?></p>
            <a href="../live_auction.php" class="btn btn-primary me-2">
                <i class="fas fa-gavel me-2"></i>Browse Auctions
            </a>
            <a href="../index.php" class="btn btn-outline-secondary">
                <i class="fas fa-home me-2"></i>Go Home
            </a>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>