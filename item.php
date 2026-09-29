<?php
/**
 * Geo Auction System - Canonical Item Router
 * Resolves item.php?id=... links to the active live auction view
 */
$id = (int)($_GET['id'] ?? 0);

if ($id > 0) {
    header("Location: live_auction.php?id=" . $id);
} else {
    header("Location: live_auction.php");
}
exit();
