> Read this when using NanoORM for database operations.

# NanoORM Usage Pattern

## Setup

```php
$pdo = new PDO('sqlite:app.db');
$orm = new NanoORM($pdo, 'users');                    // PK defaults to 'id'
$orm = new NanoORM($pdo, 'user_settings', 'user_id'); // Custom PK

// Both table name and primary key are validated — must match /^[a-zA-Z_][a-zA-Z0-9_]*$/
// Invalid: new NanoORM($pdo, 'my-table') → InvalidArgumentException
// Invalid: new NanoORM($pdo, 'users', '1bad') → InvalidArgumentException
```

## CRUD Examples

### Create

```php
$user = new NanoORM($pdo, 'users');
$user->fill(['name' => 'Jane', 'email' => 'jane@example.com']);
$user->save();              // INSERT, auto-fills PK via lastInsertId()
echo $user->getId();        // The new ID
```

### Read

```php
$user = (new NanoORM($pdo, 'users'))->findById(1);
echo $user->name;           // Magic getter

$actives = (new NanoORM($pdo, 'users'))->findBy('status', 'active', 10);
// Returns array of NanoORM instances

$recent = (new NanoORM($pdo, 'posts'))->findAll(
    ['published' => 1],
    'created_at DESC',
    10
);
```

### Update

```php
$user = (new NanoORM($pdo, 'users'))->findById(1);
$user->email = 'new@example.com';
$user->save();              // UPDATE because isNew is false
```

### Delete

```php
$user = (new NanoORM($pdo, 'users'))->findById(1);
$user->delete();            // DELETE by PK

(new NanoORM($pdo, 'users'))->deleteWhere(['status' => 'inactive']);
// Returns number of deleted rows
```

## Joins

```php
$orders = new NanoORM($pdo, 'orders');
$orders
    ->addJoin('users', 'user_id', 'id', 'INNER', ['name', 'email'])
    ->addJoin('products', 'product_id', 'id', 'LEFT', ['title', 'price']);

$rows = $orders->fetchWithJoins(['status' => 'completed']);
// $rows is array of assoc arrays with aliased fields:
// ['id' => 1, 'status' => 'completed', 'j0_name' => 'Jane', 'j1_title' => 'Widget', ...]

// Note: condition keys must be plain field names (no table prefixes, no dots).
// Invalid: ['orders.status' => 'completed'] → InvalidArgumentException (dot not allowed)
// For ambiguous column names with JOINs, use fetchWithJoins() without conditions and filter in PHP.
```

## State Methods

```php
$orm->isNew();      // bool — true if not yet saved
$orm->getId();      // PK value or null
$orm->getTable();   // Table name string
$orm->toArray();    // All field data as array
$orm->clear();      // Reset to fresh state (clears data, joins, sets isNew)
isset($orm->name);   // __isset — check if field is set
unset($orm->name);   // __unset — remove field from data
```

## Important Notes

- `__set` silently drops fields not in the discovered schema — no error, no exception.
- `findById`, `findBy`, and `findAll` all return cloned instances — modifying one does not affect others.
- `fetchWithJoins` returns raw arrays, not ORM instances.
- `save()` decides insert vs update based on `isNew` flag, not PK presence.
- `deleteWhere([])` throws — empty conditions are rejected as a safety measure against accidental full-table deletes.
- Field names in conditions (`findBy`, `findAll`, `deleteWhere`, `fetchWithJoins`) are validated — must be plain identifiers matching `/^[a-zA-Z_][a-zA-Z0-9_]*$/`. Dotted names like `table.column` are rejected.
- `addJoin()` validates all parameters: table, keys must be valid identifiers; type must be one of `INNER`, `LEFT`, `RIGHT`, `FULL`, `CROSS`. Select fields are also validated — each field (except `*` wildcard) must match `/^[a-zA-Z_][a-zA-Z0-9_]*$/`.
- `findAll()` sanitizes ORDER BY — column names are validated segment by segment, directions must be valid SQL keywords. Invalid input throws `InvalidArgumentException`.

## Pagination

```php
$orm = new NanoORM($pdo, 'posts');

$result = $orm->paginate(page: 1, perPage: 10, conditions: ['published' => 1], orderBy: 'created_at DESC');
// $result = [
//     'data'      => [...],     // array of NanoORM instances
//     'total'     => 42,
//     'page'      => 1,
//     'per_page'  => 10,
//     'last_page' => 5,
// ]
```

- `$page` and `$perPage` must be >= 1 (throws `InvalidArgumentException` otherwise).
- `last_page` is always >= 1 (even with zero results).

## Transactions

```php
$orm = new NanoORM($pdo, 'orders');

// Manual transaction
$orm->beginTransaction();
try {
    $orm->fill(['product' => 'Widget', 'qty' => 5])->save();
    $orm->commit();
} catch (\Throwable $e) {
    $orm->rollback();
    throw $e;
}

// Automatic transaction
$orm->transaction(function () use ($orm) {
    $orm->fill(['product' => 'Gadget', 'qty' => 3])->save();
    // Auto-commits on success, auto-rolls back on \Throwable
});
```

- ⚠️ MySQL DDL statements cause implicit commit — they cannot be rolled back.

## Migrations

```php
// Run all pending migrations
NanoORM::migrateDir('/path/to/migrations', $pdo);

// Roll back the last migration
NanoORM::rollbackDir('/path/to/migrations', $pdo);

// Check migration status
$status = NanoORM::migrationStatus('/path/to/migrations', $pdo);
// Returns array of ['file' => '...', 'applied' => bool]
```

- File naming: `YYYY_MM_DD_HH_MM_SS_name.sql` (e.g., `2025_01_15_10_30_00_create_users.sql`).
- Invalid file names throw `InvalidArgumentException`.
- Driver detection: checks `PDO::ATTR_DRIVER_NAME` for SQLite vs MySQL dialect.
