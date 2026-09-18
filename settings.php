<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include_once __DIR__ . ('includes/database.php');

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

 $user_id = (int)$_SESSION['user_id'];
 $toasts = [];

if (isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    $name   = mysqli_real_escape_string($conn, trim($_POST['name'] ?? ''));
    $phone  = mysqli_real_escape_string($conn, trim($_POST['phone'] ?? ''));
    $gender = mysqli_real_escape_string($conn, trim($_POST['gender'] ?? ''));
    $bio    = mysqli_real_escape_string($conn, trim($_POST['bio'] ?? ''));

    if (!empty($name)) {
        $avatar_update = "";
        $newFileName = "";

        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $fileTmpPath  = $_FILES['avatar']['tmp_name'];
            $fileName     = $_FILES['avatar']['name'];
            $fileSize     = $_FILES['avatar']['size'];
            $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

            $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $maxSize = 2 * 1024 * 1024; // 2MB

            if (!in_array($fileExtension, $allowedExtensions)) {
                $toasts[] = ['type' => 'error', 'msg' => 'Invalid file type. Allowed: JPG, PNG, GIF, WebP'];
            } elseif ($fileSize > $maxSize) {
                $toasts[] = ['type' => 'error', 'msg' => 'File too large. Maximum 2MB allowed.'];
            } else {
                $newFileName = md5(time() . $fileName) . '.' . $fileExtension;
                $uploadFileDir = 'uploads/';
                if (!is_dir($uploadFileDir)) {
                    mkdir($uploadFileDir, 0755, true);
                }
                $dest_path = $uploadFileDir . $newFileName;
                if (move_uploaded_file($fileTmpPath, $dest_path)) {
                    // Delete old avatar
                    if (!empty($_SESSION['user_avatar'])) {
                        $oldFile = $uploadFileDir . $_SESSION['user_avatar'];
                        if (file_exists($oldFile)) unlink($oldFile);
                    }
                    $avatar_update = ", `avatar` = '" . mysqli_real_escape_string($conn, $newFileName) . "'";
                } else {
                    $toasts[] = ['type' => 'error', 'msg' => 'Failed to move uploaded file.'];
                }
            }
        }

        $sql = "UPDATE `users` SET `name` = '$name', `phone` = '$phone', `gender` = '$gender', `bio` = '$bio'" . $avatar_update . " WHERE `id` = $user_id";

        if (mysqli_query($conn, $sql)) {
            $_SESSION['user_name'] = $name;
            if (!empty($newFileName)) {
                $_SESSION['user_avatar'] = $newFileName;
            }
            $toasts[] = ['type' => 'success', 'msg' => 'Profile updated successfully!'];
        } else {
            $toasts[] = ['type' => 'error', 'msg' => 'Database error: ' . mysqli_error($conn)];
        }
    } else {
        $toasts[] = ['type' => 'error', 'msg' => 'Name is required.'];
    }
}

if (isset($_POST['action']) && $_POST['action'] === 'change_password') {
    $current      = $_POST['current_password'] ?? '';
    $new_pass     = $_POST['new_password'] ?? '';
    $confirm_pass = $_POST['confirm_password'] ?? '';

    $res = mysqli_query($conn, "SELECT `password` FROM `users` WHERE `id` = $user_id LIMIT 1");
    $row = mysqli_fetch_assoc($res);
    $hashed = $row['password'] ?? '';

    if (empty($current) || empty($new_pass) || empty($confirm_pass)) {
        $toasts[] = ['type' => 'error', 'msg' => 'All password fields are required.'];
    } elseif (!password_verify($current, $hashed)) {
        $toasts[] = ['type' => 'error', 'msg' => 'Current password is incorrect.'];
    } elseif (strlen($new_pass) < 8) {
        $toasts[] = ['type' => 'error', 'msg' => 'New password must be at least 8 characters.'];
    } elseif ($new_pass !== $confirm_pass) {
        $toasts[] = ['type' => 'error', 'msg' => 'New passwords do not match.'];
    } else {
        $new_hashed = password_hash($new_pass, PASSWORD_DEFAULT);
        if (mysqli_query($conn, "UPDATE `users` SET `password` = '$new_hashed' WHERE `id` = $user_id")) {
            $toasts[] = ['type' => 'success', 'msg' => 'Password changed successfully!'];
        } else {
            $toasts[] = ['type' => 'error', 'msg' => 'Failed to update password.'];
        }
    }
}

// ─── Handle Notification Preferences ───
if (isset($_POST['action']) && $_POST['action'] === 'update_notifications') {
    $email_bids      = isset($_POST['email_bids']) ? 1 : 0;
    $email_outbid    = isset($_POST['email_outbid']) ? 1 : 0;
    $email_won       = isset($_POST['email_won']) ? 1 : 0;
    $email_newsletter = isset($_POST['email_newsletter']) ? 1 : 0;
    $sms_bids        = isset($_POST['sms_bids']) ? 1 : 0;

    // Check if notification row exists
    $check = mysqli_query($conn, "SELECT `id` FROM `user_notifications` WHERE `user_id` = $user_id LIMIT 1");
    if (mysqli_num_rows($check) > 0) {
        $sql = "UPDATE `user_notifications` SET  `email_bids` = $email_bids, `email_outbid` = $email_outbid, 
            `email_won` = $email_won, `email_newsletter` = $email_newsletter,
            `sms_bids` = $sms_bids WHERE `user_id` = $user_id";
    } else {
        $sql = "INSERT INTO `user_notifications` 
            (`user_id`, `email_bids`, `email_outbid`, `email_won`, `email_newsletter`, `sms_bids`) 
            VALUES ($user_id, $email_bids, $email_outbid, $email_won, $email_newsletter, $sms_bids)";
    }

    if (mysqli_query($conn, $sql)) {
        $toasts[] = ['type' => 'success', 'msg' => 'Notification preferences saved!'];
    } else {
        $toasts[] = ['type' => 'error', 'msg' => 'Failed to save preferences.'];
    }
}

// ─── Handle Account Deletion ───
if (isset($_POST['action']) && $_POST['action'] === 'delete_account') {
    $confirm_text = trim($_POST['delete_confirm'] ?? '');
    if ($confirm_text === 'DELETE') {
        // Delete avatar file
        if (!empty($_SESSION['user_avatar'])) {
            $oldFile = 'uploads/' . $_SESSION['user_avatar'];
            if (file_exists($oldFile)) unlink($oldFile);
        }
        mysqli_query($conn, "DELETE FROM `user_notifications` WHERE `user_id` = $user_id");
        mysqli_query($conn, "DELETE FROM `users` WHERE `id` = $user_id");
        session_destroy();
        header("Location: index.php?msg=account_deleted");
        exit();
    } else {
        $toasts[] = ['type' => 'error', 'msg' => 'Please type DELETE to confirm.'];
    }
}

// ─── Fetch User Data ───
 $user_res = mysqli_query($conn, "SELECT `id`, `name`, `email`, `phone`, `role`, `avatar`, `bio`, `status`, `gender`, `created_at` FROM `users` WHERE `id` = $user_id LIMIT 1");
 $user = ($user_res && mysqli_num_rows($user_res) > 0) ? mysqli_fetch_assoc($user_res) : ['name' => '', 'email' => '', 'phone' => '', 'avatar' => '', 'bio' => '', 'gender' => '', 'created_at' => ''];

// ─── Fetch Notification Preferences ───
 $notif_res = mysqli_query($conn, "SELECT * FROM `user_notifications` WHERE `user_id` = $user_id LIMIT 1");
 $notif = ($notif_res && mysqli_num_rows($notif_res) > 0) ? mysqli_fetch_assoc($notif_res) : [
    'email_bids' => 1, 'email_outbid' => 1, 'email_won' => 1, 
    'email_newsletter' => 0, 'sms_bids' => 0
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Geo Auction</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --bg-primary: #0a0a0f;
            --bg-secondary: #12121a;
            --bg-card: #16161f;
            --bg-input: #1c1c28;
            --bg-hover: #1e1e2a;
            --border: #2a2a3a;
            --border-focus: #22c55e;
            --text-primary: #f0f0f5;
            --text-secondary: #8888a0;
            --text-muted: #55556a;
            --accent: #22c55e;
            --accent-hover: #16a34a;
            --accent-soft: rgba(34, 197, 94, 0.1);
            --accent-glow: rgba(34, 197, 94, 0.25);
            --danger: #ef4444;
            --danger-hover: #dc2626;
            --danger-soft: rgba(239, 68, 68, 0.1);
            --warning: #f59e0b;
            --warning-soft: rgba(245, 158, 11, 0.1);
            --radius: 12px;
            --radius-sm: 8px;
            --radius-xs: 6px;
            --shadow: 0 4px 24px rgba(0,0,0,0.3);
            --transition: 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            min-height: 100vh;
            line-height: 1.6;
        }

        /* ── Layout ── */
        .settings-layout {
            display: flex;
            min-height: 100vh;
        }

        /* ── Sidebar ── */
        .sidebar {
            width: 280px;
            background: var(--bg-secondary);
            border-right: 1px solid var(--border);
            padding: 32px 0;
            display: flex;
            flex-direction: column;
            position: sticky;
            top: 0;
            height: 100vh;
            overflow-y: auto;
            flex-shrink: 0;
        }

        .sidebar-header {
            padding: 0 24px 28px;
            border-bottom: 1px solid var(--border);
            margin-bottom: 8px;
        }

        .sidebar-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            color: var(--text-primary);
        }

        .sidebar-brand .logo-icon {
            width: 36px;
            height: 36px;
            background: var(--accent);
            border-radius: var(--radius-sm);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 16px;
            color: #000;
        }

        .sidebar-brand span {
            font-size: 17px;
            font-weight: 600;
            letter-spacing: -0.02em;
        }

        .sidebar-label {
            padding: 20px 24px 8px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--text-muted);
        }

        .sidebar-nav {
            list-style: none;
            padding: 0 12px;
        }

        .sidebar-nav li a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 11px 16px;
            border-radius: var(--radius-sm);
            color: var(--text-secondary);
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: all var(--transition);
            cursor: pointer;
            position: relative;
        }

        .sidebar-nav li a:hover {
            background: var(--bg-hover);
            color: var(--text-primary);
        }

        .sidebar-nav li a.active {
            background: var(--accent-soft);
            color: var(--accent);
        }

        .sidebar-nav li a.active::before {
            content: '';
            position: absolute;
            left: 0;
            top: 50%;
            transform: translateY(-50%);
            width: 3px;
            height: 20px;
            background: var(--accent);
            border-radius: 0 3px 3px 0;
        }

        .sidebar-nav li a .material-symbols-rounded {
            font-size: 20px;
        }

        .sidebar-nav li a.danger-link:hover {
            background: var(--danger-soft);
            color: var(--danger);
        }

        .sidebar-footer {
            margin-top: auto;
            padding: 16px 24px;
            border-top: 1px solid var(--border);
        }

        .sidebar-footer a {
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--text-muted);
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            transition: color var(--transition);
        }

        .sidebar-footer a:hover {
            color: var(--text-primary);
        }

        .sidebar-footer a .material-symbols-rounded {
            font-size: 18px;
        }

        /* ── Main Content ── */
        .main-content {
            flex: 1;
            padding: 40px 48px 60px;
            max-width: 780px;
            overflow-y: auto;
        }

        .page-header {
            margin-bottom: 36px;
            animation: fadeInUp 0.5s ease-out;
        }

        .page-header h1 {
            font-size: 28px;
            font-weight: 700;
            letter-spacing: -0.03em;
            margin-bottom: 6px;
        }

        .page-header p {
            color: var(--text-secondary);
            font-size: 14px;
        }

        /* ── Section ── */
        .settings-section {
            display: none;
            animation: fadeInUp 0.4s ease-out;
        }

        .settings-section.active {
            display: block;
        }

        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(12px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .section-block {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 28px;
            margin-bottom: 24px;
            transition: border-color var(--transition);
        }

        .section-block:hover {
            border-color: #3a3a4a;
        }

        .section-title {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 4px;
            letter-spacing: -0.01em;
        }

        .section-desc {
            font-size: 13px;
            color: var(--text-secondary);
            margin-bottom: 24px;
            line-height: 1.5;
        }

        .section-divider {
            height: 1px;
            background: var(--border);
            margin: 24px 0;
        }

        /* ── Avatar Section ── */
        .avatar-section {
            display: flex;
            align-items: center;
            gap: 24px;
            margin-bottom: 28px;
        }

        .avatar-wrapper {
            position: relative;
            width: 88px;
            height: 88px;
            flex-shrink: 0;
        }

        .avatar-img {
            width: 88px;
            height: 88px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--border);
            background: var(--bg-input);
        }

        .avatar-placeholder {
            width: 88px;
            height: 88px;
            border-radius: 50%;
            background: var(--bg-input);
            border: 2px dashed var(--border);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-muted);
        }

        .avatar-placeholder .material-symbols-rounded {
            font-size: 32px;
        }

        .avatar-info h4 {
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 4px;
        }

        .avatar-info p {
            font-size: 12px;
            color: var(--text-secondary);
            margin-bottom: 10px;
        }

        .avatar-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 14px;
            background: var(--bg-input);
            border: 1px solid var(--border);
            border-radius: var(--radius-xs);
            color: var(--text-primary);
            font-size: 12px;
            font-weight: 500;
            cursor: pointer;
            transition: all var(--transition);
            font-family: inherit;
        }

        .avatar-btn:hover {
            border-color: var(--accent);
            color: var(--accent);
        }

        .avatar-btn .material-symbols-rounded {
            font-size: 16px;
        }

        #avatarInput { display: none; }

        /* ── Form Elements ── */
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .form-grid .full-width {
            grid-column: 1 / -1;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .form-group label {
            font-size: 13px;
            font-weight: 500;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .form-group label .required {
            color: var(--danger);
            font-size: 14px;
        }

        input[type="text"],
        input[type="email"],
        input[type="password"],
        select,
        textarea {
            width: 100%;
            padding: 10px 14px;
            background: var(--bg-input);
            border: 1px solid var(--border);
            border-radius: var(--radius-xs);
            color: var(--text-primary);
            font-size: 14px;
            font-family: inherit;
            outline: none;
            transition: all var(--transition);
        }

        textarea {
            resize: vertical;
            min-height: 90px;
            line-height: 1.5;
        }

        input:focus, select:focus, textarea:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px var(--accent-soft);
        }

        input:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        select {
            cursor: pointer;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%238888a0' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
            padding-right: 36px;
        }

        .input-hint {
            font-size: 11px;
            color: var(--text-muted);
            margin-top: 2px;
        }

        /* ── Password Strength ── */
        .password-strength {
            display: flex;
            gap: 4px;
            margin-top: 6px;
        }

        .strength-bar {
            flex: 1;
            height: 3px;
            border-radius: 2px;
            background: var(--border);
            transition: background 0.3s;
        }

        .strength-bar.weak { background: var(--danger); }
        .strength-bar.medium { background: var(--warning); }
        .strength-bar.strong { background: var(--accent); }

        .strength-text {
            font-size: 11px;
            margin-top: 4px;
            font-weight: 500;
        }

        .strength-text.weak { color: var(--danger); }
        .strength-text.medium { color: var(--warning); }
        .strength-text.strong { color: var(--accent); }

        /* ── Toggle Switch ── */
        .toggle-group {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 14px 0;
            border-bottom: 1px solid var(--border);
        }

        .toggle-group:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }

        .toggle-group:first-child {
            padding-top: 0;
        }

        .toggle-label h4 {
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 2px;
        }

        .toggle-label p {
            font-size: 12px;
            color: var(--text-muted);
        }

        .toggle-switch {
            position: relative;
            width: 44px;
            height: 24px;
            flex-shrink: 0;
        }

        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
            position: absolute;
        }

        .toggle-slider {
            position: absolute;
            cursor: pointer;
            inset: 0;
            background: var(--border);
            border-radius: 24px;
            transition: all var(--transition);
        }

        .toggle-slider::before {
            content: '';
            position: absolute;
            width: 18px;
            height: 18px;
            left: 3px;
            bottom: 3px;
            background: var(--text-primary);
            border-radius: 50%;
            transition: all var(--transition);
        }

        .toggle-switch input:checked + .toggle-slider {
            background: var(--accent);
        }

        .toggle-switch input:checked + .toggle-slider::before {
            transform: translateX(20px);
        }

        .toggle-switch input:focus-visible + .toggle-slider {
            box-shadow: 0 0 0 3px var(--accent-soft);
        }

        /* ── Buttons ── */
        .btn-row {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 12px;
            margin-top: 24px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 10px 24px;
            border-radius: var(--radius-xs);
            font-size: 14px;
            font-weight: 500;
            font-family: inherit;
            cursor: pointer;
            border: none;
            transition: all var(--transition);
            text-decoration: none;
        }

        .btn .material-symbols-rounded {
            font-size: 18px;
        }

        .btn-primary {
            background: var(--accent);
            color: #000;
        }

        .btn-primary:hover {
            background: var(--accent-hover);
            box-shadow: 0 4px 16px var(--accent-glow);
            transform: translateY(-1px);
        }

        .btn-primary:active {
            transform: translateY(0);
        }

        .btn-ghost {
            background: transparent;
            color: var(--text-secondary);
            border: 1px solid var(--border);
        }

        .btn-ghost:hover {
            background: var(--bg-hover);
            color: var(--text-primary);
            border-color: #3a3a4a;
        }

        .btn-danger {
            background: var(--danger-soft);
            color: var(--danger);
            border: 1px solid rgba(239, 68, 68, 0.2);
        }

        .btn-danger:hover {
            background: var(--danger);
            color: #fff;
            border-color: var(--danger);
            box-shadow: 0 4px 16px rgba(239, 68, 68, 0.25);
            transform: translateY(-1px);
        }

        /* ── Danger Zone ── */
        .danger-zone {
            border-color: rgba(239, 68, 68, 0.2);
        }

        .danger-zone:hover {
            border-color: rgba(239, 68, 68, 0.35);
        }

        .danger-zone .section-title {
            color: var(--danger);
        }

        .delete-confirm {
            margin-top: 16px;
        }

        .delete-confirm input[type="text"] {
            border-color: rgba(239, 68, 68, 0.3);
        }

        .delete-confirm input[type="text"]:focus {
            border-color: var(--danger);
            box-shadow: 0 0 0 3px var(--danger-soft);
        }

        /* ── Info Row ── */
        .info-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid var(--border);
        }

        .info-row:last-child { border-bottom: none; }

        .info-row .info-label {
            font-size: 13px;
            color: var(--text-secondary);
        }

        .info-row .info-value {
            font-size: 13px;
            font-weight: 500;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }

        .status-badge.active {
            background: var(--accent-soft);
            color: var(--accent);
        }

        .status-badge .dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: currentColor;
            animation: pulse-dot 2s infinite;
        }

        @keyframes pulse-dot {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.4; }
        }

        /* ── Toast Notifications ── */
        .toast-container {
            position: fixed;
            top: 24px;
            right: 24px;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            gap: 10px;
            pointer-events: none;
        }

        .toast {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 14px 20px;
            border-radius: var(--radius-sm);
            font-size: 13px;
            font-weight: 500;
            pointer-events: all;
            animation: toastIn 0.4s ease-out;
            box-shadow: 0 8px 32px rgba(0,0,0,0.4);
            max-width: 380px;
            backdrop-filter: blur(12px);
        }

        .toast.success {
            background: rgba(34, 197, 94, 0.15);
            border: 1px solid rgba(34, 197, 94, 0.3);
            color: #4ade80;
        }

        .toast.error {
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #f87171;
        }

        .toast .material-symbols-rounded {
            font-size: 20px;
            flex-shrink: 0;
        }

        .toast.removing {
            animation: toastOut 0.3s ease-in forwards;
        }

        @keyframes toastIn {
            from { opacity: 0; transform: translateX(40px) scale(0.95); }
            to { opacity: 1; transform: translateX(0) scale(1); }
        }

        @keyframes toastOut {
            to { opacity: 0; transform: translateX(40px) scale(0.95); }
        }

        /* ── Modal ── */
        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.6);
            backdrop-filter: blur(4px);
            z-index: 8000;
            align-items: center;
            justify-content: center;
            animation: fadeIn 0.2s;
        }

        .modal-overlay.show {
            display: flex;
        }

        .modal {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 28px;
            max-width: 440px;
            width: 90%;
            animation: modalIn 0.3s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes modalIn {
            from { opacity: 0; transform: scale(0.95) translateY(10px); }
            to { opacity: 1; transform: scale(1) translateY(0); }
        }

        .modal h3 {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .modal p {
            font-size: 13px;
            color: var(--text-secondary);
            margin-bottom: 20px;
            line-height: 1.5;
        }

        .modal .btn-row {
            margin-top: 20px;
        }

        /* ── Responsive ── */
        @media (max-width: 900px) {
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
                border-right: none;
                border-bottom: 1px solid var(--border);
                padding: 20px 0;
            }

            .sidebar-header { padding: 0 20px 16px; }
            .sidebar-label { padding: 12px 20px 6px; }
            .sidebar-nav { padding: 0 8px; }
            .sidebar-footer { padding: 12px 20px; }

            .sidebar-nav li a {
                padding: 9px 14px;
                font-size: 13px;
            }

            .sidebar-nav li a .nav-text-full { display: none; }
            .sidebar-nav li a .nav-text-short { display: inline; }

            .settings-layout {
                flex-direction: column;
            }

            .main-content {
                padding: 28px 20px 48px;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .avatar-section {
                flex-direction: column;
                text-align: center;
            }

            .btn-row {
                flex-direction: column;
            }

            .btn-row .btn {
                width: 100%;
            }
        }

        @media (min-width: 901px) {
            .sidebar-nav li a .nav-text-short { display: none; }
        }

        /* ── Scrollbar ── */
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: var(--border); border-radius: 3px; }
        ::-webkit-scrollbar-thumb:hover { background: #3a3a4a; }
    </style>
</head>
<body>

<!-- Toast Container -->
<div class="toast-container" id="toastContainer">
    <?php foreach ($toasts as $t): ?>
    <div class="toast <?= htmlspecialchars($t['type']) ?>">
        <span class="material-symbols-rounded"><?= $t['type'] === 'success' ? 'check_circle' : 'error' ?></span>
        <span><?= htmlspecialchars($t['msg']) ?></span>
    </div>
    <?php endforeach; ?>
</div>

<!-- Delete Account Modal -->
<div class="modal-overlay" id="deleteModal">
    <div class="modal">
        <h3 style="color: var(--danger);">Delete Account</h3>
        <p>This action is permanent and cannot be undone. All your data, bids, and listings will be permanently removed.</p>
        <form method="POST" action="settings.php">
            <input type="hidden" name="action" value="delete_account">
            <div class="form-group delete-confirm">
                <label>Type <strong>DELETE</strong> to confirm</label>
                <input type="text" name="delete_confirm" placeholder="DELETE" autocomplete="off">
            </div>
            <div class="btn-row">
                <button type="button" class="btn btn-ghost" onclick="closeDeleteModal()">Cancel</button>
                <button type="submit" class="btn btn-danger">
                    <span class="material-symbols-rounded">delete_forever</span>
                    Delete Account
                </button>
            </div>
        </form>
    </div>
</div>

<div class="settings-layout">
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <a href="dashboard.php" class="sidebar-brand">
                <div class="logo-icon">G</div>
                <span>Geo Auction</span>
            </a>
        </div>

        <div class="sidebar-label">Settings</div>
        <ul class="sidebar-nav">
            <li>
                <a href="#" class="active" data-section="profile" onclick="switchSection('profile', this)">
                    <span class="material-symbols-rounded">person</span>
                    <span class="nav-text-full">Profile</span>
                    <span class="nav-text-short">Profile</span>
                </a>
            </li>
            <li>
                <a href="#" data-section="security" onclick="switchSection('security', this)">
                    <span class="material-symbols-rounded">lock</span>
                    <span class="nav-text-full">Security</span>
                    <span class="nav-text-short">Security</span>
                </a>
            </li>
            <li>
                <a href="#" data-section="notifications" onclick="switchSection('notifications', this)">
                    <span class="material-symbols-rounded">notifications</span>
                    <span class="nav-text-full">Notifications</span>
                    <span class="nav-text-short">Notifs</span>
                </a>
            </li>
            <li>
                <a href="#" data-section="account" onclick="switchSection('account', this)">
                    <span class="material-symbols-rounded">info</span>
                    <span class="nav-text-full">Account Info</span>
                    <span class="nav-text-short">Info</span>
                </a>
            </li>
            <li>
                <a href="#" class="danger-link" onclick="openDeleteModal(); return false;">
                    <span class="material-symbols-rounded">delete</span>
                    <span class="nav-text-full">Delete Account</span>
                    <span class="nav-text-short">Delete</span>
                </a>
            </li>
        </ul>

        <div class="sidebar-footer">
            <a href="dashboard.php">
                <span class="material-symbols-rounded">arrow_back</span>
                Back to Dashboard
            </a>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <div class="page-header">
            <h1>Settings</h1>
            <p>Manage your account preferences and settings</p>
        </div>

        <!-- ═══ Profile Section ═══ -->
        <div class="settings-section active" id="section-profile">
            <form method="POST" action="settings.php" enctype="multipart/form-data" id="profileForm">
                <input type="hidden" name="action" value="update_profile">

                <div class="section-block">
                    <div class="section-title">Profile Photo</div>
                    <div class="section-desc">Upload a new avatar. JPG, PNG, GIF, or WebP. Max 2MB.</div>

                    <div class="avatar-section">
                        <div class="avatar-wrapper">
                            <?php if (!empty($user['avatar'])): ?>
                                <img src="uploads/<?= htmlspecialchars($user['avatar']) ?>" alt="Avatar" class="avatar-img" id="avatarPreview">
                            <?php else: ?>
                                <div class="avatar-placeholder" id="avatarPlaceholder">
                                    <span class="material-symbols-rounded">person</span>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="avatar-info">
                            <h4>Profile Picture</h4>
                            <p>Recommended: 200×200px, square crop</p>
                            <button type="button" class="avatar-btn" onclick="document.getElementById('avatarInput').click()">
                                <span class="material-symbols-rounded">upload</span>
                                Upload Photo
                            </button>
                            <input type="file" name="avatar" id="avatarInput" accept="image/jpeg,image/png,image/gif,image/webp" onchange="previewAvatar(this)">
                        </div>
                    </div>

                    <div class="section-divider"></div>

                    <div class="section-title">Personal Information</div>
                    <div class="section-desc">Update your personal details here.</div>

                    <div class="form-grid" style="margin-top: 4px;">
                        <div class="form-group">
                            <label>Full Name <span class="required">*</span></label>
                            <input type="text" name="name" value="<?= htmlspecialchars($user['name'] ?? '') ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Email Address</label>
                            <input type="email" value="<?= htmlspecialchars($user['email'] ?? '') ?>" disabled>
                            <span class="input-hint">Email cannot be changed</span>
                        </div>
                        <div class="form-group">
                            <label>Phone Number</label>
                            <input type="text" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" placeholder="+1 (555) 000-0000">
                        </div>
                        <div class="form-group">
                            <label>Gender</label>
                            <select name="gender">
                                <option value="">Prefer not to say</option>
                                <option value="Male" <?= (($user['gender'] ?? '') === 'Male') ? 'selected' : '' ?>>Male</option>
                                <option value="Female" <?= (($user['gender'] ?? '') === 'Female') ? 'selected' : '' ?>>Female</option>
                                <option value="Other" <?= (($user['gender'] ?? '') === 'Other') ? 'selected' : '' ?>>Other</option>
                            </select>
                        </div>
                        <div class="form-group full-width">
                            <label>Bio</label>
                            <textarea name="bio" placeholder="Tell us about yourself..." maxlength="300"><?= htmlspecialchars($user['bio'] ?? '') ?></textarea>
                            <span class="input-hint">Maximum 300 characters</span>
                        </div>
                    </div>

                    <div class="btn-row">
                        <button type="submit" class="btn btn-primary">
                            <span class="material-symbols-rounded">save</span>
                            Save Changes
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- ═══ Security Section ═══ -->
        <div class="settings-section" id="section-security">
            <div class="section-block">
                <div class="section-title">Change Password</div>
                <div class="section-desc">Ensure your account is using a strong, unique password.</div>

                <form method="POST" action="settings.php" id="passwordForm">
                    <input type="hidden" name="action" value="change_password">

                    <div class="form-grid">
                        <div class="form-group full-width">
                            <label>Current Password <span class="required">*</span></label>
                            <input type="password" name="current_password" required placeholder="Enter current password" id="currentPass">
                        </div>
                        <div class="form-group">
                            <label>New Password <span class="required">*</span></label>
                            <input type="password" name="new_password" required placeholder="Min 8 characters" id="newPass" oninput="checkStrength(this.value)">
                            <div class="password-strength" id="strengthBars">
                                <div class="strength-bar" id="bar1"></div>
                                <div class="strength-bar" id="bar2"></div>
                                <div class="strength-bar" id="bar3"></div>
                                <div class="strength-bar" id="bar4"></div>
                            </div>
                            <span class="strength-text" id="strengthText"></span>
                        </div>
                        <div class="form-group">
                            <label>Confirm New Password <span class="required">*</span></label>
                            <input type="password" name="confirm_password" required placeholder="Repeat new password" id="confirmPass">
                        </div>
                    </div>

                    <div class="btn-row">
                        <button type="submit" class="btn btn-primary">
                            <span class="material-symbols-rounded">lock_reset</span>
                            Update Password
                        </button>
                    </div>
                </form>
            </div>

            <div class="section-block">
                <div class="section-title">Security Tips</div>
                <div class="section-desc">Follow these best practices to keep your account secure.</div>
                <ul style="list-style: none; display: flex; flex-direction: column; gap: 12px;">
                    <li style="display:flex; align-items:flex-start; gap:10px; font-size:13px; color:var(--text-secondary);">
                        <span class="material-symbols-rounded" style="color:var(--accent); font-size:18px; margin-top:1px;">shield</span>
                        Use a unique password you don't use on other sites
                    </li>
                    <li style="display:flex; align-items:flex-start; gap:10px; font-size:13px; color:var(--text-secondary);">
                        <span class="material-symbols-rounded" style="color:var(--accent); font-size:18px; margin-top:1px;">shield</span>
                        Enable two-factor authentication if available
                    </li>
                    <li style="display:flex; align-items:flex-start; gap:10px; font-size:13px; color:var(--text-secondary);">
                        <span class="material-symbols-rounded" style="color:var(--accent); font-size:18px; margin-top:1px;">shield</span>
                        Never share your password with anyone
                    </li>
                    <li style="display:flex; align-items:flex-start; gap:10px; font-size:13px; color:var(--text-secondary);">
                        <span class="material-symbols-rounded" style="color:var(--accent); font-size:18px; margin-top:1px;">shield</span>
                        Change your password periodically
                    </li>
                </ul>
            </div>
        </div>

        <!-- ═══ Notifications Section ═══ -->
        <div class="settings-section" id="section-notifications">
            <form method="POST" action="settings.php">
                <input type="hidden" name="action" value="update_notifications">

                <div class="section-block">
                    <div class="section-title">Email Notifications</div>
                    <div class="section-desc">Choose which emails you'd like to receive.</div>

                    <div class="toggle-group">
                        <div class="toggle-label">
                            <h4>Bid Activity</h4>
                            <p>Get notified when someone bids on your items</p>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" name="email_bids" value="1" <?= ($notif['email_bids'] ?? 1) ? 'checked' : '' ?>>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>

                    <div class="toggle-group">
                        <div class="toggle-label">
                            <h4>Outbid Alerts</h4>
                            <p>Get notified when you've been outbid on an auction</p>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" name="email_outbid" value="1" <?= ($notif['email_outbid'] ?? 1) ? 'checked' : '' ?>>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>

                    <div class="toggle-group">
                        <div class="toggle-label">
                            <h4>Auction Won</h4>
                            <p>Get notified when you win an auction</p>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" name="email_won" value="1" <?= ($notif['email_won'] ?? 1) ? 'checked' : '' ?>>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>

                    <div class="toggle-group">
                        <div class="toggle-label">
                            <h4>Newsletter</h4>
                            <p>Receive updates about new features and promotions</p>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" name="email_newsletter" value="1" <?= ($notif['email_newsletter'] ?? 0) ? 'checked' : '' ?>>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                </div>

                <div class="section-block">
                    <div class="section-title">SMS Notifications</div>
                    <div class="section-desc">Get critical alerts via text message.</div>

                    <div class="toggle-group">
                        <div class="toggle-label">
                            <h4>SMS Bid Alerts</h4>
                            <p>Receive a text when you're outbid (requires phone number)</p>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" name="sms_bids" value="1" <?= ($notif['sms_bids'] ?? 0) ? 'checked' : '' ?>>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>

                    <div class="btn-row">
                        <button type="submit" class="btn btn-primary">
                            <span class="material-symbols-rounded">save</span>
                            Save Preferences
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- ═══ Account Info Section ═══ -->
        <div class="settings-section" id="section-account">
            <div class="section-block">
                <div class="section-title">Account Details</div>
                <div class="section-desc">Read-only information about your account.</div>

                <div class="info-row">
                    <span class="info-label">Account ID</span>
                    <span class="info-value">#<?= str_pad($user['id'] ?? 0, 5, '0', STR_PAD_LEFT) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Name</span>
                    <span class="info-value"><?= htmlspecialchars($user['name'] ?? '—') ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Email</span>
                    <span class="info-value"><?= htmlspecialchars($user['email'] ?? '—') ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Role</span>
                    <span class="info-value" style="text-transform: capitalize;"><?= htmlspecialchars($user['role'] ?? 'User') ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Status</span>
                    <span class="status-badge active">
                        <span class="dot"></span>
                        <?= ucfirst($user['status'] ?? 'Active') ?>
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label">Member Since</span>
                    <span class="info-value"><?= !empty($user['created_at']) ? date('M d, Y', strtotime($user['created_at'])) : '—' ?></span>
                </div>
            </div>

            <div class="section-block">
                <div class="section-title">Data & Privacy</div>
                <div class="section-desc">Information about how we handle your data.</div>
                <ul style="list-style: none; display: flex; flex-direction: column; gap: 12px;">
                    <li style="display:flex; align-items:flex-start; gap:10px; font-size:13px; color:var(--text-secondary);">
                        <span class="material-symbols-rounded" style="color:var(--text-muted); font-size:18px; margin-top:1px;">policy</span>
                        Your personal data is encrypted and stored securely
                    </li>
                    <li style="display:flex; align-items:flex-start; gap:10px; font-size:13px; color:var(--text-secondary);">
                        <span class="material-symbols-rounded" style="color:var(--text-muted); font-size:18px; margin-top:1px;">policy</span>
                        We never share your information with third parties
                    </li>
                    <li style="display:flex; align-items:flex-start; gap:10px; font-size:13px; color:var(--text-secondary);">
                        <span class="material-symbols-rounded" style="color:var(--text-muted); font-size:18px; margin-top:1px;">policy</span>
                        You can request full data export or deletion at any time
                    </li>
                </ul>
            </div>

            <div class="section-block danger-zone">
                <div class="section-title">Danger Zone</div>
                <div class="section-desc">Permanently delete your account and all associated data. This cannot be undone.</div>
                <button type="button" class="btn btn-danger" onclick="openDeleteModal()">
                    <span class="material-symbols-rounded">delete_forever</span>
                    Delete My Account
                </button>
            </div>
        </div>
    </main>
</div>

<script>
// ── Section Switching ──
function switchSection(sectionId, linkEl) {
    document.querySelectorAll('.settings-section').forEach(s => s.classList.remove('active'));
    document.querySelectorAll('.sidebar-nav a[data-section]').forEach(a => a.classList.remove('active'));

    const section = document.getElementById('section-' + sectionId);
    if (section) {
        section.classList.remove('active');
        // Force reflow for re-triggering animation
        void section.offsetWidth;
        section.classList.add('active');
    }

    if (linkEl) linkEl.classList.add('active');
}

// ── Avatar Preview ──
function previewAvatar(input) {
    if (!input.files || !input.files[0]) return;

    const file = input.files[0];
    if (file.size > 2 * 1024 * 1024) {
        showToast('error', 'File too large. Maximum 2MB allowed.');
        input.value = '';
        return;
    }

    const reader = new FileReader();
    reader.onload = function(e) {
        let img = document.getElementById('avatarPreview');
        const placeholder = document.getElementById('avatarPlaceholder');

        if (!img) {
            img = document.createElement('img');
            img.id = 'avatarPreview';
            img.className = 'avatar-img';
            if (placeholder) placeholder.replaceWith(img);
        }

        img.src = e.target.result;
        img.style.display = 'block';
    };
    reader.readAsDataURL(file);
}

// ── Password Strength Checker ──
function checkStrength(password) {
    const bars = [
        document.getElementById('bar1'),
        document.getElementById('bar2'),
        document.getElementById('bar3'),
        document.getElementById('bar4')
    ];
    const textEl = document.getElementById('strengthText');

    // Reset
    bars.forEach(b => b.className = 'strength-bar');
    if (textEl) { textEl.textContent = ''; textEl.className = 'strength-text'; }

    if (!password) return;

    let score = 0;
    if (password.length >= 8) score++;
    if (/[a-z]/.test(password) && /[A-Z]/.test(password)) score++;
    if (/\d/.test(password)) score++;
    if (/[^a-zA-Z0-9]/.test(password)) score++;

    const levels = [
        { cls: 'weak', text: 'Weak' },
        { cls: 'medium', text: 'Fair' },
        { cls: 'medium', text: 'Good' },
        { cls: 'strong', text: 'Strong' }
    ];

    const level = levels[Math.max(0, score - 1)] || levels[0];

    for (let i = 0; i < score && i < 4; i++) {
        bars[i].classList.add(level.cls);
    }

    if (textEl) {
        textEl.textContent = level.text;
        textEl.classList.add(level.cls);
    }
}

// ── Delete Modal ──
function openDeleteModal() {
    document.getElementById('deleteModal').classList.add('show');
    document.body.style.overflow = 'hidden';
}

function closeDeleteModal() {
    document.getElementById('deleteModal').classList.remove('show');
    document.body.style.overflow = '';
}

// Close modal on overlay click
document.getElementById('deleteModal').addEventListener('click', function(e) {
    if (e.target === this) closeDeleteModal();
});

// Close modal on Escape
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeDeleteModal();
});

// ── Toast System ──
function showToast(type, message) {
    const container = document.getElementById('toastContainer');
    const toast = document.createElement('div');
    toast.className = 'toast ' + type;
    toast.innerHTML = `
        <span class="material-symbols-rounded">${type === 'success' ? 'check_circle' : 'error'}</span>
        <span>${message}</span>
    `;
    container.appendChild(toast);

    setTimeout(() => {
        toast.classList.add('removing');
        setTimeout(() => toast.remove(), 300);
    }, 4000);
}

// ── Auto-dismiss PHP toasts ──
document.querySelectorAll('.toast-container > .toast').forEach(toast => {
    setTimeout(() => {
        toast.classList.add('removing');
        setTimeout(() => toast.remove(), 300);
    }, 4000);
});

// ── Bio Character Counter ──
const bioTextarea = document.querySelector('textarea[name="bio"]');
if (bioTextarea) {
    const hint = bioTextarea.parentElement.querySelector('.input-hint');
    bioTextarea.addEventListener('input', function() {
        const remaining = 300 - this.value.length;
        if (hint) {
            hint.textContent = `${remaining} characters remaining`;
            hint.style.color = remaining < 30 ? 'var(--danger)' : 'var(--text-muted)';
        }
    });
    // Init
    bioTextarea.dispatchEvent(new Event('input'));
}

// ── Password Form Validation ──
const passwordForm = document.getElementById('passwordForm');
if (passwordForm) {
    passwordForm.addEventListener('submit', function(e) {
        const newPass = document.getElementById('newPass').value;
        const confirmPass = document.getElementById('confirmPass').value;

        if (newPass !== confirmPass) {
            e.preventDefault();
            showToast('error', 'New passwords do not match.');
            return;
        }

        if (newPass.length < 8) {
            e.preventDefault();
            showToast('error', 'Password must be at least 8 characters.');
            return;
        }
    });
}
</script>

</body>
</html>