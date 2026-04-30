const { pool } = require('../../config/db');
const notificationsService = require('../notifications/notifications.service');

class JobService {
    /**
     * Create a new job posting
     */
    async createJob(schoolUserId, jobData) {
        // Get school_id from school_profiles using user_id
        const [schools] = await pool.execute('SELECT id FROM school_profiles WHERE user_id = ?', [schoolUserId]);
        if (schools.length === 0) throw new Error('School profile not found');
        const schoolId = schools[0].id;

        const {
            title, subject, description, requirements, responsibilities,
            location, state, salary_min, salary_max, employment_type,
            experience_level, application_deadline
        } = jobData;

        const query = `
            INSERT INTO jobs (
                school_id, title, subject, description, requirements, responsibilities,
                location, state, salary_min, salary_max, employment_type,
                experience_level, application_deadline, approval_status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
        `;

        const [result] = await pool.execute(query, [
            schoolId, title, subject, description, requirements, responsibilities || null,
            location, state, salary_min || null, salary_max || null, employment_type,
            experience_level, application_deadline || null
        ]);

        return result.insertId;
    }

    /**
     * Get job by ID with school details
     */
    async getJobById(jobId) {
        const query = `
            SELECT j.*, sp.school_name, sp.school_type, sp.logo_url, sp.website
            FROM jobs j
            JOIN school_profiles sp ON j.school_id = sp.id
            WHERE j.id = ?
        `;
        const [rows] = await pool.execute(query, [jobId]);
        return rows[0];
    }

    /**
     * Update job posting
     */
    async updateJob(jobId, schoolUserId, updateData) {
        // Verify ownership if not admin (caller should handle role check, here we ensure school owns it)
        const [schoolCheck] = await pool.execute(
            'SELECT j.id FROM jobs j JOIN school_profiles sp ON j.school_id = sp.id WHERE j.id = ? AND sp.user_id = ?',
            [jobId, schoolUserId]
        );
        if (schoolCheck.length === 0) return false;

        const fields = [];
        const values = [];

        for (const [key, value] of Object.entries(updateData)) {
            fields.push(`${key} = ?`);
            values.push(value);
        }

        if (fields.length === 0) return true;

        values.push(jobId);
        const query = `UPDATE jobs SET ${fields.join(', ')} WHERE id = ?`;
        
        const [result] = await pool.execute(query, values);
        return result.affectedRows > 0;
    }

    /**
     * Delete/Close job
     */
    async deleteJob(jobId, schoolUserId) {
        // If schoolUserId is provided, check ownership
        if (schoolUserId) {
            const [schoolCheck] = await pool.execute(
                'SELECT j.id FROM jobs j JOIN school_profiles sp ON j.school_id = sp.id WHERE j.id = ? AND sp.user_id = ?',
                [jobId, schoolUserId]
            );
            if (schoolCheck.length === 0) return false;
        }

        const [result] = await pool.execute('DELETE FROM jobs WHERE id = ?', [jobId]);
        return result.affectedRows > 0;
    }

    /**
     * Get all jobs with filtering and pagination
     */
    async getAllJobs(filters) {
        const {
            page = 1, limit = 10, search, subject, location, state,
            salary_min, salary_max, employment_type, experience_level,
            school_type, featured, approval_status = 'approved', status = 'active'
        } = filters;

        const offset = (page - 1) * limit;
        const values = [];
        let whereClauses = [];

        // Base filters
        if (approval_status) {
            whereClauses.push('j.approval_status = ?');
            values.push(approval_status);
        }
        if (status) {
            whereClauses.push('j.status = ?');
            values.push(status);
        }

        // Search
        if (search) {
            whereClauses.push('(j.title LIKE ? OR j.description LIKE ? OR j.subject LIKE ?)');
            const searchVal = `%${search}%`;
            values.push(searchVal, searchVal, searchVal);
        }

        // Filters
        if (subject) {
            whereClauses.push('j.subject = ?');
            values.push(subject);
        }
        if (location) {
            whereClauses.push('j.location LIKE ?');
            values.push(`%${location}%`);
        }
        if (state) {
            whereClauses.push('j.state = ?');
            values.push(state);
        }
        if (salary_min) {
            whereClauses.push('j.salary_max >= ?');
            values.push(salary_min);
        }
        if (salary_max) {
            whereClauses.push('j.salary_min <= ?');
            values.push(salary_max);
        }
        if (employment_type) {
            whereClauses.push('j.employment_type = ?');
            values.push(employment_type);
        }
        if (experience_level) {
            whereClauses.push('j.experience_level = ?');
            values.push(experience_level);
        }
        if (school_type) {
            whereClauses.push('sp.school_type = ?');
            values.push(school_type);
        }
        if (featured !== undefined && featured !== null) {
            whereClauses.push('j.is_featured = ?');
            values.push(featured ? 1 : 0);
        }

        const whereSql = whereClauses.length > 0 ? `WHERE ${whereClauses.join(' AND ')}` : '';

        // Count query
        const countQuery = `
            SELECT COUNT(*) as total 
            FROM jobs j 
            JOIN school_profiles sp ON j.school_id = sp.id
            ${whereSql}
        `;
        const [countRows] = await pool.execute(countQuery, values);
        const total = countRows[0].total;

        // Data query
        const dataQuery = `
            SELECT j.*, sp.school_name, sp.school_type, sp.logo_url
            FROM jobs j
            JOIN school_profiles sp ON j.school_id = sp.id
            ${whereSql}
            ORDER BY j.is_featured DESC, j.created_at DESC
            LIMIT ? OFFSET ?
        `;
        
        // LIMIT and OFFSET must be numbers and passed as values, but in some mysql drivers they work differently
        // mysql2/promise supports numeric placeholders if properly configured, but here we add them to values
        values.push(String(limit), String(offset));
        
        const [rows] = await pool.execute(dataQuery, values);

        return {
            jobs: rows,
            pagination: {
                total,
                page: Number(page),
                limit: Number(limit),
                totalPages: Math.ceil(total / limit)
            }
        };
    }

    /**
     * Get jobs for a specific school
     */
    async getJobsBySchool(schoolUserId) {
        const query = `
            SELECT j.*, (SELECT COUNT(*) FROM job_applications WHERE job_id = j.id) as application_count
            FROM jobs j
            JOIN school_profiles sp ON j.school_id = sp.id
            WHERE sp.user_id = ?
            ORDER BY j.created_at DESC
        `;
        const [rows] = await pool.execute(query, [schoolUserId]);
        return rows;
    }

    /**
     * Admin: Update approval status
     */
    async updateApprovalStatus(jobId, status) {
        const [result] = await pool.execute(
            'UPDATE jobs SET approval_status = ? WHERE id = ?',
            [status, jobId]
        );

        // Notify School
        const [jobDetails] = await pool.execute(
            'SELECT j.title, sp.user_id as school_user_id FROM jobs j JOIN school_profiles sp ON j.school_id = sp.id WHERE j.id = ?',
            [jobId]
        );

        await notificationsService.createNotification(jobDetails[0].school_user_id, {
            title: `Job ${status.charAt(0).toUpperCase() + status.slice(1)}`,
            message: `Your job posting "${jobDetails[0].title}" has been ${status} by the admin.`,
            type: 'job_approval_status_changed',
            relatedEntityType: 'job',
            relatedEntityId: jobId
        });

        return result.affectedRows > 0;
    }

    /**
     * Admin: Toggle featured
     */
    async toggleFeatured(jobId, isFeatured) {
        const [result] = await pool.execute(
            'UPDATE jobs SET is_featured = ? WHERE id = ?',
            [isFeatured ? 1 : 0, jobId]
        );
        return result.affectedRows > 0;
    }

    /**
     * View applications for a specific job (for school)
     */
    async getApplicationsByJob(jobId, schoolUserId) {
        // Verify ownership
        const [schoolCheck] = await pool.execute(
            'SELECT j.id FROM jobs j JOIN school_profiles sp ON j.school_id = sp.id WHERE j.id = ? AND sp.user_id = ?',
            [jobId, schoolUserId]
        );
        if (schoolCheck.length === 0) throw new Error('Unauthorized access to applications');

        const query = `
            SELECT ja.*, tp.headline, u.name as teacher_name, u.email as teacher_email
            FROM job_applications ja
            JOIN teacher_profiles tp ON ja.teacher_id = tp.id
            JOIN users u ON tp.user_id = u.id
            WHERE ja.job_id = ?
            ORDER BY ja.created_at DESC
        `;
        const [rows] = await pool.execute(query, [jobId]);
        return rows;
    }
}

module.exports = new JobService();
