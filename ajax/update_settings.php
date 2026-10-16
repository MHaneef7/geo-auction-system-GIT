<?php
/**
 * Settings Update AJAX Handler - Geo Auction System
 * File: ajax/update_settings.php
 *
 * OOP PHP + PDO Architecture with strict CSRF and role authentication.
 */

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/settings.php';

// Method check
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
    exit();
}

// 1. Verify CSRF
$csrf = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!Settings::verifyCsrf($csrf)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid or expired CSRF security token. Please refresh the page and try again.']);
    exit();
}

$action = trim((string)($_POST['action'] ?? ''));

try {
    $pdo = Database::getPdo();

    // =========================================================================
    // 1. ADMIN SETTINGS ACTIONS
    // =========================================================================
    if (strpos($action, 'admin_') === 0) {
        if (!is_admin()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Unauthorized: Administrator privileges required.']);
            exit();
        }

        // Action: Admin Global / Group Settings Update
        if ($action === 'admin_save_group') {
            $group = trim((string)($_POST['group'] ?? 'general'));
            $allowedGroups = ['general', 'auction', 'users', 'notifications', 'security', 'payment'];
            if (!in_array($group, $allowedGroups, true)) {
                echo json_encode(['success' => false, 'message' => 'Invalid settings group specified.']);
                exit();
            }

            $settingsData = $_POST['settings'] ?? [];
            if (!is_array($settingsData) || empty($settingsData)) {
                echo json_encode(['success' => false, 'message' => 'No settings data supplied for update.']);
                exit();
            }

            // Input Validation per group
            if ($group === 'general') {
                if (isset($settingsData['site_name']) && mb_strlen(trim($settingsData['site_name'])) < 2) {
                    echo json_encode(['success' => false, 'message' => 'Website Name must have at least 2 characters.']);
                    exit();
                }
                if (isset($settingsData['site_email']) && !filter_var(trim($settingsData['site_email']), FILTER_VALIDATE_EMAIL)) {
                    echo json_encode(['success' => false, 'message' => 'Please provide a valid website email address.']);
                    exit();
                }
            } elseif ($group === 'auction') {
                if (isset($settingsData['min_bid_increment']) && (float)$settingsData['min_bid_increment'] <= 0) {
                    echo json_encode(['success' => false, 'message' => 'Minimum bid increment must be greater than zero.']);
                    exit();
                }
                if (isset($settingsData['auto_extend_minutes']) && (int)$settingsData['auto_extend_minutes'] < 1) {
                    echo json_encode(['success' => false, 'message' => 'Auto-extend duration must be at least 1 minute.']);
                    exit();
                }
            } elseif ($group === 'payment') {
                if (isset($settingsData['min_wallet_deposit']) && (float)$settingsData['min_wallet_deposit'] < 0) {
                    echo json_encode(['success' => false, 'message' => 'Minimum deposit cannot be negative.']);
                    exit();
                }
            }

            // Save to settings table
            $saved = Settings::saveMultiple($settingsData, $group);
            if ($saved) {
                echo json_encode([
                    'success' => true,
                    'message' => ucfirst($group) . ' settings saved and updated successfully!'
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to save settings into database.']);
            }
            exit();
        }

        echo json_encode(['success' => false, 'message' => 'Unknown admin action.']);
        exit();
    }

    // =========================================================================
    // 2. USER SETTINGS ACTIONS
    // =========================================================================
    if (!is_logged_in()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Please sign in to update settings.']);
        exit();
    }

    $userId = (int)($_SESSION['user_id'] ?? $_SESSION['id'] ?? 0);
    if ($userId <= 0) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Invalid user session.']);
        exit();
    }

    // Action: Update User Profile Settings (Name, Phone, Bio, Gender, Avatar)
    if ($action === 'user_update_profile') {
        $name = trim((string)($_POST['name'] ?? ''));
        $phone = trim((string)($_POST['phone'] ?? ''));
        $gender = strtolower(trim((string)($_POST['gender'] ?? 'other')));
        $bio = trim((string)($_POST['bio'] ?? ''));

        if ($name === '' || mb_strlen($name) < 2) {
            echo json_encode(['success' => false, 'message' => 'Name is required and must have at least 2 characters.']);
            exit();
        }

        if (!in_array($gender, ['male', 'female', 'other'], true)) {
            $gender = 'other';
        }

        $avatarFileName = null;
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $fileTmp = $_FILES['avatar']['tmp_name'];
            $fileName = $_FILES['avatar']['name'];
            $fileSize = $_FILES['avatar']['size'];
            $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'jfif', 'pjpeg', 'avif'];
            if (!in_array($ext, $allowed, true)) {
                echo json_encode(['success' => false, 'message' => 'Invalid image format. Allowed: JPG, PNG, GIF, WEBP, JFIF.']);
                exit();
            }
            if ($fileSize > 8 * 1024 * 1024) {
                echo json_encode(['success' => false, 'message' => 'Avatar image must not exceed 8MB.']);
                exit();
            }

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
            $avatarFileName = 'avatar_' . $userId . '_' . time() . '.' . $normalized_ext;
            $dest = $uploadDir . $avatarFileName;

            if (!move_uploaded_file($fileTmp, $dest)) {
                echo json_encode(['success' => false, 'message' => 'Failed to save avatar image.']);
                exit();
            }
        }

        // Update users table via prepared statement
        if ($avatarFileName !== null) {
            $stmt = $pdo->prepare("UPDATE `users` SET `name` = :n, `phone` = :p, `gender` = :g, `bio` = :b, `avatar` = :av, `updated_at` = NOW() WHERE `id` = :uid");
            $saved = $stmt->execute([
                ':n'  => $name,
                ':p'  => $phone,
                ':g'  => $gender,
                ':b'  => $bio,
                ':av' => 'uploads/avatars/' . $avatarFileName,
                ':uid'=> $userId,
            ]);
            $_SESSION['user_avatar'] = 'uploads/avatars/' . $avatarFileName;
        } else {
            $stmt = $pdo->prepare("UPDATE `users` SET `name` = :n, `phone` = :p, `gender` = :g, `bio` = :b, `updated_at` = NOW() WHERE `id` = :uid");
            $saved = $stmt->execute([
                ':n'  => $name,
                ':p'  => $phone,
                ':g'  => $gender,
                ':b'  => $bio,
                ':uid'=> $userId,
            ]);
        }

        $_SESSION['user_name'] = $name;

        echo json_encode([
            'success' => true,
            'message' => 'Profile settings updated successfully!',
            'avatar_url' => $avatarFileName ? ('uploads/avatars/' . $avatarFileName) : null
        ]);
        exit();
    }

    // Action: Update User Account Contacts (Email & Phone)
    if ($action === 'user_update_account' || $action === 'update_account_contacts') {
        $email = trim((string)($_POST['email'] ?? ''));
        $phone = trim((string)($_POST['phone'] ?? ''));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'message' => 'Please provide a valid email address.']);
            exit();
        }

        $chk = $pdo->prepare("SELECT `id` FROM `users` WHERE `email` = :em AND `id` != :uid LIMIT 1");
        $chk->execute([':em' => $email, ':uid' => $userId]);
        if ($chk->fetch()) {
            echo json_encode(['success' => false, 'message' => 'This email address is already in use by another account.']);
            exit();
        }

        $upStmt = $pdo->prepare("UPDATE `users` SET `email` = :em, `phone` = :ph, `updated_at` = NOW() WHERE `id` = :uid");
        $upStmt->execute([':em' => $email, ':ph' => $phone, ':uid' => $userId]);
        $_SESSION['user_email'] = $email;
        $_SESSION['user_phone'] = $phone;

        echo json_encode(['success' => true, 'message' => 'Account contacts updated successfully!']);
        exit();
    }

    // Action: Change User Password
    if ($action === 'user_change_password') {
        $currPassword = (string)($_POST['current_password'] ?? '');
        $newPassword  = (string)($_POST['new_password'] ?? '');
        $confPassword = (string)($_POST['confirm_password'] ?? '');

        if ($currPassword === '' || $newPassword === '' || $confPassword === '') {
            echo json_encode(['success' => false, 'message' => 'Please fill in all password fields.']);
            exit();
        }

        if ($newPassword !== $confPassword) {
            echo json_encode(['success' => false, 'message' => 'New password and confirmation do not match.']);
            exit();
        }

        $minLen = (int)Settings::get('password_min_length', 8);
        if (mb_strlen($newPassword) < $minLen) {
            echo json_encode(['success' => false, 'message' => "Password must be at least {$minLen} characters in length."]);
            exit();
        }

        // Fetch current password hash
        $stmt = $pdo->prepare("SELECT `password` FROM `users` WHERE `id` = :uid LIMIT 1");
        $stmt->execute([':uid' => $userId]);
        $userRow = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$userRow || !password_verify($currPassword, $userRow['password'])) {
            echo json_encode(['success' => false, 'message' => 'Your current password is incorrect.']);
            exit();
        }

        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $upStmt = $pdo->prepare("UPDATE `users` SET `password` = :pw, `updated_at` = NOW() WHERE `id` = :uid");
        $upStmt->execute([':pw' => $newHash, ':uid' => $userId]);

        echo json_encode(['success' => true, 'message' => 'Your password has been changed successfully!']);
        exit();
    }

    // Action: Update User Preferences / Notifications / Privacy
    if ($action === 'user_save_preferences') {
        $section = trim((string)($_POST['section'] ?? ''));
        $data = $_POST['data'] ?? [];

        if (!is_array($data)) {
            echo json_encode(['success' => false, 'message' => 'Invalid preference data.']);
            exit();
        }

        $allowedSections = ['auction_preferences', 'notifications', 'privacy'];
        if (!in_array($section, $allowedSections, true)) {
            echo json_encode(['success' => false, 'message' => 'Invalid preference section.']);
            exit();
        }

        // Structure payload
        $payload = [$section => $data];
        $saved = Settings::saveUserSettings($userId, $payload);

        if ($saved) {
            echo json_encode([
                'success' => true,
                'message' => ucwords(str_replace('_', ' ', $section)) . ' updated successfully!'
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to save preferences.']);
        }
        exit();
    }

    echo json_encode(['success' => false, 'message' => 'Unknown request action: ' . htmlspecialchars($action)]);
    exit();

} catch (\Throwable $e) {
    error_log('ajax/update_settings.php Error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'A system error occurred while processing settings update.'
    ]);
    exit();
}
