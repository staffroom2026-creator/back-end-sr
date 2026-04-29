const express = require('express');
const morgan = require('morgan');
const helmet = require('helmet');
const cors = require('cors');
const rateLimit = require('express-rate-limit');

const AppError = require('./utils/appError');
const globalErrorHandler = require('./middleware/errorMiddleware');
const authRouter = require('./routes/authRoutes');
const profileRouter = require('./routes/profileRoutes');
const jobRouter = require('./routes/jobRoutes');
const applicationRouter = require('./routes/applicationRoutes');
const featureRouter = require('./routes/featureRoutes');
const adminRouter = require('./routes/adminRoutes');

const app = express();

// 1. GLOBAL MIDDLEWARES
// Set security HTTP headers
app.use(helmet());

// Limit requests from same API
const limiter = rateLimit({
    max: 100,
    windowMs: 60 * 60 * 1000,
    message: 'Too many requests from this IP, please try again in an hour!'
});
app.use('/api', limiter);

// Development logging
if (process.env.NODE_ENV === 'development') {
    app.use(morgan('dev'));
}

// Body parser, reading data from body into req.body
app.use(express.json({ limit: '10kb' }));

// Implement CORS
app.use(cors());

// Serve static files
app.use('/uploads', express.static('uploads'));

// 2. ROUTES
app.use('/api/auth', authRouter);
app.use('/api/profiles', profileRouter);
app.use('/api/jobs', jobRouter);
app.use('/api/applications', applicationRouter);
app.use('/api/features', featureRouter);
app.use('/api/admin', adminRouter);

app.get('/health', (req, res) => {
    res.status(200).json({
        status: 'success',
        message: 'StaffRoom API is healthy'
    });
});

// Handle unhandled routes
app.all('*path', (req, res, next) => {
    next(new AppError(`Can't find ${req.originalUrl} on this server!`, 404));
});

// 3. ERROR HANDLING
app.use(globalErrorHandler);

module.exports = app;
