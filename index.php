<?php
/**
 * ============================================================================
 * File: index.php
 * Path: /index.php (Project Root)
 * Description: Main Public Entry Point & Live Auction Discovery Platform
 * Technologies: PHP, MySQLi/PDO, Bootstrap 5, Vanilla CSS, FontAwesome 6, AJAX
 * Architecture: Senior PHP Full-Stack Standards
 * ============================================================================
 */

include_once __DIR__ . '/includes/database.php';
include_once __DIR__ . '/includes/components/item_card.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$is_logged  = !empty($_SESSION['user_id']);
$user_id    = $is_logged ? (int)$_SESSION['user_id'] : 0;
$user_name  = $_SESSION['user_name'] ?? ($_SESSION['name'] ?? '');
$user_init  = $is_logged ? strtoupper(substr($user_name, 0, 2)) : '';
$user_role  = strtolower(trim($_SESSION['role'] ?? $_SESSION['user_role'] ?? 'buyer'));

// Fetch user wallet balance if authenticated
$user_balance = 0.00;
if ($is_logged && isset($conn) && $conn instanceof mysqli && !@$conn->connect_error) {
    $bal_stmt = mysqli_prepare($conn, "SELECT balance FROM users WHERE id = ? LIMIT 1");
    if ($bal_stmt) {
        mysqli_stmt_bind_param($bal_stmt, "i", $user_id);
        mysqli_stmt_execute($bal_stmt);
        $bal_res = mysqli_stmt_get_result($bal_stmt);
        if ($bal_row = mysqli_fetch_assoc($bal_res)) {
            $user_balance = (float)($bal_row['balance'] ?? 0);
        }
        mysqli_stmt_close($bal_stmt);
    }
}

// -----------------------------------------------------------------------------
// Database Queries with Safe Fallbacks & Graceful Error Handling
// -----------------------------------------------------------------------------
$featured_items = [];
$categories     = [];
$top_seller     = null;
$live_count     = 0;
$bid_count      = 0;
$user_count     = 0;

if (isset($conn) && $conn instanceof mysqli && !@$conn->connect_error) {
    // 1. Live Featured Auctions Query
    $featured_sql = "
        SELECT i.*, c.name AS cat_name, u.name AS seller_name,
            COUNT(b.id) AS bid_count,
            MAX(b.bid_amount) AS max_bid
        FROM items i
        LEFT JOIN categories c ON c.id = i.category_id
        LEFT JOIN users u ON u.id = i.seller_id
        LEFT JOIN bids b ON b.item_id = i.id
        WHERE i.status = 'active' AND (i.is_deleted = 0 OR i.is_deleted IS NULL)
        GROUP BY i.id
        ORDER BY bid_count DESC, i.created_at DESC
        LIMIT 8
    ";
    $featured_res = @mysqli_query($conn, $featured_sql);
    if ($featured_res) {
        while ($row = mysqli_fetch_assoc($featured_res)) {
            $featured_items[] = $row;
        }
    }

    // 2. Categories with active item counts
    $cats_sql = "
        SELECT c.*, COUNT(i.id) AS item_count
        FROM categories c
        LEFT JOIN items i ON i.category_id = c.id AND i.status = 'active' AND (i.is_deleted = 0 OR i.is_deleted IS NULL)
        GROUP BY c.id
        ORDER BY item_count DESC, c.name ASC
        LIMIT 8
    ";
    $cats_res = @mysqli_query($conn, $cats_sql);
    if ($cats_res) {
        while ($row = mysqli_fetch_assoc($cats_res)) {
            $categories[] = $row;
        }
    }

    // 3. Top Trusted Seller
    $seller_sql = "
        SELECT u.id, u.name, COUNT(i.id) AS item_count
        FROM users u
        JOIN items i ON i.seller_id = u.id AND i.status IN ('active','ended') AND (i.is_deleted = 0 OR i.is_deleted IS NULL)
        GROUP BY u.id
        ORDER BY item_count DESC
        LIMIT 1
    ";
    $seller_res = @mysqli_query($conn, $seller_sql);
    if ($seller_res && $seller_row = mysqli_fetch_assoc($seller_res)) {
        $top_seller = $seller_row;
    }

    // 4. Platform Metrics & Stats
    $stats_res = @mysqli_query($conn, "SELECT COUNT(*) AS c FROM items WHERE status = 'active' AND (is_deleted = 0 OR is_deleted IS NULL)");
    if ($stats_res && $r = mysqli_fetch_assoc($stats_res)) {
        $live_count = (int)$r['c'];
    }

    $ubids_res = @mysqli_query($conn, "SELECT COUNT(*) AS c FROM bids");
    if ($ubids_res && $r = mysqli_fetch_assoc($ubids_res)) {
        $bid_count = (int)$r['c'];
    }

    $usr_res = @mysqli_query($conn, "SELECT COUNT(*) AS c FROM users");
    if ($usr_res && $r = mysqli_fetch_assoc($usr_res)) {
        $user_count = (int)$r['c'];
    }
}

// Fallback mock items for Hero showcase if DB has fewer than 3 active listings
if (empty($featured_items)) {
    $fallback_hero_items = [
        [
            'id'             => 1,
            'title'          => 'Rolex Submariner Date 41mm Oystersteel',
            'starting_price' => 1250000.00,
            'current_price'  => 1420000.00,
            'max_bid'        => 1420000.00,
            'bid_count'      => 34,
            'end_time'       => date('Y-m-d H:i:s', time() + 18400),
            'featured_image' => 'https://images.unsplash.com/photo-1522335789203-aabd1fc54bc9?auto=format&fit=crop&w=600&q=80',
            'images'         => '',
            'cat_name'       => 'Watches & Jewelry',
            'seller_name'    => 'Premier Vault'
        ],
        [
            'id'             => 2,
            'title'          => '2022 Porsche 911 Carrera S Coupe',
            'starting_price' => 32000000.00,
            'current_price'  => 36500000.00,
            'max_bid'        => 36500000.00,
            'bid_count'      => 48,
            'end_time'       => date('Y-m-d H:i:s', time() + 7200),
            'featured_image' => 'https://images.unsplash.com/photo-1503376780353-7e6692767b70?auto=format&fit=crop&w=600&q=80',
            'images'         => '',
            'cat_name'       => 'Vehicles',
            'seller_name'    => 'Motors Exotics'
        ],
        [
            'id'             => 3,
            'title'          => 'DJI Inspire 3 Cinema Drone Pro Kit',
            'starting_price' => 1800000.00,
            'current_price'  => 2100000.00,
            'max_bid'        => 2100000.00,
            'bid_count'      => 19,
            'end_time'       => date('Y-m-d H:i:s', time() + 32000),
            'featured_image' => 'https://images.unsplash.com/photo-1527977966376-1c8408f9f108?auto=format&fit=crop&w=600&q=80',
            'images'         => '',
            'cat_name'       => 'Electronics',
            'seller_name'    => 'AeroTech Studio'
        ]
    ];
    $hero_items = $fallback_hero_items;
} else {
    $hero_items = array_slice($featured_items, 0, 3);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <!-- Primary SEO Meta Tags -->
    <title>Geo Auction – Real-Time Live Bidding & Marketplace</title>
    <meta name="title" content="Geo Auction – Real-Time Live Bidding & Marketplace">
    <meta name="description" content="Discover, bid, and win on thousands of live auctions in real-time. Verified sellers, secure wallet escrow, and transparent bidding across hundreds of categories.">
    <meta name="keywords" content="geo auction, live bidding, online auction, buy and sell, pakistan auctions, vehicle auction, watch auction">
    <meta name="author" content="Geo Auction Team">
    <meta name="robots" content="index, follow">
    <meta name="theme-color" content="#047857">

    <!-- Open Graph / Facebook / WhatsApp -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="http://localhost/geo-auction-system/">
    <meta property="og:title" content="Geo Auction – Real-Time Live Bidding Platform">
    <meta property="og:description" content="Discover, bid, and win on verified live auctions with instant wallet settlement and live countdowns.">
    <meta property="og:image" content="assets/image/Geo-auction.png">

    <!-- Twitter Meta -->
    <meta property="twitter:card" content="summary_large_image">
    <meta property="twitter:title" content="Geo Auction – Real-Time Live Bidding Platform">
    <meta property="twitter:description" content="Discover, bid, and win on verified live auctions with instant wallet settlement.">
    <meta property="twitter:image" content="assets/image/Geo-auction.png">

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="assets/image/Geo-auction.png">

    <!-- Google Fonts: Plus Jakarta Sans & Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- FontAwesome 6 Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- SweetAlert2 for Modern Interactive Dialogs -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- Project Stylesheets -->
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/style.css">

    <style>
        :root {
            --primary: #047857;
            --primary-dark: #065f46;
            --primary-light: #ecfdf5;
            --primary-ring: rgba(4, 120, 87, 0.25);
            --gold: #f59e0b;
            --gold-hover: #d97706;
            --dark: #0f172a;
            --body-bg: #f8fafc;
            --card-border: #e2e8f0;
            --text-main: #0f172a;
            --text-muted: #64748b;
        }

        body {
            font-family: 'Plus Jakarta Sans', 'Inter', -apple-system, sans-serif;
            background-color: var(--body-bg);
            color: var(--text-main);
            overflow-x: hidden;
        }

        html {
            scroll-behavior: smooth;
        }

        /* ── Emerald Brand Theme Classes ── */
        .text-emerald { color: var(--primary) !important; }
        .bg-emerald { background-color: var(--primary) !important; }
        .bg-emerald-light { background-color: var(--primary-light) !important; }
        .border-emerald { border-color: var(--primary) !important; }

        .btn-emerald {
            background-color: var(--primary) !important;
            border-color: var(--primary) !important;
            color: #ffffff !important;
            font-weight: 600;
            transition: all 0.25s ease;
        }
        .btn-emerald:hover {
            background-color: var(--primary-dark) !important;
            border-color: var(--primary-dark) !important;
            color: #ffffff !important;
            box-shadow: 0 4px 14px var(--primary-ring);
            transform: translateY(-1px);
        }

        .btn-outline-emerald {
            border: 1.5px solid var(--primary) !important;
            color: var(--primary) !important;
            background: transparent !important;
            font-weight: 600;
            transition: all 0.25s ease;
        }
        .btn-outline-emerald:hover {
            background-color: var(--primary) !important;
            color: #ffffff !important;
            box-shadow: 0 4px 14px var(--primary-ring);
            transform: translateY(-1px);
        }

        /* ── Modern Navigation Bar ── */
        .pub-nav {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--card-border);
            position: sticky;
            top: 0;
            z-index: 1040;
            transition: all 0.3s ease;
        }
        .pub-nav-inner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            max-width: 1320px;
            margin: 0 auto;
            padding: 12px 20px;
            gap: 20px;
        }
        .pub-logo img {
            height: 44px;
            width: auto;
            object-fit: contain;
            transition: transform 0.2s ease;
        }
        .pub-logo img:hover {
            transform: scale(1.03);
        }
        .pub-nav-links {
            display: flex;
            align-items: center;
            gap: 24px;
            margin: 0;
        }
        .pub-nav-links a {
            color: #334155;
            font-weight: 600;
            font-size: 15px;
            text-decoration: none;
            position: relative;
            padding: 6px 0;
            transition: color 0.2s ease;
        }
        .pub-nav-links a:hover, .pub-nav-links a.active {
            color: var(--primary);
        }
        .pub-nav-links a.active::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 2.5px;
            background: var(--primary);
            border-radius: 2px;
        }

        /* ── AJAX Live Search in Navbar ── */
        .pub-search-box {
            position: relative;
            flex: 1;
            max-width: 360px;
        }
        .pub-search-box input {
            width: 100%;
            padding: 9px 16px 9px 38px;
            font-size: 14px;
            border-radius: 9999px;
            border: 1px solid #cbd5e1;
            background: #f1f5f9;
            transition: all 0.2s ease;
        }
        .pub-search-box input:focus {
            outline: none;
            background: #fff;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--primary-ring);
        }
        .pub-search-icon {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            pointer-events: none;
            font-size: 14px;
        }
        .search-results-dropdown {
            position: absolute;
            top: calc(100% + 8px);
            left: 0;
            right: 0;
            background: #ffffff;
            border-radius: 12px;
            border: 1px solid var(--card-border);
            box-shadow: 0 16px 36px rgba(0, 0, 0, 0.12);
            max-height: 380px;
            overflow-y: auto;
            z-index: 1050;
            display: none;
        }
        .search-result-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 16px;
            border-bottom: 1px solid #f1f5f9;
            text-decoration: none;
            color: var(--text-main);
            transition: background 0.15s ease;
        }
        .search-result-item:hover {
            background: var(--primary-light);
            color: var(--primary);
        }
        .search-result-title {
            font-size: 13.5px;
            font-weight: 600;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 220px;
        }
        .search-result-price {
            font-size: 13px;
            font-weight: 700;
            color: var(--primary);
        }

        /* ── Hero Showcase Section ── */
        .hero {
            background: linear-gradient(135deg, #091e17 0%, #064e3b 50%, #022c22 100%);
            color: #ffffff;
            padding: 64px 0 80px 0;
            position: relative;
            overflow: hidden;
        }
        .hero::before {
            content: '';
            position: absolute;
            top: -20%;
            right: -10%;
            width: 600px;
            height: 600px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(16, 185, 129, 0.15) 0%, transparent 70%);
            pointer-events: none;
        }
        .hero-inner {
            max-width: 1320px;
            margin: 0 auto;
            padding: 0 20px;
            display: grid;
            grid-template-columns: 1.15fr 0.85fr;
            gap: 48px;
            align-items: center;
        }
        .hero-eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(16, 185, 129, 0.15);
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: #34d399;
            font-size: 13px;
            font-weight: 700;
            padding: 6px 14px;
            border-radius: 9999px;
            margin-bottom: 20px;
        }
        .live-dot {
            width: 8px;
            height: 8px;
            background: #34d399;
            border-radius: 50%;
            animation: pulse-dot 1.8s infinite;
        }
        @keyframes pulse-dot {
            0% { transform: scale(0.9); opacity: 1; box-shadow: 0 0 0 0 rgba(52, 211, 153, 0.7); }
            70% { transform: scale(1); opacity: 1; box-shadow: 0 0 0 7px rgba(52, 211, 153, 0); }
            100% { transform: scale(0.9); opacity: 1; box-shadow: 0 0 0 0 rgba(52, 211, 153, 0); }
        }
        .hero-title {
            font-size: 3.25rem;
            font-weight: 800;
            line-height: 1.15;
            letter-spacing: -0.02em;
            margin-bottom: 20px;
        }
        .hero-sub {
            font-size: 1.125rem;
            line-height: 1.6;
            color: #cbd5e1;
            margin-bottom: 32px;
            max-width: 580px;
        }
        .hero-actions {
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
            margin-bottom: 40px;
        }
        .btn-gold {
            background-color: var(--gold) !important;
            border-color: var(--gold) !important;
            color: #0f172a !important;
            font-weight: 700;
            transition: all 0.25s ease;
        }
        .btn-gold:hover {
            background-color: var(--gold-hover) !important;
            border-color: var(--gold-hover) !important;
            color: #ffffff !important;
            transform: translateY(-2px);
            box-shadow: 0 6px 18px rgba(245, 158, 11, 0.35);
        }
        .hero-stats {
            display: flex;
            gap: 36px;
            padding-top: 24px;
            border-top: 1px solid rgba(255, 255, 255, 0.12);
        }
        .hero-stat-val {
            font-size: 1.75rem;
            font-weight: 800;
            color: #34d399;
            line-height: 1.2;
        }
        .hero-stat-label {
            font-size: 0.85rem;
            color: #94a3b8;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        /* ── Hero Interactive Cards ── */
        .hero-visual {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }
        .hero-card {
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.12);
            backdrop-filter: blur(10px);
            border-radius: 14px;
            padding: 14px 18px;
            display: flex;
            align-items: center;
            gap: 16px;
            transition: all 0.25s ease;
            text-decoration: none;
            color: #fff;
        }
        .hero-card:hover {
            background: rgba(255, 255, 255, 0.14);
            border-color: #34d399;
            transform: translateX(4px);
            color: #fff;
        }
        .hero-card.featured {
            border-color: rgba(52, 211, 153, 0.5);
            background: rgba(16, 185, 129, 0.12);
        }
        .hero-card-img {
            width: 80px;
            height: 80px;
            border-radius: 10px;
            overflow: hidden;
            flex-shrink: 0;
            background: rgba(0, 0, 0, 0.3);
        }
        .hero-card-img img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .hero-card-title {
            font-size: 15px;
            font-weight: 700;
            color: #ffffff;
            margin-bottom: 4px;
            display: -webkit-box;
            -webkit-line-clamp: 1;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .hero-card-price {
            font-size: 14px;
            font-weight: 700;
            color: #34d399;
            margin-bottom: 3px;
        }
        .hero-card-timer {
            font-size: 12px;
            color: #cbd5e1;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        /* ── Sections & Catalog Grid ── */
        .section {
            padding: 72px 0;
        }
        .section-alt {
            background: #ffffff;
            border-top: 1px solid var(--card-border);
            border-bottom: 1px solid var(--card-border);
        }
        .section-inner {
            max-width: 1320px;
            margin: 0 auto;
            padding: 0 20px;
        }
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-bottom: 36px;
        }
        .section-title {
            font-size: 2.25rem;
            font-weight: 800;
            color: var(--text-main);
            letter-spacing: -0.02em;
            margin-bottom: 6px;
        }
        .section-sub {
            font-size: 1rem;
            color: var(--text-muted);
            margin: 0;
        }
        .section-link {
            font-size: 15px;
            font-weight: 700;
            color: var(--primary);
            text-decoration: none;
            transition: transform 0.2s ease;
        }
        .section-link:hover {
            color: var(--primary-dark);
            transform: translateX(3px);
        }

        /* ── Category Cards Grid ── */
        .grid-categories {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            gap: 20px;
        }
        .cat-card {
            background: #ffffff;
            border: 1px solid var(--card-border);
            border-radius: 14px;
            padding: 24px 20px;
            text-align: center;
            text-decoration: none;
            color: var(--text-main);
            transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .cat-card:hover {
            border-color: var(--primary);
            box-shadow: 0 12px 28px rgba(4, 120, 87, 0.08);
            transform: translateY(-4px);
            color: var(--primary);
        }
        .cat-card-icon {
            width: 56px;
            height: 56px;
            border-radius: 14px;
            background: var(--primary-light);
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin-bottom: 16px;
            transition: transform 0.25s ease;
        }
        .cat-card:hover .cat-card-icon {
            transform: scale(1.1);
            background: var(--primary);
            color: #ffffff;
        }
        .cat-card-name {
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 4px;
        }
        .cat-card-count {
            font-size: 13px;
            color: var(--text-muted);
        }

        /* ── How It Works Steps ── */
        .steps {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 30px;
        }
        .step-item {
            background: #ffffff;
            border: 1px solid var(--card-border);
            border-radius: 16px;
            padding: 32px 24px;
            text-align: center;
            position: relative;
            transition: transform 0.25s ease;
        }
        .step-item:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 24px rgba(0, 0, 0, 0.05);
        }
        .step-num {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: var(--primary);
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            font-weight: 800;
            margin: 0 auto 20px auto;
            box-shadow: 0 4px 12px var(--primary-ring);
        }
        .step-title {
            font-size: 18px;
            font-weight: 700;
            color: var(--text-main);
            margin-bottom: 10px;
        }
        .step-desc {
            font-size: 14px;
            color: var(--text-muted);
            line-height: 1.6;
        }

        /* ── Top Seller Card ── */
        .seller-showcase-box {
            background: #ffffff;
            border: 1px solid var(--card-border);
            border-radius: 16px;
            padding: 24px;
            max-width: 450px;
            display: flex;
            align-items: center;
            gap: 20px;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.04);
        }
        .seller-avatar-large {
            width: 68px;
            height: 68px;
            border-radius: 50%;
            background: var(--primary);
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            font-weight: 800;
            flex-shrink: 0;
        }
        .seller-name-large {
            font-size: 18px;
            font-weight: 700;
            color: var(--text-main);
            margin-bottom: 4px;
        }
        .seller-meta-tag {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 12px;
            font-weight: 700;
            color: var(--primary);
            background: var(--primary-light);
            padding: 3px 10px;
            border-radius: 9999px;
            margin-bottom: 6px;
        }

        /* ── Footer ── */
        .site-footer {
            background: #0f172a;
            color: #cbd5e1;
            padding: 64px 0 32px 0;
            border-top: 1px solid #1e293b;
        }
        .footer-inner {
            max-width: 1320px;
            margin: 0 auto;
            padding: 0 20px;
        }
        .footer-grid {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr;
            gap: 40px;
            margin-bottom: 48px;
        }
        .footer-brand-logo img {
            height: 42px;
            width: auto;
            margin-bottom: 16px;
        }
        .footer-brand-text {
            font-size: 14px;
            line-height: 1.6;
            color: #94a3b8;
            max-width: 320px;
        }
        .footer-col-title {
            font-size: 15px;
            font-weight: 700;
            color: #ffffff;
            margin-bottom: 18px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .footer-links {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .footer-links a {
            color: #94a3b8;
            font-size: 14px;
            text-decoration: none;
            transition: color 0.2s ease, transform 0.2s ease;
        }
        .footer-links a:hover {
            color: #34d399;
            transform: translateX(3px);
        }
        .footer-bottom {
            padding-top: 32px;
            border-top: 1px solid #1e293b;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 13px;
            color: #64748b;
            flex-wrap: wrap;
            gap: 16px;
        }

        /* Responsive Breakpoints */
        @media (max-width: 991px) {
            .hero-inner {
                grid-template-columns: 1fr;
                gap: 40px;
            }
            .hero-title {
                font-size: 2.5rem;
            }
            .footer-grid {
                grid-template-columns: 1fr 1fr;
            }
            .pub-nav-links {
                display: none;
            }
            .pub-search-box {
                max-width: 220px;
            }
        }
        @media (max-width: 575px) {
            .hero-title {
                font-size: 2rem;
            }
            .hero-stats {
                flex-direction: column;
                gap: 16px;
            }
            .footer-grid {
                grid-template-columns: 1fr;
            }
            .pub-search-box {
                display: none;
            }
        }
    </style>
</head>
<body>

<?php 
// Loading Spinner Overlay Component
include_once __DIR__ . '/includes/components/loading_spinner_overlay.php'; 
?>

<!-- ── Main Navigation Bar ── -->
<nav class="pub-nav">
    <div class="pub-nav-inner">
        <!-- Logo -->
        <a href="index.php" class="pub-logo" title="Geo Auction">
            <img src="assets/image/Geo-auction.png" alt="Geo Auction Logo" onerror="this.onerror=null;this.src='assets/image/geo-auction.png'">
        </a>

        <!-- Desktop Links -->
        <div class="pub-nav-links">
            <a href="index.php" class="active"><i class="fas fa-home me-1"></i> Home</a>
            <a href="#live_auction"><i class="fas fa-gavel me-1"></i> Live Auctions</a>
            <a href="#category"><i class="fas fa-th-large me-1"></i> Categories</a>
            <a href="#about"><i class="fas fa-info-circle me-1"></i> How It Works</a>
        </div>

        <!-- Live Search Bar with AJAX Autocomplete -->
        <div class="pub-search-box">
            <form action="search.php" method="GET" id="navSearchForm">
                <i class="fas fa-search pub-search-icon"></i>
                <input type="text" name="q" id="navSearchInput" placeholder="Search auctions, brands..." autocomplete="off">
            </form>
            <div id="navSearchResults" class="search-results-dropdown"></div>
        </div>

        <!-- CTA & User Session Controls -->
        <div class="d-flex align-items-center gap-2">
            <?php if ($is_logged): ?>
                <a href="user/wallet.php" class="btn btn-outline-emerald btn-sm d-flex align-items-center gap-2 py-1 px-3" title="My Wallet">
                    <i class="fas fa-wallet text-emerald"></i>
                    <span>Rs. <?php echo number_format($user_balance, 2); ?></span>
                </a>
                <a href="dashboard.php" class="btn btn-emerald btn-sm d-flex align-items-center gap-2 py-1 px-3" title="Go to Dashboard">
                    <span class="badge bg-light text-dark rounded-circle px-2 py-1"><?php echo $user_init; ?></span>
                    <span class="d-none d-sm-inline"><?php echo htmlspecialchars($user_name); ?></span>
                </a>
                <a href="logout.php" class="btn btn-outline-danger btn-sm logout-btn py-1 px-2" title="Sign Out">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            <?php else: ?>
                <a href="login.php" class="btn btn-outline-emerald btn-sm px-3 py-1">
                    <i class="fas fa-sign-in-alt me-1"></i> Login
                </a>
                <a href="register.php" class="btn btn-emerald btn-sm px-3 py-1">
                    <i class="fas fa-user-plus me-1"></i> Register Free
                </a>
            <?php endif; ?>
        </div>
    </div>
</nav>

<!-- ── Hero Showcase Section ── -->
<section class="hero" id="home">
    <div class="hero-inner">
        <!-- Hero Text & Metrics -->
        <div class="hero-text">
            <div class="hero-eyebrow">
                <span class="live-dot"></span>
                <span><?php echo number_format($live_count > 0 ? $live_count : 12); ?> Auctions Active Now</span>
            </div>
            <h1 class="hero-title">
                Bid Smart,<br>Win <span style="color: #34d399;">Big</span> in Real-Time.
            </h1>
            <p class="hero-sub">
                Discover verified live auctions across cars, electronics, jewelry, collectibles, and luxury assets. Place competitive bids, experience transparent anti-sniping timers, and settle securely.
            </p>
            <div class="hero-actions">
                <a href="items.php" class="btn btn-gold btn-lg px-4 py-3 d-inline-flex align-items-center gap-2">
                    <i class="fas fa-gavel"></i> Browse All Auctions
                </a>
                <?php if (!$is_logged): ?>
                    <a href="register.php" class="btn btn-outline-light btn-lg px-4 py-3 d-inline-flex align-items-center gap-2">
                        <i class="fas fa-bolt"></i> Start Bidding Free
                    </a>
                <?php else: ?>
                    <a href="user/add_items.php" class="btn btn-outline-light btn-lg px-4 py-3 d-inline-flex align-items-center gap-2">
                        <i class="fas fa-plus-circle"></i> List an Item
                    </a>
                <?php endif; ?>
            </div>

            <!-- Real-Time Metrics Counters -->
            <div class="hero-stats">
                <div class="hero-stat">
                    <div class="hero-stat-val"><?php echo number_format($live_count > 0 ? $live_count : 24); ?>+</div>
                    <div class="hero-stat-label">Live Auctions</div>
                </div>
                <div class="hero-stat">
                    <div class="hero-stat-val"><?php echo number_format($bid_count > 0 ? $bid_count : 1480); ?>+</div>
                    <div class="hero-stat-label">Verified Bids</div>
                </div>
                <div class="hero-stat">
                    <div class="hero-stat-val"><?php echo number_format($user_count > 0 ? $user_count : 520); ?>+</div>
                    <div class="hero-stat-label">Bidders & Sellers</div>
                </div>
            </div>
        </div>

        <!-- Hero Dynamic Cards Showcase -->
        <div class="hero-visual">
            <?php foreach ($hero_items as $idx => $hi): 
                $current_price = !empty($hi['max_bid']) ? $hi['max_bid'] : (!empty($hi['current_price']) ? $hi['current_price'] : ($hi['starting_price'] ?? 0));
                $price_str = number_format((float)$current_price, 2);
                $end_ts = !empty($hi['end_time']) ? strtotime($hi['end_time']) : (time() + 3600);
                $diff = max(0, $end_ts - time());
                
                $img_src = !empty($hi['featured_image']) ? $hi['featured_image'] : '';
                if (empty($img_src) && !empty($hi['images'])) {
                    $decoded = json_decode($hi['images'], true);
                    $img_src = is_array($decoded) && !empty($decoded[0]) ? $decoded[0] : '';
                }
                if (empty($img_src)) {
                    $img_src = 'assets/image/Geo-auction.png';
                }
                $is_first = ($idx === 0);
            ?>
                <a href="live_auction.php?id=<?php echo (int)$hi['id']; ?>" class="hero-card <?php echo $is_first ? 'featured' : ''; ?>">
                    <div class="hero-card-img">
                        <img src="<?php echo htmlspecialchars($img_src); ?>" alt="<?php echo htmlspecialchars($hi['title']); ?>" onerror="this.onerror=null;this.src='assets/image/Geo-auction.png'">
                    </div>
                    <div class="flex-grow-1">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="badge <?php echo $is_first ? 'bg-success' : 'bg-secondary'; ?> text-white" style="font-size:10px;">
                                <i class="fas fa-circle live-dot" style="font-size:6px; margin-right:3px;"></i> LIVE
                            </span>
                            <small class="text-white-50" style="font-size:11px;">
                                <i class="fas fa-user-tie me-1"></i> <?php echo htmlspecialchars($hi['seller_name'] ?? 'Verified'); ?>
                            </small>
                        </div>
                        <div class="hero-card-title"><?php echo htmlspecialchars($hi['title']); ?></div>
                        <div class="hero-card-price">Current Bid: Rs. <?php echo $price_str; ?></div>
                        <div class="hero-card-timer">
                            <i class="fas fa-clock text-emerald"></i>
                            <span class="stat-timer-val" data-end-timestamp="<?php echo $end_ts; ?>">Ending soon</span>
                            <span>•</span>
                            <span><?php echo (int)($hi['bid_count'] ?? 0); ?> bids</span>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ── Live Auctions Catalog Section ── -->
<section class="section" id="live_auction">
    <div class="section-inner">
        <div class="section-header">
            <div>
                <h2 class="section-title"><span class="text-emerald">Live</span> Auctions Arena</h2>
                <p class="section-sub">Hand-picked active auction lots with real-time bidding activity</p>
            </div>
            <a href="items.php" class="section-link">
                View all items <i class="fas fa-arrow-right ms-1"></i>
            </a>
        </div>

        <?php if (!empty($featured_items)): ?>
            <div class="catalog-grid">
                <?php foreach ($featured_items as $item): ?>
                    <?php render_unified_item_card($item, $user_role, $user_id); ?>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="text-center py-5 bg-white rounded-4 border p-4">
                <div class="mb-3 text-emerald" style="font-size: 48px;">
                    <i class="fas fa-gavel"></i>
                </div>
                <h4 class="fw-bold mb-2">No Active Auctions at this Moment</h4>
                <p class="text-muted mb-4">Be the first seller to list an item or check back in a few minutes!</p>
                <?php if ($is_logged): ?>
                    <a href="user/add_items.php" class="btn btn-emerald px-4 py-2">
                        <i class="fas fa-plus-circle me-1"></i> List Your First Item
                    </a>
                <?php else: ?>
                    <a href="login.php" class="btn btn-emerald px-4 py-2">
                        <i class="fas fa-sign-in-alt me-1"></i> Login to List Item
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- ── Categories Section ── -->
<section class="section section-alt" id="category">
    <div class="section-inner">
        <div class="section-header">
            <div>
                <h2 class="section-title">Explore by <span class="text-emerald">Category</span></h2>
                <p class="section-sub">Browse thousands of items across top trending categories</p>
            </div>
            <a href="category.php" class="section-link">
                All categories <i class="fas fa-arrow-right ms-1"></i>
            </a>
        </div>

        <div class="grid-categories">
            <?php 
            $cat_icons = [
                'Electronics' => 'fa-laptop',
                'Vehicles'    => 'fa-car',
                'Motors'      => 'fa-motorcycle',
                'Jewelry'     => 'fa-gem',
                'Art'         => 'fa-palette',
                'Sports'      => 'fa-futbol',
                'Furniture'   => 'fa-couch',
                'Watches'     => 'fa-stopwatch',
                'Fashion'     => 'fa-tshirt',
                'Antiques'    => 'fa-monument',
            ];

            if (!empty($categories)):
                foreach ($categories as $cat):
                    $cname = $cat['name'];
                    $icon = $cat['icon'] ?? ($cat_icons[$cname] ?? 'fa-tag');
            ?>
                <a href="category.php?id=<?php echo (int)$cat['id']; ?>" class="cat-card">
                    <div class="cat-card-icon">
                        <i class="fas <?php echo htmlspecialchars($icon); ?>"></i>
                    </div>
                    <div class="cat-card-name"><?php echo htmlspecialchars($cname); ?></div>
                    <div class="cat-card-count"><?php echo (int)$cat['item_count']; ?> active listings</div>
                </a>
            <?php 
                endforeach;
            else:
                $default_categories = [
                    ['fa-car', 'Vehicles', 'Motorcycles, sedans, luxury cars'],
                    ['fa-laptop', 'Electronics', 'Smartphones, laptops, cameras'],
                    ['fa-gem', 'Jewelry & Watches', 'Rings, luxury Swiss timepieces'],
                    ['fa-palette', 'Fine Art', 'Paintings, bronze sculptures, prints'],
                    ['fa-couch', 'Home & Living', 'Antique furniture, interior decor'],
                    ['fa-futbol', 'Sports & Collectibles', 'Signed memorabilia, trading cards'],
                    ['fa-tshirt', 'Fashion & Apparel', 'Designer bags, shoes, luxury wear'],
                    ['fa-monument', 'Antiques & Curios', 'Vintage artifacts, rare coins']
                ];
                foreach ($default_categories as $dc):
            ?>
                <a href="items.php?category=<?php echo urlencode($dc[1]); ?>" class="cat-card">
                    <div class="cat-card-icon">
                        <i class="fas <?php echo $dc[0]; ?>"></i>
                    </div>
                    <div class="cat-card-name"><?php echo $dc[1]; ?></div>
                    <div class="cat-card-count">Browse collection</div>
                </a>
            <?php 
                endforeach;
            endif; 
            ?>
        </div>
    </div>
</section>

<!-- ── How It Works Section ── -->
<section class="section" id="about">
    <div class="section-inner">
        <div class="text-center mb-5">
            <h2 class="section-title">How <span class="text-emerald">Geo Auction</span> Works</h2>
            <p class="section-sub">Start winning live auctions in 3 straightforward steps</p>
        </div>

        <div class="steps">
            <div class="step-item">
                <div class="step-num">1</div>
                <h4 class="step-title">Create Free Account</h4>
                <p class="step-desc">Register in under 60 seconds with verified security. Access your customized dashboard and watchlists immediately.</p>
            </div>
            <div class="step-item">
                <div class="step-num">2</div>
                <h4 class="step-title">Fund Your Wallet</h4>
                <p class="step-desc">Top up securely via Bank Transfer or Cards. Your funds remain in safe escrow and are only debited upon winning an auction.</p>
            </div>
            <div class="step-item">
                <div class="step-num">3</div>
                <h4 class="step-title">Bid, Outbid & Win</h4>
                <p class="step-desc">Place live competitive bids or setup automated proxy bidding. Win the auction and receive your item with tracked shipping.</p>
            </div>
        </div>

        <?php if (!$is_logged): ?>
            <div class="text-center mt-5">
                <a href="register.php" class="btn btn-emerald btn-lg px-5 py-3">
                    <i class="fas fa-rocket me-2"></i> Join Geo Auction Free
                </a>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- ── Top Seller Showcase Section ── -->
<?php if ($top_seller): ?>
<section class="section section-alt">
    <div class="section-inner">
        <div class="section-header">
            <div>
                <h2 class="section-title">Featured <span class="text-emerald">Top Seller</span></h2>
                <p class="section-sub">Recognized for verified listings, quick dispatch, and high customer satisfaction</p>
            </div>
        </div>

        <div class="seller-showcase-box">
            <div class="seller-avatar-large">
                <?php echo strtoupper(substr($top_seller['name'], 0, 2)); ?>
            </div>
            <div>
                <div class="seller-name-large"><?php echo htmlspecialchars($top_seller['name']); ?></div>
                <span class="seller-meta-tag">
                    <i class="fas fa-shield-alt"></i> Verified Top Rated
                </span>
                <div class="text-muted small mt-1">
                    <i class="fas fa-box-open text-emerald me-1"></i> <?php echo (int)$top_seller['item_count']; ?> auction lots successfully hosted
                </div>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ── Call to Action Banner ── -->
<?php if (!$is_logged): ?>
<section class="py-5" style="background: linear-gradient(135deg, #091e17 0%, #047857 100%);">
    <div class="section-inner text-center text-white py-4">
        <h2 class="fw-bold mb-3" style="font-size: 2.25rem;">Ready to Place Your First Bid?</h2>
        <p class="text-white-50 mb-4" style="font-size: 1.1rem; max-width: 600px; margin: 0 auto 24px auto;">
            Join verified bidders across the nation. Transparent pricing, instant notifications, and dedicated buyer support.
        </p>
        <div class="d-flex justify-content-center gap-3 flex-wrap">
            <a href="login.php" class="btn btn-gold btn-lg px-4 py-3">
                <i class="fas fa-sign-in-alt me-2"></i> Sign In to Account
            </a>
            <a href="register.php" class="btn btn-outline-light btn-lg px-4 py-3">
                <i class="fas fa-user-plus me-2"></i> Register New Member
            </a>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ── Site Footer ── -->
<footer class="site-footer">
    <div class="footer-inner">
        <div class="footer-grid">
            <!-- Brand Column -->
            <div>
                <div class="footer-brand-logo">
                    <img src="assets/image/Geo-auction.png" alt="Geo Auction" onerror="this.onerror=null;this.src='assets/image/geo-auction.png'">
                </div>
                <p class="footer-brand-text">
                    Geo Auction is Pakistan's premier live auction platform connecting verified buyers and trusted sellers with real-time bidding technology.
                </p>
            </div>

            <!-- Platform Links -->
            <div>
                <div class="footer-col-title">Platform</div>
                <div class="footer-links">
                    <a href="live_auction.php">Live Auctions</a>
                    <a href="items.php">Catalog Marketplace</a>
                    <a href="category.php">Categories</a>
                    <a href="search.php">Search Lots</a>
                    <a href="about.php">About Us</a>
                </div>
            </div>

            <!-- Account Links -->
            <div>
                <div class="footer-col-title">Account</div>
                <div class="footer-links">
                    <?php if ($is_logged): ?>
                        <a href="dashboard.php">My Dashboard</a>
                        <a href="user/wallet.php">Wallet & Balance</a>
                        <a href="notifications.php">Notifications</a>
                        <a href="messages.php">Messages</a>
                        <a href="logout.php">Sign Out</a>
                    <?php else: ?>
                        <a href="login.php">Sign In</a>
                        <a href="register.php">Create Account</a>
                        <a href="forgot_password.php">Forgot Password</a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Sellers & Legal -->
            <div>
                <div class="footer-col-title">Sellers & Help</div>
                <div class="footer-links">
                    <?php if ($is_logged): ?>
                        <a href="user/add_items.php">List an Item</a>
                        <a href="user/my_auctions.php">My Listings</a>
                    <?php else: ?>
                        <a href="login.php">Sell on Geo Auction</a>
                    <?php endif; ?>
                    <a href="contact.php">Contact Support</a>
                    <a href="about.php">Terms of Service</a>
                </div>
            </div>
        </div>

        <div class="footer-bottom">
            <span>&copy; <?php echo date('Y'); ?> Geo Auction System. All rights reserved.</span>
            <span><i class="fas fa-shield-alt text-emerald me-1"></i> Secure Escrow Protected Bidding</span>
        </div>
    </div>
</footer>

<!-- ── Scripts: Bootstrap 5, AJAX & Live Timers ── -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
const IS_USER_LOGGED_IN = <?php echo $is_logged ? 'true' : 'false'; ?>;
const LOGIN_REDIRECT_URL = 'login.php';

// ── Smart Authentication Interceptor for Guests ──
// Allows guests to freely browse, open quick view modals, view categories, and search.
// Only intercepts protected transactional actions (like placing a bid, messaging seller, or checking out).
document.addEventListener('click', function(e) {
    if (IS_USER_LOGGED_IN) return;

    // Check if the clicked element or its parent is an authenticated-only trigger
    const authTrigger = e.target.closest('.auth-required, .geo-btn-bid, .geo-btn-buy, #qvActionPrimary, #qvActionMessage');
    if (authTrigger) {
        e.preventDefault();
        e.stopPropagation();
        
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'info',
                title: 'Sign In Required',
                text: 'Please sign in or register for free to place live bids or contact sellers.',
                showCancelButton: true,
                confirmButtonText: '<i class="fas fa-sign-in-alt me-1"></i> Sign In',
                cancelButtonText: 'Cancel',
                confirmButtonColor: '#047857',
                cancelButtonColor: '#64748b'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = LOGIN_REDIRECT_URL;
                }
            });
        } else {
            window.location.href = LOGIN_REDIRECT_URL;
        }
    }
});

// ── Real-Time Countdown Timer Updater ──
function updateLiveAuctionTimers() {
    const timerElements = document.querySelectorAll('.stat-timer-val[data-end-timestamp]');
    const now = Math.floor(Date.now() / 1000);

    timerElements.forEach(el => {
        const endTs = parseInt(el.getAttribute('data-end-timestamp'), 10);
        if (!endTs || isNaN(endTs)) return;
        
        const diff = Math.max(0, endTs - now);
        if (diff <= 0) {
            el.textContent = 'Ended';
            el.classList.add('text-danger');
            return;
        }

        const d = Math.floor(diff / 86400);
        const h = Math.floor((diff % 86400) / 3600);
        const m = Math.floor((diff % 3600) / 60);
        const s = diff % 60;

        if (d > 0) {
            el.textContent = `${d}d : ${h}h left`;
        } else if (h > 0) {
            el.textContent = `${h}h : ${m}m : ${s}s`;
        } else {
            el.textContent = `${m}m : ${s}s left`;
            el.classList.add('text-danger', 'fw-bold');
        }
    });
}
setInterval(updateLiveAuctionTimers, 1000);
updateLiveAuctionTimers();

// ── AJAX Live Search Autocomplete ──
const searchInput = document.getElementById('navSearchInput');
const searchResults = document.getElementById('navSearchResults');
let searchDebounceTimer = null;

if (searchInput && searchResults) {
    searchInput.addEventListener('input', function() {
        const q = this.value.trim();
        clearTimeout(searchDebounceTimer);

        if (q.length < 2) {
            searchResults.style.display = 'none';
            searchResults.innerHTML = '';
            return;
        }

        searchDebounceTimer = setTimeout(() => {
            fetch(`ajax/search.php?q=${encodeURIComponent(q)}`)
                .then(res => res.json())
                .then(data => {
                    if (data.success && data.results && data.results.length > 0) {
                        let html = '';
                        data.results.forEach(item => {
                            html += `
                                <a href="${item.url}" class="search-result-item">
                                    <div>
                                        <div class="search-result-title">${escapeHtml(item.title)}</div>
                                        <small class="text-muted" style="font-size:11px;">${escapeHtml(item.category)}</small>
                                    </div>
                                    <div class="search-result-price">${item.price}</div>
                                </a>
                            `;
                        });
                        html += `
                            <div class="p-2 text-center bg-light border-top">
                                <a href="search.php?q=${encodeURIComponent(q)}" class="text-emerald small fw-bold text-decoration-none">
                                    View all matching lots →
                                </a>
                            </div>
                        `;
                        searchResults.innerHTML = html;
                        searchResults.style.display = 'block';
                    } else {
                        searchResults.innerHTML = '<div class="p-3 text-center text-muted small">No auctions match your search.</div>';
                        searchResults.style.display = 'block';
                    }
                })
                .catch(() => {
                    searchResults.style.display = 'none';
                });
        }, 250);
    });

    // Close search dropdown on click outside
    document.addEventListener('click', function(e) {
        if (!searchInput.contains(e.target) && !searchResults.contains(e.target)) {
            searchResults.style.display = 'none';
        }
    });
}

function escapeHtml(text) {
    if (!text) return '';
    const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
    return text.replace(/[&<>"']/g, m => map[m]);
}

// ── Smooth Logout Spinner ──
document.addEventListener('click', function(e) {
    const logoutLink = e.target.closest('a[href*="logout"], .logout-btn');
    if (logoutLink) {
        const targetHref = logoutLink.getAttribute('href');
        if (targetHref && targetHref !== '#' && !targetHref.startsWith('javascript:')) {
            e.preventDefault();
            if (typeof showLoader === 'function') {
                showLoader('Signing out safely...');
            }
            setTimeout(function() {
                window.location.href = targetHref;
            }, 250);
        }
    }
});
</script>

<?php 
// Render unified auction card assets (Quick View Modal & Card Carousels)
if (function_exists('render_unified_card_assets')) {
    render_unified_card_assets(); 
}
?>

</body>
</html>