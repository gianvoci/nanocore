<?php

declare(strict_types=1);

require_once __DIR__ . '/../TestHelpers.php';

use NanoCore\NanoORM;

$tests = [];

// Test 1: Single INNER JOIN returns joined data
$tests[] = function () {
    $pdo = createMemoryPDO();
    prepareSchema($pdo);

    $pdo->exec("INSERT INTO users (id, name) VALUES (1, 'Alice')");
    $pdo->exec("INSERT INTO orders (id, user_id, product_id) VALUES (1, 1, NULL)");

    $orm = new NanoORM($pdo, 'orders');
    $orm->addJoin('users', 'user_id', 'id', 'INNER', ['name']);
    $results = $orm->fetchWithJoins();

    assertEquals(1, count($results), 'Single INNER JOIN should return 1 row');
    assertEquals('Alice', $results[0]['j0_name'], 'Joined name should be Alice');
};

// Test 2: Multiple INNER JOINs return data from all joined tables
$tests[] = function () {
    $pdo = createMemoryPDO();
    prepareSchema($pdo);

    $pdo->exec("INSERT INTO users (id, name) VALUES (1, 'Alice')");
    $pdo->exec("INSERT INTO products (id, title) VALUES (1, 'Widget')");
    $pdo->exec("INSERT INTO orders (id, user_id, product_id) VALUES (1, 1, 1)");

    $orm = new NanoORM($pdo, 'orders');
    $orm->addJoin('users', 'user_id', 'id', 'INNER', ['name']);
    $orm->addJoin('products', 'product_id', 'id', 'INNER', ['title']);
    $results = $orm->fetchWithJoins();

    assertEquals(1, count($results), 'Multiple joins should return 1 row');
    assertEquals('Alice', $results[0]['j0_name'], 'First join should have user name Alice');
    assertEquals('Widget', $results[0]['j1_title'], 'Second join should have product title Widget');
};

// Test 3: Invalid join type throws InvalidArgumentException
$tests[] = function () {
    $pdo = createMemoryPDO();
    prepareSchema($pdo);

    $orm = new NanoORM($pdo, 'orders');

    assertThrows(
        \InvalidArgumentException::class,
        "Invalid join type: 'INVALID'",
        fn() => $orm->addJoin('users', 'user_id', 'id', 'INVALID')
    );
};

// Test 4: LEFT JOIN returns null for missing foreign record
$tests[] = function () {
    $pdo = createMemoryPDO();
    prepareSchema($pdo);

    $pdo->exec("INSERT INTO orders (id, user_id, product_id) VALUES (1, 999, NULL)");

    $orm = new NanoORM($pdo, 'orders');
    $orm->addJoin('users', 'user_id', 'id', 'LEFT', ['name']);
    $results = $orm->fetchWithJoins();

    assertEquals(1, count($results), 'LEFT JOIN should return 1 row even without match');
    assertEquals(null, $results[0]['j0_name'], 'Missing foreign record should yield null');
};

// Test 5: addJoin returns self for method chaining
$tests[] = function () {
    $pdo = createMemoryPDO();
    prepareSchema($pdo);

    $orm = new NanoORM($pdo, 'orders');
    $returned = $orm->addJoin('users', 'user_id', 'id', 'INNER', ['name']);

    assertTrue($orm === $returned, 'addJoin should return the same NanoORM instance');
};

// Test 6: Invalid table name in addJoin throws InvalidArgumentException
$tests[] = function () {
    $pdo = createMemoryPDO();
    prepareSchema($pdo);

    $orm = new NanoORM($pdo, 'orders');

    assertThrows(
        \InvalidArgumentException::class,
        '',
        fn() => $orm->addJoin('bad table', 'user_id', 'id')
    );
};

// Test 7: Invalid field name in addJoin select fields throws InvalidArgumentException
$tests[] = function () {
    $pdo = createMemoryPDO();
    prepareSchema($pdo);

    $orm = new NanoORM($pdo, 'orders');

    assertThrows(
        \InvalidArgumentException::class,
        '',
        fn() => $orm->addJoin('users', 'user_id', 'id', 'INNER', ['bad field'])
    );
};

// Test 8: Select all fields from joined table with wildcard
$tests[] = function () {
    $pdo = createMemoryPDO();
    prepareSchema($pdo);

    $pdo->exec("INSERT INTO users (id, name, email) VALUES (1, 'Alice', 'alice@example.com')");
    $pdo->exec("INSERT INTO orders (id, user_id, product_id) VALUES (1, 1, NULL)");

    $orm = new NanoORM($pdo, 'orders');
    $orm->addJoin('users', 'user_id', 'id', 'INNER', ['*']);
    $results = $orm->fetchWithJoins();

    assertEquals(1, count($results), 'Wildcard join should return 1 row');
    // With wildcard, the joined table columns come as j0.* — check that user data is present
    assertTrue(
        isset($results[0]['j0_name']) || isset($results[0]['name']),
        'Wildcard join should include user columns'
    );
};

runTests($tests);
