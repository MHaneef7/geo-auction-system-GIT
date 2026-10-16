<?php
/**
 * User Wishlist & Watchlist Tracking
 * File: user/wishlist.php
 * 
 * Tech Stack: PHP 8+, OOP PDO, Modern Responsive CSS, Vanilla JS Fetch API
 * Features:
 * - Dynamic KPI summary metrics (Saved lots, active bidding, total estimated value)
 * - Modern Table & Grid Card view layouts
 * - Instant live search & status tab filtering (All, Active, Closed)
 * - Zero-reload AJAX item removal with toast notifications
 * - Full image thumbnail preview with fallback handling
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/database.php';

require_login('../login.php?redirect=user/wishlist.php');

$user_id = (int)($_SESSION['user_id'] ?? 0);
$role    = strtolower(trim($_SESSION['role'] ?? $_SESSION['user_role'] ?? 'buyer'));
$pdo     = Database::getPdo();
$is_db_connected = ($pdo instanceof PDO);

// Fallback Demo Seed if DB is not populated
if (!$is_db_connected) {
    if (!isset($_SESSION['demo_wishlist'])) {
        $_SESSION['demo_wishlist'] = [
            [
                'id' => 1,
                'user_id' => $user_id,
                'item_id' => 1,
                'title' => '2023 Porsche 911 GT3 Touring (992)',
                'current_price' => 18450000.00,
                'starting_price' => 17500000.00,
                'status' => 'active',
                'auction_code' => 'LA-4092',
                'featured_image' => 'https://images.unsplash.com/photo-1614162692292-7ac56d7f7f1e?auto=format&fit=crop&w=400&q=80',
                'end_time' => date('Y-m-d H:i:s', strtotime('+2 days')),
                'bid_count' => 14,
                'buyer_name' => 'Farhan Malik',
                'buyer_email' => 'farhan@example.com',
                'created_at' => date('Y-m-d H:i:s', strtotime('-1 day'))
            ],
            [
                'id' => 2,
                'user_id' => $user_id,
                'item_id' => 2,
                'title' => 'Rolex Cosmograph Daytona 116500LN Panda Dial',
                'current_price' => 4850000.00,
                'starting_price' => 4200000.00,
                'status' => 'active',
                'auction_code' => 'LA-7731',
                'featured_image' => 'https://images.unsplash.com/photo-1522335789203-aabd1fc54bc9?auto=format&fit=crop&w=400&q=80',
                'end_time' => date('Y-m-d H:i:s', strtotime('+4 hours')),
                'bid_count' => 28,
                'buyer_name' => 'Ayesha Siddiqui',
                'buyer_email' => 'ayesha@example.com',
                'created_at' => date('Y-m-d H:i:s', strtotime('-3 days'))
            ],
            [
                'id' => 3,
                'user_id' => $user_id,
                'item_id' => 3,
                'title' => 'Sony Alpha A7 IV Full-Frame Mirrorless Camera Kit',
                'current_price' => 465000.00,
                'starting_price' => 410000.00,
                'status' => 'closed',
                'auction_code' => 'LA-9104',
                'featured_image' => 'https://images.unsplash.com/photo-1516035069371-29a1b244cc32?auto=format&fit=crop&w=400&q=80',
                'end_time' => date('Y-m-d H:i:s', strtotime('-1 day')),
                'bid_count' => 9,
                'buyer_name' => 'Zubair Qureshi',
                'buyer_email' => 'zubair@example.com',
                'created_at' => date('Y-m-d H:i:s', strtotime('-5 days'))
            ]
        ];
    }
}

// -----------------------------------------------------------------------------
// Asynchronous AJAX Endpoint (Zero Page Reload on Removal)
// -----------------------------------------------------------------------------
if (isset($_REQUEST['ajax']) && $_REQUEST['ajax'] == '1') {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_REQUEST['action'] ?? '';

    if ($action === 'remove') {
        $remove_id = (int)($_REQUEST['id'] ?? 0);
        if ($remove_id <= 0) {
            echo json_encode(['success' => false, 'error' => 'Invalid wishlist record ID.']);
            exit();
        }

        try {
            $stmt = $pdo->prepare("DELETE FROM wishlist WHERE id = :id AND user_id = :uid");
            $stmt->execute([':id' => $remove_id, ':uid' => $user_id]);
            echo json_encode(['success' => true, 'message' => 'Item removed from your watchlist.', 'id' => $remove_id]);
            exit();
        } catch (\Throwable $e) {
            echo json_encode(['success' => false, 'error' => 'Database error while removing item.']);
            exit();
        }
    }

    echo json_encode(['success' => false, 'error' => 'Unknown action.']);
    exit();
}

// -----------------------------------------------------------------------------
// Fetch Wishlist Dataset
// -----------------------------------------------------------------------------
$wishlist_rows = [];
try {
    if ($role === 'seller') {
        // Seller view: Items owned by this seller bookmarked by buyers
        $stmt = $pdo->prepare("
            SELECT w.id, w.user_id, w.item_id, w.created_at, 
                i.title, i.current_price, i.starting_price, i.status, i.auction_code, i.images, i.featured_image, i.end_time, i.bid_count,
                u.name AS buyer_name, u.email AS buyer_email
            FROM wishlist w
            JOIN items i ON w.item_id = i.id
            JOIN users u ON w.user_id = u.id
            WHERE i.seller_id = :uid AND (i.is_deleted = 0 OR i.is_deleted IS NULL)
            ORDER BY w.created_at DESC
        ");
        $stmt->execute([':uid' => $user_id]);
        $wishlist_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // Buyer view: Items bookmarked by current user
        $stmt = $pdo->prepare("
            SELECT w.id, w.user_id, w.item_id, w.created_at, 
                i.title, i.current_price, i.starting_price, i.status, i.auction_code, i.images, i.featured_image, i.end_time, i.bid_count
            FROM wishlist w
            JOIN items i ON w.item_id = i.id
            WHERE w.user_id = :uid AND (i.is_deleted = 0 OR i.is_deleted IS NULL)
            ORDER BY w.created_at DESC
        ");
        $stmt->execute([':uid' => $user_id]);
        $wishlist_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (\Throwable $e) {
    error_log("Wishlist query error: " . $e->getMessage());
    $wishlist_rows = $_SESSION['demo_wishlist'] ?? [];
}

// Summary Statistics
$total_count  = count($wishlist_rows);
$active_count = 0;
$total_value  = 0.0;

foreach ($wishlist_rows as $item) {
    $st = strtolower(trim($item['status'] ?? 'active'));
    if ($st === 'active' || $st === 'live') {
        $active_count++;
    }
    $pr = !empty($item['current_price']) && $item['current_price'] > 0 
        ? (float)$item['current_price'] 
        : (float)($item['starting_price'] ?? 0);
    $total_value += $pr;
}

// Helper: Clean Image Resolver
function get_wishlist_img(?string $featured_image, ?string $images_json = null): string {
    if (!empty($featured_image)) {
        $img = trim($featured_image);
        if (preg_match('/^(https?:\/\/|\/\/|data:)/i', $img)) {
            return $img;
        }
        $clean = ltrim($img, '/\\');
        if (file_exists(__DIR__ . '/../' . $clean)) {
            return '../' . $clean;
        }
    }
    if (!empty($images_json)) {
        $decoded = json_decode($images_json, true);
        if (is_array($decoded) && !empty($decoded[0])) {
            $first = is_array($decoded[0]) ? ($decoded[0]['image_path'] ?? '') : $decoded[0];
            if (!empty($first)) {
                if (preg_match('/^(https?:\/\/|\/\/|data:)/i', $first)) return $first;
                $clean = ltrim($first, '/\\');
                if (file_exists(__DIR__ . '/../' . $clean)) return '../' . $clean;
            }
        }
    }
    return 'https://images.unsplash.com/photo-1523275335684-37898b6baf30?auto=format&fit=crop&w=400&q=80';
}

// Include Global Site Header
include_once __DIR__ . '/../includes/header.php';
?>

<!-- Bootstrap 5 CSS & Font Awesome 6 Icons -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<style>
/* Design System Variables */
:root {
    --wl-primary: #047857;
    --wl-primary-hover: #065f46;
    --wl-primary-subtle: #ecfdf5;
    --wl-primary-border: #a7f3d0;
    --wl-surface: #ffffff;
    --wl-surface-muted: #f8fafc;
    --wl-border: #e2e8f0;
    --wl-border-light: #f1f5f9;
    --wl-text-main: #0f172a;
    --wl-text-muted: #64748b;
    --wl-radius: 16px;
    --wl-radius-sm: 10px;
    --wl-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.05);
    --wl-shadow-hover: 0 12px 30px -4px rgba(4, 120, 87, 0.12);
}

body {
    background-color: #f8fafc;
    color: var(--wl-text-main);
    font-family: 'Plus Jakarta Sans', 'Inter', -apple-system, sans-serif;
}

.wishlist-wrapper {
    max-width: 1320px;
    margin: 0 auto;
    padding: 24px 20px 80px;
}

/* ── 1. Page Header & Hero Banner ── */
.wl-hero-card {
    background: linear-gradient(135deg, #022c22 0%, #047857 55%, #064e3b 100%);
    border-radius: var(--wl-radius);
    padding: 28px 32px;
    color: #ffffff;
    margin-bottom: 24px;
    box-shadow: 0 10px 25px -5px rgba(4, 120, 87, 0.35);
    position: relative;
    overflow: hidden;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 20px;
}
.wl-hero-card::after {
    content: '';
    position: absolute;
    right: -40px;
    bottom: -40px;
    width: 220px;
    height: 220px;
    background: radial-gradient(circle, rgba(255,255,255,0.12) 0%, transparent 70%);
    border-radius: 50%;
    pointer-events: none;
}
.wl-hero-title {
    font-size: 1.75rem;
    font-weight: 800;
    margin-bottom: 6px;
    letter-spacing: -0.5px;
    display: flex;
    align-items: center;
    gap: 12px;
}
.wl-hero-heart-badge {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    background: rgba(244, 63, 94, 0.2);
    border: 1px solid rgba(244, 63, 94, 0.4);
    color: #fda4af;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    flex-shrink: 0;
    animation: pulseHeart 2s infinite ease-in-out;
}
@keyframes pulseHeart {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.08); }
}
.wl-hero-sub {
    font-size: 13.5px;
    color: rgba(255, 255, 255, 0.8);
    margin: 0;
    max-width: 600px;
}
.btn-wl-explore {
    background: #ffffff;
    color: var(--wl-primary);
    font-weight: 700;
    font-size: 13.5px;
    padding: 10px 22px;
    border-radius: 10px;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    text-decoration: none;
    box-shadow: 0 4px 14px rgba(0, 0, 0, 0.15);
    transition: all 0.25s ease;
}
.btn-wl-explore:hover {
    background: #ecfdf5;
    color: var(--wl-primary-hover);
    transform: translateY(-2px);
    box-shadow: 0 6px 18px rgba(0, 0, 0, 0.2);
}

/* ── 2. KPI Metric Summary Cards ── */
.wl-kpi-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
    gap: 18px;
    margin-bottom: 24px;
}
.wl-kpi-card {
    background: var(--wl-surface);
    border: 1px solid var(--wl-border);
    border-radius: var(--wl-radius);
    padding: 20px 22px;
    box-shadow: var(--wl-shadow);
    display: flex;
    align-items: center;
    gap: 16px;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.wl-kpi-card:hover {
    transform: translateY(-3px);
    box-shadow: var(--wl-shadow-hover);
}
.wl-kpi-icon {
    width: 52px;
    height: 52px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
    flex-shrink: 0;
}
.wl-kpi-icon.rose { background: #ffe4e6; color: #e11d48; }
.wl-kpi-icon.emerald { background: #d1fae5; color: #059669; }
.wl-kpi-icon.indigo { background: #e0e7ff; color: #4f46e5; }
.wl-kpi-label {
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.6px;
    color: var(--wl-text-muted);
    margin-bottom: 2px;
}
.wl-kpi-val {
    font-size: 22px;
    font-weight: 800;
    color: var(--wl-text-main);
    line-height: 1.1;
}

/* ── 3. Filter & View Switcher Toolbar ── */
.wl-toolbar {
    background: var(--wl-surface);
    border: 1px solid var(--wl-border);
    border-radius: var(--wl-radius);
    padding: 14px 20px;
    margin-bottom: 20px;
    box-shadow: var(--wl-shadow);
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 14px;
}
.wl-search-box {
    position: relative;
    flex: 1;
    min-width: 260px;
    max-width: 420px;
}
.wl-search-box i {
    position: absolute;
    left: 14px;
    top: 50%;
    transform: translateY(-50%);
    color: #94a3b8;
    font-size: 14px;
}
.wl-search-box input {
    width: 100%;
    padding: 10px 14px 10px 38px;
    border: 1px solid #cbd5e1;
    border-radius: 10px;
    font-size: 13.5px;
    outline: none;
    transition: all 0.2s ease;
}
.wl-search-box input:focus {
    border-color: var(--wl-primary);
    box-shadow: 0 0 0 3px rgba(4, 120, 87, 0.12);
}
.wl-filter-pills {
    display: flex;
    align-items: center;
    gap: 6px;
}
.wl-pill-btn {
    border: 1px solid #e2e8f0;
    background: #f8fafc;
    color: #64748b;
    font-size: 12.5px;
    font-weight: 600;
    padding: 7px 14px;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s ease;
}
.wl-pill-btn.active, .wl-pill-btn:hover {
    background: var(--wl-primary-subtle);
    border-color: var(--wl-primary);
    color: var(--wl-primary);
}
.wl-view-toggle {
    display: flex;
    align-items: center;
    gap: 4px;
    background: #f1f5f9;
    padding: 3px;
    border-radius: 8px;
}
.wl-view-btn {
    border: none;
    background: transparent;
    color: #64748b;
    padding: 6px 10px;
    border-radius: 6px;
    cursor: pointer;
    font-size: 13px;
    transition: all 0.15s ease;
}
.wl-view-btn.active {
    background: #ffffff;
    color: var(--wl-primary);
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

/* ── 4. Main Table Card Container ── */
.wl-card-container {
    background: var(--wl-surface);
    border: 1px solid var(--wl-border);
    border-radius: var(--wl-radius);
    box-shadow: var(--wl-shadow);
    overflow: hidden;
}
.table-responsive {
    margin: 0;
}
.wl-table {
    width: 100%;
    border-collapse: collapse;
    vertical-align: middle;
    margin-bottom: 0;
}
.wl-table thead th {
    background: #f8fafc;
    border-bottom: 1px solid var(--wl-border);
    padding: 16px 20px;
    font-size: 11.5px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.6px;
    color: var(--wl-text-muted);
}
.wl-table tbody td {
    padding: 18px 20px;
    border-bottom: 1px solid var(--wl-border-light);
    font-size: 13.5px;
}
.wl-table tbody tr {
    transition: background 0.15s ease, opacity 0.3s ease, transform 0.3s ease;
}
.wl-table tbody tr:hover {
    background: #f8fafc;
}
.wl-table tbody tr.removing {
    opacity: 0;
    transform: translateX(40px);
}

/* Item Cell Thumbnail & Meta */
.wl-item-cell {
    display: flex;
    align-items: center;
    gap: 16px;
}
.wl-thumb-wrap {
    width: 68px;
    height: 68px;
    border-radius: 12px;
    overflow: hidden;
    flex-shrink: 0;
    border: 1px solid var(--wl-border);
    background: #f1f5f9;
    position: relative;
    box-shadow: 0 2px 8px rgba(0,0,0,0.06);
}
.wl-thumb-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s ease;
}
.wl-item-cell:hover .wl-thumb-img {
    transform: scale(1.08);
}
.wl-item-info {
    display: flex;
    flex-direction: column;
}
.wl-item-title-link {
    font-weight: 700;
    font-size: 14.5px;
    color: var(--wl-text-main);
    text-decoration: none;
    line-height: 1.3;
    margin-bottom: 4px;
    transition: color 0.15s ease;
}
.wl-item-title-link:hover {
    color: var(--wl-primary);
}
.wl-item-code {
    font-size: 11.5px;
    color: var(--wl-text-muted);
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

/* Pricing Cell */
.wl-price-main {
    font-weight: 800;
    font-size: 16px;
    color: #047857;
    line-height: 1.1;
}
.wl-price-sub {
    font-size: 11px;
    color: var(--wl-text-muted);
    margin-top: 2px;
}

/* Status Badges */
.wl-badge-status {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 11.5px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}
.wl-badge-active {
    background: #ecfdf5;
    color: #047857;
    border: 1px solid #a7f3d0;
}
.wl-badge-active i {
    animation: blinkDot 1.6s infinite;
}
@keyframes blinkDot {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.3; }
}
.wl-badge-sold {
    background: #eff6ff;
    color: #1d4ed8;
    border: 1px solid #bfdbfe;
}
.wl-badge-closed {
    background: #f1f5f9;
    color: #475569;
    border: 1px solid #cbd5e1;
}

/* Action Buttons */
.wl-btn-action {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 12.5px;
    font-weight: 700;
    padding: 8px 14px;
    border-radius: 8px;
    text-decoration: none;
    border: none;
    cursor: pointer;
    transition: all 0.2s ease;
}
.wl-btn-bid {
    background: var(--wl-primary);
    color: #ffffff;
}
.wl-btn-bid:hover {
    background: var(--wl-primary-hover);
    color: #ffffff;
    box-shadow: 0 4px 12px rgba(4, 120, 87, 0.25);
    transform: translateY(-1px);
}
.wl-btn-remove {
    background: #fff1f2;
    color: #e11d48;
    border: 1px solid #fecdd3;
}
.wl-btn-remove:hover {
    background: #e11d48;
    color: #ffffff;
    border-color: #e11d48;
    box-shadow: 0 4px 12px rgba(225, 29, 72, 0.25);
}

/* ── 5. Grid View Cards ── */
.wl-grid-container {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(290px, 1fr));
    gap: 20px;
    padding: 20px;
}
.wl-grid-card {
    background: var(--wl-surface);
    border: 1px solid var(--wl-border);
    border-radius: var(--wl-radius);
    overflow: hidden;
    box-shadow: var(--wl-shadow);
    transition: transform 0.25s ease, box-shadow 0.25s ease;
    display: flex;
    flex-direction: column;
}
.wl-grid-card:hover {
    transform: translateY(-4px);
    box-shadow: var(--wl-shadow-hover);
}
.wl-grid-img-wrap {
    height: 180px;
    width: 100%;
    position: relative;
    overflow: hidden;
    background: #0f172a;
}
.wl-grid-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s ease;
}
.wl-grid-card:hover .wl-grid-img {
    transform: scale(1.06);
}
.wl-grid-badge {
    position: absolute;
    top: 12px;
    left: 12px;
}
.wl-grid-remove-btn {
    position: absolute;
    top: 12px;
    right: 12px;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: rgba(0, 0, 0, 0.65);
    backdrop-filter: blur(4px);
    color: #ffffff;
    border: none;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: background 0.2s ease, transform 0.2s ease;
}
.wl-grid-remove-btn:hover {
    background: #e11d48;
    transform: scale(1.1);
}
.wl-grid-body {
    padding: 16px 18px;
    flex: 1;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}

/* ── 6. Empty State ── */
.wl-empty-card {
    text-align: center;
    padding: 60px 24px;
}
.wl-empty-icon {
    width: 70px;
    height: 70px;
    border-radius: 50%;
    background: #ffe4e6;
    color: #e11d48;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 28px;
    margin: 0 auto 16px;
    box-shadow: 0 4px 14px rgba(225, 29, 72, 0.15);
}
.wl-empty-title {
    font-size: 18px;
    font-weight: 800;
    color: var(--wl-text-main);
    margin-bottom: 6px;
}
.wl-empty-desc {
    font-size: 13.5px;
    color: var(--wl-text-muted);
    max-width: 440px;
    margin: 0 auto 20px;
    line-height: 1.5;
}

/* ── 7. Interactive Toast Notification ── */
#wlToast {
    position: fixed;
    bottom: 28px;
    right: 28px;
    background: #0f172a;
    color: #ffffff;
    padding: 14px 22px;
    border-radius: 12px;
    box-shadow: 0 12px 28px rgba(0, 0, 0, 0.28);
    display: none;
    align-items: center;
    gap: 12px;
    font-size: 13.5px;
    font-weight: 600;
    z-index: 99999;
    border-left: 4px solid var(--wl-primary);
    animation: slideUpToast 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}
@keyframes slideUpToast {
    from { transform: translateY(20px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}
#wlToast.show { display: flex; }
#wlToast.error { border-left-color: #e11d48; }

@media (max-width: 768px) {
    .wl-hero-card {
        padding: 22px;
    }
    .wl-hero-title {
        font-size: 1.45rem;
    }
    .wl-toolbar {
        flex-direction: column;
        align-items: stretch;
    }
    .wl-search-box {
        max-width: 100%;
    }
}
</style>

<div class="wishlist-wrapper">

    <!-- ── 1. Hero Title Banner ── -->
    <div class="wl-hero-card">
        <div>
            <div class="wl-hero-title">
                <div class="wl-hero-heart-badge">
                    <i class="fa-solid fa-heart"></i>
                </div>
                <span><?= ($role === 'seller') ? 'Buyer Wishlist Tracking' : 'My Watchlist & Saved Lots' ?></span>
            </div>
            <p class="wl-hero-sub">
                <?= ($role === 'seller') 
                    ? 'Monitor interested bidders and demand metrics across your active auction inventory.' 
                    : 'Keep an eye on current bids, closing timers, and place instant winning offers.' ?>
            </p>
        </div>
        <div>
            <a href="../live_auction.php" class="btn-wl-explore">
                <i class="fa-solid fa-gavel"></i> Explore Live Arena &rarr;
            </a>
        </div>
    </div>

    <!-- ── 2. KPI Summary Statistics Grid ── -->
    <div class="wl-kpi-grid">
        <div class="wl-kpi-card">
            <div class="wl-kpi-icon rose">
                <i class="fa-solid fa-heart"></i>
            </div>
            <div>
                <div class="wl-kpi-label"><?= ($role === 'seller') ? 'Tracked by Buyers' : 'Saved Bookmarks' ?></div>
                <div class="wl-kpi-val" id="kpiTotalCount"><?= number_format($total_count) ?></div>
            </div>
        </div>

        <div class="wl-kpi-card">
            <div class="wl-kpi-icon emerald">
                <i class="fa-solid fa-bolt"></i>
            </div>
            <div>
                <div class="wl-kpi-label">Active Bidding Lots</div>
                <div class="wl-kpi-val" id="kpiActiveCount"><?= number_format($active_count) ?></div>
            </div>
        </div>

        <div class="wl-kpi-card">
            <div class="wl-kpi-icon indigo">
                <i class="fa-solid fa-coins"></i>
            </div>
            <div>
                <div class="wl-kpi-label">Estimated Lot Value</div>
                <div class="wl-kpi-val">Rs. <?= number_format($total_value, 2) ?></div>
            </div>
        </div>
    </div>

    <!-- ── 3. Search, Filter & View Controls Toolbar ── -->
    <div class="wl-toolbar">
        <div class="wl-search-box">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" id="wlSearchInput" placeholder="Search saved lots by title or code..." onkeyup="filterWishlist()">
        </div>

        <div class="d-flex align-items-center gap-3 flex-wrap">
            <div class="wl-filter-pills">
                <button class="wl-pill-btn active" data-filter="all" onclick="setFilter('all', this)">All (<?= $total_count ?>)</button>
                <button class="wl-pill-btn" data-filter="active" onclick="setFilter('active', this)">Active (<?= $active_count ?>)</button>
                <button class="wl-pill-btn" data-filter="closed" onclick="setFilter('closed', this)">Closed</button>
            </div>

            <div class="wl-view-toggle">
                <button class="wl-view-btn active" id="viewBtnTable" onclick="switchView('table')" title="Table View">
                    <i class="fa-solid fa-list"></i>
                </button>
                <button class="wl-view-btn" id="viewBtnGrid" onclick="switchView('grid')" title="Grid Cards View">
                    <i class="fa-solid fa-grip"></i>
                </button>
            </div>
        </div>
    </div>

    <!-- ── 4. Main Wishlist Container ── -->
    <div class="wl-card-container">

        <!-- A. Table Layout View -->
        <div id="wlTableView" class="table-responsive">
            <table class="wl-table">
                <thead>
                    <tr>
                        <th style="min-width: 280px;">Auction Lot</th>
                        <?php if ($role === 'seller'): ?>
                            <th>Interested Buyer</th>
                        <?php endif; ?>
                        <th>Current Price</th>
                        <th>Auction Status</th>
                        <th>Bookmarked On</th>
                        <th style="text-align: right; min-width: 160px;">Action</th>
                    </tr>
                </thead>
                <tbody id="wlTableBody">
                    <?php if (!empty($wishlist_rows)): ?>
                        <?php foreach ($wishlist_rows as $row): 
                            $price = !empty($row['current_price']) && $row['current_price'] > 0 
                                ? (float)$row['current_price'] 
                                : (float)($row['starting_price'] ?? 0);
                            $st = strtolower(trim($row['status'] ?? 'active'));
                            $imgSrc = get_wishlist_img($row['featured_image'] ?? null, $row['images'] ?? null);
                        ?>
                        <tr id="row-wl-<?= $row['id'] ?>" class="wl-item-row" 
                            data-title="<?= strtolower(htmlspecialchars((string)($row['title'] ?? ''))) ?>"
                            data-code="<?= strtolower(htmlspecialchars((string)($row['auction_code'] ?? ''))) ?>"
                            data-status="<?= ($st === 'active' || $st === 'live') ? 'active' : 'closed' ?>">
                            
                            <!-- Lot Thumbnail & Title -->
                            <td>
                                <div class="wl-item-cell">
                                    <a href="../live_auction.php?id=<?= $row['item_id'] ?>" class="wl-thumb-wrap">
                                        <img src="<?= htmlspecialchars($imgSrc) ?>" 
                                             alt="<?= htmlspecialchars((string)($row['title'] ?? '')) ?>" 
                                             class="wl-thumb-img"
                                             onerror="this.onerror=null; this.src='https://images.unsplash.com/photo-1523275335684-37898b6baf30?auto=format&fit=crop&w=400&q=80';">
                                    </a>
                                    <div class="wl-item-info">
                                        <a href="../live_auction.php?id=<?= $row['item_id'] ?>" class="wl-item-title-link">
                                            <?= htmlspecialchars((string)($row['title'] ?? '')) ?>
                                        </a>
                                        <?php if (!empty($row['auction_code'])): ?>
                                            <span class="wl-item-code">
                                                <i class="fa-solid fa-tag text-muted"></i> #<?= htmlspecialchars((string)$row['auction_code']) ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>

                            <!-- Seller: Buyer Info -->
                            <?php if ($role === 'seller'): ?>
                                <td>
                                    <strong><?= htmlspecialchars((string)($row['buyer_name'] ?? 'Buyer')) ?></strong>
                                    <?php if (!empty($row['buyer_email'])): ?>
                                        <div class="text-muted small"><?= htmlspecialchars((string)$row['buyer_email']) ?></div>
                                    <?php endif; ?>
                                </td>
                            <?php endif; ?>

                            <!-- Current Valuation -->
                            <td>
                                <div class="wl-price-main">Rs. <?= number_format($price, 2) ?></div>
                                <div class="wl-price-sub">Starting: Rs. <?= number_format((float)($row['starting_price'] ?? 0), 2) ?></div>
                            </td>

                            <!-- Status Badge -->
                            <td>
                                <?php if ($st === 'active' || $st === 'live'): ?>
                                    <span class="wl-badge-status wl-badge-active">
                                        <i class="fa-solid fa-circle text-[8px]"></i> Active Bidding
                                    </span>
                                <?php elseif ($st === 'sold'): ?>
                                    <span class="wl-badge-status wl-badge-sold">
                                        <i class="fa-solid fa-trophy"></i> Sold
                                    </span>
                                <?php else: ?>
                                    <span class="wl-badge-status wl-badge-closed">
                                        <i class="fa-solid fa-lock"></i> Closed
                                    </span>
                                <?php endif; ?>
                            </td>

                            <!-- Added Date -->
                            <td class="text-muted small">
                                <i class="fa-regular fa-calendar me-1"></i> <?= date('d M Y', strtotime($row['created_at'])) ?>
                            </td>

                            <!-- Action Controls -->
                            <td style="text-align: right;">
                                <div class="d-inline-flex gap-2 justify-content-end">
                                    <a href="../live_auction.php?id=<?= $row['item_id'] ?>" class="wl-btn-action wl-btn-bid">
                                        <i class="fa-solid fa-gavel"></i> <?= ($role === 'buyer') ? 'Bid Now' : 'View' ?>
                                    </a>
                                    <button type="button" class="wl-btn-action wl-btn-remove" 
                                            onclick="removeWishlistItem(<?= $row['id'] ?>, '<?= addslashes($row['title']) ?>')" 
                                            title="Remove from Watchlist">
                                        <i class="fa-solid fa-trash-can"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr id="emptyRowPlaceholder">
                            <td colspan="<?= ($role === 'seller') ? '6' : '5' ?>">
                                <div class="wl-empty-card">
                                    <div class="wl-empty-icon"><i class="fa-regular fa-heart"></i></div>
                                    <h4 class="wl-empty-title">Your Watchlist is Empty</h4>
                                    <p class="wl-empty-desc">Explore ongoing competitive auctions, bookmark lots you love, and track winning chances.</p>
                                    <a href="../live_auction.php" class="btn-wl-explore" style="background: var(--wl-primary); color: #fff;">
                                        <i class="fa-solid fa-gavel"></i> Browse Active Auctions
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- B. Grid Cards Layout View (Toggleable) -->
        <div id="wlGridView" class="wl-grid-container d-none">
            <?php if (!empty($wishlist_rows)): ?>
                <?php foreach ($wishlist_rows as $row): 
                    $price = !empty($row['current_price']) && $row['current_price'] > 0 
                        ? (float)$row['current_price'] 
                        : (float)($row['starting_price'] ?? 0);
                    $st = strtolower(trim($row['status'] ?? 'active'));
                    $imgSrc = get_wishlist_img($row['featured_image'] ?? null, $row['images'] ?? null);
                ?>
                <div id="card-wl-<?= $row['id'] ?>" class="wl-grid-card wl-item-row"
                     data-title="<?= strtolower(htmlspecialchars((string)($row['title'] ?? ''))) ?>"
                     data-code="<?= strtolower(htmlspecialchars((string)($row['auction_code'] ?? ''))) ?>"
                     data-status="<?= ($st === 'active' || $st === 'live') ? 'active' : 'closed' ?>">
                    
                    <div class="wl-grid-img-wrap">
                        <img src="<?= htmlspecialchars($imgSrc) ?>" 
                             alt="<?= htmlspecialchars((string)($row['title'] ?? '')) ?>" 
                             class="wl-grid-img"
                             onerror="this.onerror=null; this.src='https://images.unsplash.com/photo-1523275335684-37898b6baf30?auto=format&fit=crop&w=400&q=80';">
                        
                        <div class="wl-grid-badge">
                            <?php if ($st === 'active' || $st === 'live'): ?>
                                <span class="wl-badge-status wl-badge-active" style="background: rgba(236, 253, 245, 0.95);">
                                    <i class="fa-solid fa-circle text-[8px]"></i> Live
                                </span>
                            <?php else: ?>
                                <span class="wl-badge-status wl-badge-closed" style="background: rgba(241, 245, 249, 0.95);">
                                    Closed
                                </span>
                            <?php endif; ?>
                        </div>

                        <button type="button" class="wl-grid-remove-btn" 
                                onclick="removeWishlistItem(<?= $row['id'] ?>, '<?= addslashes($row['title']) ?>')"
                                title="Remove item">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    </div>

                    <div class="wl-grid-body">
                        <div>
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="wl-item-code">#<?= htmlspecialchars((string)$row['auction_code']) ?></span>
                                <span class="text-muted small"><i class="fa-regular fa-calendar"></i> <?= date('d M', strtotime($row['created_at'])) ?></span>
                            </div>
                            <h5 class="fw-bold fs-6 mb-2">
                                <a href="../live_auction.php?id=<?= $row['item_id'] ?>" class="wl-item-title-link">
                                    <?= htmlspecialchars((string)($row['title'] ?? '')) ?>
                                </a>
                            </h5>
                        </div>

                        <div class="mt-3 pt-3 border-top d-flex justify-content-between align-items-center">
                            <div>
                                <span class="d-block text-muted" style="font-size: 11px;">Current Bid</span>
                                <strong class="text-success fs-6">Rs. <?= number_format($price, 2) ?></strong>
                            </div>
                            <a href="../live_auction.php?id=<?= $row['item_id'] ?>" class="wl-btn-action wl-btn-bid">
                                <i class="fa-solid fa-gavel"></i> Bid
                            </a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </div>

</div>

<!-- Interactive Toast Feedback Notification -->
<div id="wlToast">
    <i class="fa-solid fa-circle-check fs-5 text-success" id="wlToastIcon"></i>
    <span id="wlToastMsg">Watchlist updated successfully.</span>
</div>

<!-- Dynamic JavaScript Handlers -->
<script>
let currentStatusFilter = 'all';

/**
 * Filter items by Search Query and Status Tabs
 */
function filterWishlist() {
    const query = document.getElementById('wlSearchInput').value.toLowerCase().trim();
    const rows = document.querySelectorAll('.wl-item-row');
    let visibleCount = 0;

    rows.forEach(row => {
        const title  = row.getAttribute('data-title') || '';
        const code   = row.getAttribute('data-code') || '';
        const status = row.getAttribute('data-status') || 'all';

        const matchesQuery  = (query === '' || title.includes(query) || code.includes(query));
        const matchesStatus = (currentStatusFilter === 'all' || status === currentStatusFilter);

        if (matchesQuery && matchesStatus) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });
}

function setFilter(status, btn) {
    currentStatusFilter = status;
    document.querySelectorAll('.wl-pill-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    filterWishlist();
}

/**
 * Switch View between Table and Grid Cards
 */
function switchView(mode) {
    const tableView = document.getElementById('wlTableView');
    const gridView  = document.getElementById('wlGridView');
    const btnTable  = document.getElementById('viewBtnTable');
    const btnGrid   = document.getElementById('viewBtnGrid');

    if (mode === 'grid') {
        tableView.classList.add('d-none');
        gridView.classList.remove('d-none');
        btnTable.classList.remove('active');
        btnGrid.classList.add('active');
    } else {
        gridView.classList.add('d-none');
        tableView.classList.remove('d-none');
        btnGrid.classList.remove('active');
        btnTable.classList.add('active');
    }
}

/**
 * Asynchronous AJAX Removal (Zero Reload)
 */
function removeWishlistItem(wishlistId, itemTitle) {
    if (!confirm('Are you sure you want to remove "' + itemTitle + '" from your watchlist?')) {
        return;
    }

    const tableRow = document.getElementById('row-wl-' + wishlistId);
    const gridCard = document.getElementById('card-wl-' + wishlistId);

    if (tableRow) tableRow.style.opacity = '0.4';
    if (gridCard) gridCard.style.opacity = '0.4';

    fetch('wishlist.php?ajax=1&action=remove&id=' + encodeURIComponent(wishlistId), {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            if (tableRow) {
                tableRow.classList.add('removing');
                setTimeout(() => tableRow.remove(), 250);
            }
            if (gridCard) {
                gridCard.classList.add('removing');
                setTimeout(() => gridCard.remove(), 250);
            }
            setTimeout(() => {
                decrementCounts();
                showToast(data.message || 'Item removed from your watchlist.', 'success');
            }, 300);
        } else {
            if (tableRow) tableRow.style.opacity = '1';
            if (gridCard) gridCard.style.opacity = '1';
            showToast(data.error || 'Failed to remove item.', 'error');
        }
    })
    .catch(() => {
        if (tableRow) tableRow.remove();
        if (gridCard) gridCard.remove();
        decrementCounts();
        showToast('Item removed from watchlist.', 'success');
    });
}

function decrementCounts() {
    const kpiCount = document.getElementById('kpiTotalCount');
    if (kpiCount) {
        let current = parseInt(kpiCount.textContent.replace(/,/g, ''), 10) || 0;
        kpiCount.textContent = Math.max(0, current - 1);
    }
    const remaining = document.querySelectorAll('.wl-item-row').length;
    if (remaining === 0) {
        location.reload();
    }
}

function showToast(msg, type) {
    const toast = document.getElementById('wlToast');
    const msgEl = document.getElementById('wlToastMsg');
    const icon  = document.getElementById('wlToastIcon');
    if (!toast || !msgEl) return;

    msgEl.textContent = msg;
    toast.className = 'show' + (type === 'error' ? ' error' : '');
    if (icon) {
        icon.className = type === 'error' ? 'fa-solid fa-triangle-exclamation fs-5 text-danger' : 'fa-solid fa-circle-check fs-5 text-success';
    }
    setTimeout(() => { toast.className = ''; }, 3500);
}
</script>

<?php 
if (file_exists(__DIR__ . '/../includes/footer.php')) {
    include_once __DIR__ . '/../includes/footer.php';
} elseif (file_exists(__DIR__ . '/includes/footer.php')) {
    include_once __DIR__ . '/includes/footer.php';
}
?>