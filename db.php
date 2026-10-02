<?php
/**
 * ============================================================================
 * File: db.php
 * Path: /db.php (Project Root)
 * Description: Central Database Connection, Security Helper & Session Handler
 * Architecture: Senior PHP Full-Stack Standards (PDO + Prepared Statements)
 * ============================================================================
 * 
 * Yeh file poore project ka central database gateway hai. 
 * Isme PDO connection with strict error reporting, CSRF protection,
 * XSS sanitization, aur standard session handling include ki gayi hai.
 */

// 1. Session ko securely initialize karein agar pehle se start na ho
if (session_status() === PHP_SESSION_NONE) {
    // Session security settings (Cookie HttpOnly, SameSite)
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    session_start();
}

// 2. Database configuration constants
if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
if (!defined('DB_NAME')) define('DB_NAME', 'geo_auction_db');
if (!defined('DB_USER')) define('DB_USER', 'root');
if (!defined('DB_PASS')) define('DB_PASS', '');
if (!defined('DB_PORT')) define('DB_PORT', 3306);
if (!defined('DB_CHARSET')) define('DB_CHARSET', 'utf8mb4');

/**
 * Global PDO Database Provider (Singleton Pattern)
 * 
 * @return PDO Active PDO Database connection instance
 */
function getDB(): PDO {
    static $pdoInstance = null;

    if ($pdoInstance === null) {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET . " COLLATE utf8mb4_unicode_ci"
        ];

        try {
            $pdoInstance = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log("Database Connection Error: " . $e->getMessage());
            die("Database connection failed. Please ensure MySQL server is running in XAMPP.");
        }
    }

    return $pdoInstance;
}

// Initialize global $pdo variable for direct use
$pdo = getDB();

// Global $conn (mysqli) connection backwards-compatibility ke liye
if (!isset($conn) || !($conn instanceof mysqli)) {
    mysqli_report(MYSQLI_REPORT_OFF);
    $conn = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
    if ($conn->connect_error) {
        $conn = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    }
    if (!$conn->connect_error) {
        $conn->set_charset(DB_CHARSET);
    }
}

/**
 * ============================================================================
 * SECURITY & HELPER FUNCTIONS
 * ============================================================================
 */

/**
 * CSRF Token Generator
 * Har session ke liye ek cryptographically secure token generate karta hai.
 * 
 * @return string Secure 64-char hex CSRF Token
 */
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * CSRF HTML Input Field Generator
 * Form ke andar hidden input inject karne ke liye.
 * 
 * @return string HTML Hidden Input
 */
function csrf_field(): string {
    $token = csrf_token();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * CSRF Token Validator
 * POST request aane par timing-attack safe verification karta hai.
 * 
 * @param string|null $token Submitted token (agar null ho to $_POST['csrf_token'] lega)
 * @return bool True agar token valid ho
 */
function verify_csrf_token(?string $token = null): bool {
    if ($token === null) {
        $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    }
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * XSS Sanitizer Function
 * Output render karte waqt script injection se bachata hai.
 * 
 * @param mixed $data Raw string ya array
 * @return string Sanitized string
 */
function sanitize($data): string {
    if (is_null($data)) return '';
    return htmlspecialchars(trim((string)$data), ENT_QUOTES, 'UTF-8');
}

/**
 * Shorthand XSS sanitizer
 */
function e($data): string {
    return sanitize($data);
}

/**
 * Standardized JSON API Response Helper
 * 
 * @param array $payload Response data
 * @param int $statusCode HTTP Status Code (default: 200)
 */
function json_response(array $payload, int $statusCode = 200): void {
    if (!headers_sent()) {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit();
}

/**
 * Flash Message Set Helper
 * User alerts (SweetAlert/Toasts) ke liye session me message store karta hai.
 * 
 * @param string $type success | error | warning | info
 * @param string $message Alert text
 */
function set_flash(string $type, string $message): void {
    $_SESSION['flash_message'] = [
        'type'    => $type,
        'message' => $message
    ];
}

/**
 * Flash Message Get & Clear Helper
 * 
 * @return array|null Flash message array or null
 */
function get_flash(): ?array {
    if (isset($_SESSION['flash_message'])) {
        $msg = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $msg;
    }
    return null;
}
