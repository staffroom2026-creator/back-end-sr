const express = require('express');
const teacherController = require('./teacher.controller');
const { protect } = require('../../middleware/authMiddleware');
const { restrictTo } = require('../../middleware/roleMiddleware');
const { validate, teacherProfileSchema } = require('../../utils/validators');
const { upload } = require('../../middleware/uploadMiddleware');

const router = express.Router();

// All teacher routes require authentication and teacher role
router.use(protect);
router.use(restrictTo('teacher'));

// Profile Routes
router.get('/profile', teacherController.getMyProfile);
router.put('/profile', validate(teacherProfileSchema), teacherController.updateMyProfile);

// Document Upload
router.post('/upload-cv', upload.single('cv'), teacherController.uploadCv);

module.exports = router;
