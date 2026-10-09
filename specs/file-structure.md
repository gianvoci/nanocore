> Read this when you need to locate a file or understand what a directory contains.

# File Structure

| Path | Description |
| --- | --- |
| `src/NanoCore.php` | `NanoCore\NanoCore`: error handlers, routing/dispatch, responses, middleware, validation, events, CLI, sessions, config manager, SSRF guard, `curlRequest`, body parser, `renderHtml`, magic storage, `execDetach` |
| `src/NanoORM.php` | `NanoCore\NanoORM`: schema discovery, CRUD, joins, pagination, transactions, static SQL migration runner |
| `tests/runAllTests.php` | Orchestrator: runs every `tests/cases/*.php` in a separate `php` process, exit 1 on any failure |
| `tests/TestHelpers.php` | Assertions (`assertEquals`, `assertTrue`, `assertThrows`), PDO/route/config/temp-file/test-server helpers, `runTests()` |
| `tests/cases/*Test.php` | One standalone runner per feature (`Routes`, `RouteEdgeCases`, `Config`, `ErrorHandling`, `Utilities`, `CurlRequest`, `ORM`, `ORMEdgeCases`, `Join`, `NewFeatures`, `SecurityHardening`) |
| `tests/app.json` | Empty JSON fixture (`{}`) |
| `vendor/` | Composer autoloader only (no packages); git-ignored |
| `composer.json` | Package metadata, PSR-4 map, `php >=8.5`, archive excludes |
| `.env.example` | Sample config keys (`DB.*`, `PHP.INI.*`) |
| `.gitattributes` | `export-ignore` for `specs/`, `tests/`, `AGENTS.md`, `CLAUDE.md`, `.env`, `nanocore.log` |
| `README.md` | Consumer-facing documentation of the full public API |
| `CHANGELOG.md` | Release history (newest first) |
| `MIGRATION-to-{version}.md` | Upgrade guide per release (English) |
| `AGENTS.md`, `specs/` | Agent documentation (this tree) |

## Runtime artifacts (not in repo)

| File | Created by | Location |
| --- | --- | --- |
| `.env` | `NanoCore` constructor if missing (empty) | Path passed to constructor (default cwd) |
| `nc_cfg_*` | `saveConfig()` temp file, renamed over `.env` | `.env` directory |
| `nanocore.log` | curl log, listener errors, hidden 5xx, `execDetach` output | `.env` directory |

See [module-index.md](module-index.md) for risk tiers.
