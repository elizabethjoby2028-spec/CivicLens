<?php
/**
 * CivicLens — Reusable Notification Service
 * Handles in-app notifications and external delivery (email/SMS mock/integrations)
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/db.php';

class NotificationService {

    /**
     * Send notification to a citizen
     */
    public static function notifyCitizen($citizenId, $complaintId, $title, $message, $eventType = 'general') {
        if (!$citizenId) return false;
        return self::createRecord('citizen', $citizenId, $complaintId, $title, $message, $eventType);
    }

    /**
     * Send notification to an authority
     */
    public static function notifyAuthority($authorityId, $complaintId, $title, $message, $eventType = 'general') {
        if (!$authorityId) return false;
        return self::createRecord('authority', $authorityId, $complaintId, $title, $message, $eventType);
    }

    /**
     * Send notification to all active administrators
     */
    public static function notifyAdmins($complaintId, $title, $message, $eventType = 'general') {
        $db = get_db();
        $stmt = $db->query("SELECT id FROM administrators WHERE is_active = 1");
        $admins = $stmt->fetchAll(PDO::FETCH_COLUMN);
        foreach ($admins as $adminId) {
            self::createRecord('admin', $adminId, $complaintId, $title, $message, $eventType);
        }
        return true;
    }

    /**
     * Core record creator
     */
    protected static function createRecord($recipientType, $recipientId, $complaintId, $title, $message, $eventType) {
        $db = get_db();
        $stmt = $db->prepare("
            INSERT INTO notifications (recipient_type, recipient_id, complaint_id, title, message, event_type, is_read, delivery_channel)
            VALUES (?, ?, ?, ?, ?, ?, 0, 'in_app')
        ");
        $success = $stmt->execute([$recipientType, $recipientId, $complaintId, $title, $message, $eventType]);

        // Attempt external delivery if configured (logged for audit)
        if ($success) {
            self::attemptExternalDelivery($recipientType, $recipientId, $title, $message);
        }

        return $success;
    }

    /**
     * Attempt external email or SMS delivery if credentials exist
     */
    protected static function attemptExternalDelivery($recipientType, $recipientId, $title, $message) {
        $mailEnabled = env('MAIL_ENABLED', false);
        $smsEnabled = env('SMS_ENABLED', false);

        if ($mailEnabled) {
            // Note: Mail delivery will be dispatched here if mail transport is configured.
            error_log("[Notification Mail] To {$recipientType} #{$recipientId}: {$title}");
        }

        if ($smsEnabled) {
            error_log("[Notification SMS] To {$recipientType} #{$recipientId}: {$title}");
        }
    }

    /**
     * Fetch unread notifications for a user
     */
    public static function getUnread($recipientType, $recipientId, $limit = 10) {
        $db = get_db();
        $stmt = $db->prepare("
            SELECT n.*, c.complaint_id as ref_code 
            FROM notifications n
            LEFT JOIN complaints c ON n.complaint_id = c.id
            WHERE n.recipient_type = ? AND n.recipient_id = ? AND n.is_read = 0
            ORDER BY n.created_at DESC
            LIMIT ?
        ");
        $stmt->bindValue(1, $recipientType);
        $stmt->bindValue(2, $recipientId, PDO::PARAM_INT);
        $stmt->bindValue(3, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Fetch all notifications for a user with pagination
     */
    public static function getAll($recipientType, $recipientId, $limit = 30) {
        $db = get_db();
        $stmt = $db->prepare("
            SELECT n.*, c.complaint_id as ref_code 
            FROM notifications n
            LEFT JOIN complaints c ON n.complaint_id = c.id
            WHERE n.recipient_type = ? AND n.recipient_id = ?
            ORDER BY n.created_at DESC
            LIMIT ?
        ");
        $stmt->bindValue(1, $recipientType);
        $stmt->bindValue(2, $recipientId, PDO::PARAM_INT);
        $stmt->bindValue(3, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Mark a specific notification as read
     */
    public static function markAsRead($notificationId, $recipientType, $recipientId) {
        $db = get_db();
        $stmt = $db->prepare("
            UPDATE notifications 
            SET is_read = 1 
            WHERE id = ? AND recipient_type = ? AND recipient_id = ?
        ");
        return $stmt->execute([$notificationId, $recipientType, $recipientId]);
    }

    /**
     * Mark all notifications as read for a user
     */
    public static function markAllAsRead($recipientType, $recipientId) {
        $db = get_db();
        $stmt = $db->prepare("
            UPDATE notifications 
            SET is_read = 1 
            WHERE recipient_type = ? AND recipient_id = ?
        ");
        return $stmt->execute([$recipientType, $recipientId]);
    }

    /**
     * Get unread count
     */
    public static function getUnreadCount($recipientType, $recipientId) {
        $db = get_db();
        $stmt = $db->prepare("
            SELECT COUNT(*) FROM notifications 
            WHERE recipient_type = ? AND recipient_id = ? AND is_read = 0
        ");
        $stmt->execute([$recipientType, $recipientId]);
        return (int)$stmt->fetchColumn();
    }
}
