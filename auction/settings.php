<?php
/**
 * Auction System Settings - Geo Auction System
 * File: auction/settings.php
 *
 * Dedicated Auction Configuration Area.
 * OOP PHP + PDO Architecture.
 *
 * Controls:
 * - Starting price rules
 * - Reserve price
 * - Minimum increment
 * - Buy Now price
 * - Auction start/end date
 * - Auto extension
 * - Bid rules
 * - Seller approval
 * - Auction approval
 * - Maximum active auctions per seller
 * - Auction image limits
 * - Allowed auction conditions
 * - Location settings
 */

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/settings.php';

// Enforce login
require_login('../login.php?redirect=auction/settings.php');

$pdo = Database::getPdo();
Settings::init($pdo);

$user_id   = (int)$_SESSION['user_id'];
$user_role = strtolower(trim((string)($_SESSION['role'] ?? $_SESSION['user_role'] ?? 'buyer')));
$is_admin  = ($user_role === 'admin');
$is_seller = ($user_role === 'seller');

$active_tab  = $_GET['tab'] ?? 'pricing';
$status_msg  = '';
$status_type = 'success';

// -----------------------------------------------------------------------------
// POST Request Handling (CSRF Protected & PDO Prepared Statements)
// -----------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!Settings::verifyCsrf($token)) {
        $status_msg  = 'Security validation token expired. Please reload and try again.';
        $status_type = 'danger';
    } else {
        $action = trim((string)($_POST['action'] ?? ''));
        // =========================================================================
        // ADMIN: Update Platform-Wide Auction Rules & Policies
        // =========================================================================
        if ($action === 'admin_save_auction_settings' && $is_admin) {
            $active_tab = $_POST['active_tab'] ?? 'pricing';
            $submitted  = $_POST['settings'] ?? [];

            if (is_array($submitted)) {
                // Checkbox keys that submit nothing when unchecked
                $booleanAuctionKeys = [
                    'auto_extend_enabled',
                    'buy_now_enabled',
                    'reserve_price_enabled',
                    'auto_bid_enabled',
                    'bid_confirmation_required',
                    'bid_cancellation_allowed',
                    'seller_approval_required',
                    'auction_approval_required',
                    'require_item_location'
                ];

                foreach ($booleanAuctionKeys as $bKey) {
                    if (!isset($submitted[$bKey])) {
                        $submitted[$bKey] = '0';
                    }
                }

                // Input validation
                if (isset($submitted['min_starting_price']) && (float)$submitted['min_starting_price'] < 0) {
                    $submitted['min_starting_price'] = '1';
                }
                if (isset($submitted['min_bid_increment']) && (float)$submitted['min_bid_increment'] <= 0) {
                    $submitted['min_bid_increment'] = '10';
                }
                if (isset($submitted['max_images_per_auction']) && (int)$submitted['max_images_per_auction'] < 1) {
                    $submitted['max_images_per_auction'] = '1';
                }

                if (Settings::saveMultiple($submitted, 'auction')) {
                    $status_msg  = 'Global Auction settings successfully updated and applied!';
                    $status_type = 'success';
                } else {
                    $status_msg  = 'Failed to update global auction settings. Please check database logs.';
                    $status_type = 'danger';
                }
            }
        }

        // =========================================================================
        // SELLER: Update Personal Listing Defaults & Presets
        // =========================================================================
        elseif ($action === 'seller_save_defaults') {
            $active_tab = 'seller_presets';
            $seller_presets = [
                'default_starting_bid'     => max(1.0, (float)($_POST['default_starting_bid'] ?? 100)),
                'default_reserve_markup'   => max(0.0, (float)($_POST['default_reserve_markup'] ?? 10)),
                'default_bid_increment'    => max(10.0, (float)($_POST['default_bid_increment'] ?? 100)),
                'default_enable_buynow'    => isset($_POST['default_enable_buynow']) ? 1 : 0,
                'default_buynow_markup'    => max(5.0, (float)($_POST['default_buynow_markup'] ?? 20)),
                'default_duration_hours'   => max(1, (int)($_POST['default_duration_hours'] ?? 72)),
                'default_auto_extend'      => isset($_POST['default_auto_extend']) ? 1 : 0,
                'default_condition_type'   => trim((string)($_POST['default_condition_type'] ?? 'used')),
                'default_lot_city'         => trim((string)($_POST['default_lot_city'] ?? 'Hyderabad')),
                'default_lot_area'         => trim((string)($_POST['default_lot_area'] ?? 'Auto Bhan Road')),
            ];

            $userSettings = Settings::getUserSettings($user_id);
            $userSettings['seller_presets'] = $seller_presets;
            if (Settings::saveUserSettings($user_id, $userSettings)) {
                $status_msg  = 'Your personal seller auction presets have been saved!';
                $status_type = 'success';
            } else {
                $status_msg  = 'Failed to save seller presets.';
                $status_type = 'danger';
            }
        }
    }
}

// -----------------------------------------------------------------------------
// Fetch Fresh Values
// -----------------------------------------------------------------------------
$auctionSettings = Settings::getGroup('auction');
$userPref = Settings::getUserSettings($user_id);
$sellerPresets = $userPref['seller_presets'] ?? [
    'default_starting_bid'   => 100,
    'default_reserve_markup' => 10,
    'default_bid_increment'  => 100,
    'default_enable_buynow'  => 1,
    'default_buynow_markup'  => 20,
    'default_duration_hours' => 72,
    'default_auto_extend'    => 1,
    'default_condition_type' => 'used',
    'default_lot_city'       => 'Hyderabad',
    'default_lot_area'       => 'Auto Bhan Road',
];

$currency = Settings::get('currency_symbol', 'Rs. ');

// Include Header
include_once __DIR__ . '/../includes/header.php';
?>

<!-- Custom CSS & Font Icons -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

<style>
:root {
    --auc-primary: #047857;
    --auc-primary-dark: #065f46;
    --auc-surface: #ffffff;
    --auc-border: #e2e8f0;
}
.auction-settings-wrapper {
    max-width: 1140px;
    margin: 0 auto;
    padding: 24px 16px 60px;
}
.card-auc-settings {
    background: #ffffff;
    border: 1px solid var(--auc-border);
    border-radius: 16px;
    box-shadow: 0 1px 4px rgba(0,0,0,0.03);
    overflow: hidden;
}
.auc-settings-nav .nav-link {
    color: #64748b;
    font-weight: 600;
    font-size: 13.5px;
    padding: 14px 20px;
    border: none;
    border-bottom: 3px solid transparent;
    border-radius: 0;
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    white-space: nowrap;
}
.auc-settings-nav .nav-link:hover {
    color: var(--auc-primary);
    background: rgba(4, 120, 87, 0.04);
}
.auc-settings-nav .nav-link.active {
    color: var(--auc-primary);
    background: #ffffff;
    border-bottom-color: var(--auc-primary);
    font-weight: 700;
}
.auc-section-title {
    font-size: 15px;
    font-weight: 700;
    color: #0f172a;
    display: flex;
    align-items: center;
    gap: 8px;
}
.auc-section-desc {
    font-size: 12.5px;
    color: #64748b;
    margin-top: 3px;
}
.btn-save-auc {
    background: var(--auc-primary);
    border-color: var(--auc-primary);
    color: #ffffff;
    font-weight: 700;
    font-size: 13.5px;
    border-radius: 10px;
    padding: 9px 24px;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.2s ease;
}
.btn-save-auc:hover {
    background: var(--auc-primary-dark);
    border-color: var(--auc-primary-dark);
    color: #ffffff;
    box-shadow: 0 4px 12px rgba(4, 120, 87, 0.2);
}
.setting-badge {
    font-size: 11px;
    padding: 3px 8px;
    border-radius: 6px;
    font-weight: 600;
}
.policy-card {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 16px;
    height: 100%;
}
</style>

<div class="auction-settings-wrapper">

    <!-- Header Breadcrumbs / Title -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <a href="../dashboard.php" class="text-decoration-none small text-success fw-bold">
                    <i class="fa-solid fa-house me-1"></i> Dashboard
                </a>
                <span class="text-muted small">&bull;</span>
                <span class="text-muted small">Auction Settings</span>
            </div>
            <h2 class="fw-bold mb-1" style="color: #0f172a;">Auction System Settings &amp; Rules</h2>
            <p class="text-muted mb-0 small">
                Configure starting prices, reserve rules, bid increments, auto-extension, seller approvals, and listing policies.
            </p>
        </div>

        <div class="d-flex align-items-center gap-2">
            <a href="../live_auction.php" class="btn btn-outline-secondary btn-sm px-3 py-2 fw-semibold rounded-3 d-inline-flex align-items-center gap-2">
                <i class="fa-solid fa-gavel"></i> Live Auctions
            </a>
            <?php if ($is_admin): ?>
                <a href="../admin/settings.php?tab=auction" class="btn btn-outline-success btn-sm px-3 py-2 fw-semibold rounded-3 d-inline-flex align-items-center gap-2">
                    <i class="fa-solid fa-sliders"></i> Master Admin Settings
                </a>
            <?php else: ?>
                <a href="../user/add_items.php" class="btn btn-success btn-sm px-3 py-2 fw-semibold rounded-3 d-inline-flex align-items-center gap-2 text-white">
                    <i class="fa-solid fa-plus-circle"></i> Create New Lot
                </a>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!empty($status_msg)): ?>
        <div class="alert alert-<?php echo $status_type; ?> alert-dismissible fade show d-flex align-items-center gap-2 shadow-sm rounded-3 mb-4" role="alert">
            <i class="fa-solid fa-<?php echo $status_type === 'success' ? 'circle-check text-success' : 'triangle-exclamation text-danger'; ?> fs-5"></i>
            <div class="small fw-semibold"><?php echo htmlspecialchars($status_msg); ?></div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Role Indicator Pill -->
    <div class="card p-3 mb-4 border-0 shadow-sm rounded-4" style="background: linear-gradient(135deg, #f0fdf4 0%, #ecfdf5 100%); border-left: 4px solid var(--auc-primary) !important;">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="d-flex align-items-center gap-3">
                <div class="bg-success text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                    <i class="fa-solid fa-gavel"></i>
                </div>
                <div>
                    <h6 class="fw-bold mb-0 text-success">
                        <?php echo $is_admin ? 'Master Auction Configuration (Administrator)' : 'Seller Listing Defaults & Presets'; ?>
                    </h6>
                    <small class="text-muted">
                        <?php echo $is_admin 
                            ? 'You have administrative authority to configure global bidding rules, reserve rules, duration limits, and seller approval policies.' 
                            : 'Set your listing defaults for new auctions. Global platform rules set by administration are shown for reference.'; ?>
                    </small>
                </div>
            </div>
            <div>
                <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2 rounded-pill fw-semibold">
                    <i class="fa-solid fa-user-tag me-1"></i> Role: <?php echo strtoupper($user_role); ?>
                </span>
            </div>
        </div>
    </div>

    <!-- Main Auction Settings Card -->
    <div class="card-auc-settings">
        <!-- Tabs Header -->
        <ul class="nav auc-settings-nav border-bottom px-3 pt-2 bg-light flex-nowrap overflow-x-auto scrollbar-none" id="aucSettingsTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link <?php echo $active_tab === 'pricing' ? 'active' : ''; ?>" id="tab-btn-pricing" data-bs-toggle="tab" data-bs-target="#tab-pricing" type="button" role="tab">
                    <i class="fa-solid fa-coins"></i> 1. Pricing &amp; Bids
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link <?php echo $active_tab === 'duration' ? 'active' : ''; ?>" id="tab-btn-duration" data-bs-toggle="tab" data-bs-target="#tab-duration" type="button" role="tab">
                    <i class="fa-solid fa-clock-rotate-left"></i> 2. Duration &amp; Anti-Sniping
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link <?php echo $active_tab === 'limits' ? 'active' : ''; ?>" id="tab-btn-limits" data-bs-toggle="tab" data-bs-target="#tab-limits" type="button" role="tab">
                    <i class="fa-solid fa-images"></i> 3. Images &amp; Limits
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link <?php echo $active_tab === 'policies' ? 'active' : ''; ?>" id="tab-btn-policies" data-bs-toggle="tab" data-bs-target="#tab-policies" type="button" role="tab">
                    <i class="fa-solid fa-shield-check"></i> 4. Approvals &amp; Rules
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link <?php echo $active_tab === 'location' ? 'active' : ''; ?>" id="tab-btn-location" data-bs-toggle="tab" data-bs-target="#tab-location" type="button" role="tab">
                    <i class="fa-solid fa-location-dot"></i> 5. Location Settings
                </button>
            </li>
            <?php if (!$is_admin): ?>
            <li class="nav-item" role="presentation">
                <button class="nav-link <?php echo $active_tab === 'seller_presets' ? 'active' : ''; ?>" id="tab-btn-seller-presets" data-bs-toggle="tab" data-bs-target="#tab-seller-presets" type="button" role="tab">
                    <i class="fa-solid fa-sliders"></i> My Seller Presets
                </button>
            </li>
            <?php endif; ?>
        </ul>

        <!-- Tabs Content Body -->
        <div class="tab-content p-4 p-md-5" id="aucSettingsTabsContent">

            <!-- =============================================================== -->
            <!-- TAB 1: PRICING & BID RULES -->
            <!-- =============================================================== -->
            <div class="tab-pane fade <?php echo $active_tab === 'pricing' ? 'show active' : ''; ?>" id="tab-pricing" role="tabpanel">
                <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
                    <div>
                        <div class="auc-section-title"><i class="fa-solid fa-coins text-success"></i> Starting Price, Reserve &amp; Bid Increments</div>
                        <div class="auc-section-desc">Manage opening bid thresholds, reserve price protection, minimum increment step, and Buy Now parameters.</div>
                    </div>
                </div>

                <?php if ($is_admin): ?>
                <form method="POST" action="settings.php?tab=pricing">
                    <input type="hidden" name="csrf_token" value="<?php echo Settings::generateCsrf(); ?>">
                    <input type="hidden" name="action" value="admin_save_auction_settings">
                    <input type="hidden" name="active_tab" value="pricing">

                    <div class="row g-4">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Minimum Allowed Starting Price (<?php echo $currency; ?>)</label>
                            <input type="number" step="1" min="1" class="form-control" name="settings[min_starting_price]" value="<?php echo htmlspecialchars((string)($auctionSettings['min_starting_price'] ?? '100')); ?>" required>
                            <div class="form-text">Lowest starting bid allowed when sellers create an auction.</div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Maximum Allowed Starting Price (<?php echo $currency; ?>)</label>
                            <input type="number" step="1000" min="1000" class="form-control" name="settings[max_starting_price]" value="<?php echo htmlspecialchars((string)($auctionSettings['max_starting_price'] ?? '10000000')); ?>" required>
                            <div class="form-text">Cap on initial listing opening price.</div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Minimum Bid Increment (<?php echo $currency; ?>)</label>
                            <input type="number" step="1" min="1" class="form-control" name="settings[min_bid_increment]" value="<?php echo htmlspecialchars((string)($auctionSettings['min_bid_increment'] ?? '100')); ?>" required>
                            <div class="form-text">Default required raise between consecutive competing bids.</div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Reserve Price Minimum Markup (%)</label>
                            <input type="number" step="1" min="0" max="1000" class="form-control" name="settings[reserve_price_min_pct]" value="<?php echo htmlspecialchars((string)($auctionSettings['reserve_price_min_pct'] ?? '10')); ?>" required>
                            <div class="form-text">Minimum % reserve must exceed starting price (0 = any amount).</div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Buy Now Minimum Markup (%)</label>
                            <input type="number" step="1" min="1" max="1000" class="form-control" name="settings[buy_now_min_markup_pct]" value="<?php echo htmlspecialchars((string)($auctionSettings['buy_now_min_markup_pct'] ?? '15')); ?>" required>
                            <div class="form-text">Minimum % Buy Now must exceed starting price to avoid underbidding.</div>
                        </div>

                        <div class="col-md-6">
                            <div class="policy-card">
                                <label class="form-label fw-semibold small mb-2 d-block">Feature Switches</label>
                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input" type="checkbox" role="switch" id="sw_reserve" name="settings[reserve_price_enabled]" value="1" <?php echo ($auctionSettings['reserve_price_enabled'] ?? '1') == '1' ? 'checked' : ''; ?>>
                                    <label class="form-check-label small fw-semibold" for="sw_reserve">Enable Reserve Price System</label>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch" id="sw_buynow" name="settings[buy_now_enabled]" value="1" <?php echo ($auctionSettings['buy_now_enabled'] ?? '1') == '1' ? 'checked' : ''; ?>>
                                    <label class="form-check-label small fw-semibold" for="sw_buynow">Enable Buy It Now Instant Purchase</label>
                                </div>
                            </div>
                        </div>

                        <div class="col-12 mt-4 pt-3 border-top d-flex justify-content-end">
                            <button type="submit" class="btn-save-auc">
                                <i class="fa-solid fa-floppy-disk"></i> Save Pricing Rules
                            </button>
                        </div>
                    </div>
                </form>
                <?php else: ?>
                <!-- Seller View of Platform Pricing Policies -->
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="policy-card">
                            <div class="text-muted small fw-semibold mb-1">Min. Starting Price</div>
                            <div class="fs-5 fw-bold text-dark"><?php echo $currency . number_format((float)($auctionSettings['min_starting_price'] ?? 100)); ?></div>
                            <small class="text-muted">Lowest opening price allowed</small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="policy-card">
                            <div class="text-muted small fw-semibold mb-1">Default Bid Increment</div>
                            <div class="fs-5 fw-bold text-dark"><?php echo $currency . number_format((float)($auctionSettings['min_bid_increment'] ?? 100)); ?></div>
                            <small class="text-muted">Minimum step between bids</small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="policy-card">
                            <div class="text-muted small fw-semibold mb-1">Buy Now Feature</div>
                            <div class="fs-5 fw-bold <?php echo ($auctionSettings['buy_now_enabled'] ?? '1') == '1' ? 'text-success' : 'text-danger'; ?>">
                                <?php echo ($auctionSettings['buy_now_enabled'] ?? '1') == '1' ? 'Allowed' : 'Disabled'; ?>
                            </div>
                            <small class="text-muted">Instant purchase on lots</small>
                        </div>
                    </div>
                </div>
                <div class="mt-4 p-3 bg-light rounded-3 text-muted small">
                    <i class="fa-solid fa-info-circle me-1 text-primary"></i> As a seller, you can configure your personalized default starting bids and markup preferences in the <strong>My Seller Presets</strong> tab.
                </div>
                <?php endif; ?>
            </div>

            <!-- =============================================================== -->
            <!-- TAB 2: DURATION & ANTI-SNIPING -->
            <!-- =============================================================== -->
            <div class="tab-pane fade <?php echo $active_tab === 'duration' ? 'show active' : ''; ?>" id="tab-duration" role="tabpanel">
                <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
                    <div>
                        <div class="auc-section-title"><i class="fa-solid fa-clock-rotate-left text-success"></i> Auction Runtime &amp; Anti-Sniping Protection</div>
                        <div class="auc-section-desc">Manage start/end date limits, default listing duration, and automatic time extension on last-minute bids.</div>
                    </div>
                </div>

                <?php if ($is_admin): ?>
                <form method="POST" action="settings.php?tab=duration">
                    <input type="hidden" name="csrf_token" value="<?php echo Settings::generateCsrf(); ?>">
                    <input type="hidden" name="action" value="admin_save_auction_settings">
                    <input type="hidden" name="active_tab" value="duration">

                    <div class="row g-4">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Default Auction Duration (Hours)</label>
                            <input type="number" step="1" min="1" max="720" class="form-control" name="settings[default_auction_duration_hours]" value="<?php echo htmlspecialchars((string)($auctionSettings['default_auction_duration_hours'] ?? '72')); ?>" required>
                            <div class="form-text">72 hours = 3 days. Applied when creating listings without custom end time.</div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Maximum Allowed Duration (Days)</label>
                            <input type="number" step="1" min="1" max="90" class="form-control" name="settings[max_auction_duration_days]" value="<?php echo htmlspecialchars((string)($auctionSettings['max_auction_duration_days'] ?? '30')); ?>" required>
                            <div class="form-text">Maximum days a seller can keep a lot active.</div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Anti-Sniping Trigger Window (Seconds)</label>
                            <input type="number" step="10" min="30" max="600" class="form-control" name="settings[auto_extend_trigger_seconds]" value="<?php echo htmlspecialchars((string)($auctionSettings['auto_extend_trigger_seconds'] ?? '180')); ?>" required>
                            <div class="form-text">180s = If bid placed within the final 3 minutes, clock automatically extends.</div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Auto-Extension Duration (Minutes)</label>
                            <input type="number" step="1" min="1" max="30" class="form-control" name="settings[auto_extend_minutes]" value="<?php echo htmlspecialchars((string)($auctionSettings['auto_extend_minutes'] ?? '3')); ?>" required>
                            <div class="form-text">Minutes added to end time each time an anti-sniping trigger fires.</div>
                        </div>

                        <div class="col-12">
                            <div class="form-check form-switch p-3 bg-light rounded-3">
                                <input class="form-check-input ms-0 me-2" type="checkbox" role="switch" id="sw_autoext" name="settings[auto_extend_enabled]" value="1" <?php echo ($auctionSettings['auto_extend_enabled'] ?? '1') == '1' ? 'checked' : ''; ?>>
                                <label class="form-check-label small fw-semibold" for="sw_autoext">
                                    Enable Auto-Extend / Anti-Sniping Protection Platform-Wide
                                </label>
                                <div class="form-text mt-1 text-muted">Protects sellers and fair bidders against automated last-second bid bots.</div>
                            </div>
                        </div>

                        <div class="col-12 mt-4 pt-3 border-top d-flex justify-content-end">
                            <button type="submit" class="btn-save-auc">
                                <i class="fa-solid fa-floppy-disk"></i> Save Duration Settings
                            </button>
                        </div>
                    </div>
                </form>
                <?php else: ?>
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="policy-card">
                            <div class="text-muted small fw-semibold mb-1">Default Duration</div>
                            <div class="fs-5 fw-bold text-dark"><?php echo (int)($auctionSettings['default_auction_duration_hours'] ?? 72); ?> Hours</div>
                            <small class="text-muted">Standard listing window</small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="policy-card">
                            <div class="text-muted small fw-semibold mb-1">Max Duration Limit</div>
                            <div class="fs-5 fw-bold text-dark"><?php echo (int)($auctionSettings['max_auction_duration_days'] ?? 30); ?> Days</div>
                            <small class="text-muted">Longest allowed runtime</small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="policy-card">
                            <div class="text-muted small fw-semibold mb-1">Anti-Sniping Extension</div>
                            <div class="fs-5 fw-bold text-success">+<?php echo (int)($auctionSettings['auto_extend_minutes'] ?? 3); ?> Minutes</div>
                            <small class="text-muted">Triggered in closing <?php echo round((int)($auctionSettings['auto_extend_trigger_seconds'] ?? 180)/60); ?>m</small>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <div class="tab-pane fade <?php echo $active_tab === 'limits' ? 'show active' : ''; ?>" id="tab-limits" role="tabpanel">
                <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
                    <div>
                        <div class="auc-section-title"><i class="fa-solid fa-images text-success"></i> Upload Limits &amp; Item Conditions</div>
                        <div class="auc-section-desc">Manage upload quotas per lot, allowed item condition tags, and active listing constraints per seller.</div>
                    </div>
                </div>

                <?php if ($is_admin): ?>
                <form method="POST" action="settings.php?tab=limits">
                    <input type="hidden" name="csrf_token" value="<?php echo Settings::generateCsrf(); ?>">
                    <input type="hidden" name="action" value="admin_save_auction_settings">
                    <input type="hidden" name="active_tab" value="limits">

                    <div class="row g-4">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Maximum Images per Auction</label>
                            <input type="number" step="1" min="1" max="30" class="form-control" name="settings[max_images_per_auction]" value="<?php echo htmlspecialchars((string)($auctionSettings['max_images_per_auction'] ?? '10')); ?>" required>
                            <div class="form-text">Enforced in the upload form when adding or editing items.</div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Maximum Active Auctions per Seller</label>
                            <input type="number" step="1" min="1" max="500" class="form-control" name="settings[max_active_auctions_per_seller]" value="<?php echo htmlspecialchars((string)($auctionSettings['max_active_auctions_per_seller'] ?? '25')); ?>" required>
                            <div class="form-text">Prevents spam listings and maintains marketplace catalog quality.</div>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold small">Allowed Auction Conditions</label>
                            <input type="text" class="form-control font-monospace" name="settings[allowed_auction_conditions]" value="<?php echo htmlspecialchars((string)($auctionSettings['allowed_auction_conditions'] ?? 'new,used,refurbished')); ?>" required>
                            <div class="form-text">Comma-separated tags (e.g. <code>new,used,refurbished,open_box,for_parts</code>).</div>
                        </div>

                        <div class="col-12 mt-4 pt-3 border-top d-flex justify-content-end">
                            <button type="submit" class="btn-save-auc">
                                <i class="fa-solid fa-floppy-disk"></i> Save Upload &amp; Condition Limits
                            </button>
                        </div>
                    </div>
                </form>
                <?php else: ?>
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="policy-card">
                            <div class="text-muted small fw-semibold mb-1">Max Upload Images per Listing</div>
                            <div class="fs-5 fw-bold text-dark"><?php echo (int)($auctionSettings['max_images_per_auction'] ?? 10); ?> Photos</div>
                            <small class="text-muted">JPG, PNG, WEBP allowed (Max 5MB each)</small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="policy-card">
                            <div class="text-muted small fw-semibold mb-1">Max Concurrently Active Listings</div>
                            <div class="fs-5 fw-bold text-dark"><?php echo (int)($auctionSettings['max_active_auctions_per_seller'] ?? 25); ?> Active Lots</div>
                            <small class="text-muted">Per seller account limit</small>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- =============================================================== -->
            <!-- TAB 4: APPROVALS & POLICIES -->
            <!-- =============================================================== -->
            <div class="tab-pane fade <?php echo $active_tab === 'policies' ? 'show active' : ''; ?>" id="tab-policies" role="tabpanel">
                <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
                    <div>
                        <div class="auc-section-title"><i class="fa-solid fa-shield-check text-success"></i> Seller Approvals, Lot Moderation &amp; Bidding Rules</div>
                        <div class="auc-section-desc">Manage manual administrative moderation, seller approvals, and buyer bidding safeguards.</div>
                    </div>
                </div>

                <?php if ($is_admin): ?>
                <form method="POST" action="settings.php?tab=policies">
                    <input type="hidden" name="csrf_token" value="<?php echo Settings::generateCsrf(); ?>">
                    <input type="hidden" name="action" value="admin_save_auction_settings">
                    <input type="hidden" name="active_tab" value="policies">

                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="policy-card">
                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input" type="checkbox" role="switch" id="sw_auc_appr" name="settings[auction_approval_required]" value="1" <?php echo ($auctionSettings['auction_approval_required'] ?? '0') == '1' ? 'checked' : ''; ?>>
                                    <label class="form-check-label small fw-semibold" for="sw_auc_appr">Auction Approval Required</label>
                                </div>
                                <small class="text-muted d-block">When enabled, newly created lots remain in "pending" status until an administrator approves them.</small>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="policy-card">
                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input" type="checkbox" role="switch" id="sw_seller_appr" name="settings[seller_approval_required]" value="1" <?php echo ($auctionSettings['seller_approval_required'] ?? '0') == '1' ? 'checked' : ''; ?>>
                                    <label class="form-check-label small fw-semibold" for="sw_seller_appr">Seller Approval Required</label>
                                </div>
                                <small class="text-muted d-block">Require administrative approval before a seller can submit new items to the marketplace.</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="policy-card">
                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input" type="checkbox" role="switch" id="sw_autobid" name="settings[auto_bid_enabled]" value="1" <?php echo ($auctionSettings['auto_bid_enabled'] ?? '1') == '1' ? 'checked' : ''; ?>>
                                    <label class="form-check-label small fw-semibold" for="sw_autobid">Enable Auto-Bid Proxy Feature</label>
                                </div>
                                <small class="text-muted d-block">Allows buyers to configure a maximum proxy bid that bids automatically on their behalf.</small>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="policy-card">
                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input" type="checkbox" role="switch" id="sw_bid_conf" name="settings[bid_confirmation_required]" value="1" <?php echo ($auctionSettings['bid_confirmation_required'] ?? '1') == '1' ? 'checked' : ''; ?>>
                                    <label class="form-check-label small fw-semibold" for="sw_bid_conf">Require Bid Confirmation Dialog</label>
                                </div>
                                <small class="text-muted d-block">Shows a modal confirmation before committing a bid to prevent accidental clicks.</small>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="policy-card">
                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input" type="checkbox" role="switch" id="sw_bid_canc" name="settings[bid_cancellation_allowed]" value="1" <?php echo ($auctionSettings['bid_cancellation_allowed'] ?? '0') == '1' ? 'checked' : ''; ?>>
                                    <label class="form-check-label small fw-semibold" for="sw_bid_canc">Allow Bid Retraction / Cancellation</label>
                                </div>
                                <small class="text-muted d-block">Whether buyers are permitted to revoke bids prior to lot closing.</small>
                            </div>
                        </div>

                        <div class="col-12 mt-4 pt-3 border-top d-flex justify-content-end">
                            <button type="submit" class="btn-save-auc">
                                <i class="fa-solid fa-floppy-disk"></i> Save Policies
                            </button>
                        </div>
                    </div>
                </form>
                <?php else: ?>
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="policy-card">
                            <div class="text-muted small fw-semibold mb-1">Auction Moderation</div>
                            <div class="fs-6 fw-bold <?php echo ($auctionSettings['auction_approval_required'] ?? '0') == '1' ? 'text-warning' : 'text-success'; ?>">
                                <?php echo ($auctionSettings['auction_approval_required'] ?? '0') == '1' ? 'Admin Approval Required' : 'Instant Publication'; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="policy-card">
                            <div class="text-muted small fw-semibold mb-1">Proxy Bidding</div>
                            <div class="fs-6 fw-bold <?php echo ($auctionSettings['auto_bid_enabled'] ?? '1') == '1' ? 'text-success' : 'text-danger'; ?>">
                                <?php echo ($auctionSettings['auto_bid_enabled'] ?? '1') == '1' ? 'Enabled' : 'Disabled'; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="policy-card">
                            <div class="text-muted small fw-semibold mb-1">Bid Retraction</div>
                            <div class="fs-6 fw-bold <?php echo ($auctionSettings['bid_cancellation_allowed'] ?? '0') == '1' ? 'text-primary' : 'text-danger'; ?>">
                                <?php echo ($auctionSettings['bid_cancellation_allowed'] ?? '0') == '1' ? 'Permitted' : 'Not Permitted (Final)'; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- =============================================================== -->
            <!-- TAB 5: LOCATION SETTINGS -->
            <!-- =============================================================== -->
            <div class="tab-pane fade <?php echo $active_tab === 'location' ? 'show active' : ''; ?>" id="tab-location" role="tabpanel">
                <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
                    <div>
                        <div class="auc-section-title"><i class="fa-solid fa-location-dot text-success"></i> Geographic &amp; Item Location Settings</div>
                        <div class="auc-section-desc">Manage regional requirements, default origin city, and allowed provinces for delivery and inspection.</div>
                    </div>
                </div>

                <?php if ($is_admin): ?>
                <form method="POST" action="settings.php?tab=location">
                    <input type="hidden" name="csrf_token" value="<?php echo Settings::generateCsrf(); ?>">
                    <input type="hidden" name="action" value="admin_save_auction_settings">
                    <input type="hidden" name="active_tab" value="location">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Default Auction City</label>
                            <input type="text" class="form-control" name="settings[default_auction_city]" value="<?php echo htmlspecialchars((string)($auctionSettings['default_auction_city'] ?? 'Hyderabad')); ?>" required>
                            <div class="form-text">Default city prefilled on new listing forms.</div>
                        </div>

                        <div class="col-md-6">
                            <div class="policy-card">
                                <div class="form-check form-switch mb-1">
                                    <input class="form-check-input" type="checkbox" role="switch" id="sw_loc_req" name="settings[require_item_location]" value="1" <?php echo ($auctionSettings['require_item_location'] ?? '1') == '1' ? 'checked' : ''; ?>>
                                    <label class="form-check-label small fw-semibold" for="sw_loc_req">Require Item Location</label>
                                </div>
                                <small class="text-muted d-block">Requires sellers to specify city and area before creating an auction.</small>
                            </div>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold small">Allowed Provinces / Regions</label>
                            <textarea class="form-control" rows="3" name="settings[allowed_provinces]" required><?php echo htmlspecialchars((string)($auctionSettings['allowed_provinces'] ?? 'Sindh,Punjab,Khyber Pakhtunkhwa,Balochistan,Islamabad Capital Territory,Gilgit-Baltistan,Azad Kashmir')); ?></textarea>
                            <div class="form-text">Comma-separated regional territory zones supported across Pakistan.</div>
                        </div>

                        <div class="col-12 mt-4 pt-3 border-top d-flex justify-content-end">
                            <button type="submit" class="btn-save-auc">
                                <i class="fa-solid fa-floppy-disk"></i> Save Location Settings
                            </button>
                        </div>
                    </div>
                </form>
                <?php else: ?>
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="policy-card">
                            <div class="text-muted small fw-semibold mb-1">Default Market City</div>
                            <div class="fs-5 fw-bold text-dark"><?php echo htmlspecialchars((string)($auctionSettings['default_auction_city'] ?? 'Hyderabad')); ?></div>
                            <small class="text-muted">Primary regional center</small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="policy-card">
                            <div class="text-muted small fw-semibold mb-1">Location Enforcement</div>
                            <div class="fs-5 fw-bold <?php echo ($auctionSettings['require_item_location'] ?? '1') == '1' ? 'text-success' : 'text-muted'; ?>">
                                <?php echo ($auctionSettings['require_item_location'] ?? '1') == '1' ? 'Strictly Required' : 'Optional'; ?>
                            </div>
                            <small class="text-muted">City and area details</small>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <!-- =============================================================== -->
            <!-- TAB: SELLER PRESETS (FOR SELLERS ONLY) -->
            <!-- =============================================================== -->
            <?php if (!$is_admin): ?>
            <div class="tab-pane fade <?php echo $active_tab === 'seller_presets' ? 'show active' : ''; ?>" id="tab-seller-presets" role="tabpanel">
                <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
                    <div>
                        <div class="auc-section-title"><i class="fa-solid fa-sliders text-success"></i> My Personal Listing Presets</div>
                        <div class="auc-section-desc">Save default pricing, location, and duration preferences to automatically prefill your new listing forms.</div>
                    </div>
                </div>

                <form method="POST" action="settings.php?tab=seller_presets">
                    <input type="hidden" name="csrf_token" value="<?php echo Settings::generateCsrf(); ?>">
                    <input type="hidden" name="action" value="seller_save_defaults">

                    <div class="row g-4">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">Default Starting Bid (<?php echo $currency; ?>)</label>
                            <input type="number" step="10" min="1" class="form-control" name="default_starting_bid" value="<?php echo htmlspecialchars((string)($sellerPresets['default_starting_bid'] ?? 100)); ?>" required>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">Default Bid Increment (<?php echo $currency; ?>)</label>
                            <input type="number" step="10" min="10" class="form-control" name="default_bid_increment" value="<?php echo htmlspecialchars((string)($sellerPresets['default_bid_increment'] ?? 100)); ?>" required>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">Default Duration (Hours)</label>
                            <select class="form-select" name="default_duration_hours">
                                <option value="24" <?php echo ($sellerPresets['default_duration_hours'] ?? 72) == 24 ? 'selected' : ''; ?>>24 Hours (1 Day)</option>
                                <option value="48" <?php echo ($sellerPresets['default_duration_hours'] ?? 72) == 48 ? 'selected' : ''; ?>>48 Hours (2 Days)</option>
                                <option value="72" <?php echo ($sellerPresets['default_duration_hours'] ?? 72) == 72 ? 'selected' : ''; ?>>72 Hours (3 Days)</option>
                                <option value="120" <?php echo ($sellerPresets['default_duration_hours'] ?? 72) == 120 ? 'selected' : ''; ?>>120 Hours (5 Days)</option>
                                <option value="168" <?php echo ($sellerPresets['default_duration_hours'] ?? 72) == 168 ? 'selected' : ''; ?>>168 Hours (7 Days)</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Default Item City</label>
                            <input type="text" class="form-control" name="default_lot_city" value="<?php echo htmlspecialchars((string)($sellerPresets['default_lot_city'] ?? 'Hyderabad')); ?>" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Default Item Area / Neighborhood</label>
                            <input type="text" class="form-control" name="default_lot_area" value="<?php echo htmlspecialchars((string)($sellerPresets['default_lot_area'] ?? 'Auto Bhan Road')); ?>" required>
                        </div>

                        <div class="col-md-6">
                            <div class="policy-card">
                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input" type="checkbox" role="switch" id="pref_buynow" name="default_enable_buynow" value="1" <?php echo ($sellerPresets['default_enable_buynow'] ?? 1) ? 'checked' : ''; ?>>
                                    <label class="form-check-label small fw-semibold" for="pref_buynow">Enable Buy Now by Default</label>
                                </div>
                                <label class="form-label fw-semibold small mt-2">Buy Now Default Markup (%)</label>
                                <input type="number" step="5" min="5" max="200" class="form-control form-control-sm" name="default_buynow_markup" value="<?php echo htmlspecialchars((string)($sellerPresets['default_buynow_markup'] ?? 20)); ?>">
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="policy-card">
                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input" type="checkbox" role="switch" id="pref_autoext" name="default_auto_extend" value="1" <?php echo ($sellerPresets['default_auto_extend'] ?? 1) ? 'checked' : ''; ?>>
                                    <label class="form-check-label small fw-semibold" for="pref_autoext">Auto-Extend Anti-Sniping</label>
                                </div>
                                <label class="form-label fw-semibold small mt-2">Reserve Price Default Markup (%)</label>
                                <input type="number" step="5" min="0" max="200" class="form-control form-control-sm" name="default_reserve_markup" value="<?php echo htmlspecialchars((string)($sellerPresets['default_reserve_markup'] ?? 10)); ?>">
                            </div>
                        </div>

                        <div class="col-12 mt-4 pt-3 border-top d-flex justify-content-end">
                            <button type="submit" class="btn-save-auc">
                                <i class="fa-solid fa-floppy-disk"></i> Save Personal Listing Presets
                            </button>
                        </div>
                    </div>
                </form>
            </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Sync active tab to URL
    document.querySelectorAll('#aucSettingsTabs button[data-bs-toggle="tab"]').forEach(function(btn) {
        btn.addEventListener('shown.bs.tab', function(e) {
            var tabName = e.target.getAttribute('data-bs-target').replace('#tab-', '');
            if (history.replaceState) {
                var newUrl = window.location.protocol + "//" + window.location.host + window.location.pathname + '?tab=' + tabName;
                window.history.replaceState({ path: newUrl }, '', newUrl);
            }
        });
    });
</script>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>
