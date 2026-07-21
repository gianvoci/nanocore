# NanoCore

A lightweight PHP micro-framework with routing, config management, a micro ORM, and utility functions. Zero dependencies, PHP 8.5+.

## Features

- Pattern-based routing with path parameters and wildcards
- Response methods: `$app->json()`, `$app->html()`, `$app->redirect()`
- Middleware pipeline with `$next` chaining, route and method passed to callbacks
- Input validation with 10 built-in rules
- Event system with built-in lifecycle events
- CLI command registration and auto-detection
- Session management with config-driven settings
- Env-based config management with dot-notation access
- NanoORM: lightweight ORM with CRUD, joins, pagination, transactions, migrations
- HTTP client with retry and SSRF protection
- Request body parser with size limit, cache, and Content-Type auto-detect
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
    return $app->json(['message' => 'Hello, NanoCore!']);
});

$app->addRoute('GET', '/users/@id', function ($app, $params) {
    return $app->json(['user_id' => $params['id']]);
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
return $app->json(['user' => $user], 201);

// HTML response with raw content
return $app->html('<h1>Hello</h1>', 200);

// Redirect (CRLF stripped from URL for security)
return $app->redirect('/login', 302);
```

| Method | Signature | Description |
| --- | --- | --- |
| `json()` | `json($data, $status = 200, array $headers = [])` | JSON response with Content-Type header |
| `html()` | `html(string $content, int $status = 200, array $headers = [])` | Raw HTML response (not a template path) |
| `redirect()` | `redirect($url, $status = 302)` | Redirect header (CR/LF stripped) |

For template rendering with XSS escaping, use `$app->renderHtml()` (see [HTML Rendering](#html-rendering) section).

To send a JSON response, use `$app->json()` or return a `__nc_response` descriptor. Returning a plain array does NOT auto-encode — it is returned to the caller without HTTP output.

## Middleware

```php
// First registered = first executed
$app->addMiddleware(function ($app, $params, $next, $route, $method) {
    // $route = resolved path (e.g. '/api/auth/login')
    // $method = HTTP method (e.g. 'POST')
    
    // Before handler
    $response = $next($app, $params);
    // After handler
    return $response;
});
```

- Middleware wraps handlers in reverse order (last registered = innermost)
- `$next` continues the chain to the next middleware or the route handler
- `$route` and `$method` are passed as additional parameters — middleware that doesn't use them still works

## Input Validation

```php
// Throws Exception(422) on failure
$validated = $app->validate($data, [
    'name'  => 'required|string',
    'email' => 'required|email',
    'age'   => 'integer|min:0|max:150',
]);

// Returns result array — no exception
$result = $app->check($data, ['email' => 'required|email']);
// $result = ['valid' => bool, 'errors' => array, 'data' => array]
```

### Built-in Rules

| Rule | Param | Example | Description |
| --- | --- | --- | --- |
| `required` | — | `required` | Field must be present and non-empty |
| `integer` | — | `integer` | Must be a whole number (numeric + no fractional part) |
| `numeric` | — | `numeric` | Must be numeric (int or float) |
| `string` | — | `string` | Must be a string |
| `email` | — | `email` | Must be a valid email address |
| `url` | — | `url` | Must be a valid URL |
| `min` | `:value` | `min:1` | Numeric: >= value. String: >= length |
| `max` | `:value` | `max:100` | Numeric: <= value. String: <= length |
| `regex` | `:pattern` | `regex:^[a-z]+$` | Must match regex (auto-wrapped in `/` delimiters) |
| `in` | `:values` | `in:active,pending` | Must be one of the comma-separated values |

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

CLI mode is detected automatically — `run()` delegates to `runCli()` when `php_sapi_name() === 'cli'` AND at least one command is registered. With no commands registered in CLI, `run()` falls through to the HTTP path. Exits with code 1 on unknown command.

Command names must match `/^[a-zA-Z0-9:_-]+$/` — invalid names throw `InvalidArgumentException`.

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
| `SESSION.COOKIE_HTTPONLY` | HTTP-only cookie (no JS access) |
| `SESSION.COOKIE_SECURE` | HTTPS-only cookie |
| `SESSION.USE_STRICT_MODE` | Reject uninitialized session IDs |

Only these 3 keys are read by `sessionStart()`. Other `SESSION.*` keys (e.g. `AUTO_START`, `NAME`, `LIFETIME`, `PATH`, `DOMAIN`) are not yet implemented.

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

A `.env.example` file is included as a minimal template with common settings (DB.*, PHP.INI.*) commented out. See the Configuration section below for the full list of available keys.

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

// findBy/findAll return associative arrays, not ORM instances
$actives = (new NanoORM($pdo, 'users'))->findBy('status = ?', ['active'], 10);
$recent = (new NanoORM($pdo, 'posts'))->findAll(
    'published = ?',
    [1],
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
(new NanoORM($pdo, 'users'))->deleteWhere('status = ?', ['inactive']);
```

### Pagination

```php
$result = (new NanoORM($pdo, 'users'))->paginate(1, 25, 'status = ?', ['active'], 'name ASC');
// $result = [
//     'data'     => [...],  // associative arrays
//     'total'    => 142,
//     'page'     => 1,
//     'per_page' => 25,
//     'last_page' => 6,
// ]
```

Validates `page` and `perPage` >= 1.

Note: `paginate()` throws `LogicException` if JOINs are registered via `addJoin()`. Use `fetchWithJoins()` for paginated join queries (or call `clear()` first).

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

File naming: `{digits}_{name}.sql` (e.g. `2026_01_01_00_00_00_init.sql` or `1_init.sql`). The prefix must be digits followed by an underscore; the name must be alphanumeric + underscores. The `YYYY_MM_DD_HH_MM_SS` format is the recommended convention but not enforced.

Rollback files: stored in a `rollback/` subdirectory with the SAME filename as the migration (e.g. `migrations/rollback/2026_01_01_00_00_00_init.sql`), NOT a `_rollback.sql` suffix. Rollback files are required for rollback to work.

Driver detection for SQLite vs MySQL. Invalid file names throw `InvalidArgumentException`.

Note: Migration SQL files are split on `;` — avoid semicolons inside string literals or comments, as the splitter is naive and will break the statement.

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
| `deleteWhere($where, $params)` | `int` | Delete matching records with WHERE clause, returns affected rows |
| `findById($id)` | `self\|null` | Find by primary key |
| `findBy($where, $params, $limit)` | `array<array>` | Find with WHERE clause, returns associative arrays |
| `findAll($where, $params, $orderBy, $limit)` | `array<array>` | Find with WHERE, order, limit — returns associative arrays |
| `paginate($page, $perPage, $where, $params, $orderBy)` | `array` | Paginated results with metadata |
| `addJoin($table, $local, $foreign, $type, $fields)` | `self` | Register a JOIN |
| `fetchWithJoins(array $conditions)` | `array` | Execute query with registered JOINs (associative array conditions) |
| `beginTransaction()` | `void` | Start a transaction |
| `commit()` | `void` | Commit current transaction |
| `rollback()` | `void` | Rollback current transaction |
| `transaction(callable)` | `mixed` | Run callable with auto-rollback on `\Throwable` |
| `toArray()` | `array` | Get all field data |
| `fromArray(array $row)` | `self` | Hydrate from associative array (validates fields) |
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

// Get response with metadata (status code, content type, headers)
$info = NanoCore::curlRequest('https://api.example.com/users', [
    'with_info' => true,
]);
// $info = ['body' => ..., 'status' => 200, 'content_type' => 'application/json', 'headers' => [...]]
```

**Options:**

| Key | Type | Default | Description |
| --- | --- | --- | --- |
| `method` | string | `'GET'` | HTTP method (case-insensitive) |
| `params` | array | `[]` | Request parameters (query string for GET, POST body otherwise) |
| `headers` | array | `[]` | HTTP headers |
| `raw` | bool | `false` | Skip JSON decoding, return raw string |
| `with_info` | bool | `false` | Return `['body'=>mixed,'status'=>int,'content_type'=>string|null,'headers'=>array<string,string[]>]` instead of just the body |
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

### Batch Requests

`curlRequest` accepts an array of URLs for parallel execution via `curl_multi`:

```php
$results = NanoCore::curlRequest([
    'https://api.example.com/users',
    'https://api.example.com/posts',
    'https://api.example.com/comments',
]);
// $results = [body1, body2, body3] — in input order
```

- Max 10 concurrent handles; excess URLs are queued and processed as slots open
- Per-URL retry (5 attempts, linear backoff 100ms-400ms) — non-blocking, timestamp-based
- Batch failures return `Exception` objects in the results array (batch continues, does not abort)
- Empty array `[]` returns `[]` immediately
- All options (`with_info`, `raw`, `method`, `params`, `headers`, CURLOPT_*) apply to every URL in the batch

```php
$results = NanoCore::curlRequest([
    'https://httpbin.org/get',
    'http://nonexistent.invalid',  // will fail
], ['with_info' => true]);
// $results[0] = ['body' => '...', 'status' => 200, 'content_type' => 'application/json', 'headers' => [...]]
// $results[1] = Exception('External request failed')
```

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
    $body = $app->body;  // Shorthand — reads and decodes the request body

    // Body is cached — subsequent calls return the same result
    // Auto-detects Content-Type: JSON, form-urlencoded, multipart/form-data

    // Or with options:
    $body = $app->getBodyRequest(10_485_760, true);  // 10MB limit, enforce JSON Content-Type

    // ... create user ...
    return ['status' => 'created'];
});
```

Default size limit: 10MB. Throws if exceeded.

When `validateContentType=true`, JSON is enforced ONLY if a Content-Type header is present. An empty Content-Type bypasses the check and falls back to auto-detection.

### Content-Type Auto-Detection

| Content-Type | Result |
| --- | --- |
| `application/json` | JSON-decoded array |
| `application/x-www-form-urlencoded` | `parse_str` result |
| `multipart/form-data` | `$_POST` |
| Other / absent | Try JSON, fallback to raw string |

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

On Windows, array mode uses `escapeshellarg` per element; string mode uses `escapeshellcmd`. On non-Windows, array mode uses `escapeshellcmd` on the program name and `escapeshellarg` on each argument; string mode uses `escapeshellcmd`.

## Magic Properties

```php
$app->body;    // → reads request body (cached, auto-detects Content-Type)
$app->cli;     // → true if running in CLI mode

$app->myVar = 'hello';  // → store custom data
echo $app->myVar;        // → 'hello'

// Fail-fast property access — throws RuntimeException if not configured
$pdo = $app->require('pdo');        // Returns stored value
$body = $app->require('body');      // Returns parsed body (virtual property)
$cli = $app->require('cli');        // Returns bool (virtual property)
$app->require('nonexistent');       // → RuntimeException
```

## Security

NanoCore has security protections built in:

| Protection | Where | Description |
| --- | --- | --- |
| **SSRF Prevention** | `curlRequest`, `validateUrlNotRestricted`, `validateIpNotRestricted` | Only http/https URLs. Blocks private IPs, localhost, restricted ranges, IPv6 brackets. DNS resolution checked. `FOLLOWLOCATION` disabled. |
| **CRLF Injection** | `redirect()` | CR/LF characters stripped from redirect URLs |
| **Path Traversal** | `renderHtml()` | `DIRECTORY_SEPARATOR` appended to root path prevents traversal |
| **SQL Injection** | NanoORM | All identifiers validated. Field names backtick-quoted in queries. PDO prepared statements for all values. Note: `findBy()`/`findAll()`/`deleteWhere()` accept arbitrary WHERE — caller must ensure safe SQL. |
| **XSS Prevention** | `renderHtml` | HTML-escaping enabled by default. Path traversal blocked. |
| **Config Tampering** | `configSet` | Protected keys (`PHP.INI`, `CORE`) cannot be modified. Atomic file writes. Internal double quotes escaped in `saveConfig()`. |
| **Command Injection** | `execDetach` | Array mode escapes each argument independently via `escapeshellarg`. String mode uses `escapeshellcmd`. |
| **Arbitrary ini_set** | Constructor | Only 13 safe PHP directives are allowed. |
| **Error Disclosure** | Error handlers | No file paths or line numbers in error responses. |
| **Throwable Catching** | `run()` | Catches `\Throwable` not just `\Exception` |
| **Credential Logging** | `curlRequest` | Credentials stripped from logged URLs |
| **Response Log Truncation** | `curlRequest` | Response body truncated to 500 chars in logs |

### Known Limitations

- **DNS rebinding TOCTOU**: DNS resolution is checked before the request, but the actual connection may resolve to a different IP. This is a known limitation of client-side SSRF validation.
- **`executeSqlFile` naive `;` split**: Migration SQL files are split on `;` — semicolons inside string literals or comments will break the statement.
- **`paginate()` throws if JOINs registered**: Pagination does not support joined queries. Use `fetchWithJoins()` or call `clear()` first.
- **`CURLOPT_MAXREDIRS` ineffective**: `CURLOPT_MAXREDIRS=5` is set in defaults but has no effect because `CURLOPT_FOLLOWLOCATION` is force-disabled for SSRF safety. Redirects are NOT followed.
- **`getBodyRequest` JSON enforcement bypassed on empty Content-Type**: When `validateContentType=true`, JSON is enforced only if a Content-Type header is present. An empty Content-Type bypasses the check.

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

GPL-3.0-or-later — see [LICENSE](https://www.gnu.org/licenses/gpl-3.0.html)
