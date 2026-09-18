<?php
include_once '../includes/header.php';
include_once __DIR__ . '/../includes/database.php';

$user_id = (int)$_SESSION['user_id'];

// Query the user's notifications
$notifs = mysqli_query($conn, "SELECT * FROM notifications WHERE user_id = $user_id ORDER BY created_at DESC");
?>

<div style="max-width: 900px; margin: 0 auto; background: #fff; padding: 28px; border-radius: 8px; border: 1px solid #e8e8e8;">
    <h2 style="font-size: 22px; font-weight: 800; color: #1a1a2e; margin-bottom: 20px;">
        Notifications
    </h2>

    <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 14px;">
        <thead>
            <tr style="border-bottom: 2px solid #eee; color: #6c757d; background: #f8f9fa;">
                <th style="padding: 12px;">Type</th>
                <th style="padding: 12px;">Message</th>
                <th style="padding: 12px;">Status</th>
                <th style="padding: 12px;">Date</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($notifs && mysqli_num_rows($notifs) > 0): ?>
                <?php while ($row = mysqli_fetch_assoc($notifs)): ?>
                <tr style="border-bottom: 1px solid #eee;">
                    <td style="padding: 12px; font-weight: 600; text-transform: capitalize;"><?php echo htmlspecialchars($row['type'] ?? 'General'); ?></td>
                    <td style="padding: 12px;"><?php echo htmlspecialchars($row['message'] ?? $row['content'] ?? ''); ?></td>
                    <td style="padding: 12px;">
                        <span style="background: <?php echo ($row['is_read'] ?? 0) ? '#eee' : '#e8f5ec'; ?>; color: <?php echo ($row['is_read'] ?? 0) ? '#666' : '#007a33'; ?>; padding: 4px 10px; border-radius: 20px; font-weight: 700; font-size: 11px;">
                            <?php echo ($row['is_read'] ?? 0) ? 'Read' : 'Unread'; ?>
                        </span>
                    </td>
                    <td style="padding: 12px; color: #888; font-size: 12px;"><?php echo $row['created_at']; ?></td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="4" style="padding: 24px; text-align: center; color: #888;">No notifications found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include_once '../includes/footer.php'; ?>
