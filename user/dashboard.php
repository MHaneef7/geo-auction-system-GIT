<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    if (file_exists('login.php')) {
        header("Location: login.php");
        exit();
    } elseif (file_exists('../login.php')) {
        header("Location: ../login.php");
        exit();
    } else {
        $_SESSION['user_id'] = 1;
        $_SESSION['role'] = 'buyer';
    }
    }if (file_exists('../includes/header.php')) {
        include_once '../includes/header.php';
    } elseif (file_exists(__DIR__ . '/../includes/header.php')) {
        include_once __DIR__ . '/../includes/header.php';
    }
    if (file_exists(__DIR__ . '/../includes/database.php')) {
        include_once __DIR__ . '/../includes/database.php';
    } elseif (file_exists('../includes/database.php')) {
        include_once '../includes/database.php';
    }$user_id = (int)$_SESSION['user_id'];
    $user_role = strtolower(trim($_SESSION['role'] ?? 'buyer'));
    $is_logged = true;

    $bid_message = '';
    $bid_success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_bid'])) {
    $item_id = (int)$_POST['item_id'];
    $bid_amount = (float)$_POST['bid_amount'];
    
    if ($bid_amount > 0 && isset($conn) && $conn instanceof mysqli) {
        mysqli_begin_transaction($conn);
        try {
            $check_stmt = mysqli_prepare($conn, "SELECT current_price, starting_price, min_increment, status, start_time, end_time FROM items WHERE id = ? FOR UPDATE");
            mysqli_stmt_bind_param($check_stmt, "i", $item_id);
            mysqli_stmt_execute($check_stmt);
            $result = mysqli_stmt_get_result($check_stmt);
            if ($item_data = mysqli_fetch_assoc($result)) {
                $now = time();
                $start_ts = strtotime($item_data['start_time']);
                $end_ts = strtotime($item_data['end_time']);
                if ($item_data['status'] !== 'active' || $now < $start_ts || $now > $end_ts) {
                    throw new Exception("This auction is not currently active.");
                }
                $base_price = $item_data['current_price'] > 0 ? $item_data['current_price'] : $item_data['starting_price'];
                $min_inc = $item_data['min_increment'] > 0 ? $item_data['min_increment'] : 1.00;
                $minimum_required = $base_price + $min_inc;
                if ($bid_amount >= $minimum_required) {
                    $insert_bid = mysqli_prepare($conn, "INSERT INTO bids (item_id, user_id, bid_amount, created_at) VALUES (?, ?, ?, NOW())");
                    mysqli_stmt_bind_param($insert_bid, "iid", $item_id, $user_id, $bid_amount);
                    if (mysqli_stmt_execute($insert_bid)) {
                        $update_item = mysqli_prepare($conn, "UPDATE items SET current_price = ?, bid_count = bid_count + 1 WHERE id = ?");
                        mysqli_stmt_bind_param($update_item, "di", $bid_amount, $item_id);
                        mysqli_stmt_execute($update_item);
                        
                        mysqli_commit($conn);
                        $bid_success = true;
                        $bid_message = "Your bid was placed successfully!";
                    } else {
                        throw new Exception("Error recording your bid. Please try again.");
                    }
                } else {
                    throw new Exception("Bid amount must be at least Rs. " . number_format($minimum_required, 2));
                }
            } else {
                throw new Exception("Item not found.");
            }
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $bid_message = $e->getMessage();
        }
    } else {
        $bid_message = "Invalid bid amount.";
    }
}
$totalItems = $liveAuctions = $upcomingAuctions = $soldItems = $totalBidsOnItems = 0;
$buyerBidsCount = $buyerWonCount = 0;
if (isset($conn) && $conn instanceof mysqli) {
    if ($user_role === 'seller' || $user_role === 'admin') {
        $stmt = $user_role === 'seller' 
            ? mysqli_prepare($conn, "SELECT COUNT(*) AS c FROM items WHERE seller_id = ?") 
            : mysqli_prepare($conn, "SELECT COUNT(*) AS c FROM items");
        if ($user_role === 'seller') mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        if ($res) { $totalItems = mysqli_fetch_assoc($res)['c']; }

        $stmt = $user_role === 'seller' 
            ? mysqli_prepare($conn, "SELECT COUNT(*) AS c FROM items WHERE status = 'active' AND start_time <= NOW() AND end_time >= NOW() AND seller_id = ?") 
            : mysqli_prepare($conn, "SELECT COUNT(*) AS c FROM items WHERE status = 'active' AND start_time <= NOW() AND end_time >= NOW()");
        if ($user_role === 'seller') mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        if ($res) { $liveAuctions = mysqli_fetch_assoc($res)['c']; }

        $stmt = $user_role === 'seller' 
            ? mysqli_prepare($conn, "SELECT COUNT(*) AS c FROM items WHERE start_time > NOW() AND seller_id = ?") 
            : mysqli_prepare($conn, "SELECT COUNT(*) AS c FROM items WHERE start_time > NOW()");
        if ($user_role === 'seller') mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        if ($res) { $upcomingAuctions = mysqli_fetch_assoc($res)['c']; }

        $stmt = $user_role === 'seller' 
            ? mysqli_prepare($conn, "SELECT COUNT(*) AS c FROM items WHERE winner_id IS NOT NULL AND seller_id = ?") 
            : mysqli_prepare($conn, "SELECT COUNT(*) AS c FROM items WHERE winner_id IS NOT NULL");
        if ($user_role === 'seller') mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        if ($res) { $soldItems = mysqli_fetch_assoc($res)['c']; }
        $stmt = $user_role === 'seller' 
            ? mysqli_prepare($conn, "SELECT COUNT(b.id) AS c FROM bids b JOIN items i ON b.item_id = i.id WHERE i.seller_id = ?") 
            : mysqli_prepare($conn, "SELECT COUNT(b.id) AS c FROM bids b");
        if ($user_role === 'seller') mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        if ($res) { $totalBidsOnItems = mysqli_fetch_assoc($res)['c']; }
    }
    if ($user_role === 'buyer' || $user_role === 'admin') {
        $stmt = mysqli_prepare($conn, "SELECT COUNT(id) AS c FROM bids WHERE user_id = ?");
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        if ($res) { $buyerBidsCount = mysqli_fetch_assoc($res)['c']; }

        $stmt = mysqli_prepare($conn, "SELECT COUNT(id) AS c FROM items WHERE winner_id = ?");
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        if ($res) { $buyerWonCount = mysqli_fetch_assoc($res)['c']; }
    }

    if ($user_role === 'seller') {
        $stmt = mysqli_prepare($conn, "
            SELECT i.*, c.name AS cat_name, 
            (SELECT MAX(bid_amount) FROM bids WHERE item_id = i.id) AS max_bid
            FROM items i 
            LEFT JOIN categories c ON i.category_id = c.id 
            WHERE i.seller_id = ? 
            ORDER BY i.created_at DESC LIMIT 50
        ");
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        $recent_items_res = mysqli_stmt_get_result($stmt);
        $featured_res = $recent_items_res;
    } elseif ($user_role === 'buyer') {
        $recent_items_res = mysqli_query($conn, "
            SELECT i.*, c.name AS cat_name, 
            (SELECT MAX(bid_amount) FROM bids WHERE item_id = i.id) AS max_bid
            FROM items i 
            LEFT JOIN categories c ON i.category_id = c.id 
            WHERE i.status = 'active' AND i.start_time <= NOW() AND i.end_time >= NOW()
            ORDER BY i.end_time ASC LIMIT 50
        ");
        $featured_res = $recent_items_res;
    } else { 
        $recent_items_res = mysqli_query($conn, "
            SELECT i.*, c.name AS cat_name, 
            (SELECT MAX(bid_amount) FROM bids WHERE item_id = i.id) AS max_bid
            FROM items i 
            LEFT JOIN categories c ON i.category_id = c.id 
            ORDER BY i.created_at DESC LIMIT 50
        ");
        $featured_res = $recent_items_res;
    }
} else {
    $featured_res = false;
}
?>

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
    .image-carousel {
        position: relative;
        width: 100%;
        height: 180px;
        background: #f5f5f5;
        overflow: hidden;
    }
    .carousel-track {
        display: flex;
        width: 100%;
        height: 100%;
        transition: transform 0.3s ease-in-out;
    }
    .carousel-slide {
        min-width: 100%;
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .carousel-slide img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .carousel-btn {
        position: absolute;
        top: 50%;
        transform: translateY(-50%);
        background: rgba(0, 0, 0, 0.5);
        color: #fff;
        border: none;
        padding: 6px 10px;
        cursor: pointer;
        font-size: 12px;
        border-radius: 3px;
        z-index: 10;
        transition: background 0.2s;
    }
    .carousel-btn:hover {
        background: rgba(0, 0, 0, 0.8);
    }
    .carousel-prev { left: 5px; }
    .carousel-next { right: 5px; }
</style>

<div style="max-width: 1200px; margin: 0 auto; padding: 20px 10px;">

    <?php if (!empty($bid_message)): ?>
        <div style="padding: 12px 20px; margin-bottom: 20px; border-radius: 6px; background: <?php echo $bid_success ? '#e6f4ea' : '#fce8e6'; ?>; color: <?php echo $bid_success ? '#137333' : '#c5221f'; ?>; font-weight: 600;">
            <?php echo htmlspecialchars($bid_message); ?>
        </div>
    <?php endif; ?>

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
                    if ($user_role === 'seller') echo "Manage your auction items, add new items, track live listings, and monitor bids.";
                    elseif ($user_role === 'buyer') echo "Explore live auction items and place your bids.";
                    else echo "Overview of all system activities, sellers, buyers, and items.";
                ?>
            </p>
        </div>
        <div>
            <?php if ($user_role === 'seller' || $user_role === 'admin'): ?>
                <a href="add_items.php" style="background: rgb(4 120 87); color: #fff; padding: 10px 20px; border-radius: 8px; text-decoration: none; font-weight: 700; font-size: 14px; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 4px 12px rgba(4,120,87,0.25); transition: 0.2s ease;">
                    <i class="fas fa-plus-circle"></i> + Add New Item
                </a>
            <?php endif; ?>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 35px;">
        <?php if ($user_role === 'seller' || $user_role === 'admin'): ?>
            <div class="dashboard-card">
                <div>
                    <div style="font-size: 24px; font-weight: 800; color: #007a33;"><?php echo $totalItems; ?></div>
                    <div style="color: #888; font-size: 13px; font-weight: 600; margin-top: 4px;"><?php echo ($user_role === 'seller') ? 'Items' : 'Total Items'; ?></div>
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
                    <div style="color: #888; font-size: 13px; font-weight: 600; margin-top: 4px;">My Bidding Count</div>
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

    <h3 style="font-size: 20px; font-weight: 700; color: #1a1a2e; margin-bottom: 15px;">
        <?php 
            if ($user_role === 'seller') echo 'Your Listed Items';
            elseif ($user_role === 'buyer') echo 'Live Auctions Available for Bidding';
            else echo 'All System Auctions (Admin Control)';
        ?>
    </h3>
    <?php if ($featured_res && mysqli_num_rows($featured_res) > 0): ?>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
        <?php while ($item = mysqli_fetch_assoc($featured_res)):
            $current_price = $item['max_bid'] ? $item['max_bid'] : ($item['current_price'] ?? $item['starting_price']);
            $price   = number_format((float)$current_price, 2);
            $diff    = $item['end_time'] ? strtotime($item['end_time']) - time() : 0;
            if ($diff <= 0) $timer = 'Ending soon';
            elseif ($diff < 3600) $timer = round($diff/60).'m left';
            elseif ($diff < 86400) $timer = round($diff/3600).'h left';
            else {
                $days = floor($diff / 86400);
                $hours = floor(($diff % 86400) / 3600);
                $timer = $days . ' Days ' . $hours . ' Hours';
            }
            $is_urgent = ($diff > 0 && $diff < 3600);
            $card_link = $is_logged ? "live_auction.php?id={$item['id']}" : "login.php";
            $images_list = [];
            if (!empty($item['images'])) {
                $decoded = json_decode($item['images'], true);
                if (is_array($decoded)) {
                    $images_list = $decoded;
                } else {
                    $images_list = explode(',', $item['images']);
                }
            } elseif (!empty($item['featured_image'])) {
                $images_list[] = $item['featured_image'];
            }
        ?>
            <div style="border:1px solid #e8e8e8; border-radius:8px; overflow:hidden; background:#fff; display:flex; flex-direction:column; justify-content:space-between;">
                <div class="image-carousel">
                    <?php if (!empty($images_list)): ?>
                        <div class="carousel-track">
                            <?php foreach ($images_list as $img): ?>
                                <div class="carousel-slide">
                                    <img src="../<?php echo htmlspecialchars(trim($img)); ?>">
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php if (count($images_list) > 1): ?>
                            <button class="carousel-btn carousel-prev" onclick="moveSlide(this, -1)">&#10094;</button>
                            <button class="carousel-btn carousel-next" onclick="moveSlide(this, 1)">&#10095;</button>
                        <?php endif; ?>
                    <?php else: ?>
                        <div style="width:100%; height:100%; display:flex; align-items:center; justify-content:center; color:#ccc; font-size:32px;">
                            <i class="fas fa-image"></i>
                        </div>
                    <?php endif; ?>
                </div>

                <div style="padding: 15px; cursor:pointer;" onclick="window.location.href='<?php echo $card_link; ?>';">
                    <div style="font-size:12px; color:#007a33; font-weight:600; text-transform:uppercase; margin-bottom:4px;"><?php echo htmlspecialchars($item['cat_name'] ?? 'General'); ?></div>
                    <div style="font-size:16px; font-weight:700; color:#333; margin-bottom:10px;"><?php echo htmlspecialchars($item['title']); ?></div>
                    <div style="margin-bottom:8px;">
                        <div style="font-weight:700; color:#111;">Current Bid: Rs.<?php echo $price; ?></div>
                        <div style="font-size:12px; color:#666;">Total Bids: <?php echo $item['bid_count'] ?? 0; ?></div>
                    </div>
                    <div style="font-size:13px; color:<?php echo $is_urgent ? '#c5221f' : '#555'; ?>;">
                        <i class="fas fa-clock"></i> Time Remaining: <?php echo $timer; ?>
                    </div>
                    <?php if(!empty($item['end_time'])): ?>
                    <div style="font-size: 12px; color: #888; margin-top: 4px;">
                        Expire Date: <?php echo date('d M Y, h:i A', strtotime($item['end_time'])); ?>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div style="padding: 15px; border-top: 1px solid #f0f0f0; display:flex; justify-content:space-between; align-items:center;">
                    <span style="background: rgba(4, 120, 87, 0.15); color: rgb(4 120 87); padding: 4px 8px; border-radius: 4px; font-size: 11px;">Live</span>
                    <a href="<?php echo $card_link; ?>" style="padding:6px 14px; font-size:12px; background:#007a33; color:#fff; border-radius:4px; text-decoration:none;">Bid Now</a>
                </div>
            </div>
        <?php endwhile; ?>
        </div>
    <?php else: ?>
        <div style="text-align: center; padding: 30px; background:#fff; border-radius:8px; border:1px solid #e8e8e8; margin-bottom: 30px; color: #777;">
            <div style="font-size: 32px; color: #ccc; margin-bottom: 10px;"><i class="fas fa-gavel"></i></div>
            <div style="font-weight: 700; color: #333;">No Live Auctions Yet</div>
            <div style="font-size: 14px; margin-bottom: 15px;">Be the first to list an item for auction!</div>
        </div>
    <?php endif; ?>

</div>

<script>
function moveSlide(btn, direction) {
    const carousel = btn.closest('.image-carousel');
    const track = carousel.querySelector('.carousel-track');
    const slides = track.querySelectorAll('.carousel-slide');
    
    let currentIndex = parseInt(track.dataset.index || '0', 10);
    currentIndex += direction;
    
    if (currentIndex < 0) {
        currentIndex = slides.length - 1;
    } else if (currentIndex >= slides.length) {
        currentIndex = 0;
    }
    
    track.dataset.index = currentIndex;
    track.style.transform = `translateX(-${currentIndex * 100}%)`;
}
</script>

<?php 
if (file_exists('../includes/footer.php')) {
    include_once '../includes/footer.php';
} elseif (file_exists(__DIR__ . '/../includes/footer.php')) {
    include_once __DIR__ . '/../includes/footer.php';
}
?>