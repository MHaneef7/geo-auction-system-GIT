<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include_once __DIR__ . '/../includes/database.php';
include_once __DIR__ . '/../includes/auth.php';

$msg = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'approve') {
        $iid = (int)$_POST['item_id'];
        mysqli_query($conn, "UPDATE items SET status = 'active' WHERE id = $iid");
        $msg = 'Item approved and set to active successfully.';
    } elseif ($action === 'reject') {
        $iid = (int)$_POST['item_id'];
        mysqli_query($conn, "UPDATE items SET status = 'rejected' WHERE id = $iid");
        $msg = 'Item rejected successfully.';
    } elseif ($action === 'end') {
        $iid = (int)$_POST['item_id'];
        mysqli_query($conn, "UPDATE items SET status = 'ended' WHERE id = $iid");
        $msg = 'Auction ended successfully.';
    } elseif ($action === 'delete') {
        $iid = (int)$_POST['item_id'];
        mysqli_query($conn, "DELETE FROM bids WHERE item_id = $iid");
        mysqli_query($conn, "DELETE FROM items WHERE id = $iid");
        $msg = 'Item deleted successfully.';
    }
}

$filter = isset($_GET['status']) ? mysqli_real_escape_string($conn, $_GET['status']) : '';
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, trim($_GET['search'])) : '';

$where_parts = [];
if ($filter) $where_parts[] = "i.status = '$filter'";
if ($search) $where_parts[] = "(i.title LIKE '%$search%' OR u.name LIKE '%$search%')";
$where = count($where_parts) ? 'WHERE ' . implode(' AND ', $where_parts) : '';

$items_res = mysqli_query($conn, "
    SELECT i.*, u.name AS seller_name, c.name AS category_name,
           (SELECT COUNT(*) FROM bids b WHERE b.item_id = i.id) AS bid_count
    FROM items i
    LEFT JOIN users u ON u.id = i.seller_id
    LEFT JOIN categories c ON c.id = i.category_id
    $where
    ORDER BY i.id DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Items - Admin Panel</title>
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
        <a href="items.php" class="active"><i class="fas fa-box-open me-2"></i> Items</a>
        <a href="bids.php"><i class="fas fa-hand-holding-usd me-2"></i> Bids</a>
        
        <div class="nav-category">System</div>
        <a href="reports.php"><i class="fas fa-chart-line me-2"></i> Reports</a>
        <a href="logout.php" class="text-danger mt-4"><i class="fas fa-sign-out-alt me-2"></i> Logout</a>
    </div>

    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
            <h2 class="fw-bold mb-0" style="color: #111827;"><i class="fas fa-box-open me-2 text-success"></i>Manage Items</h2>
            <span class="text-muted bg-white px-3 py-2 border rounded shadow-sm">Logged in as: <strong class="text-dark"><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Admin'); ?></strong></span>
        </div>

        <?php if ($msg): ?>
            <div class="alert alert-success alert-dismissible fade show shadow-sm">
                <i class="fas fa-circle-check me-2"></i><?php echo htmlspecialchars($msg); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Filter & Search -->
        <div class="card border-0 shadow-sm mb-4" style="border-radius: 12px;">
            <div class="card-body p-3">
                <form method="GET" class="row g-2 align-items-center">
                    <div class="col-md-5">
                        <div class="input-group">
                            <span class="input-group-text bg-white border-end-0 text-muted"><i class="fas fa-search"></i></span>
                            <input type="text" name="search" class="form-control border-start-0 ps-0 shadow-none" placeholder="Search by title or seller..." value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <select name="status" class="form-select shadow-none">
                            <option value="">All Statuses</option>
                            <option value="pending" <?php echo $filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="active" <?php echo $filter === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="ended" <?php echo $filter === 'ended' ? 'selected' : ''; ?>>Ended</option>
                            <option value="rejected" <?php echo $filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex gap-2">
                        <button type="submit" class="btn text-white w-100 fw-semibold" style="background-color: var(--primary-green);"><i class="fas fa-filter me-1"></i>Filter</button>
                        <a href="items.php" class="btn btn-outline-secondary">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="card border-0 shadow-sm" style="border-radius: 12px; overflow: hidden;">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="py-3 ps-3">#ID</th>
                                <th class="py-3">Title</th>
                                <th class="py-3">Seller</th>
                                <th class="py-3">Category</th>
                                <th class="py-3">Start Price</th>
                                <th class="py-3">Current Price</th>
                                <th class="py-3">Bids</th>
                                <th class="py-3">Status</th>
                                <th class="py-3">Ends</th>
                                <th class="py-3 text-end pe-3">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($items_res && mysqli_num_rows($items_res) > 0): ?>
                                <?php while ($item = mysqli_fetch_assoc($items_res)): ?>
                                <?php
                                $status_badges = [
                                    'active'   => 'bg-success bg-opacity-10 text-success',
                                    'pending'  => 'bg-warning bg-opacity-10 text-warning text-dark',
                                    'ended'    => 'bg-secondary bg-opacity-10 text-secondary',
                                    'rejected' => 'bg-danger bg-opacity-10 text-danger',
                                ];
                                $badge = $status_badges[$item['status']] ?? 'bg-secondary bg-opacity-10 text-secondary';
                                ?>
                                <tr>
                                    <td class="ps-3 fw-semibold">#<?php echo $item['id']; ?></td>
                                    <td>
                                        <a href="../item.php?id=<?php echo $item['id']; ?>" target="_blank" class="text-decoration-none fw-semibold text-dark">
                                            <?php echo htmlspecialchars(substr($item['title'], 0, 30)) . (strlen($item['title']) > 30 ? '...' : ''); ?>
                                        </a>
                                    </td>
                                    <td class="text-muted"><?php echo htmlspecialchars($item['seller_name'] ?? 'N/A'); ?></td>
                                    <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($item['category_name'] ?? 'None'); ?></span></td>
                                    <td class="text-muted">Rs. <?php echo number_format($item['starting_price'], 2); ?></td>
                                    <td class="fw-bold text-success">Rs. <?php echo number_format($item['current_price'] ?? $item['starting_price'], 2); ?></td>
                                    <td><span class="badge bg-info bg-opacity-10 text-info px-2 py-1"><?php echo $item['bid_count']; ?></span></td>
                                    <td><span class="badge <?php echo $badge; ?> px-2 py-1"><?php echo ucfirst($item['status']); ?></span></td>
                                    <td class="text-muted small"><?php echo $item['end_time'] ? date('d M Y', strtotime($item['end_time'])) : 'N/A'; ?></td>
                                    <td class="text-end pe-3">
                                        <div class="d-flex gap-1 justify-content-end">
                                            <?php if ($item['status'] === 'pending'): ?>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="action" value="approve">
                                                <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-success" title="Approve Item"><i class="fas fa-check"></i></button>
                                            </form>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="action" value="reject">
                                                <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-warning" title="Reject Item"><i class="fas fa-times"></i></button>
                                            </form>
                                            <?php endif; ?>
                                            <?php if ($item['status'] === 'active'): ?>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="action" value="end">
                                                <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-secondary" title="End Auction"><i class="fas fa-stop"></i></button>
                                            </form>
                                            <?php endif; ?>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this item and all its bids?');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete Item"><i class="fas fa-trash"></i></button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="10" class="text-center py-4 text-muted">No items found.</td></tr>
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