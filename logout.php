<?php
// Root logout script - Geo Auction System
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Unset all session variables
$_SESSION = array();

// Delete session cookie if present
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Destroy session
session_unset();
session_destroy();

// Prevent caching of logout response
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="refresh" content="2;url=index.php">
    <title>Logging Out – Geo Auction System</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            background-color: #ffffff;
            color: #000000;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            overflow: hidden;
        }
        .logout-card {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            padding: 2.25rem 2.5rem;
            background: #ffffff;
            color: #000000;
            border: 1px solid rgba(0, 0, 0, 0.08);
            border-radius: 1rem;
            box-shadow: 0 20px 45px -10px rgba(0, 0, 0, 0.14), 0 4px 16px rgba(0, 0, 0, 0.06);
            animation: popIn 0.25s cubic-bezier(0.16, 1, 0.3, 1);
        }
        @keyframes popIn {
            from { transform: scale(0.92); opacity: 0; }
            to { transform: scale(1); opacity: 1; }
        }
        .spinner-icon {
            width: 52px;
            height: 52px;
            color: #000000;
            animation: spin 0.85s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .logout-title {
            margin-top: 1.15rem;
            font-size: 1.05rem;
            font-weight: 700;
            color: #000000;
            letter-spacing: 0.01em;
        }
        .logout-subtext {
            margin-top: 0.35rem;
            font-size: 0.825rem;
            color: #334155;
        }
    </style>
</head>
<body>
    <div class="logout-card">
        <!-- Circular Two-Arrow Loop SVG Spinner -->
        <svg class="spinner-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <polyline points="23 4 23 10 17 10"></polyline>
            <polyline points="1 20 1 14 7 14"></polyline>
            <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path>
        </svg>
        <div class="logout-title">Logging out...</div>
        <div class="logout-subtext">Session cleared. Redirecting to home...</div>
    </div>
    <script>
        setTimeout(function() {
            window.location.replace("index.php");
        }, 2000);
    </script>
</body>
</html>