<?php
/**
 * Admin Dashboard - Geo Auction System
 * File: admin/dashboard.php
 *
 * Implements real-time platform metrics, dynamic visual analytics powered by Chart.js,
 * secure prepared statements, and seamless integration with the existing admin layout.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Prevent caching of protected admin dashboard
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

// -------------------------------------------------------------
// SECURE ADMIN SESSION GUARD
// -------------------------------------------------------------
$is_admin_authenticated = (
    (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) ||
    (isset($_SESSION['user_id']) && !empty($_SESSION['user_id']) && (
        (isset($_SESSION['role']) && strtolower($_SESSION['role']) === 'admin') ||
        (isset($_SESSION['user_role']) && strtolower($_SESSION['user_role']) === 'admin')
    ))
);

if (!$is_admin_authenticated) {
    header("Location: ../index.php");
    exit();
}

include_once __DIR__ . '/../includes/database.php';
include_once __DIR__ . '/../includes/auth.php';

// Establish PDO connection using existing OOP database architecture
try {
    $pdo = Database::getPdo();
} catch (\Throwable $e) {
    die("Database Connection Error: " . htmlspecialchars($e->getMessage()));
}

// -------------------------------------------------------------
// 1. TOP STAT CARDS (Prepared Statements)
// -------------------------------------------------------------
$stmt_users = $pdo->prepare("SELECT COUNT(*) AS c FROM users");
$stmt_users->execute();
$total_users = (int)$stmt_users->fetchColumn();

$stmt_active = $pdo->prepare("SELECT COUNT(*) AS c FROM items WHERE status = 'active' AND (is_deleted = 0 OR is_deleted IS NULL)");
$stmt_active->execute();
$active_auctions = (int)$stmt_active->fetchColumn();

$stmt_bids = $pdo->prepare("SELECT COUNT(*) AS c FROM bids");
$stmt_bids->execute();
$total_bids = (int)$stmt_bids->fetchColumn();

$stmt_rev = $pdo->prepare("SELECT SUM(current_price) AS s FROM items WHERE status IN ('ended', 'sold') AND (is_deleted = 0 OR is_deleted IS NULL)");
$stmt_rev->execute();
$total_revenue = 'Rs. ' . number_format((float)($stmt_rev->fetchColumn() ?: 0), 2);

$stmt_total_items = $pdo->prepare("SELECT COUNT(*) AS c FROM items WHERE (is_deleted = 0 OR is_deleted IS NULL)");
$stmt_total_items->execute();
$total_auctions_count = (int)$stmt_total_items->fetchColumn();

// -------------------------------------------------------------
// 2. ANALYTICS: 1. AUCTION ACTIVITY OVER TIME
// -------------------------------------------------------------
$stmt_auction_act = $pdo->prepare("
    SELECT 
        DATE(created_at) as act_date,
        DATE_FORMAT(created_at, '%d %b %Y') as date_label,
        COUNT(*) as auction_count
    FROM items
    WHERE (is_deleted = 0 OR is_deleted IS NULL)
    GROUP BY DATE(created_at)
    ORDER BY act_date ASC
    LIMIT 30
");
$stmt_auction_act->execute();
$raw_auction_act = $stmt_auction_act->fetchAll();

$auction_chart_labels = [];
$auction_chart_data   = [];

if (!empty($raw_auction_act)) {
    foreach ($raw_auction_act as $row) {
        $auction_chart_labels[] = $row['date_label'];
        $auction_chart_data[]   = (int)$row['auction_count'];
    }
} else {
    $auction_chart_labels = [date('d M Y')];
    $auction_chart_data   = [0];
}

// -------------------------------------------------------------
// 3. ANALYTICS: 2. BIDS ACTIVITY OVER TIME
// -------------------------------------------------------------
$stmt_bids_act = $pdo->prepare("
    SELECT 
        DATE(created_at) as act_date,
        DATE_FORMAT(created_at, '%d %b %Y') as date_label,
        COUNT(*) as bid_count
    FROM bids
    GROUP BY DATE(created_at)
    ORDER BY act_date ASC
    LIMIT 30
");
$stmt_bids_act->execute();
$raw_bids_act = $stmt_bids_act->fetchAll();

$bids_chart_labels = [];
$bids_chart_data   = [];

if (!empty($raw_bids_act)) {
    foreach ($raw_bids_act as $row) {
        $bids_chart_labels[] = $row['date_label'];
        $bids_chart_data[]   = (int)$row['bid_count'];
    }
} else {
    // If no bids recorded yet, align with auction timeline or today's date
    $bids_chart_labels = !empty($auction_chart_labels) ? $auction_chart_labels : [date('d M Y')];
    $bids_chart_data   = array_fill(0, count($bids_chart_labels), 0);
}

// -------------------------------------------------------------
// 4. ANALYTICS: 3. USER STATISTICS (USERS BY ROLE)
// -------------------------------------------------------------
$stmt_user_roles = $pdo->prepare("
    SELECT 
        LOWER(role) as role_name,
        COUNT(*) as user_count
    FROM users
    GROUP BY role
    ORDER BY user_count DESC
");
$stmt_user_roles->execute();
$raw_user_roles = $stmt_user_roles->fetchAll();

$user_role_labels = [];
$user_role_counts = [];
$user_role_colors = [];

$role_color_map = [
    'admin'  => '#dc2626',
    'seller' => 'rgb(4, 120, 87)',
    'buyer'  => '#2563eb',
];

foreach ($raw_user_roles as $row) {
    $rName = strtolower(trim($row['role_name'] ?? 'buyer'));
    $user_role_labels[] = ucfirst($rName);
    $user_role_counts[] = (int)$row['user_count'];
    $user_role_colors[] = $role_color_map[$rName] ?? '#64748b';
}

// -------------------------------------------------------------
// 5. ANALYTICS: 4. AUCTION STATUS STATISTICS (ACTUAL DATABASE VALUES)
// -------------------------------------------------------------
$stmt_item_status = $pdo->prepare("
    SELECT 
        LOWER(status) as status_name,
        COUNT(*) as status_count
    FROM items
    WHERE (is_deleted = 0 OR is_deleted IS NULL)
    GROUP BY status
    ORDER BY status_count DESC
");
$stmt_item_status->execute();
$raw_item_status = $stmt_item_status->fetchAll();

$auction_status_labels = [];
$auction_status_counts = [];
$auction_status_colors = [];

$status_color_map = [
    'active'    => 'rgb(4, 120, 87)',
    'ended'     => '#64748b',
    'sold'      => '#2563eb',
    'pending'   => '#f59e0b',
    'cancelled' => '#dc2626',
    'paused'    => '#8b5cf6',
];

foreach ($raw_item_status as $row) {
    $sName = strtolower(trim($row['status_name'] ?? 'active'));
    $auction_status_labels[] = ucfirst($sName);
    $auction_status_counts[] = (int)$row['status_count'];
    $auction_status_colors[] = $status_color_map[$sName] ?? '#0d9488';
}

// -------------------------------------------------------------
// 6. RECENT AUCTION ITEMS TABLE (Prepared Statement)
// -------------------------------------------------------------
$stmt_recent = $pdo->prepare("
    SELECT i.*, 
        (SELECT COUNT(*) FROM bids b WHERE b.item_id = i.id) AS bid_count
    FROM items i 
    WHERE (i.is_deleted = 0 OR i.is_deleted IS NULL)
    ORDER BY i.id DESC 
    LIMIT 5
");
$stmt_recent->execute();
$recent_items = $stmt_recent->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Geo Auction System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Chart.js CDN for dynamic visual analytics -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>
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
        .analytics-card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            background: #ffffff;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .analytics-card:hover {
            box-shadow: 0 8px 15px rgba(0,0,0,0.07);
        }
        .chart-container-box {
            position: relative;
            height: 255px;
            width: 100%;
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
    <?php include_once __DIR__ . '/../includes/components/loading_spinner_overlay.php'; ?>
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
        <a href="setting.php"><i class="fas fa-database me-2"></i> Database & Backup</a>
        <a href="reports.php"><i class="fas fa-chart-line me-2"></i> Reports</a>
        <a href="logout.php" class="text-danger mt-4"><i class="fas fa-sign-out-alt me-2"></i> Logout</a>
    </div>
    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
            <div>
                <h2 class="fw-bold mb-1" style="color: #111827;">Admin Dashboard Overview</h2>
                <p class="text-muted mb-0 small">Real-time marketplace monitoring, inventory and bidding controls</p>
            </div>
            <div class="d-flex align-items-center gap-2">
                <a href="../user/add_items.php" class="btn btn-success px-3 py-2 fw-semibold d-inline-flex align-items-center gap-2 shadow-sm" style="background-color: rgb(4 120 87); border-color: rgb(4 120 87);">
                    <i class="fas fa-plus-circle"></i> + Add New Item
                </a>
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
        </div>

        <!-- ── Top Metric Summary Cards ── -->
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

        <!-- ── Analytics & Statistics Section (Chart.js Visualizations) ── -->
        <div class="d-flex justify-content-between align-items-center mb-3 mt-2 flex-wrap gap-2">
            <div>
                <h5 class="fw-bold mb-0 text-dark">
                    <i class="fas fa-chart-line text-success me-2"></i>Analytics & Statistics
                </h5>
                <p class="text-muted small mb-0">Dynamic performance metrics derived from live auction and user activity</p>
            </div>
            <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2">
                <i class="fas fa-circle-dot me-1"></i> Live Database Data
            </span>
        </div>

        <div class="row g-4 mb-4">
            <!-- 1. Auction Activity Chart -->
            <div class="col-lg-6 col-12">
                <div class="card analytics-card p-4 h-100">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h6 class="fw-bold text-dark mb-0">Auction Activity</h6>
                            <small class="text-muted">Auctions created over time</small>
                        </div>
                        <span class="badge bg-light text-dark border">
                            Total: <?php echo number_format($total_auctions_count); ?> lots
                        </span>
                    </div>
                    <div class="chart-container-box">
                        <canvas id="auctionActivityChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- 2. Bids Activity Chart -->
            <div class="col-lg-6 col-12">
                <div class="card analytics-card p-4 h-100">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h6 class="fw-bold text-dark mb-0">Bids Activity</h6>
                            <small class="text-muted">Number of bids recorded over time</small>
                        </div>
                        <span class="badge bg-light text-dark border">
                            Total: <?php echo number_format($total_bids); ?> bids
                        </span>
                    </div>
                    <div class="chart-container-box">
                        <canvas id="bidsActivityChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- 3. User Statistics Chart (Roles) -->
            <div class="col-lg-6 col-12">
                <div class="card analytics-card p-4 h-100">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h6 class="fw-bold text-dark mb-0">User Role Distribution</h6>
                            <small class="text-muted">Breakdown by registered roles (Admin, Seller, Buyer)</small>
                        </div>
                        <span class="badge bg-light text-dark border">
                            Total: <?php echo number_format($total_users); ?> users
                        </span>
                    </div>
                    <div class="chart-container-box d-flex align-items-center justify-content-center">
                        <canvas id="userRolesChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- 4. Auction Status Statistics Chart -->
            <div class="col-lg-6 col-12">
                <div class="card analytics-card p-4 h-100">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h6 class="fw-bold text-dark mb-0">Auction Status Breakdown</h6>
                            <small class="text-muted">Distribution by actual database statuses</small>
                        </div>
                        <span class="badge bg-light text-dark border">
                            Total: <?php echo number_format($total_auctions_count); ?> lots
                        </span>
                    </div>
                    <div class="chart-container-box d-flex align-items-center justify-content-center">
                        <canvas id="auctionStatusChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── Recent Auction Items & Quick Actions ── -->
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
                                <?php if (!empty($recent_items)): ?>
                                    <?php foreach ($recent_items as $item): ?>
                                    <tr>
                                        <td>#<?php echo (int)$item['id']; ?></td>
                                        <td>
                                            <a href="../item.php?id=<?php echo (int)$item['id']; ?>" class="text-dark fw-semibold text-decoration-none">
                                                <?php echo htmlspecialchars($item['title'] ?? ''); ?>
                                            </a>
                                        </td>
                                        <td class="fw-bold text-success">Rs. <?php echo number_format((float)($item['current_price'] ?? $item['starting_price']), 2); ?></td>
                                        <td><?php echo (int)$item['bid_count']; ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo ($item['status'] === 'active') ? 'success' : 'secondary'; ?>">
                                                <?php echo ucfirst(htmlspecialchars($item['status'] ?? '')); ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
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

    <!-- ── Dynamic Chart.js Rendering Engine ── -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof Chart === 'undefined') {
            console.error('Chart.js library failed to load.');
            return;
        }

        const primaryGreen = 'rgb(4, 120, 87)';
        const primaryGreenLight = 'rgba(4, 120, 87, 0.15)';
        const axisColor = '#64748b';
        const gridColor = 'rgba(0, 0, 0, 0.05)';

        // 1. Auction Activity Chart (Over Time)
        const ctxAuction = document.getElementById('auctionActivityChart');
        if (ctxAuction) {
            new Chart(ctxAuction, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode($auction_chart_labels, JSON_UNESCAPED_UNICODE); ?>,
                    datasets: [{
                        label: 'Auctions Created',
                        data: <?php echo json_encode($auction_chart_data); ?>,
                        borderColor: primaryGreen,
                        backgroundColor: primaryGreenLight,
                        borderWidth: 2.5,
                        pointBackgroundColor: primaryGreen,
                        pointBorderColor: '#ffffff',
                        pointBorderWidth: 2,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        tension: 0.35,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return ' ' + context.parsed.y + ' auction(s) created';
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: { display: false },
                            ticks: { color: axisColor, font: { size: 11, weight: 500 } }
                        },
                        y: {
                            beginAtZero: true,
                            grid: { color: gridColor },
                            ticks: {
                                color: axisColor,
                                precision: 0,
                                stepSize: 1
                            }
                        }
                    }
                }
            });
        }

        // 2. Bids Activity Chart (Over Time)
        const ctxBids = document.getElementById('bidsActivityChart');
        if (ctxBids) {
            new Chart(ctxBids, {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode($bids_chart_labels, JSON_UNESCAPED_UNICODE); ?>,
                    datasets: [{
                        label: 'Bids Placed',
                        data: <?php echo json_encode($bids_chart_data); ?>,
                        backgroundColor: 'rgba(245, 158, 11, 0.85)',
                        borderColor: 'rgb(217, 119, 6)',
                        borderWidth: 1.5,
                        borderRadius: 6,
                        barPercentage: 0.65,
                        categoryPercentage: 0.7
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return ' ' + context.parsed.y + ' bid(s) recorded';
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: { display: false },
                            ticks: { color: axisColor, font: { size: 11, weight: 500 } }
                        },
                        y: {
                            beginAtZero: true,
                            grid: { color: gridColor },
                            ticks: {
                                color: axisColor,
                                precision: 0,
                                stepSize: 1
                            }
                        }
                    }
                }
            });
        }

        // 3. User Roles Chart
        const ctxRoles = document.getElementById('userRolesChart');
        if (ctxRoles) {
            new Chart(ctxRoles, {
                type: 'doughnut',
                data: {
                    labels: <?php echo json_encode($user_role_labels, JSON_UNESCAPED_UNICODE); ?>,
                    datasets: [{
                        data: <?php echo json_encode($user_role_counts); ?>,
                        backgroundColor: <?php echo json_encode($user_role_colors); ?>,
                        borderWidth: 2,
                        borderColor: '#ffffff',
                        hoverOffset: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '66%',
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                color: axisColor,
                                padding: 14,
                                font: { size: 12, weight: 600 },
                                usePointStyle: true,
                                pointStyle: 'circle'
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const val = context.parsed;
                                    const pct = total > 0 ? ((val / total) * 100).toFixed(1) : 0;
                                    return ` ${context.label}: ${val} (${pct}%)`;
                                }
                            }
                        }
                    }
                }
            });
        }

        // 4. Auction Status Statistics Chart
        const ctxStatus = document.getElementById('auctionStatusChart');
        if (ctxStatus) {
            new Chart(ctxStatus, {
                type: 'doughnut',
                data: {
                    labels: <?php echo json_encode($auction_status_labels, JSON_UNESCAPED_UNICODE); ?>,
                    datasets: [{
                        data: <?php echo json_encode($auction_status_counts); ?>,
                        backgroundColor: <?php echo json_encode($auction_status_colors); ?>,
                        borderWidth: 2,
                        borderColor: '#ffffff',
                        hoverOffset: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '66%',
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                color: axisColor,
                                padding: 14,
                                font: { size: 12, weight: 600 },
                                usePointStyle: true,
                                pointStyle: 'circle'
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const val = context.parsed;
                                    const pct = total > 0 ? ((val / total) * 100).toFixed(1) : 0;
                                    return ` ${context.label}: ${val} lots (${pct}%)`;
                                }
                            }
                        }
                    }
                }
            });
        }
    });

    window.addEventListener('pageshow', function (event) {
        if (event.persisted || (window.performance && window.performance.navigation && window.performance.navigation.type === 2)) {
            window.location.reload();
        }
    });

    // Intercept all logout clicks to display the loading spinner immediately
    document.addEventListener('click', function(e) {
        const logoutLink = e.target.closest('a[href*="logout"]');
        if (logoutLink && !logoutLink.hasAttribute('data-no-loader')) {
            const targetHref = logoutLink.getAttribute('href');
            if (targetHref && targetHref !== '#' && !targetHref.startsWith('javascript:')) {
                e.preventDefault();
                if (typeof showLoader === 'function') {
                    showLoader('Logging out, please wait...');
                }
                setTimeout(function() {
                    window.location.href = targetHref;
                }, 200);
            }
        }
    });
    </script>
</body>
</html>