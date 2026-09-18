<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include_once __DIR__ . '/database.php';

function is_logged_in() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function is_admin() {
    return is_logged_in() && isset($_SESSION['user_role']) && strtolower($_SESSION['user_role']) === 'admin';
}

function require_login($redirect_path = 'index.php') {
    if (!is_logged_in()) {
        header("Location: " . $redirect_path);
        exit();
    }
}

function require_admin($redirect_path = '../index.php') {
    if (!is_admin()) {
        header("Location: " . $redirect_path);
        exit();
    }
}

function db_escape($conn, $str) {
    return mysqli_real_escape_string($conn, trim($str ?? ''));
}
?>
