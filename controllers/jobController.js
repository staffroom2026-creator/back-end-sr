const db = require('../config/db');
const catchAsync = require('../utils/catchAsync');
const AppError = require('../utils/appError');

exports.getAllJobs = catchAsync(async (req, res, next) => {
    const { role_type, employment_type, location, salary_min } = req.query;
    
    let query = `
        SELECT j.*, sp.school_name, sp.logo_url, sp.state, sp.lga 
        FROM jobs j 
        JOIN school_profiles sp ON j.school_id = sp.id 
        WHERE j.is_active = TRUE
    `;
    const queryParams = [];

    if (role_type) {
        query += ' AND j.role_type LIKE ?';
        queryParams.push(`%${role_type}%`);
    }

    if (employment_type) {
        query += ' AND j.employment_type = ?';
        queryParams.push(employment_type);
    }

    if (location) {
        query += ' AND (j.location LIKE ? OR sp.state LIKE ? OR sp.lga LIKE ?)';
        queryParams.push(`%${location}%`, `%${location}%`, `%${location}%`);
    }

    query += ' ORDER BY j.created_at DESC';

    const [jobs] = await db.execute(query, queryParams);

    res.status(200).json({
        status: 'success',
        results: jobs.length,
        data: {
            jobs
        }
    });
});

exports.getJob = catchAsync(async (req, res, next) => {
    const [rows] = await db.execute(
        'SELECT j.*, sp.school_name, sp.school_type, sp.address, sp.website, sp.logo_url FROM jobs j JOIN school_profiles sp ON j.school_id = sp.id WHERE j.id = ?',
        [req.params.id]
    );

    if (rows.length === 0) {
        return next(new AppError('No job found with that ID', 404));
    }

    res.status(200).json({
        status: 'success',
        data: {
            job: rows[0]
        }
    });
});

exports.createJob = catchAsync(async (req, res, next) => {
    const { title, description, role_type, employment_type, salary_range, location, requirements } = req.body;

    // Get school profile ID for the current user
    const [schools] = await db.execute('SELECT id FROM school_profiles WHERE user_id = ?', [req.user.id]);
    
    if (schools.length === 0) {
        return next(new AppError('No school profile found for this user', 404));
    }

    const schoolId = schools[0].id;

    const [result] = await db.execute(
        'INSERT INTO jobs (school_id, title, description, role_type, employment_type, salary_range, location, requirements) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
        [schoolId, title, description, role_type, employment_type, salary_range, location, requirements]
    );

    const [newJob] = await db.execute('SELECT * FROM jobs WHERE id = ?', [result.insertId]);

    res.status(201).json({
        status: 'success',
        data: {
            job: newJob[0]
        }
    });
});

exports.updateJob = catchAsync(async (req, res, next) => {
    const { title, description, role_type, employment_type, salary_range, location, requirements, is_active } = req.body;

    // Check if job exists and belongs to the school
    const [jobs] = await db.execute(
        'SELECT j.* FROM jobs j JOIN school_profiles sp ON j.school_id = sp.id WHERE j.id = ? AND sp.user_id = ?',
        [req.params.id, req.user.id]
    );

    if (jobs.length === 0) {
        return next(new AppError('No job found or you do not have permission to update it', 404));
    }

    await db.execute(
        'UPDATE jobs SET title = ?, description = ?, role_type = ?, employment_type = ?, salary_range = ?, location = ?, requirements = ?, is_active = ? WHERE id = ?',
        [title, description, role_type, employment_type, salary_range, location, requirements, is_active, req.params.id]
    );

    res.status(200).json({
        status: 'success',
        message: 'Job updated successfully'
    });
});

exports.deleteJob = catchAsync(async (req, res, next) => {
    const [jobs] = await db.execute(
        'SELECT j.* FROM jobs j JOIN school_profiles sp ON j.school_id = sp.id WHERE j.id = ? AND sp.user_id = ?',
        [req.params.id, req.user.id]
    );

    if (jobs.length === 0) {
        return next(new AppError('No job found or you do not have permission to delete it', 404));
    }

    await db.execute('DELETE FROM jobs WHERE id = ?', [req.params.id]);

    res.status(204).json({
        status: 'success',
        data: null
    });
});
