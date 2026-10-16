<?php
/**
 * User Notifications Center
 * File: user/notifications.php
 * 
 * Powered by NotificationService (OOP PHP & PDO).
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/NotificationService.php';

if (!is_logged_in()) {
    header("Location: ../login.php");
    exit();
}

$userId = (int)($_SESSION['user_id'] ?? 0);
$notifService = new NotificationService();

// Handle filter param: 'all', 'unread', 'read'
$filter = isset($_GET['filter']) ? strtolower(trim($_GET['filter'])) : 'all';
$isReadFilter = null;
if ($filter === 'unread') {
    $isReadFilter = 0;
} elseif ($filter === 'read') {
    $isReadFilter = 1;
}

$unreadCount = $notifService->getUnreadCount($userId);
$notifications = $notifService->getAllNotifications($userId, $isReadFilter, 100);

include_once __DIR__ . '/../includes/header.php';
?>

<div class="notifications-container">
    <div class="notif-header">
        <div class="notif-header-title">
            <div class="notif-title-wrap">
                <i class="fa-solid fa-bell notif-bell-main"></i>
                <h2>Notifications</h2>
                <?php if ($unreadCount > 0): ?>
                    <span class="notif-badge-pill" id="pageUnreadBadge"><?php echo $unreadCount; ?> unread</span>
                <?php endif; ?>
            </div>
            <p class="notif-subtext">Real-time alerts for your bids, auction lots, messages, and marketplace activity.</p>
        </div>

        <div class="notif-actions">
            <?php if ($unreadCount > 0): ?>
                <button type="button" class="btn-mark-all" id="btnMarkAllRead" onclick="markAllNotificationsAsRead(event)">
                    <i class="fa-solid fa-check-double"></i> Mark all as read
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Filter Tabs -->
    <div class="notif-tabs">
        <a href="?filter=all" class="notif-tab <?php echo $filter === 'all' ? 'active' : ''; ?>">
            All Activity
        </a>
        <a href="?filter=unread" class="notif-tab <?php echo $filter === 'unread' ? 'active' : ''; ?>">
            Unread <?php if ($unreadCount > 0): ?><span class="tab-count"><?php echo $unreadCount; ?></span><?php endif; ?>
        </a>
        <a href="?filter=read" class="notif-tab <?php echo $filter === 'read' ? 'active' : ''; ?>">
            Read History
        </a>
    </div>

    <!-- Notifications List -->
    <div class="notif-list-card">
        <?php if (!empty($notifications)): ?>
            <div class="notif-feed">
                <?php foreach ($notifications as $n): 
                    $isUnread = ((int)$n['is_read'] === 0);
                    $iconClass = $n['icon'] ?: 'fa-solid fa-bell';
                    
                    // Themed styling per notification category
                    $themeClass = 'theme-general';
                    $type = strtolower($n['type'] ?? '');
                    if ($type === 'comment') {
                        $themeClass = 'theme-comment';
                    } elseif ($type === 'bid' || $type === 'bid_placed') {
                        $themeClass = 'theme-bid';
                    } elseif ($type === 'outbid') {
                        $themeClass = 'theme-outbid';
                    } elseif ($type === 'auction_won') {
                        $themeClass = 'theme-won';
                    } elseif ($type === 'message') {
                        $themeClass = 'theme-message';
                    }
                ?>
                    <div class="notif-card <?php echo $isUnread ? 'is-unread' : 'is-read'; ?> <?php echo $themeClass; ?>" id="notif-row-<?php echo $n['id']; ?>">
                        <div class="notif-icon-col">
                            <div class="notif-icon-bubble">
                                <i class="<?php echo htmlspecialchars($iconClass); ?>"></i>
                            </div>
                        </div>

                        <div class="notif-body-col">
                            <div class="notif-meta-row">
                                <span class="notif-type-tag"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $n['type']))); ?></span>
                                <span class="notif-time"><?php echo htmlspecialchars($n['time_ago']); ?></span>
                            </div>

                            <h4 class="notif-item-title"><?php echo $n['title']; ?></h4>
                            <p class="notif-item-desc"><?php echo $n['message']; ?></p>

                            <div class="notif-footer-actions">
                                <?php if (!empty($n['link'])): 
                                    $targetLink = $root_path . ltrim($n['link'], '/');
                                ?>
                                    <a href="<?php echo htmlspecialchars($targetLink); ?>" class="btn-notif-action" onclick="onNotificationOpen(<?php echo $n['id']; ?>)">
                                        <span>View Details</span> <i class="fa-solid fa-arrow-right text-[10px]"></i>
                                    </a>
                                <?php endif; ?>

                                <?php if ($isUnread): ?>
                                    <button type="button" class="btn-mark-single" onclick="markSingleAsRead(<?php echo $n['id']; ?>)" title="Mark as read">
                                        <i class="fa-solid fa-check"></i> Mark read
                                    </button>
                                <?php else: ?>
                                    <span class="status-read-badge"><i class="fa-solid fa-check text-[10px]"></i> Read</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php if ($isUnread): ?>
                            <div class="unread-indicator-dot" title="Unread"></div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="notif-empty-state">
                <div class="empty-icon-wrap">
                    <i class="fa-regular fa-bell-slash"></i>
                </div>
                <h3>No Notifications Found</h3>
                <p>
                    <?php if ($filter === 'unread'): ?>
                        You have caught up with all your unread notifications!
                    <?php elseif ($filter === 'read'): ?>
                        No archived read notifications yet.
                    <?php else: ?>
                        You don't have any notifications yet. Place bids, join lot discussions, or message sellers to see updates here!
                    <?php endif; ?>
                </p>
                <div class="empty-actions">
                    <a href="<?php echo $root_path; ?>live_auction.php" class="btn-primary-explore">
                        <i class="fa-solid fa-gavel"></i> Explore Live Auctions
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
.notifications-container {
    max-width: 900px;
    margin: 28px auto 60px;
    padding: 0 16px;
    font-family: inherit;
}

.notif-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 16px;
    margin-bottom: 24px;
}

.notif-title-wrap {
    display: flex;
    align-items: center;
    gap: 12px;
}

.notif-bell-main {
    font-size: 24px;
    color: #047857;
    background: rgba(4, 120, 87, 0.1);
    padding: 10px;
    border-radius: 12px;
}

.notif-title-wrap h2 {
    font-size: 24px;
    font-weight: 800;
    color: var(--text, #111827);
    margin: 0;
}

.notif-badge-pill {
    background: #047857;
    color: #ffffff;
    font-size: 11px;
    font-weight: 700;
    padding: 3px 10px;
    border-radius: 20px;
    letter-spacing: 0.3px;
}

.notif-subtext {
    font-size: 13px;
    color: var(--text-muted, #64748b);
    margin-top: 6px;
    margin-bottom: 0;
}

.btn-mark-all {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: #ffffff;
    color: #047857;
    border: 1.5px solid #047857;
    padding: 8px 16px;
    font-size: 12px;
    font-weight: 700;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s ease;
}

.btn-mark-all:hover {
    background: #047857;
    color: #ffffff;
    box-shadow: 0 4px 12px rgba(4, 120, 87, 0.2);
}

.notif-tabs {
    display: flex;
    gap: 8px;
    margin-bottom: 20px;
    border-bottom: 1px solid var(--border, #e2e8f0);
    padding-bottom: 12px;
}

.notif-tab {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 13px;
    font-weight: 600;
    color: var(--text-muted, #64748b);
    text-decoration: none;
    padding: 8px 16px;
    border-radius: 8px;
    transition: all 0.15s ease;
}

.notif-tab:hover {
    color: var(--text, #111827);
    background: rgba(0,0,0,0.03);
}

.notif-tab.active {
    color: #047857;
    background: rgba(4, 120, 87, 0.08);
    font-weight: 700;
}

.tab-count {
    background: #047857;
    color: #ffffff;
    font-size: 10px;
    padding: 1px 6px;
    border-radius: 10px;
}

.notif-list-card {
    background: var(--bg-card, #ffffff);
    border: 1px solid var(--border, #e2e8f0);
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 4px 20px rgba(0,0,0,0.03);
}

.notif-feed {
    display: flex;
    flex-direction: column;
}

.notif-card {
    display: flex;
    align-items: flex-start;
    gap: 16px;
    padding: 18px 20px;
    border-bottom: 1px solid var(--border, #f1f5f9);
    position: relative;
    transition: background 0.15s ease;
}

.notif-card:last-child {
    border-bottom: none;
}

.notif-card.is-unread {
    background: rgba(4, 120, 87, 0.03);
}

.notif-card:hover {
    background: rgba(0,0,0,0.015);
}

.notif-icon-col {
    flex-shrink: 0;
}

.notif-icon-bubble {
    width: 42px;
    height: 42px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
}

/* Category bubble themes */
.theme-comment .notif-icon-bubble { background: #e0f2fe; color: #0284c7; }
.theme-bid .notif-icon-bubble     { background: #dcfce7; color: #16a34a; }
.theme-outbid .notif-icon-bubble  { background: #fef3c7; color: #d97706; }
.theme-won .notif-icon-bubble     { background: #fef9c3; color: #ca8a04; }
.theme-message .notif-icon-bubble { background: #ede9fe; color: #7c3aed; }
.theme-general .notif-icon-bubble { background: #f1f5f9; color: #475569; }

.notif-body-col {
    flex: 1;
    min-width: 0;
}

.notif-meta-row {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 4px;
}

.notif-type-tag {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--text-muted, #64748b);
}

.notif-time {
    font-size: 11px;
    color: var(--text-muted, #94a3b8);
}

.notif-item-title {
    font-size: 14px;
    font-weight: 700;
    color: var(--text, #1e293b);
    margin: 0 0 4px 0;
}

.notif-item-desc {
    font-size: 13px;
    color: var(--text, #475569);
    line-height: 1.5;
    margin: 0 0 12px 0;
}

.notif-footer-actions {
    display: flex;
    align-items: center;
    gap: 12px;
}

.btn-notif-action {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: #047857;
    color: #ffffff;
    font-size: 12px;
    font-weight: 600;
    padding: 6px 14px;
    border-radius: 6px;
    text-decoration: none;
    transition: background 0.15s ease;
}

.btn-notif-action:hover {
    background: #065f46;
}

.btn-mark-single {
    background: none;
    border: none;
    font-size: 11px;
    font-weight: 600;
    color: #64748b;
    cursor: pointer;
    padding: 4px 8px;
    border-radius: 4px;
    transition: all 0.15s ease;
}

.btn-mark-single:hover {
    background: rgba(0,0,0,0.05);
    color: #047857;
}

.status-read-badge {
    font-size: 11px;
    color: #94a3b8;
    font-weight: 600;
}

.unread-indicator-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #047857;
    flex-shrink: 0;
    margin-top: 6px;
}

.notif-empty-state {
    padding: 60px 24px;
    text-align: center;
}

.empty-icon-wrap {
    width: 64px;
    height: 64px;
    border-radius: 50%;
    background: rgba(4, 120, 87, 0.08);
    color: #047857;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 26px;
    margin: 0 auto 16px;
}

.notif-empty-state h3 {
    font-size: 18px;
    font-weight: 700;
    color: var(--text, #1e293b);
    margin-bottom: 6px;
}

.notif-empty-state p {
    font-size: 13px;
    color: var(--text-muted, #64748b);
    max-width: 440px;
    margin: 0 auto 20px;
    line-height: 1.5;
}

.btn-primary-explore {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: #047857;
    color: #ffffff;
    font-size: 13px;
    font-weight: 700;
    padding: 10px 22px;
    border-radius: 8px;
    text-decoration: none;
    box-shadow: 0 4px 12px rgba(4, 120, 87, 0.2);
    transition: 0.15s ease;
}

.btn-primary-explore:hover {
    background: #065f46;
}
</style>

<script>
async function markSingleAsRead(id) {
    try {
        const root = '<?php echo $root_path; ?>';
        const res = await fetch(root + 'mark_as_read.php?notification_id=' + id);
        const data = await res.json();
        if (data.success) {
            const row = document.getElementById('notif-row-' + id);
            if (row) {
                row.classList.remove('is-unread');
                row.classList.add('is-read');
                const dot = row.querySelector('.unread-indicator-dot');
                if (dot) dot.remove();
                const markBtn = row.querySelector('.btn-mark-single');
                if (markBtn) {
                    markBtn.outerHTML = '<span class="status-read-badge"><i class="fa-solid fa-check text-[10px]"></i> Read</span>';
                }
            }
            if (typeof loadHeaderBadges === 'function') {
                loadHeaderBadges();
            }
        }
    } catch (e) {
        console.error('Error marking as read:', e);
    }
}

async function markAllNotificationsAsRead(event) {
    if (event) event.preventDefault();
    try {
        const root = '<?php echo $root_path; ?>';
        const res = await fetch(root + 'mark_as_read.php?mark_all_notifications=1');
        const data = await res.json();
        if (data.success) {
            // Update UI elements
            document.querySelectorAll('.notif-card.is-unread').forEach(card => {
                card.classList.remove('is-unread');
                card.classList.add('is-read');
                const dot = card.querySelector('.unread-indicator-dot');
                if (dot) dot.remove();
                const markBtn = card.querySelector('.btn-mark-single');
                if (markBtn) {
                    markBtn.outerHTML = '<span class="status-read-badge"><i class="fa-solid fa-check text-[10px]"></i> Read</span>';
                }
            });

            const pill = document.getElementById('pageUnreadBadge');
            if (pill) pill.remove();

            const btn = document.getElementById('btnMarkAllRead');
            if (btn) btn.remove();

            if (typeof loadHeaderBadges === 'function') {
                loadHeaderBadges();
            }
        }
    } catch (e) {
        console.error('Error marking all as read:', e);
    }
}

function onNotificationOpen(id) {
    try {
        const root = '<?php echo $root_path; ?>';
        fetch(root + 'mark_as_read.php?notification_id=' + id);
    } catch (e) {}
}
</script>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>
