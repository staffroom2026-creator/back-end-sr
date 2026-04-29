const express = require('express');
const jobController = require('../controllers/jobController');
const authController = require('../controllers/authController');

const router = express.Router();

router.get('/', jobController.getAllJobs);
router.get('/:id', jobController.getJob);

// All routes after this middleware are protected
router.use(authController.protect);

router.post('/', authController.restrictTo('school'), jobController.createJob);
router.put('/:id', authController.restrictTo('school'), jobController.updateJob);
router.delete('/:id', authController.restrictTo('school'), jobController.deleteJob);

module.exports = router;
