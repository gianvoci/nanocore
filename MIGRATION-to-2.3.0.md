# Migration Guide: → 2.3.0

## Breaking Changes

### 1. `findBy()` / `findAll()` — new signature, returns `array<array>`

**Before**: `findBy(string $field, mixed $value, int $limit = 0): array` — returned an array of NanoORM instances. `findAll(array $conds, string $orderBy = '', int $limit = 0): array` — returned an array of NanoORM instances.

**After**: `findBy(string $where, array $params = [], ?int $limit = null): array` — returns `array<array>` (associative arrays). `findAll(string $where = '', array $params = [], string $orderBy = '', ?int $limit = null): array` — returns `array<array>`. Passing `0` for "no limit" no longer works — use `null`.

No field validation in the WHERE clause — the caller is responsible for SQL safety.

```php
// BEFORE — field/value, returned NanoORM instances
$users = $orm->findBy('status', 'active', 10);
echo $users[0]->name;  // object access

// AFTER — WHERE clause, returns associative arrays
$users = $orm->findBy('status = ?', ['active'], 10);
echo $users[0]['name'];  // array access
```

**Action**: Replace all `findBy()` and `findAll()` calls with the new signature. Change object access (`$row->field`) to array access (`$row['field']`).

### 2. `paginate()` — returns `array<array>` in the `data` field

**Before**: `$result['data']` contained an array of NanoORM instances. Signature: `paginate(int $page, int $perPage, array $conds = [], string $orderBy = '')`.

**After**: `$result['data']` contains an array of associative arrays. Signature: `paginate(int $page, int $perPage, string $where = '', array $params = [], string $orderBy = '')`.

```php
// BEFORE — NanoORM instances in data
$result = $orm->paginate(1, 25, ['status' => 'active'], 'name ASC');
$result['data'][0]->name;  // object access

// AFTER — associative arrays in data
$result = $orm->paginate(1, 25, 'status = ?', ['active'], 'name ASC');
$result['data'][0]['name'];  // array access
```

**Action**: Update `paginate()` calls to the new signature. Change object access on the elements of `$result['data']` to array access.

### 3. `hydrate()` → `fromArray()` — now public with field validation

**Before**: `hydrate()` was private, used internally by `findById()`.

**After**: `fromArray(array $row): self` is public. Validates fields against the schema — unknown fields throw `\InvalidArgumentException("Unknown field: {$key}")`.

```php
// AFTER — public method, validates fields
$user = (new NanoORM($pdo, 'users'))->fromArray(['name' => 'Test', 'email' => 'a@b.c']);
// unknown fields are NOT allowed:
// (new NanoORM($pdo, 'users'))->fromArray(['unknown' => 'x']);  // → \InvalidArgumentException
```

**Action**: Use `fromArray()` for manual hydration. For JOIN data with extra fields, use `fetchWithJoins()` instead.

### 4. `deleteWhere()` — new signature with arbitrary WHERE

**Before**: `deleteWhere(array $conds): int` — associative array of conditions.

**After**: `deleteWhere(string $where, array $params = []): int` — WHERE clause with params. An empty WHERE throws `\Exception` (safety guard).

```php
// BEFORE — associative array
$orm->deleteWhere(['status' => 'inactive']);

// AFTER — WHERE clause
$orm->deleteWhere('status = ?', ['inactive']);
$orm->deleteWhere('');  // → \Exception (safety: prevents accidental deletion of all rows)
```

To intentionally delete all rows: `deleteWhere('1=1')`.

**Action**: Replace `deleteWhere(['field' => 'value'])` with `deleteWhere('field = ?', ['value'])`. If you need to delete all rows, use `deleteWhere('1=1')`.

### 5. Middleware receives `$route` and `$method`

**Before**: The middleware callback signature was `function ($app, $params, $next)`.

**After**: The middleware callback receives two additional parameters: `function ($app, $params, $next, $route, $method)`.

```php
// BEFORE
$app->addMiddleware(function ($app, $params, $next) { ... });

// AFTER
$app->addMiddleware(function ($app, $params, $next, $route, $method) {
    // $route = '/api/auth/login'
    // $method = 'POST'
});
```

**Action**: Existing middleware with 3 parameters still works (PHP allows extra arguments). Update the signature if you want to use `$route`/`$method`.

### 6. `getBodyRequest()` — caching and Content-Type auto-detection

**Before**: Every call re-read `php://input`. Only JSON was supported.

**After**: The first call reads and caches in `$storage['__nc_body']`. Content-Type auto-detection: JSON, form-urlencoded, multipart/form-data. `$maxBytes` only takes effect on the first call. New parameter `$validateContentType` (second argument, default `false`): when `true`, requires Content-Type `application/json` and throws an exception if it does not match. Unknown Content-Types: attempts JSON decode, then falls back to raw string.

```php
// BEFORE — always JSON, no cache
$body = $app->getBodyRequest();

// AFTER — cached, auto-detect Content-Type
$body = $app->getBodyRequest();  // first call: reads + caches
$body = $app->getBodyRequest();  // subsequent calls: return cache
```

**Action**: If you relied on multiple `getBodyRequest()` calls with different `$maxBytes`, only the first call's limit is applied. If you relied on non-JSON bodies being rejected, they are now auto-parsed. If you need to enforce `application/json`, pass `true` as the second argument: `$app->getBodyRequest(null, true)`.

### 7. New method `require(string $key): mixed`

Fail-fast property access. Checks `$storage` and then virtual properties (`body`, `cli`). Throws `RuntimeException` for unknown keys.

```php
$pdo = $app->require('pdo');
$body = $app->require('body');   // virtual property
$cli = $app->require('cli');     // virtual property
$app->require('nonexistent');     // → RuntimeException
```

**Action**: Use `require()` instead of the `isset` + access pattern for required config values.

### 8. `execDetach()` — Windows fix

**Before**: On Windows, `execDetach()` could show popup windows or block.

**After**: Uses `start /B cmd /c "..."` with output redirected to a log — no popup, non-blocking.

**Action**: No code changes required. Behavioral fix only.

---

## New Protections

These do not break anything but change runtime behavior:

| Change | What happens |
|--------|-------------|
| `getBodyRequest()` cache | Prevents double reading of `php://input` |
| `require()` throws on missing key | `RuntimeException` instead of silent null |
| `deleteWhere('')` safety guard | Prevents accidental DELETE without WHERE |

---

## Migration Checklist

- [ ] Search for `findBy('field', 'value')` calls — replace with `findBy('field = ?', ['value'])` and change access from object to array
- [ ] Search for `findAll(['field' => 'value'])` calls — replace with `findAll('field = ?', ['value'])` and change access from object to array
- [ ] Search for `paginate()` calls with a conditions array — replace with the new WHERE + params signature
- [ ] Search for object access on `paginate()` results (`$row->field`) — change to array access (`$row['field']`)
- [ ] Search for uses of `hydrate()` — replace with `fromArray()` (public, throws `\InvalidArgumentException` for unknown fields)
- [ ] Search for `deleteWhere(['field' => 'value'])` calls — replace with `deleteWhere('field = ?', ['value'])`
- [ ] Verify that `deleteWhere('')` is not used to delete everything — use `deleteWhere('1=1')` if intentional
- [ ] Verify existing middleware — add `$route` and `$method` to the signature if needed
- [ ] Search for multiple `getBodyRequest()` calls with different `$maxBytes` — only the first one takes effect
- [ ] Verify that parsing non-JSON bodies does not cause problems — they are now auto-parsed
- [ ] Replace the `isset` + access pattern with `require()` for required config values
- [ ] Search for `findBy()`/`findAll()` calls that pass `0` or `false` as the limit — replace with `null` for no limit
- [ ] Run the test suite to verify compatibility
