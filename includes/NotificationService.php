<?php
/**
 * Notification Service
 * File: includes/NotificationService.php
 * 
 * Manages notifications using OOP PHP and PDO.
 * Strictly adheres to the schema of the `notifications` table:
 * id, user_id, type, title, message, link, icon, is_read, is_global, read_at, created_at
 */

require_once __DIR__ . '/Database.php';

class NotificationService
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getPdo();
    }

    /**
     * Create a notification for a user
     */
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

    /**
     * Mark single notification as read (must be owned by the user)
     */
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
}
