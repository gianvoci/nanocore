> Read this when you need to find where something lives or understand what a file does.

# File Structure

```
nanocore/
├── src/
│   ├── NanoCore.php          # Main framework class — routing, config, utilities
│   └── NanoORM.php           # Lightweight ORM — CRUD, joins, schema discovery
├── composer.json             # Package definition (gianvoci/nanocore, PSR-4)
├── .env                      # Runtime config (dot-notation .env, gitignored, auto-created)
├── .env.example              # Config template (tracked in git, safe defaults)
├── CLAUDE.md                 # Project overview and doc index
├── LICENSE                   # GPL-3.0-or-later license
├── README.md                 # Package documentation — shown on Packagist and GitHub
├── .gitignore                # Ignores .env, .vscode, /vendor/, *.log, composer.lock, OS files
├── .gitattributes            # export-ignore for distribution (excludes docs, tests, dev files)
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
        └── JoinTest.php             # Join-specific tests
```

## Key Files

| File | Role |
| --- | --- |
| `src/NanoCore.php` | Core class with routing engine (pattern-based), config manager (.env format, dot-notation, in-memory cache), cURL helper with retry, linear backoff, CURLOPT passthrough, streaming support, and request logging, public SSRF validation methods, request body parser with size limit, HTML template renderer, detached process executor. Sets custom error/exception handlers on construction (no file/line in responses). |
| `src/NanoORM.php` | ORM class accepting PDO + table name + optional primary key. Validates identifiers on construction. Auto-discovers schema via `DESCRIBE` (MySQL) or `PRAGMA table_info` (SQLite). Provides magic getters/setters, fill/toArray, findById/findBy/findAll (all return cloned instances), save (insert or update), delete/deleteWhere, and JOIN support via addJoin/fetchWithJoins. All identifiers validated against `/^[a-zA-Z_][a-zA-Z0-9_]*$/`. ORDER BY sanitized. SQL injection prevention via identifier validation + PDO prepared statements. |
| `.env` | Runtime config file. Auto-created as empty if missing. Gitignored (contains sensitive data). Accessed via `configGet('SECTION.KEY')` and `configSet('SECTION.KEY', value)`. |
| `.env.example` | Config template with commented-out examples. Tracked in git so developers know what settings are available. |
| `.gitattributes` | Marks dev files for exclusion from distribution archives (`export-ignore`). Excludes `/docs`, `/tests`, `CLAUDE.md`, `.env`, log files, and editor config. Works alongside `archive.exclude` in composer.json. |
| `tests/runAllTests.php` | Test orchestrator. Uses glob to discover all `cases/*.php` files, runs each in a separate process, and reports pass/fail summary. |
| `tests/TestHelpers.php` | Shared test infrastructure: `assertEquals`, `assertTrue` assertions; `createMemoryPDO`, `prepareSchema`, `prepareJoinSchema` database helpers; `runRequest` route helper; `tmpConfigPath` and `createTempHtml` file helpers; `runTests()` runner function. |
| `tests/cases/*.php` | Individual test files. Each defines a `$tests[]` array of anonymous functions and calls `runTests($tests)` at the end. Standalone runners — no PHPUnit dependency. |
