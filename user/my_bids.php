<?php
session_start();
include_once __DIR__ . "/../includes/database.php";

if ($conn->connect_error) {
    die("Database Connection Failed: " . $conn->connect_error);
}

/*
|--------------------------------------------------------------------------
| Check Login
|--------------------------------------------------------------------------
*/
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$user_id = (int) $_SESSION['user_id'];

/*
|--------------------------------------------------------------------------
| Get User Bids (Updated with schema columns)
|--------------------------------------------------------------------------
*/
$sql = "
    SELECT 
        b.id,
        b.item_id,
        b.bid_amount AS bid_amount,
        b.is_winning,
        b.bid_status,
        b.created_at,
        i.title,
        i.current_price
    FROM bids AS b
    INNER JOIN items AS i ON b.item_id = i.id
    WHERE b.user_id = $user_id
    ORDER BY b.created_at DESC
";

$bids = mysqli_query($conn, $sql);

if (!$bids) {
    die("Query Error: " . mysqli_error($conn));
}
include_once __DIR__ . "/../includes/header.php";
?>

<style>
:root {
    --bg: #f8fafc;
    --bg-white: #ffffff;
    --text: #0f172a;
    --text-muted: #64748b;
    --border: #e2e8f0;
    --border-light: #f1f5f9;
    --green: #10b981;
    --green-light: #ecfdf5;
    --green-dark: #065f46;
    --green-border: #a7f3d0;
    --red: #ef4444;
    --red-light: #fef2f2;
    --red-dark: #991b1b;
    --blue: #2563eb;
    --gold: #f59e0b;
    --gold-light: #fffbeb;
    --gray-light: #f8fafc;
    --shadow: 0 4px 6px -1px rgba(0,0,0,0.05), 0 2px 4px -1px rgba(0,0,0,0.03);
    --radius-sm: 6px;
    --radius: 10px;
    --radius-lg: 14px;
    --font: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body {
    font-family: var(--font);
    background: var(--bg);
    color: var(--text);
    font-size: 14px;
    line-height: 1.5;
}

.main-content {
    max-width: 1000px;
    margin: 40px auto;
    padding: 0 20px 50px;
}

.card {
    background: var(--bg-white);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow);
    overflow: hidden;
    padding: 16px;
    margin-left: 120px;
    margin-top: 30px;
}

.page-header {
    margin-bottom: 24px;
}
.page-header h2 {
    font-size: 22px;
    font-weight: 700;
    color: var(--text);
    display: flex;
    align-items: center;
    gap: 10px;
}
.page-header h2 i { color: #10b981; }

.table-wrap {
    width: 100%;
    overflow-x: auto;
}

.tx-table {
    width: 100%;
    border-collapse: collapse;
    text-align: left;
}

.tx-table thead th {
    padding: 14px 16px;
    font-size: 11px;
    font-weight: 700;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    background: var(--gray-light);
    border-bottom: 1px solid var(--border);
}

.tx-table tbody td {
    padding: 16px;
    font-size: 13px;
    border-bottom: 1px solid var(--border-light);
    vertical-align: middle;
}

.tx-table tbody tr {
    transition: background-color 0.15s ease;
}
.tx-table tbody tr:hover {
    background: #f8fafc;
}
.tx-table tbody tr:last-child td {
    border-bottom: none;
}

.td-item-title a {
    font-weight: 600;
    color: var(--text);
    text-decoration: none;
}
.td-item-title a:hover {
    color: #10b981;
}

.td-amount {
    font-weight: 700;
    color: var(--text);
}

.badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.02em;
}
.badge-green {
    background: var(--green-light);
    color: var(--green-dark);
    border: 1px solid var(--green-border);
}
.badge-red {
    background: var(--red-light);
    color: var(--red-dark);
    border: 1px solid #fecaca;
}

.td-date {
    color: var(--text-muted);
    font-size: 12px;
}

.empty-state {
    text-align: center;
    padding: 40px 20px;
    color: var(--text-muted);
}
</style>

<div class="main-content">
    <div class="card">
        <div class="page-header">
            <h2><i class="fas fa-gavel"></i> My Bid History</h2>
        </div>

        <div class="table-wrap">
            <table class="tx-table">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Your Bid</th>
                        <th>Current Price</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (mysqli_num_rows($bids) > 0): ?>
                    <?php while ($row = mysqli_fetch_assoc($bids)): ?>
                        <?php 
                            // Database ke 'is_winning' field ya current price comparison ko use kiya gaya hai
                            $is_winning = ($row['is_winning'] == 1 || (float)$row['bid_amount'] >= (float)$row['current_price']);
                        ?>
                        <tr>
                            <td class="td-item-title">
                                <a href="../item.php?id=<?php echo (int)$row['item_id']; ?>">
                                    <?php echo htmlspecialchars($row['title'] ?? 'Item #' . $row['item_id']); ?>
                                </a>
                            </td>
                            <td class="td-amount">
                                $<?php echo number_format((float)$row['bid_amount'], 2); ?>
                            </td>
                            <td>
                                $<?php echo number_format((float)$row['current_price'], 2); ?>
                            </td>
                            <td>
                                <?php if ($is_winning): ?>
                                    <span class="badge badge-green">
                                        <i class="fas fa-check-circle"></i> Winning
                                    </span>
                                <?php else: ?>
                                    <span class="badge badge-red">
                                        <i class="fas fa-times-circle"></i> Outbid
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="td-date">
                                <?php echo date('d M Y, h:i A', strtotime($row['created_at'])); ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5">
                            <div class="empty-state">
                                <i class="fas fa-info-circle" style="font-size: 24px; margin-bottom: 8px; color: #10b981;"></i>
                                <p>No bid history found.</p>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include_once __DIR__ . "/../includes/footer.php"; ?>