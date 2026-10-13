<?php
/**
 * Focused tests for the public contact endpoint contract.
 * Run with: php tests/contact_endpoint_test.php
 */

require_once __DIR__ . '/../app/bootstrap.php';

function assertContact($condition, $message)
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$valid = [
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'subject' => 'general',
    'message' => 'Tell us how we can help.'
];

assertContact(ContactController::validatePayload($valid) === [], 'Valid submission should pass');
assertContact(isset(ContactController::validatePayload(['name' => 'John'])['email']), 'Missing fields should fail');
assertContact(
    ContactController::validatePayload(array_merge($valid, ['email' => 'invalid']))['email'] === 'Invalid email format',
    'Invalid email should fail'
);
assertContact(
    isset(ContactController::validatePayload(array_merge($valid, ['message' => str_repeat('x', 5001)]))['message']),
    'Oversized message should fail'
);
assertContact(
    isset(ContactController::validatePayload(array_merge($valid, ['unexpected' => true]))['unexpected']),
    'Unknown fields should fail'
);

$routes = require __DIR__ . '/../routes/api.php';
assertContact(isset($routes['POST /api/contact']), 'Contact route should be registered');
assertContact($routes['POST /api/contact']['middleware'] === [], 'Contact route should be public');

echo "Contact endpoint tests passed\n";