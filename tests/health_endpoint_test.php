<?php
/** Contract test for the route-declared public health endpoint. */
require_once __DIR__ . '/../app/bootstrap.php';

function assertHealthEndpoint($condition, $message)
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

if (($argv[1] ?? null) === '--child') {
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = '/health';
    $_SERVER['SCRIPT_NAME'] = '/index.php';
    unset($_SERVER['HTTP_AUTHORIZATION'], $_SERVER['REDIRECT_HTTP_AUTHORIZATION'], $_SERVER['Authorization']);
    register_shutdown_function(function () {
        fwrite(STDERR, '__HTTP_STATUS__=' . http_response_code() . "\n");
    });
    new Core();
    exit(99);
}

$process = proc_open([PHP_BINARY, __FILE__, '--child'], [
    0 => ['pipe', 'r'],
    1 => ['pipe', 'w'],
    2 => ['pipe', 'w'],
], $pipes);

if (!is_resource($process)) {
    throw new RuntimeException('Unable to start health endpoint subprocess');
}

fclose($pipes[0]);
$stdout = stream_get_contents($pipes[1]);
$stderr = stream_get_contents($pipes[2]);
fclose($pipes[1]);
fclose($pipes[2]);
$exitCode = proc_close($process);
$response = json_decode($stdout, true);
preg_match('/__HTTP_STATUS__=(\d+)/', $stderr, $statusMatch);

assertHealthEndpoint($exitCode === 0, 'Health endpoint did not terminate through its response helper: ' . $stderr);
assertHealthEndpoint(isset($statusMatch[1]) && (int)$statusMatch[1] === 200, 'Public health endpoint must return HTTP 200 without credentials');
assertHealthEndpoint(($response['success'] ?? null) === true, 'Health endpoint must return a successful JSON response');
assertHealthEndpoint(($response['data']['status'] ?? null) === 'ok', 'Health response status must be ok');
assertHealthEndpoint(($response['data']['service'] ?? null) === 'StaffRoom API', 'Health response service name changed');

echo "Health endpoint contract passed\n";