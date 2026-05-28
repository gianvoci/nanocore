<?php

declare(strict_types=1);

require_once __DIR__ . '/../TestHelpers.php';

use NanoCore\NanoORM;

$tests = [];

$tests[] = function () {
    $pdo = createMemoryPDO();
    prepareSchema($pdo);

    $user = new NanoORM($pdo, 'users');
    $user->fill(['name' => 'Jane Doe', 'email' => 'jane@example.com', 'status' => 'active']);

    assertTrue($user->save(), 'Insert should return true');
    $id = $user->getId();
    assertTrue(is_int($id) || is_string($id), 'Primary key must be populated');

    $retrieved = (new NanoORM($pdo, 'users'))->findById($id);
    assertEquals('Jane Doe', $retrieved->name, 'Name should match inserted value');
    assertEquals('jane@example.com', $retrieved->email, 'Email should match inserted value');

    $retrieved->email = 'jane.updated@example.com';
    assertTrue($retrieved->save(), 'Update should succeed');

    $fresh = (new NanoORM($pdo, 'users'))->findById($id);
    assertEquals('jane.updated@example.com', $fresh->email, 'Updated email should persist');
    assertTrue($fresh->isNew() === false, 'Hydrated record should be marked as persisted');
};

$tests[] = function () {
    $pdo = createMemoryPDO();
    prepareSchema($pdo);

    $first = new NanoORM($pdo, 'users');
    $first->fill(['name' => 'Old User', 'email' => 'old@example.com', 'status' => 'inactive']);
    $first->save();

    $second = new NanoORM($pdo, 'users');
    $second->fill(['name' => 'Also Inactive', 'email' => 'inactive@example.com', 'status' => 'inactive']);
    $second->save();

    $deleted = $first->deleteWhere(['status' => 'inactive']);
    assertEquals(2, $deleted, 'Should delete both inactive records');

    $stillExists = (new NanoORM($pdo, 'users'))->findBy('status', 'inactive');
    assertEquals([], $stillExists, 'No inactive records should remain');
};

$tests[] = function () {
    $pdo = createMemoryPDO();
    prepareSchema($pdo);

    $user = new NanoORM($pdo, 'users');
    $user->fill(['name' => 'Order User', 'email' => 'order@example.com', 'status' => 'active']);
    $user->save();

    $product = new NanoORM($pdo, 'products');
    $product->fill(['title' => 'Widget', 'price' => 9.99]);
    $product->save();

    $order = new NanoORM($pdo, 'orders');
    $order->fill(['user_id' => $user->getId(), 'product_id' => $product->getId(), 'status' => 'completed']);
    $order->save();

    $ordersOrm = new NanoORM($pdo, 'orders');
    $ordersOrm
        ->addJoin('users', 'user_id', 'id', 'INNER', ['name'])
        ->addJoin('products', 'product_id', 'id', 'LEFT', ['title']);

    $results = $ordersOrm->fetchWithJoins();
    $results = array_filter($results, fn ($row) => (string)($row['id'] ?? '') === (string)$order->getId());
    $results = array_values($results);
    assertEquals(1, count($results), 'Should return a single joined row');

    $joined = $results[0];
    assertEquals('Order User', $joined['j0_name'] ?? null, 'Joined user name should be available');
    assertEquals('Widget', $joined['j1_title'] ?? null, 'Joined product title should be available');
};

// Test 4: findById returns cloned instances
$tests[] = function () {
    $pdo = createMemoryPDO();
    prepareSchema($pdo);

    $user = new NanoORM($pdo, 'users');
    $user->fill(['name' => 'CloneTest', 'email' => 'clone@test.com', 'status' => 'active']);
    $user->save();
    $id = $user->getId();

    $user1 = (new NanoORM($pdo, 'users'))->findById($id);
    $user2 = (new NanoORM($pdo, 'users'))->findById($id);

    $user1->name = 'changed';
    assertTrue($user2->name !== 'changed', 'Cloned instances must be independent');
    assertTrue($user->name !== 'changed', 'Original ORM instance must be untouched');
};

// Test 5: findById returns null for non-existent ID
$tests[] = function () {
    $pdo = createMemoryPDO();
    prepareSchema($pdo);

    $result = (new NanoORM($pdo, 'users'))->findById(99999);
    assertTrue($result === null, 'findById should return null for non-existent ID');
};

// Test 6: Custom primaryKey works correctly
$tests[] = function () {
    $pdo = createMemoryPDO();
    $pdo->exec('CREATE TABLE settings (user_id INTEGER PRIMARY KEY, key TEXT, value TEXT)');
    $pdo->exec("INSERT INTO settings (user_id, key, value) VALUES (1, 'theme', 'dark')");

    $orm = new NanoORM($pdo, 'settings', 'user_id');
    $setting = $orm->findById(1);
    assertEquals('dark', $setting->value, 'Should find record by custom primary key');

    $setting->value = 'light';
    assertTrue($setting->save(), 'Update with custom PK should succeed');

    $fresh = (new NanoORM($pdo, 'settings', 'user_id'))->findById(1);
    assertEquals('light', $fresh->value, 'Updated value should persist');

    assertTrue($fresh->delete(), 'Delete by custom PK should succeed');
    $gone = (new NanoORM($pdo, 'settings', 'user_id'))->findById(1);
    assertTrue($gone === null, 'Deleted record should not be found');
};

// Test 7: validateIdentifier rejects invalid table names
$tests[] = function () {
    $pdo = createMemoryPDO();
    prepareSchema($pdo);

    assertThrows(\InvalidArgumentException::class, '', function () use ($pdo) {
        new NanoORM($pdo, 'invalid-table');
    });

    assertThrows(\InvalidArgumentException::class, '', function () use ($pdo) {
        new NanoORM($pdo, 'users', 'bad key');
    });

    assertThrows(\InvalidArgumentException::class, '', function () use ($pdo) {
        new NanoORM($pdo, '123numbers');
    });

    // Valid table name should not throw InvalidArgumentException
    // (It may throw other exceptions if the table doesn't exist, but validateIdentifier should be fine)
    try {
        new NanoORM($pdo, 'valid_table');
    } catch (\InvalidArgumentException $e) {
        assertTrue(false, 'Valid table name should not throw InvalidArgumentException');
    }
};

// Test 8: validateFieldName rejects invalid field names in findBy
$tests[] = function () {
    $pdo = createMemoryPDO();
    prepareSchema($pdo);

    assertThrows(\InvalidArgumentException::class, '', function () use ($pdo) {
        (new NanoORM($pdo, 'users'))->findBy('id; DROP TABLE users--', 'test');
    });
};

// Test 9: validateFieldName rejects invalid field names in deleteWhere
$tests[] = function () {
    $pdo = createMemoryPDO();
    prepareSchema($pdo);

    assertThrows(\InvalidArgumentException::class, '', function () use ($pdo) {
        (new NanoORM($pdo, 'users'))->deleteWhere(['1=1 OR 1' => 'x']);
    });
};

// Test 10: sanitizeOrderBy validates ORDER BY
$tests[] = function () {
    $pdo = createMemoryPDO();
    prepareSchema($pdo);

    $user = new NanoORM($pdo, 'users');
    $user->fill(['name' => 'OrderTest', 'email' => 'order@test.com', 'status' => 'active']);
    $user->save();

    $orm = new NanoORM($pdo, 'users');

    // Valid order by should work
    $results = $orm->findAll([], 'name ASC');
    assertTrue(count($results) >= 1, 'findAll with valid ASC order should succeed');

    $results = $orm->findAll([], 'name DESC');
    assertTrue(count($results) >= 1, 'findAll with valid DESC order should succeed');

    // SQL injection in ORDER BY should throw
    assertThrows(\InvalidArgumentException::class, '', function () use ($orm) {
        $orm->findAll([], 'name; DROP TABLE users--');
    });

    // ORDER BY starting with digit should throw
    assertThrows(\InvalidArgumentException::class, '', function () use ($orm) {
        $orm->findAll([], '1=1');
    });
};

// Test 11: addJoin validates parameters
$tests[] = function () {
    $pdo = createMemoryPDO();
    prepareSchema($pdo);

    $orm = new NanoORM($pdo, 'orders');

    // Valid join should not throw
    $orm->addJoin('users', 'user_id', 'id', 'INNER');

    $threw = false;
    try {
        $orm->addJoin('bad table', 'user_id', 'id');
    } catch (\InvalidArgumentException $e) {
        $threw = true;
    }
    assertTrue($threw, 'Invalid join table name should throw InvalidArgumentException');

    $threw = false;
    try {
        $orm->addJoin('users', 'user_id', 'id', 'INVALID');
    } catch (\InvalidArgumentException $e) {
        $threw = true;
    }
    assertTrue($threw, 'Invalid join type should throw InvalidArgumentException');
};

// Test 12: findAll with conditions, orderBy, and limit
$tests[] = function () {
    $pdo = createMemoryPDO();
    prepareSchema($pdo);

    $users = [
        ['name' => 'Charlie', 'email' => 'c@test.com', 'status' => 'active'],
        ['name' => 'Alice', 'email' => 'a@test.com', 'status' => 'active'],
        ['name' => 'Bob', 'email' => 'b@test.com', 'status' => 'active'],
    ];
    foreach ($users as $u) {
        $orm = new NanoORM($pdo, 'users');
        $orm->fill($u);
        $orm->save();
    }

    $orm = new NanoORM($pdo, 'users');
    $active = $orm->findAll(['status' => 'active'], 'name ASC', 2);
    assertTrue(count($active) <= 2, 'findAll with limit should return at most 2');
    if (count($active) >= 2) {
        assertEquals('Alice', $active[0]->name, 'First result should be Alice (alphabetically)');
        assertEquals('Bob', $active[1]->name, 'Second result should be Bob');
    }

    $none = $orm->findAll(['status' => 'nonexistent']);
    assertEquals([], $none, 'findAll with no matching conditions should return empty array');
};

// Test 13: delete() single record
$tests[] = function () {
    $pdo = createMemoryPDO();
    prepareSchema($pdo);

    $user = new NanoORM($pdo, 'users');
    $user->fill(['name' => 'ToDelete', 'email' => 'del@test.com', 'status' => 'active']);
    $user->save();
    $id = $user->getId();

    assertTrue($user->delete(), 'delete() should return true');
    assertTrue((new NanoORM($pdo, 'users'))->findById($id) === null, 'Deleted record should not be found');
    assertTrue($user->isNew(), 'Record should be marked as new after delete');
};

// Test 14: clear() resets state
$tests[] = function () {
    $pdo = createMemoryPDO();
    prepareSchema($pdo);

    $user = new NanoORM($pdo, 'users');
    $user->fill(['name' => 'ClearTest', 'email' => 'clear@test.com', 'status' => 'active']);
    $user->save();

    $user->clear();
    assertTrue($user->isNew(), 'clear() should reset isNew to true');
    assertEquals([], $user->toArray(), 'clear() should empty all data');
};

// Test 15: __set throws on unknown fields
$tests[] = function () {
    $pdo = createMemoryPDO();
    prepareSchema($pdo);

    $user = new NanoORM($pdo, 'users');

    assertThrows(
        \InvalidArgumentException::class,
        '',
        function () use ($user) {
            $user->nonexistent_field = 'value';
        }
    );
};

runTests($tests);
