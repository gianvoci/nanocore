> Read this when reading or writing config values, or understanding .env structure.

# Config Management Pattern

## Storage

Config is persisted in `.env` at the project root. The file is auto-created as empty if it doesn't exist.

A `.env.local` file can override values from `.env`. Loading order:
1. `.env` (base config)
2. `.env.local` (overrides) — values from `.env.local` overwrite `.env` values

The local file is derived from the main config file: same directory, same name + `.local` suffix.
- `.env` → `.env.local`
- `custom.env` → `custom.env.local`

The config file path is validated on construction:
- Must end in `.env`
- Resolved to the current working directory (prevents writing to arbitrary locations)

## .env Format

```env
# This is a comment
DB.HOST=localhost
DB.PORT=3306
APP.NAME=My App
PHP.INI.display_errors=0
PHP.INI.date.timezone=Europe/Rome

# Quoted values — surrounding quotes are stripped
APP.TITLE="My Application"
APP.DESCRIPTION='Single quoted'

# Inline comments
APP.DEBUG=true # enabled for dev

# Variable interpolation — ${VAR} resolves to previously loaded values
DB.URL=${DB.HOST}:${DB.PORT}

# export prefix is silently stripped
export APP.ENV=production

# Empty value
APP.SECRET=
```

Parser rebuilds nested array: `DB.HOST=localhost` → `['DB' => ['HOST' => 'localhost']]`

**All values are strings.** No type coercion. If someone writes `DB.PORT=3306`, `configGet('DB.PORT')` returns `"3306"` (string), not `3306` (int). This is standard .env behavior.

## Dot-Notation Access

```php
// Read
$dbHost = $app->configGet('DB.HOST');           // Nested: ['DB' => ['HOST' => 'localhost']]
$iniSettings = $app->configGet('PHP.INI');       // Returns array or null

// Write
$app->configSet('DB.HOST', 'localhost');         // Creates nested structure automatically
$app->configSet('DB.PORT', '3306');
```

## Protected Keys

The following top-level keys cannot be modified via `configSet`:

| Key | Reason |
| --- | --- |
| `CORE` | Set automatically by the constructor (`CORE.ROOT`). |
| `PHP.INI` | Controls server behavior via `ini_set()`. |

Attempting `configSet('PHP.INI.display_errors', '1')` will throw an exception.

## Special Keys

| Key | Purpose |
| --- | --- |
| `CORE.ROOT` | Set automatically by the constructor to the detected base path. Do not set manually. |
| `PHP.INI` | Group of `ini_set` key-value pairs. Only allowed directives are applied (see `ALLOWED_INI_SETTINGS` constant in NanoCore.php). Unknown directives are silently skipped. |

## INI Allowlist

Only the following PHP directives may be set through `PHP.INI`. Anything else is silently ignored:

| Directive |
| --- |
| `display_errors` |
| `error_log` |
| `error_reporting` |
| `log_errors` |
| `upload_max_filesize` |
| `post_max_size` |
| `max_execution_time` |
| `memory_limit` |
| `default_charset` |
| `date.timezone` |
| `session.cookie_httponly` |
| `session.cookie_secure` |
| `session.use_strict_mode` |

This is enforced in the `ALLOWED_INI_SETTINGS` constant. To add a new directive, update the constant in `NanoCore.php`.

## Implementation Details

- Config is cached in memory after first load. `configGet` returns from cache on subsequent calls.
- `configSet` reads → modifies → writes the file, then updates the cache only on successful write.
- `saveConfig` uses atomic writes (temp file + rename) to prevent data corruption.
- `saveConfig` flattens nested arrays to dot-notation keys and writes them sorted alphabetically.
- `saveConfig` only writes to the main config file (`.env`), never to `.env.local`.
- Config file path is configurable: `new NanoCore('custom.env')`.
