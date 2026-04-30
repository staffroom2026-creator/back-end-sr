const schoolService = require('./school.service');
const { success, fail, error } = require('../../utils/response');
const { updateProfileSchema } = require('./school.validator');

class SchoolController {
    /**
     * Get current school profile
     */
    async getMe(req, res) {
        try {
            const profile = await schoolService.getProfileByUserId(req.user.id);
            if (!profile) {
                return fail(res, 'School profile not found', 404);
            }
            return success(res, profile, 'School profile retrieved successfully');
        } catch (err) {
            console.error('Error in getMe:', err);
            return error(res, 'Failed to retrieve school profile');
        }
    }

    /**
     * Update current school profile
     */
    async updateMe(req, res) {
        try {
            // Validation
            const { error: validationError, value } = updateProfileSchema.validate(req.body, { abortEarly: false });
            if (validationError) {
                const errors = validationError.details.map(detail => ({
                    field: detail.path[0],
                    message: detail.message
                }));
                return fail(res, 'Validation failed', 400, errors);
            }

            const updated = await schoolService.updateProfile(req.user.id, value);
            if (!updated) {
                return fail(res, 'School profile could not be updated or no changes made', 400);
            }

            const profile = await schoolService.getProfileByUserId(req.user.id);
            return success(res, profile, 'School profile updated successfully');
        } catch (err) {
            console.error('Error in updateMe:', err);
            return error(res, 'Failed to update school profile');
        }
    }

    /**
     * Upload school logo
     */
    async uploadLogo(req, res) {
        try {
            if (!req.file) {
                return fail(res, 'Please upload a logo image', 400);
            }

            const logoUrl = `/uploads/${req.file.filename}`;
            await schoolService.updateLogo(req.user.id, logoUrl);

            return success(res, { logo_url: logoUrl }, 'School logo uploaded successfully');
        } catch (err) {
            console.error('Error in uploadLogo:', err);
            return error(res, 'Failed to upload logo');
        }
    }

    /**
     * Upload verification document
     */
    async uploadVerificationDocument(req, res) {
        try {
            if (!req.file) {
                return fail(res, 'Please upload a verification document (PDF or image)', 400);
            }

            const docUrl = `/uploads/${req.file.filename}`;
            await schoolService.updateVerificationDocument(req.user.id, docUrl);

            return success(res, { verification_document_url: docUrl }, 'Verification document uploaded successfully. Status set to pending.');
        } catch (err) {
            console.error('Error in uploadVerificationDocument:', err);
            return error(res, 'Failed to upload verification document');
        }
    }

    /**
     * Get verification status
     */
    async getVerificationStatus(req, res) {
        try {
            const status = await schoolService.getVerificationStatus(req.user.id);
            if (!status) {
                return fail(res, 'Verification status not found', 404);
            }
            return success(res, status, 'Verification status retrieved successfully');
        } catch (err) {
            console.error('Error in getVerificationStatus:', err);
            return error(res, 'Failed to retrieve verification status');
        }
    }
}

module.exports = new SchoolController();
