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
    ├── runAllTests.php           # Main test runner — executes all test files
    ├── NanoCoreRoutesTest.php    # Routing tests (simple GET, path params, wildcards)
    ├── NanoCoreErrorHandlingTest.php  # Error/exception handler tests (ErrorException conversion, JSON output, HTTP status codes)
    ├── NanoCoreConfigTest.php    # Config tests (dot-notation, caching, auto-create, JSON encoding, custom file path)
    ├── NanoCoreRouteEdgeCasesTest.php # Route edge cases (path normalization, param sanitization, wildcard, path/query param collision)
    ├── NanoCoreUtilitiesTest.php # Utility tests (getBodyRequest, renderHtml, magic properties, execDetach)
    ├── NanoORMTest.php           # ORM tests (CRUD, deleteWhere, joins with SQLite in-memory)
    └── NanoORMEdgeCasesTest.php  # ORM edge cases (deleteWhere empty, update without PK, findBy ignores JOINs, fetchWithJoins conditions, clear clears joins, findBy with limit)
```

## Key Files

| File | Role |
| --- | --- |
| `NanoCore.php` | Core class with routing engine (pattern-based), config manager (JSON dot-notation, in-memory cache), cURL helper with retry and linear backoff, request body parser with size limit, HTML template renderer, detached process executor. Sets custom error/exception handlers on construction (no file/line in responses). |
| `NanoORM.php` | ORM class accepting PDO + table name + optional primary key. Validates identifiers on construction. Auto-discovers schema via `DESCRIBE` (MySQL) or `PRAGMA table_info` (SQLite). Provides magic getters/setters, fill/toArray, findById/findBy/findAll (all return cloned instances), save (insert or update), delete/deleteWhere, and JOIN support via addJoin/fetchWithJoins. Field names in conditions and join parameters are validated. ORDER BY is sanitized. |
| `app.json` | Persisted config file. Auto-created as `{}` if missing. Accessed via `configGet('SECTION.KEY')` and `configSet('SECTION.KEY', value)`. The constructor writes `CORE.ROOT` on every instantiation. |
| `tests/runAllTests.php` | Main test runner that executes all 7 individual test files in sequence. Prints a summary (X/7 passed, Y failed). Exit code 0 if all pass, 1 if any fail. |
| `tests/NanoCoreRoutesTest.php` | Standalone test runner (no PHPUnit dependency). Sets `$_SERVER` globals, calls `run()`, asserts response. Tests: simple GET, path params, wildcards, POST method, query param merging, 404 handling, config get/set, trailing slash normalization, magic properties (9 tests). |
| `tests/NanoCoreErrorHandlingTest.php` | Tests error/exception handler behavior: ErrorException conversion, JSON error output format, HTTP status codes for different error types, custom handler restoration. |
| `tests/NanoCoreConfigTest.php` | Tests config management: dot-notation get/set, in-memory caching, auto-create missing file, JSON encoding/decoding, custom config file path. |
| `tests/NanoCoreRouteEdgeCasesTest.php` | Tests route edge cases: path normalization, parameter sanitization, wildcard matching, path/query param name collision, duplicate route handling. |
| `tests/NanoCoreUtilitiesTest.php` | Tests utility methods: getBodyRequest (JSON/form parsing), renderHtml (template rendering), magic properties (__get/__set), execDetach (detached process execution). |
| `tests/NanoORMTest.php` | Standalone test runner using SQLite `:memory:`. Tests: full CRUD lifecycle, deleteWhere, multi-table JOINs, cloned instances, custom primary key, identifier validation, ORDER BY sanitization, join parameter validation, findAll conditions/order/limit, single delete, clear state, toArray and magic methods (15 tests). |
| `tests/NanoORMEdgeCasesTest.php` | Tests ORM edge cases: deleteWhere with empty conditions, update without primary key set, findBy ignoring JOINs, fetchWithJoins with conditions, clear resets JOIN state, findBy with limit parameter. |
