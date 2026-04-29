const { error, fail } = require('../utils/response');

/**
 * Global Error Handling Middleware
 */
const errorHandler = (err, req, res, next) => {
    console.error('💥 Error:', err);

    // Default status code and message
    let statusCode = err.statusCode || 500;
    let message = err.message || 'Internal Server Error';

    // Handle specific error types
    if (err.name === 'ValidationError') {
        statusCode = 400;
        message = 'Validation Error';
    } else if (err.code === 'ER_DUP_ENTRY') {
        statusCode = 400;
        message = 'Duplicate entry found. This record already exists.';
    } else if (err.type === 'entity.parse.failed') {
        statusCode = 400;
        message = 'Invalid JSON payload passed.';
    }

    if (process.env.NODE_ENV === 'development') {
        return res.status(statusCode).json({
            status: statusCode < 500 ? 'fail' : 'error',
            message,
            stack: err.stack,
            error: err
        });
    }

    // Production response (hide stack trace)
    if (statusCode < 500) {
        return fail(res, message, statusCode);
    }
    
    return error(res, 'Something went wrong!', 500);
};

module.exports = { errorHandler };
