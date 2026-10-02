<?php
/**
 * ============================================================================
 * File: user/notifications.php
 * Path: /user/notifications.php
 * Description: Subdirectory Router to Canonical Root Notifications Center
 * Architecture: Senior PHP Full-Stack Standards
 * ============================================================================
 * 
 * Yeh file user/ context (jaise user/dashboard.php) se aane wale notification links
 * ko canonical /notifications.php par smoothly 302 redirect karti hai taake
 * tamam users ko modern, filterable notification center dastiyab ho.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$queryString = $_SERVER['QUERY_STRING'] ?? '';
$targetUrl   = '../notifications.php' . ($queryString !== '' ? '?' . $queryString : '');

header('Location: ' . $targetUrl);
exit();
