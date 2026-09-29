<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include_once __DIR__ . '/../includes/database.php';
include_once __DIR__ . '/../includes/auth.php';
include_once __DIR__ . '/../includes/components/item_card.php';

require_admin('../index.php');

$msg = '';
$msg_type = 'success';
$admin_id = (int)($_SESSION['user_id'] ?? 0);

// Handle Admin Actions: Approve, Reject, End, Soft-Delete, Restore, Purge
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = $_POST['action'] ?? '';
    $iid    = (int)($_POST['item_id'] ?? 0);

    if ($iid > 0) {
        if ($action === 'approve') {
            $stmt = mysqli_prepare($conn, "UPDATE items SET status = 'active', is_deleted = 0 WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "i", $iid);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            $msg = 'Item #' . $iid . ' approved and set to active successfully.';
        } elseif ($action === 'reject') {
            $stmt = mysqli_prepare($conn, "UPDATE items SET status = 'cancelled' WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "i", $iid);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            $msg = 'Item #' . $iid . ' rejected and cancelled.';
        } elseif ($action === 'end') {
            $stmt = mysqli_prepare($conn, "UPDATE items SET status = 'ended' WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "i", $iid);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            $msg = 'Auction for item #' . $iid . ' ended.';
        } elseif ($action === 'soft_delete') {
            $stmt = mysqli_prepare($conn, "UPDATE items SET is_deleted = 1, deleted_at = NOW(), deleted_by = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "ii", $admin_id, $iid);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            $msg = 'Item #' . $iid . ' has been soft-deleted (hidden from marketplace, preserved for audit).';
        } elseif ($action === 'restore') {
            $stmt = mysqli_prepare($conn, "UPDATE items SET is_deleted = 0, deleted_at = NULL, deleted_by = NULL WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "i", $iid);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            $msg = 'Item #' . $iid . ' has been successfully restored to the active inventory.';
        } elseif ($action === 'purge') {
            // Permanent hard deletion
            mysqli_query($conn, "DELETE FROM bids WHERE item_id = $iid");
            mysqli_query($conn, "DELETE FROM wishlist WHERE item_id = $iid");
            if ($conn->query("SHOW TABLES LIKE 'item_images'")->num_rows > 0) {
                mysqli_query($conn, "DELETE FROM item_images WHERE item_id = $iid");
            }
            mysqli_query($conn, "DELETE FROM items WHERE id = $iid");
            $msg = 'Item #' . $iid . ' and all related bids were permanently purged from the database.';
            $msg_type = 'danger';
        }
    }
}

$filter    = isset($_GET['status']) ? mysqli_real_escape_string($conn, $_GET['status']) : '';
$search    = isset($_GET['search']) ? mysqli_real_escape_string($conn, trim($_GET['search'])) : '';
$view_mode = isset($_GET['view']) && $_GET['view'] === 'cards' ? 'cards' : 'table';

$where_parts = [];
if ($filter === 'deleted') {
    $where_parts[] = "i.is_deleted = 1";
} elseif ($filter !== '') {
    $where_parts[] = "i.status = '$filter' AND i.is_deleted = 0";
}
if ($search) {
    $where_parts[] = "(i.title LIKE '%$search%' OR u.name LIKE '%$search%' OR i.auction_code LIKE '%$search%')";
}
$where = count($where_parts) ? 'WHERE ' . implode(' AND ', $where_parts) : '';

$items_res = mysqli_query($conn, "
    SELECT i.*, u.name AS seller_name, c.name AS category_name,
           (SELECT COUNT(*) FROM bids b WHERE b.item_id = i.id) AS bid_count
    FROM items i
    LEFT JOIN users u ON u.id = i.seller_id
    LEFT JOIN categories c ON c.id = i.category_id
    $where
    ORDER BY i.is_deleted DESC, i.id DESC
");

// Counts for filter pills
$cnt_total_res   = mysqli_query($conn, "SELECT COUNT(*) AS c FROM items");
$cnt_total       = mysqli_fetch_assoc($cnt_total_res)['c'] ?? 0;
$cnt_active_res  = mysqli_query($conn, "SELECT COUNT(*) AS c FROM items WHERE status = 'active' AND is_deleted = 0");
$cnt_active      = mysqli_fetch_assoc($cnt_active_res)['c'] ?? 0;
$cnt_pending_res = mysqli_query($conn, "SELECT COUNT(*) AS c FROM items WHERE status = 'pending' AND is_deleted = 0");
$cnt_pending     = mysqli_fetch_assoc($cnt_pending_res)['c'] ?? 0;
$cnt_deleted_res = mysqli_query($conn, "SELECT COUNT(*) AS c FROM items WHERE is_deleted = 1");
$cnt_deleted     = mysqli_fetch_assoc($cnt_deleted_res)['c'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Items & Unified Cards - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-green: rgb(4, 120, 87);
            --primary-green-hover: rgb(3, 98, 71);
        }
        body { 
            background-color: #f8f9fa; 
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
        }
        .sidebar { 
            height: 100vh; 
            background-color: #1a1d21; 
            color: #fff; 
            position: fixed; 
            top: 0; 
            left: 0; 
            width: 250px; 
            padding-top: 20px; 
            overflow-y: auto; 
            z-index: 100;
        }
        .sidebar a { 
            color: #8fa8b8; 
            text-decoration: none; 
            padding: 12px 20px; 
            display: block; 
            transition: 0.3s; 
            font-size: 14px;
        }
        .sidebar a:hover, .sidebar a.active { 
            color: #fff; 
            background-color: var(--primary-green); 
        }
        .sidebar .nav-category { 
            font-size: 0.75rem; 
            text-transform: uppercase; 
            letter-spacing: 1px; 
            color: #4a6a7a; 
            padding: 10px 20px; 
            margin-top: 10px; 
        }
        .main-content { 
            margin-left: 250px; 
            padding: 30px; 
        }
        @media(max-width: 768px) {
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
            }
            .main-content {
                margin-left: 0;
                padding: 15px;
            }
        }
        .table-row-deleted {
            background-color: #fff5f5 !important;
        }
        .text-strikethrough {
            text-decoration: line-through !important;
            color: #dc2626 !important;
        }
        .admin-cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(290px, 1fr));
            gap: 22px;
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <h3 class="text-center text-white mb-4"><i class="fas fa-gavel me-2"></i>AuctionAdmin</h3>
        <div class="nav-category">Core</div>
        <a href="dashboard.php"><i class="fas fa-tachometer-alt me-2"></i> Dashboard</a>
        <a href="../user/dashboard.php"><i class="fas fa-house me-2"></i> User Portal</a>
        
        <div class="nav-category">Management</div>
        <a href="users.php"><i class="fas fa-users me-2"></i> Users</a>
        <a href="categories.php"><i class="fas fa-tags me-2"></i> Categories</a>
        <a href="items.php" class="active"><i class="fas fa-box-open me-2"></i> Items (Cards & Table)</a>
        <a href="bids.php"><i class="fas fa-hand-holding-usd me-2"></i> Bids</a>
        
        <div class="nav-category">System</div>
        <a href="reports.php"><i class="fas fa-chart-line me-2"></i> Reports</a>
        <a href="logout.php" class="text-danger mt-4"><i class="fas fa-sign-out-alt me-2"></i> Logout</a>
    </div>

    <div class="main-content">
        <!-- Top Title Bar -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
            <div>
                <h2 class="fw-bold mb-1" style="color: #111827;">
                    <i class="fas fa-box-open me-2 text-success"></i>Item Inventory & Soft-Delete Audit
                </h2>
                <p class="text-muted small mb-0">Role-Based Access Control, Soft-Delete tracking, and Unified Card System.</p>
            </div>
            <div class="d-flex align-items-center gap-2">
                <!-- Toggle View Mode Button -->
                <div class="btn-group shadow-sm me-2">
                    <a href="?view=table<?php echo $filter ? '&status='.$filter : ''; ?><?php echo $search ? '&search='.urlencode($search) : ''; ?>" 
                       class="btn btn-sm <?php echo $view_mode === 'table' ? 'btn-dark' : 'btn-outline-secondary'; ?>">
                        <i class="fas fa-table me-1"></i> Table View
                    </a>
                    <a href="?view=cards<?php echo $filter ? '&status='.$filter : ''; ?><?php echo $search ? '&search='.urlencode($search) : ''; ?>" 
                       class="btn btn-sm <?php echo $view_mode === 'cards' ? 'btn-dark' : 'btn-outline-secondary'; ?>">
                        <i class="fas fa-th-large me-1"></i> Cards View
                    </a>
                </div>

                <a href="../user/add_items.php" class="btn btn-success fw-semibold px-3 py-2 shadow-sm d-inline-flex align-items-center gap-2" style="background-color: var(--primary-green); border-color: var(--primary-green);">
                    <i class="fas fa-plus-circle"></i> + Add New Item
                </a>
            </div>
        </div>

        <?php if ($msg): ?>
            <div class="alert alert-<?php echo $msg_type; ?> alert-dismissible fade show shadow-sm">
                <i class="fas fa-circle-info me-2"></i><?php echo htmlspecialchars($msg); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Quick Stats Tabs -->
        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-lg-3">
                <div class="card border-0 shadow-sm p-3 rounded-3 bg-white">
                    <span class="text-muted small fw-semibold">Total Catalog</span>
                    <h3 class="fw-bold text-dark mb-0"><?php echo number_format($cnt_total); ?></h3>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="card border-0 shadow-sm p-3 rounded-3 bg-white">
                    <span class="text-muted small fw-semibold">Live Active</span>
                    <h3 class="fw-bold text-success mb-0"><?php echo number_format($cnt_active); ?></h3>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="card border-0 shadow-sm p-3 rounded-3 bg-white">
                    <span class="text-muted small fw-semibold">Pending Approval</span>
                    <h3 class="fw-bold text-warning mb-0"><?php echo number_format($cnt_pending); ?></h3>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="card border-0 shadow-sm p-3 rounded-3 bg-white border-start border-danger border-4">
                    <span class="text-danger small fw-semibold">Soft-Deleted (Trash)</span>
                    <h3 class="fw-bold text-danger mb-0"><?php echo number_format($cnt_deleted); ?></h3>
                </div>
            </div>
        </div>

        <!-- Filter & Search Controls -->
        <div class="card border-0 shadow-sm mb-4" style="border-radius: 12px;">
            <div class="card-body p-3">
                <form method="GET" class="row g-2 align-items-center">
                    <input type="hidden" name="view" value="<?php echo htmlspecialchars($view_mode); ?>">
                    <div class="col-md-5">
                        <div class="input-group">
                            <span class="input-group-text bg-white border-end-0 text-muted"><i class="fas fa-search"></i></span>
                            <input type="text" name="search" class="form-control border-start-0 ps-0 shadow-none" placeholder="Search by title, lot code, or seller..." value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <select name="status" class="form-select shadow-none">
                            <option value="">All Lots (Active & Deleted)</option>
                            <option value="active" <?php echo $filter === 'active' ? 'selected' : ''; ?>>Active Only</option>
                            <option value="pending" <?php echo $filter === 'pending' ? 'selected' : ''; ?>>Pending Approval</option>
                            <option value="ended" <?php echo $filter === 'ended' ? 'selected' : ''; ?>>Ended Auctions</option>
                            <option value="sold" <?php echo $filter === 'sold' ? 'selected' : ''; ?>>Sold Items</option>
                            <option value="cancelled" <?php echo $filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            <option value="deleted" <?php echo $filter === 'deleted' ? 'selected' : ''; ?>>⚠️ Deleted by Seller / Soft-Deleted</option>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex gap-2">
                        <button type="submit" class="btn text-white w-100 fw-semibold" style="background-color: var(--primary-green);"><i class="fas fa-filter me-1"></i>Filter</button>
                        <a href="items.php?view=<?php echo $view_mode; ?>" class="btn btn-outline-secondary">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- VIEW MODE 1: UNIFIED CARDS VIEW -->
        <?php if ($view_mode === 'cards'): ?>
            <?php if ($items_res && mysqli_num_rows($items_res) > 0): ?>
                <div class="admin-cards-grid">
                    <?php 
                    // Render using the Unified Card Component
                    while ($item = mysqli_fetch_assoc($items_res)) {
                        render_unified_item_card($item, 'admin', $admin_id, ['is_subfolder' => true]);
                    }
                    ?>
                </div>
            <?php else: ?>
                <div class="card border-0 shadow-sm p-5 text-center text-muted rounded-3">
                    <i class="fas fa-box-open fa-3x mb-3 text-secondary"></i>
                    <h5>No auction lots match your filter criteria.</h5>
                </div>
            <?php endif; ?>

        <!-- VIEW MODE 2: TABLE VIEW -->
        <?php else: ?>
            <div class="card border-0 shadow-sm" style="border-radius: 12px; overflow: hidden;">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="py-3 ps-3">#ID</th>
                                    <th class="py-3">Title & Code</th>
                                    <th class="py-3">Seller</th>
                                    <th class="py-3">Category</th>
                                    <th class="py-3">Current Price</th>
                                    <th class="py-3">Bids</th>
                                    <th class="py-3">Status</th>
                                    <th class="py-3">Audit Details</th>
                                    <th class="py-3 text-end pe-3">Role-Based Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($items_res && mysqli_num_rows($items_res) > 0): ?>
                                    <?php while ($item = mysqli_fetch_assoc($items_res)): 
                                        $is_del = !empty($item['is_deleted']);
                                    ?>
                                    <tr class="<?php echo $is_del ? 'table-row-deleted' : ''; ?>">
                                        <td class="ps-3 fw-semibold">#<?php echo $item['id']; ?></td>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="w-10 h-10 rounded overflow-hidden bg-light border shrink-0" style="width: 40px; height: 40px;">
                                                    <img src="<?php echo cleanCardImagePath($item['featured_image'], true); ?>" alt="Thumb" style="width: 100%; height: 100%; object-fit: cover;" onerror="this.src='../assets/image/Geo-auction.png';">
                                                </div>
                                                <div>
                                                    <div class="<?php echo $is_del ? 'text-strikethrough' : 'fw-semibold text-dark'; ?>">
                                                        <?php echo htmlspecialchars(mb_substr($item['title'], 0, 32)) . (mb_strlen($item['title']) > 32 ? '...' : ''); ?>
                                                    </div>
                                                    <small class="text-muted font-mono">#<?php echo htmlspecialchars($item['auction_code']); ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="text-muted"><?php echo htmlspecialchars($item['seller_name'] ?? 'N/A'); ?></td>
                                        <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($item['category_name'] ?? 'General'); ?></span></td>
                                        
                                        <!-- Price with strikethrough if deleted -->
                                        <td class="fw-bold <?php echo $is_del ? 'text-strikethrough' : 'text-success'; ?>">
                                            Rs. <?php echo number_format($item['current_price'] ?? $item['starting_price'], 2); ?>
                                        </td>

                                        <td><span class="badge bg-info bg-opacity-10 text-info px-2 py-1"><?php echo (int)$item['bid_count']; ?> bids</span></td>
                                        
                                        <!-- Status Badge -->
                                        <td>
                                            <?php if ($is_del): ?>
                                                <span class="badge bg-danger text-white px-2 py-1 shadow-sm">
                                                    <i class="fas fa-trash-alt me-1"></i> Deleted by Seller
                                                </span>
                                            <?php elseif ($item['status'] === 'active'): ?>
                                                <span class="badge bg-success bg-opacity-10 text-success px-2 py-1">Active</span>
                                            <?php elseif ($item['status'] === 'pending'): ?>
                                                <span class="badge bg-warning bg-opacity-10 text-dark px-2 py-1">Pending</span>
                                            <?php elseif ($item['status'] === 'ended'): ?>
                                                <span class="badge bg-secondary bg-opacity-10 text-secondary px-2 py-1">Ended</span>
                                            <?php elseif ($item['status'] === 'sold'): ?>
                                                <span class="badge bg-primary bg-opacity-10 text-primary px-2 py-1">Sold</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger bg-opacity-10 text-danger px-2 py-1"><?php echo ucfirst($item['status']); ?></span>
                                            <?php endif; ?>
                                        </td>

                                        <!-- Audit Details -->
                                        <td class="small text-muted">
                                            <?php if ($is_del): ?>
                                                <span class="text-danger fw-semibold d-block"><i class="fas fa-clock me-1"></i> Deleted:</span>
                                                <?php echo date('M d, H:i', strtotime($item['deleted_at'])); ?>
                                            <?php else: ?>
                                                <span>Ends: <?php echo $item['end_time'] ? date('M d, H:i', strtotime($item['end_time'])) : 'N/A'; ?></span>
                                            <?php endif; ?>
                                        </td>

                                        <!-- Role-Based Action Buttons -->
                                        <td class="text-end pe-3">
                                            <div class="d-flex gap-1 justify-content-end align-items-center">
                                                <!-- View Details modal trigger -->
                                                <?php
                                                $table_payload = [
                                                    'id'               => (int)$item['id'],
                                                    'auction_code'     => $item['auction_code'],
                                                    'title'            => $item['title'],
                                                    'category'         => $item['category_name'] ?? 'General',
                                                    'seller_name'      => $item['seller_name'] ?? 'Verified Seller',
                                                    'city'             => $item['city'] ?? 'Pakistan',
                                                    'condition'        => $item['condition_type'] ?? '',
                                                    'current_price'    => number_format((float)($item['current_price'] ?? $item['starting_price']), 2),
                                                    'starting_price'   => number_format((float)$item['starting_price'], 2),
                                                    'bid_count'        => (int)$item['bid_count'],
                                                    'status'           => $is_del ? 'Deleted by Seller' : ucfirst($item['status']),
                                                    'is_deleted'       => $is_del,
                                                    'is_urgent'        => false,
                                                    'description'      => $item['description'] ?? '',
                                                    'brand'            => $item['brand'] ?? 'N/A',
                                                    'model'            => $item['model'] ?? 'N/A',
                                                    'year'             => $item['year'] ?? 'N/A',
                                                    'mileage'          => $item['mileage'] ?? 'N/A',
                                                    'engine_type'      => $item['engine_type'] ?? 'N/A',
                                                    'color'            => $item['color'] ?? 'N/A',
                                                    'images'           => [cleanCardImagePath($item['featured_image'], true)],
                                                    'detail_link'      => '../live_auction.php?id=' . $item['id']
                                                ];
                                                ?>
                                                <button type="button" class="btn btn-sm btn-outline-secondary" 
                                                        onclick="openUnifiedQuickView(this)" 
                                                        data-payload="<?php echo htmlspecialchars(json_encode($table_payload), ENT_QUOTES, 'UTF-8'); ?>"
                                                        title="View Full Item Details">
                                                    <i class="fas fa-eye"></i>
                                                </button>

                                                <?php if ($is_del): ?>
                                                    <!-- Restore Action -->
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="action" value="restore">
                                                        <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-success fw-semibold" title="Restore to Active Catalog">
                                                            <i class="fas fa-undo me-1"></i> Restore
                                                        </button>
                                                    </form>
                                                    <!-- Permanent Purge Action -->
                                                    <form method="POST" class="d-inline" onsubmit="return confirm('PERMANENT PURGE: Delete this item and all associated bid records forever?');">
                                                        <input type="hidden" name="action" value="purge">
                                                        <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-danger fw-semibold" title="Permanently Delete (Purge)">
                                                            <i class="fas fa-skull me-1"></i> Purge
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <!-- Normal Actions -->
                                                    <?php if ($item['status'] === 'pending'): ?>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="action" value="approve">
                                                        <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-success" title="Approve Item"><i class="fas fa-check"></i></button>
                                                    </form>
                                                    <?php endif; ?>

                                                    <a href="../user/edit_item.php?id=<?php echo $item['id']; ?>" class="btn btn-sm btn-outline-primary" title="Edit Item">
                                                        <i class="fas fa-pen"></i>
                                                    </a>

                                                    <!-- Soft Delete Action -->
                                                    <form method="POST" class="d-inline" onsubmit="return confirm('Soft-delete this item? It will be removed from the public marketplace but kept in admin audit.');">
                                                        <input type="hidden" name="action" value="soft_delete">
                                                        <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Soft Delete Item"><i class="fas fa-trash"></i></button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr><td colspan="9" class="text-center py-4 text-muted">No auction lots found matching query.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Render Unified Modal Assets -->
    <?php render_unified_card_assets(); ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    window.addEventListener('pageshow', function (event) {
        if (event.persisted || (window.performance && window.performance.navigation && window.performance.navigation.type === 2)) {
            window.location.reload();
        }
    });
    </script>
</body>
</html>