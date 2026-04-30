const express = require('express');
const adminController = require('./admin.controller');
const { protect } = require('../../middleware/authMiddleware');
const { restrictTo } = require('../../middleware/roleMiddleware');

const router = express.Router();

// All routes are protected and restricted to system admins
router.use(protect);
router.use(restrictTo('admin'));

/**
 * Dashboard & Logs
 */
router.get('/stats', adminController.getStats);
router.get('/logs', adminController.getActivityLogs);

/**
 * User Management
 */
router.get('/users', adminController.getUsers);
router.patch('/users/:userId/status', adminController.toggleUserStatus);

/**
 * School Moderation
 */
router.patch('/schools/:schoolId/verify', adminController.verifySchool);

/**
 * Job & Application Oversight
 */
router.get('/jobs', adminController.getAllJobs);
router.patch('/jobs/:jobId/moderate', adminController.moderateJob);
router.get('/applications', adminController.getAllApplications);

module.exports = router;
