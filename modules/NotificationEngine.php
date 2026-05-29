<?php
/**
 * WaMark - Notification Engine Module
 * Handles in-app notifications
 */

class NotificationEngine {
    private $db;

    public function __construct() {
        global $db;
        $this->db = $db;
    }

    /**
     * Create notification
     */
    public function send($userId, $type, $title, $message = '', $actionUrl = '', $icon = 'bell') {
        return $this->db->insert('notifications', [
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'action_url' => $actionUrl,
            'icon' => $icon,
            'is_read' => 0,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Send to multiple users
     */
    public function broadcast($userIds, $type, $title, $message = '', $actionUrl = '') {
        foreach ($userIds as $uid) {
            $this->send($uid, $type, $title, $message, $actionUrl);
        }
    }

    /**
     * Send to all users with role
     */
    public function sendToRole($role, $type, $title, $message = '', $actionUrl = '') {
        $users = $this->db->fetchAll("SELECT id FROM " . $this->db->table('users') . " WHERE role = ? AND status = 'active'", [$role]);
        foreach ($users as $u) {
            $this->send($u['id'], $type, $title, $message, $actionUrl);
        }
    }

    /**
     * Get unread notifications for user
     */
    public function getUnread($userId, $limit = 10) {
        return $this->db->fetchAll(
            "SELECT * FROM " . $this->db->table('notifications') . " WHERE user_id = ? AND is_read = 0 ORDER BY created_at DESC LIMIT ?",
            [$userId, $limit]
        );
    }

    /**
     * Get all notifications for user
     */
    public function getAll($userId, $limit = 50) {
        return $this->db->fetchAll(
            "SELECT * FROM " . $this->db->table('notifications') . " WHERE user_id = ? ORDER BY created_at DESC LIMIT ?",
            [$userId, $limit]
        );
    }

    /**
     * Mark as read
     */
    public function markRead($notificationId, $userId) {
        return $this->db->update('notifications', ['is_read' => 1, 'read_at' => date('Y-m-d H:i:s')], 'id = ? AND user_id = ?', [$notificationId, $userId]);
    }

    /**
     * Mark all as read
     */
    public function markAllRead($userId) {
        return $this->db->query(
            "UPDATE " . $this->db->table('notifications') . " SET is_read = 1, read_at = NOW() WHERE user_id = ? AND is_read = 0",
            [$userId]
        );
    }

    /**
     * Get unread count
     */
    public function getUnreadCount($userId) {
        return $this->db->count('notifications', 'user_id = ? AND is_read = 0', [$userId]);
    }
}
