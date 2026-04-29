const db = require('../config/db');
const catchAsync = require('../utils/catchAsync');

exports.getMyNotifications = catchAsync(async (req, res, next) => {
    const [notifications] = await db.execute(
        'SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC',
        [req.user.id]
    );

    res.status(200).json({
        status: 'success',
        results: notifications.length,
        data: {
            notifications
        }
    });
});

exports.markAsRead = catchAsync(async (req, res, next) => {
    await db.execute(
        'UPDATE notifications SET is_read = TRUE WHERE id = ? AND user_id = ?',
        [req.params.id, req.user.id]
    );

    res.status(200).json({
        status: 'success',
        message: 'Notification marked as read'
    });
});
