const express = require('express');
const savedJobsController = require('./savedJobs.controller');
const { protect } = require('../../middleware/authMiddleware');
const { restrictTo } = require('../../middleware/roleMiddleware');

const router = express.Router();

// All saved jobs routes are protected and restricted to teachers
router.use(protect);
router.use(restrictTo('teacher'));

router.get('/', savedJobsController.getSavedJobs);
router.post('/:jobId', savedJobsController.saveJob);
router.delete('/:jobId', savedJobsController.unsaveJob);

module.exports = router;
