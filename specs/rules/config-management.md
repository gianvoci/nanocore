> Read this when you read/write config, add a config-driven feature, or extend the PHP ini allowlist.

# Config Management

Contract: [config/spec.md](../config/spec.md). Trust boundaries: [integrations/env-config.md](../integrations/env-config.md).

## Read a value

```php
$host = $app->configGet('DB.HOST') ?? 'localhost';   // string|null
$ini  = $app->configGet('PHP.INI') ?? [];             // prefix → nested array
```

Values are always strings: cast booleans with `filter_var($v, FILTER_VALIDATE_BOOLEAN)` (as `sessionStart()` does).

## Write a value

```php
$app->configSet('APP.MAINTENANCE', 'true');
```

- Rewrites the whole `.env` (sorted, comments dropped). Do not use for hand-maintained files that need comments.
- Encode multi-line data first (`base64_encode` / `json_encode`).

## Add a config-driven feature

1. Choose a top-level namespace (`SESSION.*`, `DB.*`, ...). Never use `CORE.*` for consumer-writable settings — it is protected.
2. Read it lazily with `configGet()` at use time; validate allowed values and throw `\InvalidArgumentException` before any side effect (pattern: `SESSION.COOKIE_SAMESITE`).
3. Default must preserve current behavior when the key is absent.
4. Document the key in `README.md` → Configuration, in [config/spec.md](../config/spec.md), and in `.env.example` if it is commonly set.

## Allow a new `PHP.INI.*` directive

1. Justify it as non-dangerous (no `open_basedir`, `disable_functions`, `auto_prepend_file`, `allow_url_*`, extension loading).
2. Append it to `ALLOWED_INI_SETTINGS` (`src/NanoCore.php:29`).
3. Update the count/list in [config/spec.md](../config/spec.md#constants) and the README Security table.
4. Add a test in `tests/cases/ConfigTest.php` that saves and restores the ini value.

## Add a protected key

Append to `PROTECTED_CONFIG_KEYS`; `configSet()` blocks the key, its children, and ancestors automatically. This is a breaking change for consumers writing that key.
