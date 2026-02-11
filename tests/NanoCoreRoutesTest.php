<?php

declare(strict_types=1);

require __DIR__ . '/../NanoCore.php';

use NanoCore\NanoCore;

function runRequest(NanoCore $app, string $method, string $path, array $query = []): mixed
{
    $_SERVER['REQUEST_METHOD'] = $method;
    $queryString = http_build_query($query);
    $_SERVER['REQUEST_URI'] = $path . ($queryString !== '' ? '?' . $queryString : '');
    $_SERVER['QUERY_STRING'] = $queryString;
    $_SERVER['SCRIPT_NAME'] = '/index.php';

    return $app->run();
}

function assertEquals(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(sprintf("%s (expected %s, got %s)", $message, var_export($expected, true), var_export($actual, true)));
    }
}

$tests = [];

$tests[] = function () {
    $app = new NanoCore();
    $app->addRoute('GET', '/ping', fn () => ['status' => 'ok']);

    $response = runRequest($app, 'GET', '/ping');
    assertEquals(['status' => 'ok'], $response, 'Simple GET /ping should return status ok');
};

$tests[] = function () {
    $app = new NanoCore();
    $app->addRoute('GET', '/users/@id', fn ($core, array $params) => ['id' => $params['id'] ?? null]);

    $response = runRequest($app, 'GET', '/users/42');
    assertEquals(['id' => '42'], $response, 'Path parameter should be extracted as id');
};

$tests[] = function () {
    $app = new NanoCore();
    $app->addRoute('GET', '/files/@*', fn ($core, array $params) => ['wildcard' => $params['wildcard'] ?? null]);

    $response = runRequest($app, 'GET', '/files/foo/bar.txt');
    assertEquals(['wildcard' => 'foo/bar.txt'], $response, 'Wildcard should capture the remaining path');
};

$failed = 0;
foreach ($tests as $index => $test) {
    try {
        $test();
        echo "Test " . ($index + 1) . " passed.\n";
    } catch (Throwable $exception) {
        $failed++;
        echo "Test " . ($index + 1) . " failed: " . $exception->getMessage() . "\n";
    }
}

$exitCode = $failed > 0 ? 1 : 0;
exit($exitCode);
