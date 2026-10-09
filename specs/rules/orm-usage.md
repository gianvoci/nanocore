> Read this when you write CRUD, queries, joins, pagination, transactions, or migrations with NanoORM.

# NanoORM Usage

Contract: [orm/spec.md](../orm/spec.md). Conventions: [conventions-database.md](../conventions-database.md).

## CRUD

```php
$users = new NanoORM($pdo, 'users');               // PK 'id'

$user = (clone $users)->fillOnly($input, ['name', 'email']);
$user->save();                                     // INSERT, id from lastInsertId()

$found = $users->findById($user->getId());         // clone or null
$found->status = 'active';
$found->save();                                    // UPDATE all fields in $data

$found->delete();                                  // record reset, isNew() = true
$n = $users->deleteWhere('status = ?', ['banned']);
```

Use `clone` (or `clear()`) to start a new record from a configured instance — the schema is reused.

## Queries

```php
$rows = $users->findAll('status = ?', ['active'], 'created_at DESC', 20);
$page = $users->paginate(2, 20, 'status = ?', ['active'], 'id DESC');
```

## Joins

```php
$orders = new NanoORM($pdo, 'orders');
$rows = $orders
    ->addJoin('products', 'product_id', 'id', 'LEFT', ['title', 'price'])
    ->fetchWithJoins(['status' => 'paid']);        // keys: orders cols + j0_title, j0_price
$orders->clear();                                  // drop joins before paginate()
```

## Transactions

```php
$users->transaction(function () use ($users, $data) {
    $users->fill($data)->save();
    // throw to roll back
});
```

## Migrations

1. Create `migrations/NNN_description.sql` (zero-padded number, `[a-zA-Z0-9_]` only).
2. Optional: `migrations/rollback/NNN_description.sql` with the inverse SQL.
3. Run:

```php
$applied = NanoORM::migrateDir($pdo, __DIR__ . '/migrations');
$undone  = NanoORM::rollbackDir($pdo, __DIR__ . '/migrations', 1);
$status  = NanoORM::migrationStatus($pdo, __DIR__ . '/migrations');
```

4. Never rename or edit an applied migration file; add a new one.

## Changing NanoORM itself

1. Keep public/protected signatures stable (subclasses exist); add new methods instead of new params.
2. Every new identifier input → `validateIdentifier()`/`validateFieldName()`; values → placeholders.
3. Test on SQLite (`createMemoryPDO()` + `prepareSchema()`), update [orm/spec.md](../orm/spec.md) and README → NanoORM.
