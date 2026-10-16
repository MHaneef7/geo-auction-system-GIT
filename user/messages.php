<?php
/**
 * User Messages Bridge & Forwarder - Geo Auction System
 * File: user/messages.php
 *
 * Automatically delegates and forwards to the primary Messages & Conversation
 * Portal at ../messages.php, preserving any query parameters (?user_id=38&item_id=X).
 */

declare(strict_types=1);

$queryString = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
header('Location: ../messages.php' . $queryString, true, 302);
exit();
