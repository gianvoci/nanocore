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

// Test 4: POST method route
$tests[] = function () {
    $app = new NanoCore();
    $app->addRoute('POST', '/submit', fn ($core, array $params) => ['received' => $params]);

    $response = runRequest($app, 'POST', '/submit', ['data' => 'hello']);
    assertEquals('hello', $response['received']['data'] ?? null, 'POST route should receive query params');

    // GET to the same path should not match
    ob_start();
    $noMatch = runRequest($app, 'GET', '/submit');
    $output = ob_get_clean();
    assertEquals(null, $noMatch, 'GET to a POST-only route should return null');
};

// Test 5: Query parameters merged with path params
$tests[] = function () {
    $app = new NanoCore();
    $app->addRoute('GET', '/users/@id', fn ($core, array $params) => $params);

    $response = runRequest($app, 'GET', '/users/42', ['page' => '2']);
    assertEquals('42', $response['id'] ?? null, 'Path param id should be 42');
    assertEquals('2', $response['page'] ?? null, 'Query param page should be 2');
};

// Test 6: 404 route not found
$tests[] = function () {
    $app = new NanoCore();

    ob_start();
    $result = runRequest($app, 'GET', '/anything');
    $output = ob_get_clean();

    assertEquals(null, $result, 'No matching route should return null');
    $decoded = json_decode($output, true);
    assertEquals('Route not found', $decoded['error'] ?? null, 'Error message should say Route not found');
};

// Test 7: configGet and configSet
$tests[] = function () {
    $tmpFile = tempnam(sys_get_temp_dir(), 'nc_test_');
    $app = new NanoCore($tmpFile);

    $app->configSet('TEST.KEY', 'value');
    assertEquals('value', $app->configGet('TEST.KEY'), 'configGet should return the value just set');
    assertEquals(null, $app->configGet('TEST.NONEXISTENT'), 'configGet for missing key should return null');

    // Clean up
    $app->configSet('TEST', []);
    unlink($tmpFile);
};

// Test 8: Route with trailing slash normalization
$tests[] = function () {
    $app = new NanoCore();
    $app->addRoute('GET', '/ping', fn () => ['status' => 'ok']);

    $response = runRequest($app, 'GET', '/ping/');
    assertEquals(['status' => 'ok'], $response, '/ping/ should match route registered as /ping');
};

// Test 9: Magic property storage
$tests[] = function () {
    $tmpFile = tempnam(sys_get_temp_dir(), 'nc_test_');
    $app = new NanoCore($tmpFile);

    $app->customProp = 'hello';
    assertEquals('hello', $app->customProp, 'Magic __get should return value set via __set');
    assertEquals(null, $app->undefinedProp, 'Magic __get for undefined property should return null');

    unlink($tmpFile);
};

$failed = 0;
$messages = [];
foreach ($tests as $index => $test) {
    try {
        $test();
        $messages[] = "Test " . ($index + 1) . " passed.\n";
    } catch (Throwable $exception) {
        $failed++;
        $messages[] = "Test " . ($index + 1) . " failed: " . $exception->getMessage() . "\n";
    }
}

echo implode('', $messages);
exit($failed > 0 ? 1 : 0);
