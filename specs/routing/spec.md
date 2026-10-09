> Read this when you change route registration, dispatch, middleware, response descriptors, or error handling.

# Routing, Dispatch & Error Contract

## Purpose

Maps an HTTP request (or CLI `argv[1]`) to a handler through a middleware chain, sends response descriptors, and turns every failure into a JSON error.

## Data model

Route entry (`$this->routes[METHOD][]`):

| Field | Type | Notes |
| --- | --- | --- |
| `handler` | `callable` | `fn(NanoCore $app, array $params)` |
| `pattern` | `string` | Regex built by `buildRoutePattern()`, delimiter `#` |
| `params` | `string[]` | Captured param names in order |

Response descriptor (returned by `json()`/`html()`/`redirect()`):

| Key | Type | Notes |
| --- | --- | --- |
| `__nc_response` | `true` | Marker detected by `run()` |
| `type` | `'json'\|'html'\|'redirect'` | |
| `body` | `mixed` | `null` for redirect |
| `status` | `int` | Out of 100–599 → 500 at send time |
| `headers` | `string[]` | json: `Content-Type: application/json` first, caller `content-type:` headers dropped; html: `text/html; charset=UTF-8` first, caller headers appended unfiltered |
| `url` | `string` | redirect only, CR/LF stripped |

## Business rules

### Path normalization (registration and dispatch)
1. `\` → `/`, repeated `/` collapsed, leading `/` forced, trailing `/` removed, empty → `/`.
2. Base path (`dirname($_SERVER['SCRIPT_NAME'])`, empty in CLI) is stripped from both registered paths and request URIs.
3. Method is upper-cased; no `HEAD`→`GET` fallback, no 405 — an unknown method/path is a 404.

### Pattern syntax
- `@name` → one segment `[^/]+`; name sanitized to `[a-zA-Z0-9_]`, empty → `param{N}`.
- `@*` → rest of path into `wildcard`; segments after it are ignored.
- Other segments are literal (`preg_quote`).
- First registered matching route for the method wins.

### Parameters
- `$params` = CLI positional args (`argv[2..]`, CLI only) + query string, then path params override on key collision.
- In CLI without registered commands, `argv[1]` is the request path.

### Dispatch
1. CLI with commands registered → `runCli()`, `run()` returns `null`.
2. On match: emit `route.matched`, build middleware chain (first registered = outermost), call it.
3. Result with `__nc_response` → `sendResponse()`, `run()` returns `null`; any other result is returned unchanged with **no output**.
4. No match → emit `route.not_found`, throw `Exception('Route not found', 404)`.
5. Any `\Throwable` → emit `error` → `sendJsonError()`; `run()` returns `null`.
6. Middleware never runs for unmatched routes.

### Sending
- `redirect`, status `204`, `304` → headers only, no body.
- `json` body encoded with `JSON_THROW_ON_ERROR`.

### Error contract
- Body: `{"error": "<message>", "code": <status>}`, `Content-Type: application/json`, encoded with `JSON_THROW_ON_ERROR`.
- Status = `(int) $exception->getCode()` if in 100–599, else 500 (PDO SQLSTATE codes therefore become 500).
- `CORE.EXPOSE_ERRORS` (default `true`, parsed with `FILTER_VALIDATE_BOOLEAN`, read from cache only): when `false`, status ≥ 500 returns `"Internal Server Error"` and the original message is logged with CR/LF stripped (`error_log()` fallback). 4xx messages are always exposed.
- No file paths, lines, or traces in responses.
- Global error handler (installed in constructor): converts every PHP error to `ErrorException`, except errors suppressed with `@` (detected as runtime mask ≠ ini `error_reporting`). Errors excluded by the configured `error_reporting` still throw.
- Global exception handler sends the same JSON for exceptions thrown outside `run()`.
- Changing the shape or exposure defaults is a breaking change (major release).

### Built-in events

| Event | Emitted | Payload |
| --- | --- | --- |
| `route.matched` | Before middleware chain | `method`, `path`, `params` |
| `route.not_found` | Before the 404 exception | `method`, `path` |
| `error` | In `run()` catch, before JSON error | `exception` |
| `response.sent` | After descriptor output | `type`, `status` (+ `url` for redirect) |

## Constants

| Name | Value | Location |
| --- | --- | --- |
| Default error status | `500` | `sendJsonError()`, `sendResponse()` |
| Wildcard param name | `wildcard` | `buildRoutePattern()` |
| Hidden 5xx message | `Internal Server Error` | `sendJsonError()` |

## Cross-links

- API: [api-routes.md](../api-routes.md#nanocore--application--routing)
- Recipes: [rules/add-route.md](../rules/add-route.md), [rules/error-handling.md](../rules/error-handling.md)
- Inbound trust boundaries: [integrations/inbound-request.md](../integrations/inbound-request.md)
