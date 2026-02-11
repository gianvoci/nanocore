## NanoCore

NanoCore is a lightweight PHP library designed to handle routing, global variables, and utility functions in a simple and efficient manner. Ideal for lightweight projects, NanoCore provides a fast and intuitive solution without burdening your applications.

## Key Features

* Lightweight Routing: Define and manage routes with ease.
* Global Variables Management: Access and manipulate global variables securely.
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
