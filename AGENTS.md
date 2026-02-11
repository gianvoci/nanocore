## NanoCore

NanoCore is a lightweight PHP library designed to handle routing, global variables, and utility functions in a simple and efficient manner. Ideal for lightweight projects, NanoCore provides a fast and intuitive solution without burdening your applications.

## Key Features

* Lightweight Routing: Define and manage routes with ease.
* Global Variables Management: Access and manipulate global variables securely.
* Lightweight ORM: Simple database operations with `NanoORM` class.
* Utility Functions: Useful integrated functions to simplify daily development tasks.
* High Efficiency: Designed to be fast and consume minimal resources.
* Easy Integration: Easily integrates with any existing PHP project.

## Development Guidelines

* Always use the long `<?php` opening tag so the library loads on default PHP installs.
* Ensure HTTP responses use valid status codes (e.g., fallback to 500) when emitting JSON error payloads.
* Sanitize commands in `ExecDetach()` via `escapeshellcmd`/`escapeshellarg` before invoking `shell_exec`.
* Run `php -l NanoCore.php` after making changes to guard against syntax errors.
* When change NanoCore.php, update AGENTS.md file.
* At end of changes, generate always a git commit comment.

## Core File Reference

* `NanoCore.php` contains the main NanoCore class and provides routing, configuration management, utilities, and helpers used by the framework.

## Routing Behavior

NanoCore expects routes to be registered with `addRoute`, passing both the HTTP method and the full path you intend to match. Internally the library:

* Normalizes paths by collapsing duplicate slashes, ensuring they are prefixed with `/`, and stripping the configured base path (so `subdir/app/test` becomes `/test` when running under `/subdir/app`).
* Stores each handler alongside a regex pattern derived from the normalized path so optional parameters (e.g., `@param`) or wildcards (`@*`) can be captured.
* Uppercases HTTP methods so `get`, `GET`, and `GeT` all map to the same route bucket.

When `run()` dispatches, it applies the same normalization, tries every handler pattern for the current method, and, if a match occurs, passes both query parameters and any extracted path parameters to the handler.

### Parameterized Route Examples

| Route Pattern | Example Request | Extracted Params |
| --- | --- | --- |
| `/users/@id` | `/users/42` | `['id' => '42']` |
| `/path/to/@param/@param2` | `/path/to/one/two` | `['param' => 'one', 'param2' => 'two']` |
| `/files/@*` | `/files/foo/bar.txt` | `['wildcard' => 'foo/bar.txt']` |

Handlers receive `$params` that combine query data with these path parameters. Path segments that are prefixed with `@` capture single path elements; `@*` captures the rest of the path as a wildcard.

## Error and Response Guidelines

* All controllers should throw exceptions when something goes wrong, since NanoCore wraps dispatch in a try/catch and emits a JSON error with the exception message, code, file, and line.
* Throw with a proper HTTP code (e.g., `throw new \Exception('Not allowed', 403)`)—NanoCore defaults to 500 when an exception code is outside the 100–599 range.
* When returning JSON from handlers, ensure you encode and return arrays/objects; NanoCore will `echo` whatever your handler returns.

## Helpful Tips

* Use `configSet`/`configGet` to store application state or feature toggles in `app.json`.
* Rely on `getBodyRequest()` to parse JSON body payloads during POST/PUT requests.
* Sanitize and escape commands before calling `execDetach`, and prefer `curlRequest` for simple external HTTP calls rather than re-implementing cURL setups.

## API Overview

| Method | Description |
| --- | --- |
| `addRoute(string $method, string $path, callable $handler)` | Registers a handler for a normalized HTTP method/path. Paths are automatically normalized and stripped of the detected base path. |
| `run()` | Matches the incoming request against registered routes and dispatches the handler, returning its result or emitting a JSON error response if the route is missing or the handler is not callable. |
| `curlRequest(string $url, array $options = [])` | Makes a simple cURL request with retry logic, optional params/headers, and JSON decoding fallback. |
| `renderHtml(string $filename, array $data = [])` | Loads an HTML template and replaces placeholders with provided data. |
| `execDetach(string $cmd)` | Runs a shell command in the background while logging output; sanitizes inputs before invoking `shell_exec`. |

## Example Usage

```php
use NanoCore\NanoCore;

$app = new NanoCore();

$app->addRoute('GET', '/ping', function () {
    return ['status' => 'ok'];
});

$app->run();
```

```php
$response = NanoCore::curlRequest('https://api.example.com/data', [
    'method' => 'POST',
    'params' => ['foo' => 'bar'],
]);
```

### URI Normalization Examples

| Registered URI | Normalized Storage Path |
| --- | --- |
| `/` | `/` |
| `/ping/` | `/ping` |
| `ping` | `/ping` |
| `/api//health` | `/api/health` |
| `/subdir/app/test` (with base path `/subdir/app`) | `/test` |
| `subdir\app\test` | `/subdir/app/test` |

## NanoORM - Lightweight ORM

NanoORM provides simple database table management with magic getters/setters, CRUD operations, and JOIN support.

### Constructor

```php
$pdo = new PDO('mysql:host=localhost;dbname=mydb', 'user', 'pass');
$user = new NanoORM($pdo, 'users', 'id');
```

### Magic Getters/Setters

Access fields dynamically using magic methods:

```php
$user->name = 'John Doe';      // Set field
$user->email = 'john@example.com';
echo $user->name;              // Get field: 'John Doe'
$user->fill(['name' => 'Jane', 'email' => 'jane@example.com']);
$data = $user->toArray();      // Get all data as array
```

### CRUD Operations

| Method | Description |
| --- | --- |
| `findById($id)` | Retrieve a single record by primary key. Returns `NanoORM` instance or `null`. |
| `findBy(string $field, $value, ?int $limit = null)` | Find records by any field value. Returns array of instances. |
| `findAll(array $conditions = [], string $orderBy = '', ?int $limit = null)` | Find all records with optional conditions, ordering, and limit. |
| `save()` | Insert (if new) or update (if existing) the record. Returns `bool`. |
| `delete()` | Delete the current record by primary key. Returns `bool`. |
| `deleteWhere(array $conditions)` | Delete multiple records by conditions. Returns affected row count. |

### Examples

```php
// Find by ID
$user = (new NanoORM($pdo, 'users'))->findById(42);

// Find by custom field
$activeUsers = (new NanoORM($pdo, 'users'))->findBy('status', 'active');

// Find with conditions
$recent = (new NanoORM($pdo, 'posts'))->findAll(
    ['published' => 1],
    'created_at DESC',
    10
);

// Create new record
$user = new NanoORM($pdo, 'users');
$user->name = 'John Doe';
$user->email = 'john@example.com';
$user->save();  // INSERT

// Update existing record
$user = (new NanoORM($pdo, 'users'))->findById(1);
$user->name = 'Jane Doe';
$user->save();  // UPDATE

// Delete record
$user->delete();

// Delete by condition
$user->deleteWhere(['status' => 'inactive']);
```

### Table Joins

Use `addJoin()` to join multiple tables:

```php
$orders = new NanoORM($pdo, 'orders');
$orders
    ->addJoin('users', 'user_id', 'id', 'INNER', ['name', 'email'])
    ->addJoin('products', 'product_id', 'id', 'LEFT', ['title', 'price']);

$results = $orders->fetchWithJoins(['orders.status' => 'completed']);
```

#### addJoin Parameters

| Parameter | Description |
| --- | --- |
| `$table` | Table name to join |
| `$localKey` | Field in main table |
| `$foreignKey` | Field in joined table |
| `$type` | JOIN type: INNER, LEFT, RIGHT (default: INNER) |
| `$selectFields` | Array of fields to select from joined table (default: ['*']) |

### Utility Methods

| Method | Description |
| --- | --- |
| `getId()` | Get the primary key value |
| `isNew()` | Check if record is new (not yet saved) |
| `getTable()` | Get the table name |
| `clear()` | Reset the object to a fresh state |
| `fill(array $data)` | Bulk set fields from array |
| `toArray()` | Get all data as associative array |

### Complete Example

```php
use NanoCore\NanoCore;
use NanoCore\NanoORM;

$app = new NanoCore();

// Setup database connection
$pdo = new PDO('sqlite:app.db');

$app->addRoute('GET', '/users/@id', function ($app, $params) use ($pdo) {
    $user = (new NanoORM($pdo, 'users'))->findById($params['id']);
    
    if (!$user) {
        throw new \Exception('User not found', 404);
    }
    
    return $user->toArray();
});

$app->addRoute('POST', '/users', function ($app, $params) use ($pdo) {
    $body = $app->body;
    
    $user = new NanoORM($pdo, 'users');
    $user->fill($body);
    $user->save();
    
    return ['id' => $user->getId(), 'message' => 'User created'];
});

$app->run();
```

## NanoORM Class Details

The `NanoORM` class defined in `NanoORM.php` drives the lightweight ORM layer:

* **Construction & Schema**: A `\PDO` instance, table name, and optional primary key land via the constructor, which immediately inspects the table schema (`DESCRIBE` or `PRAGMA table_info`).
* **Data Access**: Magic getters/setters (`__get`, `__set`) respect discovered schema fields and the primary key, while `fill()`, `toArray()`, and `clear()` offer easy manipulation of field data.
* **CRUD & Querying**: `findById`, `findBy`, and `findAll` return hydrated ORM instances that honor optional conditions, ordering, and limits. Inserts, updates, and deletes are managed via `save`, `insert`, `update`, `delete`, and `deleteWhere`, all of which use prepared statements and primary key checks.
* **Joins Support**: `addJoin` configures foreign table joins, while `fetchWithJoins` and `buildSelectQuery` build queries that alias joined tables, expose fields with prefixes, and execute composite selects.
* **Utilities**: Helper methods such as `hydrate`, `getId`, `isNew`, and `getTable` reveal instance state, track persistence, and assist with clarity in controllers or services.

### Usage Examples

```php
$user = (new NanoORM($pdo, 'users'))
    ->fill(['name' => 'Jane Doe', 'email' => 'jane@example.com']);
$user->save(); // insert or update depending on isNew()

$found = (new NanoORM($pdo, 'users'))->findById(1);
if ($found) {
    $found->email = 'jane.roe@example.com';
    $found->save();
}

$activeUsers = (new NanoORM($pdo, 'users'))->findBy('status', 'active', 10);

$orders = (new NanoORM($pdo, 'orders'))
    ->addJoin('users', 'user_id', 'id', 'INNER', ['name'])
    ->addJoin('products', 'product_id', 'id', 'LEFT', ['title']);
$joinedResults = $orders->fetchWithJoins(['orders.status' => 'completed']);
```
