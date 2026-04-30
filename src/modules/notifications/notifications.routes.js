const express = require('express');
const notificationsController = require('./notifications.controller');
const { protect } = require('../../middleware/authMiddleware');

const router = express.Router();

router.use(protect);

router.get('/', notificationsController.getMyNotifications);
router.patch('/:id/read', notificationsController.markRead);
router.patch('/read-all', notificationsController.markAllRead);

module.exports = router;
