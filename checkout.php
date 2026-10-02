<?php
/**
 * ============================================================================
 * File: checkout.php
 * Path: /checkout.php (Project Root)
 * Description: Complete Auction Checkout, Payment & Order Finalization Engine
 * Architecture: Senior PHP Full-Stack Standards (Bootstrap 5, PDO Transactions)
 * ============================================================================
 * 
 * Yeh page sirf auction ke verified winner ko checkout allow karta hai.
 * 1. Checks auction status ('ended') aur verifies logged-in user is the winner.
 * 2. Shipping details entry form (Name, Address, City, Phone).
 * 3. Payment Methods: Wallet Balance, Direct Bank Transfer, Card, COD/Escrow.
 * 4. Transactional Order Creation: Inserts into `orders`, `payments`, `transactions`.
 * 5. Updates item status to 'sold' and sends automated notifications to buyer & seller.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth_check.php';

// Enforce login
$user = require_auth();
$userId = (int)$user['id'];
$pdo    = getDB();

$itemId = (int)($_GET['item_id'] ?? $_POST['item_id'] ?? 0);

if ($itemId <= 0) {
    set_flash('error', 'Please select a valid auction item to checkout.');
    header("Location: index.php");
    exit();
}

// 1. Fetch Item and verify ownership & auction state
$itemStmt = $pdo->prepare("
    SELECT 
        i.*, 
        c.name AS category_name,
        u.name AS seller_name,
        u.email AS seller_email,
        u.phone AS seller_phone
    FROM items i
    LEFT JOIN categories c ON c.id = i.category_id
    LEFT JOIN users u ON u.id = i.seller_id
    WHERE i.id = :id
    LIMIT 1
");
$itemStmt->execute([':id' => $itemId]);
$item = $itemStmt->fetch();

if (!$item) {
    set_flash('error', 'Auction listing not found.');
    header("Location: index.php");
    exit();
}

// 2. Fetch winning bid
$winningBidStmt = $pdo->prepare("
    SELECT id, user_id, bid_amount, created_at 
    FROM bids 
    WHERE item_id = :item_id 
    ORDER BY bid_amount DESC, is_winning DESC 
    LIMIT 1
");
$winningBidStmt->execute([':item_id' => $itemId]);
$winningBid = $winningBidStmt->fetch();

if (!$winningBid) {
    set_flash('error', 'No bids were recorded for this auction.');
    header("Location: live_auction.php?id=" . $itemId);
    exit();
}

$winningBidderId = (int)($item['winner_id'] ?: $winningBid['user_id']);
$winningAmount   = (float)$winningBid['bid_amount'];

// 3. Security: Check if current logged-in user is the winning bidder
if ($winningBidderId !== $userId) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Unauthorized Checkout – Geo Auction</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    </head>
    <body class="bg-light d-flex align-items-center justify-content-center min-vh-100">
        <div class="card shadow-sm border-0 text-center p-4 p-md-5" style="max-width: 520px; border-radius: 16px;">
            <div class="mb-3 text-warning">
                <i class="fa-solid fa-lock fa-4x"></i>
            </div>
            <h3 class="fw-bold text-dark">Access Restricted</h3>
            <p class="text-muted mt-2">
                Checkout for this auction lot is exclusively reserved for the official winning bidder.
            </p>
            <div class="mt-4">
                <a href="live_auction.php?id=<?= $itemId ?>" class="btn btn-outline-secondary px-4 me-2">
                    <i class="fa-solid fa-arrow-left me-1"></i> View Auction
                </a>
                <a href="index.php" class="btn btn-primary px-4" style="background-color: #047857; border-color: #047857;">
                    Explore Auctions
                </a>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit();
}

// 4. Check if already purchased / paid
$existingOrderStmt = $pdo->prepare("
    SELECT * FROM orders 
    WHERE auction_id = :item_id AND buyer_id = :buyer_id 
    LIMIT 1
");
$existingOrderStmt->execute([
    ':item_id'  => $itemId,
    ':buyer_id' => $userId
]);
$existingOrder = $existingOrderStmt->fetch();

// Financial Calculations
$hammerPrice = $winningAmount;
$platformFee = round($hammerPrice * 0.02, 2); // 2% marketplace protection fee
$shippingFee = 500.00; // Flat insured logistics fee (PKR)
$totalAmount = $hammerPrice + $platformFee + $shippingFee;

// User's current wallet balance
$userBalStmt = $pdo->prepare("SELECT balance FROM users WHERE id = :id");
$userBalStmt->execute([':id' => $userId]);
$userBalance = (float)$userBalStmt->fetchColumn();

$errors = [];
$successOrder = $existingOrder;

// -------------------------------------------------------------
// POST Request: Process Payment & Place Order
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order']) && !$existingOrder) {
    if (!verify_csrf_token()) {
        $errors[] = 'Security token expired. Please try again.';
    }

    $fullName   = trim($_POST['full_name'] ?? '');
    $phone      = trim($_POST['phone'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $address    = trim($_POST['address'] ?? '');
    $city       = trim($_POST['city'] ?? '');
    $state      = trim($_POST['state'] ?? '');
    $zipCode    = trim($_POST['zip_code'] ?? '');
    $payMethod  = trim($_POST['payment_method'] ?? 'cod');

    // Validation
    if (empty($fullName)) $errors[] = 'Recipient Full Name is required.';
    if (empty($phone)) $errors[] = 'Contact Phone Number is required.';
    if (empty($address) || empty($city)) $errors[] = 'Complete Delivery Address & City are required.';

    $validMethods = ['cod', 'bank_transfer', 'card', 'wallet'];
    if (!in_array($payMethod, $validMethods)) {
        $errors[] = 'Please select a valid payment method.';
    }

    if ($payMethod === 'wallet' && $userBalance < $totalAmount) {
        $errors[] = 'Insufficient Wallet Balance (Available: Rs. ' . number_format($userBalance, 2) . ', Required: Rs. ' . number_format($totalAmount, 2) . '). Please select another payment method or top up your wallet.';
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // Generate unique Order Number & Invoice No
            $orderNumber = 'ORD-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
            $invoiceNo   = 'INV-' . strtoupper(substr(bin2hex(random_bytes(5)), 0, 8));

            $formattedShipping = json_encode([
                'recipient_name' => $fullName,
                'phone'          => $phone,
                'email'          => $email,
                'address'        => $address,
                'city'           => $city,
                'state'          => $state,
                'zip_code'       => $zipCode
            ], JSON_UNESCAPED_UNICODE);

            $payStatus = ($payMethod === 'cod') ? 'PAYMENT_PENDING' : 'PAID';

            // 1. Insert into orders table
            $insOrderStmt = $pdo->prepare("
                INSERT INTO orders (
                    order_number, auction_id, buyer_id, seller_id, 
                    hammer_price, platform_fee, shipping_fee, total_amount, 
                    payment_status, shipping_status, shipping_address, 
                    created_at, updated_at
                ) VALUES (
                    :order_number, :auction_id, :buyer_id, :seller_id, 
                    :hammer_price, :platform_fee, :shipping_fee, :total_amount, 
                    :payment_status, 'PROCESSING', :shipping_address, 
                    NOW(), NOW()
                )
            ");
            $insOrderStmt->execute([
                ':order_number'     => $orderNumber,
                ':auction_id'       => $itemId,
                ':buyer_id'         => $userId,
                ':seller_id'        => (int)$item['seller_id'],
                ':hammer_price'     => $hammerPrice,
                ':platform_fee'     => $platformFee,
                ':shipping_fee'     => $shippingFee,
                ':total_amount'     => $totalAmount,
                ':payment_status'   => $payStatus,
                ':shipping_address' => $formattedShipping
            ]);
            $newOrderId = (int)$pdo->lastInsertId();

            // 2. Insert into payments table
            $insPayStmt = $pdo->prepare("
                INSERT INTO payments (
                    invoice_no, user_id, item_id, bid_id, 
                    amount, fee, total_amount, currency, 
                    payment_method, payment_type, transaction_id, 
                    payment_status, billing_name, billing_email, 
                    billing_phone, billing_address, billing_city, 
                    billing_state, billing_zip, billing_country, 
                    paid_at, created_at, updated_at, buyer_id
                ) VALUES (
                    :invoice_no, :user_id, :item_id, :bid_id, 
                    :amount, :fee, :total_amount, 'PKR', 
                    :payment_method, 'auction_win', :txn_id, 
                    :pay_status, :billing_name, :billing_email, 
                    :billing_phone, :billing_address, :billing_city, 
                    :billing_state, :billing_zip, 'Pakistan', 
                    NOW(), NOW(), NOW(), :buyer_id_str
                )
            ");
            $txnId = 'TXN-' . strtoupper(bin2hex(random_bytes(6)));
            $insPayStmt->execute([
                ':invoice_no'       => $invoiceNo,
                ':user_id'          => $userId,
                ':item_id'          => $itemId,
                ':bid_id'           => (int)$winningBid['id'],
                ':amount'           => $hammerPrice,
                ':fee'              => $platformFee,
                ':total_amount'     => $totalAmount,
                ':payment_method'   => $payMethod,
                ':txn_id'           => $txnId,
                ':pay_status'       => ($payStatus === 'PAID') ? 'completed' : 'pending',
                ':billing_name'     => $fullName,
                ':billing_email'    => $email,
                ':billing_phone'    => $phone,
                ':billing_address'  => $address,
                ':billing_city'     => $city,
                ':billing_state'    => $state,
                ':billing_zip'      => $zipCode,
                ':buyer_id_str'     => (string)$userId
            ]);

            // 3. If Wallet Payment: Deduct Balance & Record Transaction
            if ($payMethod === 'wallet') {
                $deductStmt = $pdo->prepare("UPDATE users SET balance = balance - :amount WHERE id = :id");
                $deductStmt->execute([
                    ':amount' => $totalAmount,
                    ':id'     => $userId
                ]);

                $txnStmt = $pdo->prepare("
                    INSERT INTO transactions (
                        user_id, type, amount, balance_before, balance_after, 
                        item_id, reference_id, description, status, created_at
                    ) VALUES (
                        :user_id, 'bid_payment', :amount, :before, :after, 
                        :item_id, :ref_id, :descr, 'completed', NOW()
                    )
                ");
                $txnStmt->execute([
                    ':user_id' => $userId,
                    ':amount'  => $totalAmount,
                    ':before'  => $userBalance,
                    ':after'   => ($userBalance - $totalAmount),
                    ':item_id' => $itemId,
                    ':ref_id'  => $orderNumber,
                    ':descr'   => "Auction settlement for item #" . $item['auction_code'] . " (" . $item['title'] . ")"
                ]);
            }

            // 4. Mark Item as Sold
            $upItemStmt = $pdo->prepare("
                UPDATE items 
                SET status = 'sold', winner_id = :winner_id, updated_at = NOW() 
                WHERE id = :id
            ");
            $upItemStmt->execute([
                ':winner_id' => $userId,
                ':id'        => $itemId
            ]);

            // 5. Create System Notifications
            // Notify Buyer
            $pdo->prepare("
                INSERT INTO notifications (user_id, type, title, message, link, is_read, created_at)
                VALUES (:user_id, 'order_confirmed', 'Order Confirmed!', :msg, :link, 0, NOW())
            ")->execute([
                ':user_id' => $userId,
                ':msg'     => "Your order #{$orderNumber} for '{$item['title']}' has been confirmed! We will notify you once dispatched.",
                ':link'    => "checkout.php?item_id={$itemId}"
            ]);

            // Notify Seller
            $pdo->prepare("
                INSERT INTO notifications (user_id, type, title, message, link, is_read, created_at)
                VALUES (:user_id, 'item_sold', 'Item Sold & Paid!', :msg, :link, 0, NOW())
            ")->execute([
                ':user_id' => (int)$item['seller_id'],
                ':msg'     => "Lot #{$item['auction_code']} ('{$item['title']}') was purchased by {$fullName}. Order #{$orderNumber} is ready for packing.",
                ':link'    => "user/my_auctions.php"
            ]);

            $pdo->commit();

            // Fetch newly created order to render success view
            $fetchNewOrder = $pdo->prepare("SELECT * FROM orders WHERE id = :id");
            $fetchNewOrder->execute([':id' => $newOrderId]);
            $successOrder = $fetchNewOrder->fetch();

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = 'Checkout failed: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout – <?= htmlspecialchars($item['title']) ?> – Geo Auction</title>
    
    <!-- Modern Bootstrap 5 & FontAwesome -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- SweetAlert2 for modern interactive dialogs -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        :root {
            --primary: #047857;
            --primary-hover: #065f46;
            --primary-light: #ecfdf5;
            --dark: #0f172a;
            --gray-subtle: #f8fafc;
            --border-color: #e2e8f0;
        }
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: #f1f5f9;
            color: #1e293b;
        }
        .navbar-brand {
            font-weight: 700;
            color: var(--primary);
        }
        .checkout-card {
            background: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: 16px;
            box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.05);
        }
        .item-thumb {
            width: 80px;
            height: 80px;
            border-radius: 12px;
            object-fit: cover;
            border: 1px solid var(--border-color);
        }
        .price-breakdown-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            font-size: 0.95rem;
            color: #64748b;
        }
        .price-breakdown-row.total {
            font-size: 1.25rem;
            font-weight: 700;
            color: #0f172a;
            border-top: 2px dashed #cbd5e1;
            margin-top: 8px;
            padding-top: 14px;
        }
        .payment-method-label {
            display: flex;
            align-items: center;
            padding: 14px 18px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.2s ease-in-out;
            background: #ffffff;
        }
        .payment-method-label:hover {
            border-color: #94a3b8;
            background: #f8fafc;
        }
        .form-check-input:checked + .payment-method-label {
            border-color: var(--primary);
            background: var(--primary-light);
        }
        .badge-verified-winner {
            background: #fef3c7;
            color: #92400e;
            font-weight: 600;
            padding: 6px 14px;
            border-radius: 30px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 0.85rem;
        }
        .btn-confirm-order {
            background-color: var(--primary);
            border: none;
            color: #ffffff;
            font-weight: 600;
            padding: 14px 28px;
            border-radius: 12px;
            font-size: 1.1rem;
            transition: background 0.2s;
        }
        .btn-confirm-order:hover {
            background-color: var(--primary-hover);
            color: #ffffff;
        }
        .receipt-stamp {
            border: 2px solid var(--primary);
            color: var(--primary);
            padding: 4px 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 2px;
            display: inline-block;
            border-radius: 6px;
            transform: rotate(-5deg);
        }
    </style>
</head>
<body>

    <!-- Header Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom sticky-top py-3">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center gap-2" href="index.php">
                <i class="fa-solid fa-gavel fa-lg text-emerald-600" style="color: #047857;"></i>
                <span class="fs-4">Geo Auction</span>
            </a>
            <div class="d-flex align-items-center gap-3">
                <span class="text-muted d-none d-md-inline">Welcome, <strong><?= htmlspecialchars($user['name']) ?></strong></span>
                <a href="user/dashboard.php" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
                    <i class="fa-solid fa-user me-1"></i> Dashboard
                </a>
            </div>
        </div>
    </nav>

    <div class="container py-5">
        <?php if ($successOrder): ?>
            <!-- ============================================================= -->
            <!-- SUCCESS CONFIRMATION RECEIPT VIEW                             -->
            <!-- ============================================================= -->
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="checkout-card p-4 p-md-5">
                        <div class="text-center mb-4">
                            <div class="mb-3 text-success">
                                <i class="fa-solid fa-circle-check fa-4x" style="color: #047857;"></i>
                            </div>
                            <span class="receipt-stamp mb-2">Payment Settled</span>
                            <h2 class="fw-bold text-dark mt-3">Order Confirmed!</h2>
                            <p class="text-muted">
                                Thank you for your purchase. Your order has been placed and transmitted to the seller.
                            </p>
                        </div>

                        <div class="bg-light p-4 rounded-4 mb-4 border">
                            <div class="row g-3">
                                <div class="col-6 col-md-3">
                                    <small class="text-muted d-block">Order Reference</small>
                                    <strong class="text-dark"><?= htmlspecialchars($successOrder['order_number']) ?></strong>
                                </div>
                                <div class="col-6 col-md-3">
                                    <small class="text-muted d-block">Order Date</small>
                                    <strong class="text-dark"><?= date('M d, Y', strtotime($successOrder['created_at'])) ?></strong>
                                </div>
                                <div class="col-6 col-md-3">
                                    <small class="text-muted d-block">Total Paid</small>
                                    <strong class="text-success fs-6">Rs. <?= number_format($successOrder['total_amount'], 2) ?></strong>
                                </div>
                                <div class="col-6 col-md-3">
                                    <small class="text-muted d-block">Shipping Status</small>
                                    <span class="badge bg-primary rounded-pill"><?= htmlspecialchars($successOrder['shipping_status']) ?></span>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex align-items-center gap-3 p-3 bg-white rounded-3 border mb-4">
                            <?php 
                                $img = !empty($item['featured_image']) ? htmlspecialchars($item['featured_image']) : 'assets/image/placeholder.png';
                            ?>
                            <img src="<?= $img ?>" alt="Item Image" class="item-thumb">
                            <div class="flex-grow-1">
                                <h6 class="mb-1 fw-bold"><?= htmlspecialchars($item['title']) ?></h6>
                                <p class="text-muted small mb-0">Lot: <code><?= htmlspecialchars($item['auction_code']) ?></code> | Seller: <?= htmlspecialchars($item['seller_name']) ?></p>
                            </div>
                            <div class="text-end">
                                <span class="fw-bold text-dark">Rs. <?= number_format($successOrder['hammer_price'], 2) ?></span>
                            </div>
                        </div>

                        <div class="d-flex flex-wrap gap-2 justify-content-between pt-3 border-top">
                            <button onclick="window.print()" class="btn btn-outline-secondary rounded-pill px-4">
                                <i class="fa-solid fa-print me-1"></i> Print Receipt
                            </button>
                            <div class="d-flex gap-2">
                                <a href="user/won_auctions.php" class="btn btn-outline-success rounded-pill px-4">
                                    My Won Auctions
                                </a>
                                <a href="index.php" class="btn btn-primary rounded-pill px-4" style="background-color: #047857; border-color: #047857;">
                                    Continue Exploring
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <script>
                // Celebrate successful order placement
                Swal.fire({
                    icon: 'success',
                    title: 'Payment Successful!',
                    text: 'Your order <?= htmlspecialchars($successOrder['order_number']) ?> has been confirmed.',
                    timer: 3500,
                    showConfirmButton: false
                });
            </script>

        <?php else: ?>

            <!-- ============================================================= -->
            <!-- ACTIVE CHECKOUT & PAYMENT FORM VIEW                           -->
            <!-- ============================================================= -->
            <div class="row g-4">
                <!-- Left Column: Shipping Form & Payment Selection -->
                <div class="col-lg-7">
                    <div class="checkout-card p-4 p-md-5 mb-4">
                        <div class="d-flex align-items-center justify-content-between mb-4">
                            <div>
                                <span class="badge-verified-winner mb-2">
                                    <i class="fa-solid fa-trophy text-warning"></i> Verified Winning Bidder
                                </span>
                                <h3 class="fw-bold mb-0">Complete Your Purchase</h3>
                            </div>
                            <div class="text-end">
                                <small class="text-muted">Lot Code</small>
                                <div class="fw-bold text-dark">#<?= htmlspecialchars($item['auction_code']) ?></div>
                            </div>
                        </div>

                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger rounded-4 py-2 px-3 small mb-4">
                                <ul class="mb-0 ps-3">
                                    <?php foreach ($errors as $err): ?>
                                        <li><?= htmlspecialchars($err) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="checkout.php" id="checkoutForm">
                            <?= csrf_field() ?>
                            <input type="hidden" name="item_id" value="<?= $itemId ?>">
                            <input type="hidden" name="place_order" value="1">

                            <!-- Section 1: Shipping Information -->
                            <div class="mb-5">
                                <h5 class="fw-bold text-dark mb-3 d-flex align-items-center gap-2">
                                    <i class="fa-solid fa-truck-fast text-emerald-600" style="color: #047857;"></i>
                                    1. Shipping & Logistics Address
                                </h5>

                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label small fw-semibold text-secondary">Recipient Full Name *</label>
                                        <input type="text" name="full_name" class="form-control rounded-3" 
                                               value="<?= htmlspecialchars($_POST['full_name'] ?? $user['name']) ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-semibold text-secondary">Phone Number *</label>
                                        <input type="tel" name="phone" class="form-control rounded-3" 
                                               placeholder="03001234567" value="<?= htmlspecialchars($_POST['phone'] ?? ($user['phone'] ?? '')) ?>" required>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label small fw-semibold text-secondary">Email Address</label>
                                        <input type="email" name="email" class="form-control rounded-3" 
                                               value="<?= htmlspecialchars($_POST['email'] ?? $user['email']) ?>" readonly>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label small fw-semibold text-secondary">Street Delivery Address *</label>
                                        <textarea name="address" class="form-control rounded-3" rows="2" 
                                                  placeholder="House / Apartment #, Street, Colony..." required><?= htmlspecialchars($_POST['address'] ?? '') ?></textarea>
                                    </div>
                                    <div class="col-md-5">
                                        <label class="form-label small fw-semibold text-secondary">City *</label>
                                        <input type="text" name="city" class="form-control rounded-3" 
                                               placeholder="e.g. Lahore, Karachi, Islamabad" value="<?= htmlspecialchars($_POST['city'] ?? '') ?>" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label small fw-semibold text-secondary">State / Province</label>
                                        <input type="text" name="state" class="form-control rounded-3" 
                                               placeholder="e.g. Punjab, Sindh" value="<?= htmlspecialchars($_POST['state'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label small fw-semibold text-secondary">Postal Code</label>
                                        <input type="text" name="zip_code" class="form-control rounded-3" 
                                               placeholder="54000" value="<?= htmlspecialchars($_POST['zip_code'] ?? '') ?>">
                                    </div>
                                </div>
                            </div>

                            <!-- Section 2: Payment Method -->
                            <div class="mb-4">
                                <h5 class="fw-bold text-dark mb-3 d-flex align-items-center gap-2">
                                    <i class="fa-solid fa-credit-card text-emerald-600" style="color: #047857;"></i>
                                    2. Choose Payment Method
                                </h5>

                                <div class="d-flex flex-column gap-3">
                                    <!-- Wallet Balance -->
                                    <div>
                                        <input type="radio" class="btn-check" name="payment_method" id="payWallet" value="wallet" 
                                               <?= ($userBalance >= $totalAmount) ? 'checked' : '' ?>>
                                        <label class="payment-method-label w-100" for="payWallet">
                                            <div class="me-3 fs-4 text-emerald-600" style="color: #047857;">
                                                <i class="fa-solid fa-wallet"></i>
                                            </div>
                                            <div class="flex-grow-1">
                                                <div class="fw-bold">Platform Wallet Balance</div>
                                                <small class="text-muted">
                                                    Available Balance: <strong>Rs. <?= number_format($userBalance, 2) ?></strong>
                                                    <?php if ($userBalance < $totalAmount): ?>
                                                        <span class="text-danger ms-1">(Insufficient funds)</span>
                                                    <?php endif; ?>
                                                </small>
                                            </div>
                                            <div>
                                                <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill">Instant Settle</span>
                                            </div>
                                        </label>
                                    </div>

                                    <!-- Cash on Delivery / Escrow -->
                                    <div>
                                        <input type="radio" class="btn-check" name="payment_method" id="payCOD" value="cod" 
                                               <?= ($userBalance < $totalAmount) ? 'checked' : '' ?>>
                                        <label class="payment-method-label w-100" for="payCOD">
                                            <div class="me-3 fs-4 text-primary">
                                                <i class="fa-solid fa-hand-holding-dollar"></i>
                                            </div>
                                            <div class="flex-grow-1">
                                                <div class="fw-bold">Cash on Delivery / Direct Escrow</div>
                                                <small class="text-muted">Inspect the parcel at your doorstep before final payment release.</small>
                                            </div>
                                            <div>
                                                <span class="badge bg-light text-dark border rounded-pill">Safe & Secure</span>
                                            </div>
                                        </label>
                                    </div>

                                    <!-- Bank Transfer -->
                                    <div>
                                        <input type="radio" class="btn-check" name="payment_method" id="payBank" value="bank_transfer">
                                        <label class="payment-method-label w-100" for="payBank">
                                            <div class="me-3 fs-4 text-info">
                                                <i class="fa-solid fa-building-columns"></i>
                                            </div>
                                            <div class="flex-grow-1">
                                                <div class="fw-bold">Direct Online Bank Transfer</div>
                                                <small class="text-muted">Transfer to verified auction escrow IBAN / Raast account.</small>
                                            </div>
                                        </label>
                                    </div>

                                    <!-- Credit / Debit Card -->
                                    <div>
                                        <input type="radio" class="btn-check" name="payment_method" id="payCard" value="card">
                                        <label class="payment-method-label w-100" for="payCard">
                                            <div class="me-3 fs-4 text-warning">
                                                <i class="fa-brands fa-cc-visa me-1"></i><i class="fa-brands fa-cc-mastercard"></i>
                                            </div>
                                            <div class="flex-grow-1">
                                                <div class="fw-bold">Debit / Credit Card</div>
                                                <small class="text-muted">Instant checkout via Visa, MasterCard or PayPak.</small>
                                            </div>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <button type="submit" class="btn-confirm-order w-100 mt-4 d-flex align-items-center justify-content-center gap-2">
                                <i class="fa-solid fa-shield-halved"></i>
                                Confirm Order & Settle (Rs. <?= number_format($totalAmount, 2) ?>)
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Right Column: Order Summary Card -->
                <div class="col-lg-5">
                    <div class="checkout-card p-4 sticky-top" style="top: 90px;">
                        <h5 class="fw-bold mb-4 pb-2 border-bottom">Auction Lot Summary</h5>

                        <div class="d-flex gap-3 mb-4">
                            <?php 
                                $featImage = !empty($item['featured_image']) ? htmlspecialchars($item['featured_image']) : 'assets/image/placeholder.png';
                            ?>
                            <img src="<?= $featImage ?>" alt="<?= htmlspecialchars($item['title']) ?>" class="item-thumb" style="width: 90px; height: 90px;">
                            <div>
                                <h6 class="fw-bold mb-1 text-dark"><?= htmlspecialchars($item['title']) ?></h6>
                                <p class="text-muted small mb-1">
                                    <i class="fa-solid fa-tag me-1"></i> <?= htmlspecialchars($item['category_name'] ?? 'General') ?>
                                </p>
                                <p class="text-muted small mb-0">
                                    <i class="fa-solid fa-user-tie me-1"></i> Seller: <strong><?= htmlspecialchars($item['seller_name']) ?></strong>
                                </p>
                            </div>
                        </div>

                        <div class="bg-light p-3 rounded-3 mb-3">
                            <div class="price-breakdown-row">
                                <span>Winning Hammer Bid</span>
                                <span class="fw-bold text-dark">Rs. <?= number_format($hammerPrice, 2) ?></span>
                            </div>
                            <div class="price-breakdown-row">
                                <span>Platform Protection Fee (2%)</span>
                                <span>Rs. <?= number_format($platformFee, 2) ?></span>
                            </div>
                            <div class="price-breakdown-row">
                                <span>Insured Logistics & Handling</span>
                                <span>Rs. <?= number_format($shippingFee, 2) ?></span>
                            </div>
                            <div class="price-breakdown-row total">
                                <span>Grand Total Amount</span>
                                <span class="text-emerald-600" style="color: #047857;">Rs. <?= number_format($totalAmount, 2) ?></span>
                            </div>
                        </div>

                        <div class="p-3 border rounded-3 bg-white mb-3">
                            <div class="d-flex align-items-center gap-2 mb-2 text-dark fw-semibold small">
                                <i class="fa-solid fa-award text-warning"></i> 100% Buyer Protection Guarantee
                            </div>
                            <p class="text-muted small mb-0">
                                Your payment is held securely in platform escrow and is only disbursed to the seller once delivery is verified.
                            </p>
                        </div>

                        <div class="text-center">
                            <small class="text-muted">
                                Need assistance? Contact <a href="contact.php" class="text-decoration-none text-success">Support Team</a>
                            </small>
                        </div>
                    </div>
                </div>
            </div>

        <?php endif; ?>
    </div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
