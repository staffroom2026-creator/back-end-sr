const { pool } = require('../src/config/db');
const dotenv = require('dotenv');

dotenv.config();

const updateSchema = async () => {
    try {
        console.log('Updating school_profiles schema...');
        
        await pool.execute(`
            ALTER TABLE school_profiles 
            ADD COLUMN IF NOT EXISTS city VARCHAR(100) AFTER address,
            ADD COLUMN IF NOT EXISTS contact_person_name VARCHAR(255) AFTER state,
            ADD COLUMN IF NOT EXISTS contact_phone VARCHAR(20) AFTER contact_person_name,
            ADD COLUMN IF NOT EXISTS description TEXT AFTER website,
            ADD COLUMN IF NOT EXISTS verification_document_url VARCHAR(255) AFTER logo_url,
            MODIFY COLUMN verification_status ENUM('pending', 'verified', 'rejected') DEFAULT 'pending'
        `);
        
        console.log('✅ Schema updated successfully!');
        process.exit(0);
    } catch (err) {
        console.error('❌ Error updating schema:', err);
        process.exit(1);
    }
};

updateSchema();
