> Read this when writing or reviewing tests for this library.

# Testing Conventions

## File Structure & Naming

- Test files go in `tests/cases/`
- Naming patterns:
  - `{Feature}Test.php` — main tests (e.g. `RoutesTest.php`, `ConfigTest.php`, `ORMTest.php`)
  - `{Feature}EdgeCasesTest.php` — edge case tests in separate file (e.g. `RouteEdgeCasesTest.php`, `ORMEdgeCasesTest.php`)
  - `{SubFeature}Test.php` — dedicated sub-feature tests (e.g. `JoinTest.php`)
- Comment before each test: `// Test N: Description` — sequential numbering per file, describes what the test validates; early tests sometimes omit comments, the convention is to use them but it's not strictly enforced on every test

## Required Boilerplate

Every test file must follow this exact structure in order:

```php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../TestHelpers.php';

use NanoCore\{ClassName};

$tests = [];

// Test 1: ...

$tests[] = function () {
    // setup → action → assertion
};

runTests($tests);
```

- No variations. No PHPUnit. Each file is a standalone runner.

## Setup Patterns

Three patterns exist depending on what's being tested:

**NanoCore tests** (routing, utilities, error handling):

```php
[$app, $tmpFile] = createNanoCoreApp();
// ... use $app ...
unlink($tmpFile);
```

**Config tests** (direct instantiation):

```php
$tmpFile = tmpConfigPath();
$app = new NanoCore($tmpFile);
// ... use $app ...
unlink($tmpFile);
```

**ORM tests** (database operations, joins):

```php
$pdo = createMemoryPDO();
prepareSchema($pdo);
// ... use $pdo with NanoORM ...
// No cleanup needed — in-memory SQLite dies with the process
```

## Cleanup Rules

- **Temp config files**: always `unlink($tmpFile)` at the end of the test
- **Global handlers** (error/exception): wrap in `try/finally` and call `restore_error_handler()` + `restore_exception_handler()` in the `finally` block, plus `unlink($tmpFile)`
- **Output buffering**: two patterns — `ob_start()` / `ob_get_clean()` to capture output for assertion; `ob_start()` / `ob_end_clean()` to discard output (e.g. for `execDetach` smoke tests)
- **Superglobals** (`$_SERVER`): should be restored or `unset` when possible. Tests using `runRequest()` don't need manual restoration (the helper sets them automatically), but direct `$_SERVER` modifications outside `runRequest()` should still be cleaned up
- **ORM in-memory**: no cleanup needed — the PDO connection dies with the process
- **Temp HTML files**: always `unlink($htmlPath)` at the end
- **PHP ini settings**: save before modifying, restore after — `$previous = ini_get('key'); …; ini_set('key', $previous);`
- **Companion temp files** (e.g. `.env.local`): use guarded cleanup — `if (file_exists($localFile)) { unlink($localFile); }`

## Available Assertions

Only 3 assertion functions exist in `TestHelpers.php`. Do not invent new ones without adding them to TestHelpers first.

| Function | Purpose | Behavior |
| --- | --- | --- |
| `assertEquals($expected, $actual, $message)` | Strict equality check | Uses `!==` comparison. Throws `RuntimeException` with expected/actual diff on failure. |
| `assertTrue($condition, $message)` | Boolean condition check | Throws `RuntimeException` on false. |
| `assertThrows($exceptionClass, $expectedMessage, $callback)` | Exception assertion | Catches `Throwable`. Verifies class and message. Empty `''` expected message skips message check. Throws `RuntimeException` if no exception was thrown. |

## Helper Functions

Non-assertion helpers from `TestHelpers.php`:

| Function | Purpose |
| --- | --- |
| `runRequest(NanoCore $app, string $method, string $path, array $query = [])` | Sets `$_SERVER` vars for method/URI and calls `$app->run()` |
| `tmpConfigPath(): string` | Generates a unique temp file path for `.env` configs |
| `createNanoCoreApp(): array` | Returns `[$app, $tmpFile]` — NanoCore instance + temp config path |
| `createTempHtml(string $content): string` | Writes HTML content to a temp file and returns its path |
| `prepareSchema(PDO $pdo): void` | Creates `users`, `products`, and `orders` tables |
| `createMemoryPDO(): PDO` | Creates an in-memory SQLite PDO with exception error mode |

## Isolation & Quality Rules

- **Zero shared state** — every test creates its own instances (NanoCore app, PDO connection, temp files)
- **Top-to-bottom flow** — each test follows: setup → action → assertion
- **Multiple assertions per test** are acceptable
- **Test ordering** — within a file, order by complexity: basic → advanced → edge cases
- **Each test is independent** — no test depends on the outcome of another test in the same file

## Common Mistakes to Avoid

- Forgetting to `unlink($tmpFile)` — creates temp file leaks
- Not restoring global error/exception handlers with `try/finally` — corrupts subsequent tests
- Using assertions that don't exist (e.g. `assertNull`, `assertCount`) — only the 3 listed above are available
- Modifying `$_SERVER` without restoring or cleaning up — can leak state between tests
- Using `echo` or `print` in tests — output interferes with the test runner's pass/fail detection
- Creating ORM instances without `prepareSchema()` first — tables don't exist
