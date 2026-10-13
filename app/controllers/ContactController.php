<?php
/**
 * Public contact form endpoint.
 */

require_once __DIR__ . '/../helpers/MailHelper.php';

class ContactController extends Controller
{
    public function store()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            ResponseHelper::error('Method Not Allowed', 405);
        }

        $data = $this->getData(true);
        if (!empty($data['__invalid_json'])) {
            ResponseHelper::error('Malformed JSON request body', 400);
        }

        $errors = self::validatePayload($data);
        if (!empty($errors)) {
            ResponseHelper::error('Validation failed', 400, $errors);
        }

        $contactId = $this->model('Contact')->create($data);
        $supportEmail = defined('CONTACT_SUPPORT_EMAIL')
            ? CONTACT_SUPPORT_EMAIL
            : 'info@staffroomng.com';
        $safeName = htmlspecialchars($data['name'], ENT_QUOTES, 'UTF-8');
        $safeEmail = htmlspecialchars($data['email'], ENT_QUOTES, 'UTF-8');
        $safeSubject = htmlspecialchars($data['subject'], ENT_QUOTES, 'UTF-8');
        $safeMessage = nl2br(htmlspecialchars($data['message'], ENT_QUOTES, 'UTF-8'));
        $body = '<p><strong>Name:</strong> ' . $safeName . '</p>'
            . '<p><strong>Email:</strong> ' . $safeEmail . '</p>'
            . '<p><strong>Subject:</strong> ' . $safeSubject . '</p>'
            . '<p><strong>Message:</strong><br>' . $safeMessage . '</p>';

        if (!MailHelper::send($supportEmail, 'New Staffroom contact message: ' . $data['subject'], $body)) {
            error_log('Contact notification email failed for ' . $contactId);
        }

        ResponseHelper::success(
            'Your message has been sent successfully.',
            ['contact_id' => $contactId],
            201
        );
    }

    public static function validatePayload($data)
    {
        $errors = [];
        $fields = ['name', 'email', 'subject', 'message'];

        if (!is_array($data)) {
            return ['body' => 'Request body must be a JSON object'];
        }

        foreach (array_diff(array_keys($data), $fields) as $field) {
            $errors[$field] = 'Unknown field';
        }

        foreach ($fields as $field) {
            if (!array_key_exists($field, $data) || !is_string($data[$field]) || trim($data[$field]) === '') {
                $errors[$field] = 'This field is required and must be a string';
            }
        }

        $limits = ['name' => 255, 'email' => 320, 'subject' => 255, 'message' => 5000];
        foreach ($limits as $field => $limit) {
            if (isset($data[$field]) && is_string($data[$field]) && mb_strlen($data[$field]) > $limit) {
                $errors[$field] = "Must not exceed {$limit} characters";
            }
        }

        if (isset($data['email']) && is_string($data['email']) && trim($data['email']) !== ''
            && !filter_var(trim($data['email']), FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Invalid email format';
        }

        return $errors;
    }
}
?>
