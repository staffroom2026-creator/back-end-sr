const express = require('express');
const jobController = require('./job.controller');
const { protect } = require('../../middleware/authMiddleware');
const { restrictTo } = require('../../middleware/roleMiddleware');

const router = express.Router();

// ─── Public Routes ───────────────────────────────────────────────────────────
router.get('/', jobController.getJobs);
router.get('/:id', jobController.getJob);

// ─── Protected Routes (Requires Login) ───────────────────────────────────────
router.use(protect);

// School Specific Routes
router.post('/', restrictTo('school'), jobController.createJob);
router.get('/my/posted', restrictTo('school'), jobController.getMyJobs);
router.get('/:id/applications', restrictTo('school'), jobController.getJobApplications);
router.patch('/:id', restrictTo('school', 'admin'), jobController.updateJob);
router.delete('/:id', restrictTo('school', 'admin'), jobController.deleteJob);

// Admin Specific Routes
router.patch('/:id/approve', restrictTo('admin'), jobController.approveJob);
router.patch('/:id/reject', restrictTo('admin'), jobController.rejectJob);
router.patch('/:id/feature', restrictTo('admin'), jobController.toggleFeatured);

module.exports = router;
