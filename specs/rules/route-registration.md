> Read this when registering routes, using path parameters, or debugging route matching.

# Route Registration Pattern

## Adding Routes

```php
$app->addRoute(string $method, string $path, callable $handler);
```

- Method is uppercased internally (`get` → `GET`).
- Path is normalized: backslashes → forward slashes, duplicate slashes collapsed, leading `/` ensured, trailing `/` removed.
- Base path (detected from `SCRIPT_NAME`) is stripped automatically.

## Path Parameters

| Syntax | Captures | Example |
| --- | --- | --- |
| `@name` | Single path segment | `/users/@id` → `['id' => '42']` |
| `@*` | Rest of path (wildcard) | `/files/@*` → `['wildcard' => 'foo/bar.txt']` |
| Multiple `@param` | Each captures one segment | `/path/@a/@b` → `['a' => 'x', 'b' => 'y']` |

Rules:
- `@` prefix triggers parameter capture.
- `@*` must be the last segment — everything after is captured as `wildcard`.
- Parameter names are sanitized: non-word characters stripped (alphanumerics and underscores preserved), empty names become `param0`, `param1`, etc.

## Dispatch Flow

1. Incoming URI is parsed (`parse_url` for path + query string).
2. Query string is parsed into params.
3. URI is normalized and base-path-stripped.
4. Routes for the current HTTP method are iterated.
5. Each route's regex pattern is tested against the URI.
6. On match: `route.matched` event emitted, path params extracted, merged with query params.
7. Middleware chain executes (first registered = first executed), wrapping the handler.
8. Handler called with `($app, $params)`.
9. If handler returns a `__nc_response` descriptor, `sendResponse()` processes it.
10. `response.sent` event emitted.
11. On no match after all routes: `route.not_found` event emitted, `Exception('Route not found', 404)`.
12. Path params take precedence: if a path param and query param share the same key, the path param value wins (`array_merge` with path params second).

## Middleware

```php
$app->addMiddleware(function (NanoCore $app, array $params, callable $next): mixed {
    // Before handler
    $result = $next($app, $params);  // Call next middleware or handler
    // After handler
    return $result;
});
```

- `addMiddleware()` registers middleware in order. First registered = first executed.
- Internally, `run()` wraps the handler in reverse order: last registered wraps innermost.
- `$next(NanoCore $app, array $params): mixed` — calls the next middleware or the final handler.
- Middleware can return a `__nc_response` descriptor to short-circuit the chain.

## URI Normalization Examples

| Input | Normalized |
| --- | --- |
| `/` | `/` |
| `/ping/` | `/ping` |
| `ping` | `/ping` |
| `/api//health` | `/api/health` |
| `\windows\path` | `/windows/path` |
