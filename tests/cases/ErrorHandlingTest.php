<?php

declare(strict_types=1);

require_once __DIR__ . '/../TestHelpers.php';

use NanoCore\NanoCore;

$tests = [];

// Test 1: Error handler converts PHP errors to ErrorException
$tests[] = function () {
    [$app, $tmpFile] = createNanoCoreApp();

    try {
        $caught = false;
        try {
            // Accessing an undefined array key triggers a warning in PHP 8.x,
            // which the custom error handler converts into an ErrorException.
            $arr = [];
            $arr['nonexistent'];
        } catch (ErrorException $e) {
            $caught = true;
        }

        assertTrue($caught, 'PHP warning should be converted to ErrorException');
    } finally {
        restore_error_handler();
        restore_exception_handler();
        unlink($tmpFile);
    }
};

// Test 2: Exception handler emits JSON without file/line
$tests[] = function () {
    [$app, $tmpFile] = createNanoCoreApp();

    try {
        // Grab the exception handler that NanoCore registered
        $handler = set_exception_handler(function ($e) {});
        // Restore so our own handler doesn't interfere
        restore_exception_handler();

        ob_start();
        $handler(new \Exception('test error', 42));
        $output = ob_get_clean();

        $json = json_decode($output, true);

        assertEquals('test error', $json['error'] ?? null, 'JSON should contain error message');
        assertEquals(500, $json['code'] ?? null, 'JSON code should be clamped to 500');
        assertTrue(!isset($json['file']), 'JSON must not contain file');
        assertTrue(!isset($json['line']), 'JSON must not contain line');
    } finally {
        restore_exception_handler();
        restore_error_handler();
        unlink($tmpFile);
    }
};

// Test 3: run() catches exceptions and emits JSON error with correct HTTP status
$tests[] = function () {
    [$app, $tmpFile] = createNanoCoreApp();
    $app->addRoute('GET', '/boom', function () {
        throw new \Exception('Not found', 404);
    });

    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = '/boom';
    $_SERVER['SCRIPT_NAME'] = '/index.php';

    try {
        ob_start();
        $result = $app->run();
        $output = ob_get_clean();

        $json = json_decode($output, true);

        assertEquals(null, $result, 'run() should return null when catching an exception');
        assertEquals('Not found', $json['error'] ?? null, 'JSON error should match exception message');
        assertEquals(404, $json['code'] ?? null, 'JSON code should be 404');
    } finally {
        restore_error_handler();
        restore_exception_handler();
        unlink($tmpFile);
    }
};

// Test 4: run() falls back to 500 for invalid HTTP codes
$tests[] = function () {
    [$app, $tmpFile] = createNanoCoreApp();
    $app->addRoute('GET', '/bad', function () {
        throw new \Exception('Bad code', 999);
    });

    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = '/bad';
    $_SERVER['SCRIPT_NAME'] = '/index.php';

    try {
        // Capture headers via xdebug or a output buffer approach
        ob_start();
        $app->run();
        $output = ob_get_clean();

        $json = json_decode($output, true);

        // The JSON body uses the clamped status code (500 for out-of-range codes).
        // We can't easily assert the HTTP status code in CLI without xdebug.
        assertEquals('Bad code', $json['error'] ?? null, 'JSON error should match exception message');
        assertEquals(500, $json['code'] ?? null, 'JSON code should be clamped to 500');
    } finally {
        restore_error_handler();
        restore_exception_handler();
        unlink($tmpFile);
    }
};

// Test 5: run() emits 500 for handler not callable
$tests[] = function () {
    [$app, $tmpFile] = createNanoCoreApp();

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

    try {
        ob_start();
        $app->run();
        $output = ob_get_clean();

        $json = json_decode($output, true);

        assertEquals(500, $json['code'] ?? null, 'Error code should be 500');
    } finally {
        restore_error_handler();
        restore_exception_handler();
        unlink($tmpFile);
    }
};

runTests($tests);
