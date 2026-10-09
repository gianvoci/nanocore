> Read this when you add or modify a test in `tests/cases/`.

# Add a Test

Commands: [commands.md](../commands.md#tests).

1. Pick the file: `{Feature}Test.php`, `{Feature}EdgeCasesTest.php`, or a new `{SubFeature}Test.php` (auto-discovered by `runAllTests.php`).
2. New file boilerplate (no PHPUnit, no variations):

```php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../TestHelpers.php';

use NanoCore\{NanoCore, NanoORM};

$tests = [];

// Test 1: <what it validates>
$tests[] = function () {
    [$app, $tmpFile] = createNanoCoreApp();

    try {
        $app->addRoute('GET', '/ping', fn (NanoCore $app) => $app->json(['ok' => true]));
        ob_start();
        $result = runRequest($app, 'GET', '/ping');
        $output = ob_get_clean();
        assertEquals(null, $result, 'Descriptor responses return null');
        assertEquals('{"ok":true}', $output, 'JSON body should be sent');
    } finally {
        restore_error_handler();
        restore_exception_handler();
        unlink($tmpFile);
    }
};

runTests($tests);
```

3. Append to an existing file with the next `// Test N:` number.
4. Setup per area:

| Area | Setup | Cleanup |
| --- | --- | --- |
| NanoCore | `[$app, $tmpFile] = createNanoCoreApp();` | `finally`: `restore_error_handler()`, `restore_exception_handler()`, `unlink($tmpFile)` |
| ORM | `$pdo = createMemoryPDO(); prepareSchema($pdo);` (`users`, `products`, `orders`) | none |
| Templates | `$path = createTempHtml('<p>{{X}}</p>');` | `unlink($path)` |
| Real HTTP | `$server = createTestServer();` (skip if `null`) | `stopTestServer($server)` |
| ini / `$_SERVER` changes | save previous value | restore it |

5. Assertions available: `assertEquals($expected, $actual, $msg)` (strict `!==`), `assertTrue($cond, $msg)`, `assertThrows($class, $message, $callback)` (`''` skips message check). Add new ones to `TestHelpers.php` first if needed.
6. Capture output with `ob_start()` / `ob_get_clean()` (e.g. JSON errors); never `echo` in tests.
7. One independent test per closure: setup → action → assertion; no shared state.
8. Run the file, then the full suite.
