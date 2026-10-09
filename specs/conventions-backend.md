> Read this when you write or review PHP code in `src/` or code that consumes NanoCore.

# Backend Conventions

## Source style

| Topic | Convention |
| --- | --- |
| File header | `<?php`, blank line, `declare(strict_types=1);`, docblock, `namespace NanoCore;` |
| Layout | One class per file; sections marked with `#####` / `# SECTION` banners |
| Naming | `camelCase` methods/vars, `UPPER_SNAKE` `private const`, assertion helpers prefixed `assert*` (e.g. `assertValidConfigKey`) |
| Types | Every param and return typed; union types allowed (`string\|array`); `mixed` when needed |
| Exceptions | Root `\Exception` with HTTP code for client-facing errors; `\InvalidArgumentException` for programmer/input errors; `\RuntimeException` from `require()` |
| Static vs instance | Utilities without app state are `public static` (`curlRequest`, `validate*NotRestricted`); log dir shared via `private static ?string $logBasePath` |
| PHP 8.5 features | `match`, `str_starts_with`, `array_any`, numeric separators (`10_485_760`) are in use |

## Handlers and responses

- Handler signature: `function (NanoCore $app, array $params)`; return `$app->json(...)`, `$app->html(...)` or `$app->redirect(...)`.
- Returning a plain array/string sends **nothing** — `run()` returns it to the caller. Contract: [routing/spec.md](routing/spec.md).
- Signal errors by throwing with the HTTP status as code; never `echo` + `exit` inside handlers. Recipe: [rules/error-handling.md](rules/error-handling.md).

## Middleware

```php
$app->addMiddleware(function (NanoCore $app, array $params, callable $next, string $uri, string $method): mixed {
    if (!$app->sessionGet('user')) {
        throw new \Exception('Unauthorized', 401);
    }
    return $next($app, $params);   // must return $next's result to keep the descriptor
});
```

First registered = outermost. Middleware only runs for matched routes (404s bypass it).

## Events

- `on($event, fn(NanoCore $app, array $data))`; `emit()` calls listeners in registration order.
- A throwing listener never breaks the request: the error is appended to `nanocore.log` and the next listener runs. Do not rely on exceptions from listeners for control flow.
- Built-in event names and payloads: [routing/spec.md](routing/spec.md#built-in-events).

## Shared state

- Store services on the app instance via magic properties: `$app->pdo = $pdo;` then `$app->require('pdo')` (throws if missing) or `$app->pdo` (returns `null` if missing).
- `body` and `cli` are reserved virtual keys; `__nc_body` is the internal body cache key — do not overwrite.

## Logging

- Single sink: `{dir of .env}/nanocore.log`, appended with `FILE_APPEND | LOCK_EX`, line format `[Y-m-d H:i:s] <context>: <message>`.
- Before logging untrusted data: strip CR/LF and mask secrets (see `maskSensitiveParams`, `maskUrlQuery`, `logUnhandledException`).
- Logging must not throw in error paths (use `@file_put_contents` + `error_log()` fallback as in `logUnhandledException`).

## Security patterns to reuse

| Need | Reuse |
| --- | --- |
| Outbound URL | `self::validateUrlNotRestricted($url)` |
| SQL identifier | `validateIdentifier()` / `validateFieldName()` in NanoORM |
| Header value from input | Strip `\r`/`\n` (as in `redirect()`) |
| File path from input | `realpath` + root prefix check with trailing `DIRECTORY_SEPARATOR` (as in `renderHtml()`) |
| Shell command | Array form + `escapeshellarg` (as in `execDetach()`) |

All guards are catalogued in [integrations/index.md](integrations/index.md).
