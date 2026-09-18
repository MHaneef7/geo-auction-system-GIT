<?php
include_once '../includes/header.php';
include_once __DIR__ .('/../includes/database.php');

$user_id = (int)$_SESSION['user_id'];

// Fetch the current user's details
$user_res = mysqli_query($conn, "SELECT * FROM users WHERE id = $user_id LIMIT 1");
$userRow  = ($user_res) ? mysqli_fetch_assoc($user_res) : [];

// Counts
$activeBidsRes    = mysqli_query($conn, "SELECT COUNT(*) AS c FROM bids WHERE user_id = $user_id AND (is_winning = 1 OR is_winning = 0)");
$activeBids       = ($activeBidsRes) ? mysqli_fetch_assoc($activeBidsRes)['c'] : 0;

$myAuctionsRes    = mysqli_query($conn, "SELECT COUNT(*) AS c FROM items WHERE seller_id = $user_id");
$myAuctions       = ($myAuctionsRes) ? mysqli_fetch_assoc($myAuctionsRes)['c'] : 0;

$wishlistCountRes = mysqli_query($conn, "SELECT COUNT(*) AS c FROM wishlist WHERE user_id = $user_id");
$wishlistCount    = ($wishlistCountRes) ? mysqli_fetch_assoc($wishlistCountRes)['c'] : 0;

$notifRes         = mysqli_query($conn, "SELECT COUNT(*) AS c FROM notifications WHERE user_id = $user_id AND is_read = 0");
$notifications    = ($notifRes) ? mysqli_fetch_assoc($notifRes)['c'] : 0;
?>

<div style="max-width: 1000px; margin: 0 auto;">
    <h2 style="font-size:22px; font-weight:800; color:#1a1a2e; margin-bottom:20px;">
        User Control Dashboard
    </h2>

    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:16px; margin-bottom:24px;">
   <div style="background:#fff; border-radius:8px; padding:20px; border:1px solid #e8e8e8;">
            <div style="font-size:28px; font-weight:800; color:#007a33;"><?php echo $wishlistCount; ?></div>
            <div style="color:#888; font-size:13px; font-weight:600;">Wishlist Items</div>
        </div>
        <div style="background:#fff; border-radius:8px; padding:20px; border:1px solid #e8e8e8;">
            <div style="font-size:28px; font-weight:800; color:#007a33;"><?php echo $myAuctions; ?></div>
            <div style="color:#888; font-size:13px; font-weight:600;">My Auctions</div>
        </div>
        
        <div style="background:#fff; border-radius:8px; padding:20px; border:1px solid #e8e8e8;">
            <div style="font-size:28px; font-weight:800; color:#007a33;"><?php echo $activeBids; ?></div>
            <div style="color:#888; font-size:13px; font-weight:600;">Active Bids</div>
        </div>
        <div style="background:#fff; border-radius:8px; padding:20px; border:1px solid #e8e8e8;">
            <div style="font-size:28px; font-weight:800; color:#007a33;"><?php echo $notifications; ?></div>
            <div style="color:#888; font-size:13px; font-weight:600;">Unread Notifications</div>
        </div>
    </div>

    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:16px;">
        <a href="my_bids.php" style="display:block; background:#fff; padding:16px; border-radius:8px; text-decoration:none; color:#333; border:1px solid #e8e8e8; font-weight:600;">
            <i class="fa-solid fa-gavel" style="color:#007a33; margin-right:8px;"></i> My Bids
        </a>
        <a href="won_auctions.php" style="display:block; background:#fff; padding:16px; border-radius:8px; text-decoration:none; color:#333; border:1px solid #e8e8e8; font-weight:600;">
            <i class="fa-solid fa-trophy" style="color:#007a33; margin-right:8px;"></i> Won Auctions
        </a>
        <a href="my_auctions.php" style="display:block; background:#fff; padding:16px; border-radius:8px; text-decoration:none; color:#333; border:1px solid #e8e8e8; font-weight:600;">
            <i class="fa-solid fa-box-archive" style="color:#007a33; margin-right:8px;"></i> My Auctions
        </a>
        <a href="add_items.php" style="display:block; background:#fff; padding:16px; border-radius:8px; text-decoration:none; color:#333; border:1px solid #e8e8e8; font-weight:600;">
            <i class="fa-solid fa-plus-circle" style="color:#007a33; margin-right:8px;"></i> Add New Item
        </a>
        <a href="wishlist.php" style="display:block; background:#fff; padding:16px; border-radius:8px; text-decoration:none; color:#333; border:1px solid #e8e8e8; font-weight:600;">
            <i class="fa-solid fa-heart" style="color:#007a33; margin-right:8px;"></i> Wishlist
        </a>
        <a href="notifications.php" style="display:block; background:#fff; padding:16px; border-radius:8px; text-decoration:none; color:#333; border:1px solid #e8e8e8; font-weight:600;">
            <i class="fa-solid fa-bell" style="color:#007a33; margin-right:8px;"></i> Notifications
        </a>
        <a href="profile.php" style="display:block; background:#fff; padding:16px; border-radius:8px; text-decoration:none; color:#333; border:1px solid #e8e8e8; font-weight:600;">
            <i class="fa-solid fa-user-gear" style="color:#007a33; margin-right:8px;"></i> Profile Settings
        </a>
    </div>
</div>

<?php include_once '../includes/footer.php'; ?>
