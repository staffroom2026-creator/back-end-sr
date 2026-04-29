const db = require('../config/db');
const catchAsync = require('../utils/catchAsync');
const AppError = require('../utils/appError');

exports.getStats = catchAsync(async (req, res, next) => {
    const [userStats] = await db.execute('SELECT role, COUNT(*) as count FROM users GROUP BY role');
    const [jobStats] = await db.execute('SELECT COUNT(*) as count FROM jobs');
    const [appStats] = await db.execute('SELECT status, COUNT(*) as count FROM applications GROUP BY status');
    const [verificationStats] = await db.execute('SELECT is_verified, COUNT(*) as count FROM school_profiles GROUP BY is_verified');

    res.status(200).json({
        status: 'success',
        data: {
            users: userStats,
            totalJobs: jobStats[0].count,
            applications: appStats,
            verifications: verificationStats
        }
    });
});

exports.getPendingVerifications = catchAsync(async (req, res, next) => {
    const [schools] = await db.execute(`
        SELECT sp.*, u.name as admin_name, u.email 
        FROM school_profiles sp 
        JOIN users u ON sp.user_id = u.id 
        WHERE sp.is_verified = FALSE
    `);

    res.status(200).json({
        status: 'success',
        results: schools.length,
        data: {
            schools
        }
    });
});

exports.verifySchool = catchAsync(async (req, res, next) => {
    const { id } = req.params;
    const { verify } = req.body; // true or false

    const [schools] = await db.execute('SELECT * FROM school_profiles WHERE id = ?', [id]);
    if (schools.length === 0) {
        return next(new AppError('School not found', 404));
    }

    await db.execute('UPDATE school_profiles SET is_verified = ? WHERE id = ?', [verify, id]);

    // Notify school admin
    await db.execute(
        'INSERT INTO notifications (user_id, title, message) VALUES (?, ?, ?)',
        [schools[0].user_id, 'Verification Update', verify ? 'Your school has been verified!' : 'Your school verification was not successful.']
    );

    res.status(200).json({
        status: 'success',
        message: `School verification status updated to ${verify}`
    });
});
