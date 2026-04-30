const { pool } = require('../src/config/db');
const dotenv = require('dotenv');

dotenv.config();

const updateSchema = async () => {
    try {
        console.log('Updating job_applications table schema...');
        
        await pool.execute(`
            ALTER TABLE job_applications 
            ADD COLUMN IF NOT EXISTS cv_url VARCHAR(255) AFTER status,
            MODIFY COLUMN status ENUM('submitted', 'reviewed', 'shortlisted', 'rejected', 'hired', 'withdrawn') DEFAULT 'submitted';
        `);
        
        console.log('✅ Job applications table updated successfully!');
        process.exit(0);
    } catch (err) {
        console.error('❌ Error updating applications schema:', err);
        process.exit(1);
    }
};

updateSchema();
