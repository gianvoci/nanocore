# Migration Guide: → 2.5.0

Security hardening release. No breaking changes to the documented contract: public signatures, JSON response format, and defaults are unchanged.

## Behavior Changes (aligned with specs)

### 1. `configSet` — protected keys and input validation

- `configSet('PHP.INI.<x>', ...)` and `configSet('PHP', ...)` now throw `\Exception("Cannot modify protected config key: PHP.INI")`. This was already documented, but the check never triggered (it compared `PHP` against `PHP.INI`). Unrelated keys like `PHP.OTHER` are still allowed.
- Empty keys or keys containing whitespace, `=`, `#`, quotes, backslash, or control characters → `\InvalidArgumentException("Invalid config key: <key>")`.
- Values containing `\r`, `\n`, or `\0` (including nested in arrays) → `\InvalidArgumentException("Config value must not contain line breaks or NUL bytes")`.

**Action**: none, unless your code wrote `PHP.INI.*` keys via `configSet` (move them to `.env`) or multi-line values (encode them, e.g. `base64_encode`/`json_encode`).

### 2. Error handler respects `@`

Errors suppressed with the `@` operator are no longer converted to `ErrorException`. Errors excluded by your configured `error_reporting` (php.ini, `PHP.INI.error_reporting`, `error_reporting()`) are still converted, exactly as in 2.4.0. Example: a `regex:` rule with a malformed pattern now returns the validation error `has invalid regex pattern` instead of a 500.

**Action**: none. If a `try/catch (ErrorException)` wrapped a call prefixed with `@`, remove the `@` to keep receiving the exception.

### 3. `renderHtml` — allowed roots

In addition to the current root (`basePath`, or cwd in CLI), templates inside the `.env` directory are now accepted. The `.env` file itself is always rejected as a template.

### 3b. Unreadable `.env` fails closed

An existing `.env` that cannot be read now throws `\Exception("Cannot read config file: <path>")` instead of silently loading an empty config. (In 2.4.0 it also failed, via `ErrorException`.)

---

## New Features

### 4. `CORE.EXPOSE_ERRORS`

```env
CORE.EXPOSE_ERRORS=false
```

When `false`, 5xx responses return `{"error": "Internal Server Error", "code": 500}` and the real message is written to `nanocore.log` (falls back to `error_log()` if the log is not writable). 4xx responses are unchanged. Default `true` (current behavior). Recommended `false` in production.

### 5. `SESSION.COOKIE_SAMESITE` and `sessionRegenerate()`

```env
SESSION.COOKIE_SAMESITE=Lax
```

Allowed values: `Lax`, `Strict`, `None` (case-insensitive); any other value makes `sessionStart()` throw `\InvalidArgumentException`.

```php
$app->sessionStart();
// after login
$app->sessionRegenerate();
```

### 6. `NanoORM::fillOnly()`

```php
$user->fillOnly($input, ['name', 'email']); // other keys are skipped
```

`fill()` is unchanged (same signature), so subclasses overriding it keep working.

### 7. Internal hardening (transparent)

- SSRF: URL validation also checks AAAA (IPv6) records, not only A.
- `curlRequest` log: `params` values whose keys look like `password`, `token`, `secret`, `key`, `auth`, `credential` are masked as `***`, both in logged params and in the logged URL query string; non-array bodies are logged as `[raw body]`.

---

## Migration Checklist

- [ ] No mandatory changes to consumer code
- [ ] Set `CORE.EXPOSE_ERRORS=false` in the production `.env`
- [ ] Set `SESSION.COOKIE_SAMESITE=Lax` (or `Strict`) and call `sessionRegenerate()` after login
- [ ] Use `fillOnly($input, [...])` with request data
- [ ] Make sure you don't write `PHP.INI.*` keys or multi-line values via `configSet`
