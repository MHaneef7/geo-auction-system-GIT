<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include_once __DIR__ . '/../includes/database.php';

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$user_id = (int)$_SESSION['user_id'];

    // Use a prepared statement for safety against SQL injection
    $stmt = mysqli_prepare($conn, "SELECT i.*,
        (SELECT COUNT(*) FROM bids b WHERE b.item_id = i.id) AS total_bids,
        (SELECT MAX(b.bid_amount) FROM bids b WHERE b.item_id = i.id) AS max_bid
        FROM items i
        WHERE i.seller_id = ?
        ORDER BY i.created_at DESC");

    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $auctions = mysqli_stmt_get_result($stmt);
    include_once __DIR__ . '/../includes/header.php';
?>

<div style="max-width: 1100px; margin: 0 auto; background: #fff; padding: 28px; border-radius: 8px; border: 1px solid #e8e8e8;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
        <h2 style="font-size:22px; font-weight:800; color:#1a1a2e; margin:0;">
            My Auction Listings
        </h2>
        <a href="add_items.php" style="background:#007a33; color:#fff; padding:8px 18px; border-radius:6px; font-weight:600; text-decoration:none; font-size:13px;">
            + Add New Listing
        </a>
    </div>

    <table style="width:100%; border-collapse:collapse; text-align:left; font-size:14px;">
        <thead>
            <tr style="border-bottom:2px solid #eee; color:#6c757d; background:#f8f9fa;">
                <th style="padding:12px;">Title</th>
                <th style="padding:12px;">Starting Price</th>
                <th style="padding:12px;">Current Price</th>
                <th style="padding:12px;">Bids</th>
                <th style="padding:12px;">Status</th>
                <th style="padding:12px;">End Time</th>
                <th style="padding:12px;">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($auctions && mysqli_num_rows($auctions) > 0): ?>
                <?php while ($row = mysqli_fetch_assoc($auctions)): ?>
                <tr style="border-bottom:1px solid #eee;">
                    <td style="padding:12px; font-weight:600;">
                        <a href="../item.php?id=<?php echo $row['id']; ?>" style="color:#1a1a2e; text-decoration:none;">
                            <?php echo htmlspecialchars($row['title']); ?>
                        </a>
                    </td>
                    <td style="padding:12px;">Rs. <?php echo number_format($row['starting_price'], 2); ?></td>
                    <td style="padding:12px; font-weight:600; color:#007a33;">Rs. <?php echo number_format($row['max_bid'] ?? $row['current_price'], 2); ?></td>
                    <td style="padding:12px;"><?php echo $row['total_bids']; ?> bids</td>
                    <td style="padding:12px;">
                        <span style="background:#e8f5ec; color:#007a33; padding:4px 10px; border-radius:20px; font-weight:700; font-size:11px; text-transform:uppercase;">
                            <?php echo ucfirst($row['status']); ?>
                        </span>
                    </td>
                    <td style="padding:12px; color:#888; font-size:12px;"><?php echo htmlspecialchars($row['end_time']); ?></td>
                    <td style="padding:12px;">
                        <a href="edit_item.php?id=<?php echo $row['id']; ?>" style="color:#007a33; font-weight:600; text-decoration:none; margin-right:10px;">Edit</a>
                        <a href="delete_item.php?id=<?php echo $row['id']; ?>" onclick="return confirm('Are you sure you want to delete this item?');" style="color:#e2001a; font-weight:600; text-decoration:none;">Delete</a>
                    </td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="7" style="padding:24px; text-align:center; color:#888;">
                        No auction listings found. <a href="add_items.php" style="color:#007a33; font-weight:600;">Create your first listing</a>.
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php 
// Clean up statement resource
if (isset($stmt)) {
    mysqli_stmt_close($stmt);
}
include_once __DIR__ . '/../includes/footer.php'; 
?>