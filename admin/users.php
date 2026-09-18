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

    if ($action === 'toggle_status') {
        $uid = (int)$_POST['user_id'];
        $new_status = $_POST['new_status'];
        $new_status = ($new_status === 'active') ? 'active' : 'banned';
        mysqli_query($conn, "UPDATE users SET status = '$new_status' WHERE id = $uid");
        $msg = 'User status updated successfully.';
    } elseif ($action === 'delete') {
        $uid = (int)$_POST['user_id'];
        mysqli_query($conn, "DELETE FROM users WHERE id = $uid AND role != 'admin'");
        $msg = 'User deleted successfully.';
    } elseif ($action === 'change_role') {
        $uid = (int)$_POST['user_id'];
        $role = ($_POST['role'] === 'admin') ? 'admin' : 'user';
        mysqli_query($conn, "UPDATE users SET role = '$role' WHERE id = $uid");
        $msg = 'User role updated successfully.';
    }
}

// Search / filter
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, trim($_GET['search'])) : '';
$where = $search ? "WHERE name LIKE '%$search%' OR email LIKE '%$search%'" : '';

$users_res = mysqli_query($conn, "SELECT * FROM users $where ORDER BY id DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - Admin Panel</title>
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
        <a href="users.php" class="active"><i class="fas fa-users me-2"></i> Users</a>
        <a href="categories.php"><i class="fas fa-tags me-2"></i> Categories</a>
        <a href="items.php"><i class="fas fa-box-open me-2"></i> Items</a>
        <a href="bids.php"><i class="fas fa-hand-holding-usd me-2"></i> Bids</a>
        
        <div class="nav-category">System</div>
        <a href="reports.php"><i class="fas fa-chart-line me-2"></i> Reports</a>
        <a href="logout.php" class="text-danger mt-4"><i class="fas fa-sign-out-alt me-2"></i> Logout</a>
    </div>

    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
            <h2 class="fw-bold mb-0" style="color: #111827;"><i class="fas fa-users me-2 text-success"></i>Manage Users</h2>
            <span class="text-muted bg-white px-3 py-2 border rounded shadow-sm">Logged in as: <strong class="text-dark"><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Admin'); ?></strong></span>
        </div>

        <?php if ($msg): ?>
            <div class="alert alert-success alert-dismissible fade show shadow-sm">
                <i class="fas fa-circle-check me-2"></i><?php echo htmlspecialchars($msg); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Search Bar -->
        <div class="card border-0 shadow-sm mb-4" style="border-radius: 12px;">
            <div class="card-body p-3">
                <form method="GET" class="d-flex gap-2 align-items-center">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0 text-muted"><i class="fas fa-search"></i></span>
                        <input type="text" name="search" class="form-control border-start-0 ps-0 shadow-none" placeholder="Search by name or email..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <button type="submit" class="btn text-white px-4 fw-semibold" style="background-color: var(--primary-green);">Search</button>
                    <?php if ($search): ?>
                        <a href="users.php" class="btn btn-outline-secondary">Clear</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <!-- Users Table -->
        <div class="card border-0 shadow-sm" style="border-radius: 12px; overflow: hidden;">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="py-3 ps-3">#ID</th>
                                <th class="py-3">Name</th>
                                <th class="py-3">Email</th>
                                <th class="py-3">Phone</th>
                                <th class="py-3">Role</th>
                                <th class="py-3">Status</th>
                                <th class="py-3">Joined</th>
                                <th class="py-3 text-end pe-3">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($users_res && mysqli_num_rows($users_res) > 0): ?>
                                <?php while ($user = mysqli_fetch_assoc($users_res)): ?>
                                <tr>
                                    <td class="ps-3 fw-semibold">#<?php echo $user['id']; ?></td>
                                    <td class="fw-bold text-dark"><?php echo htmlspecialchars($user['name']); ?></td>
                                    <td class="text-muted"><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td><?php echo htmlspecialchars($user['phone'] ?? '-'); ?></td>
                                    <td>
                                        <span class="badge <?php echo $user['role'] === 'admin' ? 'bg-danger bg-opacity-10 text-danger' : 'bg-secondary bg-opacity-10 text-secondary'; ?> px-2 py-1">
                                            <?php echo ucfirst($user['role']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo ($user['status'] ?? 'active') === 'active' ? 'bg-success bg-opacity-10 text-success' : 'bg-danger bg-opacity-10 text-danger'; ?> px-2 py-1">
                                            <?php echo ucfirst($user['status'] ?? 'active'); ?>
                                        </span>
                                    </td>
                                    <td class="text-muted small"><?php echo date('d M Y', strtotime($user['created_at'])); ?></td>
                                    <td class="text-end pe-3">
                                        <!-- Toggle Status -->
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="action" value="toggle_status">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <input type="hidden" name="new_status" value="<?php echo ($user['status'] === 'active') ? 'banned' : 'active'; ?>">
                                            <button type="submit" class="btn btn-sm <?php echo ($user['status'] === 'active') ? 'btn-outline-warning' : 'btn-outline-success'; ?> me-1" title="<?php echo ($user['status'] === 'active') ? 'Ban User' : 'Activate User'; ?>">
                                                <i class="fas <?php echo ($user['status'] === 'active') ? 'fa-ban' : 'fa-check'; ?>"></i>
                                            </button>
                                        </form>

                                        <!-- Change Role -->
                                        <?php if ($user['role'] !== 'admin' || $user['id'] !== ($_SESSION['user_id'] ?? 0)): ?>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="action" value="change_role">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <input type="hidden" name="role" value="<?php echo ($user['role'] === 'admin') ? 'user' : 'admin'; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-info me-1" title="Toggle Admin Role">
                                                <i class="fas fa-user-shield"></i>
                                            </button>
                                        </form>
                                        <?php endif; ?>

                                        <!-- Delete -->
                                        <?php if ($user['role'] !== 'admin'): ?>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this user?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete User">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="8" class="text-center py-4 text-muted">No users found.</td></tr>
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