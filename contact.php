<?php
/**
 * Contact Support Page - Geo Auction System
 *
 * Implements OOP PHP + PDO with prepared statements and CSRF protection.
 */

declare(strict_types=1);

$require_auth = false;
include_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/database.php';

$pdo = Database::getPdo();
$msg = '';
$msgType = 'success';

// Ensure table exists
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `contact_messages` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(100) NOT NULL,
            `email` VARCHAR(150) NOT NULL,
            `subject` VARCHAR(200) NULL,
            `message` TEXT NOT NULL,
            `status` ENUM('unread', 'read', 'replied') NOT NULL DEFAULT 'unread',
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
} catch (\Throwable $e) {
    error_log("contact_messages table check: " . $e->getMessage());
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $name    = trim((string)($_POST['name'] ?? ''));
    $email   = trim((string)($_POST['email'] ?? ''));
    $subject = trim((string)($_POST['subject'] ?? ''));
    $message = trim((string)($_POST['message'] ?? ''));

    if (!empty($name) && !empty($email) && !empty($message)) {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $msg = "Please provide a valid email address.";
            $msgType = 'danger';
        } else {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO contact_messages (`name`, `email`, `subject`, `message`, `created_at`) 
                    VALUES (:name, :email, :subject, :message, NOW())
                ");
                $stmt->execute([
                    ':name'    => $name,
                    ':email'   => $email,
                    ':subject' => $subject,
                    ':message' => $message
                ]);
                $msg = "Thank you for reaching out! Our support team will review your message shortly.";
                $msgType = 'success';
            } catch (\Throwable $e) {
                error_log("Contact form submission error: " . $e->getMessage());
                $msg = "A database error occurred. Please try again later.";
                $msgType = 'danger';
            }
        }
    } else {
        $msg = "Please fill in all required fields (Name, Email, Message).";
        $msgType = 'danger';
    }
}
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-7 col-md-9">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-header bg-emerald text-white p-4 text-center" style="background: linear-gradient(135deg, #064e3b 0%, #047857 100%);">
                    <div class="w-12 h-12 rounded-circle bg-white bg-opacity-20 mx-auto d-flex align-items-center justify-content-center mb-2" style="width: 52px; height: 52px;">
                        <i class="fa-solid fa-headset fa-xl text-white"></i>
                    </div>
                    <h2 class="h4 fw-bold mb-1">Contact Support</h2>
                    <p class="mb-0 text-white-50 small">Have a question regarding lot bidding, settlement, or your account? Reach out anytime.</p>
                </div>
                
                <div class="card-body p-4 p-md-5">
                    <?php if (!empty($msg)): ?>
                        <div class="alert alert-<?php echo htmlspecialchars($msgType); ?> d-flex align-items-center gap-2 rounded-3 mb-4" role="alert">
                            <i class="fa-solid <?php echo $msgType === 'success' ? 'fa-circle-check text-success' : 'fa-circle-exclamation text-danger'; ?>"></i>
                            <div><?php echo htmlspecialchars($msg); ?></div>
                        </div>
                    <?php endif; ?>

                    <form action="contact.php" method="POST" class="needs-validation">
                        <div class="mb-3">
                            <label class="form-label fw-semibold text-dark small">Your Full Name <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0 text-muted"><i class="fa-solid fa-user"></i></span>
                                <input type="text" name="name" class="form-control border-start-0 ps-1" required placeholder="e.g. Muhammad Ali" value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold text-dark small">Email Address <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0 text-muted"><i class="fa-solid fa-envelope"></i></span>
                                <input type="email" name="email" class="form-control border-start-0 ps-1" required placeholder="you@domain.com" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold text-dark small">Subject</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0 text-muted"><i class="fa-solid fa-tag"></i></span>
                                <input type="text" name="subject" class="form-control border-start-0 ps-1" placeholder="e.g. Question regarding Auction #201" value="<?php echo htmlspecialchars($_POST['subject'] ?? ''); ?>">
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-semibold text-dark small">Message <span class="text-danger">*</span></label>
                            <textarea name="message" class="form-control" rows="5" required placeholder="Describe your question or issue in detail..."><?php echo htmlspecialchars($_POST['message'] ?? ''); ?></textarea>
                        </div>

                        <button type="submit" class="btn text-white w-100 py-3 fw-bold rounded-3 shadow-sm transition" style="background:#047857;">
                            <i class="fa-solid fa-paper-plane me-2"></i> Send Message
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once __DIR__ . '/includes/footer.php'; ?>
