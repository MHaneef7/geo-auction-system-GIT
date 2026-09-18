<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

$_SESSION = array();
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_unset();
session_destroy();

$message = "You have been successfully logged out.";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logged Out - Geo Auction</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        }
        .logout-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: #d1e7dd;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
        }
        .logout-icon i {
            font-size: 2rem;
            color: #198754;
        }
        .card {
            border: none;
            border-radius: 12px;
        }
        .card-header {
            border-radius: 12px 12px 0 0 !important;
            background: #007a33 !important;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="col-md-5 mx-auto">
        <div class="card shadow">
            <div class="card-header text-white text-center py-3">
                <h4 class="mb-0">Logged Out</h4>
            </div>
            <div class="card-body text-center py-4">
                <div class="logout-icon">
                    <i class="fa-solid fa-check"></i>
                </div>
                <div class="alert alert-success">
                    <i class="fa-solid fa-circle-check me-1"></i>
                    <?= htmlspecialchars($message) ?>
                </div>
                <p class="text-muted mb-4">Your session has been ended securely.</p>
                <div class="d-grid gap-2">
                    <a href="index.php" class="btn btn-success" style="background:#007a33; border:none;">
                        <i class="fa-solid fa-right-to-bracket me-1"></i> Log In Again
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
    // 3. Clear History state so back button cannot restore previous cached page views
    if (window.history && window.history.pushState) {
        window.history.pushState('', null, './logout.php');
        window.addEventListener('popstate', function () {
            window.history.pushState('', null, './logout.php');
        });
    }

    setTimeout(function() {
        window.location.replace('index.php');
    }, 2000);
</script>
</body>
</html>