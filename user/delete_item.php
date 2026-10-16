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
    try {
        $pdo = Database::getPdo();
        
        // Ensure soft delete columns exist
        $cols = $pdo->query("SHOW COLUMNS FROM items")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('is_deleted', $cols, true)) {
            $pdo->exec("ALTER TABLE items ADD COLUMN `is_deleted` TINYINT(1) NOT NULL DEFAULT 0");
        }
        if (!in_array('deleted_at', $cols, true)) {
            $pdo->exec("ALTER TABLE items ADD COLUMN `deleted_at` DATETIME NULL DEFAULT NULL");
        }

        $hasDeletedBy = in_array('deleted_by', $cols, true);

        if ($role === 'admin') {
            if ($hasDeletedBy) {
                $stmt = $pdo->prepare("UPDATE items SET is_deleted = 1, deleted_at = NOW(), deleted_by = :uid WHERE id = :id");
                $stmt->execute([':uid' => $user_id, ':id' => $id]);
            } else {
                $stmt = $pdo->prepare("UPDATE items SET is_deleted = 1, deleted_at = NOW() WHERE id = :id");
                $stmt->execute([':id' => $id]);
            }
        } else {
            if ($hasDeletedBy) {
                $stmt = $pdo->prepare("UPDATE items SET is_deleted = 1, deleted_at = NOW(), deleted_by = :uid WHERE id = :id AND seller_id = :seller_id");
                $stmt->execute([':uid' => $user_id, ':id' => $id, ':seller_id' => $user_id]);
            } else {
                $stmt = $pdo->prepare("UPDATE items SET is_deleted = 1, deleted_at = NOW() WHERE id = :id AND seller_id = :seller_id");
                $stmt->execute([':id' => $id, ':seller_id' => $user_id]);
            }
        }
    } catch (Throwable $e) {
        error_log("Delete item error: " . $e->getMessage());
    }
}

$redirect = $_GET['redirect'] ?? 'my_auctions.php';
// Prevent open redirect
if (strpos($redirect, 'http://') !== false || strpos($redirect, 'https://') !== false) {
    $redirect = 'my_auctions.php';
}

header('Location: ' . $redirect);
exit();
