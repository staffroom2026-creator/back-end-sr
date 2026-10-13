<?php
/**
 * RoleMiddleware
 * Enforces Role-Based Access Control (RBAC) across Teacher, School, and Admin roles.
 */

require_once __DIR__ . '/AuthMiddleware.php';
require_once __DIR__ . '/../helpers/Response.php';

class RoleMiddleware
{
    /**
     * Verify that the currently authenticated user has one of the allowed roles.
     * Accepts a single role (e.g. 'school') or array of roles (e.g. ['school', 'admin']).
     *
     * @param array|string $allowedRoles
     * @return object $user
     */
    public static function allow($allowedRoles)
    {
        $user = AuthMiddleware::authenticate();

        if (is_string($allowedRoles)) {
            $allowedRoles = [$allowedRoles];
        }

        if (!empty($allowedRoles) && !in_array($user->role, (array)$allowedRoles)) {
            Response::error('Forbidden: Access denied. Required role(s): ' . implode(', ', (array)$allowedRoles), 403);
        }

        return $user;
    }

    /**
     * Alias for allow()
     *
     * @param array|string $allowedRoles
     * @return object
     */
    public static function authorize($allowedRoles)
    {
        return self::allow($allowedRoles);
    }

    /**
     * Alias for allow()
     *
     * @param array|string $allowedRoles
     * @return object
     */
    public static function check($allowedRoles)
    {
        return self::allow($allowedRoles);
    }

    /**
     * Alias for allow()
     *
     * @param array|string $allowedRoles
     * @return object
     */
    public static function handle($allowedRoles)
    {
        return self::allow($allowedRoles);
    }
}
?>
