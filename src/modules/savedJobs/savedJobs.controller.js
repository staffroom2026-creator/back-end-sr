const savedJobsService = require('./savedJobs.service');
const { success, created, fail, error } = require('../../utils/response');

class SavedJobsController {
    /**
     * @desc    Save a job
     */
    async saveJob(req, res) {
        try {
            const { jobId } = req.params;
            await savedJobsService.saveJob(req.user.id, jobId);
            return created(res, null, 'Job saved successfully');
        } catch (err) {
            console.error('Error in saveJob:', err.message);
            return fail(res, err.message, 400);
        }
    }

    /**
     * @desc    Unsave a job
     */
    async unsaveJob(req, res) {
        try {
            const { jobId } = req.params;
            const removed = await savedJobsService.unsaveJob(req.user.id, jobId);
            if (!removed) return fail(res, 'Job not found in saved list', 404);
            return success(res, null, 'Job removed from saved list');
        } catch (err) {
            console.error('Error in unsaveJob:', err);
            return error(res, 'Failed to remove job');
        }
    }

    /**
     * @desc    Get all saved jobs
     */
    async getSavedJobs(req, res) {
        try {
            const jobs = await savedJobsService.getSavedJobs(req.user.id);
            return success(res, jobs, 'Saved jobs retrieved');
        } catch (err) {
            console.error('Error in getSavedJobs:', err);
            return error(res, 'Failed to retrieve saved jobs');
        }
    }
}

module.exports = new SavedJobsController();
