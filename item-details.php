<?php
/**
 * Item Details Router / View - Geo Auction System
 * Links from Auction Cards: item-details.php?id=[item_id]
 */
$id = (int)($_GET['id'] ?? 0);

if ($id > 0) {
    header("Location: live_auction.php?id=" . $id);
} else {
    header("Location: items.php");
}
exit();
