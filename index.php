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
    LIMIT 8
");

$cats_res = mysqli_query($conn, "
    SELECT c.*, COUNT(i.id) AS item_count
    FROM categories c
    LEFT JOIN items i ON i.category_id = c.id AND i.status = 'active'
    GROUP BY c.id
    ORDER BY item_count DESC
    LIMIT 8
");
$sellers_res = mysqli_query($conn, "
    SELECT u.id, u.name, COUNT(i.id) AS item_count
    FROM users u
    JOIN items i ON i.seller_id = u.id AND i.status IN ('active','ended')
    GROUP BY u.id
    ORDER BY item_count DESC
    LIMIT 1
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
    <meta name="description" content="Geo Auction – Discover, bid, and win on thousands of live auctions. Cars, electronics, art, collectibles and more.">
    <title>Geo Auction – Live Bidding Platform</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Hero countdown numbers */
        .hero-card-price { font-size: 15px; }
        .bid-live { color: #4df08a; font-weight: 700; font-size: 12px; }
        
        /* Smooth scrolling for anchor links */
        html {
            scroll-behavior: smooth;
        }

        /* Custom RGB(4, 120, 87) and Black combination for Buttons */
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
        .pub-search-btn {
            background-color: rgb(4 120 87) !important;
            color: #fff !important;
            border: none;
        }
        .pub-search-btn:hover {
            background-color: #000000 !important;
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
            <a href="#home" class="active">Home</a>
            <a href="#live_auction" class="require-login">Live Auctions</a>
            <a href="#category" class="require-login">Categories</a>
            <a href="#about">About</a>
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

<section class="hero" id="home">
    <div class="hero-inner">
        <div class="hero-text">
            <div class="hero-eyebrow">
                <span class="live-dot" style="background: rgb(4 120 87);"></span>
                <?php echo number_format($live_count); ?> Live Auctions 
            </div>
            <h1 class="hero-title">
                Bid Smart,<br>Win <span style="color: rgb(4 120 87);">Big</span>.
            </h1>
            <p class="hero-sub">
                Discover exciting live auctions across hundreds of categories. Find amazing items, place your best bid, and compete with thousands of bidders in real time. Join <?php echo number_format($user_count); ?> + the action today and make your next great deal yours! 
            </p>
            <div class="hero-actions">
                <?php if ($is_logged): ?>
                    <a href="live_auction.php" class="btn btn-gold btn-lg">
                        <i class="fas fa-gavel"></i> Browse Live Auctions
                    </a>
                <?php else: ?>
                    <a href="login.php" class="btn btn-gold btn-lg">
                        <i class="fas fa-gavel"></i> Browse Live Auctions
                    </a>
                    <a href="register.php" class="btn btn-outline btn-lg" style="color:#fff;border-color:rgba(255,255,255,0.4);">
                        <i class="fas fa-user-plus"></i> Register Free
                    </a>
                <?php endif; ?>
            </div>
            <div class="hero-stats">
                <div class="hero-stat">
                    <div class="hero-stat-val" style="color: rgb(4 120 87);"><?php echo number_format($live_count); ?>+</div>
                    <div class="hero-stat-label">Live Auctions</div>
                </div>
                <div class="hero-stat">
                    <div class="hero-stat-val" style="color: rgb(4 120 87);"><?php echo number_format($bid_count); ?>+</div>
                    <div class="hero-stat-label">Total Bids</div>
                </div>
                <div class="hero-stat">
                    <div class="hero-stat-val" style="color: rgb(4 120 87);"><?php echo number_format($user_count); ?>+</div>
                    <div class="hero-stat-label">Members</div>
                </div>
            </div>
        </div>

        <div class="hero-visual">
            <?php
            $hero_items = [];
            if ($featured_res) {
                $tmp = [];
                while ($r = mysqli_fetch_assoc($featured_res)) { $tmp[] = $r; }
                mysqli_data_seek($featured_res, 0);
                $hero_items = array_slice($tmp, 0, 3);
            }
            foreach ($hero_items as $idx => $hi):
                $current_price = $hi['max_bid'] ? $hi['max_bid'] : $hi['starting_price'];
                $price = number_format((float)$current_price, 2);
                $diff  = $hi['end_time'] ? strtotime($hi['end_time']) - time() : 0;
                if ($diff < 3600 && $diff > 0) $timer_str = round($diff/60).'m left';
                elseif ($diff < 86400 && $diff > 0) $timer_str = round($diff/3600).'h left';
                elseif ($diff > 0) {
                    $days = floor($diff / 86400);
                    $hours = floor(($diff % 86400) / 3600);
                    $timer_str = $days . ' Days ' . $hours . ' Hours';
                }
                else $timer_str = 'Ending soon';
                $is_featured = ($idx === 0);
                $item_link = $is_logged ? "item.php?id={$hi['id']}" : "login.php";
                
                $hi_raw_images = $hi['images'] ?? '';
                $hi_img_list = [];
                if (!empty($hi_raw_images)) {
                    $hi_decoded = json_decode($hi_raw_images, true);
                    if (is_array($hi_decoded)) {
                        $hi_img_list = $hi_decoded;
                    } else {
                        $hi_img_list = array_map('trim', explode(',', $hi_raw_images));
                    }
                }
                $img_src = !empty($hi_img_list[0]) ? $hi_img_list[0] : '';
            ?>
                <a href="<?php echo $item_link; ?>" class="hero-card<?php echo $is_featured ? ' featured' : ''; ?>" style="text-decoration:none;color:inherit;">
                    <div class="hero-card-img" style="width:<?php echo $is_featured?'100px':'100px'; ?>;height:<?php echo $is_featured?'70px':'100px'; ?>;background:rgba(4,120,87,0.15);border-radius:8px;overflow:hidden;flex-shrink:0;">
                        <?php if ($img_src): ?>
                            <img src="<?php echo htmlspecialchars($img_src); ?>" style="width:100%;height:100%;object-fit:cover;" alt="">
                        <?php else: ?>
                            <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;font-size:24px;">🏷️</div>
                        <?php endif; ?>
                    </div>
                    <div>
                        <div class="hero-card-title"><?php echo htmlspecialchars(substr($hi['title'],0,40)); ?></div>
                        <div class="hero-card-price">Current Bid: Rs.<?php echo $price; ?></div>
                        <div class="hero-card-timer"><?php echo htmlspecialchars($hi['bid_count']); ?> bids · <?php echo $timer_str; ?></div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<section class="section" id="live_auction">
    <div class="section-inner">
        <div class="section-header">
            <div>
                <h2 class="section-title"><span style="color: rgb(4 120 87);">Live</span> Auctions</h2>
                <p class="section-sub">Hand-picked active items with the most bidding activity</p>
            </div>
            <a href="#" class="section-link" style="color: rgb(4 120 87);">View all →</a>
        </div>

        <?php if ($featured_res && mysqli_num_rows($featured_res) > 0): ?>
            <?php mysqli_data_seek($featured_res, 0); ?>
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

<section class="section section-alt" id="category">
    <div class="section-inner">
        <div class="section-header">
            <div>
                <h2 class="section-title">Browse by <span style="color: rgb(4 120 87);">Category</span></h2>
                <p class="section-sub">Find exactly what you're looking for</p>
            </div>
            <?php if ($is_logged): ?>
                <a href="category.php" class="section-link" style="color: rgb(4 120 87);">All categories →</a>
            <?php else: ?>
                <a href="login.php" class="section-link" style="color: rgb(4 120 87);">All categories →</a>
            <?php endif; ?>
        </div>

        <?php if ($cats_res && mysqli_num_rows($cats_res) > 0): ?>
        <div class="grid-4">
            <?php while ($cat = mysqli_fetch_assoc($cats_res)):
                $cat_link = $is_logged ? "category.php?id={$cat['id']}" : "login.php";
            ?>
            <a href="<?php echo $cat_link; ?>" class="cat-card" style="text-decoration:none;">
                <div class="cat-card-icon" style="color: rgb(4 120 87);">
                    <i class="fas <?php echo htmlspecialchars($cat['icon'] ?? 'fa-tag'); ?>"></i>
                </div>
                <div class="cat-card-name"><?php echo htmlspecialchars($cat['name']); ?></div>
                <div class="cat-card-count"><?php echo $cat['item_count']; ?> active items</div>
            </a>
            <?php endwhile; ?>
        </div>
        <?php else: ?>
        <div class="grid-4">
            <?php
            $def_cats = [
                ['fa-laptop','Electronics'],['fa-car','Vehicles'],
                ['fa-gem','Jewelry'],['fa-paint-brush','Art'],
                ['fa-futbol','Sports'],['fa-couch','Furniture'],
                ['fa-book','Books'],['fa-tshirt','Fashion'],
            ];
            foreach ($def_cats as $dc): ?>
            <a href="login.php" class="cat-card" style="text-decoration:none;">
                <div class="cat-card-icon" style="color: rgb(4 120 87);"><i class="fas <?php echo $dc[0]; ?>"></i></div>
                <div class="cat-card-name"><?php echo $dc[1]; ?></div>
                <div class="cat-card-count">0 active items</div>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</section>

<section class="section" id="about">
    <div class="section-inner">
        <div class="section-header" style="justify-content:center;text-align:center;display:block;margin-bottom:48px;">
            <h2 class="section-title">How <span style="color: rgb(4 120 87);">It Works</span></h2>
            <p class="section-sub" style="margin-top:8px;">Start bidding in 3 simple steps</p>
        </div>
        <div class="steps">
            <div class="step">
                <div class="step-num" style="background: rgb(4 120 87); color: #fff;">1</div>
                <div class="step-title">Create an Account</div>
                <div class="step-desc">Register for free in under 60 seconds. Verify your email and you're ready to start bidding on live auctions.</div>
            </div>
            <div class="step">
                <div class="step-num" style="background: rgb(4 120 87); color: #fff;">2</div>
                <div class="step-title">Add Wallet Funds</div>
                <div class="step-desc">Top up your wallet with any amount. Your funds are secure and only deducted when you win an auction.</div>
            </div>
            <div class="step">
                <div class="step-num" style="background: rgb(4 120 87); color: #fff;">3</div>
                <div class="step-title">Bid & Win!</div>
                <div class="step-desc">Browse live auctions, place your bids, and win amazing items at unbeatable prices. Payment is instant from your wallet.</div>
            </div>
        </div>
        <?php if (!$is_logged): ?>
        <div class="text-center mt-4">
            <a href="login.php" class="btn btn-primary btn-lg">
                <i class="fas fa-rocket"></i> Get Started — Login Now
            </a>
        </div>
        <?php endif; ?>
    </div>
</section>

<?php if ($sellers_res && mysqli_num_rows($sellers_res) > 0): ?>
<section class="section section-alt">
    <div class="section-inner">
        <div class="section-header">
            <div>
                <h2 class="section-title">Top <span style="color: rgb(4 120 87);">Seller</span></h2>
                <p class="section-sub">Our most active trusted member</p>
            </div>
        </div>
        <div  class="dashboard" styling="border:2px solid black; width:100%;">
            <div style="width: 300px;">
                <?php while ($seller = mysqli_fetch_assoc($sellers_res)):
                    $initials = strtoupper(substr($seller['name'], 0, 2));
                ?>
                <div class="seller-card" >
                    <div class="seller-avatar" style="background: rgb(4 120 87); color: #fff;"><?php echo $initials; ?></div>
                    <div>
                        <div class="seller-name"><?php echo htmlspecialchars($seller['name']); ?></div>
                        <div class="seller-meta">Top Rated Seller</div>
                        <div class="seller-items"><i class="fas fa-box me-1"></i> <?php echo $seller['item_count']; ?> items listed</div>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if (!$is_logged): ?>
<section style="background:linear-gradient(135deg,#000000, rgb(4 120 87));padding:60px 0;">
    <div class="section-inner text-center">
        <h2 style="font-family:'Playfair Display',serif;font-size:36px;color:#fff;margin-bottom:12px;">
            Ready to Start Bidding?
        </h2>
        <p style="color:rgba(255,255,255,0.7);font-size:16px;margin-bottom:32px;">
            Join thousands of bidders. No hidden fees. Cancel anytime.
        </p>
        <div style="display:flex;gap:16px;justify-content:center;flex-wrap:wrap;">
            <a href="login.php" class="btn btn-gold btn-lg"><i class="fas fa-sign-in-alt"></i> Login to Your Account</a>
            <a href="register.php" class="btn btn-lg" style="background:rgba(255,255,255,0.15);color:#fff;border:2px solid rgba(255,255,255,0.3);">
                <i class="fas fa-user-plus"></i> Register Free
            </a>
        </div>
    </div>
</section>
<?php endif; ?>

<footer class="site-footer">
    <div class="footer-inner">
        <div class="footer-grid">
            <div>
                <div class="footer-brand-logo">
                    <img src="assets/image/Geo-auction.png" alt="Geo Auction" onerror="this.onerror=null;this.src='assets/image/geo-auction.png'">
                </div>
                <p class="footer-brand-text">
                    Geo Auction is the leading live bidding platform connecting buyers and sellers across hundreds of categories. Bid smart, win big.
                </p>
            </div>
            <div>
                <div class="footer-col-title">Platform</div>
                <div class="footer-links">
                    <?php if ($is_logged): ?>
                        <a href="live_auction">Live Auctions</a>
                        <a href="#category">Categories</a>
                        <a href="#">Search</a>
                    <?php else: ?>
                        <a href="#live_auction">Live Auctions</a>
                        <a href="#category">Categories</a>
                        <a href="search.php">Search</a>
                    <?php endif; ?>
                    <a href="#about">About Us</a>
                </div>
            </div>
            <div>
                <div class="footer-col-title">Account</div>
                <div class="footer-links">
                    <a href="login.php">Login</a>
                    <a href="register.php">Register</a>
                    <?php if ($is_logged): ?>
                    <a href="user/wallet.php">My Wallet</a>
                    <a href="dashboard.php">Dashboard</a>
                    <?php endif; ?>
                </div>
            </div>
            <div>
                <div class="footer-col-title">Sellers</div>
                <div class="footer-links">
                    <?php if ($is_logged): ?>
                        <a href="user/add_items.php">List an Item</a>
                        <a href="user/my_auctions.php">My Listings</a>
                    <?php else: ?>
                        <a href="login.php">List an Item</a>
                        <a href="login.php">My Listings</a>
                    <?php endif; ?>
                    <a href="contact.php">Contact Us</a>
                </div>
            </div>
        </div>
        <div class="footer-bottom">
            <span>&copy; <?php echo date('Y'); ?> GeoAuction. All rights reserved.</span>
            <span>Built with ❤️ for bidders everywhere</span>
        </div>
    </div>
</footer>

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

    document.querySelectorAll('a[href]').forEach(function(link) {
        var href = link.getAttribute('href');
        if (href && href !== '#' && !href.startsWith('#') && href !== 'about.php' && href !== 'contact.php' && !href.startsWith('login.php') && !href.startsWith('register.php') && !href.startsWith('home.php') && !href.startsWith('search.php')) {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                window.location.href = LOGIN_URL;
            });
        }
    });

    document.querySelectorAll('form:not(.pub-search form)').forEach(function(form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            window.location.href = LOGIN_URL;
        });
    });

    document.querySelectorAll('button').forEach(function(btn) {
        if (!btn.closest('form') && !btn.classList.contains('slider-btn')) {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                window.location.href = LOGIN_URL;
            });
        }
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