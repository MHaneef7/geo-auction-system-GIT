<?php
/**
 * Message Service
 * File: includes/MessageService.php
 * 
 * Manages chat conversations, message delivery, and read statuses
 * using OOP PHP, PDO, and database transactions.
 * Adheres strictly to the schema of `messages` and `notifications` tables.
 */

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/NotificationService.php';

class MessageService
{
    private PDO $pdo;
    private NotificationService $notifService;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getPdo();
        $this->notifService = new NotificationService($this->pdo);
    }

    /**
     * Fetch user details by ID
     */
    public function getUser(int $userId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT id, name, username, email, avatar, role, status FROM users WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $userId]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    /**
     * Fetch item details by ID
     */
    public function getItem(int $itemId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT id, auction_code, title, seller_id, current_price, starting_price, featured_image, status FROM items WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $itemId]);
        $item = $stmt->fetch();
        return $item ?: null;
    }

    /**
     * Send a new message with validation and automated notification inside a transaction
     */
    public function sendMessage(
        int $senderId,
        int $receiverId,
        string $messageBody,
        ?int $itemId = null,
        ?string $subject = null,
        ?int $parentId = null
    ): array {
        if ($senderId <= 0 || $receiverId <= 0) {
            throw new InvalidArgumentException("Invalid sender or receiver identity.");
        }

        if ($senderId === $receiverId) {
            throw new InvalidArgumentException("You cannot send a message to yourself.");
        }

        $receiver = $this->getUser($receiverId);
        if (!$receiver) {
            throw new RuntimeException("Recipient user does not exist.");
        }

        if (in_array(strtolower($receiver['status'] ?? ''), ['suspended', 'inactive'], true)) {
            throw new RuntimeException("Recipient account is not active.");
        }

        $cleanBody = trim($messageBody);
        if ($cleanBody === '') {
            throw new InvalidArgumentException("Message content cannot be blank.");
        }

        if (mb_strlen($cleanBody) > 5000) {
            throw new InvalidArgumentException("Message exceeds maximum permitted length (5,000 characters).");
        }

        $itemData = null;
        if ($itemId !== null && $itemId > 0) {
            $itemData = $this->getItem($itemId);
            if (!$itemData) {
                $itemId = null;
            }
        } else {
            $itemId = null;
        }

        $cleanSubject = null;
        if (!empty($subject)) {
            $cleanSubject = mb_substr(trim($subject), 0, 255);
        } elseif ($itemData) {
            $cleanSubject = "Regarding: " . mb_substr($itemData['title'], 0, 200) . " (#" . $itemData['auction_code'] . ")";
        }

        $sender = $this->getUser($senderId);
        $senderName = $sender['name'] ?? 'A member';

        $this->pdo->beginTransaction();

        try {
            $sql = "INSERT INTO messages (
                        sender_id,
                        receiver_id,
                        message_body,
                        message,
                        item_id,
                        subject,
                        is_read,
                        is_starred,
                        parent_id,
                        created_at
                    ) VALUES (
                        :sender_id,
                        :receiver_id,
                        :message_body,
                        :message,
                        :item_id,
                        :subject,
                        0,
                        0,
                        :parent_id,
                        NOW()
                    )";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':sender_id'    => $senderId,
                ':receiver_id'  => $receiverId,
                ':message_body' => $cleanBody,
                ':message'      => $cleanBody,
                ':item_id'      => $itemId,
                ':subject'      => $cleanSubject,
                ':parent_id'    => $parentId,
            ]);

            $messageId = (int)$this->pdo->lastInsertId();

            $link = "messages.php?user_id=" . $senderId . ($itemId ? "&item_id=" . $itemId : "");
            $notifTitle = "New Message";
            $notifMessage = $senderName . " sent you a new message.";

            $this->notifService->createNotification(
                $receiverId,
                'message',
                $notifTitle,
                $notifMessage,
                $link,
                'fa-solid fa-message',
                0
            );

            $this->pdo->commit();

            return [
                'success'    => true,
                'message_id' => $messageId,
                'sender_id'  => $senderId,
                'created_at' => date('Y-m-d H:i:s'),
                'time_fmt'   => date('g:i A'),
            ];
        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log("SendMessage Exception: " . $e->getMessage());
            throw new RuntimeException("Unable to transmit message. Please try again.");
        }
    }

    /**
     * Retrieve complete chat history between two users
     */
    public function getChatHistory(int $currentUser, int $otherUser): array
    {
        $sql = "SELECT 
                    m.id,
                    m.sender_id,
                    m.receiver_id,
                    m.message_body,
                    m.message,
                    m.item_id,
                    m.subject,
                    m.is_read,
                    m.is_starred,
                    m.parent_id,
                    m.read_at,
                    m.created_at,
                    i.title AS item_title,
                    i.auction_code AS item_auction_code
                FROM messages m
                LEFT JOIN items i ON m.item_id = i.id
                WHERE (m.sender_id = :cu1 AND m.receiver_id = :ou1)
                   OR (m.sender_id = :ou2 AND m.receiver_id = :cu2)
                ORDER BY m.created_at ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':cu1' => $currentUser,
            ':ou1' => $otherUser,
            ':ou2' => $otherUser,
            ':cu2' => $currentUser,
        ]);

        $rows = $stmt->fetchAll();
        $history = [];

        foreach ($rows as $row) {
            $isOutgoing = ((int)$row['sender_id'] === $currentUser);
            $body = !empty($row['message_body']) ? $row['message_body'] : ($row['message'] ?? '');
            $ts = strtotime($row['created_at']);

            $history[] = [
                'id'            => (int)$row['id'],
                'sender_id'     => (int)$row['sender_id'],
                'receiver_id'   => (int)$row['receiver_id'],
                'is_outgoing'   => $isOutgoing,
                'message_body'  => htmlspecialchars($body, ENT_QUOTES, 'UTF-8'),
                'raw_body'      => $body,
                'item_id'       => $row['item_id'] ? (int)$row['item_id'] : null,
                'item_title'    => $row['item_title'] ? htmlspecialchars($row['item_title'], ENT_QUOTES, 'UTF-8') : null,
                'auction_code'  => $row['item_auction_code'] ? htmlspecialchars($row['item_auction_code'], ENT_QUOTES, 'UTF-8') : null,
                'subject'       => $row['subject'] ? htmlspecialchars($row['subject'], ENT_QUOTES, 'UTF-8') : null,
                'is_read'       => (int)$row['is_read'],
                'created_at'    => $row['created_at'],
                'time_formatted'=> date('g:i A', $ts),
                'date_formatted'=> date('M d, Y', $ts),
            ];
        }

        return $history;
    }

    /**
     * Mark all messages sent by other user to current user as read
     */
    public function markMessagesAsRead(int $currentUserId, int $otherUserId): int
    {
        $sql = "UPDATE messages
                SET is_read = 1, read_at = NOW()
                WHERE sender_id = :other_user
                  AND receiver_id = :current_user
                  AND is_read = 0";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':other_user'   => $otherUserId,
            ':current_user' => $currentUserId,
        ]);

        return $stmt->rowCount();
    }

    /**
     * Get count of unread messages for a recipient
     */
    public function getUnreadCount(int $userId): int
    {
        $sql = "SELECT COUNT(*) FROM messages WHERE receiver_id = :user_id AND is_read = 0";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':user_id' => $userId]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Derive all distinct conversations for a user
     */
    public function getConversations(int $userId): array
    {
        $sql = "SELECT 
                    CASE 
                        WHEN sender_id = :uid1 THEN receiver_id 
                        ELSE sender_id 
                    END AS partner_id,
                    MAX(id) AS latest_message_id,
                    MAX(created_at) AS last_activity,
                    SUM(CASE WHEN receiver_id = :uid2 AND is_read = 0 THEN 1 ELSE 0 END) AS unread_count
                FROM messages
                WHERE sender_id = :uid3 OR receiver_id = :uid4
                GROUP BY partner_id
                ORDER BY last_activity DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':uid1' => $userId,
            ':uid2' => $userId,
            ':uid3' => $userId,
            ':uid4' => $userId,
        ]);
        $partners = $stmt->fetchAll();

        if (empty($partners)) {
            return [];
        }

        $conversations = [];
        $now = time();

        foreach ($partners as $p) {
            $partnerId = (int)$p['partner_id'];
            $partnerUser = $this->getUser($partnerId);

            if (!$partnerUser) {
                continue;
            }

            $msgStmt = $this->pdo->prepare("SELECT message_body, message, created_at, sender_id, item_id FROM messages WHERE id = :mid LIMIT 1");
            $msgStmt->execute([':mid' => $p['latest_message_id']]);
            $latestMsg = $msgStmt->fetch();

            $body = $latestMsg ? (!empty($latestMsg['message_body']) ? $latestMsg['message_body'] : ($latestMsg['message'] ?? '')) : '';
            $preview = mb_substr($body, 0, 60) . (mb_strlen($body) > 60 ? '...' : '');

            $createdTs = strtotime($p['last_activity']);
            $diff = max(0, $now - $createdTs);
            if ($diff < 60) {
                $timeAgo = 'just now';
            } elseif ($diff < 3600) {
                $timeAgo = floor($diff / 60) . 'm ago';
            } elseif ($diff < 86400) {
                $timeAgo = floor($diff / 3600) . 'h ago';
            } else {
                $timeAgo = date('M d', $createdTs);
            }

            $avatar = trim($partnerUser['avatar'] ?? '');
            if (!empty($avatar)) {
                if (!preg_match('/^(https?:\/\/|\/\/|data:)/i', $avatar)) {
                    $avatar = ltrim($avatar, '/\\');
                }
            } else {
                $avatar = 'https://ui-avatars.com/api/?name=' . urlencode($partnerUser['name'] ?: 'User') . '&background=047857&color=fff&size=96&bold=true';
            }

            $conversations[] = [
                'partner_id'      => $partnerId,
                'name'            => htmlspecialchars($partnerUser['name'], ENT_QUOTES, 'UTF-8'),
                'username'        => !empty($partnerUser['username']) ? htmlspecialchars($partnerUser['username'], ENT_QUOTES, 'UTF-8') : '',
                'avatar'          => $avatar,
                'role'            => $partnerUser['role'] ?? 'buyer',
                'status'          => $partnerUser['status'] ?? 'active',
                'last_message'    => htmlspecialchars($preview, ENT_QUOTES, 'UTF-8'),
                'last_time'       => $timeAgo,
                'last_timestamp'  => $p['last_activity'],
                'unread_count'    => (int)$p['unread_count'],
                'is_outgoing'     => ((int)($latestMsg['sender_id'] ?? 0) === $userId),
                'item_id'         => $latestMsg['item_id'] ? (int)$latestMsg['item_id'] : null,
            ];
        }

        return $conversations;
    }

    /**
     * Get all registered users available for messaging (excluding the current user)
     */
    public function getAllUsers(int $excludeUserId, string $search = ''): array
    {
        $params = [':current_user' => $excludeUserId];
        $searchSql = '';

        if (!empty($search)) {
            $searchSql = " AND (name LIKE :s1 OR username LIKE :s2 OR email LIKE :s3 OR role LIKE :s4) ";
            $term = '%' . trim($search) . '%';
            $params[':s1'] = $term;
            $params[':s2'] = $term;
            $params[':s3'] = $term;
            $params[':s4'] = $term;
        }

        $sql = "SELECT id, name, username, email, role, avatar, status 
                FROM users 
                WHERE id != :current_user 
                  AND (status IS NULL OR status = 'active' OR status = '1')
                  $searchSql
                ORDER BY role = 'admin' DESC, name ASC 
                LIMIT 100";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $users = [];
        foreach ($rows as $u) {
            $avatar = trim($u['avatar'] ?? '');
            if (!empty($avatar)) {
                if (!preg_match('/^(https?:\/\/|\/\/|data:)/i', $avatar)) {
                    $avatar = ltrim($avatar, '/\\');
                }
            } else {
                $avatar = 'https://ui-avatars.com/api/?name=' . urlencode($u['name'] ?: 'User') . '&background=047857&color=fff&size=96&bold=true';
            }

            $users[] = [
                'id'       => (int)$u['id'],
                'name'     => htmlspecialchars($u['name'] ?? 'User', ENT_QUOTES, 'UTF-8'),
                'username' => !empty($u['username']) ? htmlspecialchars($u['username'], ENT_QUOTES, 'UTF-8') : '',
                'email'    => htmlspecialchars($u['email'] ?? '', ENT_QUOTES, 'UTF-8'),
                'role'     => ucfirst(strtolower($u['role'] ?? 'buyer')),
                'avatar'   => $avatar,
                'status'   => $u['status'] ?? 'active'
            ];
        }

        return $users;
    }
}
