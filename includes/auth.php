<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include_once __DIR__ . '/database.php';

// Synchronize user ID and role session variables across the entire platform
if (!empty($_SESSION['id']) && empty($_SESSION['user_id'])) {
    $_SESSION['user_id'] = (int)$_SESSION['id'];
}
if (!empty($_SESSION['user_id']) && empty($_SESSION['id'])) {
    $_SESSION['id'] = (int)$_SESSION['user_id'];
}
if (!empty($_SESSION['user_role']) && empty($_SESSION['role'])) {
    $_SESSION['role'] = strtolower(trim($_SESSION['user_role']));
}
if (!empty($_SESSION['role']) && empty($_SESSION['user_role'])) {
    $_SESSION['user_role'] = strtolower(trim($_SESSION['role']));
}

function is_logged_in() {
    return (!empty($_SESSION['user_id'])) ||
           (!empty($_SESSION['id'])) ||
           (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true);
}

function is_admin() {
    if (!is_logged_in()) return false;
    if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
        return true;
    }
    $role = strtolower(trim($_SESSION['role'] ?? $_SESSION['user_role'] ?? ''));
    return $role === 'admin';
}

function is_seller() {
    if (!is_logged_in()) return false;
    $role = strtolower(trim($_SESSION['role'] ?? $_SESSION['user_role'] ?? ''));
    return in_array($role, ['seller', 'admin'], true);
}

/**
 * Prevent browser back button caching on protected pages
 */
if (!function_exists('prevent_page_caching')) {
    function prevent_page_caching() {
        if (!headers_sent()) {
            header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
            header("Cache-Control: post-check=0, pre-check=0", false);
            header("Pragma: no-cache");
            header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");
        }
    }
}

function require_login($redirect_path = 'index.php') {
    prevent_page_caching();
    if (!is_logged_in()) {
        header("Location: " . $redirect_path);
        exit();
    }
}

function require_admin($redirect_path = '../index.php') {
    prevent_page_caching();
    if (!is_admin()) {
        $_SESSION['admin_login_error'] = 'Access restricted: Administrator privileges required.';
        header("Location: " . $redirect_path);
        exit();
    }
}

function db_escape($connOrStr, $str = null) {
    if ($str === null) {
        return htmlspecialchars(trim((string)$connOrStr), ENT_QUOTES, 'UTF-8');
    }
    if ($connOrStr instanceof PDO) {
        return trim($connOrStr->quote((string)$str), "'");
    }
    if ($connOrStr instanceof mysqli) {
        return mysqli_real_escape_string($connOrStr, trim((string)$str));
    }
    return htmlspecialchars(trim((string)$str), ENT_QUOTES, 'UTF-8');
}

/**
 * Generate or retrieve the existing CSRF token
 */
if (!function_exists('csrf_token')) {
    function csrf_token(): string {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('generate_csrf_token')) {
    function generate_csrf_token(): string {
        return csrf_token();
    }
}

/**
 * Output a secure hidden CSRF input field
 */
if (!function_exists('csrf_input')) {
    function csrf_input(): string {
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
    }
}

/**
 * Validate CSRF token from POST, JSON payload, or HTTP headers (X-CSRF-Token)
 */
if (!function_exists('validate_csrf_token')) {
    function validate_csrf_token(?string $token = null): bool {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $sessionToken = $_SESSION['csrf_token'] ?? '';
        if (empty($sessionToken) || !is_string($sessionToken)) {
            return false;
        }

        // If no token was provided, attempt automatic extraction
        if ($token === null) {
            if (!empty($_POST['csrf_token'])) {
                $token = (string)$_POST['csrf_token'];
            } elseif (!empty($_SERVER['HTTP_X_CSRF_TOKEN'])) {
                $token = (string)$_SERVER['HTTP_X_CSRF_TOKEN'];
            } else {
                $raw = @file_get_contents('php://input');
                if (!empty($raw)) {
                    $json = @json_decode($raw, true);
                    if (is_array($json) && !empty($json['csrf_token'])) {
                        $token = (string)$json['csrf_token'];
                    }
                }
            }
        }

        if (empty($token) || !is_string($token)) {
            return false;
        }

        return hash_equals($sessionToken, $token);
    }
}

/**
 * Enforce CSRF check or immediately abort request with 403 Forbidden
 */
if (!function_exists('verify_csrf_or_abort')) {
    function verify_csrf_or_abort(bool $isJson = true): void {
        if (!validate_csrf_token()) {
            http_response_code(403);
            if ($isJson) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'success' => false,
                    'message' => 'Invalid or expired CSRF security token. Please refresh the page.'
                ]);
            } else {
                echo '<!DOCTYPE html><html><body><h3>403 Forbidden</h3><p>Invalid or expired CSRF security token. Please return to the previous page and reload.</p></body></html>';
            }
            exit();
        }
    }
}

/**
 * Regenerate session safely preserving user data while invalidating old session ID
 */
if (!function_exists('regenerate_session_safely')) {
    function regenerate_session_safely(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        session_regenerate_id(true);
        // Refresh CSRF token on privilege escalation / re-login
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
}

/**
 * Lightweight rate-limiting helper based on Session and IP address
 */
if (!function_exists('check_rate_limit')) {
    function check_rate_limit(string $action, int $maxAttempts = 5, int $decaySeconds = 300): array {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $key = 'rl_' . md5($action . '_' . $ip);
        $data = $_SESSION[$key] ?? ['attempts' => 0, 'first_attempt' => time()];

        $now = time();
        if (($now - $data['first_attempt']) > $decaySeconds) {
            $data = ['attempts' => 0, 'first_attempt' => $now];
        }

        $remaining = max(0, $maxAttempts - $data['attempts']);
        $retryAfter = max(0, ($data['first_attempt'] + $decaySeconds) - $now);

        return [
            'allowed'     => $data['attempts'] < $maxAttempts,
            'attempts'    => $data['attempts'],
            'remaining'   => $remaining,
            'retry_after' => $retryAfter,
            'key'         => $key
        ];
    }
}

if (!function_exists('record_rate_limit_attempt')) {
    function record_rate_limit_attempt(string $action, int $decaySeconds = 300): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $key = 'rl_' . md5($action . '_' . $ip);
        $now = time();
        $data = $_SESSION[$key] ?? ['attempts' => 0, 'first_attempt' => $now];

        if (($now - $data['first_attempt']) > $decaySeconds) {
            $data = ['attempts' => 1, 'first_attempt' => $now];
        } else {
            $data['attempts']++;
        }

        $_SESSION[$key] = $data;
    }
}

if (!function_exists('reset_rate_limit')) {
    function reset_rate_limit(string $action): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $key = 'rl_' . md5($action . '_' . $ip);
        unset($_SESSION[$key]);
    }
}
