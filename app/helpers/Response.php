<?php
/**
 * Response Helper Class
 * Handles consistent JSON API responses across the StaffRoom API.
 */

class Response
{
    /**
     * Send a successful JSON response
     * Signature: success($data, $message = 'Success', $statusCode = 200)
     * Supports flexible argument order for backward compatibility with ($message, $data, $statusCode).
     *
     * @param mixed $data
     * @param string $message
     * @param int $statusCode
     * @return void
     */
    public static function success($data = null, $message = 'Success', $statusCode = 200, $extra = [])
    {
        // Support backward compatibility if called as success($messageString, $dataArray, $code)
        if (is_string($data) && (is_array($message) || is_object($message) || is_null($message))) {
            $tempMessage = $data;
            $data = $message;
            $message = $tempMessage;
        }

        @http_response_code($statusCode);

        $response = [
            'success' => true,
            'status' => true,
            'message' => $message,
            'data' => $data
        ];

        // Merge root-level data for Postman compatibility (e.g. token, user, jobs, etc.)
        if (is_array($data)) {
            foreach ($data as $key => $val) {
                if (!isset($response[$key]) && is_string($key)) {
                    $response[$key] = $val;
                }
            }
        } elseif (is_object($data)) {
            foreach (get_object_vars($data) as $key => $val) {
                if (!isset($response[$key])) {
                    $response[$key] = $val;
                }
            }
        }

        if (!empty($extra) && is_array($extra)) {
            $response = array_merge($response, $extra);
        }

        @header('Content-Type: application/json; charset=utf-8');
        echo json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Send an error JSON response
     * Signature: error($message, $statusCode = 400, $errors = [])
     *
     * @param string $message
     * @param int $statusCode
     * @param array $errors
     * @return void
     */
    public static function error($message = 'Error', $statusCode = 400, $errors = [])
    {
        @http_response_code($statusCode);

        $response = [
            'success' => false,
            'status' => false,
            'message' => $message
        ];

        if (!empty($errors)) {
            $response['errors'] = $errors;
        }

        @header('Content-Type: application/json; charset=utf-8');
        echo json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }
}
?>
