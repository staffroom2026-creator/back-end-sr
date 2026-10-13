<?php

if (isset($_SERVER['HTTP_ORIGIN'])) {
    // Whitelist specific origins — add production domains via CORS_ORIGINS env variable
    $allowedOrigins = [
        'http://localhost',
        'http://localhost:3000',
        'http://localhost:5173',
        'http://localhost:5174',
        'http://localhost:5175',
        'https://staffroomng.com',    
        'https://front-end-sr.vercel.app'
    ];

    // Load additional origins from environment (comma-separated)
    $envOrigins = function_exists('env') ? env('CORS_ORIGINS', '') : (getenv('CORS_ORIGINS') ?: '');
    if (!empty($envOrigins)) {
        $extraOrigins = array_map('trim', explode(',', $envOrigins));
        $allowedOrigins = array_merge($allowedOrigins, $extraOrigins);
    }

    if (in_array($_SERVER['HTTP_ORIGIN'], $allowedOrigins, true)) {
        header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
        header("Access-Control-Allow-Credentials: true");
    }
    // If origin not whitelisted, no Access-Control-Allow-Origin header is sent (browser blocks the request)
} else {
    // No Origin header present (same-origin or non-browser request) — do not send wildcard
    // Omitting the header is safer than sending *
}

// Allow specific methods
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, PATCH, OPTIONS");

// Allow specific headers
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Accept");

// Expose headers for the client to access
header("Access-Control-Expose-Headers: Content-Length, X-JSON");

// Caching and other headers
header("Access-Control-Max-Age: 86400"); // Cache preflight response for 24 hours

// Handle preflight OPTIONS requests
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header("HTTP/1.1 204 No Content");
    exit();
}

include_once('jwt_helper.php');

if (!function_exists('getData')) {
    function getData($strict = false)
    {
        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            // Check if $_POST has data or if Content-Type is formdata / urlencoded
            if (!empty($_POST)) {
                return $_POST;
            }
            if ($strict && trim($raw) !== '') {
                return ['__invalid_json' => true];
            }
            return [];
        }
        return $data;
    }
}
?>