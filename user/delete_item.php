<?php
/**
 * Soft-Delete Handler for Auction Items
 * Geo Auction System
 *
 * Implements audit-trailed soft deletion: sets is_deleted = 1, deleted_at = NOW(), deleted_by = user_id
 * Items disappear from public marketplace immediately while remaining audited in Admin.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include_once __DIR__ . '/../includes/database.php';
include_once __DIR__ . '/../includes/auth.php';

require_login('../login.php');

$user_id = (int)($_SESSION['user_id'] ?? 0);
$id      = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$role    = strtolower($_SESSION['role'] ?? $_SESSION['user_role'] ?? 'buyer');

if ($id > 0) {
    if ($role === 'admin') {
        $stmt = mysqli_prepare($conn, "UPDATE items SET is_deleted = 1, deleted_at = NOW(), deleted_by = ? WHERE id = ?");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "ii", $user_id, $id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
    } else {
        $stmt = mysqli_prepare($conn, "UPDATE items SET is_deleted = 1, deleted_at = NOW(), deleted_by = ? WHERE id = ? AND seller_id = ?");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "iii", $user_id, $id, $user_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
    }
}

$redirect = $_GET['redirect'] ?? 'my_auctions.php';
// Prevent open redirect
if (strpos($redirect, 'http://') !== false || strpos($redirect, 'https://') !== false) {
    $redirect = 'my_auctions.php';
}

header('Location: ' . $redirect);
exit();
