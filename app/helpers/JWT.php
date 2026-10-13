<?php
/**
 * JWT Helper Class
 */

require_once __DIR__ . '/jwt_helper.php';

// If jwt_helper.php already defines JWT class, we verify and ensure standard methods are accessible.
if (!class_exists('JWT')) {
    class JWT
    {
        public static function encode($payload, $key, $alg = 'HS256')
        {
            $header = json_encode(['typ' => 'JWT', 'alg' => $alg]);
            $header = self::base64UrlEncode($header);
            $payload = self::base64UrlEncode(json_encode($payload));
            $signature = hash_hmac('sha256', "$header.$payload", $key, true);
            $signature = self::base64UrlEncode($signature);
            return "$header.$payload.$signature";
        }

        public static function decode($token, $key, $verify = true)
        {
            $parts = explode('.', $token);
            if (count($parts) != 3) {
                return false;
            }
            list($header, $payload, $signature) = $parts;
            if ($verify) {
                $sig = self::base64UrlEncode(hash_hmac('sha256', "$header.$payload", $key, true));
                if (!hash_equals($signature, $sig)) {
                    return false;
                }
                $obj = json_decode(self::base64UrlDecode($payload));
                if (isset($obj->exp) && time() >= $obj->exp) {
                    throw new Exception('Expired token');
                }
                return $obj;
            }
            return json_decode(self::base64UrlDecode($payload));
        }

        private static function base64UrlEncode($data)
        {
            return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
        }

        private static function base64UrlDecode($data)
        {
            return base64_decode(strtr($data, '-_', '+/'));
        }
    }
}
?>
