<?php
/**
 * Root Profile Router
 * File: profile.php
 * 
 * Routes requests to user/profile.php preserving query parameters.
 */

$queryString = $_SERVER['QUERY_STRING'] ?? '';
$target = 'user/profile.php' . (!empty($queryString) ? '?' . $queryString : '');

header("Location: " . $target, true, 302);
exit();
