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
    // Constructor sets CORE.ROOT after creation, so file will have that structure
    $decoded = json_decode(file_get_contents($tmpFile), true);
    assertTrue($decoded !== null, 'Auto-created file should contain valid JSON');

    unlink($tmpFile);
};

// Test 2: Dot-notation get returns nested values
$tests[] = function () {
    $tmpFile = tmpConfigPath();
    $app = new NanoCore($tmpFile);

    $app->configSet('DB.HOST', 'localhost');
    $app->configSet('DB.PORT', 3306);

    assertEquals('localhost', $app->configGet('DB.HOST'), 'DB.HOST should be localhost');
    assertEquals(3306, $app->configGet('DB.PORT'), 'DB.PORT should be 3306');
    assertEquals(['HOST' => 'localhost', 'PORT' => 3306], $app->configGet('DB'), 'DB should return full section');

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
    file_put_contents($tmpFile, '{"TEST":{"KEY":"from_disk"}}');

    // Should still return null because cache is used
    assertEquals(null, $app->configGet('TEST.KEY'), 'Cached value should be used, not disk');

    unlink($tmpFile);
};

// Test 5: configSet writes to file with correct JSON encoding
$tests[] = function () {
    $tmpFile = tmpConfigPath();
    $app = new NanoCore($tmpFile);

    $app->configSet('APP.NAME', 'Test App');

    $raw = file_get_contents($tmpFile);
    $decoded = json_decode($raw, true);

    assertTrue($decoded !== null, 'File should contain valid JSON');
    assertEquals('Test App', $decoded['APP']['NAME'], 'JSON structure should match dot-notation');

    // Verify pretty-print: should have newlines and indentation
    assertTrue(str_contains($raw, "\n"), 'JSON should be pretty-printed');

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
    $tmpFile = sys_get_temp_dir() . '/custom_config_' . uniqid() . '.json';
    $app = new NanoCore($tmpFile);

    $app->configSet('CUSTOM', 'works');

    assertEquals('works', $app->configGet('CUSTOM'), 'Custom path configGet should return correct value');

    $raw = file_get_contents($tmpFile);
    $decoded = json_decode($raw, true);
    assertEquals('works', $decoded['CUSTOM'], 'Custom file should contain the value on disk');

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
    file_put_contents($tmpFile, '{"PHP":{"INI":{"display_errors":"0"}}}');

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

runTests($tests);
