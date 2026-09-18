<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include_once('includes/database.php');

$error = '';
$success = '';
$token = $_GET['token'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = mysqli_real_escape_string($conn, $_POST['token'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (!empty($token) && !empty($password) && !empty($confirm_password)) {
        if ($password !== $confirm_password) {
            $error = "Passwords do not match.";
        } elseif (strlen($password) < 6) {
            $error = "Password must be at least 6 characters long.";
        } else {
            $sql = "SELECT id FROM users WHERE reset_token = '$token' AND reset_expires > NOW() LIMIT 1";
            $res = mysqli_query($conn, $sql);

            if ($res && mysqli_num_rows($res) > 0) {
                $user = mysqli_fetch_assoc($res);
                $user_id = (int)$user['id'];
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $hashed_escaped  = mysqli_real_escape_string($conn, $hashed_password);

                $update_sql = "UPDATE users SET password = '$hashed_escaped', reset_token = NULL, reset_expires = NULL WHERE id = $user_id";
                mysqli_query($conn, $update_sql);

                $success = "Password successfully reset! You can now <a href='index.php'>login here</a>.";
            } else {
                $error = "Invalid or expired reset token.";
            }
        }
    } else {
        $error = "Please fill in all fields.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Geo Auction</title>
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
            margin-bottom: 24px;
            color: #1a1a2e;
            font-size: 22px;
            font-weight: 700;
        }
        .form-group { margin-bottom: 16px; position: relative; }
        label { display: block; margin-bottom: 6px; color: #444; font-size: 13px; font-weight: 600; }
        input[type="password"] {
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
        }
        .success a { color: #155724; text-decoration: underline; font-weight: 600; }
        .links { margin-top: 18px; text-align: center; font-size: 13px; color: #666; }
        .links a { color: #28a745; text-decoration: none; font-weight: 600; }
        .links a:hover { text-decoration: underline; }
    </style>
</head>
<body>

<div class="card">
    <h2>Reset Password</h2>

    <?php if (!empty($error)): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if (!empty($success)): ?>
        <div class="success"><?= $success ?></div>
    <?php endif; ?>
    
    <?php if (empty($success)): ?>
    <form action="reset_password.php" method="POST">
        <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
        
        <div class="form-group">
            <label for="password">New Password <span style="color:#dc3545">*</span></label>
            <input type="password" id="password" name="password" placeholder="Min 6 characters" required>
        </div>

        <div class="form-group">
            <label for="confirm_password">Confirm New Password <span style="color:#dc3545">*</span></label>
            <input type="password" id="confirm_password" name="confirm_password" placeholder="Re-enter password" required>
        </div>

        <button type="submit" class="btn">Update Password</button>
    </form>
    <?php endif; ?>

    <div class="links">
        <a href="index.php">Back to Login</a>
    </div>
</div>

</body>
</html>