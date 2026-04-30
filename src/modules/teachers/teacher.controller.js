const teacherService = require('./teacher.service');
const { success, fail } = require('../../utils/response');

/**
 * Get current teacher's profile
 */
const getMyProfile = async (req, res, next) => {
    try {
        const profile = await teacherService.getProfileByUserId(req.user.id);
        
        if (!profile) {
            return fail(res, 'Profile not found.', 404);
        }

        // Parse JSON strings back to arrays if needed
        if (profile.subjects && typeof profile.subjects === 'string') {
            try {
                profile.subjects = JSON.parse(profile.subjects);
            } catch (e) {
                // If it's not valid JSON, leave it as is or handle accordingly
            }
        }

        return success(res, { profile }, 'Profile retrieved successfully.');
    } catch (err) {
        next(err);
    }
};

/**
 * Update teacher's profile
 */
const updateMyProfile = async (req, res, next) => {
    try {
        // Validation ensures only valid fields are processed
        const updatedProfile = await teacherService.updateProfile(req.user.id, req.body);
        
        if (updatedProfile && updatedProfile.subjects && typeof updatedProfile.subjects === 'string') {
            try {
                updatedProfile.subjects = JSON.parse(updatedProfile.subjects);
            } catch (e) {}
        }

        return success(res, { profile: updatedProfile }, 'Profile updated successfully.');
    } catch (err) {
        next(err);
    }
};

/**
 * Upload CV
 */
const uploadCv = async (req, res, next) => {
    try {
        if (!req.file) {
            return fail(res, 'Please upload a PDF or Image file.', 400);
        }

        // Construct URL path based on your static serving setup
        const cvUrl = `/uploads/${req.file.filename}`;
        
        const updatedProfile = await teacherService.updateCvUrl(req.user.id, cvUrl);

        return success(res, { profile: updatedProfile, cv_url: cvUrl }, 'CV uploaded successfully.');
    } catch (err) {
        next(err);
    }
};

module.exports = {
    getMyProfile,
    updateMyProfile,
    uploadCv
};
