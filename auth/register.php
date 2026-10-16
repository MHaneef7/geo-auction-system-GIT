<?php
/**
 * Auth Register Canonical Router
 * File: auth/register.php
 * Geo Auction System
 *
 * Redirects securely to the canonical, CSRF-protected registration portal.
 */

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header("Location: ../register.php", true, 301);
exit();