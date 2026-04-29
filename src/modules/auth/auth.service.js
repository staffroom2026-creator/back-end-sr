const bcrypt = require('bcryptjs');
const { pool } = require('../../config/db');

class AuthService {
    /**
     * Check if user exists by email
     */
    async userExists(email) {
        const [rows] = await pool.execute('SELECT id FROM users WHERE email = ?', [email]);
        return rows.length > 0;
    }

    /**
     * Register a new teacher
     */
    async registerTeacher(data) {
        const { full_name, email, phone, password } = data;
        
        const connection = await pool.getConnection();
        try {
            await connection.beginTransaction();

            // Hash password
            const hashedPassword = await bcrypt.hash(password, 10);

            // Insert into users
            const [userResult] = await connection.execute(
                'INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)',
                [full_name, email, hashedPassword, 'teacher']
            );
            const userId = userResult.insertId;

            // Insert into teacher_profiles
            await connection.execute(
                'INSERT INTO teacher_profiles (user_id, phone) VALUES (?, ?)',
                [userId, phone]
            );

            await connection.commit();
            return userId;
        } catch (error) {
            await connection.rollback();
            throw error;
        } finally {
            connection.release();
        }
    }

    /**
     * Register a new school
     */
    async registerSchool(data) {
        const { school_name, contact_person_name, email, phone, password } = data;
        
        const connection = await pool.getConnection();
        try {
            await connection.beginTransaction();

            // Hash password
            const hashedPassword = await bcrypt.hash(password, 10);

            // Insert into users
            const [userResult] = await connection.execute(
                'INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)',
                [contact_person_name, email, hashedPassword, 'school']
            );
            const userId = userResult.insertId;

            // Insert into school_profiles. 
            // Note: If 'phone' column doesn't exist in school_profiles, we might need to alter the table.
            // Assuming we alter it to support this requirement.
            await connection.execute(
                'INSERT INTO school_profiles (user_id, school_name, school_type) VALUES (?, ?, ?)',
                [userId, school_name, 'primary'] // Defaulting school_type as it is required in DB schema
            );

            await connection.commit();
            return userId;
        } catch (error) {
            await connection.rollback();
            throw error;
        } finally {
            connection.release();
        }
    }

    /**
     * Find user by email (for login)
     */
    async findByEmail(email) {
        const [rows] = await pool.execute('SELECT * FROM users WHERE email = ?', [email]);
        return rows[0];
    }
    
    /**
     * Find user by ID
     */
    async findById(id) {
        const [rows] = await pool.execute('SELECT id, name, email, role, is_email_verified, active, created_at FROM users WHERE id = ?', [id]);
        return rows[0];
    }

    /**
     * Update last login timestamp
     */
    async updateLastLogin(userId) {
        await pool.execute('UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = ?', [userId]);
    }

    /**
     * Change password
     */
    async changePassword(userId, newPassword) {
        const hashedPassword = await bcrypt.hash(newPassword, 10);
        await pool.execute('UPDATE users SET password = ? WHERE id = ?', [hashedPassword, userId]);
    }

    /**
     * Create password reset token
     */
    async createPasswordResetToken(email, token, expiresAt) {
        await pool.execute(
            'INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)',
            [email, token, expiresAt]
        );
    }

    /**
     * Verify password reset token
     */
    async verifyPasswordResetToken(token) {
        const [rows] = await pool.execute(
            'SELECT * FROM password_resets WHERE token = ? AND is_used = FALSE AND expires_at > CURRENT_TIMESTAMP',
            [token]
        );
        return rows[0];
    }

    /**
     * Mark reset token as used and update password
     */
    async resetPassword(email, token, newPassword) {
        const connection = await pool.getConnection();
        try {
            await connection.beginTransaction();

            const hashedPassword = await bcrypt.hash(newPassword, 10);

            // Update user password
            await connection.execute('UPDATE users SET password = ? WHERE email = ?', [hashedPassword, email]);

            // Mark token as used
            await connection.execute('UPDATE password_resets SET is_used = TRUE WHERE token = ?', [token]);

            await connection.commit();
        } catch (error) {
            await connection.rollback();
            throw error;
        } finally {
            connection.release();
        }
    }
}

module.exports = new AuthService();
