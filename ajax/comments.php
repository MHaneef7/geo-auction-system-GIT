<?php
/**
 * Auction Item Comments AJAX Endpoint
 * File: ajax/comments.php
 * 
 * Tech Stack: PHP 8+, OOP PHP, PDO Prepared Statements, JSON API
 * Features: Multi-format payload support (FormData & JSON), CSRF & Same-Origin Defense,
 * Anti-Spam Rate Limiting, Strict Input Validation & Sanitization,
 * Real-Time Seller Notifications, and Standardized HTTP Status Codes.
 */

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
}

require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/NotificationService.php';

class CommentController
{
    private PDO $pdo;
    private array $payload = [];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->parseIncomingPayload();
        $this->ensureTable();
    }

    /**
     * Parse both multipart/form-data and application/json requests
     */
    private function parseIncomingPayload(): void
    {
        $this->payload = $_POST;

        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (stripos($contentType, 'application/json') !== false) {
            $raw = file_get_contents('php://input');
            if (!empty($raw)) {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    $this->payload = array_merge($this->payload, $decoded);
                }
            }
        }
    }

    /**
     * Ensure database schema exists with optimized indexing
     */
    private function ensureTable(): void
    {
        try {
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS `item_comments` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `item_id` INT NOT NULL,
                    `user_id` INT NOT NULL,
                    `comment` TEXT NOT NULL,
                    `status` VARCHAR(20) DEFAULT 'active',
                    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                    INDEX `idx_item_created` (`item_id`, `created_at`),
                    INDEX `idx_user` (`user_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        } catch (\Throwable $e) {
            error_log('CommentController ensureTable: ' . $e->getMessage());
        }
    }

    /**
     * Dispatch incoming action
     */
    public function handle(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $action = $this->payload['action'] ?? $_GET['action'] ?? ($method === 'POST' ? 'add' : 'get');

        try {
            if ($action === 'get') {
                $this->handleGetComments();
            } elseif ($action === 'add') {
                $this->addComment();
            } else {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Invalid or unsupported action requested.'
                ], JSON_UNESCAPED_UNICODE);
            }
        } catch (\Throwable $e) {
            error_log('CommentController Error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'A system error occurred while processing your comment.'
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * Fetch comments list for an item
     */
    public function getComments(int $itemId, int $limit = 50, int $offset = 0): array
    {
        if ($itemId <= 0) {
            return [];
        }

        $limit = max(1, min(100, $limit));
        $offset = max(0, $offset);

        $stmt = $this->pdo->prepare("
            SELECT 
                c.id,
                c.item_id,
                c.user_id,
                c.comment,
                c.created_at,
                u.name AS user_name,
                u.avatar AS user_avatar,
                u.role AS user_role
            FROM item_comments c
            JOIN users u ON c.user_id = u.id
            WHERE c.item_id = :item_id 
              AND (c.status = 'active' OR c.status IS NULL)
            ORDER BY c.created_at DESC
            LIMIT :limit OFFSET :offset
        ");
        $stmt->bindValue(':item_id', $itemId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $comments = [];
        foreach ($rows as $row) {
            $avatar = !empty($row['user_avatar']) ? (string)$row['user_avatar'] : '';
            if (!empty($avatar) && !preg_match('/^(https?:\/\/|\/\/|data:)/i', $avatar)) {
                $avatar = ltrim($avatar, '/');
            }

            $comments[] = [
                'id'          => (int)$row['id'],
                'item_id'     => (int)$row['item_id'],
                'user_id'     => (int)$row['user_id'],
                'user_name'   => htmlspecialchars($row['user_name'] ?? 'Auction Member', ENT_QUOTES, 'UTF-8'),
                'user_avatar' => $avatar,
                'user_role'   => htmlspecialchars(strtolower($row['user_role'] ?? 'buyer'), ENT_QUOTES, 'UTF-8'),
                'comment'     => htmlspecialchars($row['comment'] ?? '', ENT_QUOTES, 'UTF-8'),
                'time_ago'    => $this->timeElapsedString($row['created_at']),
                'created_at'  => date('M d, Y h:i A', strtotime($row['created_at']))
            ];
        }

        return $comments;
    }

    /**
     * Handle HTTP GET request for lot comments
     */
    private function handleGetComments(): void
    {
        $itemId = (int)($_GET['item_id'] ?? ($this->payload['item_id'] ?? 0));
        $limit  = (int)($_GET['limit'] ?? 50);
        $offset = (int)($_GET['offset'] ?? 0);

        if ($itemId <= 0) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'A valid auction lot item ID is required.'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        $comments = $this->getComments($itemId, $limit, $offset);

        http_response_code(200);
        echo json_encode([
            'success'  => true,
            'count'    => count($comments),
            'comments' => $comments
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Post a new comment on an item
     */
    private function addComment(): void
    {
        // 1. Authentication check
        if (!is_logged_in()) {
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'message' => 'Please log in to participate in the conversation.'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        $userId = (int)($_SESSION['user_id'] ?? $_SESSION['id'] ?? 0);
        if ($userId <= 0) {
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'message' => 'Invalid user session. Please sign in again.'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        // 2. CSRF & Same-Origin Verification
        $csrfToken = (string)($this->payload['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
        $isCsrfValid = false;

        if (!empty($csrfToken) && validate_csrf_token($csrfToken)) {
            $isCsrfValid = true;
        } else {
            // Validate same-origin request
            $serverHost = $_SERVER['HTTP_HOST'] ?? '';
            $origin     = $_SERVER['HTTP_ORIGIN'] ?? '';
            $referer    = $_SERVER['HTTP_REFERER'] ?? '';
            $originHost = !empty($origin) ? parse_url($origin, PHP_URL_HOST) : (!empty($referer) ? parse_url($referer, PHP_URL_HOST) : null);

            if ($originHost !== null && strcasecmp($originHost, explode(':', $serverHost)[0]) === 0) {
                $isCsrfValid = true;
            }
        }

        if (!$isCsrfValid) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'message' => 'Security token mismatch. Please reload the auction page.'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        // 3. Anti-Spam Rate Limiting (8 comments / 60 seconds per user)
        $rateLimit = check_rate_limit('comment_add_' . $userId, 8, 60);
        if (!$rateLimit['allowed']) {
            http_response_code(429);
            echo json_encode([
                'success' => false,
                'message' => 'You are posting comments too quickly. Please pause for a few seconds.'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        // 4. Input Extraction & Sanitization
        $itemId = (int)($this->payload['item_id'] ?? 0);
        $rawComment = (string)($this->payload['comment'] ?? '');

        // Remove null bytes and strip HTML tags
        $cleanComment = str_replace(chr(0), '', trim($rawComment));
        $cleanComment = strip_tags($cleanComment);

        if ($itemId <= 0) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Invalid auction item ID.'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        if ($cleanComment === '') {
            http_response_code(422);
            echo json_encode([
                'success' => false,
                'message' => 'Comment cannot be empty.'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        if (mb_strlen($cleanComment) < 2) {
            http_response_code(422);
            echo json_encode([
                'success' => false,
                'message' => 'Comment must be at least 2 characters long.'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        if (mb_strlen($cleanComment) > 500) {
            http_response_code(422);
            echo json_encode([
                'success' => false,
                'message' => 'Comment cannot exceed 500 characters.'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        // 5. Verify Item Existence
        $itemCheck = $this->pdo->prepare("SELECT id, title, seller_id, status FROM items WHERE id = :item_id LIMIT 1");
        $itemCheck->execute([':item_id' => $itemId]);
        $item = $itemCheck->fetch(PDO::FETCH_ASSOC);

        if (!$item) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'The requested auction lot does not exist or has been removed.'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        // Check for duplicate comment submitted consecutively within 20 seconds
        $dupCheck = $this->pdo->prepare("
            SELECT id FROM item_comments 
            WHERE item_id = :item_id AND user_id = :user_id AND comment = :comment 
              AND created_at >= (NOW() - INTERVAL 20 SECOND)
            LIMIT 1
        ");
        $dupCheck->execute([
            ':item_id' => $itemId,
            ':user_id' => $userId,
            ':comment' => $cleanComment
        ]);
        if ($dupCheck->fetch()) {
            http_response_code(429);
            echo json_encode([
                'success' => false,
                'message' => 'Duplicate comment detected. Please do not submit the same comment twice.'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        // Record attempt for rate limit
        record_rate_limit_attempt('comment_add_' . $userId, 60);

        // 6. Insert Comment via Prepared Statement
        $insertStmt = $this->pdo->prepare("
            INSERT INTO item_comments (item_id, user_id, comment, status, created_at)
            VALUES (:item_id, :user_id, :comment, 'active', NOW())
        ");
        $insertStmt->execute([
            ':item_id' => $itemId,
            ':user_id' => $userId,
            ':comment' => $cleanComment
        ]);

        $newCommentId = (int)$this->pdo->lastInsertId();

        // 7. Retrieve user details for immediate client display
        $uStmt = $this->pdo->prepare("SELECT name, avatar, role FROM users WHERE id = :id LIMIT 1");
        $uStmt->execute([':id' => $userId]);
        $uRow = $uStmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $avatar = !empty($uRow['avatar']) ? (string)$uRow['avatar'] : '';
        if (!empty($avatar) && !preg_match('/^(https?:\/\/|\/\/|data:)/i', $avatar)) {
            $avatar = ltrim($avatar, '/');
        }

        // 8. Trigger real-time notification to the seller if commenter is not the seller
        $sellerId = (int)($item['seller_id'] ?? 0);
        if ($sellerId > 0 && $sellerId !== $userId) {
            try {
                $notifService = new NotificationService($this->pdo);
                $commenterName = !empty($uRow['name']) ? $uRow['name'] : ($_SESSION['user_name'] ?? 'A bidder');
                $itemTitle = !empty($item['title']) ? $item['title'] : 'Lot #' . $itemId;
                $notifService->notifyComment($sellerId, $commenterName, $itemTitle, $itemId);
            } catch (\Throwable $e) {
                error_log("Failed to trigger comment notification: " . $e->getMessage());
            }
        }

        http_response_code(201);
        echo json_encode([
            'success' => true,
            'message' => 'Comment posted successfully.',
            'comment' => [
                'id'          => $newCommentId,
                'item_id'     => $itemId,
                'user_id'     => $userId,
                'user_name'   => htmlspecialchars($uRow['name'] ?? ($_SESSION['user_name'] ?? 'You'), ENT_QUOTES, 'UTF-8'),
                'user_avatar' => $avatar,
                'user_role'   => htmlspecialchars(strtolower($uRow['role'] ?? 'buyer'), ENT_QUOTES, 'UTF-8'),
                'comment'     => htmlspecialchars($cleanComment, ENT_QUOTES, 'UTF-8'),
                'time_ago'    => 'Just now',
                'created_at'  => date('M d, Y h:i A')
            ]
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Human-friendly relative timestamp formatter
     */
    private function timeElapsedString(string $datetime): string
    {
        try {
            $now  = new DateTime();
            $ago  = new DateTime($datetime);
            $diff = $now->diff($ago);

            if ($diff->y > 0) return $diff->y . 'y ago';
            if ($diff->m > 0) return $diff->m . 'mo ago';
            if ($diff->d > 0) return $diff->d . 'd ago';
            if ($diff->h > 0) return $diff->h . 'h ago';
            if ($diff->i > 0) return $diff->i . 'm ago';
            return 'Just now';
        } catch (\Throwable $e) {
            return 'Recently';
        }
    }
}

// Main execution entry point
if (!defined('IN_COMMENT_TEST')) {
    $pdo = Database::getPdo();
    $controller = new CommentController($pdo);
    $controller->handle();
}
