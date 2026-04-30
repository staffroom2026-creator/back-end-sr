const { pool } = require('../../config/db');

class NotificationsService {
    /**
     * Create a notification for a user
     */
    async createNotification(userId, data) {
        const { title, message, type, relatedEntityType, relatedEntityId } = data;
        
        const query = `
            INSERT INTO notifications (user_id, title, message, type, related_entity_type, related_entity_id)
            VALUES (?, ?, ?, ?, ?, ?)
        `;
        
        const [result] = await pool.execute(query, [
            userId, title, message, type, relatedEntityType || null, relatedEntityId || null
        ]);
        
        return result.insertId;
    }

    /**
     * Get user notifications
     */
    async getUserNotifications(userId) {
        const query = `
            SELECT * FROM notifications 
            WHERE user_id = ? 
            ORDER BY is_read ASC, created_at DESC 
            LIMIT 50
        `;
        const [rows] = await pool.execute(query, [userId]);
        return rows;
    }

    /**
     * Mark notification as read
     */
    async markAsRead(notificationId, userId) {
        const [result] = await pool.execute(
            'UPDATE notifications SET is_read = TRUE WHERE id = ? AND user_id = ?',
            [notificationId, userId]
        );
        return result.affectedRows > 0;
    }

    /**
     * Mark all as read
     */
    async markAllAsRead(userId) {
        const [result] = await pool.execute(
            'UPDATE notifications SET is_read = TRUE WHERE user_id = ?',
            [userId]
        );
        return result.affectedRows;
    }
}

module.exports = new NotificationsService();
