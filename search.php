<?php
$require_auth = false;
include_once('includes/header.php');

$q = mysqli_real_escape_string($conn, trim($_GET['q'] ?? ''));

$where = "WHERE status = 'active'";
if (!empty($q)) {
    $where .= " AND (title LIKE '%$q%' OR description LIKE '%$q%')";
}

$sql = "SELECT * FROM items $where ORDER BY id DESC";
$res = mysqli_query($conn, $sql);
?>

<div style="max-width: 1200px; margin: 24px auto; padding: 0 20px;">
    <div style="margin-bottom: 24px;">
        <h2 style="font-size: 22px; font-weight: 800; color: #1a1a2e;">
            Search Results for: "<?php echo htmlspecialchars($q); ?>"
        </h2>
    </div>

    <div class="auction-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 20px;">
        <?php if ($res && mysqli_num_rows($res) > 0): ?>
            <?php while ($item = mysqli_fetch_assoc($res)): 
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
                <i class="fa-solid fa-magnifying-glass" style="font-size:40px; margin-bottom:12px; opacity:0.3; display:block;"></i>
                <p style="font-weight:600;">No items found matching your search query.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include_once('includes/footer.php'); ?>
