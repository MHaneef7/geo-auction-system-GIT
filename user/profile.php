<?php
/**
 * User Profile & Comprehensive Account Management Dashboard
 * 
 * Tech Stack: PHP 8.x, MariaDB / MySQL, Tailwind CSS + FontAwesome
 * 
 * Modules:
 * 1. Profile Information & Full KYC Verification (CNIC, NTN, KYC Tier & Document Audit)
 * 2. Real-Time Auction Wallet Overview & Balances
 * 3. Live Auction Activity Center (Active Bids, Won Auctions, Watchlist)
 * 4. Multi-Device Session Management & Security (Password Change, Device Revocation, Notifications)
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/components/profile_reviews_messages.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php?redirect=user/profile.php");
    exit();
}

$logged_in_user_id = (int)$_SESSION['user_id'];
$requested_user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : (isset($_GET['id']) ? (int)$_GET['id'] : $logged_in_user_id);
$is_own_profile    = ($requested_user_id === $logged_in_user_id || $requested_user_id <= 0);
$user_id           = $is_own_profile ? $logged_in_user_id : $requested_user_id;
$curr_sess_id = session_id();

// Track current session in user_sessions table
if (!empty($curr_sess_id)) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown Browser', 0, 500);
    $now_ts = time();
    $sess_chk = mysqli_prepare($conn, "
        INSERT INTO user_sessions (id, user_id, ip_address, user_agent, payload, last_activity)
        VALUES (?, ?, ?, ?, '', ?)
        ON DUPLICATE KEY UPDATE ip_address = VALUES(ip_address), user_agent = VALUES(user_agent), last_activity = VALUES(last_activity)
    ");
    if ($sess_chk) {
        mysqli_stmt_bind_param($sess_chk, "sissi", $curr_sess_id, $user_id, $ip, $ua, $now_ts);
        mysqli_stmt_execute($sess_chk);
        mysqli_stmt_close($sess_chk);
    }
}

// CSRF Protection Token Generation
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

$success_message = '';
$error_message   = '';
$active_tab      = $_GET['tab'] ?? 'general';

// -------------------------------------------------------------
// POST Request Processing
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Verify CSRF Token
    $submitted_token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $submitted_token)) {
        $error_message = 'Security validation failed (Invalid CSRF token). Please refresh and try again.';
    } else {
        $action = $_POST['form_action'] ?? '';

        // -----------------------------------------------------
        // Action 1: Update Profile & Direct KYC Information
        // -----------------------------------------------------
        if ($action === 'update_profile') {
            $name             = trim($_POST['name'] ?? '');
            $username         = trim($_POST['username'] ?? '');
            $phone            = trim($_POST['phone'] ?? '');
            $shipping_address = trim($_POST['shipping_address'] ?? '');
            $cnic_tax_id      = trim($_POST['cnic_tax_id'] ?? '');
            $bio              = trim($_POST['bio'] ?? '');

            if (empty($name)) {
                $error_message = 'Full Name is required.';
            } else {
                // Check if username is already taken by someone else
                if (!empty($username)) {
                    $u_chk = mysqli_prepare($conn, "SELECT id FROM users WHERE username = ? AND id != ? LIMIT 1");
                    mysqli_stmt_bind_param($u_chk, "si", $username, $user_id);
                    mysqli_stmt_execute($u_chk);
                    if (mysqli_fetch_assoc(mysqli_stmt_get_result($u_chk))) {
                        $error_message = 'The username "' . htmlspecialchars($username) . '" is already taken. Please choose another.';
                    }
                    mysqli_stmt_close($u_chk);
                }

                if (empty($error_message)) {
                    // Fetch current user details
                    $u_stmt = mysqli_prepare($conn, "SELECT avatar, settings FROM users WHERE id = ?");
                    mysqli_stmt_bind_param($u_stmt, "i", $user_id);
                    mysqli_stmt_execute($u_stmt);
                    $curr_user = mysqli_fetch_assoc(mysqli_stmt_get_result($u_stmt));
                    mysqli_stmt_close($u_stmt);

                    $avatar_path = $curr_user['avatar'] ?? '';
                    $settings    = json_decode($curr_user['settings'] ?? '{}', true) ?: [];

                    // Handle Secure Avatar Upload
                    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
                        $file_tmp  = $_FILES['avatar']['tmp_name'];
                        $file_name = $_FILES['avatar']['name'];
                        $file_size = $_FILES['avatar']['size'];
                        $allowed_types = ['image/jpeg', 'image/png', 'image/webp'];

                        $file_mime = mime_content_type($file_tmp);
                        if (!in_array($file_mime, $allowed_types)) {
                            $error_message = 'Invalid image format. Only JPG, PNG, and WEBP are permitted.';
                        } elseif ($file_size > 2 * 1024 * 1024) { // 2MB Limit
                            $error_message = 'Avatar file size exceeds the 2MB limit.';
                        } else {
                            $upload_dir = __DIR__ . '/../uploads/avatars/';
                            if (!is_dir($upload_dir)) {
                                mkdir($upload_dir, 0755, true);
                            }
                            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                            $new_filename = 'avatar_' . $user_id . '_' . bin2hex(random_bytes(6)) . '.' . $file_ext;
                            $destination = $upload_dir . $new_filename;

                            if (move_uploaded_file($file_tmp, $destination)) {
                                // Remove old avatar if it exists
                                if (!empty($avatar_path) && file_exists(__DIR__ . '/../' . $avatar_path)) {
                                    @unlink(__DIR__ . '/../' . $avatar_path);
                                }
                                $avatar_path = 'uploads/avatars/' . $new_filename;
                            } else {
                                $error_message = 'Failed to store uploaded avatar image.';
                            }
                        }
                    }

                    if (empty($error_message)) {
                        // Keep settings sync
                        $settings['username']         = $username;
                        $settings['shipping_address'] = $shipping_address;
                        $settings['cnic_tax_id']      = $cnic_tax_id;
                        $settings_json = json_encode($settings);

                        // Direct column update in users table
                        $up_stmt = mysqli_prepare($conn, "
                            UPDATE users 
                            SET name = ?, username = ?, phone = ?, bio = ?, avatar = ?, shipping_address = ?, cnic_tax_id = ?, settings = ? 
                            WHERE id = ?
                        ");
                        $u_param = !empty($username) ? $username : null;
                        mysqli_stmt_bind_param($up_stmt, "ssssssssi", $name, $u_param, $phone, $bio, $avatar_path, $shipping_address, $cnic_tax_id, $settings_json, $user_id);
                        
                        if (mysqli_stmt_execute($up_stmt)) {
                            $_SESSION['user_name'] = $name;
                            $_SESSION['user_avatar'] = $avatar_path;
                            $success_message = 'Profile information and KYC details updated successfully!';
                        } else {
                            $error_message = 'Database update error: ' . mysqli_stmt_error($up_stmt);
                        }
                        mysqli_stmt_close($up_stmt);
                    }
                }
            }
            $active_tab = 'general';
        }

        // -----------------------------------------------------
        // Action 2: Upload KYC Verification Document
        // -----------------------------------------------------
        if ($action === 'upload_kyc_doc') {
            $doc_type = trim($_POST['document_type'] ?? '');
            $valid_types = ['CNIC_FRONT', 'CNIC_BACK', 'PASSPORT', 'NTN_CERTIFICATE'];

            if (!in_array($doc_type, $valid_types)) {
                $error_message = 'Please select a valid identity document type.';
            } elseif (!isset($_FILES['kyc_file']) || $_FILES['kyc_file']['error'] !== UPLOAD_ERR_OK) {
                $error_message = 'Please choose a document file to upload.';
            } else {
                $file_tmp  = $_FILES['kyc_file']['tmp_name'];
                $file_name = $_FILES['kyc_file']['name'];
                $file_size = $_FILES['kyc_file']['size'];
                $file_mime = mime_content_type($file_tmp);

                $allowed_mimes = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];

                if (!in_array($file_mime, $allowed_mimes)) {
                    $error_message = 'Invalid file type. Allowed formats: JPG, PNG, WEBP, and PDF.';
                } elseif ($file_size > 5 * 1024 * 1024) { // 5MB Limit
                    $error_message = 'Document file size exceeds the 5MB limit.';
                } else {
                    $kyc_dir = __DIR__ . '/../uploads/kyc/';
                    if (!is_dir($kyc_dir)) {
                        mkdir($kyc_dir, 0755, true);
                    }

                    $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                    $clean_name = 'kyc_' . $user_id . '_' . strtolower($doc_type) . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
                    $target_file = $kyc_dir . $clean_name;

                    if (move_uploaded_file($file_tmp, $target_file)) {
                        $doc_rel_path = 'uploads/kyc/' . $clean_name;

                        $k_stmt = mysqli_prepare($conn, "
                            INSERT INTO user_kyc_verifications (user_id, document_type, document_path, status, created_at)
                            VALUES (?, ?, ?, 'PENDING', NOW())
                        ");
                        if ($k_stmt) {
                            mysqli_stmt_bind_param($k_stmt, "iss", $user_id, $doc_type, $doc_rel_path);
                            mysqli_stmt_execute($k_stmt);
                            mysqli_stmt_close($k_stmt);
                            $success_message = 'Document uploaded successfully! Our compliance team will review your verification shortly.';
                        } else {
                            $error_message = 'Failed to register verification record in database.';
                        }
                    } else {
                        $error_message = 'Failed to save uploaded document to server storage.';
                    }
                }
            }
            $active_tab = 'general';
        }

        // -----------------------------------------------------
        // Action 3: Change Password
        // -----------------------------------------------------
        if ($action === 'change_password') {
            $current_password = $_POST['current_password'] ?? '';
            $new_password     = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';

            if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
                $error_message = 'All password fields are required.';
            } elseif ($new_password !== $confirm_password) {
                $error_message = 'New password and confirmation do not match.';
            } elseif (strlen($new_password) < 8) {
                $error_message = 'New password must be at least 8 characters in length.';
            } else {
                $pw_stmt = mysqli_prepare($conn, "SELECT password FROM users WHERE id = ?");
                mysqli_stmt_bind_param($pw_stmt, "i", $user_id);
                mysqli_stmt_execute($pw_stmt);
                $pw_res = mysqli_fetch_assoc(mysqli_stmt_get_result($pw_stmt));
                mysqli_stmt_close($pw_stmt);

                if (!$pw_res || !password_verify($current_password, $pw_res['password'])) {
                    $error_message = 'Current password entered is incorrect.';
                } else {
                    $new_hash = password_hash($new_password, PASSWORD_BCRYPT);
                    $up_pw = mysqli_prepare($conn, "UPDATE users SET password = ? WHERE id = ?");
                    mysqli_stmt_bind_param($up_pw, "si", $new_hash, $user_id);
                    if (mysqli_stmt_execute($up_pw)) {
                        $success_message = 'Your password has been changed securely.';
                    } else {
                        $error_message = 'Failed to update password: ' . mysqli_stmt_error($up_pw);
                    }
                    mysqli_stmt_close($up_pw);
                }
            }
            $active_tab = 'security';
        }

        // -----------------------------------------------------
        // Action 4: Session Management (Revoke Other Devices)
        // -----------------------------------------------------
        if ($action === 'revoke_sessions') {
            $new_token = bin2hex(random_bytes(32));
            $rev_stmt = mysqli_prepare($conn, "UPDATE users SET remember_token = ? WHERE id = ?");
            mysqli_stmt_bind_param($rev_stmt, "si", $new_token, $user_id);
            mysqli_stmt_execute($rev_stmt);
            mysqli_stmt_close($rev_stmt);

            // Clean other active sessions from user_sessions table
            $del_sess = mysqli_prepare($conn, "DELETE FROM user_sessions WHERE user_id = ? AND id != ?");
            if ($del_sess) {
                mysqli_stmt_bind_param($del_sess, "is", $user_id, $curr_sess_id);
                mysqli_stmt_execute($del_sess);
                mysqli_stmt_close($del_sess);
            }

            $success_message = 'Successfully logged out all other active sessions and devices.';
            $active_tab = 'security';
        }

        // -----------------------------------------------------
        // Action 5: Notification Preferences
        // -----------------------------------------------------
        if ($action === 'update_notifications') {
            $email_bids       = isset($_POST['email_bids']) ? 1 : 0;
            $email_outbid     = isset($_POST['email_outbid']) ? 1 : 0;
            $email_won        = isset($_POST['email_won']) ? 1 : 0;
            $email_newsletter = isset($_POST['email_newsletter']) ? 1 : 0;
            $sms_bids         = isset($_POST['sms_bids']) ? 1 : 0;

            // Check if record exists
            $chk_n = mysqli_prepare($conn, "SELECT id FROM user_notifications WHERE user_id = ?");
            mysqli_stmt_bind_param($chk_n, "i", $user_id);
            mysqli_stmt_execute($chk_n);
            $has_notif = mysqli_fetch_assoc(mysqli_stmt_get_result($chk_n));
            mysqli_stmt_close($chk_n);

            if ($has_notif) {
                $n_up = mysqli_prepare($conn, "
                    UPDATE user_notifications 
                    SET email_bids = ?, email_outbid = ?, email_won = ?, email_newsletter = ?, sms_bids = ? 
                    WHERE user_id = ?
                ");
                mysqli_stmt_bind_param($n_up, "iiiiii", $email_bids, $email_outbid, $email_won, $email_newsletter, $sms_bids, $user_id);
                mysqli_stmt_execute($n_up);
                mysqli_stmt_close($n_up);
            } else {
                $n_ins = mysqli_prepare($conn, "
                    INSERT INTO user_notifications (user_id, email_bids, email_outbid, email_won, email_newsletter, sms_bids) 
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                mysqli_stmt_bind_param($n_ins, "iiiiii", $user_id, $email_bids, $email_outbid, $email_won, $email_newsletter, $sms_bids);
                mysqli_stmt_execute($n_ins);
                mysqli_stmt_close($n_ins);
            }

            $success_message = 'Notification preferences updated successfully.';
            $active_tab = 'security';
        }
    }
}

// -------------------------------------------------------------
// Data Aggregation for View
// -------------------------------------------------------------

// 1. Fetch User Profile
$u_query = mysqli_prepare($conn, "SELECT * FROM users WHERE id = ?");
mysqli_stmt_bind_param($u_query, "i", $user_id);
mysqli_stmt_execute($u_query);
$user = mysqli_fetch_assoc(mysqli_stmt_get_result($u_query));
mysqli_stmt_close($u_query);

$settings = json_decode($user['settings'] ?? '{}', true) ?: [];
$username = $user['username'] ?? ($settings['username'] ?? '');
$shipping_address = $user['shipping_address'] ?? ($settings['shipping_address'] ?? '');
$cnic_tax_id = $user['cnic_tax_id'] ?? ($settings['cnic_tax_id'] ?? '');
$kyc_tier = $user['kyc_tier'] ?? 'verified_bidder';
$kyc_verified_at = $user['kyc_verified_at'] ?? null;

// 2. Fetch User KYC Documents Audit
$kyc_docs = [];
$kd_stmt = mysqli_prepare($conn, "SELECT * FROM user_kyc_verifications WHERE user_id = ? ORDER BY created_at DESC");
if ($kd_stmt) {
    mysqli_stmt_bind_param($kd_stmt, "i", $user_id);
    mysqli_stmt_execute($kd_stmt);
    $kd_res = mysqli_stmt_get_result($kd_stmt);
    while ($krow = mysqli_fetch_assoc($kd_res)) {
        $kyc_docs[] = $krow;
    }
    mysqli_stmt_close($kd_stmt);
}

// 3. Fetch Active Devices & Sessions
$active_sessions = [];
$s_stmt = mysqli_prepare($conn, "SELECT * FROM user_sessions WHERE user_id = ? ORDER BY last_activity DESC");
if ($s_stmt) {
    mysqli_stmt_bind_param($s_stmt, "i", $user_id);
    mysqli_stmt_execute($s_stmt);
    $s_res = mysqli_stmt_get_result($s_stmt);
    while ($srow = mysqli_fetch_assoc($s_res)) {
        $active_sessions[] = $srow;
    }
    mysqli_stmt_close($s_stmt);
}

// 4. Fetch Wallet & Balances
$available_balance = (float)($user['balance'] ?? 0);

// Active Bids & Locked Funds
$active_bids_query = "
    SELECT b.id AS bid_id, b.item_id, b.bid_amount, b.is_winning, b.created_at AS bid_time,
           i.title, i.auction_code, i.current_price, i.end_time, i.featured_image, i.status AS item_status
    FROM bids b
    JOIN items i ON b.item_id = i.id
    WHERE b.user_id = ? AND i.status = 'active'
    ORDER BY i.end_time ASC
";
$stmt_bids = mysqli_prepare($conn, $active_bids_query);
mysqli_stmt_bind_param($stmt_bids, "i", $user_id);
mysqli_stmt_execute($stmt_bids);
$bids_res = mysqli_stmt_get_result($stmt_bids);
$active_bids = [];
$locked_balance = 0.0;
while ($row = mysqli_fetch_assoc($bids_res)) {
    $active_bids[] = $row;
    if ($row['is_winning']) {
        $locked_balance += (float)$row['bid_amount'];
    }
}
mysqli_stmt_close($stmt_bids);
$total_balance = $available_balance + $locked_balance;

// 5. Fetch Won Auctions
$won_query = "
    SELECT i.*, p.payment_status, p.paid_at, p.id AS payment_id
    FROM items i
    LEFT JOIN payments p ON p.item_id = i.id AND p.user_id = ?
    WHERE i.winner_id = ?
    ORDER BY i.end_time DESC
";
$stmt_won = mysqli_prepare($conn, $won_query);
mysqli_stmt_bind_param($stmt_won, "ii", $user_id, $user_id);
mysqli_stmt_execute($stmt_won);
$won_res = mysqli_stmt_get_result($stmt_won);
$won_auctions = [];
while ($w = mysqli_fetch_assoc($won_res)) {
    $won_auctions[] = $w;
}
mysqli_stmt_close($stmt_won);

// 6. Fetch Watchlist
$watch_query = "
    SELECT w.id AS watch_id, i.id AS item_id, i.title, i.auction_code, i.current_price, i.end_time, i.featured_image, i.status
    FROM wishlist w
    JOIN items i ON w.item_id = i.id
    WHERE w.user_id = ?
    ORDER BY w.created_at DESC
";
$stmt_watch = mysqli_prepare($conn, $watch_query);
mysqli_stmt_bind_param($stmt_watch, "i", $user_id);
mysqli_stmt_execute($stmt_watch);
$watch_res = mysqli_stmt_get_result($stmt_watch);
$watchlist = [];
while ($wl = mysqli_fetch_assoc($watch_res)) {
    $watchlist[] = $wl;
}
mysqli_stmt_close($stmt_watch);

// 7. Fetch Recent Transactions
$tx_query = "
    SELECT * FROM transactions 
    WHERE user_id = ? 
    ORDER BY created_at DESC 
    LIMIT 20
";
$stmt_tx = mysqli_prepare($conn, $tx_query);
mysqli_stmt_bind_param($stmt_tx, "i", $user_id);
mysqli_stmt_execute($stmt_tx);
$tx_res = mysqli_stmt_get_result($stmt_tx);
$transactions = [];
while ($tx = mysqli_fetch_assoc($tx_res)) {
    $transactions[] = $tx;
}
mysqli_stmt_close($stmt_tx);

// 8. Fetch Notification Preferences
$stmt_np = mysqli_prepare($conn, "SELECT * FROM user_notifications WHERE user_id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt_np, "i", $user_id);
mysqli_stmt_execute($stmt_np);
$notif_prefs = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_np)) ?: [
    'email_bids' => 1,
    'email_outbid' => 1,
    'email_won' => 1,
    'email_newsletter' => 0,
    'sms_bids' => 0
];
mysqli_stmt_close($stmt_np);

include_once __DIR__ . '/../includes/header.php';
?>

<!-- Tailwind CSS CDN & FontAwesome -->
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

<style>
    :root {
        --brand-green: rgb(4 120 87);
        --brand-green-hover: rgb(3 98 71);
    }
    .tab-active {
        border-bottom: 2px solid var(--brand-green);
        color: var(--brand-green);
        font-weight: 700;
    }
</style>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

    <!-- Back Navigation Bar -->
    <div class="mb-5">
        <a href="javascript:history.back()" class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl bg-white border border-slate-200 text-slate-700 text-sm font-semibold hover:text-emerald-700 hover:border-emerald-600 hover:-translate-x-1 transition shadow-sm" title="Go Back">
            <i class="fa-solid fa-arrow-left text-xs"></i>
            <span>Back</span>
        </a>
    </div>

    <!-- Top Profile & Wallet Summary Banner -->
    <div class="bg-gradient-to-r from-emerald-800 to-teal-950 rounded-2xl p-6 sm:p-8 text-white shadow-lg mb-8">
        <div class="flex flex-col lg:flex-row items-start lg:items-center justify-between gap-6">
            <!-- User Info & Avatar -->
            <div class="flex items-center gap-5">
                <div class="relative w-20 h-20 sm:w-24 sm:h-24 rounded-full overflow-hidden border-4 border-white/20 shadow-md bg-white/10 shrink-0">
                    <img src="<?php echo !empty($user['avatar']) ? '../' . htmlspecialchars($user['avatar']) : 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=200&q=80'; ?>" 
                         alt="Avatar" class="w-full h-full object-cover">
                </div>
                <div>
                    <div class="flex items-center gap-3 flex-wrap">
                        <h1 class="text-2xl sm:text-3xl font-black tracking-tight"><?php echo htmlspecialchars($user['name']); ?></h1>
                        <?php if (!empty($username)): ?>
                            <span class="text-xs px-2.5 py-0.5 rounded-full bg-white/15 text-emerald-200 font-mono">@<?php echo htmlspecialchars($username); ?></span>
                        <?php endif; ?>
                        
                        <!-- Dynamic Trust Tier Badge -->
                        <?php if ($kyc_tier === 'vip_buyer'): ?>
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-amber-500/20 text-amber-300 border border-amber-500/40">
                                <i class="fa-solid fa-crown text-amber-300"></i> VIP Buyer
                            </span>
                        <?php elseif ($kyc_tier === 'unverified'): ?>
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-slate-500/30 text-slate-300 border border-slate-500/40">
                                <i class="fa-solid fa-clock"></i> Unverified Account
                            </span>
                        <?php else: ?>
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">
                                <i class="fa-solid fa-shield-check"></i> Verified Bidder
                            </span>
                        <?php endif; ?>
                    </div>
                    <p class="text-emerald-200/80 text-sm mt-0.5"><?php echo htmlspecialchars($user['email']); ?></p>
                    <div class="flex items-center gap-4 mt-2 text-xs text-white/70 flex-wrap">
                        <span><i class="fa-solid fa-user-tag text-emerald-400 mr-1"></i> Role: <strong><?php echo ucfirst(htmlspecialchars($user['role'])); ?></strong></span>
                        <span><i class="fa-solid fa-calendar-check text-emerald-400 mr-1"></i> Member Since: <strong><?php echo date('M Y', strtotime($user['created_at'])); ?></strong></span>
                        <?php if (!empty($kyc_verified_at)): ?>
                            <span><i class="fa-solid fa-circle-check text-emerald-400 mr-1"></i> KYC Certified: <strong><?php echo date('M d, Y', strtotime($kyc_verified_at)); ?></strong></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Wallet Overview Mini-Card -->
            <div class="bg-white/10 backdrop-blur-md rounded-xl p-4 sm:p-5 border border-white/15 flex flex-wrap items-center gap-6 w-full lg:w-auto justify-between lg:justify-end">
                <div>
                    <span class="text-xs uppercase tracking-wider text-emerald-200/80 font-medium block">Available Balance</span>
                    <span class="text-2xl sm:text-3xl font-extrabold text-white">Rs. <?php echo number_format($available_balance, 2); ?></span>
                    <span class="text-xs text-white/60 block mt-0.5">Locked in Bids: Rs. <?php echo number_format($locked_balance, 2); ?></span>
                </div>
                <div class="flex gap-2 flex-wrap">
                    <?php if (!$is_own_profile): ?>
                        <a href="../messages.php?user_id=<?= (int)$user['id']; ?>" class="inline-flex items-center gap-2 bg-emerald-500 hover:bg-emerald-600 text-white text-xs font-bold px-4 py-2.5 rounded-lg shadow-sm transition profile-message-btn" title="Send Message">
                            <i class="fa-solid fa-message"></i> <span>Message</span>
                        </a>
                    <?php else: ?>
                        <a href="../messages.php" class="inline-flex items-center gap-2 bg-white/15 hover:bg-white/25 text-white text-xs font-bold px-4 py-2.5 rounded-lg border border-white/20 transition profile-message-btn" title="My Messages">
                            <i class="fa-solid fa-message"></i> <span>Messages</span>
                        </a>
                        <a href="wallet.php" class="inline-flex items-center gap-2 bg-emerald-500 hover:bg-emerald-600 text-white text-xs font-bold px-4 py-2.5 rounded-lg shadow-sm transition">
                            <i class="fa-solid fa-plus-circle"></i> Top Up
                        </a>
                        <a href="wallet.php" class="inline-flex items-center gap-2 bg-white/15 hover:bg-white/25 text-white text-xs font-bold px-4 py-2.5 rounded-lg border border-white/20 transition">
                            <i class="fa-solid fa-wallet"></i> Full Wallet
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Alert Notifications -->
    <?php if (!empty($success_message)): ?>
        <div class="mb-6 p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 flex items-center gap-3 shadow-sm">
            <i class="fa-solid fa-circle-check text-emerald-600 text-lg"></i>
            <span class="text-sm font-semibold"><?php echo htmlspecialchars($success_message); ?></span>
        </div>
    <?php endif; ?>

    <?php if (!empty($error_message)): ?>
        <div class="mb-6 p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 flex items-center gap-3 shadow-sm">
            <i class="fa-solid fa-circle-exclamation text-rose-600 text-lg"></i>
            <span class="text-sm font-semibold"><?php echo htmlspecialchars($error_message); ?></span>
        </div>
    <?php endif; ?>

    <!-- Navigation Tabs -->
    <div class="border-b border-slate-200 mb-8 overflow-x-auto">
        <nav class="flex space-x-8 min-w-max" aria-label="Tabs">
            <button onclick="switchTab('general')" id="tab-btn-general" 
                    class="py-4 px-1 text-sm font-semibold text-slate-500 hover:text-slate-700 transition flex items-center gap-2 <?php echo $active_tab === 'general' ? 'tab-active' : ''; ?>">
                <i class="fa-regular fa-user"></i> General Info & KYC
            </button>
            <button onclick="switchTab('activity')" id="tab-btn-activity" 
                    class="py-4 px-1 text-sm font-semibold text-slate-500 hover:text-slate-700 transition flex items-center gap-2 <?php echo $active_tab === 'activity' ? 'tab-active' : ''; ?>">
                <i class="fa-solid fa-gavel"></i> Auction Activity
                <?php if (count($active_bids) > 0): ?>
                    <span class="px-2 py-0.5 text-xs rounded-full bg-emerald-100 text-emerald-800 font-bold"><?php echo count($active_bids); ?></span>
                <?php endif; ?>
            </button>
            <button onclick="switchTab('wallet')" id="tab-btn-wallet" 
                    class="py-4 px-1 text-sm font-semibold text-slate-500 hover:text-slate-700 transition flex items-center gap-2 <?php echo $active_tab === 'wallet' ? 'tab-active' : ''; ?>">
                <i class="fa-solid fa-receipt"></i> Wallet Ledger
            </button>
            <button onclick="switchTab('reviews')" id="tab-btn-reviews" 
                    class="py-4 px-1 text-sm font-semibold text-slate-500 hover:text-slate-700 transition flex items-center gap-2 <?php echo $active_tab === 'reviews' ? 'tab-active' : ''; ?>">
                <i class="fa-regular fa-comments"></i> Reviews & Messages
            </button>
            <button onclick="switchTab('security')" id="tab-btn-security" 
                    class="py-4 px-1 text-sm font-semibold text-slate-500 hover:text-slate-700 transition flex items-center gap-2 <?php echo $active_tab === 'security' ? 'tab-active' : ''; ?>">
                <i class="fa-solid fa-lock"></i> Security & Devices
                <?php if (count($active_sessions) > 0): ?>
                    <span class="px-2 py-0.5 text-xs rounded-full bg-slate-100 text-slate-700 font-bold"><?php echo count($active_sessions); ?></span>
                <?php endif; ?>
            </button>
        </nav>
    </div>

    <!-- TAB 1: General Info & KYC -->
    <div id="tab-content-general" class="<?php echo $active_tab === 'general' ? '' : 'hidden'; ?> space-y-8">
        
        <!-- Profile & Personal Details Card -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 sm:p-8">
            <h2 class="text-xl font-bold text-slate-900 mb-6 flex items-center gap-2">
                <i class="fa-solid fa-id-card text-emerald-700"></i> Personal Information & KYC Attributes
            </h2>

            <form action="profile.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <input type="hidden" name="form_action" value="update_profile">

                <!-- Avatar Upload Row -->
                <div class="flex items-center gap-6 p-4 rounded-xl bg-slate-50 border border-slate-200 mb-6">
                    <div class="relative w-20 h-20 rounded-full overflow-hidden border-2 border-emerald-600 bg-white cursor-pointer group" onclick="document.getElementById('avatarInput').click();">
                        <img id="avatarPreview" src="<?php echo !empty($user['avatar']) ? '../' . htmlspecialchars($user['avatar']) : 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=200&q=80'; ?>" alt="Avatar" class="w-full h-full object-cover">
                        <div class="absolute inset-0 bg-black/40 text-white text-[11px] font-bold flex items-center justify-center opacity-0 group-hover:opacity-100 transition">Change</div>
                    </div>
                    <div>
                        <h4 class="text-sm font-bold text-slate-800 mb-1">Avatar Photo</h4>
                        <p class="text-xs text-slate-500 mb-2">JPG, PNG, or WEBP. Max 2MB file size.</p>
                        <input type="file" name="avatar" id="avatarInput" accept="image/jpeg, image/png, image/webp" class="hidden">
                        <button type="button" onclick="document.getElementById('avatarInput').click();" class="px-3 py-1.5 bg-white border border-slate-300 rounded-lg text-xs font-semibold text-slate-700 hover:bg-slate-100 transition">
                            <i class="fa-solid fa-upload mr-1 text-emerald-700"></i> Select New Image
                        </button>
                    </div>
                </div>

                <!-- Form Fields Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">Full Legal Name <span class="text-rose-500">*</span></label>
                        <input type="text" name="name" value="<?php echo htmlspecialchars($user['name'] ?? ''); ?>" required
                               class="w-full px-4 py-2.5 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:border-emerald-600 outline-none transition">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">Display Username (Unique Handle)</label>
                        <input type="text" name="username" value="<?php echo htmlspecialchars($username); ?>" placeholder="e.g. haneef_bids"
                               class="w-full px-4 py-2.5 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:border-emerald-600 outline-none transition">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">Registered Email <span class="text-slate-400 font-normal">(Primary Login)</span></label>
                        <input type="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" disabled
                               class="w-full px-4 py-2.5 text-sm bg-slate-100 border border-slate-200 rounded-xl text-slate-500 cursor-not-allowed">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">Phone Number (E.164)</label>
                        <input type="text" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" placeholder="+92 300 1234567"
                               class="w-full px-4 py-2.5 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:border-emerald-600 outline-none transition">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">National ID / Tax ID (CNIC / NTN)</label>
                        <input type="text" name="cnic_tax_id" value="<?php echo htmlspecialchars($cnic_tax_id); ?>" placeholder="42101-*******-1"
                               class="w-full px-4 py-2.5 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:border-emerald-600 outline-none transition">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">KYC Verification Tier</label>
                        <div class="w-full px-4 py-2.5 text-sm bg-slate-100 border border-slate-200 rounded-xl font-bold flex items-center justify-between">
                            <span class="text-emerald-800 uppercase tracking-wide"><?php echo str_replace('_', ' ', $kyc_tier); ?></span>
                            <span class="text-xs font-semibold px-2 py-0.5 rounded bg-emerald-100 text-emerald-800">Certified</span>
                        </div>
                    </div>
                </div>

                <!-- Shipping Address -->
                <div class="mb-6">
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">Primary Shipping & Delivery Address</label>
                    <textarea name="shipping_address" rows="3" placeholder="Apartment, Street Address, City, Postal Code..."
                              class="w-full px-4 py-2.5 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:border-emerald-600 outline-none transition"><?php echo htmlspecialchars($shipping_address); ?></textarea>
                </div>

                <!-- Bio -->
                <div class="mb-8">
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">About / Seller Bio</label>
                    <textarea name="bio" rows="3" placeholder="Tell other collectors and sellers about yourself..."
                              class="w-full px-4 py-2.5 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:border-emerald-600 outline-none transition"><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea>
                </div>

                <div class="flex justify-end">
                    <button type="submit" class="px-6 py-3 bg-emerald-700 hover:bg-emerald-800 text-white font-bold text-sm rounded-xl shadow-sm transition inline-flex items-center gap-2">
                        <i class="fa-solid fa-floppy-disk"></i> Save Profile Details
                    </button>
                </div>
            </form>
        </div>

        <!-- KYC Document Upload & Audit Trail -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 sm:p-8">
            <div class="flex justify-between items-center mb-6 flex-wrap gap-4">
                <div>
                    <h3 class="text-lg font-bold text-slate-900 flex items-center gap-2">
                        <i class="fa-solid fa-file-shield text-emerald-700"></i> KYC Document Verification
                    </h3>
                    <p class="text-xs text-slate-500 mt-1">Submit official government identification for verified buyer/seller status.</p>
                </div>
                <span class="text-xs font-semibold px-3 py-1 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200">
                    Encrypted Storage
                </span>
            </div>

            <!-- Upload Form -->
            <form action="profile.php" method="POST" enctype="multipart/form-data" class="bg-slate-50 border border-slate-200 rounded-xl p-5 mb-8">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <input type="hidden" name="form_action" value="upload_kyc_doc">

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 items-end">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">Document Type</label>
                        <select name="document_type" required class="w-full px-4 py-2.5 text-sm bg-white border border-slate-200 rounded-xl focus:border-emerald-600 outline-none">
                            <option value="CNIC_FRONT">CNIC / National ID (Front)</option>
                            <option value="CNIC_BACK">CNIC / National ID (Back)</option>
                            <option value="PASSPORT">Passport Data Page</option>
                            <option value="NTN_CERTIFICATE">NTN / Tax Registration Certificate</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">Select File (JPG, PNG, PDF &le; 5MB)</label>
                        <input type="file" name="kyc_file" required accept="image/jpeg, image/png, image/webp, application/pdf"
                               class="w-full text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-emerald-100 file:text-emerald-800 hover:file:bg-emerald-200 cursor-pointer">
                    </div>

                    <div>
                        <button type="submit" class="w-full py-2.5 px-4 bg-emerald-700 hover:bg-emerald-800 text-white font-bold text-sm rounded-xl shadow-sm transition inline-flex items-center justify-center gap-2">
                            <i class="fa-solid fa-cloud-arrow-up"></i> Upload Document
                        </button>
                    </div>
                </div>
            </form>

            <!-- Audit Trail Table -->
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 text-slate-500 font-semibold text-xs border-b border-slate-200">
                        <tr>
                            <th class="p-3 pl-4">Document Type</th>
                            <th class="p-3">File Reference</th>
                            <th class="p-3">Submission Date</th>
                            <th class="p-3">Status</th>
                            <th class="p-3 pr-4 text-right">Remarks</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php if (!empty($kyc_docs)): ?>
                            <?php foreach ($kyc_docs as $doc): ?>
                            <tr class="hover:bg-slate-50/80 transition text-xs">
                                <td class="p-3 pl-4 font-bold text-slate-900">
                                    <i class="fa-regular fa-file-lines mr-1.5 text-slate-400"></i>
                                    <?php echo str_replace('_', ' ', htmlspecialchars($doc['document_type'])); ?>
                                </td>
                                <td class="p-3 font-mono text-slate-500 truncate max-w-xs">
                                    <?php echo htmlspecialchars(basename($doc['document_path'])); ?>
                                </td>
                                <td class="p-3 text-slate-500 font-mono">
                                    <?php echo date('M d, Y', strtotime($doc['created_at'])); ?>
                                </td>
                                <td class="p-3">
                                    <?php if ($doc['status'] === 'APPROVED'): ?>
                                        <span class="px-2 py-0.5 rounded-full text-[11px] font-bold bg-emerald-100 text-emerald-800">
                                            <i class="fa-solid fa-check mr-1"></i> Approved
                                        </span>
                                    <?php elseif ($doc['status'] === 'REJECTED'): ?>
                                        <span class="px-2 py-0.5 rounded-full text-[11px] font-bold bg-rose-100 text-rose-800">
                                            <i class="fa-solid fa-xmark mr-1"></i> Rejected
                                        </span>
                                    <?php else: ?>
                                        <span class="px-2 py-0.5 rounded-full text-[11px] font-bold bg-amber-100 text-amber-800">
                                            <i class="fa-solid fa-hourglass-half mr-1"></i> Under Review
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="p-3 pr-4 text-right text-slate-500">
                                    <?php echo !empty($doc['rejection_reason']) ? htmlspecialchars($doc['rejection_reason']) : 'Standard verification'; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center py-6 text-slate-400 text-xs">
                                    No verification documents submitted yet. Upload your National ID or Passport above.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <!-- TAB 2: Auction Activity Center -->
    <div id="tab-content-activity" class="<?php echo $active_tab === 'activity' ? '' : 'hidden'; ?> space-y-8">
        
        <!-- 1. Active Bids -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="p-6 border-b border-slate-200 flex justify-between items-center flex-wrap gap-2">
                <h3 class="text-lg font-bold text-slate-900 flex items-center gap-2">
                    <i class="fa-solid fa-gavel text-emerald-700"></i> Active Bids (Live Auctions)
                </h3>
                <span class="text-xs font-semibold px-2.5 py-1 bg-slate-100 text-slate-700 rounded-lg">
                    <?php echo count($active_bids); ?> Live Holding
                </span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 text-slate-500 font-semibold text-xs border-b border-slate-200">
                        <tr>
                            <th class="p-4 pl-6">Auction Item</th>
                            <th class="p-4">Your Bid</th>
                            <th class="p-4">Current Price</th>
                            <th class="p-4">Status</th>
                            <th class="p-4">Closing Time</th>
                            <th class="p-4 pr-6 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php if (!empty($active_bids)): ?>
                            <?php foreach ($active_bids as $bid): 
                                $is_winning = (bool)$bid['is_winning'];
                                $end_ts = strtotime($bid['end_time']);
                            ?>
                            <tr class="hover:bg-slate-50/80 transition">
                                <td class="p-4 pl-6">
                                    <div class="flex items-center gap-3">
                                        <div class="w-12 h-12 rounded-lg bg-slate-100 overflow-hidden shrink-0 border border-slate-200">
                                            <img src="<?php echo !empty($bid['featured_image']) ? '../' . htmlspecialchars($bid['featured_image']) : 'https://via.placeholder.com/60'; ?>" alt="Item" class="w-full h-full object-cover">
                                        </div>
                                        <div>
                                            <a href="../item.php?id=<?php echo (int)$bid['item_id']; ?>" class="font-bold text-slate-900 hover:text-emerald-700 transition line-clamp-1">
                                                <?php echo htmlspecialchars($bid['title']); ?>
                                            </a>
                                            <span class="text-xs text-slate-400 font-mono">#<?php echo htmlspecialchars($bid['auction_code']); ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td class="p-4 font-bold text-emerald-800">
                                    Rs. <?php echo number_format((float)$bid['bid_amount'], 2); ?>
                                </td>
                                <td class="p-4 text-slate-700 font-semibold">
                                    Rs. <?php echo number_format((float)$bid['current_price'], 2); ?>
                                </td>
                                <td class="p-4">
                                    <?php if ($is_winning): ?>
                                        <span class="px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 border border-emerald-200">
                                            <i class="fa-solid fa-crown mr-1"></i> Winning
                                        </span>
                                    <?php else: ?>
                                        <span class="px-2.5 py-1 rounded-full text-xs font-bold bg-rose-100 text-rose-800 border border-rose-200">
                                            <i class="fa-solid fa-arrow-down mr-1"></i> Outbid
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="p-4 text-slate-500 font-mono text-xs">
                                    <?php echo date('M d, H:i', $end_ts); ?>
                                </td>
                                <td class="p-4 pr-6 text-right">
                                    <a href="../item.php?id=<?php echo (int)$bid['item_id']; ?>" class="px-3 py-1.5 rounded-lg bg-slate-900 text-white text-xs font-bold hover:bg-emerald-700 transition">
                                        View Lot
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center py-8 text-slate-400">
                                    You have no active bids on live auctions. <a href="../items.php" class="text-emerald-700 font-bold hover:underline">Explore live lots</a>.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- 2. Won Auctions & Orders -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="p-6 border-b border-slate-200">
                <h3 class="text-lg font-bold text-slate-900 flex items-center gap-2">
                    <i class="fa-solid fa-trophy text-amber-500"></i> Won Auctions & Invoices
                </h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 text-slate-500 font-semibold text-xs border-b border-slate-200">
                        <tr>
                            <th class="p-4 pl-6">Item</th>
                            <th class="p-4">Winning Bid</th>
                            <th class="p-4">Payment Status</th>
                            <th class="p-4">Won Date</th>
                            <th class="p-4 pr-6 text-right">Invoice</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php if (!empty($won_auctions)): ?>
                            <?php foreach ($won_auctions as $won): ?>
                            <tr class="hover:bg-slate-50/80 transition">
                                <td class="p-4 pl-6 font-bold text-slate-900">
                                    <?php echo htmlspecialchars($won['title']); ?>
                                </td>
                                <td class="p-4 font-bold text-emerald-800">
                                    Rs. <?php echo number_format((float)$won['current_price'], 2); ?>
                                </td>
                                <td class="p-4">
                                    <span class="px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 border border-emerald-200">
                                        Completed / Settled
                                    </span>
                                </td>
                                <td class="p-4 text-slate-500 text-xs font-mono">
                                    <?php echo date('M d, Y', strtotime($won['end_time'])); ?>
                                </td>
                                <td class="p-4 pr-6 text-right">
                                    <a href="pay.php?item_id=<?php echo (int)$won['id']; ?>" class="px-3 py-1.5 rounded-lg border border-slate-300 text-xs font-bold hover:bg-slate-100 transition">
                                        <i class="fa-solid fa-file-invoice mr-1 text-emerald-700"></i> View Order
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center py-8 text-slate-400">
                                    No completed auction wins recorded yet.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- 3. Saved Items (Watchlist) -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="p-6 border-b border-slate-200">
                <h3 class="text-lg font-bold text-slate-900 flex items-center gap-2">
                    <i class="fa-solid fa-bookmark text-indigo-600"></i> Bookmarked Lots (Watchlist)
                </h3>
            </div>
            <div class="p-6 grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
                <?php if (!empty($watchlist)): ?>
                    <?php foreach ($watchlist as $wl): ?>
                    <div class="p-4 rounded-xl border border-slate-200 bg-slate-50 hover:bg-white hover:border-emerald-600 transition flex flex-col justify-between">
                        <div>
                            <span class="text-[10px] font-bold font-mono uppercase bg-slate-200 text-slate-700 px-2 py-0.5 rounded">#<?php echo htmlspecialchars($wl['auction_code']); ?></span>
                            <h4 class="text-sm font-bold text-slate-900 mt-2 line-clamp-1"><?php echo htmlspecialchars($wl['title']); ?></h4>
                            <p class="text-xs text-slate-500 mt-1">Current: <strong class="text-emerald-800">Rs. <?php echo number_format((float)$wl['current_price'], 2); ?></strong></p>
                        </div>
                        <div class="mt-4 pt-3 border-t border-slate-200/60 flex justify-between items-center">
                            <span class="text-[11px] text-slate-400"><?php echo date('M d', strtotime($wl['end_time'])); ?></span>
                            <a href="../item.php?id=<?php echo (int)$wl['item_id']; ?>" class="text-xs font-bold text-emerald-700 hover:underline">View Auction &rarr;</a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-span-3 text-center py-6 text-slate-400 text-sm">
                        No bookmarked lots found. Click the heart icon on any auction item to save it here.
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </div>

    <!-- TAB 3: Wallet Ledger -->
    <div id="tab-content-wallet" class="<?php echo $active_tab === 'wallet' ? '' : 'hidden'; ?>">
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="p-6 border-b border-slate-200 flex justify-between items-center flex-wrap gap-4">
                <div>
                    <h3 class="text-lg font-bold text-slate-900 flex items-center gap-2">
                        <i class="fa-solid fa-receipt text-emerald-700"></i> Double-Entry Ledger Statement
                    </h3>
                    <p class="text-xs text-slate-500 mt-0.5">Immutable audit trail of all deposits, bid holds, releases, and payouts.</p>
                </div>
                <a href="wallet.php" class="px-4 py-2 bg-emerald-700 hover:bg-emerald-800 text-white font-bold text-xs rounded-xl shadow-sm transition">
                    Open Full Wallet &rarr;
                </a>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 text-slate-500 font-semibold text-xs border-b border-slate-200">
                        <tr>
                            <th class="p-4 pl-6">Date</th>
                            <th class="p-4">Reference</th>
                            <th class="p-4">Type</th>
                            <th class="p-4">Description</th>
                            <th class="p-4">Amount</th>
                            <th class="p-4">Balance After</th>
                            <th class="p-4 pr-6 text-right">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php if (!empty($transactions)): ?>
                            <?php foreach ($transactions as $tx): 
                                $is_pos = ((float)$tx['amount'] >= 0);
                            ?>
                            <tr class="hover:bg-slate-50/80 transition text-xs">
                                <td class="p-4 pl-6 font-mono text-slate-500"><?php echo date('M d, Y H:i', strtotime($tx['created_at'])); ?></td>
                                <td class="p-4 font-mono font-bold text-slate-700"><?php echo htmlspecialchars($tx['reference_id'] ?? ('TXN-' . $tx['id'])); ?></td>
                                <td class="p-4 uppercase font-bold text-slate-600"><?php echo htmlspecialchars($tx['type']); ?></td>
                                <td class="p-4 text-slate-600 max-w-xs truncate"><?php echo htmlspecialchars($tx['description'] ?? ''); ?></td>
                                <td class="p-4 font-bold <?php echo $is_pos ? 'text-emerald-700' : 'text-rose-600'; ?>">
                                    <?php echo $is_pos ? '+ Rs. ' : '- Rs. '; ?><?php echo number_format(abs((float)$tx['amount']), 2); ?>
                                </td>
                                <td class="p-4 font-mono text-slate-700">Rs. <?php echo number_format((float)$tx['balance_after'], 2); ?></td>
                                <td class="p-4 pr-6 text-right">
                                    <span class="px-2 py-0.5 rounded-full text-[11px] font-bold bg-emerald-100 text-emerald-800">
                                        <?php echo ucfirst($tx['status']); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center py-8 text-slate-400">
                                    No ledger transactions recorded yet.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- TAB 4: Security & Privacy Settings -->
    <div id="tab-content-security" class="<?php echo $active_tab === 'security' ? '' : 'hidden'; ?> space-y-8">
        
        <!-- 1. Change Password -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 sm:p-8">
            <h3 class="text-lg font-bold text-slate-900 mb-2 flex items-center gap-2">
                <i class="fa-solid fa-key text-emerald-700"></i> Change Password
            </h3>
            <p class="text-xs text-slate-500 mb-6">Ensure your account uses a secure password of at least 8 characters.</p>

            <form action="profile.php" method="POST" class="max-w-xl">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <input type="hidden" name="form_action" value="change_password">

                <div class="space-y-4 mb-6">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">Current Password</label>
                        <input type="password" name="current_password" required
                               class="w-full px-4 py-2.5 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:border-emerald-600 outline-none transition">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">New Password (Min 8 chars)</label>
                        <input type="password" name="new_password" required minlength="8"
                               class="w-full px-4 py-2.5 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:border-emerald-600 outline-none transition">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">Confirm New Password</label>
                        <input type="password" name="confirm_password" required minlength="8"
                               class="w-full px-4 py-2.5 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:border-emerald-600 outline-none transition">
                    </div>
                </div>

                <button type="submit" class="px-6 py-2.5 bg-slate-900 hover:bg-emerald-700 text-white font-bold text-sm rounded-xl transition">
                    Update Password
                </button>
            </form>
        </div>

        <!-- 2. Active Devices & Sessions Audit -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 sm:p-8">
            <div class="flex justify-between items-center mb-6 flex-wrap gap-4">
                <div>
                    <h3 class="text-lg font-bold text-slate-900 flex items-center gap-2">
                        <i class="fa-solid fa-mobile-screen-button text-emerald-700"></i> Active Devices & Sessions
                    </h3>
                    <p class="text-xs text-slate-500 mt-1">Manage and terminate active browser sessions connected to your account.</p>
                </div>

                <form action="profile.php" method="POST" onsubmit="return confirm('Are you sure you want to terminate all other active device sessions?');">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                    <input type="hidden" name="form_action" value="revoke_sessions">

                    <button type="submit" class="px-4 py-2 bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 font-bold text-xs rounded-xl shadow-sm transition inline-flex items-center gap-2">
                        <i class="fa-solid fa-power-off"></i> Terminate Other Sessions
                    </button>
                </form>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 text-slate-500 font-semibold text-xs border-b border-slate-200">
                        <tr>
                            <th class="p-3 pl-4">Device / Browser</th>
                            <th class="p-3">IP Address</th>
                            <th class="p-3">Last Active</th>
                            <th class="p-3 pr-4 text-right">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php if (!empty($active_sessions)): ?>
                            <?php foreach ($active_sessions as $sess): 
                                $is_current = ($sess['id'] === $curr_sess_id);
                            ?>
                            <tr class="hover:bg-slate-50/80 transition text-xs">
                                <td class="p-3 pl-4 font-semibold text-slate-800">
                                    <i class="fa-solid fa-desktop mr-2 text-slate-400"></i>
                                    <?php echo htmlspecialchars(substr($sess['user_agent'], 0, 70)); ?>...
                                </td>
                                <td class="p-3 font-mono text-slate-500"><?php echo htmlspecialchars($sess['ip_address']); ?></td>
                                <td class="p-3 font-mono text-slate-500"><?php echo date('M d, H:i:s', $sess['last_activity']); ?></td>
                                <td class="p-3 pr-4 text-right">
                                    <?php if ($is_current): ?>
                                        <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-200">
                                            Current Device
                                        </span>
                                    <?php else: ?>
                                        <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-slate-100 text-slate-600">
                                            Active Session
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="text-center py-6 text-slate-400 text-xs">
                                    No remote active sessions recorded.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- 3. Notification Preferences -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 sm:p-8">
            <h3 class="text-lg font-bold text-slate-900 mb-2 flex items-center gap-2">
                <i class="fa-solid fa-bell text-emerald-700"></i> Notification & Alerts Settings
            </h3>
            <p class="text-xs text-slate-500 mb-6">Choose which alerts you want to receive via email and SMS.</p>

            <form action="profile.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <input type="hidden" name="form_action" value="update_notifications">

                <div class="space-y-4 max-w-xl mb-6">
                    <label class="flex items-center gap-3 cursor-pointer">
                        <input type="checkbox" name="email_outbid" value="1" <?php echo ($notif_prefs['email_outbid'] ?? 1) ? 'checked' : ''; ?>
                               class="w-4 h-4 text-emerald-600 rounded border-slate-300 focus:ring-emerald-500">
                        <div>
                            <span class="text-sm font-bold text-slate-800 block">Outbid Notifications (Email)</span>
                            <span class="text-xs text-slate-500">Receive instant notification whenever another bidder places a higher bid.</span>
                        </div>
                    </label>

                    <label class="flex items-center gap-3 cursor-pointer">
                        <input type="checkbox" name="email_won" value="1" <?php echo ($notif_prefs['email_won'] ?? 1) ? 'checked' : ''; ?>
                               class="w-4 h-4 text-emerald-600 rounded border-slate-300 focus:ring-emerald-500">
                        <div>
                            <span class="text-sm font-bold text-slate-800 block">Auction Won & Invoice Confirmation</span>
                            <span class="text-xs text-slate-500">Receive congratulatory email and invoice payment receipt when you win.</span>
                        </div>
                    </label>

                    <label class="flex items-center gap-3 cursor-pointer">
                        <input type="checkbox" name="sms_bids" value="1" <?php echo ($notif_prefs['sms_bids'] ?? 0) ? 'checked' : ''; ?>
                               class="w-4 h-4 text-emerald-600 rounded border-slate-300 focus:ring-emerald-500">
                        <div>
                            <span class="text-sm font-bold text-slate-800 block">SMS Critical Alerts</span>
                            <span class="text-xs text-slate-500">Receive SMS notifications for high-priority lots ending soon.</span>
                        </div>
                    </label>

                    <label class="flex items-center gap-3 cursor-pointer">
                        <input type="checkbox" name="email_newsletter" value="1" <?php echo ($notif_prefs['email_newsletter'] ?? 0) ? 'checked' : ''; ?>
                               class="w-4 h-4 text-emerald-600 rounded border-slate-300 focus:ring-emerald-500">
                        <div>
                            <span class="text-sm font-bold text-slate-800 block">Weekly Featured Digest</span>
                            <span class="text-xs text-slate-500">Weekly highlights of trending vehicles, watches, and collectibles.</span>
                        </div>
                    </label>
                </div>

                <button type="submit" class="px-6 py-2.5 bg-slate-900 hover:bg-emerald-700 text-white font-bold text-sm rounded-xl transition">
                    Save Alert Preferences
                </button>
            </form>
        </div>

    </div>

    <!-- TAB: Reviews & Messages -->
    <div id="tab-content-reviews" class="<?php echo $active_tab === 'reviews' ? '' : 'hidden'; ?> space-y-8">
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 sm:p-8">
            <?php render_profile_reviews_messages($user_id); ?>
        </div>
    </div>

</div>

<!-- Tab Switching JavaScript -->
<script>
function switchTab(tabName) {
    const tabs = ['general', 'activity', 'wallet', 'reviews', 'security'];
    tabs.forEach(t => {
        const content = document.getElementById('tab-content-' + t);
        const btn = document.getElementById('tab-btn-' + t);
        if (t === tabName) {
            content.classList.remove('hidden');
            btn.classList.add('tab-active');
        } else {
            content.classList.add('hidden');
            btn.classList.remove('tab-active');
        }
    });
}

// Live Avatar Image Preview
document.getElementById('avatarInput').addEventListener('change', function(event) {
    const file = event.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('avatarPreview').src = e.target.result;
        }
        reader.readAsDataURL(file);
    }
});
</script>

<?php
include_once __DIR__ . '/../includes/footer.php';
?>