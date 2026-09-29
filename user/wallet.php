<?php
/**
 * User Wallet Dashboard - Geo Auction System
 *
 * Real-time Dynamic Wallet Management:
 * - Available, Locked (in active bids), and Total Balances
 * - Secure Top-up / Deposit processing
 * - Payout / Withdrawal requests with balance validation
 * - Active Bid Guarantees & Real Double-Entry Transaction Ledger
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/database.php';

// Authentication Check: Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php?redirect=user/wallet.php");
    exit();
}

$user_id   = (int)$_SESSION['user_id'];
$user_name = $_SESSION['user_name'] ?? ($_SESSION['name'] ?? 'User');
$role      = strtolower(trim($_SESSION['role'] ?? $_SESSION['user_role'] ?? 'buyer'));

// Initialize CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$success_msg = '';
$error_msg   = '';

// -------------------------------------------------------------
// POST Handling: Deposit & Withdrawal with CSRF Protection
// -------------------------------------------------------------
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $submitted_csrf = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $submitted_csrf)) {
        $error_msg = 'Security check failed: Invalid CSRF token. Please refresh the page and try again.';
    } else {
        $action = $_POST['action'] ?? '';

        // 1. Process Deposit / Top-up
        if ($action === 'deposit') {
            $deposit_amount = (float)($_POST['amount'] ?? 0);
            $payment_method = trim($_POST['payment_method'] ?? 'JazzCash');
            $tx_ref         = trim($_POST['transaction_ref'] ?? ('TXN-' . strtoupper(substr(uniqid(), -8))));

            if ($deposit_amount < 100) {
                $error_msg = 'Minimum top-up amount is Rs. 100.';
            } elseif ($deposit_amount > 5000000) {
                $error_msg = 'Maximum single top-up amount is Rs. 5,000,000.';
            } else {
                mysqli_begin_transaction($conn);
                try {
                    // Lock and fetch current balance from users table
                    $stmt = mysqli_prepare($conn, "SELECT balance FROM users WHERE id = ? FOR UPDATE");
                    mysqli_stmt_bind_param($stmt, "i", $user_id);
                    mysqli_stmt_execute($stmt);
                    $res = mysqli_stmt_get_result($stmt);
                    $u_row = mysqli_fetch_assoc($res);
                    mysqli_stmt_close($stmt);

                    $balance_before = (float)($u_row['balance'] ?? 0);
                    $balance_after  = $balance_before + $deposit_amount;

                    // Update user balance in users table
                    $up_stmt = mysqli_prepare($conn, "UPDATE users SET balance = ? WHERE id = ?");
                    mysqli_stmt_bind_param($up_stmt, "di", $balance_after, $user_id);
                    mysqli_stmt_execute($up_stmt);
                    mysqli_stmt_close($up_stmt);

                    // Sync with enterprise wallets table
                    $up_w = mysqli_prepare($conn, "
                        INSERT INTO wallets (user_id, currency, available_balance, locked_balance, status, updated_at)
                        VALUES (?, 'PKR', ?, 0.0000, 'ACTIVE', NOW())
                        ON DUPLICATE KEY UPDATE available_balance = ?, updated_at = NOW()
                    ");
                    if ($up_w) {
                        mysqli_stmt_bind_param($up_w, "idd", $user_id, $balance_after, $balance_after);
                        mysqli_stmt_execute($up_w);
                        mysqli_stmt_close($up_w);
                    }

                    $desc = "Wallet top-up via " . htmlspecialchars($payment_method) . " (Ref: " . htmlspecialchars($tx_ref) . ")";
                    $tx_stmt = mysqli_prepare($conn, "
                        INSERT INTO transactions 
                        (user_id, type, amount, balance_before, balance_after, reference_id, description, status, created_at) 
                        VALUES (?, 'deposit', ?, ?, ?, ?, ?, 'completed', NOW())
                    ");
                    mysqli_stmt_bind_param($tx_stmt, "idddss", $user_id, $deposit_amount, $balance_before, $balance_after, $tx_ref, $desc);
                    mysqli_stmt_execute($tx_stmt);
                    mysqli_stmt_close($tx_stmt);

                    mysqli_commit($conn);
                    $success_msg = "Successfully added Rs. " . number_format($deposit_amount, 2) . " to your wallet!";
                } catch (Exception $e) {
                    mysqli_rollback($conn);
                    $error_msg = "Deposit failed: " . $e->getMessage();
                }
            }
        }

        // 2. Process Withdrawal Request
        if ($action === 'withdraw') {
            $withdraw_amount = (float)($_POST['amount'] ?? 0);
            $bank_name       = trim($_POST['bank_name'] ?? '');
            $account_title   = trim($_POST['account_title'] ?? '');
            $account_number  = trim($_POST['account_number'] ?? '');

            if ($withdraw_amount < 500) {
                $error_msg = 'Minimum withdrawal amount is Rs. 500.';
            } elseif ($bank_name === '' || $account_title === '' || $account_number === '') {
                $error_msg = 'Please provide complete bank or mobile wallet account details.';
            } else {
                mysqli_begin_transaction($conn);
                try {
                    // Lock and verify available balance
                    $stmt = mysqli_prepare($conn, "SELECT balance FROM users WHERE id = ? FOR UPDATE");
                    mysqli_stmt_bind_param($stmt, "i", $user_id);
                    mysqli_stmt_execute($stmt);
                    $res = mysqli_stmt_get_result($stmt);
                    $u_row = mysqli_fetch_assoc($res);
                    mysqli_stmt_close($stmt);

                    $available_balance = (float)($u_row['balance'] ?? 0);

                    if ($withdraw_amount > $available_balance) {
                        mysqli_rollback($conn);
                        $error_msg = 'Insufficient available balance. You cannot withdraw more than your available funds.';
                    } else {
                        $balance_before = $available_balance;
                        $balance_after  = $balance_before - $withdraw_amount;

                        // Deduct balance in users table
                        $up_stmt = mysqli_prepare($conn, "UPDATE users SET balance = ? WHERE id = ?");
                        mysqli_stmt_bind_param($up_stmt, "di", $balance_after, $user_id);
                        mysqli_stmt_execute($up_stmt);
                        mysqli_stmt_close($up_stmt);

                        // Sync with enterprise wallets table
                        $up_w = mysqli_prepare($conn, "
                            UPDATE wallets 
                            SET available_balance = ?, updated_at = NOW() 
                            WHERE user_id = ?
                        ");
                        if ($up_w) {
                            mysqli_stmt_bind_param($up_w, "di", $balance_after, $user_id);
                            mysqli_stmt_execute($up_w);
                            mysqli_stmt_close($up_w);
                        }

                        // Insert into transactions as pending payout
                        $payout_ref = 'WD-' . strtoupper(substr(uniqid(), -8));
                        $desc = "Withdrawal to " . htmlspecialchars($bank_name) . " (" . htmlspecialchars($account_title) . " - " . htmlspecialchars($account_number) . ")";
                        
                        $tx_stmt = mysqli_prepare($conn, "
                            INSERT INTO transactions 
                            (user_id, type, amount, balance_before, balance_after, reference_id, description, status, created_at) 
                            VALUES (?, 'withdraw', ?, ?, ?, ?, ?, 'pending', NOW())
                        ");
                        $neg_amount = -$withdraw_amount;
                        mysqli_stmt_bind_param($tx_stmt, "idddss", $user_id, $neg_amount, $balance_before, $balance_after, $payout_ref, $desc);
                        mysqli_stmt_execute($tx_stmt);
                        mysqli_stmt_close($tx_stmt);

                        mysqli_commit($conn);
                        $success_msg = "Withdrawal request of Rs. " . number_format($withdraw_amount, 2) . " submitted successfully! Funds will be transferred following verification.";
                    }
                } catch (Exception $e) {
                    mysqli_rollback($conn);
                    $error_msg = "Withdrawal failed: " . $e->getMessage();
                }
            }
        }
    }
}

// -------------------------------------------------------------
// 1. Fetch Current Available Balance
// -------------------------------------------------------------
$stmt_bal = mysqli_prepare($conn, "SELECT balance FROM users WHERE id = ?");
mysqli_stmt_bind_param($stmt_bal, "i", $user_id);
mysqli_stmt_execute($stmt_bal);
$bal_res = mysqli_stmt_get_result($stmt_bal);
$available_balance = (float)(mysqli_fetch_assoc($bal_res)['balance'] ?? 0);
mysqli_stmt_close($stmt_bal);

// -------------------------------------------------------------
// 2. Fetch Active Bid Guarantees / Locked Funds
// Priority: Read directly from wallet_locks table, with fallback to active bids
// -------------------------------------------------------------
$active_locks = [];
$locked_balance = 0.00;

// Query wallet_locks table for active locks via wallets join
$locks_query = "
    SELECT 
        wl.id AS lock_id,
        wl.amount AS bid_amount,
        wl.created_at AS bid_time,
        i.id AS item_id,
        i.title,
        i.auction_code,
        i.end_time,
        i.current_price
    FROM wallet_locks wl
    JOIN wallets w ON wl.wallet_id = w.id
    JOIN items i ON wl.auction_id = i.id
    WHERE w.user_id = ?
      AND wl.status IN ('LOCKED', 'ACTIVE')
      AND i.status = 'active'
      AND (i.end_time IS NULL OR i.end_time > NOW())
    ORDER BY wl.created_at DESC
";
$stmt_locks = mysqli_prepare($conn, $locks_query);
if ($stmt_locks) {
    mysqli_stmt_bind_param($stmt_locks, "i", $user_id);
    mysqli_stmt_execute($stmt_locks);
    $locks_res = mysqli_stmt_get_result($stmt_locks);
    while ($lock = mysqli_fetch_assoc($locks_res)) {
        $active_locks[] = $lock;
        $locked_balance += (float)$lock['bid_amount'];
    }
    mysqli_stmt_close($stmt_locks);
}

// If no wallet_locks records exist, fallback to active winning bids
if (empty($active_locks)) {
    $fallback_bids_query = "
        SELECT 
            b.id AS bid_id,
            b.item_id,
            b.bid_amount,
            b.created_at AS bid_time,
            i.title,
            i.auction_code,
            i.end_time,
            i.current_price
        FROM bids b
        JOIN items i ON b.item_id = i.id
        WHERE b.user_id = ?
          AND i.status = 'active'
          AND (i.end_time IS NULL OR i.end_time > NOW())
          AND b.bid_amount = (SELECT MAX(b2.bid_amount) FROM bids b2 WHERE b2.item_id = i.id)
        ORDER BY b.bid_amount DESC
    ";
    $stmt_fb = mysqli_prepare($conn, $fallback_bids_query);
    if ($stmt_fb) {
        mysqli_stmt_bind_param($stmt_fb, "i", $user_id);
        mysqli_stmt_execute($stmt_fb);
        $fb_res = mysqli_stmt_get_result($stmt_fb);
        while ($fb = mysqli_fetch_assoc($fb_res)) {
            $active_locks[] = $fb;
            $locked_balance += (float)$fb['bid_amount'];
        }
        mysqli_stmt_close($stmt_fb);
    }
}

$total_balance = $available_balance + $locked_balance;

// -------------------------------------------------------------
// 3. Fetch Transaction History (Double-Entry Ledger)
// -------------------------------------------------------------
$history_query = "
    SELECT id, type, amount, balance_before, balance_after, reference_id, description, status, created_at
    FROM transactions
    WHERE user_id = ?
    ORDER BY created_at DESC
    LIMIT 50
";
$stmt_hist = mysqli_prepare($conn, $history_query);
mysqli_stmt_bind_param($stmt_hist, "i", $user_id);
mysqli_stmt_execute($stmt_hist);
$history_res = mysqli_stmt_get_result($stmt_hist);
$transactions = [];
while ($t = mysqli_fetch_assoc($history_res)) {
    $transactions[] = $t;
}
mysqli_stmt_close($stmt_hist);

include_once __DIR__ . '/../includes/header.php';
?>

<!-- Include Bootstrap 5 CSS & FontAwesome for complete, resilient component styling -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

<style>
/* Wallet Custom Styles aligned with Theme */
:root {
    --wallet-primary: #047857;
    --wallet-primary-dark: #065f46;
    --wallet-primary-light: #ecfdf5;
    --wallet-card-bg: var(--white, #ffffff);
    --wallet-border: var(--border, #e2e8f0);
    --wallet-text: var(--text, #0f172a);
    --wallet-text-muted: var(--text-muted, #64748b);
}

.wallet-container {
    max-width: 1140px;
    margin: 0 auto;
    padding: 24px 16px 60px;
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
    color: var(--wallet-text);
}

.wallet-card {
    background: var(--wallet-card-bg);
    border: 1px solid var(--wallet-border);
    border-radius: 14px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.04);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.wallet-balance-card-primary {
    background: linear-gradient(135deg, #047857 0%, #064e3b 100%);
    color: #ffffff !important;
    border: none;
    border-radius: 14px;
    box-shadow: 0 10px 25px -5px rgba(4, 120, 87, 0.3);
}

.wallet-btn-primary {
    background: linear-gradient(135deg, #047857 0%, #065f46 100%);
    color: #ffffff !important;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    padding: 10px 20px;
    box-shadow: 0 4px 12px rgba(4, 120, 87, 0.25);
    transition: 0.2s ease;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}
.wallet-btn-primary:hover {
    filter: brightness(1.08);
    transform: translateY(-1px);
}

.wallet-btn-outline {
    background: transparent;
    color: var(--wallet-primary) !important;
    border: 1.5px solid var(--wallet-primary);
    border-radius: 8px;
    font-weight: 600;
    padding: 9px 18px;
    transition: 0.2s ease;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}
.wallet-btn-outline:hover {
    background: var(--wallet-primary-light);
    color: var(--wallet-primary-dark) !important;
}

.amount-chip {
    background: #f1f5f9;
    border: 1px solid #cbd5e1;
    color: #334155;
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: 0.15s ease;
}
.amount-chip:hover {
    background: #e2e8f0;
    border-color: #94a3b8;
    color: #0f172a;
}

/* Custom Modal Backdrop & Box */
.wallet-modal-backdrop {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    background: rgba(15, 23, 42, 0.65);
    backdrop-filter: blur(4px);
    z-index: 1050;
    align-items: center;
    justify-content: center;
    padding: 20px;
    box-sizing: border-box;
}
.wallet-modal-backdrop.active {
    display: flex !important;
}
.wallet-modal-box {
    background: #ffffff;
    border-radius: 16px;
    max-width: 520px;
    width: 100%;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
    border: 1px solid #e2e8f0;
    animation: modalPop 0.2s ease-out;
}
@keyframes modalPop {
    from { opacity: 0; transform: scale(0.95); }
    to { opacity: 1; transform: scale(1); }
}

.wallet-badge-credit {
    background-color: #ecfdf5;
    color: #047857;
    border: 1px solid #a7f3d0;
    font-weight: 700;
}
.wallet-badge-debit {
    background-color: #fef2f2;
    color: #b91c1c;
    border: 1px solid #fecaca;
    font-weight: 700;
}

.wallet-back-btn {
    width: 42px;
    height: 42px;
    border-radius: 12px;
    background: #ffffff;
    border: 1px solid var(--wallet-border);
    color: var(--wallet-text);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.04);
    transition: all 0.2s ease;
    text-decoration: none;
    flex-shrink: 0;
}
.wallet-back-btn:hover {
    background: var(--wallet-primary-light);
    color: var(--wallet-primary);
    border-color: var(--wallet-primary);
    transform: translateX(-3px);
}
</style>

<div class="wallet-container">

    <!-- Top Header & Actions -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div class="d-flex align-items-center gap-3">
            <a href="javascript:history.back()" class="wallet-back-btn" title="Go Back" aria-label="Go back">
                <i class="fa-solid fa-arrow-left"></i>
            </a>
            <div>
                <div class="text-muted small mb-1">
                    <a href="dashboard.php" class="text-decoration-none text-muted">Dashboard</a> &rsaquo; <span class="text-dark fw-semibold">My Wallet</span>
                </div>
                <h1 class="h3 fw-bold text-dark mb-1 d-flex align-items-center gap-2">
                    <i class="fa-solid fa-wallet text-success"></i> My Wallet & Funds
                </h1>
                <p class="text-muted small mb-0">View real-time balances, manage instant top-ups, payout requests, and live bid holds.</p>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="d-flex gap-2">
            <button type="button" class="wallet-btn-primary" onclick="openDepositModal()">
                <i class="fa-solid fa-circle-plus"></i>
                <span>Add Funds</span>
            </button>
            <button type="button" class="wallet-btn-outline" onclick="openWithdrawModal()">
                <i class="fa-solid fa-money-bill-transfer"></i>
                <span>Withdraw</span>
            </button>
        </div>
    </div>

    <!-- Alert Notifications -->
    <?php if ($success_msg !== ''): ?>
        <div class="alert alert-success d-flex align-items-center gap-3 shadow-sm border-0 mb-4 rounded-3" style="background-color: #ecfdf5; border-left: 5px solid #10b981 !important; color: #065f46;">
            <i class="fa-solid fa-circle-check fs-4 text-success"></i>
            <div>
                <strong class="d-block">Transaction Successful!</strong>
                <span><?php echo htmlspecialchars($success_msg); ?></span>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($error_msg !== ''): ?>
        <div class="alert alert-danger d-flex align-items-center gap-3 shadow-sm border-0 mb-4 rounded-3" style="background-color: #fef2f2; border-left: 5px solid #ef4444 !important; color: #991b1b;">
            <i class="fa-solid fa-triangle-exclamation fs-4 text-danger"></i>
            <div>
                <strong class="d-block">Transaction Error:</strong>
                <span><?php echo htmlspecialchars($error_msg); ?></span>
            </div>
        </div>
    <?php endif; ?>

    <!-- 3 Core Balance Cards -->
    <div class="row g-3 mb-4">
        <!-- 1. Available Balance -->
        <div class="col-md-4">
            <div class="wallet-balance-card-primary p-4 h-100">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-uppercase fw-semibold" style="font-size: 11px; letter-spacing: 0.5px; opacity: 0.9;">Available Balance</span>
                    <span class="badge bg-white text-success rounded-pill px-2 py-1" style="font-size: 10px;">Free to Use</span>
                </div>
                <h2 class="h2 fw-bold mb-1">
                    Rs. <?php echo number_format($available_balance, 2); ?>
                </h2>
                <p class="small mb-0" style="opacity: 0.85;">Funds immediately available to place bids or withdraw.</p>
            </div>
        </div>

        <!-- 2. Active Bid Holds -->
        <div class="col-md-4">
            <div class="wallet-card p-4 h-100">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-uppercase text-muted fw-semibold" style="font-size: 11px; letter-spacing: 0.5px;">Active Bid Holds</span>
                    <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle rounded-pill px-2 py-1" style="font-size: 10px;">
                        <?php echo count($active_locks); ?> Active
                    </span>
                </div>
                <h2 class="h2 fw-bold text-dark mb-1">
                    Rs. <?php echo number_format($locked_balance, 2); ?>
                </h2>
                <p class="small text-muted mb-0">Reserved as active bid guarantee. Released instantly if outbid.</p>
            </div>
        </div>

        <!-- 3. Total Portfolio -->
        <div class="col-md-4">
            <div class="wallet-card p-4 h-100">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-uppercase text-muted fw-semibold" style="font-size: 11px; letter-spacing: 0.5px;">Total Portfolio</span>
                    <span class="badge bg-light text-dark border rounded-pill px-2 py-1" style="font-size: 10px;">PKR</span>
                </div>
                <h2 class="h2 fw-bold text-dark mb-1">
                    Rs. <?php echo number_format($total_balance, 2); ?>
                </h2>
                <p class="small text-muted mb-0">Total combined funds across available and active bids.</p>
            </div>
        </div>
    </div>

    <!-- Active Bid Guarantees (Locked Funds) Section -->
    <?php if (!empty($active_locks)): ?>
    <div class="wallet-card mb-4 overflow-hidden">
        <div class="p-3 px-4 border-bottom bg-light d-flex justify-content-between align-items-center">
            <h5 class="fw-bold text-dark mb-0 fs-6 d-flex align-items-center gap-2">
                <i class="fa-solid fa-lock text-warning"></i> Active Bid Guarantees (Locked Funds)
            </h5>
            <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle">
                <?php echo count($active_locks); ?> Auctions Active
            </span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" style="font-size: 13px;">
                <thead class="table-light text-muted">
                    <tr>
                        <th class="ps-4">Lot Code</th>
                        <th>Auction Item</th>
                        <th>Current Price</th>
                        <th>Your Active Bid</th>
                        <th>Auction Closes</th>
                        <th class="text-end pe-4">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($active_locks as $lock): ?>
                    <tr>
                        <td class="ps-4 fw-bold text-primary">#<?php echo htmlspecialchars($lock['auction_code']); ?></td>
                        <td>
                            <a href="../item.php?id=<?php echo (int)$lock['item_id']; ?>" class="text-dark fw-semibold text-decoration-none">
                                <?php echo htmlspecialchars($lock['title']); ?>
                            </a>
                        </td>
                        <td>Rs. <?php echo number_format((float)$lock['current_price'], 2); ?></td>
                        <td class="text-success fw-bold">Rs. <?php echo number_format((float)$lock['bid_amount'], 2); ?></td>
                        <td class="text-muted"><?php echo date('M d, Y H:i', strtotime($lock['end_time'])); ?></td>
                        <td class="text-end pe-4">
                            <a href="../item.php?id=<?php echo (int)$lock['item_id']; ?>" class="btn btn-sm btn-outline-success px-3" style="border-radius: 6px;">
                                View Lot
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- Transaction History (Double-Entry Ledger) -->
    <div class="wallet-card overflow-hidden">
        <div class="p-3 px-4 border-bottom bg-light">
            <h5 class="fw-bold text-dark mb-1 fs-6 d-flex align-items-center gap-2">
                <i class="fa-solid fa-clock-rotate-left text-primary"></i> Transaction Ledger
            </h5>
            <p class="text-muted small mb-0">Immutable record of all deposits, bid payments, releases, and payouts.</p>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" style="font-size: 13px;">
                <thead class="table-light text-muted">
                    <tr>
                        <th class="ps-4">Date & Time</th>
                        <th>Reference ID</th>
                        <th>Type</th>
                        <th>Description</th>
                        <th>Amount</th>
                        <th>Balance After</th>
                        <th class="text-end pe-4">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($transactions)): ?>
                        <?php foreach ($transactions as $t): 
                            $is_credit = ((float)$t['amount'] >= 0);
                            $type_clean = strtolower($t['type']);
                        ?>
                        <tr>
                            <td class="ps-4 text-muted" style="white-space: nowrap;">
                                <?php echo date('M d, Y h:i A', strtotime($t['created_at'])); ?>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border font-monospace px-2 py-1" style="font-size: 11px;">
                                    <?php echo htmlspecialchars($t['reference_id'] ?? ('TXN-' . $t['id'])); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($type_clean === 'deposit'): ?>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">Top-Up</span>
                                <?php elseif ($type_clean === 'withdraw'): ?>
                                    <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle px-2 py-1">Withdraw</span>
                                <?php elseif ($type_clean === 'bid' || $type_clean === 'bid_payment'): ?>
                                    <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle px-2 py-1">Bid Payment</span>
                                <?php elseif ($type_clean === 'refund'): ?>
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1">Refund</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary-subtle text-secondary border px-2 py-1"><?php echo htmlspecialchars(ucfirst($type_clean)); ?></span>
                                <?php endif; ?>
                            </td>
                            <td style="max-width: 280px;">
                                <?php echo htmlspecialchars($t['description'] ?? 'Wallet transaction'); ?>
                            </td>
                            <td class="fw-bold <?php echo $is_credit ? 'text-success' : 'text-danger'; ?>" style="white-space: nowrap;">
                                <?php echo $is_credit ? '+ Rs. ' : '- Rs. '; ?><?php echo number_format(abs((float)$t['amount']), 2); ?>
                            </td>
                            <td class="text-muted" style="white-space: nowrap;">
                                Rs. <?php echo number_format((float)$t['balance_after'], 2); ?>
                            </td>
                            <td class="text-end pe-4">
                                <?php if ($t['status'] === 'completed'): ?>
                                    <span class="badge bg-success text-white px-2 py-1 rounded-pill" style="font-size: 10px;">Completed</span>
                                <?php elseif ($t['status'] === 'pending'): ?>
                                    <span class="badge bg-warning text-dark px-2 py-1 rounded-pill" style="font-size: 10px;">Pending</span>
                                <?php else: ?>
                                    <span class="badge bg-danger text-white px-2 py-1 rounded-pill" style="font-size: 10px;"><?php echo htmlspecialchars(ucfirst($t['status'])); ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="fa-solid fa-receipt fs-2 mb-2 d-block text-secondary opacity-50"></i>
                                No transactions recorded yet. Top up your wallet to start participating in live auctions!
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<!-- Deposit Modal Backdrop & Content -->
<div id="depositModalBackdrop" class="wallet-modal-backdrop" onclick="handleBackdropClick(event, 'depositModalBackdrop')">
    <div class="wallet-modal-box">
        <div class="p-4 border-bottom d-flex justify-content-between align-items-center">
            <h5 class="fw-bold text-dark mb-0 fs-5 d-flex align-items-center gap-2">
                <i class="fa-solid fa-circle-plus text-success"></i> Add Funds to Wallet
            </h5>
            <button type="button" class="btn-close" onclick="closeDepositModal()"></button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="action" value="deposit">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
            <div class="p-4">
                <p class="text-muted small mb-3">Top up your balance instantly using your preferred local payment method.</p>

                <!-- Amount Field -->
                <div class="mb-3">
                    <label class="form-label fw-semibold small text-dark">Amount (PKR) <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text bg-light fw-bold text-muted">Rs.</span>
                        <input type="number" step="1" min="100" max="5000000" name="amount" id="depositAmountInput" class="form-control form-control-lg" placeholder="1000" required>
                    </div>
                    <!-- Quick Amount Chips -->
                    <div class="d-flex flex-wrap gap-2 mt-2">
                        <button type="button" class="amount-chip" onclick="addDepositValue(500)">+500</button>
                        <button type="button" class="amount-chip" onclick="addDepositValue(1000)">+1,000</button>
                        <button type="button" class="amount-chip" onclick="addDepositValue(5000)">+5,000</button>
                        <button type="button" class="amount-chip" onclick="addDepositValue(10000)">+10,000</button>
                        <button type="button" class="amount-chip" onclick="addDepositValue(50000)">+50,000</button>
                    </div>
                </div>

                <!-- Payment Channel -->
                <div class="mb-3">
                    <label class="form-label fw-semibold small text-dark">Payment Channel <span class="text-danger">*</span></label>
                    <select name="payment_method" class="form-select form-select-lg" required>
                        <option value="JazzCash">JazzCash (Instant Mobile Account)</option>
                        <option value="EasyPaisa">EasyPaisa (Instant Mobile Account)</option>
                        <option value="Bank Transfer">Online Bank Transfer (1Link / IBFT)</option>
                        <option value="Debit/Credit Card">Visa / MasterCard Debit/Credit Card</option>
                    </select>
                </div>

                <!-- Transaction Reference -->
                <div class="mb-3">
                    <label class="form-label fw-semibold small text-dark">Transaction Reference / Sender Mobile</label>
                    <input type="text" name="transaction_ref" class="form-control" placeholder="e.g. 03001234567 or Bank TID">
                    <div class="form-text">Optional for instant sandbox simulation.</div>
                </div>
            </div>
            <div class="p-3 px-4 border-top bg-light d-flex justify-content-end gap-2">
                <button type="button" class="btn btn-light px-3" onclick="closeDepositModal()">Cancel</button>
                <button type="submit" class="wallet-btn-primary">
                    <i class="fa-solid fa-check-circle"></i> Confirm Top-up
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Withdraw Modal Backdrop & Content -->
<div id="withdrawModalBackdrop" class="wallet-modal-backdrop" onclick="handleBackdropClick(event, 'withdrawModalBackdrop')">
    <div class="wallet-modal-box">
        <div class="p-4 border-bottom d-flex justify-content-between align-items-center">
            <h5 class="fw-bold text-dark mb-0 fs-5 d-flex align-items-center gap-2">
                <i class="fa-solid fa-money-bill-transfer text-primary"></i> Request Payout / Withdrawal
            </h5>
            <button type="button" class="btn-close" onclick="closeWithdrawModal()"></button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="action" value="withdraw">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
            <div class="p-4">
                <div class="p-3 rounded-3 mb-3 d-flex justify-content-between align-items-center" style="background-color: #f8fafc; border: 1px solid #e2e8f0;">
                    <span class="small text-muted">Available to Withdraw:</span>
                    <strong class="text-success fs-5">Rs. <?php echo number_format($available_balance, 2); ?></strong>
                </div>

                <!-- Withdrawal Amount -->
                <div class="mb-3">
                    <label class="form-label fw-semibold small text-dark">Withdrawal Amount (PKR) <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text bg-light fw-bold text-muted">Rs.</span>
                        <input type="number" step="1" min="500" max="<?php echo htmlspecialchars((string)max(0, $available_balance)); ?>" name="amount" class="form-control form-control-lg" placeholder="500" <?php echo $available_balance < 500 ? 'disabled' : ''; ?> required>
                    </div>
                    <div class="form-text <?php echo $available_balance < 500 ? 'text-danger fw-semibold' : ''; ?>">
                        <?php if ($available_balance < 500): ?>
                            <i class="fa-solid fa-triangle-exclamation"></i> You need at least Rs. 500 available balance to request a withdrawal.
                        <?php else: ?>
                            Minimum withdrawal amount is Rs. 500. Maximum available: Rs. <?php echo number_format($available_balance, 2); ?>.
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Bank Name -->
                <div class="mb-3">
                    <label class="form-label fw-semibold small text-dark">Bank / Mobile Wallet Name <span class="text-danger">*</span></label>
                    <input type="text" name="bank_name" class="form-control" placeholder="e.g. Meezan Bank, JazzCash, EasyPaisa, HBL" <?php echo $available_balance < 500 ? 'disabled' : ''; ?> required>
                </div>

                <!-- Account Title -->
                <div class="mb-3">
                    <label class="form-label fw-semibold small text-dark">Account Title / Beneficiary Name <span class="text-danger">*</span></label>
                    <input type="text" name="account_title" class="form-control" placeholder="e.g. Muhammad Haneef" <?php echo $available_balance < 500 ? 'disabled' : ''; ?> required>
                </div>

                <!-- Account Number / IBAN -->
                <div class="mb-3">
                    <label class="form-label fw-semibold small text-dark">Account Number / IBAN <span class="text-danger">*</span></label>
                    <input type="text" name="account_number" class="form-control" placeholder="e.g. PK36MEZN00012345678901 or 0306xxxxxxx" <?php echo $available_balance < 500 ? 'disabled' : ''; ?> required>
                </div>
            </div>
            <div class="p-3 px-4 border-top bg-light d-flex justify-content-end gap-2">
                <button type="button" class="btn btn-light px-3" onclick="closeWithdrawModal()">Cancel</button>
                <button type="submit" class="wallet-btn-primary" style="background: linear-gradient(135deg, #2563eb, #1d4ed8);" <?php echo $available_balance < 500 ? 'disabled style="opacity:0.6; cursor:not-allowed;"' : ''; ?>>
                    Submit Request
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openDepositModal() {
    document.getElementById('depositModalBackdrop').classList.add('active');
    document.body.style.overflow = 'hidden';
}
function closeDepositModal() {
    document.getElementById('depositModalBackdrop').classList.remove('active');
    document.body.style.overflow = '';
}

function openWithdrawModal() {
    document.getElementById('withdrawModalBackdrop').classList.add('active');
    document.body.style.overflow = 'hidden';
}
function closeWithdrawModal() {
    document.getElementById('withdrawModalBackdrop').classList.remove('active');
    document.body.style.overflow = '';
}

function handleBackdropClick(e, backdropId) {
    if (e.target.id === backdropId) {
        if (backdropId === 'depositModalBackdrop') closeDepositModal();
        if (backdropId === 'withdrawModalBackdrop') closeWithdrawModal();
    }
}

function addDepositValue(val) {
    const input = document.getElementById('depositAmountInput');
    const current = parseFloat(input.value) || 0;
    input.value = current + val;
}
</script>

<?php
include_once __DIR__ . '/../includes/footer.php';
?>