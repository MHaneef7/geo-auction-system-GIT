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
    $base_url = file_exists('includes/database.php') ? '' : 'user/';
        include_once __DIR__ . '/database.php';
        include_once __DIR__ . '/auth.php';
        include_once __DIR__ . '/functions.php';

    if (!is_logged_in()) {
        header("Location: " . $base_url . "login.php");
        exit();
    }
    
    $user_name     = $_SESSION['user_name'] ?? 'User';
    $user_role     = $_SESSION['role'] ?? 'Member';
    $user_avatar   = $_SESSION['user_avatar'] ?? '';
    $user_initials = strtoupper(substr($user_name, 0, 2));

    $_uid_hdr = (int)$_SESSION['user_id'];
    $stmt_bal = mysqli_prepare($conn, "SELECT balance FROM users WHERE id = ?");
    mysqli_stmt_bind_param($stmt_bal, "i", $_uid_hdr);
    mysqli_stmt_execute($stmt_bal);
    $balance_res = mysqli_stmt_get_result($stmt_bal);
    $user_balance = (float)(mysqli_fetch_assoc($balance_res)['balance'] ?? 0);
    mysqli_stmt_close($stmt_bal);
    $current_script = $_SERVER['SCRIPT_NAME'] ?? '';
    $current_dir = basename(dirname($current_script));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Geo Auction System</title>
    <link rel="stylesheet" href="../assets/css/main.css">
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
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 64px;
            background: var(--white);
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            padding: 0 24px;
            z-index: 1000;
            gap: 16px;
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
    <?php
    if ($current_dir === 'admin' || $current_dir === 'user') {
            $base_url = '../user/';
        } else {
            $base_url = 'user/';
        } 
    ?>
    <nav class="navbar">
        <button class="sidebar-toggle" onclick="toggleSidebar()" aria-label="Toggle sidebar">
            <i class="fa-solid fa-bars"></i>
        </button>
        <a href="<?php echo $base_url; ?>dashboard.php" class="nav-brand">
            <img id="logo" src="<?php echo ($current_dir === 'admin' || $current_dir === 'user') ? '../assets/image/Geo-auction.png' : 'assets/image/Geo-auction.png'; ?>" alt="Geo Auction Logo" height="38" style="height: 180px; object-fit:contain;">
        </a> 
        <div class="nav-search">
            <form>
                <input type="text" placeholder="Search auctions, items, categories...">
                <button type="submit"><i class="fa-solid fa-magnifying-glass"></i></button>
            </form>
        </div>
        <div class="nav-location-wrapper">
            <div class="nav-location-btn" onclick="toggleLocationDropdown(event)">
                <i class="fa-solid fa-location-dot"></i>
                <span>Karachi, PK</span>
                <i class="fa-solid fa-chevron-down" style="font-size: 10px; color: #777;"></i>
            </div>
            <div class="location-dropdown-menu" id="locationDropdown">
                <div class="location-dropdown-item" onclick="selectLocation('Karachi, PK')">Karachi, PK</div>
                <div class="location-dropdown-item" onclick="selectLocation('Lahore, PK')">Lahore, PK</div>
                <div class="location-dropdown-item" onclick="selectLocation('Islamabad, PK')">Islamabad, PK</div>
                <div class="location-dropdown-item" onclick="selectLocation('Peshawar, PK')">Peshawar, PK</div>
            </div>
        </div>
        <div class="nav-actions">
            <a href="<?php echo $base_url; ?>wallet.php" class="nav-icon-btn" title="Wallet — Rs. <?php echo number_format($user_balance, 2); ?>" style="position:relative;">
                <i class="fa-solid fa-wallet"></i>
                <span style="position:absolute;top:-2px;right:-2px;background:var(--green);color:#fff;font-size:9px;font-weight:700;border-radius:10px;padding:1px 5px;min-width:18px;text-align:center;"><?php echo ($user_balance >= 1000 ? round($user_balance/1000,1).'k' : number_format($user_balance,0)); ?></span>
            </a>
            <a href="<?php echo $base_url; ?>notifications.php" class="nav-icon-btn" title="Notifications">
                <i class="fa-regular fa-bell"></i>
            </a>
            <a href="<?php echo $base_url; ?>wishlist.php" class="nav-icon-btn" title="Watchlist">
                <i class="fa-regular fa-heart"></i>
            </a>
            <div class="nav-divider"></div>
            
            <div class="profile-dropdown-wrapper">
                <div class="nav-user" onclick="toggleProfileDropdown(event)">
                    <div class="nav-user-avatar">
                        <?php if (!empty($user_avatar)): ?>
                            <img src="<?php echo (($current_dir === 'admin' || $current_dir === 'user') ? '../' . $user_avatar : $user_avatar); ?>" alt="<?php echo htmlspecialchars($user_name); ?>">
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
                    <a href="<?php echo $base_url; ?>profile.php" class="profile-dropdown-item">
                        <i class="fa-solid fa-user"></i> Profile
                    </a>

                    <!-- Admin Panel Link inside popup (Only for admin) -->
                    <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                    <a href="<?php echo ($current_dir === 'user' || $current_dir === 'admin') ? '../admin/dashboard.php' : 'admin/dashboard.php'; ?>" class="profile-dropdown-item">
                        <i class="fa-solid fa-user-shield"></i> Admin Panel
                    </a>
                    <?php endif; ?>

                    <a href="/geo-auction-system/logout.php" class="profile-dropdown-item logout">
                        <i class="fa-solid fa-right-from-bracket"></i> Logout
                    </a>
                </div>
            </div>

        </div>
    </nav>

    <div class="layout">
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-section">
                <div class="sidebar-label">Main Navigation</div>
                <a href="<?php echo $base_url;?>dashboard.php" class="sidebar-link">
                    <i class="fa-solid fa-house"></i> Dashboard
                </a>
                <a href="<?php echo $base_url;?>live_auction.php" class="sidebar-link">
                    <i class="fa-solid fa-fire"></i> Live Auctions
                </a>
                <a href="<?php echo $base_url; ?>my_bids.php" class="sidebar-link">
                    <i class="fa-solid fa-gavel"></i> My Bids
                </a>
                
                <a href="<?php echo $base_url; ?>wishlist.php" class="sidebar-link">
                    <i class="fa-solid fa-heart"></i> Watchlist
                </a>
            </div>

            <div class="sidebar-section">
                <div class="sidebar-label">Seller Portal</div>
                <a href="<?php echo $base_url; ?>my_auctions.php" class="sidebar-link">
                    <i class="fa-solid fa-box-archive"></i> My Listings
                </a>
            </div>
            <div class="sidebar-section">
                <div class="sidebar-label">Wallet</div>
                <a href="<?php echo $base_url; ?>wallet.php" class="sidebar-link">
                    <i class="fa-solid fa-wallet"></i> My Wallet
                    <span style="margin-left:auto;font-size:11px;font-weight:700;color:#4df08a;">Rs. <?php echo number_format($user_balance, 2); ?></span>
                </a>
                <a href="<?php echo $base_url; ?>won_auctions.php" class="sidebar-link">
                    <i class="fa-solid fa-trophy"></i> Won Auctions
                </a>
            </div>

            <!-- Admin Portal Link only visible if user role is admin -->
            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
            <div class="sidebar-section">
                <div class="sidebar-label">Admin Portal</div>
                <a href="<?php echo "../admin/dashboard.php "?>" class="sidebar-link">
                    <i class="fa-solid fa-user-shield"></i> Admin Dashboard
                </a>
            </div>
            <?php endif; ?>

            <div class="sidebar-section">
                <div class="sidebar-label">Account</div>
                <a href="<?php echo $base_url; ?>profile.php" class="sidebar-link">
                    <i class="fa-solid fa-gear"></i> Settings
                </a>
                <a href="/geo-auction-system/logout.php" class="sidebar-link" style="color: #f87171;">
                    <i class="fa-solid fa-right-from-bracket"></i> Logout
                </a>
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

            // Location Dropdown Toggle Function
            function toggleLocationDropdown(event) {
                event.stopPropagation();
                const dropdown = document.getElementById('locationDropdown');
                // Close profile dropdown if open
                document.getElementById('profileDropdown')?.classList.remove('show');
                dropdown.classList.toggle('show');
            }

            function selectLocation(city) {
                const span = document.querySelector('.nav-location-btn span');
                if(span) span.textContent = city;
            }

            // Close all dropdowns when clicking outside
            window.addEventListener('click', function() {
                const profileDropdown = document.getElementById('profileDropdown');
                if (profileDropdown && profileDropdown.classList.contains('show')) {
                    profileDropdown.classList.remove('show');
                }
                const locationDropdown = document.getElementById('locationDropdown');
                if (locationDropdown && locationDropdown.classList.contains('show')) {
                    locationDropdown.classList.remove('show');
                }
            });
        </script>