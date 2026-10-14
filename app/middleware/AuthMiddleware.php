<?php
/**
 * AuthMiddleware
 * Handles JWT Authentication, token verification, and current database user resolution.
 */

require_once __DIR__ . '/../helpers/JWT.php';
require_once __DIR__ . '/../helpers/Response.php';
if (file_exists(__DIR__ . '/../helpers/ResponseHelper.php')) {
    require_once __DIR__ . '/../helpers/ResponseHelper.php';
}
require_once __DIR__ . '/../models/User.php';

class AuthMiddleware
{
    public static $user = null;

    /**
     * Authenticate user from Authorization Bearer token.
     * Validates token using JWT_SECRET, decodes user id, role, email,
     * fetches current user from database, rejects invalid/expired/missing token or missing user,
     * and attaches authenticated user to the request context.
     *
     * @return object
     */
    public static function authenticate()
    {
        // 1. Read Authorization header
        $authHeader = null;
        if (isset($_SERVER['Authorization'])) {
            $authHeader = trim($_SERVER['Authorization']);
        } elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $authHeader = trim($_SERVER['HTTP_AUTHORIZATION']);
        } elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            $authHeader = trim($_SERVER['REDIRECT_HTTP_AUTHORIZATION']);
        } elseif (function_exists('getallheaders')) {
            $requestHeaders = getallheaders();
            $headers = array_combine(array_map('ucwords', array_keys($requestHeaders)), array_values($requestHeaders));
            if (isset($headers['Authorization'])) {
                $authHeader = trim($headers['Authorization']);
            }
        } elseif (function_exists('apache_request_headers')) {
            $requestHeaders = apache_request_headers();
            $headers = array_combine(array_map('ucwords', array_keys($requestHeaders)), array_values($requestHeaders));
            if (isset($headers['Authorization'])) {
                $authHeader = trim($headers['Authorization']);
            }
        }

        // 2. Expect Bearer token & check missing token
        if (!$authHeader || !preg_match('/Bearer\s+(\S+)/i', $authHeader, $matches)) {
            Response::error('Unauthorized: Missing token', 401);
        }

        $token = $matches[1];

        // 3. Validate JWT token using JWT_SECRET and decode payload
        try {
            $secretKey = defined('JWT_SECRET_KEY') ? JWT_SECRET_KEY : (function_exists('env') ? env('JWT_SECRET') : null);
            if (!is_string($secretKey) || trim($secretKey) === '') {
                Response::error('Authentication service unavailable', 500);
            }
            $decoded = JWT::decode($token, $secretKey, true);

            if (!$decoded || !isset($decoded->user_id) || isset($decoded->purpose)) {
                Response::error('Unauthorized: Invalid token', 401);
            }
        } catch (Exception $e) {
            $msg = $e->getMessage();
            if (stripos($msg, 'Expired') !== false) {
                Response::error('Session expired. Please login again.', 401);
            } else {
                Response::error('Unauthorized: Invalid token', 401);
            }
        }

        // 4. Fetch current user from database
        try {
            $userModel = new User();
            $user = $userModel->findById($decoded->user_id);
        } catch (Exception $e) {
            $user = false;
        }

        // 5. Reject user not found
        if (!$user) {
            Response::error('Unauthorized: User not found', 401);
        }

        // Ensure user_id property exists
        if (!isset($user->user_id)) {
            Response::error('Unauthorized: User not found', 401);
        }

        $status = strtolower((string)($user->status ?? 'active'));
        if (!empty($user->deleted_at) || $status === 'deleted') {
            Response::error('This account has been deleted.', 401);
        }

        if (in_array($status, ['inactive', 'suspended'])) {
            Response::error('This account is inactive.', 401);
        }

        if ((int)($user->active ?? 1) !== 1) {
            Response::error('This account is inactive.', 401);
        }

        // 6. Attach authenticated user to the request/context
        self::$user = $user;
        $_REQUEST['auth_user'] = $user;
        $GLOBALS['auth_user'] = $user;

        return $user;
    }

    /**
     * Authenticate and check if user's role matches any of the allowed roles.
     * Returns authenticated user object if authorized, else returns 403 Forbidden.
     *
     * @param array|string $allowedRoles
     * @return object
     */
    public static function authorize($allowedRoles = [])
    {
        $user = self::authenticate();

        if (!empty($allowedRoles)) {
            if (is_string($allowedRoles)) {
                $allowedRoles = [$allowedRoles];
            }
            if (!in_array($user->role, (array)$allowedRoles)) {
                Response::error('Forbidden: Access denied. Required role(s): ' . implode(', ', (array)$allowedRoles), 403);
            }
        }

        return $user;
    }
}
?>
