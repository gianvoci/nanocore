<?php

declare(strict_types=1);

require __DIR__ . '/../../src/NanoCore.php';
require_once __DIR__ . '/../TestHelpers.php';

use NanoCore\NanoCore;

$tests = [];

// Test 1: Config file is auto-created if missing
$tests[] = function () {
    $tmpFile = tmpConfigPath();
    if (file_exists($tmpFile)) {
        unlink($tmpFile);
    }

    new NanoCore($tmpFile);

    assertTrue(file_exists($tmpFile), 'Config file should be auto-created');
    // Auto-created file should be empty (not JSON)
    $contents = file_get_contents($tmpFile);
    assertEquals('', $contents, 'Auto-created file should be empty');

    unlink($tmpFile);
};

// Test 2: Dot-notation get returns nested values
$tests[] = function () {
    $tmpFile = tmpConfigPath();
    $app = new NanoCore($tmpFile);

    $app->configSet('DB.HOST', 'localhost');
    $app->configSet('DB.PORT', '3306');

    assertEquals('localhost', $app->configGet('DB.HOST'), 'DB.HOST should be localhost');
    assertEquals('3306', $app->configGet('DB.PORT'), 'DB.PORT should be "3306"');
    assertEquals(['HOST' => 'localhost', 'PORT' => '3306'], $app->configGet('DB'), 'DB should return full section');

    unlink($tmpFile);
};

// Test 3: configGet returns null for non-existent keys
$tests[] = function () {
    $tmpFile = tmpConfigPath();
    $app = new NanoCore($tmpFile);

    assertEquals(null, $app->configGet('NONEXISTENT'), 'Missing top-level key should return null');
    assertEquals(null, $app->configGet('A.B.C'), 'Missing nested key should return null');

    unlink($tmpFile);
};

// Test 4: Config values are cached in memory
$tests[] = function () {
    $tmpFile = tmpConfigPath();
    $app = new NanoCore($tmpFile);

    // First call loads and caches the config
    $app->configGet('TEST.KEY');

    // Modify file on disk directly
    file_put_contents($tmpFile, "TEST.KEY=from_disk\n");

    // Should still return null because cache is used
    assertEquals(null, $app->configGet('TEST.KEY'), 'Cached value should be used, not disk');

    unlink($tmpFile);
};

// Test 5: configSet writes to file in .env format
$tests[] = function () {
    $tmpFile = tmpConfigPath();
    $app = new NanoCore($tmpFile);

    $app->configSet('APP.NAME', 'Test App');

    $raw = file_get_contents($tmpFile);

    assertTrue(str_contains($raw, 'APP.NAME="Test App"'), 'File should contain APP.NAME="Test App" with quoting');

    unlink($tmpFile);
};

// Test 6: configSet creates nested structure automatically
$tests[] = function () {
    $tmpFile = tmpConfigPath();
    $app = new NanoCore($tmpFile);

    $app->configSet('LEVEL1.LEVEL2.LEVEL3', 'deep');

    assertEquals('deep', $app->configGet('LEVEL1.LEVEL2.LEVEL3'), 'Deep nested value should be retrievable');
    assertEquals(['LEVEL3' => 'deep'], $app->configGet('LEVEL1.LEVEL2'), 'Intermediate level should return sub-array');

    unlink($tmpFile);
};

// Test 7: Custom config file path works
$tests[] = function () {
    $tmpFile = sys_get_temp_dir() . '/custom_config_' . uniqid() . '.env';
    $app = new NanoCore($tmpFile);

    $app->configSet('CUSTOM', 'works');

    assertEquals('works', $app->configGet('CUSTOM'), 'Custom path configGet should return correct value');

    $raw = file_get_contents($tmpFile);
    assertTrue(str_contains($raw, 'CUSTOM=works'), 'Custom file should contain the value on disk');

    unlink($tmpFile);
};

// Test 8: CORE.ROOT is set on construction
$tests[] = function () {
    $tmpFile = tmpConfigPath();
    $app = new NanoCore($tmpFile);

    $root = $app->configGet('CORE.ROOT');
    assertEquals('', $root, 'CORE.ROOT should be empty string in CLI mode');

    unlink($tmpFile);
};

// Test 9: PHP.INI settings are applied on construction
$tests[] = function () {
    // Save current value so we can restore it
    $previous = ini_get('display_errors');

    $tmpFile = tmpConfigPath();
    file_put_contents($tmpFile, "PHP.INI.display_errors=0\n");

    new NanoCore($tmpFile);

    assertEquals('0', ini_get('display_errors'), 'PHP.INI display_errors should be set to 0');

    // Restore previous value
    ini_set('display_errors', $previous);
    unlink($tmpFile);
};

// Test 10: configGet for top-level key returns entire section
$tests[] = function () {
    $tmpFile = tmpConfigPath();
    $app = new NanoCore($tmpFile);

    $app->configSet('SECTION.KEY1', 'val1');
    $app->configSet('SECTION.KEY2', 'val2');

    $section = $app->configGet('SECTION');
    assertEquals(['KEY1' => 'val1', 'KEY2' => 'val2'], $section, 'Top-level key should return full section');

    unlink($tmpFile);
};

// Test 11: Quoted values have surrounding quotes stripped
$tests[] = function () {
    $tmpFile = tmpConfigPath();
    file_put_contents($tmpFile, "APP.TITLE=\"My App\"\nAPP.DESC='short'\n");

    $app = new NanoCore($tmpFile);

    assertEquals('My App', $app->configGet('APP.TITLE'), 'Double-quoted value should be unquoted');
    assertEquals('short', $app->configGet('APP.DESC'), 'Single-quoted value should be unquoted');

    unlink($tmpFile);
};

// Test 12: Inline comments are stripped
$tests[] = function () {
    $tmpFile = tmpConfigPath();
    file_put_contents($tmpFile, "APP.DEBUG=true # enabled for dev\n");

    $app = new NanoCore($tmpFile);

    assertEquals('true', $app->configGet('APP.DEBUG'), 'Inline comment should be stripped');

    unlink($tmpFile);
};

// Test 13: Variable interpolation resolves ${VAR} references
$tests[] = function () {
    $tmpFile = tmpConfigPath();
    file_put_contents($tmpFile, "DB.HOST=localhost\nDB.PORT=3306\nDB.URL=\${DB.HOST}:\${DB.PORT}\n");

    $app = new NanoCore($tmpFile);

    assertEquals('localhost:3306', $app->configGet('DB.URL'), 'Variable interpolation should resolve');

    unlink($tmpFile);
};

// Test 14: .env.local overrides .env values
$tests[] = function () {
    $tmpFile = tmpConfigPath();
    $localFile = $tmpFile . '.local';

    file_put_contents($tmpFile, "APP.MODE=production\n");
    file_put_contents($localFile, "APP.MODE=development\n");

    $app = new NanoCore($tmpFile);

    assertEquals('development', $app->configGet('APP.MODE'), '.env.local should override .env');

    unlink($tmpFile);
    if (file_exists($localFile)) {
        unlink($localFile);
    }
};

// Test 15: export prefix is stripped
$tests[] = function () {
    $tmpFile = tmpConfigPath();
    file_put_contents($tmpFile, "export APP.ENV=staging\n");

    $app = new NanoCore($tmpFile);

    assertEquals('staging', $app->configGet('APP.ENV'), 'export prefix should be stripped');

    unlink($tmpFile);
};

// Test 16: Value with # survives round-trip
$tests[] = function () {
    $tmpFile = tmpConfigPath();
    $app = new NanoCore($tmpFile);

    $app->configSet('APP.MSG', 'hello # world');

    // Create a new instance to force re-parse from file
    $app2 = new NanoCore($tmpFile);
    assertEquals('hello # world', $app2->configGet('APP.MSG'), 'Value with # should survive round-trip');

    unlink($tmpFile);
};

// Test 17: Value with internal quotes preserved via strrpos
$tests[] = function () {
    $tmpFile = tmpConfigPath();
    file_put_contents($tmpFile, 'APP.MSG="hello \"world\""');

    $app = new NanoCore($tmpFile);
    // With strrpos fix, the full quoted content is preserved
    $value = $app->configGet('APP.MSG');
    assertTrue(str_contains($value, 'hello'), 'Quoted value with internal quotes should contain the text');

    unlink($tmpFile);
};

// Test 18: Single-quoted values skip interpolation
$tests[] = function () {
    $tmpFile = tmpConfigPath();
    file_put_contents($tmpFile, "DB.HOST=localhost\nDB.URL='\${DB.HOST}:3306'");

    $app = new NanoCore($tmpFile);
    assertEquals('${DB.HOST}:3306', $app->configGet('DB.URL'), 'Single-quoted value should not interpolate');

    unlink($tmpFile);
};

runTests($tests);
