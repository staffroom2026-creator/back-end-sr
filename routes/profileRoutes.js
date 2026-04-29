const express = require('express');
const profileController = require('../controllers/profileController');
const authController = require('../controllers/authController');
const upload = require('../middleware/uploadMiddleware');

const router = express.Router();

// Protect all routes after this middleware
router.use(authController.protect);

router.get('/me', profileController.getMe);
router.put('/teacher', authController.restrictTo('teacher'), profileController.updateTeacherProfile);
router.put('/school', authController.restrictTo('school'), profileController.updateSchoolProfile);

// File Uploads
router.post('/upload-cv', authController.restrictTo('teacher'), upload.single('cv'), profileController.uploadCV);
router.post('/upload-logo', authController.restrictTo('school'), upload.single('logo'), profileController.uploadLogo);

module.exports = router;
