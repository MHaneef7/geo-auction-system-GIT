<?php
/**
 * User Account & Preference Settings - Geo Auction System
 * File: user/settings.php
 *
 * OOP PHP + PDO Architecture.
 * Sections: Profile, Account, Auction Preferences, Notifications, Privacy, Security.
 */

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/settings.php';

// Protect user settings
require_login('../login.php?redirect=user/settings.php');

$pdo = Database::getPdo();
Settings::init($pdo);

$user_id = (int)$_SESSION['user_id'];
$active_tab = $_GET['tab'] ?? 'profile';
$status_msg = '';
$status_type = 'success';

// -----------------------------------------------------------------------------
// Handle Form Submissions (Standard POST with CSRF & Flash Feedback)
// -----------------------------------------------------------------------------

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!Settings::verifyCsrf($token)) {
        $status_msg = 'Security token expired. Please try again.';
        $status_type = 'danger';
    } else {
        $action = trim((string)($_POST['action'] ?? ''));

        // 1. Profile Settings Update
        if ($action === 'update_profile') {
            $active_tab = 'profile';
            $name   = trim((string)($_POST['name'] ?? ''));
            $phone  = trim((string)($_POST['phone'] ?? ''));
            $gender = strtolower(trim((string)($_POST['gender'] ?? 'other')));
            $bio    = trim((string)($_POST['bio'] ?? ''));

            if ($name === '' || mb_strlen($name) < 2) {
                $status_msg = 'Name is required and must have at least 2 characters.';
                $status_type = 'danger';
            } else {
                if (!in_array($gender, ['male', 'female', 'other'], true)) {
                    $gender = 'other';
                }

                $avatarPath = null;
                if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
                    $fileTmp  = $_FILES['avatar']['tmp_name'];
                    $fileName = $_FILES['avatar']['name'];
                    $fileSize = $_FILES['avatar']['size'];
                    $ext      = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

                    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'jfif', 'pjpeg', 'avif'];
                    if (!in_array($ext, $allowed, true)) {
                        $status_msg = 'Invalid avatar format. Allowed: JPG, PNG, GIF, WEBP, JFIF.';
                        $status_type = 'danger';
                    } elseif ($fileSize > 8 * 1024 * 1024) {
                        $status_msg = 'Avatar image must not exceed 8MB.';
                        $status_type = 'danger';
                    } else {
                        $uploadDir = __DIR__ . '/../uploads/avatars/';
                        if (!is_dir($uploadDir)) {
                            mkdir($uploadDir, 0755, true);
                        }
                        $normalized_ext = match($ext) {
                            'jpeg', 'jfif', 'pjpeg' => 'jpg',
                            'png' => 'png',
                            'webp' => 'webp',
                            'gif' => 'gif',
                            'avif' => 'avif',
                            default => 'jpg'
                        };
                        $newFile = 'avatar_' . $user_id . '_' . time() . '.' . $normalized_ext;
                        $dest = $uploadDir . $newFile;
                        if (move_uploaded_file($fileTmp, $dest)) {
                            $avatarPath = 'uploads/avatars/' . $newFile;
                            $_SESSION['user_avatar'] = $avatarPath;
                        }
                    }
                }

                if (empty($status_msg)) {
                    if ($avatarPath !== null) {
                        $stmt = $pdo->prepare("UPDATE `users` SET `name` = :n, `phone` = :p, `gender` = :g, `bio` = :b, `avatar` = :av, `updated_at` = NOW() WHERE `id` = :uid");
                        $stmt->execute([':n' => $name, ':p' => $phone, ':g' => $gender, ':b' => $bio, ':av' => $avatarPath, ':uid' => $user_id]);
                    } else {
                        $stmt = $pdo->prepare("UPDATE `users` SET `name` = :n, `phone` = :p, `gender` = :g, `bio` = :b, `updated_at` = NOW() WHERE `id` = :uid");
                        $stmt->execute([':n' => $name, ':p' => $phone, ':g' => $gender, ':b' => $bio, ':uid' => $user_id]);
                    }

                    $_SESSION['user_name'] = $name;
                    $status_msg = 'Profile details saved successfully!';
                    $status_type = 'success';
                }
            }
        }

        // 2. Account Settings (Email & Phone Updates)
        elseif ($action === 'update_account_contacts') {
            $active_tab = 'account';
            $email = trim((string)($_POST['email'] ?? ''));
            $phone = trim((string)($_POST['phone'] ?? ''));

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $status_msg = 'Please enter a valid email address.';
                $status_type = 'danger';
            } else {
                // Ensure email uniqueness
                $chk = $pdo->prepare("SELECT `id` FROM `users` WHERE `email` = :em AND `id` != :uid LIMIT 1");
                $chk->execute([':em' => $email, ':uid' => $user_id]);
                if ($chk->fetch()) {
                    $status_msg = 'This email address is already associated with another account.';
                    $status_type = 'danger';
                } else {
                    $upStmt = $pdo->prepare("UPDATE `users` SET `email` = :em, `phone` = :ph, `updated_at` = NOW() WHERE `id` = :uid");
                    $upStmt->execute([':em' => $email, ':ph' => $phone, ':uid' => $user_id]);
                    $_SESSION['user_email'] = $email;
                    $status_msg = 'Account contact credentials updated successfully!';
                    $status_type = 'success';
                }
            }
        }

        // 3. Security (Password Change)
        elseif ($action === 'change_password') {
            $active_tab = 'security';
            $currPass = (string)($_POST['current_password'] ?? '');
            $newPass  = (string)($_POST['new_password'] ?? '');
            $confPass = (string)($_POST['confirm_password'] ?? '');

            if ($currPass === '' || $newPass === '' || $confPass === '') {
                $status_msg = 'All password fields are required.';
                $status_type = 'danger';
            } elseif ($newPass !== $confPass) {
                $status_msg = 'New password and confirmation do not match.';
                $status_type = 'danger';
            } elseif (mb_strlen($newPass) < 8) {
                $status_msg = 'New password must be at least 8 characters long.';
                $status_type = 'danger';
            } else {
                $stmt = $pdo->prepare("SELECT `password` FROM `users` WHERE `id` = :uid LIMIT 1");
                $stmt->execute([':uid' => $user_id]);
                $userRow = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$userRow || !password_verify($currPass, $userRow['password'])) {
                    $status_msg = 'Your current password is incorrect.';
                    $status_type = 'danger';
                } else {
                    $hash = password_hash($newPass, PASSWORD_DEFAULT);
                    $up = $pdo->prepare("UPDATE `users` SET `password` = :pw, `updated_at` = NOW() WHERE `id` = :uid");
                    $up->execute([':pw' => $hash, ':uid' => $user_id]);
                    $status_msg = 'Your password has been changed successfully!';
                    $status_type = 'success';
                }
            }
        }

        // 4. Auction Preferences
        elseif ($action === 'update_auction_preferences') {
            $active_tab = 'auction';
            $catId = (int)($_POST['default_category_id'] ?? 0);
            $bidConfirm = !empty($_POST['bid_confirmation']);
            $autoBidPref = !empty($_POST['auto_bid_preference']);

            $payload = [
                'auction_preferences' => [
                    'default_category_id' => $catId,
                    'bid_confirmation'    => $bidConfirm,
                    'auto_bid_preference' => $autoBidPref,
                ]
            ];
            Settings::saveUserSettings($user_id, $payload);
            $status_msg = 'Auction preferences updated successfully!';
            $status_type = 'success';
        }

        // 5. Notification Preferences
        elseif ($action === 'update_notifications') {
            $active_tab = 'notifications';
            $payload = [
                'notifications' => [
                    'bid_notifications'            => !empty($_POST['bid_notifications']),
                    'outbid_notifications'         => !empty($_POST['outbid_notifications']),
                    'auction_ending_notifications' => !empty($_POST['auction_ending_notifications']),
                    'winning_notifications'        => !empty($_POST['winning_notifications']),
                    'message_notifications'        => !empty($_POST['message_notifications']),
                ]
            ];
            Settings::saveUserSettings($user_id, $payload);
            $status_msg = 'Notification preferences updated successfully!';
            $status_type = 'success';
        }

        // 6. Privacy Preferences
        elseif ($action === 'update_privacy') {
            $active_tab = 'privacy';
            $vis = trim((string)($_POST['profile_visibility'] ?? 'public'));
            if (!in_array($vis, ['public', 'private', 'bidders_only'], true)) {
                $vis = 'public';
            }

            $payload = [
                'privacy' => [
                    'profile_visibility' => $vis,
                    'show_phone'         => !empty($_POST['show_phone']),
                    'show_email'         => !empty($_POST['show_email']),
                ]
            ];
            Settings::saveUserSettings($user_id, $payload);
            $status_msg = 'Privacy settings updated successfully!';
            $status_type = 'success';
        }
    }
}

// Fetch current user record
$userStmt = $pdo->prepare("SELECT * FROM `users` WHERE `id` = :uid LIMIT 1");
$userStmt->execute([':uid' => $user_id]);
$currentUser = $userStmt->fetch(PDO::FETCH_ASSOC);

if (!$currentUser) {
    header("Location: ../logout.php");
    exit();
}

$userPrefs = Settings::getUserSettings($user_id);
$csrfToken = Settings::csrfToken();

// Load categories for auction preference dropdown
$categories = [];
try {
    $catStmt = $pdo->query("SELECT `id`, `name` FROM `categories` ORDER BY `name` ASC");
    $categories = $catStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (\Throwable $e) {}

// Include Header
include_once __DIR__ . '/../includes/header.php';
?>

<!-- Bootstrap 5 CSS & Font Awesome 6 Icons -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

<style>
:root {
    --dash-primary: #047857;
    --dash-primary-dark: #065f46;
    --dash-surface: #ffffff;
    --dash-border: #e2e8f0;
}
.settings-page-wrapper {
    max-width: 1140px;
    margin: 0 auto;
    padding: 24px 16px 60px;
}
.card-user-settings {
    background: #ffffff;
    border: 1px solid var(--dash-border);
    border-radius: 16px;
    box-shadow: 0 1px 4px rgba(0,0,0,0.03);
    overflow: hidden;
}
.user-settings-nav .nav-link {
    color: #64748b;
    font-weight: 600;
    font-size: 13.5px;
    padding: 14px 18px;
    border: none;
    border-bottom: 3px solid transparent;
    display: flex;
    align-items: center;
    gap: 8px;
    background: transparent;
    transition: all 0.2s ease;
    white-space: nowrap;
}
.user-settings-nav .nav-link:hover {
    color: var(--dash-primary);
    background: rgba(4, 120, 87, 0.04);
}
.user-settings-nav .nav-link.active {
    color: var(--dash-primary);
    border-bottom-color: var(--dash-primary);
    background: #ffffff;
}
.settings-section-head {
    margin-bottom: 22px;
    padding-bottom: 12px;
    border-bottom: 1px solid #f1f5f9;
}
.settings-section-title {
    font-size: 15px;
    font-weight: 700;
    color: #0f172a;
    display: flex;
    align-items: center;
    gap: 8px;
}
.settings-section-desc {
    font-size: 12px;
    color: #64748b;
    margin-top: 3px;
}
.btn-save-settings {
    background: var(--dash-primary);
    border-color: var(--dash-primary);
    color: #ffffff;
    font-weight: 700;
    font-size: 13px;
    border-radius: 10px;
    padding: 9px 22px;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.2s ease;
}
.btn-save-settings:hover {
    background: var(--dash-primary-dark);
    border-color: var(--dash-primary-dark);
    color: #ffffff;
    box-shadow: 0 4px 12px rgba(4, 120, 87, 0.2);
}
.avatar-preview-box {
    width: 90px;
    height: 90px;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid #ecfdf5;
    box-shadow: 0 2px 8px rgba(0,0,0,0.06);
}
.security-metric-tile {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 16px;
}

/* ── Public Profile Details Modern Styling ── */
.profile-details-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    padding: 24px;
    box-shadow: 0 1px 4px rgba(0, 0, 0, 0.02);
    margin-bottom: 24px;
}
.settings-section-head.profile-head-enhanced {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 26px;
    padding-bottom: 16px;
    border-bottom: 1px solid #f1f5f9;
}
.profile-title-wrap {
    display: flex;
    align-items: center;
    gap: 14px;
}
.profile-icon-badge {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%);
    color: #047857;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    box-shadow: 0 2px 6px rgba(4, 120, 87, 0.12);
    border: 1px solid #a7f3d0;
}
.profile-head-enhanced .settings-section-title {
    font-size: 18px;
    font-weight: 800;
    color: #0f172a;
    letter-spacing: -0.02em;
    margin-bottom: 2px;
}
.profile-head-enhanced .settings-section-desc {
    font-size: 13px;
    color: #64748b;
    margin: 0;
}
.badge-public-pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 14px;
    border-radius: 9999px;
    font-size: 11.5px;
    font-weight: 700;
    background: #ecfdf5;
    color: #047857;
    border: 1px solid #a7f3d0;
    letter-spacing: 0.3px;
}
.avatar-upload-card {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    padding: 24px 16px;
    text-align: center;
    height: 100%;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    transition: all 0.25s ease;
}
.avatar-upload-card:hover {
    border-color: #cbd5e1;
    background: #f1f5f9;
}
.avatar-frame {
    position: relative;
    width: 112px;
    height: 112px;
    margin: 0 auto 16px;
}
.avatar-frame img {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    object-fit: cover;
    border: 4px solid #ffffff;
    box-shadow: 0 4px 14px rgba(0, 0, 0, 0.08);
    transition: transform 0.2s ease;
}
.avatar-frame:hover img {
    transform: scale(1.03);
}
.avatar-frame-badge {
    position: absolute;
    bottom: 2px;
    right: 2px;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: #047857;
    color: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 13px;
    border: 3px solid #ffffff;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.15);
    cursor: pointer;
    transition: all 0.2s ease;
}
.avatar-frame-badge:hover {
    background: #065f46;
    transform: scale(1.1);
}
.btn-avatar-pill {
    background: #ffffff;
    color: #0f172a;
    border: 1px solid #cbd5e1;
    font-weight: 600;
    font-size: 12.5px;
    padding: 7px 16px;
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all 0.2s ease;
    cursor: pointer;
    box-shadow: 0 1px 2px rgba(0,0,0,0.04);
}
.btn-avatar-pill:hover {
    background: #047857;
    color: #ffffff;
    border-color: #047857;
    box-shadow: 0 4px 12px rgba(4, 120, 87, 0.25);
    transform: translateY(-1px);
}
.avatar-help-text {
    font-size: 11px;
    color: #94a3b8;
    margin-top: 8px;
    line-height: 1.4;
}
.profile-input-group {
    margin-bottom: 2px;
}
.profile-input-group label {
    font-size: 12.5px;
    font-weight: 700;
    color: #334155;
    margin-bottom: 6px;
    display: flex;
    align-items: center;
    gap: 7px;
}
.profile-input-group label i {
    color: #047857;
    font-size: 12px;
}
.profile-input-control {
    border-radius: 10px;
    border: 1px solid #cbd5e1;
    padding: 10px 14px;
    font-size: 13.5px;
    color: #0f172a;
    background: #ffffff;
    transition: all 0.2s ease;
}
.profile-input-control:focus {
    border-color: #047857;
    box-shadow: 0 0 0 3px rgba(4, 120, 87, 0.15);
    outline: none;
}
.role-badge-box {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 9px 14px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.role-badge-text {
    font-size: 13px;
    font-weight: 700;
    color: #0f172a;
    display: flex;
    align-items: center;
    gap: 8px;
}
.role-verified-tag {
    font-size: 11px;
    font-weight: 700;
    padding: 3px 10px;
    border-radius: 9999px;
    background: #ecfdf5;
    color: #047857;
    border: 1px solid #a7f3d0;
}
.bio-counter-text {
    font-size: 11.5px;
    color: #94a3b8;
    text-align: right;
    margin-top: 4px;
}
.profile-footer-bar {
    margin-top: 24px;
    padding-top: 18px;
    border-top: 1px solid #f1f5f9;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 14px;
}
.profile-footer-hint {
    font-size: 12px;
    color: #64748b;
    display: flex;
    align-items: center;
    gap: 6px;
}
.btn-save-profile-enhanced {
    background: linear-gradient(135deg, #047857 0%, #065f46 100%);
    color: #ffffff;
    border: none;
    font-size: 13.5px;
    font-weight: 700;
    border-radius: 10px;
    padding: 10px 24px;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    box-shadow: 0 4px 14px rgba(4, 120, 87, 0.25);
    transition: all 0.2s ease;
    cursor: pointer;
}
.btn-save-profile-enhanced:hover {
    background: linear-gradient(135deg, #059669 0%, #047857 100%);
    box-shadow: 0 6px 18px rgba(4, 120, 87, 0.35);
    transform: translateY(-1px);
    color: #ffffff;
}
</style>

<div class="settings-page-wrapper">

    <!-- Header Breadcrumbs / Title -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <a href="dashboard.php" class="text-decoration-none small text-success fw-bold">
                    <i class="fa-solid fa-house me-1"></i> Dashboard
                </a>
                <span class="text-muted small">&bull;</span>
                <span class="text-muted small">Account Settings</span>
            </div>
            <h2 class="fw-bold mb-1" style="color: #0f172a;">User Settings &amp; Preferences</h2>
            <p class="text-muted mb-0 small">Manage your personal profile, bidding rules, real-time alert notifications, and security options.</p>
        </div>

        <div class="d-flex align-items-center gap-2">
            <a href="../profile.php" class="btn btn-outline-secondary btn-sm px-3 py-2 fw-semibold rounded-3 d-inline-flex align-items-center gap-2">
                <i class="fa-solid fa-user"></i> View Public Profile
            </a>
            <a href="dashboard.php" class="btn btn-outline-success btn-sm px-3 py-2 fw-semibold rounded-3 d-inline-flex align-items-center gap-2">
                <i class="fa-solid fa-house-laptop"></i> User Dashboard
            </a>
        </div>
    </div>

    <?php if (!empty($status_msg)): ?>
        <div class="alert alert-<?php echo $status_type; ?> alert-dismissible fade show d-flex align-items-center gap-2 shadow-sm rounded-3 mb-4" role="alert">
            <i class="fa-solid fa-<?php echo $status_type === 'success' ? 'circle-check text-success' : 'triangle-exclamation text-danger'; ?> fs-5"></i>
            <div class="small fw-semibold"><?php echo htmlspecialchars($status_msg); ?></div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Main Settings Card -->
    <div class="card-user-settings">
        <!-- Tabs Header -->
        <ul class="nav user-settings-nav border-bottom px-3 pt-2 bg-light flex-nowrap overflow-x-auto scrollbar-none" id="userSettingsTabs" role="tablist">
            <li class="nav-item">
                <button class="nav-link <?php echo $active_tab === 'profile' ? 'active' : ''; ?>" id="tab-profile" data-bs-toggle="tab" data-bs-target="#pane-profile" type="button">
                    <i class="fa-solid fa-user-pen"></i> Profile
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link <?php echo $active_tab === 'account' ? 'active' : ''; ?>" id="tab-account" data-bs-toggle="tab" data-bs-target="#pane-account" type="button">
                    <i class="fa-solid fa-address-card"></i> Account Contacts
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link <?php echo $active_tab === 'auction' ? 'active' : ''; ?>" id="tab-auction" data-bs-toggle="tab" data-bs-target="#pane-auction" type="button">
                    <i class="fa-solid fa-gavel"></i> Auction Preferences
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link <?php echo $active_tab === 'notifications' ? 'active' : ''; ?>" id="tab-notifications" data-bs-toggle="tab" data-bs-target="#pane-notifications" type="button">
                    <i class="fa-solid fa-bell"></i> Notifications
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link <?php echo $active_tab === 'privacy' ? 'active' : ''; ?>" id="tab-privacy" data-bs-toggle="tab" data-bs-target="#pane-privacy" type="button">
                    <i class="fa-solid fa-user-shield"></i> Privacy
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link <?php echo $active_tab === 'security' ? 'active' : ''; ?>" id="tab-security" data-bs-toggle="tab" data-bs-target="#pane-security" type="button">
                    <i class="fa-solid fa-lock"></i> Security &amp; Login
                </button>
            </li>
        </ul>

        <div class="tab-content p-4" id="userSettingsContent">

            <!-- ── 1. PROFILE SETTINGS TAB ── -->
            <div class="tab-pane fade <?php echo $active_tab === 'profile' ? 'show active' : ''; ?>" id="pane-profile" role="tabpanel">
                <form method="POST" action="settings.php?tab=profile" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="update_profile">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">

                    <div class="profile-details-card">
                        <!-- Enhanced Section Head -->
                        <div class="settings-section-head profile-head-enhanced">
                            <div class="profile-title-wrap">
                                <div class="profile-icon-badge">
                                    <i class="fa-solid fa-user-pen"></i>
                                </div>
                                <div>
                                    <div class="settings-section-title">Public Profile Details</div>
                                    <div class="settings-section-desc">Manage your displayed member name, avatar, bio, and gender.</div>
                                </div>
                            </div>
                            <span class="badge-public-pill">
                                <i class="fa-solid fa-globe"></i> Visible to Public
                            </span>
                        </div>

                        <div class="row g-4 align-items-stretch">
                            <!-- Left: Interactive Avatar Photo Manager -->
                            <div class="col-lg-3 col-md-4">
                                <div class="avatar-upload-card">
                                    <?php 
                                        $av = !empty($currentUser['avatar']) ? '../' . ltrim($currentUser['avatar'], '/') : 'https://ui-avatars.com/api/?name=' . urlencode($currentUser['name']) . '&background=047857&color=fff&size=200&bold=true';
                                    ?>
                                    <div class="avatar-frame">
                                        <img src="<?php echo htmlspecialchars($av); ?>" id="avatarImgPreview" alt="Profile Avatar" onerror="this.src='https://ui-avatars.com/api/?name=User&background=047857&color=fff&size=200';">
                                        <label for="avatarInput" class="avatar-frame-badge" title="Change Avatar Photo">
                                            <i class="fa-solid fa-camera"></i>
                                        </label>
                                    </div>
                                    
                                    <div class="w-100 px-2">
                                        <label for="avatarInput" class="btn-avatar-pill w-100 justify-content-center">
                                            <i class="fa-solid fa-cloud-arrow-up text-success"></i> Change Photo
                                        </label>
                                        <input type="file" name="avatar" id="avatarInput" accept="image/*,.jpg,.jpeg,.png,.webp,.jfif,.gif,.avif" class="d-none" onchange="previewAvatar(this)">
                                        
                                        <div id="avatarSelectedBadge" class="d-none text-success small fw-semibold mt-2 text-truncate" style="font-size: 11px;">
                                            <i class="fa-solid fa-circle-check"></i> <span id="avatarFileName"></span>
                                        </div>

                                        <p class="avatar-help-text mb-0">
                                            Formats: JPG, PNG, WEBP, JFIF<br>Max file size: 8MB
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <!-- Right: Clean Form Grid -->
                            <div class="col-lg-9 col-md-8">
                                <div class="row g-3">
                                    <!-- Full Name -->
                                    <div class="col-md-6 profile-input-group">
                                        <label class="form-label">
                                            <i class="fa-solid fa-user"></i> Full Display Name <span class="text-danger">*</span>
                                        </label>
                                        <input type="text" name="name" class="form-control profile-input-control" value="<?php echo htmlspecialchars($currentUser['name'] ?? ''); ?>" placeholder="e.g. John Doe" required>
                                    </div>

                                    <!-- Phone Number -->
                                    <div class="col-md-6 profile-input-group">
                                        <label class="form-label">
                                            <i class="fa-solid fa-phone"></i> Contact Phone Number
                                        </label>
                                        <input type="text" name="phone" class="form-control profile-input-control" value="<?php echo htmlspecialchars($currentUser['phone'] ?? ''); ?>" placeholder="+92 300 1234567">
                                    </div>

                                    <!-- Gender -->
                                    <div class="col-md-6 profile-input-group">
                                        <label class="form-label">
                                            <i class="fa-solid fa-venus-mars"></i> Gender
                                        </label>
                                        <select name="gender" class="form-select profile-input-control">
                                            <option value="male" <?php echo ($currentUser['gender'] ?? '') === 'male' ? 'selected' : ''; ?>>Male</option>
                                            <option value="female" <?php echo ($currentUser['gender'] ?? '') === 'female' ? 'selected' : ''; ?>>Female</option>
                                            <option value="other" <?php echo ($currentUser['gender'] ?? '') === 'other' ? 'selected' : ''; ?>>Other</option>
                                        </select>
                                    </div>

                                    <!-- Member Role (Styled Display) -->
                                    <div class="col-md-6 profile-input-group">
                                        <label class="form-label">
                                            <i class="fa-solid fa-shield-halved"></i> Member Account Role
                                        </label>
                                        <div class="role-badge-box">
                                            <div class="role-badge-text">
                                                <i class="fa-solid fa-certificate text-success"></i>
                                                <span><?php echo ucfirst(htmlspecialchars($currentUser['role'] ?? 'Buyer')); ?> Account</span>
                                            </div>
                                            <span class="role-verified-tag">
                                                <i class="fa-solid fa-check"></i> Verified
                                            </span>
                                        </div>
                                    </div>

                                    <!-- Bio / Description -->
                                    <div class="col-md-12 profile-input-group">
                                        <label class="form-label">
                                            <i class="fa-solid fa-pen-nib"></i> Public Bio / About You
                                        </label>
                                        <textarea name="bio" id="userBioInput" rows="3" class="form-control profile-input-control" placeholder="Share a few lines about yourself, trading interests, or collecting history..."><?php echo htmlspecialchars($currentUser['bio'] ?? ''); ?></textarea>
                                        <div class="bio-counter-text">
                                            <span id="userBioCounter"><?php echo mb_strlen($currentUser['bio'] ?? ''); ?></span> / 500 characters
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Action Footer -->
                        <div class="profile-footer-bar">
                            <div class="profile-footer-hint">
                                <i class="fa-solid fa-shield-check text-success"></i>
                                <span>Changes are instantly updated on your live auction bidding presence and seller profile.</span>
                            </div>
                            <button type="submit" class="btn-save-profile-enhanced">
                                <i class="fa-solid fa-floppy-disk"></i> Save Profile Details
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- ── 2. ACCOUNT SETTINGS TAB ── -->
            <div class="tab-pane fade <?php echo $active_tab === 'account' ? 'show active' : ''; ?>" id="pane-account" role="tabpanel">
                <form method="POST" action="settings.php?tab=account">
                    <input type="hidden" name="action" value="update_account_contacts">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">

                    <div class="settings-section-head">
                        <div class="settings-section-title"><i class="fa-solid fa-address-card text-success"></i> Primary Account Credentials</div>
                        <div class="settings-section-desc">Keep your primary login email and contact telephone up to date.</div>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Account Login Email <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($currentUser['email'] ?? ''); ?>" required>
                            <div class="form-text small">Used for receiving outbid alerts, winning receipts, and account authentication.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Contact Phone</label>
                            <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($currentUser['phone'] ?? ''); ?>">
                            <div class="form-text small">Used for courier delivery coordination and urgent lot notifications.</div>
                        </div>
                    </div>

                    <div class="border-top pt-3 d-flex justify-content-end">
                        <button type="submit" class="btn btn-save-settings">
                            <i class="fa-solid fa-floppy-disk"></i> Update Account Contacts
                        </button>
                    </div>
                </form>
            </div>

            <!-- ── 3. AUCTION PREFERENCES TAB ── -->
            <div class="tab-pane fade <?php echo $active_tab === 'auction' ? 'show active' : ''; ?>" id="pane-auction" role="tabpanel">
                <form method="POST" action="settings.php?tab=auction">
                    <input type="hidden" name="action" value="update_auction_preferences">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">

                    <div class="settings-section-head">
                        <div class="settings-section-title"><i class="fa-solid fa-gavel text-success"></i> Auction &amp; Bidding Preferences</div>
                        <div class="settings-section-desc">Tailor your live bidding terminal behavior and default categories.</div>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Default Auction Category</label>
                            <select name="default_category_id" class="form-select">
                                <option value="0">All Categories (Default)</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>" <?php echo ((int)($userPrefs['auction_preferences']['default_category_id'] ?? 0) === (int)$cat['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cat['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text small">Pre-selects this category when opening catalog filters.</div>
                        </div>

                        <div class="col-12"><hr class="my-2 text-muted"></div>

                        <div class="col-md-6">
                            <div class="form-check form-switch p-2 rounded bg-light border">
                                <input class="form-check-input ms-0 me-3" type="checkbox" name="bid_confirmation" value="1" id="bidConfirmSwitch" <?php echo !empty($userPrefs['auction_preferences']['bid_confirmation']) ? 'checked' : ''; ?>>
                                <label class="form-check-label fw-semibold small" for="bidConfirmSwitch">
                                    Ask for Confirmation Before Placing Bids
                                    <div class="text-muted fw-normal" style="font-size: 11px;">Prevents accidental quick bids on the live auction floor.</div>
                                </label>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-check form-switch p-2 rounded bg-light border">
                                <input class="form-check-input ms-0 me-3" type="checkbox" name="auto_bid_preference" value="1" id="autoBidPrefSwitch" <?php echo !empty($userPrefs['auction_preferences']['auto_bid_preference']) ? 'checked' : ''; ?>>
                                <label class="form-check-label fw-semibold small" for="autoBidPrefSwitch">
                                    Enable Auto-Bid Proxy Suggestions
                                    <div class="text-muted fw-normal" style="font-size: 11px;">Prompts automated proxy bidbot ceilings when opening new auction items.</div>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="border-top pt-3 d-flex justify-content-end">
                        <button type="submit" class="btn btn-save-settings">
                            <i class="fa-solid fa-floppy-disk"></i> Save Auction Preferences
                        </button>
                    </div>
                </form>
            </div>

            <!-- ── 4. NOTIFICATION SETTINGS TAB ── -->
            <div class="tab-pane fade <?php echo $active_tab === 'notifications' ? 'show active' : ''; ?>" id="pane-notifications" role="tabpanel">
                <form method="POST" action="settings.php?tab=notifications">
                    <input type="hidden" name="action" value="update_notifications">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">

                    <div class="settings-section-head">
                        <div class="settings-section-title"><i class="fa-solid fa-bell text-success"></i> Notification Channels &amp; Triggers</div>
                        <div class="settings-section-desc">Select which auction events generate real-time alerts.</div>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <div class="form-check form-switch p-2 rounded bg-light border">
                                <input class="form-check-input ms-0 me-3" type="checkbox" name="bid_notifications" value="1" id="userBidNotif" <?php echo !empty($userPrefs['notifications']['bid_notifications']) ? 'checked' : ''; ?>>
                                <label class="form-check-label fw-semibold small" for="userBidNotif">
                                    New Bid Received Alerts
                                    <div class="text-muted fw-normal" style="font-size: 11px;">Notify me when offers are placed on items I listed.</div>
                                </label>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-check form-switch p-2 rounded bg-light border">
                                <input class="form-check-input ms-0 me-3" type="checkbox" name="outbid_notifications" value="1" id="userOutbidNotif" <?php echo !empty($userPrefs['notifications']['outbid_notifications']) ? 'checked' : ''; ?>>
                                <label class="form-check-label fw-semibold small" for="userOutbidNotif">
                                    Outbid Alert Notifications
                                    <div class="text-muted fw-normal" style="font-size: 11px;">Immediately notify me when another bidder places a higher offer.</div>
                                </label>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-check form-switch p-2 rounded bg-light border">
                                <input class="form-check-input ms-0 me-3" type="checkbox" name="auction_ending_notifications" value="1" id="userEndingNotif" <?php echo !empty($userPrefs['notifications']['auction_ending_notifications']) ? 'checked' : ''; ?>>
                                <label class="form-check-label fw-semibold small" for="userEndingNotif">
                                    Auction Ending-Soon Reminders
                                    <div class="text-muted fw-normal" style="font-size: 11px;">Alert me when watched lots are within 30 minutes of close.</div>
                                </label>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-check form-switch p-2 rounded bg-light border">
                                <input class="form-check-input ms-0 me-3" type="checkbox" name="winning_notifications" value="1" id="userWinNotif" <?php echo !empty($userPrefs['notifications']['winning_notifications']) ? 'checked' : ''; ?>>
                                <label class="form-check-label fw-semibold small" for="userWinNotif">
                                    Auction Won Confirmation
                                    <div class="text-muted fw-normal" style="font-size: 11px;">Notify me when I win an auction with checkout instructions.</div>
                                </label>
                            </div>
                        </div>

                        <div class="col-md-12">
                            <div class="form-check form-switch p-2 rounded bg-light border">
                                <input class="form-check-input ms-0 me-3" type="checkbox" name="message_notifications" value="1" id="userMsgNotif" <?php echo !empty($userPrefs['notifications']['message_notifications']) ? 'checked' : ''; ?>>
                                <label class="form-check-label fw-semibold small" for="userMsgNotif">
                                    Direct Message &amp; Seller Inquiry Alerts
                                    <div class="text-muted fw-normal" style="font-size: 11px;">Notify me when buyers or sellers message me regarding lot inquiries.</div>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="border-top pt-3 d-flex justify-content-end">
                        <button type="submit" class="btn btn-save-settings">
                            <i class="fa-solid fa-floppy-disk"></i> Save Notification Settings
                        </button>
                    </div>
                </form>
            </div>

            <!-- ── 5. PRIVACY SETTINGS TAB ── -->
            <div class="tab-pane fade <?php echo $active_tab === 'privacy' ? 'show active' : ''; ?>" id="pane-privacy" role="tabpanel">
                <form method="POST" action="settings.php?tab=privacy">
                    <input type="hidden" name="action" value="update_privacy">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">

                    <div class="settings-section-head">
                        <div class="settings-section-title"><i class="fa-solid fa-user-shield text-success"></i> Privacy &amp; Public Visibility</div>
                        <div class="settings-section-desc">Control what contact information and activity appears on your public profile.</div>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Public Profile Visibility</label>
                            <select name="profile_visibility" class="form-select">
                                <option value="public" <?php echo ($userPrefs['privacy']['profile_visibility'] ?? '') === 'public' ? 'selected' : ''; ?>>Public (Visible to everyone)</option>
                                <option value="bidders_only" <?php echo ($userPrefs['privacy']['profile_visibility'] ?? '') === 'bidders_only' ? 'selected' : ''; ?>>Bidders Only (Active lot participants)</option>
                                <option value="private" <?php echo ($userPrefs['privacy']['profile_visibility'] ?? '') === 'private' ? 'selected' : ''; ?>>Private (Hidden from public directory)</option>
                            </select>
                        </div>

                        <div class="col-12"><hr class="my-2 text-muted"></div>

                        <div class="col-md-6">
                            <div class="form-check form-switch p-2 rounded bg-light border">
                                <input class="form-check-input ms-0 me-3" type="checkbox" name="show_phone" value="1" id="showPhoneSwitch" <?php echo !empty($userPrefs['privacy']['show_phone']) ? 'checked' : ''; ?>>
                                <label class="form-check-label fw-semibold small" for="showPhoneSwitch">
                                    Display Phone Number on Public Profile
                                    <div class="text-muted fw-normal" style="font-size: 11px;">Allow interested bidders to view your phone for lot verification.</div>
                                </label>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-check form-switch p-2 rounded bg-light border">
                                <input class="form-check-input ms-0 me-3" type="checkbox" name="show_email" value="1" id="showEmailSwitch" <?php echo !empty($userPrefs['privacy']['show_email']) ? 'checked' : ''; ?>>
                                <label class="form-check-label fw-semibold small" for="showEmailSwitch">
                                    Display Email Address on Public Profile
                                    <div class="text-muted fw-normal" style="font-size: 11px;">Permit other verified members to see your contact email.</div>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="border-top pt-3 d-flex justify-content-end">
                        <button type="submit" class="btn btn-save-settings">
                            <i class="fa-solid fa-floppy-disk"></i> Save Privacy Settings
                        </button>
                    </div>
                </form>
            </div>

            <!-- ── 6. SECURITY & PASSWORD TAB ── -->
            <div class="tab-pane fade <?php echo $active_tab === 'security' ? 'show active' : ''; ?>" id="pane-security" role="tabpanel">
                <form method="POST" action="settings.php?tab=security">
                    <input type="hidden" name="action" value="change_password">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">

                    <div class="settings-section-head">
                        <div class="settings-section-title"><i class="fa-solid fa-lock text-success"></i> Change Password &amp; Session Security</div>
                        <div class="settings-section-desc">Keep your account secure with robust passwords and monitored logins.</div>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Current Password <span class="text-danger">*</span></label>
                            <input type="password" name="current_password" class="form-control" required autocomplete="current-password">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">New Password <span class="text-danger">*</span></label>
                            <input type="password" name="new_password" class="form-control" required autocomplete="new-password">
                            <div class="form-text small">Minimum 8 characters.</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Confirm New Password <span class="text-danger">*</span></label>
                            <input type="password" name="confirm_password" class="form-control" required autocomplete="new-password">
                        </div>
                    </div>

                    <div class="d-flex justify-content-end mb-4">
                        <button type="submit" class="btn btn-save-settings">
                            <i class="fa-solid fa-key"></i> Update Password
                        </button>
                    </div>
                </form>

                <!-- Last Login Audit Information -->
                <div class="border-top pt-4">
                    <div class="settings-section-head">
                        <div class="settings-section-title"><i class="fa-solid fa-clock-rotate-left text-success"></i> Recent Activity &amp; Session Controls</div>
                        <div class="settings-section-desc">Audit your recent sign-in timestamps and current active login IP.</div>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <div class="security-metric-tile">
                                <span class="text-muted small d-block">Last Successful Login</span>
                                <strong class="fs-6 text-dark">
                                    <?php echo !empty($currentUser['last_login_at']) ? date('M d, Y - h:i A', strtotime($currentUser['last_login_at'])) : 'Active current session'; ?>
                                </strong>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="security-metric-tile">
                                <span class="text-muted small d-block">Last Login IP Address</span>
                                <strong class="fs-6 text-dark font-monospace">
                                    <?php echo htmlspecialchars($currentUser['last_login_ip'] ?? ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1')); ?>
                                </strong>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 pt-2">
                        <span class="text-muted small">Need to terminate all sessions or sign out?</span>
                        <a href="../logout.php" class="btn btn-outline-danger btn-sm px-3 fw-bold rounded-3">
                            <i class="fa-solid fa-right-from-bracket me-1"></i> Sign Out from Account
                        </a>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function previewAvatar(input) {
        if (input.files && input.files[0]) {
            var file = input.files[0];
            var reader = new FileReader();
            reader.onload = function(e) {
                var preview = document.getElementById('avatarImgPreview');
                if (preview) preview.src = e.target.result;
            }
            reader.readAsDataURL(file);

            var badge = document.getElementById('avatarSelectedBadge');
            var nameSpan = document.getElementById('avatarFileName');
            if (badge && nameSpan) {
                nameSpan.textContent = file.name + ' (' + (file.size / 1024).toFixed(1) + ' KB)';
                badge.classList.remove('d-none');
            }
        }
    }

    // Dynamic Character Counter for Bio
    document.addEventListener("DOMContentLoaded", function() {
        var bio = document.getElementById("userBioInput");
        var counter = document.getElementById("userBioCounter");
        if (bio && counter) {
            bio.addEventListener("input", function() {
                counter.textContent = this.value.length;
            });
        }
    });

    // Sync active tab to URL
    document.querySelectorAll('#userSettingsTabs button[data-bs-toggle="tab"]').forEach(function(btn) {
        btn.addEventListener('shown.bs.tab', function(e) {
            var tabName = e.target.getAttribute('data-bs-target').replace('#pane-', '');
            if (history.replaceState) {
                var newUrl = window.location.protocol + "//" + window.location.host + window.location.pathname + '?tab=' + tabName;
                window.history.replaceState({ path: newUrl }, '', newUrl);
            }
        });
    });
</script>

<?php
if (file_exists(__DIR__ . '/../includes/footer.php')) {
    include_once __DIR__ . '/../includes/footer.php';
} else {
    echo '</body></html>';
}
?>
