const db = require('../config/db');
const catchAsync = require('../utils/catchAsync');
const AppError = require('../utils/appError');

exports.saveJob = catchAsync(async (req, res, next) => {
    const { jobId } = req.params;

    // Check if job exists
    const [jobs] = await db.execute('SELECT id FROM jobs WHERE id = ?', [jobId]);
    if (jobs.length === 0) {
        return next(new AppError('Job not found', 404));
    }

    try {
        await db.execute(
            'INSERT INTO saved_jobs (user_id, job_id) VALUES (?, ?)',
            [req.user.id, jobId]
        );
    } catch (err) {
        if (err.code === 'ER_DUP_ENTRY') {
            return next(new AppError('You have already saved this job', 400));
        }
        throw err;
    }

    res.status(201).json({
        status: 'success',
        message: 'Job saved successfully'
    });
});

exports.getSavedJobs = catchAsync(async (req, res, next) => {
    const [savedJobs] = await db.execute(`
        SELECT sj.id as save_id, j.*, sp.school_name, sp.logo_url 
        FROM saved_jobs sj 
        JOIN jobs j ON sj.job_id = j.id 
        JOIN school_profiles sp ON j.school_id = sp.id 
        WHERE sj.user_id = ?
        ORDER BY sj.created_at DESC
    `, [req.user.id]);

    res.status(200).json({
        status: 'success',
        results: savedJobs.length,
        data: {
            jobs: savedJobs
        }
    });
});

exports.unsaveJob = catchAsync(async (req, res, next) => {
    const { jobId } = req.params;

    await db.execute(
        'DELETE FROM saved_jobs WHERE user_id = ? AND job_id = ?',
        [req.user.id, jobId]
    );

    res.status(204).json({
        status: 'success',
        data: null
    });
});
