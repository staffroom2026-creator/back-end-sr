const { pool } = require('../src/config/db');
const dotenv = require('dotenv');

dotenv.config();

const updateSchema = async () => {
    try {
        console.log('Updating jobs table schema...');
        
        await pool.execute(`
            ALTER TABLE jobs 
            ADD COLUMN IF NOT EXISTS subject VARCHAR(100) AFTER title,
            ADD COLUMN IF NOT EXISTS responsibilities TEXT AFTER description,
            ADD COLUMN IF NOT EXISTS state VARCHAR(100) AFTER location,
            ADD COLUMN IF NOT EXISTS salary_min DECIMAL(10, 2) AFTER salary_range,
            ADD COLUMN IF NOT EXISTS salary_max DECIMAL(10, 2) AFTER salary_min,
            ADD COLUMN IF NOT EXISTS experience_level ENUM('entry', 'mid', 'senior', 'lead') DEFAULT 'mid' AFTER employment_type,
            ADD COLUMN IF NOT EXISTS application_deadline DATE AFTER experience_level,
            ADD COLUMN IF NOT EXISTS is_featured BOOLEAN DEFAULT FALSE AFTER approval_status,
            ADD COLUMN IF NOT EXISTS status ENUM('active', 'closed', 'draft') DEFAULT 'active' AFTER is_featured;
        `);
        
        // Add Full-text index for search if not exists
        try {
            await pool.execute('CREATE FULLTEXT INDEX idx_jobs_search ON jobs(title, description, subject, requirements)');
        } catch (e) {
            console.log('Note: Fulltext index might already exist or not supported by storage engine.');
        }

        console.log('✅ Jobs table updated successfully!');
        process.exit(0);
    } catch (err) {
        console.error('❌ Error updating jobs schema:', err);
        process.exit(1);
    }
};

updateSchema();
