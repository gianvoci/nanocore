> Read this when writing or modifying PHP code in this library.

# Backend Conventions

## General

- Always use long `<?php` opening tag (not `<?`) — ensures loading on default PHP installs.
- Namespace is `NanoCore` — all classes live in the project root under this namespace.
- PHP >=8.0 minimum — use named arguments, match expressions, nullsafe operator, etc. where appropriate.
- No runtime dependencies beyond PHP itself (no external libraries).

## Error Handling

- The constructor registers a custom error handler that converts all PHP errors to `ErrorException`.
- A global exception handler emits JSON: `{message, code}` with HTTP 500. File and line are intentionally excluded for security.
- `run()` catches exceptions and emits JSON: `{error, code}` with the exception code as HTTP status. Falls back to 500 if code is outside 100–599.
- Controllers using this library should throw exceptions with HTTP codes: `throw new \Exception('Not found', 404)`.

## Config System

- Config lives in `app.json` (or custom file passed to constructor).
- Dot-notation keys: `configGet('PHP.INI')` returns the `PHP.INI` nested object.
- `configSet` creates nested structure automatically.
- `PHP.INI` is a special key — the constructor iterates it and calls `ini_set()` for each entry.

## Config Caching

- Config values are cached in memory after first read. Subsequent `configGet` calls return from cache.
- `configSet` updates the cache only after a successful file write.
- The cache is an in-memory property — it does not persist across requests.

## Type Patterns

- `addRoute` accepts mixed method/path and casts to string internally.
- `curlRequest` is static — called as `NanoCore::curlRequest($url, $options)`.
- `curlRequest` retries up to 5 times with linear backoff (100ms, 200ms, 300ms...) and resets the curl handle between attempts.
- Magic properties via `__get`/`__set` on NanoCore instance: `$app->body` reads request body via `getBodyRequest()` with a 10MB default size limit (throws on overflow). The limit is customizable only via direct `getBodyRequest($maxBytes)` calls.
- `renderHtml` loads a template file and does string replacement from a data array.
- `execDetach` runs a shell command in the background, logging output to `nanocore.log`.

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
