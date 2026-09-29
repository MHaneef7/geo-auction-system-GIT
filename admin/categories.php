<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include_once __DIR__ .'/../includes/database.php';
include_once __DIR__ . '/../includes/auth.php';

require_admin('../index.php');

$msg = '';

// Handle add / edit / delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name = mysqli_real_escape_string($conn, trim($_POST['name']));
        $icon = mysqli_real_escape_string($conn, trim($_POST['icon'] ?? 'fa-tag'));
        if ($name) {
            mysqli_query($conn, "INSERT INTO categories (name, icon) VALUES ('$name', '$icon')");
            $msg = 'Category added successfully.';
        }
    } elseif ($action === 'edit') {
        $cid  = (int)$_POST['cat_id'];
        $name = mysqli_real_escape_string($conn, trim($_POST['name']));
        $icon = mysqli_real_escape_string($conn, trim($_POST['icon'] ?? 'fa-tag'));
        if ($name) {
            mysqli_query($conn, "UPDATE categories SET name = '$name', icon = '$icon' WHERE id = $cid");
            $msg = 'Category updated successfully.';
        }
    } elseif ($action === 'delete') {
        $cid = (int)$_POST['cat_id'];
        mysqli_query($conn, "DELETE FROM categories WHERE id = $cid");
        $msg = 'Category deleted successfully.';
    }
}

$cats_res = mysqli_query($conn, "
    SELECT c.*, (SELECT COUNT(*) FROM items i WHERE i.category_id = c.id) AS item_count
    FROM categories c
    ORDER BY c.name ASC
");

// Category to edit (if requested)
$edit_cat = null;
if (isset($_GET['edit'])) {
    $eid = (int)$_GET['edit'];
    $er  = mysqli_query($conn, "SELECT * FROM categories WHERE id = $eid");
    $edit_cat = $er ? mysqli_fetch_assoc($er) : null;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Categories - Admin Panel</title>
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
        <a href="categories.php" class="active"><i class="fas fa-tags me-2"></i> Categories</a>
        <a href="items.php"><i class="fas fa-box-open me-2"></i> Items</a>
        <a href="bids.php"><i class="fas fa-hand-holding-usd me-2"></i> Bids</a>
        
        <div class="nav-category">System</div>
        <a href="reports.php"><i class="fas fa-chart-line me-2"></i> Reports</a>
        <a href="logout.php" class="text-danger mt-4"><i class="fas fa-sign-out-alt me-2"></i> Logout</a>
    </div>

    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
            <h2 class="fw-bold mb-0" style="color: #111827;"><i class="fas fa-tags me-2 text-success"></i>Manage Categories</h2>
            <span class="text-muted bg-white px-3 py-2 border rounded shadow-sm">Logged in as: <strong class="text-dark"><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Admin'); ?></strong></span>
        </div>

        <?php if ($msg): ?>
            <div class="alert alert-success alert-dismissible fade show shadow-sm">
                <i class="fas fa-circle-check me-2"></i><?php echo htmlspecialchars($msg); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row g-4">
            <!-- Add / Edit Form -->
            <div class="col-md-4">
                <div class="card border-0 shadow-sm" style="border-radius: 12px; overflow: hidden;">
                    <div class="card-header text-white py-3" style="background-color: var(--primary-green);">
                        <h5 class="mb-0 fw-bold fs-6">
                            <?php echo $edit_cat ? '<i class="fas fa-edit me-2"></i>Edit Category' : '<i class="fas fa-plus me-2"></i>Add New Category'; ?>
                        </h5>
                    </div>
                    <div class="card-body p-4">
                        <form method="POST">
                            <input type="hidden" name="action" value="<?php echo $edit_cat ? 'edit' : 'add'; ?>">
                            <?php if ($edit_cat): ?>
                                <input type="hidden" name="cat_id" value="<?php echo $edit_cat['id']; ?>">
                            <?php endif; ?>
                            <div class="mb-3">
                                <label class="form-label fw-semibold text-dark">Category Name</label>
                                <input type="text" name="name" class="form-control" required
                                       value="<?php echo $edit_cat ? htmlspecialchars($edit_cat['name']) : ''; ?>"
                                       placeholder="e.g. Electronics">
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold text-dark">Font Awesome Icon Class</label>
                                <input type="text" name="icon" class="form-control"
                                       value="<?php echo $edit_cat ? htmlspecialchars($edit_cat['icon'] ?? 'fa-tag') : 'fa-tag'; ?>"
                                       placeholder="e.g. fa-laptop">
                                <div class="form-text">Use FontAwesome classes (e.g. fa-mobile, fa-car, fa-laptop)</div>
                            </div>
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn text-white fw-bold py-2" style="background-color: var(--primary-green);">
                                    <i class="fas <?php echo $edit_cat ? 'fa-save' : 'fa-plus'; ?> me-2"></i>
                                    <?php echo $edit_cat ? 'Save Changes' : 'Add Category'; ?>
                                </button>
                                <?php if ($edit_cat): ?>
                                    <a href="categories.php" class="btn btn-outline-secondary py-2">Cancel</a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Categories List -->
            <div class="col-md-8">
                <div class="card border-0 shadow-sm" style="border-radius: 12px; overflow: hidden;">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="py-3 ps-3">#</th>
                                        <th class="py-3">Icon</th>
                                        <th class="py-3">Name</th>
                                        <th class="py-3">Items</th>
                                        <th class="py-3 text-end pe-3">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($cats_res && mysqli_num_rows($cats_res) > 0): ?>
                                        <?php while ($cat = mysqli_fetch_assoc($cats_res)): ?>
                                        <tr>
                                            <td class="ps-3">#<?php echo $cat['id']; ?></td>
                                            <td><i class="fas <?php echo htmlspecialchars($cat['icon'] ?? 'fa-tag'); ?> fa-lg text-success"></i></td>
                                            <td class="fw-semibold text-dark"><?php echo htmlspecialchars($cat['name']); ?></td>
                                            <td><span class="badge bg-success bg-opacity-10 text-success px-2 py-1"><?php echo $cat['item_count']; ?> Items</span></td>
                                            <td class="text-end pe-3">
                                                <a href="categories.php?edit=<?php echo $cat['id']; ?>" class="btn btn-sm btn-outline-warning me-1">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this category?');">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="cat_id" value="<?php echo $cat['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                                                </form>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr><td colspan="5" class="text-center py-4 text-muted">No categories found.</td></tr>
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