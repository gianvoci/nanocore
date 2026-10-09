> Read this when you change SQL generation in NanoORM or pass caller input into ORM queries.

# Database (PDO)

Contract: [orm/spec.md](../orm/spec.md). Usage: [conventions-database.md](../conventions-database.md).

## Implementing files

| File | Key symbols |
| --- | --- |
| `src/NanoORM.php` | `validateIdentifier()`, `validateFieldName()`, `sanitizeOrderBy()`, `buildSelectQuery()`, `insert()`, `update()`, `executeSqlFile()`, `ensureMigrationsTable()` |

## Flow

1. Consumer creates `\PDO` and passes it to `new NanoORM($pdo, $table, $pk)`.
2. Identifiers validated; schema loaded (`DESCRIBE` / `PRAGMA table_info`).
3. Queries built with backtick-quoted identifiers and PDO placeholders; executed with `prepare` + `execute`.
4. Migrations read `.sql` files from a consumer directory and `exec` them.

## Trust boundaries

| Boundary | Mechanism | Location |
| --- | --- | --- |
| Table / PK / join table names | Regex `^[a-zA-Z_][a-zA-Z0-9_]*$` | `validateIdentifier()` (constructor, `addJoin`) |
| Column names (join keys, select fields, conditions) | Same regex | `validateFieldName()` |
| Assigned field names | Must exist in loaded schema | `__set()`, `fromArray()` |
| Mass assignment | Opt-in allowlist | `fillOnly()` |
| Values | PDO placeholders only (`:field`, `?`) | all CRUD methods |
| ORDER BY | Column regex per dotted segment + direction allowlist | `sanitizeOrderBy()` |
| LIMIT / OFFSET | `int`-typed params interpolated | `findBy`, `findAll`, `paginate` |
| Unscoped delete | Empty WHERE rejected | `deleteWhere()` |
| Migration file names | `^\d+_[a-zA-Z0-9_]+\.sql$` (also for names read back from DB in rollback) | `migrateDir()`, `rollbackDir()`, `migrationStatus()` |

**Not guarded**: the `$where` string of `findBy`, `findAll`, `paginate`, `deleteWhere` is raw SQL — callers must never interpolate input into it. Migration SQL is trusted and split naively on `;`.

## Config

None in the library. Error mode, charset, and timeouts are set by the consumer on its `\PDO` (tests: `PDO::ERRMODE_EXCEPTION`).
