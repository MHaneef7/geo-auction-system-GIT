<?php
/**
 * ============================================================================
 * File: user/messages.php
 * Path: /user/messages.php
 * Description: Subdirectory Router to Canonical Root Messages Portal
 * Architecture: Senior PHP Full-Stack Standards
 * ============================================================================
 * 
 * Agar koi user ya notification user/ folder ke context se messages ko access kare
 * (jaise user/messages.php?user_id=38), to yeh controller tamam query parameters
 * (user_id, item_id wagera) ko mehfooz rakhte hue user ko smoothly
 * canonical ../messages.php par redirect karta hai.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$queryString = $_SERVER['QUERY_STRING'] ?? '';
$targetUrl   = '../messages.php' . ($queryString !== '' ? '?' . $queryString : '');

// Safe 302 Found redirect to canonical messages portal
header('Location: ' . $targetUrl);
exit();
