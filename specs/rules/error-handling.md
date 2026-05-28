> Read this when handling errors, writing exception logic, or debugging JSON error responses.

# Error Handling Pattern

## Global Handlers (NanoCore constructor)

Two handlers are registered on instantiation:

1. **Error handler** — converts all PHP errors to `ErrorException`:
   ```
   set_error_handler → throw ErrorException
   ```

2. **Exception handler** — catches uncaught exceptions, delegates to `sendJsonError()` which emits JSON:
    ```json
    {"error": "...", "code": 500}
    ```
    File and line are intentionally excluded for security — they expose internal paths.
    The shared `sendJsonError()` private method clamps the exception code to 100–599 range (defaults to 500 if outside range) and uses the clamped value for both HTTP status and the `code` field.

## Route Dispatch Errors (`run()`)

The `run()` method wraps dispatch in try/catch catching `\Throwable`:

- **404**: Route not matched → `throw new \Exception('Route not found', 404)`
- **Any \Throwable**: Delegates to `sendJsonError()` — HTTP status and JSON `code` field use the clamped exception code (100–599, falls back to 500)

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
| `json` | Sets HTTP status, sends all headers from descriptor's `headers` array, echoes `json_encode($descriptor['body'], JSON_THROW_ON_ERROR)` |
| `html` | Sets HTTP status, sends all headers from descriptor's `headers` array, echoes `$descriptor['body']` directly (no template rendering) |
| `redirect` | Sets HTTP status, sends all headers from descriptor's `headers` array (includes `Location:`), no body output |
| Status 204 or 304 | No body output, emits `response.sent` event and returns |

## Response Descriptor Format

All response methods (`json()`, `html()`, `redirect()`) return a descriptor array:

```php
[
    '__nc_response' => true,
    'type'          => 'json|html|redirect',
    'body'          => mixed,   // data for json, content string for html, null for redirect
    'status'        => int,     // HTTP status code
    'headers'       => array,   // array of header strings (e.g. ['Content-Type: application/json'])
    'url'           => string,  // only for redirect type — the redirect URL
]
```

- `json()` sets `body` to the data and `headers` to `['Content-Type: application/json', ...$customHeaders]`.
- `html()` sets `body` to the content string and `headers` to `['Content-Type: text/html; charset=UTF-8', ...$customHeaders]`.
- `redirect()` sets `body` to `null` and `headers` to `["Location: {$url}"]`.

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
- `run()` returns the handler's return value for normal responses. If the handler returns a truthy `__nc_response` descriptor (`!empty()` check), `run()` calls `sendResponse()` and returns `null`. If an exception is caught, `run()` outputs a JSON error via `sendJsonError()` and returns `null`.
