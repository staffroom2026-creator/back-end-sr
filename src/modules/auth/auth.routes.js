const express = require('express');
const authController = require('./auth.controller');
const { validate, registerTeacherSchema, registerSchoolSchema, loginSchema, changePasswordSchema, forgotPasswordSchema, resetPasswordSchema } = require('../../utils/validators');
const { protect } = require('../../middleware/authMiddleware');

const router = express.Router();

// Public Routes
router.post('/register/teacher', validate(registerTeacherSchema), authController.registerTeacher);
router.post('/register/school', validate(registerSchoolSchema), authController.registerSchool);
router.post('/login', validate(loginSchema), authController.login);
router.post('/forgot-password', validate(forgotPasswordSchema), authController.forgotPassword);
router.post('/reset-password', validate(resetPasswordSchema), authController.resetPassword);

// Protected Routes
router.use(protect);

router.get('/me', authController.getMe);
router.post('/refresh-token', authController.refreshToken);
router.post('/change-password', validate(changePasswordSchema), authController.changePassword);
router.post('/logout', authController.logout);

module.exports = router;
