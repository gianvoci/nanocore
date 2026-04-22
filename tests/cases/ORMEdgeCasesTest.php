<?php

declare(strict_types=1);

require_once __DIR__ . '/../TestHelpers.php';

use NanoCore\NanoORM;

$tests = [];

// Test 1: deleteWhere with empty conditions throws exception
$tests[] = function () {
    $pdo = createMemoryPDO();
    prepareSchema($pdo);

    $orm = new NanoORM($pdo, 'users');

    assertThrows(\Exception::class, 'Delete conditions cannot be empty', function () use ($orm) {
        $orm->deleteWhere([]);
    });
};

// Test 2: save() update without primary key throws exception
$tests[] = function () {
    $pdo = createMemoryPDO();
    prepareSchema($pdo);

    // Insert a user so it gets an ID and isNew=false
    $user = new NanoORM($pdo, 'users');
    $user->fill(['name' => 'Test', 'email' => 'test@test.com', 'status' => 'active']);
    $user->save();

    // Now unset the PK field — isNew is false, so save() will try to update
    unset($user->id);

    assertThrows(\Exception::class, 'Cannot update record without primary key', function () use ($user) {
        $user->save();
    });
};

// Test 3: findBy ignores registered JOINs
$tests[] = function () {
    $pdo = createMemoryPDO();
    prepareSchema($pdo);

    // Insert a user and an order
    $user = new NanoORM($pdo, 'users');
    $user->fill(['name' => 'JoinTest', 'email' => 'join@test.com', 'status' => 'active']);
    $user->save();

    $order = new NanoORM($pdo, 'orders');
    $order->fill(['user_id' => $user->getId(), 'product_id' => null, 'status' => 'completed']);
    $order->save();

    // Register a JOIN, then call findBy — it should still work without JOIN complications
    $ordersOrm = new NanoORM($pdo, 'orders');
    $ordersOrm->addJoin('users', 'user_id', 'id', 'INNER', ['name']);

    $results = $ordersOrm->findBy('status', 'completed');
    assertEquals(1, count($results), 'findBy should return 1 result ignoring JOINs');
    assertEquals('completed', $results[0]->status, 'findBy result should have correct status');
};

// Test 4: fetchWithJoins with conditions
$tests[] = function () {
    $pdo = createMemoryPDO();
    prepareSchema($pdo);

    // Insert two users and a product
    $user1 = new NanoORM($pdo, 'users');
    $user1->fill(['name' => 'User One', 'email' => 'one@test.com', 'status' => 'active']);
    $user1->save();

    $user2 = new NanoORM($pdo, 'users');
    $user2->fill(['name' => 'User Two', 'email' => 'two@test.com', 'status' => 'active']);
    $user2->save();

    $product = new NanoORM($pdo, 'products');
    $product->fill(['title' => 'Widget', 'price' => 9.99]);
    $product->save();

    // One order per user
    $completed = new NanoORM($pdo, 'orders');
    $completed->fill(['user_id' => $user1->getId(), 'product_id' => $product->getId(), 'status' => 'completed']);
    $completed->save();

    $pending = new NanoORM($pdo, 'orders');
    $pending->fill(['user_id' => $user2->getId(), 'product_id' => $product->getId(), 'status' => 'pending']);
    $pending->save();

    $ordersOrm = new NanoORM($pdo, 'orders');
    $ordersOrm->addJoin('users', 'user_id', 'id', 'INNER', ['name']);

    // Filter by user_id to get only user1's completed order
    $results = $ordersOrm->fetchWithJoins(['user_id' => $user1->getId()]);
    assertEquals(1, count($results), 'fetchWithJoins with conditions should return only matching orders');
    assertEquals('completed', $results[0]['status'], 'Returned order should be the completed one');
    assertEquals('User One', $results[0]['j0_name'] ?? null, 'Joined user name should be present');
};

// Test 5: fetchWithJoins returns raw arrays, not ORM instances
$tests[] = function () {
    $pdo = createMemoryPDO();
    prepareSchema($pdo);

    $user = new NanoORM($pdo, 'users');
    $user->fill(['name' => 'RawArray', 'email' => 'raw@test.com', 'status' => 'active']);
    $user->save();

    $order = new NanoORM($pdo, 'orders');
    $order->fill(['user_id' => $user->getId(), 'product_id' => null, 'status' => 'pending']);
    $order->save();

    $ordersOrm = new NanoORM($pdo, 'orders');
    $ordersOrm->addJoin('users', 'user_id', 'id', 'INNER', ['name']);

    $results = $ordersOrm->fetchWithJoins();
    assertTrue(count($results) > 0, 'fetchWithJoins should return at least one result');
    assertTrue(is_array($results[0]), 'fetchWithJoins should return raw arrays, not ORM instances');
};

// Test 6: __isset and __unset work correctly
$tests[] = function () {
    $pdo = createMemoryPDO();
    prepareSchema($pdo);

    $user = new NanoORM($pdo, 'users');
    $user->fill(['name' => 'IssetTest', 'email' => 'isset@test.com', 'status' => 'active']);
    $user->save();

    // isset on a set field
    assertTrue(isset($user->name), 'isset should return true for a set field');

    // unset removes it
    unset($user->name);
    assertTrue(!isset($user->name), 'isset should return false after unset');

    // isset on a field that was never set
    assertTrue(!isset($user->nonexistent_field), 'isset should return false for non-schema field');
};

// Test 7: fill() returns self for chaining
$tests[] = function () {
    $pdo = createMemoryPDO();
    prepareSchema($pdo);

    $user = new NanoORM($pdo, 'users');
    $result = $user->fill(['name' => 'Chain', 'email' => 'chain@test.com', 'status' => 'active']);

    assertTrue($result === $user, 'fill() should return self for chaining');
};

// Test 8: fill() silently ignores non-schema fields
$tests[] = function () {
    $pdo = createMemoryPDO();
    prepareSchema($pdo);

    $user = new NanoORM($pdo, 'users');
    $user->fill(['name' => 'Test', 'nonexistent' => 'ignored']);

    $arr = $user->toArray();
    assertEquals('Test', $arr['name'], 'Schema field should be set');
    assertTrue(!array_key_exists('nonexistent', $arr), 'Non-schema field should be silently ignored');
};

// Test 9: isNew() returns true before save, false after
$tests[] = function () {
    $pdo = createMemoryPDO();
    prepareSchema($pdo);

    $user = new NanoORM($pdo, 'users');
    assertTrue($user->isNew(), 'Fresh instance should be new');

    $user->fill(['name' => 'NewTest', 'email' => 'new@test.com', 'status' => 'active'])->save();
    assertTrue(!$user->isNew(), 'After save, instance should not be new');
};

// Test 10: clear() also clears joins
$tests[] = function () {
    $pdo = createMemoryPDO();
    prepareSchema($pdo);

    // Insert data so fetchWithJoins has something to return
    $user = new NanoORM($pdo, 'users');
    $user->fill(['name' => 'ClearJoin', 'email' => 'clearjoin@test.com', 'status' => 'active']);
    $user->save();

    $order = new NanoORM($pdo, 'orders');
    $order->fill(['user_id' => $user->getId(), 'product_id' => null, 'status' => 'pending']);
    $order->save();

    $ordersOrm = new NanoORM($pdo, 'orders');
    $ordersOrm->addJoin('users', 'user_id', 'id', 'INNER', ['name']);

    // Before clear, joins are active
    $withJoins = $ordersOrm->fetchWithJoins();
    assertTrue(array_key_exists('j0_name', $withJoins[0]), 'Before clear, joined fields should be present');

    // After clear, joins are gone
    $ordersOrm->clear();

    $afterClear = $ordersOrm->fetchWithJoins();
    assertTrue(count($afterClear) > 0, 'After clear, results should still exist');

    // Verify no JOIN keys remain in the result rows
    foreach ($afterClear as $row) {
        foreach (array_keys($row) as $key) {
            assertTrue(
                !str_starts_with($key, 'j0_') && !str_starts_with($key, 'j1_'),
                "After clear, result row must not contain JOIN keys, found: {$key}"
            );
        }
    }
};

// Test 11: getTable() returns table name
$tests[] = function () {
    $pdo = createMemoryPDO();
    prepareSchema($pdo);

    $orm = new NanoORM($pdo, 'users');
    assertEquals('users', $orm->getTable(), 'getTable() should return the table name');
};

// Test 12: getId() returns null before save
$tests[] = function () {
    $pdo = createMemoryPDO();
    prepareSchema($pdo);

    $user = new NanoORM($pdo, 'users');
    assertEquals(null, $user->getId(), 'getId() should return null before save');
};

// Test 13: Multiple params in findAll conditions
$tests[] = function () {
    $pdo = createMemoryPDO();
    prepareSchema($pdo);

    $data = [
        ['name' => 'Alice', 'email' => 'alice@test.com', 'status' => 'active'],
        ['name' => 'Alice', 'email' => 'alice2@test.com', 'status' => 'inactive'],
        ['name' => 'Bob', 'email' => 'bob@test.com', 'status' => 'active'],
    ];
    foreach ($data as $row) {
        $orm = new NanoORM($pdo, 'users');
        $orm->fill($row)->save();
    }

    $orm = new NanoORM($pdo, 'users');
    $results = $orm->findAll(['name' => 'Alice', 'status' => 'active']);
    assertEquals(1, count($results), 'findAll with multiple conditions should match only Alice+active');
    assertEquals('Alice', $results[0]->name, 'Matched name should be Alice');
    assertEquals('active', $results[0]->status, 'Matched status should be active');
};

// Test 14: findAll with no conditions returns all records
$tests[] = function () {
    $pdo = createMemoryPDO();
    prepareSchema($pdo);

    for ($i = 1; $i <= 3; $i++) {
        $orm = new NanoORM($pdo, 'users');
        $orm->fill(['name' => "User{$i}", 'email' => "user{$i}@test.com", 'status' => 'active'])->save();
    }

    $orm = new NanoORM($pdo, 'users');
    $results = $orm->findAll([]);
    assertEquals(3, count($results), 'findAll with no conditions should return all records');
};

// Test 15: Delete without primary key throws exception
$tests[] = function () {
    $pdo = createMemoryPDO();
    prepareSchema($pdo);

    $user = new NanoORM($pdo, 'users');
    $user->fill(['name' => 'Test']);

    assertThrows(\Exception::class, 'Cannot delete record without primary key', function () use ($user) {
        $user->delete();
    });
};

// Test 16: findBy with limit parameter
$tests[] = function () {
    $pdo = createMemoryPDO();
    prepareSchema($pdo);

    // Insert 5 active users
    for ($i = 1; $i <= 5; $i++) {
        $orm = new NanoORM($pdo, 'users');
        $orm->fill(['name' => "LimitUser{$i}", 'email' => "limit{$i}@test.com", 'status' => 'active'])->save();
    }

    $orm = new NanoORM($pdo, 'users');

    // With limit 3
    $limited = $orm->findBy('status', 'active', 3);
    assertEquals(3, count($limited), 'findBy with limit=3 should return exactly 3 results');

    // Without limit
    $all = $orm->findBy('status', 'active');
    assertEquals(5, count($all), 'findBy without limit should return all 5 results');
};

runTests($tests);
