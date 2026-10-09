# Migration Guide: → 2.2.4

## Breaking Changes

### 1. Unified JSON error key: `message` → `error`

`sendJsonError()` (used by both the global exception handler and `run()`) now uses the `"error"` key in the JSON response, consistently. Previously, the global handler used `"message"` while `run()` used `"error"`.

**If your client parsed `response.message`**, it must now parse `response.error`:

```php
// BEFORE (2.2.2) — global handler used 'message'
$error = $json['message'];

// AFTER (2.2.3) — always 'error'
$error = $json['error'];
```

**Action**: Search for `$json['message']` or `response.message` in error response parsing and replace it with `error`.

### 2. `__set` on NanoORM now throws an exception for unknown fields

Previously, assigning a value to a non-existent field on an ORM was silently ignored. Now it throws `\InvalidArgumentException`.

```php
// BEFORE (2.2.2) — silently ignored
$user->campo_inesistente = 'valore'; // no error, the data was not saved

// AFTER (2.2.3) — immediate exception
$user->campo_inesistente = 'valore'; // → \InvalidArgumentException("Unknown field: campo_inesistente")
```

This includes `fill()`:

```php
// BEFORE (2.2.2) — ignored fields not in the schema
$user->fill(['name' => 'Test', 'nonexistent' => 'ignored']);

// AFTER (2.2.3) — exception
$user->fill(['name' => 'Test', 'nonexistent' => 'ignored']); // → \InvalidArgumentException
```

**Action**: Verify all assignments to `NanoORM` objects — field names must exactly match the database schema.

### 3. `paginate()` now blocks queries with JOINs

If you had registered JOINs with `addJoin()` and called `paginate()`, the JOINs were silently ignored. Now `paginate()` throws an explicit exception.

```php
// BEFORE (2.2.2) — JOINs ignored, incomplete results
$orm->addJoin('users', 'user_id', 'id');
$result = $orm->paginate(1, 10); // JOIN ignored, only orders were paginated

// AFTER (2.2.3) — exception
$orm->addJoin('users', 'user_id', 'id');
$result = $orm->paginate(1, 10); // → \Exception("Paginate does not support joined queries")
```

**Action**: Call `paginate()` BEFORE `addJoin()`, or use `fetchWithJoins()` for queries with JOINs.

### 4. `sessionStart()` — correct boolean interpretation

Previously, `(int)(bool)"false"` produced `1` because in PHP `(bool)"false"` is `true`. Now `filter_var("false", FILTER_VALIDATE_BOOLEAN)` correctly produces `false`.

```env
# BEFORE (2.2.2) — these values were interpreted as true
SESSION.COOKIE_SECURE=false
SESSION.COOKIE_HTTPONLY=false

# AFTER (2.2.3) — now they are respected as false
SESSION.COOKIE_SECURE=false
SESSION.COOKIE_HTTPONLY=false
```

**Action**: If you were using `SESSION.COOKIE_SECURE=false` and relied on it being ignored (buggy behavior), the session will now start without secure cookies. If you want `true`, explicitly write `true`.

### 5. `saveConfig()` now throws an exception on rename failure

Previously, if `rename()` of the `.env` file failed (e.g. file locked by antivirus), the in-memory cache was updated anyway but the file was not saved — no error. Now it throws an exception.

```php
// BEFORE (2.2.2) — silent failure
$app->configSet('DB.HOST', 'newhost'); // cache updated, file possibly not

// AFTER (2.2.3) — exception on failure
$app->configSet('DB.HOST', 'newhost'); // → \Exception("Failed to save config file: ...")
```

**Action**: Wrap `configSet()` in try/catch if you operate in environments where lock files are possible.

### 6. `curlRequest()` now blocks URLs without a host

Malformed URLs such as `http:///path` are now rejected by `validateUrlNotRestricted()`.

```php
// BEFORE (2.2.2) — passed validation
NanoCore::curlRequest('http:///path'); // "worked" (curl failed afterwards)

// AFTER (2.2.3) — immediate exception
NanoCore::curlRequest('http:///path'); // → \Exception("URL must specify a host")
```

**Action**: Verify that URLs passed to `curlRequest()` always have a valid host.

### 7. `declare(strict_types=1)` in both files

PHP now enforces strict type checking. Parameters with the wrong type throw `TypeError` instead of being converted automatically.

```php
// BEFORE (2.2.2) — PHP converted automatically
$app->addRoute(123, $path, $handler); // 123 was cast to "123"

// AFTER (2.2.3) — TypeError
$app->addRoute(123, $path, $handler); // → TypeError: expected string, got int
```

**Action**: Verify that all parameters passed to public methods match their declared type hints.

### 8. `renderHtml()` switches from `str_replace` to `strtr`

The template system now uses `strtr()` instead of `str_replace()`, which matches the longest key first. Overlapping placeholders change behavior.

```php
// If you had overlapping placeholders:
$result = $app->renderHtml($template, ['{{NAME}}' => 'A', '{{NAME}_X}' => 'B']);

// BEFORE (2.2.2) — unpredictable order with str_replace
// AFTER (2.2.3) — deterministic: longest match wins ({{NAME}_X} replaced first)
```

**Action**: Verify that placeholders in templates do not have overlapping prefixes. If you use a simple `{{nome}}` there are no problems.

---

## New Protections

These do not break anything but change runtime behavior:

| Change | What happens |
|--------|-------------|
| `error_log()` for unknown PHP.INI directives | Every `PHP.INI.*` directive not in the whitelist is logged as a warning |
| `loadConfig()` exception on `.env` creation | If `.env` does not exist and cannot be created (permissions), it now throws an exception |
| `rollbackDir()` migration name validation | Names read from the DB are validated with a regex before building the file path |
| `sendJsonError()` uses clamped `$status` in JSON `code` | The `code` field in the JSON body now matches the HTTP status (e.g. 500 instead of 0) |

---

## Migration Checklist

- [ ] Search for `$json['message']` in error parsing — replace with `$json['error']`
- [ ] Search for assignments to `NanoORM` with field names — verify they match the DB schema
- [ ] Search for `paginate()` after `addJoin()` — swap the order or use `fetchWithJoins()`
- [ ] Search for `SESSION.*=false` in `.env` — verify the boolean value is the one intended
- [ ] Search for `configSet()` in contexts where lock files are possible — add try/catch
- [ ] Search for malformed URLs passed to `curlRequest()` — add a valid host
- [ ] Search for calls to public methods with wrong types — align them with the type hints
- [ ] Search for overlapping template placeholders — make the keys unique
- [ ] Run the test suite to verify compatibility
