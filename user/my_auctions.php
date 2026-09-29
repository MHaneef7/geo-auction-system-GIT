<?php
/**
 * Seller Dashboard - My Auction Listings
 * Geo Auction System
 *
 * Implements:
 * - Unified Card component rendering (Cards View)
 * - Table View with audit trail & strikethrough for soft-deleted items
 * - Status filtering (All, Active, Ended, Sold, Deleted)
 * - Stats summary cards
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include_once __DIR__ . '/../includes/database.php';
include_once __DIR__ . '/../includes/components/item_card.php';

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$user_id   = (int)$_SESSION['user_id'];
$user_name = $_SESSION['user_name'] ?? ($_SESSION['name'] ?? 'Seller');
$user_role = strtolower(trim($_SESSION['role'] ?? $_SESSION['user_role'] ?? 'seller'));

// View mode and filtering
$view_mode = isset($_GET['view']) && $_GET['view'] === 'table' ? 'table' : 'cards';
$filter    = isset($_GET['status']) ? trim($_GET['status']) : '';
$search    = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build Query
$where_clauses = ["i.seller_id = ?"];
$bind_params   = [$user_id];
$bind_types    = "i";

if ($filter === 'deleted') {
    $where_clauses[] = "i.is_deleted = 1";
} elseif ($filter === 'active') {
    $where_clauses[] = "i.status = 'active' AND (i.is_deleted = 0 OR i.is_deleted IS NULL) AND (i.end_time IS NULL OR i.end_time > NOW())";
} elseif ($filter === 'ended') {
    $where_clauses[] = "(i.status = 'ended' OR (i.end_time <= NOW() AND i.status != 'sold')) AND (i.is_deleted = 0 OR i.is_deleted IS NULL)";
} elseif ($filter === 'sold') {
    $where_clauses[] = "i.status = 'sold' AND (i.is_deleted = 0 OR i.is_deleted IS NULL)";
}

if ($search !== '') {
    $where_clauses[] = "(i.title LIKE ? OR i.auction_code LIKE ?)";
    $like = '%' . $search . '%';
    $bind_params[] = $like;
    $bind_params[] = $like;
    $bind_types   .= "ss";
}

$where_sql = implode(' AND ', $where_clauses);

$query = "
    SELECT 
        i.*,
        c.name AS cat_name,
        u.name AS seller_name,
        (SELECT COUNT(*) FROM bids b WHERE b.item_id = i.id) AS bid_count,
        (SELECT MAX(b.bid_amount) FROM bids b WHERE b.item_id = i.id) AS max_bid
    FROM items i
    LEFT JOIN categories c ON c.id = i.category_id
    LEFT JOIN users u ON u.id = i.seller_id
    WHERE $where_sql
    ORDER BY i.is_deleted ASC, i.id DESC
";

$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, $bind_types, ...$bind_params);
mysqli_stmt_execute($stmt);
$auctions = mysqli_stmt_get_result($stmt);

// Counts for filter tabs
$cnt_total_stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS c FROM items WHERE seller_id = ?");
mysqli_stmt_bind_param($cnt_total_stmt, "i", $user_id);
mysqli_stmt_execute($cnt_total_stmt);
$cnt_total = (int)(mysqli_fetch_assoc(mysqli_stmt_get_result($cnt_total_stmt))['c'] ?? 0);
mysqli_stmt_close($cnt_total_stmt);

$cnt_active_stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS c FROM items WHERE seller_id = ? AND status = 'active' AND (is_deleted = 0 OR is_deleted IS NULL) AND (end_time IS NULL OR end_time > NOW())");
mysqli_stmt_bind_param($cnt_active_stmt, "i", $user_id);
mysqli_stmt_execute($cnt_active_stmt);
$cnt_active = (int)(mysqli_fetch_assoc(mysqli_stmt_get_result($cnt_active_stmt))['c'] ?? 0);
mysqli_stmt_close($cnt_active_stmt);

$cnt_sold_stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS c FROM items WHERE seller_id = ? AND status = 'sold' AND (is_deleted = 0 OR is_deleted IS NULL)");
mysqli_stmt_bind_param($cnt_sold_stmt, "i", $user_id);
mysqli_stmt_execute($cnt_sold_stmt);
$cnt_sold = (int)(mysqli_fetch_assoc(mysqli_stmt_get_result($cnt_sold_stmt))['c'] ?? 0);
mysqli_stmt_close($cnt_sold_stmt);

$cnt_del_stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS c FROM items WHERE seller_id = ? AND is_deleted = 1");
mysqli_stmt_bind_param($cnt_del_stmt, "i", $user_id);
mysqli_stmt_execute($cnt_del_stmt);
$cnt_deleted = (int)(mysqli_fetch_assoc(mysqli_stmt_get_result($cnt_del_stmt))['c'] ?? 0);
mysqli_stmt_close($cnt_del_stmt);

include_once __DIR__ . '/../includes/header.php';
?>

<style>
    .seller-dash-wrap {
        max-width: 1240px;
        margin: 32px auto 60px;
        padding: 0 20px;
    }
    .seller-header-box {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 16px;
        margin-bottom: 24px;
    }
    .seller-title {
        font-size: 26px;
        font-weight: 800;
        color: #0f172a;
        margin: 0 0 4px;
        letter-spacing: -0.5px;
    }
    .seller-subtitle {
        font-size: 14px;
        color: #64748b;
        margin: 0;
    }
    .seller-btn-primary {
        background: #047857;
        color: #ffffff !important;
        padding: 11px 22px;
        border-radius: 10px;
        font-weight: 700;
        font-size: 14px;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        box-shadow: 0 4px 14px rgba(4, 120, 87, 0.25);
        transition: transform 0.2s ease, background 0.2s ease;
    }
    .seller-btn-primary:hover {
        background: #065f46;
        transform: translateY(-2px);
    }
    /* Stats Row */
    .seller-stats-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 16px;
        margin-bottom: 28px;
    }
    .seller-stat-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        padding: 18px 20px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.02);
    }
    .seller-stat-label {
        font-size: 12px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #64748b;
        margin-bottom: 6px;
    }
    .seller-stat-val {
        font-size: 26px;
        font-weight: 800;
        color: #0f172a;
        margin: 0;
    }
    .seller-stat-active .seller-stat-val { color: #047857; }
    .seller-stat-sold .seller-stat-val { color: #2563eb; }
    .seller-stat-del .seller-stat-val { color: #dc2626; }

    /* Filter & View Switcher Bar */
    .seller-toolbar {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        padding: 12px 18px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 14px;
        margin-bottom: 26px;
    }
    .seller-tabs {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }
    .seller-tab-btn {
        padding: 7px 14px;
        border-radius: 8px;
        font-size: 13px;
        font-weight: 600;
        color: #475569;
        text-decoration: none;
        background: #f1f5f9;
        transition: all 0.2s ease;
    }
    .seller-tab-btn:hover {
        background: #e2e8f0;
        color: #0f172a;
    }
    .seller-tab-btn.active {
        background: #047857;
        color: #ffffff;
    }
    .seller-tab-badge {
        background: rgba(0, 0, 0, 0.12);
        padding: 2px 6px;
        border-radius: 10px;
        font-size: 11px;
        margin-left: 4px;
    }
    .seller-tab-btn.active .seller-tab-badge {
        background: rgba(255, 255, 255, 0.25);
    }
    .seller-view-toggle {
        display: flex;
        background: #f1f5f9;
        padding: 4px;
        border-radius: 8px;
        gap: 4px;
    }
    .seller-view-btn {
        padding: 6px 12px;
        border-radius: 6px;
        font-size: 12px;
        font-weight: 700;
        color: #64748b;
        text-decoration: none;
        display: flex;
        align-items: center;
        gap: 6px;
    }
    .seller-view-btn.active {
        background: #ffffff;
        color: #0f172a;
        box-shadow: 0 1px 3px rgba(0,0,0,0.08);
    }

    /* Cards Grid */
    .seller-cards-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(285px, 1fr));
        gap: 24px;
    }

    /* Table View Styles */
    .seller-table-wrap {
        background: #ffffff;
        border-radius: 14px;
        border: 1px solid #e2e8f0;
        overflow-x: auto;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.02);
    }
    .seller-table {
        width: 100%;
        border-collapse: collapse;
        text-align: left;
        font-size: 14px;
    }
    .seller-table th {
        background: #f8fafc;
        color: #64748b;
        font-weight: 700;
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        padding: 14px 18px;
        border-bottom: 1px solid #e2e8f0;
    }
    .seller-table td {
        padding: 16px 18px;
        border-bottom: 1px solid #f1f5f9;
        vertical-align: middle;
        color: #1e293b;
    }
    .seller-table tr:hover {
        background: #f8fafc;
    }
    .seller-table tr.row-deleted {
        background: #fef2f2;
    }
    .strikethrough-text {
        text-decoration: line-through;
        color: #94a3b8;
    }
    .badge-soft-deleted {
        background: #fee2e2;
        color: #dc2626;
        padding: 4px 10px;
        border-radius: 20px;
        font-weight: 700;
        font-size: 11px;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    .badge-status {
        padding: 4px 10px;
        border-radius: 20px;
        font-weight: 700;
        font-size: 11px;
        text-transform: uppercase;
    }
    .badge-active { background: #dcfce7; color: #15803d; }
    .badge-ended  { background: #f1f5f9; color: #475569; }
    .badge-sold   { background: #dbeafe; color: #1d4ed8; }
    
    .seller-act-link {
        font-weight: 700;
        font-size: 13px;
        text-decoration: none;
        padding: 6px 12px;
        border-radius: 6px;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        transition: all 0.15s ease;
    }
    .seller-act-edit {
        background: #eff6ff;
        color: #2563eb;
    }
    .seller-act-edit:hover { background: #dbeafe; }
    .seller-act-del {
        background: #fef2f2;
        color: #dc2626;
    }
    .seller-act-del:hover { background: #fee2e2; }

    /* Empty state */
    .seller-empty-box {
        background: #ffffff;
        border-radius: 14px;
        border: 1px dashed #cbd5e1;
        padding: 56px 20px;
        text-align: center;
        color: #64748b;
    }
</style>

<div class="seller-dash-wrap">
    <!-- Header -->
    <div class="seller-header-box">
        <div>
            <h1 class="seller-title">Seller Dashboard</h1>
            <p class="seller-subtitle">Manage your auction listings, track bids, and configure inventory.</p>
        </div>
        <a href="add_items.php" class="seller-btn-primary">
            <i class="fas fa-plus-circle"></i> + Add New Listing
        </a>
    </div>

    <!-- Quick Stats -->
    <div class="seller-stats-row">
        <div class="seller-stat-card">
            <div class="seller-stat-label">Total Listings</div>
            <div class="seller-stat-val"><?php echo number_format($cnt_total); ?></div>
        </div>
        <div class="seller-stat-card seller-stat-active">
            <div class="seller-stat-label">Live Active Lots</div>
            <div class="seller-stat-val"><?php echo number_format($cnt_active); ?></div>
        </div>
        <div class="seller-stat-card seller-stat-sold">
            <div class="seller-stat-label">Items Sold</div>
            <div class="seller-stat-val"><?php echo number_format($cnt_sold); ?></div>
        </div>
        <div class="seller-stat-card seller-stat-del">
            <div class="seller-stat-label">Soft-Deleted (Trash)</div>
            <div class="seller-stat-val"><?php echo number_format($cnt_deleted); ?></div>
        </div>
    </div>

    <!-- Toolbar: Tabs & View Switcher -->
    <div class="seller-toolbar">
        <div class="seller-tabs">
            <a href="my_auctions.php?view=<?php echo $view_mode; ?>" class="seller-tab-btn <?php echo empty($filter) ? 'active' : ''; ?>">
                All Lots <span class="seller-tab-badge"><?php echo $cnt_total; ?></span>
            </a>
            <a href="my_auctions.php?status=active&view=<?php echo $view_mode; ?>" class="seller-tab-btn <?php echo $filter === 'active' ? 'active' : ''; ?>">
                Active <span class="seller-tab-badge"><?php echo $cnt_active; ?></span>
            </a>
            <a href="my_auctions.php?status=ended&view=<?php echo $view_mode; ?>" class="seller-tab-btn <?php echo $filter === 'ended' ? 'active' : ''; ?>">
                Ended
            </a>
            <a href="my_auctions.php?status=sold&view=<?php echo $view_mode; ?>" class="seller-tab-btn <?php echo $filter === 'sold' ? 'active' : ''; ?>">
                Sold <span class="seller-tab-badge"><?php echo $cnt_sold; ?></span>
            </a>
            <a href="my_auctions.php?status=deleted&view=<?php echo $view_mode; ?>" class="seller-tab-btn <?php echo $filter === 'deleted' ? 'active' : ''; ?>">
                <i class="fas fa-trash-alt"></i> Soft-Deleted <span class="seller-tab-badge"><?php echo $cnt_deleted; ?></span>
            </a>
        </div>

        <div class="seller-view-toggle">
            <a href="my_auctions.php?view=cards<?php echo $filter ? '&status='.$filter : ''; ?>" class="seller-view-btn <?php echo $view_mode === 'cards' ? 'active' : ''; ?>">
                <i class="fas fa-th-large"></i> Cards View
            </a>
            <a href="my_auctions.php?view=table<?php echo $filter ? '&status='.$filter : ''; ?>" class="seller-view-btn <?php echo $view_mode === 'table' ? 'active' : ''; ?>">
                <i class="fas fa-table"></i> Table View
            </a>
        </div>
    </div>

    <!-- MAIN CONTENT VIEW -->
    <?php if ($auctions && mysqli_num_rows($auctions) > 0): ?>
        <?php if ($view_mode === 'cards'): ?>
            <!-- UNIFIED CARDS VIEW -->
            <div class="seller-cards-grid">
                <?php 
                while ($row = mysqli_fetch_assoc($auctions)) {
                    render_unified_item_card($row, 'seller', $user_id, ['is_subfolder' => true]);
                }
                ?>
            </div>
        <?php else: ?>
            <!-- TABLE VIEW -->
            <div class="seller-table-wrap">
                <table class="seller-table">
                    <thead>
                        <tr>
                            <th>Lot Item</th>
                            <th>Starting</th>
                            <th>Current Bid</th>
                            <th>Bids</th>
                            <th>Status</th>
                            <th>End Time</th>
                            <th style="text-align: right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = mysqli_fetch_assoc($auctions)): 
                            $is_del = !empty($row['is_deleted']);
                        ?>
                        <tr class="<?php echo $is_del ? 'row-deleted' : ''; ?>">
                            <td>
                                <div style="display: flex; align-items: center; gap: 12px;">
                                    <div style="width: 44px; height: 44px; border-radius: 8px; overflow: hidden; background: #e2e8f0; flex-shrink: 0;">
                                        <img src="<?php echo cleanCardImagePath($row['featured_image'], true); ?>" 
                                             alt="Thumb" 
                                             style="width: 100%; height: 100%; object-fit: cover;" 
                                             onerror="this.src='../assets/image/Geo-auction.png';">
                                    </div>
                                    <div>
                                        <div style="font-weight: 700;" class="<?php echo $is_del ? 'strikethrough-text' : ''; ?>">
                                            <a href="../live_auction.php?id=<?php echo $row['id']; ?>" style="color: inherit; text-decoration: none;">
                                                <?php echo htmlspecialchars($row['title']); ?>
                                            </a>
                                        </div>
                                        <div style="font-size: 11px; color: #64748b;">
                                            Code: #<?php echo htmlspecialchars($row['auction_code'] ?? ('LOT-'.$row['id'])); ?>
                                            <?php if (!empty($row['cat_name'])): ?> &bull; <?php echo htmlspecialchars($row['cat_name']); ?><?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td>Rs. <?php echo number_format($row['starting_price'], 2); ?></td>
                            <td style="font-weight: 700; color: #047857;" class="<?php echo $is_del ? 'strikethrough-text' : ''; ?>">
                                Rs. <?php echo number_format($row['max_bid'] ?? $row['current_price'], 2); ?>
                            </td>
                            <td><?php echo (int)$row['bid_count']; ?> bids</td>
                            <td>
                                <?php if ($is_del): ?>
                                    <span class="badge-soft-deleted">
                                        <i class="fas fa-trash-alt"></i> Soft Deleted
                                    </span>
                                <?php else: ?>
                                    <span class="badge-status badge-<?php echo strtolower($row['status']); ?>">
                                        <?php echo ucfirst($row['status']); ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td style="color: #64748b; font-size: 12px;"><?php echo !empty($row['end_time']) ? htmlspecialchars($row['end_time']) : 'No limit'; ?></td>
                            <td style="text-align: right;">
                                <?php if (!$is_del): ?>
                                    <a href="edit_item.php?id=<?php echo $row['id']; ?>" class="seller-act-link seller-act-edit" title="Edit Item">
                                        <i class="fas fa-pen"></i> Edit
                                    </a>
                                    <a href="delete_item.php?id=<?php echo $row['id']; ?>" 
                                       onclick="return confirm('Soft-delete this auction listing? It will immediately disappear from the public marketplace.');" 
                                       class="seller-act-link seller-act-del" 
                                       title="Soft Delete Item">
                                        <i class="fas fa-trash-alt"></i> Delete
                                    </a>
                                <?php else: ?>
                                    <span style="font-size: 12px; color: #dc2626; font-weight: 600;">
                                        <i class="fas fa-ban"></i> Removed
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <div class="seller-empty-box">
            <i class="fas fa-gavel fa-3x" style="color: #cbd5e1; margin-bottom: 16px;"></i>
            <h3 style="font-size: 18px; font-weight: 700; color: #1e293b; margin-bottom: 6px;">No auction listings found</h3>
            <p style="font-size: 14px; margin-bottom: 18px;">You haven't listed any auctions under this filter tab.</p>
            <a href="add_items.php" class="seller-btn-primary">Create Your First Listing</a>
        </div>
    <?php endif; ?>
</div>

<?php 
if (isset($stmt)) {
    mysqli_stmt_close($stmt);
}

// Render the unified quick view modal and interactive scripts
render_unified_card_assets();

include_once __DIR__ . '/../includes/footer.php'; 
?>