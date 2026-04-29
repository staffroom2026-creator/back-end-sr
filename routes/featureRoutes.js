const express = require('express');
const savedJobController = require('../controllers/savedJobController');
const notificationController = require('../controllers/notificationController');
const authController = require('../controllers/authController');

const router = express.Router();

router.use(authController.protect);

// Saved Jobs
router.post('/saved-jobs/:jobId', savedJobController.saveJob);
router.get('/saved-jobs', savedJobController.getSavedJobs);
router.delete('/saved-jobs/:jobId', savedJobController.unsaveJob);

// Notifications
router.get('/notifications', notificationController.getMyNotifications);
router.patch('/notifications/:id/read', notificationController.markAsRead);

module.exports = router;
