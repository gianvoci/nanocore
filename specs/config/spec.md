> Read this when you change `.env` loading, `configGet`/`configSet`, PHP ini handling, or `CORE.*` keys.

# Config Manager

## Purpose

Loads a dot-notation `.env` file into a nested array cache, applies allowlisted PHP ini settings, and rewrites the file atomically on `configSet`.

## Data model

| Item | Type | Notes |
| --- | --- | --- |
| `$configFile` | `string` | Absolute path; dir resolved with `realpath` (falls back to cwd if the dir does not exist) |
| `$configCache` | `?array` | Nested array of strings; loaded once per instance, replaced after each successful save |
| `CORE.ROOT` | `string` | Injected into the cache after load = URL base path (`''` in CLI) |
| `CORE.EXPOSE_ERRORS` | `string` (bool-ish) | See [routing/spec.md](../routing/spec.md#error-contract) |
| `PHP.INI.<setting>` | `string` | Applied via `ini_set` in the constructor if allowlisted |
| `SESSION.*` | `string` | Read by `sessionStart()`; see [integrations/sessions.md](../integrations/sessions.md) |

## Business rules

### Loading
1. Missing file → created empty; creation failure → `\Exception('Cannot create config file: <path>')`.
2. Unreadable existing file → `\Exception('Cannot read config file: <path>')` (fail-closed).
3. A new file name must start with `.env`; an existing file is accepted with any name.
4. Per line: trim; skip empty and `#` lines; strip `export `; split on first `=`; lines without `=` skipped; later duplicates override.
5. Unquoted value: cut at ` #` (space + hash). Quoted value: kept up to the **last** matching quote, then outer quotes stripped. No escape sequences are unescaped.
6. `${A.B}` interpolation in unquoted and double-quoted values, resolved only against keys defined **earlier** in the file; unresolved references stay literal. Single-quoted values are literal.
7. Dot keys build nested arrays; a scalar parent is replaced by an array when a child key appears.

### Reading
- `configGet('A.B')` returns a string leaf, a nested array for a prefix (e.g. `PHP.INI`), or `null`.

### Writing (`configSet`)
1. Key must be non-empty and match none of `INVALID_CONFIG_KEY_PATTERN` → else `\InvalidArgumentException('Invalid config key: <key>')`.
2. Key equal to, under, or an ancestor of a protected key (`PHP.INI`, `CORE`; e.g. `PHP`, `PHP.INI.x`, `CORE.X`) → `\Exception('Cannot modify protected config key: <protected>')`. `PHP.OTHER` is allowed.
3. Value (recursively for arrays, including array keys) must not contain `\r`, `\n`, `\0` → `\InvalidArgumentException`.
4. The **whole cache** is rewritten: keys flattened and `ksort`ed, comments and original order lost, interpolated values persisted resolved, `CORE.ROOT` persisted too (re-overridden on next construct).
5. Values with whitespace, `#`, quotes, backslash, or empty are written double-quoted with `"` escaped as `\"`; the parser does not unescape it, so values containing `"` do not round-trip.
6. `$` is not quoted: a value containing `${X.Y}` is interpolated on the next load (open issue: can copy a secret into another key).
7. Atomic write: `tempnam` in the `.env` dir → `rename`; on failure the temp file is removed and an `\Exception` thrown; cache updated only on success.

### PHP ini
- Only `ALLOWED_INI_SETTINGS` are applied; others are skipped with an `error_log` notice. Applied once, in the constructor.

## Constants

| Name | Value | Location |
| --- | --- | --- |
| `PROTECTED_CONFIG_KEYS` | `['PHP.INI', 'CORE']` | `src/NanoCore.php:18` |
| `INVALID_CONFIG_KEY_PATTERN` | `/[\s=#"'\\\\]\|[\x00-\x1F\x7F]/` | `src/NanoCore.php:21` |
| `ALLOWED_INI_SETTINGS` | 13 directives: `display_errors`, `error_log`, `error_reporting`, `log_errors`, `upload_max_filesize`, `post_max_size`, `max_execution_time`, `memory_limit`, `default_charset`, `date.timezone`, `session.cookie_httponly`, `session.cookie_secure`, `session.use_strict_mode` | `src/NanoCore.php:29` |

## Cross-links

- Trust boundaries: [integrations/env-config.md](../integrations/env-config.md)
- Recipe: [rules/config-management.md](../rules/config-management.md)
- API: [api-routes.md](../api-routes.md#config)
