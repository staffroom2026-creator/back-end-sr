<?php
/**
 * MailHelper / EmailService
 * Handles sending emails by delegating to the unified Controller email methods and PHPMailer.
 */

if (file_exists(__DIR__ . '/phpMailer/PHPMailerAutoload.php')) {
    require_once __DIR__ . '/phpMailer/PHPMailerAutoload.php';
} elseif (file_exists(__DIR__ . '/phpMailer/class.phpmailer.php')) {
    require_once __DIR__ . '/phpMailer/class.phpmailer.php';
    require_once __DIR__ . '/phpMailer/class.smtp.php';
}

require_once __DIR__ . '/../libraries/Controller.php';

class MailHelper
{
    /**
     * Send email using main Controller PHPMailer SMTP function
     *
     * @param string $to
     * @param string $subject
     * @param string $htmlBody
     * @return bool
     */
    public static function send($to, $subject, $htmlBody)
    {
        if (class_exists('Controller')) {
            $controller = new Controller();
            return $controller->sendHtmlEmailWithAttachment($to, $subject, $htmlBody);
        }
        return false;
    }

    public static function sendTemplate($to, $subject, $templateName, $values)
    {
        $templatePath = __DIR__ . '/../controllers/emails/' . basename($templateName) . '.php';
        if (!is_file($templatePath)) {
            error_log('Email template not found: ' . $templatePath);
            return false;
        }

        $html = file_get_contents($templatePath);
        foreach ($values as $key => $value) {
            $html = str_replace('{' . $key . '}', htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'), $html);
        }

        return self::send($to, $subject, $html);
    }

    /**
     * Send 6-digit verification code email using main Controller sendOTPEmail function
     *
     * @param string $to
     * @param string $fullName
     * @param string $code
     * @return bool
     */
    public static function sendVerificationCode($to, $fullName, $code)
    {
        if (class_exists('Controller')) {
            $controller = new Controller();
            return $controller->sendOTPEmail(['email' => $to, 'otp' => $code]);
        }
        return false;
    }
}

// Alias class for flexibility
if (!class_exists('EmailService')) {
    class EmailService extends MailHelper {}
}
?>
