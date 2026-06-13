> Read this when you need to find where something lives or understand what a file does.

# File Structure

```
nanocore/
├── src/
│   ├── NanoCore.php          # Main framework class — routing, config, utilities
│   └── NanoORM.php           # Lightweight ORM — CRUD, joins, schema discovery
├── composer.json             # Package definition (gianvoci/nanocore, PSR-4, archive.exclude)
├── .env                      # Runtime config (dot-notation .env, gitignored, auto-created)
├── .env.example              # Config template (tracked in git, safe defaults)
├── LICENSE                   # GPL-3.0-or-later license
├── README.md                 # Package documentation — shown on Packagist and GitHub
├── .gitignore                # Ignores .env, .env.local, config.json, .vscode, /vendor/, *.log, composer.lock, OS files
├── .gitattributes            # export-ignore for distribution (excludes specs, tests, dev files)
└── tests/
    ├── runAllTests.php       # Test orchestrator — discovers and runs all cases/*.php
    ├── TestHelpers.php       # Shared test infrastructure (assertions, helpers, runner)
    └── cases/
        ├── RoutesTest.php           # Routing tests
        ├── ErrorHandlingTest.php    # Error/exception handler tests
        ├── ConfigTest.php           # Config tests
        ├── RouteEdgeCasesTest.php   # Route edge cases
        ├── UtilitiesTest.php        # Utility tests
        ├── ORMTest.php              # ORM tests
        ├── ORMEdgeCasesTest.php     # ORM edge cases
        ├── JoinTest.php             # Join-specific tests
        ├── NewFeaturesTest.php      # Tests for v3 features (findBy/findAll arrays, body cache, middleware route/method, require(), fromArray())
        └── CurlRequestTest.php      # cURL SSRF/retry/integration tests
```

## Key Files

| File | Role |
| --- | --- |
| `src/NanoCore.php` | Core class with routing engine (pattern-based), config manager (.env format, dot-notation, in-memory cache), cURL helper with 5 total attempts (initial + 4 retries), linear backoff, CURLOPT passthrough, streaming support, and request logging, public SSRF validation methods, request body parser with size limit and cache + Content-Type auto-detect (`application/json` → `json_decode`, `application/x-www-form-urlencoded` → `parse_str`, `multipart/form-data` → `$_POST`), HTML response via `html($content)` (content string, not template), detached process executor (`execDetach` — Windows: `escapeshellarg` per array element, `> NUL 2> NUL &`; non-Windows: `shell_exec`; `flush()` unguarded). Response methods (`json()`, `html()`, `redirect()`) return `__nc_response` descriptors with `body` and `headers` keys. Middleware pipeline (`addMiddleware()`, reverse-order wrapping, passes `$route` and `$method` to middleware callbacks). `require(string $key): mixed` — fail-fast property access, throws `RuntimeException` if not configured. Input validation (`validate()`, `check()`, 10 rules: `required`, `integer`, `numeric`, `string`, `min`, `max`, `email`, `url`, `regex`, `in`; `parseRule` returns `['name' => ..., 'param' => ...]`). Event system (`on()`, `emit()`, 4 built-in events). CLI command dispatch (`addCommand()` validates names against `/^[a-zA-Z0-9:_-]+$/`; `run()` delegates to `runCli()` only when `php_sapi_name() === 'cli' && !empty($this->commands)`). Session management (`sessionStart()` reads `SESSION.COOKIE_HTTPONLY`, `SESSION.COOKIE_SECURE`, `SESSION.USE_STRICT_MODE`; `sessionGet()`, `sessionSet()`, `sessionDestroy()`). `__get` supports `$app->body` (request body, cached), `$app->cli` (bool), and generic storage access. `require()` for fail-fast property access. Sets custom error/exception handlers on construction (no file/line in responses). |
| `src/NanoORM.php` | ORM class accepting PDO + table name + optional primary key. Validates identifiers on construction. Auto-discovers schema via `DESCRIBE` (MySQL) or `PRAGMA table_info` (SQLite). Provides magic getters/setters, fill/toArray, findById (returns `NanoORM|null` for mutation), findBy/findAll (return `array<array>` — associative arrays, not ORM instances), save (insert or update), delete/deleteWhere, and JOIN support via addJoin/fetchWithJoins. `fromArray(array $row): self` — public method for hydrating from known data without DB. Pagination via `paginate()` with COUNT+SELECT, offset/limit, last_page. Transactions via `beginTransaction()`, `commit()`, `rollback()`, `transaction(callable)`. Migrations via static `migrateDir()` (returns newly applied file names), `rollbackDir($steps)` (uses `rollback/` subdirectory, returns rolled-back file names), `migrationStatus()` (returns `['applied' => [...], 'pending' => [...]]`). Migration file naming: `/^\d+_[a-zA-Z0-9_]+\.sql$/`. `executeSqlFile` takes SQL content string (not file path); SQLite: executes statements individually without transaction; non-SQLite: wraps in transaction. All identifiers validated against `/^[a-zA-Z_][a-zA-Z0-9_]*$/`. ORDER BY sanitized. SQL injection prevention via identifier validation + PDO prepared statements. |
| `composer.json` | Package definition (gianvoci/nanocore, PSR-4). Includes `archive.exclude` excluding `/specs`, `/tests`, `nanocore.log`, `.env`, `app.json`, `CLAUDE.md`, `.gitignore` from distribution archives. |
| `.env` | Runtime config file. Auto-created as empty if missing. Gitignored (contains sensitive data). Accessed via `configGet('SECTION.KEY')` and `configSet('SECTION.KEY', value)`. |
| `.env.example` | Config template with commented-out examples. Tracked in git so developers know what settings are available. |
| `.gitattributes` | Marks dev files for exclusion from distribution archives (`export-ignore`). Excludes `/specs`, `/tests`, `/CLAUDE.md`, `.env`, `app.json`, `nanocore.log`, `.vscode`, `.prettierrc`. Works alongside `archive.exclude` in composer.json. |
| `.gitignore` | Ignores .env, .env.local, config.json, .vscode, /vendor/, *.log, composer.lock, OS files (.DS_Store, Thumbs.db). |
| `tests/runAllTests.php` | Test orchestrator. Uses glob to discover all `cases/*.php` files, runs each in a separate process, and reports pass/fail summary. |
| `tests/TestHelpers.php` | Shared test infrastructure: `assertEquals`, `assertTrue`, `assertThrows` assertions; `createMemoryPDO`, `prepareSchema` database helpers; `runRequest` route helper; `tmpConfigPath` and `createTempHtml` file helpers; `createTestServer()` and `stopTestServer()` for cURL integration tests; `runTests()` runner function. |
| `tests/cases/*.php` | Individual test files. Each defines a `$tests[]` array of anonymous functions and calls `runTests($tests)` at the end. Standalone runners — no PHPUnit dependency. |