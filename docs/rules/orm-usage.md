> Read this when using NanoORM for database operations.

# NanoORM Usage Pattern

## Setup

```php
$pdo = new PDO('sqlite:app.db');
$orm = new NanoORM($pdo, 'users');                    // PK defaults to 'id'
$orm = new NanoORM($pdo, 'user_settings', 'user_id'); // Custom PK
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

$rows = $orders->fetchWithJoins(['orders.status' => 'completed']);
// $rows is array of assoc arrays with aliased fields:
// ['id' => 1, 'status' => 'completed', 'j0_name' => 'Jane', 'j1_title' => 'Widget', ...]
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
- `findBy` and `findAll` return cloned instances — modifying one does not affect others.
- `fetchWithJoins` returns raw arrays, not ORM instances.
- `save()` decides insert vs update based on `isNew` flag, not PK presence.
- `deleteWhere([])` throws — empty conditions are rejected as a safety measure against accidental full-table deletes.
