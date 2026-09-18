<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

    include_once 'includes/database.php';
        if (isset($_SESSION['user_id']) || isset($_SESSION['id'])) {
            $redirect = isset($_GET['redirect']) ? $_GET['redirect'] : 'user/dashboard.php';
            header("Location: " . $redirect);
            exit();
        }

$alert_type = '';
$alert_msg  = '';
$email_val  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email     = trim($_POST['email'] ?? '');
    $password  = trim($_POST['password'] ?? '');
    $email_val = $email;

    if (!empty($email) && !empty($password)) {
        // Prepared statement use kiya gaya hai security ke liye
        $stmt = mysqli_prepare($conn, "SELECT `id`, `name`, `email`, `phone`, `password`, `role`, `avatar`, `bio`, `gender`, `status` FROM users WHERE `email` = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if ($result && mysqli_num_rows($result) > 0) {
            $user = mysqli_fetch_assoc($result);
            
            if (password_verify($password, $user['password'])) {
                $status = $user['status'] ?? 'active';
                
                if ($status == 1 || $status === 'active') {

                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['role']    = strtolower($user['role'] ?? 'user');
                    $_SESSION['name']    = $user['name'];
                    $_SESSION['email']   = $user['email'];
                    
                    $_SESSION['user_name']   = $user['name'];
                    $_SESSION['user_email']  = $user['email'];
                    $_SESSION['user_phone']  = $user['phone'] ?? '';
                    $_SESSION['user_avatar'] = $user['avatar'] ?? '';
                    $_SESSION['user_bio']    = $user['bio'] ?? '';
                    $_SESSION['user_gender'] = $user['gender'] ?? '';
                    $_SESSION['id']          = $user['id']; // Purani compatibility ke liye

                    // Seedha user/dashboard.php par redirect karein
                    $redirect = isset($_POST['redirect']) && !empty($_POST['redirect']) ? $_POST['redirect'] : 'user/dashboard.php';
                    header("Location: " . $redirect);
                    exit();
                } else {
                    $alert_type = 'error';
                    $alert_msg  = 'Your account has been suspended. Please contact support.';
                }
            } else {
                $alert_type = 'error';
                $alert_msg  = 'Incorrect password. Please try again.';
            }
        } else {
            $alert_type = 'error';
            $alert_msg  = 'No account found with that email address.';
        }
        mysqli_stmt_close($stmt);
    } else {
        $alert_type = 'error';
        $alert_msg  = 'Please enter your email and password.';
    }
}

$redirect_param = isset($_GET['redirect']) ? htmlspecialchars($_GET['redirect']) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Login to Geo Auction and start bidding on live auctions.">
    <title>Login – Geo Auction</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --green: rgb(4 120 87);
        }
        /* Custom wrapper to handle icon alignments inside form controls properly */
        .input-icon-wrap {
            position: relative;
            display: flex;
            align-items: center;
        }
        .input-icon-wrap .left-icon {
            position: absolute;
            left: 14px;
            color: rgb(4 120 87);
            font-size: 15px;
            pointer-events: none;
        }
        .input-icon-wrap .form-control {
            padding-left: 42px !important;
            padding-right: 44px;
        }
    </style>
</head>
<body>
<div class="auth-page">

    <!-- ── LEFT: Brand Panel ── -->
    <div class="auth-brand">
        <div class="auth-brand-logo">
            <img src="assets/image/Geo-auction.png" alt="Geo Auction" onerror="this.onerror=null;this.src='assets/image/geo-auction.png'">
        </div>
        <h1 class="auth-brand-title">
            Your Next<br>Great Deal <span>Awaits.</span>
        </h1>
        <p class="auth-brand-sub">
            Join thousands of bidders on Geo Auction — the live bidding platform where you discover, bid, and win amazing items every day.
        </p>
        <div class="auth-brand-stats">
            <div class="auth-stat">
                <?php
                $ls = mysqli_query($conn,"SELECT COUNT(*) AS c FROM items WHERE status='active'");
                $lc = mysqli_fetch_assoc($ls)['c'] ?? 0;
                ?>
                <div class="auth-stat-val"><?php echo number_format($lc); ?>+</div>
                <div class="auth-stat-label">Live Auctions</div>
            </div>
            <div class="auth-stat">
                <?php
                $bs = mysqli_query($conn,"SELECT COUNT(*) AS c FROM bids");
                $bc = mysqli_fetch_assoc($bs)['c'] ?? 0;
                ?>
                <div class="auth-stat-val"><?php echo number_format($bc); ?>+</div>
                <div class="auth-stat-label">Bids Placed</div>
            </div>
            <div class="auth-stat">
                <?php
                $us = mysqli_query($conn,"SELECT COUNT(*) AS c FROM users");
                $uc = mysqli_fetch_assoc($us)['c'] ?? 0;
                ?>
                <div class="auth-stat-val"><?php echo number_format($uc); ?>+</div>
                <div class="auth-stat-label">Members</div>
            </div>
        </div>
    </div>

    <div class="auth-form-side">
        <a href="index.php" style="display:inline-flex;align-items:center;gap:6px;font-size:13px;color:var(--text-muted);margin-bottom:32px;">
            <i class="fas fa-arrow-left"></i> Back to Home
        </a>

        <div class="auth-form-header">
            <h1>Welcome back</h1>
            <p>Sign in to your Geo Auction account to continue</p>
        </div>

        <?php if (!empty($alert_msg)): ?>
        <div class="alert alert-<?php echo $alert_type === 'error' ? 'error' : 'success'; ?>">
            <i class="fas fa-<?php echo $alert_type === 'error' ? 'circle-xmark' : 'circle-check'; ?>"></i>
            <?php echo htmlspecialchars($alert_msg); ?>
        </div>
        <?php endif; ?>

        <form method="POST" action="" id="loginForm">
            <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($redirect_param); ?>">

            <div class="form-group">
                <label class="form-label" for="email">Email Address</label>
                <div class="input-icon-wrap">
                    <i class="fas fa-envelope left-icon"></i>
                    <input type="email" id="email" name="email" class="form-control"
                           placeholder="you@example.com"
                           value="<?php echo htmlspecialchars($email_val); ?>"
                           required autocomplete="email">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="password">
                    Password
                    <a href="forgot_password.php" style="float:right;font-size:12px;color:var(--green);font-weight:600;">Forgot password?</a>
                </label>
                
                <div class="input-icon-wrap">
                    <i class="fas fa-lock left-icon"></i>
                    <input type="password" id="password" name="password" class="form-control"
                           placeholder="Enter your password"
                           required autocomplete="current-password">
                    <button type="button" onclick="togglePwd()" id="togglePwdBtn"
                            style="position:absolute; right:14px; top:50%; transform:translateY(-50%); background:none; border:none; color:var(--text-muted); cursor:pointer; font-size:15px; z-index: 5;">
                        <i class="fas fa-eye" id="eyeIcon"></i>
                    </button>
                </div>
            </div>

            <div style="display:flex;align-items:center;gap:8px;margin-bottom:22px;">
                <input type="checkbox" id="remember" name="remember" style="width:16px;height:16px;accent-color:var(--green);">
                <label for="remember" style="font-size:13px;color:var(--text-muted);cursor:pointer;">Remember me for 30 days</label>
            </div>

            <button type="submit" class="btn btn-primary btn-block btn-lg" id="loginBtn">
                <i class="fas fa-sign-in-alt"></i> Sign In
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
function togglePwd() {
    var input = document.getElementById('password');
    var icon  = document.getElementById('eyeIcon');
    if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'fas fa-eye-slash';
    } else {
        input.type = 'password';
        icon.className = 'fas fa-eye';
    }
}

document.getElementById('loginForm').addEventListener('submit', function() {
    var btn = document.getElementById('loginBtn');
    btn.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> Signing in...';
    btn.disabled = true;
});
</script>
</body>
</html>