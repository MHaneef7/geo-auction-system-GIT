<?php
/**
 * User Profile & Account Settings
 * File: profile.php
 * 
 * Built with OOP PHP + PDO exclusively.
 * Compliant with Geo-Auction UI & Security Architecture.
 */

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/auth.php';

// Authentication Check: Redirect to login if unauthenticated
if (!is_logged_in()) {
    header("Location: login.php?redirect=profile.php");
    exit();
}

class UserProfileService
{
    private PDO $pdo;
    private int $userId;

    public function __construct(PDO $pdo, int $userId)
    {
        $this->pdo    = $pdo;
        $this->userId = $userId;
    }

    /**
     * Fetch user profile data
     */
    public function getUserProfile(): ?array
    {
        try {
            $stmt = $this->pdo->prepare("SELECT id, name, email, phone, gender, bio, role, status, 
                       avatar, balance, created_at, updated_at
                FROM users 
                WHERE id = :id 
                LIMIT 1
            ");
            $stmt->execute([':id' => $this->userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($user) {
                $user['username'] = explode('@', $user['email'])[0];
                $user['kyc_tier'] = 'Tier 1';
            }
            return $user ?: null;
        } catch (\PDOException $e) {
            error_log("getUserProfile error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Update user profile information & avatar
     */
    public function updateProfile(array $data, ?array $file): array
    {
        $name   = trim((string)($data['name'] ?? ''));
        $phone  = trim((string)($data['phone'] ?? ''));
        $gender = trim((string)($data['gender'] ?? ''));
        $bio    = trim((string)($data['bio'] ?? ''));

        // Basic Validations
        if ($name === '') {
            return ['success' => false, 'message' => 'Full Name cannot be empty.'];
        }
        if (mb_strlen($name) > 100) {
            return ['success' => false, 'message' => 'Name cannot exceed 100 characters.'];
        }

        // Allowed gender values
        $validGenders = ['male', 'female', 'other'];
        if ($gender !== '' && !in_array(strtolower($gender), $validGenders, true)) {
            $gender = null;
        } else {
            $gender = strtolower($gender);
        }

        // Phone validation
        if ($phone !== '' && mb_strlen($phone) > 25) {
            return ['success' => false, 'message' => 'Phone number is too long.'];
        }

        // Bio length validation
        if (mb_strlen($bio) > 1000) {
            return ['success' => false, 'message' => 'Bio cannot exceed 1000 characters.'];
        }

        // Fetch current avatar to handle replacement
        $currentProfile = $this->getUserProfile();
        if (!$currentProfile) {
            return ['success' => false, 'message' => 'User account not found.'];
        }
        $newAvatarPath = $currentProfile['avatar'] ?? null;

        // Process File Upload if provided
        if ($file && isset($file['error']) && $file['error'] !== UPLOAD_ERR_NO_FILE) {
            $uploadResult = $this->handleAvatarUpload($file, $currentProfile['avatar'] ?? null);
            if (!$uploadResult['success']) {
                return $uploadResult;
            }
            $newAvatarPath = $uploadResult['path'];
        }

        // Execute PDO update
        $updateStmt = $this->pdo->prepare("
            UPDATE users 
            SET name = :name,
                phone = :phone,
                gender = :gender,
                bio = :bio,
                avatar = :avatar,
                updated_at = NOW()
            WHERE id = :id
        ");

        $updateStmt->execute([
            ':name'   => $name,
            ':phone'  => $phone !== '' ? $phone : null,
            ':gender' => $gender !== '' ? $gender : null,
            ':bio'    => $bio !== '' ? $bio : null,
            ':avatar' => $newAvatarPath,
            ':id'     => $this->userId
        ]);

        // Sync session cache
        $_SESSION['user_name'] = $name;
        if (!empty($newAvatarPath)) {
            $_SESSION['user_avatar'] = $newAvatarPath;
        }

        return ['success' => true, 'message' => 'Profile updated successfully!'];
    }

    /**
     * Securely handle avatar upload with strict validation
     */
    private function handleAvatarUpload(array $file, ?string $oldAvatar): array
    {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errCodes = [
                UPLOAD_ERR_INI_SIZE   => 'Image exceeds upload_max_filesize limit.',
                UPLOAD_ERR_FORM_SIZE  => 'Image exceeds MAX_FILE_SIZE limit.',
                UPLOAD_ERR_PARTIAL    => 'Image was only partially uploaded.',
                UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder on server.',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.'
            ];
            $msg = $errCodes[$file['error']] ?? 'Error occurred during image upload.';
            return ['success' => false, 'message' => $msg];
        }

        // 1. File size check (Max 8 MB for modern high-res photos)
        $maxBytes = 8 * 1024 * 1024;
        if ($file['size'] > $maxBytes) {
            return ['success' => false, 'message' => 'Image size cannot exceed 8MB.'];
        }

        // 2. Strict Extension Check (Prevent executable / script files, allow all valid image types)
        $origName = trim((string)($file['name'] ?? ''));
        $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'jfif', 'pjpeg', 'gif', 'avif'];
        $dangerousExtensions = ['php', 'phtml', 'php3', 'php4', 'php5', 'php7', 'phar', 'exe', 'sh', 'pl', 'cgi', 'js', 'html', 'htm', 'asp', 'aspx', 'jsp'];

        if (in_array($ext, $dangerousExtensions, true) || !in_array($ext, $allowedExtensions, true)) {
            return ['success' => false, 'message' => 'Security Error: Only valid image formats (JPG, JPEG, PNG, WEBP, JFIF, GIF) are permitted.'];
        }

        // 3. MIME type inspection via finfo/mime_content_type
        $tmpPath = $file['tmp_name'];
        $mime = '';
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = (string)finfo_file($finfo, $tmpPath);
            finfo_close($finfo);
        } elseif (function_exists('mime_content_type')) {
            $mime = (string)mime_content_type($tmpPath);
        }

        $allowedMimes = [
            'image/jpeg', 
            'image/pjpeg', 
            'image/png', 
            'image/x-png', 
            'image/webp', 
            'image/gif', 
            'image/avif', 
            'image/x-jfif'
        ];

        // 4. Verify image dimensions using getimagesize
        $imageInfo = @getimagesize($tmpPath);
        if ($imageInfo !== false && !empty($imageInfo['mime'])) {
            // Fallback or secondary confirmation from image header
            if (empty($mime) || $mime === 'application/octet-stream') {
                $mime = $imageInfo['mime'];
            }
        }

        if (!in_array($mime, $allowedMimes, true) && $imageInfo === false) {
            return ['success' => false, 'message' => 'Invalid image content. MIME type verification failed.'];
        }

        // 5. Ensure target directory exists
        $uploadDir = __DIR__ . '/uploads/profile/';
        if (!is_dir($uploadDir)) {
            if (!mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
                return ['success' => false, 'message' => 'Server could not prepare uploads directory.'];
            }
        }

        // 6. Generate safe unique filename with normalized extension
        $normalizedExt = match($ext) {
            'jpeg', 'jfif', 'pjpeg' => 'jpg',
            'png' => 'png',
            'webp' => 'webp',
            'gif' => 'gif',
            'avif' => 'avif',
            default => 'jpg'
        };
        $safeFilename = 'profile_' . $this->userId . '_' . bin2hex(random_bytes(8)) . '.' . $normalizedExt;
        $destination = $uploadDir . $safeFilename;

        if (!move_uploaded_file($tmpPath, $destination)) {
            return ['success' => false, 'message' => 'Failed to store image in destination directory.'];
        }

        $relativePath = 'uploads/profile/' . $safeFilename;

        // 7. Safely delete old image if it exists in uploads folder
        if (!empty($oldAvatar)) {
            $this->safeDeleteOldAvatar($oldAvatar);
        }

        return ['success' => true, 'path' => $relativePath];
    }

    /**
     * Safely delete previous uploaded avatar file
     */
    private function safeDeleteOldAvatar(string $oldAvatar): void
    {
        // Avoid deleting default assets or path traversal
        $oldAvatar = trim($oldAvatar, "/\\");
        if (str_starts_with($oldAvatar, 'uploads/profile/') || str_starts_with($oldAvatar, 'uploads/avatars/')) {
            $fullPath = __DIR__ . '/' . $oldAvatar;
            if (file_exists($fullPath) && is_file($fullPath)) {
                @unlink($fullPath);
            }
        }
    }
}

// Instantiate Service
$pdo = Database::getPdo();
$userId = (int)$_SESSION['user_id'];
$profileService = new UserProfileService($pdo, $userId);

$feedbackMessage = '';
$feedbackType    = '';

// CSRF Protection Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

// Process POST Form Submission
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $submittedToken)) {
        $feedbackMessage = 'Security validation failed: Invalid CSRF token. Please refresh the page.';
        $feedbackType    = 'danger';
    } else {
        $avatarFile = isset($_FILES['avatar']) && $_FILES['avatar']['name'] !== '' ? $_FILES['avatar'] : null;
        $res = $profileService->updateProfile($_POST, $avatarFile);
        $feedbackMessage = $res['message'];
        $feedbackType    = $res['success'] ? 'success' : 'danger';
    }
}

// Fetch Latest Profile Data
$user = $profileService->getUserProfile();
if (!$user) {
    die('User account not found.');
}

// Clean Avatar URL helper
$avatarUrl = '';
if (!empty($user['avatar'])) {
    if (preg_match('/^(https?:\/\/|\/\/)/i', $user['avatar'])) {
        $avatarUrl = $user['avatar'];
    } else {
        $avatarUrl = htmlspecialchars(ltrim($user['avatar'], '/'));
    }
} else {
    $avatarUrl = 'https://ui-avatars.com/api/?name=' . urlencode($user['name'] ?: 'User') . '&background=007a33&color=fff&size=200&bold=true';
}

// Include Geo-Auction standard header (navbar, sidebar, theme support)
$page_title = 'My Profile - Geo Auction';
require_once __DIR__ . '/includes/header.php';
?>

<!-- Bootstrap 5 CSS & FontAwesome -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

<!-- Geo-Auction Profile Design System -->
<style>
:root {
    --profile-primary: #047857;
    --profile-primary-hover: #065f46;
    --profile-primary-light: #ecfdf5;
    --profile-surface: #ffffff;
    --profile-surface-alt: #f8fafc;
    --profile-border: #e2e8f0;
    --profile-text: #0f172a;
    --profile-text-muted: #64748b;
    --profile-radius: 16px;
    --profile-radius-sm: 10px;
    --profile-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
    --profile-shadow-hover: 0 8px 30px rgba(0, 0, 0, 0.09);
    --profile-input-bg: #ffffff;
    --profile-input-border: #cbd5e1;
}

html.dark-theme {
    --profile-primary: #10b981;
    --profile-primary-hover: #059669;
    --profile-primary-light: rgba(16, 185, 129, 0.14);
    --profile-surface: #1e293b;
    --profile-surface-alt: #0f172a;
    --profile-border: #334155;
    --profile-text: #f1f5f9;
    --profile-text-muted: #94a3b8;
    --profile-shadow: 0 4px 24px rgba(0, 0, 0, 0.35);
    --profile-shadow-hover: 0 8px 32px rgba(0, 0, 0, 0.45);
    --profile-input-bg: #0f172a;
    --profile-input-border: #334155;
}

/* Base Profile Layout */
.profile-page-wrapper {
    max-width: 1200px;
    margin: 0 auto;
    padding: 24px 16px 60px;
    color: var(--profile-text);
}

/* Hero Header Banner */
.profile-hero-banner {
    background: linear-gradient(135deg, #047857 0%, #064e3b 100%);
    border-radius: var(--profile-radius);
    padding: 30px 32px;
    margin-bottom: 28px;
    color: #ffffff;
    box-shadow: 0 10px 28px -6px rgba(4, 120, 87, 0.38);
    position: relative;
    overflow: hidden;
}
.profile-hero-banner::after {
    content: '';
    position: absolute;
    right: -40px;
    top: -40px;
    width: 220px;
    height: 220px;
    background: radial-gradient(circle, rgba(255,255,255,0.12) 0%, rgba(255,255,255,0) 70%);
    border-radius: 50%;
    pointer-events: none;
}

/* Glass Accent Buttons in Hero */
.hero-action-btn {
    background: rgba(255, 255, 255, 0.15);
    color: #ffffff !important;
    border: 1px solid rgba(255, 255, 255, 0.25);
    border-radius: 50px;
    padding: 7px 18px;
    font-size: 13px;
    font-weight: 600;
    transition: all 0.2s ease;
    backdrop-filter: blur(6px);
    display: inline-flex;
    align-items: center;
    gap: 6px;
    text-decoration: none;
}
.hero-action-btn:hover {
    background: rgba(255, 255, 255, 0.28);
    border-color: rgba(255, 255, 255, 0.4);
    transform: translateY(-1px);
    color: #ffffff !important;
}

/* Card Styling */
.geo-card {
    background: var(--profile-surface);
    border: 1px solid var(--profile-border);
    border-radius: var(--profile-radius);
    box-shadow: var(--profile-shadow);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    overflow: hidden;
}
.geo-card-header {
    background: var(--profile-surface);
    border-bottom: 1px solid var(--profile-border);
    padding: 18px 24px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

/* Avatar Styling */
.avatar-preview-box {
    position: relative;
    display: inline-block;
    margin: 0 auto;
}
.avatar-preview-box img {
    width: 124px;
    height: 124px;
    border-radius: 50%;
    object-fit: cover;
    border: 4px solid var(--profile-surface);
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
    transition: transform 0.25s ease;
    background: #e2e8f0;
}
.avatar-preview-box:hover img {
    transform: scale(1.02);
}
.avatar-camera-btn {
    position: absolute;
    bottom: 2px;
    right: 2px;
    width: 38px;
    height: 38px;
    border-radius: 50%;
    background: #047857;
    color: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 3px solid var(--profile-surface);
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
    cursor: pointer;
    transition: all 0.2s ease;
}
.avatar-camera-btn:hover {
    background: #065f46;
    transform: scale(1.1);
    color: #ffffff;
}

/* Upload Dropzone Box */
.avatar-upload-zone {
    background: var(--profile-surface-alt);
    border: 2px dashed var(--profile-border);
    border-radius: var(--profile-radius-sm);
    padding: 16px 20px;
    transition: border-color 0.2s ease, background 0.2s ease;
}
.avatar-upload-zone:hover {
    border-color: var(--profile-primary);
    background: var(--profile-primary-light);
}

/* Form Controls & Inputs */
.form-label {
    color: var(--profile-text);
    font-size: 13px;
    font-weight: 600;
    margin-bottom: 6px;
}
.profile-input-group .input-group-text {
    background-color: var(--profile-surface-alt);
    border: 1px solid var(--profile-input-border);
    border-right: none;
    color: var(--profile-text-muted);
    border-radius: var(--profile-radius-sm) 0 0 var(--profile-radius-sm);
    padding: 9px 14px;
    font-size: 14px;
}
.profile-input-group .form-control,
.profile-input-group .form-select,
.profile-textarea {
    background-color: var(--profile-input-bg);
    border: 1px solid var(--profile-input-border);
    color: var(--profile-text);
    border-radius: 0 var(--profile-radius-sm) var(--profile-radius-sm) 0;
    padding: 9px 14px;
    font-size: 14px;
    transition: border-color 0.15s ease, box-shadow 0.15s ease;
}
.profile-textarea {
    border-radius: var(--profile-radius-sm) !important;
}
.profile-input-group .form-control:focus,
.profile-input-group .form-select:focus,
.profile-textarea:focus {
    border-color: var(--profile-primary);
    box-shadow: 0 0 0 3px rgba(4, 120, 87, 0.18);
    background-color: var(--profile-input-bg);
    color: var(--profile-text);
    outline: none;
}
.profile-input-group .form-control[readonly],
.profile-input-group .form-control:disabled {
    background-color: var(--profile-surface-alt);
    color: var(--profile-text-muted);
    cursor: not-allowed;
    border-color: var(--profile-border);
}

/* Metadata List */
.meta-info-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 0;
    border-bottom: 1px solid var(--profile-border);
    font-size: 13px;
}
.meta-info-row:last-child {
    border-bottom: none;
}
.meta-info-label {
    color: var(--profile-text-muted);
    display: flex;
    align-items: center;
    gap: 8px;
}
.meta-info-value {
    color: var(--profile-text);
    font-weight: 600;
}

/* Primary Action Buttons */
.btn-geo-primary {
    background: linear-gradient(135deg, #047857 0%, #065f46 100%);
    color: #ffffff;
    border: none;
    border-radius: 50px;
    padding: 10px 24px;
    font-size: 14px;
    font-weight: 600;
    box-shadow: 0 4px 14px rgba(4, 120, 87, 0.28);
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}
.btn-geo-primary:hover {
    background: linear-gradient(135deg, #059669 0%, #047857 100%);
    box-shadow: 0 6px 18px rgba(4, 120, 87, 0.38);
    transform: translateY(-1px);
    color: #ffffff;
}
.btn-geo-light {
    background: var(--profile-surface-alt);
    color: var(--profile-text);
    border: 1px solid var(--profile-border);
    border-radius: 50px;
    padding: 10px 20px;
    font-size: 14px;
    font-weight: 500;
    transition: all 0.2s ease;
}
.btn-geo-light:hover {
    background: var(--profile-border);
    color: var(--profile-text);
}

/* Subtle Bio Quote Box */
.profile-bio-box {
    background: var(--profile-surface-alt);
    border-left: 3px solid var(--profile-primary);
    border-radius: 6px;
    padding: 12px 14px;
    font-size: 13px;
    color: var(--profile-text-muted);
    font-style: italic;
    line-height: 1.5;
    margin-top: 14px;
}
</style>

<!-- Profile Page Wrapper -->
<div class="profile-page-wrapper">

    <!-- Top Hero Header Banner -->
    <div class="profile-hero-banner">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-4">
            <div>
                <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                    <span class="badge rounded-pill bg-white text-dark shadow-sm px-3 py-1 text-uppercase fw-bold" style="font-size: 11px; letter-spacing: 0.5px;">
                        <i class="fa-solid fa-user-tag text-success me-1"></i> <?= htmlspecialchars(strtoupper($user['role'] ?? 'BUYER')); ?>
                    </span>
                    <?php if (($user['status'] ?? 'active') === 'active'): ?>
                        <span class="badge rounded-pill bg-success bg-opacity-25 text-white border border-white border-opacity-25 px-2.5 py-1" style="font-size: 11px;">
                            <i class="fa-solid fa-circle-check me-1"></i> Active Member
                        </span>
                    <?php else: ?>
                        <span class="badge rounded-pill bg-warning text-dark px-2.5 py-1" style="font-size: 11px;">
                            <i class="fa-solid fa-clock me-1"></i> <?= htmlspecialchars(ucfirst($user['status'] ?? 'pending')); ?>
                        </span>
                    <?php endif; ?>
                </div>
                <h1 class="h2 fw-bold mb-1 text-white"><?= htmlspecialchars($user['name']); ?></h1>
                <p class="mb-0 text-white text-opacity-75 small">
                    <i class="fa-solid fa-envelope me-1"></i> <?= htmlspecialchars($user['email']); ?>
                    <?php if (!empty($user['username'])): ?>
                        <span class="ms-2 opacity-75">(@<?= htmlspecialchars($user['username']); ?>)</span>
                    <?php endif; ?>
                </p>
            </div>
            
            <!-- Quick Action Links -->
            <div class="d-flex flex-wrap gap-2">
                <a href="user/dashboard.php" class="hero-action-btn">
                    <i class="fa-solid fa-gauge"></i> Dashboard
                </a>
                <a href="live_auction.php" class="hero-action-btn">
                    <i class="fa-solid fa-gavel"></i> Live Arena
                </a>
                <a href="user/won_auctions.php" class="hero-action-btn">
                    <i class="fa-solid fa-trophy"></i> Won Lots
                </a>
            </div>
        </div>
    </div>

    <!-- Alert / Feedback Message -->
    <?php if (!empty($feedbackMessage)): ?>
        <div class="alert alert-<?= $feedbackType === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show rounded-3 shadow-sm d-flex align-items-center gap-2 mb-4" role="alert" style="border: 1px solid var(--profile-border);">
            <i class="fa-solid <?= $feedbackType === 'success' ? 'fa-circle-check text-success' : 'fa-triangle-exclamation text-danger'; ?> fs-5"></i>
            <div class="fw-medium small"><?= htmlspecialchars($feedbackMessage); ?></div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Two-Column Grid: Profile Card (Left) & Edit Form (Right) -->
    <div class="row g-4">
        
        <!-- LEFT COLUMN: Profile Overview Card -->
        <div class="col-lg-4 col-md-5">
            <div class="geo-card">
                
                <!-- Card Header Accent -->
                <div style="height: 100px; background: linear-gradient(135deg, #047857 0%, #064e3b 100%); position: relative;">
                    <div style="position: absolute; right: 14px; top: 14px;">
                        <span class="badge rounded-pill bg-white text-dark shadow-sm px-2.5 py-1 text-uppercase fw-bold" style="font-size: 10px;">
                            <?= htmlspecialchars(strtoupper($user['role'] ?? 'BUYER')); ?>
                        </span>
                    </div>
                </div>

                <div class="p-4 pt-0 text-center" style="margin-top: -55px;">
                    
                    <!-- Circular Avatar with Camera Trigger -->
                    <div class="avatar-preview-box mb-3">
                        <img id="avatarPreviewDisplay" 
                             src="<?= $avatarUrl; ?>" 
                             alt="<?= htmlspecialchars($user['name']); ?>" 
                             onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($user['name'] ?: 'User'); ?>&background=007a33&color=fff&size=200';">
                        
                        <label for="avatarInputFile" 
                               class="avatar-camera-btn" 
                               title="Click to select new profile photo">
                            <i class="fa-solid fa-camera" style="font-size: 13px;"></i>
                        </label>
                    </div>

                    <h2 class="h5 fw-bold mb-1" style="color: var(--profile-text);"><?= htmlspecialchars($user['name']); ?></h2>
                    <p class="small mb-3" style="color: var(--profile-text-muted);"><?= htmlspecialchars($user['email']); ?></p>

                    <!-- Trust / KYC Badge -->
                    <div class="d-flex justify-content-center gap-2 flex-wrap mb-3">
                        <span class="badge rounded-pill px-3 py-1.5" style="background: var(--profile-primary-light); color: var(--profile-primary); border: 1px solid rgba(4, 120, 87, 0.2); font-size: 11px; font-weight: 600;">
                            <i class="fa-solid fa-shield-halved me-1"></i> <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $user['kyc_tier'] ?? 'Standard Verified'))); ?>
                        </span>
                    </div>

                    <!-- Bio Blockquote -->
                    <?php if (!empty($user['bio'])): ?>
                        <div class="profile-bio-box text-start">
                            "<?= htmlspecialchars($user['bio']); ?>"
                        </div>
                    <?php endif; ?>

                    <hr style="border-color: var(--profile-border); margin: 20px 0;">

                    <!-- Metadata Rows -->
                    <div class="text-start">
                        <div class="meta-info-row">
                            <span class="meta-info-label"><i class="fa-solid fa-phone text-muted" style="width: 16px;"></i> Phone</span>
                            <span class="meta-info-value"><?= htmlspecialchars($user['phone'] ?: 'Not set'); ?></span>
                        </div>
                        <div class="meta-info-row">
                            <span class="meta-info-label"><i class="fa-solid fa-venus-mars text-muted" style="width: 16px;"></i> Gender</span>
                            <span class="meta-info-value"><?= htmlspecialchars(ucfirst($user['gender'] ?: 'Unspecified')); ?></span>
                        </div>
                        <div class="meta-info-row">
                            <span class="meta-info-label"><i class="fa-solid fa-wallet text-success" style="width: 16px;"></i> Wallet Balance</span>
                            <span class="meta-info-value text-success" style="color: var(--profile-primary) !important;">Rs. <?= number_format((float)($user['balance'] ?? 0), 2); ?></span>
                        </div>
                        <div class="meta-info-row">
                            <span class="meta-info-label"><i class="fa-solid fa-calendar-check text-muted" style="width: 16px;"></i> Member Since</span>
                            <span class="meta-info-value"><?= date('M Y', strtotime($user['created_at'] ?? 'now')); ?></span>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        <!-- RIGHT COLUMN: Update Profile Form -->
        <div class="col-lg-8 col-md-7">
            <div class="geo-card">
                
                <div class="geo-card-header">
                    <div class="d-flex align-items-center gap-2">
                        <i class="fa-solid fa-user-pen" style="color: var(--profile-primary); font-size: 16px;"></i>
                        <span class="fw-bold" style="color: var(--profile-text); font-size: 15px;">Update Profile Information</span>
                    </div>
                    <span class="badge rounded-pill bg-light text-secondary border px-2.5 py-1" style="font-size: 11px;">
                        <i class="fa-solid fa-lock text-success me-1"></i> Secure Form
                    </span>
                </div>

                <div class="p-4">
                    <form method="POST" action="profile.php" enctype="multipart/form-data" id="profileUpdateForm">
                        <input type="hidden" name="action" value="update_profile">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken); ?>">

                        <!-- Avatar Upload Box -->
                        <div class="avatar-upload-zone mb-4">
                            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                                <div>
                                    <div class="fw-bold small mb-1" style="color: var(--profile-text);">
                                        <i class="fa-solid fa-image me-1 text-success"></i> Change Profile Avatar
                                    </div>
                                    <p class="text-muted mb-0" style="font-size: 12px;">
                                        Select a clear headshot. Formats: JPG, JPEG, PNG, WEBP, JFIF, GIF (Max 8MB).
                                    </p>
                                </div>
                                <div>
                                    <input type="file" 
                                           name="avatar" 
                                           id="avatarInputFile" 
                                           accept="image/*,.jpg,.jpeg,.png,.webp,.jfif,.gif,.avif" 
                                           class="d-none" 
                                           onchange="previewProfileImage(this)">
                                    <button type="button" 
                                            onclick="document.getElementById('avatarInputFile').click()" 
                                            class="btn btn-sm btn-geo-light">
                                        <i class="fa-solid fa-cloud-arrow-up me-1 text-success"></i> Choose Photo
                                    </button>
                                </div>
                            </div>
                            
                            <!-- File Selected Confirmation Badge -->
                            <div id="fileSelectedBadge" class="mt-2 text-success small d-none font-monospace">
                                <i class="fa-solid fa-circle-check me-1"></i> <span id="fileNameText"></span>
                            </div>
                        </div>

                        <!-- Form Field Grid -->
                        <div class="row g-3">
                            
                            <!-- Full Name -->
                            <div class="col-sm-6">
                                <label for="inputName" class="form-label">
                                    Full Name <span class="text-danger">*</span>
                                </label>
                                <div class="input-group profile-input-group">
                                    <span class="input-group-text"><i class="fa-solid fa-user"></i></span>
                                    <input type="text" 
                                           name="name" 
                                           id="inputName" 
                                           required 
                                           maxlength="100" 
                                           class="form-control" 
                                           value="<?= htmlspecialchars($user['name'] ?? ''); ?>" 
                                           placeholder="Enter your full name">
                                </div>
                            </div>

                            <!-- Email Address (Read-only) -->
                            <div class="col-sm-6">
                                <label class="form-label">
                                    Email Address <span class="text-muted" style="font-size: 11px;">(Permanent)</span>
                                </label>
                                <div class="input-group profile-input-group">
                                    <span class="input-group-text"><i class="fa-solid fa-envelope"></i></span>
                                    <input type="email" 
                                           class="form-control" 
                                           value="<?= htmlspecialchars($user['email'] ?? ''); ?>" 
                                           disabled 
                                           readonly>
                                </div>
                            </div>

                            <!-- Phone Number -->
                            <div class="col-sm-6">
                                <label for="inputPhone" class="form-label">
                                    Phone Number
                                </label>
                                <div class="input-group profile-input-group">
                                    <span class="input-group-text"><i class="fa-solid fa-phone"></i></span>
                                    <input type="text" 
                                           name="phone" 
                                           id="inputPhone" 
                                           maxlength="25" 
                                           class="form-control" 
                                           value="<?= htmlspecialchars($user['phone'] ?? ''); ?>" 
                                           placeholder="+92 300 1234567">
                                </div>
                            </div>

                            <!-- Gender Select -->
                            <div class="col-sm-6">
                                <label for="inputGender" class="form-label">
                                    Gender
                                </label>
                                <div class="input-group profile-input-group">
                                    <span class="input-group-text"><i class="fa-solid fa-venus-mars"></i></span>
                                    <select name="gender" id="inputGender" class="form-select">
                                        <option value="" <?= empty($user['gender']) ? 'selected' : ''; ?>>Prefer not to say</option>
                                        <option value="male" <?= ($user['gender'] ?? '') === 'male' ? 'selected' : ''; ?>>Male</option>
                                        <option value="female" <?= ($user['gender'] ?? '') === 'female' ? 'selected' : ''; ?>>Female</option>
                                        <option value="other" <?= ($user['gender'] ?? '') === 'other' ? 'selected' : ''; ?>>Other</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Bio / About -->
                            <div class="col-12">
                                <label for="inputBio" class="form-label">
                                    Bio / About You
                                </label>
                                <textarea name="bio" 
                                          id="inputBio" 
                                          rows="3" 
                                          maxlength="1000" 
                                          class="form-control profile-textarea" 
                                          placeholder="Share a short introduction or your auction interests..."><?= htmlspecialchars($user['bio'] ?? ''); ?></textarea>
                                <div class="d-flex justify-content-between text-muted mt-1" style="font-size: 11px;">
                                    <span>Brief summary displayed on your public profile and consignments.</span>
                                    <span id="bioCharCount" class="fw-semibold">0/1000</span>
                                </div>
                            </div>

                        </div>

                        <!-- Submit Button Bar -->
                        <div class="mt-4 pt-3 d-flex justify-content-end align-items-center gap-3" style="border-top: 1px solid var(--profile-border);">
                            <a href="user/dashboard.php" class="btn btn-geo-light">
                                Cancel
                            </a>
                            <button type="submit" 
                                    id="btnSubmitProfile" 
                                    class="btn btn-geo-primary">
                                <i class="fa-solid fa-floppy-disk me-1"></i> Update Profile
                            </button>
                        </div>

                    </form>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- Image Preview & Client Validation Script -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function previewProfileImage(input) {
    if (input.files && input.files[0]) {
        var file = input.files[0];
        
        // 1. File size check (Max 8MB)
        if (file.size > 8 * 1024 * 1024) {
            alert("File size exceeds 8MB limit. Please choose a smaller image.");
            input.value = "";
            return;
        }

        // 2. MIME type & Extension check (Support JFIF, WebP, PNG, JPEG, GIF, AVIF)
        var validTypes = ["image/jpeg", "image/png", "image/webp", "image/gif", "image/avif", "image/pjpeg", "image/x-jfif"];
        var fileName = (file.name || "").toLowerCase();
        var fileExt = fileName.split('.').pop();
        var validExts = ["jpg", "jpeg", "png", "webp", "jfif", "pjpeg", "gif", "avif"];

        var isTypeOk = file.type ? (validTypes.includes(file.type) || file.type.startsWith("image/")) : true;
        var isExtOk  = validExts.includes(fileExt);

        if (!isTypeOk && !isExtOk) {
            alert("Please select a valid image file (JPG, PNG, WEBP, JFIF, GIF).");
            input.value = "";
            return;
        }

        // 3. Client FileReader preview
        var reader = new FileReader();
        reader.onload = function(e) {
            var previewImg = document.getElementById("avatarPreviewDisplay");
            if (previewImg) {
                previewImg.src = e.target.result;
            }
        };
        reader.readAsDataURL(file);

        // Show confirmation badge
        var badge = document.getElementById("fileSelectedBadge");
        var nameSpan = document.getElementById("fileNameText");
        if (badge && nameSpan) {
            nameSpan.textContent = file.name + " (" + (file.size / 1024).toFixed(1) + " KB)";
            badge.classList.remove("d-none");
        }
    }
}

// Character counter for Bio
document.addEventListener("DOMContentLoaded", function() {
    var bioArea = document.getElementById("inputBio");
    var counter = document.getElementById("bioCharCount");
    if (bioArea && counter) {
        function updateCount() {
            counter.textContent = bioArea.value.length + "/1000";
        }
        bioArea.addEventListener("input", updateCount);
        updateCount();
    }
});
</script>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
