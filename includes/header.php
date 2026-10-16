<?php
    if (isset($_GET['action']) && $_GET['action'] === 'logout') {
        $_SESSION = array();
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();
        header("Location: index.php");
        exit();
    }

    $current_script = $_SERVER['SCRIPT_NAME'] ?? '';
    $current_dir = basename(dirname($current_script));
    $is_subfolder = in_array($current_dir, ['user', 'admin', 'ajax', 'auction']);
    $root_path  = $is_subfolder ? '../' : './';
    $user_path  = $is_subfolder ? ($current_dir === 'user' ? './' : '../user/') : 'user/';
    $admin_path = $is_subfolder ? ($current_dir === 'admin' ? './' : '../admin/') : 'admin/';
    $base_url   = $user_path;
    include_once __DIR__ . '/database.php';
    include_once __DIR__ . '/auth.php';
    include_once __DIR__ . '/functions.php';
    $is_logged = is_logged_in();
    $user_role = strtolower(trim($_SESSION['role'] ?? $_SESSION['user_role'] ?? 'guest'));
    $is_admin  = $is_logged && ($user_role === 'admin');

    if (!isset($require_auth) || $require_auth !== false) {
        if (!$is_logged) {
            header("Location: " . $root_path . "login.php");
            exit();
        }
    }

    $user_name     = $_SESSION['user_name'] ?? ($_SESSION['name'] ?? 'User');
    $user_avatar   = $_SESSION['user_avatar'] ?? ($_SESSION['avatar'] ?? '');
    $user_initials = strtoupper(substr($user_name, 0, 2));
    $user_balance = 0.00;
    if ($is_logged) {
        $_uid_hdr = (int)$_SESSION['user_id'];
        try {
            $pdo_hdr = Database::getPdo();
            $stmt_bal = $pdo_hdr->prepare("SELECT balance FROM users WHERE id = :id LIMIT 1");
            $stmt_bal->execute([':id' => $_uid_hdr]);
            $user_balance = (float)($stmt_bal->fetchColumn() ?? 0.0);
        } catch (\Throwable $e) {
            $user_balance = 0.00;
        }
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Geo Auction System</title>
    <meta name="csrf-token" content="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
    <!-- Immediate Theme Initialization Script (Prevents FOUC / Flashing) -->
    <script>
        (function() {
            var theme = localStorage.getItem('geo_theme') || 'dark';
            if (theme === 'dark') {
                document.documentElement.classList.add('dark-theme');
                document.documentElement.classList.remove('light-theme');
            } else {
                document.documentElement.classList.add('light-theme');
                document.documentElement.classList.remove('dark-theme');
            }
        })();
    </script>
    <link rel="stylesheet" href="<?= $root_path; ?>assets/css/main.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">

    <style>
        :root {
            --green: #007a33;
            --green-dark: #005f28;
            --green-light: #e8f5ec;
            --gold: #c4a000;
            --gold-light: #fdf6e0;
            --red: #e2001a;
            --dark: #1a1a2e;
            --sidebar-bg: #0f1923;
            --sidebar-hover: #162533;
            --sidebar-active: #1a3040;
            --text: #333;
            --text-muted: #888;
            --border: #e8e8e8;
            --white: #fff;
            --radius: 8px;
        }

        html.dark-theme {
            --white: #1e293b;
            --border: #334155;
            --text: #f1f5f9;
            --text-muted: #94a3b8;
            --dark: #f8fafc;
            --green-light: rgba(16, 185, 129, 0.15);
            --sidebar-bg: #0b0f19;
            --sidebar-hover: #151e2e;
            --sidebar-active: #1e293b;
        }

        html.dark-theme body {
            background: #0b0f19;
            color: #f1f5f9;
        }

        html.dark-theme .navbar {
            background: #0f172a;
            border-color: #1e293b;
        }

        html.dark-theme .nav-search input {
            background: #0b0f19;
            border-color: #334155;
            color: #f1f5f9;
        }

        html.dark-theme .nav-location-btn {
            background: #1e293b;
            border-color: #334155;
            color: #f1f5f9;
        }

        html.dark-theme .location-dropdown-menu,
        html.dark-theme .profile-dropdown-menu {
            background: #0f172a;
            border-color: #1e293b;
            box-shadow: 0 8px 25px rgba(0,0,0,0.5);
        }

        html.dark-theme .location-dropdown-item,
        html.dark-theme .profile-dropdown-item {
            color: #f1f5f9;
        }

        html.dark-theme .location-dropdown-item:hover,
        html.dark-theme .profile-dropdown-item:hover,
        html.dark-theme .nav-user:hover {
            background: #1e293b;
        }

        html.dark-theme .nav-icon-btn {
            color: #94a3b8;
        }

        html.dark-theme .nav-icon-btn:hover {
            background: rgba(16, 185, 129, 0.15);
            color: #10b981;
        }

        html.dark-theme .nav-divider {
            background: #334155;
        }

        html.dark-theme .theme-icon-light { display: inline-block !important; }
        html.dark-theme .theme-icon-dark { display: none !important; }
        html.light-theme .theme-icon-light { display: none !important; }
        html.light-theme .theme-icon-dark { display: inline-block !important; }

        body, .navbar, .nav-search input, .nav-location-btn, .sidebar, .card {
            transition: background-color 0.25s ease, color 0.25s ease, border-color 0.25s ease;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background: #f0f2f5;
            color: var(--text);
            overflow-x: hidden;
        }

        a { text-decoration: none; color: inherit; }
        ul { list-style: none; }

        /* ── NAVBAR ── */
        .navbar {
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            right: 0 !important;
            height: 64px !important;
            min-height: 64px !important;
            max-height: 64px !important;
            background: var(--white) !important;
            border-bottom: 1px solid var(--border) !important;
            display: flex !important;
            align-items: center !important;
            padding: 0 24px !important;
            z-index: 1000 !important;
            gap: 16px !important;
            box-sizing: border-box !important;
            flex-wrap: nowrap !important;
            margin: 0 !important;
        }

        .navbar::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            right: 0;
            height: 2px;
            background: linear-gradient(90deg, var(--green), var(--gold), var(--red));
        }

        .sidebar-toggle {
            display: none;
            width: 40px;
            height: 40px;
            border: none;
            background: var(--green-light);
            border-radius: var(--radius);
            cursor: pointer;
            font-size: 18px;
            color: var(--green);
            transition: 0.2s;
            flex-shrink: 0;
        }
        .sidebar-toggle:hover { background: var(--green); color: var(--white); }

        .nav-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-shrink: 0;
        }
        #logo {
            height: 40px !important;
            max-height: 44px !important;
            width: auto !important;
            object-fit: contain !important;
            display: block !important;
        }

        .nav-search {
            flex: 1;
            max-width: 480px;
            margin: 0 auto;
            position: relative;
        }

        .nav-search form {
            display: flex;
            align-items: center;
            width: 100%;
            position: relative;
        }

        .nav-search input {
            width: 100%;
            height: 42px;
            border: 2px solid var(--border);
            border-radius: 50px;
            padding: 0 44px 0 18px;
            font-size: 14px;
            outline: none;
            transition: 0.3s;
            background: #f8f9fa;
        }

        .nav-search input:focus {
            border-color: var(--green);
            background: var(--white);
            box-shadow: 0 0 0 4px rgba(0,122,51,0.08);
        }

        .nav-search button {
            position: absolute;
            right: 4px;
            top: 4px;
            width: 34px;
            height: 34px;
            border: none;
            background: var(--green);
            color: var(--white);
            border-radius: 50%;
            cursor: pointer;
            font-size: 14px;
            transition: 0.2s;
        }

        .nav-search button:hover { background: var(--green-dark); transform: scale(1.05); }

        /* ── LOCATION SELECTOR STYLES ── */
        .nav-location-wrapper {
            position: relative;
            display: inline-block;
        }

        .nav-location-btn {
            display: flex;
            align-items: center;
            gap: 8px;
            background: #f8f9fa;
            border: 1px solid var(--border);
            padding: 8px 14px;
            border-radius: 50px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            color: var(--dark);
            transition: 0.2s;
            white-space: nowrap;
        }

        .nav-location-btn:hover {
            background: #f0f0f0;
            border-color: #d0d0d0;
        }

        .nav-location-btn i.fa-location-dot {
            color: var(--red);
        }

        .location-dropdown-menu {
            display: none;
            position: absolute;
            right: 0;
            top: 45px;
            width: 200px;
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
            z-index: 1100;
            overflow: hidden;
        }

        .location-dropdown-menu.show {
            display: block;
        }

        .location-dropdown-item {
            padding: 10px 16px;
            font-size: 13px;
            color: var(--text);
            cursor: pointer;
            transition: background 0.2s;
        }

        .location-dropdown-item:hover {
            background: #f8f9fa;
            color: var(--green);
        }

        .nav-actions {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-shrink: 0;
        }

        .nav-icon-btn {
            width: 40px;
            height: 40px;
            border: none;
            background: transparent;
            border-radius: var(--radius);
            cursor: pointer;
            font-size: 18px;
            color: #555;
            position: relative;
            transition: 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .nav-icon-btn:hover { background: var(--green-light); color: var(--green); }

        .nav-divider {
            width: 1px;
            height: 28px;
            background: var(--border);
            margin: 0 8px;
        }

        /* ── PROFILE DROPDOWN STYLES ── */
        .profile-dropdown-wrapper {
            position: relative;
            display: inline-block;
        }

        .nav-user {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 4px 12px 4px 4px;
            border-radius: 50px;
            cursor: pointer;
            transition: 0.2s;
        }

        .nav-user:hover { background: #f0f0f0; }

        .nav-user-avatar {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background: var(--dark);
            border: 2px solid var(--dark);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--white);
            font-weight: 700;
            font-size: 14px;
            overflow: hidden;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            transition: transform 0.2s ease;
        }

        .nav-user-avatar:hover {
            transform: scale(1.05);
        }

        .nav-user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .nav-user-info { line-height: 1.2; }
        .nav-user-name { font-size: 13px; font-weight: 600; color: var(--dark); }
        .nav-user-role { font-size: 11px; color: var(--text-muted); text-transform: capitalize; margin-top: 2px; }

        .profile-dropdown-menu {
            display: none;
            position: absolute;
            right: 0;
            top: 50px;
            width: 180px;
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
            z-index: 1100;
            overflow: hidden;
        }

        .profile-dropdown-menu.show {
            display: block;
        }

        .profile-dropdown-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 16px;
            font-size: 14px;
            color: var(--text);
            transition: background 0.2s;
        }

        .profile-dropdown-item:hover {
            background: #f8f9fa;
        }

        .profile-dropdown-item.logout {
            color: var(--red);
            border-top: 1px solid var(--border);
        }

        .profile-dropdown-item.logout:hover {
            background: #fdf2f2;
        }

        /* ── LAYOUT ── */
        .layout {
            display: flex;
            margin-top: 64px;
            min-height: calc(100vh - 64px);
        }

        /* ── SIDEBAR ── */
        .sidebar {
            height: calc(100vh - 64px);
            background: linear-gradient(180deg, #1a1d21 0%, #212529 100%);
            color: #fff;
            position: fixed;
            top: 64px;
            left: 0;
            width: 250px;
            padding-top: 10px;
            z-index: 100;
            overflow-y: auto;
        }

        .sidebar-section {
            padding: 15px 0;
            border-bottom: 1px solid rgba(255,255,255,0.06);
        }

        .sidebar-section:last-child { border-bottom: none; }

        .sidebar-label {
            padding: 0 20px;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            color: #4a6a7a;
            margin-bottom: 8px;
        }

        .sidebar-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 20px;
            font-size: 14px;
            font-weight: 500;
            color: #8fa8b8;
            cursor: pointer;
            transition: 0.2s;
            text-decoration: none;
            width: 100%;
        }

        .sidebar-link:hover {
            background: var(--sidebar-hover);
            color: #d0e0e8;
        }

        .sidebar-link.active {
            background: var(--sidebar-active);
            color: var(--white);
        }

        .sidebar-link i {
            width: 20px;
            text-align: center;
            font-size: 15px;
            flex-shrink: 0;
        }

        .main-content {
            flex: 1;
            margin-left: 250px;
            padding: 24px;
            min-width: 0;
        }

        @media (max-width: 992px) {
            .sidebar-toggle { display: flex; align-items: center; justify-content: center; }
            .sidebar { transform: translateX(-100%); transition: transform 0.3s ease; }
            .sidebar.open { transform: translateX(0); }
            .main-content { margin-left: 0; }
        }
    </style>
</head>
<body>
    <?php include_once __DIR__ . '/components/loading_spinner_overlay.php'; ?>
    <nav class="navbar">
        <button class="sidebar-toggle" onclick="toggleSidebar()" aria-label="Toggle sidebar">
            <i class="fa-solid fa-bars"></i>
        </button>
        <a href="<?php echo $root_path; ?>dashboard.php" class="nav-brand">
            <img id="logo" src="<?php echo $root_path; ?>assets/image/Geo-auction-logo-clean.png" alt="Geo Auction Logo" style="height: 40px; width: auto; object-fit: contain;" onerror="this.onerror=null; this.src='<?php echo $root_path; ?>assets/image/Geo-auction.png';">
        </a> 
        <div class="nav-search">
            <form action="<?php echo $root_path; ?>search.php" method="GET">
                <input type="text" name="q" placeholder="Search auctions, items, categories...">
                <button type="submit"><i class="fa-solid fa-magnifying-glass"></i></button>
            </form>
        </div>
        <div class="nav-actions">
            <!-- Theme Toggle Button -->
            <button type="button" class="nav-icon-btn" id="themeToggleBtn" onclick="toggleTheme()" title="Toggle Light / Dark Theme" style="border:none;background:none;cursor:pointer;">
                <i class="fa-solid fa-sun theme-icon-light" style="color: #f59e0b;"></i>
                <i class="fa-solid fa-moon theme-icon-dark" style="color: #64748b;"></i>
            </button>

            <!-- Admin Badge in Navbar (Only for Admin) -->
            <?php if ($is_admin): ?>
                <a href="<?php echo $admin_path; ?>dashboard.php" class="admin-badge-nav" title="Admin Control Panel" style="background:#dc2626;color:#fff;font-size:11px;font-weight:700;padding:4px 10px;border-radius:20px;text-decoration:none;display:inline-flex;align-items:center;gap:6px;">
                    <i class="fa-solid fa-shield-halved"></i> Admin
                </a>
            <?php endif; ?>

            <?php if ($is_logged): ?>
                <a href="<?php echo $user_path; ?>wallet.php" class="nav-icon-btn" title="Wallet — Rs. <?php echo number_format($user_balance, 2); ?>" style="position:relative;">
                    <i class="fa-solid fa-wallet"></i>
                    <span style="position:absolute;top:-2px;right:-2px;background:var(--green);color:#fff;font-size:9px;font-weight:700;border-radius:10px;padding:1px 5px;min-width:18px;text-align:center;"><?php echo ($user_balance >= 1000 ? round($user_balance/1000,1).'k' : number_format($user_balance,0)); ?></span>
                </a>

                <!-- Navbar Message Icon with Unread Count Badge -->
                <a href="<?php echo $root_path; ?>messages.php" class="nav-icon-btn message-icon" title="Messages" style="position:relative;">
                    <i class="fa-solid fa-message"></i>
                    <span id="messageBadge" class="message-badge" style="display:none;position:absolute;top:-2px;right:-2px;background:#e67e22;color:#fff;font-size:9px;font-weight:700;border-radius:10px;padding:1px 5px;min-width:18px;text-align:center;box-shadow:0 2px 4px rgba(0,0,0,0.2);">0</span>
                </a>

                <!-- Navbar Notification Bell with Interactive Dropdown -->
                <div class="nav-notification-wrapper" style="position:relative;display:inline-block;">
                    <button type="button" class="nav-icon-btn" id="notifBellBtn" onclick="toggleNotificationDropdown(event)" title="Notifications" style="position:relative;background:none;border:none;cursor:pointer;">
                        <i class="fa-regular fa-bell"></i>
                        <span id="notificationBadge" class="notification-badge" style="display:none;position:absolute;top:-2px;right:-2px;background:#dc2626;color:#fff;font-size:9px;font-weight:700;border-radius:10px;padding:1px 5px;min-width:18px;text-align:center;box-shadow:0 2px 4px rgba(0,0,0,0.2);">0</span>
                    </button>
                    <div class="notification-dropdown-menu" id="notificationDropdownMenu" style="display:none;position:absolute;right:0;top:48px;width:340px;background:var(--white);border:1px solid var(--border);border-radius:14px;box-shadow:0 12px 32px rgba(0,0,0,0.18);z-index:1200;overflow:hidden;animation:fadeIn 0.2s ease;">
                        <div style="padding:12px 16px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;background:rgba(0,0,0,0.02);">
                            <div style="font-size:13px;font-weight:700;color:var(--text);display:flex;align-items:center;gap:6px;">
                                <i class="fa-regular fa-bell text-emerald-600"></i> Notifications
                            </div>
                            <button type="button" onclick="markAllNotificationsAsReadHeader(event)" style="background:none;border:none;font-size:11px;color:var(--green);font-weight:700;cursor:pointer;padding:2px 6px;">
                                Mark all read
                            </button>
                        </div>
                        <div id="headerNotificationList" style="max-height:340px;overflow-y:auto;padding:4px 0;">
                            <div style="padding:24px;text-align:center;color:var(--text-muted);font-size:12px;">Loading notifications...</div>
                        </div>
                        <div style="padding:10px 16px;border-top:1px solid var(--border);text-align:center;background:rgba(0,0,0,0.01);">
                            <a href="<?php echo $user_path; ?>notifications.php" style="font-size:12px;font-weight:700;color:var(--green);text-decoration:none;">
                                View All Notifications →
                            </a>
                        </div>
                    </div>
                </div>

                <a href="<?php echo $user_path; ?>wishlist.php" class="nav-icon-btn" title="Watchlist">
                    <i class="fa-regular fa-heart"></i>
                </a>
                
                <?php if ($is_logged && in_array($user_role, ['seller', 'admin'])): ?>
                    <a href="<?php echo $user_path; ?>add_items.php" class="btn-list-item-nav" title="List a new auction item" style="background: rgb(4 120 87); color: #ffffff; font-size: 12px; font-weight: 700; padding: 6px 14px; border-radius: 6px; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; transition: 0.2s ease; box-shadow: 0 2px 6px rgba(4,120,87,0.25);">
                        <i class="fa-solid fa-plus"></i> <span>List Item</span>
                    </a>
                <?php endif; ?>

                <div class="nav-divider"></div>
                <div class="profile-dropdown-wrapper">
                    <div class="nav-user" onclick="toggleProfileDropdown(event)">
                        <div class="nav-user-avatar">
                            <?php if (!empty($user_avatar)): ?>
                                <img src="<?php echo $root_path . $user_avatar; ?>" alt="<?php echo htmlspecialchars($user_name); ?>">
                            <?php else: ?>
                                <?php echo $user_initials; ?>
                            <?php endif; ?>
                        </div>
                        <div class="nav-user-info">
                            <div class="nav-user-name"><?php echo htmlspecialchars($user_name); ?></div>
                            <div class="nav-user-role"><?php echo htmlspecialchars($user_role); ?></div>
                        </div>
                        <i class="fa-solid fa-chevron-down" style="font-size: 10px; color: #777; margin-left: 2px;"></i>
                    </div>
                    <div class="profile-dropdown-menu" id="profileDropdown">
                        <?php if ($is_logged && in_array($user_role, ['seller', 'admin'])): ?>
                            <a href="<?php echo $user_path; ?>add_items.php" class="profile-dropdown-item" style="color: #4df08a; font-weight: 600;">
                                <i class="fa-solid fa-plus-circle"></i> + Add New Item
                            </a>
                        <?php endif; ?>
                        <a href="<?php echo $root_path; ?>profile.php" class="profile-dropdown-item">
                            <i class="fa-solid fa-user"></i> Profile
                        </a>
                        <a href="<?php echo $user_path; ?>settings.php" class="profile-dropdown-item">
                            <i class="fa-solid fa-gear"></i> Account Settings
                        </a>
                        <a href="<?php echo $user_path; ?>dashboard.php" class="profile-dropdown-item">
                            <i class="fa-solid fa-house"></i> User Dashboard
                        </a>

                        <?php if ($is_admin): ?>
                            <a href="<?php echo $admin_path; ?>dashboard.php" class="profile-dropdown-item" style="color: #fca5a5;">
                                <i class="fa-solid fa-user-shield"></i> Admin Panel
                            </a>
                            <a href="<?php echo $admin_path; ?>settings.php" class="profile-dropdown-item" style="color: #fca5a5;">
                                <i class="fa-solid fa-sliders"></i> Platform Settings
                            </a>
                        <?php endif; ?>
                        <a href="<?php echo $root_path; ?>logout.php" class="profile-dropdown-item logout">
                            <i class="fa-solid fa-right-from-bracket"></i> Logout
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <a href="<?php echo $root_path; ?>login.php" class="sidebar-link" style="padding:6px 12px;font-weight:600;">Login</a>
                <a href="<?php echo $root_path; ?>register.php" class="admin-badge-nav" style="background:#007a33;">Register</a>
            <?php endif; ?>

        </div>
    </nav>
    <div class="layout">
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-section">
                <div class="sidebar-label">Main Navigation</div>
                <a href="<?php echo $root_path; ?>dashboard.php" class="sidebar-link">
                    <i class="fa-solid fa-house"></i> Dashboard
                </a>
                <a href="<?php echo $root_path; ?>live_auction.php" class="sidebar-link">
                    <i class="fa-solid fa-fire"></i> Live Auctions
                </a>
                <?php if ($is_logged): ?>
                    <a href="<?php echo $user_path; ?>my_bids.php" class="sidebar-link">
                        <i class="fa-solid fa-gavel"></i> My Bids
                    </a>
                    <a href="<?php echo $user_path; ?>wishlist.php" class="sidebar-link">
                        <i class="fa-solid fa-heart"></i> Watchlist
                    </a>
                <?php endif; ?>
            </div>

            <?php if ($is_logged): ?>
                <div class="sidebar-section">
                    <div class="sidebar-label">Seller Portal</div>
                    <a href="<?php echo $user_path; ?>my_auctions.php" class="sidebar-link">
                        <i class="fa-solid fa-box-archive"></i> My Listings
                    </a>
                    <a href="<?php echo $root_path; ?>auction/settings.php" class="sidebar-link">
                        <i class="fa-solid fa-sliders"></i> Auction Settings
                    </a>
                </div>
                <div class="sidebar-section">
                    <div class="sidebar-label">Wallet</div>
                    <a href="<?php echo $user_path; ?>wallet.php" class="sidebar-link">
                        <i class="fa-solid fa-wallet"></i> My Wallet
                        <span style="margin-left:auto;font-size:11px;font-weight:700;color:#4df08a;">Rs. <?php echo number_format($user_balance, 2); ?></span>
                    </a>
                    <a href="<?php echo $user_path; ?>won_auctions.php" class="sidebar-link">
                        <i class="fa-solid fa-trophy"></i> Won Auctions
                    </a>
                </div>
            <?php endif; ?>

            <?php if ($is_admin): ?>
            <div class="sidebar-section">
                <div class="sidebar-label" style="color:#f87171;">Admin Portal</div>
                <a href="<?php echo $admin_path; ?>dashboard.php" class="sidebar-link" style="color:#f87171;">
                    <i class="fa-solid fa-user-shield"></i> Admin Dashboard
                </a>
                <a href="<?php echo $admin_path; ?>settings.php" class="sidebar-link" style="color:#f87171;">
                    <i class="fa-solid fa-sliders"></i> Platform Settings
                </a>
                <a href="<?php echo $admin_path; ?>setting.php" class="sidebar-link">
                    <i class="fa-solid fa-database"></i> Database & Backup
                </a>
            </div>
            <?php endif; ?>

            <div class="sidebar-section">
                <div class="sidebar-label">Account</div>
                <?php if ($is_logged): ?>
                    <a href="<?php echo $root_path; ?>profile.php" class="sidebar-link">
                        <i class="fa-solid fa-user"></i> My Profile
                    </a>
                    <a href="<?php echo $user_path; ?>settings.php" class="sidebar-link">
                        <i class="fa-solid fa-gear"></i> Account Settings
                    </a>
                    <a href="<?php echo $root_path; ?>settings.php?section=backup" class="sidebar-link">
                        <i class="fa-solid fa-database"></i> Database Backup
                    </a>
                    <a href="<?php echo $root_path; ?>logout.php" class="sidebar-link" style="color: #f87171;">
                        <i class="fa-solid fa-right-from-bracket"></i> Logout
                    </a>
                <?php else: ?>
                    <a href="<?php echo $root_path; ?>login.php" class="sidebar-link">
                        <i class="fa-solid fa-right-to-bracket"></i> Login
                    </a>
                    <a href="<?php echo $root_path; ?>register.php" class="sidebar-link">
                        <i class="fa-solid fa-user-plus"></i> Register
                    </a>
                <?php endif; ?>
            </div>
        </aside>

        <main class="main-content">
        <script>
            function toggleSidebar() {
                document.getElementById('sidebar').classList.toggle('open');
            }

            // Profile Dropdown Toggle Function
            function toggleProfileDropdown(event) {
                event.stopPropagation();
                const dropdown = document.getElementById('profileDropdown');
                // Close location dropdown if open
                document.getElementById('locationDropdown')?.classList.remove('show');
                dropdown.classList.toggle('show');
            }
            function toggleLocationDropdown(event) {
                event.stopPropagation();
                const dropdown = document.getElementById('locationDropdown');
                document.getElementById('profileDropdown')?.classList.remove('show');
                dropdown.classList.toggle('show');
            }

            function selectLocation(city) {
                const span = document.querySelector('.nav-location-btn span');
                if(span) span.textContent = city;
            }
            function toggleTheme() {
                var isDark = document.documentElement.classList.contains('dark-theme');
                if (isDark) {
                    document.documentElement.classList.remove('dark-theme');
                    document.documentElement.classList.add('light-theme');
                    localStorage.setItem('geo_theme', 'light');
                } else {
                    document.documentElement.classList.remove('light-theme');
                    document.documentElement.classList.add('dark-theme');
                    localStorage.setItem('geo_theme', 'dark');
                }
            }
            // Notification Dropdown Toggle Function
            function toggleNotificationDropdown(event) {
                event.stopPropagation();
                const dropdown = document.getElementById('notificationDropdownMenu');
                document.getElementById('profileDropdown')?.classList.remove('show');
                document.getElementById('locationDropdown')?.classList.remove('show');

                if (dropdown) {
                    const isVisible = dropdown.style.display === 'block';
                    dropdown.style.display = isVisible ? 'none' : 'block';
                    if (!isVisible) {
                        loadHeaderNotifications();
                    }
                }
            }

            async function loadHeaderBadges() {
                try {
                    const root = '<?php echo $root_path; ?>';
                    const res = await fetch(root + 'get_unread_count.php');
                    const data = await res.json();
                    if (data && data.success) {
                        // Message Badge
                        const msgBadge = document.getElementById('messageBadge');
                        if (msgBadge) {
                            if (data.unread_messages > 0) {
                                msgBadge.textContent = data.unread_messages;
                                msgBadge.style.display = 'inline-block';
                            } else {
                                msgBadge.style.display = 'none';
                            }
                        }
                        // Notification Badge
                        const notifBadge = document.getElementById('notificationBadge');
                        if (notifBadge) {
                            if (data.unread_notifications > 0) {
                                notifBadge.textContent = data.unread_notifications;
                                notifBadge.style.display = 'inline-block';
                            } else {
                                notifBadge.style.display = 'none';
                            }
                        }
                    }
                } catch (e) {
                    // Fail silently on network glitch
                }
            }

            async function loadHeaderNotifications() {
                const list = document.getElementById('headerNotificationList');
                if (!list) return;

                try {
                    const root = '<?php echo $root_path; ?>';
                    const res = await fetch(root + 'get_notifications.php?limit=10');
                    const data = await res.json();

                    if (data && data.success) {
                        const notifs = data.notifications || [];
                        if (notifs.length === 0) {
                            list.innerHTML = '<div style="padding:24px;text-align:center;color:var(--text-muted);font-size:12px;">No notifications yet.</div>';
                            return;
                        }

                        let html = '';
                        notifs.forEach(n => {
                            const isUnread = (n.is_read === 0);
                            const bgStyle = isUnread ? 'background:rgba(4, 120, 87, 0.06);' : 'background:transparent;';
                            const linkAttr = n.link ? `onclick="handleNotificationClick(${n.id}, '${n.link.replace(/'/g, "\\'")}')"` : `onclick="handleNotificationClick(${n.id}, '')"`;

                            html += `
                                <div style="display:flex;align-items:flex-start;gap:10px;padding:10px 14px;border-bottom:1px solid rgba(0,0,0,0.05);cursor:pointer;transition:background 0.15s;${bgStyle}" 
                                     onmouseover="this.style.background='rgba(0,0,0,0.03)'" 
                                     onmouseout="this.style.background='${isUnread ? 'rgba(4, 120, 87, 0.06)' : 'transparent'}'"
                                     ${linkAttr}>
                                    <div style="width:30px;height:30px;border-radius:50%;background:rgba(4, 120, 87, 0.15);color:#047857;display:flex;align-items:center;justify-content:center;font-size:13px;flex-shrink:0;">
                                        <i class="${n.icon || 'fa-solid fa-bell'}"></i>
                                    </div>
                                    <div style="flex:1;min-width:0;">
                                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:2px;">
                                            <span style="font-size:12px;font-weight:700;color:var(--text);">${n.title}</span>
                                            <span style="font-size:10px;color:var(--text-muted);">${n.time_ago}</span>
                                        </div>
                                        <div style="font-size:11px;color:var(--text-muted);line-height:1.4;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;">
                                            ${n.message}
                                        </div>
                                    </div>
                                    ${isUnread ? '<span style="width:7px;height:7px;border-radius:50%;background:#047857;margin-top:6px;flex-shrink:0;"></span>' : ''}
                                </div>`;
                        });

                        list.innerHTML = html;
                    }
                } catch (e) {
                    list.innerHTML = '<div style="padding:20px;text-align:center;color:#ef4444;font-size:12px;">Failed to load notifications.</div>';
                }
            }

            async function handleNotificationClick(nid, link) {
                try {
                    const root = '<?php echo $root_path; ?>';
                    await fetch(root + 'mark_as_read.php?notification_id=' + nid);
                    loadHeaderBadges();
                } catch (e) {}

                if (link && link.trim() !== '') {
                    window.location.href = link;
                } else {
                    loadHeaderNotifications();
                }
            }

            async function markAllNotificationsAsReadHeader(event) {
                event.stopPropagation();
                try {
                    const root = '<?php echo $root_path; ?>';
                    await fetch(root + 'mark_as_read.php?mark_all_notifications=1');
                    loadHeaderBadges();
                    loadHeaderNotifications();
                } catch (e) {}
            }

            window.addEventListener('click', function() {
                const profileDropdown = document.getElementById('profileDropdown');
                if (profileDropdown && profileDropdown.classList.contains('show')) {
                    profileDropdown.classList.remove('show');
                }
                const locationDropdown = document.getElementById('locationDropdown');
                if (locationDropdown && locationDropdown.classList.contains('show')) {
                    locationDropdown.classList.remove('show');
                }
                const notifDropdown = document.getElementById('notificationDropdownMenu');
                if (notifDropdown && notifDropdown.style.display === 'block') {
                    notifDropdown.style.display = 'none';
                }
            });

            // Start polling badges if user is logged in
            <?php if ($is_logged): ?>
            document.addEventListener('DOMContentLoaded', function() {
                loadHeaderBadges();
                setInterval(loadHeaderBadges, 5000);
            });
            <?php endif; ?>

            // Intercept all logout clicks to display the loading spinner immediately
            document.addEventListener('click', function(e) {
                const logoutLink = e.target.closest('a[href*="logout"], .logout');
                if (logoutLink && !logoutLink.hasAttribute('data-no-loader')) {
                    const targetHref = logoutLink.getAttribute('href');
                    if (targetHref && targetHref !== '#' && !targetHref.startsWith('javascript:')) {
                        e.preventDefault();
                        if (typeof showLoader === 'function') {
                            showLoader('Logging out, please wait...');
                        }
                        setTimeout(function() {
                            window.location.href = targetHref;
                        }, 250);
                    }
                }
            });
        </script>