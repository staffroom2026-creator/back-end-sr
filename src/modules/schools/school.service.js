const { pool } = require('../../config/db');
const notificationsService = require('../notifications/notifications.service');

class SchoolService {
    /**
     * Get school profile by user ID
     */
    async getProfileByUserId(userId) {
        const [rows] = await pool.execute(
            `SELECT u.id as user_id, u.email, sp.school_name, sp.school_type, sp.address, sp.city, sp.state, 
            sp.contact_person_name, sp.contact_phone, sp.website, sp.description, sp.logo_url, 
            sp.verification_document_url, sp.verification_status, sp.is_verified, sp.created_at
            FROM users u
            JOIN school_profiles sp ON u.id = sp.user_id
            WHERE u.id = ?`,
            [userId]
        );
        return rows[0];
    }

    /**
     * Update school profile
     */
    async updateProfile(userId, profileData) {
        const fields = [];
        const values = [];

        for (const [key, value] of Object.entries(profileData)) {
            fields.push(`${key} = ?`);
            values.push(value);
        }

        if (fields.length === 0) return null;

        values.push(userId);

        const query = `UPDATE school_profiles SET ${fields.join(', ')} WHERE user_id = ?`;
        
        const [result] = await pool.execute(query, values);
        return result.affectedRows > 0;
    }

    /**
     * Update logo URL
     */
    async updateLogo(userId, logoUrl) {
        const [result] = await pool.execute(
            'UPDATE school_profiles SET logo_url = ? WHERE user_id = ?',
            [logoUrl, userId]
        );
        return result.affectedRows > 0;
    }

    /**
     * Update verification document URL
     */
    async updateVerificationDocument(userId, docUrl) {
        const [result] = await pool.execute(
            'UPDATE school_profiles SET verification_document_url = ?, verification_status = "pending" WHERE user_id = ?',
            [docUrl, userId]
        );
        return result.affectedRows > 0;
    }

    /**
     * Get verification status
     */
    async getVerificationStatus(userId) {
        const [rows] = await pool.execute(
            'SELECT verification_status, is_verified FROM school_profiles WHERE user_id = ?',
            [userId]
        );
        return rows[0];
    }

    /**
     * Admin: Update school verification status
     */
    async updateVerificationStatus(schoolId, status) {
        const isVerified = status === 'verified';
        const [result] = await pool.execute(
            'UPDATE school_profiles SET verification_status = ?, is_verified = ? WHERE id = ?',
            [status, isVerified, schoolId]
        );

        // Notify School
        const [school] = await pool.execute('SELECT user_id, school_name FROM school_profiles WHERE id = ?', [schoolId]);
        
        await notificationsService.createNotification(school[0].user_id, {
            title: `Verification ${status.charAt(0).toUpperCase() + status.slice(1)}`,
            message: `Your school verification for "${school[0].school_name}" has been ${status}.`,
            type: 'school_verification_status_changed',
            relatedEntityType: 'school',
            relatedEntityId: schoolId
        });

        return result.affectedRows > 0;
    }
}

module.exports = new SchoolService();
