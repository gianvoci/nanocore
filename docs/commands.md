> Read this when you need to run tests, check syntax, or build the package.

# Commands

## Syntax Check

```bash
php -l NanoCore.php
php -l NanoORM.php
```

Run after every change to NanoCore.php or NanoORM.php. Required by project conventions.

## Tests

```bash
php tests/NanoCoreRoutesTest.php
php tests/NanoORMTest.php
```

Both are standalone runners (no PHPUnit). They exit with code 0 on success, 1 on failure. Each test prints pass/fail to stdout.

## Composer

```bash
composer install        # Install dependencies (dev only, no runtime deps beyond PHP 8.0)
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
