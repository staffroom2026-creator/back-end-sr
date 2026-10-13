<?php
/**
 * API Routes Definition for StaffRoom PHP API
 * Exactly maps HTTP Method + URI patterns to Controller@Method actions and applies AuthMiddleware / RoleMiddleware.
 */

return [
    // Health Check
    'GET /health' => [
        'action' => 'Health@index',
        'middleware' => []
    ],

    'GET /api/dashboard' => [
        'action' => 'Dashboard@index',
        'middleware' => ['auth']
    ],

    // Auth Routes
    'POST /api/auth/register' => [
        'action' => 'AuthController@register',
        'middleware' => ['rate_limit:auth']
    ],
    'POST /api/auth/register_sch' => [
        'action' => 'AuthController@register_sch',
        'middleware' => ['rate_limit:auth']
    ],
    'POST /api/auth/login' => [
        'action' => 'AuthController@login',
        'middleware' => ['rate_limit:auth']
    ],
    'POST /api/auth/forgot-password' => ['action' => 'AuthController@forgetPassword', 'middleware' => ['rate_limit:auth']],
    'POST /api/auth/verify-reset-otp' => ['action' => 'AuthController@verifyResetOtp', 'middleware' => ['rate_limit:auth']],
    'POST /api/auth/reset-password' => ['action' => 'AuthController@resetPassword', 'middleware' => ['rate_limit:auth']],
    'DELETE /api/auth/delete-account' => [
        'action' => 'AuthController@delete_account',
        'middleware' => ['auth']
    ],
    'DELETE /api/profile/delete-account' => [
        'action' => 'AuthController@delete_account',
        'middleware' => ['auth']
    ],
    'POST /api/auth/verify-email' => [
        'action' => 'AuthController@verify_email',
        'middleware' => ['rate_limit:auth']
    ],
    'POST /api/auth/resend-verification' => [
        'action' => 'AuthController@resend_verification',
        'middleware' => ['rate_limit:auth']
    ],
    'POST /auth/register_sch' => [
        'action' => 'AuthController@register_sch',
        'middleware' => ['rate_limit:auth']
    ],
    'POST /auth/verify-email' => [
        'action' => 'AuthController@verify_email',
        'middleware' => ['rate_limit:auth']
    ],
    'POST /auth/resend-verification' => [
        'action' => 'AuthController@resend_verification',
        'middleware' => ['rate_limit:auth']
    ],

    // Public contact form
    'POST /api/contact' => [
        'action' => 'ContactController@store',
        'middleware' => []
    ],

    // Jobs Routes
    'GET /api/jobs' => [
        'action' => 'JobController@index',
        'middleware' => ['auth']
    ],
    'GET /api/jobs/{id}' => [
        'action' => 'JobController@index',
        'middleware' => ['auth']
    ],
    'POST /api/jobs' => [
        'action' => 'JobController@index',
        'middleware' => ['auth', 'role:school']
    ],
    'PUT /api/jobs/{id}' => [
        'action' => 'JobController@index',
        'middleware' => ['auth', 'role:school']
    ],
    'POST /api/jobs/{id}/publish' => [
        'action' => 'JobController@publish',
        'middleware' => ['auth', 'role:school']
    ],
    'DELETE /api/jobs/{id}' => [
        'action' => 'JobController@index',
        'middleware' => ['auth', 'role:school']
    ],

    // Teacher Directory Routes
    'GET /api/teachers' => [
        'action' => 'TeacherController@index',
        'middleware' => ['auth', 'role:school']
    ],
    'GET /api/teachers/{userId}' => [
        'action' => 'TeacherController@index',
        'middleware' => ['auth', 'role:school']
    ],
    'POST /api/teachers/{userId}/invite' => [
        'action' => 'TeacherController@invite',
        'middleware' => ['auth', 'role:school']
    ],

    // Applications Routes
    'GET /api/applications/apply/{jobId}' => [
        'action' => 'ApplicationController@applyRequirements',
        'middleware' => ['auth', 'role:teacher']
    ],
    'POST /api/applications/apply/{jobId}' => [
        'action' => 'ApplicationController@apply',
        'middleware' => ['auth', 'role:teacher']
    ],
    'GET /api/applications/my-applications' => [
        'action' => 'ApplicationController@my_applications',
        'middleware' => ['auth', 'role:teacher']
    ],
    'GET /api/applications/{id}' => [
        'action' => 'ApplicationController@show',
        'middleware' => ['auth']
    ],
    'GET /api/applications/job/{jobId}' => [
        'action' => 'ApplicationController@job',
        'middleware' => ['auth', 'role:school']
    ],
    'PATCH /api/applications/{id}/status' => [
        'action' => 'ApplicationController@status',
        'middleware' => ['auth', 'role:school']
    ],
    'POST /api/applications/{id}/reject' => [
        'action' => 'ApplicationController@reject',
        'middleware' => ['auth', 'role:school']
    ],
    'DELETE /api/applications/{id}' => [
        'action' => 'ApplicationController@withdraw',
        'middleware' => ['auth', 'role:teacher']
    ],
    'PATCH /api/applications/{id}/withdraw' => [
        'action' => 'ApplicationController@withdraw',
        'middleware' => ['auth', 'role:teacher']
    ],
    'GET /api/applications/{id}/interview' => [
        'action' => 'InterviewController@index',
        'middleware' => ['auth', 'role:school']
    ],
    'POST /api/applications/{id}/interview' => [
        'action' => 'InterviewController@index',
        'middleware' => ['auth', 'role:school']
    ],

    // Profiles Routes
    'GET /api/profiles/me' => [
        'action' => 'ProfileController@me',
        'middleware' => ['auth']
    ],
    'GET /api/profiles/teacher/profile-views' => [
        'action' => 'ProfileController@profileViews',
        'middleware' => ['auth', 'role:teacher']
    ],
    'PUT /api/profiles/teacher' => [
        'action' => 'ProfileController@teacher',
        'middleware' => ['auth', 'role:teacher']
    ],
    'POST /api/profiles/teacher/education' => ['action' => 'ProfileController@education', 'middleware' => ['auth', 'role:teacher']],
    'GET /api/profiles/teacher/education' => ['action' => 'ProfileController@education', 'middleware' => ['auth', 'role:teacher']],
    'PUT /api/profiles/teacher/education/{id}' => ['action' => 'ProfileController@education', 'middleware' => ['auth', 'role:teacher']],
    'PATCH /api/profiles/teacher/education/{id}' => ['action' => 'ProfileController@education', 'middleware' => ['auth', 'role:teacher']],
    'DELETE /api/profiles/teacher/education/{id}' => ['action' => 'ProfileController@education', 'middleware' => ['auth', 'role:teacher']],
    'POST /api/profiles/teacher/experience' => ['action' => 'ProfileController@experience', 'middleware' => ['auth', 'role:teacher']],
    'GET /api/profiles/teacher/experience' => ['action' => 'ProfileController@experience', 'middleware' => ['auth', 'role:teacher']],
    'PUT /api/profiles/teacher/experience/{id}' => ['action' => 'ProfileController@experience', 'middleware' => ['auth', 'role:teacher']],
    'PATCH /api/profiles/teacher/experience/{id}' => ['action' => 'ProfileController@experience', 'middleware' => ['auth', 'role:teacher']],
    'DELETE /api/profiles/teacher/experience/{id}' => ['action' => 'ProfileController@experience', 'middleware' => ['auth', 'role:teacher']],
    'PUT /api/profiles/school' => [
        'action' => 'ProfileController@school',
        'middleware' => ['auth', 'role:school']
    ],
    'POST /api/profiles/upload-cv' => [
        'action' => 'ProfileController@upload_cv',
        'middleware' => ['auth', 'role:teacher', 'rate_limit:upload']
    ],
    'POST /api/profiles/upload-logo' => [
        'action' => 'ProfileController@upload_logo',
        'middleware' => ['auth', 'role:school', 'rate_limit:upload']
    ],

    // Account and Settings Routes
    'GET /api/account/profile' => ['action' => 'AccountController@profile', 'middleware' => ['auth']],
    'PUT /api/account/profile' => ['action' => 'AccountController@profile', 'middleware' => ['auth']],
    'PATCH /api/account/profile' => ['action' => 'AccountController@profile', 'middleware' => ['auth']],
    'POST /api/account/profile-photo' => ['action' => 'AccountController@profilePhoto', 'middleware' => ['auth']],
    'POST /api/account/password' => ['action' => 'AccountController@password', 'middleware' => ['auth']],
    'GET /api/account/preferences' => ['action' => 'AccountController@preferences', 'middleware' => ['auth']],
    'PUT /api/account/preferences' => ['action' => 'AccountController@preferences', 'middleware' => ['auth']],
    'PATCH /api/account/preferences' => ['action' => 'AccountController@preferences', 'middleware' => ['auth']],
    'POST /api/account/email' => ['action' => 'AccountController@email', 'middleware' => ['auth']],
    'PATCH /api/account/email' => ['action' => 'AccountController@email', 'middleware' => ['auth']],
    'GET /api/account/login-activity' => ['action' => 'AccountController@loginActivity', 'middleware' => ['auth']],

    // Features Routes (Saved Jobs & Notifications)
    'POST /api/features/saved-jobs/{jobId}' => [
        'action' => 'FeatureController@saved_jobs',
        'middleware' => ['auth']
    ],
    'GET /api/features/saved-jobs' => [
        'action' => 'FeatureController@saved_jobs',
        'middleware' => ['auth']
    ],
    'DELETE /api/features/saved-jobs/{jobId}' => [
        'action' => 'FeatureController@saved_jobs',
        'middleware' => ['auth']
    ],
    'GET /api/features/saved-teachers' => [
        'action' => 'FeatureController@saved_teachers',
        'middleware' => ['auth', 'role:school']
    ],
    'POST /api/features/saved-teachers/{teacherId}' => [
        'action' => 'FeatureController@saved_teachers',
        'middleware' => ['auth', 'role:school']
    ],
    'DELETE /api/features/saved-teachers/{teacherId}' => [
        'action' => 'FeatureController@saved_teachers',
        'middleware' => ['auth', 'role:school']
    ],
    'GET /api/features/job-alerts' => ['action' => 'FeatureController@job_alerts', 'middleware' => ['auth', 'role:teacher']],
    'PUT /api/features/job-alerts' => ['action' => 'FeatureController@job_alerts', 'middleware' => ['auth', 'role:teacher']],
    'PATCH /api/features/job-alerts' => ['action' => 'FeatureController@job_alerts', 'middleware' => ['auth', 'role:teacher']],
    'GET /api/features/notifications' => [
        'action' => 'FeatureController@notifications',
        'middleware' => ['auth']
    ],
    'PATCH /api/features/notifications/{id}/read' => [
        'action' => 'FeatureController@notifications',
        'middleware' => ['auth']
    ],
    'DELETE /api/features/notifications/{id}' => [
        'action' => 'FeatureController@notifications',
        'middleware' => ['auth']
    ],

    // Admin Routes
    'GET /api/admin/stats' => [
        'action' => 'AdminController@stats',
        'middleware' => ['auth', 'role:admin']
    ],
    'GET /api/admin/verifications' => [
        'action' => 'AdminController@verifications',
        'middleware' => ['auth', 'role:admin']
    ],
    'PATCH /api/admin/verifications/{id}' => [
        'action' => 'AdminController@verifications',
        'middleware' => ['auth', 'role:admin']
    ],

    // Internal Admin Routes (JWT + police role)
    'GET /api/internal-admin/overview' => ['action' => 'InternalAdminController@overview', 'middleware' => ['auth', 'role:police']],
    'GET /api/internal-admin/jobs' => ['action' => 'InternalAdminController@jobs', 'middleware' => ['auth', 'role:police']],
    'GET /api/internal-admin/jobs/{jobId}' => ['action' => 'InternalAdminController@job', 'middleware' => ['auth', 'role:police']],
    'PATCH /api/internal-admin/jobs/{jobId}/status' => ['action' => 'InternalAdminController@jobStatus', 'middleware' => ['auth', 'role:police', 'rate_limit:moderation']],
    'GET /api/internal-admin/schools' => ['action' => 'InternalAdminController@schools', 'middleware' => ['auth', 'role:police']],
    'GET /api/internal-admin/schools/{schoolId}' => ['action' => 'InternalAdminController@school', 'middleware' => ['auth', 'role:police']],
    'PATCH /api/internal-admin/schools/{schoolId}/status' => ['action' => 'InternalAdminController@schoolStatus', 'middleware' => ['auth', 'role:police', 'rate_limit:moderation']],
    'GET /api/internal-admin/teachers' => ['action' => 'InternalAdminController@teachers', 'middleware' => ['auth', 'role:police']],
    'GET /api/internal-admin/teachers/{userId}' => ['action' => 'InternalAdminController@teacher', 'middleware' => ['auth', 'role:police']],
    'GET /api/internal-admin/teachers/{userId}/applications' => ['action' => 'InternalAdminController@teacherApplications', 'middleware' => ['auth', 'role:police']],
    'PATCH /api/internal-admin/teachers/{userId}/status' => ['action' => 'InternalAdminController@teacherStatus', 'middleware' => ['auth', 'role:police']],
    'GET /api/internal-admin/admins' => ['action' => 'InternalAdminController@admins', 'middleware' => ['auth', 'role:police']],
    'POST /api/internal-admin/admins/invitations' => ['action' => 'InternalAdminController@inviteAdmin', 'middleware' => ['auth', 'role:police', 'rate_limit:moderation']],
    'GET /api/internal-admin/admins/invitations' => ['action' => 'InternalAdminController@invitations', 'middleware' => ['auth', 'role:police']],
    'POST /api/internal-admin/admins/invitations/{invitationId}/resend' => ['action' => 'InternalAdminController@resendInvitation', 'middleware' => ['auth', 'role:police', 'rate_limit:moderation']],
    'POST /api/internal-admin/admins/invitations/{invitationId}/revoke' => ['action' => 'InternalAdminController@revokeInvitation', 'middleware' => ['auth', 'role:police']],
    'PATCH /api/internal-admin/admins/{adminId}/status' => ['action' => 'InternalAdminController@adminStatus', 'middleware' => ['auth', 'role:police']],
    'PATCH /api/internal-admin/admins/{adminId}/role' => ['action' => 'InternalAdminController@adminRole', 'middleware' => ['auth', 'role:police']],
    'GET /api/internal-admin/reports' => ['action' => 'InternalAdminController@reports', 'middleware' => ['auth', 'role:police']],
    'GET /api/internal-admin/reports/{reportId}' => ['action' => 'InternalAdminController@report', 'middleware' => ['auth', 'role:police']],
    'PATCH /api/internal-admin/reports/{reportId}' => ['action' => 'InternalAdminController@reportStatus', 'middleware' => ['auth', 'role:police']],
    'GET /api/internal-admin/notifications' => ['action' => 'InternalAdminController@notifications', 'middleware' => ['auth', 'role:police']],
    'PATCH /api/internal-admin/notifications/{notificationId}/read' => ['action' => 'InternalAdminController@notificationRead', 'middleware' => ['auth', 'role:police']],
    'PATCH /api/internal-admin/notifications/read-all' => ['action' => 'InternalAdminController@notificationReadAll', 'middleware' => ['auth', 'role:police']],
    'GET /api/internal-admin/settings' => ['action' => 'InternalAdminController@settings', 'middleware' => ['auth', 'role:police']],
    'PATCH /api/internal-admin/settings' => ['action' => 'InternalAdminController@updateSettings', 'middleware' => ['auth', 'role:police']],
    'GET /api/internal-admin/audit-log' => ['action' => 'InternalAdminController@auditLog', 'middleware' => ['auth', 'role:police']],
    'POST /api/internal-admin/invitations/accept' => ['action' => 'InternalAdminController@acceptInvitation', 'middleware' => []],

    // Backward compatibility aliases supporting without /api/ prefix and :param notation
    'POST /auth/register' => ['action' => 'AuthController@register', 'middleware' => ['rate_limit:auth']],
    'POST /auth/login' => ['action' => 'AuthController@login', 'middleware' => ['rate_limit:auth']],
    'POST /auth/forgot-password' => ['action' => 'AuthController@forgetPassword', 'middleware' => ['rate_limit:auth']],
    'POST /auth/verify-reset-otp' => ['action' => 'AuthController@verifyResetOtp', 'middleware' => ['rate_limit:auth']],
    'POST /auth/reset-password' => ['action' => 'AuthController@resetPassword', 'middleware' => ['rate_limit:auth']],
    'DELETE /auth/delete-account' => ['action' => 'AuthController@delete_account', 'middleware' => ['auth']],
    'DELETE /profile/delete-account' => ['action' => 'AuthController@delete_account', 'middleware' => ['auth']],

    'GET /jobs' => ['action' => 'JobController@index', 'middleware' => ['auth']],
    'GET /jobs/:id' => ['action' => 'JobController@index', 'middleware' => ['auth']],
    'POST /jobs' => ['action' => 'JobController@index', 'middleware' => ['auth', 'role:school']],
    'PUT /jobs/:id' => ['action' => 'JobController@index', 'middleware' => ['auth', 'role:school']],
    'POST /jobs/:id/publish' => ['action' => 'JobController@publish', 'middleware' => ['auth', 'role:school']],
    'DELETE /jobs/:id' => ['action' => 'JobController@index', 'middleware' => ['auth', 'role:school']],

    'GET /teachers' => ['action' => 'TeacherController@index', 'middleware' => ['auth', 'role:school']],
    'GET /teachers/:userId' => ['action' => 'TeacherController@index', 'middleware' => ['auth', 'role:school']],
    'POST /teachers/:userId/invite' => ['action' => 'TeacherController@invite', 'middleware' => ['auth', 'role:school']],
    'GET /dashboard' => ['action' => 'Dashboard@index', 'middleware' => ['auth']],
    'GET /profiles/teacher/profile-views' => ['action' => 'ProfileController@profileViews', 'middleware' => ['auth', 'role:teacher']],

    'GET /applications/apply/:jobId' => ['action' => 'ApplicationController@applyRequirements', 'middleware' => ['auth', 'role:teacher']],
    'POST /applications/apply/:jobId' => ['action' => 'ApplicationController@apply', 'middleware' => ['auth', 'role:teacher']],
    'GET /applications/my-applications' => ['action' => 'ApplicationController@my_applications', 'middleware' => ['auth', 'role:teacher']],
    'GET /applications/:id' => ['action' => 'ApplicationController@show', 'middleware' => ['auth']],
    'GET /applications/job/:jobId' => ['action' => 'ApplicationController@job', 'middleware' => ['auth', 'role:school']],
    'PATCH /applications/:id/status' => ['action' => 'ApplicationController@status', 'middleware' => ['auth', 'role:school']],
    'POST /applications/:id/reject' => ['action' => 'ApplicationController@reject', 'middleware' => ['auth', 'role:school']],
    'DELETE /applications/:id' => ['action' => 'ApplicationController@withdraw', 'middleware' => ['auth', 'role:teacher']],
    'PATCH /applications/:id/withdraw' => ['action' => 'ApplicationController@withdraw', 'middleware' => ['auth', 'role:teacher']],
    'GET /applications/:id/interview' => ['action' => 'InterviewController@index', 'middleware' => ['auth', 'role:school']],
    'POST /applications/:id/interview' => ['action' => 'InterviewController@index', 'middleware' => ['auth', 'role:school']],

    'GET /profiles/me' => ['action' => 'ProfileController@me', 'middleware' => ['auth']],
    'PUT /profiles/teacher' => ['action' => 'ProfileController@teacher', 'middleware' => ['auth', 'role:teacher']],
    'POST /profiles/teacher/education' => ['action' => 'ProfileController@education', 'middleware' => ['auth', 'role:teacher']],
    'GET /profiles/teacher/education' => ['action' => 'ProfileController@education', 'middleware' => ['auth', 'role:teacher']],
    'PUT /profiles/teacher/education/:id' => ['action' => 'ProfileController@education', 'middleware' => ['auth', 'role:teacher']],
    'PATCH /profiles/teacher/education/:id' => ['action' => 'ProfileController@education', 'middleware' => ['auth', 'role:teacher']],
    'DELETE /profiles/teacher/education/:id' => ['action' => 'ProfileController@education', 'middleware' => ['auth', 'role:teacher']],
    'POST /profiles/teacher/experience' => ['action' => 'ProfileController@experience', 'middleware' => ['auth', 'role:teacher']],
    'GET /profiles/teacher/experience' => ['action' => 'ProfileController@experience', 'middleware' => ['auth', 'role:teacher']],
    'PUT /profiles/teacher/experience/:id' => ['action' => 'ProfileController@experience', 'middleware' => ['auth', 'role:teacher']],
    'PATCH /profiles/teacher/experience/:id' => ['action' => 'ProfileController@experience', 'middleware' => ['auth', 'role:teacher']],
    'DELETE /profiles/teacher/experience/:id' => ['action' => 'ProfileController@experience', 'middleware' => ['auth', 'role:teacher']],
    'PUT /profiles/school' => ['action' => 'ProfileController@school', 'middleware' => ['auth', 'role:school']],
    'POST /profiles/upload-cv' => ['action' => 'ProfileController@upload_cv', 'middleware' => ['auth', 'role:teacher', 'rate_limit:upload']],
    'POST /profiles/upload-logo' => ['action' => 'ProfileController@upload_logo', 'middleware' => ['auth', 'role:school', 'rate_limit:upload']],

    'GET /account/profile' => ['action' => 'AccountController@profile', 'middleware' => ['auth']],
    'PUT /account/profile' => ['action' => 'AccountController@profile', 'middleware' => ['auth']],
    'PATCH /account/profile' => ['action' => 'AccountController@profile', 'middleware' => ['auth']],
    'POST /account/password' => ['action' => 'AccountController@password', 'middleware' => ['auth']],
    'GET /account/preferences' => ['action' => 'AccountController@preferences', 'middleware' => ['auth']],
    'PUT /account/preferences' => ['action' => 'AccountController@preferences', 'middleware' => ['auth']],
    'PATCH /account/preferences' => ['action' => 'AccountController@preferences', 'middleware' => ['auth']],
    'POST /account/email' => ['action' => 'AccountController@email', 'middleware' => ['auth']],
    'PATCH /account/email' => ['action' => 'AccountController@email', 'middleware' => ['auth']],

    'POST /features/saved-jobs/:jobId' => ['action' => 'FeatureController@saved_jobs', 'middleware' => ['auth']],
    'GET /features/saved-jobs' => ['action' => 'FeatureController@saved_jobs', 'middleware' => ['auth']],
    'DELETE /features/saved-jobs/:jobId' => ['action' => 'FeatureController@saved_jobs', 'middleware' => ['auth']],
    'GET /features/saved-teachers' => ['action' => 'FeatureController@saved_teachers', 'middleware' => ['auth', 'role:school']],
    'POST /features/saved-teachers/:teacherId' => ['action' => 'FeatureController@saved_teachers', 'middleware' => ['auth', 'role:school']],
    'DELETE /features/saved-teachers/:teacherId' => ['action' => 'FeatureController@saved_teachers', 'middleware' => ['auth', 'role:school']],
    'GET /features/job-alerts' => ['action' => 'FeatureController@job_alerts', 'middleware' => ['auth', 'role:teacher']],
    'PUT /features/job-alerts' => ['action' => 'FeatureController@job_alerts', 'middleware' => ['auth', 'role:teacher']],
    'PATCH /features/job-alerts' => ['action' => 'FeatureController@job_alerts', 'middleware' => ['auth', 'role:teacher']],
    'GET /features/notifications' => ['action' => 'FeatureController@notifications', 'middleware' => ['auth']],
    'PATCH /features/notifications/:id/read' => ['action' => 'FeatureController@notifications', 'middleware' => ['auth']],
    'DELETE /features/notifications/:id' => ['action' => 'FeatureController@notifications', 'middleware' => ['auth']],

    'GET /admin/stats' => ['action' => 'AdminController@stats', 'middleware' => ['auth', 'role:admin']],
    'GET /admin/verifications' => ['action' => 'AdminController@verifications', 'middleware' => ['auth', 'role:admin']],
    'PATCH /admin/verifications/:id' => ['action' => 'AdminController@verifications', 'middleware' => ['auth', 'role:admin']],
];
?>
