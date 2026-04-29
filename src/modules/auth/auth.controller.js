const bcrypt = require('bcryptjs');
const crypto = require('crypto');
const authService = require('./auth.service');
const { generateToken } = require('../../utils/jwt');
const { success, created, fail, error } = require('../../utils/response');

/**
 * Register a Teacher
 */
const registerTeacher = async (req, res, next) => {
    try {
        const { email } = req.body;

        // Check if user already exists
        const exists = await authService.userExists(email);
        if (exists) {
            return fail(res, 'Email is already in use.', 400);
        }

        // Register user
        const userId = await authService.registerTeacher(req.body);

        // Generate token
        const token = generateToken({ id: userId, role: 'teacher' });

        return created(res, { token, userId }, 'Teacher registered successfully.');
    } catch (err) {
        next(err);
    }
};

/**
 * Register a School
 */
const registerSchool = async (req, res, next) => {
    try {
        const { email } = req.body;

        // Check if user already exists
        const exists = await authService.userExists(email);
        if (exists) {
            return fail(res, 'Email is already in use.', 400);
        }

        // Register user
        const userId = await authService.registerSchool(req.body);

        // Generate token
        const token = generateToken({ id: userId, role: 'school' });

        return created(res, { token, userId }, 'School registered successfully.');
    } catch (err) {
        next(err);
    }
};

/**
 * Login User
 */
const login = async (req, res, next) => {
    try {
        const { email, password } = req.body;

        // Find user
        const user = await authService.findByEmail(email);
        if (!user) {
            return fail(res, 'Invalid email or password.', 401);
        }

        // Check if account is active
        if (!user.active) {
            return fail(res, 'Your account has been deactivated.', 401);
        }

        // Verify password
        const isMatch = await bcrypt.compare(password, user.password);
        if (!isMatch) {
            return fail(res, 'Invalid email or password.', 401);
        }

        // Update last login
        await authService.updateLastLogin(user.id);

        // Generate tokens
        const token = generateToken({ id: user.id, role: user.role });
        // In a real scenario, you might also generate a separate refreshToken here

        // Remove password from response
        const { password: _, ...userWithoutPassword } = user;

        return success(res, { token, user: userWithoutPassword }, 'Login successful.');
    } catch (err) {
        next(err);
    }
};

/**
 * Get Authenticated User Profile
 */
const getMe = async (req, res, next) => {
    try {
        const user = await authService.findById(req.user.id);
        if (!user) {
            return fail(res, 'User not found.', 404);
        }

        return success(res, { user }, 'User profile retrieved successfully.');
    } catch (err) {
        next(err);
    }
};

/**
 * Refresh Token
 * (Simplified approach: issues a new token if the current one is still valid)
 */
const refreshToken = async (req, res, next) => {
    try {
        const user = req.user; // Available because this route will be protected
        const newToken = generateToken({ id: user.id, role: user.role });
        
        return success(res, { token: newToken }, 'Token refreshed successfully.');
    } catch (err) {
        next(err);
    }
};

/**
 * Change Password
 */
const changePassword = async (req, res, next) => {
    try {
        const { current_password, new_password } = req.body;
        const userId = req.user.id;

        // Get user to verify current password
        const user = await authService.findByEmail(req.user.email);
        
        const isMatch = await bcrypt.compare(current_password, user.password);
        if (!isMatch) {
            return fail(res, 'Incorrect current password.', 400);
        }

        // Change password
        await authService.changePassword(userId, new_password);

        return success(res, null, 'Password changed successfully.');
    } catch (err) {
        next(err);
    }
};

/**
 * Forgot Password
 */
const forgotPassword = async (req, res, next) => {
    try {
        const { email } = req.body;

        const user = await authService.findByEmail(email);
        if (!user) {
            // Return success even if user not found to prevent email enumeration
            return success(res, null, 'If that email address is in our database, we will send you an email to reset your password.');
        }

        // Create reset token (random string)
        const resetToken = crypto.randomBytes(32).toString('hex');
        
        // Expiration (e.g., 1 hour)
        const expiresAt = new Date(Date.now() + 60 * 60 * 1000); 

        // Save token to DB
        await authService.createPasswordResetToken(email, resetToken, expiresAt);

        // TODO: Send email with reset token link
        // Example: await emailService.sendPasswordReset(user.email, resetToken);
        console.log(`[DEV] Password Reset Token for ${email}: ${resetToken}`);

        return success(res, { debug_token: resetToken }, 'Password reset instructions sent to your email.');
    } catch (err) {
        next(err);
    }
};

/**
 * Reset Password
 */
const resetPassword = async (req, res, next) => {
    try {
        const { token, new_password } = req.body;

        // Verify token
        const resetRecord = await authService.verifyPasswordResetToken(token);
        if (!resetRecord) {
            return fail(res, 'Token is invalid or has expired.', 400);
        }

        // Reset password
        await authService.resetPassword(resetRecord.email, token, new_password);

        return success(res, null, 'Password has been successfully reset. You can now log in.');
    } catch (err) {
        next(err);
    }
};

/**
 * Logout
 * (Client-side usually handles this by deleting the token. For backend, we can just send a success message.)
 */
const logout = async (req, res, next) => {
    try {
        // If implementing token blacklisting, add token to blacklist here.
        return success(res, null, 'Logged out successfully.');
    } catch (err) {
        next(err);
    }
};

module.exports = {
    registerTeacher,
    registerSchool,
    login,
    getMe,
    refreshToken,
    changePassword,
    forgotPassword,
    resetPassword,
    logout
};
