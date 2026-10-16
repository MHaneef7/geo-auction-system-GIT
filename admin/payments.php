<?php
/**
 * Manual Payments Verification & Method Configuration - Admin Panel
 * File: admin/payments.php
 * 
 * Features:
 * - Review and verify manual payment submissions (Bank Transfer, JazzCash, Easypaisa)
 * - Inspect buyer, auction item, due amount, submitted reference, date, and receipt screenshot
 * - Approve & mark paid (updates orders, items, payments, notifies buyer and seller)
 * - Reject submission with mandatory administrative reason (notifies buyer to resubmit)
 * - Configure manual payment methods (Bank accounts, JazzCash, Easypaisa accounts, toggle active/inactive)
 * - Audit trail logging and duplicate reference prevention
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include_once __DIR__ . '/../includes/database.php';
include_once __DIR__ . '/../includes/auth.php';

require_admin('../index.php');

$current_admin_id = (int)($_SESSION['user_id'] ?? 0);
$msg      = '';
$msg_type = 'success'; // 'success' or 'danger'

// CSRF Token setup
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

function check_csrf_payment($token) {
    return !empty($token) && !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// -------------------------------------------------------------
// POST HANDLERS
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim($_POST['action'] ?? '');
    $posted_csrf = $_POST['csrf_token'] ?? '';

    if (!check_csrf_payment($posted_csrf)) {
        $msg = 'Invalid or expired CSRF session token. Please try again.';
        $msg_type = 'danger';
    } else {

        // 1. APPROVE SUBMISSION
        if ($action === 'approve_submission') {
            $sub_id = (int)($_POST['submission_id'] ?? 0);
            $admin_notes = trim($_POST['admin_notes'] ?? 'Verified by Finance Admin');

            $sStmt = $pdo->prepare("SELECT * FROM payment_submissions WHERE id = :id FOR UPDATE");
            $pdo->beginTransaction();
            try {
                $sStmt->execute([':id' => $sub_id]);
                $submission = $sStmt->fetch(PDO::FETCH_ASSOC);

                if (!$submission) {
                    throw new Exception("Payment submission record not found.");
                }

                if ($submission['status'] === 'Verified') {
                    throw new Exception("This payment has already been verified.");
                }

                $item_id   = (int)$submission['item_id'];
                $buyer_id  = (int)$submission['buyer_id'];
                $seller_id = (int)$submission['seller_id'];
                $amount    = (float)$submission['amount_submitted'];
                $ref_no    = $submission['reference_number'];
                $method    = $submission['payment_method_code'];

                // A. Update submission status
                $upSub = $pdo->prepare("
                    UPDATE payment_submissions 
                    SET status = 'Verified', admin_notes = :notes, reviewed_by = :admin_id, reviewed_at = NOW() 
                    WHERE id = :id
                ");
                $upSub->execute([
                    ':notes'    => $admin_notes,
                    ':admin_id' => $current_admin_id,
                    ':id'       => $sub_id
                ]);

                // B. Update or Insert Payments Table record
                $invNo = 'INV-' . strtoupper(substr(uniqid(), -8));
                $payStmt = $pdo->prepare("
                    INSERT INTO payments (
                        invoice_no, user_id, buyer_id, item_id, amount, total_amount, currency,
                        payment_method, payment_type, transaction_id, payment_status, paid_at, notes, created_at
                    ) VALUES (
                        :inv, :user_id, :buyer_id, :item_id, :amount, :total, 'PKR',
                        :method, 'auction_win', :trans_id, 'completed', NOW(), :notes, NOW()
                    )
                ");
                $payStmt->execute([
                    ':inv'      => $invNo,
                    ':user_id'  => $buyer_id,
                    ':buyer_id' => (string)$buyer_id,
                    ':item_id'  => $item_id,
                    ':amount'   => $amount,
                    ':total'    => $amount,
                    ':method'   => $method,
                    ':trans_id' => $ref_no,
                    ':notes'    => 'Manual transfer verified: ' . $admin_notes
                ]);

                // C. Update Order record if exists
                $upOrd = $pdo->prepare("UPDATE orders SET payment_status = 'PAID', updated_at = NOW() WHERE item_id = :item_id AND buyer_id = :buyer_id");
                $upOrd->execute([':item_id' => $item_id, ':buyer_id' => $buyer_id]);

                // D. Update Item status to sold
                $upItem = $pdo->prepare("UPDATE items SET status = 'sold', winner_id = :buyer_id, updated_at = NOW() WHERE id = :item_id");
                $upItem->execute([':buyer_id' => $buyer_id, ':item_id' => $item_id]);

                $pdo->commit();

                // E. Dispatch Notifications to Buyer and Seller
                try {
                    require_once __DIR__ . '/../includes/NotificationService.php';
                    $notifService = new NotificationService($pdo);

                    // To Buyer
                    $notifService->createNotification(
                        $buyer_id,
                        'payment',
                        'Payment Verified & Approved!',
                        'Your payment of Rs. ' . number_format($amount, 2) . ' for Lot #' . $item_id . ' (Ref: ' . $ref_no . ') has been approved by admin. Invoice is ready.',
                        'user/pay.php?item_id=' . $item_id,
                        'fa-solid fa-circle-check'
                    );

                    // To Seller
                    if ($seller_id > 0) {
                        $notifService->createNotification(
                            $seller_id,
                            'payment',
                            'Buyer Payment Confirmed',
                            'Payment of Rs. ' . number_format($amount, 2) . ' for your lot #' . $item_id . ' has been verified. You can now dispatch the item.',
                            'user/my_auctions.php',
                            'fa-solid fa-box-open'
                        );
                    }
                } catch (\Throwable $ne) {}

                $msg = 'Submission #' . $sub_id . ' verified successfully! Order marked as PAID and notifications sent.';
                $msg_type = 'success';
            } catch (\Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $msg = 'Error approving submission: ' . $e->getMessage();
                $msg_type = 'danger';
            }
        }

        // 2. REJECT SUBMISSION
        elseif ($action === 'reject_submission') {
            $sub_id = (int)($_POST['submission_id'] ?? 0);
            $reason = trim($_POST['rejection_reason'] ?? '');

            if (empty($reason)) {
                $msg = 'Please provide a clear administrative rejection reason for the buyer.';
                $msg_type = 'danger';
            } else {
                try {
                    $sStmt = $pdo->prepare("SELECT * FROM payment_submissions WHERE id = :id");
                    $sStmt->execute([':id' => $sub_id]);
                    $sub = $sStmt->fetch(PDO::FETCH_ASSOC);

                    if (!$sub) {
                        $msg = 'Submission record not found.';
                        $msg_type = 'danger';
                    } else {
                        $upStmt = $pdo->prepare("
                            UPDATE payment_submissions 
                            SET status = 'Rejected', admin_notes = :reason, reviewed_by = :admin_id, reviewed_at = NOW() 
                            WHERE id = :id
                        ");
                        $upStmt->execute([
                            ':reason'   => $reason,
                            ':admin_id' => $current_admin_id,
                            ':id'       => $sub_id
                        ]);

                        // Send rejection notification to buyer
                        try {
                            require_once __DIR__ . '/../includes/NotificationService.php';
                            $notifService = new NotificationService($pdo);
                            $notifService->createNotification(
                                (int)$sub['buyer_id'],
                                'payment',
                                'Payment Submission Rejected',
                                'Your payment submission for Lot #' . $sub['item_id'] . ' was rejected: ' . $reason . '. Please review and resubmit.',
                                'user/pay.php?item_id=' . $sub['item_id'],
                                'fa-solid fa-triangle-exclamation'
                            );
                        } catch (\Throwable $ne) {}

                        $msg = 'Submission #' . $sub_id . ' has been rejected with notice sent to the buyer.';
                        $msg_type = 'success';
                    }
                } catch (\Throwable $e) {
                    $msg = 'Database error: ' . $e->getMessage();
                    $msg_type = 'danger';
                }
            }
        }

        // 3. UPDATE / SAVE PAYMENT METHOD CONFIGURATION
        elseif ($action === 'save_payment_methods') {
            try {
                $methods_data = $_POST['methods'] ?? [];
                if (is_array($methods_data)) {
                    $upMethodStmt = $pdo->prepare("
                        UPDATE payment_methods 
                        SET method_name = :name, provider_name = :provider, account_title = :title, 
                            account_number = :acc_no, instructions = :instr, is_enabled = :enabled
                        WHERE id = :id
                    ");

                    foreach ($methods_data as $m_id => $m_data) {
                        $upMethodStmt->execute([
                            ':name'     => trim($m_data['method_name'] ?? ''),
                            ':provider' => trim($m_data['provider_name'] ?? ''),
                            ':title'    => trim($m_data['account_title'] ?? ''),
                            ':acc_no'   => trim($m_data['account_number'] ?? ''),
                            ':instr'    => trim($m_data['instructions'] ?? ''),
                            ':enabled'  => !empty($m_data['is_enabled']) ? 1 : 0,
                            ':id'       => (int)$m_id
                        ]);
                    }
                    $msg = 'Payment method configuration updated successfully!';
                    $msg_type = 'success';
                }
            } catch (\Throwable $e) {
                $msg = 'Failed to update payment methods: ' . $e->getMessage();
                $msg_type = 'danger';
            }
        }

        // 4. ADD NEW PAYMENT METHOD
        elseif ($action === 'add_payment_method') {
            $code     = strtolower(preg_replace('/[^a-z0-9_]/', '_', trim($_POST['method_code'] ?? '')));
            $name     = trim($_POST['method_name'] ?? '');
            $provider = trim($_POST['provider_name'] ?? '');
            $title    = trim($_POST['account_title'] ?? '');
            $acc_no   = trim($_POST['account_number'] ?? '');
            $instr    = trim($_POST['instructions'] ?? '');

            if (empty($code) || empty($name) || empty($title) || empty($acc_no)) {
                $msg = 'Method Code, Display Name, Account Title, and Account Number are required.';
                $msg_type = 'danger';
            } else {
                try {
                    $insMethod = $pdo->prepare("
                        INSERT INTO payment_methods (method_code, method_name, provider_name, account_title, account_number, instructions, is_enabled, sort_order)
                        VALUES (:code, :name, :provider, :title, :acc_no, :instr, 1, 10)
                    ");
                    $insMethod->execute([
                        ':code'     => $code,
                        ':name'     => $name,
                        ':provider' => $provider,
                        ':title'    => $title,
                        ':acc_no'   => $acc_no,
                        ':instr'    => $instr
                    ]);
                    $msg = 'New payment method added successfully!';
                    $msg_type = 'success';
                } catch (\Throwable $e) {
                    $msg = 'Failed to add method (may already exist): ' . $e->getMessage();
                    $msg_type = 'danger';
                }
            }
        }
    }
}

// -------------------------------------------------------------
// FETCH DATA FOR DISPLAY
// -------------------------------------------------------------
$tab = $_GET['tab'] ?? 'pending';

// Counts for badges
$cnt_pending = (int)$pdo->query("SELECT COUNT(*) FROM payment_submissions WHERE status IN ('Pending', 'Under Review')")->fetchColumn();
$cnt_verified = (int)$pdo->query("SELECT COUNT(*) FROM payment_submissions WHERE status = 'Verified'")->fetchColumn();
$cnt_rejected = (int)$pdo->query("SELECT COUNT(*) FROM payment_submissions WHERE status IN ('Rejected', 'Failed')")->fetchColumn();
$total_collected = (float)$pdo->query("SELECT COALESCE(SUM(amount_submitted), 0) FROM payment_submissions WHERE status = 'Verified'")->fetchColumn();

// Fetch Pending Submissions
$pendingStmt = $pdo->query("
    SELECT ps.*, pm.method_name, pm.provider_name,
           u_buyer.name AS buyer_name, u_buyer.email AS buyer_email, u_buyer.phone AS buyer_phone,
           u_seller.name AS seller_name,
           i.title AS item_title, i.auction_code
    FROM payment_submissions ps
    LEFT JOIN payment_methods pm ON pm.id = ps.payment_method_id
    LEFT JOIN users u_buyer ON u_buyer.id = ps.buyer_id
    LEFT JOIN users u_seller ON u_seller.id = ps.seller_id
    LEFT JOIN items i ON i.id = ps.item_id
    WHERE ps.status IN ('Pending', 'Under Review')
    ORDER BY ps.created_at ASC
");
$pending_submissions = $pendingStmt ? $pendingStmt->fetchAll(PDO::FETCH_ASSOC) : [];

// Fetch Verified Submissions
$verifiedStmt = $pdo->query("
    SELECT ps.*, pm.method_name,
           u_buyer.name AS buyer_name, u_buyer.email AS buyer_email,
           u_admin.name AS admin_name,
           i.title AS item_title, i.auction_code
    FROM payment_submissions ps
    LEFT JOIN payment_methods pm ON pm.id = ps.payment_method_id
    LEFT JOIN users u_buyer ON u_buyer.id = ps.buyer_id
    LEFT JOIN users u_admin ON u_admin.id = ps.reviewed_by
    LEFT JOIN items i ON i.id = ps.item_id
    WHERE ps.status = 'Verified'
    ORDER BY ps.reviewed_at DESC LIMIT 50
");
$verified_submissions = $verifiedStmt ? $verifiedStmt->fetchAll(PDO::FETCH_ASSOC) : [];

// Fetch Rejected Submissions
$rejectedStmt = $pdo->query("
    SELECT ps.*, pm.method_name,
           u_buyer.name AS buyer_name, u_buyer.email AS buyer_email,
           u_admin.name AS admin_name,
           i.title AS item_title, i.auction_code
    FROM payment_submissions ps
    LEFT JOIN payment_methods pm ON pm.id = ps.payment_method_id
    LEFT JOIN users u_buyer ON u_buyer.id = ps.buyer_id
    LEFT JOIN users u_admin ON u_admin.id = ps.reviewed_by
    LEFT JOIN items i ON i.id = ps.item_id
    WHERE ps.status IN ('Rejected', 'Failed')
    ORDER BY ps.reviewed_at DESC LIMIT 50
");
$rejected_submissions = $rejectedStmt ? $rejectedStmt->fetchAll(PDO::FETCH_ASSOC) : [];

// Fetch all payment methods for config tab
$all_methods = $pdo->query("SELECT * FROM payment_methods ORDER BY sort_order ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manual Payments Verification & Configuration - Geo Auction Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-green: #059669;
            --primary-dark: #0f172a;
            --sidebar-bg: #1e293b;
        }
        body { 
            font-family: 'Plus Jakarta Sans', sans-serif; 
            background-color: #f8fafc; 
            color: #1e293b;
        }
        .sidebar {
            height: 100vh;
            width: 250px;
            position: fixed;
            top: 0;
            left: 0;
            background-color: var(--sidebar-bg);
            padding-top: 20px;
            z-index: 100;
            overflow-y: auto;
        }
        .sidebar a {
            color: #94a3b8;
            text-decoration: none;
            padding: 12px 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: 0.2s;
            font-size: 14px;
            font-weight: 500;
        }
        .sidebar a:hover, .sidebar a.active {
            color: #fff;
            background-color: var(--primary-green);
        }
        .sidebar .nav-category {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #64748b;
            padding: 10px 20px;
            margin-top: 10px;
            font-weight: 700;
        }
        .main-content { 
            margin-left: 250px; 
            padding: 30px; 
        }
        .card-stat { 
            border: none; 
            border-radius: 12px; 
            box-shadow: 0 1px 3px rgba(0,0,0,0.05); 
            background: #fff;
        }
        .receipt-thumb {
            width: 48px;
            height: 48px;
            border-radius: 8px;
            object-fit: cover;
            cursor: pointer;
            border: 1px solid #e2e8f0;
            transition: transform 0.2s;
        }
        .receipt-thumb:hover {
            transform: scale(1.08);
        }
        @media(max-width: 768px) {
            .sidebar { width: 100%; height: auto; position: relative; }
            .main-content { margin-left: 0; padding: 15px; }
        }
    </style>
</head>
<body>

    <!-- Sidebar Navigation -->
    <div class="sidebar">
        <h3 class="text-center text-white mb-4"><i class="fas fa-gavel me-2"></i>AuctionAdmin</h3>
        <div class="nav-category">Core</div>
        <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
        <a href="../user/dashboard.php"><i class="fas fa-house"></i> User Portal</a>
        
        <div class="nav-category">Management</div>
        <a href="users.php"><i class="fas fa-users"></i> Users</a>
        <a href="categories.php"><i class="fas fa-tags"></i> Categories</a>
        <a href="items.php"><i class="fas fa-box-open"></i> Items</a>
        <a href="bids.php"><i class="fas fa-hand-holding-usd"></i> Bids</a>
        <a href="payments.php" class="active"><i class="fas fa-credit-card"></i> Payments</a>
        
        <div class="nav-category">System</div>
        <a href="settings.php"><i class="fas fa-sliders-h"></i> Platform Settings</a>
        <a href="setting.php"><i class="fas fa-database"></i> Database & Backup</a>
        <a href="reports.php"><i class="fas fa-chart-line"></i> Reports</a>
        <a href="logout.php" class="text-danger mt-4"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>

    <!-- Main Content Area -->
    <div class="main-content">
        
        <!-- Header -->
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
            <div>
                <h1 class="h3 fw-bold mb-1">Manual Payments &amp; Verifications</h1>
                <p class="text-muted small mb-0">Verify bank transfers, JazzCash, Easypaisa payments, and manage payment instructions.</p>
            </div>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-outline-success btn-sm rounded-3" data-bs-toggle="modal" data-bs-target="#addMethodModal">
                    <i class="fa-solid fa-plus me-1"></i> Add Payment Method
                </button>
            </div>
        </div>

        <!-- Alert messages -->
        <?php if ($msg): ?>
            <div class="alert alert-<?php echo $msg_type; ?> alert-dismissible fade show d-flex align-items-center gap-2 mb-4" role="alert">
                <i class="fa-solid fa-<?php echo $msg_type === 'danger' ? 'circle-exclamation' : 'circle-check'; ?> fs-5"></i>
                <div><?php echo htmlspecialchars($msg); ?></div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Stat Metric Cards -->
        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-xl-3">
                <div class="card card-stat p-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <span class="text-muted small d-block text-uppercase fw-bold" style="font-size: 11px;">Pending Verification</span>
                            <h3 class="fw-bold text-warning mb-0"><?php echo $cnt_pending; ?></h3>
                        </div>
                        <div class="p-3 bg-warning bg-opacity-10 rounded-circle text-warning fs-4">
                            <i class="fa-solid fa-clock-rotate-left"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card card-stat p-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <span class="text-muted small d-block text-uppercase fw-bold" style="font-size: 11px;">Verified Payments</span>
                            <h3 class="fw-bold text-success mb-0"><?php echo $cnt_verified; ?></h3>
                        </div>
                        <div class="p-3 bg-success bg-opacity-10 rounded-circle text-success fs-4">
                            <i class="fa-solid fa-circle-check"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card card-stat p-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <span class="text-muted small d-block text-uppercase fw-bold" style="font-size: 11px;">Rejected / Failed</span>
                            <h3 class="fw-bold text-danger mb-0"><?php echo $cnt_rejected; ?></h3>
                        </div>
                        <div class="p-3 bg-danger bg-opacity-10 rounded-circle text-danger fs-4">
                            <i class="fa-solid fa-circle-xmark"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card card-stat p-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <span class="text-muted small d-block text-uppercase fw-bold" style="font-size: 11px;">Total Verified Revenue</span>
                            <h3 class="fw-bold text-dark mb-0 font-mono" style="font-size: 18px;">Rs. <?php echo number_format($total_collected, 2); ?></h3>
                        </div>
                        <div class="p-3 bg-primary bg-opacity-10 rounded-circle text-primary fs-4">
                            <i class="fa-solid fa-money-bill-wave"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Navigation Tabs -->
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
            <div class="card-header bg-white border-bottom p-0">
                <ul class="nav nav-tabs border-0 px-3 pt-2" id="paymentAdminTabs" role="tablist">
                    <li class="nav-item">
                        <button class="nav-link <?php echo $tab === 'pending' ? 'active fw-bold text-success' : 'text-secondary'; ?> py-2.5 px-3" 
                                id="pending-tab" data-bs-toggle="tab" data-bs-target="#pending-pane" type="button">
                            <i class="fa-solid fa-hourglass-half me-1"></i> Pending Verifications
                            <?php if ($cnt_pending > 0): ?>
                                <span class="badge bg-warning text-dark ms-1.5 rounded-pill"><?php echo $cnt_pending; ?></span>
                            <?php endif; ?>
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link <?php echo $tab === 'verified' ? 'active fw-bold text-success' : 'text-secondary'; ?> py-2.5 px-3" 
                                id="verified-tab" data-bs-toggle="tab" data-bs-target="#verified-pane" type="button">
                            <i class="fa-solid fa-check-double me-1"></i> Verified History
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link <?php echo $tab === 'rejected' ? 'active fw-bold text-success' : 'text-secondary'; ?> py-2.5 px-3" 
                                id="rejected-tab" data-bs-toggle="tab" data-bs-target="#rejected-pane" type="button">
                            <i class="fa-solid fa-ban me-1"></i> Rejected Submissions
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link <?php echo $tab === 'methods' ? 'active fw-bold text-success' : 'text-secondary'; ?> py-2.5 px-3" 
                                id="methods-tab" data-bs-toggle="tab" data-bs-target="#methods-pane" type="button">
                            <i class="fa-solid fa-gears me-1"></i> Payment Methods Config
                        </button>
                    </li>
                </ul>
            </div>

            <div class="card-body p-0">
                <div class="tab-content" id="paymentAdminTabContent">

                    <!-- PANE 1: PENDING VERIFICATIONS -->
                    <div class="tab-pane fade <?php echo $tab === 'pending' ? 'show active' : ''; ?>" id="pending-pane">
                        <?php if (!empty($pending_submissions)): ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="bg-light text-muted small text-uppercase">
                                        <tr>
                                            <th class="ps-4">ID &amp; Date</th>
                                            <th>Buyer / Winner</th>
                                            <th>Auction Lot</th>
                                            <th>Method &amp; Ref #</th>
                                            <th>Amounts</th>
                                            <th>Receipt</th>
                                            <th class="pe-4 text-end">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-light">
                                        <?php foreach ($pending_submissions as $sub): 
                                            $is_amount_match = ((float)$sub['amount_submitted'] >= (float)$sub['amount_due']);
                                        ?>
                                            <tr>
                                                <td class="ps-4">
                                                    <span class="fw-bold font-mono text-dark">#<?php echo $sub['id']; ?></span>
                                                    <div class="text-muted" style="font-size: 11px;"><?php echo date('M d, Y', strtotime($sub['created_at'])); ?></div>
                                                </td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($sub['buyer_name'] ?? 'Buyer'); ?></strong>
                                                    <div class="text-muted" style="font-size: 11px;"><?php echo htmlspecialchars($sub['buyer_email'] ?? ''); ?></div>
                                                    <?php if (!empty($sub['sender_account_info'])): ?>
                                                        <span class="badge bg-light text-secondary border mt-1" style="font-size: 10px;">
                                                            Sender: <?php echo htmlspecialchars($sub['sender_account_info']); ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="fw-semibold text-truncate" style="max-width: 180px;" title="<?php echo htmlspecialchars($sub['item_title']); ?>">
                                                        <?php echo htmlspecialchars($sub['item_title']); ?>
                                                    </div>
                                                    <span class="badge bg-light text-dark font-mono" style="font-size: 10px;">
                                                        Lot #<?php echo htmlspecialchars($sub['auction_code'] ?? $sub['item_id']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-info bg-opacity-10 text-info fw-bold mb-1">
                                                        <?php echo htmlspecialchars($sub['method_name'] ?? $sub['payment_method_code']); ?>
                                                    </span>
                                                    <div>
                                                        <code class="fw-bold text-dark"><?php echo htmlspecialchars($sub['reference_number']); ?></code>
                                                    </div>
                                                    <span class="text-muted" style="font-size: 10px;">Paid on: <?php echo date('M d, Y', strtotime($sub['payment_date'])); ?></span>
                                                </td>
                                                <td>
                                                    <div class="small">Due: <strong>Rs. <?php echo number_format($sub['amount_due'], 2); ?></strong></div>
                                                    <div class="small <?php echo $is_amount_match ? 'text-success fw-bold' : 'text-danger fw-bold'; ?>">
                                                        Paid: Rs. <?php echo number_format($sub['amount_submitted'], 2); ?>
                                                        <?php if (!$is_amount_match): ?>
                                                            <i class="fa-solid fa-triangle-exclamation" title="Underpaid!"></i>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <?php if (!empty($sub['receipt_image'])): ?>
                                                        <img src="../<?php echo htmlspecialchars($sub['receipt_image']); ?>" 
                                                             class="receipt-thumb" 
                                                             onclick="openReceiptPreview('../<?php echo htmlspecialchars($sub['receipt_image']); ?>', '<?php echo htmlspecialchars($sub['reference_number']); ?>')"
                                                             title="Click to zoom receipt" 
                                                             alt="Receipt">
                                                    <?php else: ?>
                                                        <span class="text-muted small">No File</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="pe-4 text-end">
                                                    <div class="btn-group btn-group-sm">
                                                        <!-- Approve Button -->
                                                        <form method="POST" action="" class="d-inline" onsubmit="return confirm('Verify and settle Rs. <?php echo number_format($sub['amount_submitted'], 2); ?> for this lot?');">
                                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                                            <input type="hidden" name="action" value="approve_submission">
                                                            <input type="hidden" name="submission_id" value="<?php echo $sub['id']; ?>">
                                                            <button type="submit" class="btn btn-success btn-sm rounded-start">
                                                                <i class="fa-solid fa-check me-1"></i> Verify &amp; Settle
                                                            </button>
                                                        </form>

                                                        <!-- Reject Trigger Button -->
                                                        <button type="button" class="btn btn-outline-danger btn-sm rounded-end"
                                                                onclick="openRejectModal(<?php echo $sub['id']; ?>, '<?php echo htmlspecialchars(addslashes($sub['buyer_name'])); ?>', '<?php echo htmlspecialchars($sub['reference_number']); ?>')">
                                                            <i class="fa-solid fa-xmark me-1"></i> Reject
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5 text-muted">
                                <i class="fa-solid fa-clipboard-check fs-1 text-emerald-500 mb-2"></i>
                                <h6 class="fw-bold">No Pending Verifications</h6>
                                <p class="small mb-0">All submitted manual transfers have been verified.</p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- PANE 2: VERIFIED HISTORY -->
                    <div class="tab-pane fade <?php echo $tab === 'verified' ? 'show active' : ''; ?>" id="verified-pane">
                        <?php if (!empty($verified_submissions)): ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="bg-light text-muted small text-uppercase">
                                        <tr>
                                            <th class="ps-4">Sub ID</th>
                                            <th>Buyer</th>
                                            <th>Auction Item</th>
                                            <th>Method &amp; Ref</th>
                                            <th>Amount Verified</th>
                                            <th>Verified By &amp; When</th>
                                            <th class="pe-4">Receipt</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($verified_submissions as $v): ?>
                                            <tr>
                                                <td class="ps-4 fw-mono text-dark fw-bold">#<?php echo $v['id']; ?></td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($v['buyer_name'] ?? 'Buyer'); ?></strong>
                                                    <div class="text-muted small"><?php echo htmlspecialchars($v['buyer_email'] ?? ''); ?></div>
                                                </td>
                                                <td>
                                                    <div class="fw-semibold text-truncate" style="max-width: 200px;"><?php echo htmlspecialchars($v['item_title']); ?></div>
                                                    <span class="badge bg-light text-muted" style="font-size: 10px;">#<?php echo htmlspecialchars($v['auction_code'] ?? $v['item_id']); ?></span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-success bg-opacity-10 text-success"><?php echo htmlspecialchars($v['method_name'] ?? $v['payment_method_code']); ?></span>
                                                    <div class="small"><code><?php echo htmlspecialchars($v['reference_number']); ?></code></div>
                                                </td>
                                                <td>
                                                    <strong class="text-success font-mono">Rs. <?php echo number_format($v['amount_submitted'], 2); ?></strong>
                                                </td>
                                                <td>
                                                    <span class="badge bg-light text-dark"><i class="fa-solid fa-user-shield me-1"></i><?php echo htmlspecialchars($v['admin_name'] ?? 'Admin'); ?></span>
                                                    <div class="text-muted" style="font-size: 10px;"><?php echo date('M d, Y H:i', strtotime($v['reviewed_at'])); ?></div>
                                                </td>
                                                <td class="pe-4">
                                                    <?php if (!empty($v['receipt_image'])): ?>
                                                        <a href="../<?php echo htmlspecialchars($v['receipt_image']); ?>" target="_blank" class="btn btn-sm btn-outline-secondary">
                                                            <i class="fa-solid fa-eye"></i> View
                                                        </a>
                                                    <?php else: ?>
                                                        <span class="text-muted small">-</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5 text-muted">No verified records found.</div>
                        <?php endif; ?>
                    </div>

                    <!-- PANE 3: REJECTED SUBMISSIONS -->
                    <div class="tab-pane fade <?php echo $tab === 'rejected' ? 'show active' : ''; ?>" id="rejected-pane">
                        <?php if (!empty($rejected_submissions)): ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="bg-light text-muted small text-uppercase">
                                        <tr>
                                            <th class="ps-4">Sub ID</th>
                                            <th>Buyer</th>
                                            <th>Auction Item</th>
                                            <th>Ref Number</th>
                                            <th>Amount</th>
                                            <th>Rejection Reason</th>
                                            <th class="pe-4">Reviewed Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($rejected_submissions as $rej): ?>
                                            <tr>
                                                <td class="ps-4 fw-mono text-dark fw-bold">#<?php echo $rej['id']; ?></td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($rej['buyer_name'] ?? 'Buyer'); ?></strong>
                                                </td>
                                                <td><?php echo htmlspecialchars($rej['item_title']); ?></td>
                                                <td><code><?php echo htmlspecialchars($rej['reference_number']); ?></code></td>
                                                <td class="font-mono">Rs. <?php echo number_format($rej['amount_submitted'], 2); ?></td>
                                                <td>
                                                    <span class="text-danger small fw-semibold"><?php echo htmlspecialchars($rej['admin_notes'] ?? 'Unspecified'); ?></span>
                                                </td>
                                                <td class="pe-4 text-muted small">
                                                    <?php echo !empty($rej['reviewed_at']) ? date('M d, Y H:i', strtotime($rej['reviewed_at'])) : '-'; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5 text-muted">No rejected submissions found.</div>
                        <?php endif; ?>
                    </div>

                    <!-- PANE 4: PAYMENT METHODS CONFIGURATION -->
                    <div class="tab-pane fade <?php echo $tab === 'methods' ? 'show active' : ''; ?>" id="methods-pane">
                        <div class="p-4">
                            <form method="POST" action="">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                <input type="hidden" name="action" value="save_payment_methods">

                                <div class="row g-4 mb-4">
                                    <?php foreach ($all_methods as $m): ?>
                                        <div class="col-12 col-xl-4">
                                            <div class="card h-100 border rounded-4 p-3 <?php echo $m['is_enabled'] ? 'border-success border-opacity-25' : 'bg-light'; ?>">
                                                <div class="d-flex align-items-center justify-content-between mb-3">
                                                    <span class="badge bg-dark font-mono text-uppercase"><?php echo htmlspecialchars($m['method_code']); ?></span>
                                                    <div class="form-check form-switch mb-0">
                                                        <input class="form-check-input" type="checkbox" name="methods[<?php echo $m['id']; ?>][is_enabled]" value="1" <?php echo $m['is_enabled'] ? 'checked' : ''; ?> id="sw_<?php echo $m['id']; ?>">
                                                        <label class="form-check-label small fw-bold" for="sw_<?php echo $m['id']; ?>">Active</label>
                                                    </div>
                                                </div>

                                                <div class="mb-2">
                                                    <label class="form-label text-muted small mb-1">Display Method Name</label>
                                                    <input type="text" name="methods[<?php echo $m['id']; ?>][method_name]" class="form-control form-control-sm" value="<?php echo htmlspecialchars($m['method_name']); ?>" required>
                                                </div>

                                                <div class="mb-2">
                                                    <label class="form-label text-muted small mb-1">Bank / Provider</label>
                                                    <input type="text" name="methods[<?php echo $m['id']; ?>][provider_name]" class="form-control form-control-sm" value="<?php echo htmlspecialchars($m['provider_name']); ?>" required>
                                                </div>

                                                <div class="mb-2">
                                                    <label class="form-label text-muted small mb-1">Account Title</label>
                                                    <input type="text" name="methods[<?php echo $m['id']; ?>][account_title]" class="form-control form-control-sm" value="<?php echo htmlspecialchars($m['account_title']); ?>" required>
                                                </div>

                                                <div class="mb-2">
                                                    <label class="form-label text-muted small mb-1">Account No. / IBAN</label>
                                                    <input type="text" name="methods[<?php echo $m['id']; ?>][account_number]" class="form-control form-control-sm font-mono" value="<?php echo htmlspecialchars($m['account_number']); ?>" required>
                                                </div>

                                                <div class="mb-0">
                                                    <label class="form-label text-muted small mb-1">Transfer Instructions</label>
                                                    <textarea name="methods[<?php echo $m['id']; ?>][instructions]" class="form-control form-control-sm" rows="3"><?php echo htmlspecialchars($m['instructions']); ?></textarea>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>

                                <button type="submit" class="btn btn-success px-4 py-2 fw-bold rounded-3 shadow-sm">
                                    <i class="fa-solid fa-floppy-disk me-1.5"></i> Save Payment Methods Configuration
                                </button>
                            </form>
                        </div>
                    </div>

                </div>
            </div>
        </div>

    </div>

    <!-- Lightbox Receipt Image Modal -->
    <div class="modal fade" id="receiptModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 rounded-4 overflow-hidden shadow-2xl">
                <div class="modal-header bg-dark text-white py-2.5 px-4">
                    <h6 class="modal-title fw-bold mb-0">Payment Receipt Preview (Ref: <span id="modalRefTitle"></span>)</h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-2 text-center bg-black">
                    <img id="modalReceiptImg" src="" alt="Receipt" class="img-fluid rounded" style="max-height: 80vh;">
                </div>
                <div class="modal-footer py-2 px-4 bg-light">
                    <a id="modalReceiptDownload" href="" download class="btn btn-sm btn-outline-primary">
                        <i class="fa-solid fa-download me-1"></i> Download Original
                    </a>
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Administrative Rejection Reason Modal -->
    <div class="modal fade" id="rejectModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form method="POST" action="" class="modal-content border-0 rounded-4 shadow">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <input type="hidden" name="action" value="reject_submission">
                <input type="hidden" name="submission_id" id="rejectSubId" value="">

                <div class="modal-header bg-danger text-white py-2.5 px-4">
                    <h6 class="modal-title fw-bold mb-0">Reject Payment Submission</h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <p class="small text-muted mb-3">
                        Rejecting payment for buyer <strong id="rejectBuyerName"></strong> (Ref: <code id="rejectRefCode"></code>). Please state the exact reason so the buyer can correct it and resubmit.
                    </p>
                    <div class="mb-3">
                        <label class="form-label text-xs fw-bold text-slate-700 text-uppercase">Rejection Reason <span class="text-danger">*</span></label>
                        <select class="form-select form-select-sm mb-2" onchange="if(this.value) document.getElementById('rejectReasonText').value = this.value;">
                            <option value="">-- Choose Common Reason --</option>
                            <option value="TID not found in bank statement. Please verify reference number.">TID not found in bank statement</option>
                            <option value="Amount paid does not match the required winning lot amount.">Amount mismatch</option>
                            <option value="Receipt screenshot is unreadable or blurred. Please re-upload clear receipt.">Blurred / Unreadable Receipt</option>
                            <option value="Duplicate transaction reference detected.">Duplicate reference</option>
                        </select>
                        <textarea name="rejection_reason" id="rejectReasonText" class="form-control" rows="3" placeholder="Provide specific reason..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer py-2 px-4 bg-light">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-danger fw-bold">
                        <i class="fa-solid fa-ban me-1"></i> Confirm Rejection
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Add Custom Payment Method Modal -->
    <div class="modal fade" id="addMethodModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form method="POST" action="" class="modal-content border-0 rounded-4 shadow">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <input type="hidden" name="action" value="add_payment_method">

                <div class="modal-header bg-dark text-white py-2.5 px-4">
                    <h6 class="modal-title fw-bold mb-0">Add Manual Payment Method</h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-2">
                        <label class="form-label small text-muted mb-1">Unique Code (e.g. meezan_bank, nayapay)</label>
                        <input type="text" name="method_code" class="form-control form-control-sm font-mono" placeholder="e.g. meezan_bank" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small text-muted mb-1">Display Method Name</label>
                        <input type="text" name="method_name" class="form-control form-control-sm" placeholder="e.g. Meezan Bank Direct" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small text-muted mb-1">Bank / Provider</label>
                        <input type="text" name="provider_name" class="form-control form-control-sm" placeholder="e.g. Meezan Bank Limited" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small text-muted mb-1">Account Title</label>
                        <input type="text" name="account_title" class="form-control form-control-sm" placeholder="e.g. Geo Auction Systems" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small text-muted mb-1">Account Number / IBAN</label>
                        <input type="text" name="account_number" class="form-control form-control-sm font-mono" placeholder="e.g. PK82MEZN0012345678901234" required>
                    </div>
                    <div class="mb-0">
                        <label class="form-label small text-muted mb-1">Payment Instructions</label>
                        <textarea name="instructions" class="form-control form-control-sm" rows="3" placeholder="Instructions for buyer when transferring..."></textarea>
                    </div>
                </div>
                <div class="modal-footer py-2 px-4 bg-light">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-success fw-bold">
                        <i class="fa-solid fa-plus me-1"></i> Add Method
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function openReceiptPreview(src, ref) {
            document.getElementById('modalReceiptImg').src = src;
            document.getElementById('modalReceiptDownload').href = src;
            document.getElementById('modalRefTitle').textContent = ref;
            new bootstrap.Modal(document.getElementById('receiptModal')).show();
        }

        function openRejectModal(subId, buyerName, refCode) {
            document.getElementById('rejectSubId').value = subId;
            document.getElementById('rejectBuyerName').textContent = buyerName;
            document.getElementById('rejectRefCode').textContent = refCode;
            document.getElementById('rejectReasonText').value = '';
            new bootstrap.Modal(document.getElementById('rejectModal')).show();
        }
    </script>
</body>
</html>
