<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include_once __DIR__ . '/../includes/header.php';
include_once __DIR__ . '/../includes/database.php';

$user_id = (int)($_SESSION['user_id'] ?? 0);
$success_message = "";
$error_message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name  = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $bio   = trim($_POST['bio'] ?? '');

    if (empty($name)) {
        $error_message = "Please fill in your name.";
    } else {
        $current_res = mysqli_query($conn, "SELECT avatar FROM users WHERE id = $user_id LIMIT 1");
        $current_user = ($current_res) ? mysqli_fetch_assoc($current_res) : [];
        $avatar_path = $current_user['avatar'] ?? '';
        
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $file_tmp  = $_FILES['avatar']['tmp_name'];
            $file_name = $_FILES['avatar']['name'];
            $file_size = $_FILES['avatar']['size'];
            
            $allowed_types = ['image/jpeg', 'image/png', 'image/webp', 'image/jpg'];
            $file_mime = mime_content_type($file_tmp);

            if (!in_array($file_mime, $allowed_types)) {
                $error_message = 'Invalid image format. Only JPG, PNG, and WEBP are allowed for avatar.';
            } elseif ($file_size > 5 * 1024 * 1024) { // 5MB limit
                $error_message = 'Avatar image size exceeds 5MB limit.';
            } else {
                $upload_dir = __DIR__ . '/../uploads/avatars/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                $file_ext = pathinfo($file_name, PATHINFO_EXTENSION);
                $new_filename = 'avatar_' . $user_id . '_' . time() . '.' . $file_ext;
                $destination = $upload_dir . $new_filename;
                
                if (move_uploaded_file($file_tmp, $destination)) {
                    if (!empty($avatar_path) && file_exists(__DIR__ . '/../' . $avatar_path)) {
                        @unlink(__DIR__ . '/../' . $avatar_path);
                    }
                    $avatar_path = 'uploads/avatars/' . $new_filename;
                } else {
                    $error_message = 'Failed to upload avatar image.';
                }
            }
        }
        
        if (empty($error_message)) {
            $name_esc  = mysqli_real_escape_string($conn, $name);
            $phone_esc = mysqli_real_escape_string($conn, $phone);
            $bio_esc   = mysqli_real_escape_string($conn, $bio);
            $avatar_esc = mysqli_real_escape_string($conn, $avatar_path);

            $update_sql = "UPDATE users SET name = '$name_esc', phone = '$phone_esc', bio = '$bio_esc', avatar = '$avatar_esc' WHERE id = $user_id";
            
            if (mysqli_query($conn, $update_sql)) {
                $_SESSION['user_name'] = $name;
                $success_message = "Profile updated successfully!";
            } else {
                $error_message = "Database error: " . mysqli_error($conn);
            }
        }
    }
}

$user_res = mysqli_query($conn, "SELECT * FROM users WHERE id = $user_id LIMIT 1");
$user     = ($user_res) ? mysqli_fetch_assoc($user_res) : [];
?>

<style>
    :root {
        --primary-green: rgb(4 120 87);
        --primary-green-hover: rgb(3 98 71);
    }
    .profile-wrapper {
        max-width: 900px;
        margin: 40px auto;
        background: #ffffff;
        border-radius: 12px;
        border: 1px solid #e5e7eb;
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.05);
        overflow: hidden;
    }
    .profile-header-banner {
        background: linear-gradient(135deg, rgb(4 120 87), #065f46);
        padding: 30px 40px;
        color: #ffffff;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 15px;
    }
    .profile-body {
        padding: 40px;
    }
    .form-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
    }
    @media(max-width: 768px) {
        .form-grid {
            grid-template-columns: 1fr;
        }
        .profile-body {
            padding: 20px;
        }
    }
    .custom-input {
        width: 100%;
        padding: 12px 14px;
        border: 1px solid #d1d5db;
        border-radius: 8px;
        font-size: 14px;
        color: #374151;
        outline: none;
        transition: all 0.2s ease;
    }
    .custom-input:focus {
        border-color: var(--primary-green);
        box-shadow: 0 0 0 3px rgba(4, 120, 87, 0.15);
    }
    .custom-input:disabled {
        background-color: #f3f4f6;
        cursor: not-allowed;
    }
    .avatar-upload-container {
        display: flex;
        align-items: center;
        gap: 24px;
        background: #f9fafb;
        padding: 20px;
        border-radius: 10px;
        border: 1px solid #e5e7eb;
        margin-bottom: 30px;
    }
    .avatar-preview-wrapper {
        width: 100px;
        height: 100px;
        border-radius: 50%;
        overflow: hidden;
        border: 3px solid var(--primary-green);
        background: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        position: relative;
        flex-shrink: 0;
        box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
    }
    .avatar-preview-wrapper img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .avatar-overlay {
        position: absolute;
        bottom: 0;
        width: 100%;
        background: rgba(4, 120, 87, 0.85);
        color: white;
        font-size: 11px;
        text-align: center;
        padding: 3px 0;
        font-weight: 700;
        letter-spacing: 0.5px;
        text-transform: uppercase;
    }
    .btn-save {
        background: var(--primary-green);
        color: #fff;
        border: none;
        padding: 12px 28px;
        border-radius: 8px;
        font-weight: 700;
        font-size: 15px;
        cursor: pointer;
        transition: background 0.2s ease;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    .btn-save:hover {
        background: var(--primary-green-hover);
    }
</style>

<div class="profile-wrapper">
    <!-- Header Banner -->
    <div class="profile-header-banner">
        <div>
            <h2 style="font-size: 24px; font-weight: 800; margin: 0 0 5px 0;">Account Settings</h2>
            <p style="margin: 0; font-size: 14px; opacity: 0.9;">Manage your profile information and account preferences</p>
        </div>
        <div style="background: rgba(255, 255, 255, 0.15); padding: 8px 16px; border-radius: 8px; backdrop-filter: blur(5px);">
            <div style="font-size: 11px; text-transform: uppercase; opacity: 0.8; font-weight: 600;">Account Balance</div>
            <div style="font-size: 18px; font-weight: 800; color: #fff;">Rs. <?php echo number_format((float)($user['balance'] ?? 0), 2); ?></div>
        </div>
    </div>

    <div class="profile-body">
        <?php if ($success_message): ?>
            <div style="background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; padding: 14px 18px; border-radius: 8px; margin-bottom: 24px; font-weight: 600; display: flex; align-items: center; gap: 10px;">
                <i class="fa-solid fa-circle-check" style="font-size: 18px;"></i> 
                <div><?php echo htmlspecialchars($success_message); ?></div>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div style="background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; padding: 14px 18px; border-radius: 8px; margin-bottom: 24px; font-weight: 600; display: flex; align-items: center; gap: 10px;">
                <i class="fa-solid fa-circle-exclamation" style="font-size: 18px;"></i> 
                <div><?php echo htmlspecialchars($error_message); ?></div>
            </div>
        <?php endif; ?>

        <form action="profile.php" method="POST" enctype="multipart/form-data">
            
            <!-- Avatar Section -->
            <div class="avatar-upload-container">
                <div class="avatar-preview-wrapper" onclick="document.getElementById('avatarInput').click();" title="Click to change profile picture">
                    <img id="avatarPreview" src="<?php echo !empty($user['avatar']) ? '../' . htmlspecialchars($user['avatar']) : 'https://via.placeholder.com/100?text=Upload'; ?>" alt="Avatar">
                    <div class="avatar-overlay">Edit</div>
                </div>
                <div>
                    <label style="display: block; font-weight: 700; color: #111827; margin-bottom: 4px; font-size: 15px;">Profile Picture</label>
                    <p style="margin: 0 0 12px 0; font-size: 13px; color: #6b7280;">Upload a clear picture. Accepted formats: JPG, PNG, WEBP (Max 5MB).</p>
                    <input type="file" name="avatar" id="avatarInput" accept="image/jpeg, image/png, image/webp" style="display: none;">
                    <button type="button" onclick="document.getElementById('avatarInput').click();" style="background: #ffffff; border: 1px solid #d1d5db; padding: 8px 16px; border-radius: 6px; cursor: pointer; font-size: 13px; font-weight: 600; color: #374151; box-shadow: 0 1px 2px 0 rgba(0,0,0,0.05);">
                        <i class="fa-solid fa-camera" style="margin-right: 6px; color: rgb(4 120 87);"></i> Browse Image
                    </button>
                </div>
            </div>

            <div class="form-grid">
                <!-- Full Name -->
                <div style="margin-bottom: 20px;">
                    <label style="display: block; font-weight: 600; color: #374151; margin-bottom: 8px; font-size: 14px;">Full Name <span style="color: #dc2626;">*</span></label>
                    <input type="text" name="name" value="<?php echo htmlspecialchars($user['name'] ?? ''); ?>" required class="custom-input" placeholder="Enter your full name">
                </div>

                <!-- Email (Read-only) -->
                <div style="margin-bottom: 20px;">
                    <label style="display: block; font-weight: 600; color: #374151; margin-bottom: 8px; font-size: 14px;">Email Address <span style="font-size: 12px; color: #9ca3af; font-weight: normal;">(Cannot be changed)</span></label>
                    <input type="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" disabled class="custom-input">
                </div>
            </div>

            <div class="form-grid">
                <!-- Phone Number -->
                <div style="margin-bottom: 20px;">
                    <label style="display: block; font-weight: 600; color: #374151; margin-bottom: 8px; font-size: 14px;">Phone Number</label>
                    <input type="text" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" class="custom-input" placeholder="+1 (555) 000-0000">
                </div>

                <!-- Role (Read-only badge info) -->
                <div style="margin-bottom: 20px;">
                    <label style="display: block; font-weight: 600; color: #374151; margin-bottom: 8px; font-size: 14px;">Account Role</label>
                    <input type="text" value="<?php echo ucfirst(htmlspecialchars($user['role'] ?? 'Buyer')); ?>" disabled class="custom-input" style="text-transform: capitalize; font-weight: 600; color: rgb(4 120 87);">
                </div>
            </div>

            <!-- Bio -->
            <div style="margin-bottom: 30px;">
                <label style="display: block; font-weight: 600; color: #374151; margin-bottom: 8px; font-size: 14px;">Bio / Description</label>
                <textarea name="bio" rows="4" class="custom-input" style="resize: vertical;" placeholder="Write a short bio about yourself..."><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea>
            </div>

            <!-- Submit Button -->
            <div>
                <button type="submit" class="btn-save">
                    <i class="fa-solid fa-floppy-disk"></i> Save Profile Changes
                </button>
            </div>
        </form>
    </div>
</div>

<!-- JavaScript for Live Avatar Preview -->
<script>
document.getElementById('avatarInput').addEventListener('change', function(event) {
    const file = event.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('avatarPreview').src = e.target.result;
        }
        reader.readAsDataURL(file);
    }
});
</script>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>