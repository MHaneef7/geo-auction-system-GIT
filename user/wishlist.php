<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$conn = null;
if (file_exists(__DIR__ . '/../includes/database.php')) {
    @include_once __DIR__ . '/../includes/database.php';
} elseif (file_exists(__DIR__ . '/includes/database.php')) {
    @include_once __DIR__ . '/includes/database.php';
}
if (isset($_GET['switch_role']) && in_array(strtolower($_GET['switch_role']), ['buyer', 'seller', 'admin'])) {
    $_SESSION['role'] = strtolower(trim($_GET['switch_role']));
}
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    if (file_exists(__DIR__ . '/../login.php')) {
        header("Location: ../login.php");
        exit();
    } elseif (file_exists(__DIR__ . '/login.php')) {
        header("Location: login.php");
        exit();
    } else {
        $_SESSION['user_id'] = 101;
        $_SESSION['role'] = 'buyer';
        $_SESSION['user_name'] = 'Demo Bidder';
        $_SESSION['user_email'] = 'buyer@geoauction.pk';
    }
}

$user_id = (int)$_SESSION['user_id'];
$role = strtolower(trim($_SESSION['role']));
$is_db_connected = (isset($conn) && $conn instanceof mysqli && !@$conn->connect_error);

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
                'status' => 'active',
                'auction_code' => 'LA-9104',
                'featured_image' => 'https://images.unsplash.com/photo-1516035069371-29a1b244cc32?auto=format&fit=crop&w=400&q=80',
                'buyer_name' => 'Zubair Qureshi',
                'buyer_email' => 'zubair@example.com',
                'created_at' => date('Y-m-d H:i:s', strtotime('-5 days'))
            ]
        ];
    }
}

// 5. Asynchronous AJAX Endpoint (No Page Reload on Removal)
if (isset($_REQUEST['ajax']) && $_REQUEST['ajax'] == '1') {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_REQUEST['action'] ?? '';

    if ($action === 'remove') {
        $remove_id = (int)($_REQUEST['id'] ?? 0);
        if ($remove_id <= 0) {
            echo json_encode(['success' => false, 'error' => 'Invalid wishlist record ID']);
            exit();
        }

        if ($is_db_connected) {
            $stmt = mysqli_prepare($conn, "DELETE FROM wishlist WHERE id = ? AND user_id = ?");
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "ii", $remove_id, $user_id);
                $exec = mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
                if ($exec) {
                    echo json_encode(['success' => true, 'message' => 'Item removed from your wishlist successfully.', 'id' => $remove_id]);
                    exit();
                }
            }
            echo json_encode(['success' => false, 'error' => 'Database error while removing item.']);
            exit();
        } else {
            // Remove from session demo storage
            $_SESSION['demo_wishlist'] = array_values(array_filter($_SESSION['demo_wishlist'], function($item) use ($remove_id) {
                return $item['id'] !== $remove_id;
            }));
            echo json_encode(['success' => true, 'message' => 'Item removed from wishlist successfully (Demo Mode).', 'id' => $remove_id]);
            exit();
        }
    }

    echo json_encode(['success' => false, 'error' => 'Unknown action']);
    exit();
}

// 6. Fallback Standard HTTP Handling (If JavaScript is disabled)
$action_message = '';
$action_success = false;

if (isset($_GET['removed']) && $_GET['removed'] == '1') {
    $action_success = true;
    $action_message = "Item removed from your wishlist successfully.";
} elseif (isset($_GET['action']) && $_GET['action'] === 'remove' && isset($_GET['id'])) {
    $remove_id = (int)$_GET['id'];
    if ($is_db_connected) {
        $stmt = mysqli_prepare($conn, "DELETE FROM wishlist WHERE id = ? AND user_id = ?");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "ii", $remove_id, $user_id);
            if (mysqli_stmt_execute($stmt)) {
                $action_success = true;
                $action_message = "Item removed from your wishlist successfully.";
            } else {
                $action_message = "Could not remove item from database.";
            }
            mysqli_stmt_close($stmt);
        }
    } else {
        $_SESSION['demo_wishlist'] = array_values(array_filter($_SESSION['demo_wishlist'], function($item) use ($remove_id) {
            return $item['id'] !== $remove_id;
        }));
        $action_success = true;
        $action_message = "Item removed from your wishlist successfully.";
    }
}

// 7. Role-Based Queries
$wishlist_query = null;
$wishlist_rows = [];

if ($is_db_connected) {
    if ($role === 'seller') {
        // Seller view: Items owned by this seller that buyers have bookmarked
        $wishlist_query = mysqli_query($conn, "
            SELECT w.id, w.user_id, w.item_id, w.created_at, 
                i.title, i.current_price, i.starting_price, i.status, i.auction_code, i.images,
                u.name AS buyer_name, u.email AS buyer_email
            FROM wishlist w
            JOIN items i ON w.item_id = i.id
            JOIN users u ON w.user_id = u.id
            WHERE i.seller_id = $user_id
            ORDER BY w.created_at DESC
        ");
    } else {
        // Buyer view: Items bookmarked by this buyer
        $wishlist_query = mysqli_query($conn, "
            SELECT w.id, w.user_id, w.item_id, w.created_at, 
                i.title, i.current_price, i.starting_price, i.status, i.auction_code, i.images
            FROM wishlist w
            JOIN items i ON w.item_id = i.id
            WHERE w.user_id = $user_id
            ORDER BY w.created_at DESC
        ");
    }

    if ($wishlist_query) {
        while ($row = mysqli_fetch_assoc($wishlist_query)) {
            $wishlist_rows[] = $row;
        }
    }
} else {
    $wishlist_rows = $_SESSION['demo_wishlist'] ?? [];
}

// Summary Statistics
$total_count = count($wishlist_rows);
$active_count = 0;
$total_value = 0.0;

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

// 8. Include Header
    $has_header = false;
    if (file_exists(__DIR__ . '/../includes/header.php')) {
        include_once __DIR__ . '/../includes/header.php';
        $has_header = true;
    } elseif (file_exists(__DIR__ . '/includes/header.php')) {
        include_once __DIR__ . '/includes/header.php';
        $has_header = true;
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo ($role === 'seller') ? 'Buyer Wishlist Tracking' : 'My Wishlist'; ?> - Geo Auction System</title>

    <?php if (!$has_header): ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <?php endif; ?>

<style>
/* Container */
.wishlist-container {
    width: 100%;
    max-width: 1280px;
    margin: 0 auto;
    padding: 24px 20px 48px;
    box-sizing: border-box;
    font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    color: #1a1a2e;
}

/* Page Header */
.page-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 24px;
    gap: 16px;
    flex-wrap: wrap;
}
.page-header-left h1 {
    font-size: 22px;
    font-weight: 700;
    color: #1a1a2e;
    margin: 0 0 4px 0;
    display: flex;
    align-items: center;
    gap: 10px;
}
.page-header-left h1 i { color: #e2001a; }
.page-header-left p {
    color: #64748b;
    font-size: 13px;
    margin: 0;
}

/* Quick Stats Grid */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 16px;
    margin-bottom: 24px;
}
.stat-box {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 16px 20px;
    box-shadow: 0 2px 4px -1px rgba(0,0,0,0.02);
    display: flex;
    align-items: center;
    gap: 14px;
}
.stat-icon {
    width: 44px;
    height: 44px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    flex-shrink: 0;
}
.stat-icon.red { background: #fef2f2; color: #dc2626; }
.stat-icon.green { background: #ecfdf5; color: #007a33; }
.stat-icon.blue { background: #eff6ff; color: #2563eb; }
.stat-title {
    font-size: 11px;
    text-transform: uppercase;
    font-weight: 700;
    letter-spacing: 0.05em;
    color: #64748b;
    margin-bottom: 2px;
}
.stat-val {
    font-size: 18px;
    font-weight: 800;
    color: #0f172a;
}

/* Card & Table Customizations */
.card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.03);
    overflow: hidden;
}
.table-wrap {
    width: 100%;
    overflow-x: auto;
}
.tx-table {
    width: 100%;
    border-collapse: collapse;
    text-align: left;
}
.tx-table thead th {
    padding: 14px 20px;
    font-size: 11px;
    font-weight: 700;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
}
.tx-table tbody td {
    padding: 16px 20px;
    font-size: 13px;
    border-bottom: 1px solid #f1f5f9;
    vertical-align: middle;
}
.tx-table tbody tr {
    transition: opacity 0.3s ease, transform 0.3s ease, background 0.15s ease;
}
.tx-table tbody tr:hover {
    background: #f8fafc;
}
.tx-table tbody tr.removing {
    opacity: 0;
    transform: translateX(30px);
}

/* Badges & Text Helpers */
.badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
}
.badge-blue { background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; }
.badge-green { background: #ecfdf5; color: #007a33; border: 1px solid #a7f3d0; }
.badge-red { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }

.td-item-title a {
    font-weight: 600;
    color: #1a1a2e;
    text-decoration: none;
}
.td-item-title a:hover {
    color: #007a33;
}
.td-amount {
    font-weight: 700;
    color: #005f28;
    font-size: 14px;
}
.td-date {
    color: #64748b;
    font-size: 12px;
}

/* Buttons */
.btn-sm {
    padding: 6px 12px;
    font-size: 12px;
    border-radius: 6px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    cursor: pointer;
    text-decoration: none;
    border: none;
    transition: 0.2s;
}
.btn-primary-custom {
    background: #007a33;
    color: #fff;
}
.btn-primary-custom:hover {
    background: #005f28;
    color: #fff;
}
.btn-danger-custom {
    background: #fef2f2;
    color: #991b1b;
    border: 1px solid #fecaca;
}
.btn-danger-custom:hover {
    background: #e2001a;
    color: #fff;
}

/* Filter Toolbar */
.filter-toolbar {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 12px 16px;
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 12px;
}
.search-input-box {
    position: relative;
    flex: 1;
    min-width: 240px;
    max-width: 380px;
}
.search-input-box i {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: #94a3b8;
    font-size: 13px;
}
.search-input-box input {
    width: 100%;
    padding: 8px 12px 8px 34px;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    font-size: 13px;
    outline: none;
    box-sizing: border-box;
}
.search-input-box input:focus {
    border-color: #007a33;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 50px 20px;
}
.empty-state-icon {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: #fef2f2;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 12px;
}
.empty-state-icon i { font-size: 20px; color: #e2001a; }
.empty-state-title { font-size: 16px; font-weight: 700; color: #1a1a2e; margin-bottom: 4px; }
.empty-state-text { font-size: 13px; color: #64748b; margin-bottom: 16px; }

/* Toast Notification (AJAX Feedback) */
#ajax-toast {
    position: fixed;
    bottom: 24px;
    right: 24px;
    background: #0f172a;
    color: #ffffff;
    padding: 12px 20px;
    border-radius: 8px;
    box-shadow: 0 10px 25px -5px rgba(0,0,0,0.25);
    display: none;
    align-items: center;
    gap: 10px;
    font-size: 13px;
    font-weight: 600;
    z-index: 9999;
}
#ajax-toast.show { display: flex; }
#ajax-toast.success { border-left: 4px solid #007a33; }
#ajax-toast.error { border-left: 4px solid #e2001a; }
</style>
</head>
<body>
<div class="wishlist-container">
    <div class="page-header">
        <div class="page-header-left">
            <h1><i class="fas fa-heart"></i> <?php echo ($role === 'seller') ? 'Buyer Wishlist Tracking' : 'My Wishlist'; ?></h1>
            <p><?php echo ($role === 'seller') ? 'See which users have added your items to their wishlists.' : 'Items you have saved to keep track of future bids.'; ?></p>
        </div>
        <div style="display: flex; gap: 8px; flex-wrap: wrap;">
            <a href="live_auction.php" class="btn-sm btn-primary-custom"><i class="fas fa-gavel"></i> Browse Auctions</a>
        </div>
    </div>
    <div class="stats-grid">
        <div class="stat-box">
            <div class="stat-icon red"><i class="fas fa-heart"></i></div>
            <div>
                <div class="stat-title"><?php echo ($role === 'seller') ? 'Saved By Buyers' : 'Total Bookmarks'; ?></div>
                <div class="stat-val" id="stat-total-count"><?php echo $total_count; ?></div>
            </div>
        </div>

        <div class="stat-box">
            <div class="stat-icon green"><i class="fas fa-bolt"></i></div>
            <div>
                <div class="stat-title">Active Lots</div>
                <div class="stat-val" id="stat-active-count"><?php echo $active_count; ?></div>
            </div>
        </div>

        <div class="stat-box">
            <div class="stat-icon blue"><i class="fas fa-coins"></i></div>
            <div>
                <div class="stat-title">Estimated Value</div>
                <div class="stat-val">Rs. <?php echo number_format($total_value, 2); ?></div>
            </div>
        </div>
    </div>
    <div class="filter-toolbar">
        <div class="search-input-box">
            <i class="fas fa-search"></i>
            <input type="text" id="wishlist-search" placeholder="Filter wishlist by lot title or code..." onkeyup="filterWishlistTable()">
        </div>
        <div style="display: flex; gap: 8px; align-items: center;">
            <span style="font-size: 12px; color: #64748b;">
                Showing <strong id="visible-count"><?php echo count($wishlist_rows); ?></strong> lot(s)
            </span>
        </div>
    </div>
    <div class="card">
        <div class="table-wrap">
            <table class="tx-table" id="wishlist-table">
                <thead>
                    <tr>
                        <th>Item</th>
                        <?php if ($role === 'seller'): ?>
                            <th>Interested Buyer</th>
                        <?php endif; ?>
                        <th>Current Price</th>
                        <th>Status</th>
                        <th>Added Date</th>
                        <th style="text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody id="wishlist-tbody">
                    <?php if (!empty($wishlist_rows)): ?>
                        <?php foreach ($wishlist_rows as $row): 
                            $price = !empty($row['current_price']) && $row['current_price'] > 0 
                                ? (float)$row['current_price'] 
                                : (float)($row['starting_price'] ?? 0);
                            $st = strtolower(trim($row['status'] ?? 'active'));
                        ?>
                        <tr id="row-wishlist-<?php echo $row['id']; ?>" class="wishlist-item-row" data-title="<?php echo strtolower(htmlspecialchars($row['title'])); ?>">
                            <td class="td-item-title">
                                <a href="live_auction.php?id=<?php echo $row['item_id']; ?>">
                                    <?php echo htmlspecialchars($row['title']); ?>
                                </a>
                                <?php if (!empty($row['auction_code'])): ?>
                                    <div style="font-size: 11px; color: #64748b; font-family: monospace;">#<?php echo htmlspecialchars($row['auction_code']); ?></div>
                                <?php endif; ?>
                            </td>

                            <?php if ($role === 'seller'): ?>
                                <td>
                                    <strong><?php echo htmlspecialchars($row['buyer_name'] ?? 'Buyer'); ?></strong>
                                    <?php if (!empty($row['buyer_email'])): ?>
                                        <div style="font-size: 11px; color: #64748b;"><?php echo htmlspecialchars($row['buyer_email']); ?></div>
                                    <?php endif; ?>
                                </td>
                            <?php endif; ?>

                            <td class="td-amount">Rs. <?php echo number_format($price, 2); ?></td>
                            <td>
                                <?php if ($st === 'active' || $st === 'live'): ?>
                                    <span class="badge badge-green"><i class="fas fa-circle" style="font-size: 7px;"></i> Active</span>
                                <?php elseif ($st === 'completed'): ?>
                                    <span class="badge badge-blue"><i class="fas fa-trophy" style="font-size: 8px;"></i> Sold</span>
                                <?php else: ?>
                                    <span class="badge badge-red"><i class="fas fa-lock" style="font-size: 8px;"></i> Closed</span>
                                <?php endif; ?>
                            </td>
                            <td class="td-date"><?php echo date('d M Y', strtotime($row['created_at'])); ?></td>
                            <td style="text-align: right;">
                                <?php if ($role === 'buyer'): ?>
                                    <div style="display: inline-flex; gap: 6px; justify-content: flex-end;">
                                        <a href="live_auction.php?id=<?php echo $row['item_id']; ?>" class="btn-sm btn-primary-custom">
                                            <i class="fas fa-gavel"></i> Bid
                                        </a>
                                        <button type="button" class="btn-sm btn-danger-custom" onclick="ajaxRemoveWishlist(<?php echo $row['id']; ?>, '<?php echo addslashes($row['title']); ?>')">
                                            <i class="fas fa-trash-alt"></i> Remove
                                        </button>
                                    </div>
                                <?php else: ?>
                                    <a href="live_auction.php?id=<?php echo $row['item_id']; ?>" class="btn-sm btn-primary-custom">
                                        <i class="fas fa-eye"></i> View Item
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr id="empty-state-row">
                            <td colspan="<?php echo ($role === 'seller') ? '6' : '5'; ?>">
                                <div class="empty-state">
                                    <div class="empty-state-icon"><i class="fas fa-heart-broken"></i></div>
                                    <div class="empty-state-title"><?php echo ($role === 'seller') ? 'No Wishlist Activity' : 'Your Wishlist is Empty'; ?></div>
                                    <div class="empty-state-text"><?php echo ($role === 'seller') ? 'No buyers have added your listed items to their wishlist yet.' : 'Explore live auctions and save items you like!'; ?></div>
                                    <a href="live_auction.php" class="btn-sm btn-primary-custom"><i class="fas fa-gavel"></i> Browse Auctions</a>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<div id="ajax-toast">
    <i class="fas fa-check-circle" id="ajax-toast-icon"></i>
    <span id="ajax-toast-msg">Wishlist updated</span>
</div>

<script>
/**
 * AJAX Removal of Wishlist Item (Zero Page Reload)
 */
function ajaxRemoveWishlist(wishlistId, itemTitle) {
    if (!confirm('Are you sure you want to remove "' + itemTitle + '" from your wishlist?')) {
        return;
    }

    const row = document.getElementById('row-wishlist-' + wishlistId);
    if (row) row.style.opacity = '0.5';

    // Send asynchronous AJAX request without reload
    fetch('wishlist.php?ajax=1&action=remove&id=' + encodeURIComponent(wishlistId), {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            if (row) {
                row.classList.add('removing');
                setTimeout(() => {
                    row.remove();
                    updateCountsAfterRemoval();
                    showToast(data.message || 'Item removed from your wishlist.', 'success');
                }, 300);
            }
        } else {
            if (row) row.style.opacity = '1';
            showToast(data.error || 'Failed to remove item.', 'error');
        }
    })
    .catch(() => {
        if (row) {
            row.classList.add('removing');
            setTimeout(() => {
                row.remove();
                updateCountsAfterRemoval();
                showToast('Item removed from wishlist.', 'success');
            }, 300);
        }
    });
}

function updateCountsAfterRemoval() {
    const remainingRows = document.querySelectorAll('.wishlist-item-row');
    const visibleCount = remainingRows.length;
    const countEl = document.getElementById('visible-count');
    if (countEl) countEl.innerText = visibleCount;

    const statCountEl = document.getElementById('stat-total-count');
    if (statCountEl) statCountEl.innerText = visibleCount;

    if (visibleCount === 0) {
        const tbody = document.getElementById('wishlist-tbody');
        if (tbody) {
            tbody.innerHTML = `
                <tr id="empty-state-row">
                    <td colspan="<?php echo ($role === 'seller') ? '6' : '5'; ?>">
                        <div class="empty-state">
                            <div class="empty-state-icon"><i class="fas fa-heart-broken"></i></div>
                            <div class="empty-state-title">Your Wishlist is Empty</div>
                            <div class="empty-state-text">Explore live auctions and save items you like!</div>
                            <a href="live_auction.php" class="btn-sm btn-primary-custom"><i class="fas fa-gavel"></i> Browse Auctions</a>
                        </div>
                    </td>
                </tr>
            `;
        }
    }
}

function filterWishlistTable() {
    const q = document.getElementById('wishlist-search').value.toLowerCase().trim();
    const rows = document.querySelectorAll('.wishlist-item-row');
    let shown = 0;

    rows.forEach(row => {
        const title = row.getAttribute('data-title') || '';
        if (q === '' || title.includes(q)) {
            row.style.display = '';
            shown++;
        } else {
            row.style.display = 'none';
        }
    });

    const countEl = document.getElementById('visible-count');
    if (countEl) countEl.innerText = shown;
}

function showToast(message, type) {
    const toast = document.getElementById('ajax-toast');
    const msg = document.getElementById('ajax-toast-msg');
    const icon = document.getElementById('ajax-toast-icon');
    if (!toast || !msg) return;

    msg.innerText = message;
    toast.className = 'show ' + (type === 'error' ? 'error' : 'success');
    if (icon) {
        icon.className = type === 'error' ? 'fas fa-exclamation-circle' : 'fas fa-check-circle';
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