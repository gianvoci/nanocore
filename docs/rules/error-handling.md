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
   {"message": "...", "code": ..., "file": "...", "line": ...}
   ```
   Always responds with HTTP 500.

## Route Dispatch Errors (`run()`)

The `run()` method wraps dispatch in try/catch:

- **404**: Route not matched → `throw new \Exception('Route not found', 404)`
- **500**: Handler not callable → `throw new \Exception('Handler for route not callable', 500)`
- **Any exception**: HTTP status from `$exception->getCode()`, clamped to 100–599 range (falls back to 500)

Response format on error:
```json
{
    "error": "Exception message",
    "code": 404,
    "file": "/path/to/file.php",
    "line": 42
}
```

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
