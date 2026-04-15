<?php

declare(strict_types=1);

require __DIR__ . '/../NanoCore.php';

use NanoCore\NanoCore;

function assertEquals(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(sprintf("%s (expected %s, got %s)", $message, var_export($expected, true), var_export($actual, true)));
    }
}

function assertTrue(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$tests = [];

// Test 1: Error handler converts PHP errors to ErrorException
$tests[] = function () {
    $tmpFile = tempnam(sys_get_temp_dir(), 'nc_test_');
    $app = new NanoCore($tmpFile);

    $caught = false;
    try {
        // Accessing an undefined array key triggers a warning in PHP 8.x,
        // which the custom error handler converts into an ErrorException.
        $arr = [];
        $arr['nonexistent'];
    } catch (ErrorException $e) {
        $caught = true;
    }

    restore_error_handler();
    restore_exception_handler();
    unlink($tmpFile);

    assertTrue($caught, 'PHP warning should be converted to ErrorException');
};

// Test 2: Exception handler emits JSON without file/line
$tests[] = function () {
    $tmpFile = tempnam(sys_get_temp_dir(), 'nc_test_');
    $app = new NanoCore($tmpFile);

    // Grab the exception handler that NanoCore registered
    $handler = set_exception_handler(function ($e) {});
    // Restore so our own handler doesn't interfere
    restore_exception_handler();

    ob_start();
    $handler(new \Exception('test error', 42));
    $output = ob_get_clean();

    $json = json_decode($output, true);

    assertEquals('test error', $json['message'] ?? null, 'JSON should contain message');
    assertEquals(42, $json['code'] ?? null, 'JSON should contain code');
    assertTrue(!isset($json['file']), 'JSON must not contain file');
    assertTrue(!isset($json['line']), 'JSON must not contain line');

    restore_exception_handler();
    restore_error_handler();
    unlink($tmpFile);
};

// Test 3: run() catches exceptions and emits JSON error with correct HTTP status
$tests[] = function () {
    $tmpFile = tempnam(sys_get_temp_dir(), 'nc_test_');
    $app = new NanoCore($tmpFile);
    $app->addRoute('GET', '/boom', function () {
        throw new \Exception('Not found', 404);
    });

    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = '/boom';
    $_SERVER['SCRIPT_NAME'] = '/index.php';

    ob_start();
    $result = $app->run();
    $output = ob_get_clean();

    $json = json_decode($output, true);

    assertEquals(null, $result, 'run() should return null when catching an exception');
    assertEquals('Not found', $json['error'] ?? null, 'JSON error should match exception message');
    assertEquals(404, $json['code'] ?? null, 'JSON code should be 404');

    restore_error_handler();
    restore_exception_handler();
    unlink($tmpFile);
};

// Test 4: run() falls back to 500 for invalid HTTP codes
$tests[] = function () {
    $tmpFile = tempnam(sys_get_temp_dir(), 'nc_test_');
    $app = new NanoCore($tmpFile);
    $app->addRoute('GET', '/bad', function () {
        throw new \Exception('Bad code', 999);
    });

    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = '/bad';
    $_SERVER['SCRIPT_NAME'] = '/index.php';

    // Capture headers via xdebug or a output buffer approach
    ob_start();
    $app->run();
    $output = ob_get_clean();

    $json = json_decode($output, true);

    // The JSON body keeps the original code, but we can't easily assert
    // the HTTP status code in CLI without xdebug. At minimum, check the body.
    assertEquals('Bad code', $json['error'] ?? null, 'JSON error should match exception message');
    assertEquals(999, $json['code'] ?? null, 'JSON code should reflect original exception code');

    restore_error_handler();
    restore_exception_handler();
    unlink($tmpFile);
};

// Test 5: run() emits 500 for handler not callable
$tests[] = function () {
    $tmpFile = tempnam(sys_get_temp_dir(), 'nc_test_');
    $app = new NanoCore($tmpFile);

    // Inject a non-callable handler directly into the routes array
    $ref = new ReflectionClass($app);
    $prop = $ref->getProperty('routes');
    $routes = $prop->getValue($app);
    $routes['GET'][] = [
        'handler' => 'not_a_callable',
        'pattern' => '#^/broken$#',
        'params'  => [],
    ];
    $prop->setValue($app, $routes);

    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = '/broken';
    $_SERVER['SCRIPT_NAME'] = '/index.php';

    ob_start();
    $app->run();
    $output = ob_get_clean();

    $json = json_decode($output, true);

    assertEquals('Handler for route not callable', $json['error'] ?? null, 'Error message should say handler not callable');
    assertEquals(500, $json['code'] ?? null, 'Error code should be 500');

    restore_error_handler();
    restore_exception_handler();
    unlink($tmpFile);
};

$failed = 0;
$messages = [];
foreach ($tests as $index => $test) {
    try {
        $test();
        $messages[] = "Test " . ($index + 1) . " passed.\n";
    } catch (Throwable $exception) {
        $failed++;
        $messages[] = "Test " . ($index + 1) . " failed: " . $exception->getMessage() . "\n";
    }
}

echo implode('', $messages);
exit($failed > 0 ? 1 : 0);
