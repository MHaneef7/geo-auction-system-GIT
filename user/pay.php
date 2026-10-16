<?php
/**
 * User Payment Portal - Geo Auction System
 * File: user/pay.php
 * 
 * Supports:
 * 1. Static / Manual Payment System (Bank Transfer, JazzCash, Easypaisa) with reference & receipt submission
 * 2. Instant Wallet Payment Settlement
 * 3. Real-time verification status tracking (Pending, Under Review, Verified, Rejected)
 */

include_once __DIR__ . '/../includes/header.php';

if (empty($_SESSION['user_id'])) {
    header("Location: ../login.php?redirect=user/won_auctions.php");
    exit();
}

$user_id  = (int)$_SESSION['user_id'];
$item_id  = isset($_GET['item_id']) ? (int)$_GET['item_id'] : 0;
$msg      = '';
$msg_type = 'success';

if (!$item_id) {
    header("Location: ../user/won_auctions.php");
    exit();
}

// Ensure PDO is available
if (!isset($pdo) || !($pdo instanceof PDO)) {
    require_once __DIR__ . '/../includes/database.php';
    $pdo = Database::getPdo();
}

// 1. Get item details
$itemStmt = $pdo->prepare("
    SELECT i.*, u.name AS seller_name, u.id AS seller_id_val, c.name AS category_name,
           (SELECT MAX(b.bid_amount) FROM bids b WHERE b.item_id = i.id) AS winning_bid
    FROM items i
    LEFT JOIN users u ON u.id = i.seller_id
    LEFT JOIN categories c ON c.id = i.category_id
    WHERE i.id = :item_id
");
$itemStmt->execute([':item_id' => $item_id]);
$item = $itemStmt->fetch(PDO::FETCH_ASSOC);

if (!$item) {
    header("Location: ../user/won_auctions.php");
    exit();
}

// 2. Verify this user is the highest bidder or designated winner
$winnerStmt = $pdo->prepare("
    SELECT user_id FROM bids
    WHERE item_id = :item_id
    ORDER BY bid_amount DESC
    LIMIT 1
");
$winnerStmt->execute([':item_id' => $item_id]);
$winner_row = $winnerStmt->fetch(PDO::FETCH_ASSOC);
$is_winner = ($winner_row && (int)$winner_row['user_id'] === $user_id) || ((int)($item['winner_id'] ?? 0) === $user_id);

if (!$is_winner) {
    header("Location: ../user/won_auctions.php");
    exit();
}

$due_amount = (float)($item['winning_bid'] > 0 ? $item['winning_bid'] : ($item['current_price'] > 0 ? $item['current_price'] : $item['starting_price']));

// 3. Check order record if exists
$ordStmt = $pdo->prepare("SELECT * FROM orders WHERE item_id = :item_id AND buyer_id = :buyer_id ORDER BY id DESC LIMIT 1");
$ordStmt->execute([':item_id' => $item_id, ':buyer_id' => $user_id]);
$order = $ordStmt->fetch(PDO::FETCH_ASSOC);

// 4. Check if already marked as completed / paid
$already_paid = false;
if ($order && in_array(strtoupper($order['payment_status']), ['PAID', 'AUTO_SETTLED'])) {
    $already_paid = true;
}

if (!$already_paid) {
    $paidStmt = $pdo->prepare("SELECT id, invoice_no FROM payments WHERE item_id = :item_id AND (user_id = :user_id OR buyer_id = :buyer_id) AND payment_status = 'completed' LIMIT 1");
    $paidStmt->execute([':item_id' => $item_id, ':user_id' => $user_id, ':buyer_id' => (string)$user_id]);
    if ($paidStmt->fetch(PDO::FETCH_ASSOC)) {
        $already_paid = true;
    }
}

// 5. Fetch enabled Manual Payment Methods
$methodsStmt = $pdo->query("SELECT * FROM payment_methods WHERE is_enabled = 1 ORDER BY sort_order ASC, id ASC");
$payment_methods = $methodsStmt ? $methodsStmt->fetchAll(PDO::FETCH_ASSOC) : [];

// 6. Fetch existing payment submissions for this item
$subStmt = $pdo->prepare("
    SELECT ps.*, pm.method_name, pm.provider_name 
    FROM payment_submissions ps
    LEFT JOIN payment_methods pm ON pm.id = ps.payment_method_id
    WHERE ps.item_id = :item_id AND ps.buyer_id = :buyer_id
    ORDER BY ps.id DESC LIMIT 1
");
$subStmt->execute([':item_id' => $item_id, ':buyer_id' => $user_id]);
$existing_submission = $subStmt->fetch(PDO::FETCH_ASSOC);

if ($existing_submission && $existing_submission['status'] === 'Verified') {
    $already_paid = true;
}

// 7. Get user wallet balance
$balStmt = $pdo->prepare("SELECT balance FROM users WHERE id = :user_id");
$balStmt->execute([':user_id' => $user_id]);
$balance = (float)($balStmt->fetch(PDO::FETCH_ASSOC)['balance'] ?? 0);

// =========================================================================
// ACTION A: SUBMIT MANUAL PAYMENT (Bank Transfer, JazzCash, Easypaisa)
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_manual_payment' && !$already_paid) {
    $method_id      = (int)($_POST['payment_method_id'] ?? 0);
    $reference_no   = trim($_POST['reference_number'] ?? '');
    $payment_date   = trim($_POST['payment_date'] ?? date('Y-m-d'));
    $sender_info    = trim($_POST['sender_account_info'] ?? '');
    $buyer_notes    = trim($_POST['buyer_notes'] ?? '');
    $submitted_amt  = (float)($_POST['amount_submitted'] ?? 0);

    // Find selected method
    $chosen_method = null;
    foreach ($payment_methods as $pm) {
        if ((int)$pm['id'] === $method_id) {
            $chosen_method = $pm;
            break;
        }
    }

    if (!$chosen_method) {
        $msg = 'Please select a valid payment method from the available options.';
        $msg_type = 'error';
    } elseif (empty($reference_no)) {
        $msg = 'Please enter your Transaction Reference Number / TID.';
        $msg_type = 'error';
    } elseif ($submitted_amt < $due_amount) {
        $msg = 'The submitted amount (Rs. ' . number_format($submitted_amt, 2) . ') cannot be less than the actual amount due (Rs. ' . number_format($due_amount, 2) . ').';
        $msg_type = 'error';
    } else {
        // Check for duplicate reference
        $dupStmt = $pdo->prepare("SELECT id, status FROM payment_submissions WHERE payment_method_id = :pm_id AND reference_number = :ref LIMIT 1");
        $dupStmt->execute([':pm_id' => $method_id, ':ref' => $reference_no]);
        $dupRow = $dupStmt->fetch(PDO::FETCH_ASSOC);

        if ($dupRow && $dupRow['status'] !== 'Rejected') {
            $msg = 'This transaction reference (' . htmlspecialchars($reference_no) . ') has already been submitted and is currently ' . htmlspecialchars($dupRow['status']) . '.';
            $msg_type = 'error';
        } else {
            // Handle optional receipt screenshot upload
            $receipt_path = null;
            if (!empty($_FILES['receipt']['name']) && $_FILES['receipt']['error'] === UPLOAD_ERR_OK) {
                $fileTmp   = $_FILES['receipt']['tmp_name'];
                $fileName  = $_FILES['receipt']['name'];
                $fileSize  = $_FILES['receipt']['size'];
                $ext       = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                $allowed   = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];

                if (!in_array($ext, $allowed)) {
                    $msg = 'Receipt must be an image (JPG, PNG, WEBP) or PDF file.';
                    $msg_type = 'error';
                } elseif ($fileSize > 5 * 1024 * 1024) {
                    $msg = 'Receipt file size exceeds 5MB limit.';
                    $msg_type = 'error';
                } else {
                    $destName = 'receipt_' . $item_id . '_' . $user_id . '_' . time() . '.' . $ext;
                    $destDir  = __DIR__ . '/../uploads/receipts/';
                    if (!is_dir($destDir)) {
                        @mkdir($destDir, 0755, true);
                    }
                    $targetPath = $destDir . $destName;
                    if (move_uploaded_file($fileTmp, $targetPath)) {
                        $receipt_path = 'uploads/receipts/' . $destName;
                    }
                }
            }

            if (empty($msg)) {
                try {
                    $order_id_val = $order ? (int)$order['id'] : null;
                    $seller_id_val = (int)$item['seller_id'];

                    $insStmt = $pdo->prepare("
                        INSERT INTO payment_submissions (
                            order_id, item_id, buyer_id, seller_id, payment_method_id, payment_method_code,
                            amount_due, amount_submitted, reference_number, payment_date,
                            sender_account_info, receipt_image, buyer_notes, status, created_at
                        ) VALUES (
                            :order_id, :item_id, :buyer_id, :seller_id, :pm_id, :pm_code,
                            :amount_due, :amount_sub, :ref_no, :p_date,
                            :sender_info, :receipt, :notes, 'Pending', NOW()
                        )
                    ");
                    $insStmt->execute([
                        ':order_id'    => $order_id_val,
                        ':item_id'     => $item_id,
                        ':buyer_id'    => $user_id,
                        ':seller_id'   => $seller_id_val,
                        ':pm_id'       => $method_id,
                        ':pm_code'     => $chosen_method['method_code'],
                        ':amount_due'  => $due_amount,
                        ':amount_sub'  => $submitted_amt,
                        ':ref_no'      => $reference_no,
                        ':p_date'      => $payment_date,
                        ':sender_info' => $sender_info ?: null,
                        ':receipt'     => $receipt_path,
                        ':notes'       => $buyer_notes ?: null
                    ]);

                    // Send notification to Admin & Buyer
                    try {
                        require_once __DIR__ . '/../includes/NotificationService.php';
                        $notifService = new NotificationService($pdo);
                        $notifService->createNotification(
                            $user_id,
                            'payment',
                            'Payment Submitted for Verification',
                            'Your payment of Rs. ' . number_format($submitted_amt, 2) . ' (Ref: ' . $reference_no . ') has been received and is pending verification.',
                            'user/pay.php?item_id=' . $item_id,
                            'fa-solid fa-clock'
                        );
                    } catch (\Throwable $ne) {}

                    $msg = 'Payment details submitted successfully! Your payment is now Under Review. Our verification team will review and confirm it shortly.';
                    $msg_type = 'success';

                    // Refresh existing submission record
                    $subStmt->execute([':item_id' => $item_id, ':buyer_id' => $user_id]);
                    $existing_submission = $subStmt->fetch(PDO::FETCH_ASSOC);

                } catch (\Throwable $e) {
                    $msg = 'Database error submitting payment: ' . $e->getMessage();
                    $msg_type = 'error';
                }
            }
        }
    }
}

// =========================================================================
// ACTION B: INSTANT WALLET SETTLEMENT
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'pay_via_wallet' && !$already_paid) {
    if ($balance < $due_amount) {
        $msg      = 'Insufficient wallet balance. Please top up your wallet or use a Manual Transfer below.';
        $msg_type = 'error';
    } else {
        try {
            $pdo->beginTransaction();

            $uLockStmt = $pdo->prepare("SELECT balance FROM users WHERE id = :id FOR UPDATE");
            $uLockStmt->execute([':id' => $user_id]);
            $currentBal = (float)($uLockStmt->fetch(PDO::FETCH_ASSOC)['balance'] ?? 0);

            if ($currentBal < $due_amount) {
                throw new Exception("Insufficient balance for this payment.");
            }

            $balAfter = $currentBal - $due_amount;

            // 1. Deduct balance
            $upBalStmt = $pdo->prepare("UPDATE users SET balance = balance - :amount WHERE id = :id");
            $upBalStmt->execute([':amount' => $due_amount, ':id' => $user_id]);

            // 2. Record transaction
            $txStmt = $pdo->prepare("
                INSERT INTO transactions (user_id, type, amount, balance_before, balance_after, item_id, description, status, created_at)
                VALUES (:user_id, 'winning_payment', :amount, :bal_before, :bal_after, :item_id, :desc, 'completed', NOW())
            ");
            $txStmt->execute([
                ':user_id'    => $user_id,
                ':amount'     => $due_amount,
                ':bal_before' => $currentBal,
                ':bal_after'  => $balAfter,
                ':item_id'    => $item_id,
                ':desc'       => 'Instant wallet payment for won lot: ' . ($item['title'] ?? '')
            ]);

            // 3. Record payment
            $invNo = 'INV-' . strtoupper(substr(uniqid(), -8));
            $payStmt = $pdo->prepare("
                INSERT INTO payments (invoice_no, user_id, buyer_id, item_id, amount, total_amount, currency, payment_method, payment_type, payment_status, paid_at, created_at)
                VALUES (:inv, :user_id, :buyer_id, :item_id, :amount, :total, 'PKR', 'wallet', 'auction_win', 'completed', NOW(), NOW())
            ");
            $payStmt->execute([
                ':inv'      => $invNo,
                ':user_id'  => $user_id,
                ':buyer_id' => (string)$user_id,
                ':item_id'  => $item_id,
                ':amount'   => $due_amount,
                ':total'    => $due_amount
            ]);

            // 4. Update order payment status
            if ($order) {
                $upOrdStmt = $pdo->prepare("UPDATE orders SET payment_status = 'PAID', updated_at = NOW() WHERE id = :oid");
                $upOrdStmt->execute([':oid' => $order['id']]);
            }

            // 5. Update item status to 'sold'
            $soldStmt = $pdo->prepare("UPDATE items SET status = 'sold', winner_id = :winner_id, updated_at = NOW() WHERE id = :id");
            $soldStmt->execute([':winner_id' => $user_id, ':id' => $item_id]);

            $pdo->commit();

            // 6. Notify seller
            try {
                require_once __DIR__ . '/../includes/NotificationService.php';
                $notifService = new NotificationService($pdo);
                $sellerId = (int)$item['seller_id'];
                if ($sellerId > 0) {
                    $notifService->createNotification(
                        $sellerId,
                        'payment',
                        'Payment Received',
                        'Payment of Rs. ' . number_format($due_amount, 2) . ' received for: ' . ($item['title'] ?? ''),
                        'user/my_auctions.php',
                        'fa-solid fa-money-bill-wave'
                    );
                }
            } catch (\Throwable $ne) {}

            $already_paid = true;
            $balance = $balAfter;
            $msg = 'Payment successful! Your order has been marked as PAID.';
            $msg_type = 'success';
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $msg = 'Payment processing error: ' . $e->getMessage();
            $msg_type = 'error';
        }
    }
}

// Clean item thumbnail
$item_img = 'assets/image/Geo-auction.png';
if (!empty($item['featured_image'])) {
    $item_img = cleanImagePath($item['featured_image']);
} elseif (!empty($item['images'])) {
    $p_imgs = parseItemImages($pdo, $item_id, $item['images'], '');
    if (!empty($p_imgs[0])) $item_img = $p_imgs[0];
}
if (strpos($item_img, '../') === 0) {
    $item_img = substr($item_img, 3);
}
?>

<div class="container py-4" style="max-width: 960px;">
    <!-- Breadcrumb / Back button -->
    <a href="../user/won_auctions.php" class="text-decoration-none text-muted d-inline-flex align-items-center gap-1 mb-3 small">
        <i class="fa-solid fa-arrow-left"></i> Back to Won Auctions
    </a>

    <!-- Page Header -->
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
        <div>
            <h1 class="h4 fw-bold text-slate-900 mb-1">Pay for Won Auction</h1>
            <p class="text-muted small mb-0">Select an approved manual payment method (Bank Transfer, JazzCash, Easypaisa) or pay via wallet.</p>
        </div>
        <span class="badge bg-emerald-50 text-emerald-700 border border-emerald-200 px-3 py-2 rounded-pill font-mono">
            Lot #<?php echo htmlspecialchars($item['auction_code'] ?? ('LOT-'.$item_id)); ?>
        </span>
    </div>

    <!-- Alert / Feedback message -->
    <?php if ($msg): ?>
        <div class="alert alert-<?php echo $msg_type === 'error' ? 'danger' : 'success'; ?> alert-dismissible fade show d-flex align-items-center gap-2 mb-4" role="alert">
            <i class="fa-solid fa-<?php echo $msg_type === 'error' ? 'circle-exclamation' : 'circle-check'; ?> fs-5"></i>
            <div><?php echo htmlspecialchars($msg); ?></div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Already Paid Card -->
    <?php if ($already_paid): ?>
        <div class="card border-0 shadow-sm rounded-4 text-center p-5 mb-4">
            <div class="mx-auto mb-3 d-flex align-items-center justify-content-center rounded-circle bg-emerald-50 text-emerald-600" style="width: 72px; height: 72px; font-size: 32px;">
                <i class="fa-solid fa-check"></i>
            </div>
            <h2 class="h4 fw-bold text-emerald-700 mb-2">Payment Completed & Verified!</h2>
            <p class="text-muted small mb-4">
                Payment of <strong>Rs. <?php echo number_format($due_amount, 2); ?></strong> for <strong><?php echo htmlspecialchars($item['title']); ?></strong> has been verified and settled.
            </p>
            <div class="d-flex justify-content-center gap-2 flex-wrap">
                <a href="../invoice.php?item_id=<?php echo $item_id; ?>&format=download" class="btn btn-success px-4 py-2 rounded-3">
                    <i class="fa-solid fa-file-pdf me-1"></i> Download Invoice PDF
                </a>
                <a href="../user/won_auctions.php" class="btn btn-outline-secondary px-4 py-2 rounded-3">
                    <i class="fa-solid fa-trophy me-1"></i> Won Auctions
                </a>
                <a href="../live_auction.php" class="btn btn-outline-secondary px-4 py-2 rounded-3">
                    <i class="fa-solid fa-gavel me-1"></i> Live Arena
                </a>
            </div>
        </div>
    <?php else: ?>

        <!-- Pending or Rejected Verification Status Banner -->
        <?php if ($existing_submission): ?>
            <?php if ($existing_submission['status'] === 'Pending' || $existing_submission['status'] === 'Under Review'): ?>
                <div class="card border-warning bg-warning bg-opacity-10 rounded-4 p-4 mb-4">
                    <div class="d-flex align-items-start gap-3">
                        <div class="text-warning fs-3 mt-1"><i class="fa-solid fa-clock-rotate-left"></i></div>
                        <div class="flex-grow-1">
                            <h5 class="fw-bold text-dark mb-1">Payment Verification Pending</h5>
                            <p class="text-secondary small mb-2">
                                You submitted a manual payment of <strong>Rs. <?php echo number_format($existing_submission['amount_submitted'], 2); ?></strong> 
                                via <strong><?php echo htmlspecialchars($existing_submission['method_name'] ?? $existing_submission['payment_method_code']); ?></strong> 
                                (Reference: <code><?php echo htmlspecialchars($existing_submission['reference_number']); ?></code>) on <?php echo date('M d, Y', strtotime($existing_submission['payment_date'])); ?>.
                            </p>
                            <span class="badge bg-warning text-dark px-3 py-1.5 rounded-pill">Status: <?php echo htmlspecialchars($existing_submission['status']); ?></span>
                            <span class="text-muted small ms-2">Our finance team is actively verifying this transfer with the bank statement.</span>
                        </div>
                    </div>
                </div>
            <?php elseif ($existing_submission['status'] === 'Rejected'): ?>
                <div class="card border-danger bg-danger bg-opacity-10 rounded-4 p-4 mb-4">
                    <div class="d-flex align-items-start gap-3">
                        <div class="text-danger fs-3 mt-1"><i class="fa-solid fa-triangle-exclamation"></i></div>
                        <div class="flex-grow-1">
                            <h5 class="fw-bold text-danger mb-1">Previous Submission Rejected</h5>
                            <p class="text-secondary small mb-2">
                                Your previous submission for Reference <code><?php echo htmlspecialchars($existing_submission['reference_number']); ?></code> was rejected.
                            </p>
                            <?php if (!empty($existing_submission['admin_notes'])): ?>
                                <div class="p-2.5 bg-white border border-danger border-opacity-25 rounded-3 text-danger small mb-2">
                                    <strong>Admin Reason:</strong> <?php echo htmlspecialchars($existing_submission['admin_notes']); ?>
                                </div>
                            <?php endif; ?>
                            <p class="text-muted small mb-0">Please verify your transaction details and resubmit below.</p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <!-- Two-column payment form -->
        <div class="row g-4">
            <!-- Left Column: Item Summary Card -->
            <div class="col-lg-5">
                <div class="card border-0 shadow-sm rounded-4 overflow-hidden sticky-top" style="top: 20px;">
                    <div class="position-relative bg-light" style="height: 200px;">
                        <img src="../<?php echo htmlspecialchars($item_img); ?>" 
                             alt="<?php echo htmlspecialchars($item['title']); ?>" 
                             class="w-full h-100 object-fit-cover"
                             onerror="this.onerror=null; this.src='../assets/image/Geo-auction.png';">
                        <span class="position-absolute top-2 start-2 badge bg-dark bg-opacity-75 text-white">
                            #<?php echo htmlspecialchars($item['auction_code'] ?? ('LOT-'.$item_id)); ?>
                        </span>
                    </div>
                    <div class="card-body p-4">
                        <span class="badge bg-emerald-50 text-emerald-700 text-uppercase mb-2" style="font-size: 10px;">
                            <?php echo htmlspecialchars($item['category_name'] ?? 'Auction Lot'); ?>
                        </span>
                        <h5 class="fw-bold text-slate-900 mb-2"><?php echo htmlspecialchars($item['title']); ?></h5>
                        <p class="text-muted small mb-3">
                            <?php echo htmlspecialchars(mb_substr(strip_tags($item['description'] ?? ''), 0, 110)) . '...'; ?>
                        </p>

                        <div class="border-top pt-3">
                            <div class="d-flex justify-content-between text-muted small mb-1">
                                <span>Seller</span>
                                <span class="fw-semibold text-dark"><?php echo htmlspecialchars($item['seller_name'] ?? 'Authorized Vendor'); ?></span>
                            </div>
                            <div class="d-flex justify-content-between text-muted small mb-2">
                                <span>Item Condition</span>
                                <span class="fw-semibold text-dark"><?php echo htmlspecialchars(ucfirst($item['condition_type'] ?? 'Standard')); ?></span>
                            </div>
                            <div class="d-flex justify-content-between align-items-baseline pt-2 border-top">
                                <span class="fw-bold text-slate-800">Total Payable Amount</span>
                                <span class="fs-4 fw-extrabold text-emerald-700 font-mono">
                                    Rs. <?php echo number_format($due_amount, 2); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column: Payment Options (Tabs) -->
            <div class="col-lg-7">
                <div class="card border-0 shadow-sm rounded-4 p-4">
                    <ul class="nav nav-pills mb-4 gap-2" id="paymentTab" role="tablist">
                        <li class="nav-item flex-grow-1" role="presentation">
                            <button class="nav-link active w-100 fw-bold py-2.5 rounded-3 text-center" 
                                    id="manual-tab" data-bs-toggle="pill" data-bs-target="#manual-pane" type="button" role="tab">
                                <i class="fa-solid fa-building-columns me-1.5"></i> Manual Transfer
                            </button>
                        </li>
                        <li class="nav-item flex-grow-1" role="presentation">
                            <button class="nav-link w-100 fw-bold py-2.5 rounded-3 text-center" 
                                    id="wallet-tab" data-bs-toggle="pill" data-bs-target="#wallet-pane" type="button" role="tab">
                                <i class="fa-solid fa-wallet me-1.5"></i> Pay from Wallet
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content" id="paymentTabContent">
                        
                        <!-- TAB 1: MANUAL TRANSFER (Bank Transfer, JazzCash, Easypaisa) -->
                        <div class="tab-pane fade show active" id="manual-pane" role="tabpanel">
                            <form method="POST" action="" enctype="multipart/form-data">
                                <input type="hidden" name="action" value="submit_manual_payment">
                                
                                <!-- Method Selection Cards -->
                                <div class="mb-3">
                                    <label class="form-label text-xs fw-bold text-slate-700 text-uppercase tracking-wider">
                                        1. Select Payment Method
                                    </label>
                                    <div class="row g-2">
                                        <?php if (!empty($payment_methods)): ?>
                                            <?php foreach ($payment_methods as $idx => $pm): ?>
                                                <div class="col-12 col-md-4">
                                                    <input type="radio" class="btn-check" name="payment_method_id" 
                                                           id="pm_<?php echo $pm['id']; ?>" value="<?php echo $pm['id']; ?>" 
                                                           <?php echo $idx === 0 ? 'checked' : ''; ?>
                                                           onchange="showMethodDetails(<?php echo htmlspecialchars(json_encode($pm), ENT_QUOTES, 'UTF-8'); ?>)">
                                                    <label class="btn btn-outline-secondary w-100 text-start p-2.5 rounded-3 d-flex align-items-center gap-2 h-100" 
                                                           for="pm_<?php echo $pm['id']; ?>">
                                                        <i class="fa-solid <?php 
                                                            if (strpos($pm['method_code'], 'bank') !== false) echo 'fa-building-columns text-primary';
                                                            elseif (strpos($pm['method_code'], 'jazz') !== false) echo 'fa-mobile-screen text-danger';
                                                            else echo 'fa-money-bill-transfer text-success';
                                                        ?> fs-5"></i>
                                                        <div class="text-truncate">
                                                            <div class="fw-bold small text-truncate"><?php echo htmlspecialchars($pm['method_name']); ?></div>
                                                            <div class="text-muted" style="font-size: 10px;"><?php echo htmlspecialchars($pm['provider_name']); ?></div>
                                                        </div>
                                                    </label>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <p class="text-muted small">No manual payment methods configured by administrator.</p>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Dynamic Account Details Box -->
                                <div id="methodDetailsBox" class="p-3 bg-slate-50 border border-slate-200 rounded-3 mb-4">
                                    <div class="d-flex align-items-center justify-content-between mb-2 pb-2 border-bottom">
                                        <span class="text-xs fw-bold text-slate-600 text-uppercase" id="dispProvider">Bank Details</span>
                                        <span class="badge bg-emerald-100 text-emerald-800 font-mono text-[11px]" id="dispCode">BANK_TRANSFER</span>
                                    </div>
                                    <div class="row g-2 small">
                                        <div class="col-sm-6">
                                            <span class="text-muted d-block" style="font-size: 11px;">Account Title:</span>
                                            <strong class="text-dark" id="dispTitle">Geo Auction Systems (Pvt) Ltd</strong>
                                        </div>
                                        <div class="col-sm-6">
                                            <span class="text-muted d-block" style="font-size: 11px;">Account No / IBAN:</span>
                                            <strong class="text-dark font-mono" id="dispNumber">PK36ALFH0012345678901234</strong>
                                        </div>
                                        <div class="col-12 mt-2 pt-2 border-top">
                                            <span class="text-muted d-block" style="font-size: 11px;">Transfer Instructions:</span>
                                            <span class="text-secondary small" id="dispInstructions">Please transfer exact winning amount and retain TID.</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Transaction Submission Form Fields -->
                                <div class="mb-3">
                                    <label class="form-label text-xs fw-bold text-slate-700 text-uppercase">
                                        2. Amount Transferred (Rs.) <span class="text-danger">*</span>
                                    </label>
                                    <input type="number" step="0.01" name="amount_submitted" class="form-control font-mono fw-bold" 
                                           value="<?php echo number_format($due_amount, 2, '.', ''); ?>" required readonly>
                                    <div class="form-text" style="font-size: 11px;">Must match the winning payable amount exactly.</div>
                                </div>

                                <div class="row g-3 mb-3">
                                    <div class="col-sm-6">
                                        <label class="form-label text-xs fw-bold text-slate-700 text-uppercase">
                                            Transaction Ref / TID <span class="text-danger">*</span>
                                        </label>
                                        <input type="text" name="reference_number" class="form-control" 
                                               placeholder="e.g. 12948201948 or Bank Ref" required>
                                    </div>
                                    <div class="col-sm-6">
                                        <label class="form-label text-xs fw-bold text-slate-700 text-uppercase">
                                            Payment Date <span class="text-danger">*</span>
                                        </label>
                                        <input type="date" name="payment_date" class="form-control" 
                                               value="<?php echo date('Y-m-d'); ?>" max="<?php echo date('Y-m-d'); ?>" required>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label text-xs fw-bold text-slate-700 text-uppercase">
                                        Sender Phone / Account Title (Optional)
                                    </label>
                                    <input type="text" name="sender_account_info" class="form-control" 
                                           placeholder="e.g. 0300-1234567 or Sender Account Name">
                                </div>

                                <div class="mb-3">
                                    <label class="form-label text-xs fw-bold text-slate-700 text-uppercase">
                                        Attach Payment Receipt / Screenshot
                                    </label>
                                    <input type="file" name="receipt" class="form-control" accept="image/*,application/pdf">
                                    <div class="form-text" style="font-size: 11px;">Supported: JPG, PNG, WEBP, PDF (Max 5MB).</div>
                                </div>

                                <div class="mb-4">
                                    <label class="form-label text-xs fw-bold text-slate-700 text-uppercase">
                                        Additional Notes (Optional)
                                    </label>
                                    <textarea name="buyer_notes" class="form-control" rows="2" placeholder="Any extra information for the finance verification team..."></textarea>
                                </div>

                                <button type="submit" class="btn btn-success w-100 py-2.5 fw-bold rounded-3 shadow-sm"
                                        onclick="return confirm('Confirm submission of this manual payment reference?');">
                                    <i class="fa-solid fa-paper-plane me-1.5"></i> Submit Payment for Verification
                                </button>
                            </form>
                        </div>

                        <!-- TAB 2: INSTANT WALLET SETTLEMENT -->
                        <div class="tab-pane fade" id="wallet-pane" role="tabpanel">
                            <div class="p-3 bg-slate-50 border border-slate-200 rounded-3 mb-3 d-flex align-items-center justify-content-between">
                                <div class="d-flex align-items-center gap-2.5">
                                    <div class="w-10 h-10 rounded-circle bg-emerald-100 text-emerald-700 d-flex align-items-center justify-content-center fs-5">
                                        <i class="fa-solid fa-wallet"></i>
                                    </div>
                                    <div>
                                        <span class="text-muted d-block" style="font-size: 11px;">Your Available Balance</span>
                                        <strong class="fs-5 font-mono text-emerald-700">Rs. <?php echo number_format($balance, 2); ?></strong>
                                    </div>
                                </div>
                                <a href="../user/wallet.php" class="btn btn-sm btn-outline-secondary">
                                    <i class="fa-solid fa-plus me-1"></i> Top Up
                                </a>
                            </div>

                            <?php if ($balance < $due_amount): ?>
                                <div class="alert alert-warning small d-flex align-items-center gap-2 mb-3">
                                    <i class="fa-solid fa-triangle-exclamation fs-5"></i>
                                    <div>
                                        Insufficient balance. You need <strong>Rs. <?php echo number_format($due_amount - $balance, 2); ?></strong> more to complete this payment instantly.
                                    </div>
                                </div>
                                <a href="../user/wallet.php" class="btn btn-primary w-100 py-2 fw-bold rounded-3 mb-2">
                                    <i class="fa-solid fa-circle-plus me-1"></i> Add Funds to Wallet
                                </a>
                                <p class="text-muted text-center small mb-0">Alternatively, use the <strong>Manual Transfer</strong> tab to pay via Bank, JazzCash, or Easypaisa directly.</p>
                            <?php else: ?>
                                <div class="border rounded-3 p-3 mb-3">
                                    <div class="d-flex justify-content-between small text-muted mb-1">
                                        <span>Payable Amount:</span>
                                        <strong class="text-dark">Rs. <?php echo number_format($due_amount, 2); ?></strong>
                                    </div>
                                    <div class="d-flex justify-content-between small text-muted">
                                        <span>Remaining Balance After:</span>
                                        <strong class="text-emerald-700">Rs. <?php echo number_format($balance - $due_amount, 2); ?></strong>
                                    </div>
                                </div>
                                <form method="POST" action="">
                                    <input type="hidden" name="action" value="pay_via_wallet">
                                    <button type="submit" class="btn btn-success w-100 py-2.5 fw-bold rounded-3 shadow-sm"
                                            onclick="return confirm('Deduct Rs. <?php echo number_format($due_amount, 2); ?> from your wallet to instantly settle this lot?');">
                                        <i class="fa-solid fa-bolt me-1.5"></i> Pay Rs. <?php echo number_format($due_amount, 2); ?> Now
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
    // Live Method Details Updater
    function showMethodDetails(method) {
        if (!method) return;
        var pEl = document.getElementById('dispProvider');
        var cEl = document.getElementById('dispCode');
        var tEl = document.getElementById('dispTitle');
        var nEl = document.getElementById('dispNumber');
        var iEl = document.getElementById('dispInstructions');

        if (pEl) pEl.textContent = method.provider_name || 'Payment Provider';
        if (cEl) cEl.textContent = (method.method_code || 'METHOD').toUpperCase();
        if (tEl) tEl.textContent = method.account_title || 'N/A';
        if (nEl) nEl.textContent = method.account_number || 'N/A';
        if (iEl) iEl.textContent = method.instructions || 'Transfer funds to the account above.';
    }

    // Initialize first method on page load
    document.addEventListener("DOMContentLoaded", function() {
        var firstRadio = document.querySelector('input[name="payment_method_id"]:checked');
        if (firstRadio) {
            firstRadio.dispatchEvent(new Event('change'));
        }
    });
</script>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>
