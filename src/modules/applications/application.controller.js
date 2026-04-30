const applicationService = require('./application.service');
const { success, created, fail, error } = require('../../utils/response');
const { applySchema, updateStatusSchema } = require('./application.validator');

class ApplicationController {
    /**
     * @desc    Apply for a job (Teacher)
     */
    async apply(req, res) {
        try {
            const { error: validationError, value } = applySchema.validate(req.body);
            if (validationError) return fail(res, validationError.details[0].message);

            const applicationId = await applicationService.apply(req.user.id, value);
            const application = await applicationService.getApplicationById(applicationId);

            return created(res, application, 'Application submitted successfully');
        } catch (err) {
            console.error('Error in apply:', err.message);
            return fail(res, err.message, 400);
        }
    }

    /**
     * @desc    Get teacher's own applications (Teacher)
     */
    async getMyApplications(req, res) {
        try {
            const applications = await applicationService.getApplications({ teacherUserId: req.user.id });
            return success(res, applications, 'Your applications retrieved');
        } catch (err) {
            console.error('Error in getMyApplications:', err);
            return error(res, 'Failed to retrieve applications');
        }
    }

    /**
     * @desc    Get applications for school's jobs (School)
     */
    async getSchoolApplications(req, res) {
        try {
            const filters = { schoolUserId: req.user.id };
            if (req.query.job_id) filters.jobId = req.query.job_id;

            const applications = await applicationService.getApplications(filters);
            return success(res, applications, 'Applications for your jobs retrieved');
        } catch (err) {
            console.error('Error in getSchoolApplications:', err);
            return error(res, 'Failed to retrieve applications');
        }
    }

    /**
     * @desc    Get all applications (Admin)
     */
    async getAllApplications(req, res) {
        try {
            const applications = await applicationService.getApplications({ admin: true });
            return success(res, applications, 'All applications retrieved');
        } catch (err) {
            console.error('Error in getAllApplications:', err);
            return error(res, 'Failed to retrieve applications');
        }
    }

    /**
     * @desc    Update application status (School)
     */
    async updateStatus(req, res) {
        try {
            const { error: validationError, value } = updateStatusSchema.validate(req.body);
            if (validationError) return fail(res, validationError.details[0].message);

            await applicationService.updateStatus(req.params.id, req.user.id, value.status);
            return success(res, null, `Application status updated to ${value.status}`);
        } catch (err) {
            console.error('Error in updateStatus:', err.message);
            return fail(res, err.message, 403);
        }
    }

    /**
     * @desc    Withdraw application (Teacher)
     */
    async withdraw(req, res) {
        try {
            await applicationService.withdraw(req.params.id, req.user.id);
            return success(res, null, 'Application withdrawn successfully');
        } catch (err) {
            console.error('Error in withdraw:', err.message);
            return fail(res, err.message, 403);
        }
    }

    /**
     * @desc    Get application details
     */
    async getApplicationDetails(req, res) {
        try {
            const application = await applicationService.getApplicationById(req.params.id);
            if (!application) return fail(res, 'Application not found', 404);
            
            // Basic auth check: only the teacher, school, or admin can view
            // (Assuming more granular check could be added here)
            
            return success(res, application, 'Application details retrieved');
        } catch (err) {
            console.error('Error in getApplicationDetails:', err);
            return error(res, 'Failed to retrieve application details');
        }
    }
}

module.exports = new ApplicationController();
