> Read this when writing or modifying PHP code in this library.

# Backend Conventions

## General

- Always use long `<?php` opening tag (not `<?`) — ensures loading on default PHP installs.
- Namespace is `NanoCore` — all classes live in the project root under this namespace.
- PHP >=8.5 minimum — use named arguments, match expressions, nullsafe operator, etc. where appropriate.
- No runtime dependencies beyond PHP itself (no external libraries).

## Error Handling

- The constructor registers a custom error handler that converts all PHP errors to `ErrorException`.
- A global exception handler emits JSON: `{message, code}` with HTTP status from the exception code (clamped to 100–599, defaults to 500). File and line are intentionally excluded for security.
- `run()` catches exceptions and emits JSON: `{error, code}` with the exception code as HTTP status. Falls back to 500 if code is outside 100–599.
- Controllers using this library should throw exceptions with HTTP codes: `throw new \Exception('Not found', 404)`.
- All JSON encoding uses `JSON_THROW_ON_ERROR` to prevent silent encoding failures.

## Config System

- Config lives in `.env` (or custom file passed to constructor). The file path is validated — must be a `.env` file within the project directory. A `.env.local` file can override values from `.env` (loaded second, values overwrite).
- Dot-notation keys: `configGet('PHP.INI')` returns the `PHP.INI` nested object.
- `configSet` creates nested structure automatically. Protected keys (`PHP.INI`, `CORE`) cannot be modified via `configSet`.
- `PHP.INI` is a special key — the constructor iterates it and calls `ini_set()` only for allowed directives (see `ALLOWED_INI_SETTINGS` constant). Unknown directives are silently skipped.

## Config Caching

- Config values are cached in memory after first read. Subsequent `configGet` calls return from cache.
- `configSet` updates the cache only after a successful file write.
- `saveConfig` uses atomic writes (temp file + rename) to prevent corruption. Flattens nested arrays to dot-notation `.env` format.
- `.env.local` overrides `.env` — loaded second, values overwrite base config.
- The cache is an in-memory property — it does not persist across requests.

## Type Patterns

- `addRoute` accepts mixed method/path and casts to string internally.
- `curlRequest` is static — called as `NanoCore::curlRequest($url, $options)`.
- `curlRequest` validates URLs before making requests: only `http`/`https` schemes allowed, private/restricted IPs are blocked (SSRF protection).
- `curlRequest` retries up to 5 times with linear backoff (100ms, 200ms, 300ms...) and resets the curl handle between attempts. On failure, throws a generic "External request failed" exception (no internal details leaked).
- Magic properties via `__get`/`__set` on NanoCore instance: `$app->body` reads request body via `getBodyRequest()` with a 10MB default size limit (throws on overflow). The limit is customizable only via direct `getBodyRequest($maxBytes)` calls. An optional `$validateContentType` parameter (defaults to `false`) can enforce `application/json` Content-Type.
- `renderHtml` loads a template file and does string replacement from a data array. The path is validated to prevent traversal outside the project root. String values in `$data` are HTML-escaped by default (`$escape = true`); pass `false` to opt out.
- `execDetach` runs a shell command in the background, logging output to `nanocore.log`. Accepts a string (backward compat) or an array of `[command, arg, arg, ...]` for proper argument escaping. `ob_flush()` is guarded — skips when no output buffer is active, preventing warnings.

## Security

Built-in protections across the library:

| Protection | Where | How |
| --- | --- | --- |
| SSRF | `curlRequest` | Only `http`/`https` schemes; blocks private/restricted IPs; resolves DNS to check resulting IPs |
| Path traversal | `renderHtml` | Template path must be within project root |
| XSS | `renderHtml` | String values are HTML-escaped by default (`$escape = true`; opt-out via `false`) |
| SQL injection | NanoORM | Identifier validation (`/^[a-zA-Z_][a-zA-Z0-9_]*$/`); parameterized queries via PDO prepared statements |
| Config tampering | `configSet` | Protected keys (`PHP.INI`, `CORE`) throw on write; atomic file writes (temp + rename) |
| Command injection | `execDetach` | Array mode escapes each argument with `escapeshellarg()` |
| Arbitrary `ini_set` | Constructor | Only directives in `ALLOWED_INI_SETTINGS` are applied; unknown ones are silently skipped |

## Naming

- Public methods: camelCase (`addRoute`, `configGet`, `getBodyRequest`).
- Private methods: camelCase (`normalizeRoutePath`, `removeBasePathPrefix`).
- No strict convention on method parameter ordering, but handlers receive `($app, $params)`.

## Route Handler Signature

Handlers registered via `addRoute` receive exactly two arguments:

```php
function (NanoCore $app, array $params): mixed
```

`$params` is a merge of query string parameters + extracted path parameters. Path params override query params on collision.
