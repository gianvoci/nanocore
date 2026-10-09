> Read this when you raise, catch, or report errors in NanoCore or in handlers.

# Error Handling

Contract (JSON shape, status mapping, `CORE.EXPOSE_ERRORS`): [routing/spec.md](../routing/spec.md#error-contract).

1. Client errors: throw `\Exception` with the HTTP status as code.

```php
throw new \Exception('User not found', 404);
throw new \Exception('Unauthorized', 401);
```

2. Library input/programming errors: throw `\InvalidArgumentException` (no code → 500) with a specific message (`"Invalid config key: {$key}"`).
3. Never put paths, SQL, or secrets in a 4xx message — 4xx messages are always sent to the client.
4. Do not catch-and-echo inside handlers; let `run()` produce the JSON. To observe failures, listen to the `error` event:

```php
$app->on('error', function (NanoCore $app, array $data): void {
    error_log($data['exception']->getMessage());
});
```

5. Calling a PHP function that may warn: either handle the `ErrorException`, or suppress with `@` **and** check the return value (pattern used by `regex:` and `parseEnvFile()`):

```php
$contents = @file_get_contents($path);
if ($contents === false) {
    throw new \Exception("Cannot read config file: {$path}");
}
```

6. Logging inside an error path must not throw: `@file_put_contents(...) === false` → `error_log()` (see `logUnhandledException()`).
7. Tests that construct `NanoCore` must restore handlers in `finally` ([add-test.md](add-test.md)).
8. New exception message or code visible to consumers → note it in `MIGRATION-to-{version}.md` ([release.md](release.md)).
