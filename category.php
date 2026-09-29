<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include_once('includes/database.php');
include_once('includes/functions.php');

$cat_id = (int)($_GET['id'] ?? 0);
$cat_slug = trim($_GET['cat'] ?? '');
$category_name = 'All Categories';

$where_clauses = ["i.status = 'active'"];
$params = [];
$types = "";

if ($cat_id > 0) {
    $where_clauses[] = "i.category_id = ?";
    $types .= "i";
    $params[] = $cat_id;
    
    $c_stmt = mysqli_prepare($conn, "SELECT name FROM categories WHERE id = ? LIMIT 1");
    if ($c_stmt) {
        mysqli_stmt_bind_param($c_stmt, "i", $cat_id);
        mysqli_stmt_execute($c_stmt);
        $c_res = mysqli_stmt_get_result($c_stmt);
        if ($c_row = mysqli_fetch_assoc($c_res)) {
            $category_name = $c_row['name'];
        }
        mysqli_stmt_close($c_stmt);
    }
} elseif (!empty($cat_slug)) {
    $where_clauses[] = "(c.slug LIKE ? OR c.name LIKE ? OR i.title LIKE ?)";
    $types .= "sss";
    $term = "%" . $cat_slug . "%";
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
    $category_name = ucfirst($cat_slug);
}

$where_sql = implode(" AND ", $where_clauses);
$sql = "SELECT i.*, c.name AS category_name FROM items i LEFT JOIN categories c ON c.id = i.category_id WHERE $where_sql ORDER BY i.id DESC";

$stmt = mysqli_prepare($conn, $sql);
if ($stmt) {
    if (!empty($params)) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
} else {
    $result = false;
}

$is_logged = !empty($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Category: <?php echo htmlspecialchars($category_name); ?> - Geo Auction</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body style="background: #f8fafc; font-family: 'Inter', sans-serif;">

<div style="max-width: 1200px; margin: 30px auto; padding: 0 20px;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 24px;">
        <h2 style="font-size: 22px; font-weight: 800; color: #1a1a2e;">
            Category: <?php echo htmlspecialchars($category_name); ?>
        </h2>
        <a href="index.php" style="color: rgb(4 120 87); font-weight: 600; text-decoration: none;">← Back to Home</a>
    </div>

    <div class="auction-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 20px;">
        <?php if ($result && mysqli_num_rows($result) > 0): ?>
            <?php while ($item = mysqli_fetch_assoc($result)): 
                $time_left = get_time_remaining($item['end_time'] ?? date('Y-m-d H:i:s'));
                $img = !empty($item['featured_image']) ? $item['featured_image'] : 'assets/image/geo-auction.png';
            ?>
                <div class="auction-card" style="background:#fff; border-radius:8px; border:1px solid #e8e8e8; overflow:hidden;">
                    <div style="height:180px; background:#f0f0f0; overflow:hidden;">
                        <img src="<?php echo htmlspecialchars($img); ?>" 
                             alt="<?php echo htmlspecialchars($item['title']); ?>" 
                             style="width:100%; height:100%; object-fit:cover;"
                             onerror="this.onerror=null; this.src='assets/image/geo-auction.png';">
                    </div>
                    <div style="padding:16px;">
                        <h4 style="font-size:16px; font-weight:700; margin-bottom:8px; color:#1a1a2e;">
                            <?php echo htmlspecialchars($item['title']); ?>
                        </h4>
                        <p style="color:rgb(4 120 87); font-size:18px; font-weight:800; margin-bottom:6px;">
                            $<?php echo number_format($item['current_price'] ?? $item['starting_price'], 2); ?>
                        </p>
                        <p style="color:#888; font-size:12px; margin-bottom:12px;">
                            <i class="fa-solid fa-clock"></i> <?php echo $time_left; ?>
                        </p>
                        <a href="live_auction.php?id=<?php echo $item['id']; ?>" 
                           style="display:block; text-align:center; background:rgb(4 120 87); color:#fff; padding:10px; border-radius:6px; font-weight:700; text-decoration:none;">
                            View Details & Bid
                        </a>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div style="grid-column: 1/-1; background:#fff; padding:40px; text-align:center; border-radius:8px; color:#888;">
                <i class="fa-solid fa-folder-open" style="font-size:40px; margin-bottom:12px; opacity:0.3; display:block;"></i>
                <p style="font-weight:600;">No auction items found in this category.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php 
if (isset($stmt)) {
    mysqli_stmt_close($stmt);
}
include_once('includes/footer.php'); 
?>