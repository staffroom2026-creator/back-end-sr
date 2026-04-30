const express = require('express');
const schoolController = require('./school.controller');
const { protect } = require('../../middleware/authMiddleware');
const { restrictTo } = require('../../middleware/roleMiddleware');
const upload = require('../../middleware/uploadMiddleware');

const router = express.Router();

// All routes are protected and restricted to schools
router.use(protect);
router.use(restrictTo('school'));

/**
 * @route   GET /api/schools/me
 * @desc    View own school profile
 * @access  Private (School)
 */
router.get('/me', schoolController.getMe);

/**
 * @route   PATCH /api/schools/me
 * @desc    Update school profile
 * @access  Private (School)
 */
router.patch('/me', schoolController.updateMe);

/**
 * @route   POST /api/schools/upload-logo
 * @desc    Upload school logo
 * @access  Private (School)
 */
router.post('/upload-logo', upload.single('logo'), schoolController.uploadLogo);

/**
 * @route   POST /api/schools/upload-verification
 * @desc    Upload verification document
 * @access  Private (School)
 */
router.post('/upload-verification', upload.single('verification_document'), schoolController.uploadVerificationDocument);

/**
 * @route   GET /api/schools/verification-status
 * @desc    View verification status
 * @access  Private (School)
 */
router.get('/verification-status', schoolController.getVerificationStatus);

module.exports = router;
