> Read this when you change how `.env` is located, read, written, or how its values reach `ini_set`.

# `.env` Config File

Contract: [config/spec.md](../config/spec.md).

## Implementing files

| File | Key symbols |
| --- | --- |
| `src/NanoCore.php` | `validateConfigPath()`, `loadConfig()`, `parseEnvFile()`, `saveConfig()`, `configSet()`, `assertValidConfigKey()`, `assertSafeConfigValue()`, `setPHPConfig()`, `PROTECTED_CONFIG_KEYS`, `ALLOWED_INI_SETTINGS` |

## Flow

1. Constructor: install handlers → `validateConfigPath()` → set log dir to the `.env` directory → `setPHPConfig()` (first `configGet` loads/creates the file) → inject `CORE.ROOT`.
2. `configGet()` reads from the in-memory cache.
3. `configSet()` validates key/value → updates a copy of the cache → `saveConfig()` writes temp file + `rename`.

## Trust boundaries

| Boundary | Mechanism | Location |
| --- | --- | --- |
| New file naming | Non-existing file must start with `.env` | `validateConfigPath()` |
| Fail-closed read | Unreadable file throws, never loads empty config | `parseEnvFile()` |
| Line injection via key | Keys with whitespace, `=`, `#`, quotes, backslash, control chars rejected | `assertValidConfigKey()` |
| Line injection via value | `\r`, `\n`, `\0` rejected (recursive) | `assertSafeConfigValue()` |
| Protected subtrees | `PHP.INI`, `CORE`, their children and ancestors unwritable | `configSet()` |
| Arbitrary `ini_set` | 13-directive allowlist | `setPHPConfig()` |
| Atomic write | `tempnam` in same dir + `rename`; temp removed on failure | `saveConfig()` |
| Template exposure | `.env` itself always rejected by `renderHtml` | [filesystem.md](filesystem.md) |

Open issue: values containing `${KEY}` written via `configSet` are interpolated on the next load (secret copy).

## Config

| Item | Value |
| --- | --- |
| Default path | `.env` relative to cwd |
| Temp file prefix | `nc_cfg_` |
| Sample keys | `.env.example` |
