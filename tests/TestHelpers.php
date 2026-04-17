<?php

declare(strict_types=1);

/**
 * Shared test helpers for the NanoCore test suite.
 * Provides assertion functions, common setup helpers, and the test runner.
 * 
 * Each test file should require_once this file, then define its $tests[] array,
 * and call runTests($tests) at the end.
 */

// ─── Assertions ──────────────────────────────────────────────────────────────

function assertEquals(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(sprintf(
            "%s (expected %s, got %s)",
            $message,
            var_export($expected, true),
            var_export($actual, true)
        ));
    }
}

function assertTrue(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

// ─── Database Helpers ─────────────────────────────────────────────────────────

function createMemoryPDO(): PDO
{
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    return $pdo;
}

function prepareSchema(PDO $pdo): void
{
    $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, email TEXT, status TEXT)');
    $pdo->exec('CREATE TABLE products (id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT, price REAL)');
    $pdo->exec('CREATE TABLE orders (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, product_id INTEGER, status TEXT)');
}

function prepareJoinSchema(PDO $pdo): void
{
    $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, email TEXT)');
    $pdo->exec('CREATE TABLE products (id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT, price REAL)');
    $pdo->exec('CREATE TABLE orders (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, product_id INTEGER, status TEXT)');
}

// ─── Route Helpers ────────────────────────────────────────────────────────────

function runRequest(\NanoCore\NanoCore $app, string $method, string $path, array $query = []): mixed
{
    $_SERVER['REQUEST_METHOD'] = $method;
    $queryString = http_build_query($query);
    $_SERVER['REQUEST_URI'] = $path . ($queryString !== '' ? '?' . $queryString : '');
    $_SERVER['QUERY_STRING'] = $queryString;
    $_SERVER['SCRIPT_NAME'] = '/index.php';

    return $app->run();
}

// ─── Config Helpers ───────────────────────────────────────────────────────────

function tmpConfigPath(): string
{
    return sys_get_temp_dir() . '/nc_test_' . uniqid() . '.env';
}

// ─── Temp File Helpers ────────────────────────────────────────────────────────

function createTempHtml(string $content): string
{
    $path = tempnam(__DIR__, 'nc_html_');
    file_put_contents($path, $content);
    return $path;
}

// ─── Test Runner ──────────────────────────────────────────────────────────────

/**
 * Runs all test functions and reports results.
 * Each test that throws will be counted as failed.
 * Exits with code 0 if all pass, 1 if any fail.
 *
 * @param array<int, callable> $tests Array of test functions
 */
function runTests(array $tests): void
{
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
}
