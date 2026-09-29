<?php
/**
 * Geo Auction System - Root Dashboard Router
 * Directs authenticated users to their corresponding dashboard based on their role.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$role = strtolower(trim($_SESSION['role'] ?? $_SESSION['user_role'] ?? 'buyer'));

if ($role === 'admin') {
    header("Location: admin/dashboard.php");
} else {
    header("Location: user/dashboard.php");
}
exit();
