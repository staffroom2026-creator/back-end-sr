const adminService = require('./admin.service');
const jobService = require('../jobs/job.service');
const schoolService = require('../schools/school.service');
const applicationService = require('../applications/application.service');
const { success, fail, error } = require('../../utils/response');

class AdminController {
    /**
     * @desc    Get dashboard statistics
     */
    async getStats(req, res) {
        try {
            const stats = await adminService.getDashboardStats();
            return success(res, stats, 'Dashboard statistics retrieved');
        } catch (err) {
            console.error('Error in getStats:', err);
            return error(res, 'Failed to retrieve stats');
        }
    }

    /**
     * @desc    List all users (Teachers/Schools/Admins)
     */
    async getUsers(req, res) {
        try {
            const result = await adminService.getAllUsers(req.query);
            return success(res, result, 'Users retrieved successfully');
        } catch (err) {
            console.error('Error in getUsers:', err);
            return error(res, 'Failed to retrieve users');
        }
    }

    /**
     * @desc    Suspend or Activate user
     */
    async toggleUserStatus(req, res) {
        try {
            const { userId } = req.params;
            const { active } = req.body;
            
            const updated = await adminService.updateUserStatus(userId, active);
            if (!updated) return fail(res, 'User not found', 404);

            await adminService.logAction(req.user.id, active ? 'ACTIVATE_USER' : 'SUSPEND_USER', 'user', userId);
            
            return success(res, null, `User ${active ? 'activated' : 'suspended'} successfully`);
        } catch (err) {
            console.error('Error in toggleUserStatus:', err);
            return error(res, 'Failed to update user status');
        }
    }

    /**
     * @desc    Verify/Reject school profile
     */
    async verifySchool(req, res) {
        try {
            const { schoolId } = req.params;
            const { status } = req.body; // 'verified' or 'rejected'

            await schoolService.updateVerificationStatus(schoolId, status);
            await adminService.logAction(req.user.id, `VERIFY_SCHOOL_${status.toUpperCase()}`, 'school', schoolId);

            return success(res, null, `School verification ${status} successfully`);
        } catch (err) {
            console.error('Error in verifySchool:', err);
            return error(res, 'Failed to verify school');
        }
    }

    /**
     * @desc    Approve/Reject job posting
     */
    async moderateJob(req, res) {
        try {
            const { jobId } = req.params;
            const { status } = req.body; // 'approved' or 'rejected'

            await jobService.updateApprovalStatus(jobId, status);
            await adminService.logAction(req.user.id, `MODERATE_JOB_${status.toUpperCase()}`, 'job', jobId);

            return success(res, null, `Job ${status} successfully`);
        } catch (err) {
            console.error('Error in moderateJob:', err);
            return error(res, 'Failed to moderate job');
        }
    }

    /**
     * @desc    View all activity logs
     */
    async getActivityLogs(req, res) {
        try {
            const { page, limit } = req.query;
            const result = await adminService.getActivityLogs(page, limit);
            return success(res, result, 'Activity logs retrieved');
        } catch (err) {
            console.error('Error in getActivityLogs:', err);
            return error(res, 'Failed to retrieve logs');
        }
    }

    /**
     * @desc    View all jobs (with unfiltered access)
     */
    async getAllJobs(req, res) {
        try {
            // Use existing job service with admin-specific filters (all jobs, regardless of status)
            const filters = { ...req.query, approval_status: null, status: null };
            const result = await jobService.getAllJobs(filters);
            return success(res, result, 'All jobs retrieved');
        } catch (err) {
            console.error('Error in getAllJobs:', err);
            return error(res, 'Failed to retrieve jobs');
        }
    }

    /**
     * @desc    View all applications
     */
    async getAllApplications(req, res) {
        try {
            const result = await applicationService.getApplications({ admin: true });
            return success(res, result, 'All applications retrieved');
        } catch (err) {
            console.error('Error in getAllApplications:', err);
            return error(res, 'Failed to retrieve applications');
        }
    }
}

module.exports = new AdminController();
