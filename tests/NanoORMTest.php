<?php

declare(strict_types=1);

require __DIR__ . '/../NanoORM.php';

use NanoCore\NanoORM;
use PDO;
use RuntimeException;

function createMemoryPDO(): PDO
{
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    return $pdo;
}

function prepareSchema(PDO $pdo): void
{
    $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, email TEXT, status TEXT)');
    $pdo->exec('CREATE TABLE products (id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT, price REAL)');
    $pdo->exec('CREATE TABLE orders (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, product_id INTEGER, status TEXT)');
}

function assertEquals(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(sprintf("%s (expected %s, got %s)", $message, var_export($expected, true), var_export($actual, true)));
    }
}

function assertTrue(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

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
