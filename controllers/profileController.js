const db = require('../config/db');
const catchAsync = require('../utils/catchAsync');
const AppError = require('../utils/appError');

exports.getMe = catchAsync(async (req, res, next) => {
    let profile;
    if (req.user.role === 'teacher') {
        const [rows] = await db.execute(
            'SELECT u.id, u.name, u.email, u.role, tp.* FROM users u JOIN teacher_profiles tp ON u.id = tp.user_id WHERE u.id = ?',
            [req.user.id]
        );
        profile = rows[0];
    } else if (req.user.role === 'school') {
        const [rows] = await db.execute(
            'SELECT u.id, u.name, u.email, u.role, sp.* FROM users u JOIN school_profiles sp ON u.id = sp.user_id WHERE u.id = ?',
            [req.user.id]
        );
        profile = rows[0];
    } else {
        profile = req.user;
    }

    res.status(200).json({
        status: 'success',
        data: {
            profile
        }
    });
});

exports.updateTeacherProfile = catchAsync(async (req, res, next) => {
    const { phone, bio, skills, experience_years, qualification, location } = req.body;
    
    await db.execute(
        'UPDATE teacher_profiles SET phone = ?, bio = ?, skills = ?, experience_years = ?, qualification = ?, location = ? WHERE user_id = ?',
        [phone, bio, skills, experience_years, qualification, location, req.user.id]
    );

    res.status(200).json({
        status: 'success',
        message: 'Profile updated successfully'
    });
});

exports.updateSchoolProfile = catchAsync(async (req, res, next) => {
    const { school_name, school_type, address, lga, state, website } = req.body;
    
    await db.execute(
        'UPDATE school_profiles SET school_name = ?, school_type = ?, address = ?, lga = ?, state = ?, website = ? WHERE user_id = ?',
        [school_name, school_type, address, lga, state, website, req.user.id]
    );

    res.status(200).json({
        status: 'success',
        message: 'School profile updated successfully'
    });
});

exports.uploadCV = catchAsync(async (req, res, next) => {
    if (!req.file) return next(new AppError('Please upload a file', 400));

    const cvUrl = `/uploads/${req.file.filename}`;
    
    await db.execute(
        'UPDATE teacher_profiles SET cv_url = ? WHERE user_id = ?',
        [cvUrl, req.user.id]
    );

    res.status(200).json({
        status: 'success',
        data: {
            cv_url: cvUrl
        }
    });
});

exports.uploadLogo = catchAsync(async (req, res, next) => {
    if (!req.file) return next(new AppError('Please upload a file', 400));

    const logoUrl = `/uploads/${req.file.filename}`;
    
    await db.execute(
        'UPDATE school_profiles SET logo_url = ? WHERE user_id = ?',
        [logoUrl, req.user.id]
    );

    res.status(200).json({
        status: 'success',
        data: {
            logo_url: logoUrl
        }
    });
});
