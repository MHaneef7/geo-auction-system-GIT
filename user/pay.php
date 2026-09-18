<?php
include_once '../includes/header.php';

$user_id  = (int)$_SESSION['user_id'];
$item_id  = isset($_GET['item_id']) ? (int)$_GET['item_id'] : 0;
$msg      = '';
$msg_type = 'success';

if (!$item_id) {
    header("Location: ../user/won_auctions.php");
    exit;
}

// Get item details + check user is the winner
$item_res = mysqli_query($conn, "
    SELECT i.*, u.name AS seller_name,
           (SELECT MAX(b.amount) FROM bids b WHERE b.item_id = i.id) AS winning_bid
    FROM items i
    LEFT JOIN users u ON u.id = i.seller_id
    WHERE i.id = $item_id AND i.status = 'ended'
");

if (!$item_res || mysqli_num_rows($item_res) === 0) {
    header("Location: ../user/won_auctions.php");
    exit;
}
$item = mysqli_fetch_assoc($item_res);

// Verify this user is the highest bidder
$winner_res = mysqli_query($conn, "
    SELECT user_id FROM bids
    WHERE item_id = $item_id
    ORDER BY amount DESC
    LIMIT 1
");
$winner_row = $winner_res ? mysqli_fetch_assoc($winner_res) : null;
if (!$winner_row || (int)$winner_row['user_id'] !== $user_id) {
    header("Location: ../user/won_auctions.php");
    exit;
}

// Check if already paid
$paid_res = mysqli_query($conn, "SELECT id, status FROM payments WHERE item_id = $item_id AND buyer_id = $user_id");
$paid_row = $paid_res ? mysqli_fetch_assoc($paid_res) : null;
$already_paid = ($paid_row && $paid_row['status'] === 'paid');

// Get user balance
$bal_res = mysqli_query($conn, "SELECT balance FROM users WHERE id = $user_id");
$balance = (float)(mysqli_fetch_assoc($bal_res)['balance'] ?? 0);

$due_amount = (float)$item['winning_bid'];

// Handle payment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pay']) && !$already_paid) {
    if ($balance < $due_amount) {
        $msg      = 'Insufficient wallet balance. Please top up your wallet.';
        $msg_type = 'error';
    } else {
        // Deduct balance
        $due_esc = mysqli_real_escape_string($conn, $due_amount);
        mysqli_query($conn, "UPDATE users SET balance = balance - '$due_esc' WHERE id = $user_id");

        // Record transaction
        $item_title_esc = mysqli_real_escape_string($conn, $item['title']);
        mysqli_query($conn, "INSERT INTO transactions (user_id, type, amount, description, item_id, created_at)
            VALUES ($user_id, 'bid_payment', '$due_esc', 'Payment for: $item_title_esc', $item_id, NOW())");

        // Record payment
        if ($paid_row) {
            mysqli_query($conn, "UPDATE payments SET status = 'paid', paid_at = NOW() WHERE id = {$paid_row['id']}");
        } else {
            mysqli_query($conn, "INSERT INTO payments (item_id, buyer_id, amount, status, paid_at, created_at)
                VALUES ($item_id, $user_id, '$due_esc', 'paid', NOW(), NOW())");
        }

        // Notify seller
        $seller_id = (int)$item['seller_id'];
        $notif_msg = "Payment of \$$due_esc received for: $item_title_esc";
        mysqli_query($conn, "INSERT INTO notifications (user_id, type, title, message, is_read, created_at)
            VALUES ($seller_id, 'payment', 'Payment Received', '$notif_msg', 0, NOW())");

        $already_paid = true;
        $msg = 'Payment successful! Thank you for your purchase.';

        // Refresh balance
        $bal_res = mysqli_query($conn, "SELECT balance FROM users WHERE id = $user_id");
        $balance = (float)(mysqli_fetch_assoc($bal_res)['balance'] ?? 0);
    }
}
?>

<div style="max-width:700px;margin:0 auto;">
    <a href="../user/won_auctions.php" style="display:inline-flex;align-items:center;gap:6px;font-size:13px;color:var(--text-muted);margin-bottom:24px;">
        <i class="fas fa-arrow-left"></i> Back to Won Auctions
    </a>

    <h1 style="font-size:22px;font-weight:800;margin-bottom:4px;">Complete Payment</h1>
    <p style="font-size:14px;color:var(--text-muted);margin-bottom:28px;">Finalise your winning bid payment using your wallet balance</p>

    <?php if ($msg): ?>
    <div class="alert alert-<?php echo $msg_type === 'error' ? 'error' : 'success'; ?>" style="margin-bottom:24px;">
        <i class="fas fa-<?php echo $msg_type === 'error' ? 'circle-xmark' : 'circle-check'; ?>"></i>
        <?php echo htmlspecialchars($msg); ?>
    </div>
    <?php endif; ?>

    <?php if ($already_paid): ?>
    <!-- Paid state -->
    <div class="card" style="border-radius:16px;text-align:center;padding:48px 28px;">
        <div style="width:72px;height:72px;border-radius:50%;background:var(--green-light);display:flex;align-items:center;justify-content:center;margin:0 auto 18px;font-size:32px;color:var(--green);">
            <i class="fas fa-check"></i>
        </div>
        <h2 style="font-size:22px;font-weight:800;color:var(--green);margin-bottom:8px;">Payment Complete!</h2>
        <p style="font-size:14px;color:var(--text-muted);margin-bottom:28px;">
            You've successfully paid <strong>$<?php echo number_format($due_amount, 2); ?></strong> for <strong><?php echo htmlspecialchars($item['title']); ?></strong>
        </p>
        <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap;">
            <a href="../user/won_auctions.php" class="btn btn-primary"><i class="fas fa-trophy"></i> My Won Auctions</a>
            <a href="../live_auction.php" class="btn btn-outline"><i class="fas fa-gavel"></i> Browse More</a>
        </div>
    </div>

    <?php else: ?>
    <!-- Payment form -->
    <div style="display:grid;grid-template-columns:1fr 280px;gap:20px;align-items:start;">

        <!-- Item summary -->
        <div class="card" style="border-radius:16px;">
            <div class="card-body">
                <h3 style="font-size:15px;font-weight:700;margin-bottom:14px;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.5px;font-size:11px;">Item Summary</h3>

                <?php if (!empty($item['image'])): ?>
                <img src="<?php echo htmlspecialchars($item['image']); ?>" alt=""
                     style="width:100%;aspect-ratio:16/9;object-fit:cover;border-radius:10px;margin-bottom:16px;">
                <?php else: ?>
                <div style="width:100%;aspect-ratio:16/9;background:var(--green-light);border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:48px;margin-bottom:16px;">🏷️</div>
                <?php endif; ?>

                <h2 style="font-size:18px;font-weight:800;margin-bottom:6px;"><?php echo htmlspecialchars($item['title']); ?></h2>
                <p style="font-size:13px;color:var(--text-muted);margin-bottom:16px;line-height:1.5;">
                    <?php echo htmlspecialchars(substr($item['description'] ?? '', 0, 120)) . '...'; ?>
                </p>
                <div style="border-top:1px solid var(--border);padding-top:14px;">
                    <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:8px;">
                        <span style="color:var(--text-muted);">Seller</span>
                        <span style="font-weight:600;"><?php echo htmlspecialchars($item['seller_name'] ?? 'N/A'); ?></span>
                    </div>
                    <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:8px;">
                        <span style="color:var(--text-muted);">Winning Bid</span>
                        <span style="font-weight:800;color:var(--green);font-size:16px;">$<?php echo number_format($due_amount, 2); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Payment panel -->
        <div>
            <div class="card" style="border-radius:16px;margin-bottom:14px;">
                <div class="card-body">
                    <h3 style="font-size:14px;font-weight:700;margin-bottom:16px;">Payment Method</h3>

                    <div style="display:flex;align-items:center;gap:10px;padding:12px;border:2px solid var(--green);border-radius:10px;background:var(--green-light);margin-bottom:16px;">
                        <i class="fas fa-wallet" style="font-size:20px;color:var(--green);"></i>
                        <div>
                            <div style="font-size:13px;font-weight:700;color:var(--green-dark);">Wallet Balance</div>
                            <div style="font-size:18px;font-weight:800;color:var(--green);">$<?php echo number_format($balance, 2); ?></div>
                        </div>
                    </div>

                    <?php if ($balance < $due_amount): ?>
                    <div class="alert alert-error" style="margin-bottom:14px;">
                        <i class="fas fa-triangle-exclamation"></i>
                        Insufficient funds. You need $<?php echo number_format($due_amount - $balance, 2); ?> more.
                    </div>
                    <a href="../user/wallet.php" class="btn btn-primary btn-block">
                        <i class="fas fa-plus"></i> Add Funds to Wallet
                    </a>
                    <?php else: ?>
                    <div style="border-top:1px solid var(--border);padding-top:14px;margin-bottom:14px;">
                        <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:6px;">
                            <span style="color:var(--text-muted);">Amount Due</span>
                            <span style="font-weight:800;font-size:16px;">$<?php echo number_format($due_amount, 2); ?></span>
                        </div>
                        <div style="display:flex;justify-content:space-between;font-size:13px;">
                            <span style="color:var(--text-muted);">Balance After</span>
                            <span style="font-weight:600;color:var(--text-muted);">$<?php echo number_format($balance - $due_amount, 2); ?></span>
                        </div>
                    </div>
                    <form method="POST">
                        <button type="submit" name="pay" value="1" class="btn btn-primary btn-block btn-lg"
                                onclick="return confirm('Pay $<?php echo number_format($due_amount, 2); ?> from wallet?')">
                            <i class="fas fa-bolt"></i> Pay Now
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card" style="border-radius:12px;background:var(--bg);border:none;">
                <div class="card-body" style="padding:14px;">
                    <i class="fas fa-shield-halved" style="color:var(--green);"></i>
                    <span style="font-size:12px;color:var(--text-muted);margin-left:6px;">Secure wallet payment — instant confirmation</span>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

</div><!-- /.main-content -->
<?php include_once '../includes/footer.php'; ?>
