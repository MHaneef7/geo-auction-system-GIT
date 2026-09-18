<?php
include_once('includes/header.php');

$cat = trim($_GET['cat'] ?? '');

// Base query structure
$query = "SELECT * FROM items WHERE status = 'active'";
$params = [];
$types = "";

if (!empty($cat)) {
    $query .= " AND (category LIKE ? OR title LIKE ?)";
    $searchTerm = "%" . $cat . "%";
    // Two parameters, both strings ('s')
    $types = "ss"; 
    $params = [$searchTerm, $searchTerm];
}

$query .= " ORDER BY id DESC";

// Prepare and execute safely
$stmt = mysqli_prepare($conn, $query);

if ($stmt) {
    if (!empty($params)) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
} else {
    $result = false;
}
?>

<div style="margin-bottom: 24px;">
    <h2 style="font-size: 22px; font-weight: 800; color: #1a1a2e; text-transform: capitalize;">
        Category: <?php echo !empty($cat) ? htmlspecialchars($cat) : 'All Categories'; ?>
    </h2>
</div>

<div class="auction-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 20px;">
    <?php if ($result && mysqli_num_rows($result) > 0): ?>
        <?php while ($item = mysqli_fetch_assoc($result)): 
            $time_left = get_time_remaining($item['end_time'] ?? date('Y-m-d H:i:s'));
        ?>
            <div class="auction-card" style="background:#fff; border-radius:8px; border:1px solid #e8e8e8; overflow:hidden;">
                <div style="height:180px; background:#f0f0f0; overflow:hidden;">
                    <img src="uploads/items/<?php echo htmlspecialchars($item['image'] ?? 'default.jpg'); ?>" 
                         alt="<?php echo htmlspecialchars($item['title']); ?>" 
                         style="width:100%; height:100%; object-fit:cover;"
                         onerror="this.onerror=null; this.src='assets/image/Geo-auction.png';">
                </div>
                <div style="padding:16px;">
                    <h4 style="font-size:16px; font-weight:700; margin-bottom:8px; color:#1a1a2e;">
                        <?php echo htmlspecialchars($item['title']); ?>
                    </h4>
                    <p style="color:#007a33; font-size:18px; font-weight:800; margin-bottom:6px;">
                        $<?php echo number_format($item['current_price'] ?? 0, 2); ?>
                    </p>
                    <p style="color:#888; font-size:12px; margin-bottom:12px;">
                        <i class="fa-solid fa-clock"></i> <?php echo $time_left; ?>
                    </p>
                    <a href="item.php?id=<?php echo $item['id']; ?>" 
                       style="display:block; text-align:center; background:#007a33; color:#fff; padding:10px; border-radius:6px; font-weight:700; text-decoration:none;">
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

<?php 
if (isset($stmt)) {
    mysqli_stmt_close($stmt);
}
include_once('includes/footer.php'); 
?>