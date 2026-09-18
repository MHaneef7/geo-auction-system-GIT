<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include_once __DIR__ . '/../includes/database.php';
include_once __DIR__ . '/../includes/header.php'; 

$user_id = (int)($_SESSION['user_id'] ?? 0); 
$id = (int)($_GET['id'] ?? ($_POST['id'] ?? 0)); 
$item = null; 
$msg = ''; 
$error = ''; 

/*
|--------------------------------------------------------------------------
| Handle Image Deletion via AJAX/POST if requested
|--------------------------------------------------------------------------
*/
if (isset($_POST['delete_image_id'])) {
    $img_to_delete = (int)$_POST['delete_image_id'];
    
    // Check if image belongs to user's item
    $chk_q = "SELECT ii.image_path FROM item_images ii JOIN items i ON ii.item_id = i.id WHERE ii.id = ? AND i.seller_id = ?";
    $chk_stmt = mysqli_prepare($conn, $chk_q);
    if ($chk_stmt) {
        mysqli_stmt_bind_param($chk_stmt, "ii", $img_to_delete, $user_id);
        mysqli_stmt_execute($chk_stmt);
        $res = mysqli_stmt_get_result($chk_stmt);
        if ($row = mysqli_fetch_assoc($res)) {
            $file_path = __DIR__ . '/../' . $row['image_path'];
            if (file_exists($file_path)) {
                @unlink($file_path); // Delete file from folder
            }
            // Delete from database
            $del_q = "DELETE FROM item_images WHERE id = ?";
            $del_stmt = mysqli_prepare($conn, $del_q);
            if ($del_stmt) {
                mysqli_stmt_bind_param($del_stmt, "i", $img_to_delete);
                mysqli_stmt_execute($del_stmt);
                mysqli_stmt_close($del_stmt);
            }
        }
        mysqli_stmt_close($chk_stmt);
    }
    exit; // Stop execution if called via script
}

/*
|--------------------------------------------------------------------------
| Handle Form Submission (Update)
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['delete_image_id'])) { 
    $title = trim($_POST['title'] ?? ''); 
    $desc  = trim($_POST['description'] ?? ''); 
    $price = (float)($_POST['starting_price'] ?? 0); 
    $end_time = trim($_POST['end_time'] ?? ''); 

    if (empty($title) || $price <= 0 || $id <= 0) { 
        $error = 'Please fill in all required fields.'; 
    } else { 
        // Format end time for MySQL
        $end_time_mysql = str_replace('T', ' ', $end_time);
        if (strlen($end_time_mysql) === 16) {
            $end_time_mysql .= ':00';
        }

        $end_timestamp = strtotime($end_time_mysql);

        if ($end_timestamp === false) {
            $error = 'Invalid auction end time.';
        } elseif ($end_timestamp <= time()) {
            $error = 'Auction end time must be in the future.';
        } else {
            // Update Item Details via Prepared Statement
            $sql = "UPDATE items 
                    SET title = ?, 
                        `description` = ?, 
                        starting_price = ?, 
                        end_time = ?, 
                        updated_at = NOW() 
                    WHERE id = ? 
                    AND seller_id = ?"; 

            $stmt = mysqli_prepare($conn, $sql);
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "ssdssi", $title, $desc, $price, $end_time_mysql, $id, $user_id);
                
                if (mysqli_stmt_execute($stmt)) {
                    $msg = 'Item updated successfully!';
                } else {
                    $error = 'Failed to update item: ' . mysqli_stmt_error($stmt);
                }
                mysqli_stmt_close($stmt);
            } else {
                $error = 'Database prepare error: ' . mysqli_error($conn);
            }

            /*
            |--------------------------------------------------------------------------
            | Handle New Image Uploads (If user selects new files)
            |--------------------------------------------------------------------------
            */
            if ($error === '' && isset($_FILES['item_images']) && !empty($_FILES['item_images']['name'][0])) {
                $files = $_FILES['item_images'];
                $file_count = count($files['name']);
                $upload_dir = __DIR__ . '/../uploads/items/';

                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }

                $uploaded_images = [];
                for ($i = 0; $i < $file_count; $i++) {
                    if ($files['error'][$i] === UPLOAD_ERR_OK) {
                        $file_tmp = $files['tmp_name'][$i];
                        $file_name = $files['name'][$i];
                        $file_size = $files['size'][$i];

                        $allowed_types = ['image/jpeg', 'image/png', 'image/webp', 'image/jpg'];
                        $file_mime = mime_content_type($file_tmp);

                        if (in_array($file_mime, $allowed_types) && $file_size <= 5 * 1024 * 1024) {
                            $file_ext = pathinfo($file_name, PATHINFO_EXTENSION);
                            $new_filename = 'item_' . time() . '_' . mt_rand(1000, 9999) . '_' . $i . '.' . $file_ext;
                            
                            if (move_uploaded_file($file_tmp, $upload_dir . $new_filename)) {
                                $uploaded_images[] = 'uploads/items/' . $new_filename;
                            }
                        }
                    }
                }

                // If new images were successfully uploaded, insert them into `item_images` table
                if (!empty($uploaded_images)) {
                    foreach ($uploaded_images as $index => $img_path) {
                        $img_sql = "INSERT INTO item_images (item_id, image_path, is_primary, created_at) VALUES (?, ?, 0, NOW())";
                        $img_stmt = mysqli_prepare($conn, $img_sql);
                        if ($img_stmt) {
                            mysqli_stmt_bind_param($img_stmt, "is", $id, $img_path);
                            mysqli_stmt_execute($img_stmt);
                            mysqli_stmt_close($img_stmt);
                        }
                    }
                    
                    // Update primary image in items table if it was empty
                    $check_feat = "SELECT featured_image FROM items WHERE id = ? AND (featured_image IS NULL OR featured_image = '')";
                    $cf_stmt = mysqli_prepare($conn, $check_feat);
                    if ($cf_stmt) {
                        mysqli_stmt_bind_param($cf_stmt, "i", $id);
                        mysqli_stmt_execute($cf_stmt);
                        $cf_res = mysqli_stmt_get_result($cf_stmt);
                        if (mysqli_num_rows($cf_res) > 0) {
                            $update_feat = "UPDATE items SET featured_image = ? WHERE id = ? AND seller_id = ?";
                            $feat_stmt = mysqli_prepare($conn, $update_feat);
                            if ($feat_stmt) {
                                mysqli_stmt_bind_param($feat_stmt, "sii", $uploaded_images[0], $id, $user_id);
                                mysqli_stmt_execute($feat_stmt);
                                mysqli_stmt_close($feat_stmt);
                            }
                        }
                        mysqli_stmt_close($cf_stmt);
                    }
                }
            }
        } 
    } 
} 

/*
|--------------------------------------------------------------------------
| Fetch Existing Item Data & Images
|--------------------------------------------------------------------------
*/
if ($id > 0) { 
    $res_sql = "SELECT * FROM items WHERE id = ? AND seller_id = ? LIMIT 1";
    $res_stmt = mysqli_prepare($conn, $res_sql);
    if ($res_stmt) {
        mysqli_stmt_bind_param($res_stmt, "ii", $id, $user_id);
        mysqli_stmt_execute($res_stmt);
        $res = mysqli_stmt_get_result($res_stmt);
        $item = ($res) ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($res_stmt);
    }

    // Fetch existing images for this item to show them
    $img_res_sql = "SELECT id, image_path FROM item_images WHERE item_id = ?";
    $img_res_stmt = mysqli_prepare($conn, $img_res_sql);
    $existing_images = [];
    if ($img_res_stmt) {
        mysqli_stmt_bind_param($img_res_stmt, "i", $id);
        mysqli_stmt_execute($img_res_stmt);
        $img_result = mysqli_stmt_get_result($img_res_stmt);
        while ($row = mysqli_fetch_assoc($img_result)) {
            $existing_images[] = $row;
        }
        mysqli_stmt_close($img_res_stmt);
    }
} 

if (!$item) { 
    echo '<div style="max-width: 600px; margin: 30px auto; background:#fff; padding:24px; border-radius:8px; text-align:center; border: 1px solid #e8e8e8;">'; 
    echo '<p style="color:#c0392b; font-weight:600;">Item not found or you do not have permission to edit it.</p>'; 
    echo '<p><a href="my_auctions.php" style="color:#007a33; font-weight:600; text-decoration:none;">Back to My Listings</a></p>'; 
    echo '</div>'; 
    include_once __DIR__ . '/../includes/footer.php'; 
    exit; 
} 
?> 

<div style="
    max-width: 700px; 
    margin: 30px auto; 
    background: #fff; 
    padding: 28px; 
    border-radius: 8px; 
    border: 1px solid #e8e8e8;
    box-shadow: 0 4px 15px rgba(0,0,0,0.05);
"> 

    <h2 style="
        font-size: 22px; 
        font-weight: 800; 
        color: #1a1a2e; 
        margin-bottom: 20px;
    "> 
        Edit Auction Item 
    </h2> 

    <?php if ($msg !== ''): ?> 
        <div style="background: #e8f5ec; color: #007a33; padding: 12px; border-radius: 6px; margin-bottom: 16px; font-weight: 600;"> 
            <?php echo htmlspecialchars($msg); ?> 
        </div> 
    <?php endif; ?> 

    <?php if ($error !== ''): ?> 
        <div style="background: #fdecea; color: #c0392b; padding: 12px; border-radius: 6px; margin-bottom: 16px; font-weight: 600;"> 
            <?php echo htmlspecialchars($error); ?> 
        </div> 
    <?php endif; ?> 

    <form method="POST" action="edit_item.php?id=<?php echo (int)$item['id']; ?>" enctype="multipart/form-data"> 

        <input type="hidden" name="id" value="<?php echo (int)$item['id']; ?>"> 

        <!-- TITLE -->
        <div style="margin-bottom: 16px;"> 
            <label style="display: block; font-weight: 600; margin-bottom: 6px;">Title *</label> 
            <input type="text" name="title" value="<?php echo htmlspecialchars($item['title'] ?? ''); ?>" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; box-sizing: border-box;"> 
        </div> 

        <!-- DESCRIPTION -->
        <div style="margin-bottom: 16px;"> 
            <label style="display: block; font-weight: 600; margin-bottom: 6px;">Description *</label> 
            <textarea name="description" required rows="4" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; box-sizing: border-box; resize: vertical;"><?php echo htmlspecialchars($item['description'] ?? ''); ?></textarea> 
        </div> 

        <!-- CURRENT / EXISTING IMAGES WITH REMOVE (X) BUTTON -->
        <?php if (!empty($existing_images)): ?>
        <div style="margin-bottom: 16px;">
            <label style="display: block; font-weight: 600; margin-bottom: 6px;">Current Images</label>
            <div style="display: flex; gap: 10px; flex-wrap: wrap;" id="currentImagesContainer">
                <?php foreach ($existing_images as $img): ?>
                    <div id="existing-img-<?php echo $img['id']; ?>" style="position: relative; width: 70px; height: 70px; border: 1px solid #ddd; border-radius: 6px; overflow: hidden; background: #f9f9f9;">
                        <img src="../<?php echo htmlspecialchars($img['image_path']); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                        <span onclick="deleteExistingImage(<?php echo $img['id']; ?>)" style="position: absolute; top: 2px; right: 4px; background: rgba(0,0,0,0.6); color: white; border-radius: 50%; width: 18px; height: 18px; font-size: 14px; line-height: 16px; text-align: center; cursor: pointer; font-weight: bold;">&times;</span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- ADD MORE IMAGES & LIVE PREVIEW WITH REMOVE (X) BUTTON -->
        <div style="margin-bottom: 16px;"> 
            <label style="display: block; font-weight: 600; margin-bottom: 6px;">Add More Images (Optional)</label> 
            <input type="file" id="imageInput" name="item_images[]" accept="image/jpeg, image/png, image/webp" multiple style="width: 100%; padding: 9px; border: 1px solid #ddd; border-radius: 6px; box-sizing: border-box; background: #fafafa;"> 
            <small style="display: block; margin-top: 5px; color: #777;">Upload additional item pictures if desired.</small>
            
            <!-- Preview Container -->
            <div id="imagePreviewContainer" style="display: flex; gap: 10px; flex-wrap: wrap; margin-top: 10px;"></div>
        </div> 

        <!-- STARTING PRICE -->
        <div style="margin-bottom: 16px;"> 
            <label style="display: block; font-weight: 600; margin-bottom: 6px;">Starting Price (PKR) *</label> 
            <div style="position: relative;"> 
                <span style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #007a33; font-weight: 700; z-index: 2;">Rs.</span>
                <input type="number" step="0.01" min="1" name="starting_price" value="<?php echo htmlspecialchars($item['starting_price'] ?? ''); ?>" required style="width: 100%; padding: 10px 10px 10px 48px; border: 1px solid #ddd; border-radius: 6px; box-sizing: border-box;"> 
            </div> 
        </div> 

        <!-- END TIME -->
        <div style="margin-bottom: 20px;"> 
            <label style="display: block; font-weight: 600; margin-bottom: 6px;">End Time *</label> 
            <input type="datetime-local" name="end_time" value="<?php echo !empty($item['end_time']) ? date('Y-m-d\TH:i', strtotime($item['end_time'])) : ''; ?>" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; box-sizing: border-box;"> 
        </div> 

        <!-- UPDATE BUTTON -->
        <button type="submit" style="background: #007a33; color: #fff; border: none; padding: 12px 24px; border-radius: 6px; font-weight: 700; cursor: pointer;"> 
            Update Item 
        </button> 

    </form> 
</div> 

<!-- JavaScript for Thumbnail Previews and AJAX Image Deletion -->
<script>
let dt = new DataTransfer();

document.getElementById('imageInput').addEventListener('change', function(event) {
    for (let file of event.target.files) {
        dt.items.add(file);
    }
    this.files = dt.files;
    renderPreviews();
});

function renderPreviews() {
    const container = document.getElementById('imagePreviewContainer');
    container.innerHTML = '';
    
    for (let i = 0; i < dt.files.length; i++) {
        const file = dt.files[i];
        
        const reader = new FileReader();
        reader.onload = function(e) {
            const wrapper = document.createElement('div');
            wrapper.style.cssText = "position: relative; width: 70px; height: 70px; border: 1px solid #ddd; border-radius: 6px; overflow: hidden; background: #f9f9f9;";
            
            const img = document.createElement('img');
            img.src = e.target.result;
            img.style.cssText = "width: 100%; height: 100%; object-fit: cover;";
            
            const removeBtn = document.createElement('span');
            removeBtn.innerHTML = '&times;';
            removeBtn.style.cssText = "position: absolute; top: 2px; right: 4px; background: rgba(0,0,0,0.6); color: white; border-radius: 50%; width: 18px; height: 18px; font-size: 14px; line-height: 16px; text-align: center; cursor: pointer; font-weight: bold;";
            
            removeBtn.onclick = function() {
                dt.items.remove(i);
                document.getElementById('imageInput').files = dt.files;
                renderPreviews();
            };
            
            wrapper.appendChild(img);
            wrapper.appendChild(removeBtn);
            container.appendChild(wrapper);
        }
        reader.readAsDataURL(file);
    }
}

// Function to delete existing database image immediately via AJAX
function deleteExistingImage(imageId) {
    if (confirm('Are you sure you want to delete this image?')) {
        const formData = new FormData();
        formData.append('delete_image_id', imageId);

        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => {
            const element = document.getElementById('existing-img-' + imageId);
            if (element) {
                element.remove();
            }
        })
        .catch(error => {
            alert('Failed to delete image.');
        });
    }
}
</script>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>