<?php
/**
 * Live Auctions Catalog - Geo Auction System
 * File: items.php
 * 
 * Features:
 * - Emerald Green (#047857 / Tailwind emerald-700) Branding & Modern UI
 * - Page Header with Breadcrumbs & Live Stats
 * - Comprehensive Filter Bar (Search, Category Pills, Min/Max Price, Sort)
 * - Responsive Auction Card Component (LIVE pulse badge, Title, Category, Current Bid in Emerald, Bids count, Live Countdown, Place Bid CTA)
 * - Role-Based Actions (Buyer, Seller, Admin)
 * - Pagination Controls & Empty State handling
 * - Secure PDO Prepared Statements with fallback to realistic sample mock auctions
 */

if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

// -----------------------------------------------------------------------------
// 1. User Session & Authentication Context
// -----------------------------------------------------------------------------
$is_logged       = !empty($_SESSION['user_id']);
$current_user_id = $is_logged ? (int)$_SESSION['user_id'] : 0;
$user_name       = $_SESSION['user_name'] ?? ($_SESSION['name'] ?? 'User');
$user_init       = $is_logged ? strtoupper(substr($user_name, 0, 2)) : '';
$user_role       = strtolower(trim($_SESSION['role'] ?? $_SESSION['user_role'] ?? 'buyer'));

// -----------------------------------------------------------------------------
// 2. Request Parameters Sanitization
// -----------------------------------------------------------------------------
$search_query = trim($_GET['q'] ?? '');
$category_req = trim($_GET['category'] ?? '');
$min_price    = (isset($_GET['min_price']) && $_GET['min_price'] !== '') ? max(0, (float)$_GET['min_price']) : null;
$max_price    = (isset($_GET['max_price']) && $_GET['max_price'] !== '') ? max(0, (float)$_GET['max_price']) : null;
$sort_by      = strtolower(trim($_GET['sort'] ?? 'ending_soon'));
$current_page = max(1, (int)($_GET['page'] ?? 1));
$per_page     = 12;

// Allowed sorting keys
$allowed_sorts = ['ending_soon', 'price_asc', 'price_desc', 'most_bids', 'newest'];
if (!in_array($sort_by, $allowed_sorts)) {
    $sort_by = 'ending_soon';
}

// Helper to preserve active query parameters in links
function buildQueryUrl($overrides = []) {
    $params = $_GET;
    foreach ($overrides as $k => $v) {
        if ($v === null || $v === '') {
            unset($params[$k]);
        } else {
            $params[$k] = $v;
        }
    }
    return 'items.php' . (!empty($params) ? ('?' . http_build_query($params)) : '');
}

// -----------------------------------------------------------------------------
// 3. Fallback / Sample Mock Data Array
// -----------------------------------------------------------------------------
$mock_auctions_data = [
    [
        'id'            => 101,
        'title'         => 'Apple MacBook Pro 16" M3 Max (36GB RAM, 1TB SSD) - Space Black',
        'category'      => 'Electronics',
        'category_slug' => 'electronics',
        'starting_bid'  => 650000.00,
        'current_bid'   => 745000.00,
        'bid_count'     => 18,
        'end_time'      => date('Y-m-d H:i:s', time() + 3600 * 4 + 1800), // 4.5h left
        'image'         => 'https://images.unsplash.com/photo-1517336714731-489689fd1ca8?auto=format&fit=crop&w=800&q=80',
        'city'          => 'Karachi',
        'seller_id'     => 2,
        'seller_name'   => 'TechZone Official',
        'status'        => 'active',
        'is_deleted'    => 0,
        'description'   => 'Flawless condition MacBook Pro 16 inch with Apple M3 Max chip, battery health 100%, original 140W charger and packaging.'
    ],
    [
        'id'            => 102,
        'title'         => '19th Century Victorian Solid Gold Pocket Watch w/ Chain & Display Case',
        'category'      => 'Antiques',
        'category_slug' => 'antiques',
        'starting_bid'  => 250000.00,
        'current_bid'   => 380000.00,
        'bid_count'     => 24,
        'end_time'      => date('Y-m-d H:i:s', time() + 3600 * 9 + 720), // 9.2h left
        'image'         => 'https://images.unsplash.com/photo-1509042239860-f550ce710b93?auto=format&fit=crop&w=800&q=80',
        'city'          => 'Lahore',
        'seller_id'     => 3,
        'seller_name'   => 'Royal Heritage Antiques',
        'status'        => 'active',
        'is_deleted'    => 0,
        'description'   => 'Handmade Swiss movement timepiece, 18K solid yellow gold casing, pristine mechanical condition, certified authentic.'
    ],
    [
        'id'            => 103,
        'title'         => 'Original Oil on Canvas "Emerald Twilight" Signed by A. Rahman (2021)',
        'category'      => 'Art',
        'category_slug' => 'art',
        'starting_bid'  => 150000.00,
        'current_bid'   => 195000.00,
        'bid_count'     => 11,
        'end_time'      => date('Y-m-d H:i:s', time() + 3600 * 14 + 1200), // 14.3h left
        'image'         => 'https://images.unsplash.com/photo-1579783900882-c0d3dad7b119?auto=format&fit=crop&w=800&q=80',
        'city'          => 'Islamabad',
        'seller_id'     => 4,
        'seller_name'   => 'Artisan Gallery',
        'status'        => 'active',
        'is_deleted'    => 0,
        'description'   => 'Breathtaking 36x48 inch contemporary oil painting depicting mystical twilight hills in textured emerald and gold leaf.'
    ],
    [
        'id'            => 104,
        'title'         => '1968 Ford Mustang Fastback GT 390 V8 - Fully Restored Classic Muscle',
        'category'      => 'Vehicles',
        'category_slug' => 'vehicles',
        'starting_bid'  => 7500000.00,
        'current_bid'   => 8900000.00,
        'bid_count'     => 37,
        'end_time'      => date('Y-m-d H:i:s', time() + 3600 * 2 + 450), // ~2h left
        'image'         => 'https://images.unsplash.com/photo-1584345604476-8ec5e12e42dd?auto=format&fit=crop&w=800&q=80',
        'city'          => 'Lahore',
        'seller_id'     => 1,
        'seller_name'   => 'Apex Classic Motors',
        'status'        => 'active',
        'is_deleted'    => 0,
        'description'   => 'Highland Green 390ci Big Block V8, 4-speed manual Toploader transmission, verified matching numbers, museum grade restore.'
    ],
    [
        'id'            => 105,
        'title'         => '1999 Pokemon 1st Edition Holographic Charizard #4 PSA 9 Mint Condition',
        'category'      => 'Collectibles',
        'category_slug' => 'collectibles',
        'starting_bid'  => 900000.00,
        'current_bid'   => 1250000.00,
        'bid_count'     => 42,
        'end_time'      => date('Y-m-d H:i:s', time() + 3600 * 6 + 180), // 6h left
        'image'         => 'https://images.unsplash.com/photo-1613771404784-3a5686aa2be3?auto=format&fit=crop&w=800&q=80',
        'city'          => 'Karachi',
        'seller_id'     => 5,
        'seller_name'   => 'Vault Collectibles',
        'status'        => 'active',
        'is_deleted'    => 0,
        'description'   => 'The Holy Grail of trading cards. Encapsulated and graded PSA 9 Mint. Flawless foil, centered margins, ultra-rare investment piece.'
    ],
    [
        'id'            => 106,
        'title'         => 'Sony Alpha A7 IV Full-Frame Mirrorless Camera w/ 24-70mm f/2.8 GM Lens',
        'category'      => 'Electronics',
        'category_slug' => 'electronics',
        'starting_bid'  => 480000.00,
        'current_bid'   => 520000.00,
        'bid_count'     => 15,
        'end_time'      => date('Y-m-d H:i:s', time() + 3600 * 18 + 900), // 18.2h left
        'image'         => 'https://images.unsplash.com/photo-1516035069371-29a1b244cc32?auto=format&fit=crop&w=800&q=80',
        'city'          => 'Rawalpindi',
        'seller_id'     => 2,
        'seller_name'   => 'TechZone Official',
        'status'        => 'active',
        'is_deleted'    => 0,
        'description'   => '33MP BSI CMOS Sensor, 4K 60p 10-bit video, paired with Sony G Master 24-70 f2.8 lens. Low shutter count under 2,500.'
    ],
    [
        'id'            => 107,
        'title'         => 'BMW R 1250 GS Adventure (Trophy Edition) - 2023 Low Mileage Tourer',
        'category'      => 'Vehicles',
        'category_slug' => 'vehicles',
        'starting_bid'  => 5200000.00,
        'current_bid'   => 5800000.00,
        'bid_count'     => 29,
        'end_time'      => date('Y-m-d H:i:s', time() + 3600 * 8 + 600), // 8.1h left
        'image'         => 'https://images.unsplash.com/photo-1558981403-c5f9899a28bc?auto=format&fit=crop&w=800&q=80',
        'city'          => 'Islamabad',
        'seller_id'     => 1,
        'seller_name'   => 'Apex Classic Motors',
        'status'        => 'active',
        'is_deleted'    => 0,
        'description'   => 'Equipped with dynamic ESA, shift assistant pro, Akrapovic exhaust, full aluminum pannier set. Islamabad registered.'
    ],
    [
        'id'            => 108,
        'title'         => 'Authentic Vintage Marine Brass Sextant & Wooden Compass Box (Circa 1924)',
        'category'      => 'Antiques',
        'category_slug' => 'antiques',
        'starting_bid'  => 120000.00,
        'current_bid'   => 165000.00,
        'bid_count'     => 8,
        'end_time'      => date('Y-m-d H:i:s', time() + 3600 * 22 + 400), // 22h left
        'image'         => 'https://images.unsplash.com/photo-1526374965328-7f61d4dc18c5?auto=format&fit=crop&w=800&q=80',
        'city'          => 'Peshawar',
        'seller_id'     => 3,
        'seller_name'   => 'Royal Heritage Antiques',
        'status'        => 'active',
        'is_deleted'    => 0,
        'description'   => 'Original Royal Navy navigational instruments housed in hand-carved mahogany box with working mirrors and vernier scale.'
    ],
    [
        'id'            => 109,
        'title'         => 'Signed Michael Jordan 1998 Chicago Bulls Finals Home Jersey w/ COA',
        'category'      => 'Collectibles',
        'category_slug' => 'collectibles',
        'starting_bid'  => 2500000.00,
        'current_bid'   => 3100000.00,
        'bid_count'     => 53,
        'end_time'      => date('Y-m-d H:i:s', time() + 3600 * 3 + 300), // 3.1h left
        'image'         => 'https://images.unsplash.com/photo-1546519638-68e109498ffc?auto=format&fit=crop&w=800&q=80',
        'city'          => 'Karachi',
        'seller_id'     => 5,
        'seller_name'   => 'Vault Collectibles',
        'status'        => 'active',
        'is_deleted'    => 0,
        'description'   => 'Authentic Mitchell & Ness 1998 The Last Dance finals jersey autographed by Michael Jordan in bold silver paint pen. Beckett Authenticated.'
    ],
    [
        'id'            => 110,
        'title'         => 'Modern Bronze Sculpture "Ascension" by Farhan Qureshi (Limited Ed. 3/10)',
        'category'      => 'Art',
        'category_slug' => 'art',
        'starting_bid'  => 350000.00,
        'current_bid'   => 450000.00,
        'bid_count'     => 14,
        'end_time'      => date('Y-m-d H:i:s', time() + 3600 * 12 + 800), // 12.2h left
        'image'         => 'https://images.unsplash.com/photo-1561839561-b13bcfe95249?auto=format&fit=crop&w=800&q=80',
        'city'          => 'Lahore',
        'seller_id'     => 4,
        'seller_name'   => 'Artisan Gallery',
        'status'        => 'active',
        'is_deleted'    => 0,
        'description'   => 'Heavy cast patinated bronze abstract sculpture mounted on solid black Italian Nero Marquina marble pedestal.'
    ],
    [
        'id'            => 111,
        'title'         => 'DJI Mavic 3 Pro Cine Drone Combo w/ Hasselblad Camera & RC Pro',
        'category'      => 'Electronics',
        'category_slug' => 'electronics',
        'starting_bid'  => 750000.00,
        'current_bid'   => 820000.00,
        'bid_count'     => 21,
        'end_time'      => date('Y-m-d H:i:s', time() + 3600 * 5 + 500), // 5.1h left
        'image'         => 'https://images.unsplash.com/photo-1527977966376-1c8408f9f108?auto=format&fit=crop&w=800&q=80',
        'city'          => 'Islamabad',
        'seller_id'     => 2,
        'seller_name'   => 'TechZone Official',
        'status'        => 'active',
        'is_deleted'    => 0,
        'description'   => 'Apple ProRes 422 HQ 5.1K video, triple-camera system, 1TB internal SSD, 3 flight batteries, hard transport case.'
    ],
    [
        'id'            => 112,
        'title'         => 'Mercedes-Benz G63 AMG Edition 1 (Obsidian Black, Designo Nappa Interior)',
        'category'      => 'Vehicles',
        'category_slug' => 'vehicles',
        'starting_bid'  => 38000000.00,
        'current_bid'   => 42000000.00,
        'bid_count'     => 64,
        'end_time'      => date('Y-m-d H:i:s', time() + 3600 * 1 + 250), // 1h left
        'image'         => 'https://images.unsplash.com/photo-1520050206274-a1ae44613e6d?auto=format&fit=crop&w=800&q=80',
        'city'          => 'Lahore',
        'seller_id'     => 1,
        'seller_name'   => 'Apex Classic Motors',
        'status'        => 'active',
        'is_deleted'    => 0,
        'description'   => '4.0L V8 Biturbo producing 577 hp. Burmester Surround Sound, 22-inch forged cross-spoke wheels, ceramic coated, single owner.'
    ]
];

// -----------------------------------------------------------------------------
// 4. PDO Database Connection & Fetching Logic
// -----------------------------------------------------------------------------
$db_host = 'localhost';
$db_name = 'geo_auction_db';
$db_user = 'root';
$db_pass = '';
$pdo     = null;
$db_connected = false;

try {
    $pdo = new PDO("mysql:host={$db_host};dbname={$db_name};charset=utf8mb4", $db_user, $db_pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false
    ]);
    $db_connected = true;
} catch (Throwable $e) {
    $pdo = null;
    $db_connected = false;
}

$items_list       = [];
$total_items      = 0;
$total_pages      = 1;
$categories_stats = [
    'Electronics'  => 0,
    'Antiques'     => 0,
    'Art'          => 0,
    'Vehicles'     => 0,
    'Collectibles' => 0
];

// Helper to sanitize image path
function resolveAuctionImage($item) {
    if (!empty($item['image'])) {
        return $item['image'];
    }
    if (!empty($item['featured_image'])) {
        $p = trim($item['featured_image']);
        if (preg_match('/^(https?:\/\/|\/\/|data:)/i', $p)) return $p;
        if (file_exists(__DIR__ . '/' . $p)) return $p;
        return $p;
    }
    if (!empty($item['images'])) {
        $decoded = json_decode($item['images'], true);
        if (is_array($decoded) && !empty($decoded[0])) {
            $p = trim($decoded[0], " \t\n\r\0\x0B\"'[]\\");
            $p = str_replace(['\\/', '//'], '/', $p);
            return $p;
        }
    }
    // High quality themed fallbacks
    $cat = strtolower($item['category'] ?? $item['cat_name'] ?? '');
    if (strpos($cat, 'vehicle') !== false || strpos($cat, 'motor') !== false || strpos($cat, 'bike') !== false) {
        return 'https://images.unsplash.com/photo-1552519507-da3b142c6e3d?auto=format&fit=crop&w=800&q=80';
    }
    if (strpos($cat, 'art') !== false) {
        return 'https://images.unsplash.com/photo-1579783900882-c0d3dad7b119?auto=format&fit=crop&w=800&q=80';
    }
    if (strpos($cat, 'antique') !== false || strpos($cat, 'watch') !== false || strpos($cat, 'jewel') !== false) {
        return 'https://images.unsplash.com/photo-1509042239860-f550ce710b93?auto=format&fit=crop&w=800&q=80';
    }
    if (strpos($cat, 'collect') !== false) {
        return 'https://images.unsplash.com/photo-1613771404784-3a5686aa2be3?auto=format&fit=crop&w=800&q=80';
    }
    return 'https://images.unsplash.com/photo-1517336714731-489689fd1ca8?auto=format&fit=crop&w=800&q=80';
}

if ($db_connected && $pdo !== null) {
    try {
        // Fetch Categories
        $cat_stmt = $pdo->query("SELECT id, name, slug FROM categories WHERE status = 'active' ORDER BY sort_order ASC, name ASC");
        $raw_cats = $cat_stmt->fetchAll();
        $categories_stats = [];
        foreach ($raw_cats as $rc) {
            $categories_stats[$rc['name']] = 0;
        }

        // Build Prepared Query
        $where_clauses = ["(i.is_deleted = 0 OR i.is_deleted IS NULL)", "i.status = 'active'"];
        $params        = [];

        // Search query
        if ($search_query !== '') {
            $where_clauses[] = "(i.title LIKE :q OR i.description LIKE :q OR i.brand LIKE :q OR i.model LIKE :q)";
            $params[':q'] = '%' . $search_query . '%';
        }

        // Category filter
        if ($category_req !== '' && strtolower($category_req) !== 'all') {
            $where_clauses[] = "(c.name = :cat_name OR c.slug = :cat_slug OR i.category_id = :cat_id)";
            $params[':cat_name'] = $category_req;
            $params[':cat_slug'] = strtolower(str_replace(' ', '-', $category_req));
            $params[':cat_id']   = is_numeric($category_req) ? (int)$category_req : 0;
        }

        // Min price
        if ($min_price !== null) {
            $where_clauses[] = "COALESCE(i.current_price, i.starting_price) >= :min_p";
            $params[':min_p'] = $min_price;
        }

        // Max price
        if ($max_price !== null) {
            $where_clauses[] = "COALESCE(i.current_price, i.starting_price) <= :max_p";
            $params[':max_p'] = $max_price;
        }

        $where_sql = implode(' AND ', $where_clauses);

        // Sorting
        switch ($sort_by) {
            case 'price_asc':
                $order_sql = "ORDER BY COALESCE(i.current_price, i.starting_price) ASC, i.id DESC";
                break;
            case 'price_desc':
                $order_sql = "ORDER BY COALESCE(i.current_price, i.starting_price) DESC, i.id DESC";
                break;
            case 'most_bids':
                $order_sql = "ORDER BY i.bid_count DESC, i.id DESC";
                break;
            case 'newest':
                $order_sql = "ORDER BY i.created_at DESC, i.id DESC";
                break;
            case 'ending_soon':
            default:
                $order_sql = "ORDER BY (i.end_time IS NULL) ASC, i.end_time ASC, i.id DESC";
                break;
        }

        // 1. Total Count Statement
        $count_query = "
            SELECT COUNT(*) AS total 
            FROM items i 
            LEFT JOIN categories c ON c.id = i.category_id 
            WHERE {$where_sql}
        ";
        $count_stmt = $pdo->prepare($count_query);
        $count_stmt->execute($params);
        $total_items = (int)$count_stmt->fetchColumn();
        $total_pages = max(1, (int)ceil($total_items / $per_page));

        if ($current_page > $total_pages) {
            $current_page = $total_pages;
        }
        $offset = ($current_page - 1) * $per_page;

        // 2. Paginated Items Statement
        $items_query = "
            SELECT 
                i.id,
                i.title,
                i.description,
                i.category_id,
                i.starting_price,
                i.current_price,
                i.bid_count,
                i.end_time,
                i.featured_image,
                i.images,
                i.city,
                i.seller_id,
                i.status,
                i.is_deleted,
                c.name AS category_name,
                c.slug AS category_slug,
                u.name AS seller_name
            FROM items i
            LEFT JOIN categories c ON c.id = i.category_id
            LEFT JOIN users u ON u.id = i.seller_id
            WHERE {$where_sql}
            {$order_sql}
            LIMIT :lim OFFSET :off
        ";
        $fetch_stmt = $pdo->prepare($items_query);
        foreach ($params as $k => $v) {
            $fetch_stmt->bindValue($k, $v);
        }
        $fetch_stmt->bindValue(':lim', (int)$per_page, PDO::PARAM_INT);
        $fetch_stmt->bindValue(':off', (int)$offset, PDO::PARAM_INT);
        $fetch_stmt->execute();
        $db_rows = $fetch_stmt->fetchAll();

        if (!empty($db_rows)) {
            foreach ($db_rows as $row) {
                $cur = (float)($row['current_price'] > 0 ? $row['current_price'] : $row['starting_price']);
                $items_list[] = [
                    'id'            => (int)$row['id'],
                    'title'         => $row['title'],
                    'category'      => $row['category_name'] ?: 'General',
                    'category_slug' => $row['category_slug'] ?: 'general',
                    'starting_bid'  => (float)$row['starting_price'],
                    'current_bid'   => $cur,
                    'bid_count'     => (int)$row['bid_count'],
                    'end_time'      => $row['end_time'] ?: date('Y-m-d H:i:s', time() + 86400 * 2),
                    'image'         => resolveAuctionImage($row),
                    'city'          => $row['city'] ?: 'Karachi',
                    'seller_id'     => (int)$row['seller_id'],
                    'seller_name'   => $row['seller_name'] ?: 'Verified Seller',
                    'status'        => $row['status'],
                    'is_deleted'    => (int)$row['is_deleted'],
                    'description'   => $row['description'] ?: 'Official verified auction lot.'
                ];
            }
        }

        // Count category statistics from database
        $cat_counts_stmt = $pdo->query("
            SELECT c.name, COUNT(i.id) AS cnt 
            FROM categories c 
            JOIN items i ON i.category_id = c.id 
            WHERE (i.is_deleted = 0 OR i.is_deleted IS NULL) AND i.status = 'active'
            GROUP BY c.id, c.name
        ");
        while ($cr = $cat_counts_stmt->fetch()) {
            $categories_stats[$cr['name']] = (int)$cr['cnt'];
        }

    } catch (Throwable $db_err) {
        // Fallback to sample mock data if query throws
        $items_list = [];
    }
}

// -----------------------------------------------------------------------------
// 5. Fallback Mock Data Filtering & Pagination (When DB returns 0 or offline)
// -----------------------------------------------------------------------------
if (empty($items_list) && !($db_connected && $total_items === 0 && ($search_query !== '' || $category_req !== '' || $min_price !== null || $max_price !== null))) {
    // If not actively filtered to 0 on a live DB, load and filter the mock data array
    $filtered_mock = $mock_auctions_data;

    // Filter by Search Query
    if ($search_query !== '') {
        $q_lower = strtolower($search_query);
        $filtered_mock = array_filter($filtered_mock, function($item) use ($q_lower) {
            return (strpos(strtolower($item['title']), $q_lower) !== false ||
                    strpos(strtolower($item['category']), $q_lower) !== false ||
                    strpos(strtolower($item['description']), $q_lower) !== false);
        });
    }

    // Filter by Category
    if ($category_req !== '' && strtolower($category_req) !== 'all') {
        $c_lower = strtolower($category_req);
        $filtered_mock = array_filter($filtered_mock, function($item) use ($c_lower) {
            return (strtolower($item['category']) === $c_lower ||
                    strtolower($item['category_slug']) === $c_lower);
        });
    }

    // Filter by Price Range
    if ($min_price !== null) {
        $filtered_mock = array_filter($filtered_mock, function($item) use ($min_price) {
            return $item['current_bid'] >= $min_price;
        });
    }
    if ($max_price !== null) {
        $filtered_mock = array_filter($filtered_mock, function($item) use ($max_price) {
            return $item['current_bid'] <= $max_price;
        });
    }

    // Sort Mock Data
    usort($filtered_mock, function($a, $b) use ($sort_by) {
        if ($sort_by === 'price_asc') {
            return $a['current_bid'] <=> $b['current_bid'];
        } elseif ($sort_by === 'price_desc') {
            return $b['current_bid'] <=> $a['current_bid'];
        } elseif ($sort_by === 'most_bids') {
            return $b['bid_count'] <=> $a['bid_count'];
        } elseif ($sort_by === 'newest') {
            return $b['id'] <=> $a['id'];
        } else {
            // Ending soonest
            return strtotime($a['end_time']) <=> strtotime($b['end_time']);
        }
    });

    $total_items = count($filtered_mock);
    $total_pages = max(1, (int)ceil($total_items / $per_page));
    if ($current_page > $total_pages) {
        $current_page = $total_pages;
    }
    $offset = ($current_page - 1) * $per_page;
    $items_list = array_slice($filtered_mock, $offset, $per_page);

    // Compute mock stats for categories
    foreach ($mock_auctions_data as $m) {
        $cat_n = $m['category'];
        if (!isset($categories_stats[$cat_n])) {
            $categories_stats[$cat_n] = 0;
        }
        $categories_stats[$cat_n]++;
    }
}

// Global stats counters
$active_filters_count = 0;
if ($search_query !== '') $active_filters_count++;
if ($category_req !== '' && strtolower($category_req) !== 'all') $active_filters_count++;
if ($min_price !== null) $active_filters_count++;
if ($max_price !== null) $active_filters_count++;
if ($sort_by !== 'ending_soon') $active_filters_count++;

// Standard categories list for pills
$standard_pills = ['All', 'Electronics', 'Antiques', 'Art', 'Vehicles', 'Collectibles'];
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Live Auctions | Geo Auction</title>
    <meta name="description" content="Explore all active auction items, place real-time bids, and track ending times on Geo Auction.">
    
    <!-- Google Fonts: Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- FontAwesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Tailwind CSS (via CDN) with Emerald Theme Extentions -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        emerald: {
                            50: '#ecfdf5',
                            100: '#d1fae5',
                            200: '#a7f3d0',
                            300: '#6ee7b7',
                            400: '#34d399',
                            500: '#10b981',
                            600: '#059669',
                            700: '#047857', // Primary Accent Color
                            800: '#065f46',
                            900: '#064e3b',
                        }
                    },
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', 'system-ui', 'sans-serif'],
                    }
                }
            }
        }
    </script>

    <style>
        /* Pulse Animation for LIVE Indicator */
        @keyframes pulse-ring {
            0% { transform: scale(0.9); opacity: 0.8; }
            50% { transform: scale(1.3); opacity: 0.2; }
            100% { transform: scale(0.9); opacity: 0.8; }
        }
        .pulse-beacon {
            animation: pulse-ring 1.8s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }

        /* Line Clamping */
        .line-clamp-2 {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        /* Glassmorphism Accents */
        .glass-badge {
            background: rgba(15, 23, 42, 0.75);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
        }
        .glass-badge-emerald {
            background: rgba(4, 120, 87, 0.90);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
        }
        
        /* Modal Backdrop Fade */
        .modal-fade {
            transition: opacity 0.25s ease, visibility 0.25s ease;
        }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 font-sans min-h-screen flex flex-col antialiased">

<!-- ======================================================================= -->
<!-- Top Navigation Bar                                                      -->
<!-- ======================================================================= -->
<header class="sticky top-0 z-40 bg-white/95 backdrop-blur-md border-b border-slate-200 transition-all shadow-sm">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-20 flex items-center justify-between gap-4">
        <!-- Logo & Main Nav -->
        <div class="flex items-center gap-8">
            <a href="index.php" class="flex items-center gap-2 flex-shrink-0" title="Geo Auction Home">
                <img src="assets/image/Geo-auction-logo-clean.png" alt="Geo Auction" class="h-10 w-auto object-contain" onerror="this.onerror=null;this.src='assets/image/Geo-auction.png'">
            </a>
            <nav class="hidden md:flex items-center space-x-1 font-medium text-sm text-slate-600">
                <a href="index.php" class="px-3.5 py-2 rounded-lg hover:text-emerald-700 hover:bg-emerald-50 transition">Home</a>
                <a href="items.php" class="px-3.5 py-2 rounded-lg text-emerald-700 font-semibold bg-emerald-50/80 transition">Live Auctions</a>
                <a href="index.php#category" class="px-3.5 py-2 rounded-lg hover:text-emerald-700 hover:bg-emerald-50 transition">Categories</a>
                <a href="index.php#about" class="px-3.5 py-2 rounded-lg hover:text-emerald-700 hover:bg-emerald-50 transition">About</a>
            </nav>
        </div>

        <!-- Right Side: User Controls / Auth -->
        <div class="flex items-center gap-3">
            <?php if ($is_logged): ?>
                <?php if (in_array($user_role, ['seller', 'admin'])): ?>
                    <a href="user/add_items.php" class="hidden sm:inline-flex items-center gap-2 bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-bold px-3.5 py-2 rounded-lg shadow-sm transition">
                        <i class="fas fa-plus-circle"></i> + Create Auction
                    </a>
                <?php endif; ?>
                <a href="user/wallet.php" class="inline-flex items-center gap-2 text-slate-700 hover:text-emerald-700 bg-slate-100 hover:bg-emerald-50 px-3 py-2 rounded-lg text-xs font-semibold transition border border-slate-200">
                    <i class="fas fa-wallet text-emerald-700"></i>
                    <span>Wallet</span>
                </a>
                <a href="user/profile.php" class="flex items-center gap-2.5 pl-2 group">
                    <div class="w-9 h-9 rounded-full bg-emerald-700 text-white font-bold text-xs flex items-center justify-center shadow-sm ring-2 ring-emerald-600/20">
                        <?= htmlspecialchars($user_init); ?>
                    </div>
                    <span class="hidden lg:inline text-xs font-semibold text-slate-800 group-hover:text-emerald-700 transition">
                        <?= htmlspecialchars($user_name); ?>
                    </span>
                </a>
            <?php else: ?>
                <a href="login.php" class="text-sm font-semibold text-slate-700 hover:text-emerald-700 px-3 py-2 rounded-lg transition">Login</a>
                <a href="register.php" class="inline-flex items-center gap-2 bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-bold px-4 py-2.5 rounded-lg shadow-sm hover:shadow transition">
                    Register Free
                </a>
            <?php endif; ?>
        </div>
    </div>
</header>

<!-- ======================================================================= -->
<!-- Page Header: Title, Subtitle & Breadcrumbs                             -->
<!-- ======================================================================= -->
<section class="bg-gradient-to-b from-slate-900 via-slate-900 to-slate-800 text-white pt-10 pb-16 relative overflow-hidden">
    <!-- Subtle Pattern Overlay -->
    <div class="absolute inset-0 opacity-10 bg-[radial-gradient(#34d399_1px,transparent_1px)] [background-size:16px_16px] pointer-events-none"></div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
        <!-- Breadcrumb Navigation -->
        <nav class="flex items-center text-xs font-medium text-slate-400 mb-4" aria-label="Breadcrumb">
            <ol class="flex items-center space-x-2">
                <li>
                    <a href="index.php" class="hover:text-emerald-400 transition flex items-center gap-1.5">
                        <i class="fas fa-home text-xs"></i> Home
                    </a>
                </li>
                <li class="text-slate-600">/</li>
                <li class="text-emerald-400 font-semibold flex items-center gap-1.5">
                    <i class="fas fa-gavel text-xs"></i> Items
                </li>
            </ol>
        </nav>

        <div class="flex flex-col md:flex-row md:items-end justify-between gap-6">
            <div>
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-semibold bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 mb-3">
                    <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                    Verified Real-Time Marketplace
                </div>
                <h1 class="text-3xl sm:text-4xl lg:text-5xl font-extrabold tracking-tight text-white">
                    All Live Auctions
                </h1>
                <p class="mt-2.5 text-base sm:text-lg text-slate-300 max-w-2xl font-normal">
                    Explore all active auction items, place bids, and track ending times.
                </p>
            </div>

            <!-- Header Quick Metric Badges -->
            <div class="flex items-center flex-wrap gap-3">
                <div class="bg-slate-800/80 backdrop-blur border border-slate-700/80 rounded-xl px-4 py-2.5 flex items-center gap-3">
                    <div class="w-9 h-9 rounded-lg bg-emerald-500/20 text-emerald-400 flex items-center justify-center font-bold text-sm">
                        <i class="fas fa-layer-group"></i>
                    </div>
                    <div>
                        <div class="text-xs text-slate-400 font-medium">Available Lots</div>
                        <div class="text-base font-extrabold text-white"><?= number_format($total_items); ?> Items</div>
                    </div>
                </div>
                <div class="bg-slate-800/80 backdrop-blur border border-slate-700/80 rounded-xl px-4 py-2.5 flex items-center gap-3">
                    <div class="w-9 h-9 rounded-lg bg-amber-500/20 text-amber-400 flex items-center justify-center font-bold text-sm">
                        <i class="fas fa-bolt"></i>
                    </div>
                    <div>
                        <div class="text-xs text-slate-400 font-medium">Bidding Status</div>
                        <div class="text-base font-extrabold text-emerald-400">100% Live</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ======================================================================= -->
<!-- Filters & Search Controls Topbar                                        -->
<!-- ======================================================================= -->
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 -mt-8 relative z-20 mb-8 w-full">
    <div class="bg-white rounded-2xl shadow-xl border border-slate-200/80 p-5 sm:p-6 transition-all">
        <!-- Form Container -->
        <form method="GET" action="items.php" id="filterForm" class="space-y-5">
            
            <!-- Category Filter Pills (Quick Tap) -->
            <div class="flex items-center gap-2 overflow-x-auto pb-2 scrollbar-none border-b border-slate-100">
                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider mr-2 flex-shrink-0">
                    <i class="fas fa-tags text-emerald-700 mr-1"></i> Categories:
                </span>
                <?php foreach ($standard_pills as $pill): 
                    $pill_val = strtolower($pill);
                    $is_active = ($category_req === '' && $pill === 'All') || 
                                 (strtolower($category_req) === $pill_val) || 
                                 (strtolower($category_req) === 'all' && $pill === 'All');
                ?>
                    <a href="<?= buildQueryUrl(['category' => ($pill === 'All' ? null : $pill), 'page' => 1]); ?>" 
                       class="px-4 py-1.5 rounded-full text-xs font-semibold transition-all whitespace-nowrap flex items-center gap-1.5 <?= $is_active ? 'bg-emerald-700 text-white shadow-sm ring-2 ring-emerald-700/30' : 'bg-slate-100 text-slate-600 hover:bg-slate-200/80 hover:text-slate-900'; ?>">
                        <?= htmlspecialchars($pill); ?>
                        <?php if ($pill !== 'All' && isset($categories_stats[$pill])): ?>
                            <span class="text-[10px] px-1.5 py-0.2 rounded-full <?= $is_active ? 'bg-emerald-800 text-emerald-100' : 'bg-slate-200 text-slate-500'; ?>">
                                <?= $categories_stats[$pill]; ?>
                            </span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>

            <!-- Controls Grid: Search, Category Select, Price Range, Sort, Apply -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3 sm:gap-4 items-center">
                
                <!-- Search Input (4 Columns) -->
                <div class="lg:col-span-4 relative">
                    <label for="searchInput" class="sr-only">Search by item name</label>
                    <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                        <i class="fas fa-search text-sm"></i>
                    </div>
                    <input type="text" name="q" id="searchInput" value="<?= htmlspecialchars($search_query); ?>" 
                           placeholder="Search by item name or keyword..." 
                           class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium text-slate-800 focus:outline-none focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/20 focus:bg-white transition">
                </div>

                <!-- Category Dropdown (3 Columns) -->
                <div class="lg:col-span-3 relative">
                    <label for="categorySelect" class="sr-only">Category</label>
                    <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                        <i class="fas fa-filter text-sm"></i>
                    </div>
                    <select name="category" id="categorySelect" 
                            class="w-full pl-10 pr-9 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium text-slate-800 focus:outline-none focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/20 focus:bg-white transition appearance-none cursor-pointer">
                        <option value="">All Categories</option>
                        <?php foreach ($standard_pills as $sp): if ($sp === 'All') continue; ?>
                            <option value="<?= htmlspecialchars($sp); ?>" <?= strtolower($category_req) === strtolower($sp) ? 'selected' : ''; ?>>
                                <?= htmlspecialchars($sp); ?>
                            </option>
                        <?php endforeach; ?>
                        <?php foreach ($categories_stats as $cName => $cCount): 
                            if (in_array($cName, $standard_pills)) continue; 
                        ?>
                            <option value="<?= htmlspecialchars($cName); ?>" <?= strtolower($category_req) === strtolower($cName) ? 'selected' : ''; ?>>
                                <?= htmlspecialchars($cName); ?> (<?= $cCount; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="absolute inset-y-0 right-0 pr-3.5 flex items-center pointer-events-none text-slate-400">
                        <i class="fas fa-chevron-down text-xs"></i>
                    </div>
                </div>

                <!-- Price/Bid Range (3 Columns) -->
                <div class="lg:col-span-3 grid grid-cols-2 gap-2">
                    <div class="relative">
                        <input type="number" name="min_price" value="<?= $min_price !== null ? htmlspecialchars($min_price) : ''; ?>" 
                               placeholder="Min PKR" min="0" step="500" 
                               class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium text-slate-800 focus:outline-none focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/20 focus:bg-white transition">
                    </div>
                    <div class="relative">
                        <input type="number" name="max_price" value="<?= $max_price !== null ? htmlspecialchars($max_price) : ''; ?>" 
                               placeholder="Max PKR" min="0" step="500" 
                               class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium text-slate-800 focus:outline-none focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/20 focus:bg-white transition">
                    </div>
                </div>

                <!-- Sort Dropdown & Submit (2 Columns) -->
                <div class="lg:col-span-2 flex items-center gap-2">
                    <div class="relative w-full">
                        <select name="sort" id="sortSelect" onchange="this.form.submit()" 
                                class="w-full pl-3 pr-8 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium text-slate-800 focus:outline-none focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/20 focus:bg-white transition appearance-none cursor-pointer">
                            <option value="ending_soon" <?= $sort_by === 'ending_soon' ? 'selected' : ''; ?>>Ending Soonest</option>
                            <option value="price_asc" <?= $sort_by === 'price_asc' ? 'selected' : ''; ?>>Price: Low to High</option>
                            <option value="price_desc" <?= $sort_by === 'price_desc' ? 'selected' : ''; ?>>Price: High to Low</option>
                            <option value="most_bids" <?= $sort_by === 'most_bids' ? 'selected' : ''; ?>>Most Bids</option>
                            <option value="newest" <?= $sort_by === 'newest' ? 'selected' : ''; ?>>Newly Listed</option>
                        </select>
                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-slate-400">
                            <i class="fas fa-chevron-down text-xs"></i>
                        </div>
                    </div>
                    <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white font-bold p-2.5 rounded-xl shadow-sm hover:shadow transition flex-shrink-0" title="Apply Filter">
                        <i class="fas fa-sliders-h"></i>
                    </button>
                </div>

            </div>

            <!-- Active Filters Breadcrumb Tags (If applied) -->
            <?php if ($active_filters_count > 0): ?>
                <div class="pt-3 border-t border-slate-100 flex flex-wrap items-center gap-2 text-xs">
                    <span class="text-slate-500 font-semibold mr-1">Active Filters:</span>
                    <?php if ($search_query !== ''): ?>
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-emerald-50 text-emerald-800 font-medium border border-emerald-200">
                            Keyword: "<?= htmlspecialchars($search_query); ?>"
                            <a href="<?= buildQueryUrl(['q' => null, 'page' => 1]); ?>" class="text-emerald-600 hover:text-emerald-900 ml-1"><i class="fas fa-times"></i></a>
                        </span>
                    <?php endif; ?>
                    <?php if ($category_req !== '' && strtolower($category_req) !== 'all'): ?>
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-emerald-50 text-emerald-800 font-medium border border-emerald-200">
                            Category: <?= htmlspecialchars($category_req); ?>
                            <a href="<?= buildQueryUrl(['category' => null, 'page' => 1]); ?>" class="text-emerald-600 hover:text-emerald-900 ml-1"><i class="fas fa-times"></i></a>
                        </span>
                    <?php endif; ?>
                    <?php if ($min_price !== null): ?>
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-emerald-50 text-emerald-800 font-medium border border-emerald-200">
                            Min: PKR <?= number_format($min_price); ?>
                            <a href="<?= buildQueryUrl(['min_price' => null, 'page' => 1]); ?>" class="text-emerald-600 hover:text-emerald-900 ml-1"><i class="fas fa-times"></i></a>
                        </span>
                    <?php endif; ?>
                    <?php if ($max_price !== null): ?>
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-emerald-50 text-emerald-800 font-medium border border-emerald-200">
                            Max: PKR <?= number_format($max_price); ?>
                            <a href="<?= buildQueryUrl(['max_price' => null, 'page' => 1]); ?>" class="text-emerald-600 hover:text-emerald-900 ml-1"><i class="fas fa-times"></i></a>
                        </span>
                    <?php endif; ?>
                    <?php if ($sort_by !== 'ending_soon'): ?>
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-slate-100 text-slate-700 font-medium border border-slate-200">
                            Sorted: <?= ucwords(str_replace('_', ' ', $sort_by)); ?>
                            <a href="<?= buildQueryUrl(['sort' => null, 'page' => 1]); ?>" class="text-slate-500 hover:text-slate-800 ml-1"><i class="fas fa-times"></i></a>
                        </span>
                    <?php endif; ?>
                    <a href="items.php" class="text-rose-600 hover:text-rose-800 font-bold ml-2 transition">Clear All</a>
                </div>
            <?php endif; ?>

        </form>
    </div>
</div>

<!-- ======================================================================= -->
<!-- Main Content Area: Auction Items Grid                                   -->
<!-- ======================================================================= -->
<main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mb-16 flex-grow w-full">

    <?php if (!empty($items_list)): ?>
        <!-- Responsive Grid Layout (Mobile: 1, Tablet: 2, Desktop: 3-4 columns) -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
            <?php foreach ($items_list as $item): 
                $item_id      = (int)$item['id'];
                $item_title   = $item['title'];
                $item_cat     = $item['category'];
                $current_bid  = (float)$item['current_bid'];
                $starting_bid = (float)$item['starting_bid'];
                $bids_count   = (int)$item['bid_count'];
                $end_time_raw = $item['end_time'];
                $end_stamp    = strtotime($end_time_raw);
                $is_ended     = $end_stamp <= time();
                $item_image   = $item['image'];
                $item_city    = $item['city'] ?? 'Karachi';
                $item_seller  = (int)($item['seller_id'] ?? 0);
                
                // Role-Based Permissions
                $is_admin        = ($user_role === 'admin');
                $is_owner_seller = ($user_role === 'seller' && $current_user_id > 0 && $current_user_id === $item_seller);
                $can_manage      = ($is_admin || $is_owner_seller);
            ?>
                <!-- Auction Item Card Component -->
                <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden shadow-sm hover:shadow-xl hover:-translate-y-1 transition-all duration-300 flex flex-col group relative" data-item-id="<?= $item_id; ?>">
                    
                    <!-- 1. Product Image & Badges Overlay -->
                    <div class="relative w-full h-52 bg-slate-900 overflow-hidden">
                        <img src="<?= htmlspecialchars($item_image); ?>" 
                             alt="<?= htmlspecialchars($item_title); ?>" 
                             class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                             loading="lazy"
                             onerror="this.onerror=null;this.src='https://images.unsplash.com/photo-1517336714731-489689fd1ca8?auto=format&fit=crop&w=800&q=80'">
                        
                        <!-- Top-Left Badges: LIVE pulse badge & Category -->
                        <div class="absolute top-3 left-3 flex flex-col gap-1.5 items-start z-10">
                            <!-- LIVE Badge -->
                            <div class="glass-badge-emerald text-white text-[11px] font-extrabold px-2.5 py-1 rounded-full flex items-center gap-1.5 shadow-sm tracking-wide uppercase">
                                <span class="relative flex h-2 w-2">
                                    <span class="pulse-beacon absolute inline-flex h-full w-full rounded-full bg-emerald-300"></span>
                                    <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-200"></span>
                                </span>
                                <?= $is_ended ? 'Ended' : 'LIVE'; ?>
                            </div>

                            <!-- Location Badge -->
                            <div class="glass-badge text-white text-[11px] font-semibold px-2 py-0.5 rounded-md flex items-center gap-1 shadow-sm">
                                <i class="fas fa-map-marker-alt text-emerald-400 text-[10px]"></i>
                                <?= htmlspecialchars($item_city); ?>
                            </div>
                        </div>

                        <!-- Top-Right: Quick View Button -->
                        <div class="absolute top-3 right-3 z-10">
                            <button type="button" 
                                    onclick="openItemQuickView(<?= htmlspecialchars(json_encode($item), ENT_QUOTES, 'UTF-8'); ?>)"
                                    class="w-8 h-8 rounded-full bg-white/90 hover:bg-white text-slate-700 hover:text-emerald-700 flex items-center justify-center shadow-md transition transform hover:scale-110"
                                    title="Quick View Details">
                                <i class="fas fa-eye text-xs"></i>
                            </button>
                        </div>

                        <!-- Role-based Admin/Seller Corner Badge (If owner or admin) -->
                        <?php if ($can_manage): ?>
                            <div class="absolute bottom-2 right-2 z-10">
                                <span class="bg-amber-500/95 text-slate-950 font-bold text-[10px] px-2 py-0.5 rounded shadow">
                                    <?= $is_admin ? 'Admin Controls' : 'Your Listing'; ?>
                                </span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- 2. Card Body Content -->
                    <div class="p-4 sm:p-5 flex flex-col flex-grow">
                        
                        <!-- Category Tag -->
                        <div class="mb-1.5 flex items-center justify-between">
                            <span class="inline-block text-[11px] font-bold text-emerald-700 bg-emerald-50 px-2.5 py-0.5 rounded-md uppercase tracking-wider">
                                <?= htmlspecialchars($item_cat); ?>
                            </span>
                            <span class="text-xs text-slate-400 font-medium">Lot #<?= $item_id; ?></span>
                        </div>

                        <!-- Item Title with Truncation -->
                        <h2 class="text-base font-bold text-slate-900 group-hover:text-emerald-700 transition-colors line-clamp-2 min-h-[44px] mb-3" title="<?= htmlspecialchars($item_title); ?>">
                            <a href="item-details.php?id=<?= $item_id; ?>" class="hover:underline">
                                <?= htmlspecialchars($item_title); ?>
                            </a>
                        </h2>

                        <!-- Live Countdown Timer Component -->
                        <div class="bg-slate-50 border border-slate-200/80 rounded-xl px-3 py-2 mb-4 flex items-center justify-between">
                            <div class="flex items-center gap-1.5 text-xs text-slate-500 font-medium">
                                <i class="far fa-clock text-emerald-700"></i>
                                <span>Closing in:</span>
                            </div>
                            <div class="font-mono text-xs font-bold text-slate-900 countdown-timer-node <?= $is_ended ? 'text-rose-600' : ''; ?>" 
                                 data-end-timestamp="<?= $end_stamp; ?>">
                                <?= $is_ended ? 'Auction Closed' : 'Calculating...'; ?>
                            </div>
                        </div>

                        <!-- Pricing & Total Bids Row -->
                        <div class="mt-auto pt-3 border-t border-slate-100 flex items-end justify-between">
                            <div>
                                <div class="text-[11px] font-semibold uppercase text-slate-400 tracking-wider">
                                    Current Bid
                                </div>
                                <div class="text-xl font-extrabold text-emerald-700 leading-tight">
                                    <span class="text-xs font-bold">PKR</span> <?= number_format($current_bid); ?>
                                </div>
                            </div>
                            <div class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-slate-100 text-slate-700 text-xs font-semibold">
                                <i class="fas fa-gavel text-slate-400 text-[11px]"></i>
                                <span><?= $bids_count; ?> <?= $bids_count === 1 ? 'Bid' : 'Bids'; ?></span>
                            </div>
                        </div>

                    </div>

                    <!-- 3. Card Footer Action Controls (Role-Based) -->
                    <div class="px-4 py-3 bg-slate-50/80 border-t border-slate-100 flex items-center gap-2">
                        
                        <!-- Primary Call to Action: Place Bid / View Details -->
                        <a href="item-details.php?id=<?= $item_id; ?>" 
                           class="flex-1 inline-flex items-center justify-center gap-2 bg-emerald-700 hover:bg-emerald-800 text-white font-bold text-xs py-2.5 px-3 rounded-xl shadow-sm hover:shadow transition-all duration-200">
                            <i class="fas fa-hand-holding-usd text-xs"></i>
                            <span>Place Bid</span>
                        </a>

                        <!-- Quick View Button -->
                        <button type="button" 
                                onclick="openItemQuickView(<?= htmlspecialchars(json_encode($item), ENT_QUOTES, 'UTF-8'); ?>)"
                                class="inline-flex items-center justify-center text-slate-600 hover:text-emerald-700 hover:bg-white bg-slate-200/60 border border-slate-200 text-xs font-semibold py-2.5 px-3 rounded-xl transition"
                                title="View Specifications">
                            <i class="fas fa-info-circle"></i>
                        </button>

                        <!-- Role-based Admin Controls -->
                        <?php if ($is_admin): ?>
                            <div class="flex items-center gap-1 pl-1 border-l border-slate-200">
                                <a href="admin/items.php?edit=<?= $item_id; ?>" 
                                   class="w-8 h-8 rounded-lg bg-blue-50 text-blue-600 hover:bg-blue-600 hover:text-white flex items-center justify-center text-xs transition" 
                                   title="Admin Edit Item">
                                    <i class="fas fa-pencil-alt"></i>
                                </a>
                                <a href="admin/items.php?action=soft_delete&id=<?= $item_id; ?>" 
                                   onclick="return confirm('Are you sure you want to soft-delete this auction? It will remain visible in the Admin Trash.');"
                                   class="w-8 h-8 rounded-lg bg-rose-50 text-rose-600 hover:bg-rose-600 hover:text-white flex items-center justify-center text-xs transition" 
                                   title="Admin Delete Item">
                                    <i class="fas fa-trash-alt"></i>
                                </a>
                            </div>
                        <?php elseif ($is_owner_seller): ?>
                            <!-- Role-based Seller Controls (Owner only) -->
                            <div class="flex items-center gap-1 pl-1 border-l border-slate-200">
                                <a href="user/edit_item.php?id=<?= $item_id; ?>" 
                                   class="w-8 h-8 rounded-lg bg-amber-50 text-amber-700 hover:bg-amber-600 hover:text-white flex items-center justify-center text-xs transition" 
                                   title="Edit My Auction">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="user/delete_item.php?id=<?= $item_id; ?>" 
                                   onclick="return confirm('Delete this auction? It will be soft-deleted and hidden from buyers.');"
                                   class="w-8 h-8 rounded-lg bg-rose-50 text-rose-600 hover:bg-rose-600 hover:text-white flex items-center justify-center text-xs transition" 
                                   title="Delete Auction">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </div>
                        <?php endif; ?>

                    </div>

                </div>
            <?php endforeach; ?>
        </div>

        <!-- =================================================================== -->
        <!-- Pagination Controls                                                 -->
        <!-- =================================================================== -->
        <?php if ($total_pages > 1): ?>
            <div class="mt-12 flex flex-col sm:flex-row items-center justify-between gap-4 border-t border-slate-200 pt-6">
                <div class="text-xs text-slate-500 font-medium">
                    Showing <span class="font-bold text-slate-800"><?= number_format($offset + 1); ?></span> to 
                    <span class="font-bold text-slate-800"><?= number_format(min($total_items, $offset + $per_page)); ?></span> of 
                    <span class="font-bold text-slate-800"><?= number_format($total_items); ?></span> items
                </div>

                <nav class="flex items-center gap-1" aria-label="Pagination">
                    <!-- Prev Page -->
                    <a href="<?= $current_page > 1 ? buildQueryUrl(['page' => $current_page - 1]) : '#'; ?>" 
                       class="px-3.5 py-2 rounded-xl text-xs font-bold border transition flex items-center gap-1.5 <?= $current_page <= 1 ? 'border-slate-200 text-slate-300 cursor-not-allowed bg-slate-50' : 'border-slate-200 text-slate-700 hover:bg-emerald-50 hover:text-emerald-700 hover:border-emerald-300'; ?>">
                        <i class="fas fa-chevron-left text-[10px]"></i> Previous
                    </a>

                    <!-- Page Numbers -->
                    <?php 
                        $start_p = max(1, $current_page - 2);
                        $end_p   = min($total_pages, $current_page + 2);

                        if ($start_p > 1) {
                            echo '<a href="' . buildQueryUrl(['page' => 1]) . '" class="w-9 h-9 rounded-xl text-xs font-bold border border-slate-200 text-slate-700 hover:bg-emerald-50 hover:text-emerald-700 flex items-center justify-center transition">1</a>';
                            if ($start_p > 2) echo '<span class="px-1 text-slate-400 text-xs">...</span>';
                        }

                        for ($p = $start_p; $p <= $end_p; $p++):
                            $active = ($p === $current_page);
                    ?>
                        <a href="<?= buildQueryUrl(['page' => $p]); ?>" 
                           class="w-9 h-9 rounded-xl text-xs font-bold transition flex items-center justify-center <?= $active ? 'bg-emerald-700 text-white shadow-sm ring-2 ring-emerald-700/30' : 'border border-slate-200 text-slate-700 hover:bg-emerald-50 hover:text-emerald-700'; ?>">
                            <?= $p; ?>
                        </a>
                    <?php endfor; ?>

                    <?php 
                        if ($end_p < $total_pages) {
                            if ($end_p < $total_pages - 1) echo '<span class="px-1 text-slate-400 text-xs">...</span>';
                            echo '<a href="' . buildQueryUrl(['page' => $total_pages]) . '" class="w-9 h-9 rounded-xl text-xs font-bold border border-slate-200 text-slate-700 hover:bg-emerald-50 hover:text-emerald-700 flex items-center justify-center transition">' . $total_pages . '</a>';
                        }
                    ?>

                    <!-- Next Page -->
                    <a href="<?= $current_page < $total_pages ? buildQueryUrl(['page' => $current_page + 1]) : '#'; ?>" 
                       class="px-3.5 py-2 rounded-xl text-xs font-bold border transition flex items-center gap-1.5 <?= $current_page >= $total_pages ? 'border-slate-200 text-slate-300 cursor-not-allowed bg-slate-50' : 'border-slate-200 text-slate-700 hover:bg-emerald-50 hover:text-emerald-700 hover:border-emerald-300'; ?>">
                        Next <i class="fas fa-chevron-right text-[10px]"></i>
                    </a>
                </nav>
            </div>
        <?php endif; ?>

    <?php else: ?>
        <!-- =================================================================== -->
        <!-- Empty State UI (When no auctions match criteria)                   -->
        <!-- =================================================================== -->
        <div class="bg-white rounded-2xl border border-slate-200 p-12 text-center max-w-lg mx-auto my-12 shadow-sm">
            <div class="w-16 h-16 rounded-full bg-emerald-50 text-emerald-700 flex items-center justify-center text-2xl mx-auto mb-4">
                <i class="fas fa-search"></i>
            </div>
            <h2 class="text-xl font-bold text-slate-900 mb-2">No Active Auctions Found</h2>
            <p class="text-sm text-slate-500 mb-6">
                We couldn't find any auctions matching your current filter criteria. Try adjusting your search keyword, category, or price range.
            </p>
            <div class="flex items-center justify-center gap-3">
                <a href="items.php" class="bg-emerald-700 hover:bg-emerald-800 text-white font-bold text-xs px-5 py-2.5 rounded-xl shadow-sm transition">
                    <i class="fas fa-undo mr-1.5"></i> Reset All Filters
                </a>
                <a href="index.php" class="bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs px-5 py-2.5 rounded-xl transition">
                    Back to Home
                </a>
            </div>
        </div>
    <?php endif; ?>

</main>

<!-- ======================================================================= -->
<!-- Universal Quick View Modal (For Buyers, Sellers, Admins)                -->
<!-- ======================================================================= -->
<div id="quickViewModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm opacity-0 pointer-events-none modal-fade">
    <div class="bg-white rounded-3xl shadow-2xl max-w-2xl w-full overflow-hidden border border-slate-200 transform transition-all scale-95" id="quickViewCard">
        <div class="relative">
            <!-- Modal Close Button -->
            <button type="button" onclick="closeItemQuickView()" class="absolute top-4 right-4 z-20 w-9 h-9 rounded-full bg-slate-900/70 hover:bg-slate-900 text-white flex items-center justify-center transition">
                <i class="fas fa-times text-sm"></i>
            </button>
            <!-- Hero Image Preview -->
            <div class="h-64 sm:h-72 w-full bg-slate-950 overflow-hidden relative">
                <img id="qvImage" src="" alt="Item Image" class="w-full h-full object-cover">
                <div class="absolute bottom-3 left-4 flex gap-2">
                    <span id="qvCategory" class="glass-badge-emerald text-white text-xs font-bold px-3 py-1 rounded-full uppercase"></span>
                    <span id="qvCity" class="glass-badge text-white text-xs font-semibold px-2.5 py-1 rounded-full"></span>
                </div>
            </div>
        </div>

        <div class="p-6">
            <h3 id="qvTitle" class="text-xl font-bold text-slate-900 mb-2 leading-snug"></h3>
            <p id="qvDescription" class="text-sm text-slate-600 mb-6 leading-relaxed"></p>

            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 p-4 bg-slate-50 rounded-2xl border border-slate-100 mb-6 text-center">
                <div>
                    <div class="text-[11px] font-bold text-slate-400 uppercase">Current Bid</div>
                    <div id="qvBid" class="text-lg font-extrabold text-emerald-700"></div>
                </div>
                <div>
                    <div class="text-[11px] font-bold text-slate-400 uppercase">Total Bids</div>
                    <div id="qvBidsCount" class="text-lg font-extrabold text-slate-800"></div>
                </div>
                <div class="col-span-2 sm:col-span-1">
                    <div class="text-[11px] font-bold text-slate-400 uppercase">Seller</div>
                    <div id="qvSeller" class="text-sm font-bold text-slate-700 truncate mt-1"></div>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <a id="qvActionBtn" href="#" class="flex-1 bg-emerald-700 hover:bg-emerald-800 text-white font-bold text-sm py-3 px-4 rounded-xl text-center shadow-md hover:shadow-lg transition">
                    <i class="fas fa-gavel mr-1.5"></i> Open Full Auction Details
                </a>
                <button type="button" onclick="closeItemQuickView()" class="px-5 py-3 rounded-xl border border-slate-200 text-slate-600 font-bold text-sm hover:bg-slate-50 transition">
                    Close
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ======================================================================= -->
<!-- Site Footer                                                             -->
<!-- ======================================================================= -->
<footer class="bg-white border-t border-slate-200 mt-auto">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
        <div class="flex flex-col md:flex-row items-center justify-between gap-6">
            <div class="flex items-center gap-3">
                <img src="assets/image/Geo-auction-logo-clean.png" alt="Geo Auction" class="h-8 w-auto" onerror="this.onerror=null;this.src='assets/image/Geo-auction.png'">
                <span class="text-xs text-slate-500 font-medium">© <?= date('Y'); ?> GeoAuction. Real-time online bidding platform.</span>
            </div>
            <div class="flex items-center gap-6 text-xs font-semibold text-slate-600">
                <a href="index.php" class="hover:text-emerald-700 transition">Home</a>
                <a href="items.php" class="hover:text-emerald-700 transition">Live Catalog</a>
                <a href="about.php" class="hover:text-emerald-700 transition">Terms & Conditions</a>
                <a href="contact.php" class="hover:text-emerald-700 transition">Help & Support</a>
            </div>
        </div>
    </div>
</footer>

<!-- ======================================================================= -->
<!-- Interactive Countdown Engine & Modal Scripts                            -->
<!-- ======================================================================= -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    // 1. Live Countdown Timer Ticking Engine
    const timerNodes = document.querySelectorAll('.countdown-timer-node[data-end-timestamp]');

    function updateTimers() {
        const nowSec = Math.floor(Date.now() / 1000);

        timerNodes.forEach(function (node) {
            const endSec = parseInt(node.getAttribute('data-end-timestamp'), 10);
            if (!endSec || isNaN(endSec)) {
                node.textContent = 'Closing soon';
                return;
            }

            const diff = endSec - nowSec;

            if (diff <= 0) {
                node.textContent = 'Auction Closed';
                node.classList.add('text-rose-600');
                return;
            }

            const days  = Math.floor(diff / 86400);
            const hours = Math.floor((diff % 86400) / 3600);
            const mins  = Math.floor((diff % 3600) / 60);
            const secs  = diff % 60;

            const pad = (n) => String(n).padStart(2, '0');

            if (days > 0) {
                node.textContent = `${days}d ${pad(hours)}h ${pad(mins)}m ${pad(secs)}s`;
            } else {
                node.textContent = `${pad(hours)}:${pad(mins)}:${pad(secs)}`;
                if (hours < 1) {
                    node.classList.add('text-rose-600');
                }
            }
        });
    }

    updateTimers();
    setInterval(updateTimers, 1000);
});

// 2. Quick View Modal Engine
function openItemQuickView(item) {
    if (!item) return;

    document.getElementById('qvTitle').textContent = item.title || 'Auction Lot';
    document.getElementById('qvDescription').textContent = item.description || 'Verified authentic auction lot with full documentation.';
    document.getElementById('qvCategory').textContent = item.category || 'General';
    document.getElementById('qvCity').textContent = item.city || 'Karachi';
    document.getElementById('qvBid').textContent = 'PKR ' + Number(item.current_bid).toLocaleString();
    document.getElementById('qvBidsCount').textContent = (item.bid_count || 0) + ' Bids';
    document.getElementById('qvSeller').textContent = item.seller_name || 'Verified Seller';
    
    const imgEl = document.getElementById('qvImage');
    imgEl.src = item.image || '';
    imgEl.onerror = function() {
        this.src = 'https://images.unsplash.com/photo-1517336714731-489689fd1ca8?auto=format&fit=crop&w=800&q=80';
    };

    const actionBtn = document.getElementById('qvActionBtn');
    actionBtn.href = 'item-details.php?id=' + (item.id || '');

    const modal = document.getElementById('quickViewModal');
    const card = document.getElementById('quickViewCard');
    
    modal.classList.remove('opacity-0', 'pointer-events-none');
    card.classList.remove('scale-95');
    card.classList.add('scale-100');
}

function closeItemQuickView() {
    const modal = document.getElementById('quickViewModal');
    const card = document.getElementById('quickViewCard');

    card.classList.remove('scale-100');
    card.classList.add('scale-95');
    modal.classList.add('opacity-0', 'pointer-events-none');
}

// Close modal when clicking outside card or pressing Escape key
document.getElementById('quickViewModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        closeItemQuickView();
    }
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeItemQuickView();
    }
});
</script>

</body>
</html>