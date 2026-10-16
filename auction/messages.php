<?php
/**
 * Auction Messages Router & Gateway Bridge
 * File: auction/messages.php
 * Geo Auction System
 *
 * Robust messaging bridge that routes incoming auction chat requests
 * (/auction/messages.php?user_id=X&item_id=Y) to the primary real-time
 * messaging portal (../messages.php) with full query parameter integrity.
 *
 * Tech: PHP 8+, Strict Types, Session Preservation, HTTP 302 + Client Fallback.
 */

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$baseDir = dirname(__DIR__);
if (file_exists($baseDir . '/includes/database.php')) {
    require_once $baseDir . '/includes/database.php';
}
if (file_exists($baseDir . '/includes/auth.php')) {
    require_once $baseDir . '/includes/auth.php';
}

// Sanitize & build target query parameters
$userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
$itemId = isset($_GET['item_id']) ? (int)$_GET['item_id'] : 0;

$queryParams = [];
if ($userId > 0) {
    $queryParams['user_id'] = $userId;
}
if ($itemId > 0) {
    $queryParams['item_id'] = $itemId;
}

// Preserve any extra query string parameters if passed
foreach ($_GET as $key => $val) {
    if (!isset($queryParams[$key]) && is_string($val)) {
        $queryParams[$key] = $val;
    }
}

$queryString = !empty($queryParams) ? '?' . http_build_query($queryParams) : '';
$destinationUrl = '../messages.php' . $queryString;

// Route unauthenticated visitors to login while preserving the return target
if (function_exists('is_logged_in') && !is_logged_in()) {
    $loginRedirect = 'messages.php' . $queryString;
    $destinationUrl = '../login.php?redirect=' . urlencode($loginRedirect);
}

// Immediate server-side HTTP 302 redirect
if (!headers_sent()) {
    header('Location: ' . $destinationUrl, true, 302);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="refresh" content="0;url=<?php echo htmlspecialchars($destinationUrl); ?>">
    <title>Connecting to Auction Messages – Geo Auction</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            color: #ffffff;
        }
        .gateway-card {
            background: rgba(30, 41, 59, 0.85);
            border: 1px solid rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border-radius: 24px;
            padding: 40px 32px;
            max-width: 440px;
            width: 100%;
            text-align: center;
            box-shadow: 0 20px 40px -10px rgba(0, 0, 0, 0.5);
        }
        .gateway-icon {
            width: 72px;
            height: 72px;
            border-radius: 20px;
            background: rgba(0, 132, 137, 0.15);
            border: 1px solid rgba(0, 132, 137, 0.35);
            color: #2dd4bf;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            margin: 0 auto 20px;
            position: relative;
            animation: pulse-ring 2s infinite cubic-bezier(0.4, 0, 0.6, 1);
        }
        @keyframes pulse-ring {
            0%, 100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(0, 132, 137, 0.4); }
            50% { transform: scale(1.04); box-shadow: 0 0 0 12px rgba(0, 132, 137, 0); }
        }
        h2 { font-size: 20px; font-weight: 800; margin-bottom: 8px; color: #f8fafc; }
        p { font-size: 13.5px; color: #94a3b8; line-height: 1.5; margin-bottom: 24px; }
        .loading-bar {
            width: 100%;
            height: 4px;
            background: rgba(255, 255, 255, 0.08);
            border-radius: 9999px;
            overflow: hidden;
            margin-bottom: 28px;
            position: relative;
        }
        .loading-bar::after {
            content: '';
            position: absolute;
            top: 0; left: 0; bottom: 0;
            width: 40%;
            background: linear-gradient(90deg, #008489, #2dd4bf);
            border-radius: 9999px;
            animation: load-slide 1.2s infinite ease-in-out;
        }
        @keyframes load-slide {
            0% { left: -40%; width: 30%; }
            50% { width: 50%; }
            100% { left: 100%; width: 30%; }
        }
        .btn-direct {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            padding: 13px 24px;
            background: #008489;
            color: #ffffff;
            font-size: 14px;
            font-weight: 700;
            border-radius: 14px;
            text-decoration: none;
            transition: all 0.2s ease;
            box-shadow: 0 4px 14px rgba(0, 132, 137, 0.4);
        }
        .btn-direct:hover {
            background: #006c70;
            transform: translateY(-2px);
            color: #ffffff;
            box-shadow: 0 6px 20px rgba(0, 132, 137, 0.55);
        }
    </style>
    <script>
        // Immediate client redirection fallback
        window.location.replace(<?php echo json_encode($destinationUrl); ?>);
    </script>
</head>
<body>
    <div class="gateway-card">
        <div class="gateway-icon">
            <i class="fa-solid fa-comments"></i>
        </div>
        <h2>Connecting to Conversation</h2>
        <p>Routing to the live auction messaging portal<?php echo $userId > 0 ? ' with User #' . $userId : ''; ?>...</p>
        <div class="loading-bar"></div>
        <a href="<?php echo htmlspecialchars($destinationUrl); ?>" class="btn-direct">
            <span>Continue to Chat</span>
            <i class="fa-solid fa-arrow-right"></i>
        </a>
    </div>
</body>
</html>
