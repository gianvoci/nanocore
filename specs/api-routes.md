> Read this when you need the public API surface (signatures, returns, throws) of NanoCore or NanoORM.

# Public API

This is a library: the "routes" are public methods. Behavioral contracts live in the domain specs linked per section. Consumer-facing examples: `README.md`.

## NanoCore — application & routing

See [routing/spec.md](routing/spec.md).

| Method | Args | Returns | Description |
| --- | --- | --- | --- |
| `__construct` | `string $configFile = '.env'` | — | Installs global handlers, resolves/creates `.env`, applies `PHP.INI.*`, sets `CORE.ROOT` |
| `addRoute` | `string $method, string $path, callable $handler` | `void` | Handler `fn(NanoCore $app, array $params)` |
| `addMiddleware` | `callable $middleware` | `void` | `fn($app, $params, $next, $uri, $method)` |
| `run` | — | `mixed` | CLI commands if registered, else dispatch; returns handler result if not a descriptor, else `null` |
| `json` | `mixed $data, int $status = 200, array $headers = []` | `array` descriptor | Custom `Content-Type` headers are dropped |
| `html` | `string $content, int $status = 200, array $headers = []` | `array` descriptor | Raw HTML (not a template path) |
| `redirect` | `string $url, int $status = 302` | `array` descriptor | CR/LF stripped from URL |

## NanoCore — validation

See [validation/spec.md](validation/spec.md).

| Method | Args | Returns | Throws |
| --- | --- | --- | --- |
| `validate` | `array $data, array $rules` | validated fields | `\Exception('Validation failed', 422)` |
| `check` | `array $data, array $rules` | `['valid'=>bool,'errors'=>[field=>string[]],'data'=>array]` | `\InvalidArgumentException` on unknown rule / non-numeric `min`/`max` param |

## NanoCore — events

| Method | Args | Returns | Description |
| --- | --- | --- | --- |
| `on` | `string $event, callable $listener` | `void` | Listener `fn(NanoCore $app, array $data)` |
| `emit` | `string $event, array $data = []` | `void` | Built-in events: [routing/spec.md](routing/spec.md#built-in-events) |

## CLI

| Method | Args | Returns | Description |
| --- | --- | --- | --- |
| `addCommand` | `string $name, callable $handler` | `void` | Name must match `^[a-zA-Z0-9:_-]+$`; handler `fn(NanoCore $app, array $args)` |
| `runCli` | — | `void` | Dispatches `argv[1]`; missing/unknown command lists commands and `exit(1)` |

## Sessions

See [integrations/sessions.md](integrations/sessions.md).

| Method | Args | Returns |
| --- | --- | --- |
| `sessionStart` | — | `void` (idempotent; throws `\InvalidArgumentException` on bad `SESSION.COOKIE_SAMESITE`) |
| `sessionGet` | `string $key, mixed $default = null` | `mixed` |
| `sessionSet` | `string $key, mixed $value` | `void` |
| `sessionDestroy` | — | `void` (no-op if inactive) |
| `sessionRegenerate` | — | `void` (no-op if inactive; deletes old session) |

## Config

See [config/spec.md](config/spec.md).

| Method | Args | Returns | Throws |
| --- | --- | --- | --- |
| `configGet` | `string $name` (dot notation) | scalar, nested `array`, or `null` | `\Exception` if `.env` unreadable/uncreatable |
| `configSet` | `string $prop, mixed $value` | `void` | `\Exception` protected key / write failure; `\InvalidArgumentException` invalid key or CR/LF/NUL value |

## HTTP client & SSRF guard (static)

See [http-client/spec.md](http-client/spec.md) and [integrations/outbound-http.md](integrations/outbound-http.md).

| Method | Args | Returns | Throws |
| --- | --- | --- | --- |
| `curlRequest` | `string\|array $url, array $options = []` | body / `with_info` array; batch: list in input order | `\Exception` invalid/restricted URL, single transport failure after retries (code 0), single HTTP ≥ 400 (code = HTTP status) unless `ignore_http_errors => true`; batch: `\Exception` in the failed slot |
| `validateUrlNotRestricted` | `string $url` | `void` | `\Exception` |
| `validateIpNotRestricted` | `string $ip` | `void` | `\Exception` |

## Request, templates, storage, processes

| Method | Args | Returns | Notes |
| --- | --- | --- | --- |
| `getBodyRequest` | `int $maxBytes = 10_485_760, bool $validateContentType = false` | decoded body | [integrations/inbound-request.md](integrations/inbound-request.md) |
| `renderHtml` | `string $filename, array $data = [], bool $escape = true` | `string` | `strtr` placeholder replace; [integrations/filesystem.md](integrations/filesystem.md) |
| `__get` | `string $name` | `mixed` | `body` → `getBodyRequest()`, `cli` → bool, else storage or `null` |
| `__set` | `string $name, mixed $value` | `void` | Writes storage (e.g. `$app->pdo = $pdo`) |
| `require` | `string $key` | `mixed` | `\RuntimeException("Required property '<key>' not configured")` if missing/null |
| `execDetach` | `string\|array $cmd` | `void` | [integrations/process.md](integrations/process.md) |

## NanoORM

See [orm/spec.md](orm/spec.md) and [conventions-database.md](conventions-database.md).

| Method | Args | Returns | Notes |
| --- | --- | --- | --- |
| `__construct` | `\PDO $pdo, string $table, string $primaryKey = 'id'` | — | Validates identifiers, loads schema |
| `fill` | `array $data` | `self` | Throws on unknown field |
| `fillOnly` | `array $data, array $allowed` | `self` | Keeps only `$allowed` keys, then `fill()` |
| `toArray` / `fromArray` | — / `array $row` | `array` / `self` | `fromArray` marks record persisted |
| `findById` | `mixed $id` | `?self` (clone) | |
| `findBy` | `string $where, array $params = [], ?int $limit = null` | `array<array>` | Raw WHERE, no joins |
| `findAll` | `string $where = '', array $params = [], string $orderBy = '', ?int $limit = null` | `array<array>` | `orderBy` sanitized |
| `addJoin` | `string $table, string $localKey, string $foreignKey, string $type = 'INNER', array $selectFields = ['*']` | `self` | |
| `fetchWithJoins` | `array $conditions = []` | `array<array>` | `field => value` equality, AND-ed |
| `save` / `delete` | — | `bool` | |
| `deleteWhere` | `string $where, array $params = []` | `int` rows | Empty WHERE throws |
| `paginate` | `int $page, int $perPage, string $where = '', array $params = [], string $orderBy = ''` | `['data','total','page','per_page','last_page']` | |
| `getId` / `isNew` / `getTable` / `clear` | — | `mixed` / `bool` / `string` / `self` | |
| `beginTransaction` / `commit` / `rollback` | — | `void` | Proxy to PDO |
| `transaction` | `callable $callback` | callback result | Rollback + rethrow on `\Exception`/`\Error` |
| `migrateDir` (static) | `\PDO $pdo, string $migrationsDir` | applied file names | |
| `rollbackDir` (static) | `\PDO $pdo, string $migrationsDir, int $steps = 1` | rolled-back names | |
| `migrationStatus` (static) | `\PDO $pdo, string $migrationsDir` | `['applied'=>[], 'pending'=>[]]` | |
