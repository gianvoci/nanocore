> Read this when you change session start/regeneration or `SESSION.*` config handling.

# Sessions

## Implementing files

| File | Key symbols |
| --- | --- |
| `src/NanoCore.php` | `sessionStart()`, `sessionGet()`, `sessionSet()`, `sessionDestroy()`, `sessionRegenerate()`, `SAMESITE_VALUES` |

## Flow

1. `sessionStart()` returns immediately if a session is active.
2. Reads `SESSION.COOKIE_SAMESITE` (validated first), `SESSION.COOKIE_HTTPONLY`, `SESSION.COOKIE_SECURE`, `SESSION.USE_STRICT_MODE` and applies them with `ini_set`.
3. `session_start()`; data accessed through `$_SESSION` wrappers.
4. After login: `sessionRegenerate()` → `session_regenerate_id(true)`.
5. Logout: `sessionDestroy()` → `session_destroy()` + `$_SESSION = []`.

## Trust boundaries

| Boundary | Mechanism | Location |
| --- | --- | --- |
| SameSite value | Normalized `ucfirst(strtolower())`, must be `Lax`/`Strict`/`None`, non-scalar rejected; throws before any ini change | `sessionStart()` |
| Boolean flags | `FILTER_VALIDATE_BOOLEAN` → `0/1` | `sessionStart()` |
| Session fixation | Opt-in ID regeneration deleting the old session | `sessionRegenerate()` |
| Inactive session | `sessionDestroy()` / `sessionRegenerate()` are no-ops | both |

Unset keys leave PHP defaults (also settable via `PHP.INI.session.*` allowlisted directives, see [config/spec.md](../config/spec.md)).

## Config

| Key | Values |
| --- | --- |
| `SESSION.COOKIE_SAMESITE` | `Lax`, `Strict`, `None` (case-insensitive) |
| `SESSION.COOKIE_HTTPONLY` | bool-ish |
| `SESSION.COOKIE_SECURE` | bool-ish |
| `SESSION.USE_STRICT_MODE` | bool-ish |
