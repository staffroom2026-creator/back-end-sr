<?php
/**
 * ResponseHelper wrapper around Response helper for backward compatibility
 */

require_once __DIR__ . '/Response.php';

class ResponseHelper
{
    public static function send($status, $message, $data = null, $statusCode = 200, $extra = [])
    {
        if ($status) {
            Response::success($data, $message, $statusCode, $extra);
        } else {
            Response::error($message, $statusCode, is_array($data) ? $data : []);
        }
    }

    public static function success($message = 'Success', $data = null, $statusCode = 200, $extra = [])
    {
        Response::success($data, $message, $statusCode, $extra);
    }

    public static function error($message = 'Error', $statusCode = 400, $data = null)
    {
        Response::error($message, $statusCode, is_array($data) ? $data : []);
    }
}
?>
