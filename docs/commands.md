> Read this when you need to run tests, check syntax, or build the package.

# Commands

## Syntax Check

```bash
php -l NanoCore.php
php -l NanoORM.php
```

Run after every change to NanoCore.php or NanoORM.php. Required by project conventions.

## Tests

### Run All Tests

```bash
php tests/runAllTests.php
```

Discovers and runs all test files in `tests/cases/` via glob. Prints a summary (X/Y passed, Z failed). Exit code 0 if all pass, 1 if any fail.

### Run Individual Tests

```bash
php tests/cases/RoutesTest.php
php tests/cases/ErrorHandlingTest.php
php tests/cases/ConfigTest.php
php tests/cases/RouteEdgeCasesTest.php
php tests/cases/UtilitiesTest.php
php tests/cases/ORMTest.php
php tests/cases/ORMEdgeCasesTest.php
php tests/cases/JoinTest.php
```

All are standalone runners (no PHPUnit). They exit with code 0 on success, 1 on failure. Each test prints pass/fail to stdout.

### Test Structure

- `tests/TestHelpers.php` — shared assertion functions, database/route helpers, and the `runTests()` runner
- `tests/cases/*.php` — individual test files, each defining a `$tests[]` array and calling `runTests($tests)`
- `tests/runAllTests.php` — orchestrator that discovers all `cases/*.php` files and runs them in separate processes

## Composer

```bash
composer install        # Install dependencies (dev only, no runtime deps beyond PHP 8.5)
composer dump-autoload  # Regenerate PSR-4 autoload map after adding/moving classes
```

## Consuming in Another Project

```bash
composer require gianvoci/nanocore
```

Or via local path in `composer.json`:
```json
{
    "repositories": [{"type": "path", "url": "../nanocore"}],
    "require": {"gianvoci/nanocore": "@dev"}
}
```
