<?php
header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database Connection
require_once __DIR__ . '/../includes/database.php';
$has_db = ($pdo instanceof PDO);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'status'  => 'error', 
        'title'   => 'Method Not Allowed',
        'message' => 'Invalid request method. POST is required.'
    ]);
    exit();
}

if (!$has_db) {
    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'title'   => 'Database Error',
        'message' => 'Database connection failed. Please ensure the MySQL service is running in XAMPP.'
    ]);
    exit();
}

// Rate Limiting (Protection against brute-force attacks)
$max_attempts    = 5;
$lockout_seconds = 300; // 5 minutes
$failed_attempts = $_SESSION['admin_login_attempts'] ?? 0;
$lockout_time    = $_SESSION['admin_lockout_until'] ?? 0;

if ($lockout_time > time()) {
    $remaining_seconds = $lockout_time - time();
    $remaining_minutes = ceil($remaining_seconds / 60);
    http_response_code(429);
    echo json_encode([
        'status'  => 'error', 
        'title'   => 'Security Lockout',
        'message' => "Too many failed login attempts. Access temporarily locked. Please try again in {$remaining_minutes} minute(s)."
    ]);
    exit();
}

// Parse input (supports standard FormData POST, x-www-form-urlencoded, and JSON payloads)
$raw_input = file_get_contents('php://input');
$json_data = json_decode($raw_input, true);

$username = trim($_POST['username'] ?? $_POST['email'] ?? ($json_data['username'] ?? ($json_data['email'] ?? '')));
$password = trim($_POST['password'] ?? ($json_data['password'] ?? ''));

if (empty($username) || empty($password)) {
    http_response_code(400);
    echo json_encode([
        'status'  => 'error',
        'title'   => 'Missing Credentials',
        'message' => 'Please enter both your administrator username/email and password.'
    ]);
    exit();
}

$admin_user = null;
$clean_username = ltrim($username, '@');

// 1. Check dedicated 'admins' table if it exists
try {
    $check_tbl = $pdo->query("SHOW TABLES LIKE 'admins'");
    if ($check_tbl && $check_tbl->fetch()) {
        $stmt = $pdo->prepare("SELECT * FROM admins WHERE (email = :em OR username = :un) LIMIT 1");
        $stmt->execute([':em' => $username, ':un' => $clean_username]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $admin_user = $row;
            $admin_user['role'] = 'admin'; // Enforce admin role
        }
    }
} catch (\Throwable $e) {}

// 2. Query 'users' table where user can be identified by email, username, or name
if (!$admin_user) {
    try {
        $stmt = $pdo->prepare("SELECT `id`, `name`, `username`, `email`, `phone`, `password`, `role`, `status`, `avatar` FROM users WHERE (email = :em OR username = :un OR name = :nm) LIMIT 1");
        $stmt->execute([':em' => $username, ':un' => $clean_username, ':nm' => $username]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $admin_user = $row;
        }
    } catch (\Throwable $e) {}
}


if (!$admin_user) {
    $_SESSION['admin_login_attempts'] = (++$failed_attempts);
    if ($failed_attempts >= $max_attempts) {
        $_SESSION['admin_lockout_until'] = time() + $lockout_seconds;
        http_response_code(429);
        echo json_encode([
            'status'  => 'error',
            'title'   => 'Security Lockout',
            'message' => 'Too many invalid attempts. Account access locked for 5 minutes.'
        ]);
    } else {
        http_response_code(401);
        echo json_encode([
            'status'  => 'error',
            'title'   => 'Authentication Failed',
            'message' => 'Invalid username or password. Please try again.'
        ]);
    }
    exit();
}

$hashed_password = $admin_user['password'] ?? '';
$status = strtolower($admin_user['status'] ?? 'active');
$role   = strtolower(trim($admin_user['role'] ?? ''));

// Verify Password using secure password_verify()
if (!password_verify($password, $hashed_password)) {
    $_SESSION['admin_login_attempts'] = (++$failed_attempts);
    if ($failed_attempts >= $max_attempts) {
        $_SESSION['admin_lockout_until'] = time() + $lockout_seconds;
        http_response_code(429);
        echo json_encode([
            'status'  => 'error',
            'title'   => 'Security Lockout',
            'message' => 'Too many invalid attempts. Account access locked for 5 minutes.'
        ]);
    } else {
        http_response_code(401);
        echo json_encode([
            'status'  => 'error',
            'title'   => 'Authentication Failed',
            'message' => 'Invalid username or password. Please try again.'
        ]);
    }
    exit();
}

// Enforce Administrator Role
if ($role !== 'admin') {
    $_SESSION['admin_login_attempts'] = (++$failed_attempts);
    http_response_code(403);
    echo json_encode([
        'status'  => 'error',
        'title'   => 'Access Denied',
        'message' => 'Access restricted: This account does not possess administrator privileges.'
    ]);
    exit();
}

// Check Account Active Status
if ($status !== 'active' && $status !== '1') {
    http_response_code(403);
    echo json_encode([
        'status'  => 'error',
        'title'   => 'Account Suspended',
        'message' => 'This administrator account is currently inactive or suspended.'
    ]);
    exit();
}

// Reset failure attempts on success
unset($_SESSION['admin_login_attempts']);
unset($_SESSION['admin_lockout_until']);

// Prevent Session Fixation attack
session_regenerate_id(true);

// Establish Authoritative Admin Sessions
$_SESSION['admin_logged_in'] = true;
$_SESSION['admin_id']        = (int)$admin_user['id'];
$_SESSION['admin_name']      = $admin_user['name'] ?? 'Administrator';
$_SESSION['admin_email']     = $admin_user['email'] ?? '';
$_SESSION['admin_last_login']= time();

// Synchronize standard user sessions for site-wide navigation compatibility
$_SESSION['user_id']         = (int)$admin_user['id'];
$_SESSION['id']              = (int)$admin_user['id'];
$_SESSION['role']            = 'admin';
$_SESSION['user_role']       = 'admin';
$_SESSION['name']            = $admin_user['name'] ?? 'Administrator';
$_SESSION['user_name']       = $admin_user['name'] ?? 'Administrator';
$_SESSION['user_email']      = $admin_user['email'] ?? '';
$_SESSION['user_avatar']     = $admin_user['avatar'] ?? '';

// Determine redirect destination
$custom_redirect = trim($_POST['redirect'] ?? ($json_data['redirect'] ?? ''));
$redirect_url = !empty($custom_redirect) ? $custom_redirect : 'admin/dashboard.php';

// Return clean JSON response
echo json_encode([
    'status'       => 'success',
    'title'        => 'Authentication Successful',
    'message'      => 'Welcome back, ' . htmlspecialchars($_SESSION['admin_name']) . '! Redirecting to dashboard...',
    'redirect_url' => $redirect_url
]);
exit();
