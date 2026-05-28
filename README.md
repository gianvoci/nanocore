# NanoCore

A lightweight PHP micro-framework with routing, config management, a micro ORM, and utility functions. Zero dependencies, PHP 8.5+.

## Features

- Pattern-based routing with path parameters and wildcards
- Response methods: `json()`, `html()`, `redirect()`
- Middleware pipeline with `$next` chaining
- Input validation with 10 built-in rules
- Event system with built-in lifecycle events
- CLI command registration and auto-detection
- Session management with config-driven settings
- Env-based config management with dot-notation access
- NanoORM: lightweight ORM with CRUD, joins, pagination, transactions, migrations
- HTTP client with retry and SSRF protection
- Request body parser with size limit
- HTML template rendering with XSS protection
- Background process execution
- Built-in error handling (JSON responses)
- Security hardening out of the box

## Requirements

- PHP >= 8.5
- No extensions beyond standard PHP (curl extension needed for `curlRequest`)

## Installation

```bash
composer require gianvoci/nanocore
```

Or via local path in `composer.json`:

```json
{
    "repositories": [{"type": "path", "url": "../nanocore"}],
    "require": {"gianvoci/nanocore": "@dev"}
}
```

## Quick Start

Create `index.php`:

```php
<?php
require_once __DIR__ . '/vendor/autoload.php';

use NanoCore\NanoCore;

$app = new NanoCore();

$app->addRoute('GET', '/', function ($app, $params) {
    return json(['message' => 'Hello, NanoCore!']);
});

$app->addRoute('GET', '/users/@id', function ($app, $params) {
    return json(['user_id' => $params['id']]);
});

$app->run();
```

Run with PHP built-in server:

```bash
php -S localhost:8000
```

Test it:

```bash
curl http://localhost:8000/           # {"message":"Hello, NanoCore!"}
curl http://localhost:8000/users/42   # {"user_id":"42"}
```

## Routing

### Registering Routes

```php
$app->addRoute(string $method, string $path, callable $handler);
```

- Method: GET, POST, PUT, DELETE, PATCH, etc. (case-insensitive)
- Path: auto-normalized (trailing slashes removed, duplicate slashes collapsed)
- Handler: receives `($app, $params)` — must be callable

### Path Parameters

| Syntax | Captures | Example |
| --- | --- | --- |
| `@name` | Single segment | `/users/@id` → `['id' => '42']` |
| `@*` | Rest of path | `/files/@*` → `['wildcard' => 'specs/readme.md']` |
| Multiple | One per segment | `/api/@version/@resource` → `['version' => 'v1', 'resource' => 'users']` |

### Query Parameters

Query params are automatically merged with path params. Path params take precedence on collision:

```php
$app->addRoute('GET', '/search/@query', function ($app, $params) {
    // $params contains 'query' from path + any query string params
    return $params;
});

// GET /search/php?limit=10&page=1
// → ['query' => 'php', 'limit' => '10', 'page' => '1']
```

### Error Responses

Unmatched routes return `404`. Exceptions in handlers use the exception code as HTTP status:

```php
$app->addRoute('GET', '/users/@id', function ($app, $params) {
    throw new \Exception('User not found', 404);
});

// Response: {"error":"User not found","code":404} with HTTP 404
```

## Response Methods

Return from handlers to send structured responses. `run()` detects `__nc_response` descriptors automatically.

```php
// JSON response with Content-Type header
return json(['user' => $user], 201);

// HTML template rendering
return html('templates/profile.html', ['{{NAME}}' => $user->name]);

// Redirect (CRLF stripped from URL for security)
return redirect('/login', 302);
```

| Function | Signature | Description |
| --- | --- | --- |
| `json()` | `json($data, $status = 200)` | JSON response with Content-Type header |
| `html()` | `html($path, $data, $escape = true)` | Rendered HTML template |
| `redirect()` | `redirect($url, $status = 302)` | Redirect header (CR/LF stripped) |

Returning a plain array still works — auto-encoded as JSON with status 200.

## Middleware

```php
// First registered = first executed
$app->addMiddleware(function ($app, $params, $next) {
    // Before handler
    $response = $next($app, $params);
    // After handler
    return $response;
});
```

- Middleware wraps handlers in reverse order (last registered = innermost)
- `$next` continues the chain to the next middleware or the route handler
- Return value flows back through the middleware stack

## Input Validation

```php
use function NanoCore\validate;
use function NanoCore\check;

// Throws Exception(422) on failure
$validated = validate($data, [
    'name'  => 'required|string',
    'email' => 'required|email',
    'age'   => 'int|min:0|max:150',
]);

// Returns result array — no exception
$result = check($data, ['email' => 'required|email']);
// $result = ['valid' => bool, 'errors' => array, 'data' => array]
```

### Built-in Rules

| Rule | Param | Example |
| --- | --- | --- |
| `required` | — | `required` |
| `string` | — | `string` |
| `int` | — | `int` |
| `float` | — | `float` |
| `bool` | — | `bool` |
| `email` | — | `email` |
| `url` | — | `url` |
| `min` | `:value` | `min:1` |
| `max` | `:value` | `max:100` |
| `regex` | `:pattern` | `regex:^[a-z]+$` |

The `regex` rule auto-wraps the pattern in `/` delimiters — do not include delimiters in the param.

## Events

```php
// Listen
$app->on('route.matched', function ($data) {
    // $data contains event-specific payload
});

// Emit
$app->emit('custom.event', ['key' => 'value']);
```

### Built-in Events

| Event | When |
| --- | --- |
| `route.matched` | After a route is matched |
| `route.not_found` | No route matched the request |
| `error` | On uncaught exception |
| `response.sent` | After response is sent |

Listeners catch `\Throwable` internally — one broken listener doesn't break the chain.

## CLI Commands

```php
$app->addCommand('migrate', function ($app, $args) {
    // Run migrations
});

$app->addCommand('seed', function ($app, $args) {
    // Run seeders
});
```

CLI mode is detected automatically — `run()` delegates to `runCli()` when `php_sapi_name() === 'cli'`. Exits with code 1 on unknown command.

```bash
php index.php migrate
php index.php seed
```

## Sessions

```php
$app->sessionStart();                          // Idempotent — safe to call multiple times
$app->sessionSet('user_id', 42);
$userId = $app->sessionGet('user_id');         // → 42
$userId = $app->sessionGet('user_id', 0);      // → 42 (with default)
$app->sessionDestroy();
```

### Session Config

Config keys read from `.env`:

| Key | Description |
| --- | --- |
| `SESSION.AUTO_START` | Auto-start session on construction |
| `SESSION.NAME` | Session cookie name |
| `SESSION.LIFETIME` | Cookie lifetime in seconds |
| `SESSION.PATH` | Cookie path |
| `SESSION.DOMAIN` | Cookie domain |
| `SESSION.SECURE` | HTTPS-only cookie |
| `SESSION.HTTPONLY` | HTTP-only cookie (no JS access) |

`sessionStart()` is idempotent. `sessionDestroy()` guards for active session.

## Configuration

Config is stored in `.env` (auto-created as empty if missing). Access via dot-notation:

```php
// Read
$dbHost = $app->configGet('DB.HOST');         // → "localhost"
$dbPort = $app->configGet('DB.PORT');         // → "3306"
$fullDb = $app->configGet('DB');              // → ['HOST' => 'localhost', 'PORT' => '3306']

// Write
$app->configSet('DB.HOST', 'localhost');
$app->configSet('DB.PORT', '3306');
```

### .env Format

```env
# Database
DB.HOST=localhost
DB.PORT=3306
DB.NAME=myapp

# Quoted values (quotes are stripped)
APP.TITLE="My Application"

# Variable interpolation
DB.URL=${DB.HOST}:${DB.PORT}

# Inline comments
APP.DEBUG=true # enabled for dev

# export prefix is silently stripped
export APP.ENV=production
```

All values are strings — `DB.PORT=3306` returns `"3306"`, not `3306`.

### Config Template

A `.env.example` file is included as a template with all available settings commented out. Copy it to `.env` and fill in your values.

### PHP.ini Settings

Set PHP directives through config (only allowed directives are applied):

```env
PHP.INI.display_errors=1
PHP.INI.error_reporting=E_ALL
PHP.INI.date.timezone=Europe/Rome
```

Allowed directives: `display_errors`, `error_log`, `error_reporting`, `log_errors`, `upload_max_filesize`, `post_max_size`, `max_execution_time`, `memory_limit`, `default_charset`, `date.timezone`, `session.cookie_httponly`, `session.cookie_secure`, `session.use_strict_mode`.

### Protected Keys

`PHP.INI` and `CORE` cannot be modified via `configSet()`. They're managed internally.

## NanoORM

A lightweight ORM that auto-discovers your table schema. Works with MySQL and SQLite.

### Setup

```php
use NanoCore\NanoORM;

$pdo = new PDO('sqlite:app.db');
// or: $pdo = new PDO('mysql:host=localhost;dbname=myapp', 'user', 'pass');

$users = new NanoORM($pdo, 'users');                     // PK defaults to 'id'
$settings = new NanoORM($pdo, 'user_settings', 'user_id'); // Custom primary key
```

Table name and primary key are validated — must match `/^[a-zA-Z_][a-zA-Z0-9_]*$/`.

### CRUD Operations

Create:

```php
$user = new NanoORM($pdo, 'users');
$user->fill(['name' => 'Jane', 'email' => 'jane@example.com']);
$user->save();
echo $user->getId();  // Auto-generated ID
```

Read:

```php
$user = (new NanoORM($pdo, 'users'))->findById(1);
echo $user->name;     // Magic getter
echo $user->email;

$actives = (new NanoORM($pdo, 'users'))->findBy('status', 'active', 10);
$recent = (new NanoORM($pdo, 'posts'))->findAll(
    ['published' => 1],
    'created_at DESC',
    10
);
```

Update:

```php
$user = (new NanoORM($pdo, 'users'))->findById(1);
$user->email = 'newemail@example.com';
$user->save();  // UPDATE because isNew is false
```

Delete:

```php
$user = (new NanoORM($pdo, 'users'))->findById(1);
$user->delete();

// Batch delete
(new NanoORM($pdo, 'users'))->deleteWhere(['status' => 'inactive']);
```

### Pagination

```php
$result = (new NanoORM($pdo, 'users'))->paginate(1, 25, ['status' => 'active'], 'name ASC');
// $result = [
//     'data'     => [...],
//     'total'    => 142,
//     'page'     => 1,
//     'per_page' => 25,
//     'last_page' => 6,
// ]
```

Validates `page` and `perPage` >= 1.

### Transactions

```php
$orm = new NanoORM($pdo, 'users');

// Manual control
$orm->beginTransaction();
try {
    $orm->fill(['name' => 'Jane'])->save();
    $orm->commit();
} catch (\Throwable $e) {
    $orm->rollback();
}

// Auto-rollback on failure
$orm->transaction(function () use ($orm) {
    $orm->fill(['name' => 'Jane'])->save();
});
```

Note: MySQL auto-commits on DDL statements, so transactions are ineffective for DDL-heavy migrations on MySQL.

### Migrations

```php
use NanoCore\NanoORM;

// Run all pending migrations
NanoORM::migrateDir($pdo, __DIR__ . '/migrations');

// Rollback last batch
NanoORM::rollbackDir($pdo, __DIR__ . '/migrations');

// Check status
$status = NanoORM::migrationStatus($pdo, __DIR__ . '/migrations');
```

File naming: `YYYY_MM_DD_HH_MM_SS_name.sql`
Rollback files: `YYYY_MM_DD_HH_MM_SS_name_rollback.sql` (required for rollback)

Driver detection for SQLite vs MySQL. Invalid file names throw `InvalidArgumentException`.

### Joins

```php
$orders = new NanoORM($pdo, 'orders');
$orders
    ->addJoin('users', 'user_id', 'id', 'INNER', ['name', 'email'])
    ->addJoin('products', 'product_id', 'id', 'LEFT', ['title', 'price']);

$rows = $orders->fetchWithJoins(['status' => 'completed']);
// $rows = [
//     ['id' => 1, 'status' => 'completed', 'j0_name' => 'Jane', 'j1_title' => 'Widget', ...],
//     ...
// ]
```

Join types: `INNER`, `LEFT`, `RIGHT`, `FULL`, `CROSS`.

### ORM Methods Reference

| Method | Returns | Description |
| --- | --- | --- |
| `fill(array $data)` | `self` | Set multiple fields at once |
| `save()` | `bool` | Insert (new) or update (existing) |
| `delete()` | `bool` | Delete current record by PK |
| `deleteWhere(array $conds)` | `int` | Delete matching records, returns affected rows |
| `findById($id)` | `self\|null` | Find by primary key |
| `findBy($field, $value, $limit)` | `array` | Find by field value |
| `findAll($conds, $orderBy, $limit)` | `array` | Find with conditions, order, limit |
| `paginate($page, $perPage, $conds, $orderBy)` | `array` | Paginated results with metadata |
| `addJoin($table, $local, $foreign, $type, $fields)` | `self` | Register a JOIN |
| `fetchWithJoins($conds)` | `array` | Execute query with registered JOINs |
| `beginTransaction()` | `void` | Start a transaction |
| `commit()` | `void` | Commit current transaction |
| `rollback()` | `void` | Rollback current transaction |
| `transaction(callable)` | `mixed` | Run callable with auto-rollback on `\Throwable` |
| `toArray()` | `array` | Get all field data |
| `clear()` | `self` | Reset data, joins, and isNew state (preserves schema) |
| `getId()` | `mixed` | Get primary key value |
| `isNew()` | `bool` | Check if record is unsaved |
| `getTable()` | `string` | Get table name |

Static methods:

| Method | Description |
| --- | --- |
| `migrateDir($pdo, $dir)` | Run pending migrations from directory |
| `rollbackDir($pdo, $dir)` | Rollback last migration batch |
| `migrationStatus($pdo, $dir)` | Get migration status array |

Magic properties via `__get`, `__set`, `__isset`, `__unset` for field access.

## HTTP Client

```php
// Simple GET
$data = NanoCore::curlRequest('https://api.example.com/users');

// POST with body
$result = NanoCore::curlRequest('https://api.example.com/users', [
    'method'  => 'POST',
    'params'  => ['name' => 'Jane', 'email' => 'jane@example.com'],
    'headers' => ['Content-Type: application/json', 'Authorization: Bearer token123'],
]);

// Custom CURLOPT overrides (timeouts, streaming, etc.)
$result = NanoCore::curlRequest('https://api.example.com/stream', [
    'method'  => 'POST',
    'params'  => $body,
    'headers' => ['Authorization: Bearer token123'],
    CURLOPT_TIMEOUT        => 120,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_WRITEFUNCTION  => $callback,  // Streaming — callback receives chunks
]);

// Skip JSON decoding (e.g. HTML responses)
$html = NanoCore::curlRequest('https://example.com/page', [
    'raw' => true,
]);

// Get response with metadata (status code, content type)
$info = NanoCore::curlRequest('https://api.example.com/users', [
    'with_info' => true,
]);
// $info = ['body' => ..., 'status' => 200, 'content_type' => 'application/json']
```

**Options:**

| Key | Type | Default | Description |
| --- | --- | --- | --- |
| `method` | string | `'GET'` | HTTP method (case-insensitive) |
| `params` | array | `[]` | Request parameters (query string for GET, POST body otherwise) |
| `headers` | array | `[]` | HTTP headers |
| `raw` | bool | `false` | Skip JSON decoding, return raw string |
| `with_info` | bool | `false` | Return `['body'=>mixed,'status'=>int,'content_type'=>string|null]` instead of just the body |
| `CURLOPT_*` | mixed | varies | Any curl constant — merged directly into curl options |

Features:

- Automatic JSON decoding (returns raw string if not valid JSON, or when `raw` is true)
- CURLOPT passthrough — any `CURLOPT_*` constant can be passed to override defaults
- Streaming support via `CURLOPT_WRITEFUNCTION` — method returns `true` on success, body consumed by callback
- Request logging to `nanocore.log` (method, URL, status code, duration, params, response)
- Up to 5 retries with linear backoff (100ms, 200ms, 300ms...)
- 30s connect timeout, 30s total timeout (overridable via CURLOPT)
- SSRF protection: only `http`/`https` schemes, blocks private/restricted IPs, resolves DNS to validate
- `CURLOPT_FOLLOWLOCATION` forced `false` for SSRF safety
- Credentials stripped from logged URLs
- Response body truncated to 500 chars in logs
- Retry reinitializes the curl handle between attempts (`curl_init`). CurlHandle objects are freed automatically.

### SSRF Validation (public)

Validate URLs before making requests:

```php
// Throws if URL points to restricted network
NanoCore::validateUrlNotRestricted('https://api.example.com');
NanoCore::validateIpNotRestricted('192.168.1.1');  // Throws — private IP
```

IPv6 bracket stripping blocks `[::1]`, `[::ffff:127.0.0.1]` and similar loopback variants.

## Request Body

```php
// In a route handler:
$app->addRoute('POST', '/users', function ($app, $params) {
    $body = $app->body;  // Shorthand — reads and JSON-decodes the request body

    // Or with options:
    $body = $app->getBodyRequest(10_485_760, true);  // 10MB limit, enforce JSON Content-Type

    // ... create user ...
    return ['status' => 'created'];
});
```

Default size limit: 10MB. Throws if exceeded.

## HTML Rendering

```php
$html = $app->renderHtml('templates/user.html', [
    '{{NAME}}'  => $user->name,
    '{{EMAIL}}' => $user->email,
]);
```

- Template path is validated (must be within project root, `DIRECTORY_SEPARATOR` appended to root path)
- String values are HTML-escaped by default (prevents XSS)
- Pass `false` as third argument to disable escaping: `$app->renderHtml($file, $data, false)`

## Background Processes

```php
// String mode
$app->execDetach('php process.php');

// Array mode (safe — each argument is properly escaped)
$app->execDetach(['php', 'process.php', '--user', $userId, '--action', 'notify']);
```

Output is logged to `nanocore.log` in the project root.
Output buffering is flushed safely — no errors if no buffer is active.
Windows support via `escapeshellcmd()`.

## Magic Properties

```php
$app->body;    // → reads request body (JSON decoded)
$app->cli;     // → true if running in CLI mode

$app->myVar = 'hello';  // → store custom data
echo $app->myVar;        // → 'hello'
```

## Security

NanoCore has security protections built in:

| Protection | Where | Description |
| --- | --- | --- |
| **SSRF Prevention** | `curlRequest`, `validateUrlNotRestricted`, `validateIpNotRestricted` | Only http/https URLs. Blocks private IPs, localhost, restricted ranges, IPv6 brackets. DNS resolution checked. `FOLLOWLOCATION` disabled. |
| **CRLF Injection** | `redirect()` | CR/LF characters stripped from redirect URLs |
| **Path Traversal** | `renderHtml()` | `DIRECTORY_SEPARATOR` appended to root path prevents traversal |
| **SQL Injection** | NanoORM | All identifiers validated. Field names backtick-quoted in queries. PDO prepared statements for all values. |
| **XSS Prevention** | `renderHtml` | HTML-escaping enabled by default. Path traversal blocked. |
| **Config Tampering** | `configSet` | Protected keys (`PHP.INI`, `CORE`) cannot be modified. Atomic file writes. Internal double quotes escaped in `saveConfig()`. |
| **Command Injection** | `execDetach` | Array mode escapes each argument independently. Windows support with `escapeshellcmd()`. |
| **Arbitrary ini_set** | Constructor | Only 13 safe PHP directives are allowed. |
| **Error Disclosure** | Error handlers | No file paths or line numbers in error responses. |
| **Throwable Catching** | `run()` | Catches `\Throwable` not just `\Exception` |
| **Credential Logging** | `curlRequest` | Credentials stripped from logged URLs |
| **Response Log Truncation** | `curlRequest` | Response body truncated to 500 chars in logs |

### Known Limitations

- **DNS rebinding TOCTOU**: DNS resolution is checked before the request, but the actual connection may resolve to a different IP. This is a known limitation of client-side SSRF validation.

## Error Handling

NanoCore registers custom handlers on construction:

- All PHP errors are converted to `ErrorException`
- Uncaught exceptions return JSON with appropriate HTTP status
- No internal paths leaked in responses
- All JSON responses use `JSON_THROW_ON_ERROR` to prevent silent encoding failures
- `run()` catches `\Throwable` (not just `\Exception`)

In route handlers, throw exceptions with HTTP codes:

```php
throw new \Exception('Not found', 404);
throw new \Exception('Unauthorized', 401);
throw new \Exception('Server error', 500);
```

## License

GPL-3.0 — see [LICENSE](https://www.gnu.org/licenses/gpl-3.0.html)