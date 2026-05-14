const db = require('../config/db');
const bcrypt = require('bcryptjs');

class User {
    static async create(userData) {
        const { name, email, password, role } = userData;
        const hashedPassword = await bcrypt.hash(password, 12);
        
        const [result] = await db.execute(
            'INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)',
            [name, email, hashedPassword, role]
        );
        
        return result.insertId;
    }

    static async findByEmail(email) {
        const [rows] = await db.execute('SELECT * FROM users WHERE email = ?', [email]);
        return rows[0];
    }

    static async findById(id) {
        const [rows] = await db.execute('SELECT * FROM users WHERE id = ?', [id]);
        return rows[0];
    }

    static async comparePasswords(candidatePassword, userPassword) {
        return await bcrypt.compare(candidatePassword, userPassword);
    }

    static async createTeacherProfile(userId, profileData = {}) {
        const { phone } = profileData;
        if (phone) {
            const [result] = await db.execute(
                'INSERT INTO teacher_profiles (user_id, phone) VALUES (?, ?)',
                [userId, phone]
            );
            return result.insertId;
        } else {
            const [result] = await db.execute(
                'INSERT INTO teacher_profiles (user_id) VALUES (?)',
                [userId]
            );
            return result.insertId;
        }
    }

    static async createSchoolProfile(userId, schoolName, schoolType) {
        const [result] = await db.execute(
            'INSERT INTO school_profiles (user_id, school_name, school_type) VALUES (?, ?, ?)',
            [userId, schoolName, schoolType]
        );
        return result.insertId;
    }
}

module.exports = User;
