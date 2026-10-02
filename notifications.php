<?php
/**
 * ============================================================================
 * File: notifications.php
 * Path: /notifications.php (Project Root)
 * Description: In-App User Notifications Center
 * Architecture: Senior PHP Full-Stack Standards
 * ============================================================================
 * 
 * Yeh page authenticated user ke tamam notifications (Bids, Outbids, Won Lots,
 * Order Status, Messages) ko sleek Bootstrap 5 UI me display karta hai.
 * Mark as read, mark all read, aur dynamic filtering provide karta hai.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth_check.php';

$user = require_auth();
$userId = (int)$user['id'];
$pdo = getDB();

// Handle AJAX actions
if (isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'mark_all_read') {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1, read_at = NOW() WHERE user_id = :user_id");
        $stmt->execute([':user_id' => $userId]);
        json_response(['success' => true, 'message' => 'All notifications marked as read.']);
    }

    if ($action === 'mark_read') {
        $notifId = (int)($_POST['notification_id'] ?? 0);
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1, read_at = NOW() WHERE id = :id AND user_id = :user_id");
        $stmt->execute([':id' => $notifId, ':user_id' => $userId]);
        json_response(['success' => true, 'message' => 'Notification marked as read.']);
    }

    if ($action === 'delete') {
        $notifId = (int)($_POST['notification_id'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM notifications WHERE id = :id AND user_id = :user_id");
        $stmt->execute([':id' => $notifId, ':user_id' => $userId]);
        json_response(['success' => true, 'message' => 'Notification deleted.']);
    }
}

// Fetch all notifications for this user
$filter = trim($_GET['filter'] ?? 'all');
$whereClause = "WHERE user_id = :user_id";

if ($filter === 'unread') {
    $whereClause .= " AND is_read = 0";
} elseif ($filter === 'bids') {
    $whereClause .= " AND type IN ('bid', 'outbid', 'won', 'auction_ended')";
} elseif ($filter === 'orders') {
    $whereClause .= " AND type IN ('order_confirmed', 'item_sold', 'payment')";
}

$stmt = $pdo->prepare("
    SELECT * FROM notifications 
    {$whereClause}
    ORDER BY created_at DESC 
    LIMIT 50
");
$stmt->execute([':user_id' => $userId]);
$notifications = $stmt->fetchAll();

// Count unread
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = :user_id AND is_read = 0");
$countStmt->execute([':user_id' => $userId]);
$unreadTotal = (int)$countStmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications – Geo Auction System</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        :root {
            --primary: #047857;
            --primary-hover: #065f46;
            --primary-light: #ecfdf5;
        }
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: #f8fafc;
            color: #1e293b;
        }
        .notif-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.03);
        }
        .notif-item {
            padding: 18px 20px;
            border-bottom: 1px solid #f1f5f9;
            transition: all 0.15s ease-in-out;
            display: flex;
            align-items: flex-start;
            gap: 16px;
        }
        .notif-item:last-child {
            border-bottom: none;
        }
        .notif-item.unread {
            background-color: #f0fdf4;
            border-left: 4px solid var(--primary);
        }
        .notif-item:hover {
            background-color: #f8fafc;
        }
        .notif-icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            flex-shrink: 0;
        }
        .icon-bid { background: #dbeafe; color: #1d4ed8; }
        .icon-outbid { background: #fee2e2; color: #b91c1c; }
        .icon-won { background: #fef3c7; color: #b45309; }
        .icon-order { background: #dcfce7; color: #15803d; }
        .icon-default { background: #f1f5f9; color: #475569; }
    </style>
</head>
<body>

    <!-- Header Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom sticky-top py-3">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center gap-2" href="index.php">
                <i class="fa-solid fa-gavel fa-lg" style="color: #047857;"></i>
                <span class="fs-4 fw-bold" style="color: #047857;">Geo Auction</span>
            </a>
            <div class="d-flex align-items-center gap-3">
                <a href="dashboard.php" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
                    <i class="fa-solid fa-arrow-left me-1"></i> Dashboard
                </a>
            </div>
        </div>
    </nav>

    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-9">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
                    <div>
                        <h3 class="fw-bold text-dark mb-1">Notification Center</h3>
                        <p class="text-muted small mb-0">Stay updated on your bids, auction results, and orders.</p>
                    </div>
                    <?php if ($unreadTotal > 0): ?>
                        <button onclick="markAllAsRead()" class="btn btn-sm btn-outline-success rounded-pill px-3">
                            <i class="fa-solid fa-check-double me-1"></i> Mark All as Read (<?= $unreadTotal ?>)
                        </button>
                    <?php endif; ?>
                </div>

                <!-- Tabs Filter -->
                <ul class="nav nav-pills mb-4 gap-2">
                    <li class="nav-item">
                        <a class="nav-link rounded-pill <?= $filter === 'all' ? 'active bg-success' : 'text-secondary bg-white border' ?>" href="notifications.php?filter=all">
                            All Notifications
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link rounded-pill <?= $filter === 'unread' ? 'active bg-success' : 'text-secondary bg-white border' ?>" href="notifications.php?filter=unread">
                            Unread <?= $unreadTotal > 0 ? "($unreadTotal)" : '' ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link rounded-pill <?= $filter === 'bids' ? 'active bg-success' : 'text-secondary bg-white border' ?>" href="notifications.php?filter=bids">
                            Bids & Auctions
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link rounded-pill <?= $filter === 'orders' ? 'active bg-success' : 'text-secondary bg-white border' ?>" href="notifications.php?filter=orders">
                            Orders & Wins
                        </a>
                    </li>
                </ul>

                <div class="notif-card overflow-hidden">
                    <?php if (empty($notifications)): ?>
                        <div class="text-center py-5">
                            <i class="fa-regular fa-bell-slash fa-3x text-muted mb-3"></i>
                            <h5 class="fw-bold text-secondary">No notifications found</h5>
                            <p class="text-muted small">You're all caught up with your auction updates.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($notifications as $n): ?>
                            <?php
                                $type = $n['type'] ?? 'info';
                                $iconClass = 'icon-default';
                                $icon = 'fa-regular fa-bell';

                                if ($type === 'bid') {
                                    $iconClass = 'icon-bid';
                                    $icon = 'fa-solid fa-hand-holding-dollar';
                                } elseif ($type === 'outbid') {
                                    $iconClass = 'icon-outbid';
                                    $icon = 'fa-solid fa-arrow-trend-down';
                                } elseif ($type === 'won') {
                                    $iconClass = 'icon-won';
                                    $icon = 'fa-solid fa-trophy';
                                } elseif (in_array($type, ['order_confirmed', 'item_sold'])) {
                                    $iconClass = 'icon-order';
                                    $icon = 'fa-solid fa-box-check';
                                }
                            ?>
                            <div class="notif-item <?= $n['is_read'] ? '' : 'unread' ?>" id="notif-row-<?= $n['id'] ?>">
                                <div class="notif-icon <?= $iconClass ?>">
                                    <i class="<?= $icon ?>"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="d-flex align-items-center justify-content-between mb-1">
                                        <h6 class="fw-bold text-dark mb-0"><?= htmlspecialchars($n['title']) ?></h6>
                                        <small class="text-muted"><?= date('M d, h:i A', strtotime($n['created_at'])) ?></small>
                                    </div>
                                    <p class="text-secondary small mb-2"><?= htmlspecialchars($n['message']) ?></p>
                                    <div class="d-flex align-items-center gap-2">
                                        <?php if (!empty($n['link'])): ?>
                                            <a href="<?= htmlspecialchars($n['link']) ?>" class="btn btn-sm btn-link text-decoration-none p-0 text-success fw-semibold">
                                                View Details <i class="fa-solid fa-arrow-right small ms-1"></i>
                                            </a>
                                        <?php endif; ?>
                                        <?php if (!$n['is_read']): ?>
                                            <button onclick="markAsRead(<?= $n['id'] ?>)" class="btn btn-sm btn-link text-muted text-decoration-none p-0 ms-3 small">
                                                <i class="fa-solid fa-check me-1"></i> Mark read
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        function markAllAsRead() {
            fetch('notifications.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=mark_all_read'
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'All Read',
                        text: data.message,
                        timer: 1500,
                        showConfirmButton: false
                    }).then(() => {
                        window.location.reload();
                    });
                }
            });
        }

        function markAsRead(id) {
            fetch('notifications.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=mark_read&notification_id=' + id
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const row = document.getElementById('notif-row-' + id);
                    if (row) {
                        row.classList.remove('unread');
                    }
                }
            });
        }
    </script>
</body>
</html>
