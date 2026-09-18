<?php
$id = (int)($_GET['id'] ?? 0);
if ($id > 0) {
    header("Location: item.php?id=" . $id);
} else {
    header("Location: live_auction.php");
}
exit();
?>
