<?php
/**
 * Admin Unified Edit Dispatcher
 * File: admin/edit.php
 * 
 * Routes edit requests to the appropriate management module:
 * - User editing: admin/users.php?edit={id} (opens "Edit User Information" modal)
 * - Item editing: user/edit_item.php?id={id} (or items.php?search={id})
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include_once __DIR__ . '/../includes/database.php';
include_once __DIR__ . '/../includes/auth.php';

require_admin('../index.php');

// 1. Explicit User Edit Parameter
if (isset($_GET['user_id'])) {
    $uid = (int)$_GET['user_id'];
    header("Location: users.php?edit=" . $uid);
    exit();
}

// 2. Type-based routing
$type = strtolower(trim($_GET['type'] ?? ''));
$id   = (int)($_GET['id'] ?? ($_GET['item_id'] ?? 0));

if ($type === 'user' || $type === 'users') {
    header("Location: users.php?edit=" . $id);
    exit();
}

if ($type === 'item' || $type === 'items') {
    if ($id > 0) {
        header("Location: ../user/edit_item.php?id=" . $id);
    } else {
        header("Location: items.php");
    }
    exit();
}

// 3. Fallback: If id is given, check whether it matches a user or item
if ($id > 0) {
    $chk_u = mysqli_query($conn, "SELECT id FROM users WHERE id = " . $id . " LIMIT 1");
    if ($chk_u && mysqli_num_rows($chk_u) > 0 && !isset($_GET['item_id'])) {
        $chk_i = mysqli_query($conn, "SELECT id FROM items WHERE id = " . $id . " LIMIT 1");
        if ($chk_i && mysqli_num_rows($chk_i) > 0) {
            header("Location: items.php?search=" . $id);
        } else {
            header("Location: users.php?edit=" . $id);
        }
        exit();
    }
    header("Location: items.php?search=" . $id);
    exit();
}

// Default fallback
header("Location: users.php");
exit();
