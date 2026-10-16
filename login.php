<?php
/**
 * Login Controller & Authentication Portal
 * File: login.php
 * 
 * Tech Stack: PHP 8+, OOP PDO, CSRF Defense, Brute-Force Rate Limiting,
 * Session Security & Persistent Remember-Me Authentication.
 */

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Security response headers
if (!headers_sent()) {
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}

require_once __DIR__ . '/includes/Database.php';
require_once __DIR__ . '/includes/auth.php';

// Helper for safe local redirection preventing Open Redirect vulnerabilities
function get_safe_redirect(string $rawRedirect, string $defaultRole = 'buyer'): string {
    $default = ($defaultRole === 'admin') ? 'admin/dashboard.php' : 'user/dashboard.php';
    $rawRedirect = trim($rawRedirect);
    $rawRedirect = preg_replace('/^(\.\.\/)+/', '', $rawRedirect);

    // Reject absolute URLs, scheme-relative URLs, and protocol indicators
    if (empty($rawRedirect) || preg_match('/^(https?:|\/\/|javascript:|data:)/i', $rawRedirect)) {
        return $default;
    }

    // Must be a valid relative path starting with alphanumeric or directory
    if (!preg_match('/^[a-zA-Z0-9_\-\/]+\.php(\?[a-zA-Z0-9_\-=&]*)?$/', $rawRedirect)) {
        return $default;
    }

    return $rawRedirect;
}

// ── Persistent "Remember Me" Auto-Login Verification ──
if (!is_logged_in() && !empty($_COOKIE['geo_remember'])) {
    $cookieParts = explode(':', (string)$_COOKIE['geo_remember'], 2);
    if (count($cookieParts) === 2) {
        $cookieUserId = (int)$cookieParts[0];
        $cookieToken  = $cookieParts[1];

        if ($cookieUserId > 0 && !empty($cookieToken)) {
            try {
                $pdo = Database::getPdo();
                $uStmt = $pdo->prepare("
                    SELECT `id`, `name`, `email`, `phone`, `role`, `avatar`, `bio`, `gender`, `status`, `remember_token` 
                    FROM users 
                    WHERE `id` = :id 
                    LIMIT 1
                ");
                $uStmt->execute([':id' => $cookieUserId]);
                $remUser = $uStmt->fetch(PDO::FETCH_ASSOC);

                if ($remUser && !empty($remUser['remember_token'])) {
                    $tokenHash = hash('sha256', $cookieToken);
                    if (hash_equals($remUser['remember_token'], $tokenHash)) {
                        $status = $remUser['status'] ?? 'active';
                        if ($status == 1 || $status === 'active') {
                            regenerate_session_safely();
                            $userRole = strtolower(trim($remUser['role'] ?? 'buyer'));

                            $_SESSION['user_id']     = (int)$remUser['id'];
                            $_SESSION['id']          = (int)$remUser['id'];
                            $_SESSION['role']        = $userRole;
                            $_SESSION['user_role']   = $userRole;
                            $_SESSION['name']        = $remUser['name'];
                            $_SESSION['email']       = $remUser['email'];
                            $_SESSION['user_name']   = $remUser['name'];
                            $_SESSION['user_email']  = $remUser['email'];
                            $_SESSION['user_phone']  = $remUser['phone'] ?? '';
                            $_SESSION['user_avatar'] = $remUser['avatar'] ?? '';
                            $_SESSION['user_bio']    = $remUser['bio'] ?? '';
                            $_SESSION['user_gender'] = $remUser['gender'] ?? '';

                            if ($userRole === 'admin') {
                                $_SESSION['admin_logged_in'] = true;
                                $_SESSION['admin_id']        = (int)$remUser['id'];
                                $_SESSION['admin_name']      = $remUser['name'];
                                $_SESSION['admin_email']     = $remUser['email'];
                            }

                            // Update last login metadata
                            $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
                            $pdo->prepare("UPDATE users SET last_login_at = NOW(), last_login_ip = :ip WHERE id = :id")
                                ->execute([':ip' => $ip, ':id' => (int)$remUser['id']]);

                            $redirect = get_safe_redirect($_GET['redirect'] ?? '', $userRole);
                            header("Location: " . $redirect);
                            exit();
                        }
                    }
                }
            } catch (\Throwable $e) {
                error_log("Remember-me auto-login failed: " . $e->getMessage());
            }
        }
    }
    // Clear invalid cookie
    setcookie('geo_remember', '', time() - 3600, '/', '', (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'), true);
}

// ── Check if already authenticated ──
if (is_logged_in()) {
    $currRole = strtolower(trim($_SESSION['role'] ?? $_SESSION['user_role'] ?? 'buyer'));
    $redirect = get_safe_redirect($_GET['redirect'] ?? '', $currRole);
    header("Location: " . $redirect);
    exit();
}

$alert_type   = '';
$alert_msg    = '';
$email_val    = '';
$remember_val = false;

if (isset($_GET['logout'])) {
    $alert_type = 'success';
    $alert_msg  = 'You have been successfully logged out.';
}

// ── Handle Login Form Submission ──
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $email        = trim($_POST['email'] ?? '');
    $password     = (string)($_POST['password'] ?? '');
    $remember     = isset($_POST['remember']);
    $email_val    = $email;
    $remember_val = $remember;

    // 1. CSRF Token Verification
    if (!validate_csrf_token()) {
        $alert_type = 'error';
        $alert_msg  = 'Your security session has expired. Please refresh and try again.';
    }
    // 2. Brute-Force Rate Limiting Check (5 attempts / 5 minutes)
    else {
        $rateLimitKey = 'login_' . strtolower($email);
        $rateLimit = check_rate_limit($rateLimitKey, 5, 300);

        if (!$rateLimit['allowed']) {
            $minutes = max(1, (int)ceil($rateLimit['retry_after'] / 60));
            $alert_type = 'error';
            $alert_msg  = "Too many consecutive failed login attempts. For your security, this account is temporarily locked for {$minutes} minute(s).";
        } elseif (empty($email) || empty($password)) {
            $alert_type = 'error';
            $alert_msg  = 'Please enter both your email address and password.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $alert_type = 'error';
            $alert_msg  = 'Please enter a valid email address.';
        } else {
            try {
                $pdo = Database::getPdo();
                $stmt = $pdo->prepare("
                    SELECT `id`, `name`, `email`, `phone`, `password`, `role`, `avatar`, `bio`, `gender`, `status` 
                    FROM users 
                    WHERE `email` = :email 
                    LIMIT 1
                ");
                $stmt->execute([':email' => $email]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($user && password_verify($password, (string)$user['password'])) {
                    $status = $user['status'] ?? 'active';

                    if ($status == 1 || $status === 'active') {
                        // Reset rate limiter on successful authentication
                        reset_rate_limit($rateLimitKey);

                        // Prevent Session Fixation attack
                        regenerate_session_safely();

                        $user_role = strtolower(trim($user['role'] ?? 'buyer'));
                        $_SESSION['user_id']     = (int)$user['id'];
                        $_SESSION['id']          = (int)$user['id'];
                        $_SESSION['role']        = $user_role;
                        $_SESSION['user_role']   = $user_role;
                        $_SESSION['name']        = $user['name'];
                        $_SESSION['email']       = $user['email'];
                        $_SESSION['user_name']   = $user['name'];
                        $_SESSION['user_email']  = $user['email'];
                        $_SESSION['user_phone']  = $user['phone'] ?? '';
                        $_SESSION['user_avatar'] = $user['avatar'] ?? '';
                        $_SESSION['user_bio']    = $user['bio'] ?? '';
                        $_SESSION['user_gender'] = $user['gender'] ?? '';

                        if ($user_role === 'admin') {
                            $_SESSION['admin_logged_in'] = true;
                            $_SESSION['admin_id']        = (int)$user['id'];
                            $_SESSION['admin_name']      = $user['name'];
                            $_SESSION['admin_email']     = $user['email'];
                        }

                        // Update login timestamp & IP
                        $clientIp = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
                        $pdo->prepare("UPDATE users SET last_login_at = NOW(), last_login_ip = :ip WHERE id = :id")
                            ->execute([':ip' => $clientIp, ':id' => (int)$user['id']]);

                        // Handle Persistent "Remember Me" Cookie
                        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
                        if ($remember) {
                            $rawToken = bin2hex(random_bytes(32));
                            $hashedToken = hash('sha256', $rawToken);

                            $pdo->prepare("UPDATE users SET remember_token = :token WHERE id = :id")
                                ->execute([':token' => $hashedToken, ':id' => (int)$user['id']]);

                            $cookieValue = $user['id'] . ':' . $rawToken;
                            setcookie(
                                'geo_remember',
                                $cookieValue,
                                [
                                    'expires'  => time() + (30 * 86400),
                                    'path'     => '/',
                                    'secure'   => $isHttps,
                                    'httponly' => true,
                                    'samesite' => 'Lax'
                                ]
                            );
                        } else {
                            $pdo->prepare("UPDATE users SET remember_token = NULL WHERE id = :id")
                                ->execute([':id' => (int)$user['id']]);
                            if (isset($_COOKIE['geo_remember'])) {
                                setcookie('geo_remember', '', time() - 3600, '/', '', $isHttps, true);
                            }
                        }

                        // Determine safe destination
                        $redirect = get_safe_redirect($_POST['redirect'] ?? $_GET['redirect'] ?? '', $user_role);
                        header("Location: " . $redirect);
                        exit();

                    } else {
                        $alert_type = 'error';
                        $alert_msg  = 'Your account has been deactivated or suspended. Please contact customer support.';
                    }
                } else {
                    // Record failed attempt for rate limiting
                    record_rate_limit_attempt($rateLimitKey, 300);
                    $alert_type = 'error';
                    $alert_msg  = 'Invalid email address or password. Please verify and try again.';
                }
            } catch (\Throwable $e) {
                error_log("Login error: " . $e->getMessage());
                $alert_type = 'error';
                $alert_msg  = 'A secure connection error occurred during sign-in. Please try again.';
            }
        }
    }
}

$redirect_param = trim($_GET['redirect'] ?? $_POST['redirect'] ?? '');

// ── Safely Query Platform Brand Statistics with Error Handling ──
$liveCount = 0;
$bidsCount = 0;
$usersCount = 0;

try {
    $statsPdo = Database::getPdo();
    $liveCount  = (int)($statsPdo->query("SELECT COUNT(*) FROM items WHERE status = 'active'")->fetchColumn() ?: 0);
    $bidsCount  = (int)($statsPdo->query("SELECT COUNT(*) FROM bids")->fetchColumn() ?: 0);
    $usersCount = (int)($statsPdo->query("SELECT COUNT(*) FROM users")->fetchColumn() ?: 0);
} catch (\Throwable $e) {
    error_log("Failed to load login stats: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Securely login to Geo Auction to discover live lots, place competitive bids, and track your transactions.">
    <title>Sign In – Geo Auction</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --green: rgb(4, 120, 87);
            --green-hover: rgb(5, 150, 105);
        }

        .input-icon-wrap {
            position: relative;
            display: flex;
            align-items: center;
        }
        .input-icon-wrap .left-icon {
            position: absolute;
            left: 14px;
            color: var(--green);
            font-size: 15px;
            pointer-events: none;
            z-index: 2;
        }
        .input-icon-wrap .form-control {
            padding-left: 42px !important;
            padding-right: 44px;
        }

        /* Interactive Popup Modal */
        .popup-overlay {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.75);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            z-index: 99999;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.25s cubic-bezier(0.16, 1, 0.3, 1), visibility 0.25s;
        }
        .popup-overlay.active {
            opacity: 1;
            visibility: visible;
        }
        .popup-card {
            width: 100%;
            max-width: 420px;
            background: #ffffff;
            border: 1px solid rgba(0, 0, 0, 0.08);
            border-radius: 20px;
            padding: 32px 28px 24px 28px;
            text-align: center;
            position: relative;
            box-shadow: 0 20px 45px -10px rgba(0, 0, 0, 0.25);
            transform: scale(0.9) translateY(12px);
            transition: transform 0.28s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .popup-overlay.active .popup-card {
            transform: scale(1) translateY(0);
        }
        .popup-close-btn {
            position: absolute;
            top: 14px;
            right: 14px;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: #f1f5f9;
            border: none;
            color: #64748b;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .popup-close-btn:hover {
            background: #e2e8f0;
            color: #0f172a;
            transform: rotate(90deg);
        }
        .popup-icon-ring {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            margin: 0 auto 16px auto;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
        }
        .popup-type-error .popup-icon-ring {
            background: #fef2f2;
            color: #dc2626;
            border: 2px solid #fecaca;
        }
        .popup-type-success .popup-icon-ring {
            background: #ecfdf5;
            color: #059669;
            border: 2px solid #a7f3d0;
        }
        .popup-title {
            color: #0f172a;
            font-size: 19px;
            font-weight: 700;
            margin-bottom: 8px;
        }
        .popup-desc {
            color: #475569;
            font-size: 14px;
            line-height: 1.5;
            margin-bottom: 22px;
        }
        .popup-btn {
            width: 100%;
            padding: 12px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 700;
            border: none;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .popup-type-error .popup-btn {
            background: #dc2626;
            color: #fff;
        }
        .popup-type-error .popup-btn:hover {
            background: #b91c1c;
        }
        .popup-type-success .popup-btn {
            background: var(--green);
            color: #fff;
        }
        .popup-type-success .popup-btn:hover {
            background: var(--green-hover);
        }
    </style>
</head>
<body>

<!-- Interactive Popup Modal for Alerts & System Notifications -->
<div id="loginPopupModal" class="popup-overlay <?php echo !empty($alert_msg) ? 'active' : ''; ?>" role="dialog" aria-modal="true" aria-labelledby="popupTitle" aria-describedby="popupDesc" onclick="if(event.target === this) closeLoginPopup()">
    <div class="popup-card <?php echo $alert_type === 'success' ? 'popup-type-success' : 'popup-type-error'; ?>">
        <button type="button" class="popup-close-btn" onclick="closeLoginPopup()" aria-label="Close message">
            <i class="fas fa-times"></i>
        </button>
        <div class="popup-icon-ring">
            <i class="fas <?php echo $alert_type === 'success' ? 'fa-check' : 'fa-circle-exclamation'; ?>"></i>
        </div>
        <h4 class="popup-title" id="popupTitle">
            <?php echo $alert_type === 'success' ? 'Success' : 'Notice'; ?>
        </h4>
        <p class="popup-desc" id="popupDesc">
            <?php echo htmlspecialchars($alert_msg, ENT_QUOTES, 'UTF-8'); ?>
        </p>
        <button type="button" class="popup-btn" id="loginPopupBtn" onclick="closeLoginPopup()">
            <?php echo $alert_type === 'success' ? 'Continue' : 'Okay, Got It'; ?>
        </button>
    </div>
</div>

<div class="auth-page">
    <!-- ── LEFT: Brand Showcase Panel ── -->
    <div class="auth-brand">
        <div class="auth-brand-logo">
            <img src="assets/image/Geo-auction-logo-clean.png" alt="Geo Auction" style="height: 48px; width: auto; object-fit: contain;" onerror="this.onerror=null;this.src='assets/image/Geo-auction.png'">
        </div>
        <h1 class="auth-brand-title">
            Your Next<br>Great Deal <span>Awaits.</span>
        </h1>
        <p class="auth-brand-sub">
            Join thousands of active bidders on Geo Auction — the live bidding platform where you discover, bid, and win verified lots every day.
        </p>
        <div class="auth-brand-stats">
            <div class="auth-stat">
                <div class="auth-stat-val"><?php echo number_format($liveCount); ?>+</div>
                <div class="auth-stat-label">Live Auctions</div>
            </div>
            <div class="auth-stat">
                <div class="auth-stat-val"><?php echo number_format($bidsCount); ?>+</div>
                <div class="auth-stat-label">Bids Placed</div>
            </div>
            <div class="auth-stat">
                <div class="auth-stat-val"><?php echo number_format($usersCount); ?>+</div>
                <div class="auth-stat-label">Members</div>
            </div>
        </div>
    </div>

    <!-- ── RIGHT: Authentication Form ── -->
    <div class="auth-form-side">
        <a href="index.php" style="display:inline-flex;align-items:center;gap:6px;font-size:13px;color:var(--text-muted);margin-bottom:32px;">
            <i class="fas fa-arrow-left"></i> Back to Home
        </a>

        <div class="auth-form-header">
            <h1>Welcome back</h1>
            <p>Sign in to your Geo Auction account to continue</p>
        </div>

        <form method="POST" action="" id="loginForm" novalidate>
            <!-- CSRF Protection Field -->
            <?php echo csrf_input(); ?>

            <!-- Safe Forwarding Parameter -->
            <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($redirect_param, ENT_QUOTES, 'UTF-8'); ?>">

            <div class="form-group">
                <label class="form-label" for="email">Email Address</label>
                <div class="input-icon-wrap">
                    <i class="fas fa-envelope left-icon" aria-hidden="true"></i>
                    <input type="email" id="email" name="email" class="form-control"
                           placeholder="you@example.com"
                           value="<?php echo htmlspecialchars($email_val, ENT_QUOTES, 'UTF-8'); ?>"
                           required autocomplete="email" aria-required="true">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="password">
                    Password
                    <a href="forgot_password.php" style="float:right;font-size:12px;color:var(--green);font-weight:600;">Forgot password?</a>
                </label>
                
                <div class="input-icon-wrap">
                    <i class="fas fa-lock left-icon" aria-hidden="true"></i>
                    <input type="password" id="password" name="password" class="form-control"
                           placeholder="Enter your password"
                           required autocomplete="current-password" aria-required="true">
                    <button type="button" onclick="togglePwd()" id="togglePwdBtn"
                            aria-label="Toggle password visibility"
                            aria-pressed="false"
                            style="position:absolute; right:14px; top:50%; transform:translateY(-50%); background:none; border:none; color:var(--text-muted); cursor:pointer; font-size:15px; z-index: 5;">
                        <i class="fas fa-eye" id="eyeIcon"></i>
                    </button>
                </div>
            </div>

            <div style="display:flex;align-items:center;gap:8px;margin-bottom:22px;">
                <input type="checkbox" id="remember" name="remember" value="1"
                       <?php echo $remember_val ? 'checked' : ''; ?>
                       style="width:16px;height:16px;accent-color:var(--green);">
                <label for="remember" style="font-size:13px;color:var(--text-muted);cursor:pointer;user-select:none;">
                    Remember me for 30 days
                </label>
            </div>

            <button type="submit" class="btn btn-primary btn-block btn-lg" id="loginBtn">
                <i class="fas fa-sign-in-alt"></i> <span>Sign In</span>
            </button>
        </form>

        <div class="auth-divider"><span>or</span></div>

        <a href="home.php" class="btn btn-block btn-lg" style="background:var(--bg);color:var(--text);border:2px solid var(--border);">
            <i class="fas fa-eye"></i> Browse Auctions as Guest
        </a>

        <div class="auth-footer-link">
            Don't have an account? <a href="register.php">Create one free →</a>
        </div>
    </div>
</div>

<script>
function closeLoginPopup() {
    const modal = document.getElementById('loginPopupModal');
    if (modal) {
        modal.classList.remove('active');
    }
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeLoginPopup();
    }
});

document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('loginPopupModal');
    if (modal && modal.classList.contains('active')) {
        const btn = document.getElementById('loginPopupBtn');
        if (btn) btn.focus();
    }
});

function togglePwd() {
    const input = document.getElementById('password');
    const icon  = document.getElementById('eyeIcon');
    const btn   = document.getElementById('togglePwdBtn');
    if (!input || !icon) return;

    if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'fas fa-eye-slash';
        if (btn) btn.setAttribute('aria-pressed', 'true');
    } else {
        input.type = 'password';
        icon.className = 'fas fa-eye';
        if (btn) btn.setAttribute('aria-pressed', 'false');
    }
}

// Client-side validation and animated progress state
document.getElementById('loginForm').addEventListener('submit', function(e) {
    const form = this;
    const emailInput = document.getElementById('email');
    const passwordInput = document.getElementById('password');

    if (!form.checkValidity()) {
        e.preventDefault();
        form.reportValidity();
        return;
    }

    const btn = document.getElementById('loginBtn');
    if (btn) {
        btn.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> <span>Signing in...</span>';
        btn.disabled = true;
    }

    if (typeof showLoader === 'function') {
        showLoader('Authenticating credentials, please wait...');
    }
});
</script>
<?php include_once __DIR__ . '/includes/components/loading_spinner_overlay.php'; ?>
</body>
</html>