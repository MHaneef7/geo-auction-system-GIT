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
    try {
        $stmt_u = $pdo->prepare("SELECT id FROM users WHERE id = :id LIMIT 1");
        $stmt_u->execute([':id' => $id]);
        $has_user = (bool)$stmt_u->fetch();
        if ($has_user && !isset($_GET['item_id'])) {
            $stmt_i = $pdo->prepare("SELECT id FROM items WHERE id = :id LIMIT 1");
            $stmt_i->execute([':id' => $id]);
            $has_item = (bool)$stmt_i->fetch();

            if ($has_item) {
                header("Location: items.php?search=" . $id);
            } else {
                header("Location: users.php?edit=" . $id);
            }
            exit();
        }
    } catch (\Throwable $e) {}
    header("Location: items.php?search=" . $id);
    exit();
}
header("Location: users.php");
exit();
