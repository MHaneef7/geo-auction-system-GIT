<?php
/**
 * ============================================================================
 * File: user/pay.php
 * Path: /user/pay.php
 * Description: Redirect Gateway to Canonical checkout.php Engine
 * ============================================================================
 * 
 * Yeh file users ko canonical checkout & order processing engine 
 * (checkout.php) par smoothly route karti hai.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$itemId = (int)($_GET['item_id'] ?? 0);

if ($itemId > 0) {
    header("Location: ../checkout.php?item_id=" . $itemId);
} else {
    header("Location: won_auctions.php");
}
exit();
