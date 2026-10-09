<?php

declare(strict_types=1);

require_once __DIR__ . '/../TestHelpers.php';

use NanoCore\{NanoCore, NanoORM};

$tests = [];

// Test 1: configSet rejects the protected PHP.INI top-level key
$tests[] = function () {
    [$app, $tmpFile] = createNanoCoreApp();

    try {
        assertThrows(\Exception::class, 'Cannot modify protected config key: PHP.INI', fn () => $app->configSet('PHP.INI.display_errors', '1'));
    } finally {
        restore_error_handler();
        restore_exception_handler();
        unlink($tmpFile);
    }
};

// Test 2: configSet rejects the protected CORE top-level key
$tests[] = function () {
    [$app, $tmpFile] = createNanoCoreApp();

    try {
        assertThrows(\Exception::class, 'Cannot modify protected config key: CORE', fn () => $app->configSet('CORE.X', 'y'));
    } finally {
        restore_error_handler();
        restore_exception_handler();
        unlink($tmpFile);
    }
};

// Test 3: configSet rejects a key containing a line break
$tests[] = function () {
    [$app, $tmpFile] = createNanoCoreApp();

    try {
        assertThrows(
            \InvalidArgumentException::class,
            "Invalid config key: APP.NAME\nPHP.INI.display_errors",
            fn () => $app->configSet("APP.NAME\nPHP.INI.display_errors", 'x')
        );
    } finally {
        restore_error_handler();
        restore_exception_handler();
        unlink($tmpFile);
    }
};

// Test 4: configSet rejects a key containing a space
$tests[] = function () {
    [$app, $tmpFile] = createNanoCoreApp();

    try {
        assertThrows(\InvalidArgumentException::class, 'Invalid config key: APP KEY', fn () => $app->configSet('APP KEY', 'x'));
    } finally {
        restore_error_handler();
        restore_exception_handler();
        unlink($tmpFile);
    }
};

// Test 5: configSet rejects a key containing an equals sign
$tests[] = function () {
    [$app, $tmpFile] = createNanoCoreApp();

    try {
        assertThrows(\InvalidArgumentException::class, 'Invalid config key: A=B', fn () => $app->configSet('A=B', 'x'));
    } finally {
        restore_error_handler();
        restore_exception_handler();
        unlink($tmpFile);
    }
};

// Test 6: configSet rejects a value containing a line feed and never writes the injected line
$tests[] = function () {
    [$app, $tmpFile] = createNanoCoreApp();

    try {
        assertThrows(
            \InvalidArgumentException::class,
            'Config value must not contain line breaks or NUL bytes',
            fn () => $app->configSet('APP.NAME', "x\nPHP.INI.display_errors=1")
        );

        $raw = file_get_contents($tmpFile);
        assertTrue(!str_contains($raw, 'display_errors'), 'Config file must not contain the injected key');
    } finally {
        restore_error_handler();
        restore_exception_handler();
        unlink($tmpFile);
    }
};

// Test 7: configSet rejects a value containing a carriage return and never writes the injected line
$tests[] = function () {
    [$app, $tmpFile] = createNanoCoreApp();

    try {
        assertThrows(
            \InvalidArgumentException::class,
            'Config value must not contain line breaks or NUL bytes',
            fn () => $app->configSet('APP.NAME', "x\rPHP.INI.display_errors=1")
        );

        $raw = file_get_contents($tmpFile);
        assertTrue(!str_contains($raw, 'display_errors'), 'Config file must not contain the injected key');
    } finally {
        restore_error_handler();
        restore_exception_handler();
        unlink($tmpFile);
    }
};

// Test 8: configSet still writes a valid key and value (regression)
$tests[] = function () {
    [$app, $tmpFile] = createNanoCoreApp();

    try {
        $app->configSet('APP.NAME', 'Test App');

        assertEquals('Test App', $app->configGet('APP.NAME'), 'APP.NAME should be stored');
        $raw = file_get_contents($tmpFile);
        assertTrue(str_contains($raw, 'APP.NAME="Test App"'), 'File should contain APP.NAME="Test App" with quoting');
    } finally {
        restore_error_handler();
        restore_exception_handler();
        unlink($tmpFile);
    }
};

// Test 9: check() reports an invalid regex pattern as a validation error instead of throwing
$tests[] = function () {
    [$app, $tmpFile] = createNanoCoreApp();

    try {
        foreach (['abc', '123'] as $value) {
            $result = $app->check(['name' => $value], ['name' => 'regex:[']);

            assertEquals(false, $result['valid'], 'Invalid regex pattern should make validation fail');
            assertTrue(
                str_contains($result['errors']['name'][0] ?? '', 'has invalid regex pattern'),
                'Error should report an invalid regex pattern'
            );
        }
    } finally {
        restore_error_handler();
        restore_exception_handler();
        unlink($tmpFile);
    }
};

// Test 10: A warning suppressed with @ does not throw once NanoCore handlers are installed
$tests[] = function () {
    [, $tmpFile] = createNanoCoreApp();

    try {
        $content = @file_get_contents('/nonexistent/' . uniqid());

        assertTrue($content === false, 'Suppressed read should return false without throwing');
    } finally {
        restore_error_handler();
        restore_exception_handler();
        unlink($tmpFile);
    }
};

// Test 11: An unsuppressed warning still throws ErrorException
$tests[] = function () {
    [, $tmpFile] = createNanoCoreApp();

    try {
        assertThrows(ErrorException::class, '', fn () => file_get_contents('/nonexistent/' . uniqid()));
    } finally {
        restore_error_handler();
        restore_exception_handler();
        unlink($tmpFile);
    }
};

// Test 12: Exception handler exposes 5xx messages by default (no CORE.EXPOSE_ERRORS in .env)
$tests[] = function () {
    [, $tmpFile] = createNanoCoreApp();

    try {
        $handler = set_exception_handler(function ($e) {});
        restore_exception_handler();

        ob_start();
        $handler(new \Exception('db password leak'));
        $json = json_decode(ob_get_clean(), true);

        assertEquals('db password leak', $json['error'] ?? null, 'Default behaviour should expose the 5xx message');
        assertEquals(500, $json['code'] ?? null, 'Code should be 500');
    } finally {
        restore_error_handler();
        restore_exception_handler();
        unlink($tmpFile);
    }
};

// Test 13: CORE.EXPOSE_ERRORS=false hides the message of a 5xx exception and logs it as a single line
$tests[] = function () {
    $configDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'nc_sec_' . uniqid();
    mkdir($configDir);
    $tmpFile = $configDir . DIRECTORY_SEPARATOR . '.env';
    $logFile = $configDir . DIRECTORY_SEPARATOR . 'nanocore.log';
    file_put_contents($tmpFile, "CORE.EXPOSE_ERRORS=false\n");

    try {
        new NanoCore($tmpFile);

        $handler = set_exception_handler(function ($e) {});
        restore_exception_handler();

        ob_start();
        $handler(new \Exception('db password leak'));
        $json = json_decode(ob_get_clean(), true);

        assertEquals('Internal Server Error', $json['error'] ?? null, 'Hidden 5xx message should be generic');
        assertEquals(500, $json['code'] ?? null, 'Code should be 500');

        $log = file_get_contents($logFile);
        assertTrue(str_contains($log, 'db password leak'), 'Log should keep the original message');
        assertEquals(1, count(explode("\n", trim($log))), 'Log entry should be a single line');

        unlink($logFile);
        ob_start();
        $handler(new \Exception("leak\nFORGED", 500));
        ob_end_clean();

        $log = file_get_contents($logFile);
        $forgedLines = array_filter(explode("\n", $log), fn ($line) => str_starts_with($line, 'FORGED'));
        assertEquals([], array_values($forgedLines), 'Line breaks in the message must not forge a log line');
    } finally {
        restore_error_handler();
        restore_exception_handler();
        if (file_exists($logFile)) {
            unlink($logFile);
        }
        unlink($tmpFile);
        rmdir($configDir);
    }
};

// Test 14: CORE.EXPOSE_ERRORS=false still exposes the message of a 4xx exception
$tests[] = function () {
    $tmpFile = tmpConfigPath();
    file_put_contents($tmpFile, "CORE.EXPOSE_ERRORS=false\n");

    try {
        new NanoCore($tmpFile);

        $handler = set_exception_handler(function ($e) {});
        restore_exception_handler();

        ob_start();
        $handler(new \Exception('Not found', 404));
        $json = json_decode(ob_get_clean(), true);

        assertEquals('Not found', $json['error'] ?? null, '4xx message should remain visible');
        assertEquals(404, $json['code'] ?? null, 'Code should be 404');
    } finally {
        restore_error_handler();
        restore_exception_handler();
        unlink($tmpFile);
    }
};

// Test 15: renderHtml accepts a template located in the same directory as the .env file
$tests[] = function () {
    $tmpFile = tmpConfigPath();
    $htmlPath = tempnam(sys_get_temp_dir(), 'nc_html_');

    try {
        $app = new NanoCore($tmpFile);
        file_put_contents($htmlPath, '<h1>{{title}}</h1>');

        $result = $app->renderHtml($htmlPath, ['{{title}}' => 'Hello']);

        assertEquals('<h1>Hello</h1>', $result, 'Template next to the .env file should render');
    } finally {
        restore_error_handler();
        restore_exception_handler();
        unlink($tmpFile);
        unlink($htmlPath);
    }
};

// Test 16: renderHtml rejects a template outside both the working directory and the .env directory
$tests[] = function () {
    $configDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'nc_sec_' . uniqid();
    mkdir($configDir);
    $tmpFile = $configDir . DIRECTORY_SEPARATOR . '.env';
    $htmlPath = tempnam(sys_get_temp_dir(), 'nc_html_');

    try {
        $app = new NanoCore($tmpFile);
        file_put_contents($htmlPath, '<h1>{{title}}</h1>');

        assertThrows(
            \Exception::class,
            'Template file path is outside the allowed directory',
            fn () => $app->renderHtml($htmlPath, ['{{title}}' => 'Hello'])
        );
    } finally {
        restore_error_handler();
        restore_exception_handler();
        if (file_exists($tmpFile)) {
            unlink($tmpFile);
        }
        unlink($htmlPath);
        rmdir($configDir);
    }
};

// Test 17: sessionStart rejects an invalid SESSION.COOKIE_SAMESITE value
$tests[] = function () {
    $tmpFile = tmpConfigPath();
    file_put_contents($tmpFile, "SESSION.COOKIE_SAMESITE=invalid\n");

    try {
        $app = new NanoCore($tmpFile);

        assertThrows(
            \InvalidArgumentException::class,
            'Invalid SESSION.COOKIE_SAMESITE value: Invalid',
            fn () => $app->sessionStart()
        );
    } finally {
        restore_error_handler();
        restore_exception_handler();
        unlink($tmpFile);
    }
};

// Test 18: sessionRegenerate does nothing when no session is active
$tests[] = function () {
    [$app, $tmpFile] = createNanoCoreApp();

    try {
        $app->sessionRegenerate();

        assertTrue(session_status() !== PHP_SESSION_ACTIVE, 'sessionRegenerate should not start or change a session');
    } finally {
        restore_error_handler();
        restore_exception_handler();
        unlink($tmpFile);
    }
};

// Test 19: NanoORM fillOnly ignores fields outside the whitelist
$tests[] = function () {
    $pdo = createMemoryPDO();
    prepareSchema($pdo);

    $user = new NanoORM($pdo, 'users');
    $user->fillOnly(['name' => 'A', 'email' => 'a@x.it'], ['name']);
    $data = $user->toArray();

    assertEquals('A', $data['name'] ?? null, 'Whitelisted field should be set');
    assertTrue(!array_key_exists('email', $data), 'Non-whitelisted field should not be set');
};

// Test 20: NanoORM fill sets all fields
$tests[] = function () {
    $pdo = createMemoryPDO();
    prepareSchema($pdo);

    $user = new NanoORM($pdo, 'users');
    $user->fill(['name' => 'A', 'email' => 'a@x.it']);
    $data = $user->toArray();

    assertEquals('A', $data['name'] ?? null, 'Name should be set without whitelist');
    assertEquals('a@x.it', $data['email'] ?? null, 'Email should be set without whitelist');
};

// Test 21: maskSensitiveParams redacts sensitive keys at any depth and keeps other values
$tests[] = function () {
    $method = new ReflectionMethod(NanoCore::class, 'maskSensitiveParams');
    $params = ['password' => 'x', 'api_key' => 'y', 'name' => 'visible', 'nested' => ['token' => 'z', 'city' => 'Rome']];

    $masked = $method->invoke(null, $params);

    assertEquals('***', $masked['password'] ?? null, 'password should be redacted');
    assertEquals('***', $masked['api_key'] ?? null, 'api_key should be redacted');
    assertEquals('visible', $masked['name'] ?? null, 'name should be unchanged');
    assertEquals('***', $masked['nested']['token'] ?? null, 'nested token should be redacted');
    assertEquals('Rome', $masked['nested']['city'] ?? null, 'nested city should be unchanged');
};

// Test 22: configSet rejects the protected PHP.INI section when it is written as a nested array
$tests[] = function () {
    [$app, $tmpFile] = createNanoCoreApp();

    try {
        assertThrows(
            \Exception::class,
            'Cannot modify protected config key: PHP.INI',
            fn () => $app->configSet('PHP', ['INI' => ['display_errors' => '1']])
        );
    } finally {
        restore_error_handler();
        restore_exception_handler();
        unlink($tmpFile);
    }
};

// Test 23: configSet accepts keys that only share a prefix with protected keys
$tests[] = function () {
    [$app, $tmpFile] = createNanoCoreApp();

    try {
        $app->configSet('PHPX.A', '1');
        $app->configSet('CORELIB.A', '1');

        assertEquals('1', $app->configGet('PHPX.A'), 'PHPX.A should be stored');
        assertEquals('1', $app->configGet('CORELIB.A'), 'CORELIB.A should be stored');
    } finally {
        restore_error_handler();
        restore_exception_handler();
        unlink($tmpFile);
    }
};

// Test 24: configSet rejects a nested key containing a space and a nested value containing a line feed
$tests[] = function () {
    [$app, $tmpFile] = createNanoCoreApp();

    try {
        assertThrows(\InvalidArgumentException::class, 'Invalid config key: BAD KEY', fn () => $app->configSet('APP', ['BAD KEY' => 'x']));
        assertThrows(
            \InvalidArgumentException::class,
            'Config value must not contain line breaks or NUL bytes',
            fn () => $app->configSet('APP', ['K' => "a\nb"])
        );
    } finally {
        restore_error_handler();
        restore_exception_handler();
        unlink($tmpFile);
    }
};

// Test 25: An unsuppressed warning still throws ErrorException when error_reporting excludes E_WARNING
$tests[] = function () {
    [, $tmpFile] = createNanoCoreApp();
    $previousReporting = error_reporting(E_ALL & ~E_WARNING);

    try {
        assertThrows(ErrorException::class, '', fn () => file_get_contents('/nonexistent/' . uniqid()));
    } finally {
        error_reporting($previousReporting);
        restore_error_handler();
        restore_exception_handler();
        unlink($tmpFile);
    }
};

// Test 26: renderHtml rejects the .env config file itself as a template
$tests[] = function () {
    $configDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'nc_sec_' . uniqid();
    mkdir($configDir);
    $tmpFile = $configDir . DIRECTORY_SEPARATOR . '.env';

    try {
        file_put_contents($tmpFile, "APP.NAME=Test\n");
        $app = new NanoCore($tmpFile);

        assertThrows(
            \Exception::class,
            'Template file path is outside the allowed directory',
            fn () => $app->renderHtml($tmpFile, [])
        );
    } finally {
        restore_error_handler();
        restore_exception_handler();
        if (file_exists($tmpFile)) {
            unlink($tmpFile);
        }
        rmdir($configDir);
    }
};

// Test 27: CORE.EXPOSE_ERRORS=false still returns a generic 500 when the log file cannot be written
$tests[] = function () {
    $configDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'nc_sec_' . uniqid();
    mkdir($configDir);
    $tmpFile = $configDir . DIRECTORY_SEPARATOR . '.env';
    $logDir = $configDir . DIRECTORY_SEPARATOR . 'nanocore.log';
    file_put_contents($tmpFile, "CORE.EXPOSE_ERRORS=false\n");
    mkdir($logDir);

    try {
        new NanoCore($tmpFile);

        $handler = set_exception_handler(function ($e) {});
        restore_exception_handler();

        ob_start();
        $handler(new \Exception('boom', 500));
        $json = json_decode(ob_get_clean(), true);

        assertEquals('Internal Server Error', $json['error'] ?? null, 'Generic message should be returned when logging fails');
    } finally {
        restore_error_handler();
        restore_exception_handler();
        if (is_dir($logDir)) {
            rmdir($logDir);
        }
        if (file_exists($tmpFile)) {
            unlink($tmpFile);
        }
        rmdir($configDir);
    }
};

// Test 28: sessionStart rejects SESSION.COOKIE_SAMESITE when it is written with sub-keys
$tests[] = function () {
    $tmpFile = tmpConfigPath();
    file_put_contents($tmpFile, "SESSION.COOKIE_SAMESITE.X=1\n");

    try {
        $app = new NanoCore($tmpFile);

        assertThrows(
            \InvalidArgumentException::class,
            'Invalid SESSION.COOKIE_SAMESITE value',
            fn () => $app->sessionStart()
        );
    } finally {
        restore_error_handler();
        restore_exception_handler();
        unlink($tmpFile);
    }
};

runTests($tests);
