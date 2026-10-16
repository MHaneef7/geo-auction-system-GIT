<?php
/**
 * User Live Auction Forwarder - Geo Auction System
 * File: user/live_auction.php
 *
 * Automatically delegates to the primary OOP PDO Live Arena controller at ../live_auction.php.
 */
declare(strict_types=1);

$queryString = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
header('Location: ../live_auction.php' . $queryString, true, 302);
exit();