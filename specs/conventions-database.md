> Read this when using NanoORM or writing database-related code.

# Database Conventions (NanoORM)

## Schema Discovery

NanoORM auto-discovers table columns on construction:
- **MySQL**: `DESCRIBE {table}` — reads `Field` column.
- **SQLite**: `PRAGMA table_info({table})` — reads `name` column.
- Falls back from MySQL to SQLite on failure. Throws with both error messages if both fail.

Only discovered fields (and the primary key) are accepted by `__set`. Unknown field names are silently ignored.

- Table name and primary key are validated in the constructor — must match `/^[a-zA-Z_][a-zA-Z0-9_]*$/` or an `InvalidArgumentException` is thrown.

## Primary Key

- Default is `id`. Override via constructor: `new NanoORM($pdo, 'users', 'user_id')`.
- Critical: tables with non-`id` primary keys MUST pass the third argument, or `findById`/`update`/`delete` will target the wrong column. The primary key name is validated at construction time.
- Auto-increment is assumed: `insert()` removes the PK from data, reads `lastInsertId()` after insert.

## CRUD Flow

| Operation | Method | Notes |
| --- | --- | --- |
| Create | `$orm->fill([...])->save()` | `isNew` starts true, insert is called |
| Read | `$orm->findById($id)` | Returns cloned instance or `null` |
| Read by field | `$orm->findBy('field', $value, 10)` | Returns array of cloned instances; optional third arg is limit |
| Read all | `$orm->findAll([...], 'col DESC', 10)` | Conditions, order, limit |
| Update | `$orm->field = 'x'; $orm->save()` | `isNew` is false after hydration |
| Delete | `$orm->delete()` | Requires primary key to be set |
| Batch delete | `$orm->deleteWhere(['status' => 'inactive'])` | Returns affected row count |

Field names in INSERT and UPDATE queries are backtick-quoted (`` `field` ``) as defense in depth — this prevents issues with SQL reserved words used as column names.

## Hydration and Cloning

- `findById`, `findBy`, and `findAll` all use `(clone $this)->hydrate($row)` — each result is an independent instance.
- `hydrate` sets `$data = $row` and `$isNew = false`.
- `clear()` resets data, sets `isNew = true`, clears joins.

## JOINs

```php
$orm->addJoin('table', 'localKey', 'foreignKey', 'INNER|LEFT|RIGHT|FULL|CROSS', ['field1', 'field2']);
```

- Joined tables get aliases: `j0`, `j1`, etc.
- Joined fields are aliased as `j0_fieldName` in the result set.
- `fetchWithJoins()` returns raw associative arrays (not ORM instances).
- `buildSelectQuery()` is used by both `findAll` and `fetchWithJoins`.
- `findBy` does NOT use `buildSelectQuery()` — it builds SQL directly and ignores registered JOINs. Use `fetchWithJoins()` for joined queries.
- Field names in conditions (`findBy`, `findAll`, `deleteWhere`, `fetchWithJoins`) are validated against `/^[a-zA-Z_][a-zA-Z0-9_]*$/`. Invalid names throw `InvalidArgumentException`.
- `addJoin()` validates table, localKey, foreignKey, and all select fields against the same regex. The `*` wildcard is allowed for select fields. Join type must be one of: `INNER`, `LEFT`, `RIGHT`, `FULL`, `CROSS`.
- `findAll()` validates `orderBy` via `sanitizeOrderBy()` — each column segment must match the identifier regex, direction must be a valid SQL keyword (ASC, DESC, with optional NULLS FIRST/LAST). Invalid input throws `InvalidArgumentException`.

## Gotchas

- PDO parameter binding: on Windows + SQLite, prepared statement parameters are bound as strings. This causes lexicographic comparison for numeric ranges (e.g., `BETWEEN`). If you encounter 0-row results with integer conditions, interpolate integer values directly in the SQL instead.
- `__set` silently ignores fields not in the discovered schema. If data isn't persisting, check that the column actually exists in the table.
- `save()` on a record without a primary key set will throw on update: `"Cannot update record without primary key"`. This can happen if you hydrate from a partial SELECT that omits the PK column.
- `deleteWhere([])` throws: `"Delete conditions cannot be empty"` — this is a safety guard against accidental full-table deletion.

## Pagination

- `paginate(int $page, int $perPage, array $conditions = [], string $orderBy = '')` — returns paginated results.
- Both `$page` and `$perPage` must be >= 1 (validated, throws `InvalidArgumentException` if not).
- Return structure:
  ```php
  [
      'data'      => [...],     // array of cloned NanoORM instances
      'total'     => 42,        // total matching rows (COUNT)
      'page'      => 1,         // current page
      'per_page'  => 10,        // items per page
      'last_page' => 5,         // ceil(total / per_page), always >= 1
  ]
  ```
- Implementation: runs COUNT query first, then SELECT with `OFFSET = ($page - 1) * $perPage` and `LIMIT = $perPage`.

## Transactions

- `beginTransaction()` — starts a database transaction via `PDO::beginTransaction()`.
- `commit()` — commits the current transaction.
- `rollback()` — rolls back the current transaction.
- `transaction(callable $callback)` — runs `$callback` inside a transaction. Auto-commits on success, auto-rolls back on `\Throwable`. Returns the callback's return value.
- ⚠️ MySQL DDL statements (ALTER, CREATE, DROP) cause implicit commit — they cannot be rolled back inside a transaction.

## Migrations

- `migrateDir(string $dir, PDO $pdo)` — static method. Runs all `.sql` files in `$dir` that haven't been applied yet, in alphabetical order.
- `rollbackDir(string $dir, PDO $pdo)` — static method. Rolls back the most recently applied migration by running the corresponding `.sql` file again (down migration).
- `migrationStatus(string $dir, PDO $pdo)` — static method. Returns array of all migrations with their applied status.
- File naming format: `YYYY_MM_DD_HH_MM_SS_name.sql` (e.g., `2025_01_15_10_30_00_create_users.sql`). Invalid file names throw `InvalidArgumentException`.
- Driver detection: checks `PDO::ATTR_DRIVER_NAME` — uses `SQLite` or `MySQL` dialect for the migrations table.
- `ensureMigrationsTable(PDO $pdo)` — private static method. Creates the `nanocore_migrations` table if it doesn't exist.
- `executeSqlFile(PDO $pdo, string $path)` — private static method. Reads and executes a `.sql` file.
