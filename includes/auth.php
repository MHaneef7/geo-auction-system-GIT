<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include_once __DIR__ . '/database.php';

if (!function_exists('is_logged_in')) {
    function is_logged_in() {
        return (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) ||
               (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true);
    }
}

function is_admin() {
    if (!is_logged_in()) return false;
    if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
        return true;
    }
    $role = strtolower(trim($_SESSION['role'] ?? $_SESSION['user_role'] ?? ''));
    return $role === 'admin';
}

/**
 * Prevent browser back button caching on protected pages
 */
if (!function_exists('prevent_page_caching')) {
    function prevent_page_caching() {
        if (!headers_sent()) {
            header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
            header("Cache-Control: post-check=0, pre-check=0", false);
            header("Pragma: no-cache");
            header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");
        }
    }
}

function require_login($redirect_path = 'index.php') {
    prevent_page_caching();
    if (!is_logged_in()) {
        header("Location: " . $redirect_path);
        exit();
    }
}

function require_admin($redirect_path = '../index.php') {
    prevent_page_caching();
    if (!is_admin()) {
        $_SESSION['admin_login_error'] = 'Access restricted: Administrator privileges required.';
        header("Location: " . $redirect_path);
        exit();
    }
}

function db_escape($conn, $str) {
    return mysqli_real_escape_string($conn, trim($str ?? ''));
}
?>
