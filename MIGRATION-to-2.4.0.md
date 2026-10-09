# Migration Guide: → 2.4.0

## Breaking Changes

### 1. `curlRequest` — signature changed from `string $url` to `string|array $url`

**Before**: `curlRequest(string $url, array $options = []): mixed` — accepted only a single URL string.

**After**: `curlRequest(string|array $url, array $options = []): mixed` — accepts a `string` for a single request or an `array` of URL strings for a parallel batch via `curl_multi`. In batch mode, options are shared across all URLs. The PHP signature changed from `string` to `string|array`.

```php
// BEFORE — string only
$result = NanoCore::curlRequest('https://api.example.com/data');

// AFTER — string still works (backward compatible)
$result = NanoCore::curlRequest('https://api.example.com/data');

// NEW — array for parallel batch
$results = NanoCore::curlRequest([
    'https://api.example.com/users',
    'https://api.example.com/posts',
]);
// $results = [body1, body2] — in input order
// With with_info: [['body'=>..., 'status'=>200], ['body'=>..., 'status'=>200]]
```

**Action**: No change needed if you pass a string — it works as before. Type checkers that enforce a strict `string` may report an error with the new union type — update the type declarations in your wrappers.

---

## New Features

### 2. Parallel batch with `curl_multi`

`curlRequest` now accepts an array of URLs. Requests are executed in parallel via `curl_multi` with a maximum of 10 concurrent handles. Excess URLs are automatically queued (when one request completes, the next one starts). Results are returned in an array in the same order as the input URLs.

```php
$urls = [
    'https://api.example.com/users',
    'https://api.example.com/posts',
    'https://api.example.com/comments',
];
$results = NanoCore::curlRequest($urls, ['with_info' => true]);
// $results[0] = ['body' => [...], 'status' => 200, 'content_type' => 'application/json', 'headers' => [...]]
// $results[1] = ['body' => [...], 'status' => 200, ...]
// $results[2] = ['body' => [...], 'status' => 200, ...]
```

### 3. Non-blocking per-request retry

Each request has up to 5 total attempts (initial + 4 retries). Linear backoff: 100ms, 200ms, 300ms, 400ms. The retry is non-blocking: in batch mode, if a request fails, its handle is removed from the multi handle, registered for retry, and the other requests keep processing. When the timer expires, the handle is re-initialized and re-added.

### 4. Graceful batch failures

In batch mode, if a URL fails after all attempts, the corresponding element in the results array is an `Exception` object. The other requests are not aborted.

```php
$results = NanoCore::curlRequest([
    'https://httpbin.org/get',
    'http://nonexistent.invalid',  // will fail
]);
// $results[0] = '...body...' (string)
// $results[1] = Exception('External request failed')
```

For single URLs (a single string), the behavior is unchanged: a failure throws an exception.

### 5. Empty array

`NanoCore::curlRequest([], $options)` returns `[]` immediately without making any HTTP requests.

---

## Migration Checklist

- [ ] No changes to consumer code required — `curlRequest` with `string` works as before
- [ ] If you use type declarations in `curlRequest` wrappers, update from `string` to `string|array`
- [ ] To use parallel batch, pass an array of URLs — results are in the same order as the input URLs
- [ ] Handle `Exception` in batch results if some URLs may fail
