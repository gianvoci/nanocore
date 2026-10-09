> Read this when you change how requests, bodies, CLI args, or error responses are handled.

# Inbound Request (HTTP + CLI)

## Implementing files

| File | Key symbols |
| --- | --- |
| `src/NanoCore.php` | `run()`, `normalizeRoutePath()`, `removeBasePathPrefix()`, `getBodyRequest()`, `__get('body')`, `redirect()`, `sendResponse()`, `sendJsonError()`, `setErrorHandlers()`, `runCli()`, `addCommand()` |

## Flow

1. Method from `REQUEST_METHOD` (default `GET`); URI from `REQUEST_URI`, or `argv[1]` in CLI.
2. Path parsed with `parse_url`, normalized, base path stripped; query string merged into `$params` via `parse_str`.
3. Route match → middleware chain → handler ([routing/spec.md](../routing/spec.md)).
4. Handler may read the body via `$app->body` / `getBodyRequest()` (cached per instance).
5. Descriptor sent, or exception converted to JSON error.
6. CLI with commands: `argv[1]` = command name, `argv[2..]` = raw args to the handler.

## Trust boundaries

| Boundary | Mechanism | Location |
| --- | --- | --- |
| Body size cap | Reads `maxBytes + 1` from `php://input`, throws if larger (code 0 → 500) | `getBodyRequest()` |
| Body Content-Type | Optional `validateContentType` requires `application/json`; **empty Content-Type bypasses** | `getBodyRequest()` |
| Body decoding | JSON → array (falls back to raw string), urlencoded → `parse_str`, multipart → `$_POST` | `getBodyRequest()` |
| Error disclosure | JSON `{error, code}` only, no paths/traces; 5xx message hidden when `CORE.EXPOSE_ERRORS=false` | `sendJsonError()` |
| Log injection (hidden 5xx) | CR/LF replaced with space before logging | `logUnhandledException()` |
| Header injection | CR/LF stripped from redirect URL | `redirect()` |
| Status sanity | Status outside 100–599 → 500 | `sendJsonError()`, `sendResponse()` |
| Output encoding | `JSON_THROW_ON_ERROR` on every JSON output | `sendJsonError()`, `sendResponse()` |
| Throwable coverage | `run()` catches `\Throwable`; global handler covers the rest | `run()`, `setErrorHandlers()` |
| CLI command names | `^[a-zA-Z0-9:_-]+$` at registration; args are passed unvalidated | `addCommand()` |

Not guarded (caller responsibility): open redirects (any URL accepted), path/query param content, CSRF, auth.

## Config

| Setting | Default | Where |
| --- | --- | --- |
| `getBodyRequest` `$maxBytes` | `10_485_760` (10 MiB) | method param |
| `CORE.EXPOSE_ERRORS` | `true` | `.env` |
| Base path | `dirname($_SERVER['SCRIPT_NAME'])`, `''` in CLI | `getBasePath()` |
