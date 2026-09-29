<?php
$require_auth = false;
include_once('includes/header.php');

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = mysqli_real_escape_string($conn, trim($_POST['name'] ?? ''));
    $email   = mysqli_real_escape_string($conn, trim($_POST['email'] ?? ''));
    $subject = mysqli_real_escape_string($conn, trim($_POST['subject'] ?? ''));
    $message = mysqli_real_escape_string($conn, trim($_POST['message'] ?? ''));

    if (!empty($name) && !empty($email) && !empty($message)) {
        $sql = "INSERT INTO contact_messages (`name`, `email`, `subject`, `message`, `created_at`) VALUES ('$name', '$email', '$subject', '$message', NOW())";
        @mysqli_query($conn, $sql);
        $msg = "Thank you for contacting us! We will respond shortly.";
    } else {
        $msg = "Please fill in all required fields.";
    }
}
?>

<div style="background:#fff; padding:32px; border-radius:8px; border:1px solid #e8e8e8; max-width:600px; margin:0 auto;">
    <h1 style="font-size:24px; font-weight:800; color:#1a1a2e; margin-bottom:16px;">Contact Support</h1>
    
    <?php if (!empty($msg)): ?>
        <div style="background:#e8f5ec; color:#007a33; padding:12px; border-radius:6px; margin-bottom:16px; font-weight:600; font-size:14px;">
            <?php echo htmlspecialchars($msg); ?>
        </div>
    <?php endif; ?>

    <form action="contact.php" method="POST">
        <div style="margin-bottom:16px;">
            <label style="display:block; font-weight:600; font-size:13px; margin-bottom:6px;">Your Name *</label>
            <input type="text" name="name" required style="width:100%; padding:10px; border:1px solid #ccc; border-radius:6px;">
        </div>
        <div style="margin-bottom:16px;">
            <label style="display:block; font-weight:600; font-size:13px; margin-bottom:6px;">Email Address *</label>
            <input type="email" name="email" required style="width:100%; padding:10px; border:1px solid #ccc; border-radius:6px;">
        </div>
        <div style="margin-bottom:16px;">
            <label style="display:block; font-weight:600; font-size:13px; margin-bottom:6px;">Subject</label>
            <input type="text" name="subject" style="width:100%; padding:10px; border:1px solid #ccc; border-radius:6px;">
        </div>
        <div style="margin-bottom:16px;">
            <label style="display:block; font-weight:600; font-size:13px; margin-bottom:6px;">Message *</label>
            <textarea name="message" required style="width:100%; padding:10px; border:1px solid #ccc; border-radius:6px; min-height:100px;"></textarea>
        </div>
        <button type="submit" style="background:#007a33; color:#fff; padding:12px 24px; border:none; border-radius:6px; font-weight:700; cursor:pointer;">
            Send Message
        </button>
    </form>
</div>

<?php include_once('includes/footer.php'); ?>
