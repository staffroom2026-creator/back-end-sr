/**
 * Standard JSON Response Helper
 * Provides consistent API response formatting across all modules.
 */

const success = (res, data = null, message = 'Success', statusCode = 200) => {
    const response = {
        status: 'success',
        message,
    };

    if (data !== null) {
        response.data = data;
    }

    return res.status(statusCode).json(response);
};

const created = (res, data = null, message = 'Created successfully') => {
    return success(res, data, message, 201);
};

const noContent = (res) => {
    return res.status(204).json();
};

const fail = (res, message = 'Bad request', statusCode = 400, errors = null) => {
    const response = {
        status: 'fail',
        message,
    };

    if (errors) {
        response.errors = errors;
    }

    return res.status(statusCode).json(response);
};

const error = (res, message = 'Internal server error', statusCode = 500) => {
    return res.status(statusCode).json({
        status: 'error',
        message,
    });
};

module.exports = { success, created, noContent, fail, error };
