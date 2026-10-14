<?php
/** Contract test for the active Auth::login implementation and response envelope. */
require_once __DIR__ . '/../app/bootstrap.php';

function assertLoginResponse($condition, $message)
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

class AuthLoginUserModelHarness
{
    public function findByEmail($email)
    {
        $GLOBALS['login_model_called'] = true;
        return (object)[
            'user_id' => 'USR-TEST-42',
            'full_name' => 'Test User',
            'email' => $email,
            'email_verified_at' => '2026-01-01 00:00:00',
            'phone' => '0000000000',
            'profile_photo' => null,
            'role' => 'teacher',
            'status' => 'active',
            'active' => 1,
            'created_at' => '2026-01-01 00:00:00',
            'updated_at' => '2026-01-01 00:00:00',
            'password' => password_hash('test-password', PASSWORD_DEFAULT),
        ];
    }

    public function logSuccessfulLogin($userId, $metadata)
    {
        $GLOBALS['login_logged_user_id'] = $userId;
        return true;
    }
}

class AuthLoginProfileModelHarness
{
}

class AuthLoginHarness extends Auth
{
    public function __construct()
    {
        $userProperty = new ReflectionProperty(Auth::class, 'userModel');
        $userProperty->setAccessible(true);
        $userProperty->setValue($this, new AuthLoginUserModelHarness());

        $profileProperty = new ReflectionProperty(Auth::class, 'profileModel');
        $profileProperty->setAccessible(true);
        $profileProperty->setValue($this, new AuthLoginProfileModelHarness());
    }

    public function getData($strict = false)
    {
        if (($GLOBALS['argv'][2] ?? null) === 'missing') {
            return [];
        }
        if (($GLOBALS['argv'][2] ?? null) === 'malformed-email') {
            return ['email' => ['teacher@example.test'], 'password' => 'test-password'];
        }
        if (($GLOBALS['argv'][2] ?? null) === 'malformed-password') {
            return ['email' => 'teacher@example.test', 'password' => ['test-password']];
        }

        return ['email' => 'teacher@example.test', 'password' => 'test-password'];
    }
}

if (($argv[1] ?? null) === '--child') {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
    $_SERVER['HTTP_USER_AGENT'] = 'isolated-test';

    register_shutdown_function(function () {
        fwrite(STDERR, '__HTTP_STATUS__=' . http_response_code() . "\n");
        fwrite(STDERR, '__LOGGED_USER__=' . ($GLOBALS['login_logged_user_id'] ?? '') . "\n");
        fwrite(STDERR, '__MODEL_CALLED__=' . (int)($GLOBALS['login_model_called'] ?? false) . "\n");
    });

    (new AuthLoginHarness())->login();
    echo '__REQUEST_CONTINUED__';
    exit(99);
}

function runLoginScenario($scenario)
{
    $process = proc_open([PHP_BINARY, __FILE__, '--child', $scenario], [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ], $pipes);

    if (!is_resource($process)) {
        throw new RuntimeException('Unable to start login response subprocess');
    }

    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);
    preg_match('/__HTTP_STATUS__=(\d+)/', $stderr, $statusMatch);
    preg_match('/__LOGGED_USER__=([^\r\n]*)/', $stderr, $userMatch);
    preg_match('/__MODEL_CALLED__=(\d+)/', $stderr, $modelMatch);

    return [
        'exit' => $exitCode,
        'stdout' => $stdout,
        'stderr' => $stderr,
        'response' => json_decode($stdout, true),
        'status' => isset($statusMatch[1]) ? (int)$statusMatch[1] : null,
        'logged_user' => $userMatch[1] ?? null,
        'model_called' => isset($modelMatch[1]) && $modelMatch[1] === '1',
    ];
}

$result = runLoginScenario('success');
$stdout = $result['stdout'];
$response = $result['response'];

assertLoginResponse($result['exit'] === 0, 'Login did not terminate through the response helper: ' . $result['stderr']);
assertLoginResponse($result['status'] === 200, 'Successful login must return HTTP 200');
assertLoginResponse(($response['success'] ?? null) === true, 'Successful login response must set success=true');
assertLoginResponse(($response['status'] ?? null) === true, 'Successful login response must set status=true');
assertLoginResponse(($response['message'] ?? null) === 'Login successful', 'Successful login response message changed');
assertLoginResponse(($response['data']['user']['user_id'] ?? null) === 'USR-TEST-42', 'Login data must contain the formatted user');
assertLoginResponse(($response['data']['user']['email'] ?? null) === 'teacher@example.test', 'Login data must contain the user email');
assertLoginResponse(($response['data']['user']['role'] ?? null) === 'teacher', 'Login data must contain the user role');
assertLoginResponse(!array_key_exists('password', $response['data']['user'] ?? []), 'Login response must not include the password');
assertLoginResponse(is_string($response['data']['token'] ?? null) && $response['data']['token'] !== '', 'Login data must contain a token');
$claims = JWT::decode($response['data']['token'], JWT_SECRET_KEY);
assertLoginResponse(($claims->user_id ?? null) === 'USR-TEST-42', 'Login token must identify the authenticated user');
assertLoginResponse(($claims->role ?? null) === 'teacher', "Login token must contain the user's role");
assertLoginResponse($result['logged_user'] === 'USR-TEST-42', "Successful login must record activity for the authenticated user");
assertLoginResponse(strpos($stdout, 'test-password') === false, 'Login response must not contain the plaintext password');
assertLoginResponse(strpos($stdout, '__REQUEST_CONTINUED__') === false, 'Login continued after sending a response');

foreach (['missing', 'malformed-email', 'malformed-password'] as $scenario) {
    $invalidResult = runLoginScenario($scenario);
    assertLoginResponse($invalidResult['exit'] === 0, "{$scenario} login should return through the response helper: {$invalidResult['stderr']}");
    assertLoginResponse($invalidResult['status'] === 400, "{$scenario} login should return HTTP 400");
    assertLoginResponse(($invalidResult['response']['success'] ?? null) === false, "{$scenario} login should return a JSON error");
    assertLoginResponse(($invalidResult['response']['message'] ?? null) === 'Email and password are required', "{$scenario} login returned an unexpected message");
    assertLoginResponse(!$invalidResult['model_called'], "{$scenario} login reached the user model");
    assertLoginResponse(strpos($invalidResult['stdout'], '__REQUEST_CONTINUED__') === false, "{$scenario} login continued after its error response");
}

echo "Auth login response contract passed\n";
