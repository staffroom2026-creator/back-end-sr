<?php 

require_once dirname(__DIR__, 2) . '/config/env.php';

// Database Configuration
if (!defined('DB_HOST')) define('DB_HOST', env('DB_HOST', 'localhost'));
if (!defined('DB_USER')) define('DB_USER', env('DB_USER', 'root'));
if (!defined('DB_PASS')) define('DB_PASS', env('DB_PASS', ''));
if (!defined('DB_NAME')) define('DB_NAME', env('DB_NAME', 'staffroom_db'));

// Application PathsDB_HOST=localhost

if (!defined('APPROOT')) define('APPROOT', dirname(dirname(__FILE__)));
if (!defined('URLROOT')) define('URLROOT', env('APP_URL', 'http://localhost/staffroom_api/public'));

// Site Configuration
if (!defined('SITENAME')) define('SITENAME', 'StaffRoom API');

// API Keys and Secrets
$jwtSecret = defined('JWT_SECRET_KEY') ? JWT_SECRET_KEY : env('JWT_SECRET');
$appEnvironment = strtolower((string)env('APP_ENV', 'development'));
if ($appEnvironment === 'production' && (!is_string($jwtSecret) || trim($jwtSecret) === '' || in_array(trim($jwtSecret), ['your_jwt_secret_key_here', 'staffroom_secret_key_2026_secure_mvc'], true))) {
	throw new RuntimeException('A production JWT secret must be configured.');
}
if (!defined('JWT_SECRET_KEY')) define('JWT_SECRET_KEY', $jwtSecret ?: 'staffroom_secret_key_2026_secure_mvc');
if (!defined('UPLOAD_CV_DIR')) define('UPLOAD_CV_DIR', dirname(dirname(APPROOT)) . '/public/uploads/cvs');
if (!defined('UPLOAD_LOGO_DIR')) define('UPLOAD_LOGO_DIR', dirname(dirname(APPROOT)) . '/public/uploads/logos');

// Mail Configuration Constants
if (!defined('MAIL_HOST')) define('MAIL_HOST', env('MAIL_HOST', 'mail.staffroomng.com'));
if (!defined('MAIL_PORT')) define('MAIL_PORT', (int)env('MAIL_PORT', 465));
if (!defined('MAIL_USERNAME')) define('MAIL_USERNAME', env('MAIL_USERNAME', 'info@staffroomng.com'));
if (!defined('MAIL_PASSWORD')) define('MAIL_PASSWORD', env('MAIL_PASSWORD', ''));
if (!defined('MAIL_FROM_ADDRESS')) define('MAIL_FROM_ADDRESS', env('MAIL_FROM_ADDRESS', 'info@staffroomng.com'));
if (!defined('MAIL_FROM_EMAIL')) define('MAIL_FROM_EMAIL', env('MAIL_FROM_ADDRESS', 'info@staffroomng.com'));
if (!defined('MAIL_FROM_NAME')) define('MAIL_FROM_NAME', env('MAIL_FROM_NAME', 'StaffRoom'));
if (!defined('MAIL_ENCRYPTION')) define('MAIL_ENCRYPTION', env('MAIL_ENCRYPTION', 'ssl'));
if (!defined('CONTACT_SUPPORT_EMAIL')) define('CONTACT_SUPPORT_EMAIL', env('CONTACT_SUPPORT_EMAIL', 'info@staffroomng.com'));

?>
