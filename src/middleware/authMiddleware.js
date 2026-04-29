const { verifyToken } = require('../utils/jwt');
const { pool } = require('../config/db');
const { fail } = require('../utils/response');

/**
 * Protects routes by verifying JWT tokens.
 * Attaches `req.user` on success.
 */
const protect = async (req, res, next) => {
    try {
        // 1. Extract token
        let token;
        if (req.headers.authorization && req.headers.authorization.startsWith('Bearer')) {
            token = req.headers.authorization.split(' ')[1];
        }

        if (!token) {
            return fail(res, 'You are not logged in. Please log in to get access.', 401);
        }

        // 2. Verify token
        const decoded = verifyToken(token);

        // 3. Check if user still exists
        const [rows] = await pool.execute(
            'SELECT id, name, email, role, active FROM users WHERE id = ?',
            [decoded.id]
        );

        if (rows.length === 0) {
            return fail(res, 'The user belonging to this token no longer exists.', 401);
        }

        const user = rows[0];

        // 4. Check if user is active
        if (!user.active) {
            return fail(res, 'This account has been deactivated.', 401);
        }

        // 5. Grant access
        req.user = user;
        next();
    } catch (err) {
        if (err.name === 'JsonWebTokenError') {
            return fail(res, 'Invalid token. Please log in again.', 401);
        }
        if (err.name === 'TokenExpiredError') {
            return fail(res, 'Your token has expired. Please log in again.', 401);
        }
        next(err);
    }
};

module.exports = { protect };
