<?php
/**
 * Mikhmon V3 Reseller System - Notification Model
 * 
 * Handles in-app notifications for resellers.
 */

if (substr($_SERVER["REQUEST_URI"], -22) == "NotificationModel.php") {
    header("Location:../");
    exit;
}

require_once(__DIR__ . '/database.php');

class NotificationModel
{
    /**
     * Create a notification for a reseller.
     */
    public static function create(int $resellerId, string $title, string $message, string $type = 'info', string $link = ''): int
    {
        return ResellerDB::insertArray('reseller_notifications', [
            'reseller_id' => $resellerId,
            'title'       => $title,
            'message'     => $message,
            'type'        => $type,
            'link'        => $link,
        ]);
    }

    /**
     * Broadcast a notification to all active resellers.
     */
    public static function broadcast(string $title, string $message, string $type = 'info'): int
    {
        $resellers = ResellerDB::fetchAll("SELECT id FROM resellers WHERE status = 'active'");
        $count = 0;
        foreach ($resellers as $r) {
            self::create($r['id'], $title, $message, $type);
            $count++;
        }
        return $count;
    }

    /**
     * Get notifications for a reseller.
     */
    public static function getForReseller(int $resellerId, bool $unreadOnly = false, int $limit = 20): array
    {
        $where = "reseller_id = ?";
        if ($unreadOnly) $where .= " AND is_read = 0";

        return ResellerDB::fetchAll(
            "SELECT * FROM reseller_notifications WHERE {$where} ORDER BY created_at DESC LIMIT ?",
            [$resellerId, $limit]
        );
    }

    /**
     * Count unread notifications.
     */
    public static function countUnread(int $resellerId): int
    {
        return (int) ResellerDB::fetchValue(
            "SELECT COUNT(*) FROM reseller_notifications WHERE reseller_id = ? AND is_read = 0",
            [$resellerId]
        );
    }

    /**
     * Mark a notification as read.
     */
    public static function markRead(int $id): bool
    {
        return ResellerDB::execute(
            "UPDATE reseller_notifications SET is_read = 1 WHERE id = ?",
            [$id]
        ) > 0;
    }

    /**
     * Mark all notifications as read for a reseller.
     */
    public static function markAllRead(int $resellerId): int
    {
        return ResellerDB::execute(
            "UPDATE reseller_notifications SET is_read = 1 WHERE reseller_id = ? AND is_read = 0",
            [$resellerId]
        );
    }

    /**
     * Delete old notifications (older than N days).
     */
    public static function cleanup(int $daysOld = 30): int
    {
        return ResellerDB::execute(
            "DELETE FROM reseller_notifications WHERE created_at < datetime('now','localtime','-' || ? || ' days')",
            [$daysOld]
        );
    }

    /**
     * Delete a specific notification.
     */
    public static function delete(int $id): bool
    {
        return ResellerDB::execute("DELETE FROM reseller_notifications WHERE id = ?", [$id]) > 0;
    }
}
