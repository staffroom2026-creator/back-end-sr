const db = require('../config/db');
const catchAsync = require('../utils/catchAsync');
const AppError = require('../utils/appError');

exports.applyForJob = catchAsync(async (req, res, next) => {
    const { jobId } = req.params;
    const { cover_letter } = req.body;

    // 1. Get teacher profile ID
    const [teachers] = await db.execute('SELECT id FROM teacher_profiles WHERE user_id = ?', [req.user.id]);
    if (teachers.length === 0) {
        return next(new AppError('Only teachers can apply for jobs', 403));
    }
    const teacherId = teachers[0].id;

    // 2. Check if job exists and is active
    const [jobs] = await db.execute('SELECT * FROM jobs WHERE id = ? AND is_active = TRUE', [jobId]);
    if (jobs.length === 0) {
        return next(new AppError('Job not found or no longer active', 404));
    }

    // 3. Check if already applied
    const [existing] = await db.execute('SELECT * FROM applications WHERE job_id = ? AND teacher_id = ?', [jobId, teacherId]);
    if (existing.length > 0) {
        return next(new AppError('You have already applied for this job', 400));
    }

    // 4. Create application
    await db.execute(
        'INSERT INTO applications (job_id, teacher_id, cover_letter) VALUES (?, ?, ?)',
        [jobId, teacherId, cover_letter]
    );

    // 5. Notify school (logic to be implemented in notifications service)
    // Create notification for school
    const [schoolInfo] = await db.execute('SELECT u.id as user_id FROM users u JOIN school_profiles sp ON u.id = sp.user_id JOIN jobs j ON sp.id = j.school_id WHERE j.id = ?', [jobId]);
    
    if (schoolInfo.length > 0) {
        await db.execute(
            'INSERT INTO notifications (user_id, title, message) VALUES (?, ?, ?)',
            [schoolInfo[0].user_id, 'New Application', `You have a new application for the position: ${jobs[0].title}`]
        );
    }

    res.status(201).json({
        status: 'success',
        message: 'Application submitted successfully'
    });
});

exports.getMyApplications = catchAsync(async (req, res, next) => {
    const [teachers] = await db.execute('SELECT id FROM teacher_profiles WHERE user_id = ?', [req.user.id]);
    if (teachers.length === 0) {
        return next(new AppError('Only teachers have applications', 403));
    }

    const [applications] = await db.execute(`
        SELECT a.*, j.title, j.location, sp.school_name 
        FROM applications a 
        JOIN jobs j ON a.job_id = j.id 
        JOIN school_profiles sp ON j.school_id = sp.id 
        WHERE a.teacher_id = ?
        ORDER BY a.created_at DESC
    `, [teachers[0].id]);

    res.status(200).json({
        status: 'success',
        results: applications.length,
        data: {
            applications
        }
    });
});

exports.getJobApplications = catchAsync(async (req, res, next) => {
    const { jobId } = req.params;

    // Check if the job belongs to the current school
    const [jobs] = await db.execute(`
        SELECT j.id 
        FROM jobs j 
        JOIN school_profiles sp ON j.school_id = sp.id 
        WHERE j.id = ? AND sp.user_id = ?
    `, [jobId, req.user.id]);

    if (jobs.length === 0) {
        return next(new AppError('Job not found or you do not have permission to view its applications', 404));
    }

    const [applications] = await db.execute(`
        SELECT a.*, tp.qualification, tp.experience_years, u.name as teacher_name, u.email as teacher_email 
        FROM applications a 
        JOIN teacher_profiles tp ON a.teacher_id = tp.id 
        JOIN users u ON tp.user_id = u.id 
        WHERE a.job_id = ?
        ORDER BY a.created_at DESC
    `, [jobId]);

    res.status(200).json({
        status: 'success',
        results: applications.length,
        data: {
            applications
        }
    });
});

exports.updateApplicationStatus = catchAsync(async (req, res, next) => {
    const { id } = req.params;
    const { status } = req.body;

    // Check if the application belongs to a job owned by this school
    const [apps] = await db.execute(`
        SELECT a.*, u.id as teacher_user_id, j.title as job_title 
        FROM applications a 
        JOIN jobs j ON a.job_id = j.id 
        JOIN school_profiles sp ON j.school_id = sp.id 
        JOIN teacher_profiles tp ON a.teacher_id = tp.id 
        JOIN users u ON tp.user_id = u.id 
        WHERE a.id = ? AND sp.user_id = ?
    `, [id, req.user.id]);

    if (apps.length === 0) {
        return next(new AppError('Application not found or you do not have permission', 404));
    }

    await db.execute('UPDATE applications SET status = ? WHERE id = ?', [status, id]);

    // Notify teacher
    await db.execute(
        'INSERT INTO notifications (user_id, title, message) VALUES (?, ?, ?)',
        [apps[0].teacher_user_id, 'Application Status Update', `Your application for ${apps[0].job_title} has been updated to: ${status}`]
    );

    res.status(200).json({
        status: 'success',
        message: `Application status updated to ${status}`
    });
});
