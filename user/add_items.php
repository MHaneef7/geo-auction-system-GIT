<?php
/**
 * Add New Auction Item - Geo Auction System
 *
 * Handles creation of new auction listings with multiple image uploads,
 * input validation, secure database insertion, and responsive Bootstrap 5 UI.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/database.php';

// Authentication Check: Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php?redirect=user/add_items.php");
    exit();
}

$user_id   = (int)$_SESSION['user_id'];
$user_role = strtolower($_SESSION['role'] ?? $_SESSION['user_role'] ?? 'seller');

$error_msg   = '';
$success_msg = '';

// Helper function to generate URL-safe slugs
function generate_slug($text) {
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
    $text = preg_replace('~[^-\w]+~', '', $text);
    $text = trim($text, '-');
    $text = preg_replace('~-+~', '-', $text);
    $text = strtolower($text);
    return empty($text) ? 'auction-item-' . time() : $text . '-' . substr(uniqid(), -4);
}

// -------------------------------------------------------------
// POST Request Handling: Process New Auction Item Submission
// -------------------------------------------------------------
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['submit_item'])) {
    // 1. Sanitize text inputs
    $title         = trim($_POST['title'] ?? '');
    $category_id   = (int)($_POST['category_id'] ?? 0);
    $description   = trim($_POST['description'] ?? '');
    $starting_bid  = (float)($_POST['starting_bid'] ?? 0);
    $reserve_price = !empty($_POST['reserve_price']) ? (float)$_POST['reserve_price'] : null;
    $min_increment = !empty($_POST['min_increment']) ? (float)$_POST['min_increment'] : 100.00;
    $city          = trim($_POST['city'] ?? '');
    $area          = trim($_POST['area'] ?? '');
    $start_time    = trim($_POST['start_time'] ?? '');
    $end_time      = trim($_POST['end_time'] ?? '');

    // 2. Validate Required Fields
    if ($title === '') {
        $error_msg = 'Please enter an item title.';
    } elseif (mb_strlen($title) < 5 || mb_strlen($title) > 255) {
        $error_msg = 'Title must be between 5 and 255 characters.';
    } elseif ($category_id <= 0) {
        $error_msg = 'Please select a valid auction category.';
    } elseif ($description === '') {
        $error_msg = 'Please provide a detailed item description.';
    } elseif ($starting_bid <= 0) {
        $error_msg = 'Starting bid price must be greater than zero (PKR).';
    } elseif ($reserve_price !== null && $reserve_price < $starting_bid) {
        $error_msg = 'Reserve price cannot be lower than the starting bid price.';
    } elseif ($min_increment <= 0) {
        $error_msg = 'Bid increment step must be greater than zero.';
    } elseif ($city === '' || $area === '') {
        $error_msg = 'Please provide both city and area for item location.';
    } elseif ($start_time === '' || $end_time === '') {
        $error_msg = 'Please specify both auction start and end dates/times.';
    } else {
        // 3. Date & Time Validation
        $start_dt = str_replace('T', ' ', $start_time);
        if (strlen($start_dt) === 16) { $start_dt .= ':00'; }

        $end_dt = str_replace('T', ' ', $end_time);
        if (strlen($end_dt) === 16) { $end_dt .= ':00'; }

        $start_ts = strtotime($start_dt);
        $end_ts   = strtotime($end_dt);

        if ($start_ts === false || $end_ts === false) {
            $error_msg = 'Invalid date/time format provided.';
        } elseif ($end_ts <= $start_ts) {
            $error_msg = 'Auction end date/time must be strictly after the start date/time.';
        } elseif ($end_ts <= time()) {
            $error_msg = 'Auction end date/time must be in the future.';
        }
    }

    // 4. File Upload & Image Validation
    $uploaded_images = [];
    $raw_upload_paths = [];

    if ($error_msg === '') {
        if (!isset($_FILES['item_images']) || empty($_FILES['item_images']['name'][0])) {
            $error_msg = 'Please upload at least one image of the auction item.';
        } else {
            $files = $_FILES['item_images'];
            $file_count = count($files['name']);

            if ($file_count > 5) {
                $error_msg = 'You can upload a maximum of 5 images.';
            } else {
                $upload_dir = __DIR__ . '/../uploads/items/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }

                $allowed_mimes = ['image/jpeg', 'image/png', 'image/webp'];
                $allowed_exts  = ['jpg', 'jpeg', 'png', 'webp'];

                for ($i = 0; $i < $file_count; $i++) {
                    if ($files['error'][$i] === UPLOAD_ERR_OK) {
                        $tmp_name = $files['tmp_name'][$i];
                        $orig_name = $files['name'][$i];
                        $file_size = $files['size'][$i];
                        $file_ext  = strtolower(pathinfo($orig_name, PATHINFO_EXTENSION));

                        // Check MIME type with mime_content_type
                        $mime_type = mime_content_type($tmp_name);

                        if (!in_array($mime_type, $allowed_mimes) || !in_array($file_ext, $allowed_exts)) {
                            $error_msg = 'Invalid image format for file: ' . htmlspecialchars($orig_name) . '. Only JPG, PNG, and WEBP are allowed.';
                            break;
                        } elseif ($file_size > 5 * 1024 * 1024) { // 5MB limit
                            $error_msg = 'File size exceeds 5MB limit for: ' . htmlspecialchars($orig_name);
                            break;
                        } else {
                            $unique_filename = 'item_' . time() . '_' . bin2hex(random_bytes(4)) . '_' . $i . '.' . $file_ext;
                            $target_filepath = $upload_dir . $unique_filename;

                            if (move_uploaded_file($tmp_name, $target_filepath)) {
                                $uploaded_images[] = 'uploads/items/' . $unique_filename;
                                $raw_upload_paths[] = $target_filepath;
                            } else {
                                $error_msg = 'Failed to save uploaded image: ' . htmlspecialchars($orig_name);
                                break;
                            }
                        }
                    } elseif ($files['error'][$i] !== UPLOAD_ERR_NO_FILE) {
                        $error_msg = 'Error uploading file: ' . htmlspecialchars($files['name'][$i]);
                        break;
                    }
                }

                if ($error_msg === '' && empty($uploaded_images)) {
                    $error_msg = 'Please upload at least one valid item image.';
                }
            }
        }
    }

    // 5. Database Insertion with Prepared Statements and Transaction
    if ($error_msg === '') {
        // Generate unique Auction Code
        do {
            $auction_code = 'AUC-' . strtoupper(substr(str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZ23456789'), 0, 6));
            $chk_stmt = mysqli_prepare($conn, "SELECT id FROM items WHERE auction_code = ? LIMIT 1");
            mysqli_stmt_bind_param($chk_stmt, "s", $auction_code);
            mysqli_stmt_execute($chk_stmt);
            $code_exists = mysqli_stmt_fetch($chk_stmt);
            mysqli_stmt_close($chk_stmt);
        } while ($code_exists);

        // Generate Slug
        $slug = generate_slug($title);
        $slug_chk = mysqli_prepare($conn, "SELECT id FROM items WHERE slug = ? LIMIT 1");
        mysqli_stmt_bind_param($slug_chk, "s", $slug);
        mysqli_stmt_execute($slug_chk);
        if (mysqli_stmt_fetch($slug_chk)) {
            $slug .= '-' . time();
        }
        mysqli_stmt_close($slug_chk);

        $featured_image = $uploaded_images[0] ?? '';
        $images_json    = json_encode($uploaded_images);
        $status         = 'active';
        $approved       = 1;

        // Start Transaction
        mysqli_begin_transaction($conn);

        $insert_sql = "
            INSERT INTO items (
                auction_code,
                title,
                slug,
                description,
                starting_price,
                reserve_price,
                current_price,
                min_increment,
                seller_id,
                category_id,
                city,
                area,
                featured_image,
                images,
                status,
                approved,
                start_time,
                end_time,
                created_at,
                updated_at
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW()
            )
        ";

        $stmt = mysqli_prepare($conn, $insert_sql);
        if ($stmt) {
            mysqli_stmt_bind_param(
                $stmt,
                "ssssddddiisssssiss",
                $auction_code,
                $title,
                $slug,
                $description,
                $starting_bid,
                $reserve_price,
                $starting_bid, // Initial current_price equals starting_bid
                $min_increment,
                $user_id,
                $category_id,
                $city,
                $area,
                $featured_image,
                $images_json,
                $status,
                $approved,
                $start_dt,
                $end_dt
            );

            if (mysqli_stmt_execute($stmt)) {
                $new_item_id = mysqli_insert_id($conn);
                mysqli_stmt_close($stmt);

                // Insert into item_images table
                $img_sql = "INSERT INTO item_images (item_id, image_path, is_primary, created_at) VALUES (?, ?, ?, NOW())";
                $img_stmt = mysqli_prepare($conn, $img_sql);

                if ($img_stmt) {
                    foreach ($uploaded_images as $idx => $img_path) {
                        $is_primary = ($idx === 0) ? 1 : 0;
                        mysqli_stmt_bind_param($img_stmt, "isi", $new_item_id, $img_path, $is_primary);
                        mysqli_stmt_execute($img_stmt);
                    }
                    mysqli_stmt_close($img_stmt);
                }

                // Commit Transaction
                mysqli_commit($conn);

                $success_msg = "Auction item published successfully! Auction Code: " . $auction_code;
                // Reset form values on success
                $_POST = [];
            } else {
                mysqli_rollback($conn);
                $error_msg = "Database execute error: " . mysqli_stmt_error($stmt);
                mysqli_stmt_close($stmt);
                // Clean up newly uploaded files on error
                foreach ($raw_upload_paths as $file_path) {
                    if (file_exists($file_path)) { @unlink($file_path); }
                }
            }
        } else {
            mysqli_rollback($conn);
            $error_msg = "Database prepare error: " . mysqli_error($conn);
            foreach ($raw_upload_paths as $file_path) {
                if (file_exists($file_path)) { @unlink($file_path); }
            }
        }
    } else {
        // Validation error: Remove any moved images
        foreach ($raw_upload_paths as $file_path) {
            if (file_exists($file_path)) { @unlink($file_path); }
        }
    }
}

// Fetch categories for select dropdown
$categories_res = mysqli_query($conn, "SELECT id, name FROM categories ORDER BY name ASC");

// Default dates for form
$default_start = date('Y-m-d\TH:i', strtotime('+5 minutes'));
$default_end   = date('Y-m-d\TH:i', strtotime('+7 days'));

include_once __DIR__ . '/../includes/header.php';
?>

<!-- Ensure Bootstrap 5, Bootstrap Icons & FontAwesome are loaded for icons and layout -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

<style>
/* Layout protection against Bootstrap 5 overrides */
.navbar {
    position: fixed !important;
    top: 0 !important;
    left: 0 !important;
    right: 0 !important;
    height: 64px !important;
    min-height: 64px !important;
    max-height: 64px !important;
    display: flex !important;
    align-items: center !important;
    flex-wrap: nowrap !important;
    margin: 0 !important;
    padding: 0 24px !important;
    z-index: 1000 !important;
}
.layout {
    display: flex !important;
    margin-top: 64px !important;
}
.sidebar {
    position: fixed !important;
    top: 64px !important;
    left: 0 !important;
    width: 250px !important;
    height: calc(100vh - 64px) !important;
    z-index: 100 !important;
}
.main-content {
    margin-left: 250px !important;
    padding: 24px !important;
}
@media (max-width: 992px) {
    .main-content { margin-left: 0 !important; }
}

.back-to-auctions-btn {
    transition: all 0.2s ease;
    border-color: #cbd5e1;
    color: #475569;
}
.back-to-auctions-btn:hover {
    background-color: #f1f5f9;
    border-color: #047857;
    color: #047857;
    transform: translateX(-3px);
}

/* Modal Overlay & Styling */
.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 9999;
  backdrop-filter: blur(2px);
  padding: 1rem;
  box-sizing: border-box;
}

.modal-overlay.hidden {
  display: none !important;
}

.modal-box {
  background: #ffffff;
  border-radius: 12px;
  width: 90%;
  max-width: 420px;
  box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
  overflow: hidden;
  animation: popIn 0.2s ease-out;
}

@keyframes popIn {
  from { transform: scale(0.92); opacity: 0; }
  to { transform: scale(1); opacity: 1; }
}

.modal-header {
  padding: 16px 20px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  border-bottom: 1px solid #f0f0f0;
}

.modal-header h3 {
  margin: 0;
  font-size: 1.15rem;
  font-weight: 700;
  color: #1f2937;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.close-x {
  background: none;
  border: none;
  font-size: 22px;
  cursor: pointer;
  color: #666;
  line-height: 1;
  padding: 0;
}

.close-x:hover {
  color: #111;
}

.modal-body {
  padding: 20px;
  color: #444;
  line-height: 1.5;
  font-size: 0.95rem;
}

.modal-body p {
  margin: 0;
}

.modal-footer {
  padding: 12px 20px;
  display: flex;
  justify-content: flex-end;
  gap: 8px;
  background: #fafafa;
  border-top: 1px solid #f0f0f0;
}

.modal-footer .btn,
.btn.primary,
.btn.secondary {
  padding: 8px 16px;
  border-radius: 6px;
  font-weight: 500;
  cursor: pointer;
  border: none;
  font-size: 0.875rem;
  transition: all 0.15s ease;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0.35rem;
}

.btn.primary { background: #047857; color: #fff; }
.btn.primary:hover { background: #065f46; color: #fff; }
.btn.secondary { background: #e5e7eb; color: #374151; }
.btn.secondary:hover { background: #d1d5db; color: #1f2937; }
</style>

<div class="container py-4" style="max-width: 960px;">
    <!-- Breadcrumb & Header -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div>
            <h1 class="h3 fw-bold text-dark mb-0">
                <i class="fa-solid fa-circle-plus text-success me-2"></i>Create New Auction Listing
            </h1>
            <p class="text-muted small mb-0">Fill in the specifications, pricing, and upload images to publish your auction item.</p>
        </div>
        <div>
            <a href="my_auctions.php" class="btn btn-outline-secondary back-to-auctions-btn d-inline-flex align-items-center gap-2 shadow-sm px-3 py-2 rounded-3 text-decoration-none">
                <i class="fa-solid fa-arrow-left"></i>
                <span class="fw-semibold">Back to My Auctions</span>
            </a>
        </div>
    </div>

    <!-- Feedback Alerts -->
    <?php if ($success_msg !== ''): ?>
        <div class="alert alert-success alert-dismissible fade show d-flex align-items-center gap-3 shadow-sm border-0 mb-4" role="alert" style="background-color: #ecfdf5; border-left: 5px solid #10b981 !important; color: #065f46;">
            <i class="bi bi-check-circle-fill fs-4 text-success"></i>
            <div>
                <strong class="d-block">Success!</strong>
                <span><?php echo htmlspecialchars($success_msg); ?></span>
                <div class="mt-2">
                    <a href="my_auctions.php" class="btn btn-sm btn-success fw-semibold me-2">View My Listings</a>
                    <a href="add_items.php" class="btn btn-sm btn-outline-success">Add Another Item</a>
                </div>
            </div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if ($error_msg !== ''): ?>
        <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center gap-3 shadow-sm border-0 mb-4" role="alert" style="background-color: #fef2f2; border-left: 5px solid #ef4444 !important; color: #991b1b;">
            <i class="bi bi-exclamation-octagon-fill fs-4 text-danger"></i>
            <div>
                <strong class="d-block">Error encountered:</strong>
                <span><?php echo htmlspecialchars($error_msg); ?></span>
            </div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Main Form Card -->
    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-body p-4 p-md-5">
            <form method="POST" action="" enctype="multipart/form-data" novalidate id="auctionForm">
                
                <!-- Section 1: Item Basic Information -->
                <div class="mb-4">
                    <h5 class="fw-bold text-dark border-bottom pb-2 mb-3">
                        <i class="bi bi-info-circle text-primary me-2"></i>Item Information
                    </h5>
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label for="itemTitle" class="form-label fw-semibold text-secondary small">
                                Item Title <span class="text-danger">*</span>
                            </label>
                            <input type="text" 
                                   class="form-control form-control-lg" 
                                   id="itemTitle" 
                                   name="title" 
                                   value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>" 
                                   placeholder="e.g., Vintage Rolex Submariner 1985" 
                                   required>
                            <div class="form-text">Include key brand, model, and year details for better discoverability.</div>
                        </div>

                        <div class="col-md-4">
                            <label for="itemCategory" class="form-label fw-semibold text-secondary small">
                                Category <span class="text-danger">*</span>
                            </label>
                            <select class="form-select form-select-lg" id="itemCategory" name="category_id" required>
                                <option value="">-- Select Category --</option>
                                <?php if ($categories_res): ?>
                                    <?php while ($cat = mysqli_fetch_assoc($categories_res)): ?>
                                        <option value="<?php echo (int)$cat['id']; ?>" <?php echo ((int)($_POST['category_id'] ?? 0) === (int)$cat['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($cat['name']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                <?php endif; ?>
                            </select>
                        </div>

                        <div class="col-12">
                            <label for="itemDescription" class="form-label fw-semibold text-secondary small">
                                Full Description <span class="text-danger">*</span>
                            </label>
                            <textarea class="form-control" 
                                      id="itemDescription" 
                                      name="description" 
                                      rows="5" 
                                      placeholder="Provide comprehensive details: physical condition, dimensions, specifications, flaws, and warranty/accessories included..." 
                                      required><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- Section 2: Pricing & Bidding Rules -->
                <div class="mb-4">
                    <h5 class="fw-bold text-dark border-bottom pb-2 mb-3">
                        <i class="bi bi-tag text-success me-2"></i>Pricing & Bidding Settings
                    </h5>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label for="startingBid" class="form-label fw-semibold text-secondary small">
                                Starting Bid (PKR) <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-light fw-bold text-muted">Rs.</span>
                                <input type="number" 
                                       step="0.01" 
                                       min="1" 
                                       class="form-control" 
                                       id="startingBid" 
                                       name="starting_bid" 
                                       value="<?php echo htmlspecialchars($_POST['starting_bid'] ?? ''); ?>" 
                                       placeholder="1000.00" 
                                       required>
                            </div>
                            <div class="form-text">The minimum initial opening bid.</div>
                        </div>

                        <div class="col-md-4">
                            <label for="reservePrice" class="form-label fw-semibold text-secondary small">
                                Reserve Price (PKR) <span class="text-muted fw-normal">(Optional)</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-light fw-bold text-muted">Rs.</span>
                                <input type="number" 
                                       step="0.01" 
                                       min="1" 
                                       class="form-control" 
                                       id="reservePrice" 
                                       name="reserve_price" 
                                       value="<?php echo htmlspecialchars($_POST['reserve_price'] ?? ''); ?>" 
                                       placeholder="Leave blank if none">
                            </div>
                            <div class="form-text">Lowest price you will accept to sell.</div>
                        </div>

                        <div class="col-md-4">
                            <label for="minIncrement" class="form-label fw-semibold text-secondary small">
                                Bid Increment Step (PKR)
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-light fw-bold text-muted">Rs.</span>
                                <input type="number" 
                                       step="0.01" 
                                       min="1" 
                                       class="form-control" 
                                       id="minIncrement" 
                                       name="min_increment" 
                                       value="<?php echo htmlspecialchars($_POST['min_increment'] ?? '100.00'); ?>" 
                                       placeholder="100.00">
                            </div>
                            <div class="form-text">Minimum step each new bid must exceed.</div>
                        </div>
                    </div>
                </div>

                <!-- Section 3: Schedule & Timeline -->
                <div class="mb-4">
                    <h5 class="fw-bold text-dark border-bottom pb-2 mb-3">
                        <i class="bi bi-clock-history text-warning me-2"></i>Auction Schedule
                    </h5>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="startTime" class="form-label fw-semibold text-secondary small">
                                Auction Start Date & Time <span class="text-danger">*</span>
                            </label>
                            <input type="datetime-local" 
                                   class="form-control" 
                                   id="startTime" 
                                   name="start_time" 
                                   value="<?php echo htmlspecialchars($_POST['start_time'] ?? $default_start); ?>" 
                                   required>
                            <div class="form-text">Bidding starts automatically at this moment.</div>
                        </div>

                        <div class="col-md-6">
                            <label for="endTime" class="form-label fw-semibold text-secondary small">
                                Auction End Date & Time <span class="text-danger">*</span>
                            </label>
                            <input type="datetime-local" 
                                   class="form-control" 
                                   id="endTime" 
                                   name="end_time" 
                                   value="<?php echo htmlspecialchars($_POST['end_time'] ?? $default_end); ?>" 
                                   required>
                            <div class="form-text">The auction closes and highest bidder wins.</div>
                        </div>
                    </div>
                </div>

                <!-- Section 4: Location Details -->
                <div class="mb-4">
                    <h5 class="fw-bold text-dark border-bottom pb-2 mb-3">
                        <i class="bi bi-geo-alt text-danger me-2"></i>Item Location
                    </h5>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="city" class="form-label fw-semibold text-secondary small">
                                City <span class="text-danger">*</span>
                            </label>
                            <input type="text" 
                                   class="form-control" 
                                   id="city" 
                                   name="city" 
                                   value="<?php echo htmlspecialchars($_POST['city'] ?? ''); ?>" 
                                   placeholder="e.g., Karachi, Lahore, Islamabad" 
                                   required>
                        </div>

                        <div class="col-md-6">
                            <label for="area" class="form-label fw-semibold text-secondary small">
                                Area / Neighborhood <span class="text-danger">*</span>
                            </label>
                            <input type="text" 
                                   class="form-control" 
                                   id="area" 
                                   name="area" 
                                   value="<?php echo htmlspecialchars($_POST['area'] ?? ''); ?>" 
                                   placeholder="e.g., DHA Phase 6, Gulshan, Blue Area" 
                                   required>
                        </div>
                    </div>
                </div>

                <!-- Section 5: Image Uploads -->
                <div class="mb-4">
                    <h5 class="fw-bold text-dark border-bottom pb-2 mb-3">
                        <i class="bi bi-images text-info me-2"></i>Item Images (Up to 5 images) <span class="text-danger">*</span>
                    </h5>
                    <div class="p-4 border border-2 border-dashed rounded-3 bg-light text-center" style="border-color: #cbd5e1 !important;">
                        <i class="bi bi-cloud-arrow-up fs-1 text-muted mb-2 d-block"></i>
                        <input type="file" 
                               class="form-control d-none" 
                               id="itemImagesInput" 
                               name="item_images[]" 
                               accept="image/jpeg,image/png,image/webp" 
                               multiple 
                               required>
                        <label for="itemImagesInput" class="btn btn-primary px-4 py-2 shadow-sm" style="cursor: pointer;">
                            <i class="bi bi-folder2-open me-1"></i> Choose Images
                        </label>
                        <p class="text-muted small mt-2 mb-0">
                            Supported formats: <strong>JPG, PNG, WEBP</strong> &bull; Maximum file size: <strong>5MB per image</strong> &bull; First image will be primary thumbnail
                        </p>
                    </div>

                    <!-- Live Image Previews -->
                    <div id="imagePreviewContainer" class="d-flex flex-wrap gap-3 mt-3"></div>
                </div>

                <!-- Submission CTA -->
                <div class="d-grid gap-2 d-md-flex justify-content-md-end pt-3 border-top">
                    <a href="my_auctions.php" class="btn btn-light px-4 py-2">Cancel</a>
                    <!-- Trigger Button -->
                    <button type="submit" name="submit_item" id="createBtn" class="btn btn-success px-5 py-2 fw-semibold shadow-sm d-inline-flex align-items-center gap-2">
                        <i class="bi bi-check2-circle fs-5"></i>
                        <span>Create Auction Listing</span>
                    </button>
                </div>

            </form>
        </div>
    </div>
</div>

<!-- Custom Popup / Modal -->
<div id="customModal" class="modal-overlay hidden">
  <div class="modal-box">
    <div class="modal-header">
      <h3 id="customModalTitle">Created Successfully!</h3>
      <button class="close-x" onclick="closePopup()">&times;</button>
    </div>
    <div class="modal-body" id="customModalBody">
      <p>Aapka naya item kamyabi se create ho chuka hai.</p>
    </div>
    <div class="modal-footer" id="customModalFooter">
      <button class="btn secondary" onclick="closePopup()">OK</button>
    </div>
  </div>
</div>

<!-- JavaScript for Live Image Previews, Custom Modal and Create Handling -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const input = document.getElementById('itemImagesInput');
    const previewContainer = document.getElementById('imagePreviewContainer');
    const modal = document.getElementById("customModal");
    const createBtn = document.getElementById("createBtn");
    const auctionForm = document.getElementById("auctionForm");
    let dt = new DataTransfer();

    // -------------------------------------------------------------
    // Custom Modal Open & Close Functions
    // -------------------------------------------------------------
    window.openPopup = function(title, message, options = {}) {
        if (!modal) return;

        const titleEl = document.getElementById("customModalTitle");
        const bodyEl = document.getElementById("customModalBody");
        const footerEl = document.getElementById("customModalFooter");

        if (title && titleEl) titleEl.innerHTML = title;
        if (message && bodyEl) bodyEl.innerHTML = typeof message === 'string' ? `<p>${message}</p>` : message;

        if (footerEl) {
            if (options && options.isConfirm) {
                footerEl.innerHTML = `
                    <button type="button" class="btn secondary" id="modalCancelBtn">${options.cancelText || 'Cancel'}</button>
                    <button type="button" class="btn primary" id="modalConfirmBtn">${options.confirmText || 'Confirm'}</button>
                `;
                const cancelBtn = document.getElementById("modalCancelBtn");
                const confirmBtn = document.getElementById("modalConfirmBtn");
                if (cancelBtn) {
                    cancelBtn.onclick = () => {
                        closePopup();
                        if (typeof options.onCancel === 'function') options.onCancel();
                    };
                }
                if (confirmBtn) {
                    confirmBtn.onclick = () => {
                        closePopup();
                        if (typeof options.onConfirm === 'function') options.onConfirm();
                    };
                }
            } else if (options && (options.okText || options.onConfirm)) {
                footerEl.innerHTML = `
                    <button type="button" class="btn primary" id="modalOkBtn">${options.okText || 'OK'}</button>
                `;
                const okBtn = document.getElementById("modalOkBtn");
                if (okBtn) {
                    okBtn.onclick = () => {
                        closePopup();
                        if (typeof options.onConfirm === 'function') options.onConfirm();
                    };
                }
            } else {
                footerEl.innerHTML = `<button type="button" class="btn secondary" onclick="closePopup()">OK</button>`;
            }
        }

        modal.classList.remove("hidden");
        document.body.style.overflow = "hidden";
    };

    window.closePopup = function() {
        if (modal) {
            modal.classList.add("hidden");
            document.body.style.overflow = "";
        }
    };

    // Click outside modal to close
    modal.addEventListener("click", (e) => {
        if (e.target === modal) closePopup();
    });

    // Close on Escape key
    document.addEventListener("keydown", (e) => {
        if (e.key === "Escape" && modal && !modal.classList.contains("hidden")) {
            closePopup();
        }
    });

    // -------------------------------------------------------------
    // Image Handling with Custom Modal on Limit Reached
    // -------------------------------------------------------------
    input.addEventListener('change', function(e) {
        // Append new files up to 5 total
        for (let file of this.files) {
            if (dt.items.length < 5) {
                dt.items.add(file);
            } else {
                // Replaced default browser alert() with custom modal
                openPopup("Image Limit Reached", "Maximum 5 images allowed for an auction listing.");
                break;
            }
        }
        input.files = dt.files;
        renderThumbnails();
    });

    function renderThumbnails() {
        previewContainer.innerHTML = '';
        if (dt.files.length === 0) return;

        Array.from(dt.files).forEach((file, index) => {
            const reader = new FileReader();
            reader.onload = function(e) {
                const col = document.createElement('div');
                col.className = 'position-relative border rounded overflow-hidden shadow-sm bg-white';
                col.style.width = '100px';
                col.style.height = '100px';

                const img = document.createElement('img');
                img.src = e.target.result;
                img.className = 'w-100 h-100 object-fit-cover';
                img.alt = file.name;

                // Primary badge for 1st image
                if (index === 0) {
                    const badge = document.createElement('span');
                    badge.className = 'badge bg-success position-absolute bottom-0 start-0 m-1';
                    badge.style.fontSize = '10px';
                    badge.innerText = 'Primary';
                    col.appendChild(badge);
                }

                // Remove button
                const removeBtn = document.createElement('button');
                removeBtn.type = 'button';
                removeBtn.className = 'btn btn-sm btn-danger position-absolute top-0 end-0 p-0 d-flex align-items-center justify-content-center m-1 rounded-circle';
                removeBtn.style.width = '20px';
                removeBtn.style.height = '20px';
                removeBtn.innerHTML = '&times;';
                removeBtn.title = 'Remove image';
                removeBtn.onclick = function() {
                    dt.items.remove(index);
                    input.files = dt.files;
                    renderThumbnails();
                };

                col.appendChild(img);
                col.appendChild(removeBtn);
                previewContainer.appendChild(col);
            };
            reader.readAsDataURL(file);
        });
    }

    // -------------------------------------------------------------
    // Trigger on Create Action: Replaced default confirm() & alert()
    // -------------------------------------------------------------
    if (createBtn && auctionForm) {
        createBtn.addEventListener("click", function(e) {
            // Check form validity first
            if (!auctionForm.checkValidity()) {
                auctionForm.reportValidity();
                return;
            }

            // Require at least one image upload
            if (dt.files.length === 0) {
                e.preventDefault();
                openPopup("Image Required", "Please upload at least one image of the auction item before creating.");
                return;
            }

            // Replaced default browser confirm() with custom modal dialog
            e.preventDefault();
            openPopup("Confirm Auction Creation", "Are you sure you want to publish this new auction listing?", {
                isConfirm: true,
                confirmText: "Yes, Create",
                onConfirm: function() {
                    createBtn.disabled = true;
                    createBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Publishing...';
                    auctionForm.submit();
                }
            });
        });
    }

    <?php if (!empty($success_msg)): ?>
    // Auto-trigger on successful creation
    openPopup(
        "Created Successfully!",
        "Aapka naya item kamyabi se create ho chuka hai.<br><small class=\"text-muted d-block mt-2\"><?= htmlspecialchars($success_msg); ?></small>",
        {
            okText: "View My Auctions",
            onConfirm: function() {
                window.location.href = "my_auctions.php";
            }
        }
    );
    <?php endif; ?>
});
</script>

<?php
include_once __DIR__ . '/../includes/footer.php';
?>