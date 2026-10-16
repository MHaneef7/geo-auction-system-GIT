<?php
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    include_once 'includes/database.php';
    include_once 'includes/settings.php';

    if (isset($_SESSION['user_id'])) {
        header("Location: dashboard.php");
        exit();
    }

    $pdo = Database::getPdo();
    Settings::init($pdo);

    $alert_type = '';
    $alert_msg  = '';
    $form_data  = [];

    $reg_enabled = ((int)Settings::get('enable_registration', 1) === 1);

    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        if (!$reg_enabled) {
            $alert_type = 'error';
            $alert_msg  = 'New user registrations are currently paused by administration.';
        } else {
            $name     = trim($_POST['name'] ?? '');
            $email    = trim($_POST['email'] ?? '');
            $phone    = trim($_POST['phone'] ?? '');
            $gender   = trim($_POST['gender'] ?? 'other');
            $role     = trim($_POST['role'] ?? 'buyer');
            $password = trim($_POST['password'] ?? '');
            $confirm  = trim($_POST['confirm_password'] ?? '');
            
            // Retain form data for repopulation
            $form_data = compact('name', 'email', 'phone', 'gender', 'role');

            // Assign image based on gender selection
            if ($gender === 'male') {
                $avatar = 'assets/image/male.png';
            } elseif ($gender === 'female') {
                $avatar = 'assets/image/female.png';
            } else {
                $avatar = 'assets/image/other.png';
            }

            // Validate role input to ensure it's either buyer or seller
            if (!in_array($role, ['buyer', 'seller'])) {
                $role = 'buyer';
            }

            $seller_reg_enabled = ((int)Settings::get('enable_seller_registration', 1) === 1);
            $buyer_reg_enabled  = ((int)Settings::get('enable_buyer_registration', 1) === 1);
            $password_min_len   = (int)Settings::get('password_min_length', 8);
            $require_strong_pwd = ((int)Settings::get('require_strong_password', 1) === 1);

            if ($role === 'seller' && !$seller_reg_enabled) {
                $alert_type = 'error';
                $alert_msg  = 'Seller registration is currently closed.';
            } elseif ($role === 'buyer' && !$buyer_reg_enabled) {
                $alert_type = 'error';
                $alert_msg  = 'Buyer registration is currently closed.';
            } elseif (empty($name) || empty($email) || empty($password)) {
                $alert_type = 'error';
                $alert_msg  = 'Please fill in all required fields.';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $alert_type = 'error';
                $alert_msg  = 'Please enter a valid email address.';
            } elseif (strlen($password) < $password_min_len) {
                $alert_type = 'error';
                $alert_msg  = "Password must be at least {$password_min_len} characters long.";
            } elseif ($require_strong_pwd && (!preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password))) {
                $alert_type = 'error';
                $alert_msg  = 'Password must include both letters and numbers.';
            } elseif ($password !== $confirm) {
                $alert_type = 'error';
                $alert_msg  = 'Passwords do not match. Please verify and retype your password.';
            } else {
                try {
                    $check = $pdo->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
                    $check->execute([':email' => $email]);

                    if ($check->fetch()) {
                        $alert_type = 'error';
                        $alert_msg  = 'This email is already registered. Please <a href="login.php">login</a> instead.';
                    } else {
                        $hashed = password_hash($password, PASSWORD_DEFAULT);
                        
                        $user_approval_req = ((int)Settings::get('user_approval_required', 0) === 1);
                        $default_status    = $user_approval_req ? 'pending' : (string)Settings::get('default_user_status', 'active');

                        // Secure prepared statement for registration
                        $ins = $pdo->prepare("
                            INSERT INTO users (`name`, `email`, `phone`, `password`, `gender`, `avatar`, `role`, `status`, `balance`, `created_at`)
                            VALUES (:name, :email, :phone, :password, :gender, :avatar, :role, :status, 0.00, NOW())
                        ");
                        $ins->execute([
                            ':name'     => $name,
                            ':email'    => $email,
                            ':phone'    => $phone,
                            ':password' => $hashed,
                            ':gender'   => $gender,
                            ':avatar'   => $avatar,
                            ':role'     => $role,
                            ':status'   => $default_status
                        ]);
                        
                        $new_id = (int)$pdo->lastInsertId();
                        
                        if ($user_approval_req || $default_status !== 'active') {
                            $alert_type = 'success';
                            $alert_msg  = 'Registration successful! Your account is pending administrative approval before you can log in.';
                            $form_data  = [];
                        } else {
                            // Comprehensive session synchronization
                            $_SESSION['user_id']     = $new_id;
                            $_SESSION['id']          = $new_id;
                            $_SESSION['name']        = $name;
                            $_SESSION['email']       = $email;
                            $_SESSION['role']        = $role;
                            $_SESSION['user_name']   = $name;
                            $_SESSION['user_email']  = $email;
                            $_SESSION['user_phone']  = $phone;
                            $_SESSION['user_gender'] = $gender;
                            $_SESSION['user_avatar'] = $avatar;
                            $_SESSION['user_role']   = $role;

                            header("Location: user/dashboard.php");
                            exit();
                        }
                    }
                } catch (\Throwable $e) {
                    error_log("Registration Error: " . $e->getMessage());
                    $alert_type = 'error';
                    $alert_msg  = 'Registration failed due to a system error. Please try again.';
                }
            }
        }
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Create your free Geo Auction account and start bidding or selling on live auctions today.">
    <title>Create Account – Geo Auction</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --green: rgb(4 120 87);
        }
        /* Buttons background & accent colors override */
        .btn-primary, button[type="submit"] {
            background-color: rgb(4 120 87) !important;
            border-color: rgb(4 120 87) !important;
        }
        .btn-primary:hover, button[type="submit"]:hover {
            opacity: 0.9;
        }
        .password-container {
            position: relative;
        }
        .password-container .fa-eye, .password-container .fa-eye-slash {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: var(--text-muted, #888);
            z-index: 10;
            text-align: right;
        }
        .form-control-icon i.fas:not(.fa-eye):not(.fa-eye-slash) {
            color: rgb(4 120 87);
        }
    </style>
</head>
<body>
<div class="auth-page">

    <!-- LEFT: Brand Panel -->
    <div class="auth-brand">
        <div class="auth-brand-logo">
            <img src="assets/image/Geo-auction-logo-clean.png" alt="Geo Auction" style="height: 48px; width: auto; object-fit: contain;" onerror="this.onerror=null;this.src='assets/image/Geo-auction.png'">
        </div>
        <h1 class="auth-brand-title">
            Join the<br><span>Auction</span> Revolution.
        </h1>
        <p class="auth-brand-sub">
            Create your free account and start buying or selling on live auctions instantly. No subscription fees — only pay when you win.
        </p>
        <div style="margin-top:44px;display:flex;flex-direction:column;gap:14px;max-width:320px;">
            <?php
            $perks = [
                ['fa-wallet','Free Wallet','Top up and pay securely from your balance'],
                ['fa-bell','Real-time Alerts','Get notified the moment you\'re outbid'],
                ['fa-shield-halved','Safe & Secure','All transactions are protected'],
            ];
            foreach ($perks as $p): ?>
            <div style="display:flex;align-items:flex-start;gap:14px;">
                <div style="width:40px;height:40px;border-radius:10px;background:rgba(4,120,87,0.2);display:flex;align-items:center;justify-content:center;color:rgb(4 120 87);font-size:16px;flex-shrink:0;">
                    <i class="fas <?php echo $p[0]; ?>"></i>
                </div>
                <div>
                    <div style="font-weight:700;color:#fff;font-size:14px;margin-bottom:2px;"><?php echo $p[1]; ?></div>
                    <div style="font-size:12px;color:rgba(255,255,255,0.5);"><?php echo $p[2]; ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- RIGHT: Form Panel -->
    <div class="auth-form-side">
        <a href="index.php" style="display:inline-flex;align-items:center;gap:6px;font-size:13px;color:var(--text-muted);margin-bottom:32px;">
            <i class="fas fa-arrow-left"></i> Back to Home
        </a>

        <div class="auth-form-header">
            <h1>Create your account</h1>
            <p>It's free and takes less than a minute</p>
        </div>

        <?php if (!empty($alert_msg)): ?>
        <div class="alert alert-<?php echo $alert_type === 'error' ? 'error' : 'success'; ?>">
            <i class="fas fa-<?php echo $alert_type === 'error' ? 'circle-xmark' : 'circle-check'; ?>"></i>
            <?php echo $alert_msg; ?>
        </div>
        <?php endif; ?>

        <form method="POST" action="register.php" id="registerForm">
            <div class="form-group">
                <label class="form-label" for="name">Full Name <span style="color:var(--red)">*</span></label>
                <div class="form-control-icon">
                    <i class="fas fa-user"></i>
                    <input type="text" id="name" name="name" class="form-control"
                        placeholder="John Doe"
                        value="<?php echo htmlspecialchars($form_data['name'] ?? ''); ?>"
                        required autocomplete="name">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="email">Email Address <span style="color:var(--red)">*</span></label>
                <div class="form-control-icon">
                    <i class="fas fa-envelope"></i>
                    <input type="email" id="email" name="email" class="form-control"
                        placeholder="you@example.com"
                        value="<?php echo htmlspecialchars($form_data['email'] ?? ''); ?>"
                        required autocomplete="email">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="phone">Phone Number</label>
                <div class="form-control-icon">
                    <i class="fas fa-phone"></i>
                    <input type="tel" id="phone" name="phone" class="form-control"
                        placeholder="+1 (555) 000-0000"
                        value="<?php echo htmlspecialchars($form_data['phone'] ?? ''); ?>"
                        autocomplete="tel">
                </div>
            </div>

            <!-- Role Selection Field (Buyer or Seller) -->
            <div class="form-group">
                <label class="form-label" for="role">I want to register as a <span style="color:var(--red)">*</span></label>
                <div class="form-control-icon">
                    <i class="fas fa-user-tag"></i>
                    <select id="role" name="role" class="form-control" required>
                        <option value="buyer" <?php echo (($form_data['role'] ?? '') === 'buyer') ? 'selected' : ''; ?>>Buyer (Bid on auctions)</option>
                        <option value="seller" <?php echo (($form_data['role'] ?? '') === 'seller') ? 'selected' : ''; ?>>Seller (List items for auction)</option>
                    </select>
                </div>
            </div>

            <!-- Gender Selection Field -->
            <div class="form-group">
                <label class="form-label" for="gender">Gender Identity</label>
                <div class="form-control-icon">
                    <i class="fas fa-venus-mars"></i>
                    <select id="gender" name="gender" class="form-control">
                        <option value="male" <?php echo (($form_data['gender'] ?? '') === 'male') ? 'selected' : ''; ?>>Male</option>
                        <option value="female" <?php echo (($form_data['gender'] ?? '') === 'female') ? 'selected' : ''; ?>>Female</option>
                        <option value="other" <?php echo (($form_data['gender'] ?? '') === 'other') ? 'selected' : ''; ?>>Choose another gender identity</option>
                    </select>
                </div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                <div class="form-group">
                    <label class="form-label" for="password">Password <span style="color:var(--red)">*</span></label>
                    <div class="form-control-icon password-container">
                        <i class="fas fa-lock"></i>
                        <input type="password" id="password" name="password" class="form-control"
                            placeholder="Min. 6 characters" required
                            oninput="checkStrength(this.value)" autocomplete="new-password" style="padding-right: 40px;">
                        <i class="fa-solid fa-eye" id="togglePassword"></i>
                    </div>
                    <div id="strengthBar" style="height:3px;border-radius:2px;background:var(--border);margin-top:6px;transition:0.3s;"></div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="confirm_password">Confirm Password <span style="color:var(--red)">*</span></label>
                    <div class="form-control-icon">
                        <i class="fas fa-lock"></i>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control"
                            placeholder="Repeat password" required autocomplete="new-password">
                    </div>
                </div>
            </div>

            <div style="display:flex;align-items:flex-start;gap:8px;margin-bottom:22px;">
                <input type="checkbox" id="terms" required style="width:16px;height:16px;margin-top:2px;accent-color:rgb(4 120 87);">
                <label for="terms" style="font-size:13px;color:var(--text-muted);cursor:pointer;line-height:1.5;">
                    I agree to the <a href="#" style="color:rgb(4 120 87);font-weight:600;">Terms of Service</a> and <a href="#" style="color:rgb(4 120 87);font-weight:600;">Privacy Policy</a>
                </label>
            </div>

            <button type="submit" class="btn btn-primary btn-block btn-lg" id="registerBtn">
                <i class="fas fa-user-plus"></i> Create Free Account
            </button>
        </form>

        <div class="auth-footer-link">
            Already have an account? <a href="login.php">Sign in →</a>
        </div>
    </div>
</div>

<script>
function checkStrength(val) {
    var bar = document.getElementById('strengthBar');
    if (!bar) return;
    var strength = 0;
    if (val.length >= 6)  strength++;
    if (val.length >= 10) strength++;
    if (/[A-Z]/.test(val)) strength++;
    if (/[0-9]/.test(val)) strength++;
    if (/[^A-Za-z0-9]/.test(val)) strength++;
    var colors = ['#e2001a','#e2001a','#f59e0b','#22c55e','rgb(4 120 87)'];
    var widths  = ['20%','40%','60%','80%','100%'];
    bar.style.background = colors[Math.max(0,strength-1)] || '#e2e8f0';
    bar.style.width      = widths[Math.max(0,strength-1)]  || '0%';
}

// Password visibility toggle script
const togglePassword = document.getElementById("togglePassword");
const passwordField = document.getElementById("password");

if (togglePassword && passwordField) {
    togglePassword.addEventListener("click", function () {
        const type = passwordField.type === "password" ? "text" : "password";
        passwordField.type = type;

        this.classList.toggle("fa-eye");
        this.classList.toggle("fa-eye-slash");
    });
}

document.getElementById('registerForm').addEventListener('submit', function(e) {
    var pwd  = document.getElementById('password').value;
    var conf = document.getElementById('confirm_password').value;
    if (pwd !== conf) {
        e.preventDefault();
        alert('Passwords do not match!');
        return;
    }
    var btn = document.getElementById('registerBtn');
    btn.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> Creating account...';
    btn.disabled  = true;
});
</script>
</body>
</html>