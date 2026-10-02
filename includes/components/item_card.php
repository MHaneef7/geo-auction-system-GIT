<?php
/**
 * Unified Card Component for Auction Products/Items
 * Geo Auction System
 *
 * Implements:
 * - Consistent Card UI across Home, Marketplace, Seller Dashboard, Admin Panel
 * - Common Elements: Image carousel, Title, Price, Description snippet, Category/Tags, Status badge
 * - "View Details" Quick-View Modal showing full specs, images, seller info
 * - Role-Based Access Control:
 *     * Buyer: View Details, Place Bid Now / Buy Now (No Edit/Delete)
 *     * Seller: View Details, Edit (own cards), Soft-Delete
 *     * Admin: View Details, Edit, Soft-Delete, Restore, Permanent Purge
 * - Soft-Delete Visual Indicator:
 *     * Strikethrough style (text-decoration: line-through)
 *     * "Deleted by Seller" / "Soft Deleted" badge
 *     * Immediate exclusion from public buyer catalog
 */

if (!function_exists('cleanCardImagePath')) {
    function cleanCardImagePath($path, $is_subfolder = false) {
        if (empty($path)) return ($is_subfolder ? '../' : '') . 'assets/image/Geo-auction.png';
        $path = trim((string)$path, " \t\n\r\0\x0B\"'[]\\");
        if (preg_match('/^(https?:\/\/|\/\/|data:)/i', $path)) {
            return $path;
        }
        if (preg_match('/^https?:\/[^\/]/i', $path)) {
            return preg_replace('/^(https?):\/([^\/])/i', '$1://$2', $path);
        }
        $path = str_replace(['\\/', '//'], '/', $path);
        $path = trim($path);
        if (empty($path)) return ($is_subfolder ? '../' : '') . 'assets/image/Geo-auction.png';
        if ($is_subfolder && strpos($path, '../') !== 0) {
            return '../' . $path;
        }
        return $path;
    }
}

/**
 * Render a single Unified Item Card
 *
 * @param array  $item              Associative array of item details from DB
 * @param string $user_role         'admin' | 'seller' | 'buyer' | 'guest'
 * @param int    $current_user_id   ID of currently logged-in user
 * @param array  $options           Optional flags (e.g. is_subfolder, show_actions, admin_table)
 */
function render_unified_item_card($item, $user_role = 'buyer', $current_user_id = 0, $options = []) {
    $is_subfolder = !empty($options['is_subfolder']);
    $base_prefix  = $is_subfolder ? '../' : '';

    $item_id     = (int)($item['id'] ?? 0);
    $title       = $item['title'] ?? 'Untitled Auction Lot';
    $code        = $item['auction_code'] ?? ('LOT-' . $item_id);
    $cat_name    = $item['cat_name'] ?? ($item['category_name'] ?? 'General Lot');
    $seller_id   = (int)($item['seller_id'] ?? 0);
    $seller_name = $item['seller_name'] ?? 'Verified Seller';
    $city        = $item['location_city'] ?: ($item['city'] ?: 'Pakistan');
    $condition   = $item['condition_type'] ?? '';
    $raw_desc    = $item['short_description'] ?: ($item['description'] ?? '');
    $snippet     = mb_substr(strip_tags($raw_desc), 0, 110) . (mb_strlen(strip_tags($raw_desc)) > 110 ? '...' : '');

    // Pricing
    $starting_price = (float)($item['starting_price'] ?? 0);
    $current_price  = (float)($item['current_price'] ?? $starting_price);
    $effective_price = $current_price > 0 ? $current_price : $starting_price;
    $bid_count      = (int)($item['bid_count'] ?? 0);

    // Timing & Status
    $status        = strtolower(trim($item['status'] ?? 'active'));
    $is_deleted    = !empty($item['is_deleted']);
    $deleted_by    = (int)($item['deleted_by'] ?? 0);
    $end_time_raw  = $item['end_time'] ?? null;
    $end_ts        = !empty($end_time_raw) ? strtotime($end_time_raw) : 0;
    $diff_sec      = $end_ts > 0 ? ($end_ts - time()) : 0;
    $is_urgent     = (!$is_deleted && $status === 'active' && $diff_sec > 0 && $diff_sec <= 7200);
    $is_ended      = ($status === 'ended' || ($end_ts > 0 && $diff_sec <= 0));

    // Images
    $image_list = [];
    $raw_images = $item['images'] ?? '';
    if (!empty($raw_images)) {
        $decoded = json_decode($raw_images, true);
        if (is_array($decoded)) {
            foreach ($decoded as $p) {
                $c = cleanCardImagePath(is_string($p) ? $p : ($p['image_path'] ?? ''), $is_subfolder);
                if (!empty($c) && !in_array($c, $image_list)) $image_list[] = $c;
            }
        } else {
            $parts = explode(',', $raw_images);
            foreach ($parts as $p) {
                $c = cleanCardImagePath($p, $is_subfolder);
                if (!empty($c) && !in_array($c, $image_list)) $image_list[] = $c;
            }
        }
    }
    if (!empty($item['featured_image'])) {
        $feat = cleanCardImagePath($item['featured_image'], $is_subfolder);
        if (!empty($feat) && !in_array($feat, $image_list)) {
            array_unshift($image_list, $feat);
        }
    }
    if (empty($image_list)) {
        $image_list = [$base_prefix . 'assets/image/Geo-auction.png'];
    }

    // Role Capabilities
    $is_admin  = ($user_role === 'admin');
    $is_seller = ($user_role === 'seller' || ($seller_id > 0 && $seller_id === $current_user_id));
    $is_owner  = ($seller_id > 0 && $seller_id === $current_user_id);

    // Links
    $detail_link = $base_prefix . 'live_auction.php?id=' . $item_id;
    $edit_link   = $base_prefix . 'user/edit_item.php?id=' . $item_id;
    $delete_url  = $base_prefix . 'user/delete_item.php?id=' . $item_id;

    // JSON payload for Quick View modal
    $quickview_payload = [
        'id'               => $item_id,
        'auction_code'     => $code,
        'title'            => $title,
        'category'         => $cat_name,
        'seller_name'      => $seller_name,
        'seller_id'        => $seller_id,
        'city'             => $city,
        'condition'        => $condition,
        'current_price'    => number_format($effective_price, 2),
        'starting_price'   => number_format($starting_price, 2),
        'bid_count'        => $bid_count,
        'status'           => $is_deleted ? 'Deleted' : ucfirst($status),
        'is_deleted'       => $is_deleted,
        'is_urgent'        => $is_urgent,
        'description'      => !empty($item['description']) ? $item['description'] : 'No extended description provided for this lot.',
        'brand'            => $item['brand'] ?? 'N/A',
        'model'            => $item['model'] ?? 'N/A',
        'year'             => !empty($item['year']) ? $item['year'] : 'N/A',
        'mileage'          => $item['mileage'] ?? 'N/A',
        'engine_type'      => $item['engine_type'] ?? 'N/A',
        'color'            => $item['color'] ?? 'N/A',
        'images'           => $image_list,
        'detail_link'      => $detail_link,
        'edit_link'        => $edit_link,
        'delete_url'       => $delete_url,
        'base_prefix'      => $base_prefix,
        'can_edit'         => ($is_admin || ($is_seller && $is_owner)),
        'can_delete'       => ($is_admin || ($is_seller && $is_owner)),
        'is_admin'         => $is_admin
    ];
    $payload_json = htmlspecialchars(json_encode($quickview_payload), ENT_QUOTES, 'UTF-8');
    ?>

    <article class="geo-item-card <?php echo $is_deleted ? 'geo-card-deleted' : ''; ?>" data-item-id="<?php echo $item_id; ?>">
        <!-- Card Header / Image Carousel Wrap -->
        <div class="geo-card-media-wrap">
            <div class="geo-card-slides" style="width: <?php echo count($image_list) * 100; ?>%;">
                <?php foreach ($image_list as $img_src): ?>
                    <div class="geo-card-slide" style="width: <?php echo 100 / count($image_list); ?>%;">
                        <img src="<?php echo htmlspecialchars($img_src); ?>" 
                             alt="<?php echo htmlspecialchars($title); ?>" 
                             loading="lazy" 
                             onerror="this.onerror=null;this.src='<?php echo $base_prefix; ?>assets/image/Geo-auction.png';">
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if (count($image_list) > 1): ?>
                <button type="button" class="geo-slide-btn geo-slide-prev" aria-label="Previous"><i class="fas fa-chevron-left"></i></button>
                <button type="button" class="geo-slide-btn geo-slide-next" aria-label="Next"><i class="fas fa-chevron-right"></i></button>
                <div class="geo-slide-dots">
                    <?php for ($d = 0; $d < count($image_list); $d++): ?>
                        <span class="geo-dot <?php echo $d === 0 ? 'active' : ''; ?>"></span>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>

            <!-- Badges Overlay -->
            <div class="geo-badges-stack">
                <?php if ($is_deleted): ?>
                    <span class="geo-badge geo-badge-deleted">
                        <i class="fas fa-trash-alt"></i> Deleted by Seller
                    </span>
                <?php elseif ($is_urgent): ?>
                    <span class="geo-badge geo-badge-urgent">
                        <span class="geo-pulse"></span> Ending Soon
                    </span>
                <?php elseif ($status === 'active'): ?>
                    <span class="geo-badge geo-badge-active">
                        <span class="geo-pulse"></span> Live Lot
                    </span>
                <?php elseif ($status === 'ended' || $is_ended): ?>
                    <span class="geo-badge geo-badge-ended">
                        <i class="fas fa-flag-checkered"></i> Ended
                    </span>
                <?php elseif ($status === 'sold'): ?>
                    <span class="geo-badge geo-badge-sold">
                        <i class="fas fa-check-double"></i> Sold
                    </span>
                <?php else: ?>
                    <span class="geo-badge geo-badge-pending">
                        <?php echo ucfirst($status); ?>
                    </span>
                <?php endif; ?>

                <span class="geo-badge geo-badge-cat">
                    <i class="fas fa-tag"></i> <?php echo htmlspecialchars($cat_name); ?>
                </span>
            </div>
        </div>

        <!-- Card Content Body -->
        <div class="geo-card-body">
            <!-- Meta Bar -->
            <div class="geo-meta-row">
                <span class="geo-meta-city"><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($city); ?></span>
                <?php if (!empty($condition)): ?>
                    <span class="geo-meta-tag"><?php echo htmlspecialchars(ucfirst($condition)); ?></span>
                <?php endif; ?>
            </div>

            <!-- Title (Strikethrough if deleted) -->
            <h3 class="geo-card-title <?php echo $is_deleted ? 'geo-strikethrough' : ''; ?>">
                <a href="<?php echo $detail_link; ?>" title="<?php echo htmlspecialchars($title); ?>">
                    <?php echo htmlspecialchars($title); ?>
                </a>
            </h3>

            <!-- Description snippet -->
            <?php if (!empty($snippet)): ?>
                <p class="geo-card-desc <?php echo $is_deleted ? 'geo-strikethrough-light' : ''; ?>">
                    <?php echo htmlspecialchars($snippet); ?>
                </p>
            <?php endif; ?>

            <!-- Countdown Timer Container -->
            <div class="geo-timer-box">
                <span class="geo-timer-lbl"><i class="far fa-clock"></i> Time Left:</span>
                <span class="geo-timer-val <?php echo $is_urgent ? 'urgent' : ''; ?>" data-end-ts="<?php echo $end_ts; ?>">
                    <?php echo $is_deleted ? 'Archived' : ($is_ended ? 'Auction Closed' : 'Calculating...'); ?>
                </span>
            </div>

            <!-- Price & Bid Stats Row -->
            <div class="geo-pricing-row">
                <div>
                    <span class="geo-price-label">Current Bid</span>
                    <div class="geo-price-value <?php echo $is_deleted ? 'geo-strikethrough' : ''; ?>">
                        Rs. <?php echo number_format($effective_price, 2); ?>
                    </div>
                </div>
                <div class="geo-bids-badge">
                    <i class="fas fa-gavel"></i> <?php echo $bid_count; ?> <?php echo $bid_count === 1 ? 'Bid' : 'Bids'; ?>
                </div>
            </div>
        </div>

        <!-- Role-Based Action Footer -->
        <div class="geo-card-footer">
            <div class="geo-btn-group">
                <!-- Universal "View Details" Feature -->
                <button type="button" 
                        class="geo-btn geo-btn-secondary" 
                        onclick="openUnifiedQuickView(this)" 
                        data-payload="<?php echo $payload_json; ?>"
                        title="View Full Specifications & Details">
                    <i class="fas fa-eye"></i> View Details
                </button>

                <!-- BUYER VIEW -->
                <?php if (!$is_admin && !$is_seller): ?>
                    <?php if (!$is_deleted && $status === 'active' && !$is_ended): ?>
                        <a href="<?php echo $detail_link; ?>" class="geo-btn geo-btn-primary">
                            <i class="fas fa-bolt"></i> Place Bid Now
                        </a>
                    <?php else: ?>
                        <a href="<?php echo $detail_link; ?>" class="geo-btn geo-btn-disabled">
                            <i class="fas fa-lock"></i> <?php echo $is_deleted ? 'Unavailable' : 'View Lot'; ?>
                        </a>
                    <?php endif; ?>

                <!-- SELLER VIEW -->
                <?php elseif ($is_seller && !$is_admin): ?>
                    <?php if ($is_owner && !$is_deleted): ?>
                        <a href="<?php echo $edit_link; ?>" class="geo-btn geo-btn-warning" title="Edit Item">
                            <i class="fas fa-pen"></i> Edit
                        </a>
                        <a href="<?php echo $delete_url; ?>" 
                           onclick="return confirm('Soft-delete this auction listing? It will immediately disappear from the public marketplace.');" 
                           class="geo-btn geo-btn-danger" 
                           title="Soft Delete Item">
                            <i class="fas fa-trash-alt"></i> Delete
                        </a>
                    <?php elseif ($is_deleted): ?>
                        <span class="geo-badge-pill-deleted"><i class="fas fa-ban"></i> Soft Deleted</span>
                    <?php else: ?>
                        <a href="<?php echo $detail_link; ?>" class="geo-btn geo-btn-primary">
                            <i class="fas fa-gavel"></i> Bid Arena
                        </a>
                    <?php endif; ?>

                <!-- ADMIN VIEW -->
                <?php elseif ($is_admin): ?>
                    <?php if ($is_deleted): ?>
                        <!-- Admin Restore Action -->
                        <form method="POST" action="<?php echo $base_prefix; ?>admin/items.php" class="geo-inline-form">
                            <input type="hidden" name="action" value="restore">
                            <input type="hidden" name="item_id" value="<?php echo $item_id; ?>">
                            <button type="submit" class="geo-btn geo-btn-restore" title="Restore Soft-Deleted Item">
                                <i class="fas fa-undo"></i> Restore
                            </button>
                        </form>
                        <!-- Admin Permanent Purge Action -->
                        <form method="POST" action="<?php echo $base_prefix; ?>admin/items.php" class="geo-inline-form" onsubmit="return confirm('PERMANENT PURGE: This will completely delete the lot, bids, and media forever from the database. Proceed?');">
                            <input type="hidden" name="action" value="purge">
                            <input type="hidden" name="item_id" value="<?php echo $item_id; ?>">
                            <button type="submit" class="geo-btn geo-btn-purge" title="Permanently Delete (Hard Purge)">
                                <i class="fas fa-fire"></i> Purge
                            </button>
                        </form>
                    <?php else: ?>
                        <a href="<?php echo $edit_link; ?>" class="geo-btn geo-btn-warning" title="Edit Item">
                            <i class="fas fa-pen"></i>
                        </a>
                        <form method="POST" action="<?php echo $base_prefix; ?>admin/items.php" class="geo-inline-form" onsubmit="return confirm('Soft-delete this auction lot?');">
                            <input type="hidden" name="action" value="soft_delete">
                            <input type="hidden" name="item_id" value="<?php echo $item_id; ?>">
                            <button type="submit" class="geo-btn geo-btn-danger" title="Soft Delete Item">
                                <i class="fas fa-trash"></i>
                            </button>
                        </form>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </article>
<?php
}

/**
 * Output the Unified Card Styling & Quick-View Modal Engine
 * Call this once per page or in header/footer
 */
function render_unified_card_assets() {
    static $assets_rendered = false;
    if ($assets_rendered) return;
    $assets_rendered = true;
    ?>
    <style>
        /* =============================================================
           Unified Card Styles
           ============================================================= */
        .geo-item-card {
            background: #ffffff;
            border-radius: 16px;
            overflow: hidden;
            border: 1px solid #e2e8f0;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            display: flex;
            flex-direction: column;
            transition: transform 0.25s ease, box-shadow 0.25s ease, border-color 0.25s ease;
            position: relative;
            box-sizing: border-box;
            height: 100%;
        }
        .geo-item-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 16px 24px -6px rgba(0, 0, 0, 0.1);
            border-color: #cbd5e1;
        }

        /* Soft-Deleted Visual Indicator */
        .geo-card-deleted {
            background: #fffafa;
            border-color: #fecaca !important;
            border-style: dashed !important;
            opacity: 0.88;
        }
        .geo-card-deleted:hover {
            opacity: 1;
        }
        .geo-strikethrough {
            text-decoration: line-through !important;
            color: #dc2626 !important;
            opacity: 0.85;
        }
        .geo-strikethrough-light {
            text-decoration: line-through !important;
            color: #94a3b8 !important;
        }

        /* Media Carousel */
        .geo-card-media-wrap {
            position: relative;
            width: 100%;
            height: 215px;
            overflow: hidden;
            background: #0f172a;
            border-top-left-radius: 15px;
            border-top-right-radius: 15px;
        }
        .geo-card-slides {
            display: flex;
            height: 100%;
            transition: transform 0.35s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .geo-card-slide {
            height: 100%;
            flex-shrink: 0;
            position: relative;
        }
        .geo-card-slide img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        /* Slide navigation buttons */
        .geo-slide-btn {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: rgba(15, 23, 42, 0.7);
            backdrop-filter: blur(4px);
            color: #ffffff;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            z-index: 5;
            font-size: 12px;
            opacity: 0;
            transition: opacity 0.2s ease, background 0.2s ease;
        }
        .geo-item-card:hover .geo-slide-btn { opacity: 1; }
        .geo-slide-btn:hover { background: rgba(15, 23, 42, 0.95); }
        .geo-slide-prev { left: 8px; }
        .geo-slide-next { right: 8px; }

        .geo-slide-dots {
            position: absolute;
            bottom: 8px;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            gap: 4px;
            z-index: 5;
        }
        .geo-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.5);
            transition: all 0.2s ease;
        }
        .geo-dot.active {
            background: #ffffff;
            width: 14px;
            border-radius: 9999px;
        }

        /* Badges Stack */
        .geo-badges-stack {
            position: absolute;
            top: 12px;
            left: 12px;
            z-index: 6;
            display: flex;
            flex-direction: column;
            gap: 6px;
            align-items: flex-start;
        }
        .geo-badge {
            font-size: 11px;
            font-weight: 700;
            padding: 4px 10px;
            border-radius: 9999px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            backdrop-filter: blur(6px);
            color: #ffffff;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .geo-badge-active { background: rgba(4, 120, 87, 0.92); }
        .geo-badge-urgent { background: rgba(220, 38, 38, 0.92); }
        .geo-badge-deleted { background: rgba(185, 28, 28, 0.95); box-shadow: 0 2px 6px rgba(185, 28, 28, 0.3); }
        .geo-badge-ended { background: rgba(71, 85, 105, 0.9); }
        .geo-badge-sold { background: rgba(30, 64, 175, 0.9); }
        .geo-badge-pending { background: rgba(217, 119, 6, 0.9); }
        .geo-badge-cat {
            background: rgba(15, 23, 42, 0.75);
            font-weight: 600;
            text-transform: none;
            letter-spacing: normal;
        }
        .geo-pulse {
            width: 6px;
            height: 6px;
            background: #34d399;
            border-radius: 50%;
            animation: geo-pulse-ring 1.6s infinite ease-in-out;
        }
        .geo-badge-urgent .geo-pulse { background: #ffffff; }
        @keyframes geo-pulse-ring {
            0% { transform: scale(0.9); box-shadow: 0 0 0 0 rgba(52, 211, 153, 0.8); }
            70% { transform: scale(1); box-shadow: 0 0 0 6px rgba(52, 211, 153, 0); }
            100% { transform: scale(0.9); box-shadow: 0 0 0 0 rgba(52, 211, 153, 0); }
        }

        /* Card Body */
        .geo-card-body {
            padding: 16px;
            display: flex;
            flex-direction: column;
            flex-grow: 1;
        }
        .geo-meta-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 12px;
            color: #64748b;
            margin-bottom: 8px;
        }
        .geo-meta-city {
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .geo-meta-tag {
            font-weight: 600;
            text-transform: capitalize;
            color: #475569;
        }
        .geo-card-title {
            font-size: 16px;
            font-weight: 700;
            color: #0f172a;
            margin: 0 0 6px 0;
            line-height: 1.35;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            text-overflow: ellipsis;
            min-height: 42px;
        }
        .geo-card-title a {
            color: inherit;
            text-decoration: none;
            transition: color 0.2s ease;
        }
        .geo-card-title a:hover { color: #047857; }
        .geo-card-desc {
            font-size: 13px;
            color: #64748b;
            line-height: 1.4;
            margin: 0 0 12px 0;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* Countdown Box */
        .geo-timer-box {
            background: #f1f5f9;
            border-radius: 8px;
            padding: 8px 12px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 12px;
            font-size: 12px;
            color: #334155;
        }
        .geo-timer-val {
            font-family: monospace, inherit;
            font-weight: 700;
            font-size: 13px;
            color: #0f172a;
        }
        .geo-timer-val.urgent { color: #ef4444; }

        /* Pricing & Bids */
        .geo-pricing-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-top: auto;
            padding-top: 10px;
            border-top: 1px solid #f1f5f9;
        }
        .geo-price-label {
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            color: #64748b;
            display: block;
            margin-bottom: 2px;
        }
        .geo-price-value {
            font-size: 18px;
            font-weight: 800;
            color: #047857;
            line-height: 1.1;
        }
        .geo-bids-badge {
            font-size: 12px;
            font-weight: 600;
            color: #334155;
            background: #f1f5f9;
            padding: 4px 8px;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        /* Card Action Footer */
        .geo-card-footer {
            padding: 12px 16px;
            background: #ffffff;
            border-top: 1px solid #f1f5f9;
        }
        .geo-btn-group {
            display: flex;
            gap: 8px;
            align-items: center;
        }
        .geo-inline-form {
            display: inline-flex;
            margin: 0;
            padding: 0;
        }
        .geo-btn {
            height: 38px;
            padding: 0 14px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.2s ease;
            border: 1px solid transparent;
            box-sizing: border-box;
            white-space: nowrap;
        }
        .geo-btn-primary {
            background: #047857;
            color: #ffffff;
            flex: 1;
        }
        .geo-btn-primary:hover { background: #065f46; color: #fff; }
        .geo-btn-secondary {
            background: #ffffff;
            color: #334155;
            border-color: #cbd5e1;
        }
        .geo-btn-secondary:hover { background: #f8fafc; border-color: #94a3b8; }
        .geo-btn-warning {
            background: #fffbeb;
            color: #b45309;
            border-color: #fde68a;
        }
        .geo-btn-warning:hover { background: #fef3c7; }
        .geo-btn-danger {
            background: #fef2f2;
            color: #b91c1c;
            border-color: #fecaca;
        }
        .geo-btn-danger:hover { background: #fee2e2; }
        .geo-btn-restore {
            background: #ecfdf5;
            color: #047857;
            border-color: #a7f3d0;
        }
        .geo-btn-restore:hover { background: #d1fae5; }
        .geo-btn-purge {
            background: #dc2626;
            color: #ffffff;
        }
        .geo-btn-purge:hover { background: #b91c1c; }
        .geo-btn-disabled {
            background: #f1f5f9;
            color: #94a3b8;
            pointer-events: none;
            flex: 1;
        }
        .geo-badge-pill-deleted {
            font-size: 11px;
            font-weight: 700;
            background: #fef2f2;
            color: #b91c1c;
            border: 1px solid #fecaca;
            padding: 6px 12px;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        /* =============================================================
           Quick-View Details Modal
           ============================================================= */
        .geo-modal-backdrop {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.7);
            backdrop-filter: blur(6px);
            z-index: 99999;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 16px;
            opacity: 0;
            transition: opacity 0.25s ease;
        }
        .geo-modal-backdrop.active {
            display: flex;
            opacity: 1;
        }
        .geo-modal-dialog {
            background: #ffffff;
            border-radius: 20px;
            width: 100%;
            max-width: 820px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            display: flex;
            flex-direction: column;
            transform: scale(0.96);
            transition: transform 0.25s ease;
        }
        .geo-modal-backdrop.active .geo-modal-dialog {
            transform: scale(1);
        }
        .geo-modal-header {
            padding: 18px 24px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            background: #ffffff;
            z-index: 10;
        }
        .geo-modal-title {
            font-size: 18px;
            font-weight: 800;
            color: #0f172a;
            margin: 0;
        }
        .geo-modal-close-btn {
            background: #f1f5f9;
            border: none;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #64748b;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .geo-modal-close-btn:hover { background: #e2e8f0; color: #0f172a; }

        .geo-modal-body {
            padding: 24px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
        }
        @media (max-width: 768px) {
            .geo-modal-body { grid-template-columns: 1fr; }
        }
        .geo-modal-gallery {
            position: relative;
            border-radius: 12px;
            overflow: hidden;
            background: #0f172a;
            height: 280px;
        }
        .geo-modal-gallery img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .geo-specs-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-top: 14px;
            background: #f8fafc;
            padding: 12px;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            font-size: 12px;
        }
        .geo-spec-item span {
            color: #64748b;
            display: block;
            font-size: 11px;
            text-transform: uppercase;
        }
        .geo-spec-item strong {
            color: #0f172a;
            font-weight: 700;
        }
        .geo-modal-footer {
            padding: 18px 24px;
            border-top: 1px solid #e2e8f0;
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            background: #f8fafc;
            border-bottom-left-radius: 20px;
            border-bottom-right-radius: 20px;
        }
    </style>

    <!-- Quick-View Modal HTML Container -->
    <div id="geoQuickViewBackdrop" class="geo-modal-backdrop" onclick="closeUnifiedQuickView(event)">
        <div class="geo-modal-dialog" onclick="event.stopPropagation()">
            <div class="geo-modal-header">
                <div>
                    <span id="qvLotCode" class="text-xs font-mono font-bold px-2 py-0.5 rounded bg-slate-100 text-slate-600">LOT-00</span>
                    <h3 id="qvLotTitle" class="geo-modal-title mt-1">Auction Item Details</h3>
                </div>
                <button type="button" class="geo-modal-close-btn" onclick="closeUnifiedQuickView()"><i class="fas fa-times"></i></button>
            </div>

            <div class="geo-modal-body">
                <!-- Gallery Column -->
                <div>
                    <div class="geo-modal-gallery">
                        <img id="qvMainImage" src="" alt="Item Image">
                    </div>
                    <div id="qvThumbsRow" style="display: flex; gap: 8px; margin-top: 10px; overflow-x: auto; padding-bottom: 4px;"></div>
                    
                    <!-- Seller & Location Card -->
                    <div style="margin-top: 14px; padding: 12px; border-radius: 12px; border: 1px solid #e2e8f0; background: #fff; font-size: 13px;">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <span style="font-size: 11px; color: #64748b; text-transform: uppercase;">Seller Information</span>
                                <div id="qvSellerName" style="font-weight: 700; color: #0f172a;">Seller Name</div>
                            </div>
                            <span style="font-size: 11px; background: #ecfdf5; color: #047857; font-weight: 700; padding: 3px 8px; border-radius: 9999px;">
                                <i class="fas fa-shield-alt"></i> Verified Seller
                            </span>
                        </div>
                        <div id="qvLocation" style="color: #64748b; font-size: 12px; margin-top: 4px;">
                            <i class="fas fa-map-marker-alt text-emerald-700"></i> Location
                        </div>
                    </div>
                </div>

                <!-- Specs & Description Column -->
                <div>
                    <div style="display: flex; justify-content: space-between; align-items: flex-end; padding-bottom: 12px; border-bottom: 1px solid #e2e8f0;">
                        <div>
                            <span style="font-size: 11px; color: #64748b; text-transform: uppercase; font-weight: 600;">Current Price</span>
                            <div id="qvPrice" style="font-size: 24px; font-weight: 800; color: #047857;">Rs. 0.00</div>
                        </div>
                        <div id="qvStatusBadge"></div>
                    </div>

                    <div style="margin-top: 12px;">
                        <span style="font-size: 12px; font-weight: 700; color: #0f172a; text-transform: uppercase;">Specifications</span>
                        <div class="geo-specs-grid">
                            <div class="geo-spec-item"><span>Category</span><strong id="qvCat">N/A</strong></div>
                            <div class="geo-spec-item"><span>Condition</span><strong id="qvCond">N/A</strong></div>
                            <div class="geo-spec-item"><span>Brand</span><strong id="qvBrand">N/A</strong></div>
                            <div class="geo-spec-item"><span>Model</span><strong id="qvModel">N/A</strong></div>
                            <div class="geo-spec-item"><span>Year</span><strong id="qvYear">N/A</strong></div>
                            <div class="geo-spec-item"><span>Mileage</span><strong id="qvMileage">N/A</strong></div>
                            <div class="geo-spec-item"><span>Engine</span><strong id="qvEngine">N/A</strong></div>
                            <div class="geo-spec-item"><span>Color</span><strong id="qvColor">N/A</strong></div>
                        </div>
                    </div>

                    <div style="margin-top: 14px;">
                        <span style="font-size: 12px; font-weight: 700; color: #0f172a; text-transform: uppercase;">Description</span>
                        <p id="qvDesc" style="font-size: 13px; color: #475569; line-height: 1.5; margin: 4px 0 0 0; max-height: 120px; overflow-y: auto;"></p>
                    </div>
                </div>
            </div>

            <div class="geo-modal-footer">
                <button type="button" class="geo-btn geo-btn-secondary" onclick="closeUnifiedQuickView()">Close</button>
                <a id="qvActionMessage" href="#" class="geo-btn" style="background:#e0f2fe; color:#0369a1; border:1px solid #bae6fd; display:none;">
                    <i class="fas fa-comment-dots"></i> Message Seller
                </a>
                <a id="qvActionPrimary" href="#" class="geo-btn geo-btn-primary">
                    <i class="fas fa-bolt"></i> Bid in Live Arena
                </a>
            </div>
        </div>
    </div>

    <!-- Interactive Card & Carousel Script -->
    <script>
    function openUnifiedQuickView(buttonEl) {
        try {
            const data = JSON.parse(buttonEl.getAttribute('data-payload'));
            document.getElementById('qvLotCode').textContent = '#' + data.auction_code;
            document.getElementById('qvLotTitle').textContent = data.title;
            document.getElementById('qvPrice').textContent = 'Rs. ' + data.current_price;
            document.getElementById('qvCat').textContent = data.category || 'General';
            document.getElementById('qvCond').textContent = data.condition || 'Pre-owned';
            document.getElementById('qvBrand').textContent = data.brand || 'N/A';
            document.getElementById('qvModel').textContent = data.model || 'N/A';
            document.getElementById('qvYear').textContent = data.year || 'N/A';
            document.getElementById('qvMileage').textContent = data.mileage || 'N/A';
            document.getElementById('qvEngine').textContent = data.engine_type || 'N/A';
            document.getElementById('qvColor').textContent = data.color || 'N/A';
            document.getElementById('qvSellerName').textContent = data.seller_name || 'Verified Seller';
            document.getElementById('qvLocation').innerHTML = '<i class="fas fa-map-marker-alt"></i> ' + (data.city || 'Pakistan');
            document.getElementById('qvDesc').textContent = data.description || '';

            // Status Badge
            const badgeEl = document.getElementById('qvStatusBadge');
            if (data.is_deleted) {
                badgeEl.innerHTML = '<span class="geo-badge geo-badge-deleted"><i class="fas fa-trash-alt"></i> Deleted by Seller</span>';
                document.getElementById('qvPrice').classList.add('geo-strikethrough');
                document.getElementById('qvLotTitle').classList.add('geo-strikethrough');
            } else {
                badgeEl.innerHTML = '<span class="geo-badge ' + (data.is_urgent ? 'geo-badge-urgent' : 'geo-badge-active') + '">' + (data.is_urgent ? 'Ending Soon' : data.status) + '</span>';
                document.getElementById('qvPrice').classList.remove('geo-strikethrough');
                document.getElementById('qvLotTitle').classList.remove('geo-strikethrough');
            }

            // Images & Thumbnails
            const mainImg = document.getElementById('qvMainImage');
            const thumbsRow = document.getElementById('qvThumbsRow');
            thumbsRow.innerHTML = '';
            if (data.images && data.images.length > 0) {
                mainImg.src = data.images[0];
                data.images.forEach(function(src, idx) {
                    const thumb = document.createElement('img');
                    thumb.src = src;
                    thumb.style.width = '48px';
                    thumb.style.height = '48px';
                    thumb.style.objectFit = 'cover';
                    thumb.style.borderRadius = '6px';
                    thumb.style.cursor = 'pointer';
                    thumb.style.border = (idx === 0) ? '2px solid #047857' : '1px solid #cbd5e1';
                    thumb.onclick = function() {
                        mainImg.src = src;
                        thumbsRow.querySelectorAll('img').forEach(function(i) { i.style.border = '1px solid #cbd5e1'; });
                        thumb.style.border = '2px solid #047857';
                    };
                    thumbsRow.appendChild(thumb);
                });
            }

            // Action Button
            const actBtn = document.getElementById('qvActionPrimary');
            if (data.is_deleted) {
                actBtn.textContent = 'Lot Archived (Deleted)';
                actBtn.className = 'geo-btn geo-btn-disabled';
                actBtn.href = '#';
            } else {
                actBtn.innerHTML = '<i class="fas fa-bolt"></i> Place Bid in Arena';
                actBtn.className = 'geo-btn geo-btn-primary';
                actBtn.href = data.detail_link;
            }

            // Message Seller Button
            const msgBtn = document.getElementById('qvActionMessage');
            if (msgBtn) {
                if (data.seller_id && !data.is_deleted) {
                    const prefix = data.base_prefix || '';
                    msgBtn.href = prefix + 'messages.php?user_id=' + encodeURIComponent(data.seller_id) + '&item_id=' + encodeURIComponent(data.id || '');
                    msgBtn.style.display = 'inline-flex';
                } else {
                    msgBtn.style.display = 'none';
                }
            }

            document.getElementById('geoQuickViewBackdrop').classList.add('active');
        } catch(e) {
            console.error('Error opening quick view:', e);
        }
    }

    function closeUnifiedQuickView(e) {
        if (!e || e.target.id === 'geoQuickViewBackdrop' || e.target.closest('.geo-modal-close-btn') || e.target.tagName === 'BUTTON') {
            document.getElementById('geoQuickViewBackdrop').classList.remove('active');
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        // Image Carousel
        document.querySelectorAll('.geo-item-card').forEach(function(card) {
            const slidesContainer = card.querySelector('.geo-card-slides');
            const slides = card.querySelectorAll('.geo-card-slide');
            const prevBtn = card.querySelector('.geo-slide-prev');
            const nextBtn = card.querySelector('.geo-slide-next');
            const dots = card.querySelectorAll('.geo-dot');
            const total = slides.length;

            if (total <= 1 || !slidesContainer) return;

            let curIndex = 0;
            function moveTo(idx) {
                curIndex = (idx + total) % total;
                slidesContainer.style.transform = 'translateX(-' + (curIndex * (100 / total)) + '%)';
                dots.forEach(function(d, i) { d.classList.toggle('active', i === curIndex); });
            }

            if (prevBtn) {
                prevBtn.addEventListener('click', function(e) {
                    e.preventDefault(); e.stopPropagation(); moveTo(curIndex - 1);
                });
            }
            if (nextBtn) {
                nextBtn.addEventListener('click', function(e) {
                    e.preventDefault(); e.stopPropagation(); moveTo(curIndex + 1);
                });
            }
        });

        // Countdowns
        const timerEls = document.querySelectorAll('.geo-timer-val[data-end-ts]');
        function updateGeoTimers() {
            const now = Math.floor(Date.now() / 1000);
            timerEls.forEach(function(el) {
                const end = parseInt(el.getAttribute('data-end-ts'), 10);
                if (!end) return;
                const diff = end - now;
                if (diff <= 0) {
                    el.textContent = 'Auction Closed';
                    el.classList.add('urgent');
                    return;
                }
                const d = Math.floor(diff / 86400);
                const h = Math.floor((diff % 86400) / 3600);
                const m = Math.floor((diff % 3600) / 60);
                const s = diff % 60;
                let str = '';
                if (d > 0) str = d + 'd ' + h + 'h ' + m + 'm';
                else if (h > 0) str = h + 'h ' + m + 'm ' + s + 's';
                else {
                    str = m + 'm ' + s + 's';
                    el.classList.add('urgent');
                }
                el.textContent = str;
            });
        }
        updateGeoTimers();
        setInterval(updateGeoTimers, 1000);
    });
    </script>
    <?php
}
