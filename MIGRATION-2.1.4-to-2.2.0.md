# Migration Guide: 2.1.x → 2.2.0

## Breaking Changes

### 1. `CURLOPT_FOLLOWLOCATION` ignored

`curlRequest()` now forces `CURLOPT_FOLLOWLOCATION = false` after merging the caller's options. This prevents SSRF attacks via redirects, but means **any `CURLOPT_FOLLOWLOCATION => true` passed in `$options` is silently ignored**.

**If your project follows HTTP redirects via `curlRequest()`**, you must handle redirects manually:

```php
// BEFORE (2.1.x) — followed redirects automatically
$response = NanoCore::curlRequest($url, [CURLOPT_FOLLOWLOCATION => true]);

// AFTER (2.2.0) — handle the redirect manually
$response = NanoCore::curlRequest($url, ['with_info' => true]);
if ($response['status'] >= 300 && $response['status'] < 400) {
    $location = $response['headers']['location'] ?? null;
    if ($location) {
        $response = NanoCore::curlRequest($location, ['with_info' => true]);
    }
}
```

**Action**: Search for `CURLOPT_FOLLOWLOCATION` in your code. If present, implement manual redirect handling.

### 2. `run()` catches `\Throwable`

`run()` now catches `\Throwable` (not just `\Exception`). If your project has a `try/catch` around `run()` to handle errors or custom exceptions, **that catch will never be reached** — `run()` handles everything internally by emitting the `error` event and returning a JSON response.

```php
// BEFORE (2.1.x) — the outer catch could intercept exceptions
try {
    $app->run();
} catch (\Exception $e) {
    // custom log
}

// AFTER (2.2.0) — use the 'error' event instead
$app->on('error', function(array $data) {
    // $data['exception'] is the \Throwable instance
    error_log($data['exception']->getMessage());
});
$app->run();
```

**Action**: Replace `try/catch` around `run()` with a listener on the `error` event.

### 3. Reserved key `__nc_response`

If a route handler returns an array containing the key `'__nc_response'` with value `true`, NanoCore interprets it as a response descriptor and calls `sendResponse()` automatically. If your project returns arrays with that key for other purposes, the behavior will change.

**Action**: Search for `__nc_response` in your code. If you use that key for other purposes, rename it.

---

## New Configuration Keys

If you use sessions, add these keys to your `.env` file:

```env
SESSION.COOKIE_HTTPONLY=true
SESSION.COOKIE_SECURE=true
SESSION.USE_STRICT_MODE=true
```

All three are optional. If absent, NanoCore uses PHP defaults.

---

## New APIs

These methods are **additive** — they don't break existing code. Use them if needed.

| Method | Description |
|--------|-------------|
| `addMiddleware(callable $mw)` | Register middleware |
| `validate(array $rules, array $data)` | Validate input, returns validated data or throws `InvalidArgumentException` |
| `on(string $event, callable $handler)` | Register event listener |
| `emit(string $event, array $data)` | Emit event |
| `addCommand(string $name, callable $handler)` | Register CLI command |
| `runCommands()` | Execute CLI command from `$_SERVER['argv']` |
| `sessionStart()` | Start PHP session |
| `sessionGet(string $key)` | Read session value |
| `sessionSet(string $key, mixed $value)` | Write session value |
| `sessionDestroy()` | Destroy session |
| `json(mixed $data, int $status)` | JSON response descriptor |
| `html(string $content, int $status)` | HTML response descriptor |
| `redirect(string $url, int $status)` | Redirect response descriptor |
| `migrate(string $dir)` | Run SQL migrations |
| `migrationStatus(string $dir)` | Migration status |
| `paginate(string $table, int $page, int $perPage, array $where)` | Query pagination |

---

## Renamed Directory

`docs/` has become `specs/`. If your project references files in `docs/`, update the paths.

---

## Migration Checklist

- [ ] Search for `CURLOPT_FOLLOWLOCATION` in code — if present, implement manual redirect handling
- [ ] Search for `try/catch` around `run()` — replace with `error` event
- [ ] Search for `__nc_response` in code — if used for other purposes, rename it
- [ ] If you use sessions, add `SESSION.*` keys to `.env` file
- [ ] Update references from `docs/` to `specs/`
- [ ] Run the existing test suite to verify backward compatibility