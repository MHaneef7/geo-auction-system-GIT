<?php

/**
 * Notification Service
 * File: includes/NotificationService.php
 * 
 * Manages notifications using OOP PHP and PDO.
 * Strictly adheres to the schema of the `notifications` table:
 * id, user_id, type, title, message, link, icon, is_read, is_global, read_at, created_at
 */

require_once __DIR__ . '/database.php';

class NotificationService
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getPdo();
    }

    public function createNotification(
        int $userId,
        string $type,
        string $title,
        string $message,
        ?string $link = null,
        string $icon = 'fa-solid fa-bell',
        int $isGlobal = 0
    ): int {
        $sql = "INSERT INTO notifications (
                    user_id,
                    type,
                    title,
                    message,
                    link,
                    icon,
                    is_read,
                    is_global,
                    created_at
                ) VALUES (
                    :user_id,
                    :type,
                    :title,
                    :message,
                    :link,
                    :icon,
                    0,
                    :is_global,
                    NOW()
                )";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':user_id'   => $userId,
            ':type'      => $type,
            ':title'     => $title,
            ':message'   => $message,
            ':link'      => $link,
            ':icon'      => $icon,
            ':is_global' => $isGlobal,
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Notify seller of a new comment on their auction lot
     */
    public function notifyComment(
        int $sellerId,
        string $commenterName,
        string $itemTitle,
        int $itemId
    ): int {
        if ($sellerId <= 0) {
            return 0;
        }

        $titleSnippet = mb_substr(trim($itemTitle), 0, 45);
        if (mb_strlen(trim($itemTitle)) > 45) {
            $titleSnippet .= '...';
        }

        $title = "New Comment on Lot";
        $message = "{$commenterName} commented on \"{$titleSnippet}\".";
        $link = "live_auction.php?id={$itemId}";

        return $this->createNotification(
            $sellerId,
            'comment',
            $title,
            $message,
            $link,
            'fa-solid fa-comments'
        );
    }

    /**
     * Notify bidder that they have been outbid on an item
     */
    public function notifyOutbid(
        int $outbidUserId,
        string $itemTitle,
        float $newAmount,
        int $itemId
    ): int {
        if ($outbidUserId <= 0) {
            return 0;
        }

        $titleSnippet = mb_substr(trim($itemTitle), 0, 45);
        if (mb_strlen(trim($itemTitle)) > 45) {
            $titleSnippet .= '...';
        }

        $formattedAmount = number_format($newAmount, 2);
        $title = "You Have Been Outbid!";
        $message = "A higher bid of Rs. {$formattedAmount} was placed on \"{$titleSnippet}\". Place a counter-bid now!";
        $link = "live_auction.php?id={$itemId}";

        return $this->createNotification(
            $outbidUserId,
            'outbid',
            $title,
            $message,
            $link,
            'fa-solid fa-arrow-trend-up'
        );
    }

    /**
     * Notify seller of a new bid placed on their item
     */
    public function notifyBidPlaced(
        int $sellerId,
        string $bidderName,
        string $itemTitle,
        float $amount,
        int $itemId
    ): int {
        if ($sellerId <= 0) {
            return 0;
        }

        $titleSnippet = mb_substr(trim($itemTitle), 0, 45);
        if (mb_strlen(trim($itemTitle)) > 45) {
            $titleSnippet .= '...';
        }

        $formattedAmount = number_format($amount, 2);
        $title = "New Bid Placed";
        $message = "{$bidderName} placed a bid of Rs. {$formattedAmount} on \"{$titleSnippet}\".";
        $link = "live_auction.php?id={$itemId}";

        return $this->createNotification(
            $sellerId,
            'bid',
            $title,
            $message,
            $link,
            'fa-solid fa-gavel'
        );
    }

    /**
     * Notify winner that they won an auction lot
     */
    public function notifyAuctionWon(
        int $winnerId,
        string $itemTitle,
        float $finalAmount,
        int $itemId
    ): int {
        if ($winnerId <= 0) {
            return 0;
        }

        $titleSnippet = mb_substr(trim($itemTitle), 0, 45);
        if (mb_strlen(trim($itemTitle)) > 45) {
            $titleSnippet .= '...';
        }

        $formattedAmount = number_format($finalAmount, 2);
        $title = "Congratulations! Auction Won";
        $message = "You won \"{$titleSnippet}\" for Rs. {$formattedAmount}! Proceed to invoice & settlement to claim your item.";
        $link = "invoice.php?item_id={$itemId}";

        return $this->createNotification(
            $winnerId,
            'auction_won',
            $title,
            $message,
            $link,
            'fa-solid fa-trophy'
        );
    }

    /**
     * Notify user of a direct chat message
     */
    public function notifyNewMessage(
        int $receiverId,
        string $senderName,
        string $messagePreview,
        int $senderId,
        ?int $itemId = null
    ): int {
        if ($receiverId <= 0) {
            return 0;
        }

        $cleanSnippet = mb_substr(trim($messagePreview), 0, 60);
        if (mb_strlen(trim($messagePreview)) > 60) {
            $cleanSnippet .= '...';
        }

        $title = "New Message";
        $message = "{$senderName}: \"{$cleanSnippet}\"";
        $link = "messages.php?user_id={$senderId}" . ($itemId ? "&item_id={$itemId}" : "");

        return $this->createNotification(
            $receiverId,
            'message',
            $title,
            $message,
            $link,
            'fa-solid fa-message'
        );
    }


    /**
     * Fetch recent notifications for a user (up to limit)
     */
    public function getRecentNotifications(int $userId, int $limit = 10): array
    {
        $limit = max(1, min(50, $limit));
        $sql = "SELECT 
                    id,
                    user_id,
                    type,
                    title,
                    message,
                    link,
                    icon,
                    is_read,
                    is_global,
                    read_at,
                    created_at
                FROM notifications
                WHERE user_id = :user_id OR is_global = 1
                ORDER BY created_at DESC
                LIMIT " . (int)$limit;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':user_id' => $userId]);
        $rows = $stmt->fetchAll();

        $formatted = [];
        $now = time();
        foreach ($rows as $row) {
            $createdTs = strtotime($row['created_at']);
            $diff = max(0, $now - $createdTs);
            if ($diff < 60) {
                $timeAgo = 'just now';
            } elseif ($diff < 3600) {
                $timeAgo = floor($diff / 60) . 'm ago';
            } elseif ($diff < 86400) {
                $timeAgo = floor($diff / 3600) . 'h ago';
            } else {
                $timeAgo = floor($diff / 86400) . 'd ago';
            }

            $formatted[] = [
                'id'         => (int)$row['id'],
                'user_id'    => (int)$row['user_id'],
                'type'       => $row['type'],
                'title'      => htmlspecialchars($row['title'], ENT_QUOTES, 'UTF-8'),
                'message'    => htmlspecialchars($row['message'], ENT_QUOTES, 'UTF-8'),
                'link'       => $row['link'] ? htmlspecialchars($row['link'], ENT_QUOTES, 'UTF-8') : '',
                'icon'       => $row['icon'] ?: 'fa-solid fa-bell',
                'is_read'    => (int)$row['is_read'],
                'is_global'  => (int)$row['is_global'],
                'created_at' => $row['created_at'],
                'time_ago'   => $timeAgo,
            ];
        }

        return $formatted;
    }

    /**
     * Alias for getRecentNotifications
     */
    public function getNotificationsForUser(int $userId, int $limit = 10): array
    {
        return $this->getRecentNotifications($userId, $limit);
    }

    /**
     * Get count of unread notifications
     */
    public function getUnreadCount(int $userId): int
    {
        $sql = "SELECT COUNT(*) 
                FROM notifications
                WHERE (user_id = :user_id OR is_global = 1)
                  AND is_read = 0";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':user_id' => $userId]);
        return (int)$stmt->fetchColumn();
    }

    public function markAsRead(int $notificationId, int $userId): bool
    {
        $sql = "UPDATE notifications
                SET is_read = 1, read_at = NOW()
                WHERE id = :id 
                  AND (user_id = :user_id OR is_global = 1)";

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            ':id'      => $notificationId,
            ':user_id' => $userId,
        ]);
    }

    /**
     * Mark all notifications as read for the user
     */
    public function markAllAsRead(int $userId): bool
    {
        $sql = "UPDATE notifications
                SET is_read = 1, read_at = NOW()
                WHERE (user_id = :user_id OR is_global = 1)
                  AND is_read = 0";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([':user_id' => $userId]);
    }

    /**
     * Get paginated notifications list with optional read status filter
     */
    public function getAllNotifications(int $userId, ?int $isRead = null, int $limit = 50, int $offset = 0): array
    {
        $limit = max(1, min(100, $limit));
        $offset = max(0, $offset);

        $where = "(user_id = :user_id OR is_global = 1)";
        $params = [':user_id' => $userId];

        if ($isRead !== null) {
            $where .= " AND is_read = :is_read";
            $params[':is_read'] = $isRead;
        }

        $sql = "SELECT 
                    id,
                    user_id,
                    type,
                    title,
                    message,
                    link,
                    icon,
                    is_read,
                    is_global,
                    read_at,
                    created_at
                FROM notifications
                WHERE {$where}
                ORDER BY created_at DESC
                LIMIT {$limit} OFFSET {$offset}";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $formatted = [];
        $now = time();
        foreach ($rows as $row) {
            $createdTs = strtotime($row['created_at']);
            $diff = max(0, $now - $createdTs);
            if ($diff < 60) {
                $timeAgo = 'just now';
            } elseif ($diff < 3600) {
                $timeAgo = floor($diff / 60) . 'm ago';
            } elseif ($diff < 86400) {
                $timeAgo = floor($diff / 3600) . 'h ago';
            } else {
                $timeAgo = floor($diff / 86400) . 'd ago';
            }

            $formatted[] = [
                'id'         => (int)$row['id'],
                'user_id'    => (int)$row['user_id'],
                'type'       => $row['type'],
                'title'      => htmlspecialchars($row['title'], ENT_QUOTES, 'UTF-8'),
                'message'    => htmlspecialchars($row['message'], ENT_QUOTES, 'UTF-8'),
                'link'       => $row['link'] ? htmlspecialchars($row['link'], ENT_QUOTES, 'UTF-8') : '',
                'icon'       => $row['icon'] ?: 'fa-solid fa-bell',
                'is_read'    => (int)$row['is_read'],
                'is_global'  => (int)$row['is_global'],
                'created_at' => $row['created_at'],
                'time_ago'   => $timeAgo,
            ];
        }

        return $formatted;
    }

    /**
     * Delete a single notification owned by user
     */
    public function deleteNotification(int $notificationId, int $userId): bool
    {
        $sql = "DELETE FROM notifications WHERE id = :id AND user_id = :user_id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            ':id'      => $notificationId,
            ':user_id' => $userId
        ]);
    }
}

