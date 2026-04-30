const { pool } = require('../../config/db');

class AdminService {
    /**
     * Get aggregate statistics for the admin dashboard
     */
    async getDashboardStats() {
        const stats = {};

        // Total Users, Teachers, Schools
        const [userStats] = await pool.execute(`
            SELECT 
                COUNT(*) as total_users,
                SUM(CASE WHEN role = 'teacher' THEN 1 ELSE 0 END) as total_teachers,
                SUM(CASE WHEN role = 'school' THEN 1 ELSE 0 END) as total_schools
            FROM users
        `);
        stats.total_users = userStats[0].total_users;
        stats.total_teachers = userStats[0].total_teachers;
        stats.total_schools = userStats[0].total_schools;

        // School Verification Stats
        const [schoolStats] = await pool.execute(`
            SELECT 
                SUM(CASE WHEN verification_status = 'verified' THEN 1 ELSE 0 END) as verified_schools,
                SUM(CASE WHEN verification_status = 'pending' THEN 1 ELSE 0 END) as pending_schools
            FROM school_profiles
        `);
        stats.verified_schools = schoolStats[0].verified_schools || 0;
        stats.pending_schools = schoolStats[0].pending_schools || 0;

        // Job Stats
        const [jobStats] = await pool.execute(`
            SELECT 
                COUNT(*) as total_jobs,
                SUM(CASE WHEN approval_status = 'pending' THEN 1 ELSE 0 END) as pending_jobs,
                SUM(CASE WHEN status = 'active' AND approval_status = 'approved' THEN 1 ELSE 0 END) as active_jobs
            FROM jobs
        `);
        stats.total_jobs = jobStats[0].total_jobs;
        stats.pending_jobs = jobStats[0].pending_jobs || 0;
        stats.active_jobs = jobStats[0].active_jobs || 0;

        // Application Stats
        const [appStats] = await pool.execute('SELECT COUNT(*) as total_applications FROM job_applications');
        stats.total_applications = appStats[0].total_applications;

        return stats;
    }

    /**
     * List all users with pagination and role filter
     */
    async getAllUsers(filters) {
        const { page = 1, limit = 20, role, active } = filters;
        const offset = (page - 1) * limit;
        const values = [];
        let whereClauses = [];

        if (role) {
            whereClauses.push('role = ?');
            values.push(role);
        }
        if (active !== undefined && active !== null) {
            whereClauses.push('active = ?');
            values.push(active ? 1 : 0);
        }

        const whereSql = whereClauses.length > 0 ? `WHERE ${whereClauses.join(' AND ')}` : '';

        const [countRows] = await pool.execute(`SELECT COUNT(*) as total FROM users ${whereSql}`, values);
        const total = countRows[0].total;

        const query = `
            SELECT id, name, email, role, active, created_at, last_login 
            FROM users 
            ${whereSql} 
            ORDER BY created_at DESC 
            LIMIT ? OFFSET ?
        `;
        values.push(String(limit), String(offset));

        const [rows] = await pool.execute(query, values);

        return {
            users: rows,
            pagination: { total, page: Number(page), limit: Number(limit) }
        };
    }

    /**
     * Suspend or Activate a user
     */
    async updateUserStatus(userId, active) {
        const [result] = await pool.execute('UPDATE users SET active = ? WHERE id = ?', [active ? 1 : 0, userId]);
        return result.affectedRows > 0;
    }

    /**
     * Record administrative action
     */
    async logAction(adminId, action, targetType, targetId, details) {
        await pool.execute(
            'INSERT INTO admin_logs (admin_id, action, target_type, target_id, details) VALUES (?, ?, ?, ?, ?)',
            [adminId, action, targetType, targetId, details ? JSON.stringify(details) : null]
        );
    }

    /**
     * Fetch platform activity logs
     */
    async getActivityLogs(page = 1, limit = 50) {
        const offset = (page - 1) * limit;
        const [countRows] = await pool.execute('SELECT COUNT(*) as total FROM admin_logs');
        const total = countRows[0].total;

        const query = `
            SELECT al.*, u.name as admin_name 
            FROM admin_logs al 
            JOIN users u ON al.admin_id = u.id 
            ORDER BY al.created_at DESC 
            LIMIT ? OFFSET ?
        `;
        const [rows] = await pool.execute(query, [String(limit), String(offset)]);

        return {
            logs: rows,
            pagination: { total, page: Number(page), limit: Number(limit) }
        };
    }
}

module.exports = new AdminService();
