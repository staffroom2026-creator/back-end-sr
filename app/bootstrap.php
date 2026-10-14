<?php

try {
    // Custom exception handler — log details server-side, return generic message to client
    set_exception_handler(function ($exception) {
        // Log full details for debugging (server-side only)
        error_log("Uncaught Exception: " . $exception->getMessage() . " in " . $exception->getFile() . ":" . $exception->getLine());
        error_log("Stack trace: " . $exception->getTraceAsString());

        header('Content-Type: application/json; charset=utf-8');
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'status' => false,
            'message' => 'Internal Server Error'
        ]);
        exit(PHP_SAPI === 'cli' ? 1 : 0);
    });

    // Load Config
    require_once 'config/config.php';

    // Load Headers (includes jwt_helper.php)
    require_once 'helpers/headers.php';
    require_once 'helpers/ResponseHelper.php';

    // Load Middleware
    if (file_exists(__DIR__ . '/middleware/AuthMiddleware.php')) {
        require_once 'middleware/AuthMiddleware.php';
    }

    // Load Optional Helper Files
    if (file_exists('helpers/Mobile/src/vendor/autoload.php')) require_once 'helpers/Mobile/src/vendor/autoload.php';
    if (file_exists('helpers/Mobile/src/autoload.php')) require_once 'helpers/Mobile/src/autoload.php';
    if (file_exists('helpers/vendor/autoload.php')) require_once 'helpers/vendor/autoload.php';
    if (file_exists('helpers/url_helper.php')) require_once 'helpers/url_helper.php';
    if (file_exists('helpers/phpMailer/PHPMailerAutoload.php')) require_once 'helpers/phpMailer/PHPMailerAutoload.php';
    if (file_exists('helpers/php/src/AES256.php')) require_once 'helpers/php/src/AES256.php';

    // Load Core Helpers and Middleware
    require_once __DIR__ . '/helpers/Response.php';
    if (file_exists(__DIR__ . '/helpers/ResponseHelper.php')) require_once __DIR__ . '/helpers/ResponseHelper.php';
    if (file_exists(__DIR__ . '/helpers/Validator.php')) require_once __DIR__ . '/helpers/Validator.php';
    if (file_exists(__DIR__ . '/helpers/JWT.php')) require_once __DIR__ . '/helpers/JWT.php';
    if (file_exists(__DIR__ . '/helpers/UploadHelper.php')) require_once __DIR__ . '/helpers/UploadHelper.php';
    if (file_exists(__DIR__ . '/middleware/AuthMiddleware.php')) require_once __DIR__ . '/middleware/AuthMiddleware.php';
    if (file_exists(__DIR__ . '/middleware/RoleMiddleware.php')) require_once __DIR__ . '/middleware/RoleMiddleware.php';
    if (file_exists(__DIR__ . '/middleware/RateLimitMiddleware.php')) require_once __DIR__ . '/middleware/RateLimitMiddleware.php';

    // Autoload Core Libraries, Models, Controllers, and Middleware
    spl_autoload_register(function ($className) {
        if (file_exists(__DIR__ . '/libraries/' . $className . '.php')) {
            require_once __DIR__ . '/libraries/' . $className . '.php';
        } elseif (file_exists(__DIR__ . '/models/' . $className . '.php')) {
            require_once __DIR__ . '/models/' . $className . '.php';
        } elseif (file_exists(__DIR__ . '/controllers/' . $className . '.php')) {
            require_once __DIR__ . '/controllers/' . $className . '.php';
        } elseif (file_exists(__DIR__ . '/middleware/' . $className . '.php')) {
            require_once __DIR__ . '/middleware/' . $className . '.php';
        } elseif (file_exists(__DIR__ . '/helpers/' . $className . '.php')) {
            require_once __DIR__ . '/helpers/' . $className . '.php';
        }
    });
} catch (Throwable $th) {
    // Catch block to handle any runtime errors
    header('Content-Type: application/json');
    http_response_code(500); // Internal Server Error
    echo json_encode([
        'status' => false,
        'message' => 'Internal Server Error',
    ]);
    exit;
}
?>