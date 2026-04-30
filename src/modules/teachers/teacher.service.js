const { pool } = require('../../config/db');

class TeacherService {
    /**
     * Get Teacher Profile by user_id
     */
    async getProfileByUserId(userId) {
        const query = `
            SELECT 
                tp.*, 
                u.name, 
                u.email,
                u.is_email_verified
            FROM teacher_profiles tp
            JOIN users u ON tp.user_id = u.id
            WHERE u.id = ? AND u.role = 'teacher'
        `;
        const [rows] = await pool.execute(query, [userId]);
        return rows[0];
    }

    /**
     * Update Teacher Profile
     */
    async updateProfile(userId, profileData) {
        const {
            headline,
            bio,
            qualification,
            years_of_experience,
            subjects,
            preferred_location,
            expected_salary,
            employment_type,
            availability_status,
            phone,
            trcn_number
        } = profileData;

        // Calculate a basic completion percentage based on field presence
        let filledFields = 0;
        const totalFields = 9; // Core fields count
        if (headline) filledFields++;
        if (bio) filledFields++;
        if (qualification) filledFields++;
        if (years_of_experience !== undefined && years_of_experience !== null) filledFields++;
        if (subjects) filledFields++;
        if (preferred_location) filledFields++;
        if (expected_salary) filledFields++;
        if (employment_type) filledFields++;
        if (phone) filledFields++;

        const completionPercentage = Math.round((filledFields / totalFields) * 100);

        const query = `
            UPDATE teacher_profiles 
            SET 
                headline = COALESCE(?, headline),
                bio = COALESCE(?, bio),
                qualification = COALESCE(?, qualification),
                years_of_experience = COALESCE(?, years_of_experience),
                subjects = COALESCE(?, subjects),
                preferred_location = COALESCE(?, preferred_location),
                expected_salary = COALESCE(?, expected_salary),
                employment_type = COALESCE(?, employment_type),
                availability_status = COALESCE(?, availability_status),
                phone = COALESCE(?, phone),
                trcn_number = COALESCE(?, trcn_number),
                profile_completion_percentage = ?
            WHERE user_id = ?
        `;

        await pool.execute(query, [
            headline || null,
            bio || null,
            qualification || null,
            years_of_experience !== undefined ? years_of_experience : null,
            subjects ? JSON.stringify(subjects) : null,
            preferred_location || null,
            expected_salary || null,
            employment_type || null,
            availability_status || null,
            phone || null,
            trcn_number || null,
            completionPercentage,
            userId
        ]);

        return this.getProfileByUserId(userId);
    }

    /**
     * Update CV URL
     */
    async updateCvUrl(userId, cvUrl) {
        await pool.execute('UPDATE teacher_profiles SET cv_url = ? WHERE user_id = ?', [cvUrl, userId]);
        return this.getProfileByUserId(userId);
    }
}

module.exports = new TeacherService();
