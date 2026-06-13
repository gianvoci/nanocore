<?php

declare(strict_types=1);

require_once __DIR__ . '/../TestHelpers.php';

use NanoCore\NanoCore;
use NanoCore\NanoORM;

$tests = [];

// Test 1: findBy returns associative arrays, not ORM instances
$tests[] = function () {
    $pdo = createMemoryPDO();
    prepareSchema($pdo);

    $user = new NanoORM($pdo, 'users');
    $user->fill(['name' => 'FindByTest', 'email' => 'findby@test.com', 'status' => 'active']);
    $user->save();

    $orm = new NanoORM($pdo, 'users');
    $results = $orm->findBy('email = ?', ['findby@test.com']);

    assertTrue(count($results) === 1, 'findBy should return 1 result');
    assertTrue(is_array($results[0]), 'findBy should return associative arrays');
    assertEquals('FindByTest', $results[0]['name'], 'findBy result should be accessible as array');
};

// Test 2: findAll returns associative arrays, not ORM instances
$tests[] = function () {
    $pdo = createMemoryPDO();
    prepareSchema($pdo);

    $user = new NanoORM($pdo, 'users');
    $user->fill(['name' => 'FindAllTest', 'email' => 'findall@test.com', 'status' => 'active']);
    $user->save();

    $orm = new NanoORM($pdo, 'users');
    $results = $orm->findAll('status = ?', ['active']);

    assertTrue(count($results) >= 1, 'findAll should return results');
    assertTrue(is_array($results[0]), 'findAll should return associative arrays');
    assertEquals('FindAllTest', $results[0]['name'], 'findAll result should be accessible as array');
};

// Test 3: findAll with no arguments returns all rows as arrays
$tests[] = function () {
    $pdo = createMemoryPDO();
    prepareSchema($pdo);

    for ($i = 1; $i <= 3; $i++) {
        $orm = new NanoORM($pdo, 'users');
        $orm->fill(['name' => "User{$i}", 'email' => "user{$i}@test.com", 'status' => 'active'])->save();
    }

    $orm = new NanoORM($pdo, 'users');
    $results = $orm->findAll();

    assertEquals(3, count($results), 'findAll() with no args should return all records');
    assertTrue(is_array($results[0]), 'findAll() should return associative arrays');
};

// Test 4: findAll with empty where and orderBy
$tests[] = function () {
    $pdo = createMemoryPDO();
    prepareSchema($pdo);

    $orm1 = new NanoORM($pdo, 'users');
    $orm1->fill(['name' => 'Beta', 'email' => 'b@test.com', 'status' => 'active'])->save();

    $orm2 = new NanoORM($pdo, 'users');
    $orm2->fill(['name' => 'Alpha', 'email' => 'a@test.com', 'status' => 'active'])->save();

    $orm = new NanoORM($pdo, 'users');
    $results = $orm->findAll('', [], 'name ASC');

    assertEquals('Alpha', $results[0]['name'], 'findAll with empty where and orderBy should work');
};

// Test 5: findBy with LIKE
$tests[] = function () {
    $pdo = createMemoryPDO();
    prepareSchema($pdo);

    $orm = new NanoORM($pdo, 'users');
    $orm->fill(['name' => 'John Doe', 'email' => 'john@test.com', 'status' => 'active'])->save();

    $orm = new NanoORM($pdo, 'users');
    $results = $orm->findBy('name LIKE ?', ['%Doe%']);

    assertEquals(1, count($results), 'findBy with LIKE should find matching records');
    assertEquals('John Doe', $results[0]['name'], 'LIKE result should have correct name');
};

// Test 6: findBy with multiple conditions
$tests[] = function () {
    $pdo = createMemoryPDO();
    prepareSchema($pdo);

    $orm1 = new NanoORM($pdo, 'users');
    $orm1->fill(['name' => 'Alice', 'email' => 'alice@test.com', 'status' => 'active'])->save();

    $orm2 = new NanoORM($pdo, 'users');
    $orm2->fill(['name' => 'Alice', 'email' => 'alice2@test.com', 'status' => 'inactive'])->save();

    $orm = new NanoORM($pdo, 'users');
    $results = $orm->findBy('name = ? AND status = ?', ['Alice', 'active']);

    assertEquals(1, count($results), 'findBy with multiple conditions should return 1');
    assertEquals('alice@test.com', $results[0]['email'], 'Multi-condition findBy should match correctly');
};

// Test 7: findById still returns NanoORM instance
$tests[] = function () {
    $pdo = createMemoryPDO();
    prepareSchema($pdo);

    $user = new NanoORM($pdo, 'users');
    $user->fill(['name' => 'ByIdTest', 'email' => 'byid@test.com', 'status' => 'active']);
    $user->save();
    $id = $user->getId();

    $found = (new NanoORM($pdo, 'users'))->findById($id);
    assertTrue($found instanceof NanoORM, 'findById should return NanoORM instance');
    assertEquals('ByIdTest', $found->name, 'findById should return hydrated instance');
};

// Test 8: fromArray() is public and creates ORM instance without DB
$tests[] = function () {
    $pdo = createMemoryPDO();
    prepareSchema($pdo);

    $orm = new NanoORM($pdo, 'users');
    $orm->fromArray(['id' => 1, 'name' => 'Test', 'email' => 'test@test.com', 'status' => 'active']);

    assertEquals(1, $orm->getId(), 'fromArray should set primary key');
    assertEquals('Test', $orm->name, 'fromArray should set data');
    assertTrue(!$orm->isNew(), 'fromArray should mark record as not new');
};

// Test 9: fromArray() returns self for chaining
$tests[] = function () {
    $pdo = createMemoryPDO();
    prepareSchema($pdo);

    $orm = new NanoORM($pdo, 'users');
    $result = $orm->fromArray(['id' => 1, 'name' => 'Test', 'email' => 'test@test.com', 'status' => 'active']);

    assertTrue($result === $orm, 'fromArray should return self');
};

// Test 10: getBodyRequest cache returns same result on second call
$tests[] = function () {
    [$app, $tmpFile] = createNanoCoreApp();

    $result1 = $app->getBodyRequest();
    $result2 = $app->getBodyRequest();

    assertEquals($result1, $result2, 'getBodyRequest should return cached result on second call');

    unlink($tmpFile);
};

// Test 11: getBodyRequest auto-detects form-urlencoded Content-Type
$tests[] = function () {
    [$app, $tmpFile] = createNanoCoreApp();

    $_SERVER['CONTENT_TYPE'] = 'application/x-www-form-urlencoded';

    $result = $app->getBodyRequest();

    assertTrue(is_array($result), 'form-urlencoded Content-Type should return array from parse_str');

    unset($_SERVER['CONTENT_TYPE']);
    unlink($tmpFile);
};

// Test 12: getBodyRequest auto-detects multipart/form-data
$tests[] = function () {
    [$app, $tmpFile] = createNanoCoreApp();

    $_SERVER['CONTENT_TYPE'] = 'multipart/form-data; boundary=----WebKitFormBoundary';

    $result = $app->getBodyRequest();

    assertTrue(is_array($result), 'multipart/form-data should return $_POST array');

    unset($_SERVER['CONTENT_TYPE']);
    unlink($tmpFile);
};

// Test 13: require() returns stored property
$tests[] = function () {
    [$app, $tmpFile] = createNanoCoreApp();

    $app->pdo = 'fake_pdo';
    assertEquals('fake_pdo', $app->require('pdo'), 'require() should return stored property');

    unlink($tmpFile);
};

// Test 14: require() throws RuntimeException for missing property
$tests[] = function () {
    [$app, $tmpFile] = createNanoCoreApp();

    assertThrows(\RuntimeException::class, "Required property 'nonexistent' not configured", function () use ($app) {
        $app->require('nonexistent');
    });

    unlink($tmpFile);
};

// Test 15: require() supports virtual properties (body, cli)
$tests[] = function () {
    [$app, $tmpFile] = createNanoCoreApp();

    assertTrue($app->require('cli') === true, 'require() should return true for cli virtual property');

    unlink($tmpFile);
};

// Test 16: Middleware receives route and method parameters
$tests[] = function () {
    [$app, $tmpFile] = createNanoCoreApp();

    $captured = [];
    $app->addMiddleware(function ($app, $params, $next, $route, $method) use (&$captured) {
        $captured['route'] = $route;
        $captured['method'] = $method;
        return $next($app, $params);
    });

    $app->addRoute('GET', '/test', fn () => ['ok' => true]);

    runRequest($app, 'GET', '/test');

    assertEquals('/test', $captured['route'], 'Middleware should receive resolved route');
    assertEquals('GET', $captured['method'], 'Middleware should receive resolved method');

    unlink($tmpFile);
};

// Test 17: Middleware without route/method params still works (backward compat)
$tests[] = function () {
    [$app, $tmpFile] = createNanoCoreApp();

    $called = false;
    $app->addMiddleware(function ($app, $params, $next) use (&$called) {
        $called = true;
        return $next($app, $params);
    });

    $app->addRoute('GET', '/compat', fn () => ['ok' => true]);

    $response = runRequest($app, 'GET', '/compat');
    assertTrue($called, 'Old-style middleware should still be called');
    assertEquals(['ok' => true], $response, 'Old-style middleware should not break route handling');

    unlink($tmpFile);
};

// Test 18: Multiple middlewares all receive route and method
$tests[] = function () {
    [$app, $tmpFile] = createNanoCoreApp();

    $captured = [];
    $app->addMiddleware(function ($app, $params, $next, $route, $method) use (&$captured) {
        $captured[] = "mw1:{$method}:{$route}";
        return $next($app, $params);
    });
    $app->addMiddleware(function ($app, $params, $next, $route, $method) use (&$captured) {
        $captured[] = "mw2:{$method}:{$route}";
        return $next($app, $params);
    });

    $app->addRoute('POST', '/api/data', fn () => ['done' => true]);

    runRequest($app, 'POST', '/api/data');

    assertEquals('mw1:POST:/api/data', $captured[0], 'First middleware should get route and method');
    assertEquals('mw2:POST:/api/data', $captured[1], 'Second middleware should get route and method');

    unlink($tmpFile);
};

// Test 19: findBy with limit uses prepared statement
$tests[] = function () {
    $pdo = createMemoryPDO();
    prepareSchema($pdo);

    for ($i = 1; $i <= 5; $i++) {
        $orm = new NanoORM($pdo, 'users');
        $orm->fill(['name' => "User{$i}", 'email' => "u{$i}@test.com", 'status' => 'active'])->save();
    }

    $orm = new NanoORM($pdo, 'users');
    $results = $orm->findBy('status = ?', ['active'], 3);

    assertEquals(3, count($results), 'findBy with limit should return exactly 3');
    assertTrue(is_array($results[0]), 'findBy with limit should return arrays');
};

// Test 20: findAll with limit uses prepared statement
$tests[] = function () {
    $pdo = createMemoryPDO();
    prepareSchema($pdo);

    for ($i = 1; $i <= 5; $i++) {
        $orm = new NanoORM($pdo, 'users');
        $orm->fill(['name' => "User{$i}", 'email' => "u{$i}@test.com", 'status' => 'active'])->save();
    }

    $orm = new NanoORM($pdo, 'users');
    $results = $orm->findAll('status = ?', ['active'], 'name ASC', 3);

    assertEquals(3, count($results), 'findAll with limit should return exactly 3');
    assertEquals('User1', $results[0]['name'], 'findAll with limit and order should return ordered results');
};

runTests($tests);