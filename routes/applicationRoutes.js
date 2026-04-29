const express = require('express');
const applicationController = require('../controllers/applicationController');
const authController = require('../controllers/authController');

const router = express.Router();

router.use(authController.protect);

router.post('/apply/:jobId', authController.restrictTo('teacher'), applicationController.applyForJob);
router.get('/my-applications', authController.restrictTo('teacher'), applicationController.getMyApplications);
router.get('/job/:jobId', authController.restrictTo('school'), applicationController.getJobApplications);
router.patch('/:id/status', authController.restrictTo('school'), applicationController.updateApplicationStatus);

module.exports = router;
