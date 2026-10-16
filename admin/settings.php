<?php
/**
 * Admin System Settings Dashboard - Geo Auction System
 * File: admin/settings.php
 *
 * Full-featured, OOP PHP + PDO, Database-Driven Configuration Panel.
 */

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/settings.php';

// Enforce strict administrator authorization
require_admin('../index.php');

$pdo = Database::getPdo();
Settings::init($pdo);

$user_name = $_SESSION['user_name'] ?? ($_SESSION['name'] ?? 'Administrator');
$status_msg = '';
$status_type = 'success';
$active_tab = $_GET['tab'] ?? 'general';

// -----------------------------------------------------------------------------
// Handle Standard Form Submission (POST with CSRF Protection)
// -----------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_group') {
    $token = $_POST['csrf_token'] ?? '';
    if (!Settings::verifyCsrf($token)) {
        $status_msg = 'Security token expired. Please try again.';
        $status_type = 'danger';
    } else {
        $group = trim((string)($_POST['group'] ?? 'general'));
        $active_tab = $group;
        $submitted = $_POST['settings'] ?? [];
        if (is_array($submitted) && !empty($submitted)) {
            // Check boolean switches that send no POST value when unchecked
            $booleanFields = [
                'general'       => ['maintenance_mode'],
                'auction'       => ['auto_extend_enabled', 'buy_now_enabled', 'reserve_price_enabled', 'auto_bid_enabled', 'auction_approval_required'],
                'users'         => ['enable_registration', 'require_email_verification', 'user_approval_required', 'enable_seller_registration', 'enable_buyer_registration'],
                'notifications' => ['enable_email_notifications', 'enable_bid_notifications', 'enable_outbid_notifications', 'enable_ending_notifications', 'enable_winning_notifications', 'enable_system_notifications'],
                'security'      => ['require_strong_password', 'admin_login_security'],
                'payment'       => ['wallet_enabled'],
            ];
            if (isset($booleanFields[$group])) {
                foreach ($booleanFields[$group] as $boolKey) {
                    if (!isset($submitted[$boolKey])) {
                        $submitted[$boolKey] = '0';
                    }
                }
            }
            if (Settings::saveMultiple($submitted, $group)) {
                $status_msg = ucfirst($group) . ' settings saved and updated successfully!';
                $status_type = 'success';
            } else {
                $status_msg = 'An error occurred while saving ' . $group . ' settings.';
                $status_type = 'danger';
            }
        }
    }
}

    $generalSettings       = Settings::getGroup('general');
    $auctionSettings       = Settings::getGroup('auction');
    $userSettings          = Settings::getGroup('users');
    $notificationSettings  = Settings::getGroup('notifications');
    $securitySettings      = Settings::getGroup('security');
    $paymentSettings       = Settings::getGroup('payment');
    $systemInfo            = Settings::getSystemInfo();
    $csrfToken             = Settings::csrfToken();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Platform Settings - Admin Panel - Geo Auction System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-green: rgb(4 120 87);
            --primary-green-hover: rgb(3 98 71);
            --sidebar-bg: #111827;
            --sidebar-text: #9ca3af;
        }
        body { 
            background-color: #f8fafc; 
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            color: #1e293b;
        }
        .sidebar {
            height: 100vh;
            background-color: var(--sidebar-bg);
            color: #fff;
            position: fixed;
            top: 0;
            left: 0;
            width: 260px;
            padding-top: 24px;
            z-index: 100;
            overflow-y: auto;
            border-right: 1px solid #1f2937;
        }
        .sidebar a {
            color: var(--sidebar-text);
            text-decoration: none;
            padding: 12px 22px;
            display: flex;
            align-items: center;
            gap: 12px;
            transition: 0.2s ease;
            font-size: 13.5px;
            font-weight: 500;
            border-left: 3px solid transparent;
        }
        .sidebar a:hover, .sidebar a.active {
            color: #fff;
            background-color: rgba(4, 120, 87, 0.15);
            border-left-color: var(--primary-green);
        }
        .sidebar .nav-category {
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 1.2px;
            color: #4b5563;
            padding: 12px 22px 6px;
            margin-top: 10px;
            font-weight: 700;
        }
        .main-content { 
            margin-left: 260px; 
            padding: 32px; 
        }
        .card-settings {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.03);
            overflow: hidden;
        }
        .settings-nav-tabs .nav-link {
            color: #64748b;
            font-weight: 600;
            font-size: 13.5px;
            padding: 14px 20px;
            border: none;
            border-bottom: 3px solid transparent;
            display: flex;
            align-items: center;
            gap: 8px;
            background: transparent;
            transition: all 0.2s ease;
        }
        .settings-nav-tabs .nav-link:hover {
            color: var(--primary-green);
            background: rgba(4, 120, 87, 0.03);
        }
        .settings-nav-tabs .nav-link.active {
            color: var(--primary-green);
            border-bottom-color: var(--primary-green);
            background: #ffffff;
        }
        .form-section-title {
            font-size: 14px;
            font-weight: 700;
            color: #0f172a;
            letter-spacing: 0.3px;
            margin-bottom: 4px;
        }
        .form-section-desc {
            font-size: 12px;
            color: #64748b;
            margin-bottom: 20px;
        }
        .form-label {
            font-size: 12.5px;
            font-weight: 600;
            color: #334155;
            margin-bottom: 6px;
        }
        .form-control, .form-select {
            font-size: 13px;
            border-radius: 10px;
            border-color: #cbd5e1;
            padding: 9px 13px;
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--primary-green);
            box-shadow: 0 0 0 3px rgba(4, 120, 87, 0.12);
        }
        .form-text {
            font-size: 11.5px;
            color: #64748b;
        }
        .form-switch .form-check-input {
            width: 2.5em;
            height: 1.3em;
            cursor: pointer;
        }
        .form-switch .form-check-input:checked {
            background-color: var(--primary-green);
            border-color: var(--primary-green);
        }
        .btn-save-settings {
            background: var(--primary-green);
            border-color: var(--primary-green);
            color: #ffffff;
            font-weight: 700;
            font-size: 13px;
            border-radius: 10px;
            padding: 9px 24px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s ease;
        }
        .btn-save-settings:hover {
            background: var(--primary-green-hover);
            border-color: var(--primary-green-hover);
            color: #ffffff;
            box-shadow: 0 4px 12px rgba(4, 120, 87, 0.2);
        }
        .info-pill {
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 16px;
        }
        @media(max-width: 991px) {
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
            }
            .main-content {
                margin-left: 0;
                padding: 18px;
            }
        }
    </style>
</head>
<body>

    <!-- Sidebar Navigation -->
    <div class="sidebar">
        <div class="px-4 mb-4 d-flex align-items-center gap-2">
            <span class="d-inline-flex align-items-center justify-content-center bg-success text-white rounded-3 p-2" style="background-color: var(--primary-green) !important;">
                <i class="fa-solid fa-gavel"></i>
            </span>
            <div>
                <h5 class="text-white mb-0 fw-bold fs-6">Geo-Auction</h5>
                <small class="text-muted" style="font-size: 11px;">Admin Control Center</small>
            </div>
        </div>

        <div class="nav-category">Core</div>
        <a href="dashboard.php"><i class="fa-solid fa-gauge"></i> Dashboard</a>
        <a href="../user/dashboard.php"><i class="fa-solid fa-house-laptop"></i> User Portal</a>
        
        <div class="nav-category">Management</div>
        <a href="users.php"><i class="fa-solid fa-users"></i> Users</a>
        <a href="categories.php"><i class="fa-solid fa-tags"></i> Categories</a>
        <a href="items.php"><i class="fa-solid fa-box-archive"></i> Items</a>
        <a href="bids.php"><i class="fa-solid fa-hand-holding-dollar"></i> Bids</a>
        <a href="payments.php"><i class="fa-solid fa-credit-card"></i> Payments</a>
        
        <div class="nav-category">System</div>
        <a href="settings.php" class="active"><i class="fa-solid fa-sliders"></i> Platform Settings</a>
        <a href="setting.php"><i class="fa-solid fa-database"></i> Database &amp; Backup</a>
        <a href="reports.php"><i class="fa-solid fa-chart-pie"></i> Reports</a>
        <a href="logout.php" class="text-danger mt-4"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
    </div>

    <!-- Main Content Area -->
    <div class="main-content">
        <!-- Header Bar -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
            <div>
                <div class="d-flex align-items-center gap-2 mb-1">
                    <span class="badge bg-success-subtle text-success fw-bold px-2 py-1 rounded" style="font-size: 11px;">
                        <i class="fa-solid fa-gear me-1"></i> Global Configuration
                    </span>
                    <span class="badge bg-secondary-subtle text-secondary fw-bold px-2 py-1 rounded" style="font-size: 11px;">
                        PKR Operational Engine
                    </span>
                </div>
                <h2 class="fw-bold mb-1" style="color: #0f172a;">Platform System Settings</h2>
                <p class="text-muted mb-0 small">Database-driven configuration for marketplace rules, bidding mechanics, registration, and security.</p>
            </div>

            <div class="d-flex align-items-center gap-2">
                <a href="../live_auction.php" target="_blank" class="btn btn-outline-secondary btn-sm px-3 py-2 fw-semibold d-inline-flex align-items-center gap-2 rounded-3">
                    <i class="fa-solid fa-arrow-up-right-from-square"></i> View Live Auction
                </a>
                <div class="dropdown">
                    <button class="btn btn-white border dropdown-toggle px-3 py-2 rounded-3 text-dark fw-semibold small bg-white" type="button" data-bs-toggle="dropdown">
                        <i class="fa-solid fa-circle-user text-success me-1"></i> <?php echo htmlspecialchars($user_name); ?>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0">
                        <li><a class="dropdown-item small" href="../user/settings.php"><i class="fa-solid fa-user-gear me-2"></i>My Account Settings</a></li>
                        <li><a class="dropdown-item small" href="setting.php"><i class="fa-solid fa-database me-2"></i>Database Backups</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item small text-danger" href="logout.php"><i class="fa-solid fa-right-from-bracket me-2"></i>Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>

        <?php if (!empty($status_msg)): ?>
            <div class="alert alert-<?php echo $status_type; ?> alert-dismissible fade show d-flex align-items-center gap-2 shadow-sm rounded-3 mb-4" role="alert">
                <i class="fa-solid fa-<?php echo $status_type === 'success' ? 'circle-check text-success' : 'triangle-exclamation text-danger'; ?> fs-5"></i>
                <div class="small fw-semibold"><?php echo htmlspecialchars($status_msg); ?></div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Settings Container with Tabs -->
        <div class="card-settings">
            <!-- Navigation Tabs -->
            <ul class="nav settings-nav-tabs border-bottom px-3 pt-2 bg-light flex-nowrap overflow-x-auto scrollbar-none" id="adminSettingsTabs" role="tablist">
                <li class="nav-item">
                    <button class="nav-link <?php echo $active_tab === 'general' ? 'active' : ''; ?>" id="tab-general" data-bs-toggle="tab" data-bs-target="#pane-general" type="button">
                        <i class="fa-solid fa-globe"></i> General
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link <?php echo $active_tab === 'auction' ? 'active' : ''; ?>" id="tab-auction" data-bs-toggle="tab" data-bs-target="#pane-auction" type="button">
                        <i class="fa-solid fa-gavel"></i> Auction Rules
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link <?php echo $active_tab === 'users' ? 'active' : ''; ?>" id="tab-users" data-bs-toggle="tab" data-bs-target="#pane-users" type="button">
                        <i class="fa-solid fa-users"></i> Users &amp; Auth
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link <?php echo $active_tab === 'notifications' ? 'active' : ''; ?>" id="tab-notifications" data-bs-toggle="tab" data-bs-target="#pane-notifications" type="button">
                        <i class="fa-solid fa-bell"></i> Notifications
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link <?php echo $active_tab === 'payment' ? 'active' : ''; ?>" id="tab-payment" data-bs-toggle="tab" data-bs-target="#pane-payment" type="button">
                        <i class="fa-solid fa-wallet"></i> Payment &amp; Wallet
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link <?php echo $active_tab === 'security' ? 'active' : ''; ?>" id="tab-security" data-bs-toggle="tab" data-bs-target="#pane-security" type="button">
                        <i class="fa-solid fa-shield-halved"></i> Security
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link <?php echo $active_tab === 'system' ? 'active' : ''; ?>" id="tab-system" data-bs-toggle="tab" data-bs-target="#pane-system" type="button">
                        <i class="fa-solid fa-server"></i> System Info
                    </button>
                </li>
            </ul>

            <div class="tab-content p-4" id="adminSettingsContent">

                <!-- ── 1. GENERAL SETTINGS TAB ── -->
                <div class="tab-pane fade <?php echo $active_tab === 'general' ? 'show active' : ''; ?>" id="pane-general" role="tabpanel">
                    <form method="POST" action="settings.php?tab=general">
                        <input type="hidden" name="action" value="save_group">
                        <input type="hidden" name="group" value="general">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">

                        <div class="form-section-title"><i class="fa-solid fa-globe text-success me-1"></i> Platform Identity &amp; Regional Localization</div>
                        <div class="form-section-desc">Manage primary platform naming, logos, contact details, currency, and maintenance status.</div>
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label">Website Name</label>
                                <input type="text" name="settings[site_name]" class="form-control" value="<?php echo htmlspecialchars($generalSettings['site_name'] ?? 'Geo Auction System'); ?>" required>
                                <div class="form-text">Shown in document titles, email templates, and page headers.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Website Logo Path</label>
                                <input type="text" name="settings[site_logo]" class="form-control" value="<?php echo htmlspecialchars($generalSettings['site_logo'] ?? 'assets/image/Geo-auction.png'); ?>">
                                <div class="form-text">Relative path to the site logo image.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Website Official Email</label>
                                <input type="email" name="settings[site_email]" class="form-control" value="<?php echo htmlspecialchars($generalSettings['site_email'] ?? 'info@geoauction.com'); ?>" required>
                                <div class="form-text">Default sender address for transactional notices.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Official Hotline / Phone</label>
                                <input type="text" name="settings[site_phone]" class="form-control" value="<?php echo htmlspecialchars($generalSettings['site_phone'] ?? '+92-300-1234567'); ?>">
                                <div class="form-text">Support phone displayed across invoices and contact sections.</div>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">Headquarters / Physical Address</label>
                                <textarea name="settings[site_address]" rows="2" class="form-control"><?php echo htmlspecialchars($generalSettings['site_address'] ?? 'Main Auto Bhan Road, Qasimabad, Hyderabad, Sindh'); ?></textarea>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Currency Symbol</label>
                                <input type="text" name="settings[currency_symbol]" class="form-control" value="<?php echo htmlspecialchars($generalSettings['currency_symbol'] ?? 'Rs. '); ?>">
                                <div class="form-text">Displayed before prices (e.g. Rs. )</div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Currency Code</label>
                                <input type="text" name="settings[currency_code]" class="form-control" value="<?php echo htmlspecialchars($generalSettings['currency_code'] ?? 'PKR'); ?>">
                                <div class="form-text">Standard currency code (PKR).</div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Timezone</label>
                                <select name="settings[timezone]" class="form-select">
                                    <option value="Asia/Karachi" <?php echo ($generalSettings['timezone'] ?? '') === 'Asia/Karachi' ? 'selected' : ''; ?>>Asia/Karachi (PKT - UTC+5)</option>
                                    <option value="UTC" <?php echo ($generalSettings['timezone'] ?? '') === 'UTC' ? 'selected' : ''; ?>>UTC (Coordinated Universal Time)</option>
                                    <option value="Asia/Dubai" <?php echo ($generalSettings['timezone'] ?? '') === 'Asia/Dubai' ? 'selected' : ''; ?>>Asia/Dubai (GST - UTC+4)</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Website Status</label>
                                <select name="settings[site_status]" class="form-select">
                                    <option value="active" <?php echo ($generalSettings['site_status'] ?? '') === 'active' ? 'selected' : ''; ?>>Active (Public Marketplace Open)</option>
                                    <option value="maintenance" <?php echo ($generalSettings['site_status'] ?? '') === 'maintenance' ? 'selected' : ''; ?>>Maintenance (Admin Only Access)</option>
                                </select>
                            </div>
                            <div class="col-md-6 d-flex align-items-center">
                                <div class="form-check form-switch mt-3">
                                    <input class="form-check-input" type="checkbox" name="settings[maintenance_mode]" value="1" id="maintSwitch" <?php echo !empty($generalSettings['maintenance_mode']) ? 'checked' : ''; ?>>
                                    <label class="form-check-label ms-2 fw-semibold text-danger small" for="maintSwitch">
                                        Enable Maintenance Mode (Pause Bidding &amp; Payments)
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="border-top pt-3 d-flex justify-content-end">
                            <button type="submit" class="btn btn-save-settings">
                                <i class="fa-solid fa-floppy-disk"></i> Save General Settings
                            </button>
                        </div>
                    </form>
                </div>

                <!-- ── 2. AUCTION SETTINGS TAB ── -->
                <div class="tab-pane fade <?php echo $active_tab === 'auction' ? 'show active' : ''; ?>" id="pane-auction" role="tabpanel">
                    <form method="POST" action="settings.php?tab=auction">
                        <input type="hidden" name="action" value="save_group">
                        <input type="hidden" name="group" value="auction">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">

                        <div class="form-section-title"><i class="fa-solid fa-gavel text-success me-1"></i> Auction Engine &amp; Bidding Mechanics</div>
                        <div class="form-section-desc">Configure bid steps, anti-sniping extensions, feature toggles, and catalog validation limits.</div>

                        <div class="row g-3 mb-4">
                            <div class="col-md-4">
                                <label class="form-label">Default Minimum Bid Increment (Rs.)</label>
                                <input type="number" step="10" name="settings[min_bid_increment]" class="form-control" value="<?php echo htmlspecialchars($auctionSettings['min_bid_increment'] ?? '100'); ?>" required>
                                <div class="form-text">Smallest price increment step required between bids.</div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Default Lot Duration (Hours)</label>
                                <input type="number" name="settings[default_auction_duration_hours]" class="form-control" value="<?php echo htmlspecialchars($auctionSettings['default_auction_duration_hours'] ?? '72'); ?>" required>
                                <div class="form-text">Default running span (72 hrs = 3 days).</div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Maximum Allowed Duration (Days)</label>
                                <input type="number" name="settings[max_auction_duration_days]" class="form-control" value="<?php echo htmlspecialchars($auctionSettings['max_auction_duration_days'] ?? '30'); ?>" required>
                                <div class="form-text">Maximum days a lot can be scheduled to run.</div>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Auto-Extend Anti-Sniping Window (Seconds)</label>
                                <input type="number" name="settings[auto_extend_trigger_seconds]" class="form-control" value="<?php echo htmlspecialchars($auctionSettings['auto_extend_trigger_seconds'] ?? '180'); ?>" required>
                                <div class="form-text">If a bid arrives within this many seconds of end (180s = 3 mins).</div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Minutes Added on Sniping Extension</label>
                                <input type="number" name="settings[auto_extend_minutes]" class="form-control" value="<?php echo htmlspecialchars($auctionSettings['auto_extend_minutes'] ?? '3'); ?>" required>
                                <div class="form-text">Clock extension added on late bids.</div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Maximum Images per Listing</label>
                                <input type="number" name="settings[max_images_per_auction]" class="form-control" value="<?php echo htmlspecialchars($auctionSettings['max_images_per_auction'] ?? '10'); ?>" required>
                                <div class="form-text">Upload cap per item in seller portal.</div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Max Concurrently Active Lots per Seller</label>
                                <input type="number" name="settings[max_active_auctions_per_seller]" class="form-control" value="<?php echo htmlspecialchars($auctionSettings['max_active_auctions_per_seller'] ?? '25'); ?>" required>
                                <div class="form-text">Limit active listings per seller to maintain quality.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Allowed Item Conditions</label>
                                <input type="text" name="settings[allowed_auction_conditions]" class="form-control" value="<?php echo htmlspecialchars($auctionSettings['allowed_auction_conditions'] ?? 'new,used,refurbished'); ?>">
                                <div class="form-text">Comma-separated tags (new, used, refurbished).</div>
                            </div>
                            <div class="col-12"><hr class="my-2 text-muted"></div>
                            <div class="col-md-6">
                                <div class="form-check form-switch p-2 rounded bg-light border">
                                    <input class="form-check-input ms-0 me-3" type="checkbox" name="settings[auto_extend_enabled]" value="1" id="autoExtSwitch" <?php echo !empty($auctionSettings['auto_extend_enabled']) ? 'checked' : ''; ?>>
                                    <label class="form-check-label fw-semibold small" for="autoExtSwitch">
                                        Enable Anti-Sniping Timer Auto-Extension
                                        <div class="text-muted fw-normal" style="font-size: 11px;">Adds time automatically to prevent last-second sniping bots.</div>
                                    </label>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-check form-switch p-2 rounded bg-light border">
                                    <input class="form-check-input ms-0 me-3" type="checkbox" name="settings[buy_now_enabled]" value="1" id="buyNowSwitch" <?php echo !empty($auctionSettings['buy_now_enabled']) ? 'checked' : ''; ?>>
                                    <label class="form-check-label fw-semibold small" for="buyNowSwitch">
                                        Enable "Buy It Now" Instant Checkout
                                        <div class="text-muted fw-normal" style="font-size: 11px;"> Permits buyers to purchase lots instantly at set price.</div>
                                    </label>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-check form-switch p-2 rounded bg-light border">
                                    <input class="form-check-input ms-0 me-3" type="checkbox" name="settings[reserve_price_enabled]" value="1" id="reserveSwitch" <?php echo !empty($auctionSettings['reserve_price_enabled']) ? 'checked' : ''; ?>>
                                    <label class="form-check-label fw-semibold small" for="reserveSwitch">
                                        Enable Hidden Reserve Price Functionality
                                        <div class="text-muted fw-normal" style="font-size: 11px;">Prevents lot from concluding as sold if reserve is not reached.</div>
                                    </label>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-check form-switch p-2 rounded bg-light border">
                                    <input class="form-check-input ms-0 me-3" type="checkbox" name="settings[auto_bid_enabled]" value="1" id="autoBidSwitch" <?php echo !empty($auctionSettings['auto_bid_enabled']) ? 'checked' : ''; ?>>
                                    <label class="form-check-label fw-semibold small" for="autoBidSwitch">
                                        Enable Proxy Auto-Bid Engine (Bidbot)
                                        <div class="text-muted fw-normal" style="font-size: 11px;">Allows buyers to configure maximum auto-bid escalation ceilings.</div>
                                    </label>
                                </div>
                            </div>

                            <div class="col-md-12">
                                <div class="form-check form-switch p-2 rounded bg-light border">
                                    <input class="form-check-input ms-0 me-3" type="checkbox" name="settings[auction_approval_required]" value="1" id="approvalSwitch" <?php echo !empty($auctionSettings['auction_approval_required']) ? 'checked' : ''; ?>>
                                    <label class="form-check-label fw-semibold small" for="approvalSwitch">
                                        Require Administrator Approval for New Listings
                                        <div class="text-muted fw-normal" style="font-size: 11px;">New items created by sellers will be placed in 'pending' status until verified.</div>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="border-top pt-3 d-flex justify-content-end">
                            <button type="submit" class="btn btn-save-settings">
                                <i class="fa-solid fa-floppy-disk"></i> Save Auction Settings
                            </button>
                        </div>
                    </form>
                </div>

                <!-- ── 3. USER SETTINGS TAB ── -->
                <div class="tab-pane fade <?php echo $active_tab === 'users' ? 'show active' : ''; ?>" id="pane-users" role="tabpanel">
                    <form method="POST" action="settings.php?tab=users">
                        <input type="hidden" name="action" value="save_group">
                        <input type="hidden" name="group" value="users">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">

                        <div class="form-section-title"><i class="fa-solid fa-users text-success me-1"></i> User Registration &amp; Account Policies</div>
                        <div class="form-section-desc">Manage sign-up eligibility, verification gates, buyer/seller access, and default statuses.</div>

                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label">Default New Account Status</label>
                                <select name="settings[default_user_status]" class="form-select">
                                    <option value="active" <?php echo ($userSettings['default_user_status'] ?? '') === 'active' ? 'selected' : ''; ?>>Active (Immediate Access)</option>
                                    <option value="inactive" <?php echo ($userSettings['default_user_status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>Inactive (Awaiting Activation)</option>
                                    <option value="suspended" <?php echo ($userSettings['default_user_status'] ?? '') === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                                </select>
                                <div class="form-text">Status applied upon successful sign-up.</div>
                            </div>

                            <div class="col-12"><hr class="my-2 text-muted"></div>

                            <div class="col-md-6">
                                <div class="form-check form-switch p-2 rounded bg-light border">
                                    <input class="form-check-input ms-0 me-3" type="checkbox" name="settings[enable_registration]" value="1" id="regSwitch" <?php echo !empty($userSettings['enable_registration']) ? 'checked' : ''; ?>>
                                    <label class="form-check-label fw-semibold small" for="regSwitch">
                                        Enable User Registration
                                        <div class="text-muted fw-normal" style="font-size: 11px;">Allow public visitors to create new accounts via register.php.</div>
                                    </label>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-check form-switch p-2 rounded bg-light border">
                                    <input class="form-check-input ms-0 me-3" type="checkbox" name="settings[require_email_verification]" value="1" id="emailVerifSwitch" <?php echo !empty($userSettings['require_email_verification']) ? 'checked' : ''; ?>>
                                    <label class="form-check-label fw-semibold small" for="emailVerifSwitch">
                                        Require Email Verification
                                        <div class="text-muted fw-normal" style="font-size: 11px;">Requires members to verify their email before participating in bids.</div>
                                    </label>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-check form-switch p-2 rounded bg-light border">
                                    <input class="form-check-input ms-0 me-3" type="checkbox" name="settings[user_approval_required]" value="1" id="userApprSwitch" <?php echo !empty($userSettings['user_approval_required']) ? 'checked' : ''; ?>>
                                    <label class="form-check-label fw-semibold small" for="userApprSwitch">
                                        Require Admin Approval for New Users
                                        <div class="text-muted fw-normal" style="font-size: 11px;">Accounts remain in review until confirmed in Admin Users portal.</div>
                                    </label>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-check form-switch p-2 rounded bg-light border">
                                    <input class="form-check-input ms-0 me-3" type="checkbox" name="settings[enable_seller_registration]" value="1" id="sellerRegSwitch" <?php echo !empty($userSettings['enable_seller_registration']) ? 'checked' : ''; ?>>
                                    <label class="form-check-label fw-semibold small" for="sellerRegSwitch">
                                        Allow Seller Registration
                                        <div class="text-muted fw-normal" style="font-size: 11px;">Permit users to choose the 'Seller' role upon sign up.</div>
                                    </label>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-check form-switch p-2 rounded bg-light border">
                                    <input class="form-check-input ms-0 me-3" type="checkbox" name="settings[enable_buyer_registration]" value="1" id="buyerRegSwitch" <?php echo !empty($userSettings['enable_buyer_registration']) ? 'checked' : ''; ?>>
                                    <label class="form-check-label fw-semibold small" for="buyerRegSwitch">
                                        Allow Buyer Registration
                                        <div class="text-muted fw-normal" style="font-size: 11px;">Permit users to choose the 'Buyer' role upon sign up.</div>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="border-top pt-3 d-flex justify-content-end">
                            <button type="submit" class="btn btn-save-settings">
                                <i class="fa-solid fa-floppy-disk"></i> Save User Policies
                            </button>
                        </div>
                    </form>
                </div>

                <!-- ── 4. NOTIFICATION SETTINGS TAB ── -->
                <div class="tab-pane fade <?php echo $active_tab === 'notifications' ? 'show active' : ''; ?>" id="pane-notifications" role="tabpanel">
                    <form method="POST" action="settings.php?tab=notifications">
                        <input type="hidden" name="action" value="save_group">
                        <input type="hidden" name="group" value="notifications">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">

                        <div class="form-section-title"><i class="fa-solid fa-bell text-success me-1"></i> Real-Time Alerts &amp; Notification Channels</div>
                        <div class="form-section-desc">Toggle system and email notification triggers across live marketplace events.</div>

                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <div class="form-check form-switch p-2 rounded bg-light border">
                                    <input class="form-check-input ms-0 me-3" type="checkbox" name="settings[enable_system_notifications]" value="1" id="sysNotifSwitch" <?php echo !empty($notificationSettings['enable_system_notifications']) ? 'checked' : ''; ?>>
                                    <label class="form-check-label fw-semibold small" for="sysNotifSwitch">
                                        Enable In-App System Notification Feed
                                        <div class="text-muted fw-normal" style="font-size: 11px;">Top bell icon &amp; dashboard alerts across the platform.</div>
                                    </label>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-check form-switch p-2 rounded bg-light border">
                                    <input class="form-check-input ms-0 me-3" type="checkbox" name="settings[enable_email_notifications]" value="1" id="emailNotifSwitch" <?php echo !empty($notificationSettings['enable_email_notifications']) ? 'checked' : ''; ?>>
                                    <label class="form-check-label fw-semibold small" for="emailNotifSwitch">
                                        Enable Email Notifications
                                        <div class="text-muted fw-normal" style="font-size: 11px;">Deliver transactional emails via configured mail transport.</div>
                                    </label>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-check form-switch p-2 rounded bg-light border">
                                    <input class="form-check-input ms-0 me-3" type="checkbox" name="settings[enable_bid_notifications]" value="1" id="bidNotifSwitch" <?php echo !empty($notificationSettings['enable_bid_notifications']) ? 'checked' : ''; ?>>
                                    <label class="form-check-label fw-semibold small" for="bidNotifSwitch">
                                        Enable New Bid Alerts
                                        <div class="text-muted fw-normal" style="font-size: 11px;">Alert sellers when an offer is placed on their listed lots.</div>
                                    </label>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-check form-switch p-2 rounded bg-light border">
                                    <input class="form-check-input ms-0 me-3" type="checkbox" name="settings[enable_outbid_notifications]" value="1" id="outbidNotifSwitch" <?php echo !empty($notificationSettings['enable_outbid_notifications']) ? 'checked' : ''; ?>>
                                    <label class="form-check-label fw-semibold small" for="outbidNotifSwitch">
                                        Enable Outbid Alerts
                                        <div class="text-muted fw-normal" style="font-size: 11px;">Immediately notify bidders when they are superseded by higher offers.</div>
                                    </label>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-check form-switch p-2 rounded bg-light border">
                                    <input class="form-check-input ms-0 me-3" type="checkbox" name="settings[enable_ending_notifications]" value="1" id="endingNotifSwitch" <?php echo !empty($notificationSettings['enable_ending_notifications']) ? 'checked' : ''; ?>>
                                    <label class="form-check-label fw-semibold small" for="endingNotifSwitch">
                                        Enable Auction Ending-Soon Alerts
                                        <div class="text-muted fw-normal" style="font-size: 11px;">Alert lot watchers when auctions have &lt; 30 minutes remaining.</div>
                                    </label>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-check form-switch p-2 rounded bg-light border">
                                    <input class="form-check-input ms-0 me-3" type="checkbox" name="settings[enable_winning_notifications]" value="1" id="winNotifSwitch" <?php echo !empty($notificationSettings['enable_winning_notifications']) ? 'checked' : ''; ?>>
                                    <label class="form-check-label fw-semibold small" for="winNotifSwitch">
                                        Enable Winning &amp; Lot Closed Notices
                                        <div class="text-muted fw-normal" style="font-size: 11px;">Notify highest bidders and sellers upon lot finalization.</div>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="border-top pt-3 d-flex justify-content-end">
                            <button type="submit" class="btn btn-save-settings">
                                <i class="fa-solid fa-floppy-disk"></i> Save Notification Settings
                            </button>
                        </div>
                    </form>
                </div>

                <!-- ── 5. PAYMENT & WALLET TAB ── -->
                <div class="tab-pane fade <?php echo $active_tab === 'payment' ? 'show active' : ''; ?>" id="pane-payment" role="tabpanel">
                    <form method="POST" action="settings.php?tab=payment">
                        <input type="hidden" name="action" value="save_group">
                        <input type="hidden" name="group" value="payment">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">

                        <div class="form-section-title"><i class="fa-solid fa-wallet text-success me-1"></i> Wallet &amp; Transaction Configurations</div>
                        <div class="form-section-desc">Manage wallet top-up floors, minimum bids, transaction settlements, and platform commission.</div>

                        <div class="row g-3 mb-4">
                            <div class="col-md-4">
                                <label class="form-label">Platform Currency</label>
                                <input type="text" class="form-control bg-light" value="PKR (Pakistani Rupee)" disabled>
                                <div class="form-text">Locked to regional Pakistani Rupee (PKR).</div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Minimum Wallet Deposit (Rs.)</label>
                                <input type="number" step="100" name="settings[min_wallet_deposit]" class="form-control" value="<?php echo htmlspecialchars($paymentSettings['min_wallet_deposit'] ?? '500'); ?>" required>
                                <div class="form-text">Lowest allowed wallet top-up increment.</div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Minimum Opening Bid Amount (Rs.)</label>
                                <input type="number" step="10" name="settings[min_bid_amount]" class="form-control" value="<?php echo htmlspecialchars($paymentSettings['min_bid_amount'] ?? '100'); ?>" required>
                                <div class="form-text">Absolute floor for starting bid prices on any lot.</div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Marketplace Commission Rate (%)</label>
                                <div class="input-group">
                                    <input type="number" step="0.5" name="settings[commission_rate]" class="form-control" value="<?php echo htmlspecialchars($paymentSettings['commission_rate'] ?? '5'); ?>" required>
                                    <span class="input-group-text">%</span>
                                </div>
                                <div class="form-text">Platform service fee deducted from hammer price upon lot sale.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Transaction Settlement Mode</label>
                                <select name="settings[transaction_settings]" class="form-select">
                                    <option value="instant" <?php echo ($paymentSettings['transaction_settings'] ?? '') === 'instant' ? 'selected' : ''; ?>>Instant Balance Settlement</option>
                                    <option value="escrow" <?php echo ($paymentSettings['transaction_settings'] ?? '') === 'escrow' ? 'selected' : ''; ?>>Escrow Holding (Pending Delivery Confirmation)</option>
                                </select>
                                <div class="form-text">Governs when funds are credited to the seller's wallet.</div>
                            </div>

                            <div class="col-12"><hr class="my-2 text-muted"></div>

                            <div class="col-md-12">
                                <div class="form-check form-switch p-2 rounded bg-light border">
                                    <input class="form-check-input ms-0 me-3" type="checkbox" name="settings[wallet_enabled]" value="1" id="walletSwitch" <?php echo !empty($paymentSettings['wallet_enabled']) ? 'checked' : ''; ?>>
                                    <label class="form-check-label fw-semibold small" for="walletSwitch">
                                        Enable Built-in Digital Wallet System
                                        <div class="text-muted fw-normal" style="font-size: 11px;">Enables top-ups, tied-up bid deposits, and checkout with wallet balance.</div>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="border-top pt-3 d-flex justify-content-end">
                            <button type="submit" class="btn btn-save-settings">
                                <i class="fa-solid fa-floppy-disk"></i> Save Payment Settings
                            </button>
                        </div>
                    </form>
                </div>

                <!-- ── 6. SECURITY SETTINGS TAB ── -->
                <div class="tab-pane fade <?php echo $active_tab === 'security' ? 'show active' : ''; ?>" id="pane-security" role="tabpanel">
                    <form method="POST" action="settings.php?tab=security">
                        <input type="hidden" name="action" value="save_group">
                        <input type="hidden" name="group" value="security">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">

                        <div class="form-section-title"><i class="fa-solid fa-shield-halved text-success me-1"></i> Authentication &amp; Platform Security</div>
                        <div class="form-section-desc">Manage rate limits, session invalidation, password complexity, and admin security locks.</div>

                        <div class="row g-3 mb-4">
                            <div class="col-md-4">
                                <label class="form-label">Failed Login Attempt Limit</label>
                                <input type="number" name="settings[login_attempt_limit]" class="form-control" value="<?php echo htmlspecialchars($securitySettings['login_attempt_limit'] ?? '5'); ?>" required>
                                <div class="form-text">Max failed password attempts before temporary IP throttle.</div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Session Idle Timeout (Minutes)</label>
                                <input type="number" name="settings[session_timeout_minutes]" class="form-control" value="<?php echo htmlspecialchars($securitySettings['session_timeout_minutes'] ?? '120'); ?>" required>
                                <div class="form-text">Automatically expire inactive browser sessions.</div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Minimum Password Length</label>
                                <input type="number" name="settings[password_min_length]" class="form-control" value="<?php echo htmlspecialchars($securitySettings['password_min_length'] ?? '8'); ?>" required>
                                <div class="form-text">Floor on user and administrator password length.</div>
                            </div>

                            <div class="col-12"><hr class="my-2 text-muted"></div>

                            <div class="col-md-6">
                                <div class="form-check form-switch p-2 rounded bg-light border">
                                    <input class="form-check-input ms-0 me-3" type="checkbox" name="settings[require_strong_password]" value="1" id="strongPwSwitch" <?php echo !empty($securitySettings['require_strong_password']) ? 'checked' : ''; ?>>
                                    <label class="form-check-label fw-semibold small" for="strongPwSwitch">
                                        Require Strong Password
                                        <div class="text-muted fw-normal" style="font-size: 11px;">Enforce combinations of letters, numbers, and special characters.</div>
                                    </label>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-check form-switch p-2 rounded bg-light border">
                                    <input class="form-check-input ms-0 me-3" type="checkbox" name="settings[admin_login_security]" value="1" id="adminSecSwitch" <?php echo !empty($securitySettings['admin_login_security']) ? 'checked' : ''; ?>>
                                    <label class="form-check-label fw-semibold small" for="adminSecSwitch">
                                        Enforce Admin Elevated Security
                                        <div class="text-muted fw-normal" style="font-size: 11px;">Strict IP logging and session rotation upon admin authentication.</div>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="border-top pt-3 d-flex justify-content-end">
                            <button type="submit" class="btn btn-save-settings">
                                <i class="fa-solid fa-floppy-disk"></i> Save Security Settings
                            </button>
                        </div>
                    </form>
                </div>

                <!-- ── 7. SYSTEM INFORMATION TAB ── -->
                <div class="tab-pane fade <?php echo $active_tab === 'system' ? 'show active' : ''; ?>" id="pane-system" role="tabpanel">
                    <div class="form-section-title"><i class="fa-solid fa-server text-success me-1"></i> Technical Diagnostics &amp; Runtime Environment</div>
                    <div class="form-section-desc">Active server specs, database engine version, memory allocation, and software release details.</div>

                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <div class="info-pill">
                                <span class="text-muted small d-block">PHP Version</span>
                                <strong class="fs-5 text-dark"><?php echo htmlspecialchars($systemInfo['php_version']); ?></strong>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="info-pill">
                                <span class="text-muted small d-block">MySQL / MariaDB Version</span>
                                <strong class="fs-5 text-dark"><?php echo htmlspecialchars($systemInfo['mysql_version']); ?></strong>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="info-pill">
                                <span class="text-muted small d-block">Application Release</span>
                                <strong class="fs-5 text-success"><?php echo htmlspecialchars($systemInfo['app_version']); ?></strong>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="info-pill">
                                <span class="text-muted small d-block">Database Name</span>
                                <strong class="fs-6 text-dark font-monospace"><?php echo htmlspecialchars($systemInfo['database_name']); ?></strong>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="info-pill">
                                <span class="text-muted small d-block">Upload Max Filesize</span>
                                <strong class="fs-6 text-dark font-monospace"><?php echo htmlspecialchars($systemInfo['upload_max_filesize']); ?> (POST: <?php echo htmlspecialchars($systemInfo['post_max_size']); ?>)</strong>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="info-pill">
                                <span class="text-muted small d-block">PHP Memory Limit</span>
                                <strong class="fs-6 text-dark font-monospace"><?php echo htmlspecialchars($systemInfo['memory_limit']); ?></strong>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-pill">
                                <span class="text-muted small d-block">Web Server Software</span>
                                <div class="text-dark small font-monospace mt-1"><?php echo htmlspecialchars($systemInfo['server_software']); ?></div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-pill">
                                <span class="text-muted small d-block">Host OS &amp; System Time</span>
                                <div class="text-dark small mt-1"><strong><?php echo htmlspecialchars($systemInfo['os']); ?></strong> &bull; <?php echo htmlspecialchars($systemInfo['server_time']); ?></div>
                            </div>
                        </div>
                    </div>

                    <div class="border-top pt-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <span class="small text-muted"><i class="fa-solid fa-circle-check text-success me-1"></i> All PDO Database Subsystems Online &amp; Synchronized</span>
                        <a href="setting.php" class="btn btn-outline-success btn-sm px-3 fw-semibold">
                            <i class="fa-solid fa-database me-1"></i> Manage Database Backups &rarr;
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Sync URL tab on click
        document.querySelectorAll('#adminSettingsTabs button[data-bs-toggle="tab"]').forEach(function(tabBtn) {
            tabBtn.addEventListener('shown.bs.tab', function(e) {
                var targetId = e.target.getAttribute('data-bs-target').replace('#pane-', '');
                if (history.replaceState) {
                    var newUrl = window.location.protocol + "//" + window.location.host + window.location.pathname + '?tab=' + targetId;
                    window.history.replaceState({ path: newUrl }, '', newUrl);
                }
            });
        });
    </script>
</body>
</html>
