const fs = require('fs');
const path = require('path');
const mysql = require('mysql2/promise');
const dotenv = require('dotenv');

dotenv.config({ path: './.env' });

const initDB = async () => {
    const connection = await mysql.createConnection({
        host: process.env.DB_HOST,
        user: process.env.DB_USER,
        password: process.env.DB_PASS,
        multipleStatements: true
    });

    try {
        console.log('Initializing database...');
        const schema = fs.readFileSync(path.join(__dirname, 'schema.sql'), 'utf8');
        
        await connection.query(schema);
        console.log('Database and tables created successfully!');
    } catch (err) {
        console.error('Error initializing database:', err.message);
    } finally {
        await connection.end();
    }
};

initDB();
