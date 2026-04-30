const jobService = require('./job.service');
const { success, created, fail, error } = require('../../utils/response');
const { createJobSchema, updateJobSchema, filterJobsSchema } = require('./job.validator');

class JobController {
    /**
     * @desc    Browse/Search jobs (Public)
     */
    async getJobs(req, res) {
        try {
            const { error: validationError, value: filters } = filterJobsSchema.validate(req.query);
            if (validationError) return fail(res, validationError.details[0].message);

            const result = await jobService.getAllJobs(filters);
            return success(res, result, 'Jobs retrieved successfully');
        } catch (err) {
            console.error('Error in getJobs:', err);
            return error(res, 'Failed to retrieve jobs');
        }
    }

    /**
     * @desc    View single job details (Public)
     */
    async getJob(req, res) {
        try {
            const job = await jobService.getJobById(req.params.id);
            if (!job) return fail(res, 'Job not found', 404);
            return success(res, job, 'Job details retrieved');
        } catch (err) {
            console.error('Error in getJob:', err);
            return error(res, 'Failed to retrieve job details');
        }
    }

    /**
     * @desc    Create job (School)
     */
    async createJob(req, res) {
        try {
            const { error: validationError, value } = createJobSchema.validate(req.body);
            if (validationError) return fail(res, validationError.details[0].message);

            const jobId = await jobService.createJob(req.user.id, value);
            const job = await jobService.getJobById(jobId);

            return created(res, job, 'Job posted successfully and is pending approval');
        } catch (err) {
            console.error('Error in createJob:', err);
            return error(res, 'Failed to create job');
        }
    }

    /**
     * @desc    Update job (School)
     */
    async updateJob(req, res) {
        try {
            const { error: validationError, value } = updateJobSchema.validate(req.body);
            if (validationError) return fail(res, validationError.details[0].message);

            const updated = await jobService.updateJob(req.params.id, req.user.id, value);
            if (!updated) return fail(res, 'Job not found or unauthorized', 404);

            const job = await jobService.getJobById(req.params.id);
            return success(res, job, 'Job updated successfully');
        } catch (err) {
            console.error('Error in updateJob:', err);
            return error(res, 'Failed to update job');
        }
    }

    /**
     * @desc    Delete/Close job (School/Admin)
     */
    async deleteJob(req, res) {
        try {
            // If admin, schoolUserId is null (skip ownership check)
            const schoolUserId = req.user.role === 'admin' ? null : req.user.id;
            const deleted = await jobService.deleteJob(req.params.id, schoolUserId);
            
            if (!deleted) return fail(res, 'Job not found or unauthorized', 404);
            return success(res, null, 'Job removed successfully');
        } catch (err) {
            console.error('Error in deleteJob:', err);
            return error(res, 'Failed to remove job');
        }
    }

    /**
     * @desc    View own jobs (School)
     */
    async getMyJobs(req, res) {
        try {
            const jobs = await jobService.getJobsBySchool(req.user.id);
            return success(res, jobs, 'Your posted jobs retrieved');
        } catch (err) {
            console.error('Error in getMyJobs:', err);
            return error(res, 'Failed to retrieve your jobs');
        }
    }

    /**
     * @desc    View job applications (School)
     */
    async getJobApplications(req, res) {
        try {
            const applications = await jobService.getApplicationsByJob(req.params.id, req.user.id);
            return success(res, applications, 'Job applications retrieved');
        } catch (err) {
            console.error('Error in getJobApplications:', err.message);
            return fail(res, err.message, 403);
        }
    }

    /**
     * @desc    Approve job (Admin)
     */
    async approveJob(req, res) {
        try {
            await jobService.updateApprovalStatus(req.params.id, 'approved');
            return success(res, null, 'Job approved successfully');
        } catch (err) {
            console.error('Error in approveJob:', err);
            return error(res, 'Failed to approve job');
        }
    }

    /**
     * @desc    Reject job (Admin)
     */
    async rejectJob(req, res) {
        try {
            const { reason } = req.body;
            await jobService.updateApprovalStatus(req.params.id, 'rejected');
            // In a real app, you'd send an email to the school here
            return success(res, null, 'Job rejected');
        } catch (err) {
            console.error('Error in rejectJob:', err);
            return error(res, 'Failed to reject job');
        }
    }

    /**
     * @desc    Feature/Unfeature job (Admin)
     */
    async toggleFeatured(req, res) {
        try {
            const { is_featured } = req.body;
            await jobService.toggleFeatured(req.params.id, is_featured);
            return success(res, null, `Job ${is_featured ? 'featured' : 'unfeatured'} successfully`);
        } catch (err) {
            console.error('Error in toggleFeatured:', err);
            return error(res, 'Failed to update featured status');
        }
    }
}

module.exports = new JobController();
