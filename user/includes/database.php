<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect to login if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

include_once '../includes/header.php';
include_once __DIR__ . '/../includes/database.php';

$user_id = (int)$_SESSION['user_id'];
$user_role = $_SESSION['role'] ?? 'buyer'; 
$is_logged = true;

// Handle Bid Submission if posted (Buyer can bid)
$bid_message = '';
$bid_success = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_bid'])) {
    $item_id = (int)$_POST['item_id'];
    $bid_amount = (float)$_POST['bid_amount'];
    
    if ($bid_amount > 0) {
        $check_stmt = mysqli_prepare($conn, "SELECT current_price FROM items WHERE id = ?");
        mysqli_stmt_bind_param($check_stmt, "i", $item_id);
        mysqli_stmt_execute($check_stmt);
        $result = mysqli_stmt_get_result($check_stmt);
        
        if ($item_data = mysqli_fetch_assoc($result)) {
            if ($bid_amount > $item_data['current_price']) {
                $insert_bid = mysqli_prepare($conn, "INSERT INTO bids (item_id, user_id, bid_amount, created_at) VALUES (?, ?, ?, NOW())");
                mysqli_stmt_bind_param($insert_bid, "iid", $item_id, $user_id, $bid_amount);
                
                if (mysqli_stmt_execute($insert_bid)) {
                    $update_item = mysqli_prepare($conn, "UPDATE items SET current_price = ?, bid_count = bid_count + 1 WHERE id = ?");
                    mysqli_stmt_bind_param($update_item, "di", $bid_amount, $item_id);
                    mysqli_stmt_execute($update_item);
                    
                    $bid_success = true;
                    $bid_message = "Your bid was placed successfully!";
                } else {
                    $bid_message = "Error placing bid. Please try again.";
                }
            } else {
                $bid_message = "Bid amount must be higher than the current price.";
            }
        }
    } else {
        $bid_message = "Invalid bid amount.";
    }
}

// Initialize variables
$totalItems = $liveAuctions = $upcomingAuctions = $soldItems = $totalBidsOnItems = 0;
$buyerBidsCount = $buyerWonCount = 0;

// --- DATA FETCHING BASED ON ROLE ---
if ($user_role === 'seller' || $user_role === 'admin') {
    $res = mysqli_query($conn, "SELECT COUNT(*) AS c FROM items" . ($user_role === 'seller' ? " WHERE seller_id = $user_id" : ""));
    if ($res) { $totalItems = mysqli_fetch_assoc($res)['c']; }

    $res = mysqli_query($conn, "SELECT COUNT(*) AS c FROM items WHERE status = 'active' AND start_time <= NOW() AND end_time >= NOW()" . ($user_role === 'seller' ? " WHERE seller_id = $user_id" : ""));
    if ($res) { $liveAuctions = mysqli_fetch_assoc($res)['c']; }

    $res = mysqli_query($conn, "SELECT COUNT(*) AS c FROM items WHERE start_time > NOW()" . ($user_role === 'seller' ? " WHERE seller_id = $user_id" : ""));
    if ($res) { $upcomingAuctions = mysqli_fetch_assoc($res)['c']; }

    $res = mysqli_query($conn, "SELECT COUNT(*) AS c FROM items WHERE winner_id IS NOT NULL" . ($user_role === 'seller' ? " WHERE seller_id = $user_id" : ""));
    if ($res) { $soldItems = mysqli_fetch_assoc($res)['c']; }

    $res = mysqli_query($conn, "SELECT COUNT(b.id) AS c FROM bids b" . ($user_role === 'seller' ? " JOIN items i ON b.item_id = i.id WHERE i.seller_id = $user_id" : ""));
    if ($res) { $totalBidsOnItems = mysqli_fetch_assoc($res)['c']; }
}

if ($user_role === 'buyer' || $user_role === 'admin') {
    $res = mysqli_query($conn, "SELECT COUNT(id) AS c FROM bids WHERE user_id = $user_id");
    if ($res) { $buyerBidsCount = mysqli_fetch_assoc($res)['c']; }

    $res = mysqli_query($conn, "SELECT COUNT(id) AS c FROM items WHERE winner_id = $user_id");
    if ($res) { $buyerWonCount = mysqli_fetch_assoc($res)['c']; }
}

// Fetch Items / Bids for Display based on role requirements
if ($user_role === 'seller') {
    $recent_items_res = mysqli_query($conn, "
        SELECT i.*, c.name AS cat_name, 
        (SELECT MAX(bid_amount) FROM bids WHERE item_id = i.id) AS max_bid
        FROM items i 
        LEFT JOIN categories c ON i.category_id = c.id 
        WHERE i.seller_id = $user_id 
        ORDER BY i.created_at DESC LIMIT 6
    ");
} elseif ($user_role === 'buyer') {
    // Buyer sees active, approved and live items to bid on
    $recent_items_res = mysqli_query($conn, "
        SELECT i.*, c.name AS cat_name, 
        (SELECT MAX(bid_amount) FROM bids WHERE item_id = i.id) AS max_bid
        FROM items i 
        LEFT JOIN categories c ON i.category_id = c.id 
        WHERE i.status = 'active' AND i.approved = 1 AND i.start_time <= NOW() AND i.end_time >= NOW()
        ORDER BY i.end_time ASC LIMIT 6
    ");
} else { 
    // Admin sees ALL items across the entire system using WHERE 1 equivalent logic
    $recent_items_res = mysqli_query($conn, "
        SELECT i.*, c.name AS cat_name, 
        (SELECT MAX(bid_amount) FROM bids WHERE item_id = i.id) AS max_bid
        FROM items i 
        LEFT JOIN categories c ON i.category_id = c.id 
        WHERE 1 
        ORDER BY i.created_at DESC LIMIT 6
    ");
}
?>

<!-- Custom CSS for Dashboard & Cards -->
<style>
@keyframes fadeUp {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}
.dashboard-card {
    background: #fff;
    border-radius: 10px;
    padding: 20px;
    border: 1px solid #e8e8e8;
    box-shadow: 0 4px 6px rgba(0,0,0,0.02);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    animation: fadeUp 0.6s ease forwards;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.dashboard-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 15px rgba(0,0,0,0.08);
}
.dashboard-card i {
    font-size: 30px;
    color: #007a33;
}
.table-container {
    background: #fff;
    border-radius: 10px;
    border: 1px solid #e8e8e8;
    overflow: hidden;
    margin-bottom: 30px;
}
.custom-table {
    width: 100%;
    border-collapse: collapse;
    text-align: left;
    font-size: 14px;
}
.custom-table th {
    background: #f8f9fa;
    color: #333;
    padding: 12px 15px;
    font-weight: 700;
    border-bottom: 1px solid #e8e8e8;
}
.custom-table td {
    padding: 12px 15px;
    border-bottom: 1px solid #eee;
    color: #555;
}
</style>

<div style="max-width: 1200px; margin: 0 auto; padding: 20px 10px;">

    <!-- Alert Message -->
    <?php if (!empty($bid_message)): ?>
        <div style="padding: 12px 20px; margin-bottom: 20px; border-radius: 6px; background: <?php echo $bid_success ? '#e6f4ea' : '#fce8e6'; ?>; color: <?php echo $bid_success ? '#137333' : '#c5221f'; ?>; font-weight: 600;">
            <?php echo htmlspecialchars($bid_message); ?>
        </div>
    <?php endif; ?>

    <!-- Top Header based on Role -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; flex-wrap: wrap; gap: 15px;">
        <div>
            <h2 style="font-size: 24px; font-weight: 800; color: #1a1a2e; margin: 0;">
                <?php 
                    if ($user_role === 'seller') echo "Seller Dashboard";
                    elseif ($user_role === 'buyer') echo "Buyer Dashboard";
                    else echo "Admin Dashboard";
                ?>
            </h2>
            <p style="color: #666; font-size: 14px; margin: 4px 0 0 0;">
                <?php 
                    if ($user_role === 'seller') echo "Manage your auction items, track live listings, and monitor bids.";
                    elseif ($user_role === 'buyer') echo "Explore live auction items and place your bids.";
                    else echo "Overview of all system activities, sellers, buyers, and items.";
                ?>
            </p>
        </div>
        <div>
            <?php if ($user_role === 'seller' || $user_role === 'admin'): ?>
                <a href="add_items.php" style="background: #007a33; color: #fff; padding: 10px 18px; border-radius: 6px; text-decoration: none; font-weight: 700; font-size: 14px; display: inline-flex; align-items: center; gap: 8px;">
                    <i class="fas fa-plus"></i> Quick Add Item
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Statistics Grid based on Role -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 35px;">
        <?php if ($user_role === 'seller' || $user_role === 'admin'): ?>
            <div class="dashboard-card">
                <div>
                    <div style="font-size: 24px; font-weight: 800; color: #007a33;"><?php echo $totalItems; ?></div>
                    <div style="color: #888; font-size: 13px; font-weight: 600; margin-top: 4px;">Total Items</div>
                </div>
                <i class="fas fa-boxes"></i>
            </div>
            <div class="dashboard-card">
                <div>
                    <div style="font-size: 24px; font-weight: 800; color: #007a33;"><?php echo $liveAuctions; ?></div>
                    <div style="color: #888; font-size: 13px; font-weight: 600; margin-top: 4px;">Live Auctions</div>
                </div>
                <i class="fas fa-broadcast-tower"></i>
            </div>
            <div class="dashboard-card">
                <div>
                    <div style="font-size: 24px; font-weight: 800; color: #007a33;"><?php echo $soldItems; ?></div>
                    <div style="color: #888; font-size: 13px; font-weight: 600; margin-top: 4px;">Sold Items</div>
                </div>
                <i class="fas fa-handshake"></i>
            </div>
        <?php endif; ?>

        <?php if ($user_role === 'buyer' || $user_role === 'admin'): ?>
            <div class="dashboard-card">
                <div>
                    <div style="font-size: 24px; font-weight: 800; color: #007a33;"><?php echo $buyerBidsCount; ?></div>
                    <div style="color: #888; font-size: 13px; font-weight: 600; margin-top: 4px;">My Total Bids</div>
                </div>
                <i class="fas fa-gavel"></i>
            </div>
            <div class="dashboard-card">
                <div>
                    <div style="font-size: 24px; font-weight: 800; color: #007a33;"><?php echo $buyerWonCount; ?></div>
                    <div style="color: #888; font-size: 13px; font-weight: 600; margin-top: 4px;">Auctions Won</div>
                </div>
                <i class="fas fa-trophy"></i>
            </div>
        <?php endif; ?>
    </div>

    <!-- Listings / Recent Section -->
    <h3 style="font-size: 20px; font-weight: 700; color: #1a1a2e; margin-bottom: 15px;">
        <?php 
            if ($user_role === 'buyer') echo 'Live Auctions Available'; 
            elseif ($user_role === 'admin') echo 'All System Items (Admin View)';
            else echo 'Recent Auctions'; 
        ?>
    </h3>
    
    <?php if ($recent_items_res && mysqli_num_rows($recent_items_res) > 0): ?>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; margin-bottom: 35px;">
        <?php while ($item = mysqli_fetch_assoc($recent_items_res)):
            $current_price = $item['max_bid'] ? $item['max_bid'] : $item['current_price'];
            $price = number_format((float)$current_price, 2);
            $diff = $item['end_time'] ? strtotime($item['end_time']) - time() : 0;
            $timer = ($diff <= 0) ? 'Ended' : round($diff/3600) . 'h left';
            $img_src = !empty($item['featured_image']) ? $item['featured_image'] : ($item['images'] ?? '');
        ?>
            <div onclick="window.location.href='live_auction.php?id=<?php echo $item['id']; ?>'" 
            style="cursor:pointer; border:1px solid #e8e8e8; border-radius:8px; overflow:hidden; background:#fff; display:flex; flex-direction:column; justify-content:space-between;">
                <div style="height:180px; background:#f5f5f5; display:flex; align-items:center; justify-content:center; overflow:hidden;">
                    <?php if (!empty($img_src)): ?>
                        <img src="../<?php echo htmlspecialchars($img_src); ?>" alt="Item" style="width:100%; height:100%; object-fit:cover;">
                    <?php else: ?>
                        <div style="color:#ccc; font-size:32px;"><i class="fas fa-image"></i></div>
                    <?php endif; ?>
                </div>
                <div style="padding: 15px;">
                    <div style="font-size:12px; color:#007a33; font-weight:600; text-transform:uppercase; margin-bottom:4px;"><?php echo htmlspecialchars($item['cat_name'] ?? 'General'); ?></div>
                    <div style="font-size:16px; font-weight:700; color:#333; margin-bottom:10px;"><?php echo htmlspecialchars($item['title']); ?></div>
                    <div style="font-weight:700; color:#111; margin-bottom:8px;">Current: Rs.<?php echo $price; ?></div>
                    <div style="font-size:13px; color:#555;"><i class="fas fa-clock"></i> <?php echo $timer; ?></div>
                    <?php if($user_role === 'admin'): ?>
                        <div style="font-size:12px; color:#007a33; font-weight:600; margin-top:6px;">
                            Code: <?php echo htmlspecialchars($item['auction_code']); ?> | Status: <?php echo ucfirst($item['status']); ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endwhile; ?>
        </div>
    <?php else: ?>
        <div style="text-align: center; padding: 30px; background:#fff; border-radius:8px; border:1px solid #e8e8e8; margin-bottom: 30px; color: #777;">
            No items found.
        </div>
    <?php endif; ?>

</div>

<?php include_once '../includes/footer.php'; ?>