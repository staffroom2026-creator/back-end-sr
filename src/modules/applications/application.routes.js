const express = require('express');
const applicationController = require('./application.controller');
const { protect } = require('../../middleware/authMiddleware');
const { restrictTo } = require('../../middleware/roleMiddleware');

const router = express.Router();

// All application routes are protected
router.use(protect);

/**
 * @route   POST /api/applications
 * @desc    Apply for a job
 * @access  Private (Teacher)
 */
router.post('/', restrictTo('teacher'), applicationController.apply);

/**
 * @route   GET /api/applications/me
 * @desc    Get own applications
 * @access  Private (Teacher)
 */
router.get('/me', restrictTo('teacher'), applicationController.getMyApplications);

/**
 * @route   GET /api/applications/school
 * @desc    Get applications for school's jobs
 * @access  Private (School)
 */
router.get('/school', restrictTo('school'), applicationController.getSchoolApplications);

/**
 * @route   GET /api/applications/all
 * @desc    Get all applications
 * @access  Private (Admin)
 */
router.get('/all', restrictTo('admin'), applicationController.getAllApplications);

/**
 * @route   GET /api/applications/:id
 * @desc    Get application details
 * @access  Private (Teacher/School/Admin)
 */
router.get('/:id', applicationController.getApplicationDetails);

/**
 * @route   PATCH /api/applications/:id/status
 * @desc    Update application status
 * @access  Private (School)
 */
router.patch('/:id/status', restrictTo('school'), applicationController.updateStatus);

/**
 * @route   PATCH /api/applications/:id/withdraw
 * @desc    Withdraw application
 * @access  Private (Teacher)
 */
router.patch('/:id/withdraw', restrictTo('teacher'), applicationController.withdraw);

module.exports = router;
