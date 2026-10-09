> Read this when you need to run a syntax check, tests, Composer, or release commands.

# Commands

## Syntax check (after every source change)

```bash
php -l src/NanoCore.php
php -l src/NanoORM.php
```

## Tests

```bash
php tests/runAllTests.php          # all files, each in its own process; exit 0/1
php tests/cases/RoutesTest.php     # one file; prints "Test N passed/failed: <msg>"
```

| Test file | Needs |
| --- | --- |
| `CurlRequestTest.php` | Local PHP built-in server (`createTestServer()`, `php -S localhost:0`) |
| `UtilitiesTest.php` | Outbound internet (httpbin); guards skip on transient non-200/418 |
| ORM / Join tests | `pdo_sqlite` (in-memory DB) |

Prefer running the single affected file first, then the full suite. Test conventions: [rules/add-test.md](rules/add-test.md).

## Composer

```bash
composer dump-autoload          # regenerate PSR-4 autoloader (tests require vendor/autoload.php)
composer require gianvoci/nanocore   # consumer install
```

Local path consumption: `"repositories": [{"type": "path", "url": "../nanocore"}]`, `"require": {"gianvoci/nanocore": "@dev"}`.

## Release

```bash
git tag --sort=-v:refname | head -1   # latest version (tags use the v prefix, e.g. v2.4.1)
git tag v{version}
git push origin main --tags
```

Full procedure: [rules/release.md](rules/release.md).
