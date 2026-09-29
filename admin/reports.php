<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include_once __DIR__ . '/../includes/database.php';
include_once __DIR__ . '/../includes/auth.php';

require_admin('../index.php');

$users_res    = mysqli_query($conn, "SELECT COUNT(*) AS c FROM users");
$total_users  = mysqli_fetch_assoc($users_res)['c'] ?? 0;

$items_res    = mysqli_query($conn, "SELECT COUNT(*) AS c FROM items");
$total_items  = mysqli_fetch_assoc($items_res)['c'] ?? 0;

$active_res   = mysqli_query($conn, "SELECT COUNT(*) AS c FROM items WHERE status = 'active'");
$active_items = mysqli_fetch_assoc($active_res)['c'] ?? 0;

$ended_res    = mysqli_query($conn, "SELECT COUNT(*) AS c FROM items WHERE status = 'ended'");
$ended_items  = mysqli_fetch_assoc($ended_res)['c'] ?? 0;

$bids_res     = mysqli_query($conn, "SELECT COUNT(*) AS c FROM bids");
$total_bids   = mysqli_fetch_assoc($bids_res)['c'] ?? 0;

$revenue_res  = mysqli_query($conn, "SELECT SUM(current_price) AS s FROM items WHERE status = 'ended'");
$total_rev    = mysqli_fetch_assoc($revenue_res)['s'] ?? 0;

$avg_bid_res  = mysqli_query($conn, "SELECT AVG(bid_amount) AS avg FROM bids");
$avg_bid      = mysqli_fetch_assoc($avg_bid_res)['avg'] ?? 0;

// Top sellers
$top_sellers_res = mysqli_query($conn, "
    SELECT u.name, u.email, COUNT(i.id) AS item_count, SUM(i.current_price) AS revenue
    FROM users u
    JOIN items i ON i.seller_id = u.id AND i.status = 'ended'
    GROUP BY u.id
    ORDER BY revenue DESC
    LIMIT 5
");

// Top bidders
$top_bidders_res = mysqli_query($conn, "
    SELECT u.name, u.email, COUNT(b.id) AS bid_count, SUM(b.bid_amount) AS total_bid
    FROM users u
    JOIN bids b ON b.user_id = u.id
    GROUP BY u.id
    ORDER BY bid_count DESC
    LIMIT 5
");

// Top categories
$top_cats_res = mysqli_query($conn, "
    SELECT c.name, COUNT(i.id) AS item_count
    FROM categories c
    LEFT JOIN items i ON i.category_id = c.id
    GROUP BY c.id
    ORDER BY item_count DESC
    LIMIT 5
");

// Recent bids
$recent_bids_res = mysqli_query($conn, "
    SELECT b.bid_amount, b.created_at, u.name AS bidder, i.title AS item_title
    FROM bids b
    LEFT JOIN users u ON u.id = b.user_id
    LEFT JOIN items i ON i.id = b.item_id
    ORDER BY b.id DESC
    LIMIT 10
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-green: rgb(4 120 87);
            --primary-green-hover: rgb(3 98 71);
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
        .stat-card { 
            border: none; 
            border-radius: 12px; 
            box-shadow: 0 4px 6px rgba(0,0,0,0.02); 
            transition: transform 0.2s ease;
        }
        .stat-card:hover {
            transform: translateY(-3px);
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
        <a href="items.php"><i class="fas fa-box-open me-2"></i> Items</a>
        <a href="bids.php"><i class="fas fa-hand-holding-usd me-2"></i> Bids</a>
        
        <div class="nav-category">System</div>
        <a href="reports.php" class="active"><i class="fas fa-chart-line me-2"></i> Reports</a>
        <a href="logout.php" class="text-danger mt-4"><i class="fas fa-sign-out-alt me-2"></i> Logout</a>
    </div>

    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
            <h2 class="fw-bold mb-0" style="color: #111827;"><i class="fas fa-chart-line me-2 text-success"></i>System Reports</h2>
            <span class="text-muted bg-white px-3 py-2 border rounded shadow-sm">Logged in as: <strong class="text-dark"><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Admin'); ?></strong></span>
        </div>

        <!-- Overall Stats -->
        <div class="row g-3 mb-4">
            <div class="col-xl-2 col-md-4 col-sm-6">
                <div class="card stat-card p-3 bg-white text-center">
                    <div class="fs-3 text-primary mb-1"><i class="fas fa-users"></i></div>
                    <h4 class="fw-bold text-dark mb-1"><?php echo number_format($total_users); ?></h4>
                    <div class="text-muted small fw-semibold text-uppercase">Total Users</div>
                </div>
            </div>
            <div class="col-xl-2 col-md-4 col-sm-6">
                <div class="card stat-card p-3 bg-white text-center">
                    <div class="fs-3 text-warning mb-1"><i class="fas fa-box-open"></i></div>
                    <h4 class="fw-bold text-dark mb-1"><?php echo number_format($total_items); ?></h4>
                    <div class="text-muted small fw-semibold text-uppercase">Total Items</div>
                </div>
            </div>
            <div class="col-xl-2 col-md-4 col-sm-6">
                <div class="card stat-card p-3 bg-white text-center">
                    <div class="fs-3 text-success mb-1"><i class="fas fa-circle-check"></i></div>
                    <h4 class="fw-bold text-dark mb-1"><?php echo number_format($active_items); ?></h4>
                    <div class="text-muted small fw-semibold text-uppercase">Active Items</div>
                </div>
            </div>
            <div class="col-xl-2 col-md-4 col-sm-6">
                <div class="card stat-card p-3 bg-white text-center">
                    <div class="fs-3 text-secondary mb-1"><i class="fas fa-flag-checkered"></i></div>
                    <h4 class="fw-bold text-dark mb-1"><?php echo number_format($ended_items); ?></h4>
                    <div class="text-muted small fw-semibold text-uppercase">Ended Items</div>
                </div>
            </div>
            <div class="col-xl-2 col-md-4 col-sm-6">
                <div class="card stat-card p-3 bg-white text-center">
                    <div class="fs-3 text-info mb-1"><i class="fas fa-gavel"></i></div>
                    <h4 class="fw-bold text-dark mb-1"><?php echo number_format($total_bids); ?></h4>
                    <div class="text-muted small fw-semibold text-uppercase">Total Bids</div>
                </div>
            </div>
            <div class="col-xl-2 col-md-4 col-sm-6">
                <div class="card stat-card p-3 bg-white text-center">
                    <div class="fs-3 text-danger mb-1"><i class="fas fa-rupee-sign"></i></div>
                    <h4 class="fw-bold text-dark mb-1">Rs. <?php echo number_format($total_rev, 0); ?></h4>
                    <div class="text-muted small fw-semibold text-uppercase">Revenue</div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <!-- Top Sellers -->
            <div class="col-md-6">
                <div class="card border-0 shadow-sm" style="border-radius: 12px; overflow: hidden;">
                    <div class="card-header bg-white py-3 border-bottom"><h5 class="mb-0 fw-bold text-dark"><i class="fas fa-star text-warning me-2"></i>Top Sellers by Revenue</h5></div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr><th class="ps-3 py-3">Seller</th><th class="py-3">Items Sold</th><th class="pe-3 py-3">Revenue</th></tr>
                                </thead>
                                <tbody>
                                    <?php if ($top_sellers_res && mysqli_num_rows($top_sellers_res) > 0): ?>
                                        <?php while ($row = mysqli_fetch_assoc($top_sellers_res)): ?>
                                        <tr>
                                            <td class="ps-3 fw-semibold text-dark"><?php echo htmlspecialchars($row['name']); ?></td>
                                            <td><span class="badge bg-light text-dark border px-2 py-1"><?php echo $row['item_count']; ?></span></td>
                                            <td class="text-success fw-bold pe-3">Rs. <?php echo number_format($row['revenue'], 2); ?></td>
                                        </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr><td colspan="3" class="text-center text-muted py-4">No data yet.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Top Bidders -->
            <div class="col-md-6">
                <div class="card border-0 shadow-sm" style="border-radius: 12px; overflow: hidden;">
                    <div class="card-header bg-white py-3 border-bottom"><h5 class="mb-0 fw-bold text-dark"><i class="fas fa-trophy text-success me-2"></i>Top Bidders</h5></div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr><th class="ps-3 py-3">Bidder</th><th class="py-3">Total Bids</th><th class="pe-3 py-3">Total Spent</th></tr>
                                </thead>
                                <tbody>
                                    <?php if ($top_bidders_res && mysqli_num_rows($top_bidders_res) > 0): ?>
                                        <?php while ($row = mysqli_fetch_assoc($top_bidders_res)): ?>
                                        <tr>
                                            <td class="ps-3 fw-semibold text-dark"><?php echo htmlspecialchars($row['name']); ?></td>
                                            <td><span class="badge bg-light text-dark border px-2 py-1"><?php echo $row['bid_count']; ?></span></td>
                                            <td class="text-primary fw-bold pe-3">Rs. <?php echo number_format($row['total_bid'], 2); ?></td>
                                        </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr><td colspan="3" class="text-center text-muted py-4">No bids yet.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Top Categories -->
            <div class="col-md-6">
                <div class="card border-0 shadow-sm" style="border-radius: 12px; overflow: hidden;">
                    <div class="card-header bg-white py-3 border-bottom"><h5 class="mb-0 fw-bold text-dark"><i class="fas fa-tags text-info me-2"></i>Top Categories</h5></div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr><th class="ps-3 py-3">Category</th><th class="pe-3 py-3">Items Listed</th></tr>
                                </thead>
                                <tbody>
                                    <?php if ($top_cats_res && mysqli_num_rows($top_cats_res) > 0): ?>
                                        <?php while ($row = mysqli_fetch_assoc($top_cats_res)): ?>
                                        <tr>
                                            <td class="ps-3 fw-semibold text-dark"><?php echo htmlspecialchars($row['name']); ?></td>
                                            <td class="pe-3"><span class="badge bg-light text-dark border px-2 py-1"><?php echo $row['item_count']; ?></span></td>
                                        </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr><td colspan="2" class="text-center text-muted py-4">No data yet.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Bids -->
            <div class="col-md-6">
                <div class="card border-0 shadow-sm" style="border-radius: 12px; overflow: hidden;">
                    <div class="card-header bg-white py-3 border-bottom"><h5 class="mb-0 fw-bold text-dark"><i class="fas fa-clock text-secondary me-2"></i>Recent Bid Activity</h5></div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr><th class="ps-3 py-3">Bidder</th><th class="py-3">Item</th><th class="py-3">Amount</th><th class="pe-3 py-3">Time</th></tr>
                                </thead>
                                <tbody>
                                    <?php if ($recent_bids_res && mysqli_num_rows($recent_bids_res) > 0): ?>
                                        <?php while ($row = mysqli_fetch_assoc($recent_bids_res)): ?>
                                        <tr>
                                            <td class="ps-3 fw-semibold text-dark"><?php echo htmlspecialchars($row['bidder'] ?? 'N/A'); ?></td>
                                            <td class="text-muted"><?php echo htmlspecialchars(substr($row['item_title'] ?? '', 0, 20)); ?></td>
                                            <td class="text-success fw-bold">Rs. <?php echo number_format($row['bid_amount'], 2); ?></td>
                                            <td class="text-muted small pe-3"><?php echo date('d M H:i', strtotime($row['created_at'])); ?></td>
                                        </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr><td colspan="4" class="text-center text-muted py-4">No recent bids.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

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