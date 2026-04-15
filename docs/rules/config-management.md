> Read this when reading or writing config values, or understanding app.json structure.

# Config Management Pattern

## Storage

Config is persisted in `app.json` at the project root. The file is auto-created as `{}` if it doesn't exist.

## Dot-Notation Access

```php
// Read
$dbHost = $app->configGet('DB.HOST');           // Nested: {"DB": {"HOST": "localhost"}}
$iniSettings = $app->configGet('PHP.INI');       // Returns array or null

// Write
$app->configSet('DB.HOST', 'localhost');         // Creates nested structure automatically
$app->configSet('DB.PORT', 3306);
```

## Special Keys

| Key | Purpose |
| --- | --- |
| `CORE.ROOT` | Set automatically by the constructor to the detected base path. Do not set manually. |
| `PHP.INI` | Object of `ini_set` key-value pairs. Applied on every NanoCore construction. Example: `{"PHP.INI": {"display_errors": "1"}}` |

## Implementation Details

- Every `configGet` call reads the file from disk (no caching).
- Every `configSet` call reads → modifies → writes the entire file.
- JSON is encoded with `JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES`.
- Config file path is configurable: `new NanoCore('custom-config.json')`.
