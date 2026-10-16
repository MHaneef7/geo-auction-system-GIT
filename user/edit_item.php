<?php
/**
 * Edit Auction Item - Geo Auction System
 * File: user/edit_item.php
 *
 * Implements full Object-Oriented PDO database operations, CSRF protection,
 * role-based access control (Owner Seller or Admin), auction safety controls,
 * multi-image management, server-side validation, and Bootstrap 5 responsive UI.
 *
 * ============================================================================
 * SQL SCHEMA REFERENCE (categories & items tables):
 * ============================================================================
 * CREATE TABLE IF NOT EXISTS categories (
 *     id INT AUTO_INCREMENT PRIMARY KEY,
 *     name VARCHAR(100) NOT NULL UNIQUE,
 *     slug VARCHAR(100) NOT NULL UNIQUE,
 *     description TEXT NULL,
 *     icon VARCHAR(50) NULL DEFAULT 'fa-tag',
 *     status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
 *     sort_order INT NOT NULL DEFAULT 0,
 *     created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 *     updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
 * ) ENGINE=InnoDB;
 *
 * CREATE TABLE IF NOT EXISTS items (
 *     id INT AUTO_INCREMENT PRIMARY KEY,
 *     auction_code VARCHAR(20) NOT NULL UNIQUE,
 *     title VARCHAR(255) NOT NULL,
 *     slug VARCHAR(255) NOT NULL,
 *     category_id INT NULL,
 *     seller_id INT NOT NULL,
 *     starting_price DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
 *     current_price DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
 *     reserve_price DECIMAL(12, 2) NULL,
 *     min_increment DECIMAL(12, 2) NOT NULL DEFAULT 100.00,
 *     buy_it_now_price DECIMAL(12, 2) NULL,
 *     status ENUM('pending', 'active', 'paused', 'ended', 'cancelled', 'sold') NOT NULL DEFAULT 'active',
 *     start_time DATETIME NOT NULL,
 *     end_time DATETIME NOT NULL,
 *     created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 *     updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 *     FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
 *     FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE CASCADE
 * ) ENGINE=InnoDB;
 * ============================================================================
 */

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

// -----------------------------------------------------------------------------
// 1. Authentication & Authorization Check
// -----------------------------------------------------------------------------
// Prevent back-button caching of protected edit page
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

if (empty($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

$current_user_id = (int)$_SESSION['user_id'];
$user_role       = strtolower(trim($_SESSION['role'] ?? $_SESSION['user_role'] ?? 'seller'));
$is_admin        = ($user_role === 'admin');

// -----------------------------------------------------------------------------
// 2. PDO Database Initialization (Object-Oriented, No Procedural Calls)
// -----------------------------------------------------------------------------
require_once __DIR__ . '/../includes/database.php';
try {
    $pdo = Database::getPdo();
} catch (\Throwable $e) {
    $db_host = 'localhost';
    $db_name = 'geo_auction_db';
    $db_user = 'root';
    $db_pass = '';
    try {
        $pdo = new PDO("mysql:host={$db_host};dbname={$db_name};charset=utf8mb4", $db_user, $db_pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (PDOException $e2) {
        error_log("Database connection failed in edit_item.php: " . $e2->getMessage());
        die("Database service temporarily unavailable. Please try again later.");
    }
}

// -----------------------------------------------------------------------------
// 3. CSRF Protection Token Setup
// -----------------------------------------------------------------------------
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Helper to verify CSRF token
if (!function_exists('verify_csrf_token')) {
    function verify_csrf_token(?string $token): bool {
        return !empty($token) && !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
}

// Helper to safely format category Font Awesome icon class
if (!function_exists('format_category_icon')) {
    function format_category_icon(?string $icon): string {
        $icon = trim((string)$icon);
        if ($icon === '') {
            return 'fa-solid fa-tags';
        }
        if (preg_match('/^(fas|far|fab|fa-solid|fa-regular|fa-brands)\s+/i', $icon)) {
            return $icon;
        }
        if (strpos($icon, 'fa-') === 0) {
            return 'fa-solid ' . $icon;
        }
        return 'fa-solid fa-' . $icon;
    }
}

// Helper to safely encode HTML strings under strict_types=1 without TypeError on null
if (!function_exists('safe_html')) {
    function safe_html(mixed $str): string {
        return htmlspecialchars((string)($str ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

// -----------------------------------------------------------------------------
// 4. Fetch Item ID and Verify Existence & Permissions
// -----------------------------------------------------------------------------
$item_id = (int)($_GET['id'] ?? ($_POST['item_id'] ?? 0));
$error_msg   = '';
$success_msg = '';

if ($item_id <= 0) {
    $item = null;
    $error_msg = 'Invalid or missing auction item identifier.';
} else {
    try {
        $stmt = $pdo->prepare("
            SELECT i.*, u.name AS seller_name, u.email AS seller_email 
            FROM items i 
            LEFT JOIN users u ON u.id = i.seller_id 
            WHERE i.id = :id 
            LIMIT 1
        ");
        $stmt->execute([':id' => $item_id]);
        $item = $stmt->fetch();

        if (!$item) {
            $error_msg = 'The requested auction lot could not be found.';
        } elseif (!$is_admin && (int)$item['seller_id'] !== $current_user_id) {
            // Seller can only edit their own listings
            $item = null;
            $error_msg = 'You are not authorized to edit this auction item.';
        }
    } catch (PDOException $e) {
        error_log("Failed to fetch auction lot #{$item_id}: " . $e->getMessage());
        $item = null;
        $error_msg = 'An unexpected database error occurred. Please try again.';
    }
}

// -----------------------------------------------------------------------------
// 5. Check Active Bids & Auction Safety Constraints
// -----------------------------------------------------------------------------
$has_bids = false;
$bids_count = 0;

if ($item) {
    try {
        $stmt_bids = $pdo->prepare("
            SELECT COUNT(*) 
            FROM bids 
            WHERE item_id = :item_id 
              AND (bid_status != 'cancelled' OR bid_status IS NULL)
        ");
        $stmt_bids->execute([':item_id' => $item_id]);
        $bids_count = (int)$stmt_bids->fetchColumn();

        if ((int)$item['bid_count'] > $bids_count) {
            $bids_count = (int)$item['bid_count'];
        }
        $has_bids = ($bids_count > 0);
    } catch (PDOException $e) {
        error_log("Bids check failed for item #{$item_id}: " . $e->getMessage());
        $bids_count = (int)($item['bid_count'] ?? 0);
        $has_bids   = ($bids_count > 0);
    }
}

if ($item && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = trim($_POST['action'] ?? '');

    // A. Delete Existing Image
    if ($action === 'delete_image') {
        $req_token = $_POST['csrf_token'] ?? '';
        $img_id    = (int)($_POST['image_id'] ?? 0);

        if (!verify_csrf_token($req_token)) {
            $res = ['success' => false, 'message' => 'Security token expired. Please refresh and try again.'];
        } elseif ($img_id <= 0) {
            $res = ['success' => false, 'message' => 'Invalid image identifier.'];
        } else {
            try {
                // Verify image belongs to this item
                $stmt_img = $pdo->prepare("SELECT * FROM item_images WHERE id = :id AND item_id = :item_id LIMIT 1");
                $stmt_img->execute([':id' => $img_id, ':item_id' => $item_id]);
                $img_row = $stmt_img->fetch();

                if (!$img_row) {
                    $res = ['success' => false, 'message' => 'Image not found or access denied.'];
                } else {
                    $pdo->beginTransaction();

                    // Delete physical file safely
                    $raw_path = ltrim((string)$img_row['image_path'], '/\\');
                    $file_on_disk = __DIR__ . '/../' . $raw_path;
                    if (file_exists($file_on_disk) && is_file($file_on_disk)) {
                        @unlink($file_on_disk);
                    }

                    // Remove DB record
                    $pdo->prepare("DELETE FROM item_images WHERE id = :id")->execute([':id' => $img_id]);

                    // Reassign primary image if deleted was primary
                    if ((int)$img_row['is_primary'] === 1 || $item['featured_image'] === $img_row['image_path']) {
                        $stmt_next = $pdo->prepare("SELECT image_path, id FROM item_images WHERE item_id = :item_id ORDER BY id ASC LIMIT 1");
                        $stmt_next->execute([':item_id' => $item_id]);
                        $next_img = $stmt_next->fetch();

                        if ($next_img) {
                            $pdo->prepare("UPDATE item_images SET is_primary = 1 WHERE id = :id")->execute([':id' => $next_img['id']]);
                            $pdo->prepare("UPDATE items SET featured_image = :path WHERE id = :item_id")->execute([
                                ':path'    => $next_img['image_path'],
                                ':item_id' => $item_id
                            ]);
                        } else {
                            $pdo->prepare("UPDATE items SET featured_image = NULL WHERE id = :item_id")->execute([':item_id' => $item_id]);
                        }
                    }

                    // Sync items.images JSON array
                    $stmt_all = $pdo->prepare("SELECT image_path FROM item_images WHERE item_id = :item_id ORDER BY is_primary DESC, id ASC");
                    $stmt_all->execute([':item_id' => $item_id]);
                    $all_paths = $stmt_all->fetchAll(PDO::FETCH_COLUMN);

                    $pdo->prepare("UPDATE items SET images = :images WHERE id = :item_id")->execute([
                        ':images'  => !empty($all_paths) ? json_encode(array_values($all_paths)) : null,
                        ':item_id' => $item_id
                    ]);

                    $pdo->commit();
                    $res = ['success' => true, 'message' => 'Image removed successfully.'];
                }
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) { $pdo->rollBack(); }
                error_log("Image deletion error: " . $e->getMessage());
                $res = ['success' => false, 'message' => 'Database error while removing image.'];
            }
        }

        // Return JSON if AJAX or reload page
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode($res);
            exit();
        } else {
            if ($res['success']) {
                $success_msg = $res['message'];
            } else {
                $error_msg = $res['message'];
            }
        }
    }

    // B. Set Image as Primary / Featured
    if ($action === 'set_primary_image') {
        $req_token = $_POST['csrf_token'] ?? '';
        $img_id    = (int)($_POST['image_id'] ?? 0);

        if (!verify_csrf_token($req_token)) {
            $error_msg = 'Security token expired. Please refresh and try again.';
        } elseif ($img_id > 0) {
            try {
                $stmt_img = $pdo->prepare("SELECT * FROM item_images WHERE id = :id AND item_id = :item_id LIMIT 1");
                $stmt_img->execute([':id' => $img_id, ':item_id' => $item_id]);
                $target_img = $stmt_img->fetch();

                if ($target_img) {
                    $pdo->beginTransaction();
                    $pdo->prepare("UPDATE item_images SET is_primary = 0 WHERE item_id = :item_id")->execute([':item_id' => $item_id]);
                    $pdo->prepare("UPDATE item_images SET is_primary = 1 WHERE id = :id")->execute([':id' => $img_id]);
                    $pdo->prepare("UPDATE items SET featured_image = :path WHERE id = :item_id")->execute([
                        ':path'    => $target_img['image_path'],
                        ':item_id' => $item_id
                    ]);
                    $pdo->commit();
                    $success_msg = 'Primary featured image updated successfully.';
                } else {
                    $error_msg = 'Selected image not found.';
                }
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) { $pdo->rollBack(); }
                error_log("Set primary image error: " . $e->getMessage());
                $error_msg = 'Could not update primary image.';
            }
        }
    }

    // C. Form Submission: Update Item Specifications
    if ($action === 'update_item' || isset($_POST['save_item'])) {
        $req_token = $_POST['csrf_token'] ?? '';

        if (!verify_csrf_token($req_token)) {
            $error_msg = 'Security token invalid or expired. Please refresh the page.';
        } else {
            // 1. Sanitize text & numeric inputs
            $title             = trim($_POST['title'] ?? '');
            $category_id       = (int)($_POST['category_id'] ?? 0);
            $short_description = trim($_POST['short_description'] ?? '');
            $description       = trim($_POST['description'] ?? '');
            $buy_it_now_price  = (isset($_POST['buy_it_now_price']) && $_POST['buy_it_now_price'] !== '') ? (float)$_POST['buy_it_now_price'] : null;
            $condition_type    = trim($_POST['condition_type'] ?? '');
            $brand             = trim($_POST['brand'] ?? '');
            $model             = trim($_POST['model'] ?? '');
            $year              = (isset($_POST['year']) && $_POST['year'] !== '') ? (int)$_POST['year'] : null;
            $mileage           = trim($_POST['mileage'] ?? '');
            $engine_type       = trim($_POST['engine_type'] ?? '');
            $color             = trim($_POST['color'] ?? '');
            $location_address  = trim($_POST['location_address'] ?? '');
            $city              = trim($_POST['city'] ?? '');
            $area              = trim($_POST['area'] ?? '');
            $location_state    = trim($_POST['location_state'] ?? '');
            $location_country  = trim($_POST['location_country'] ?? 'Pakistan');
            $location_lat      = (isset($_POST['location_lat']) && $_POST['location_lat'] !== '') ? (float)$_POST['location_lat'] : null;
            $location_lng      = (isset($_POST['location_lng']) && $_POST['location_lng'] !== '') ? (float)$_POST['location_lng'] : null;
            $status            = trim($_POST['status'] ?? 'active');
            $featured          = isset($_POST['featured']) ? 1 : 0;
            $approved          = isset($_POST['approved']) ? 1 : 0;
            $meta_title        = trim($_POST['meta_title'] ?? '');
            $meta_description  = trim($_POST['meta_description'] ?? '');
            $meta_keywords     = trim($_POST['meta_keywords'] ?? '');

            // Bidding integrity preservation
            if ($has_bids) {
                // Keep original prices and start time locked
                $starting_price = (float)$item['starting_price'];
                $reserve_price  = $item['reserve_price'] !== null ? (float)$item['reserve_price'] : null;
                $min_increment  = (float)$item['min_increment'];
                $start_time_mysql = $item['start_time'];
            } else {
                $starting_price = (float)($_POST['starting_price'] ?? $item['starting_price']);
                $reserve_price  = (isset($_POST['reserve_price']) && $_POST['reserve_price'] !== '') ? (float)$_POST['reserve_price'] : null;
                $min_increment  = (isset($_POST['min_increment']) && $_POST['min_increment'] !== '') ? (float)$_POST['min_increment'] : 100.00;
                
                $start_raw = trim($_POST['start_time'] ?? '');
                $start_time_mysql = str_replace('T', ' ', $start_raw);
                if (strlen($start_time_mysql) === 16) { $start_time_mysql .= ':00'; }
            }

            $end_raw = trim($_POST['end_time'] ?? '');
            $end_time_mysql = str_replace('T', ' ', $end_raw);
            if (strlen($end_time_mysql) === 16) { $end_time_mysql .= ':00'; }

            // 2. Server-side Validations
            $allowed_statuses = ['pending', 'active', 'paused', 'ended', 'cancelled', 'sold'];
            if (!$is_admin && in_array($status, ['ended', 'sold'])) {
                // Non-admins can only toggle active, paused, or cancelled
                $status = $item['status'];
            } elseif (!in_array($status, $allowed_statuses, true)) {
                $status = 'active';
            }

            if (!$is_admin) {
                // Non-admins cannot alter system approval flag
                $approved = (int)$item['approved'];
            }

            if ($title === '') {
                $error_msg = 'Please enter an item title.';
            } elseif (mb_strlen($title) < 5 || mb_strlen($title) > 255) {
                $error_msg = 'Item title must be between 5 and 255 characters.';
            } elseif ($category_id <= 0) {
                $error_msg = 'Please select a valid auction category.';
            } elseif ($description === '' || mb_strlen($description) < 10) {
                $error_msg = 'Please provide a detailed item description (minimum 10 characters).';
            } elseif ($city === '') {
                $error_msg = 'Please specify the item location city.';
            } elseif (!$has_bids && $starting_price <= 0) {
                $error_msg = 'Starting price must be greater than zero.';
            } elseif (!$has_bids && $reserve_price !== null && $reserve_price < $starting_price) {
                $error_msg = 'Reserve price cannot be less than the starting bid price.';
            } elseif (!$has_bids && $min_increment <= 0) {
                $error_msg = 'Minimum bid increment step must be greater than zero.';
            } elseif ($buy_it_now_price !== null && $buy_it_now_price <= $starting_price) {
                $error_msg = 'Buy It Now price must be strictly greater than the starting bid price.';
            } elseif ($year !== null && ($year < 1900 || $year > ((int)date('Y') + 2))) {
                $error_msg = 'Please enter a valid manufactured year.';
            } else {
                // Timeline validations
                $start_ts = strtotime($start_time_mysql);
                $end_ts   = strtotime($end_time_mysql);

                if ($start_ts === false || $end_ts === false) {
                    $error_msg = 'Invalid date/time format for auction schedule.';
                } elseif ($end_ts <= $start_ts) {
                    $error_msg = 'Auction end date/time must be strictly after the start date/time.';
                } elseif ($end_ts <= time() && $status === 'active') {
                    $error_msg = 'An active auction must have an end date/time in the future.';
                }
            }

            // Verify category exists in database
            if ($error_msg === '' && $category_id > 0) {
                $cat_chk = $pdo->prepare("SELECT id FROM categories WHERE id = :cid LIMIT 1");
                $cat_chk->execute([':cid' => $category_id]);
                if (!$cat_chk->fetch()) {
                    $error_msg = 'The selected auction category does not exist.';
                }
            }

            // 3. Process New Image Uploads (Up to 5 total images)
            $newly_uploaded_paths = [];
            if ($error_msg === '' && isset($_FILES['new_images']) && !empty($_FILES['new_images']['name'][0])) {
                // Count current existing images
                $stmt_count_imgs = $pdo->prepare("SELECT COUNT(*) FROM item_images WHERE item_id = :item_id");
                $stmt_count_imgs->execute([':item_id' => $item_id]);
                $existing_img_count = (int)$stmt_count_imgs->fetchColumn();

                $files = $_FILES['new_images'];
                $upload_count = count($files['name']);

                if (($existing_img_count + $upload_count) > 5) {
                    $error_msg = "You can have a maximum of 5 images per lot. Current: {$existing_img_count}. Attempted: {$upload_count}.";
                } else {
                    $upload_dir = __DIR__ . '/../uploads/items/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }

                    $allowed_exts  = ['jpg', 'jpeg', 'png', 'webp', 'jfif', 'pjpeg', 'gif', 'avif'];
                    $allowed_mimes = ['image/jpeg', 'image/pjpeg', 'image/png', 'image/x-png', 'image/webp', 'image/gif', 'image/avif', 'image/x-jfif'];

                    for ($i = 0; $i < $upload_count; $i++) {
                        if ($files['error'][$i] === UPLOAD_ERR_OK) {
                            $tmp_path  = $files['tmp_name'][$i];
                            $orig_name = $files['name'][$i];
                            $file_size = $files['size'][$i];
                            $file_ext  = strtolower(pathinfo($orig_name, PATHINFO_EXTENSION));

                            if (!in_array($file_ext, $allowed_exts, true)) {
                                $error_msg = "File '{$orig_name}' has an unsupported extension. Allowed: JPG, PNG, WEBP, JFIF, GIF.";
                                break;
                            }
                            if ($file_size > 8 * 1024 * 1024) {
                                $error_msg = "File '{$orig_name}' exceeds the 8MB size limit.";
                                break;
                            }

                            $mime = '';
                            if (class_exists('finfo')) {
                                $finfo = new finfo(FILEINFO_MIME_TYPE);
                                $mime  = $finfo->file($tmp_path);
                            }
                            $img_info = @getimagesize($tmp_path);
                            if (!empty($img_info['mime']) && empty($mime)) {
                                $mime = $img_info['mime'];
                            }

                            if (!in_array($mime, $allowed_mimes, true) && $img_info === false) {
                                $error_msg = "Security check failed: File '{$orig_name}' is not a valid image.";
                                break;
                            }

                            // Generate clean, collision-free filename
                            $normalized_ext = match($file_ext) {
                                'jpeg', 'jfif', 'pjpeg' => 'jpg',
                                'png' => 'png',
                                'webp' => 'webp',
                                'gif' => 'gif',
                                'avif' => 'avif',
                                default => 'jpg'
                            };
                            $new_filename = 'item_' . $item_id . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $normalized_ext;
                            $target_filepath = $upload_dir . $new_filename;

                            if (move_uploaded_file($tmp_path, $target_filepath)) {
                                $newly_uploaded_paths[] = 'uploads/items/' . $new_filename;
                            } else {
                                $error_msg = "Failed to upload '{$orig_name}' to server storage.";
                                break;
                            }
                        }
                    }
                }
            }

            // 4. Execute Atomic Database Update
            if ($error_msg === '') {
                try {
                    $pdo->beginTransaction();

                    // Dynamically filter update columns based on table schema
                    $colStmt = $pdo->query("SHOW COLUMNS FROM items");
                    $existingItemCols = $colStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

                    $potentialCols = [
                        'title'             => $title,
                        'category_id'       => $category_id,
                        'short_description' => $short_description !== '' ? $short_description : null,
                        'description'       => $description,
                        'starting_price'    => $starting_price,
                        'reserve_price'     => $reserve_price,
                        'min_increment'     => $min_increment,
                        'buy_it_now_price'  => $buy_it_now_price,
                        'condition_type'    => $condition_type !== '' ? $condition_type : null,
                        'brand'             => $brand !== '' ? $brand : null,
                        'model'             => $model !== '' ? $model : null,
                        'year'              => $year,
                        'mileage'           => $mileage !== '' ? $mileage : null,
                        'engine_type'       => $engine_type !== '' ? $engine_type : null,
                        'color'             => $color !== '' ? $color : null,
                        'location_address'  => $location_address !== '' ? $location_address : null,
                        'city'              => $city,
                        'location_city'     => $city,
                        'area'              => $area !== '' ? $area : null,
                        'location_state'    => $location_state !== '' ? $location_state : null,
                        'location_country'  => $location_country !== '' ? $location_country : 'Pakistan',
                        'location_lat'      => $location_lat,
                        'location_lng'      => $location_lng,
                        'start_time'        => $start_time_mysql,
                        'end_time'          => $end_time_mysql,
                        'status'            => $status,
                        'featured'          => $featured,
                        'approved'          => $approved,
                        'meta_title'        => $meta_title !== '' ? $meta_title : null,
                        'meta_description'  => $meta_description !== '' ? $meta_description : null,
                        'meta_keywords'     => $meta_keywords !== '' ? $meta_keywords : null,
                    ];

                    $setClauses = [];
                    $update_params = [':id' => $item_id];
                    foreach ($potentialCols as $colName => $val) {
                        if (in_array($colName, $existingItemCols, true)) {
                            $paramKey = ':' . $colName;
                            $setClauses[] = "{$colName} = {$paramKey}";
                            $update_params[$paramKey] = $val;
                        }
                    }
                    if (in_array('updated_at', $existingItemCols, true)) {
                        $setClauses[] = "updated_at = NOW()";
                    }

                    $update_sql = "UPDATE items SET " . implode(', ', $setClauses) . " WHERE id = :id";
                    $stmt_update = $pdo->prepare($update_sql);
                    $stmt_update->execute($update_params);

                    // Insert newly uploaded images into item_images
                    if (!empty($newly_uploaded_paths)) {
                        $stmt_insert_img = $pdo->prepare("
                            INSERT INTO item_images (item_id, image_path, is_primary, created_at) 
                            VALUES (:item_id, :image_path, :is_primary, NOW())
                        ");

                        foreach ($newly_uploaded_paths as $idx => $n_path) {
                            $is_p = ($idx === 0 && empty($item['featured_image'])) ? 1 : 0;
                            $stmt_insert_img->execute([
                                ':item_id'    => $item_id,
                                ':image_path' => $n_path,
                                ':is_primary' => $is_p,
                            ]);
                        }

                        // If featured image was blank, update items.featured_image
                        if (empty($item['featured_image']) && !empty($newly_uploaded_paths[0])) {
                            $pdo->prepare("UPDATE items SET featured_image = :feat WHERE id = :id")->execute([
                                ':feat' => $newly_uploaded_paths[0],
                                ':id'   => $item_id,
                            ]);
                        }
                    }

                    // Sync items.images JSON column
                    $stmt_all_imgs = $pdo->prepare("SELECT image_path FROM item_images WHERE item_id = :item_id ORDER BY is_primary DESC, id ASC");
                    $stmt_all_imgs->execute([':item_id' => $item_id]);
                    $all_sync_paths = $stmt_all_imgs->fetchAll(PDO::FETCH_COLUMN);

                    $pdo->prepare("UPDATE items SET images = :images WHERE id = :id")->execute([
                        ':images' => !empty($all_sync_paths) ? json_encode(array_values($all_sync_paths)) : null,
                        ':id'     => $item_id,
                    ]);

                    $pdo->commit();
                    $success_msg = 'Item updated successfully.';

                    // Refresh item data from DB
                    $stmt_refresh = $pdo->prepare("SELECT * FROM items WHERE id = :id LIMIT 1");
                    $stmt_refresh->execute([':id' => $item_id]);
                    $item = $stmt_refresh->fetch();

                } catch (PDOException $e) {
                    if ($pdo->inTransaction()) { $pdo->rollBack(); }
                    error_log("Item update error for #{$item_id}: " . $e->getMessage());
                    $error_msg = 'Failed to update auction lot due to a database conflict.';

                    // Clean up newly moved files on DB failure
                    foreach ($newly_uploaded_paths as $f_del) {
                        $f_real = __DIR__ . '/../' . $f_del;
                        if (file_exists($f_real)) { @unlink($f_real); }
                    }
                }
            } else {
                // Validation error: clean up newly moved files
                foreach ($newly_uploaded_paths as $f_del) {
                    $f_real = __DIR__ . '/../' . $f_del;
                    if (file_exists($f_real)) { @unlink($f_real); }
                }
            }
        }
    }
}

// -----------------------------------------------------------------------------
// 7. Load Categories & Existing Images for Rendering
// -----------------------------------------------------------------------------
$categories_list = [];
$existing_images = [];

if ($item) {
    try {
        $current_item_cat_id = (int)($item['category_id'] ?? 0);
        $stmt_cats = $pdo->prepare("
            SELECT id, name, slug, description, icon, status 
            FROM categories 
            WHERE status = 'active' OR id = :current_cat_id 
            ORDER BY sort_order ASC, name ASC
        ");
        $stmt_cats->execute([':current_cat_id' => $current_item_cat_id]);
        $categories_list = $stmt_cats->fetchAll();

        // Existing images from item_images
        $stmt_img_list = $pdo->prepare("SELECT * FROM item_images WHERE item_id = :id ORDER BY is_primary DESC, id ASC");
        $stmt_img_list->execute([':id' => $item_id]);
        $existing_images = $stmt_img_list->fetchAll();

        // Backfill if item_images table was empty but items.featured_image or items.images had paths
        if (empty($existing_images) && (!empty($item['featured_image']) || !empty($item['images']))) {
            $discovered_paths = [];
            if (!empty($item['featured_image'])) {
                $discovered_paths[] = $item['featured_image'];
            }
            if (!empty($item['images'])) {
                $dec = json_decode((string)$item['images'], true);
                if (is_array($dec)) {
                    foreach ($dec as $p) {
                        $clean = trim((string)$p, " \t\n\r\0\x0B\"'[]\\");
                        if ($clean !== '' && !in_array($clean, $discovered_paths, true)) {
                            $discovered_paths[] = $clean;
                        }
                    }
                }
            }

            foreach ($discovered_paths as $idx => $d_path) {
                try {
                    $pdo->prepare("INSERT INTO item_images (item_id, image_path, is_primary, created_at) VALUES (:item_id, :path, :is_p, NOW())")
                        ->execute([
                            ':item_id' => $item_id,
                            ':path'    => $d_path,
                            ':is_p'    => ($idx === 0) ? 1 : 0
                        ]);
                } catch (PDOException $e) { /* ignore duplicate */ }
            }

            // Re-fetch after backfilling
            $stmt_img_list->execute([':id' => $item_id]);
            $existing_images = $stmt_img_list->fetchAll();
        }

    } catch (PDOException $e) {
        error_log("Failed loading auxiliary data for edit_item.php: " . $e->getMessage());
    }
}

// Format dates for HTML5 datetime-local inputs
$start_val = !empty($item['start_time']) ? date('Y-m-d\TH:i', strtotime($item['start_time'])) : date('Y-m-d\TH:i');
$end_val   = !empty($item['end_time']) ? date('Y-m-d\TH:i', strtotime($item['end_time'])) : date('Y-m-d\TH:i', strtotime('+7 days'));

// Include dashboard header & sidebar
include_once __DIR__ . '/../includes/header.php';
?>

<!-- Bootstrap 5, Bootstrap Icons & FontAwesome 6 CDN -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

<style>
/* Protect layout integrity against Bootstrap 5 conflicts */
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
    background-color: #f8fafc;
    min-height: calc(100vh - 64px);
}
@media (max-width: 992px) {
    .main-content { margin-left: 0 !important; }
}

.emerald-badge {
    background-color: rgba(4, 120, 87, 0.1);
    color: #047857;
    border: 1px solid rgba(4, 120, 87, 0.2);
}
.btn-emerald {
    background-color: #047857;
    border-color: #047857;
    color: #ffffff;
}
.btn-emerald:hover {
    background-color: #065f46;
    border-color: #065f46;
    color: #ffffff;
}
.image-manage-card {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.image-manage-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(0,0,0,0.08);
}
.readonly-lock {
    background-color: #f1f5f9 !important;
    cursor: not-allowed;
}

/* Category Selector Custom Component Styles */
.text-emerald {
    color: #047857 !important;
}
.bg-emerald {
    background-color: #047857 !important;
}
.bg-emerald-light {
    background-color: rgba(4, 120, 87, 0.08) !important;
}
.border-emerald {
    border-color: #047857 !important;
}
.category-select-item {
    transition: all 0.15s ease-in-out;
    cursor: pointer;
}
.category-select-item:hover {
    background-color: rgba(4, 120, 87, 0.08) !important;
    color: #047857 !important;
}
.category-select-item.active-category {
    background-color: rgba(4, 120, 87, 0.12) !important;
}
.category-radio-card {
    transition: all 0.2s ease;
    cursor: pointer;
    user-select: none;
}
.category-radio-card:hover {
    border-color: #047857 !important;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(4, 120, 87, 0.12);
}
.category-radio-card.active-card {
    background-color: #f0fdf4 !important;
    border-color: #047857 !important;
    border-width: 2px !important;
}
</style>
<link rel="stylesheet" href="../assets/css/ai_reserve_price.css">

<div class="container-fluid py-2" style="max-width: 1040px;">

    <?php if (!$item): ?>
        <!-- Item Not Found / Access Denied State -->
        <div class="card border-0 shadow-sm rounded-4 my-5 p-5 text-center bg-white">
            <div class="w-16 h-16 rounded-circle bg-danger bg-opacity-10 text-danger d-inline-flex align-items-center justify-content-center fs-2 mx-auto mb-3" style="width: 64px; height: 64px;">
                <i class="bi bi-shield-x"></i>
            </div>
            <h3 class="fw-bold text-dark mb-2">Access Denied / Lot Not Found</h3>
            <p class="text-muted mb-4"><?= safe_html($error_msg ?: 'The requested auction lot does not exist or you do not have permission to modify it.'); ?></p>
            <div>
                <a href="my_auctions.php" class="btn btn-emerald px-4 py-2 rounded-3 fw-semibold">
                    <i class="bi bi-arrow-left me-1"></i> Back to My Listings
                </a>
            </div>
        </div>
    <?php else: ?>

        <!-- Breadcrumb & Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
            <div>
                <div class="d-flex align-items-center gap-2 mb-1">
                    <span class="badge emerald-badge px-2.5 py-1 rounded-pill text-xs fw-bold">
                        Lot #<?= (int)$item['id']; ?>
                    </span>
                    <span class="badge bg-secondary bg-opacity-10 text-secondary px-2.5 py-1 rounded-pill text-xs fw-bold">
                        <?= safe_html(strtoupper((string)($item['auction_code'] ?? 'LOT'))); ?>
                    </span>
                    <?php if ($is_admin): ?>
                        <span class="badge bg-primary bg-opacity-10 text-primary px-2.5 py-1 rounded-pill text-xs fw-bold">
                            <i class="bi bi-shield-check"></i> Admin Override Mode
                        </span>
                    <?php endif; ?>
                </div>
                <h1 class="h3 fw-bold text-dark mb-0">
                    Edit Auction Listing: <span class="text-truncate text-secondary fw-normal fs-5"><?= safe_html($item['title'] ?? ''); ?></span>
                </h1>
                <p class="text-muted small mb-0">Update item details, adjust scheduling, and manage lot photographs.</p>
            </div>
            <div class="d-flex gap-2">
                <a href="../live_auction.php?id=<?= (int)$item['id']; ?>" target="_blank" class="btn btn-outline-secondary d-inline-flex align-items-center gap-2 rounded-3 text-xs fw-semibold px-3 py-2 shadow-sm" title="Preview Public Page">
                    <i class="bi bi-box-arrow-up-right"></i>
                    <span>View Lot</span>
                </a>
                <a href="my_auctions.php" class="btn btn-outline-secondary d-inline-flex align-items-center gap-2 rounded-3 text-xs fw-semibold px-3 py-2 shadow-sm">
                    <i class="bi bi-arrow-left"></i>
                    <span>Back to My Items</span>
                </a>
            </div>
        </div>

        <!-- Success Alert Message -->
        <?php if ($success_msg !== ''): ?>
            <div class="alert alert-success alert-dismissible fade show d-flex align-items-center gap-3 shadow-sm border-0 mb-4 rounded-3" role="alert" style="background-color: #ecfdf5; border-left: 5px solid #10b981 !important; color: #065f46;">
                <i class="bi bi-check-circle-fill fs-4 text-success flex-shrink-0"></i>
                <div class="flex-grow-1">
                    <strong class="d-block">Success!</strong>
                    <span><?= safe_html($success_msg); ?></span>
                    <div class="mt-2.5 d-flex gap-2">
                        <a href="../live_auction.php?id=<?= (int)$item['id']; ?>" target="_blank" class="btn btn-sm btn-success fw-bold px-3">
                            <i class="bi bi-eye me-1"></i> View Item
                        </a>
                        <a href="my_auctions.php" class="btn btn-sm btn-outline-success fw-bold px-3">
                            <i class="bi bi-grid me-1"></i> Back to My Items
                        </a>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Error Alert Message -->
        <?php if ($error_msg !== ''): ?>
            <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center gap-3 shadow-sm border-0 mb-4 rounded-3" role="alert" style="background-color: #fef2f2; border-left: 5px solid #ef4444 !important; color: #991b1b;">
                <i class="bi bi-exclamation-octagon-fill fs-4 text-danger flex-shrink-0"></i>
                <div>
                    <strong class="d-block">Please correct the highlighted issues:</strong>
                    <span><?= safe_html($error_msg); ?></span>
                </div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Auction Safety Warning: Active Bids Notice -->
        <?php if ($has_bids): ?>
            <div class="alert alert-warning border-0 shadow-sm rounded-3 p-3.5 mb-4 d-flex align-items-center gap-3" style="background-color: #fffbeb; border-left: 5px solid #f59e0b !important; color: #92400e;">
                <i class="bi bi-shield-lock-fill fs-3 text-warning flex-shrink-0"></i>
                <div class="small">
                    <strong>Bidding Active Notice:</strong> This auction has received <strong><?= $bids_count; ?> <?= $bids_count === 1 ? 'bid' : 'bids'; ?></strong>.
                    To safeguard bidder fairness and auction integrity, <strong>Starting Bid</strong>, <strong>Reserve Price</strong>, <strong>Bid Increment</strong>, and <strong>Start Time</strong> are locked from modification.
                </div>
            </div>
        <?php endif; ?>

        <!-- =============================================================== -->
        <!-- Image Management Card                                           -->
        <!-- =============================================================== -->
        <div class="card border-0 shadow-sm rounded-4 mb-4 bg-white overflow-hidden">
            <div class="card-header bg-white border-bottom py-3 px-4 d-flex justify-content-between align-items-center">
                <h5 class="fw-bold text-dark mb-0 d-flex align-items-center gap-2">
                    <i class="bi bi-images text-emerald-700 text-success"></i> Existing Lot Photographs
                    <span class="badge bg-light text-muted fw-semibold ms-1 small"><?= count($existing_images); ?> / 5 Images</span>
                </h5>
                <span class="text-muted small">Max 5 images per lot</span>
            </div>
            <div class="card-body p-4">
                <?php if (!empty($existing_images)): ?>
                    <div class="row g-3 mb-3" id="existingImagesGrid">
                        <?php foreach ($existing_images as $img): 
                            $is_primary_img = ((int)$img['is_primary'] === 1) || ($item['featured_image'] === $img['image_path']);
                            $clean_path = ltrim((string)$img['image_path'], '/\\');
                            $web_path = '../' . $clean_path;
                        ?>
                            <div class="col-6 col-sm-4 col-md-3 col-lg-2.4" id="img-card-<?= (int)$img['id']; ?>">
                                <div class="card h-100 border image-manage-card rounded-3 overflow-hidden position-relative <?= $is_primary_img ? 'border-success ring-2 ring-success' : 'border-slate-200'; ?>">
                                    <div style="height: 130px; background: #0f172a;" class="d-flex align-items-center justify-content-center overflow-hidden">
                                        <img src="<?= safe_html($web_path); ?>" 
                                             alt="Lot Image" 
                                             class="w-100 h-100 object-fit-cover"
                                             onerror="this.onerror=null;this.src='https://images.unsplash.com/photo-1517336714731-489689fd1ca8?auto=format&fit=crop&w=400&q=80'">
                                    </div>
                                    <div class="p-2 bg-light d-flex flex-column gap-1.5 border-top">
                                        <?php if ($is_primary_img): ?>
                                             <span class="badge bg-success text-white py-1 text-center small fw-bold">
                                                <i class="bi bi-star-fill me-1"></i> Featured
                                            </span>
                                        <?php else: ?>
                                            <form method="POST" action="edit_item.php?id=<?= $item_id; ?>" class="d-inline m-0">
                                                <input type="hidden" name="csrf_token" value="<?= safe_html($csrf_token); ?>">
                                                <input type="hidden" name="action" value="set_primary_image">
                                                <input type="hidden" name="image_id" value="<?= (int)$img['id']; ?>">
                                                <button type="submit" class="btn btn-outline-secondary btn-sm w-100 py-0.5 text-xs fw-semibold" title="Set as primary featured image">
                                                    Make Primary
                                                </button>
                                            </form>
                                        <?php endif; ?>

                                        <form method="POST" action="edit_item.php?id=<?= $item_id; ?>" onsubmit="return confirm('Delete this image permanently?');" class="d-inline m-0">
                                            <input type="hidden" name="csrf_token" value="<?= safe_html($csrf_token); ?>">
                                            <input type="hidden" name="action" value="delete_image">
                                            <input type="hidden" name="image_id" value="<?= (int)$img['id']; ?>">
                                            <button type="submit" class="btn btn-outline-danger btn-sm w-100 py-0.5 text-xs fw-semibold">
                                                <i class="bi bi-trash3 me-1"></i> Delete
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="p-3 bg-light rounded-3 text-center text-muted small mb-3">
                        <i class="bi bi-card-image fs-3 d-block mb-1 text-secondary"></i>
                        No images uploaded yet. Use the upload box below to add photographs.
                    </div>
                <?php endif; ?>

                <?php if (count($existing_images) < 5): ?>
                    <div class="border rounded-3 p-3 bg-light bg-opacity-50">
                        <label for="newImagesInput" class="form-label fw-bold text-dark small mb-1">
                            <i class="bi bi-cloud-arrow-up text-primary me-1"></i> Upload Additional Photographs
                        </label>
                        <input type="file" 
                               name="new_images[]" 
                               id="newImagesInput" 
                               form="editAuctionForm"
                               class="form-control" 
                               accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" 
                               multiple>
                        <div class="form-text d-flex justify-content-between align-items-center mt-1">
                            <span>Allowed: JPG, PNG, WEBP. Max 5MB per image.</span>
                            <span class="fw-semibold text-secondary">Can add up to <?= 5 - count($existing_images); ?> more</span>
                        </div>
                        <div id="newImagePreview" class="row g-2 mt-2"></div>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info py-2 px-3 small rounded-3 mb-0 d-flex align-items-center gap-2">
                        <i class="bi bi-info-circle-fill"></i>
                        <span>Maximum lot image limit reached (5/5). Delete an existing image above to upload a new one.</span>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- =============================================================== -->
        <!-- Main Edit Form Card                                             -->
        <!-- =============================================================== -->
        <div class="card border-0 shadow-sm rounded-4 bg-white overflow-hidden">
            <div class="card-body p-4 p-md-5">
                <form method="POST" action="edit_item.php?id=<?= $item_id; ?>" id="editAuctionForm" enctype="multipart/form-data" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= safe_html($csrf_token); ?>">
                    <input type="hidden" name="action" value="update_item">
                    <input type="hidden" name="item_id" value="<?= $item_id; ?>">

                    <!-- --------------------------------------------------- -->
                    <!-- Section 1: Item Basic Information                   -->
                    <!-- --------------------------------------------------- -->
                    <div class="mb-5">
                        <h5 class="fw-bold text-dark border-bottom pb-2 mb-3 d-flex align-items-center gap-2">
                            <i class="bi bi-info-circle text-primary"></i> 1. Basic Information
                        </h5>
                        <div class="row g-3">
                            <!-- 1. Item Title -->
                            <div class="col-md-7">
                                <label for="fieldTitle" class="form-label fw-semibold text-secondary small">
                                    Item Title <span class="text-danger">*</span>
                                </label>
                                <input type="text" 
                                       class="form-control form-control-lg" 
                                       id="fieldTitle" 
                                       name="title" 
                                       value="<?= safe_html($_POST['title'] ?? ($item['title'] ?? '')); ?>" 
                                       placeholder="e.g. 1968 Ford Mustang Fastback GT 390 V8" 
                                       required>
                                <div class="form-text">A descriptive title helps buyers find your listing quickly.</div>
                            </div>

                            <!-- 4. Category Selector (Side-by-Side Icon & Name) -->
                            <div class="col-md-5">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <label for="fieldCategoryInput" class="form-label fw-semibold text-secondary small mb-0">
                                        Category <span class="text-danger fw-bold">*</span>
                                    </label>
                                    <button type="button" class="btn btn-link btn-sm p-0 text-emerald text-decoration-none small fw-semibold" id="toggleCategoryGridBtn" style="font-size: 0.78rem;">
                                        <i class="bi bi-grid-3x3-gap me-1"></i> <span id="toggleCategoryGridText">Card Grid</span>
                                    </button>
                                </div>

                                <!-- Custom Styled Select Dropdown (Icon + Name Side-by-Side) -->
                                <div class="dropdown custom-category-dropdown position-relative">
                                    <button class="form-select form-select-lg text-start d-flex align-items-center justify-content-between shadow-none bg-white w-100" 
                                            type="button" 
                                            id="categoryDropdownTrigger" 
                                            data-bs-toggle="dropdown" 
                                            aria-expanded="false" 
                                            style="height: 48px; border-color: #cbd5e1;">
                                        <span class="d-flex align-items-center gap-2 text-truncate" id="selectedCatDisplay">
                                            <span class="rounded-2 d-inline-flex align-items-center justify-content-center text-center bg-emerald-light text-emerald" style="width: 30px; height: 30px; font-size: 0.95rem;">
                                                <i class="fa-solid fa-tags"></i>
                                            </span>
                                            <span class="fw-semibold text-dark text-truncate">Select Category</span>
                                        </span>
                                    </button>

                                    <div class="dropdown-menu dropdown-menu-end w-100 p-2 shadow-lg border-0 rounded-3 mt-1" 
                                         aria-labelledby="categoryDropdownTrigger" 
                                         style="min-width: 290px; max-height: 350px; overflow-y: auto; z-index: 1060;">
                                        <!-- Search input -->
                                        <div class="p-1 mb-2 sticky-top bg-white border-bottom pb-2">
                                            <div class="input-group input-group-sm">
                                                <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-search"></i></span>
                                                <input type="text" class="form-control border-start-0 shadow-none" id="catDropdownSearch" placeholder="Search categories...">
                                            </div>
                                        </div>

                                        <!-- Categories options list -->
                                        <div class="cat-options-container" id="catOptionsContainer">
                                            <?php 
                                            $selected_cat_id = (int)($_POST['category_id'] ?? ($item['category_id'] ?? 0));
                                            foreach ($categories_list as $cat): 
                                                $cid = (int)$cat['id'];
                                                $cname = safe_html($cat['name'] ?? '');
                                                $cicon = safe_html(format_category_icon($cat['icon'] ?? 'fa-tag'));
                                                $is_selected = ($cid === $selected_cat_id);
                                            ?>
                                                <button type="button" 
                                                        class="dropdown-item d-flex align-items-center justify-content-between py-2 px-2.5 rounded-2 category-select-item mb-1 <?= $is_selected ? 'active-category' : ''; ?>"
                                                        data-cat-id="<?= $cid; ?>"
                                                        data-cat-name="<?= $cname; ?>"
                                                        data-cat-icon="<?= $cicon; ?>">
                                                    <div class="d-flex align-items-center gap-2 text-truncate">
                                                        <span class="category-icon-box rounded-2 d-inline-flex align-items-center justify-content-center text-center <?= $is_selected ? 'bg-emerald text-white' : 'bg-light text-secondary'; ?>" 
                                                              style="width: 30px; height: 30px; font-size: 0.95rem;">
                                                            <i class="<?= $cicon; ?>"></i>
                                                        </span>
                                                        <span class="category-name-label text-truncate <?= $is_selected ? 'fw-bold text-emerald' : 'text-dark'; ?>">
                                                            <?= $cname; ?>
                                                        </span>
                                                    </div>
                                                    <?php if ($is_selected): ?>
                                                        <i class="bi bi-check-circle-fill text-emerald fs-6 flex-shrink-0 ms-2"></i>
                                                    <?php endif; ?>
                                                </button>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>

                                <!-- Hidden Input submitted with the form -->
                                <input type="hidden" name="category_id" id="fieldCategoryInput" value="<?= (int)($_POST['category_id'] ?? ($item['category_id'] ?? 0)); ?>" required>
                                <div class="form-text">Choose the most accurate category to ensure high bidder discoverability.</div>
                            </div>

                            <!-- Expandable Visual Card Grid -->
                            <div class="col-12 d-none" id="categoryGridCollapse">
                                <div class="p-3 bg-light border rounded-3 mb-2">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span class="small fw-semibold text-secondary">
                                            <i class="bi bi-collection me-1"></i> Visual Category Cards:
                                        </span>
                                        <small class="text-muted">Click any card to select</small>
                                    </div>
                                    <div class="row g-2 category-card-grid">
                                        <?php 
                                        $selected_cat_id = (int)($_POST['category_id'] ?? ($item['category_id'] ?? 0));
                                        foreach ($categories_list as $cat): 
                                            $cid = (int)$cat['id'];
                                            $cname = safe_html($cat['name'] ?? '');
                                            $cicon = safe_html(format_category_icon($cat['icon'] ?? 'fa-tag'));
                                            $is_selected = ($cid === $selected_cat_id);
                                        ?>
                                            <div class="col-6 col-sm-4 col-md-3 col-lg-2">
                                                <div class="card h-100 category-radio-card p-2 text-center rounded-3 border <?= $is_selected ? 'border-emerald active-card shadow-sm' : 'border-light-subtle bg-white'; ?>"
                                                     data-cat-id="<?= $cid; ?>"
                                                     data-cat-name="<?= $cname; ?>"
                                                     data-cat-icon="<?= $cicon; ?>">
                                                    <div class="cat-card-icon fs-3 mb-1 <?= $is_selected ? 'text-emerald' : 'text-secondary'; ?>">
                                                        <i class="<?= $cicon; ?>"></i>
                                                    </div>
                                                    <div class="small fw-semibold text-truncate <?= $is_selected ? 'text-emerald' : 'text-dark'; ?>" title="<?= $cname; ?>">
                                                        <?= $cname; ?>
                                                    </div>
                                                    <?php if ($is_selected): ?>
                                                        <span class="badge bg-emerald text-white rounded-pill px-2 py-0.5 mt-1 small" style="font-size: 0.65rem;">Selected</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- 2. Short Description -->
                            <div class="col-12">
                                <label for="fieldShortDesc" class="form-label fw-semibold text-secondary small">
                                    Short Summary / Subtitle <span class="text-muted fw-normal">(Optional)</span>
                                </label>
                                <input type="text" 
                                       class="form-control" 
                                       id="fieldShortDesc" 
                                       name="short_description" 
                                       maxlength="500"
                                       value="<?= safe_html($_POST['short_description'] ?? ($item['short_description'] ?? '')); ?>" 
                                       placeholder="Brief 1-2 sentence highlight shown in catalog cards...">
                            </div>

                            <!-- 3. Full Description -->
                            <div class="col-12">
                                <label for="fieldDesc" class="form-label fw-semibold text-secondary small">
                                    Full Comprehensive Description <span class="text-danger">*</span>
                                </label>
                                <textarea class="form-control" 
                                          id="fieldDesc" 
                                          name="description" 
                                          rows="6" 
                                          placeholder="Detail specifications, history, included accessories, documentation, and condition..." 
                                          required><?= safe_html($_POST['description'] ?? ($item['description'] ?? '')); ?></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- --------------------------------------------------- -->
                    <!-- Section 2: Pricing & Bidding Rules                  -->
                    <!-- --------------------------------------------------- -->
                    <div class="mb-5">
                        <h5 class="fw-bold text-dark border-bottom pb-2 mb-3 d-flex align-items-center gap-2">
                            <i class="bi bi-tag text-success"></i> 2. Pricing & Bidding Settings
                        </h5>
                        <div class="row g-3">
                            <!-- 5. Starting Price -->
                            <div class="col-md-3">
                                <label for="fieldStartingPrice" class="form-label fw-semibold text-secondary small">
                                    Starting Price (PKR) <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light fw-bold text-muted">Rs.</span>
                                    <input type="number" 
                                           step="0.01" 
                                           min="1" 
                                           class="form-control <?= $has_bids ? 'readonly-lock' : ''; ?>" 
                                           id="fieldStartingPrice" 
                                           name="starting_price" 
                                           value="<?= safe_html($_POST['starting_price'] ?? ($item['starting_price'] ?? '')); ?>" 
                                           <?= $has_bids ? 'readonly tabindex="-1"' : 'required'; ?>>
                                </div>
                                <div class="form-text"><?= $has_bids ? 'Locked: Bids placed' : 'Initial minimum opening bid.'; ?></div>
                            </div>

                            <!-- 6. Reserve Price -->
                            <div class="col-md-3">
                                <label for="fieldReservePrice" class="form-label fw-semibold text-secondary small">
                                    Reserve Price (PKR) <span class="text-muted fw-normal">(Optional)</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light fw-bold text-muted">Rs.</span>
                                    <input type="number" 
                                           step="0.01" 
                                           min="1" 
                                           class="form-control <?= $has_bids ? 'readonly-lock' : ''; ?>" 
                                           id="fieldReservePrice" 
                                           name="reserve_price" 
                                           value="<?= safe_html($_POST['reserve_price'] ?? ($item['reserve_price'] ?? '')); ?>" 
                                           <?= $has_bids ? 'readonly tabindex="-1"' : ''; ?> 
                                            placeholder="None">
                                </div>
                                <div class="form-text">Minimum secret target to sell.</div>
                                
                                <!-- AI Dynamic Valuation Trigger Button -->
                                <div class="mt-2">
                                    <button type="button" 
                                            id="btnAiReserve" 
                                            class="btn btn-sm <?= $has_bids ? 'btn-outline-secondary' : 'btn-outline-success'; ?> w-100 d-inline-flex align-items-center justify-content-center gap-1 py-1 shadow-sm"
                                            style="<?= $has_bids ? '' : 'border-color: #10b981; color: #047857; font-weight: 600; background: #ecfdf5;'; ?> font-size: 0.82rem;">
                                        <i class="bi bi-stars text-warning fs-6"></i>
                                        <span><?= $has_bids ? '⚡ View AI Valuation' : '⚡ AI Suggest Reserve Price'; ?></span>
                                    </button>
                                </div>
                            </div>

                            <!-- 7. Minimum Bid Increment -->
                            <div class="col-md-3">
                                <label for="fieldMinIncrement" class="form-label fw-semibold text-secondary small">
                                    Bid Step Increment (PKR)
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light fw-bold text-muted">Rs.</span>
                                    <input type="number" 
                                           step="0.01" 
                                           min="1" 
                                           class="form-control <?= $has_bids ? 'readonly-lock' : ''; ?>" 
                                           id="fieldMinIncrement" 
                                           name="min_increment" 
                                           value="<?= safe_html($_POST['min_increment'] ?? ($item['min_increment'] ?? '100.00')); ?>" 
                                           <?= $has_bids ? 'readonly tabindex="-1"' : ''; ?>>
                                </div>
                                <div class="form-text">Min increment between bids.</div>
                            </div>

                            <!-- 8. Buy It Now Price -->
                            <div class="col-md-3">
                                <label for="fieldBuyNow" class="form-label fw-semibold text-secondary small">
                                    Buy It Now Price (PKR) <span class="text-muted fw-normal">(Optional)</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light fw-bold text-muted">Rs.</span>
                                    <input type="number" 
                                           step="0.01" 
                                           min="1" 
                                           class="form-control" 
                                           id="fieldBuyNow" 
                                           name="buy_it_now_price" 
                                           value="<?= safe_html($_POST['buy_it_now_price'] ?? ($item['buy_it_now_price'] ?? '')); ?>" 
                                           placeholder="Leave blank if none">
                                </div>
                                <div class="form-text">Instant purchase buyout price.</div>
                            </div>

                            <!-- AI Valuation Display Container -->
                            <div class="col-12" id="aiReserveContainer" style="display: none;"></div>
                        </div>
                    </div>

                    <!-- --------------------------------------------------- -->
                    <!-- Section 3: Attributes & Specifications              -->
                    <!-- --------------------------------------------------- -->
                    <div class="mb-5">
                        <h5 class="fw-bold text-dark border-bottom pb-2 mb-3 d-flex align-items-center gap-2">
                            <i class="bi bi-sliders text-warning"></i> 3. Specifications & Attributes
                        </h5>
                        <div class="row g-3">
                            <!-- 9. Condition -->
                            <div class="col-md-4">
                                <label for="fieldCondition" class="form-label fw-semibold text-secondary small">Condition</label>
                                <?php 
                                    $curr_cond = $_POST['condition_type'] ?? ($item['condition_type'] ?? '');
                                    $cond_options = ['Brand New', 'Used - Like New', 'Used - Excellent', 'Used - Good', 'Used - Fair', 'Refurbished', 'Antique / Vintage', 'For Parts / Not Working'];
                                ?>
                                <select class="form-select" id="fieldCondition" name="condition_type">
                                    <option value="">-- Select Condition --</option>
                                    <?php foreach ($cond_options as $cOpt): ?>
                                        <option value="<?= safe_html($cOpt); ?>" <?= strcasecmp((string)$curr_cond, $cOpt) === 0 ? 'selected' : ''; ?>>
                                            <?= safe_html($cOpt); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- 10. Brand -->
                            <div class="col-md-4">
                                <label for="fieldBrand" class="form-label fw-semibold text-secondary small">Brand / Make</label>
                                <input type="text" class="form-control" id="fieldBrand" name="brand" 
                                       value="<?= safe_html($_POST['brand'] ?? ($item['brand'] ?? '')); ?>" placeholder="e.g. Rolex, Toyota, Apple">
                            </div>

                            <!-- 11. Model -->
                            <div class="col-md-4">
                                <label for="fieldModel" class="form-label fw-semibold text-secondary small">Model / Series</label>
                                <input type="text" class="form-control" id="fieldModel" name="model" 
                                       value="<?= safe_html($_POST['model'] ?? ($item['model'] ?? '')); ?>" placeholder="e.g. Submariner Date, Corolla GLi">
                            </div>

                            <!-- 12. Year -->
                            <div class="col-md-3">
                                <label for="fieldYear" class="form-label fw-semibold text-secondary small">Manufacture Year</label>
                                <input type="number" class="form-control" id="fieldYear" name="year" min="1900" max="<?= date('Y') + 2; ?>" 
                                       value="<?= safe_html($_POST['year'] ?? ($item['year'] ?? '')); ?>" placeholder="e.g. 2022">
                            </div>

                            <!-- 13. Mileage -->
                            <div class="col-md-3">
                                <label for="fieldMileage" class="form-label fw-semibold text-secondary small">Mileage / Usage</label>
                                <input type="text" class="form-control" id="fieldMileage" name="mileage" 
                                       value="<?= safe_html($_POST['mileage'] ?? ($item['mileage'] ?? '')); ?>" placeholder="e.g. 24,000 km, 120 hrs">
                            </div>

                            <!-- 14. Engine Type -->
                            <div class="col-md-3">
                                <label for="fieldEngine" class="form-label fw-semibold text-secondary small">Engine / Power Type</label>
                                <input type="text" class="form-control" id="fieldEngine" name="engine_type" 
                                       value="<?= safe_html($_POST['engine_type'] ?? ($item['engine_type'] ?? '')); ?>" placeholder="e.g. 1.8L Hybrid, V8, Electric">
                            </div>

                            <!-- 15. Color -->
                            <div class="col-md-3">
                                <label for="fieldColor" class="form-label fw-semibold text-secondary small">Color</label>
                                <input type="text" class="form-control" id="fieldColor" name="color" 
                                       value="<?= safe_html($_POST['color'] ?? ($item['color'] ?? '')); ?>" placeholder="e.g. Midnight Black, Emerald">
                            </div>
                        </div>
                    </div>

                    <!-- --------------------------------------------------- -->
                    <!-- Section 4: Location Details                         -->
                    <!-- --------------------------------------------------- -->
                    <div class="mb-5">
                        <h5 class="fw-bold text-dark border-bottom pb-2 mb-3 d-flex align-items-center gap-2">
                            <i class="bi bi-geo-alt text-danger"></i> 4. Location Details
                        </h5>
                        <div class="row g-3">
                            <!-- 16. Location Address -->
                            <div class="col-md-6">
                                <label for="fieldAddress" class="form-label fw-semibold text-secondary small">Street / Warehouse Address</label>
                                <input type="text" class="form-control" id="fieldAddress" name="location_address" 
                                       value="<?= safe_html($_POST['location_address'] ?? ($item['location_address'] ?? '')); ?>" placeholder="Plot 12-B, Industrial Area">
                            </div>

                            <!-- 17. City -->
                            <div class="col-md-3">
                                <label for="fieldCity" class="form-label fw-semibold text-secondary small">
                                    City <span class="text-danger">*</span>
                                </label>
                                <input type="text" class="form-control" id="fieldCity" name="city" 
                                       value="<?= safe_html($_POST['city'] ?? ($item['city'] ?? ($item['location_city'] ?? 'Karachi'))); ?>" required>
                            </div>

                            <!-- 18. Area -->
                            <div class="col-md-3">
                                <label for="fieldArea" class="form-label fw-semibold text-secondary small">District / Area</label>
                                <input type="text" class="form-control" id="fieldArea" name="area" 
                                       value="<?= safe_html($_POST['area'] ?? ($item['area'] ?? '')); ?>" placeholder="e.g. DHA Phase 6, Gulberg">
                            </div>

                            <!-- 19. State -->
                            <div class="col-md-3">
                                <label for="fieldState" class="form-label fw-semibold text-secondary small">Province / State</label>
                                <input type="text" class="form-control" id="fieldState" name="location_state" 
                                       value="<?= safe_html($_POST['location_state'] ?? ($item['location_state'] ?? '')); ?>" placeholder="e.g. Sindh, Punjab">
                            </div>

                            <!-- 20. Country -->
                            <div class="col-md-3">
                                <label for="fieldCountry" class="form-label fw-semibold text-secondary small">Country</label>
                                <input type="text" class="form-control" id="fieldCountry" name="location_country" 
                                       value="<?= safe_html($_POST['location_country'] ?? ($item['location_country'] ?? 'Pakistan')); ?>">
                            </div>

                            <!-- 21. Latitude -->
                            <div class="col-md-3">
                                <label for="fieldLat" class="form-label fw-semibold text-secondary small">Latitude (GPS)</label>
                                <input type="number" step="0.00000001" class="form-control" id="fieldLat" name="location_lat" 
                                       value="<?= safe_html($_POST['location_lat'] ?? ($item['location_lat'] ?? '')); ?>" placeholder="24.8607">
                            </div>

                            <!-- 22. Longitude -->
                            <div class="col-md-3">
                                <label for="fieldLng" class="form-label fw-semibold text-secondary small">Longitude (GPS)</label>
                                <input type="number" step="0.00000001" class="form-control" id="fieldLng" name="location_lng" 
                                       value="<?= safe_html($_POST['location_lng'] ?? ($item['location_lng'] ?? '')); ?>" placeholder="67.0011">
                            </div>
                        </div>
                    </div>

                    <!-- --------------------------------------------------- -->
                    <!-- Section 5: Schedule, Timeline & Status              -->
                    <!-- --------------------------------------------------- -->
                    <div class="mb-5">
                        <h5 class="fw-bold text-dark border-bottom pb-2 mb-3 d-flex align-items-center gap-2">
                            <i class="bi bi-clock-history text-info"></i> 5. Schedule & Governance
                        </h5>
                        <div class="row g-3">
                            <!-- 23. Start Date & Time -->
                            <div class="col-md-4">
                                <label for="fieldStartTime" class="form-label fw-semibold text-secondary small">
                                    Start Date & Time <span class="text-danger">*</span>
                                </label>
                                <input type="datetime-local" 
                                       class="form-control <?= $has_bids ? 'readonly-lock' : ''; ?>" 
                                       id="fieldStartTime" 
                                       name="start_time" 
                                       value="<?= safe_html($_POST['start_time'] ?? $start_val); ?>" 
                                       <?= $has_bids ? 'readonly tabindex="-1"' : 'required'; ?>>
                                <div class="form-text"><?= $has_bids ? 'Locked: Bidding has started' : 'Bidding opens at this time.'; ?></div>
                            </div>

                            <!-- 24. End Date & Time -->
                            <div class="col-md-4">
                                <label for="fieldEndTime" class="form-label fw-semibold text-secondary small">
                                    End Date & Time <span class="text-danger">*</span>
                                </label>
                                <input type="datetime-local" 
                                       class="form-control" 
                                       id="fieldEndTime" 
                                       name="end_time" 
                                       value="<?= safe_html($_POST['end_time'] ?? $end_val); ?>" 
                                       required>
                                <div class="form-text">Auction automatically closes and concludes.</div>
                            </div>

                            <!-- 25. Status -->
                            <div class="col-md-4">
                                <label for="fieldStatus" class="form-label fw-semibold text-secondary small">Listing Status</label>
                                <?php 
                                    $curr_status = $_POST['status'] ?? ($item['status'] ?? 'active');
                                    $status_list = ['active' => 'Active (Open for Bids)', 'paused' => 'Paused (Bidding Suspended)', 'cancelled' => 'Cancelled'];
                                    if ($is_admin) {
                                        $status_list['pending'] = 'Pending Review';
                                        $status_list['ended']   = 'Ended';
                                        $status_list['sold']    = 'Sold';
                                    }
                                ?>
                                <select class="form-select" id="fieldStatus" name="status">
                                    <?php foreach ($status_list as $sKey => $sLabel): ?>
                                        <option value="<?= $sKey; ?>" <?= $curr_status === $sKey ? 'selected' : ''; ?>>
                                            <?= $sLabel; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- 26. Featured & 27. Approved Toggles -->
                            <div class="col-12 mt-3">
                                <div class="p-3 bg-light rounded-3 d-flex flex-wrap gap-4 align-items-center">
                                    <!-- 26. Featured -->
                                    <div class="form-check form-switch mb-0">
                                        <input class="form-check-input" type="checkbox" role="switch" id="fieldFeatured" name="featured" value="1"
                                               <?= ((int)($_POST['featured'] ?? ($item['featured'] ?? 0)) === 1) ? 'checked' : ''; ?>>
                                        <label class="form-check-label fw-semibold text-dark small" for="fieldFeatured">
                                            Promote to Featured Lots Showcase
                                        </label>
                                    </div>

                                    <!-- 27. Approved (Admin Only) -->
                                    <?php if ($is_admin): ?>
                                        <div class="form-check form-switch mb-0 border-start ps-4">
                                            <input class="form-check-input" type="checkbox" role="switch" id="fieldApproved" name="approved" value="1"
                                                   <?= ((int)($_POST['approved'] ?? ($item['approved'] ?? 0)) === 1) ? 'checked' : ''; ?>>
                                            <label class="form-check-label fw-semibold text-dark small" for="fieldApproved">
                                                Admin Official Verification & Approval
                                            </label>
                                        </div>
                                    <?php else: ?>
                                        <div class="text-muted small border-start ps-3">
                                            Approval Status: 
                                            <span class="badge <?= (int)($item['approved'] ?? 0) === 1 ? 'bg-success' : 'bg-warning text-dark'; ?>">
                                                <?= (int)($item['approved'] ?? 0) === 1 ? 'Approved' : 'Pending Review'; ?>
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- --------------------------------------------------- -->
                    <!-- Section 6: SEO & Meta Data                          -->
                    <!-- --------------------------------------------------- -->
                    <div class="mb-5">
                        <h5 class="fw-bold text-dark border-bottom pb-2 mb-3 d-flex align-items-center gap-2">
                            <i class="bi bi-globe text-secondary"></i> 6. Search Engine Optimization (SEO)
                        </h5>
                        <div class="row g-3">
                            <!-- 28. Meta Title -->
                            <div class="col-md-6">
                                <label for="fieldMetaTitle" class="form-label fw-semibold text-secondary small">Meta Title</label>
                                <input type="text" class="form-control" id="fieldMetaTitle" name="meta_title" 
                                       value="<?= safe_html($_POST['meta_title'] ?? ($item['meta_title'] ?? '')); ?>" placeholder="Custom title for Google search results">
                            </div>

                            <!-- 30. Meta Keywords -->
                            <div class="col-md-6">
                                <label for="fieldMetaKeywords" class="form-label fw-semibold text-secondary small">Meta Keywords</label>
                                <input type="text" class="form-control" id="fieldMetaKeywords" name="meta_keywords" 
                                       value="<?= safe_html($_POST['meta_keywords'] ?? ($item['meta_keywords'] ?? '')); ?>" placeholder="auction, vehicle, luxury, rolex">
                            </div>

                            <!-- 29. Meta Description -->
                            <div class="col-12">
                                <label for="fieldMetaDesc" class="form-label fw-semibold text-secondary small">Meta Description</label>
                                <textarea class="form-control" id="fieldMetaDesc" name="meta_description" rows="2" 
                                          placeholder="A concise summary of this listing for search engines (max 160 characters)..."><?= safe_html($_POST['meta_description'] ?? ($item['meta_description'] ?? '')); ?></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- --------------------------------------------------- -->
                    <!-- Action Bar                                          -->
                    <!-- --------------------------------------------------- -->
                    <div class="border-top pt-4 d-flex justify-content-between align-items-center flex-wrap gap-3">
                        <a href="my_auctions.php" class="btn btn-outline-secondary px-4 py-2.5 rounded-3 fw-semibold">
                            Cancel & Discard
                        </a>
                        <div class="d-flex gap-2">
                            <button type="submit" name="save_item" class="btn btn-emerald px-5 py-2.5 rounded-3 fw-bold shadow-sm d-inline-flex align-items-center gap-2">
                                <i class="bi bi-check-lg fs-5"></i>
                                <span>Save Changes</span>
                            </button>
                        </div>
                    </div>

                </form>
            </div>
        </div>

    <?php endif; ?>

</div>

<!-- Client-side Image Previews Script -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    const input = document.getElementById('newImagesInput');
    const previewContainer = document.getElementById('newImagePreview');

    if (input && previewContainer) {
        input.addEventListener('change', function () {
            previewContainer.innerHTML = '';
            const files = Array.from(input.files);

            files.forEach(function (file, index) {
                if (!file.type.match('image.*')) return;

                const reader = new FileReader();
                reader.onload = function (e) {
                    const col = document.createElement('div');
                    col.className = 'col-4 col-sm-3 col-md-2 position-relative';

                    const wrapper = document.createElement('div');
                    wrapper.className = 'border rounded-3 overflow-hidden bg-dark position-relative';
                    wrapper.style.height = '80px';

                    const img = document.createElement('img');
                    img.src = e.target.result;
                    img.className = 'w-100 h-100 object-fit-cover';

                    const badge = document.createElement('span');
                    badge.className = 'badge bg-primary position-absolute bottom-0 start-0 m-1 text-[10px]';
                    badge.textContent = 'New ' + (index + 1);

                    wrapper.appendChild(img);
                    wrapper.appendChild(badge);
                    col.appendChild(wrapper);
                    previewContainer.appendChild(col);
                };
                reader.readAsDataURL(file);
            });
        });
    }

    // -------------------------------------------------------------
    // Category Selector Interactivity (Dropdown, Search, Grid Sync)
    // -------------------------------------------------------------
    const hiddenCatInput   = document.getElementById('fieldCategoryInput');
    const selectedCatSpan  = document.getElementById('selectedCatDisplay');
    const categoryOptions  = document.querySelectorAll('.category-select-item');
    const categoryCards    = document.querySelectorAll('.category-radio-card');
    const categorySearch   = document.getElementById('catDropdownSearch');
    const toggleGridBtn    = document.getElementById('toggleCategoryGridBtn');
    const gridCollapse     = document.getElementById('categoryGridCollapse');
    const toggleGridText   = document.getElementById('toggleCategoryGridText');

    function selectCategory(catId, catName, catIcon) {
        if (!hiddenCatInput) return;
        hiddenCatInput.value = catId;

        // 1. Update dropdown button display
        if (selectedCatSpan) {
            selectedCatSpan.innerHTML = `
                <span class="rounded-2 d-inline-flex align-items-center justify-content-center text-center bg-emerald-light text-emerald" style="width: 30px; height: 30px; font-size: 0.95rem;">
                    <i class="${catIcon}"></i>
                </span>
                <span class="fw-bold text-dark text-truncate">${catName}</span>
            `;
        }

        // 2. Synchronize dropdown items
        categoryOptions.forEach(opt => {
            const isMatch = (opt.dataset.catId === String(catId));
            const iconBox = opt.querySelector('.category-icon-box');
            const nameLabel = opt.querySelector('.category-name-label');
            let checkIcon = opt.querySelector('.bi-check-circle-fill');

            if (isMatch) {
                opt.classList.add('active-category');
                if (iconBox) {
                    iconBox.className = 'category-icon-box rounded-2 d-inline-flex align-items-center justify-content-center text-center bg-emerald text-white';
                }
                if (nameLabel) {
                    nameLabel.className = 'category-name-label text-truncate fw-bold text-emerald';
                }
                if (!checkIcon) {
                    const check = document.createElement('i');
                    check.className = 'bi bi-check-circle-fill text-emerald fs-6 flex-shrink-0 ms-2';
                    opt.appendChild(check);
                }
            } else {
                opt.classList.remove('active-category');
                if (iconBox) {
                    iconBox.className = 'category-icon-box rounded-2 d-inline-flex align-items-center justify-content-center text-center bg-light text-secondary';
                }
                if (nameLabel) {
                    nameLabel.className = 'category-name-label text-truncate text-dark';
                }
                if (checkIcon) {
                    checkIcon.remove();
                }
            }
        });

        // 3. Synchronize visual cards
        categoryCards.forEach(card => {
            const isMatch = (card.dataset.catId === String(catId));
            const iconEl  = card.querySelector('.cat-card-icon');
            const nameEl  = card.querySelector('.small.fw-semibold');
            let badgeEl   = card.querySelector('.badge');

            if (isMatch) {
                card.className = 'card h-100 category-radio-card p-2 text-center rounded-3 border border-emerald active-card shadow-sm';
                if (iconEl) iconEl.className = 'cat-card-icon fs-3 mb-1 text-emerald';
                if (nameEl) nameEl.className = 'small fw-semibold text-truncate text-emerald';
                if (!badgeEl) {
                    const badge = document.createElement('span');
                    badge.className = 'badge bg-emerald text-white rounded-pill px-2 py-0.5 mt-1 small';
                    badge.style.fontSize = '0.65rem';
                    badge.textContent = 'Selected';
                    card.appendChild(badge);
                }
            } else {
                card.className = 'card h-100 category-radio-card p-2 text-center rounded-3 border border-light-subtle bg-white';
                if (iconEl) iconEl.className = 'cat-card-icon fs-3 mb-1 text-secondary';
                if (nameEl) nameEl.className = 'small fw-semibold text-truncate text-dark';
                if (badgeEl) badgeEl.remove();
            }
        });
    }

    // Attach click listeners on dropdown items
    categoryOptions.forEach(opt => {
        opt.addEventListener('click', function (e) {
            e.preventDefault();
            selectCategory(this.dataset.catId, this.dataset.catName, this.dataset.catIcon);
        });
    });

    // Attach click listeners on card grid items
    categoryCards.forEach(card => {
        card.addEventListener('click', function () {
            selectCategory(this.dataset.catId, this.dataset.catName, this.dataset.catIcon);
        });
    });

    // Dropdown live filter search
    if (categorySearch) {
        categorySearch.addEventListener('input', function () {
            const query = this.value.toLowerCase().trim();
            categoryOptions.forEach(opt => {
                const name = (opt.dataset.catName || '').toLowerCase();
                if (!query || name.includes(query)) {
                    opt.classList.remove('d-none');
                } else {
                    opt.classList.add('d-none');
                }
            });
        });
    }

    // Toggle card grid view
    if (toggleGridBtn && gridCollapse) {
        toggleGridBtn.addEventListener('click', function () {
            const isHidden = gridCollapse.classList.contains('d-none');
            if (isHidden) {
                gridCollapse.classList.remove('d-none');
                if (toggleGridText) toggleGridText.textContent = 'Hide Cards';
                this.querySelector('i').className = 'bi bi-chevron-up me-1';
            } else {
                gridCollapse.classList.add('d-none');
                if (toggleGridText) toggleGridText.textContent = 'Card Grid';
                this.querySelector('i').className = 'bi bi-grid-3x3-gap me-1';
            }
        });
    }

    // Form submit validation safeguard
    const editForm = document.getElementById('editAuctionForm');
    if (editForm) {
        editForm.addEventListener('submit', function (e) {
            const catVal = parseInt(hiddenCatInput ? hiddenCatInput.value : '0', 10);
            if (!catVal || catVal <= 0) {
                e.preventDefault();
                alert('Please select an auction category before submitting.');
                document.getElementById('categoryDropdownTrigger')?.focus();
            }
        });
    }

    // Auto-select initial assigned category on page load
    const currentCatId = hiddenCatInput ? hiddenCatInput.value : '';
    let initialItem = document.querySelector(`.category-select-item[data-cat-id="${currentCatId}"]`);
    if (!initialItem && categoryOptions.length > 0) {
        initialItem = categoryOptions[0];
    }
    if (initialItem) {
        selectCategory(initialItem.dataset.catId, initialItem.dataset.catName, initialItem.dataset.catIcon);
    }
});
</script>

<script src="../assets/js/ai_reserve_price.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    AiReservePrice.init({
        triggerBtnId:      'btnAiReserve',
        containerId:       'aiReserveContainer',
        reserveInputId:    'fieldReservePrice',
        startingInputId:   'fieldStartingPrice',
        categorySelectId:  'fieldCategory',
        brandInputId:      'fieldBrand',
        modelInputId:      'fieldModel',
        yearInputId:       'fieldYear',
        conditionSelectId: 'fieldCondition',
        cityInputId:       'fieldCity',
        itemId:            <?= (int)($item['id'] ?? 0); ?>,
        apiUrl:            '../ajax/ai_reserve_price.php'
    });
});
</script>

<?php
include_once __DIR__ . '/../includes/footer.php';
?>