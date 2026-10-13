<?php
/** Focused contract checks for internal admin routing and authorization configuration. */
require_once __DIR__ . '/../app/bootstrap.php';

function assertInternalAdmin($condition, $message)
{
    if (!$condition) throw new RuntimeException($message);
}

$routes = require __DIR__ . '/../routes/api.php';
$protected = [
    'GET /api/internal-admin/overview',
    'GET /api/internal-admin/jobs',
    'PATCH /api/internal-admin/jobs/{jobId}/status',
    'GET /api/internal-admin/schools',
    'PATCH /api/internal-admin/schools/{schoolId}/status',
    'GET /api/internal-admin/teachers',
    'GET /api/internal-admin/admins',
    'GET /api/internal-admin/reports',
    'GET /api/internal-admin/notifications',
    'GET /api/internal-admin/settings',
    'GET /api/internal-admin/audit-log'
];
foreach ($protected as $route) {
    assertInternalAdmin(isset($routes[$route]), "Missing route: {$route}");
    assertInternalAdmin(in_array('role:police', $routes[$route]['middleware'], true), "Route is not police-protected: {$route}");
}
assertInternalAdmin(($routes['POST /api/internal-admin/invitations/accept']['middleware'] ?? null) === [], 'Invitation acceptance must be public');
assertInternalAdmin(strpos(file_get_contents(__DIR__ . '/../database/migrations/2026_09_16_internal_admin_dashboard.sql'), "ENUM('teacher','school','admin','police')") !== false, 'Police role migration is missing');
echo "Internal admin endpoint tests passed\n";