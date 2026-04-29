const express = require('express');
const adminController = require('../controllers/adminController');
const authController = require('../controllers/authController');

const router = express.Router();

router.use(authController.protect);
router.use(authController.restrictTo('admin'));

router.get('/stats', adminController.getStats);
router.get('/verifications', adminController.getPendingVerifications);
router.patch('/verifications/:id', adminController.verifySchool);

module.exports = router;
