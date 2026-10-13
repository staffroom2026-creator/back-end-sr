<?php
/**
 * RateLimitMiddleware
 * Enforces rate limiting per IP address using temporary file storage to protect against DDoS and brute-force attacks.
 */

require_once __DIR__ . '/../helpers/Response.php';

class RateLimitMiddleware
{
    /**
     * Check rate limit for the client IP address.
     *
     * @param int $maxRequests Maximum allowed requests within the time window
     * @param int $windowSeconds Time window in seconds
     * @param string $prefix Prefix/category for rate limit bucket (e.g., 'auth', 'general', 'upload')
     */
    public static function throttle($maxRequests = 60, $windowSeconds = 60, $prefix = 'general')
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        // Sanitize IP and prefix to create safe filename
        $safeIp = preg_replace('/[^a-zA-Z0-9_.:-]/', '_', $ip);
        $safePrefix = preg_replace('/[^a-zA-Z0-9_-]/', '_', $prefix);
        
        $tempDir = sys_get_temp_dir() . '/staffroom_ratelimit';
        if (!is_dir($tempDir)) {
            @mkdir($tempDir, 0777, true);
        }

        $filePath = $tempDir . '/' . $safePrefix . '_' . md5($safeIp) . '.json';
        $currentTime = time();

        $data = [
            'count' => 0,
            'start_time' => $currentTime
        ];

        if (file_exists($filePath)) {
            $raw = @file_get_contents($filePath);
            if ($raw) {
                $decoded = @json_decode($raw, true);
                if (is_array($decoded) && isset($decoded['count'], $decoded['start_time'])) {
                    $data = $decoded;
                }
            }
        }

        // Check if window has expired, reset if so
        if ($currentTime - $data['start_time'] >= $windowSeconds) {
            $data['count'] = 0;
            $data['start_time'] = $currentTime;
        }

        $data['count']++;

        // Save updated count back to file
        @file_put_contents($filePath, json_encode($data), LOCK_EX);

        // Add rate limit headers
        $remaining = max(0, $maxRequests - $data['count']);
        @header('X-RateLimit-Limit: ' . $maxRequests);
        @header('X-RateLimit-Remaining: ' . $remaining);
        @header('X-RateLimit-Reset: ' . ($data['start_time'] + $windowSeconds));

        if ($data['count'] > $maxRequests) {
            $retryAfter = ($data['start_time'] + $windowSeconds) - $currentTime;
            @header('Retry-After: ' . max(1, $retryAfter));
            Response::error('Too Many Requests. Please slow down.', 429);
        }
    }

    /**
     * Named check presets
     *
     * @param string $type ('auth', 'upload', or 'general')
     */
    public static function check($type = 'general')
    {
        switch ($type) {
            case 'auth':
                // 10 requests per minute for login/register
                self::throttle(10, 60, 'auth');
                break;
            case 'upload':
                // 5 requests per minute for uploads
                self::throttle(5, 60, 'upload');
                break;
            case 'moderation':
                self::throttle(30, 60, 'moderation');
                break;
            case 'general':
            default:
                // 60 requests per minute for general API
                self::throttle(60, 60, 'general');
                break;
        }
    }
}
?>
