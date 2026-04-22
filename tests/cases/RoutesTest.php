<?php

declare(strict_types=1);

require_once __DIR__ . '/../TestHelpers.php';

use NanoCore\NanoCore;

$tests = [];

$tests[] = function () {
    [$app, $tmpFile] = createNanoCoreApp();
    $app->addRoute('GET', '/ping', fn () => ['status' => 'ok']);

    $response = runRequest($app, 'GET', '/ping');
    assertEquals(['status' => 'ok'], $response, 'Simple GET /ping should return status ok');
    
    unlink($tmpFile);
};

$tests[] = function () {
    [$app, $tmpFile] = createNanoCoreApp();
    $app->addRoute('GET', '/users/@id', fn ($core, array $params) => ['id' => $params['id'] ?? null]);

    $response = runRequest($app, 'GET', '/users/42');
    assertEquals(['id' => '42'], $response, 'Path parameter should be extracted as id');
    
    unlink($tmpFile);
};

$tests[] = function () {
    [$app, $tmpFile] = createNanoCoreApp();
    $app->addRoute('GET', '/files/@*', fn ($core, array $params) => ['wildcard' => $params['wildcard'] ?? null]);

    $response = runRequest($app, 'GET', '/files/foo/bar.txt');
    assertEquals(['wildcard' => 'foo/bar.txt'], $response, 'Wildcard should capture the remaining path');
    
    unlink($tmpFile);
};

// Test 4: POST method route
$tests[] = function () {
    [$app, $tmpFile] = createNanoCoreApp();
    $app->addRoute('POST', '/submit', fn ($core, array $params) => ['received' => $params]);

    $response = runRequest($app, 'POST', '/submit', ['data' => 'hello']);
    assertEquals('hello', $response['received']['data'] ?? null, 'POST route should receive query params');

    // GET to the same path should not match
    ob_start();
    $noMatch = runRequest($app, 'GET', '/submit');
    $output = ob_get_clean();
    assertEquals(null, $noMatch, 'GET to a POST-only route should return null');
    
    unlink($tmpFile);
};

// Test 5: Query parameters merged with path params
$tests[] = function () {
    [$app, $tmpFile] = createNanoCoreApp();
    $app->addRoute('GET', '/users/@id', fn ($core, array $params) => $params);

    $response = runRequest($app, 'GET', '/users/42', ['page' => '2']);
    assertEquals('42', $response['id'] ?? null, 'Path param id should be 42');
    assertEquals('2', $response['page'] ?? null, 'Query param page should be 2');
    
    unlink($tmpFile);
};

// Test 6: 404 route not found
$tests[] = function () {
    [$app, $tmpFile] = createNanoCoreApp();

    ob_start();
    $result = runRequest($app, 'GET', '/anything');
    $output = ob_get_clean();

    assertEquals(null, $result, 'No matching route should return null');
    $decoded = json_decode($output, true);
    assertEquals('Route not found', $decoded['error'] ?? null, 'Error message should say Route not found');
    
    unlink($tmpFile);
};

// Test 7: Route with trailing slash normalization
$tests[] = function () {
    [$app, $tmpFile] = createNanoCoreApp();
    $app->addRoute('GET', '/ping', fn () => ['status' => 'ok']);

    $response = runRequest($app, 'GET', '/ping/');
    assertEquals(['status' => 'ok'], $response, '/ping/ should match route registered as /ping');
    
    unlink($tmpFile);
};

// Test 8: Magic property storage
$tests[] = function () {
    [$app, $tmpFile] = createNanoCoreApp();

    $app->customProp = 'hello';
    assertEquals('hello', $app->customProp, 'Magic __get should return value set via __set');
    assertEquals(null, $app->undefinedProp, 'Magic __get for undefined property should return null');

    unlink($tmpFile);
};

runTests($tests);
