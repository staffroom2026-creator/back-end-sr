<?php
/**
 * Regression tests for the legacy PIN handlers. The active router currently
 * blocks Users, so these tests prove handler behavior, not route availability.
 */
require_once __DIR__ . '/../app/bootstrap.php';

function assertUserSecurity($condition, $message)
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

class UserPinModelHarness
{
    public function setLockPin($pinHash, $userId)
    {
        $GLOBALS['pin_mutation'] = [
            'method' => 'setLockPin',
            'user_id' => $userId,
            'hash_valid' => password_verify('new-lock-pin', $pinHash),
        ];
        return true;
    }

    public function setTransferPin($pinHash, $userId)
    {
        $GLOBALS['pin_mutation'] = [
            'method' => 'setTransferPin',
            'user_id' => $userId,
            'hash_valid' => password_verify('new-transfer-pin', $pinHash),
        ];
        return true;
    }

    public function storeTransactionKey($otpHash, $userId, $email)
    {
        $GLOBALS['pin_mutation'] = [
            'method' => 'storeTransactionKey',
            'user_id' => $userId,
        ];
        return true;
    }
}

class UsersPinSecurityHarness extends Users
{
    public function __construct()
    {
        $this->userModel = new UserPinModelHarness();
    }

    protected function requirePinUser()
    {
        if (in_array($GLOBALS['pin_scenario'], ['unauthenticated', 'invalid-token'], true) || strpos($GLOBALS['pin_scenario'], 'unauthenticated-') === 0) {
            return AuthMiddleware::authenticate();
        }

        return $GLOBALS['pin_user'];
    }

    public function getData($strict = false)
    {
        return $GLOBALS['pin_payload'];
    }
}

function pinUserForScenario($scenario)
{
    $user = (object)[
        'user_id' => 'authenticated-user-42',
        'email' => 'teacher@example.test',
        'transfer_pin' => password_hash('old-transfer-pin', PASSWORD_DEFAULT),
    ];

    if (in_array($scenario, ['change-lock-success', 'wrong-lock-pin'], true)) {
        $user->lock_pin = password_hash('old-lock-pin', PASSWORD_DEFAULT);
    }
    if ($scenario === 'lock-pin-no-transfer-fallback') {
        $user->transfer_pin = password_hash('old-lock-pin', PASSWORD_DEFAULT);
    }

    return $user;
}

function payloadForScenario($scenario)
{
    switch ($scenario) {
        case 'unauthenticated':
            return ['pin' => 'new-lock-pin'];
        case 'missing-pin':
            return [];
        case 'malformed-pin':
            return ['pin' => ['new-lock-pin']];
        case 'boolean-pin':
            return ['pin' => true];
        case 'missing-transfer-pin':
            return [];
        case 'malformed-transfer-pin':
            return ['transfer_pin' => ['new-transfer-pin']];
        case 'missing-lock-change':
            return ['old_pin' => 'old-lock-pin', 'new_pin' => 'new-lock-pin'];
        case 'malformed-lock-change':
            return ['old_pin' => ['old-lock-pin'], 'new_pin' => 'new-lock-pin', 'confirm_new_pin' => 'new-lock-pin'];
        case 'wrong-lock-pin':
            return ['old_pin' => 'incorrect-lock-pin', 'new_pin' => 'new-lock-pin', 'confirm_new_pin' => 'new-lock-pin'];
        case 'set-lock-success':
            return ['pin' => 'new-lock-pin'];
        case 'set-transfer-success':
            return ['transfer_pin' => 'new-transfer-pin'];
        case 'change-lock-success':
            return ['old_pin' => 'old-lock-pin', 'new_pin' => 'new-lock-pin', 'confirm_new_pin' => 'new-lock-pin'];
        case 'change-transfer-success':
            return ['old_pin' => 'old-transfer-pin', 'new_pin' => 'new-transfer-pin', 'confirm_new_pin' => 'new-transfer-pin'];
        case 'change-transfer-malformed':
            return ['old_pin' => 'old-transfer-pin', 'new_pin' => ['new-transfer-pin'], 'confirm_new_pin' => 'new-transfer-pin'];
        case 'lock-pin-no-transfer-fallback':
            return ['old_pin' => 'old-lock-pin', 'new_pin' => 'new-lock-pin', 'confirm_new_pin' => 'new-lock-pin'];
        case 'wrong-transfer-pin':
            return ['transfer_pin' => 'incorrect-transfer-pin'];
        case 'unauthenticated-transfer-change':
            return ['old_pin' => 'old-transfer-pin', 'new_pin' => 'new-transfer-pin', 'confirm_new_pin' => 'new-transfer-pin'];
        case 'unauthenticated-transfer-verify':
            return ['transfer_pin' => 'old-transfer-pin'];
        default:
            return [];
    }
}

if (($argv[1] ?? null) === '--child') {
    $GLOBALS['pin_scenario'] = $argv[2] ?? '';
    $GLOBALS['pin_payload'] = payloadForScenario($GLOBALS['pin_scenario']);
    $GLOBALS['pin_user'] = pinUserForScenario($GLOBALS['pin_scenario']);
    $GLOBALS['pin_mutation'] = null;

    unset($_SERVER['HTTP_AUTHORIZATION'], $_SERVER['REDIRECT_HTTP_AUTHORIZATION'], $_SERVER['Authorization']);
    $_SERVER['REQUEST_METHOD'] = 'POST';
    if ($GLOBALS['pin_scenario'] === 'invalid-token') {
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer not-a-valid-jwt';
    }

    register_shutdown_function(function () {
        fwrite(STDERR, '__HTTP_STATUS__=' . http_response_code() . "\n");
        if ($GLOBALS['pin_mutation'] !== null) {
            $mutation = $GLOBALS['pin_mutation'];
            fwrite(STDERR, '__MUTATION__=' . $mutation['method'] . ':' . $mutation['user_id'] . ':' . (int)($mutation['hash_valid'] ?? true) . "\n");
        }
    });

    $controller = new UsersPinSecurityHarness();
    switch ($GLOBALS['pin_scenario']) {
        case 'unauthenticated':
        case 'invalid-token':
        case 'missing-pin':
        case 'malformed-pin':
        case 'boolean-pin':
        case 'set-lock-success':
            $controller->setLockPin();
            break;
        case 'missing-transfer-pin':
        case 'malformed-transfer-pin':
        case 'set-transfer-success':
            $controller->setTransferPin();
            break;
        case 'change-lock-success':
        case 'wrong-lock-pin':
        case 'lock-pin-no-transfer-fallback':
            $controller->changeLockPin();
            break;
        case 'change-transfer-success':
        case 'change-transfer-malformed':
            $controller->changeTransferPin();
            break;
        case 'wrong-transfer-pin':
            $controller->verifyTransferPin();
            break;
        case 'unauthenticated-transfer-change':
            $controller->changeTransferPin();
            break;
        case 'unauthenticated-transfer-verify':
            $controller->verifyTransferPin();
            break;
        case 'missing-lock-change':
        case 'malformed-lock-change':
            $controller->changeLockPin();
            break;
        default:
            throw new RuntimeException('Unknown PIN test scenario');
    }

    echo '__REQUEST_CONTINUED__';
    exit(99);
}

function runPinScenario($scenario)
{
    $process = proc_open([PHP_BINARY, __FILE__, '--child', $scenario], [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ], $pipes);

    if (!is_resource($process)) {
        throw new RuntimeException('Unable to start PIN regression subprocess');
    }

    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);
    preg_match('/__HTTP_STATUS__=(\d+)/', $stderr, $statusMatch);
    preg_match('/__MUTATION__=([^\r\n]+)/', $stderr, $mutationMatch);

    return [
        'exit' => $exitCode,
        'stdout' => $stdout,
        'response' => json_decode($stdout, true),
        'status' => isset($statusMatch[1]) ? (int)$statusMatch[1] : null,
        'mutation' => $mutationMatch[1] ?? null,
        'stderr' => $stderr,
    ];
}

function assertPinError($scenario, $status, $message)
{
    $result = runPinScenario($scenario);
    assertUserSecurity($result['exit'] === 0, "{$scenario} did not terminate through the response helper: {$result['stderr']}");
    assertUserSecurity($result['status'] === $status, "{$scenario} returned HTTP {$result['status']}, expected {$status}");
    assertUserSecurity(($result['response']['success'] ?? null) === false, "{$scenario} should return a JSON error response");
    assertUserSecurity(($result['response']['message'] ?? null) === $message, "{$scenario} returned an unexpected error message");
    assertUserSecurity($result['mutation'] === null, "{$scenario} reached a model mutation");
    assertUserSecurity(strpos($result['stdout'], '__REQUEST_CONTINUED__') === false, "{$scenario} continued after the error response");
}

function assertPinMutation($scenario, $method)
{
    $result = runPinScenario($scenario);
    assertUserSecurity($result['exit'] === 0, "{$scenario} did not terminate successfully: {$result['stderr']}");
    assertUserSecurity($result['status'] === 200, "{$scenario} returned HTTP {$result['status']}, expected 200");
    assertUserSecurity(($result['response']['success'] ?? null) === true, "{$scenario} should return a JSON success response");
    assertUserSecurity($result['mutation'] === $method . ':authenticated-user-42:1', "{$scenario} did not mutate the authenticated user's hashed PIN");
    assertUserSecurity(strpos($result['stdout'], 'new-lock-pin') === false && strpos($result['stdout'], 'new-transfer-pin') === false, "{$scenario} exposed a plaintext PIN in its response");
    assertUserSecurity(strpos($result['stdout'], '__REQUEST_CONTINUED__') === false, "{$scenario} continued after success response");
}

assertPinError('unauthenticated', 401, 'Unauthorized: Missing token');
assertPinError('invalid-token', 401, 'Unauthorized: Invalid token');
assertPinError('unauthenticated-transfer-change', 401, 'Unauthorized: Missing token');
assertPinError('unauthenticated-transfer-verify', 401, 'Unauthorized: Missing token');
assertPinError('missing-pin', 404, 'invalid pin');
assertPinError('malformed-pin', 404, 'invalid pin');
assertPinError('boolean-pin', 404, 'invalid pin');
assertPinError('missing-transfer-pin', 404, 'invalid pin');
assertPinError('malformed-transfer-pin', 404, 'invalid pin');
assertPinError('missing-lock-change', 404, 'incomplete pin data');
assertPinError('malformed-lock-change', 404, 'incomplete pin data');
assertPinMutation('set-lock-success', 'setLockPin');
assertPinMutation('set-transfer-success', 'setTransferPin');
assertPinMutation('change-lock-success', 'setLockPin');
assertPinMutation('change-transfer-success', 'setTransferPin');
assertPinError('wrong-lock-pin', 404, 'wrong pin');
assertPinError('change-transfer-malformed', 400, 'incomplete pin data');
assertPinError('lock-pin-no-transfer-fallback', 404, 'Lock pin not set');
assertPinError('wrong-transfer-pin', 404, 'wrong pin');

echo "User PIN security regression tests passed\n";
