<?php
/**
 * Application Configuration
 * Reads environment variables using env() helper.
 */

require_once __DIR__ . '/env.php';

return [
    'name' => 'StaffRoom API',
    'env' => env('APP_ENV', 'development'),
    'debug' => env('APP_ENV', 'development') === 'development',
    'url' => env('APP_URL', 'http://localhost/staffroom_api/public'),
    'api_prefix' => env('API_PREFIX', '/api'),
    'jwt_secret' => env('JWT_SECRET'),
    'jwt_expiry' => (int)env('JWT_EXPIRES_IN', 2592000), // 30 days in seconds
    'uploads' => [
        'cv_dir' => dirname(__DIR__) . '/public/uploads/cvs',
        'logo_dir' => dirname(__DIR__) . '/public/uploads/logos',
        'allowed_cv_types' => ['pdf', 'doc', 'docx'],
        'allowed_logo_types' => ['jpg', 'jpeg', 'png', 'webp', 'gif'],
        'max_file_size' => 5 * 1024 * 1024, // 5MB
    ],
];
?>
