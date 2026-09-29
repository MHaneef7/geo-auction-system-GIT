<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include_once __DIR__ . '/../includes/database.php';
include_once __DIR__ . '/../includes/auth.php';

require_admin('../index.php');

include_once __DIR__ . '/../includes/backup_service.php';

// Check admin role if logged in
$user_name = $_SESSION['user_name'] ?? ($_SESSION['name'] ?? 'Administrator');

// Handle backup status notification
$status_msg = '';
$status_type = '';
if (isset($_GET['backup_status'])) {
    $status_type = $_GET['backup_status'] === 'success' ? 'success' : 'danger';
    $status_msg = $_GET['backup_msg'] ?? 'Operation completed.';
}

$db_stats = getDatabaseStats($conn, $database);
$existing_backups = getExistingBackups();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Backup & System Settings - Admin Panel</title>
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
            overflow-y: auto;
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

    <!-- Sidebar Navigation -->
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
        <a href="setting.php" class="active"><i class="fas fa-database me-2"></i> Database & Backup</a>
        <a href="reports.php"><i class="fas fa-chart-line me-2"></i> Reports</a>
        <a href="logout.php" class="text-danger mt-4"><i class="fas fa-sign-out-alt me-2"></i> Logout</a>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
            <div>
                <h2 class="fw-bold mb-1" style="color: #111827;">Database Backup & Settings</h2>
                <p class="text-muted mb-0" style="font-size: 14px;">Create, schedule, or download on-demand backups of your MySQL auction database.</p>
            </div>
            <div class="dropdown">
                <button class="btn btn-light border dropdown-toggle px-3 py-2" type="button" data-bs-toggle="dropdown">
                    <i class="fas fa-user-circle me-1 text-success"></i> <?php echo htmlspecialchars($user_name); ?>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="reports.php">Reports</a></li>
                    <li><a class="dropdown-item" href="../settings.php?section=backup">User Settings</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="logout.php">Logout</a></li>
                </ul>
            </div>
        </div>

        <?php if ($status_msg): ?>
            <div class="alert alert-<?php echo $status_type; ?> alert-dismissible fade show d-flex align-items-center gap-2" role="alert">
                <i class="fas fa-<?php echo $status_type === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> fs-5"></i>
                <div><?php echo htmlspecialchars($status_msg); ?></div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Database Metrics -->
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="card card-stat p-3 bg-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1" style="font-size: 13px;">Database Size</h6>
                            <h3 class="fw-bold mb-0 text-success"><?php echo htmlspecialchars($db_stats['size_formatted']); ?></h3>
                        </div>
                        <div class="fs-1 text-success opacity-75"><i class="fas fa-database"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card card-stat p-3 bg-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1" style="font-size: 13px;">Total Tables</h6>
                            <h3 class="fw-bold mb-0 text-dark"><?php echo htmlspecialchars($db_stats['tables_count']); ?> Tables</h3>
                        </div>
                        <div class="fs-1 text-primary opacity-75"><i class="fas fa-table"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card card-stat p-3 bg-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1" style="font-size: 13px;">Estimated Rows</h6>
                            <h3 class="fw-bold mb-0 text-dark"><?php echo number_format($db_stats['total_rows']); ?> Rows</h3>
                        </div>
                        <div class="fs-1 text-warning opacity-75"><i class="fas fa-list-ol"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card card-stat p-3 bg-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1" style="font-size: 13px;">Last Backup</h6>
                            <h5 class="fw-bold mb-0 text-secondary" style="font-size: 15px;"><?php echo htmlspecialchars($db_stats['last_backup_formatted']); ?></h5>
                        </div>
                        <div class="fs-1 text-info opacity-75"><i class="fas fa-history"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- On-Click Download & Save Actions -->
        <div class="card card-stat bg-white p-4 mb-4">
            <h5 class="fw-bold mb-2"><i class="fas fa-download text-success me-2"></i>One-Click Download & Backup Actions</h5>
            <p class="text-muted mb-4" style="font-size: 14px;">Instantly stream a full database dump to your device or save a snapshot to the server.</p>

            <div class="d-flex flex-wrap gap-2">
                <a href="../backup.php?action=download&from=admin/setting.php" class="btn btn-success px-3 py-2 fw-semibold">
                    <i class="fas fa-file-arrow-down me-1"></i> Download Full SQL (.sql)
                </a>
                <a href="../backup.php?action=download_gz&from=admin/setting.php" class="btn btn-outline-success px-3 py-2 fw-semibold">
                    <i class="fas fa-file-zipper me-1"></i> Download Compressed (.sql.gz)
                </a>
                <a href="../backup.php?action=save_server&type=daily&from=admin/setting.php" class="btn btn-primary px-3 py-2 fw-semibold">
                    <i class="fas fa-calendar-day me-1"></i> Save Daily Backup on Server
                </a>
                <a href="../backup.php?action=save_server&type=weekly&from=admin/setting.php" class="btn btn-outline-primary px-3 py-2 fw-semibold">
                    <i class="fas fa-calendar-week me-1"></i> Save Weekly Backup on Server
                </a>
            </div>
        </div>

        <!-- Automation / Cron Instructions -->
        <div class="card card-stat bg-white p-4 mb-4">
            <h5 class="fw-bold mb-2"><i class="fas fa-clock text-primary me-2"></i>Automated Daily & Weekly Backup Setup</h5>
            <p class="text-muted mb-3" style="font-size: 14px;">Set up scheduled tasks on your server to automatically generate backups without manual clicks:</p>

            <div class="bg-light p-3 rounded mb-3 border">
                <span class="badge bg-primary mb-2">Daily Schedule (e.g. at 2:00 AM)</span>
                <code class="d-block text-dark fw-bold" style="font-size: 13px;">php <?php echo htmlspecialchars(realpath(__DIR__ . '/../backup.php') ?: 'backup.php'); ?> --cron=daily</code>
            </div>

            <div class="bg-light p-3 rounded border">
                <span class="badge bg-secondary mb-2">Weekly Schedule (e.g. Every Sunday)</span>
                <code class="d-block text-dark fw-bold" style="font-size: 13px;">php <?php echo htmlspecialchars(realpath(__DIR__ . '/../backup.php') ?: 'backup.php'); ?> --cron=weekly</code>
            </div>
        </div>

        <!-- Saved Backups Ledger Table -->
        <div class="card card-stat bg-white p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="fw-bold mb-0"><i class="fas fa-folder-open text-warning me-2"></i>Archived Backups in <code>dbBackup/</code></h5>
                <span class="badge bg-light text-dark border"><?php echo count($existing_backups); ?> Available</span>
            </div>

            <?php if (empty($existing_backups)): ?>
                <div class="text-center py-4 text-muted">
                    <i class="fas fa-archive fa-3x mb-3 text-secondary opacity-50"></i>
                    <p class="mb-0">No backup files found on the server. Use the buttons above to create one.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Filename</th>
                                <th>Type</th>
                                <th>Size</th>
                                <th>Creation Date</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($existing_backups as $bk): ?>
                                <tr>
                                    <td>
                                        <i class="fas fa-file-code text-success me-2"></i>
                                        <span class="font-monospace fw-semibold" style="font-size: 13px;"><?php echo htmlspecialchars($bk['filename']); ?></span>
                                    </td>
                                    <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($bk['type']); ?></span></td>
                                    <td class="fw-bold text-success"><?php echo htmlspecialchars($bk['size_formatted']); ?></td>
                                    <td class="text-muted" style="font-size: 13px;"><?php echo htmlspecialchars($bk['modified_formatted']); ?></td>
                                    <td class="text-end">
                                        <a href="../backup.php?action=download_file&file=<?php echo urlencode($bk['filename']); ?>&from=admin/setting.php" 
                                           class="btn btn-sm btn-outline-success me-1" title="Download">
                                            <i class="fas fa-download me-1"></i> Download
                                        </a>
                                        <a href="../backup.php?action=delete_file&file=<?php echo urlencode($bk['filename']); ?>&from=admin/setting.php" 
                                           class="btn btn-sm btn-outline-danger" 
                                           onclick="return confirm('Are you sure you want to delete <?php echo htmlspecialchars($bk['filename']); ?>?');" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
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
