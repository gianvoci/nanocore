> Read this when you add a route, middleware, or validated endpoint in a NanoCore app or test.

# Add a Route

Contract: [routing/spec.md](../routing/spec.md).

1. Register with method, path pattern, handler:

```php
$app->addRoute('POST', '/users/@id/notes', function (NanoCore $app, array $params) {
    $input = $app->validate($app->body, [
        'title' => 'required|string|max:120',
        'tag'   => 'in:work,home',
    ]);                                         // 422 on failure
    return $app->json(['id' => $params['id'], 'note' => $input], 201);
});
```

2. Return a descriptor (`json`/`html`/`redirect`). A plain return value is **not** sent.
3. Use `@name` for one segment, `@*` (last segment) for the rest of the path → `$params['wildcard']`.
4. Need field-level errors in the response? Use `check()` and return them yourself:

```php
$result = $app->check($app->body, $rules);
if (!$result['valid']) {
    return $app->json(['errors' => $result['errors']], 422);
}
```

5. Cross-cutting logic (auth, logging) → middleware, always returning `$next($app, $params)`:

```php
$app->addMiddleware(fn (NanoCore $app, array $params, callable $next, string $uri, string $method)
    => str_starts_with($uri, '/admin') && !$app->sessionGet('admin')
        ? throw new \Exception('Forbidden', 403)
        : $next($app, $params));
```

6. Add a test with `runRequest($app, 'POST', '/users/7/notes')` ([add-test.md](add-test.md)).
7. If the routing engine itself changed: update [routing/spec.md](../routing/spec.md) and `README.md` → Routing.
