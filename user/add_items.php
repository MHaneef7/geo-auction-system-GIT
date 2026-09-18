<?php
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    include_once __DIR__ . '/../includes/database.php';
    include_once __DIR__ . '/../includes/header.php';
        $user_id = (int)($_SESSION['user_id'] ?? 0);
        $msg   = '';
        $error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add'])) {
    $title       = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price       = (float)($_POST['starting_price'] ?? 0);
    $category_id = (int)($_POST['category_id'] ?? 0);
    $city        = trim($_POST['city'] ?? '');
    $area        = trim($_POST['area'] ?? '');
    $start_time  = trim($_POST['start_time'] ?? '');
    $end_time    = trim($_POST['end_time'] ?? '');
    if ($title === '') {
        $error = 'Please enter item title.';
    } elseif ($description === '') {
        $error = 'Please enter item description.';
    } elseif ($price <= 0) {
        $error = 'Please enter a valid starting price in PKR.';
    } elseif ($city === '' || $area === '') {
        $error = 'Please provide city and area location details.';
    } elseif ($start_time === '' || $end_time === '') {
        $error = 'Please select both start and end times.';
    } else {
        $uploaded_images = [];
        if (isset($_FILES['item_images']) && !empty($_FILES['item_images']['name'][0])) {
            $files = $_FILES['item_images'];
            $file_count = count($files['name']);
            if ($file_count > 3) {
                $error = 'You can upload a maximum of 3 images.';
            } else {
                $upload_dir = __DIR__ . '/../uploads/items/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                for ($i = 0; $i < $file_count; $i++) {
                    if ($files['error'][$i] === UPLOAD_ERR_OK) {
                        $file_tmp  = $files['tmp_name'][$i];
                        $file_name = $files['name'][$i];
                        $file_size = $files['size'][$i];
                        $allowed_types = ['image/jpeg', 'image/png', 'image/webp', 'image/jpg'];
                        $file_mime = mime_content_type($file_tmp);
                        if (!in_array($file_mime, $allowed_types)) {
                            $error = 'Invalid image format for file: ' . htmlspecialchars($file_name) . '. Only JPG, PNG, and WEBP are allowed.';
                            break;
                        } elseif ($file_size > 5 * 1024 * 1024) { 
                            $error = 'Image size exceeds 5MB limit for file: ' . htmlspecialchars($file_name);
                            break;
                        } else {
                            $file_ext = pathinfo($file_name, PATHINFO_EXTENSION);
                            $new_filename = 'item_' . time() . '_' . mt_rand(1000, 9999) . '_' . $i . '.' . $file_ext;
                            $destination = $upload_dir . $new_filename;
                            if (move_uploaded_file($file_tmp, $destination)) {
                                $uploaded_images[] = 'uploads/items/' . $new_filename;
                            } else {
                                $error = 'Failed to move uploaded image file: ' . htmlspecialchars($file_name);
                                break;
                            }
                        }
                    } elseif ($files['error'][$i] !== UPLOAD_ERR_NO_FILE) {
                        $error = 'Error uploading file: ' . htmlspecialchars($files['name'][$i]);
                        break;
                    }
                }
            }
        } else {
            $error = 'Please upload at least one item image.';
        }
        if ($error === '') {
            $start_time_mysql = str_replace('T', ' ', $start_time);
            if (strlen($start_time_mysql) === 16) {
                $start_time_mysql .= ':00';
            }
            $end_time_mysql = str_replace('T', ' ', $end_time);
            if (strlen($end_time_mysql) === 16) {
                $end_time_mysql .= ':00';
            }
            $start_timestamp = strtotime($start_time_mysql);
            $end_timestamp   = strtotime($end_time_mysql);

            if ($start_timestamp === false || $end_timestamp === false) {
                $error = 'Invalid start or end time format.';
            } elseif ($end_timestamp <= $start_timestamp) {
                $error = 'Auction end time must be after the start time.';
            } else {
                do{
                    $auction_code = 'GA-' . strtoupper(
                        substr(
                            str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZ23456789'),
                            0,6
                        )
                    );

                    $check_sql = "SELECT id FROM items WHERE auction_code = ? LIMIT 1";
                    $check_stmt = mysqli_prepare($conn, $check_sql);

                    if (!$check_stmt) {
                        $error = 'Database error: ' . mysqli_error($conn);
                        break;
                    }

                    mysqli_stmt_bind_param($check_stmt, "s", $auction_code);
                    mysqli_stmt_execute($check_stmt);
                    $check_result = mysqli_stmt_get_result($check_stmt);
                    $code_exists = mysqli_num_rows($check_result) > 0;
                    mysqli_stmt_close($check_stmt);

                } while ($code_exists);

                if ($error === '') {
                    $min_increment = 1.00;
                    $featured_image_path = $uploaded_images[0] ?? null; 
                    $images_json = json_encode($uploaded_images);
                    $sql = "
                        INSERT INTO items
                        (
                            auction_code,
                            title,
                            description,
                            starting_price,
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
                        )
                        VALUES
                        (
                            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', 1, ?, ?, NOW(), NOW()
                        )
                    ";

                    $stmt = mysqli_prepare($conn, $sql);

                    if (!$stmt) {
                        $error = 'Database prepare error: ' . mysqli_error($conn);
                    } else {
                        mysqli_stmt_bind_param(
                            $stmt,
                            "sssdddiissssss",
                            $auction_code,
                            $title,
                            $description,
                            $price,
                            $price,
                            $min_increment,
                            $user_id,
                            $category_id,
                            $city,
                            $area,
                            $featured_image_path,
                            $images_json,
                            $start_time_mysql,
                            $end_time_mysql
                        );
                        if (mysqli_stmt_execute($stmt)) {
                            $new_item_id = mysqli_insert_id($conn);
                            mysqli_stmt_close($stmt);

                            foreach ($uploaded_images as $index => $img_path) {
                                $is_primary = ($index === 0) ? 1 : 0;

                                $img_sql = "INSERT INTO item_images (item_id, image_path, is_primary, created_at) VALUES (?, ?, ?, NOW())";
                                $img_stmt = mysqli_prepare($conn, $img_sql);
                                if ($img_stmt) {
                                    mysqli_stmt_bind_param($img_stmt, "isi", $new_item_id, $img_path, $is_primary);
                                    mysqli_stmt_execute($img_stmt);
                                    mysqli_stmt_close($img_stmt);
                                }
                            }

                            $msg = "Auction item published successfully! Auction Code: " . $auction_code;
                            $_POST = [];
                        } else {
                            $error = 'Failed to add item: ' . mysqli_stmt_error($stmt);
                            mysqli_stmt_close($stmt);
                        }
                    }
                }
            }
        }
    }
}

$categories = mysqli_query(
    $conn,
    "SELECT id, name FROM categories ORDER BY name ASC"
);
?>

<div style="text-align: center; margin: 40px 0;">
    <button type="button" onclick="openAuctionModal()" style="background: linear-gradient(135deg, #009940 0%, #007a33 100%); color: #ffffff; border: none; padding: 14px 28px; border-radius: 8px; font-weight: 700; cursor: pointer; font-size: 15px; box-shadow: 0 4px 12px rgba(0, 122, 51, 0.25);">
        <i class="fa-solid fa-gavel"></i> Create Auction
    </button>
</div>

<div id="auctionModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.6); z-index: 1000; overflow-y: auto; padding: 20px; box-sizing: border-box;">

    <div style="max-width: 750px; margin: 30px auto; background: #ffffff; padding: 35px; border-radius: 14px; border: 1px solid #e2e8f0; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1); font-family: 'Inter', sans-serif; position: relative;">

        <button type="button" onclick="closeAuctionModal()" style="position: absolute; top: 20px; right: 20px; background: #f1f5f9; border: none; width: 36px; height: 36px; border-radius: 50%; font-size: 18px; cursor: pointer; color: #334155; font-weight: bold;">&times;</button>

        <div style="display: flex; align-items: center; gap: 16px; margin-bottom: 30px; border-bottom: 1px solid #f1f5f9; padding-bottom: 20px;">
            <div style="width: 52px; height: 52px; background: linear-gradient(135deg, #009940 0%, #007a33 100%); color: #ffffff; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 22px; box-shadow: 0 4px 12px rgba(0, 122, 51, 0.2);">
                <i class="fa-solid fa-gavel"></i>
            </div>
            <div>
                <h2 style="font-size: 24px; font-weight: 800; color: #0f172a; margin: 0; letter-spacing: -0.5px;">Add New Auction Item</h2>
                <p style="margin: 6px 0 0; color: #64748b; font-size: 14px;">Fill in the details below to publish your item live for bidding.</p>
            </div>
        </div>

        <?php if ($msg !== ''): ?>
            <div style="background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; padding: 14px 18px; border-radius: 8px; margin-bottom: 24px; font-weight: 600; font-size: 14px; display: flex; align-items: center; gap: 10px;">
                <i class="fa-solid fa-circle-check" style="font-size: 16px;"></i> <span><?php echo htmlspecialchars($msg); ?></span>
            </div>
        <?php endif; ?>

        <?php if ($error !== ''): ?>
            <div style="background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; padding: 14px 18px; border-radius: 8px; margin-bottom: 24px; font-weight: 600; font-size: 14px; display: flex; align-items: center; gap: 10px;">
                <i class="fa-solid fa-circle-exclamation" style="font-size: 16px;"></i> <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" action="" enctype="multipart/form-data">

            <div style="margin-bottom: 20px;">
                <label style="display: block; font-weight: 600; color: #334155; margin-bottom: 8px; font-size: 14px;">Item Title <span style="color: #ef4444;">*</span></label>
                <input type="text" name="title" value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>" required placeholder="e.g. Vintage Rolex Submariner Watch" style="width: 100%; padding: 12px 16px; border: 1px solid #cbd5e1; border-radius: 8px; box-sizing: border-box; font-size: 14px; color: #0f172a;">
            </div>

            <div style="margin-bottom: 20px;">
                <label style="display: block; font-weight: 600; color: #334155; margin-bottom: 8px; font-size: 14px;">Category <span style="color: #ef4444;">*</span></label>
                <select name="category_id" required style="width: 100%; padding: 12px 16px; border: 1px solid #cbd5e1; border-radius: 8px; box-sizing: border-box; background: #ffffff; font-size: 14px; color: #0f172a;">
                    <option value="">-- Select Category --</option>
                    <?php if ($categories): ?>
                        <?php while ($cat = mysqli_fetch_assoc($categories)): ?>
                            <option value="<?php echo (int)$cat['id']; ?>" <?php echo ((int)($_POST['category_id'] ?? 0) === (int)$cat['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['name']); ?>
                            </option>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </select>
            </div>

            <div style="margin-bottom: 20px;">
                <label style="display: block; font-weight: 600; color: #334155; margin-bottom: 8px; font-size: 14px;">Starting Price (PKR) <span style="color: #ef4444;">*</span></label>
                <div style="position: relative;">
                    <span style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: #64748b; font-weight: 600; font-size: 14px;">Rs.</span>
                    <input type="number" step="0.01" min="1" name="starting_price" value="<?php echo htmlspecialchars($_POST['starting_price'] ?? ''); ?>" required placeholder="0.00" style="width: 100%; padding: 12px 16px 12px 45px; border: 1px solid #cbd5e1; border-radius: 8px; box-sizing: border-box; font-size: 14px; color: #0f172a;">
                </div>
            </div>

            <div style="margin-bottom: 20px;">
                <label style="display: block; font-weight: 600; color: #334155; margin-bottom: 8px; font-size: 14px;">Description <span style="color: #ef4444;">*</span></label>
                <textarea name="description" required rows="4" placeholder="Provide complete specifications, model details, and condition..." style="width: 100%; padding: 12px 16px; border: 1px solid #cbd5e1; border-radius: 8px; box-sizing: border-box; resize: vertical; font-size: 14px; color: #0f172a;"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
            </div>
            <div style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
                <h3 style="font-size: 15px; font-weight: 700; color: #334155; margin-top: 0; margin-bottom: 15px;"><i class="fa-solid fa-location-dot"></i> Location Information</h3>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                    <div>
                        <label style="display: block; font-weight: 600; color: #334155; margin-bottom: 6px; font-size: 13px;">City <span style="color: #ef4444;">*</span></label>
                        <input type="text" name="city" value="<?php echo htmlspecialchars($_POST['city'] ?? ''); ?>" required placeholder="e.g. Karachi" style="width: 100%; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px; background: #fff;">
                    </div>
                    <div>
                        <label style="display: block; font-weight: 600; color: #334155; margin-bottom: 6px; font-size: 13px;">Area <span style="color: #ef4444;">*</span></label>
                        <input type="text" name="area" value="<?php echo htmlspecialchars($_POST['area'] ?? ''); ?>" required placeholder="e.g. Clifton" style="width: 100%; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px; background: #fff;">
                    </div>
                </div>

            </div>

            <div style="margin-bottom: 20px;">
                <label style="display: block; font-weight: 600; color: #334155; margin-bottom: 8px; font-size: 14px;">Item Images (Upload 2 to 3 images) <span style="color: #ef4444;">*</span></label>
                <div style="border: 2px dashed #cbd5e1; padding: 20px; border-radius: 8px; background: #f8fafc; text-align: center;">
                    <input type="file" id="imageInput" name="item_images[]" accept="image/jpeg, image/png, image/webp" multiple required style="width: 100%; font-size: 14px; cursor: pointer;">
                    <small style="display: block; margin-top: 8px; color: #64748b; font-size: 12px;">Supported formats: JPG, PNG, WEBP (Max size: 5MB per image)</small>
                </div>
                <div id="imagePreviewContainer" style="display: flex; gap: 12px; flex-wrap: wrap; margin-top: 12px;"></div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 24px;">
                <div>
                    <label style="display: block; font-weight: 600; color: #334155; margin-bottom: 8px; font-size: 14px;">Start Time <span style="color: #ef4444;">*</span></label>
                    <input type="datetime-local" name="start_time" value="<?php echo htmlspecialchars($_POST['start_time'] ?? ''); ?>" required style="width: 100%; padding: 11px 16px; border: 1px solid #cbd5e1; border-radius: 8px; box-sizing: border-box; font-size: 14px; color: #0f172a; background: #fff;">
                </div>
                <div>
                    <label style="display: block; font-weight: 600; color: #334155; margin-bottom: 8px; font-size: 14px;">Auction End Time <span style="color: #ef4444;">*</span></label>
                    <input type="datetime-local" name="end_time" value="<?php echo htmlspecialchars($_POST['end_time'] ?? ''); ?>" required style="width: 100%; padding: 11px 16px; border: 1px solid #cbd5e1; border-radius: 8px; box-sizing: border-box; font-size: 14px; color: #0f172a; background: #fff;">
                </div>
            </div>

            <button type="submit" name="add" style="width: 100%; background: linear-gradient(135deg, #009940 0%, #007a33 100%); color: #ffffff; border: none; padding: 14px 24px; border-radius: 8px; font-weight: 700; cursor: pointer; font-size: 15px; box-shadow: 0 4px 12px rgba(0, 122, 51, 0.25);">
                <i class="fa-solid fa-gavel"></i> Publish Auction Item
            </button>

        </form>
    </div>
</div>

<script>
function openAuctionModal() {
    document.getElementById('auctionModal').style.display = 'block';
}

function closeAuctionModal() {
    document.getElementById('auctionModal').style.display = 'none';
}

<?php if (!empty($error) || !empty($msg)): ?>
    window.addEventListener('DOMContentLoaded', () => {
        openAuctionModal();
    });
<?php endif; ?>

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
            wrapper.style.cssText = "position: relative; width: 80px; height: 80px; border: 1px solid #cbd5e1; border-radius: 8px; overflow: hidden; background: #f8fafc; box-shadow: 0 2px 4px rgba(0,0,0,0.05);";
            
            const img = document.createElement('img');
            img.src = e.target.result;
            img.style.cssText = "width: 100%; height: 100%; object-fit: cover;";
            
            const removeBtn = document.createElement('span');
            removeBtn.innerHTML = '&times;';
            removeBtn.style.cssText = "position: absolute; top: 4px; right: 4px; background: rgba(15, 23, 42, 0.75); color: white; border-radius: 50%; width: 20px; height: 20px; font-size: 14px; line-height: 18px; text-align: center; cursor: pointer; font-weight: bold;";
            
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
</script>

<?php
include_once __DIR__ . '/../includes/footer.php';
?>