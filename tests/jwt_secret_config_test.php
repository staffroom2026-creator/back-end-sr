<?php
/** Production JWT configuration must not fall back to a committed secret. */

function assertJwtSecretConfig($condition, $message)
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

foreach (['', 'your_jwt_secret_key_here', 'staffroom_secret_key_2026_secure_mvc'] as $configuredSecret) {
    $secretAssignment = var_export($configuredSecret, true);
    $configPath = var_export(__DIR__ . '/../app/config/config.php', true);
    $code = 'putenv("APP_ENV=production"); putenv("JWT_SECRET=" . ' . $secretAssignment . '); $_ENV["APP_ENV"] = "production"; $_ENV["JWT_SECRET"] = ' . $secretAssignment . '; $_SERVER["APP_ENV"] = "production"; $_SERVER["JWT_SECRET"] = ' . $secretAssignment . '; require ' . $configPath . '; echo "__CONFIG_LOADED__";';
    $process = proc_open([PHP_BINARY, '-r', $code], [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ], $pipes);

    if (!is_resource($process)) {
        throw new RuntimeException('Unable to start JWT configuration subprocess');
    }

    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);

    assertJwtSecretConfig($exitCode !== 0, 'Production configuration accepted an empty or example JWT secret');
    assertJwtSecretConfig(strpos($stdout, '__CONFIG_LOADED__') === false, 'Production configuration continued with an insecure JWT secret');
    assertJwtSecretConfig(strpos($stderr, 'A production JWT secret must be configured.') !== false, 'Production configuration failed for an unexpected reason');
}

echo "Production JWT secret configuration tests passed\n";