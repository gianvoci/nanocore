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
- `run()` catches `\Throwable` (not just `\Exception`) and emits JSON: `{error, code}` with the exception code as HTTP status. Falls back to 500 if code is outside 100–599.
- Controllers using this library should throw exceptions with HTTP codes: `throw new \Exception('Not found', 404)`.
- All JSON encoding uses `JSON_THROW_ON_ERROR` to prevent silent encoding failures.

## Config System

- Config lives in `.env` (or custom file passed to constructor). The file path is validated — must be a `.env` file within the project directory.
- Dot-notation keys: `configGet('PHP.INI')` returns the `PHP.INI` nested object.
- `configSet` creates nested structure automatically. Protected keys (`PHP.INI`, `CORE`) cannot be modified via `configSet`.
- `PHP.INI` is a special key — the constructor iterates it and calls `ini_set()` only for allowed directives (see `ALLOWED_INI_SETTINGS` constant). Unknown directives are silently skipped.

## Config Caching

- Config values are cached in memory after first read. Subsequent `configGet` calls return from cache.
- `configSet` updates the cache only after a successful file write.
- `saveConfig` uses atomic writes (temp file + rename) to prevent corruption. Flattens nested arrays to dot-notation `.env` format.
- The cache is an in-memory property — it does not persist across requests.

## Type Patterns

- `addRoute` accepts mixed method/path and casts to string internally.
- `curlRequest` is static — called as `NanoCore::curlRequest($url, $options)`.
- `curlRequest` with `'with_info' => true` returns `['body'=>mixed,'status'=>int,'content_type'=>string|null]`. Without it, returns the body directly (backward compatible).
- `curlRequest` validates URLs before making requests: only `http`/`https` schemes allowed, private/restricted IPs are blocked (SSRF protection). IPv6 bracket stripping prevents bypass via `[::1]`-style addresses. `CURLOPT_FOLLOWLOCATION` is forced `false` to prevent redirect-based SSRF. Credentials are stripped from logged URLs. Body is truncated to 500 chars in logs.
- `curlRequest` retries up to 5 times with linear backoff (100ms, 200ms, 300ms...) and resets the curl handle between attempts (`curl_close` + `curl_init`). On failure, throws a generic "External request failed" exception (no internal details leaked).
- Magic properties via `__get`/`__set` on NanoCore instance: `$app->body` reads request body via `getBodyRequest()` with a 10MB default size limit (throws on overflow). The limit is customizable only via direct `getBodyRequest($maxBytes)` calls. An optional `$validateContentType` parameter (defaults to `false`) can enforce `application/json` Content-Type.
- `renderHtml` loads a template file and does string replacement from a data array. The path is validated to prevent traversal outside the project root. String values in `$data` are HTML-escaped by default (`$escape = true`); pass `false` to opt out.
- `execDetach` runs a shell command in the background, logging output to `nanocore.log`. Accepts a string (backward compat) or an array of `[command, arg, arg, ...]` for proper argument escaping. `ob_flush()` is guarded — skips when no output buffer is active, preventing warnings. On Windows, uses `escapeshellcmd()` for command escaping; on other platforms, uses `exec()` with output redirection.

## Response Methods

- `json($data, $status = 200)` — returns a `__nc_response` descriptor: `['__nc_response' => true, 'type' => 'json', 'data' => $data, 'status' => $status]`.
- `html($template, $data = [], $status = 200)` — returns a `__nc_response` descriptor with type `html`. Template is rendered via `renderHtml()`.
- `redirect($url, $status = 302)` — returns a `__nc_response` descriptor with type `redirect`. CRLF injection is prevented: `\r` and `\n` are stripped from the URL.
- `run()` detects `__nc_response` descriptors in handler return values and delegates to `sendResponse()` (private), which sets headers and outputs the body.
- `sendResponse()` handles: `json` (sets `Content-Type: application/json`, encodes data), `html` (sets `Content-Type: text/html`, renders template), `redirect` (sets `Location` header), empty return (204 No Content), null return with custom status (e.g. 304 Not Modified).

## Middleware

- `addMiddleware(callable $middleware)` — registers a middleware. Middlewares execute in **registration order** (first registered = first executed).
- Internally, `run()` wraps the handler in reverse-order: last registered middleware wraps innermost, so the call chain preserves registration order.
- Middleware signature: `function (NanoCore $app, array $params, callable $next): mixed`
- `$next` signature: `$next(NanoCore $app, array $params): mixed` — calls the next middleware or the final handler.
- If a middleware returns a `__nc_response` descriptor, `run()` processes it via `sendResponse()`.

## Input Validation

- `validate(array $data, array $rules)` — validates data against rules. Throws `\Exception` with HTTP 422 on failure. Returns validated data on success.
- `check(array $data, array $rules)` — validates data against rules. Returns `['valid' => bool, 'errors' => array, 'data' => array]`. Does not throw.
- `check()` skips absent optional fields — if a field is not present and not `required`, it is not validated.
- 10 built-in rules: `required`, `string`, `int`, `float`, `bool`, `email`, `url`, `min`, `max`, `regex`.
- Rule syntax: `'fieldname' => 'required|email'` (pipe-separated) or `'fieldname' => ['required', 'min:5']` (array).
- `parseRule('min:5')` → `['rule' => 'min', 'param' => '5']`.
- ⚠️ The `regex` rule uses `/` as the delimiter. If your pattern contains `/`, escape it or use a different delimiter in your regex and wrap the whole thing: `regex:/pattern/`.

## Events

- `on(string $event, callable $listener)` — registers a listener for an event.
- `emit(string $event, array $data = [])` — emits an event, calling all registered listeners in order. Catches `\Throwable` in each listener to prevent one broken listener from breaking the chain.
- 4 built-in events emitted by `run()`:
  - `route.matched` — emitted on successful route match, data includes method, path, params.
  - `route.not_found` — emitted on 404, data includes method, path.
  - `error` — emitted on any `\Throwable` caught by `run()`, data includes exception.
  - `response.sent` — emitted after every response is sent.

## CLI Commands

- `addCommand(string $name, callable $handler)` — registers a CLI command.
- `runCli()` — dispatches CLI commands based on `$_SERVER['argv']`.
- `run()` checks `php_sapi_name() === 'cli'` at the top and delegates to `runCli()` if in CLI mode.
- CLI commands receive `($app, $args)` where `$args` is the argv array.

## Sessions

- `sessionStart()` — idempotent session start. Reads `SESSION.*` config keys before starting. Does nothing if session is already active.
- `sessionGet(string $key, $default = null)` — reads a session value.
- `sessionSet(string $key, $value)` — writes a session value.
- `sessionDestroy()` — destroys the session. Guards for active session before calling `session_destroy()`.

## Security

Built-in protections across the library:

| Protection | Where | How |
| --- | --- | --- |
| SSRF | `curlRequest` | Only `http`/`https` schemes; blocks private/restricted IPs; resolves DNS to check resulting IPs; IPv6 bracket stripping to prevent bypass; `CURLOPT_FOLLOWLOCATION` forced `false` |
| Path traversal | `renderHtml` | Template path must be within project root (appends `DIRECTORY_SEPARATOR` before comparison) |
| XSS | `renderHtml` | String values are HTML-escaped by default (`$escape = true`; opt-out via `false`) |
| CRLF injection | `redirect` | `\r` and `\n` stripped from redirect URLs |
| SQL injection | NanoORM | Identifier validation (`/^[a-zA-Z_][a-zA-Z0-9_]*$/`); parameterized queries via PDO prepared statements |
| Config tampering | `configSet` | Protected keys (`PHP.INI`, `CORE`) throw on write; atomic file writes (temp + rename); internal double quotes escaped in `saveConfig()` |
| Command injection | `execDetach` | Array mode escapes each argument with `escapeshellarg()`; Windows support with `escapeshellcmd()` |
| Arbitrary `ini_set` | Constructor | Only directives in `ALLOWED_INI_SETTINGS` are applied; unknown ones are silently skipped |
| Credential leaking | `curlRequest` logs | Credentials stripped from logged URLs; body truncated to 500 chars in logs |
| DNS rebinding | `curlRequest` | ⚠️ Known limitation: TOCTOU between DNS resolution and request — not fully preventable at application level |

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
