<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include_once __DIR__ . '/../includes/database.php';
include_once __DIR__ . '/../includes/auth.php';

// Filter by item
$item_filter = isset($_GET['item_id']) ? (int)$_GET['item_id'] : 0;
$search      = isset($_GET['search']) ? mysqli_real_escape_string($conn, trim($_GET['search'])) : '';

$where_parts = [];
if ($item_filter) $where_parts[] = "b.item_id = $item_filter";
if ($search)      $where_parts[] = "(u.name LIKE '%$search%' OR i.title LIKE '%$search%')";
$where = count($where_parts) ? 'WHERE ' . implode(' AND ', $where_parts) : '';

$bids_res = mysqli_query($conn, "
    SELECT b.*, u.name AS bidder_name, u.email AS bidder_email, i.title AS item_title
    FROM bids b
    LEFT JOIN users u ON u.id = b.user_id
    LEFT JOIN items i ON i.id = b.item_id
    $where
    ORDER BY b.id DESC
    LIMIT 500
");

// Stats
$total_bids_res = mysqli_query($conn, "SELECT COUNT(*) AS c FROM bids");
$total_bids = mysqli_fetch_assoc($total_bids_res)['c'] ?? 0;

$total_amount_res = mysqli_query($conn, "SELECT SUM(bid_amount) AS s FROM bids");
$total_amount = mysqli_fetch_assoc($total_amount_res)['s'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Bids - Admin Panel</title>
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
        <a href="bids.php" class="active"><i class="fas fa-hand-holding-usd me-2"></i> Bids</a>
        
        <div class="nav-category">System</div>
        <a href="reports.php"><i class="fas fa-chart-line me-2"></i> Reports</a>
        <a href="logout.php" class="text-danger mt-4"><i class="fas fa-sign-out-alt me-2"></i> Logout</a>
    </div>

    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
            <h2 class="fw-bold mb-0" style="color: #111827;"><i class="fas fa-hand-holding-usd me-2 text-success"></i>All Bids</h2>
            <span class="text-muted bg-white px-3 py-2 border rounded shadow-sm">Logged in as: <strong class="text-dark"><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Admin'); ?></strong></span>
        </div>

        <!-- Stats Row -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card border-0 shadow-sm p-3" style="border-radius: 12px;">
                    <h6 class="text-muted small text-uppercase fw-semibold mb-1">Total Bids</h6>
                    <h3 class="fw-bold text-dark mb-0"><?php echo number_format($total_bids); ?></h3>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm p-3" style="border-radius: 12px;">
                    <h6 class="text-muted small text-uppercase fw-semibold mb-1">Total Bid Volume</h6>
                    <h3 class="fw-bold text-success mb-0">Rs. <?php echo number_format($total_amount, 2); ?></h3>
                </div>
            </div>
        </div>

        <!-- Search / Filter -->
        <div class="card border-0 shadow-sm mb-4" style="border-radius: 12px;">
            <div class="card-body p-3">
                <form method="GET" class="d-flex gap-2 flex-wrap align-items-center">
                    <div class="input-group" style="max-width: 350px;">
                        <span class="input-group-text bg-white border-end-0 text-muted"><i class="fas fa-search"></i></span>
                        <input type="text" name="search" class="form-control border-start-0 ps-0 shadow-none" placeholder="Search by bidder or item..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <?php if ($item_filter): ?>
                        <input type="hidden" name="item_id" value="<?php echo $item_filter; ?>">
                    <?php endif; ?>
                    <button type="submit" class="btn text-white px-4 fw-semibold" style="background-color: var(--primary-green);">Search</button>
                    <a href="bids.php" class="btn btn-outline-secondary">Reset</a>
                </form>
                <?php if ($item_filter): ?>
                    <div class="mt-2 text-muted"><small><i class="fas fa-filter me-1"></i>Filtered by item ID: #<?php echo $item_filter; ?></small></div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Bids Table -->
        <div class="card border-0 shadow-sm" style="border-radius: 12px; overflow: hidden;">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="py-3 ps-3">#ID</th>
                                <th class="py-3">Item</th>
                                <th class="py-3">Bidder</th>
                                <th class="py-3">Email</th>
                                <th class="py-3">Amount</th>
                                <th class="py-3">Winning?</th>
                                <th class="py-3 pe-3">Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($bids_res && mysqli_num_rows($bids_res) > 0): ?>
                                <?php while ($bid = mysqli_fetch_assoc($bids_res)): ?>
                                <tr>
                                    <td class="ps-3 fw-semibold">#<?php echo $bid['id']; ?></td>
                                    <td>
                                        <a href="../item.php?id=<?php echo $bid['item_id']; ?>" target="_blank" class="text-decoration-none fw-semibold text-dark">
                                            <?php echo htmlspecialchars(substr($bid['item_title'] ?? 'N/A', 0, 30)); ?>
                                        </a>
                                    </td>
                                    <td class="fw-bold text-dark"><?php echo htmlspecialchars($bid['bidder_name'] ?? 'N/A'); ?></td>
                                    <td class="text-muted"><?php echo htmlspecialchars($bid['bidder_email'] ?? '-'); ?></td>
                                    <td class="fw-bold text-success">Rs. <?php echo number_format($bid['bid_amount'] ?? 0, 2); ?></td>
                                    <td>
                                        <?php if (!empty($bid['is_winning'])): ?>
                                            <span class="badge bg-success bg-opacity-10 text-success px-2 py-1"><i class="fas fa-trophy me-1"></i>Yes</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary bg-opacity-10 text-secondary px-2 py-1">No</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-muted small pe-3"><?php echo date('d M Y H:i', strtotime($bid['created_at'])); ?></td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="7" class="text-center py-4 text-muted">No bids found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>