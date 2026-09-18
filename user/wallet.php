<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (isset($_GET['switch_role']) && in_array(strtolower($_GET['switch_role']), ['buyer', 'seller', 'admin'])) {
    $_SESSION['role'] = strtolower($_GET['switch_role']);
}

$is_authenticated = isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
$user_id = $is_authenticated ? (int)$_SESSION['user_id'] : 0;
$user_name = $_SESSION['user_name'] ?? ($_SESSION['name'] ?? 'Buyer');
$role = $is_authenticated ? strtolower(trim($_SESSION['role'] ?? 'buyer')) : 'guest';
$conn = null;
if (file_exists(__DIR__ . '/../includes/database.php')) {
    include_once __DIR__ . '/../includes/database.php';
} elseif (file_exists(__DIR__ . '/includes/database.php')) {
    include_once __DIR__ . '/includes/database.php';
}
$has_db = isset($conn) && $conn instanceof mysqli && !$conn->connect_error;

// User coordinates for Geo-distance (Default: Hyderabad / Qasimabad)
$user_lat = isset($_SESSION['user_lat']) ? (float)$_SESSION['user_lat'] : 25.3960;
$user_lng = isset($_SESSION['user_lng']) ? (float)$_SESSION['user_lng'] : 68.3578;

// Helper: Haversine Distance Calculation (km)
function getDistanceKm($lat1, $lon1, $lat2, $lon2) {
    if (empty($lat1) || empty($lon1) || empty($lat2) || empty($lon2)) return null;
    $earthRadius = 6371;
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat / 2) * sin($dLat / 2) +
        cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
        sin($dLon / 2) * sin($dLon / 2);
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    return round($earthRadius * $c, 1);
}

// Helper: Parse Images from DB column (supports JSON array, comma-separated, or single file path)
function parseItemImages($imgField, $featuredField = '') {
    $images = [];
    if (!empty($featuredField)) {
        $images[] = trim($featuredField);
    }
    if (!empty($imgField)) {
        $raw = trim($imgField);
        if (str_starts_with($raw, '[') && str_ends_with($raw, ']')) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                foreach ($decoded as $p) {
                    $val = trim($p);
                    if (!empty($val) && !in_array($val, $images)) $images[] = $val;
                }
            }
        } else {
            $parts = explode(',', $raw);
            foreach ($parts as $p) {
                $val = trim($p);
                if (!empty($val) && !in_array($val, $images)) $images[] = $val;
            }
        }
    }
    if (empty($images)) {
        $images[] = 'https://images.unsplash.com/photo-1552519507-da3b142c6e3d?auto=format&fit=crop&w=800&q=80';
    }
    return $images;
}

// 3. AJAX Handlers (Quick Modal & Watchlist)
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? $_GET['action'] ?? '';

    if ($action === 'get_modal_details') {
        $mid = (int)($_GET['item_id'] ?? 0);
        if ($mid <= 0 || !$has_db) {
            echo json_encode(['success' => false, 'error' => 'Invalid lot ID']);
            exit();
        }

        $stmt = mysqli_prepare($conn, "
            SELECT `id`, `auction_code`, `title`, `slug`, `description`, `short_description`,
                   `starting_price`, `reserve_price`, `current_price`, `min_increment`, `buy_it_now_price`,
                   `images`, `condition_type`, `brand`, `model`, `year`, `mileage`, `engine_type`, `color`,
                   `location_address`, `location_city`, `location_state`, `location_country`, `location_lat`, `location_lng`,
                   `start_time`, `end_time`, `extended_time`, `status`, `featured`, `approved`, `views`,
                   `bid_count`, `watcher_count`, `seller_id`, `category_id`, `city`, `area`, `winner_id`,
                   `winning_bid_id`, `meta_title`, `meta_description`, `meta_keywords`, `created_at`, `updated_at`
            FROM `items` 
            WHERE `id` = ? LIMIT 1
        ");
        mysqli_stmt_bind_param($stmt, "i", $mid);
        mysqli_stmt_execute($stmt);
        $m_item = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

        if (!$m_item) {
            echo json_encode(['success' => false, 'error' => 'Item not found']);
            exit();
        }

        $imgs = parseItemImages($m_item['images'] ?? '', '');
        $m_price = max((float)$m_item['current_price'], (float)$m_item['starting_price']);
        $dist = getDistanceKm($user_lat, $user_lng, $m_item['location_lat'] ?? 0, $m_item['location_lng'] ?? 0);

        echo json_encode([
            'success' => true,
            'id' => (int)$m_item['id'],
            'auction_code' => $m_item['auction_code'] ?? ('LOT-'.$m_item['id']),
            'title' => htmlspecialchars($m_item['title']),
            'description' => htmlspecialchars($m_item['description'] ?? $m_item['short_description'] ?? ''),
            'image' => $imgs[0],
            'gallery' => $imgs,
            'current_price' => number_format($m_price, 2),
            'brand' => htmlspecialchars($m_item['brand'] ?? ''),
            'model' => htmlspecialchars($m_item['model'] ?? ''),
            'year' => htmlspecialchars($m_item['year'] ?? ''),
            'mileage' => htmlspecialchars($m_item['mileage'] ?? ''),
            'engine_type' => htmlspecialchars($m_item['engine_type'] ?? ''),
            'color' => htmlspecialchars($m_item['color'] ?? ''),
            'condition_type' => ucfirst($m_item['condition_type'] ?? 'Standard'),
            'location' => htmlspecialchars($m_item['location_city'] ?? $m_item['city'] ?? 'Pakistan'),
            'distance_km' => $dist,
            'bid_count' => (int)$m_item['bid_count'],
            'end_time' => $m_item['end_time'],
            'status' => $m_item['status']
        ]);
        exit();
    }

    if ($action === 'toggle_watchlist') {
        if (!$is_authenticated) {
            echo json_encode(['success' => false, 'error' => 'Please sign in first', 'redirect' => 'login.php']);
            exit();
        }
        $w_item_id = (int)($_POST['item_id'] ?? 0);
        $chk = mysqli_prepare($conn, "SELECT id FROM wishlist WHERE user_id = ? AND item_id = ?");
        mysqli_stmt_bind_param($chk, "ii", $user_id, $w_item_id);
        mysqli_stmt_execute($chk);
        $w_res = mysqli_stmt_get_result($chk);

        if ($w_row = mysqli_fetch_assoc($w_res)) {
            $del = mysqli_prepare($conn, "DELETE FROM wishlist WHERE id = ?");
            mysqli_stmt_bind_param($del, "i", $w_row['id']);
            mysqli_stmt_execute($del);
            $in_watchlist = false;
        } else {
            $ins = mysqli_prepare($conn, "INSERT INTO wishlist (user_id, item_id, created_at) VALUES (?, ?, NOW())");
            mysqli_stmt_bind_param($ins, "ii", $user_id, $w_item_id);
            mysqli_stmt_execute($ins);
            $in_watchlist = true;
        }
        echo json_encode(['success' => true, 'in_watchlist' => $in_watchlist]);
        exit();
    }

    echo json_encode(['success' => false, 'error' => 'Unknown action']);
    exit();
}

// 4. Query & Filter Handling
$search_query = isset($_GET['q']) ? trim($_GET['q']) : '';
$selected_category = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;
$selected_city = isset($_GET['city']) ? trim($_GET['city']) : '';
$sort_by = isset($_GET['sort']) ? trim($_GET['sort']) : 'latest';
$condition_filter = isset($_GET['condition']) ? trim($_GET['condition']) : '';

$items_list = [];

if ($has_db) {
    // 45 columns from `items` table
    $sql = "SELECT 
        `id`, `auction_code`, `title`, `slug`, `description`, `short_description`, 
        `starting_price`, `reserve_price`, `current_price`, `min_increment`, `buy_it_now_price`, 
        `images`, `condition_type`, `brand`, `model`, `year`, `mileage`, `engine_type`, `color`, 
        `location_address`, `location_city`, `location_state`, `location_country`, `location_lat`, `location_lng`, 
        `start_time`, `end_time`, `extended_time`, `status`, `featured`, `approved`, `views`, 
        `bid_count`, `watcher_count`, `seller_id`, `category_id`, `city`, `area`, `winner_id`, 
        `winning_bid_id`, `meta_title`, `meta_description`, `meta_keywords`, `created_at`, `updated_at`
    FROM `items` 
    WHERE `status` = 'active'";

    $params = [];
    $types = "";

    if (!empty($search_query)) {
        $sql .= " AND (`title` LIKE ? OR `description` LIKE ? OR `brand` LIKE ? OR `model` LIKE ?)";
        $searchTerm = "%$search_query%";
        $params[] = $searchTerm; $params[] = $searchTerm; $params[] = $searchTerm; $params[] = $searchTerm;
        $types .= "ssss";
    }
    if ($selected_category > 0) {
        $sql .= " AND `category_id` = ?";
        $params[] = $selected_category;
        $types .= "i";
    }
    if (!empty($selected_city)) {
        $sql .= " AND (`location_city` = ? OR `city` = ?)";
        $params[] = $selected_city; $params[] = $selected_city;
        $types .= "ss";
    }
    if (!empty($condition_filter)) {
        $sql .= " AND `condition_type` = ?";
        $params[] = $condition_filter;
        $types .= "s";
    }

    // Sort order
    if ($sort_by === 'price_low') {
        $sql .= " ORDER BY `current_price` ASC";
    } elseif ($sort_by === 'price_high') {
        $sql .= " ORDER BY `current_price` DESC";
    } elseif ($sort_by === 'ending_soon') {
        $sql .= " ORDER BY `end_time` ASC";
    } elseif ($sort_by === 'most_bids') {
        $sql .= " ORDER BY `bid_count` DESC";
    } else {
        $sql .= " ORDER BY `created_at` DESC";
    }

    $stmt = mysqli_prepare($conn, $sql);
    if (!empty($params)) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($res)) {
        $row['gallery'] = parseItemImages($row['images'] ?? '', '');
        $row['dist_km'] = getDistanceKm($user_lat, $user_lng, $row['location_lat'] ?? 0, $row['location_lng'] ?? 0);
        $items_list[] = $row;
    }
} else {
    // Standalone fallback if MySQL is offline
    $items_list = [
        [
            'id' => 1,
            'auction_code' => 'LOT-4092',
            'title' => '2023 Porsche 911 GT3 Touring',
            'brand' => 'Porsche',
            'model' => '911 GT3',
            'year' => '2023',
            'mileage' => '4,200 km',
            'engine_type' => '4.0L Flat-6 Petrol',
            'color' => 'Oak Green Metallic',
            'condition_type' => 'Pristine Used',
            'location_city' => 'Hyderabad',
            'current_price' => 18450000,
            'starting_price' => 17500000,
            'bid_count' => 19,
            'end_time' => date('Y-m-d H:i:s', time() + 3600),
            'gallery' => ['https://images.unsplash.com/photo-1614162692292-7ac56d7f7f1e?auto=format&fit=crop&w=800&q=80'],
            'dist_km' => 2.4,
            'short_description' => 'Original Paint-to-Sample Oak Green Metallic over Cohiba Brown bucket leather.'
        ],
        [
            'id' => 2,
            'auction_code' => 'LOT-7731',
            'title' => 'Rolex Daytona Cosmograph Oystersteel',
            'brand' => 'Rolex',
            'model' => 'Daytona 116500LN',
            'year' => '2022',
            'mileage' => '-',
            'engine_type' => 'Calibre 4130',
            'color' => 'Black Dial / Steel',
            'condition_type' => 'Unworn Box & Papers',
            'location_city' => 'Karachi',
            'current_price' => 5200000,
            'starting_price' => 4800000,
            'bid_count' => 31,
            'end_time' => date('Y-m-d H:i:s', time() + 7200),
            'gallery' => ['https://images.unsplash.com/photo-1523275335684-37898b6baf30?auto=format&fit=crop&w=800&q=80'],
            'dist_km' => 148.0,
            'short_description' => 'Complete collector set with original warranty card, inner and outer presentation boxes.'
        ]
    ];
}

// 5. Header Include (Includes fixed navbar and opens <main class="main-content">)
$header_included = false;
if (file_exists(__DIR__ . '/../includes/header.php')) {
    include_once __DIR__ . '/../includes/header.php';
    $header_included = true;
} elseif (file_exists(__DIR__ . '/includes/header.php')) {
    include_once __DIR__ . '/includes/header.php';
    $header_included = true;
}
?>

<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<style>
/* ========== AUCTION ITEM CARD ENHANCEMENTS ========== */
:root {
    --brand-green: #007a33;
    --brand-green-dark: #005f28;
    --brand-green-light: #e8f5ec;
}
.auction-card {
    transition: transform 0.2s cubic-bezier(0.16, 1, 0.3, 1), box-shadow 0.2s cubic-bezier(0.16, 1, 0.3, 1), border-color 0.2s ease;
}
.auction-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 12px 24px -6px rgba(15, 23, 42, 0.08);
    border-color: #007a33;
}
.card-img-zoom {
    transition: transform 0.35s ease;
}
.auction-card:hover .card-img-zoom {
    transform: scale(1.04);
}
.modal-backdrop-box {
    opacity: 0;
    visibility: hidden;
    transition: opacity 0.25s ease, visibility 0.25s ease;
}
.modal-backdrop-box.active {
    opacity: 1;
    visibility: visible;
}
</style>

<div class="max-w-7xl mx-auto px-4 sm:px-6 py-8 space-y-6">

    <!-- Top Banner & Filters -->
    <div class="bg-white border border-slate-200 rounded-2xl p-5 shadow-sm space-y-4">
        <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight flex items-center gap-2.5">
                    <span class="w-9 h-9 rounded-xl bg-emerald-50 text-emerald-700 flex items-center justify-center text-base">
                        <i class="fa-solid fa-gavel"></i>
                    </span>
                    Live Auction Gallery
                </h1>
                <p class="text-xs text-slate-500 mt-1">
                    Showing <strong class="text-slate-800"><?php echo count($items_list); ?></strong> active auction items ready for bidding.
                </p>
            </div>

            <!-- Search and Sort Form -->
            <form method="GET" action="live_auction.php" class="flex flex-wrap items-center gap-2.5 w-full md:w-auto">
                <div class="relative flex-1 md:w-64">
                    <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                    <input type="text" name="q" value="<?php echo htmlspecialchars($search_query); ?>" 
                           placeholder="Search brand, model, title..." 
                           class="w-full bg-slate-50 border border-slate-200 rounded-xl pl-8 pr-3 py-2 text-xs text-slate-800 outline-none focus:bg-white focus:border-emerald-600 transition">
                </div>

                <select name="sort" onchange="this.form.submit()" class="bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs text-slate-700 font-semibold outline-none focus:border-emerald-600 transition cursor-pointer">
                    <option value="latest" <?php echo $sort_by === 'latest' ? 'selected' : ''; ?>>Newest First</option>
                    <option value="price_low" <?php echo $sort_by === 'price_low' ? 'selected' : ''; ?>>Price: Low to High</option>
                    <option value="price_high" <?php echo $sort_by === 'price_high' ? 'selected' : ''; ?>>Price: High to Low</option>
                    <option value="ending_soon" <?php echo $sort_by === 'ending_soon' ? 'selected' : ''; ?>>Ending Soon</option>
                    <option value="most_bids" <?php echo $sort_by === 'most_bids' ? 'selected' : ''; ?>>Most Bids</option>
                </select>

                <?php if (!empty($search_query) || !empty($selected_city) || !empty($condition_filter)): ?>
                <a href="live_auction.php" class="px-3 py-2 text-xs font-semibold text-rose-600 bg-rose-50 border border-rose-200 rounded-xl hover:bg-rose-100 transition">
                    <i class="fa-solid fa-rotate-left mr-1"></i> Reset
                </a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- AUCTION ITEMS CARDS GRID -->
    <?php if (!empty($items_list)): ?>
    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-5">
        <?php foreach ($items_list as $item): 
            $primary_image = $item['gallery'][0];
            $current_price = !empty($item['current_price']) && $item['current_price'] > 0 
                ? (float)$item['current_price'] 
                : (float)$item['starting_price'];
            $bids_count = (int)($item['bid_count'] ?? 0);
            $lot_code = $item['auction_code'] ?? ('LOT-' . $item['id']);
            $city_name = $item['location_city'] ?? ($item['city'] ?? 'Pakistan');
            $dist_km = $item['dist_km'] ?? null;
            $end_timestamp = strtotime($item['end_time'] ?? '');
            $is_ending_soon = ($end_timestamp - time()) > 0 && ($end_timestamp - time()) <= 1800;
        ?>
        <div class="auction-card bg-white border border-slate-200 rounded-2xl overflow-hidden flex flex-col justify-between shadow-xs">
            <div>
                <!-- Image Container with Badges -->
                <div class="relative h-48 sm:h-52 bg-slate-100 overflow-hidden">
                    <img src="<?php echo htmlspecialchars($primary_image); ?>" 
                         alt="<?php echo htmlspecialchars($item['title']); ?>" 
                         class="card-img-zoom w-full h-full object-cover" 
                         loading="lazy">

                    <!-- Top Left: Lot Code & Status -->
                    <div class="absolute top-2.5 left-2.5 flex items-center gap-1.5">
                        <span class="px-2.5 py-1 rounded-md bg-slate-900/80 backdrop-blur-xs text-white text-[10px] font-extrabold tracking-wider uppercase">
                            #<?php echo htmlspecialchars($lot_code); ?>
                        </span>
                        <?php if ($is_ending_soon): ?>
                        <span class="px-2 py-0.5 rounded-md bg-rose-600 text-white text-[10px] font-bold uppercase tracking-wide animate-pulse">
                            Ending Soon
                        </span>
                        <?php endif; ?>
                    </div>

                    <!-- Top Right: Watchlist Heart Button -->
                    <button type="button" onclick="toggleCardWatchlist(<?php echo $item['id']; ?>, this)" 
                            class="absolute top-2.5 right-2.5 w-8 h-8 rounded-full bg-white/90 hover:bg-white text-slate-400 hover:text-rose-500 shadow-sm flex items-center justify-center transition cursor-pointer" 
                            title="Add to Watchlist">
                        <i class="fa-regular fa-heart text-xs"></i>
                    </button>

                    <!-- Bottom Bar: Multi-Image indicator & Location -->
                    <div class="absolute bottom-2 left-2 right-2 flex items-center justify-between text-[11px] text-white">
                        <span class="px-2 py-0.5 rounded-md bg-slate-900/80 backdrop-blur-xs font-medium truncate">
                            <i class="fa-solid fa-location-dot text-emerald-400 mr-1"></i><?php echo htmlspecialchars($city_name); ?>
                            <?php if ($dist_km !== null): ?> • <?php echo $dist_km; ?> km<?php endif; ?>
                        </span>

                        <?php if (count($item['gallery']) > 1): ?>
                        <span class="px-1.5 py-0.5 rounded-md bg-black/60 backdrop-blur-xs font-bold text-[10px]">
                            <i class="fa-solid fa-camera mr-1"></i><?php echo count($item['gallery']); ?>
                        </span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Card Content -->
                <div class="p-4 space-y-2.5">
                    <!-- Brand & Year Specs Chips -->
                    <div class="flex items-center gap-1.5 flex-wrap">
                        <?php if (!empty($item['year'])): ?>
                        <span class="px-2 py-0.5 rounded bg-slate-100 text-slate-700 font-bold text-[10px]">
                            <?php echo htmlspecialchars($item['year']); ?>
                        </span>
                        <?php endif; ?>

                        <?php if (!empty($item['brand'])): ?>
                        <span class="px-2 py-0.5 rounded bg-emerald-50 text-emerald-800 font-semibold text-[10px]">
                            <?php echo htmlspecialchars($item['brand']); ?>
                        </span>
                        <?php endif; ?>

                        <?php if (!empty($item['mileage']) && $item['mileage'] !== '-'): ?>
                        <span class="px-2 py-0.5 rounded bg-slate-100 text-slate-600 text-[10px]">
                            <i class="fa-solid fa-gauge text-[9px] mr-1"></i><?php echo htmlspecialchars($item['mileage']); ?>
                        </span>
                        <?php endif; ?>
                    </div>

                    <!-- Title -->
                    <h3 class="text-sm font-bold text-slate-900 line-clamp-1 hover:text-emerald-700 transition" title="<?php echo htmlspecialchars($item['title']); ?>">
                        <?php echo htmlspecialchars($item['title']); ?>
                    </h3>

                    <!-- Short Description -->
                    <p class="text-xs text-slate-500 line-clamp-2 leading-relaxed">
                        <?php echo htmlspecialchars(!empty($item['short_description']) ? $item['short_description'] : $item['description']); ?>
                    </p>

                    <!-- Timer and Bids -->
                    <div class="flex items-center justify-between text-xs pt-1 border-t border-slate-100">
                        <span class="text-slate-400 font-medium">
                            <i class="fa-solid fa-gavel text-emerald-600 mr-1"></i> <?php echo $bids_count; ?> bids placed
                        </span>
                        <span class="text-rose-600 font-semibold flex items-center gap-1">
                            <i class="fa-regular fa-clock text-[11px]"></i>
                            <span class="countdown-timer" data-endtime="<?php echo $item['end_time']; ?>">Live</span>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Card Bottom: Pricing & Actions -->
            <div class="p-4 pt-0">
                <div class="bg-slate-50 p-3 rounded-xl border border-slate-100 mb-3 flex items-center justify-between">
                    <div>
                        <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider block">Current Bid</span>
                        <span class="text-base font-extrabold text-emerald-700">
                            Rs. <?php echo number_format($current_price, 0); ?>
                        </span>
                    </div>
                    <?php if (!empty($item['buy_it_now_price']) && (float)$item['buy_it_now_price'] > 0): ?>
                    <div class="text-right">
                        <span class="text-[10px] text-amber-600 font-bold uppercase tracking-wider block">Buy It Now</span>
                        <span class="text-xs font-bold text-slate-700">
                            Rs. <?php echo number_format($item['buy_it_now_price'], 0); ?>
                        </span>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="grid grid-cols-2 gap-2">
                    <button type="button" onclick="showQuickModal(<?php echo $item['id']; ?>)" 
                            class="py-2 px-3 bg-white hover:bg-slate-100 text-slate-700 border border-slate-200 rounded-xl text-xs font-bold transition flex items-center justify-center gap-1 cursor-pointer">
                        <i class="fa-regular fa-eye"></i> Quick View
                    </button>
                    <a href="live_auction.php?id=<?php echo $item['id']; ?>" 
                       class="py-2 px-3 bg-[#007a33] hover:bg-[#005f28] text-white rounded-xl text-xs font-bold transition text-center shadow-xs flex items-center justify-center gap-1">
                        <i class="fa-solid fa-gavel"></i> Bid Now
                    </a>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <!-- Empty State -->
    <div class="bg-white border border-slate-200 rounded-2xl p-12 text-center max-w-md mx-auto shadow-sm">
        <div class="w-16 h-16 rounded-full bg-slate-100 text-slate-400 flex items-center justify-center mx-auto mb-3 text-2xl">
            <i class="fa-solid fa-box-open"></i>
        </div>
        <h3 class="text-base font-bold text-slate-800">No Auction Items Found</h3>
        <p class="text-xs text-slate-500 mt-1">There are no live lots matching your search or filters.</p>
        <a href="live_auction.php" class="mt-4 inline-flex items-center gap-1.5 px-4 py-2 bg-emerald-600 text-white rounded-xl text-xs font-bold hover:bg-emerald-700 transition">
            <i class="fa-solid fa-rotate-left"></i> View All Active Lots
        </a>
    </div>
    <?php endif; ?>

</div>

<!-- QUICK VIEW MODAL POPUP -->
<div id="quickViewModal" class="modal-backdrop-box fixed inset-0 bg-slate-950/60 backdrop-blur-xs z-50 flex items-center justify-center p-4">
    <div class="bg-white w-full max-w-2xl rounded-2xl overflow-hidden shadow-2xl flex flex-col max-h-[90vh]">
        <div class="p-4 sm:p-5 border-b border-slate-100 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span id="modalLotBadge" class="text-xs font-extrabold text-emerald-700 bg-emerald-50 border border-emerald-200 px-2.5 py-1 rounded-md">#LOT</span>
                <span id="modalCondition" class="text-xs font-semibold px-2 py-0.5 rounded bg-slate-100 text-slate-600">Pristine</span>
            </div>
            <button type="button" onclick="closeQuickModal()" class="w-8 h-8 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100 flex items-center justify-center cursor-pointer">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>

        <div class="p-6 space-y-4 overflow-y-auto flex-1">
            <div class="h-64 rounded-xl overflow-hidden bg-slate-100 relative">
                <img id="modalMainImg" src="" class="w-full h-full object-cover" alt="Lot Preview">
            </div>

            <div>
                <h3 id="modalTitle" class="text-xl font-bold text-slate-900"></h3>
                <p id="modalDesc" class="text-xs text-slate-600 mt-2 leading-relaxed whitespace-pre-line"></p>
            </div>

            <div class="grid grid-cols-3 gap-2 bg-slate-50 p-3.5 rounded-xl border border-slate-200 text-center text-xs">
                <div>
                    <span class="text-slate-400 block font-medium">Current Highest</span>
                    <strong id="modalPrice" class="text-emerald-700 text-sm font-extrabold">Rs. 0</strong>
                </div>
                <div>
                    <span class="text-slate-400 block font-medium">Location</span>
                    <strong id="modalLocation" class="text-slate-800 text-sm">City</strong>
                </div>
                <div>
                    <span class="text-slate-400 block font-medium">Bids Placed</span>
                    <strong id="modalBids" class="text-slate-800 text-sm">0</strong>
                </div>
            </div>

            <div class="pt-2 flex items-center gap-3">
                <a id="modalBidLink" href="" class="flex-1 py-3 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs rounded-xl text-center shadow transition flex items-center justify-center gap-1.5">
                    <i class="fa-solid fa-gavel"></i> Open Live Terminal
                </a>
            </div>
        </div>
    </div>
</div>

<script>
// Live Countdown Timers for All Cards
function updateCardTimers() {
    document.querySelectorAll('.countdown-timer').forEach(function(el) {
        var end = new Date(el.getAttribute('data-endtime')).getTime();
        var now = new Date().getTime();
        var diff = end - now;

        if (diff <= 0) {
            el.textContent = "Closed";
            el.className = "text-slate-400 font-bold";
        } else {
            var hours = Math.floor(diff / (1000 * 60 * 60));
            var mins = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
            var secs = Math.floor((diff % (1000 * 60)) / 1000);
            el.textContent = hours + "h " + mins + "m " + secs + "s";
        }
    });
}
setInterval(updateCardTimers, 1000);
updateCardTimers();

// Watchlist Toggle
function toggleCardWatchlist(itemId, btn) {
    var fd = new FormData();
    fd.append('action', 'toggle_watchlist');
    fd.append('item_id', itemId);

    fetch('live_auction.php?ajax=1', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                var icon = btn.querySelector('i');
                if (res.in_watchlist) {
                    icon.className = 'fa-solid fa-heart text-rose-500 text-xs';
                    btn.classList.add('text-rose-500');
                } else {
                    icon.className = 'fa-regular fa-heart text-xs';
                    btn.classList.remove('text-rose-500');
                }
            } else if (res.redirect) {
                window.location.href = res.redirect;
            }
        });
}

// Quick View Modal
function showQuickModal(itemId) {
    fetch('live_auction.php?ajax=1&action=get_modal_details&item_id=' + itemId)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                document.getElementById('modalLotBadge').textContent = '#' + data.auction_code;
                document.getElementById('modalCondition').textContent = data.condition_type;
                document.getElementById('modalMainImg').src = data.image;
                document.getElementById('modalTitle').textContent = data.title;
                document.getElementById('modalDesc').textContent = data.description;
                document.getElementById('modalPrice').textContent = 'Rs. ' + data.current_price;
                document.getElementById('modalLocation').textContent = data.location + (data.distance_km ? ' (' + data.distance_km + 'km)' : '');
                document.getElementById('modalBids').textContent = data.bid_count + ' bids';
                document.getElementById('modalBidLink').href = 'live_auction.php?id=' + data.id;

                document.getElementById('quickViewModal').classList.add('active');
                document.body.style.overflow = 'hidden';
            }
        });
}

function closeQuickModal() {
    document.getElementById('quickViewModal').classList.remove('active');
    document.body.style.overflow = '';
}
</script>

<?php 
// 6. Footer Include
if (file_exists(__DIR__ . '/../includes/footer.php')) {
    include_once __DIR__ . '/../includes/footer.php';
} elseif (file_exists(__DIR__ . '/includes/footer.php')) {
    include_once __DIR__ . '/includes/footer.php';
}
?>