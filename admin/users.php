<?php
/**
 * User Management - Admin Panel
 * File: admin/users.php
 * 
 * Features:
 * - List and filter users with search, role tabs, and status filters
 * - Add New User (Modal with validation, role assignment, KYC tier, password hashing)
 * - Edit Existing User (Modal with pre-filled inputs, password reset option, balance)
 * - Status Toggle (Active <-> Suspended)
 * - Role Management (Admin, Seller, Buyer)
 * - Safe Delete with self-deletion and admin protection
 * - CSRF Security Protection & Prepared Statements
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include_once __DIR__ . '/../includes/database.php';
include_once __DIR__ . '/../includes/auth.php';

require_admin('../index.php');

// Ensure user profile & KYC schema compatibility
try {
    $pdo = Database::getPdo();
    $cols = $pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('username', $cols, true)) {
        $pdo->exec("ALTER TABLE `users` ADD COLUMN `username` VARCHAR(50) NULL");
    }
    if (!in_array('kyc_tier', $cols, true)) {
        $pdo->exec("ALTER TABLE `users` ADD COLUMN `kyc_tier` VARCHAR(30) NOT NULL DEFAULT 'verified_bidder'");
    }
    if (!in_array('kyc_verified_at', $cols, true)) {
        $pdo->exec("ALTER TABLE `users` ADD COLUMN `kyc_verified_at` TIMESTAMP NULL DEFAULT NULL");
    }
    if (!in_array('shipping_address', $cols, true)) {
        $pdo->exec("ALTER TABLE `users` ADD COLUMN `shipping_address` TEXT NULL");
    }
    if (!in_array('cnic_tax_id', $cols, true)) {
        $pdo->exec("ALTER TABLE `users` ADD COLUMN `cnic_tax_id` VARCHAR(50) NULL");
    }
} catch (Throwable $e) {
    error_log("Schema sync for users table: " . $e->getMessage());
}

$current_admin_id = (int)($_SESSION['user_id'] ?? 0);
$msg      = '';
$msg_type = 'success'; // 'success' or 'danger'

// CSRF Token setup
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

if (!function_exists('check_csrf')) {
    function check_csrf($token) {
        return !empty($token) && !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
}

// Helper to resolve clean avatar image URL with fallback
if (!function_exists('get_user_avatar_img_src')) {
    function get_user_avatar_img_src($avatar, $user_name) {
        if (!empty($avatar)) {
            $avatar = trim((string)$avatar);
            if (preg_match('/^(https?:\/\/|\/\/|data:)/i', $avatar)) {
                return $avatar;
            }
            $clean = ltrim($avatar, '/\\');
            if (file_exists(__DIR__ . '/../' . $clean)) {
                return '../' . $clean;
            }
        }
        // Local asset fallback
        if (file_exists(__DIR__ . '/../assets/image/other.png')) {
            return '../assets/image/other.png';
        }
        if (file_exists(__DIR__ . '/../assets/image/user.jpg')) {
            return '../assets/image/user.jpg';
        }
        return 'https://ui-avatars.com/api/?name=' . urlencode($user_name ?: 'User') . '&background=047857&color=fff&size=128&bold=true';
    }
}

// -------------------------------------------------------------
// POST Request Handling: Add, Edit, Toggle, Delete, Role Change
// -------------------------------------------------------------
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = $_POST['action'] ?? '';
    $token  = $_POST['csrf_token'] ?? '';

    if (!check_csrf($token)) {
        $msg = 'Security token invalid or expired. Please refresh and try again.';
        $msg_type = 'danger';
    } else {

        // 1. ADD NEW USER
        if ($action === 'add_user') {
            $name         = trim($_POST['name'] ?? '');
            $username     = trim($_POST['username'] ?? '');
            $email        = trim($_POST['email'] ?? '');
            $phone        = trim($_POST['phone'] ?? '');
            $role         = strtolower(trim($_POST['role'] ?? 'buyer'));
            $gender       = strtolower(trim($_POST['gender'] ?? 'male'));
            $status       = strtolower(trim($_POST['status'] ?? 'active'));
            $kyc_tier     = strtolower(trim($_POST['kyc_tier'] ?? 'unverified'));
            $balance      = max(0, (float)($_POST['balance'] ?? 0));
            $password     = trim($_POST['password'] ?? '');
            $confirm_pass = trim($_POST['confirm_password'] ?? '');

            // Allowed values
            if (!in_array($role, ['buyer', 'seller', 'admin'], true)) { $role = 'buyer'; }
            if (!in_array($gender, ['male', 'female', 'other'], true)) { $gender = 'male'; }
            if (!in_array($status, ['active', 'inactive', 'suspended'], true)) { $status = 'active'; }
            if (!in_array($kyc_tier, ['unverified', 'verified_bidder', 'vip_buyer'], true)) { $kyc_tier = 'unverified'; }

            if ($name === '') {
                $msg = 'Please enter the user full name.';
                $msg_type = 'danger';
            } elseif ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $msg = 'Please provide a valid email address.';
                $msg_type = 'danger';
            } elseif (strlen($password) < 6) {
                $msg = 'Password must be at least 6 characters long.';
                $msg_type = 'danger';
            } elseif ($password !== $confirm_pass) {
                $msg = 'Password and confirmation password do not match.';
                $msg_type = 'danger';
            } else {
                // Check if email already exists
                $chk_stmt = mysqli_prepare($conn, "SELECT id FROM users WHERE email = ? LIMIT 1");
                mysqli_stmt_bind_param($chk_stmt, "s", $email);
                mysqli_stmt_execute($chk_stmt);
                mysqli_stmt_store_result($chk_stmt);

                if (mysqli_stmt_num_rows($chk_stmt) > 0) {
                    $msg = 'A user with this email address already exists.';
                    $msg_type = 'danger';
                    mysqli_stmt_close($chk_stmt);
                } else {
                    mysqli_stmt_close($chk_stmt);

                    // Check username uniqueness if provided
                    $username_val = !empty($username) ? $username : null;
                    $username_conflict = false;
                    if ($username_val !== null) {
                        $u_chk = mysqli_prepare($conn, "SELECT id FROM users WHERE username = ? LIMIT 1");
                        mysqli_stmt_bind_param($u_chk, "s", $username_val);
                        mysqli_stmt_execute($u_chk);
                        mysqli_stmt_store_result($u_chk);
                        if (mysqli_stmt_num_rows($u_chk) > 0) {
                            $username_conflict = true;
                        }
                        mysqli_stmt_close($u_chk);
                    }

                    if ($username_conflict) {
                        $msg = 'The username "@' . htmlspecialchars($username_val) . '" is already taken.';
                        $msg_type = 'danger';
                    } else {
                        $hashed_pass = password_hash($password, PASSWORD_DEFAULT);
                        $avatar = ($gender === 'female') ? 'assets/image/female.png' : 'assets/image/other.png';

                        // Process uploaded avatar image if provided
                        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
                            $f_tmp  = $_FILES['avatar']['tmp_name'];
                            $f_name = $_FILES['avatar']['name'];
                            $f_size = $_FILES['avatar']['size'];
                            $f_mime = mime_content_type($f_tmp);
                            $allowed = ['image/jpeg', 'image/png', 'image/webp'];

                            if (in_array($f_mime, $allowed, true) && $f_size <= 2 * 1024 * 1024) {
                                $upload_dir = __DIR__ . '/../uploads/avatars/';
                                if (!is_dir($upload_dir)) { mkdir($upload_dir, 0755, true); }
                                $ext = strtolower(pathinfo($f_name, PATHINFO_EXTENSION));
                                $new_file = 'avatar_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                                if (move_uploaded_file($f_tmp, $upload_dir . $new_file)) {
                                    $avatar = 'uploads/avatars/' . $new_file;
                                }
                            }
                        }

                        $ins_stmt = mysqli_prepare($conn, "
                            INSERT INTO users (name, username, email, phone, role, gender, status, kyc_tier, balance, password, avatar, created_at)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                        ");
                        mysqli_stmt_bind_param($ins_stmt, "ssssssssdss", 
                            $name, 
                            $username_val, 
                            $email, 
                            $phone, 
                            $role, 
                            $gender,
                            $status, 
                            $kyc_tier, 
                            $balance, 
                            $hashed_pass, 
                            $avatar
                        );

                        if (mysqli_stmt_execute($ins_stmt)) {
                            $new_uid = mysqli_insert_id($conn);
                            $msg = 'User "' . htmlspecialchars($name) . '" (#' . $new_uid . ') created successfully!';
                            $msg_type = 'success';
                        } else {
                            $msg = 'Database insertion failed: ' . mysqli_stmt_error($ins_stmt);
                            $msg_type = 'danger';
                        }
                        mysqli_stmt_close($ins_stmt);
                    }
                }
            }

        // 2. EDIT EXISTING USER
        } elseif ($action === 'edit_user') {
            $uid          = (int)($_POST['user_id'] ?? 0);
            $name         = trim($_POST['name'] ?? '');
            $username     = trim($_POST['username'] ?? '');
            $email        = trim($_POST['email'] ?? '');
            $phone        = trim($_POST['phone'] ?? '');
            $role         = strtolower(trim($_POST['role'] ?? 'buyer'));
            $gender       = strtolower(trim($_POST['gender'] ?? 'male'));
            $status       = strtolower(trim($_POST['status'] ?? 'active'));
            $kyc_tier     = strtolower(trim($_POST['kyc_tier'] ?? 'unverified'));
            $balance          = max(0, (float)($_POST['balance'] ?? 0));
            $cnic_tax_id      = trim($_POST['cnic_tax_id'] ?? '');
            $shipping_address = trim($_POST['shipping_address'] ?? '');
            $bio              = trim($_POST['bio'] ?? '');
            $new_password     = trim($_POST['new_password'] ?? '');

            if (!in_array($role, ['buyer', 'seller', 'admin'], true)) { $role = 'buyer'; }
            if (!in_array($gender, ['male', 'female', 'other'], true)) { $gender = 'male'; }
            if (!in_array($status, ['active', 'inactive', 'suspended'], true)) { $status = 'active'; }
            if (!in_array($kyc_tier, ['unverified', 'verified_bidder', 'vip_buyer'], true)) { $kyc_tier = 'unverified'; }

            if ($uid <= 0) {
                $msg = 'Invalid user identifier.';
                $msg_type = 'danger';
            } elseif ($name === '') {
                $msg = 'User name cannot be empty.';
                $msg_type = 'danger';
            } elseif ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $msg = 'Please enter a valid email address.';
                $msg_type = 'danger';
            } else {
                // Check email uniqueness excluding this user
                $chk_email = mysqli_prepare($conn, "SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1");
                mysqli_stmt_bind_param($chk_email, "si", $email, $uid);
                mysqli_stmt_execute($chk_email);
                mysqli_stmt_store_result($chk_email);

                if (mysqli_stmt_num_rows($chk_email) > 0) {
                    $msg = 'This email address is already used by another user.';
                    $msg_type = 'danger';
                    mysqli_stmt_close($chk_email);
                } else {
                    mysqli_stmt_close($chk_email);

                    // Check username uniqueness if provided
                    $username_val = !empty($username) ? $username : null;
                    $username_conflict = false;
                    if ($username_val !== null) {
                        $chk_u = mysqli_prepare($conn, "SELECT id FROM users WHERE username = ? AND id != ? LIMIT 1");
                        mysqli_stmt_bind_param($chk_u, "si", $username_val, $uid);
                        mysqli_stmt_execute($chk_u);
                        mysqli_stmt_store_result($chk_u);
                        if (mysqli_stmt_num_rows($chk_u) > 0) {
                            $username_conflict = true;
                        }
                        mysqli_stmt_close($chk_u);
                    }

                    if ($username_conflict) {
                        $msg = 'The username "@' . htmlspecialchars($username_val) . '" is already taken by another account.';
                        $msg_type = 'danger';
                    } elseif ($new_password !== '' && strlen($new_password) < 6) {
                        $msg = 'New password must be at least 6 characters long.';
                        $msg_type = 'danger';
                    } else {
                        // Protect against self-demotion or self-suspension
                        if ($uid === $current_admin_id && $role !== 'admin') {
                            $role = 'admin'; // Prevent accidental lockout
                        }
                        if ($uid === $current_admin_id && $status !== 'active') {
                            $status = 'active';
                        }

                        $kyc_sql = ($kyc_tier === 'verified_bidder' || $kyc_tier === 'vip_buyer') 
                            ? "IFNULL(kyc_verified_at, NOW())" 
                            : "NULL";

                        if ($new_password !== '') {
                            // Update with password reset
                            $hashed_pass = password_hash($new_password, PASSWORD_DEFAULT);
                            $upd_stmt = mysqli_prepare($conn, "
                                UPDATE users 
                                SET name = ?, username = ?, email = ?, phone = ?, role = ?, gender = ?, status = ?, kyc_tier = ?, kyc_verified_at = {$kyc_sql}, cnic_tax_id = ?, shipping_address = ?, bio = ?, balance = ?, password = ?, updated_at = NOW() 
                                WHERE id = ?
                            ");
                            mysqli_stmt_bind_param($upd_stmt, "sssssssssssdsi", 
                                $name, 
                                $username_val, 
                                $email, 
                                $phone, 
                                $role, 
                                $gender, 
                                $status, 
                                $kyc_tier, 
                                $cnic_tax_id,
                                $shipping_address,
                                $bio,
                                $balance, 
                                $hashed_pass, 
                                $uid
                            );
                        } else {
                            // Update without modifying password
                            $upd_stmt = mysqli_prepare($conn, "
                                UPDATE users 
                                SET name = ?, username = ?, email = ?, phone = ?, role = ?, gender = ?, status = ?, kyc_tier = ?, kyc_verified_at = {$kyc_sql}, cnic_tax_id = ?, shipping_address = ?, bio = ?, balance = ?, updated_at = NOW() 
                                WHERE id = ?
                            ");
                            mysqli_stmt_bind_param($upd_stmt, "sssssssssssdi", 
                                $name, 
                                $username_val, 
                                $email, 
                                $phone, 
                                $role, 
                                $gender, 
                                $status, 
                                $kyc_tier, 
                                $cnic_tax_id,
                                $shipping_address,
                                $bio,
                                $balance, 
                                $uid
                            );
                        }

                        if (mysqli_stmt_execute($upd_stmt)) {
                            $msg = 'User #' . $uid . ' (' . htmlspecialchars($name) . ') updated successfully.';
                            $msg_type = 'success';

                            // Process new avatar upload if provided
                            if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
                                $f_tmp  = $_FILES['avatar']['tmp_name'];
                                $f_name = $_FILES['avatar']['name'];
                                $f_size = $_FILES['avatar']['size'];
                                $f_mime = mime_content_type($f_tmp);
                                $allowed = ['image/jpeg', 'image/png', 'image/webp'];

                                if (in_array($f_mime, $allowed, true) && $f_size <= 2 * 1024 * 1024) {
                                    $upload_dir = __DIR__ . '/../uploads/avatars/';
                                    if (!is_dir($upload_dir)) { mkdir($upload_dir, 0755, true); }
                                    $ext = strtolower(pathinfo($f_name, PATHINFO_EXTENSION));
                                    $new_file = 'avatar_' . $uid . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                                    if (move_uploaded_file($f_tmp, $upload_dir . $new_file)) {
                                        $new_av_path = 'uploads/avatars/' . $new_file;
                                        $av_upd = mysqli_prepare($conn, "UPDATE users SET avatar = ? WHERE id = ?");
                                        mysqli_stmt_bind_param($av_upd, "si", $new_av_path, $uid);
                                        mysqli_stmt_execute($av_upd);
                                        mysqli_stmt_close($av_upd);
                                    }
                                }
                            }
                        } else {
                            $msg = 'Database update error: ' . mysqli_stmt_error($upd_stmt);
                            $msg_type = 'danger';
                        }
                        mysqli_stmt_close($upd_stmt);
                    }
                }
            }

        // 3. TOGGLE STATUS
        } elseif ($action === 'toggle_status') {
            $uid = (int)($_POST['user_id'] ?? 0);
            $new_status = ($_POST['new_status'] ?? '') === 'active' ? 'active' : 'suspended';

            if ($uid === $current_admin_id && $new_status === 'suspended') {
                $msg = 'You cannot suspend your own active administrator account.';
                $msg_type = 'danger';
            } elseif ($uid > 0) {
                $stmt = mysqli_prepare($conn, "UPDATE users SET status = ? WHERE id = ?");
                mysqli_stmt_bind_param($stmt, "si", $new_status, $uid);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
                $msg = 'User #' . $uid . ' status set to "' . ucfirst($new_status) . '".';
                $msg_type = 'success';
            }

        // 4. CHANGE ROLE
        } elseif ($action === 'change_role') {
            $uid      = (int)($_POST['user_id'] ?? 0);
            $new_role = strtolower(trim($_POST['role'] ?? 'buyer'));

            if (!in_array($new_role, ['admin', 'seller', 'buyer'], true)) {
                $new_role = 'buyer';
            }

            if ($uid === $current_admin_id && $new_role !== 'admin') {
                $msg = 'You cannot remove your own administrator privileges.';
                $msg_type = 'danger';
            } elseif ($uid > 0) {
                $stmt = mysqli_prepare($conn, "UPDATE users SET role = ? WHERE id = ?");
                mysqli_stmt_bind_param($stmt, "si", $new_role, $uid);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
                $msg = 'User #' . $uid . ' role updated to "' . ucfirst($new_role) . '".';
                $msg_type = 'success';
            }

        // 5. DELETE USER
        } elseif ($action === 'delete') {
            $uid = (int)($_POST['user_id'] ?? 0);

            if ($uid <= 0) {
                $msg = 'Invalid user identifier.';
                $msg_type = 'danger';
            } elseif ($uid === $current_admin_id) {
                $msg = 'You cannot delete your own account while logged in.';
                $msg_type = 'danger';
            } else {
                // Check if user is an admin
                $chk_adm = mysqli_prepare($conn, "SELECT role FROM users WHERE id = ?");
                mysqli_stmt_bind_param($chk_adm, "i", $uid);
                mysqli_stmt_execute($chk_adm);
                $res_adm = mysqli_stmt_get_result($chk_adm);
                $target_user = mysqli_fetch_assoc($res_adm);
                mysqli_stmt_close($chk_adm);

                if ($target_user && $target_user['role'] === 'admin') {
                    $msg = 'Administrator accounts cannot be deleted directly. Demote their role first.';
                    $msg_type = 'danger';
                } else {
                    $del_stmt = mysqli_prepare($conn, "DELETE FROM users WHERE id = ?");
                    mysqli_stmt_bind_param($del_stmt, "i", $uid);
                    if (mysqli_stmt_execute($del_stmt)) {
                        $msg = 'User #' . $uid . ' deleted successfully.';
                        $msg_type = 'success';
                    } else {
                        $msg = 'Failed to delete user. The user may have active auction lots or placed bids.';
                        $msg_type = 'danger';
                    }
                    mysqli_stmt_close($del_stmt);
                }
            }
        }
    }
}

// -------------------------------------------------------------
// Filters & Search Construction
// -------------------------------------------------------------
$search      = trim($_GET['search'] ?? '');
$role_filter = trim($_GET['role'] ?? '');
$stat_filter = trim($_GET['status'] ?? '');

$where_parts = [];
$params      = [];
$types       = '';

if ($search !== '') {
    $where_parts[] = "(name LIKE ? OR email LIKE ? OR username LIKE ? OR phone LIKE ?)";
    $like = '%' . $search . '%';
    for ($k = 0; $k < 4; $k++) {
        $params[] = $like;
        $types   .= 's';
    }
}

if ($role_filter !== '' && in_array($role_filter, ['admin', 'seller', 'buyer'], true)) {
    $where_parts[] = "role = ?";
    $params[]      = $role_filter;
    $types        .= 's';
}

if ($stat_filter !== '' && in_array($stat_filter, ['active', 'inactive', 'suspended'], true)) {
    $where_parts[] = "status = ?";
    $params[]      = $stat_filter;
    $types        .= 's';
}

$where_sql = !empty($where_parts) ? 'WHERE ' . implode(' AND ', $where_parts) : '';

// -------------------------------------------------------------
// Fetch Statistics Counters
// -------------------------------------------------------------
$cnt_total     = (int)(mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM users"))['c'] ?? 0);
$cnt_active    = (int)(mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM users WHERE status = 'active'"))['c'] ?? 0);
$cnt_suspended = (int)(mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM users WHERE status = 'suspended'"))['c'] ?? 0);
$cnt_admins    = (int)(mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM users WHERE role = 'admin'"))['c'] ?? 0);
$cnt_sellers   = (int)(mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM users WHERE role = 'seller'"))['c'] ?? 0);
$cnt_buyers    = (int)(mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM users WHERE role = 'buyer'"))['c'] ?? 0);

// Fetch Filtered Users List
$fetch_query = "SELECT * FROM users {$where_sql} ORDER BY id DESC";
$stmt_users  = mysqli_prepare($conn, $fetch_query);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt_users, $types, ...$params);
}
mysqli_stmt_execute($stmt_users);
$users_res = mysqli_stmt_get_result($stmt_users);

// Check if a direct edit modal trigger is requested via GET (?edit=ID)
$direct_edit_user = null;
if (isset($_GET['edit'])) {
    $edit_target_id = (int)$_GET['edit'];
    if ($edit_target_id > 0) {
        $de_stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE id = ? LIMIT 1");
        mysqli_stmt_bind_param($de_stmt, "i", $edit_target_id);
        mysqli_stmt_execute($de_stmt);
        $direct_edit_user = mysqli_fetch_assoc(mysqli_stmt_get_result($de_stmt));
        mysqli_stmt_close($de_stmt);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users – Admin Panel</title>
    <!-- Bootstrap 5 & FontAwesome 6 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-green: #047857;
            --primary-green-hover: #065f46;
            --primary-light: rgba(4, 120, 87, 0.08);
            --dark-bg: #1a1d21;
        }
        body { 
            background-color: #f8fafc; 
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            color: #334155;
        }
        .sidebar { 
            height: 100vh; 
            background-color: var(--dark-bg); 
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
            transition: 0.25s ease; 
            font-size: 14px;
            font-weight: 500;
        }
        .sidebar a:hover, .sidebar a.active { 
            color: #fff; 
            background-color: var(--primary-green); 
        }
        .sidebar .nav-category { 
            font-size: 0.72rem; 
            text-transform: uppercase; 
            letter-spacing: 1.2px; 
            color: #4a6a7a; 
            padding: 12px 20px 6px; 
            margin-top: 6px; 
            font-weight: 700;
        }
        .main-content { 
            margin-left: 250px; 
            padding: 30px; 
        }
        @media(max-width: 992px) {
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
        .stat-card {
            background: #ffffff;
            border-radius: 14px;
            border: 1px solid #e2e8f0;
            padding: 18px 20px;
            transition: all 0.2s ease;
        }
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(0,0,0,0.05);
        }
        .btn-emerald {
            background-color: var(--primary-green);
            color: #ffffff;
            border: none;
            font-weight: 600;
        }
        .btn-emerald:hover {
            background-color: var(--primary-green-hover);
            color: #ffffff;
        }
        .user-avatar-img {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid rgba(4, 120, 87, 0.25);
            background-color: #f1f5f9;
            flex-shrink: 0;
            display: inline-block;
            box-shadow: 0 2px 5px rgba(0,0,0,0.06);
        }
        .user-avatar-circle {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 14px;
            background: var(--primary-light);
            color: var(--primary-green);
            border: 2px solid rgba(4, 120, 87, 0.25);
            flex-shrink: 0;
            box-shadow: 0 2px 5px rgba(0,0,0,0.06);
        }
        .badge-role-admin {
            background: rgba(220, 38, 38, 0.12);
            color: #dc2626;
            border: 1px solid rgba(220, 38, 38, 0.25);
        }
        .badge-role-seller {
            background: rgba(217, 119, 6, 0.12);
            color: #d97706;
            border: 1px solid rgba(217, 119, 6, 0.25);
        }
        .badge-role-buyer {
            background: rgba(2, 132, 199, 0.12);
            color: #0284c7;
            border: 1px solid rgba(2, 132, 199, 0.25);
        }
        .badge-status-active {
            background: rgba(16, 185, 129, 0.12);
            color: #059669;
            border: 1px solid rgba(16, 185, 129, 0.25);
        }
        .badge-status-suspended {
            background: rgba(239, 68, 68, 0.12);
            color: #dc2626;
            border: 1px solid rgba(239, 68, 68, 0.25);
        }
        .badge-status-inactive {
            background: rgba(100, 116, 139, 0.12);
            color: #64748b;
            border: 1px solid rgba(100, 116, 139, 0.25);
        }
    </style>
</head>
<body>
    <?php include_once __DIR__ . '/../includes/components/loading_spinner_overlay.php'; ?>

    <!-- Sidebar Navigation -->
    <div class="sidebar">
        <h3 class="text-center text-white mb-4 fs-5 fw-bold"><i class="fas fa-gavel me-2 text-success"></i>GeoAuction Admin</h3>
        <div class="nav-category">Core</div>
        <a href="dashboard.php"><i class="fas fa-tachometer-alt me-2"></i> Dashboard</a>
        <a href="../user/dashboard.php"><i class="fas fa-house me-2"></i> User Portal</a>
        
        <div class="nav-category">Management</div>
        <a href="users.php" class="active"><i class="fas fa-users me-2"></i> Users</a>
        <a href="categories.php"><i class="fas fa-tags me-2"></i> Categories</a>
        <a href="items.php"><i class="fas fa-box-open me-2"></i> Items</a>
        <a href="bids.php"><i class="fas fa-hand-holding-usd me-2"></i> Bids</a>
        <a href="payments.php"><i class="fas fa-credit-card me-2"></i> Payments</a>
        
        <div class="nav-category">System</div>
        <a href="settings.php"><i class="fas fa-sliders-h me-2"></i> Platform Settings</a>
        <a href="setting.php"><i class="fas fa-database me-2"></i> Database & Backup</a>
        <a href="reports.php"><i class="fas fa-chart-line me-2"></i> Reports</a>
        <a href="logout.php" class="text-danger mt-4"><i class="fas fa-sign-out-alt me-2"></i> Logout</a>
    </div>

    <!-- Main Content Container -->
    <div class="main-content">
        
        <!-- Header with Add User Button -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
            <div>
                <h2 class="fw-bold mb-1 text-dark d-flex align-items-center gap-2">
                    <i class="fas fa-users text-success"></i> Manage Users
                </h2>
                <p class="text-muted small mb-0">View registered accounts, edit profiles, adjust permissions, and register new users.</p>
            </div>
            <div class="d-flex align-items-center gap-3">
                <span class="text-muted bg-white px-3 py-2 border rounded-3 shadow-sm text-xs">
                    Admin: <strong class="text-dark"><?= htmlspecialchars($_SESSION['user_name'] ?? 'Admin'); ?></strong>
                </span>
                <!-- ADD USER BUTTON -->
                <button type="button" class="btn btn-emerald px-3 py-2 rounded-3 shadow-sm d-inline-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#addUserModal">
                    <i class="fas fa-user-plus"></i>
                    <span>+ Add New User</span>
                </button>
            </div>
        </div>

        <!-- Alert Notification -->
        <?php if ($msg): ?>
            <div class="alert alert-<?= $msg_type; ?> alert-dismissible fade show shadow-sm border-0 rounded-3 mb-4 d-flex align-items-center gap-2">
                <i class="fas <?= $msg_type === 'success' ? 'fa-circle-check text-success' : 'fa-circle-exclamation text-danger'; ?> fs-5"></i>
                <div><?= htmlspecialchars($msg); ?></div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Quick Summary Stat Cards -->
        <div class="row g-3 mb-4">
            <div class="col-6 col-md-4 col-xl-2">
                <div class="stat-card">
                    <div class="text-muted small fw-semibold">Total Accounts</div>
                    <div class="fs-4 fw-bold text-dark mt-1"><?= number_format($cnt_total); ?></div>
                </div>
            </div>
            <div class="col-6 col-md-4 col-xl-2">
                <div class="stat-card">
                    <div class="text-muted small fw-semibold text-success"><i class="fas fa-check-circle me-1"></i> Active Users</div>
                    <div class="fs-4 fw-bold text-success mt-1"><?= number_format($cnt_active); ?></div>
                </div>
            </div>
            <div class="col-6 col-md-4 col-xl-2">
                <div class="stat-card">
                    <div class="text-muted small fw-semibold text-info"><i class="fas fa-user-tag me-1"></i> Buyers</div>
                    <div class="fs-4 fw-bold text-info mt-1"><?= number_format($cnt_buyers); ?></div>
                </div>
            </div>
            <div class="col-6 col-md-4 col-xl-2">
                <div class="stat-card">
                    <div class="text-muted small fw-semibold text-warning"><i class="fas fa-store me-1"></i> Sellers</div>
                    <div class="fs-4 fw-bold text-warning mt-1"><?= number_format($cnt_sellers); ?></div>
                </div>
            </div>
            <div class="col-6 col-md-4 col-xl-2">
                <div class="stat-card">
                    <div class="text-muted small fw-semibold text-danger"><i class="fas fa-user-shield me-1"></i> Admins</div>
                    <div class="fs-4 fw-bold text-danger mt-1"><?= number_format($cnt_admins); ?></div>
                </div>
            </div>
            <div class="col-6 col-md-4 col-xl-2">
                <div class="stat-card">
                    <div class="text-muted small fw-semibold text-secondary"><i class="fas fa-ban me-1"></i> Suspended</div>
                    <div class="fs-4 fw-bold text-secondary mt-1"><?= number_format($cnt_suspended); ?></div>
                </div>
            </div>
        </div>

        <!-- Filter & Search Controls Bar -->
        <div class="card border-0 shadow-sm mb-4" style="border-radius: 14px;">
            <div class="card-body p-3">
                <form method="GET" action="users.php" class="row g-2 align-items-center">
                    
                    <!-- Search Input -->
                    <div class="col-md-5">
                        <div class="input-group">
                            <span class="input-group-text bg-white border-end-0 text-muted"><i class="fas fa-search"></i></span>
                            <input type="text" name="search" class="form-control border-start-0 ps-0 shadow-none text-sm" placeholder="Search by name, email, username or phone..." value="<?= htmlspecialchars($search); ?>">
                        </div>
                    </div>

                    <!-- Role Filter Dropdown -->
                    <div class="col-md-3">
                        <select name="role" class="form-select shadow-none text-sm" onchange="this.form.submit()">
                            <option value="">All Roles (<?= $cnt_total; ?>)</option>
                            <option value="buyer" <?= $role_filter === 'buyer' ? 'selected' : ''; ?>>Buyers Only (<?= $cnt_buyers; ?>)</option>
                            <option value="seller" <?= $role_filter === 'seller' ? 'selected' : ''; ?>>Sellers Only (<?= $cnt_sellers; ?>)</option>
                            <option value="admin" <?= $role_filter === 'admin' ? 'selected' : ''; ?>>Administrators (<?= $cnt_admins; ?>)</option>
                        </select>
                    </div>

                    <!-- Status Filter Dropdown -->
                    <div class="col-md-2">
                        <select name="status" class="form-select shadow-none text-sm" onchange="this.form.submit()">
                            <option value="">All Statuses</option>
                            <option value="active" <?= $stat_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?= $stat_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            <option value="suspended" <?= $stat_filter === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                        </select>
                    </div>

                    <!-- Search Button -->
                    <div class="col-md-2 d-flex gap-2">
                        <button type="submit" class="btn btn-emerald w-100 fw-semibold text-sm">Filter</button>
                        <?php if ($search || $role_filter || $stat_filter): ?>
                            <a href="users.php" class="btn btn-outline-secondary text-sm" title="Clear Filters"><i class="fas fa-times"></i></a>
                        <?php endif; ?>
                    </div>

                </form>
            </div>
        </div>

        <!-- Users Table -->
        <div class="card border-0 shadow-sm" style="border-radius: 14px; overflow: hidden;">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="py-3 ps-3">#ID</th>
                                <th class="py-3">User Details</th>
                                <th class="py-3">Contact</th>
                                <th class="py-3">Role</th>
                                <th class="py-3">KYC Tier</th>
                                <th class="py-3">Balance</th>
                                <th class="py-3">Status</th>
                                <th class="py-3">Joined</th>
                                <th class="py-3 text-end pe-3">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($users_res && mysqli_num_rows($users_res) > 0): ?>
                                <?php while ($user = mysqli_fetch_assoc($users_res)): 
                                    $u_role = strtolower($user['role'] ?? 'buyer');
                                    $u_stat = strtolower($user['status'] ?? 'active');
                                    $u_kyc  = strtolower($user['kyc_tier'] ?? 'unverified');
                                    $initials = strtoupper(substr($user['name'] ?: 'User', 0, 2));
                                ?>
                                <tr>
                                    <td class="ps-3 fw-bold text-muted small">#<?= $user['id']; ?></td>
                                    
                                    <!-- User Avatar & Name -->
                                    <td>
                                        <div class="d-flex align-items-center gap-2.5">
                                            <?php 
                                                $user_av_src = get_user_avatar_img_src($user['avatar'] ?? '', $user['name']);
                                            ?>
                                            <img src="<?= htmlspecialchars($user_av_src); ?>" 
                                                 alt="<?= htmlspecialchars($user['name']); ?>" 
                                                 class="user-avatar-img"
                                                 onerror="this.onerror=null; this.src='../assets/image/other.png';">
                                            <div>
                                                <div class="fw-bold text-dark">
                                                    <?= htmlspecialchars($user['name']); ?>
                                                    <?php if ((int)$user['id'] === $current_admin_id): ?>
                                                        <span class="badge bg-secondary text-white text-[10px] px-1.5 py-0.5 ms-1">You</span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="text-muted small d-flex align-items-center flex-wrap gap-1">
                                                    <?= !empty($user['username']) ? '@' . htmlspecialchars($user['username']) : '<span class="fst-italic text-slate-400">no username</span>'; ?>
                                                    <?php if (!empty($user['gender'])): ?>
                                                        <span class="badge bg-light text-secondary border text-[10px] px-1.5 py-0.5 rounded-pill text-capitalize" title="Gender: <?= htmlspecialchars($user['gender']); ?>">
                                                            <i class="fas <?= $user['gender'] === 'female' ? 'fa-venus text-danger' : ($user['gender'] === 'male' ? 'fa-mars text-primary' : 'fa-genderless text-secondary'); ?>"></i> <?= htmlspecialchars($user['gender']); ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Contact Info -->
                                    <td>
                                        <div class="text-dark small fw-medium"><?= htmlspecialchars($user['email']); ?></div>
                                        <div class="text-muted small"><?= htmlspecialchars($user['phone'] ?: 'No phone added'); ?></div>
                                    </td>

                                    <!-- Role Badge -->
                                    <td>
                                        <span class="badge badge-role-<?= $u_role; ?> px-2.5 py-1 text-xs fw-bold rounded-pill text-capitalize">
                                            <?= $u_role; ?>
                                        </span>
                                    </td>

                                    <!-- KYC Tier -->
                                    <td>
                                        <span class="badge bg-light text-secondary border px-2 py-1 text-xs rounded-pill">
                                            <?= htmlspecialchars(str_replace('_', ' ', $u_kyc)); ?>
                                        </span>
                                    </td>

                                    <!-- Balance -->
                                    <td class="fw-bold text-success">
                                        PKR <?= number_format((float)($user['balance'] ?? 0)); ?>
                                    </td>

                                    <!-- Status Badge -->
                                    <td>
                                        <span class="badge badge-status-<?= $u_stat; ?> px-2.5 py-1 text-xs fw-bold rounded-pill text-capitalize">
                                            <?= $u_stat; ?>
                                        </span>
                                    </td>

                                    <!-- Created Date -->
                                    <td class="text-muted small">
                                        <?= date('d M Y', strtotime($user['created_at'])); ?>
                                    </td>

                                    <!-- Actions Column: Edit, Status, Delete -->
                                    <td class="text-end pe-3">
                                        
                                        <!-- EDIT BUTTON (Triggers Edit Modal) -->
                                        <button type="button" 
                                                class="btn btn-sm btn-outline-primary me-1" 
                                                title="Edit User Details"
                                                data-bs-toggle="modal" 
                                                data-bs-target="#editUserModal"
                                                data-id="<?= $user['id']; ?>"
                                                data-name="<?= htmlspecialchars($user['name'], ENT_QUOTES); ?>"
                                                data-username="<?= htmlspecialchars($user['username'] ?? '', ENT_QUOTES); ?>"
                                                data-email="<?= htmlspecialchars($user['email'], ENT_QUOTES); ?>"
                                                data-phone="<?= htmlspecialchars($user['phone'] ?? '', ENT_QUOTES); ?>"
                                                data-role="<?= $u_role; ?>"
                                                data-gender="<?= htmlspecialchars($user['gender'] ?? 'male', ENT_QUOTES); ?>"
                                                data-status="<?= $u_stat; ?>"
                                                data-kyc="<?= $u_kyc; ?>"
                                                data-cnic="<?= htmlspecialchars($user['cnic_tax_id'] ?? '', ENT_QUOTES); ?>"
                                                data-shipping="<?= htmlspecialchars($user['shipping_address'] ?? '', ENT_QUOTES); ?>"
                                                data-bio="<?= htmlspecialchars($user['bio'] ?? '', ENT_QUOTES); ?>"
                                                data-balance="<?= (float)($user['balance'] ?? 0); ?>"
                                                data-avatar="<?= htmlspecialchars($user_av_src); ?>">
                                            <i class="fas fa-edit"></i>
                                        </button>

                                        <!-- Toggle Status (Active <-> Suspended) -->
                                        <?php if ((int)$user['id'] !== $current_admin_id): ?>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Change status for this user?');">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token); ?>">
                                                <input type="hidden" name="action" value="toggle_status">
                                                <input type="hidden" name="user_id" value="<?= $user['id']; ?>">
                                                <input type="hidden" name="new_status" value="<?= ($u_stat === 'active') ? 'suspended' : 'active'; ?>">
                                                <button type="submit" 
                                                        class="btn btn-sm <?= ($u_stat === 'active') ? 'btn-outline-warning' : 'btn-outline-success'; ?> me-1" 
                                                        title="<?= ($u_stat === 'active') ? 'Suspend User' : 'Activate User'; ?>">
                                                    <i class="fas <?= ($u_stat === 'active') ? 'fa-ban' : 'fa-check'; ?>"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>

                                        <!-- Delete Button (Only for Non-Admins & Not Self) -->
                                        <?php if ($u_role !== 'admin' && (int)$user['id'] !== $current_admin_id): ?>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Permanently delete user <?= htmlspecialchars(addslashes($user['name'])); ?>? This action cannot be undone.');">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token); ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="user_id" value="<?= $user['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete User">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>

                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="9" class="text-center py-5 text-muted">
                                        <i class="fas fa-users-slash fs-2 d-block mb-2 text-secondary"></i>
                                        No users found matching your search and filter criteria.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>

    <!-- =============================================================== -->
    <!-- MODAL 1: ADD NEW USER                                           -->
    <!-- =============================================================== -->
    <div class="modal fade" id="addUserModal" tabindex="-1" aria-labelledby="addUserModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 shadow rounded-4">
                <form method="POST" action="users.php" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token); ?>">
                    <input type="hidden" name="action" value="add_user">

                    <div class="modal-header border-bottom py-3 px-4">
                        <h5 class="modal-title fw-bold text-dark d-flex align-items-center gap-2" id="addUserModalLabel">
                            <i class="fas fa-user-plus text-success"></i> Add New User Account
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    <div class="modal-body p-4">
                        <div class="row g-3">
                            <!-- Full Name -->
                            <div class="col-md-6">
                                <label class="form-label fw-semibold text-secondary small">Full Name <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control" placeholder="e.g. John Doe" required>
                            </div>

                            <!-- Username -->
                            <div class="col-md-6">
                                <label class="form-label fw-semibold text-secondary small">Username <span class="text-muted fw-normal">(Optional)</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light">@</span>
                                    <input type="text" name="username" class="form-control" placeholder="johndoe">
                                </div>
                            </div>

                            <!-- Email -->
                            <div class="col-md-6">
                                <label class="form-label fw-semibold text-secondary small">Email Address <span class="text-danger">*</span></label>
                                <input type="email" name="email" class="form-control" placeholder="john@example.com" required>
                            </div>

                            <!-- Phone -->
                            <div class="col-md-6">
                                <label class="form-label fw-semibold text-secondary small">Phone Number</label>
                                <input type="text" name="phone" class="form-control" placeholder="03001234567">
                            </div>

                            <!-- Role -->
                            <div class="col-md-3">
                                <label class="form-label fw-semibold text-secondary small">Account Role <span class="text-danger">*</span></label>
                                <select name="role" class="form-select" required>
                                    <option value="buyer" selected>Buyer (Bidder)</option>
                                    <option value="seller">Seller (Auctioneer)</option>
                                    <option value="admin">Administrator</option>
                                </select>
                            </div>

                            <!-- Gender -->
                            <div class="col-md-3">
                                <label class="form-label fw-semibold text-secondary small">Gender <span class="text-danger">*</span></label>
                                <select name="gender" class="form-select" required>
                                    <option value="male" selected>Male</option>
                                    <option value="female">Female</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>

                            <!-- Status -->
                            <div class="col-md-3">
                                <label class="form-label fw-semibold text-secondary small">Initial Status</label>
                                <select name="status" class="form-select">
                                    <option value="active" selected>Active</option>
                                    <option value="inactive">Inactive</option>
                                    <option value="suspended">Suspended</option>
                                </select>
                            </div>

                            <!-- KYC Tier -->
                            <div class="col-md-3">
                                <label class="form-label fw-semibold text-secondary small">KYC Tier</label>
                                <select name="kyc_tier" class="form-select">
                                    <option value="unverified" selected>Unverified</option>
                                    <option value="verified_bidder">Verified Bidder</option>
                                    <option value="vip_buyer">VIP Buyer</option>
                                </select>
                            </div>

                            <!-- Profile Avatar Upload -->
                            <div class="col-md-12">
                                <label class="form-label fw-semibold text-secondary small">Profile Avatar Picture <span class="text-muted fw-normal">(Optional)</span></label>
                                <input type="file" name="avatar" class="form-control" accept="image/jpeg,image/png,image/webp">
                                <div class="form-text">Upload a profile photo (JPG, PNG, WEBP, max 2MB).</div>
                            </div>

                            <!-- Starting Balance -->
                            <div class="col-md-12">
                                <label class="form-label fw-semibold text-secondary small">Opening Wallet Balance (PKR)</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light">Rs.</span>
                                    <input type="number" step="0.01" min="0" name="balance" class="form-control" value="0.00">
                                </div>
                            </div>

                            <!-- Password -->
                            <div class="col-md-6">
                                <label class="form-label fw-semibold text-secondary small">Password <span class="text-danger">*</span></label>
                                <input type="password" name="password" class="form-control" placeholder="Minimum 6 characters" minlength="6" required>
                            </div>

                            <!-- Confirm Password -->
                            <div class="col-md-6">
                                <label class="form-label fw-semibold text-secondary small">Confirm Password <span class="text-danger">*</span></label>
                                <input type="password" name="confirm_password" class="form-control" placeholder="Re-type password" minlength="6" required>
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer border-top py-3 px-4">
                        <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-emerald px-4 fw-bold">
                            <i class="fas fa-check me-1"></i> Create Account
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- =============================================================== -->
    <!-- MODAL 2: EDIT USER DETAILS                                      -->
    <!-- =============================================================== -->
    <div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 shadow rounded-4">
                <form method="POST" action="users.php" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token); ?>">
                    <input type="hidden" name="action" value="edit_user">
                    <input type="hidden" name="user_id" id="edit_user_id" value="<?= (int)($direct_edit_user['id'] ?? 0); ?>">

                    <div class="modal-header border-bottom py-3 px-4">
                        <h5 class="modal-title fw-bold text-dark d-flex align-items-center gap-2" id="editUserModalLabel">
                            <i class="fas fa-user-edit text-primary"></i> Edit User Information
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    <div class="modal-body p-4">
                        <div class="row g-3">
                            <!-- Full Name -->
                            <div class="col-md-6">
                                <label class="form-label fw-semibold text-secondary small">Full Name <span class="text-danger">*</span></label>
                                <input type="text" name="name" id="edit_name" class="form-control" value="<?= htmlspecialchars($direct_edit_user['name'] ?? ''); ?>" required>
                            </div>

                            <!-- Username -->
                            <div class="col-md-6">
                                <label class="form-label fw-semibold text-secondary small">Username</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light">@</span>
                                    <input type="text" name="username" id="edit_username" class="form-control" value="<?= htmlspecialchars($direct_edit_user['username'] ?? ''); ?>">
                                </div>
                            </div>

                            <!-- Email -->
                            <div class="col-md-6">
                                <label class="form-label fw-semibold text-secondary small">Email Address <span class="text-danger">*</span></label>
                                <input type="email" name="email" id="edit_email" class="form-control" value="<?= htmlspecialchars($direct_edit_user['email'] ?? ''); ?>" required>
                            </div>

                            <!-- Phone -->
                            <div class="col-md-6">
                                <label class="form-label fw-semibold text-secondary small">Phone Number</label>
                                <input type="text" name="phone" id="edit_phone" class="form-control" value="<?= htmlspecialchars($direct_edit_user['phone'] ?? ''); ?>">
                            </div>

                            <!-- Role -->
                            <div class="col-md-3">
                                <label class="form-label fw-semibold text-secondary small">Role</label>
                                <select name="role" id="edit_role" class="form-select">
                                    <option value="buyer">Buyer (Bidder)</option>
                                    <option value="seller">Seller (Auctioneer)</option>
                                    <option value="admin">Administrator</option>
                                </select>
                            </div>

                            <!-- Gender -->
                            <div class="col-md-3">
                                <label class="form-label fw-semibold text-secondary small">Gender</label>
                                <select name="gender" id="edit_gender" class="form-select">
                                    <option value="male">Male</option>
                                    <option value="female">Female</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>

                            <!-- Status -->
                            <div class="col-md-3">
                                <label class="form-label fw-semibold text-secondary small">Account Status</label>
                                <select name="status" id="edit_status" class="form-select">
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                    <option value="suspended">Suspended</option>
                                </select>
                            </div>

                            <!-- KYC Tier -->
                            <div class="col-md-3">
                                <label class="form-label fw-semibold text-secondary small">KYC Verification</label>
                                <select name="kyc_tier" id="edit_kyc_tier" class="form-select">
                                    <option value="unverified">Unverified</option>
                                    <option value="verified_bidder">Verified Bidder</option>
                                    <option value="vip_buyer">VIP Buyer</option>
                                </select>
                            </div>

                            <!-- Profile Avatar Preview & Upload -->
                            <div class="col-md-12">
                                <label class="form-label fw-semibold text-secondary small">Profile Avatar Picture</label>
                                <div class="d-flex align-items-center gap-3 p-2.5 bg-light rounded-3 border">
                                    <img id="edit_avatar_preview" src="" alt="Avatar Preview" class="user-avatar-img" style="width: 52px; height: 52px;" onerror="this.onerror=null; this.src='../assets/image/other.png';">
                                    <div class="flex-grow-1">
                                        <input type="file" name="avatar" id="edit_avatar_file" class="form-control form-control-sm" accept="image/jpeg,image/png,image/webp">
                                        <div class="form-text small mb-0">Select an image (JPG, PNG, WEBP, max 2MB) to replace this user's profile avatar.</div>
                                    </div>
                                </div>
                            </div>

                            <!-- CNIC / Tax ID -->
                            <div class="col-md-6">
                                <label class="form-label fw-semibold text-secondary small">National ID / Tax ID (CNIC / NTN)</label>
                                <input type="text" name="cnic_tax_id" id="edit_cnic_tax_id" class="form-control" placeholder="e.g. 42101-*******-1" value="<?= htmlspecialchars($direct_edit_user['cnic_tax_id'] ?? ''); ?>">
                            </div>

                            <!-- Wallet Balance -->
                            <div class="col-md-6">
                                <label class="form-label fw-semibold text-secondary small">Wallet Balance (PKR)</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light">Rs.</span>
                                    <input type="number" step="0.01" min="0" name="balance" id="edit_balance" class="form-control" value="<?= htmlspecialchars((string)($direct_edit_user['balance'] ?? 0)); ?>">
                                </div>
                            </div>

                            <!-- Primary Shipping Address -->
                            <div class="col-md-12">
                                <label class="form-label fw-semibold text-secondary small">Primary Shipping & Delivery Address</label>
                                <textarea name="shipping_address" id="edit_shipping_address" class="form-control" rows="2" placeholder="Street Address, City, Postal Code..."><?= htmlspecialchars($direct_edit_user['shipping_address'] ?? ''); ?></textarea>
                            </div>

                            <!-- Bio -->
                            <div class="col-md-12">
                                <label class="form-label fw-semibold text-secondary small">About / User Bio</label>
                                <textarea name="bio" id="edit_bio" class="form-control" rows="2" placeholder="User bio or seller notes..."><?= htmlspecialchars($direct_edit_user['bio'] ?? ''); ?></textarea>
                            </div>

                            <!-- Reset Password -->
                            <div class="col-md-12">
                                <label class="form-label fw-semibold text-secondary small">
                                    Reset Password <span class="text-muted fw-normal">(Optional)</span>
                                </label>
                                <input type="password" name="new_password" id="edit_new_password" class="form-control" placeholder="Leave blank to keep unchanged" minlength="6">
                                <div class="form-text">Enter 6+ characters only if resetting password.</div>
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer border-top py-3 px-4">
                        <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary px-4 fw-bold">
                            <i class="fas fa-save me-1"></i> Save User Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Modal Data Binding Script -->
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const editModal = document.getElementById('editUserModal');
        if (editModal) {
            editModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                if (!button) return; // If triggered programmatically or via direct edit

                document.getElementById('edit_user_id').value   = button.getAttribute('data-id') || '';
                document.getElementById('edit_name').value      = button.getAttribute('data-name') || '';
                document.getElementById('edit_username').value  = button.getAttribute('data-username') || '';
                document.getElementById('edit_email').value     = button.getAttribute('data-email') || '';
                document.getElementById('edit_phone').value     = button.getAttribute('data-phone') || '';
                document.getElementById('edit_role').value      = button.getAttribute('data-role') || 'buyer';
                document.getElementById('edit_gender').value    = button.getAttribute('data-gender') || 'male';
                document.getElementById('edit_status').value    = button.getAttribute('data-status') || 'active';
                document.getElementById('edit_kyc_tier').value  = button.getAttribute('data-kyc') || 'unverified';
                document.getElementById('edit_cnic_tax_id').value = button.getAttribute('data-cnic') || '';
                document.getElementById('edit_shipping_address').value = button.getAttribute('data-shipping') || '';
                document.getElementById('edit_bio').value       = button.getAttribute('data-bio') || '';
                document.getElementById('edit_balance').value   = button.getAttribute('data-balance') || '0';
                document.getElementById('edit_new_password').value = '';

                const avSrc = button.getAttribute('data-avatar') || '';
                document.getElementById('edit_avatar_preview').src = avSrc || '../assets/image/other.png';
                
                const avFileInput = document.getElementById('edit_avatar_file');
                if (avFileInput) avFileInput.value = '';
            });

            // Live image preview when file is selected in edit modal
            const editAvFile = document.getElementById('edit_avatar_file');
            if (editAvFile) {
                editAvFile.addEventListener('change', function () {
                    if (this.files && this.files[0]) {
                        const reader = new FileReader();
                        reader.onload = function (e) {
                            document.getElementById('edit_avatar_preview').src = e.target.result;
                        };
                        reader.readAsDataURL(this.files[0]);
                    }
                });
            }

            <?php if ($direct_edit_user): ?>
                // Show modal automatically if direct ?edit=ID is passed in URL
                const bsModal = new bootstrap.Modal(editModal);
                document.getElementById('edit_role').value     = '<?= strtolower($direct_edit_user['role'] ?? 'buyer'); ?>';
                document.getElementById('edit_gender').value   = '<?= strtolower($direct_edit_user['gender'] ?? 'male'); ?>';
                document.getElementById('edit_status').value   = '<?= strtolower($direct_edit_user['status'] ?? 'active'); ?>';
                document.getElementById('edit_kyc_tier').value = '<?= strtolower($direct_edit_user['kyc_tier'] ?? 'unverified'); ?>';
                document.getElementById('edit_cnic_tax_id').value = '<?= htmlspecialchars(addslashes($direct_edit_user['cnic_tax_id'] ?? '')); ?>';
                document.getElementById('edit_shipping_address').value = '<?= htmlspecialchars(addslashes($direct_edit_user['shipping_address'] ?? '')); ?>';
                document.getElementById('edit_bio').value = '<?= htmlspecialchars(addslashes($direct_edit_user['bio'] ?? '')); ?>';
                document.getElementById('edit_avatar_preview').src = '<?= htmlspecialchars(get_user_avatar_img_src($direct_edit_user['avatar'] ?? '', $direct_edit_user['name'])); ?>';
                bsModal.show();
            <?php endif; ?>
        }
    });

    // Prevent BFCache from displaying protected admin page on back-button after logout
    window.addEventListener('pageshow', function (event) {
        if (event.persisted || (window.performance && window.performance.navigation && window.performance.navigation.type === 2)) {
            window.location.reload();
        }
    });

    // Intercept all logout clicks to display the loading spinner immediately
    document.addEventListener('click', function(e) {
        const logoutLink = e.target.closest('a[href*="logout"]');
        if (logoutLink && !logoutLink.hasAttribute('data-no-loader')) {
            const targetHref = logoutLink.getAttribute('href');
            if (targetHref && targetHref !== '#' && !targetHref.startsWith('javascript:')) {
                e.preventDefault();
                if (typeof showLoader === 'function') {
                    showLoader('Logging out, please wait...');
                }
                setTimeout(function() {
                    window.location.href = targetHref;
                }, 200);
            }
        }
    });
    </script>

</body>
</html>