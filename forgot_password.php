<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include_once('includes/database.php');

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = mysqli_real_escape_string($conn, trim($_POST['email'] ?? ''));

    if (!empty($email)) {
        $sql = "SELECT id FROM users WHERE email = '$email' LIMIT 1";
        $res = mysqli_query($conn, $sql);

        if ($res && mysqli_num_rows($res) > 0) {
            $user = mysqli_fetch_assoc($res);
            $token = bin2hex(random_bytes(32));
            $token_escaped = mysqli_real_escape_string($conn, $token);
            $expires = date("Y-m-d H:i:s", strtotime("+1 hour"));

            $update_sql = "UPDATE users SET reset_token = '$token_escaped', reset_expires = '$expires' WHERE email = '$email'";
            mysqli_query($conn, $update_sql);

            $message = "If that email exists, a reset link has been generated. <br><br><strong>Testing Reset Link:</strong> <a href='reset_password.php?token=$token'>Reset Password Here</a>";
        } else {
            $message = "If that email exists, a reset link has been generated.";
        }
    } else {
        $error = "Please enter your email address.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Geo Auction</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f0f2f5;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }
        .card {
            background: #fff;
            padding: 32px 30px 24px;
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            width: 100%;
            max-width: 420px;
        }
        h2 {
            text-align: center;
            margin-bottom: 10px;
            color: #1a1a2e;
            font-size: 22px;
            font-weight: 700;
        }
        .subtitle {
            text-align: center;
            color: #777;
            font-size: 13px;
            margin-bottom: 24px;
            line-height: 1.5;
        }
        .form-group { margin-bottom: 16px; }
        label { display: block; margin-bottom: 6px; color: #444; font-size: 13px; font-weight: 600; }
        input[type="email"] {
            width: 100%; padding: 10px 12px; border: 1.5px solid #ddd; border-radius: 6px;
            font-size: 14px; color: #333; background: #fafafa; outline: none;
            transition: border-color 0.2s;
        }
        input:focus { border-color: #28a745; background: #fff; }
        .btn {
            width: 100%; background: #28a745; color: #fff; border: none; padding: 11px;
            border-radius: 6px; cursor: pointer; font-size: 15px; font-weight: 600; margin-top: 6px;
        }
        .btn:hover { background: #218838; }
        .error {
            color: #dc3545; margin-bottom: 14px; font-size: 13px; background: #fef0f0;
            padding: 10px 12px; border-radius: 6px; border-left: 3px solid #dc3545;
        }
        .success {
            color: #155724; margin-bottom: 14px; font-size: 13px; background: #eef8ef;
            padding: 10px 12px; border-radius: 6px; border-left: 3px solid #28a745;
            word-break: break-all; line-height: 1.6;
        }
        .links { margin-top: 18px; text-align: center; font-size: 13px; color: #666; }
        .links a { color: #28a745; text-decoration: none; font-weight: 600; }
        .links a:hover { text-decoration: underline; }
    </style>
</head>
<body>

<div class="card">
    <h2>Forgot Password</h2>
    <p class="subtitle">Enter your email address and we'll send you a link to reset your password.</p>

    <?php if (!empty($error)): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    
    <?php if (!empty($message)): ?>
        <div class="success"><?= $message ?></div>
    <?php endif; ?>

    <?php if (empty($message)): ?>
    <form action="forgot_password.php" method="POST">
        <div class="form-group">
            <label for="email">Email Address <span style="color:#dc3545">*</span></label>
            <input type="email" id="email" name="email" placeholder="you@example.com" required>
        </div>
        <button type="submit" class="btn">Send Reset Link</button>
    </form>
    <?php endif; ?>

    <div class="links">
        <a href="login.php">Back to Login</a>
    </div>
</div>

</body>
</html>