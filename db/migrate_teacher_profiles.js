require('dotenv').config();
const { pool } = require('../src/config/db');

async function migrate() {
    try {
        console.log('Running migration...');
        
        await pool.execute(`
            ALTER TABLE teacher_profiles
            CHANGE COLUMN experience_years years_of_experience INT DEFAULT 0,
            CHANGE COLUMN location preferred_location VARCHAR(255),
            ADD COLUMN headline VARCHAR(255),
            ADD COLUMN subjects TEXT,
            ADD COLUMN expected_salary VARCHAR(100),
            ADD COLUMN employment_type ENUM('full-time', 'part-time', 'contract', 'temporary') DEFAULT 'full-time',
            ADD COLUMN cv_url VARCHAR(255),
            ADD COLUMN profile_completion_percentage INT DEFAULT 0,
            ADD COLUMN availability_status ENUM('available', 'not_looking', 'interviewing', 'hired') DEFAULT 'available'
        `);
        console.log('Migration completed successfully.');
    } catch (err) {
        if (err.code === 'ER_DUP_FIELDNAME') {
            console.log('Columns already exist. Skipping.');
        } else {
            console.error('Migration failed:', err);
        }
    } finally {
        process.exit();
    }
}

migrate();
