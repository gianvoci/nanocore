# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/), and this project adheres to [Semantic Versioning](https://semver.org/).

## [2026-05-28] - docs: migrate project overview from CLAUDE.md to AGENTS.md

- Moved project overview and documentation index from CLAUDE.md to new AGENTS.md file
- CLAUDE.md now references AGENTS.md

---

## [2026-05-28] - refactor(core): remove deprecated curl_close() calls for PHP 8.5

- Removed `curl_close()` calls from `curlRequest()` — no-op since PHP 8.0, emits deprecation warnings in PHP 8.5

---

## [2.2.0] - 2026-05-26

### Added

- **Middleware pipeline** — `addMiddleware(callable $middleware): void` registers middleware; chain wraps handlers in reverse registration order; first registered = first executed; signature `function(NanoCore $app, array $params, callable $next): mixed`
- **Request validation** — `validate(array $rules, array $data): array` validates input against rules (`required`, `string`, `int`, `float`, `bool`, `email`, `min`, `max`, `regex`, `in`); returns validated data or throws `InvalidArgumentException` with all errors
- **Event system** — `on(string $event, callable $handler): void` registers event listeners; `emit(string $event, array $data = []): void` dispatches to all listeners; built-in events: `request.received`, `response.sent`, `error.occurred`
- **CLI command runner** — `addCommand(string $name, callable $handler): void` registers CLI commands; `runCommands(): void` dispatches based on `$_SERVER['argv'][1]`; auto `--help` flag
- **Session management** — `sessionStart(): void`, `sessionGet(string $key): mixed`, `sessionSet(string $key, mixed $value): void`, `sessionDestroy(): void` with `SESSION.*` config keys
- **Response helper methods** — `json(mixed $data, int $status = 200): array`, `html(string $body, int $status = 200): array`, `redirect(string $url, int $status = 302): array` — return `__nc_response` descriptor arrays
- **Database migrations** — `migrate(string $dir): void` applies SQL files sorted alphabetically; `migrationStatus(string $dir): array` reports applied/pending; rollback files required (`_down.sql` suffix)
- **Pagination** — `paginate(string $table, int $page = 1, int $perPage = 20, array $where = []): array` returns `['data'=>[], 'total'=>int, 'page'=>int, 'per_page'=>int, 'last_page'=>int]`
- **Test server helpers** — `createTestServer(): array` returns `['url','process','pipes']`; `stopTestServer(array $server): void` cleans up; used in CurlRequestTest SSRF validation tests
- **SSRF hardening** — IPv6 bracket stripping, `CURLOPT_FOLLOWLOCATION` forced `false` after option merge (no caller override), DNS rebinding TOCTOU documented as known limitation

### Changed

- `run()` and `emit()` now catch `\Throwable` (consistency with `transaction()`)
- `docs/` directory renamed to `specs/`

## [2.1.4] - 2026-05-22

### Removed

- response string truncation in curlRequest logging

## [2.1.3] - 2026-05-22

### Changed

- Improved `.env` validation error message to include the received filename

## [2.1.2] - 2026-05-22

### Changed

- Removed `.env.local` config override mechanism — only the config file passed to the constructor is loaded now (no automatic `.env.local` overlay)

### Removed

- `$localConfigFile` property and related `.env.local` loading logic from NanoCore constructor and `loadConfig()` method
- `.env.local` override test from ConfigTest
- `.env.local` documentation sections from README, config-management.md, file-structure.md, conventions-backend.md, conventions-testing.md, and CLAUDE.md

## [2.1.1] - 2026-05-21

### Added

- `with_info` option in `curlRequest()` — when `true`, returns `['body'=>mixed,'status'=>int,'content_type'=>string|null]` instead of just the body

## [2.1.0] - 2026-05-21

### Added

- Public SSRF validation methods: `validateUrlNotRestricted()` and `validateIpNotRestricted()` (previously private)
- `raw` option in `curlRequest()` to skip JSON decoding and return the raw response string
- CURLOPT passthrough in `curlRequest()` — any `CURLOPT_*` constant can be passed in `$options` to override default curl settings
- Streaming support via `CURLOPT_WRITEFUNCTION` — method returns `true` on success, body consumed by the callback
- Request logging to `nanocore.log` — each `curlRequest` call logs method, URL, status code, duration, params, and response (truncated at 1024 chars)

### Changed

- Renamed `validateUrl()` to `validateUrlNotRestricted()` for clarity (was private, no breaking change)
- Fixed method comparison bug: `'method' => 'get'` now correctly appends params as query string instead of POST body

### Fixed

- Used `array_replace` instead of `array_merge` for CURLOPT merging — `array_merge` reindexes integer keys, destroying CURLOPT constants
- Added `curl_close()` after request completion to prevent resource leaks (later removed in PHP 8.5 deprecation cleanup)
- Used `dirname($this->configFile)` for log path instead of URL basePath

## [2.0.0] - 2026-04-18

### Changed

- Improved inline comments and documentation to reflect recent code quality improvements

### Fixed

- Added type hints to `__set` magic method and other methods across NanoCore and NanoORM
- Added `JSON_THROW_ON_ERROR` flag to all `json_encode` calls for proper error handling
- Added output buffer safety guard (`ob_flush`) before `ob_end_clean()`
- Backtick-quoted field names in NanoORM `INSERT`/`UPDATE` statements to avoid reserved word conflicts
- Updated error tests to use `try`/`finally` for reliable handler cleanup

## [0.4.0] - 2026-04-17

### Added

- `.env` configuration format with `.env.local` override support, quoted values, inline comments, variable interpolation (`${VAR}`), and `export` prefix stripping
- `.env.example` file with documented configuration keys
- README with comprehensive usage documentation
- LICENSE file (GPL-3.0-or-later)
- `.gitattributes` for Packagist distribution
- Pre-commit hook infrastructure and test runner improvements (later removed in cleanup)

### Changed

- Switched config system from `app.json` to `.env` format
- Moved all source files to `src/` directory with PSR-4 autoloading
- Restructured test suite into `tests/cases/` with shared `TestHelpers.php`
- Renamed test files to consistent naming convention
- Updated all documentation for PHP 8.5 compatibility and `src/` structure
- Added PHP 8.5 compatibility: removed deprecated `curl_close()`/`curl_error()`, replaced `strpos` with `str_contains`, added type declarations to 13 methods
- Refactored NanoCore.php and NanoORM.php for lower cognitive load (extracted helpers, reordered methods, removed dead code)
- Consolidated duplicated test helper functions into shared `TestHelpers.php`

### Removed

- Removed `app.json` configuration file
- Removed version bump scripts and pre-commit hook infrastructure

## [0.3.0] - 2026-04-16

### Added

- Comprehensive test suite covering routing, ORM, config, error handling, utilities, and edge cases
- Documentation structure with 9 files covering file structure, commands, conventions, and rules
- Config caching to avoid repeated disk I/O on every `configGet()` call
- SQL injection validation for table names, field names, and `ORDER BY` clauses

### Changed

- `findById()` now clones before hydrating to prevent mutation of the original instance
- `update()` and `delete()` use dynamic `primaryKey` for bound parameters instead of hardcoded `:id`
- Added retry with exponential backoff in `curlRequest()`
- Exception handler no longer leaks file paths and line numbers

### Fixed

- Prevented SQL injection in `findAll()` `ORDER BY` parameter via `sanitizeOrderBy()`
- Fixed variable shadowing in `configSet()` method
- Fixed `json_decode` ambiguity by adding `true` flag and `json_last_error()` check
- Added 10MB size limit to `getBodyRequest()` to prevent memory exhaustion

### Security

- SSRF protection in `curlRequest()` with URL validation blocking private/restricted IPs
- Path traversal prevention in `renderHtml()` with path validation
- Command injection prevention in `execDetach()` via array-based argument escaping
- XSS prevention in HTML rendering with auto-escaping
- `configSet()` protected keys and atomic file writes
- `setPHPConfig()` allowlist for `ini_set` calls
- DNS rebinding bypass protection in SSRF validation

## [0.2.0] - 2026-02-11

### Added

- NanoORM lightweight ORM class with CRUD operations, JOIN support, and magic property accessors (`__get`, `__set`, `__isset`, `__unset`)
- ORM documentation with capabilities and usage examples

### Changed

- Standardized NanoCore helper method naming conventions
- Switched Composer autoload to PSR-4 namespace mapping

## [0.1.0] - 2025-12-07

### Changed

- Routing fixes
- Small refactor for PHP 8.4 compatibility

## [*initial development*] - 2024-05-24

### Added

- Core NanoCore library with routing, configuration, HTTP client (`CurlRequest`), local storage manager, and `ExecDetach` for background processes
- Initial README
