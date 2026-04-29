const jwt = require('jsonwebtoken');
const User = require('../models/userModel');
const catchAsync = require('../utils/catchAsync');
const AppError = require('../utils/appError');

const signToken = id => {
    return jwt.sign({ id }, process.env.JWT_SECRET, {
        expiresIn: process.env.JWT_EXPIRES_IN
    });
};

const createSendToken = (user, statusCode, res) => {
    const token = signToken(user.id);
    
    // Remove password from output
    user.password = undefined;

    res.status(statusCode).json({
        status: 'success',
        token,
        data: {
            user
        }
    });
};

exports.register = catchAsync(async (req, res, next) => {
    const { name, email, password, role, schoolName, schoolType } = req.body;

    // 1. Check if user already exists
    const existingUser = await User.findByEmail(email);
    if (existingUser) {
        return next(new AppError('Email already in use', 400));
    }

    // 2. Create user
    const userId = await User.create({
        name,
        email,
        password,
        role
    });

    // 3. Create associated profile
    if (role === 'teacher') {
        await User.createTeacherProfile(userId);
    } else if (role === 'school') {
        if (!schoolName || !schoolType) {
            return next(new AppError('School name and type are required for school accounts', 400));
        }
        await User.createSchoolProfile(userId, schoolName, schoolType);
    }

    const newUser = await User.findById(userId);

    // 4. Send token
    createSendToken(newUser, 201, res);
});

exports.login = catchAsync(async (req, res, next) => {
    const { email, password } = req.body;

    // 1. Check if email and password exist
    if (!email || !password) {
        return next(new AppError('Please provide email and password', 400));
    }

    // 2. Check if user exists && password is correct
    const user = await User.findByEmail(email);

    if (!user || !(await User.comparePasswords(password, user.password))) {
        return next(new AppError('Incorrect email or password', 401));
    }

    // 3. If everything ok, send token to client
    createSendToken(user, 200, res);
});

exports.protect = catchAsync(async (req, res, next) => {
    // 1. Getting token and check of it's there
    let token;
    if (req.headers.authorization && req.headers.authorization.startsWith('Bearer')) {
        token = req.headers.authorization.split(' ')[1];
    }

    if (!token) {
        return next(new AppError('You are not logged in! Please log in to get access.', 401));
    }

    // 2. Verification token
    const decoded = jwt.verify(token, process.env.JWT_SECRET);

    // 3. Check if user still exists
    const currentUser = await User.findById(decoded.id);
    if (!currentUser) {
        return next(new AppError('The user belonging to this token no longer exists.', 401));
    }

    // GRANT ACCESS TO PROTECTED ROUTE
    req.user = currentUser;
    next();
});

exports.restrictTo = (...roles) => {
    return (req, res, next) => {
        // roles ['admin', 'school']. role='teacher'
        if (!roles.includes(req.user.role)) {
            return next(new AppError('You do not have permission to perform this action', 403));
        }
        next();
    };
};
