const { pool } = require('../../config/db');

class SavedJobsService {
    /**
     * Save a job for a teacher
     */
    async saveJob(userId, jobId) {
        // Check if already saved
        const [existing] = await pool.execute(
            'SELECT id FROM saved_jobs WHERE user_id = ? AND job_id = ?',
            [userId, jobId]
        );
        if (existing.length > 0) throw new Error('Job already saved');

        const [result] = await pool.execute(
            'INSERT INTO saved_jobs (user_id, job_id) VALUES (?, ?)',
            [userId, jobId]
        );
        return result.insertId;
    }

    /**
     * Unsave a job
     */
    async unsaveJob(userId, jobId) {
        const [result] = await pool.execute(
            'DELETE FROM saved_jobs WHERE user_id = ? AND job_id = ?',
            [userId, jobId]
        );
        return result.affectedRows > 0;
    }

    /**
     * Get all saved jobs for a teacher
     */
    async getSavedJobs(userId) {
        const query = `
            SELECT j.*, sp.school_name, sp.logo_url, sj.created_at as saved_at
            FROM saved_jobs sj
            JOIN jobs j ON sj.job_id = j.id
            JOIN school_profiles sp ON j.school_id = sp.id
            WHERE sj.user_id = ?
            ORDER BY sj.created_at DESC
        `;
        const [rows] = await pool.execute(query, [userId]);
        return rows;
    }
}

module.exports = new SavedJobsService();
