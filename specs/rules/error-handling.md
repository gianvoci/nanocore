> Read this when handling errors, writing exception logic, or debugging JSON error responses.

# Error Handling Pattern

## Global Handlers (NanoCore constructor)

Two handlers are registered on instantiation:

1. **Error handler** — converts all PHP errors to `ErrorException`:
   ```
   set_error_handler → throw ErrorException
   ```

2. **Exception handler** — catches uncaught exceptions, emits JSON:
    ```json
    {"message": "...", "code": ...}
    ```
    File and line are intentionally excluded for security — they expose internal paths.
    HTTP status is taken from the exception code, clamped to 100–599 range (defaults to 500 if outside range).

## Route Dispatch Errors (`run()`)

The `run()` method wraps dispatch in try/catch catching `\Throwable`:

- **404**: Route not matched → `throw new \Exception('Route not found', 404)`
- **500**: Handler not callable → `throw new \Exception('Handler for route not callable', 500)`
- **Any \Throwable**: HTTP status from `$throwable->getCode()`, clamped to 100–599 range (falls back to 500)

Built-in events emitted during dispatch:
- `route.matched` — emitted on successful route match
- `route.not_found` — emitted on 404
- `error` — emitted on any `\Throwable` caught by `run()`
- `response.sent` — emitted after every response is sent

Response format on error:
```json
{
    "error": "Exception message",
    "code": 404
}
```

## Response Dispatch (`sendResponse()`)

`run()` detects `__nc_response` descriptors in handler return values and delegates to `sendResponse()` (private):

| Descriptor type | Behavior |
| --- | --- |
| `json` | Sets `Content-Type: application/json`, HTTP status, encodes data |
| `html` | Sets `Content-Type: text/html`, HTTP status, renders template |
| `redirect` | Sets `Location` header, HTTP status (default 302) |
| Empty return | 204 No Content |
| Null with custom status | Status-only response (e.g. 304 Not Modified) |

## Handler Pattern

Controllers should throw exceptions with HTTP status codes:

```php
$app->addRoute('GET', '/users/@id', function ($app, $params) use ($pdo) {
    $user = (new NanoORM($pdo, 'users'))->findById($params['id']);
    if (!$user) {
        throw new \Exception('User not found', 404);
    }
    return $user->toArray();
});
```

Key rules:
- Always provide a valid HTTP status code (100–599) as the second argument.
- Don't use codes outside 100–599 — NanoCore will default to 500.
- `run()` returns the handler's return value — the consumer is responsible for echoing it. Return arrays/objects for JSON, strings for HTML.
