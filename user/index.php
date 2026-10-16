<?php
/**
 * User Index Gateway & Redirection Router - Geo Auction System
 * File: user/index.php
 *
 * Dynamically routes requests entering /user/ or /user/index.php:
 * 1. If contact_id or user_id query parameters are present, forwards to ../messages.php with proper params.
 * 2. If no parameters are provided, routes directly to the User Dashboard (dashboard.php).
 */

declare(strict_types=1);

$contactId = $_GET['contact_id'] ?? $_GET['user_id'] ?? null;
$itemId    = $_GET['item_id'] ?? null;

if (!empty($contactId)) {
    $targetUrl = '../messages.php?user_id=' . urlencode((string)$contactId);
    if (!empty($itemId)) {
        $targetUrl .= '&item_id=' . urlencode((string)$itemId);
    }
    header('Location: ' . $targetUrl, true, 302);
    exit();
}

// Default navigation without chat parameters -> Dashboard
header('Location: dashboard.php', true, 302);
exit();
