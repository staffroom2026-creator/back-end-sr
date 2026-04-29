const { fail } = require('../utils/response');

/**
 * Restricts access to specified roles.
 * Usage: restrictTo('admin', 'school')
 */
const restrictTo = (...roles) => {
    return (req, res, next) => {
        if (!roles.includes(req.user.role)) {
            return fail(res, 'You do not have permission to perform this action.', 403);
        }
        next();
    };
};

module.exports = { restrictTo };
