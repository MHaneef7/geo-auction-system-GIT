<?php
/**
 * Won Auctions & Order Management Dashboard - Geo Auction System
 *
 * Senior Full-Stack PHP & E-Commerce Architecture
 * Tech Stack: PHP 8.x, MySQL 8.x (InnoDB), Tailwind CSS + Vanilla JS
 *
 * Lifecycle Statuses:
 * - PAYMENT_PENDING: Winning bid placed, payment settlement required.
 * - PAID / PROCESSING: Payment confirmed (via wallet lock auto-debit or direct checkout); seller preparing lot.
 * - SHIPPED: Item dispatched with logistics courier & tracking ID.
 * - DELIVERED / COMPLETED: Buyer received item; order confirmed.
 * - DISPUTED: Active ticket open regarding damaged goods or non-delivery.
 */

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/database.php';

// Ensure schema existence for orders, wallets, and wallet_locks
try {
    $pdo = Database::getPdo();
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `orders` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `order_number` VARCHAR(50) NOT NULL UNIQUE,
            `auction_id` INT NOT NULL,
            `buyer_id` INT NOT NULL,
            `seller_id` INT NOT NULL,
            `hammer_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            `platform_fee` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            `shipping_fee` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            `total_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            `payment_status` VARCHAR(30) NOT NULL DEFAULT 'PAYMENT_PENDING',
            `shipping_status` VARCHAR(30) NOT NULL DEFAULT 'PROCESSING',
            `delivery_confirmed_at` DATETIME NULL DEFAULT NULL,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX (`auction_id`),
            INDEX (`buyer_id`),
            INDEX (`seller_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `wallets` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT(11) NOT NULL,
            `currency` VARCHAR(3) NOT NULL DEFAULT 'PKR',
            `available_balance` DECIMAL(18, 4) NOT NULL DEFAULT 0.0000,
            `locked_balance` DECIMAL(18, 4) NOT NULL DEFAULT 0.0000,
            `status` VARCHAR(20) NOT NULL DEFAULT 'ACTIVE',
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX (`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `wallet_locks` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `lock_id` VARCHAR(64) NOT NULL,
            `wallet_id` BIGINT UNSIGNED NOT NULL,
            `auction_id` INT(11) NOT NULL,
            `amount` DECIMAL(18, 4) NOT NULL,
            `status` VARCHAR(20) NOT NULL DEFAULT 'ACTIVE',
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX (`wallet_id`),
            INDEX (`auction_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
} catch (Throwable $e) {
    error_log("Schema ensure for won_auctions: " . $e->getMessage());
}

// 1. User Authentication & Authorization Check
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php?redirect=user/won_auctions.php');
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$user_name = $_SESSION['user_name'] ?? ($_SESSION['name'] ?? 'Buyer');

// CSRF Protection Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

$success_msg = '';
$error_msg   = '';

// -------------------------------------------------------------
// POST Request Handling (State-Changing Actions)
// -------------------------------------------------------------
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $submitted_csrf = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $submitted_csrf)) {
        $error_msg = 'Security check failed: Invalid CSRF token. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';
        $order_id = (int)($_POST['order_id'] ?? 0);
        $item_id  = (int)($_POST['item_id'] ?? 0);

        // Action A: Confirm Delivery Received
        if ($action === 'confirm_delivery' && $order_id > 0) {
            $stmt = mysqli_prepare($conn, "
                UPDATE orders 
                SET shipping_status = 'COMPLETED', delivery_confirmed_at = NOW(), updated_at = NOW() 
                WHERE id = ? AND buyer_id = ?
            ");
            mysqli_stmt_bind_param($stmt, "ii", $order_id, $user_id);
            if (mysqli_stmt_execute($stmt) && mysqli_stmt_affected_rows($stmt) > 0) {
                $success_msg = 'Delivery confirmed! Order has been marked as COMPLETED. Thank you for your feedback.';
            } else {
                $error_msg = 'Unable to confirm delivery for this order.';
            }
            mysqli_stmt_close($stmt);
        }

        // Action B: Open Dispute
        if ($action === 'open_dispute' && $order_id > 0) {
            $reason = trim($_POST['dispute_reason'] ?? 'Goods not received or damaged');
            $stmt = mysqli_prepare($conn, "
                UPDATE orders 
                SET shipping_status = 'DISPUTED', updated_at = NOW() 
                WHERE id = ? AND buyer_id = ?
            ");
            mysqli_stmt_bind_param($stmt, "ii", $order_id, $user_id);
            if (mysqli_stmt_execute($stmt) && mysqli_stmt_affected_rows($stmt) > 0) {
                // Also create an audit dispute report if item_reports table exists
                $r_stmt = mysqli_prepare($conn, "
                    INSERT INTO item_reports (item_id, user_id, reason, description) 
                    VALUES (?, ?, 'ORDER_DISPUTE', ?)
                ");
                if ($r_stmt) {
                    mysqli_stmt_bind_param($r_stmt, "iis", $item_id, $user_id, $reason);
                    mysqli_stmt_execute($r_stmt);
                    mysqli_stmt_close($r_stmt);
                }
                $success_msg = 'Dispute ticket registered successfully. Platform arbitration team has been alerted.';
            } else {
                $error_msg = 'Failed to file dispute.';
            }
            mysqli_stmt_close($stmt);
        }

        // Action C: Settle Payment via Wallet Balance
        if ($action === 'pay_via_wallet' && $item_id > 0) {
            $pay_amount = (float)($_POST['amount'] ?? 0);
            
            mysqli_begin_transaction($conn);
            try {
                // Fetch buyer wallet balance
                $w_stmt = mysqli_prepare($conn, "SELECT balance FROM users WHERE id = ? FOR UPDATE");
                mysqli_stmt_bind_param($w_stmt, "i", $user_id);
                mysqli_stmt_execute($w_stmt);
                $curr_bal = (float)(mysqli_fetch_assoc(mysqli_stmt_get_result($w_stmt))['balance'] ?? 0);
                mysqli_stmt_close($w_stmt);

                if ($curr_bal < $pay_amount) {
                    throw new RuntimeException('Insufficient wallet balance. Please top up your wallet to settle payment.');
                }

                $new_bal = $curr_bal - $pay_amount;
                $up_u = mysqli_prepare($conn, "UPDATE users SET balance = ? WHERE id = ?");
                mysqli_stmt_bind_param($up_u, "di", $new_bal, $user_id);
                mysqli_stmt_execute($up_u);
                mysqli_stmt_close($up_u);

                // Update or Insert Order
                if ($order_id > 0) {
                    $up_o = mysqli_prepare($conn, "UPDATE orders SET payment_status = 'PAID', updated_at = NOW() WHERE id = ? AND buyer_id = ?");
                    mysqli_stmt_bind_param($up_o, "ii", $order_id, $user_id);
                    mysqli_stmt_execute($up_o);
                    mysqli_stmt_close($up_o);
                } else {
                    $ord_num = 'ORD-' . strtoupper(substr(uniqid(), -8));
                    $seller_id = (int)($_POST['seller_id'] ?? 1);
                    $ins_o = mysqli_prepare($conn, "
                        INSERT INTO orders (order_number, auction_id, buyer_id, seller_id, hammer_price, platform_fee, shipping_fee, total_amount, payment_status, shipping_status)
                        VALUES (?, ?, ?, ?, ?, 0.00, 0.00, ?, 'PAID', 'PROCESSING')
                    ");
                    mysqli_stmt_bind_param($ins_o, "siiidd", $ord_num, $item_id, $user_id, $seller_id, $pay_amount, $pay_amount);
                    mysqli_stmt_execute($ins_o);
                    mysqli_stmt_close($ins_o);
                }

                // Record transaction
                $tx_desc = "Payment for won auction lot #" . $item_id;
                $tx_stmt = mysqli_prepare($conn, "
                    INSERT INTO transactions (user_id, type, amount, balance_before, balance_after, item_id, description, status, created_at)
                    VALUES (?, 'winning_payment', ?, ?, ?, ?, ?, 'completed', NOW())
                ");
                $neg_amount = -$pay_amount;
                mysqli_stmt_bind_param($tx_stmt, "idddis", $user_id, $neg_amount, $curr_bal, $new_bal, $item_id, $tx_desc);
                mysqli_stmt_execute($tx_stmt);
                mysqli_stmt_close($tx_stmt);

                mysqli_commit($conn);
                $success_msg = 'Payment of Rs. ' . number_format($pay_amount, 2) . ' confirmed successfully from wallet!';
            } catch (Exception $e) {
                mysqli_rollback($conn);
                $error_msg = $e->getMessage();
            }
        }
    }
}

// -------------------------------------------------------------
// Filters & Search Parameters
// -------------------------------------------------------------
$search_query   = trim($_GET['q'] ?? '');
$status_filter  = strtoupper(trim($_GET['status'] ?? 'ALL'));
$current_page   = max(1, (int)($_GET['page'] ?? 1));
$items_per_page = 10;
$offset         = ($current_page - 1) * $items_per_page;

// -------------------------------------------------------------
// Core Data Fetching: Won Items Aggregation
// -------------------------------------------------------------
$sql_base = "
    FROM items i
    LEFT JOIN orders o ON o.auction_id = i.id AND o.buyer_id = ?
    LEFT JOIN bids b ON (i.winning_bid_id = b.id OR (b.item_id = i.id AND b.is_winning = 1))
    LEFT JOIN users u ON i.seller_id = u.id
    LEFT JOIN categories c ON i.category_id = c.id
    LEFT JOIN payments p ON p.item_id = i.id AND (p.user_id = ? OR p.buyer_id = ?)
    LEFT JOIN wallet_locks wl ON wl.auction_id = i.id AND wl.wallet_id = (SELECT id FROM wallets WHERE user_id = ? LIMIT 1)
    WHERE (i.winner_id = ? OR o.buyer_id = ?)
";

// Dynamic Filtering
$filter_clauses = "";
$filter_params  = [];
$filter_types   = "";

if (!empty($search_query)) {
    $filter_clauses .= " AND (i.title LIKE ? OR i.auction_code LIKE ? OR o.order_number LIKE ?)";
    $st = "%$search_query%";
    $filter_params[] = $st; $filter_params[] = $st; $filter_params[] = $st;
    $filter_types .= "sss";
}

if ($status_filter === 'AWAITING_PAYMENT') {
    $filter_clauses .= " AND (o.payment_status = 'PAYMENT_PENDING' OR (o.payment_status IS NULL AND (p.payment_status IS NULL OR p.payment_status != 'completed')))";
} elseif ($status_filter === 'IN_TRANSIT') {
    $filter_clauses .= " AND o.shipping_status = 'SHIPPED'";
} elseif ($status_filter === 'COMPLETED') {
    $filter_clauses .= " AND (o.shipping_status = 'COMPLETED' OR o.shipping_status = 'DELIVERED')";
} elseif ($status_filter === 'DISPUTED') {
    $filter_clauses .= " AND o.shipping_status = 'DISPUTED'";
}

// 1. Fetch Metrics Counts
$metrics_query = "
    SELECT 
        COUNT(DISTINCT i.id) AS total_won,
        COALESCE(SUM(COALESCE(b.bid_amount, i.current_price)), 0) AS total_spent,
        COUNT(DISTINCT CASE WHEN o.shipping_status = 'SHIPPED' THEN i.id END) AS in_transit_count,
        COUNT(DISTINCT CASE WHEN (o.payment_status = 'PAYMENT_PENDING' OR (o.payment_status IS NULL AND (p.payment_status IS NULL OR p.payment_status != 'completed'))) THEN i.id END) AS awaiting_payment_count
    $sql_base
";
$m_stmt = mysqli_prepare($conn, $metrics_query);
$uid_str = (string)$user_id;
mysqli_stmt_bind_param($m_stmt, "isisii", $user_id, $user_id, $uid_str, $user_id, $user_id, $user_id);
mysqli_stmt_execute($m_stmt);
$metrics = mysqli_fetch_assoc(mysqli_stmt_get_result($m_stmt)) ?: [
    'total_won' => 0,
    'total_spent' => 0,
    'in_transit_count' => 0,
    'awaiting_payment_count' => 0
];
mysqli_stmt_close($m_stmt);

// 2. Fetch Filtered Count for Pagination
$count_query = "SELECT COUNT(DISTINCT i.id) AS total_filtered $sql_base $filter_clauses";
$c_stmt = mysqli_prepare($conn, $count_query);
$all_params = array_merge([$user_id, $user_id, $uid_str, $user_id, $user_id, $user_id], $filter_params);
$all_types  = "isisii" . $filter_types;
mysqli_stmt_bind_param($c_stmt, $all_types, ...$all_params);
mysqli_stmt_execute($c_stmt);
$total_records = (int)(mysqli_fetch_assoc(mysqli_stmt_get_result($c_stmt))['total_filtered'] ?? 0);
mysqli_stmt_close($c_stmt);

$total_pages = max(1, (int)ceil($total_records / $items_per_page));

// 3. Fetch Paginated Records
$records_query = "
    SELECT 
        i.id AS item_id,
        i.auction_code,
        i.title,
        i.featured_image,
        i.images,
        i.end_time,
        i.seller_id,
        COALESCE(b.bid_amount, i.current_price) AS hammer_price,
        u.name AS seller_name,
        u.email AS seller_email,
        u.phone AS seller_phone,
        c.name AS category_name,
        o.id AS order_id,
        o.order_number,
        COALESCE(o.platform_fee, 0.00) AS platform_fee,
        COALESCE(o.shipping_fee, 0.00) AS shipping_fee,
        COALESCE(o.total_amount, (COALESCE(b.bid_amount, i.current_price))) AS total_amount,
        COALESCE(o.payment_status, CASE WHEN p.payment_status = 'completed' THEN 'PAID' ELSE 'PAYMENT_PENDING' END) AS payment_status,
        COALESCE(o.shipping_status, 'PROCESSING') AS shipping_status,
        o.tracking_company,
        o.tracking_number,
        o.tracking_url,
        wl.status AS wallet_lock_status
    $sql_base
    $filter_clauses
    GROUP BY i.id
    ORDER BY i.end_time DESC
    LIMIT ? OFFSET ?
";

$r_stmt = mysqli_prepare($conn, $records_query);
$r_params = array_merge($all_params, [$items_per_page, $offset]);
$r_types  = $all_types . "ii";
mysqli_stmt_bind_param($r_stmt, $r_types, ...$r_params);
mysqli_stmt_execute($r_stmt);
$res = mysqli_stmt_get_result($r_stmt);
$orders = [];
while ($row = mysqli_fetch_assoc($res)) {
    $orders[] = $row;
}
mysqli_stmt_close($r_stmt);

include_once __DIR__ . '/../includes/header.php';
?>

<!-- Tailwind CSS & FontAwesome for Modern E-Commerce UI -->
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

    <!-- Top Breadcrumb & Page Heading -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
        <div>
            <nav class="text-xs text-slate-400 mb-1">
                <a href="dashboard.php" class="hover:text-emerald-700 transition">Dashboard</a> &rsaquo;
                <span class="text-slate-700 font-semibold">Won Auctions & Orders</span>
            </nav>
            <h1 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight flex items-center gap-3">
                <span class="w-10 h-10 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center text-lg shadow-sm border border-amber-200/60">
                    <i class="fa-solid fa-trophy"></i>
                </span>
                Won Auctions & Orders
            </h1>
            <p class="text-xs text-slate-500 mt-1">Track your winning lots, manage payments, courier dispatch, and delivery confirmations.</p>
        </div>

        <div class="flex items-center gap-3">
            <a href="wallet.php" class="inline-flex items-center gap-2 px-4 py-2 bg-white border border-slate-300 rounded-xl text-xs font-bold text-slate-700 hover:bg-slate-50 shadow-sm transition">
                <i class="fa-solid fa-wallet text-emerald-700"></i> My Wallet
            </a>
            <a href="../items.php" class="inline-flex items-center gap-2 px-4 py-2 bg-emerald-700 hover:bg-emerald-800 text-white rounded-xl text-xs font-bold shadow-sm transition">
                <i class="fa-solid fa-gavel"></i> Explore Live Auctions
            </a>
        </div>
    </div>

    <!-- Alert Notifications -->
    <?php if (!empty($success_msg)): ?>
        <div class="mb-6 p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 flex items-center gap-3 shadow-sm">
            <i class="fa-solid fa-circle-check text-emerald-600 text-lg"></i>
            <span class="text-sm font-semibold"><?php echo htmlspecialchars($success_msg); ?></span>
        </div>
    <?php endif; ?>

    <?php if (!empty($error_msg)): ?>
        <div class="mb-6 p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 flex items-center gap-3 shadow-sm">
            <i class="fa-solid fa-circle-exclamation text-rose-600 text-lg"></i>
            <span class="text-sm font-semibold"><?php echo htmlspecialchars($error_msg); ?></span>
        </div>
    <?php endif; ?>

    <!-- 1. Metrics Bar -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        <!-- Metric 1: Total Won -->
        <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center text-xl shrink-0">
                <i class="fa-solid fa-award"></i>
            </div>
            <div>
                <span class="text-xs text-slate-400 uppercase tracking-wider font-semibold block">Total Won Lots</span>
                <span class="text-2xl font-black text-slate-900"><?php echo number_format((float)$metrics['total_won']); ?></span>
            </div>
        </div>

        <!-- Metric 2: Total Spent -->
        <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-emerald-50 text-emerald-700 flex items-center justify-center text-xl shrink-0">
                <i class="fa-solid fa-coins"></i>
            </div>
            <div>
                <span class="text-xs text-slate-400 uppercase tracking-wider font-semibold block">Total Spent</span>
                <span class="text-2xl font-black text-slate-900">Rs. <?php echo number_format((float)$metrics['total_spent'], 0); ?></span>
            </div>
        </div>

        <!-- Metric 3: In-Transit -->
        <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center text-xl shrink-0">
                <i class="fa-solid fa-truck-fast"></i>
            </div>
            <div>
                <span class="text-xs text-slate-400 uppercase tracking-wider font-semibold block">In-Transit Orders</span>
                <span class="text-2xl font-black text-slate-900"><?php echo number_format((float)$metrics['in_transit_count']); ?></span>
            </div>
        </div>

        <!-- Metric 4: Awaiting Payment -->
        <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-rose-50 text-rose-600 flex items-center justify-center text-xl shrink-0">
                <i class="fa-solid fa-clock-rotate-left"></i>
            </div>
            <div>
                <span class="text-xs text-slate-400 uppercase tracking-wider font-semibold block">Awaiting Payment</span>
                <span class="text-2xl font-black text-rose-600"><?php echo number_format((float)$metrics['awaiting_payment_count']); ?></span>
            </div>
        </div>
    </div>

    <!-- 2. Search & Status Filters Bar -->
    <div class="bg-white p-4 sm:p-5 rounded-2xl border border-slate-200 shadow-sm mb-6 flex flex-col md:flex-row items-center justify-between gap-4">
        <!-- Filter Tabs -->
        <div class="flex items-center gap-2 overflow-x-auto w-full md:w-auto pb-2 md:pb-0">
            <a href="?status=ALL<?php echo !empty($search_query) ? '&q=' . urlencode($search_query) : ''; ?>" 
               class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition shrink-0 <?php echo $status_filter === 'ALL' ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'; ?>">
                All (<?php echo $metrics['total_won']; ?>)
            </a>
            <a href="?status=AWAITING_PAYMENT<?php echo !empty($search_query) ? '&q=' . urlencode($search_query) : ''; ?>" 
               class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition shrink-0 <?php echo $status_filter === 'AWAITING_PAYMENT' ? 'bg-rose-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'; ?>">
                Awaiting Payment (<?php echo $metrics['awaiting_payment_count']; ?>)
            </a>
            <a href="?status=IN_TRANSIT<?php echo !empty($search_query) ? '&q=' . urlencode($search_query) : ''; ?>" 
               class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition shrink-0 <?php echo $status_filter === 'IN_TRANSIT' ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'; ?>">
                In-Transit (<?php echo $metrics['in_transit_count']; ?>)
            </a>
            <a href="?status=COMPLETED<?php echo !empty($search_query) ? '&q=' . urlencode($search_query) : ''; ?>" 
               class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition shrink-0 <?php echo $status_filter === 'COMPLETED' ? 'bg-emerald-700 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'; ?>">
                Completed
            </a>
            <a href="?status=DISPUTED<?php echo !empty($search_query) ? '&q=' . urlencode($search_query) : ''; ?>" 
               class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition shrink-0 <?php echo $status_filter === 'DISPUTED' ? 'bg-amber-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'; ?>">
                Disputed
            </a>
        </div>

        <!-- Search Input -->
        <form method="GET" action="" class="w-full md:w-72 flex items-center relative">
            <input type="hidden" name="status" value="<?php echo htmlspecialchars($status_filter); ?>">
            <i class="fa-solid fa-magnifying-glass absolute left-3.5 text-xs text-slate-400"></i>
            <input type="text" name="q" value="<?php echo htmlspecialchars($search_query); ?>" 
                   placeholder="Search title, lot #..." 
                   class="w-full pl-9 pr-8 py-2 text-xs bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:border-emerald-600 outline-none transition">
            <?php if (!empty($search_query)): ?>
                <a href="?status=<?php echo htmlspecialchars($status_filter); ?>" class="absolute right-3 text-xs text-slate-400 hover:text-slate-600">&times;</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- 3. Orders Table & Cards List -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden mb-6">
        <?php if (!empty($orders)): ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50/80 text-slate-500 font-semibold text-xs border-b border-slate-200">
                        <tr>
                            <th class="p-4 pl-6">Lot & Item Details</th>
                            <th class="p-4">Winning Hammer Price</th>
                            <th class="p-4">Payment Status</th>
                            <th class="p-4">Fulfillment Status</th>
                            <th class="p-4">Seller Details</th>
                            <th class="p-4 pr-6 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($orders as $order): 
                            $is_paid = in_array(strtoupper($order['payment_status']), ['PAID', 'AUTO_SETTLED']);
                            $ship_status = strtoupper($order['shipping_status'] ?? 'PROCESSING');
                            $has_wallet_lock = ($order['wallet_lock_status'] === 'SETTLED');
                            $order_num = $order['order_number'] ?? ('ORD-' . $order['item_id']);
                        ?>
                        <tr class="hover:bg-slate-50/70 transition">
                            <!-- Item & Lot Info -->
                            <td class="p-4 pl-6">
                                <div class="flex items-center gap-3.5">
                                    <div class="w-14 h-14 rounded-xl bg-slate-100 overflow-hidden shrink-0 border border-slate-200">
                                        <img src="<?php echo !empty($order['featured_image']) ? '../' . htmlspecialchars($order['featured_image']) : 'https://via.placeholder.com/80'; ?>" 
                                             alt="Item" class="w-full h-full object-cover">
                                    </div>
                                    <div>
                                        <div class="flex items-center gap-2 mb-1">
                                            <span class="text-[10px] font-bold font-mono px-2 py-0.5 rounded bg-slate-100 text-slate-700">
                                                #<?php echo htmlspecialchars((string)($order['auction_code'] ?? '')); ?>
                                            </span>
                                            <span class="text-[11px] text-slate-400 font-medium">
                                                <?php echo htmlspecialchars((string)($order['category_name'] ?? 'Auction')); ?>
                                            </span>
                                        </div>
                                        <a href="../item.php?id=<?php echo (int)$order['item_id']; ?>" class="font-bold text-slate-900 hover:text-emerald-700 transition line-clamp-1">
                                            <?php echo htmlspecialchars((string)($order['title'] ?? '')); ?>
                                        </a>
                                        <span class="text-[11px] text-slate-400 block mt-0.5">
                                            Won on <?php echo date('M d, Y', strtotime($order['end_time'])); ?>
                                        </span>
                                    </div>
                                </div>
                            </td>

                            <!-- Price Breakdown -->
                            <td class="p-4">
                                <span class="text-base font-black text-slate-900 block">
                                    Rs. <?php echo number_format((float)$order['total_amount'], 2); ?>
                                </span>
                                <span class="text-[11px] text-slate-400">
                                    Hammer: Rs. <?php echo number_format((float)$order['hammer_price'], 0); ?>
                                </span>
                            </td>

                            <!-- Payment Badge -->
                            <td class="p-4">
                                <?php if ($is_paid): ?>
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 border border-emerald-200">
                                        <i class="fa-solid fa-circle-check text-emerald-600"></i>
                                        <?php echo $has_wallet_lock ? 'Paid via Lock' : 'Payment Confirmed'; ?>
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-rose-100 text-rose-800 border border-rose-200">
                                        <i class="fa-solid fa-clock text-rose-600"></i> Awaiting Payment
                                    </span>
                                <?php endif; ?>
                            </td>

                            <!-- Shipping / Fulfillment Badge -->
                            <td class="p-4">
                                <?php if ($ship_status === 'SHIPPED'): ?>
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-indigo-100 text-indigo-800 border border-indigo-200">
                                        <i class="fa-solid fa-truck-fast text-indigo-600"></i> In-Transit
                                    </span>
                                    <?php if (!empty($order['tracking_company'])): ?>
                                        <span class="text-[11px] text-slate-500 block mt-1 font-mono">
                                            <?php echo htmlspecialchars($order['tracking_company']); ?>: <?php echo htmlspecialchars($order['tracking_number'] ?? ''); ?>
                                        </span>
                                    <?php endif; ?>
                                <?php elseif ($ship_status === 'COMPLETED' || $ship_status === 'DELIVERED'): ?>
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 border border-emerald-200">
                                        <i class="fa-solid fa-box-open text-emerald-600"></i> Delivered
                                    </span>
                                <?php elseif ($ship_status === 'DISPUTED'): ?>
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-amber-100 text-amber-800 border border-amber-200">
                                        <i class="fa-solid fa-triangle-exclamation text-amber-600"></i> Disputed
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-slate-100 text-slate-700 border border-slate-200">
                                        <i class="fa-solid fa-box text-slate-500"></i> Preparing Dispatch
                                    </span>
                                <?php endif; ?>
                            </td>

                            <!-- Seller Info -->
                            <td class="p-4">
                                <span class="font-bold text-slate-800 block text-xs">
                                    <?php echo htmlspecialchars($order['seller_name'] ?? 'Platform Verified Seller'); ?>
                                </span>
                                <button type="button" 
                                        onclick="openContactSellerModal('<?php echo htmlspecialchars(addslashes($order['seller_name'] ?? 'Seller')); ?>', '<?php echo htmlspecialchars($order['seller_email'] ?? 'support@geoauction.com'); ?>', '<?php echo htmlspecialchars($order['seller_phone'] ?? 'N/A'); ?>')"
                                        class="text-xs text-emerald-700 font-bold hover:underline inline-flex items-center gap-1 mt-0.5">
                                    <i class="fa-regular fa-envelope"></i> Contact Seller
                                </button>
                            </td>

                            <!-- Action Buttons -->
                            <td class="p-4 pr-6 text-right">
                                <div class="inline-flex items-center gap-2">
                                    <?php if (!$is_paid): ?>
                                        <!-- Pay Now Portal Button (Supports Bank Transfer, JazzCash, Easypaisa, Wallet) -->
                                        <a href="pay.php?item_id=<?php echo (int)$order['item_id']; ?>" class="px-3 py-1.5 bg-emerald-700 hover:bg-emerald-800 text-white rounded-lg text-xs font-bold shadow-sm transition inline-flex items-center">
                                            <i class="fa-solid fa-credit-card mr-1"></i> Pay Now / Manual Transfer
                                        </a>
                                    <?php else: ?>
                                        <!-- Invoice Buttons (View & PDF Download) -->
                                        <div class="inline-flex rounded-lg shadow-sm border border-slate-200 overflow-hidden">
                                            <a href="../invoice.php?order_id=<?php echo (int)($order['order_id'] ?? 0); ?>&item_id=<?php echo (int)$order['item_id']; ?>" 
                                               class="px-2.5 py-1.5 bg-slate-50 hover:bg-slate-100 text-slate-800 text-xs font-bold border-r border-slate-200 transition inline-flex items-center gap-1"
                                               title="View Interactive Invoice">
                                                <i class="fa-solid fa-receipt text-emerald-700"></i> Invoice
                                            </a>
                                            <a href="../invoice.php?order_id=<?php echo (int)($order['order_id'] ?? 0); ?>&item_id=<?php echo (int)$order['item_id']; ?>&format=download" 
                                               class="px-2.5 py-1.5 bg-emerald-50 hover:bg-emerald-100 text-emerald-800 text-xs font-bold transition inline-flex items-center gap-1"
                                               title="Download Official PDF Receipt">
                                                <i class="fa-solid fa-file-arrow-down text-emerald-600"></i> PDF
                                            </a>
                                        </div>
                                    <?php endif; ?>

                                    <?php if ($ship_status === 'SHIPPED'): ?>
                                        <!-- Confirm Delivery -->
                                        <form method="POST" action="" onsubmit="return confirm('Confirm that you have received this parcel in good condition?');">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                            <input type="hidden" name="action" value="confirm_delivery">
                                            <input type="hidden" name="order_id" value="<?php echo (int)$order['order_id']; ?>">
                                            <button type="submit" class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-xs font-bold shadow-sm transition">
                                                <i class="fa-solid fa-check-double mr-1"></i> Received
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination Bar -->
            <?php if ($total_pages > 1): ?>
            <div class="p-4 border-t border-slate-200 flex items-center justify-between text-xs text-slate-500">
                <span>Showing page <strong><?php echo $current_page; ?></strong> of <strong><?php echo $total_pages; ?></strong></span>
                <div class="flex gap-1">
                    <?php if ($current_page > 1): ?>
                        <a href="?page=<?php echo ($current_page - 1); ?>&status=<?php echo urlencode($status_filter); ?>&q=<?php echo urlencode($search_query); ?>" 
                           class="px-3 py-1.5 bg-white border border-slate-300 rounded-lg text-slate-700 font-bold hover:bg-slate-50">Previous</a>
                    <?php endif; ?>
                    <?php if ($current_page < $total_pages): ?>
                        <a href="?page=<?php echo ($current_page + 1); ?>&status=<?php echo urlencode($status_filter); ?>&q=<?php echo urlencode($search_query); ?>" 
                           class="px-3 py-1.5 bg-white border border-slate-300 rounded-lg text-slate-700 font-bold hover:bg-slate-50">Next</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

        <?php else: ?>
            <!-- 4. Empty State Design -->
            <div class="text-center py-16 px-4">
                <div class="w-16 h-16 rounded-2xl bg-slate-100 text-slate-400 flex items-center justify-center text-2xl mx-auto mb-4 border border-slate-200">
                    <i class="fa-solid fa-trophy"></i>
                </div>
                <h3 class="text-lg font-bold text-slate-900 mb-1">No Won Auctions Found</h3>
                <p class="text-xs text-slate-500 max-w-sm mx-auto mb-6">
                    <?php echo !empty($search_query) || $status_filter !== 'ALL' 
                        ? 'No records match your selected filter criteria. Try resetting your search.' 
                        : 'You have not won any auctions yet. Start placing bids on live items to see your won orders here!'; ?>
                </p>
                <div class="flex justify-center gap-3">
                    <?php if (!empty($search_query) || $status_filter !== 'ALL'): ?>
                        <a href="won_auctions.php" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl transition">
                            Reset Filters
                        </a>
                    <?php endif; ?>
                    <a href="../items.php" class="px-5 py-2.5 bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-bold rounded-xl shadow-sm transition inline-flex items-center gap-2">
                        <i class="fa-solid fa-gavel"></i> Browse Live Auctions
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>

</div>

<!-- Contact Seller Modal -->
<div id="contactSellerModal" class="fixed inset-0 z-50 bg-slate-950/60 backdrop-blur-xs hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-xl border border-slate-200 animate-in fade-in zoom-in duration-200">
        <div class="flex justify-between items-center mb-4">
            <h3 class="font-bold text-slate-900 text-base flex items-center gap-2">
                <i class="fa-solid fa-user-tag text-emerald-700"></i> Seller Contact Details
            </h3>
            <button type="button" onclick="closeContactSellerModal()" class="text-slate-400 hover:text-slate-600 text-lg font-bold">&times;</button>
        </div>
        <div class="space-y-3 text-xs">
            <div class="p-3 bg-slate-50 rounded-xl border border-slate-200">
                <span class="text-slate-400 block font-semibold uppercase text-[10px]">Seller Name</span>
                <strong id="modalSellerName" class="text-slate-900 text-sm block mt-0.5"></strong>
            </div>
            <div class="p-3 bg-slate-50 rounded-xl border border-slate-200">
                <span class="text-slate-400 block font-semibold uppercase text-[10px]">Email Address</span>
                <span id="modalSellerEmail" class="text-slate-900 font-mono block mt-0.5"></span>
            </div>
            <div class="p-3 bg-slate-50 rounded-xl border border-slate-200">
                <span class="text-slate-400 block font-semibold uppercase text-[10px]">Direct Phone</span>
                <span id="modalSellerPhone" class="text-slate-900 font-mono block mt-0.5"></span>
            </div>
        </div>
        <div class="mt-6 flex justify-end">
            <button type="button" onclick="closeContactSellerModal()" class="px-4 py-2 bg-slate-900 text-white rounded-xl text-xs font-bold">
                Close
            </button>
        </div>
    </div>
</div>

<!-- Modal Control JavaScript -->
<script>
function openContactSellerModal(name, email, phone) {
    document.getElementById('modalSellerName').innerText = name;
    document.getElementById('modalSellerEmail').innerText = email;
    document.getElementById('modalSellerPhone').innerText = phone;
    document.getElementById('contactSellerModal').classList.remove('hidden');
}

function closeContactSellerModal() {
    document.getElementById('contactSellerModal').classList.add('hidden');
}
</script>

<?php
include_once __DIR__ . '/../includes/footer.php';
?>
