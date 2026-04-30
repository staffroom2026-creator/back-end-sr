const { pool } = require('../src/config/db');
const dotenv = require('dotenv');

dotenv.config();

const updateSchema = async () => {
    try {
        console.log('Updating notifications table schema...');
        
        await pool.execute(`
            ALTER TABLE notifications 
            ADD COLUMN IF NOT EXISTS related_entity_type VARCHAR(50) AFTER type,
            ADD COLUMN IF NOT EXISTS related_entity_id INT AFTER related_entity_type;
        `);
        
        console.log('✅ Notifications table updated successfully!');
        process.exit(0);
    } catch (err) {
        console.error('❌ Error updating notifications schema:', err);
        process.exit(1);
    }
};

updateSchema();
