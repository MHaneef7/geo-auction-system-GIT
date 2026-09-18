<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include_once __DIR__ . ('/../includes/database.php');
include_once __DIR__ . ('/../includes/auth.php');

// require_admin('../index.php');

// Real database statistics (non-prepared procedural queries)
$total_users_res = mysqli_query($conn, "SELECT COUNT(*) AS c FROM users");
$total_users = ($total_users_res) ? mysqli_fetch_assoc($total_users_res)['c'] : 0;

$active_auctions_res = mysqli_query($conn, "SELECT COUNT(*) AS c FROM items WHERE status = 'active'");
$active_auctions = ($active_auctions_res) ? mysqli_fetch_assoc($active_auctions_res)['c'] : 0;

$total_bids_res = mysqli_query($conn, "SELECT COUNT(*) AS c FROM bids");
$total_bids = ($total_bids_res) ? mysqli_fetch_assoc($total_bids_res)['c'] : 0;

$revenue_res = mysqli_query($conn, "SELECT SUM(current_price) AS s FROM items WHERE status = 'ended'");
$rev_row = ($revenue_res) ? mysqli_fetch_assoc($revenue_res) : [];
$total_revenue = 'Rs. ' . number_format((float)($rev_row['s'] ?? 0), 2);

$recent_items_res = mysqli_query($conn, "SELECT i.*, 
    (SELECT COUNT(*) FROM bids b WHERE b.item_id = i.id) AS bid_count
    FROM items i ORDER BY i.id DESC LIMIT 5");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Geo Auction System</title>
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
        .card-stat { 
            border: none; 
            border-radius: 12px; 
            box-shadow: 0 4px 6px rgba(0,0,0,0.05); 
            transition: transform 0.2s ease;
        }
        .card-stat:hover {
            transform: translateY(-2px);
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
        <a href="dashboard.php" class="active"><i class="fas fa-tachometer-alt me-2"></i> Dashboard</a>
        <a href="../user/dashboard.php"><i class="fas fa-house me-2"></i> User Portal</a>
        
        <div class="nav-category">Management</div>
        <a href="users.php"><i class="fas fa-users me-2"></i> Users</a>
        <a href="categories.php"><i class="fas fa-tags me-2"></i> Categories</a>
        <a href="items.php"><i class="fas fa-box-open me-2"></i> Items</a>
        <a href="bids.php"><i class="fas fa-hand-holding-usd me-2"></i> Bids</a>
        
        <div class="nav-category">System</div>
        <a href="reports.php"><i class="fas fa-chart-line me-2"></i> Reports</a>
        <a href="logout.php" class="text-danger mt-4"><i class="fas fa-sign-out-alt me-2"></i> Logout</a>
    </div>

    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
            <h2 class="fw-bold mb-0" style="color: #111827;">Admin Dashboard Overview</h2>
            <div class="dropdown">
                <button class="btn btn-light border dropdown-toggle px-3 py-2" type="button" data-bs-toggle="dropdown">
                    <i class="fas fa-user-circle me-1 text-success"></i> <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Administrator'); ?>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="reports.php">Reports</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="logout.php">Logout</a></li>
                </ul>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="card card-stat p-3 bg-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1" style="font-size: 13px;">Total Users</h6>
                            <h3 class="fw-bold mb-0 text-dark"><?php echo number_format($total_users); ?></h3>
                        </div>
                        <div class="fs-1 text-primary"><i class="fas fa-users"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card card-stat p-3 bg-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1" style="font-size: 13px;">Active Auctions</h6>
                            <h3 class="fw-bold mb-0 text-dark"><?php echo number_format($active_auctions); ?></h3>
                        </div>
                        <div class="fs-1 text-success"><i class="fas fa-gavel"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card card-stat p-3 bg-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1" style="font-size: 13px;">Total Bids</h6>
                            <h3 class="fw-bold mb-0 text-dark"><?php echo number_format($total_bids); ?></h3>
                        </div>
                        <div class="fs-1 text-warning"><i class="fas fa-hand-holding-usd"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card card-stat p-3 bg-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1" style="font-size: 13px;">Total Revenue</h6>
                            <h3 class="fw-bold mb-0 text-dark" style="font-size: 1.5rem;"><?php echo $total_revenue; ?></h3>
                        </div>
                        <div class="fs-1 text-danger"><i class="fas fa-wallet"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-md-8">
                <div class="card border-0 shadow-sm p-4 bg-white" style="border-radius: 12px;">
                    <h5 class="mb-3 fw-bold text-dark">Recent Auction Items</h5>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>ID</th>
                                    <th>Item Title</th>
                                    <th>Current Price</th>
                                    <th>Bids</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($recent_items_res && mysqli_num_rows($recent_items_res) > 0): ?>
                                    <?php while ($item = mysqli_fetch_assoc($recent_items_res)): ?>
                                    <tr>
                                        <td>#<?php echo $item['id']; ?></td>
                                        <td>
                                            <a href="../item.php?id=<?php echo $item['id']; ?>" class="text-dark fw-semibold text-decoration-none">
                                                <?php echo htmlspecialchars($item['title']); ?>
                                            </a>
                                        </td>
                                        <td class="fw-bold text-success">Rs. <?php echo number_format($item['current_price'] ?? $item['starting_price'], 2); ?></td>
                                        <td><?php echo $item['bid_count']; ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo ($item['status'] === 'active') ? 'success' : 'secondary'; ?>">
                                                <?php echo ucfirst($item['status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr><td colspan="5" class="text-center text-muted py-3">No recent auction items found.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm p-4 bg-white" style="border-radius: 12px;">
                    <h5 class="mb-3 fw-bold text-dark">Quick Actions</h5>
                    <div class="d-grid gap-2">
                        <a href="items.php" class="btn btn-outline-primary text-start py-2"><i class="fas fa-box-open me-2"></i> Manage Items</a>
                        <a href="users.php" class="btn btn-outline-success text-start py-2"><i class="fas fa-users me-2"></i> Manage Users</a>
                        <a href="categories.php" class="btn btn-outline-warning text-start py-2"><i class="fas fa-tags me-2"></i> Manage Categories</a>
                        <a href="reports.php" class="btn btn-outline-dark text-start py-2"><i class="fas fa-chart-line me-2"></i> View System Reports</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>