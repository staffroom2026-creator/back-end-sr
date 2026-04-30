const { pool } = require('../../config/db');
const notificationsService = require('../notifications/notifications.service');

class ApplicationService {
    /**
     * Apply for a job
     */
    async apply(teacherUserId, applicationData) {
        const { job_id, cover_letter, cv_url } = applicationData;

        // 1. Check if job exists, is approved, and is active
        const [jobs] = await pool.execute(
            'SELECT id, status, approval_status FROM jobs WHERE id = ?',
            [job_id]
        );
        if (jobs.length === 0) throw new Error('Job not found');
        if (jobs[0].status !== 'active') throw new Error('Cannot apply to a closed job');
        if (jobs[0].approval_status !== 'approved') throw new Error('Cannot apply to a job that is not approved');

        // 2. Get teacher profile ID and current CV
        const [teachers] = await pool.execute(
            'SELECT id, cv_url as profile_cv FROM teacher_profiles WHERE user_id = ?',
            [teacherUserId]
        );
        if (teachers.length === 0) throw new Error('Teacher profile not found');
        const teacherId = teachers[0].id;
        const finalCvUrl = cv_url || teachers[0].profile_cv;

        if (!finalCvUrl) throw new Error('Please upload a CV or update your profile CV before applying');

        // 3. Check for existing application
        const [existing] = await pool.execute(
            'SELECT id FROM job_applications WHERE job_id = ? AND teacher_id = ?',
            [job_id, teacherId]
        );
        if (existing.length > 0) throw new Error('You have already applied for this job');

        // 4. Create application
        const query = `
            INSERT INTO job_applications (job_id, teacher_id, status, cover_letter, cv_url)
            VALUES (?, ?, 'submitted', ?, ?)
        `;
        const [result] = await pool.execute(query, [job_id, teacherId, cover_letter, finalCvUrl]);
        const applicationId = result.insertId;

        // 5. Notify School
        const [jobDetails] = await pool.execute(
            'SELECT j.title, sp.user_id as school_user_id FROM jobs j JOIN school_profiles sp ON j.school_id = sp.id WHERE j.id = ?',
            [job_id]
        );
        
        await notificationsService.createNotification(jobDetails[0].school_user_id, {
            title: 'New Job Application',
            message: `A teacher has applied for your job posting: ${jobDetails[0].title}`,
            type: 'application_submitted',
            relatedEntityType: 'application',
            relatedEntityId: applicationId
        });

        return applicationId;
    }

    /**
     * Get applications with filters
     */
    async getApplications(filters) {
        const { teacherUserId, schoolUserId, jobId, admin = false } = filters;
        let query = `
            SELECT ja.*, j.title as job_title, j.location, j.state, 
            sp.school_name, sp.logo_url,
            u.name as teacher_name, u.email as teacher_email, tp.headline as teacher_headline
            FROM job_applications ja
            JOIN jobs j ON ja.job_id = j.id
            JOIN school_profiles sp ON j.school_id = sp.id
            JOIN teacher_profiles tp ON ja.teacher_id = tp.id
            JOIN users u ON tp.user_id = u.id
        `;
        const values = [];
        const conditions = [];

        if (teacherUserId) {
            conditions.push('tp.user_id = ?');
            values.push(teacherUserId);
        }
        if (schoolUserId) {
            conditions.push('sp.user_id = ?');
            values.push(schoolUserId);
        }
        if (jobId) {
            conditions.push('ja.job_id = ?');
            values.push(jobId);
        }

        if (conditions.length > 0) {
            query += ` WHERE ${conditions.join(' AND ')}`;
        }

        query += ' ORDER BY ja.created_at DESC';

        const [rows] = await pool.execute(query, values);
        return rows;
    }

    /**
     * Update application status (School Only)
     */
    async updateStatus(applicationId, schoolUserId, status) {
        // Verify school owns the job
        const [check] = await pool.execute(
            `SELECT ja.id FROM job_applications ja 
             JOIN jobs j ON ja.job_id = j.id 
             JOIN school_profiles sp ON j.school_id = sp.id 
             WHERE ja.id = ? AND sp.user_id = ?`,
            [applicationId, schoolUserId]
        );

        if (check.length === 0) throw new Error('Unauthorized to update this application');

        const [result] = await pool.execute(
            'UPDATE job_applications SET status = ? WHERE id = ?',
            [status, applicationId]
        );

        // Notify Teacher
        const [appDetails] = await pool.execute(
            'SELECT j.title, tp.user_id as teacher_user_id FROM job_applications ja JOIN jobs j ON ja.job_id = j.id JOIN teacher_profiles tp ON ja.teacher_id = tp.id WHERE ja.id = ?',
            [applicationId]
        );

        await notificationsService.createNotification(appDetails[0].teacher_user_id, {
            title: 'Application Status Updated',
            message: `Your application for "${appDetails[0].title}" has been updated to: ${status}`,
            type: 'application_status_changed',
            relatedEntityType: 'application',
            relatedEntityId: applicationId
        });

        return result.affectedRows > 0;
    }

    /**
     * Withdraw application (Teacher Only)
     */
    async withdraw(applicationId, teacherUserId) {
        // Verify teacher owns the application
        const [check] = await pool.execute(
            `SELECT ja.id FROM job_applications ja 
             JOIN teacher_profiles tp ON ja.teacher_id = tp.id 
             WHERE ja.id = ? AND tp.user_id = ?`,
            [applicationId, teacherUserId]
        );

        if (check.length === 0) throw new Error('Unauthorized to withdraw this application');

        const [result] = await pool.execute(
            'UPDATE job_applications SET status = "withdrawn" WHERE id = ?',
            [applicationId]
        );

        return result.affectedRows > 0;
    }

    /**
     * Get specific application details
     */
    async getApplicationById(applicationId) {
        const query = `
            SELECT ja.*, j.title as job_title, j.description as job_description, 
            sp.school_name, u.name as teacher_name, u.email as teacher_email, tp.*
            FROM job_applications ja
            JOIN jobs j ON ja.job_id = j.id
            JOIN school_profiles sp ON j.school_id = sp.id
            JOIN teacher_profiles tp ON ja.teacher_id = tp.id
            JOIN users u ON tp.user_id = u.id
            WHERE ja.id = ?
        `;
        const [rows] = await pool.execute(query, [applicationId]);
        return rows[0];
    }
}

module.exports = new ApplicationService();
