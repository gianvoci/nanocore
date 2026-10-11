# Migration Guide: → 2.7.2

HTTP client release (all changes below shipped in v2.7.0; v2.7.1–v2.7.3 add no code changes). One breaking behavior change: `curlRequest` now throws on HTTP ≥ 400 responses (opt-out available). Public signatures are unchanged.

## Behavior Changes (aligned with specs)

### 1. `curlRequest` throws on HTTP ≥ 400 (breaking)

A final HTTP status ≥ 400 now produces one `\Exception` per failed request — thrown for a single URL, stored in the result slot for a batch (same slot semantics as transport failures). Exception `code` = HTTP status; message is `External request returned HTTP <status>` (+ first 200 chars of the body, line breaks stripped, when non-empty). Statuses < 400 (including 3xx) are successes, unchanged.

```php
// Before
$info = NanoCore::curlRequest($url, ['with_info' => true]);
if ($info['status'] >= 400) { /* handle error */ }
$body = $info['body'];

// After
try {
    $body = NanoCore::curlRequest($url);
} catch (\Exception $e) {
    $status = $e->getCode(); // HTTP status, e.g. 429
}
```

**Action**: wrap calls in `try/catch (\Exception)` and read `$e->getCode()` for the status. If you need the old behavior (parse `Retry-After`, read structured error bodies, treat 3xx as errors yourself), pass `'ignore_http_errors' => true` — body/status are returned as before. Note: `with_info` does **not** suppress the throw.

### 2. Transport failure messages carry the cURL code

`External request failed` is now `External request failed (cURL <code>[: reason])`. Exception code stays 0 (no invented HTTP status). Transport failures are now also logged to `nanocore.log`.

**Action**: none if you catch `\Exception`. If you string-match the message: the `External request failed` prefix is preserved and cURL 28 failures still contain `timed out` (cURL's own wording).

### 3. Batch error slots now include HTTP errors

In batch mode, slots for HTTP ≥ 400 responses (without `ignore_http_errors`) are `\Exception` objects, exactly like transport failures.

**Action**: existing `instanceof \Exception` checks now cover both; make sure result consumers handle both kinds before accessing data.

### 4. Response bodies are masked in `nanocore.log`

Response bodies that decode to a JSON array have sensitive keys (`password`, `token`, `secret`, `key`, `auth`, `credential`) masked as `***` before the 500-char truncation. Log lines are stripped of CR, LF, VT, FF, U+2028, U+2029.

**Action**: none — but if you parsed tokens/secrets out of `nanocore.log`, that no longer works.

---

## New Features

### 5. `json` option — one-shot JSON body

```php
$body = NanoCore::curlRequest($url, ['method' => 'POST', 'json' => $data]);
```

`json_encode` runs once before any handle exists; `Content-Type: application/json` is appended unless you pass your own header (case-insensitive). `json` + `params`, or `json` with `GET`/`HEAD`, throw `\InvalidArgumentException` before any network I/O. Manual `json_encode` + `Content-Type` header is no longer needed.

### 6. `ignore_http_errors` option

```php
$info = NanoCore::curlRequest($url, ['with_info' => true, 'ignore_http_errors' => true]);
// 4xx/5xx returned as normal responses (pre-2.7.0 behavior)
```

### 7. Internal hardening (transparent)

- `json_encode` failure on the `json` option throws `\Exception("JSON body encoding failed", 500)` before any request is attempted.
- Non-array `json` bodies are logged as `[json body]` (never the raw value).
- A batch slot that would be skipped is filled with a defensive exception (ordering preserved).

---

## Migration Checklist

- [ ] Wrap `curlRequest` calls in `try/catch (\Exception)`; use `$e->getCode()` as the HTTP status
- [ ] Add `'ignore_http_errors' => true` where you must inspect 4xx/5xx bodies or `Retry-After` headers
- [ ] In batch mode, handle `\Exception` slots for both transport and HTTP errors
- [ ] Replace manual `json_encode` + `Content-Type` with the `json` option
- [ ] Remove consumer-side URL validation/pinning wrappers around `curlRequest` — it validates and pins internally and discards caller `CURLOPT_RESOLVE` (keep `validateUrlNotRestricted()` only for URLs you do not pass to `curlRequest`)