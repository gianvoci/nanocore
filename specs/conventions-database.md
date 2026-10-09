> Read this when you write code that uses NanoORM, raw WHERE clauses, or SQL migrations.

# Database Conventions

Contracts (state model, CRUD semantics, migration ordering): [orm/spec.md](orm/spec.md). Trust boundaries: [integrations/database.md](integrations/database.md).

## Connection

- NanoORM takes an existing `\PDO`; it never connects. Set `PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION` (the ORM does not check `execute()` failures beyond its return value).
- Supported drivers: MySQL/MariaDB (`DESCRIBE`) and SQLite (`PRAGMA table_info`). Other drivers fail at construction.
- Tests use `sqlite::memory:` (`createMemoryPDO()`).

## Usage patterns

| Do | Don't |
| --- | --- |
| `findBy('email = ?', [$email])` | `findBy("email = '$email'")` |
| `fillOnly($input, ['name', 'email'])` for request data | `fill($input)` with raw request data (throws on unknown keys, assigns any known column incl. privileged ones) |
| `findAll('', [], 'created_at DESC, id')` | `ORDER BY` built from user input without going through `$orderBy` |
| `$orm->clear()` before reusing an instance for `paginate()` after `addJoin()` | Calling `paginate()` with joins registered (throws) |
| `transaction(fn() => ...)` for multi-statement writes | Manual begin/commit without rollback on `\Error` |

- `findBy`/`findAll`/`paginate`/`deleteWhere` return or affect raw rows (`array<array>`), not ORM objects; only `findById()` returns an object.
- Positional (`?`) or named (`:x`) placeholders are both fine in raw WHERE; do not mix in one statement.
- Joined columns come back as `j{index}_{field}` (e.g. `j0_title`); `'*'` selects `j0.*` unaliased and can overwrite main-table keys with the same name.

## Schema conventions

- Table, primary key, join table and column names: `^[a-zA-Z_][a-zA-Z0-9_]*$` (enforced).
- Primary key is assumed auto-increment: it is never sent in `INSERT` (see [orm/spec.md](orm/spec.md#business-rules)).

## Migrations layout

```
migrations/
  001_create_users.sql
  002_add_status.sql
  rollback/
    002_add_status.sql   # same file name as the forward migration
```

- File name regex `^\d+_[a-zA-Z0-9_]+\.sql$`; zero-pad the number (ordering is a string `sort()`).
- One statement per `;`; no `;` inside string literals or comments.
- Recipe: [rules/orm-usage.md](rules/orm-usage.md#migrations).
