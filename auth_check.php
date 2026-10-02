<?php
/**
 * ============================================================================
 * File: auth_check.php
 * Path: /auth_check.php (Project Root)
 * Description: Centralized Authentication & Role Guard System
 * Architecture: Senior PHP Full-Stack Standards
 * ============================================================================
 * 
 * Yeh file kisi bhi protected page ya API endpoint par user session, 
 * role authorization, aur account status (active/suspended) ko verify karti hai.
 * Agar user logged-in na ho to AJAX requests par JSON 401 response deti hai
 * aur regular browser page requests par login page redirect karti hai.
 */

require_once __DIR__ . '/db.php';

/**
 * Check if a user is currently logged in
 * 
 * @return bool True agar valid user session mojood ho
 */
if (!function_exists('is_logged_in')) {
    function is_logged_in(): bool {
        $uid = (int)($_SESSION['user_id'] ?? $_SESSION['id'] ?? 0);
        return $uid > 0;
    }
}

/**
 * Get current logged in user ID
 * 
 * @return int User ID ya 0
 */
function current_user_id(): int {
    return (int)($_SESSION['user_id'] ?? $_SESSION['id'] ?? 0);
}

/**
 * Get current logged in user role
 * 
 * @return string 'buyer', 'seller', 'admin', ya 'guest'
 */
function current_role(): string {
    if (!is_logged_in()) {
        return 'guest';
    }
    return strtolower(trim($_SESSION['role'] ?? $_SESSION['user_role'] ?? 'buyer'));
}

/**
 * Get current authenticated user details from database
 * 
 * @return array|null User record ya null
 */
function current_user(): ?array {
    static $cachedUser = null;

    if ($cachedUser !== null) {
        return $cachedUser;
    }

    $uid = current_user_id();
    if ($uid <= 0) {
        return null;
    }

    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("SELECT id, name, email, phone, role, avatar, balance, status FROM users WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $uid]);
        $cachedUser = $stmt->fetch() ?: null;
        return $cachedUser;
    } catch (PDOException $e) {
        error_log("auth_check.php error: " . $e->getMessage());
        return null;
    }
}

/**
 * Enforce Authentication & Role Check
 * Protected routes aur AJAX APIs par call karein.
 * 
 * @param string|array|null $allowedRoles Specific role(s) required ('seller', 'admin', ['seller', 'admin'])
 * @return array Authenticated user array
 */
function require_auth($allowedRoles = null): array {
    $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
              || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
              || (isset($_GET['ajax']) && $_GET['ajax'] == '1')
              || (isset($_POST['ajax']) && $_POST['ajax'] == '1');

    // 1. Check if user is logged in
    if (!is_logged_in()) {
        if ($isAjax) {
            json_response([
                'success'  => false,
                'error'    => 'Authentication required. Please sign in to proceed.',
                'redirect' => 'login.php'
            ], 401);
        } else {
            $redirectTarget = urlencode($_SERVER['REQUEST_URI'] ?? 'index.php');
            header("Location: login.php?redirect=" . $redirectTarget);
            exit();
        }
    }

    // 2. Fetch fresh user data from DB to verify account status
    $user = current_user();
    if (!$user) {
        // Session invalid / User deleted from DB
        session_unset();
        session_destroy();
        if ($isAjax) {
            json_response([
                'success'  => false,
                'error'    => 'Session expired or user not found. Please log in again.',
                'redirect' => 'login.php'
            ], 401);
        } else {
            header("Location: login.php?error=invalid_session");
            exit();
        }
    }

    // 3. Verify Account Status
    $status = strtolower(trim((string)$user['status']));
    if ($status === 'suspended' || $status === 'banned' || $status === '0') {
        session_unset();
        session_destroy();
        if ($isAjax) {
            json_response([
                'success' => false,
                'error'   => 'Your account is suspended. Please contact customer support.',
                'redirect' => 'login.php?error=suspended'
            ], 403);
        } else {
            header("Location: login.php?error=suspended");
            exit();
        }
    }

    // 4. Role Authorization Check (if specific roles requested)
    if ($allowedRoles !== null) {
        if (!is_array($allowedRoles)) {
            $allowedRoles = [$allowedRoles];
        }
        $allowedRoles = array_map('strtolower', $allowedRoles);
        $userRole     = strtolower($user['role'] ?? 'buyer');

        // Admin has super-privilege access
        if ($userRole !== 'admin' && !in_array($userRole, $allowedRoles)) {
            if ($isAjax) {
                json_response([
                    'success' => false,
                    'error'   => 'Access denied. You do not have permission for this action.'
                ], 403);
            } else {
                set_flash('error', 'Access denied. You do not have permission for this section.');
                header("Location: index.php");
                exit();
            }
        }
    }

    return $user;
}
