<?php
/**
 * Notification Model
 */

class Notification
{
    private $db;

    public function __construct()
    {
        $this->db = new Database();
    }

    public function create($userId, $title, $message, $type = 'general', $relatedId = null, $relatedType = null)
    {
        $notificationId = hash('sha256', uniqid('', true) . microtime(true));
        $this->db->query("INSERT INTO notifications (notification_id, user_id, title, message, type, related_id, related_type, is_read) VALUES (:notification_id, :user_id, :title, :message, :type, :related_id, :related_type, 0)");
        $this->db->bind(':notification_id', $notificationId);
        $this->db->bind(':user_id', $userId);
        $this->db->bind(':title', $title);
        $this->db->bind(':message', $message);
        $this->db->bind(':type', $type);
        $this->db->bind(':related_id', $relatedId);
        $this->db->bind(':related_type', $relatedType);
        return $this->db->execute();
    }

    public function getByUser($userId, $page = 1, $perPage = 10)
    {
        $page = max(1, (int)$page);
        $perPage = min(50, max(1, (int)$perPage));
        $offset = ($page - 1) * $perPage;
        $this->db->query("SELECT COUNT(*) AS total FROM notifications WHERE user_id = :user_id");
        $this->db->bind(':user_id', $userId);
        $total = (int)$this->db->single()->total;
        $this->db->query("SELECT * FROM notifications WHERE user_id = :user_id ORDER BY created_at DESC LIMIT :limit OFFSET :offset");
        $this->db->bind(':user_id', $userId);
        $this->db->bind(':limit', $perPage, PDO::PARAM_INT);
        $this->db->bind(':offset', $offset, PDO::PARAM_INT);
        return ['data' => $this->db->resultSet(), 'pagination' => $this->pagination($page, $perPage, $total), 'unread_count' => $this->unreadCount($userId)];
    }

    public function markAsRead($id, $userId)
    {
        $this->db->query("SELECT notification_id FROM notifications WHERE notification_id = :id AND user_id = :user_id");
        $this->db->bind(':id', $id);
        $this->db->bind(':user_id', $userId);
        if (!$this->db->single()) {
            return false;
        }

        $this->db->query("UPDATE notifications SET is_read = 1 WHERE notification_id = :id AND user_id = :user_id");
        $this->db->bind(':id', $id);
        $this->db->bind(':user_id', $userId);
        $this->db->execute();
        return true;
    }

    public function unreadCount($userId)
    {
        $this->db->query("SELECT COUNT(*) AS total FROM notifications WHERE user_id = :user_id AND is_read = 0");
        $this->db->bind(':user_id', $userId);
        return (int)$this->db->single()->total;
    }

    public function delete($id, $userId)
    {
        $this->db->query("DELETE FROM notifications WHERE notification_id = :id AND user_id = :user_id");
        $this->db->bind(':id', $id);
        $this->db->bind(':user_id', $userId);
        return $this->db->execute() && $this->db->rowCount() > 0;
    }

    private function pagination($page, $perPage, $total)
    {
        $lastPage = $total > 0 ? (int)ceil($total / $perPage) : 1;
        return ['current_page' => $page, 'per_page' => $perPage, 'total' => $total, 'last_page' => $lastPage, 'has_more' => $page < $lastPage];
    }
}
?>
