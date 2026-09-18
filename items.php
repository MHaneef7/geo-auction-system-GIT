<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include_once ('includes/database.php');
$featured_res = mysqli_query($conn, "
    SELECT i.*, c.name AS cat_name, u.name AS seller_name,
    COUNT(b.id) AS bid_count,
    MAX(b.bid_amount) AS max_bid
    FROM items i
    LEFT JOIN categories c ON c.id = i.category_id
    LEFT JOIN users u ON u.id = i.seller_id
    LEFT JOIN bids b ON b.item_id = i.id
    WHERE i.status = 'active'
    GROUP BY i.id
    ORDER BY bid_count DESC, i.created_at DESC
");

$stats_res  = mysqli_query($conn, "SELECT COUNT(*) AS c FROM items WHERE status = 'active'");
$live_count = mysqli_fetch_assoc($stats_res)['c'] ?? 0;
$ubids_res  = mysqli_query($conn, "SELECT COUNT(*) AS c FROM bids");
$bid_count  = mysqli_fetch_assoc($ubids_res)['c'] ?? 0;
$usr_res    = mysqli_query($conn, "SELECT COUNT(*) AS c FROM users");
$user_count = mysqli_fetch_assoc($usr_res)['c'] ?? 0;

$is_logged  = !empty($_SESSION['user_id']);
$user_name  = $_SESSION['user_name'] ?? '';
$user_init  = $is_logged ? strtoupper(substr($user_name, 0, 2)) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live Auctions – Geo Auction</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        html {
            scroll-behavior: smooth;
        }
        .bid-live { color: #4df08a; font-weight: 700; font-size: 12px; }
        
        .btn-primary, .btn-gold {
            background-color: rgb(4 120 87) !important;
            border-color: rgb(4 120 87) !important;
            color: #ffffff !important;
        }
        .btn-primary:hover, .btn-gold:hover {
            background-color: #000000 !important;
            border-color: rgb(4 120 87) !important;
            color: rgb(4 120 87) !important;
        }

        /* Slider styles for live auction item images */
        .slider-container {
            position: relative;
            overflow: hidden;
            width: 100%;
            height: 200px;
        }
        .slides {
            display: flex;
            height: 100%;
            transition: transform 0.4s ease-in-out;
        }
        .slide-item {
            height: 100%;
            flex-shrink: 0;
        }
        .slide-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .slider-btn {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            background: rgba(0, 0, 0, 0.31);
            color: white;
            border: none;
            padding: 8px 12px;
            cursor: pointer;
            z-index: 10;
            border-radius: 4px;
        }
        .prev { left: 10px; }
        .next { right: 10px; }
    </style>
</head>
<body>

<nav class="pub-nav">
    <div class="pub-nav-inner">
        <a href="index.php" class="pub-logo">
            <img src="assets/image/Geo-auction.png" alt="Geo Auction" onerror="this.onerror=null;this.src='assets/image/geo-auction.png'">
        </a>
        <div class="pub-nav-links">
            <a href="index.php">Home</a>
            <a href="#live_auction" class="active require-login">Live Auctions</a>
            <a href="index.php#category" class="require-login">Categories</a>
            <a href="index.php#about">About</a>
        </div>
        <div class="pub-search"></div>
        <div class="pub-nav-cta">
            <?php if ($is_logged): ?>
                <a href="user/wallet.php" class="btn btn-outline btn-sm" style="border-color: rgb(4 120 87); color: rgb(4 120 87);">
                    <i class="fas fa-wallet"></i> Wallet
                </a>
                <a href="dashboard.php" class="pub-nav-user">
                    <div class="pub-nav-avatar" style="background: rgb(4 120 87); color: #fff;"><?php echo $user_init; ?></div>
                    <span class="pub-nav-user-name"><?php echo htmlspecialchars($user_name); ?></span>
                </a>
            <?php else: ?>
                <a href="login.php" class="btn btn-outline btn-sm" style="border-color: rgb(4 120 87); color: rgb(4 120 87);">Login</a>
                <a href="register.php" class="btn btn-primary btn-sm">Register Free</a>
            <?php endif; ?>
        </div>
    </div>
</nav>

<section class="section" id="live_auction" style="padding-top: 100px;">
    <div class="section-inner">
        <div class="section-header">
            <div>
                <h2 class="section-title"><span style="color: rgb(4 120 87);">Live</span> Auctions</h2>
                <p class="section-sub">Hand-picked active items with the most bidding activity</p>
            </div>
        </div>

        <?php if ($featured_res && mysqli_num_rows($featured_res) > 0): ?>
            <div class="grid-4">
            <?php while ($item = mysqli_fetch_assoc($featured_res)):
                $current_price = $item['max_bid'] ? $item['max_bid'] : $item['starting_price'];
                $price    = number_format((float)$current_price, 2);
                $diff     = $item['end_time'] ? strtotime($item['end_time']) - time() : 0;
                if ($diff <= 0) $timer = 'Ending soon';
                elseif ($diff < 3600) $timer = round($diff/60).'m left';
                elseif ($diff < 86400) $timer = round($diff/3600).'h left';
                else {
                    $days = floor($diff / 86400);
                    $hours = floor(($diff % 86400) / 3600);
                    $timer = $days . ' Days ' . $hours . ' Hours';
                }
                $is_urgent = ($diff > 0 && $diff < 3600);
                $card_link = $is_logged ? "item.php?id={$item['id']}" : "login.php";
                $raw_images = $item['images'] ?? '';
                $image_list = [];
                if (!empty($raw_images)) {
                    $decoded = json_decode($raw_images, true);
                    if (is_array($decoded)) {
                        $image_list = $decoded;
                    } else {
                        $image_list = array_map('trim', explode(',', $raw_images));
                    }
                }
                if (empty($image_list)) {
                    $image_list = ['assets/image/geo-auction.png'];
                }
            ?>
                <div class="card item-card" style="text-decoration:none;color:inherit;">
                    <div class="slider-container">
                        <div class="slides" style="width: <?php echo count($image_list) * 100; ?>%;">
                            <?php foreach ($image_list as $img_path): ?>
                                <div class="slide-item" style="width: <?php echo 100 / count($image_list); ?>%;">
                                    <img src="<?php echo htmlspecialchars($img_path); ?>" alt="<?php echo htmlspecialchars($item['title']); ?>">
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php if (count($image_list) > 1): ?>
                            <button class="slider-btn prev">&#10094;</button>
                            <button class="slider-btn next">&#10095;</button>
                        <?php endif; ?>
                    </div>

                    <a href="<?php echo $card_link; ?>" style="text-decoration:none;color:inherit;">
                        <div class="item-card-body">
                            <div class="item-card-cat"><?php echo htmlspecialchars($item['cat_name'] ?? 'General'); ?></div>
                            <div class="item-card-title"><?php echo htmlspecialchars($item['title']); ?></div>
                            <div class="item-card-price-row">
                                <div class="item-card-price">Current Bid: Rs.<?php echo $price; ?></div>
                                <div class="item-card-bids">Total Bids: <?php echo $item['bid_count']; ?></div>
                            </div>
                            <div class="item-card-timer <?php echo $is_urgent ? 'text-red' : ''; ?>">
                                <i class="fas fa-clock"></i> Time Remaining: <?php echo $timer; ?>
                            </div>
                            <?php if(!empty($item['end_time'])): ?>
                            <div style="font-size: 12px; color: #888; margin-top: 4px;">
                                Expire Date: <?php echo date('d M Y, h:i A', strtotime($item['end_time'])); ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="item-card-footer">
                            <span class="badge badge-green" style="background: rgba(4, 120, 87, 0.15); color: rgb(4 120 87);"><span class="live-dot" style="background: rgb(4 120 87);"></span> Live</span>
                            <span class="btn btn-sm btn-primary require-login" style="padding:6px 14px;font-size:12px;">Bid Now</span>
                        </div>
                    </a>
                </div>
            <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <div class="empty-state-icon"><i class="fas fa-gavel"></i></div>
                <div class="empty-state-title">No Live Auctions Yet</div>
                <div class="empty-state-text">Be the first to list an item for auction!</div>
                <a href="login.php" class="btn btn-primary">Login to Get Started</a>
            </div>
        <?php endif; ?>
    </div>
</section>

<script>
const NOT_LOGGED_IN = <?php echo $is_logged ? 'false' : 'true'; ?>;
const LOGIN_URL = 'login.php';

if (NOT_LOGGED_IN) {
    document.querySelectorAll('.require-login').forEach(function(link) {
        link.addEventListener('click', function(e) {
            var href = link.getAttribute('href');
            if (href && href.startsWith('#')) {
                return; 
            }
            e.preventDefault();
            window.location.href = LOGIN_URL;
        });
    });
}

// Image Slider script implementation (Dynamic support)
document.querySelectorAll('.card.item-card').forEach(function(card) {
    let currentSlide = 0;
    const slides = card.querySelector('.slides');
    const slideItems = card.querySelectorAll('.slide-item');
    const totalSlides = slideItems.length;

    const nextBtn = card.querySelector('.next');
    const prevBtn = card.querySelector('.prev');

    if (nextBtn && prevBtn && slides && totalSlides > 1) {
        nextBtn.onclick = function (e) {
            e.preventDefault();
            e.stopPropagation();
            currentSlide++;
            if (currentSlide >= totalSlides) {
                currentSlide = 0;
            }
            slides.style.transform = `translateX(-${currentSlide * (100 / totalSlides)}%)`;
        };

        prevBtn.onclick = function (e) {
            e.preventDefault();
            e.stopPropagation();
            currentSlide--;
            if (currentSlide < 0) {
                currentSlide = totalSlides - 1;
            }
            slides.style.transform = `translateX(-${currentSlide * (100 / totalSlides)}%)`;
        };
    }
});
</script>

</body>
</html>