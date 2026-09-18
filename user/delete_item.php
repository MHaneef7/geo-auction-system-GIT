<?php
include_once '../includes/auth.php';
require_login('../index.php');

$user_id = (int)$_SESSION['user_id'];
$id = (int)($_GET['id'] ?? 0);

if ($id > 0) {
    mysqli_query($conn, "DELETE FROM items WHERE id = $id AND seller_id = $user_id");
}

header('Location: my_auctions.php');
exit;
?>
