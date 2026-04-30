const express = require('express');
const cors = require('cors');
const helmet = require('helmet');
const morgan = require('morgan');
const rateLimit = require('express-rate-limit');
const path = require('path');
const { errorHandler } = require('./middleware/errorMiddleware');
const { success } = require('./utils/response');

// Initialize express app
const app = express();

// ─── Global Middleware ───────────────────────────────────────────────────────

// Set security HTTP headers
app.use(helmet());

// Development logging
if (process.env.NODE_ENV === 'development') {
    app.use(morgan('dev'));
}

// Enable CORS
app.use(cors());

// Limit requests from same API
const limiter = rateLimit({
    max: 100, // Limit each IP to 100 requests per `window` (here, per hour)
    windowMs: 60 * 60 * 1000, // 1 hour
    message: 'Too many requests from this IP, please try again in an hour!'
});
app.use('/api', limiter);

// Body parser, reading data from body into req.body
app.use(express.json({ limit: '10kb' }));
app.use(express.urlencoded({ extended: true, limit: '10kb' }));

// Serve static files (uploaded documents/images)
app.use('/uploads', express.static(path.join(__dirname, '../uploads')));

// ─── Routes ──────────────────────────────────────────────────────────────────

// Health Check Route
app.get('/health', (req, res) => {
    return success(res, null, 'StaffRoom API is running smoothly.');
});

// Import Module Routes
const authRoutes = require('./modules/auth/auth.routes');
const teacherRoutes = require('./modules/teachers/teacher.routes');
// const userRoutes = require('./modules/users/users.routes');
const schoolRoutes = require('./modules/schools/school.routes');
const jobRoutes = require('./modules/jobs/job.routes');
const applicationRoutes = require('./modules/applications/application.routes');
const savedJobRoutes = require('./modules/savedJobs/savedJobs.routes');
const notificationRoutes = require('./modules/notifications/notifications.routes');
const adminRoutes = require('./modules/admin/admin.routes');

// Mount Routes
const apiRouter = express.Router();

apiRouter.use('/auth', authRoutes);
// apiRouter.use('/users', userRoutes);

apiRouter.use('/teachers', teacherRoutes);
apiRouter.use('/schools', schoolRoutes);
apiRouter.use('/jobs', jobRoutes);
apiRouter.use('/applications', applicationRoutes);
apiRouter.use('/saved-jobs', savedJobRoutes);
apiRouter.use('/notifications', notificationRoutes);
apiRouter.use('/admin', adminRoutes);

app.use('/api', apiRouter);

// ─── Error Handling ──────────────────────────────────────────────────────────

// Handle undefined routes
app.all('*', (req, res, next) => {
    const err = new Error(`Can't find ${req.originalUrl} on this server!`);
    err.statusCode = 404;
    next(err);
});

// Global Error Handler
app.use(errorHandler);

module.exports = app;
