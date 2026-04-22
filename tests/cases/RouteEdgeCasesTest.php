<?php

declare(strict_types=1);

require_once __DIR__ . '/../TestHelpers.php';

use NanoCore\NanoCore;

$tests = [];

// Test 1: Backslashes in registered path are normalized to forward slashes
$tests[] = function () {
    [$app, $tmpFile] = createNanoCoreApp();

    $app->addRoute('GET', '\\api\\health', fn () => ['status' => 'healthy']);
    $response = runRequest($app, 'GET', '/api/health');

    assertEquals(['status' => 'healthy'], $response, 'Backslashes in route path should match forward-slash URI');
    unlink($tmpFile);
};

// Test 2: Duplicate slashes in request URI are collapsed
$tests[] = function () {
    [$app, $tmpFile] = createNanoCoreApp();

    $app->addRoute('GET', '/api/health', fn () => ['ok' => true]);
    $response = runRequest($app, 'GET', '/api//health');

    assertEquals(['ok' => true], $response, 'Duplicate slashes in URI should be collapsed and match');
    unlink($tmpFile);
};

// Test 3: Route without leading slash still matches
$tests[] = function () {
    [$app, $tmpFile] = createNanoCoreApp();

    $app->addRoute('GET', 'ping', fn () => ['status' => 'ok']);
    $response = runRequest($app, 'GET', '/ping');

    assertEquals(['status' => 'ok'], $response, 'Route without leading slash should match normalized URI');
    unlink($tmpFile);
};

// Test 4: Multiple path parameters captured correctly
$tests[] = function () {
    [$app, $tmpFile] = createNanoCoreApp();

    $app->addRoute('GET', '/path/@a/@b', fn ($core, array $params) => $params);
    $response = runRequest($app, 'GET', '/path/x/y');

    assertEquals('x', $response['a'] ?? null, 'First path param should be x');
    assertEquals('y', $response['b'] ?? null, 'Second path param should be y');
    unlink($tmpFile);
};

// Test 5: Special chars in param name are stripped (hyphen removed)
$tests[] = function () {
    [$app, $tmpFile] = createNanoCoreApp();

    $app->addRoute('GET', '/test/@name-here', fn ($core, array $params) => $params);
    $response = runRequest($app, 'GET', '/test/value');

    assertEquals('value', $response['namehere'] ?? null, 'Hyphen in param name should be stripped');
    unlink($tmpFile);
};

// Test 6: Empty param name after sanitization gets auto-generated name
$tests[] = function () {
    [$app, $tmpFile] = createNanoCoreApp();

    $app->addRoute('GET', '/test/@', fn ($core, array $params) => $params);
    $response = runRequest($app, 'GET', '/test/value');

    assertEquals('value', $response['param0'] ?? null, 'Bare @ should auto-generate param0');
    unlink($tmpFile);
};

// Test 7: Path params override query params on collision
$tests[] = function () {
    [$app, $tmpFile] = createNanoCoreApp();

    $app->addRoute('GET', '/users/@id', fn ($core, array $params) => $params);
    $response = runRequest($app, 'GET', '/users/42', ['id' => '99']);

    assertEquals('42', $response['id'] ?? null, 'Path param should override query param on key collision');
    unlink($tmpFile);
};

// Test 8: Wildcard @* captures entire rest of path
$tests[] = function () {
    [$app, $tmpFile] = createNanoCoreApp();

    $app->addRoute('GET', '/files/@*', fn ($core, array $params) => $params);
    $response = runRequest($app, 'GET', '/files/a/b/c/d.txt');

    assertEquals('a/b/c/d.txt', $response['wildcard'] ?? null, 'Wildcard should capture full remaining path');
    unlink($tmpFile);
};

// Test 9: Wildcard @* must be last segment — extra segments ignored
$tests[] = function () {
    [$app, $tmpFile] = createNanoCoreApp();

    $app->addRoute('GET', '/files/@*/extra', fn ($core, array $params) => $params);
    $response = runRequest($app, 'GET', '/files/a/b/extra');

    assertEquals('a/b/extra', $response['wildcard'] ?? null, 'Wildcard captures everything after /files/ ignoring later pattern segments');
    unlink($tmpFile);
};

// Test 10: Case-insensitive method matching (lowercase registration)
$tests[] = function () {
    [$app, $tmpFile] = createNanoCoreApp();

    $app->addRoute('get', '/ping', fn () => ['status' => 'ok']);
    $response = runRequest($app, 'GET', '/ping');

    assertEquals(['status' => 'ok'], $response, 'Lowercase method registration should match uppercase request');
    unlink($tmpFile);
};

// Test 11: Root path / route
$tests[] = function () {
    [$app, $tmpFile] = createNanoCoreApp();

    $app->addRoute('GET', '/', fn () => ['root' => true]);
    $response = runRequest($app, 'GET', '/');

    assertEquals(['root' => true], $response, 'Root path / should match');
    unlink($tmpFile);
};

// Test 12: Non-matching method returns 404 error
$tests[] = function () {
    [$app, $tmpFile] = createNanoCoreApp();

    $app->addRoute('POST', '/submit', fn () => ['submitted' => true]);

    ob_start();
    $result = runRequest($app, 'GET', '/submit');
    $output = ob_get_clean();

    assertEquals(null, $result, 'GET to POST-only route should return null');
    $decoded = json_decode($output, true);
    assertEquals('Route not found', $decoded['error'] ?? null, 'Should return Route not found error');
    unlink($tmpFile);
};

runTests($tests);
