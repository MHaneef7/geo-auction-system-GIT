<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$base_url = file_exists('includes/database.php') ? '' : '../';
?>
<nav style="background: #007a33; color: #fff; padding: 12px 24px; display: flex; justify-content: space-between; align-items: center;">
    <a href="<?php echo $base_url; ?>dashboard.php" style="color: #fff; font-weight: bold; font-size: 18px; text-decoration: none;">
        Geo Auction
    </a>
    <div>
        <?php if (isset($_SESSION['user_id'])): ?>
            <span style="margin-right: 15px;">Welcome, <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'User'); ?></span>
            <a href="<?php echo $base_url; ?>logout.php" style="color: #fff; text-decoration: underline;">Logout</a>
        <?php else: ?>
            <a href="<?php echo $base_url; ?>index.php" style="color: #fff; margin-right: 15px; text-decoration: none;">Login</a>
            <a href="<?php echo $base_url; ?>register.php" style="color: #fff; text-decoration: none;">Register</a>
        <?php endif; ?>
    </div>
</nav>
