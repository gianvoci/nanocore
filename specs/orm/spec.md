> Read this when you change `src/NanoORM.php` or rely on its record state, CRUD, join, pagination, or migration behavior.

# NanoORM

## Purpose

Active-record wrapper over one table of an injected `\PDO`, plus a static SQL-file migration runner. Usage patterns: [conventions-database.md](../conventions-database.md).

## Data model

| Property | Type | Notes |
| --- | --- | --- |
| `$pdo` | `?\PDO` | Injected, never created |
| `$table` | `string` | Validated identifier |
| `$primaryKey` | `string` | Default `id`, validated identifier |
| `$fields` | `string[]` | Column names loaded once in the constructor |
| `$data` | `array` | Current record values |
| `$joins` | `array` | `{table, localKey, foreignKey, type, fields}` per `addJoin()` |
| `$isNew` | `bool` | `true` until insert/hydration |

`migrations` table: `id` (auto-increment PK), `name` (unique), `applied_at` (`TEXT` on SQLite, `DATETIME` elsewhere). Created on demand by every migration method.

## Business rules

### Schema & field access
1. Schema: `DESCRIBE` first, then `PRAGMA table_info` fallback; both failing → `\Exception('Unable to load schema for table ...')`.
2. `__set`, `fill()`, `fromArray()` reject keys not in `$fields` (and ≠ PK) with `\InvalidArgumentException('Unknown field: <name>')`.
3. `fillOnly($data, $allowed)` = `fill(array_intersect_key($data, array_flip($allowed)))`; `fill()` signature must stay unchanged (subclasses override it).
4. `__get` of an unset field returns `null`; `__isset` uses `isset` (null values count as unset).

### State transitions

| Operation | `$data` | `$isNew` after |
| --- | --- | --- |
| `new` / `clear()` | `[]` (clear also drops joins) | `true` |
| `fromArray($row)` | replaced by `$row` | `false` |
| `findById($id)` | returns a **clone** hydrated; original untouched | `false` (clone) |
| `save()` when new → `insert()` | PK set from `lastInsertId()` if it was not set | `false` (even if execute returned false) |
| `save()` when not new → `update()` | unchanged | `false` |
| `delete()` success | `[]` | `true` |

### CRUD
- `insert()` always drops the PK column from the `INSERT` (assumes auto-increment); a manually set PK is not persisted but stays in `$data`.
- `update()` writes every key in `$data` (no dirty tracking); missing PK → `\Exception('Cannot update record without primary key')`.
- `delete()` without PK → `\Exception('Cannot delete record without primary key')`.
- `deleteWhere('')` → `\Exception('Delete conditions cannot be empty')`; returns `rowCount()`.
- `findBy`/`findAll` ignore registered joins; `$limit` is interpolated as `int`.

### Joins
- Allowed types: `INNER`, `LEFT`, `RIGHT`, `FULL`, `CROSS` (case-insensitive input). Driver support is not checked.
- Alias `j{index}`; explicit fields selected as `j{index}_{field}`; `'*'` → `j{index}.*`.
- `ON main.localKey = j{index}.foreignKey`; `fetchWithJoins($conditions)` emits unqualified `field = :field` AND-ed (ambiguous if the column exists in both tables).

### Pagination
- `page < 1` or `perPage < 1` → `\InvalidArgumentException`; joins registered → `\Exception('Paginate does not support joined queries')`.
- `last_page = max(1, ceil(total / perPage))`; pages beyond the last return empty `data`.
- Count and select use the same `$where`/`$params`.

### ORDER BY
- Comma-separated parts; column = dotted segments of `^[a-zA-Z_][a-zA-Z0-9_]*$`; direction ∈ `ASC`, `DESC`, `ASC/DESC NULLS FIRST/LAST`; anything else throws `\InvalidArgumentException`.

### Transactions
- `transaction($cb)`: begin → `$cb()` → commit; on `\Exception` or `\Error` rollback and rethrow. Nested calls are not supported (PDO throws).

### Migrations
1. Every `*.sql` in the dir must match `^\d+_[a-zA-Z0-9_]+\.sql$`, else `\InvalidArgumentException` before anything runs.
2. Pending files run in `sort()` (string) order; each is recorded in `migrations` after its SQL succeeds.
3. SQL is split naively on `;`; non-SQLite drivers wrap the file in a transaction (MySQL DDL still auto-commits), SQLite runs statements without a transaction.
4. `rollbackDir($steps)` takes the last `$steps` rows by `id DESC`, runs `rollback/<name>`; missing file → `\Exception('No rollback file found for <name>')`.
5. `migrationStatus()` → `applied` (ordered by name, from DB) and `pending` (files minus applied).
6. Missing directory → `\Exception('Migrations directory not found: <dir>')` for all three methods.

## Constants

| Name | Value | Location |
| --- | --- | --- |
| Identifier regex | `^[a-zA-Z_][a-zA-Z0-9_]*$` | `validateIdentifier()`, `validateFieldName()`, `sanitizeOrderBy()` |
| Migration file regex | `^\d+_[a-zA-Z0-9_]+\.sql$` | `migrateDir()`, `rollbackDir()`, `migrationStatus()` |
| Join types | `INNER, LEFT, RIGHT, FULL, CROSS` | `addJoin()` |

## Cross-links

- Trust boundaries: [integrations/database.md](../integrations/database.md)
- Recipe: [rules/orm-usage.md](../rules/orm-usage.md)
- API: [api-routes.md](../api-routes.md#nanoorm)
