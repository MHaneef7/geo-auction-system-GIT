<?php
    include_once ('includes/database.php');
    include_once ('includes/components/item_card.php');
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $is_logged  = !empty($_SESSION['user_id']);
    $user_id    = $is_logged ? (int)$_SESSION['user_id'] : 0;
    $user_name  = $_SESSION['user_name'] ?? ($_SESSION['name'] ?? '');
    $user_init  = $is_logged ? strtoupper(substr($user_name, 0, 2)) : '';
    $user_role  = $is_logged ? strtolower(trim($_SESSION['role'] ?? $_SESSION['user_role'] ?? 'buyer')) : 'guest';

    $pdo = Database::getPdo();
    $user_balance = 0.00;
    if ($is_logged) {
        $bal_stmt = $pdo->prepare("SELECT balance FROM users WHERE id = :id LIMIT 1");
        $bal_stmt->execute([':id' => $user_id]);
        $user_balance = (float)($bal_stmt->fetchColumn() ?? 0.0);
    }

    $featured_stmt = $pdo->query("
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
    ");
    $featured_items = $featured_stmt->fetchAll(PDO::FETCH_ASSOC);

    $cats_stmt = $pdo->query("
        SELECT c.*, COUNT(i.id) AS item_count
        FROM categories c
        LEFT JOIN items i ON i.category_id = c.id AND i.status = 'active' AND (i.is_deleted = 0 OR i.is_deleted IS NULL)
        GROUP BY c.id
        ORDER BY item_count DESC
        LIMIT 8
    ");
    $categories = $cats_stmt->fetchAll(PDO::FETCH_ASSOC);

    $sellers_stmt = $pdo->query("
        SELECT u.id, u.name, u.avatar, COUNT(i.id) AS item_count
        FROM users u
        LEFT JOIN items i ON i.seller_id = u.id AND i.status IN ('active','ended') AND (i.is_deleted = 0 OR i.is_deleted IS NULL)
        WHERE u.role IN ('seller', 'admin')
        GROUP BY u.id
        ORDER BY item_count DESC, u.id ASC
        LIMIT 4
    ");
    $top_sellers = $sellers_stmt->fetchAll(PDO::FETCH_ASSOC);

    $live_count = (int)($pdo->query("SELECT COUNT(*) AS c FROM items WHERE status = 'active' AND (is_deleted = 0 OR is_deleted IS NULL)")->fetchColumn() ?? 0);
    $bid_count  = (int)($pdo->query("SELECT COUNT(*) AS c FROM bids")->fetchColumn() ?? 0);
    $user_count = (int)($pdo->query("SELECT COUNT(*) AS c FROM users")->fetchColumn() ?? 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Geo Auction – Discover, bid, and win on live auctions in real-time. Verified sellers, secure wallet, and instant bidding on vehicles, electronics, jewelry, and more.">
    <title>Geo Auction – Live Bidding Platform</title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=Plus+Jakarta+Sans:wght@500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Core & Library Styles -->
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <style>
        /* ── Modern Design Variables & Overrides ── */
        :root {
            --primary: #047857;
            --primary-hover: #065f46;
            --primary-light: #ecfdf5;
            --primary-glow: rgba(4, 120, 87, 0.25);
            --gold: #f59e0b;
            --gold-light: #fef3c7;
            --dark-surface: #0f172a;
            --slate-800: #1e293b;
            --slate-700: #334155;
            --slate-100: #f1f5f9;
            --slate-50: #f8fafc;
            --text-heading: #0f172a;
            --text-body: #475569;
            --radius-md: 10px;
            --radius-lg: 14px;
            --radius-xl: 20px;
            --shadow-subtle: 0 4px 20px -2px rgba(15, 23, 42, 0.05);
            --shadow-card: 0 10px 30px -4px rgba(15, 23, 42, 0.08);
            --shadow-hover: 0 20px 40px -6px rgba(4, 120, 87, 0.16);
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background-color: #f8fafc;
            color: var(--text-body);
            overflow-x: hidden;
        }

        /* ── Header & Navigation ── */
        .pub-nav {
            position: sticky;
            top: 0;
            z-index: 1000;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(14px);
            -webkit-backdrop-filter: blur(14px);
            border-bottom: 1px solid rgba(226, 232, 240, 0.85);
            box-shadow: 0 4px 24px rgba(0, 0, 0, 0.04);
            transition: all 0.25s ease;
        }

        .pub-nav::after {
            content: '';
            display: block;
            height: 3px;
            background: linear-gradient(90deg, #047857 0%, #10b981 50%, #f59e0b 100%);
        }

        .pub-nav-inner {
            max-width: 1320px;
            margin: 0 auto;
            padding: 0 24px;
            height: 74px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
        }

        /* Brand Logo Container */
        .pub-logo {
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            flex-shrink: 0;
            transition: transform 0.2s ease;
        }
        .pub-logo:hover {
            transform: translateY(-1px);
        }
        .pub-logo img {
            height: 44px;
            max-height: 48px;
            width: auto;
            object-fit: contain;
            display: block;
        }

        /* Nav Links */
        .pub-nav-links {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .pub-nav-links a {
            padding: 8px 16px;
            border-radius: 50px;
            font-size: 14px;
            font-weight: 600;
            color: #475569;
            text-decoration: none;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .pub-nav-links a:hover {
            color: var(--primary);
            background: var(--primary-light);
        }
        .pub-nav-links a.active {
            color: var(--primary);
            background: var(--primary-light);
            font-weight: 700;
        }

        /* Header Search Form */
        .pub-search-wrapper {
            flex: 1;
            max-width: 380px;
            margin: 0 10px;
            position: relative;
        }
        .pub-search-form {
            position: relative;
            display: flex;
            align-items: center;
            width: 100%;
        }
        .pub-search-form input {
            width: 100%;
            height: 42px;
            background: #f1f5f9;
            border: 1.5px solid #e2e8f0;
            border-radius: 50px;
            padding: 0 46px 0 18px;
            font-size: 13.5px;
            font-weight: 500;
            color: #1e293b;
            outline: none;
            transition: all 0.25s ease;
        }
        .pub-search-form input:focus {
            background: #ffffff;
            border-color: var(--primary);
            box-shadow: 0 0 0 4px var(--primary-glow);
        }
        .pub-search-form input::placeholder {
            color: #94a3b8;
        }
        .pub-search-submit {
            position: absolute;
            right: 4px;
            width: 34px;
            height: 34px;
            background: var(--primary);
            color: #ffffff;
            border: none;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 13px;
            transition: all 0.2s ease;
        }
        .pub-search-submit:hover {
            background: var(--primary-hover);
            transform: scale(1.06);
        }

        /* User & CTA Area */
        .pub-nav-cta {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-shrink: 0;
        }
        .btn-nav-login {
            padding: 8px 18px;
            border: 1.5px solid var(--primary);
            color: var(--primary);
            border-radius: 50px;
            font-size: 13.5px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s ease;
            background: transparent;
        }
        .btn-nav-login:hover {
            background: var(--primary-light);
            color: var(--primary-hover);
        }
        .btn-nav-register {
            padding: 8px 20px;
            background: var(--primary);
            color: #ffffff !important;
            border-radius: 50px;
            font-size: 13.5px;
            font-weight: 700;
            text-decoration: none;
            border: none;
            transition: all 0.2s ease;
            box-shadow: 0 4px 12px rgba(4, 120, 87, 0.25);
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-nav-register:hover {
            background: var(--primary-hover);
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(4, 120, 87, 0.35);
        }

        .btn-nav-wallet {
            padding: 7px 14px;
            border-radius: 50px;
            font-size: 13px;
            font-weight: 700;
            background: #ecfdf5;
            color: var(--primary);
            border: 1px solid #a7f3d0;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 7px;
            transition: all 0.2s ease;
        }
        .btn-nav-wallet:hover {
            background: #d1fae5;
            transform: translateY(-1px);
        }

        .pub-user-badge {
            display: flex;
            align-items: center;
            gap: 9px;
            padding: 5px 14px 5px 6px;
            border-radius: 50px;
            background: #f1f5f9;
            text-decoration: none;
            color: #1e293b;
            font-weight: 600;
            font-size: 13.5px;
            transition: all 0.2s ease;
        }
        .pub-user-badge:hover {
            background: #e2e8f0;
        }
        .pub-avatar-circle {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: var(--primary);
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: 800;
        }
        .btn-nav-logout {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1.5px solid #fee2e2;
            background: #fef2f2;
            color: #ef4444;
            text-decoration: none;
            transition: all 0.2s ease;
        }
        .btn-nav-logout:hover {
            background: #ef4444;
            color: #ffffff;
            border-color: #ef4444;
        }

        /* Mobile Hamburger Toggle */
        .mobile-toggle-btn {
            display: none;
            background: none;
            border: none;
            color: #1e293b;
            font-size: 20px;
            padding: 8px;
            cursor: pointer;
        }

        /* Mobile Drawer */
        .mobile-drawer {
            display: none;
            background: #ffffff;
            border-bottom: 1px solid #e2e8f0;
            padding: 16px 24px;
        }
        .mobile-drawer.open {
            display: block;
        }
        .mobile-nav-links {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-bottom: 16px;
        }
        .mobile-nav-links a {
            padding: 10px 14px;
            border-radius: 8px;
            color: #1e293b;
            font-weight: 600;
            text-decoration: none;
        }
        .mobile-nav-links a:hover {
            background: #f1f5f9;
            color: var(--primary);
        }

        /* ── Hero Section ── */
        .hero {
            position: relative;
            background: linear-gradient(135deg, #091e17 0%, #0f2d24 50%, #061913 100%);
            padding: 72px 0 84px;
            color: #ffffff;
            overflow: hidden;
        }
        .hero::before {
            content: '';
            position: absolute;
            top: -20%;
            left: 10%;
            width: 500px;
            height: 500px;
            background: radial-gradient(circle, rgba(16, 185, 129, 0.18) 0%, rgba(4, 120, 87, 0) 70%);
            filter: blur(60px);
            pointer-events: none;
        }
        .hero::after {
            content: '';
            position: absolute;
            bottom: -20%;
            right: 5%;
            width: 450px;
            height: 450px;
            background: radial-gradient(circle, rgba(245, 158, 11, 0.12) 0%, rgba(245, 158, 11, 0) 70%);
            filter: blur(60px);
            pointer-events: none;
        }

        .hero-inner {
            max-width: 1320px;
            margin: 0 auto;
            padding: 0 24px;
            display: grid;
            grid-template-columns: 1.15fr 0.85fr;
            gap: 48px;
            align-items: center;
            position: relative;
            z-index: 2;
        }

        .hero-text {
            max-width: 650px;
        }

        .hero-eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 9px;
            background: rgba(16, 185, 129, 0.15);
            border: 1px solid rgba(16, 185, 129, 0.35);
            padding: 6px 16px;
            border-radius: 50px;
            font-size: 13px;
            font-weight: 700;
            color: #34d399;
            margin-bottom: 24px;
            letter-spacing: 0.3px;
        }
        .pulse-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #10b981;
            box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7);
            animation: pulse-ring 1.8s infinite;
        }
        @keyframes pulse-ring {
            0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); }
            70% { transform: scale(1); box-shadow: 0 0 0 8px rgba(16, 185, 129, 0); }
            100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
        }

        .hero-title {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 54px;
            line-height: 1.12;
            font-weight: 900;
            color: #ffffff;
            margin-bottom: 20px;
            letter-spacing: -1.2px;
        }
        .hero-title .gradient-accent {
            background: linear-gradient(135deg, #34d399 0%, #10b981 40%, #fbbf24 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .hero-sub {
            font-size: 16.5px;
            line-height: 1.65;
            color: rgba(255, 255, 255, 0.82);
            margin-bottom: 34px;
            font-weight: 400;
        }

        .hero-actions {
            display: flex;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
            margin-bottom: 42px;
        }

        .btn-hero-primary {
            background: #047857;
            color: #ffffff !important;
            padding: 14px 28px;
            border-radius: 12px;
            font-size: 15.5px;
            font-weight: 700;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            border: 1px solid rgba(255, 255, 255, 0.15);
            box-shadow: 0 8px 24px rgba(4, 120, 87, 0.45);
            transition: all 0.25s ease;
        }
        .btn-hero-primary:hover {
            background: #059669;
            transform: translateY(-2px);
            box-shadow: 0 12px 30px rgba(4, 120, 87, 0.6);
        }

        .btn-hero-secondary {
            background: rgba(255, 255, 255, 0.08);
            color: #ffffff !important;
            padding: 14px 26px;
            border-radius: 12px;
            font-size: 15.5px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 9px;
            border: 1px solid rgba(255, 255, 255, 0.22);
            backdrop-filter: blur(8px);
            transition: all 0.25s ease;
        }
        .btn-hero-secondary:hover {
            background: rgba(255, 255, 255, 0.16);
            border-color: rgba(255, 255, 255, 0.4);
            transform: translateY(-2px);
        }

        .hero-stats-row {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.12);
            padding-top: 26px;
        }
        .hero-stat-box {
            display: flex;
            flex-direction: column;
        }
        .hero-stat-number {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 28px;
            font-weight: 800;
            color: #34d399;
            line-height: 1;
            margin-bottom: 6px;
        }
        .hero-stat-desc {
            font-size: 12.5px;
            color: rgba(255, 255, 255, 0.65);
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Hero Right Preview Visual */
        .hero-visual {
            display: flex;
            flex-direction: column;
            gap: 14px;
        }
        .hero-card {
            background: rgba(255, 255, 255, 0.07);
            border: 1px solid rgba(255, 255, 255, 0.14);
            backdrop-filter: blur(16px);
            border-radius: 16px;
            padding: 14px 18px;
            display: flex;
            align-items: center;
            gap: 16px;
            text-decoration: none;
            color: #ffffff;
            transition: all 0.25s ease;
        }
        .hero-card:hover {
            background: rgba(255, 255, 255, 0.13);
            border-color: rgba(52, 211, 153, 0.4);
            transform: translateX(6px);
        }
        .hero-card.featured {
            background: linear-gradient(135deg, rgba(4, 120, 87, 0.35) 0%, rgba(15, 23, 42, 0.4) 100%);
            border: 1.5px solid rgba(52, 211, 153, 0.45);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.25);
        }
        .hero-card-thumb {
            width: 82px;
            height: 72px;
            border-radius: 10px;
            overflow: hidden;
            background: rgba(0, 0, 0, 0.3);
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .hero-card-thumb img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .hero-card-info {
            flex: 1;
            min-width: 0;
        }
        .hero-card-title {
            font-size: 14.5px;
            font-weight: 700;
            color: #ffffff;
            margin-bottom: 4px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .hero-card-price-tag {
            font-size: 13.5px;
            font-weight: 700;
            color: #34d399;
            margin-bottom: 3px;
        }
        .hero-card-meta {
            font-size: 12px;
            color: rgba(255, 255, 255, 0.65);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .hero-card-meta span {
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        /* ── Sections Common Styling ── */
        .section-wrap {
            padding: 80px 0;
        }
        .section-alt {
            background-color: #f1f5f9;
        }
        .container {
            max-width: 1320px;
            margin: 0 auto;
            padding: 0 24px;
        }
        .section-header-row {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            margin-bottom: 36px;
            flex-wrap: wrap;
            gap: 16px;
        }
        .section-badge {
            display: inline-block;
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: var(--primary);
            background: var(--primary-light);
            padding: 4px 12px;
            border-radius: 50px;
            margin-bottom: 8px;
        }
        .section-heading {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 32px;
            font-weight: 800;
            color: var(--text-heading);
            letter-spacing: -0.6px;
            line-height: 1.2;
            margin-bottom: 6px;
        }
        .section-subheading {
            font-size: 15px;
            color: #64748b;
        }
        .section-action-link {
            font-size: 14px;
            font-weight: 700;
            color: var(--primary);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s ease;
        }
        .section-action-link:hover {
            color: var(--primary-hover);
            transform: translateX(3px);
        }

        /* ── Categories Grid ── */
        .categories-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
        }
        .cat-box {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: var(--radius-lg);
            padding: 22px 20px;
            display: flex;
            align-items: center;
            gap: 16px;
            text-decoration: none;
            color: #1e293b;
            box-shadow: var(--shadow-subtle);
            transition: all 0.25s ease;
        }
        .cat-box:hover {
            border-color: var(--primary);
            box-shadow: 0 10px 25px rgba(4, 120, 87, 0.12);
            transform: translateY(-3px);
        }
        .cat-icon-wrap {
            width: 52px;
            height: 52px;
            border-radius: 14px;
            background: var(--primary-light);
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            flex-shrink: 0;
            transition: all 0.25s ease;
        }
        .cat-box:hover .cat-icon-wrap {
            background: var(--primary);
            color: #ffffff;
            transform: scale(1.08);
        }
        .cat-info-name {
            font-size: 15.5px;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 3px;
        }
        .cat-info-count {
            font-size: 12.5px;
            color: #64748b;
            font-weight: 500;
        }

        /* ── How It Works Grid ── */
        .how-steps-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 28px;
            margin-top: 20px;
        }
        .step-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: var(--radius-xl);
            padding: 36px 28px;
            text-align: center;
            box-shadow: var(--shadow-subtle);
            transition: all 0.25s ease;
            position: relative;
        }
        .step-card:hover {
            box-shadow: var(--shadow-card);
            transform: translateY(-4px);
            border-color: #cbd5e1;
        }
        .step-badge-num {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #047857 0%, #10b981 100%);
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 24px;
            font-weight: 800;
            margin: 0 auto 22px;
            box-shadow: 0 8px 20px rgba(4, 120, 87, 0.3);
        }
        .step-title {
            font-size: 18px;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 12px;
        }
        .step-desc {
            font-size: 14.5px;
            line-height: 1.6;
            color: #64748b;
        }

        /* ── Top Sellers Grid ── */
        .sellers-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 24px;
        }
        .seller-badge-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: var(--radius-lg);
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 16px;
            box-shadow: var(--shadow-subtle);
            transition: all 0.25s ease;
        }
        .seller-badge-card:hover {
            box-shadow: var(--shadow-card);
            border-color: var(--primary);
            transform: translateY(-2px);
        }
        .seller-avatar-large {
            width: 58px;
            height: 58px;
            border-radius: 50%;
            background: linear-gradient(135deg, #047857 0%, #059669 100%);
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            font-weight: 800;
            flex-shrink: 0;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(4, 120, 87, 0.2);
        }
        .seller-avatar-large img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .seller-info-title {
            font-size: 16px;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 2px;
        }
        .seller-rank-tag {
            font-size: 12px;
            font-weight: 600;
            color: var(--primary);
            display: inline-flex;
            align-items: center;
            gap: 4px;
            margin-bottom: 4px;
        }
        .seller-items-count {
            font-size: 12.5px;
            color: #64748b;
        }

        /* ── High-Impact CTA Banner ── */
        .cta-banner {
            background: linear-gradient(135deg, #091e17 0%, #047857 60%, #065f46 100%);
            padding: 70px 0;
            color: #ffffff;
            position: relative;
            overflow: hidden;
        }
        .cta-banner::before {
            content: '';
            position: absolute;
            top: 0; right: 0; bottom: 0; left: 0;
            background: radial-gradient(circle at 80% 20%, rgba(245, 158, 11, 0.2) 0%, transparent 60%);
            pointer-events: none;
        }
        .cta-banner-inner {
            position: relative;
            z-index: 2;
            text-align: center;
            max-width: 720px;
            margin: 0 auto;
        }
        .cta-banner-title {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 38px;
            font-weight: 900;
            margin-bottom: 14px;
            letter-spacing: -0.8px;
            color: #ffffff;
        }
        .cta-banner-sub {
            font-size: 16.5px;
            color: rgba(255, 255, 255, 0.85);
            margin-bottom: 32px;
            line-height: 1.6;
        }
        .cta-banner-actions {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 16px;
            flex-wrap: wrap;
        }

        /* ── Professional Footer ── */
        .site-footer {
            background: #0f172a;
            color: #94a3b8;
            padding: 68px 0 32px;
            border-top: 1px solid #1e293b;
        }
        .footer-grid-layout {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr;
            gap: 48px;
            margin-bottom: 48px;
        }
        .footer-brand-box img {
            height: 44px;
            width: auto;
            object-fit: contain;
            margin-bottom: 16px;
        }
        .footer-brand-desc {
            font-size: 13.5px;
            line-height: 1.7;
            color: #94a3b8;
            max-width: 320px;
            margin-bottom: 20px;
        }
        .footer-social-links {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .footer-social-links a {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: #1e293b;
            color: #cbd5e1;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            text-decoration: none;
            transition: all 0.2s ease;
        }
        .footer-social-links a:hover {
            background: var(--primary);
            color: #ffffff;
            transform: translateY(-2px);
        }
        .footer-title {
            font-size: 14px;
            font-weight: 700;
            color: #ffffff;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            margin-bottom: 20px;
        }
        .footer-menu {
            list-style: none;
            padding: 0;
            margin: 0;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .footer-menu a {
            font-size: 13.5px;
            color: #94a3b8;
            text-decoration: none;
            transition: all 0.2s ease;
        }
        .footer-menu a:hover {
            color: #ffffff;
            transform: translateX(3px);
        }
        .footer-divider {
            border-top: 1px solid #1e293b;
            padding-top: 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 13px;
            color: #64748b;
            flex-wrap: wrap;
            gap: 12px;
        }

        /* ── Responsive Queries ── */
        @media (max-width: 1024px) {
            .hero-inner {
                grid-template-columns: 1fr;
                gap: 40px;
            }
            .hero-title {
                font-size: 42px;
            }
            .how-steps-grid {
                grid-template-columns: 1fr;
            }
            .footer-grid-layout {
                grid-template-columns: 1fr 1fr;
            }
        }
        @media (max-width: 768px) {
            .pub-nav-links, .pub-search-wrapper {
                display: none;
            }
            .mobile-toggle-btn {
                display: block;
            }
            .hero-title {
                font-size: 34px;
            }
            .hero-stats-row {
                grid-template-columns: 1fr;
                gap: 14px;
            }
            .footer-grid-layout {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
<?php include_once __DIR__ . '/includes/components/loading_spinner_overlay.php'; ?>

<!-- ── Navigation Bar ── -->
<nav class="pub-nav">
    <div class="pub-nav-inner">
        <!-- Logo -->
        <a href="index.php" class="pub-logo" aria-label="Geo Auction Home">
            <img src="assets/image/Geo-auction-logo-clean.png" alt="Geo Auction" onerror="this.onerror=null;this.src='assets/image/Geo-auction.png'">
        </a>

        <!-- Desktop Navigation Links -->
        <div class="pub-nav-links">
            <a href="#home" class="active"><i class="fas fa-home"></i> Home</a>
            <a href="items.php"><i class="fas fa-gavel"></i> Live Auctions</a>
            <a href="#category"><i class="fas fa-th-large"></i> Categories</a>
            <a href="#about"><i class="fas fa-info-circle"></i> How It Works</a>
            <a href="contact.php"><i class="fas fa-envelope"></i> Contact</a>
        </div>

        <!-- Global Search Bar -->
        <div class="pub-search-wrapper">
            <form action="search.php" method="GET" class="pub-search-form">
                <input type="text" name="q" placeholder="Search live auctions, items..." required autocomplete="off">
                <button type="submit" class="pub-search-submit" title="Search Auctions" aria-label="Search">
                    <i class="fas fa-search"></i>
                </button>
            </form>
        </div>

        <!-- User / Auth CTA -->
        <div class="pub-nav-cta">
            <?php if ($is_logged): ?>
                <a href="user/dashboard.php" class="btn-nav-wallet" title="User Dashboard">
                    <i class="fas fa-th-large"></i>
                    <span>Dashboard</span>
                </a>
                <a href="user/dashboard.php" class="pub-user-badge" title="Go to Dashboard">
                    <div class="pub-avatar-circle"><?php echo $user_init; ?></div>
                    <span><?php echo htmlspecialchars($user_name); ?></span>
                </a>
                <a href="logout.php" class="btn-nav-logout logout-btn" title="Logout">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            <?php else: ?>
                <a href="login.php" class="btn-nav-login">Login</a>
                <a href="register.php" class="btn-nav-register">
                    <i class="fas fa-user-plus"></i> Register Free
                </a>
            <?php endif; ?>

            <!-- Mobile Hamburger Button -->
            <button class="mobile-toggle-btn" id="mobileMenuToggle" aria-label="Toggle mobile menu">
                <i class="fas fa-bars"></i>
            </button>
        </div>
    </div>

    <!-- Mobile Slide Drawer -->
    <div class="mobile-drawer" id="mobileDrawer">
        <form action="search.php" method="GET" class="pub-search-form" style="margin-bottom: 16px;">
            <input type="text" name="q" placeholder="Search live auctions..." required autocomplete="off">
            <button type="submit" class="pub-search-submit" aria-label="Search"><i class="fas fa-search"></i></button>
        </form>
        <div class="mobile-nav-links">
            <a href="#home">Home</a>
            <a href="items.php">Live Auctions</a>
            <a href="#category">Categories</a>
            <a href="#about">How It Works</a>
            <a href="contact.php">Contact</a>
        </div>
    </div>
</nav>

<!-- ── Hero Section ── -->
<section class="hero" id="home">
    <div class="hero-inner">
        <div class="hero-text">
            <div class="hero-eyebrow">
                <span class="pulse-dot"></span>
                <span><?php echo number_format($live_count); ?>+ Live Auctions Online Now</span>
            </div>
            <h1 class="hero-title">
                Bid Smart, <br><span class="gradient-accent">Win Big.</span>
            </h1>
            <p class="hero-sub">
                Explore real-time auctions across Pakistan and worldwide. Place live bids with instant updates, certified escrow transactions, and verified authentic sellers.
            </p>
            <div class="hero-actions">
                <a href="items.php" class="btn-hero-primary">
                    <i class="fas fa-gavel"></i> Explore Live Auctions
                </a>
                <?php if (!$is_logged): ?>
                    <a href="register.php" class="btn-hero-secondary">
                        <i class="fas fa-user-check"></i> Register Free
                    </a>
                <?php else: ?>
                    <a href="user/add_items.php" class="btn-hero-secondary">
                        <i class="fas fa-plus-circle"></i> Create Auction
                    </a>
                <?php endif; ?>
            </div>
            <div class="hero-stats-row">
                <div class="hero-stat-box">
                    <div class="hero-stat-number"><?php echo number_format($live_count); ?>+</div>
                    <div class="hero-stat-desc">Live Auctions</div>
                </div>
                <div class="hero-stat-box">
                    <div class="hero-stat-number"><?php echo number_format($bid_count); ?>+</div>
                    <div class="hero-stat-desc">Bids Placed</div>
                </div>
                <div class="hero-stat-box">
                    <div class="hero-stat-number"><?php echo number_format($user_count); ?>+</div>
                    <div class="hero-stat-desc">Active Members</div>
                </div>
            </div>
        </div>

        <!-- Hero Dynamic Preview Cards -->
        <div class="hero-visual">
            <?php
            $hero_items = array_slice($featured_items, 0, 3);
            foreach ($hero_items as $idx => $hi):
                $current_price = $hi['max_bid'] ? $hi['max_bid'] : $hi['starting_price'];
                $price = number_format((float)$current_price, 2);
                $diff  = $hi['end_time'] ? strtotime($hi['end_time']) - time() : 0;
                if ($diff < 3600 && $diff > 0) $timer_str = round($diff/60).'m left';
                elseif ($diff < 86400 && $diff > 0) $timer_str = round($diff/3600).'h left';
                elseif ($diff > 0) {
                    $days = floor($diff / 86400);
                    $hours = floor(($diff % 86400) / 3600);
                    $timer_str = $days . 'd ' . $hours . 'h left';
                }
                else $timer_str = 'Ending soon';
                $is_featured = ($idx === 0);
                $item_link = $is_logged ? "item.php?id={$hi['id']}" : "login.php?redirect=" . urlencode("item.php?id={$hi['id']}");
                
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
                $img_src = !empty($hi_img_list[0]) ? $hi_img_list[0] : (!empty($hi['featured_image']) ? $hi['featured_image'] : '');
            ?>
                <a href="<?php echo $item_link; ?>" class="hero-card<?php echo $is_featured ? ' featured' : ''; ?>">
                    <div class="hero-card-thumb">
                        <?php if ($img_src): ?>
                            <img src="<?php echo htmlspecialchars($img_src); ?>" alt="<?php echo htmlspecialchars($hi['title']); ?>" onerror="this.onerror=null;this.src='assets/image/Geo-auction.png';">
                        <?php else: ?>
                            <i class="fas fa-tag" style="font-size:22px;color:#34d399;"></i>
                        <?php endif; ?>
                    </div>
                    <div class="hero-card-info">
                        <div class="hero-card-title"><?php echo htmlspecialchars($hi['title']); ?></div>
                        <div class="hero-card-price-tag">Current Bid: Rs. <?php echo $price; ?></div>
                        <div class="hero-card-meta">
                            <span><i class="fas fa-gavel"></i> <?php echo (int)$hi['bid_count']; ?> bids</span>
                            <span><i class="fas fa-clock"></i> <?php echo $timer_str; ?></span>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ── Live Auctions Section ── -->
<section class="section-wrap" id="live_auction">
    <div class="container">
        <div class="section-header-row">
            <div>
                <span class="section-badge"><i class="fas fa-broadcast-tower"></i> Live & Trending</span>
                <h2 class="section-heading">Featured Live Auctions</h2>
                <p class="section-subheading">High-activity lots currently in active bidding rounds</p>
            </div>
            <a href="items.php" class="section-action-link">
                View all auctions <i class="fas fa-arrow-right"></i>
            </a>
        </div>

        <?php if (!empty($featured_items)): ?>
            <div class="catalog-grid">
            <?php foreach ($featured_items as $item): ?>
                <?php render_unified_item_card($item, $user_role, $user_id); ?>
            <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:16px; padding:60px 20px; text-align:center;">
                <div style="font-size:48px; color:#cbd5e1; margin-bottom:14px;"><i class="fas fa-gavel"></i></div>
                <h3 style="font-size:20px; font-weight:700; color:#0f172a; margin-bottom:8px;">No Live Auctions Active</h3>
                <p style="color:#64748b; margin-bottom:20px;">Be the first seller to list a featured lot on Geo Auction!</p>
                <?php if ($is_logged): ?>
                    <a href="user/add_items.php" class="btn-hero-primary" style="display:inline-flex;">Create New Listing</a>
                <?php else: ?>
                    <a href="login.php" class="btn-hero-primary" style="display:inline-flex;">Login to List an Item</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- ── Browse by Category Section ── -->
<section class="section-wrap section-alt" id="category">
    <div class="container">
        <div class="section-header-row">
            <div>
                <span class="section-badge"><i class="fas fa-layer-group"></i> Discovery</span>
                <h2 class="section-heading">Browse by Category</h2>
                <p class="section-subheading">Filter through verified auctions across popular categories</p>
            </div>
            <a href="category.php" class="section-action-link">
                All categories <i class="fas fa-arrow-right"></i>
            </a>
        </div>

        <div class="categories-grid">
        <?php if (!empty($categories)): ?>
            <?php foreach ($categories as $cat):
                $cat_link = "category.php?id={$cat['id']}";
                $cat_icon = !empty($cat['icon']) ? $cat['icon'] : 'fa-tag';
            ?>
            <a href="<?php echo $cat_link; ?>" class="cat-box">
                <div class="cat-icon-wrap">
                    <i class="fas <?php echo htmlspecialchars($cat_icon); ?>"></i>
                </div>
                <div>
                    <div class="cat-info-name"><?php echo htmlspecialchars($cat['name']); ?></div>
                    <div class="cat-info-count"><?php echo (int)$cat['item_count']; ?> active auctions</div>
                </div>
            </a>
            <?php endforeach; ?>
        <?php else: ?>
            <?php
            $def_cats = [
                ['fa-laptop','Electronics','45+ active lots'],
                ['fa-car','Vehicles','28+ active lots'],
                ['fa-gem','Jewelry & Gold','19+ active lots'],
                ['fa-paint-brush','Art & Collectibles','14+ active lots'],
                ['fa-futbol','Sports & Memorabilia','12+ active lots'],
                ['fa-couch','Furniture & Decor','22+ active lots'],
                ['fa-book','Books & Manuscripts','8+ active lots'],
                ['fa-tshirt','Fashion & Watches','35+ active lots']
            ];
            foreach ($def_cats as $dc): ?>
            <a href="category.php" class="cat-box">
                <div class="cat-icon-wrap"><i class="fas <?php echo $dc[0]; ?>"></i></div>
                <div>
                    <div class="cat-info-name"><?php echo $dc[1]; ?></div>
                    <div class="cat-info-count"><?php echo $dc[2]; ?></div>
                </div>
            </a>
            <?php endforeach; ?>
        <?php endif; ?>
        </div>
    </div>
</section>

<!-- ── How It Works Section ── -->
<section class="section-wrap" id="about">
    <div class="container">
        <div style="text-align:center; max-width:640px; margin:0 auto 48px;">
            <span class="section-badge"><i class="fas fa-shield-alt"></i> Simple & Secure</span>
            <h2 class="section-heading">How Geo Auction Works</h2>
            <p class="section-subheading">Get started in three seamless steps and win lots with total transparency.</p>
        </div>

        <div class="how-steps-grid">
            <div class="step-card">
                <div class="step-badge-num">1</div>
                <h3 class="step-title">Create Free Account</h3>
                <p class="step-desc">
                    Register in under 60 seconds with email verification. Get access to thousands of live auctions immediately.
                </p>
            </div>
            <div class="step-card">
                <div class="step-badge-num">2</div>
                <h3 class="step-title">Fund Wallet & Bid</h3>
                <p class="step-desc">
                    Top up your secure bidding wallet. Place real-time bids with instant counter-bid alerts and automated limits.
                </p>
            </div>
            <div class="step-card">
                <div class="step-badge-num">3</div>
                <h3 class="step-title">Win & Receive</h3>
                <p class="step-desc">
                    When the hammer falls, settlement is instant from your escrow wallet. Coordinate safe doorstep handover or pickup.
                </p>
            </div>
        </div>
        <?php if (!$is_logged): ?>
        <div style="text-align:center; margin-top:40px;">
            <a href="register.php" class="btn-hero-primary" style="display:inline-flex;">
                <i class="fas fa-rocket"></i> Get Started Today — Join Free
            </a>
        </div>
        <?php endif; ?>
    </div>
</section>
<?php if (!empty($top_sellers)): ?>
<section class="section-wrap section-alt">
    <div class="container">
        <div class="section-header-row">
            <div>
                <span class="section-badge"><i class="fas fa-star"></i> Trusted Community</span>
                <h2 class="section-heading">Top Rated Sellers</h2>
                <p class="section-subheading">Our most active verified sellers with 100% positive fulfillment record</p>
            </div>
        </div>
        <div class="sellers-grid">
            <?php foreach ($top_sellers as $seller):
                $initials = strtoupper(substr($seller['name'], 0, 2));
                $s_avatar = $seller['avatar'] ?? '';
            ?>
            <div class="seller-badge-card">
                <div class="seller-avatar-large">
                    <?php if (!empty($s_avatar)): ?>
                        <img src="<?php echo htmlspecialchars($s_avatar); ?>" alt="<?php echo htmlspecialchars($seller['name']); ?>">
                    <?php else: ?>
                        <?php echo $initials; ?>
                    <?php endif; ?>
                </div>
                <div>
                    <div class="seller-info-title"><?php echo htmlspecialchars($seller['name']); ?></div>
                    <div class="seller-rank-tag"><i class="fas fa-check-circle"></i> Verified Seller</div>
                    <div class="seller-items-count"><i class="fas fa-box me-1"></i> <?php echo (int)$seller['item_count']; ?> items listed</div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ── Call to Action Banner ── -->
<?php if (!$is_logged): ?>
<section class="cta-banner">
    <div class="container">
        <div class="cta-banner-inner">
            <h2 class="cta-banner-title">Ready to Experience Live Bidding?</h2>
            <p class="cta-banner-sub">
                Join thousands of certified bidders across Pakistan and beyond. Zero hidden charges. Complete escrow protection.
            </p>
            <div class="cta-banner-actions">
                <a href="register.php" class="btn-hero-primary" style="background:#ffffff; color:#047857 !important; border:none; box-shadow:0 8px 20px rgba(0,0,0,0.2);">
                    <i class="fas fa-user-plus"></i> Register Free Now
                </a>
                <a href="login.php" class="btn-hero-secondary">
                    <i class="fas fa-sign-in-alt"></i> Login to Account
                </a>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ── Site Footer ── -->
<footer class="site-footer">
    <div class="container">
        <div class="footer-grid-layout">
            <div class="footer-brand-box">
                <img src="assets/image/Geo-auction-logo-clean.png" alt="Geo Auction" onerror="this.onerror=null;this.src='assets/image/Geo-auction.png'">
                <p class="footer-brand-desc">
                    Geo Auction is Pakistan's premier live auction platform connecting authentic buyers and sellers in real time. Bid smart, win big.
                </p>
                <div class="footer-social-links">
                    <a href="#" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
                    <a href="#" aria-label="Twitter"><i class="fab fa-x-twitter"></i></a>
                    <a href="#" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
                    <a href="#" aria-label="LinkedIn"><i class="fab fa-linkedin-in"></i></a>
                </div>
            </div>
            <div>
                <h4 class="footer-title">Platform</h4>
                <ul class="footer-menu">
                    <li><a href="items.php">Live Auctions</a></li>
                    <li><a href="category.php">Categories</a></li>
                    <li><a href="search.php">Search Lots</a></li>
                    <li><a href="#about">How It Works</a></li>
                </ul>
            </div>
            <div>
                <h4 class="footer-title">Account</h4>
                <ul class="footer-menu">
                    <li><a href="login.php">Login</a></li>
                    <li><a href="register.php">Create Account</a></li>
                    <?php if ($is_logged): ?>
                        <li><a href="user/dashboard.php">Dashboard</a></li>
                        <li><a href="user/my_auctions.php">My Auctions</a></li>
                    <?php else: ?>
                        <li><a href="login.php">Wallet Overview</a></li>
                        <li><a href="login.php">Buyer Protection</a></li>
                    <?php endif; ?>
                </ul>
            </div>
            <div>
                <h4 class="footer-title">Sellers & Support</h4>
                <ul class="footer-menu">
                    <?php if ($is_logged): ?>
                        <li><a href="user/add_items.php">List an Item</a></li>
                        <li><a href="user/my_auctions.php">My Listings</a></li>
                    <?php else: ?>
                        <li><a href="login.php">Become a Seller</a></li>
                        <li><a href="login.php">Seller Fees & Escrow</a></li>
                    <?php endif; ?>
                    <li><a href="contact.php">Contact Support</a></li>
                    <li><a href="terms.php">Terms of Service</a></li>
                </ul>
            </div>
        </div>

        <div class="footer-divider">
            <span>&copy; <?php echo date('Y'); ?> GeoAuction. All rights reserved.</span>
            <span><i class="fas fa-lock me-1"></i> 256-Bit SSL Encrypted & Escrow Protected</span>
        </div>
    </div>
</footer>

<!-- ── Scripts ── -->
<script>
// Mobile Drawer Toggle
const mobileBtn = document.getElementById('mobileMenuToggle');
const mobileDrawer = document.getElementById('mobileDrawer');
if (mobileBtn && mobileDrawer) {
    mobileBtn.addEventListener('click', function() {
        mobileDrawer.classList.toggle('open');
    });
}

// Live Countdown Updater (Card & Catalog sync)
function updateAllCountdowns() {
    const timerElements = document.querySelectorAll('.stat-timer-val[data-end-timestamp], .geo-timer-val[data-end-ts]');
    const now = Math.floor(Date.now() / 1000);
    timerElements.forEach(function(el) {
        const endTs = parseInt(el.getAttribute('data-end-timestamp') || el.getAttribute('data-end-ts'), 10);
        if (!endTs) return;
        const diff = endTs - now;
        if (diff <= 0) {
            el.textContent = 'Ended';
            el.classList.add('urgent');
            return;
        }
        const d = Math.floor(diff / 86400);
        const h = Math.floor((diff % 86400) / 3600);
        const m = Math.floor((diff % 3600) / 60);
        const s = diff % 60;
        if (d > 0) {
            el.textContent = d + 'd ' + h + 'h';
        } else if (h > 0) {
            el.textContent = h + 'h ' + m + 'm';
        } else {
            el.textContent = m + 'm ' + s + 's';
            el.classList.add('urgent');
        }
    });
}
setInterval(updateAllCountdowns, 1000);
updateAllCountdowns();

// Intercept Logout clicks to show smooth spinner
document.addEventListener('click', function(e) {
    const logoutLink = e.target.closest('a[href*="logout"], .logout-btn');
    if (logoutLink && !logoutLink.hasAttribute('data-no-loader')) {
        const targetHref = logoutLink.getAttribute('href');
        if (targetHref && targetHref !== '#' && !targetHref.startsWith('javascript:')) {
            e.preventDefault();
            if (typeof showLoader === 'function') {
                showLoader('Logging out, please wait...');
            }
            setTimeout(function() {
                window.location.href = targetHref;
            }, 250);
        }
    }
});
</script>

<?php render_unified_card_assets(); ?>

</body>
</html>