> Read this when you need to find where something lives or understand what a file does.

# File Structure

```
nanocore/
├── NanoCore.php          # Main framework class — routing, config, utilities
├── NanoORM.php           # Lightweight ORM — CRUD, joins, schema discovery
├── composer.json         # Package definition (gianvoci/nanocore, PSR-4)
├── app.json              # Runtime config (dot-notation JSON, created if missing)
├── CLAUDE.md             # Project overview and doc index
├── .gitignore            # Ignores config.json, .vscode, .prettierrc, /vendor/
└── tests/
    ├── NanoCoreRoutesTest.php  # Routing tests (simple GET, path params, wildcards)
    └── NanoORMTest.php         # ORM tests (CRUD, deleteWhere, joins with SQLite in-memory)
```

## Key Files

| File | Role |
| --- | --- |
| `NanoCore.php` | Core class with routing engine (pattern-based), config manager (JSON dot-notation, in-memory cache), cURL helper with retry and linear backoff, request body parser with size limit, HTML template renderer, detached process executor. Sets custom error/exception handlers on construction (no file/line in responses). |
| `NanoORM.php` | ORM class accepting PDO + table name + optional primary key. Validates identifiers on construction. Auto-discovers schema via `DESCRIBE` (MySQL) or `PRAGMA table_info` (SQLite). Provides magic getters/setters, fill/toArray, findById/findBy/findAll (all return cloned instances), save (insert or update), delete/deleteWhere, and JOIN support via addJoin/fetchWithJoins. Field names in conditions and join parameters are validated. ORDER BY is sanitized. |
| `app.json` | Persisted config file. Auto-created as `{}` if missing. Accessed via `configGet('SECTION.KEY')` and `configSet('SECTION.KEY', value)`. The constructor writes `CORE.ROOT` on every instantiation. |
| `tests/NanoCoreRoutesTest.php` | Standalone test runner (no PHPUnit dependency). Sets `$_SERVER` globals, calls `run()`, asserts response. Tests: simple route, `@id` parameter, `@*` wildcard. |
| `tests/NanoORMTest.php` | Standalone test runner using SQLite `:memory:`. Tests: full CRUD lifecycle, deleteWhere batch delete, multi-table JOIN with aliased fields. |
