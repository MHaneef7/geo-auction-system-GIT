<?php
/**
 * admin/login.php - Deprecated / Removed
 * 
 * Redirect completely removed in favor of home page (index.php).
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header("Location: ../index.php");
exit();
