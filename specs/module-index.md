> Read this when you need to decide how much context to load before touching a module.

# Module Index

Tier 1 = load the full spec before editing; Tier 3–5 = safe with minimal context.

| Module | Path | Tier | Summary | Spec link |
| --- | --- | --- | --- | --- |
| Routing & dispatch | `src/NanoCore.php` (`addRoute`, `run`, `json`/`html`/`redirect`, `sendResponse`, `addMiddleware`) | 1 | Path normalization, `@param`/`@*` patterns, middleware onion, response descriptors | [routing/spec.md](routing/spec.md) |
| Error handling | `src/NanoCore.php` (`setErrorHandlers`, `sendJsonError`, `logUnhandledException`) | 1 | Global handlers, JSON error contract, `CORE.EXPOSE_ERRORS` | [routing/spec.md](routing/spec.md#error-contract) |
| Validation | `src/NanoCore.php` (`validate`, `check`, `applyRule`, `validateMin/Max/In`, `parseRule`) | 1 | 10 rules; accepted-input semantics are a public contract | [validation/spec.md](validation/spec.md) |
| Config manager | `src/NanoCore.php` (`loadConfig`, `parseEnvFile`, `saveConfig`, `configGet`, `configSet`, `setPHPConfig`) | 1 | `.env` parse/rewrite, interpolation, protected keys, ini allowlist | [config/spec.md](config/spec.md) |
| HTTP client | `src/NanoCore.php` (`curlRequest`, `validateUrlNotRestricted`, `validateIpNotRestricted`, `mask*`) | 1 | curl_multi batch, retry, SSRF guard, masked logging | [http-client/spec.md](http-client/spec.md) |
| NanoORM | `src/NanoORM.php` | 1 | Schema discovery, CRUD, joins, pagination, transactions | [orm/spec.md](orm/spec.md) |
| Migrations | `src/NanoORM.php` (`migrateDir`, `rollbackDir`, `migrationStatus`, `executeSqlFile`) | 2 | SQL file runner with `migrations` table | [orm/spec.md](orm/spec.md#migrations) |
| Sessions | `src/NanoCore.php` (`session*`) | 2 | Config-driven cookie flags, SameSite validation, ID regeneration | [integrations/sessions.md](integrations/sessions.md) |
| Request body | `src/NanoCore.php` (`getBodyRequest`, `__get('body')`) | 2 | Size cap, Content-Type decode, per-instance cache | [integrations/inbound-request.md](integrations/inbound-request.md) |
| Template rendering | `src/NanoCore.php` (`renderHtml`) | 2 | Root confinement + `strtr` placeholders with escaping | [integrations/filesystem.md](integrations/filesystem.md) |
| Background process | `src/NanoCore.php` (`execDetach`) | 2 | Shell escaping, OS-specific detach | [integrations/process.md](integrations/process.md) |
| Events | `src/NanoCore.php` (`on`, `emit`) | 2 | Listener errors swallowed and logged | [conventions-backend.md](conventions-backend.md#events) |
| CLI commands | `src/NanoCore.php` (`addCommand`, `runCli`) | 3 | Name regex, `argv[1]` dispatch | [api-routes.md](api-routes.md#cli) |
| Magic storage | `src/NanoCore.php` (`__get`, `__set`, `require`) | 3 | Per-instance key/value store, virtual `body`/`cli` | [api-routes.md](api-routes.md) |
| Test harness | `tests/TestHelpers.php`, `tests/runAllTests.php` | 3 | Assertions, helpers, process-per-file runner | [rules/add-test.md](rules/add-test.md) |
| Test cases | `tests/cases/*.php` | 3 | Feature test runners | [rules/add-test.md](rules/add-test.md) |
| Composer autoload | `vendor/`, `composer.json` | 4 | Generated PSR-4 loader | — |
| Docs | `README.md`, `CHANGELOG.md`, `MIGRATION-*.md` | 5 | Consumer docs and release notes | [rules/release.md](rules/release.md) |
