const notificationsService = require('./notifications.service');
const { success, error } = require('../../utils/response');

class NotificationsController {
    /**
     * @desc    Get user notifications
     */
    async getMyNotifications(req, res) {
        try {
            const notifications = await notificationsService.getUserNotifications(req.user.id);
            return success(res, notifications, 'Notifications retrieved');
        } catch (err) {
            console.error('Error in getMyNotifications:', err);
            return error(res, 'Failed to retrieve notifications');
        }
    }

    /**
     * @desc    Mark single notification as read
     */
    async markRead(req, res) {
        try {
            await notificationsService.markAsRead(req.params.id, req.user.id);
            return success(res, null, 'Notification marked as read');
        } catch (err) {
            console.error('Error in markRead:', err);
            return error(res, 'Failed to update notification');
        }
    }

    /**
     * @desc    Mark all notifications as read
     */
    async markAllRead(req, res) {
        try {
            await notificationsService.markAllAsRead(req.user.id);
            return success(res, null, 'All notifications marked as read');
        } catch (err) {
            console.error('Error in markAllRead:', err);
            return error(res, 'Failed to update notifications');
        }
    }
}

module.exports = new NotificationsController();
