# NanoCore

Zero-dependency PHP micro-framework library (`gianvoci/nanocore`): routing, `.env` config manager, validation, events, CLI, sessions, SSRF-safe HTTP client, and a micro ORM (`NanoORM`). Consumed by other projects via Composer.

## Tech Stack

- PHP `>=8.5` (uses `array_any`), `declare(strict_types=1)` everywhere
- Extensions: PDO (MySQL or SQLite), curl (for `curlRequest`)
- Composer PSR-4: `NanoCore\` → `src/`; no runtime or dev dependencies
- Tests: custom standalone runners (no PHPUnit)

## Architecture

- Two classes, no other source: `src/NanoCore.php` (app/router/config/utilities, ~1800 lines) and `src/NanoORM.php` (active-record ORM + SQL migrations)
- Entry point for consumers: `new NanoCore('.env')` → `addRoute()` / `addMiddleware()` / `addCommand()` → `run()`
- Handlers return response descriptors (`json()`, `html()`, `redirect()`); errors are exceptions whose code is the HTTP status
- Full map: [specs/file-structure.md](specs/file-structure.md), criticality: [specs/module-index.md](specs/module-index.md)

## Commands

```bash
php -l src/NanoCore.php && php -l src/NanoORM.php   # syntax check after every change
php tests/cases/ConfigTest.php                       # single test file
php tests/runAllTests.php                            # full suite
```

## Code Style

- 4-space indent, braces on new line for classes/methods, `camelCase` methods, `UPPER_SNAKE` private constants
- Typed params/returns on every method; PHPDoc on public methods
- Sections separated by `# SECTION` comment banners inside the class
- Comments explain *why* (security reason, limitation), never *what*

## Coding Rules

- The constructor installs global error/exception handlers: every PHP warning becomes `ErrorException` (except `@`-suppressed)
- HTTP status = exception code (`throw new \Exception('Not found', 404)`); codes outside 100–599 become 500
- Never interpolate values into SQL; identifiers must pass `^[a-zA-Z_][a-zA-Z0-9_]*$` and be backtick-quoted
- Every outbound URL goes through `validateUrlNotRestricted()`; `CURLOPT_FOLLOWLOCATION` stays forced `false`; the `CURLOPT_RESOLVE` SSRF pin cannot be overridden by callers
- Anything written to `nanocore.log` must be masked/stripped (credentials, CR/LF)
- Public signatures are a contract for consumers: adding a parameter to an overridable method (e.g. `NanoORM::fill`) is breaking — add a new method instead

## Constraints / What to avoid

- Do not add Composer dependencies
- Do not relax trust boundaries listed in [specs/integrations/index.md](specs/integrations/index.md)
- Do not let `configSet` touch `PHP.INI`/`CORE`, or extend `ALLOWED_INI_SETTINGS` without a security reason
- Do not change JSON error shape `{"error", "code"}` or `min`/`max` semantics outside a major release
- Do not use `echo`/`print` in tests; only the 3 assertions in `tests/TestHelpers.php` exist

## Workflow

1. Read the domain spec for the area you touch (`specs/<domain>/spec.md`)
2. Change code → `php -l` → run the single affected test file → `php tests/runAllTests.php`
3. Add/update tests in `tests/cases/` ([specs/rules/add-test.md](specs/rules/add-test.md))
4. Release steps (version, `MIGRATION-to-{version}.md`, `CHANGELOG.md`, README): [specs/rules/release.md](specs/rules/release.md)

## Self-Maintenance

> When you modify code, update the relevant file in `specs/`.
> When you change public behavior, also update `README.md`, `CHANGELOG.md`, and the `MIGRATION-to-{version}.md` file (English only).

## References

| File | Contents |
| --- | --- |
| @specs/file-structure.md | Directory and file map |
| @specs/commands.md | Syntax check, test, Composer, release commands |
| @specs/module-index.md | Modules with tier (risk) classification |
| @specs/api-routes.md | Public API surface of `NanoCore` and `NanoORM` |
| @specs/conventions-backend.md | PHP coding patterns: handlers, middleware, errors, events, logging |
| @specs/conventions-database.md | NanoORM usage, raw WHERE rules, migrations layout |
| @specs/routing/spec.md | Dispatch, middleware, responses, error JSON contract |
| @specs/validation/spec.md | Validation rule semantics |
| @specs/config/spec.md | `.env` parse/write contract, protected keys |
| @specs/http-client/spec.md | `curlRequest` batch/retry/return contract |
| @specs/orm/spec.md | ORM state model, CRUD and migration contracts |
| @specs/integrations/index.md | External boundaries and trust guards |
| @specs/rules/ | Recipes: add route, error handling, config, ORM, validation rule, test, release |
